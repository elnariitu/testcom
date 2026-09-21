<?php
declare(strict_types=1);

require_once __DIR__ . '/maten_rate.php';
require_once __DIR__ . '/notifications.php';

/**
 * Universal partner API (api/v2) — replaces the single-partner-specific v1
 * gateway (includes/api_gateway.php, now retired, see audit-report.md).
 * Any approved partner gets its own public/secret key
 * pair and its own escrow ledger row; there is no partner-specific code.
 *
 * Design constraints (see the project spec this implements):
 *  - MATEN only ever leaves a user's wallet through this API when the wallet
 *    owner has proven it: /v2/deposits mints a one-time 6-digit code and
 *    delivers it ONLY to the wallet owner's own Maten notification inbox —
 *    the calling partner never sees the plaintext code (only code_length).
 *    The user is the one who reads the code and hands it to the partner's
 *    checkout UI; the partner then proves it really talked to the real
 *    owner by redeeming that same code via /v2/transfers-out. A partner can
 *    never complete a transfer-out without the user's active cooperation.
 *  - /v2/transfers-out redeems that code and moves the fixed amount into
 *    the CALLING PARTNER's own balance (self-service key: straight to the
 *    partner's own Maten wallet; business partner key: into their
 *    system_escrow_accounts row). The destination is never a request
 *    parameter — it's resolved from the authenticated api key's partner_id.
 *  - /v2/payouts is the reverse leg — it sends MATEN OUT of the calling
 *    partner's own balance to any address. The SOURCE is likewise never a
 *    request parameter — always the authenticated key's own wallet/escrow.
 *  - Escrow balances can only be withdrawn by an admin (2FA), never through
 *    this public API. That admin flow is a separate, later piece of work —
 *    this file only accumulates escrow balance, it never drains it (aside
 *    from /v2/payouts, which only ever drains a partner's OWN escrow).
 */

const API_V2_CODE_LENGTH = 6;
const API_V2_CODE_TTL_MINUTES = 5;
const API_V2_CODE_MAX_ATTEMPTS = 3;
const API_V2_CODE_MAX_PER_WINDOW = 5;
const API_V2_CODE_WINDOW_MINUTES = 15;

const API_V2_TIMESTAMP_WINDOW_SECONDS = 300;
const API_V2_RATE_LIMIT_MAX = 100;
const API_V2_RATE_LIMIT_WINDOW_SECONDS = 60;

const API_V2_VALID_SCOPES = ['deposit:create', 'transfer:create', 'payout:create', 'transaction:read', 'webhook:manage'];

/**
 * Master key for encrypting webhook signing secrets at rest. Unlike the
 * partner's own secret_key (a login-style credential we only ever need to
 * *verify*, so we store its bcrypt hash), a webhook secret is used by
 * *this server* to sign every outgoing delivery — it must stay recoverable,
 * so it's encrypted (AES-256-GCM), not hashed.
 */
function apiV2WebhookEncKey(): string {
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $path = __DIR__ . '/webhook_enc.local.php';
    if (!is_file($path)) {
        throw new RuntimeException('includes/webhook_enc.local.php is missing — webhook secrets cannot be encrypted/decrypted without it.');
    }
    $config = require $path;
    $decoded = base64_decode((string) ($config['key_base64'] ?? ''), true);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new RuntimeException('webhook_enc.local.php key_base64 must decode to exactly 32 bytes.');
    }
    $key = $decoded;
    return $key;
}

function apiV2EncryptWebhookSecret(string $plaintext): string {
    $key = apiV2WebhookEncKey();
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Webhook secret encryption failed.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function apiV2DecryptWebhookSecret(string $stored): ?string {
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', apiV2WebhookEncKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function apiV2EnsureTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS partners (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            domain VARCHAR(255) NOT NULL,
            contact_email VARCHAR(255) NOT NULL,
            business_description TEXT NULL,
            status ENUM('pending_review','approved','suspended','rejected') NOT NULL DEFAULT 'pending_review',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL,
            approved_by INT UNSIGNED NULL,
            UNIQUE KEY uniq_partners_public_id (public_id),
            UNIQUE KEY uniq_partners_domain (domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $partnerUserColStmt = $pdo->query("SHOW COLUMNS FROM partners LIKE 'user_id'");
    if (!$partnerUserColStmt->fetch()) {
        $pdo->exec("ALTER TABLE partners ADD COLUMN user_id INT NULL AFTER id");
        try {
            $pdo->exec("CREATE UNIQUE INDEX uniq_partners_user_id ON partners (user_id)");
        } catch (Throwable $e) {
            // Index may already exist on some hosts after manual edits.
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            partner_id INT UNSIGNED NOT NULL,
            public_key VARCHAR(64) NOT NULL,
            secret_key_hash VARCHAR(255) NOT NULL,
            environment ENUM('test','live') NOT NULL DEFAULT 'test',
            scopes JSON NOT NULL,
            ip_whitelist JSON NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at TIMESTAMP NULL,
            last_used_at TIMESTAMP NULL,
            UNIQUE KEY uniq_api_keys_public_key (public_key),
            INDEX idx_api_keys_partner (partner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webhooks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL,
            partner_id INT UNSIGNED NOT NULL,
            url VARCHAR(500) NOT NULL,
            hmac_secret_hash VARCHAR(255) NOT NULL,
            events JSON NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_webhooks_public_id (public_id),
            INDEX idx_webhooks_partner (partner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_escrow_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            partner_id INT UNSIGNED NOT NULL,
            balance DECIMAL(24,8) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'MATEN',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_escrow_partner_currency (partner_id, currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_v2_deposits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL,
            partner_id INT UNSIGNED NOT NULL,
            api_key_id INT UNSIGNED NOT NULL,
            user_id INT NOT NULL,
            wallet_address VARCHAR(32) NOT NULL,
            amount DECIMAL(24,8) NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'MATEN',
            code_hash CHAR(64) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            request_idempotency_key VARCHAR(80) NOT NULL,
            confirmed_at TIMESTAMP NULL,
            canceled_at TIMESTAMP NULL,
            cancel_reason VARCHAR(80) NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_v2_deposits_public_id (public_id),
            UNIQUE KEY uniq_v2_deposits_key_idem (api_key_id, request_idempotency_key),
            INDEX idx_v2_deposits_address_created (partner_id, wallet_address, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    apiV2EnsureColumn($pdo, 'api_v2_deposits', 'canceled_at', "ALTER TABLE api_v2_deposits ADD COLUMN canceled_at TIMESTAMP NULL AFTER confirmed_at");
    apiV2EnsureColumn($pdo, 'api_v2_deposits', 'cancel_reason', "ALTER TABLE api_v2_deposits ADD COLUMN cancel_reason VARCHAR(80) NULL AFTER canceled_at");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_transactions_v2 (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL,
            partner_id INT UNSIGNED NOT NULL,
            api_key_id INT UNSIGNED NOT NULL,
            deposit_id INT UNSIGNED NULL,
            idempotency_key VARCHAR(80) NOT NULL,
            type ENUM('deposit_to_partner','escrow_transfer_out','payout') NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(24,8) NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'MATEN',
            status ENUM('pending','succeeded','failed','reversed') NOT NULL DEFAULT 'succeeded',
            escrow_account_id INT UNSIGNED NULL,
            is_simulated TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_v2_tx_public_id (public_id),
            UNIQUE KEY uniq_v2_tx_key_idem (api_key_id, idempotency_key),
            INDEX idx_v2_tx_partner_created (partner_id, created_at),
            INDEX idx_v2_tx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $txTypeColStmt = $pdo->query("SHOW COLUMNS FROM api_transactions_v2 LIKE 'type'");
    $txTypeCol = $txTypeColStmt->fetch(PDO::FETCH_ASSOC);
    if ($txTypeCol && strpos((string) $txTypeCol['Type'], "'payout'") === false) {
        $pdo->exec("ALTER TABLE api_transactions_v2 MODIFY COLUMN type ENUM('deposit_to_partner','escrow_transfer_out','payout') NOT NULL");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_v2_nonces (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            api_key_id INT UNSIGNED NOT NULL,
            nonce VARCHAR(64) NOT NULL,
            endpoint VARCHAR(80) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_v2_nonces_key_nonce (api_key_id, nonce),
            INDEX idx_v2_nonces_key_created (api_key_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_request_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            partner_id INT UNSIGNED NULL,
            api_key_id INT UNSIGNED NULL,
            endpoint VARCHAR(120) NOT NULL,
            method VARCHAR(10) NOT NULL,
            ip_address VARCHAR(64) NULL,
            status_code SMALLINT UNSIGNED NOT NULL,
            error_code VARCHAR(60) NULL,
            duration_ms INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_api_request_logs_partner_created (partner_id, created_at),
            INDEX idx_api_request_logs_key_created (api_key_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webhook_deliveries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            webhook_id INT UNSIGNED NOT NULL,
            event_type VARCHAR(60) NOT NULL,
            payload LONGTEXT NOT NULL,
            status ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_status_code SMALLINT NULL,
            last_error VARCHAR(255) NULL,
            next_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            delivered_at TIMESTAMP NULL,
            INDEX idx_webhook_deliveries_due (status, next_attempt_at),
            INDEX idx_webhook_deliveries_webhook (webhook_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function apiV2EnsureColumn(PDO $pdo, string $table, string $column, string $alterSql): void {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return;
    }
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$dbName, $table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec($alterSql);
    }
    $cache[$key] = true;
}

/** Backoff schedule (minutes) after each failed attempt, indexed by attempt number. */
const WEBHOOK_RETRY_BACKOFF_MINUTES = [1, 5, 15, 60, 240, 720];
const WEBHOOK_MAX_ATTEMPTS = 6;
const WEBHOOK_DELIVERY_TIMEOUT_SECONDS = 5;

/**
 * Fires a webhook event to every active webhook this partner has registered
 * for $eventType. Delivery is best-effort and must never block or fail the
 * API call that triggered it — the first attempt happens inline (so a
 * healthy partner endpoint gets near-instant delivery), and anything that
 * doesn't succeed immediately is queued in webhook_deliveries for the retry
 * sweep (see apiV2ProcessDueWebhookDeliveries) to pick up later.
 */
function apiV2FireWebhookEvent(PDO $pdo, int $partnerId, string $eventType, array $payload): void {
    try {
        $stmt = $pdo->prepare("SELECT * FROM webhooks WHERE partner_id = ? AND is_active = 1");
        $stmt->execute([$partnerId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $webhook) {
            $events = json_decode((string) $webhook['events'], true) ?: [];
            if (!in_array($eventType, $events, true) && !in_array('*', $events, true)) {
                continue;
            }

            $body = json_encode(['event' => $eventType, 'data' => $payload, 'timestamp' => time()], JSON_UNESCAPED_UNICODE);
            $insertStmt = $pdo->prepare("INSERT INTO webhook_deliveries (webhook_id, event_type, payload) VALUES (?, ?, ?)");
            $insertStmt->execute([(int) $webhook['id'], $eventType, $body]);
            $deliveryId = (int) $pdo->lastInsertId();

            apiV2AttemptWebhookDelivery($pdo, $deliveryId, (int) $webhook['id'], $webhook['url'], $body);
        }
    } catch (Throwable $e) {
        error_log('apiV2FireWebhookEvent failed: ' . $e->getMessage());
    }
}

function apiV2AttemptWebhookDelivery(PDO $pdo, int $deliveryId, int $webhookId, string $url, string $body): void {
    $webhookStmt = $pdo->prepare("SELECT * FROM webhooks WHERE id = ? LIMIT 1");
    $webhookStmt->execute([$webhookId]);
    $webhook = $webhookStmt->fetch(PDO::FETCH_ASSOC);
    $secret = $webhook ? apiV2DecryptWebhookSecret((string) $webhook['hmac_secret_hash']) : null;
    if ($secret === null) {
        apiV2MarkDeliveryResult($pdo, $deliveryId, 0, false, 'no_signing_secret');
        return;
    }

    $signature = hash_hmac('sha256', $body, $secret);

    if (!function_exists('curl_init')) {
        apiV2MarkDeliveryResult($pdo, $deliveryId, 0, false, 'curl_missing');
        return;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => WEBHOOK_DELIVERY_TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Signature: ' . $signature],
    ]);
    curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $ok = $status >= 200 && $status < 300;
    apiV2MarkDeliveryResult($pdo, $deliveryId, $status, $ok, $ok ? null : ($curlError !== '' ? $curlError : 'http_' . $status));
}

function apiV2MarkDeliveryResult(PDO $pdo, int $deliveryId, int $statusCode, bool $ok, ?string $error): void {
    if ($ok) {
        $pdo->prepare("UPDATE webhook_deliveries SET status = 'delivered', last_status_code = ?, delivered_at = NOW(), attempts = attempts + 1 WHERE id = ?")
            ->execute([$statusCode, $deliveryId]);
        return;
    }

    $attemptsStmt = $pdo->prepare("SELECT attempts FROM webhook_deliveries WHERE id = ?");
    $attemptsStmt->execute([$deliveryId]);
    $attempts = (int) $attemptsStmt->fetchColumn() + 1;

    if ($attempts >= WEBHOOK_MAX_ATTEMPTS) {
        $pdo->prepare("UPDATE webhook_deliveries SET status = 'failed', attempts = ?, last_status_code = ?, last_error = ? WHERE id = ?")
            ->execute([$attempts, $statusCode ?: null, $error, $deliveryId]);
        return;
    }

    $backoffMinutes = WEBHOOK_RETRY_BACKOFF_MINUTES[$attempts - 1] ?? end(WEBHOOK_RETRY_BACKOFF_MINUTES);
    $nextAttempt = gmdate('Y-m-d H:i:s', time() + $backoffMinutes * 60);
    $pdo->prepare("UPDATE webhook_deliveries SET attempts = ?, last_status_code = ?, last_error = ?, next_attempt_at = ? WHERE id = ?")
        ->execute([$attempts, $statusCode ?: null, $error, $nextAttempt, $deliveryId]);
}

/**
 * Processes up to $limit due retries. Meant to be triggered either by a
 * real cron hitting webhooks_retry_worker.php, or opportunistically from
 * api_v2.php on a small percentage of live requests (see api_v2.php) so
 * retries still happen on hosts without cron access.
 */
function apiV2ProcessDueWebhookDeliveries(PDO $pdo, int $limit = 5): int {
    $stmt = $pdo->prepare("
        SELECT d.id, d.webhook_id, d.payload, w.url
        FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id
        WHERE d.status = 'pending' AND d.next_attempt_at <= NOW() AND w.is_active = 1
        ORDER BY d.next_attempt_at ASC LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $due = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($due as $row) {
        apiV2AttemptWebhookDelivery($pdo, (int) $row['id'], (int) $row['webhook_id'], $row['url'], (string) $row['payload']);
    }
    return count($due);
}

function apiV2PublicId(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(16));
}

function apiV2ClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 64) : '';
}

function apiV2LogRequest(PDO $pdo, ?int $partnerId, ?int $apiKeyId, string $endpoint, string $method, int $status, ?string $errorCode, float $startedAt): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_request_logs (partner_id, api_key_id, endpoint, method, ip_address, status_code, error_code, duration_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $partnerId, $apiKeyId, $endpoint, $method, apiV2ClientIp(), $status, $errorCode,
            (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    } catch (Throwable $e) {
        error_log('apiV2LogRequest failed: ' . $e->getMessage());
    }
}

/**
 * Creates a new partner in pending_review status. Approval (and therefore
 * key issuance) is a separate, admin-only step — not part of this file.
 */
function apiV2RegisterPartner(PDO $pdo, string $companyName, string $domain, string $contactEmail, string $businessDescription): array {
    $companyName = trim($companyName);
    $domain = strtolower(trim($domain));
    $contactEmail = trim($contactEmail);

    if ($companyName === '' || $domain === '' || $contactEmail === '') {
        return ['ok' => false, 'error' => 'missing_fields'];
    }
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
        return ['ok' => false, 'error' => 'invalid_domain'];
    }

    $publicId = apiV2PublicId('ptn');
    try {
        // Auto-approved, no admin review step — matches the self-service
        // model used everywhere else in this API. A partner registered here
        // can only ever move money a real Maten user actively authorizes
        // (see the deposit-code notification flow), so instant approval
        // doesn't weaken money-movement safety; it only removes a manual
        // gate that had nothing left protecting.
        $stmt = $pdo->prepare("
            INSERT INTO partners (public_id, company_name, domain, contact_email, business_description, status, approved_at)
            VALUES (?, ?, ?, ?, ?, 'approved', NOW())
        ");
        $stmt->execute([$publicId, $companyName, $domain, $contactEmail, $businessDescription]);
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'domain_already_registered'];
    }

    $partnerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT IGNORE INTO system_escrow_accounts (partner_id, currency) VALUES (?, 'MATEN')")->execute([$partnerId]);

    return ['ok' => true, 'id' => $partnerId, 'public_id' => $publicId, 'status' => 'approved'];
}

/**
 * Self-service partner: every logged-in Maten user can integrate "Maten
 * Pay" on their own site under their own account — no admin approval gate.
 * This is lower-risk than the B2B admin-approved flow because a
 * self-service key can only ever move MONEY THE USER ALREADY OWNS (their
 * own wallet is the deposit source, and escrow release below only ever
 * pays back into that same user's own balance_maten — never anywhere
 * else). Auto-creates + auto-approves the partner row on first visit.
 */
function apiV2GetOrCreateSelfPartner(PDO $pdo, int $userId, string $username, string $email): array {
    apiV2EnsureTables($pdo);

    $stmt = $pdo->prepare("SELECT * FROM partners WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($partner) {
        return $partner;
    }

    $publicId = apiV2PublicId('ptn');
    $domain = 'user-' . $userId . '.maten.pro';
    $companyName = ($username !== '' ? $username : 'Maten user #' . $userId) . ' (self-service)';

    $insert = $pdo->prepare("
        INSERT INTO partners (user_id, public_id, company_name, domain, contact_email, business_description, status, approved_at)
        VALUES (?, ?, ?, ?, ?, 'Self-service Maten Pay integration.', 'approved', NOW())
    ");
    $insert->execute([$userId, $publicId, $companyName, $domain, $email]);
    $partnerId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT IGNORE INTO system_escrow_accounts (partner_id, currency) VALUES (?, 'MATEN')")->execute([$partnerId]);

    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? LIMIT 1");
    $stmt->execute([$partnerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Lets a self-service partner fill in their own real company/site details
 * (replacing the auto-generated "username (self-service)" / "user-N.maten.pro"
 * placeholders) — no admin approval involved, same as the rest of the
 * self-service model. The row is already status='approved'; this only ever
 * updates display/contact fields, never status or user_id.
 */
function apiV2UpdateSelfPartnerProfile(PDO $pdo, int $partnerId, string $companyName, string $domain, string $contactEmail, string $businessDescription): array {
    $companyName = trim($companyName);
    $domain = strtolower(trim($domain));
    $contactEmail = trim($contactEmail);
    $businessDescription = trim($businessDescription);

    if ($companyName === '' || $domain === '' || $contactEmail === '') {
        return ['ok' => false, 'error' => 'missing_fields'];
    }
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
        return ['ok' => false, 'error' => 'invalid_domain'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE partners SET company_name = ?, domain = ?, contact_email = ?, business_description = ? WHERE id = ?");
        $stmt->execute([$companyName, $domain, $contactEmail, $businessDescription, $partnerId]);
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'domain_already_registered'];
    }

    return ['ok' => true];
}

/**
 * Releases escrowed MATEN back into the OWNING user's own wallet balance.
 * Safe without 2FA/admin gating because the destination is hardcoded to
 * the same user_id the escrow row belongs to — this can never pay out to
 * anyone else's account.
 */
function apiV2SelfWithdrawEscrow(PDO $pdo, int $partnerId, int $userId, string $amount): array {
    matenRequireBcmath();

    if (!preg_match('/^\d+(\.\d{1,8})?$/', $amount) || bccomp($amount, '0', 8) <= 0) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, balance FROM system_escrow_accounts WHERE partner_id = ? AND currency = 'MATEN' FOR UPDATE");
        $stmt->execute([$partnerId]);
        $escrow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$escrow) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'no_escrow_account'];
        }
        if (bccomp((string) $escrow['balance'], $amount, 8) < 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'insufficient_escrow_balance'];
        }

        $pdo->prepare("UPDATE system_escrow_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, (int) $escrow['id']]);
        $pdo->prepare("UPDATE users SET balance_maten = balance_maten + ? WHERE id = ?")->execute([$amount, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('apiV2SelfWithdrawEscrow failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }

    return ['ok' => true];
}

function apiV2ApprovePartner(PDO $pdo, int $partnerId, int $adminUserId): bool {
    $stmt = $pdo->prepare("UPDATE partners SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ? AND status = 'pending_review'");
    $stmt->execute([$adminUserId, $partnerId]);
    if ($stmt->rowCount() !== 1) {
        return false;
    }
    $escrowStmt = $pdo->prepare("INSERT IGNORE INTO system_escrow_accounts (partner_id, currency) VALUES (?, 'MATEN')");
    $escrowStmt->execute([$partnerId]);
    return true;
}

/**
 * Issues a new key pair for an approved partner. The plaintext secret is
 * returned ONCE here and never again — only its hash is persisted.
 */
function apiV2IssueApiKey(PDO $pdo, int $partnerId, string $environment, array $scopes): array {
    if (!in_array($environment, ['test', 'live'], true)) {
        return ['ok' => false, 'error' => 'invalid_environment'];
    }
    $scopes = array_values(array_intersect($scopes, API_V2_VALID_SCOPES));
    if (empty($scopes)) {
        return ['ok' => false, 'error' => 'invalid_scopes'];
    }

    $partnerStmt = $pdo->prepare("SELECT status FROM partners WHERE id = ? LIMIT 1");
    $partnerStmt->execute([$partnerId]);
    $status = $partnerStmt->fetchColumn();
    if ($status !== 'approved') {
        return ['ok' => false, 'error' => 'partner_not_approved'];
    }

    $publicKey = 'pk_' . $environment . '_' . bin2hex(random_bytes(16));
    $secretKey = 'sk_' . $environment . '_' . bin2hex(random_bytes(24));
    $secretHash = password_hash($secretKey, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO api_keys (partner_id, public_key, secret_key_hash, environment, scopes, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$partnerId, $publicKey, $secretHash, $environment, json_encode($scopes)]);

    return ['ok' => true, 'public_key' => $publicKey, 'secret_key' => $secretKey, 'environment' => $environment, 'scopes' => $scopes];
}

function apiV2RevokeApiKey(PDO $pdo, string $publicKey): bool {
    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0, revoked_at = NOW() WHERE public_key = ? AND is_active = 1");
    $stmt->execute([$publicKey]);
    return $stmt->rowCount() === 1;
}

function apiV2EnsureEscrowWithdrawalsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_escrow_withdrawals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            partner_id INT UNSIGNED NOT NULL,
            admin_user_id INT NOT NULL,
            amount DECIMAL(24,8) NOT NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_escrow_withdrawals_partner (partner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Admin-only escrow withdrawal — the ONLY way escrow balance ever
 * decreases. Never exposed through the public /api/v2/* surface; the
 * caller (admin_partners.php) is responsible for the 2FA check before
 * calling this. Purely bookkeeping here — moving the actual off-chain
 * settlement (bank transfer, on-chain payout, etc.) to the partner is a
 * manual step outside this system; this just records that it happened.
 */
function apiV2AdminWithdrawEscrow(PDO $pdo, int $partnerId, string $amount, int $adminUserId, string $note): array {
    matenRequireBcmath();
    apiV2EnsureEscrowWithdrawalsTable($pdo);

    if (!preg_match('/^\d+(\.\d{1,8})?$/', $amount) || bccomp($amount, '0', 8) <= 0) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, balance FROM system_escrow_accounts WHERE partner_id = ? AND currency = 'MATEN' FOR UPDATE");
        $stmt->execute([$partnerId]);
        $escrow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$escrow) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'no_escrow_account'];
        }
        if (bccomp((string) $escrow['balance'], $amount, 8) < 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'insufficient_escrow_balance'];
        }

        $pdo->prepare("UPDATE system_escrow_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, (int) $escrow['id']]);
        $pdo->prepare("INSERT INTO admin_escrow_withdrawals (partner_id, admin_user_id, amount, note) VALUES (?, ?, ?, ?)")
            ->execute([$partnerId, $adminUserId, $amount, $note !== '' ? $note : null]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('apiV2AdminWithdrawEscrow failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }

    return ['ok' => true];
}

/**
 * Full request authentication: X-Api-Key (public key) + Authorization:
 * Bearer <secret key> + HMAC X-Signature + timestamp/nonce replay guard +
 * rate limit + IP whitelist + scope check.
 *
 * Returns ['ok'=>bool,'status'=>int,'error'=>?string,'key'=>?array,'partner'=>?array].
 */
function apiV2AuthenticateRequest(PDO $pdo, string $method, string $path, string $rawBody, string $requiredScope): array {
    $publicKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($authHeader === '' && function_exists('getallheaders')) {
        // Apache/PHP commonly drops the Authorization header from $_SERVER
        // unless the host is specially configured — getallheaders() still
        // has it because it reads straight from the raw request.
        foreach (getallheaders() as $headerName => $headerValue) {
            if (strcasecmp($headerName, 'Authorization') === 0) {
                $authHeader = (string) $headerValue;
                break;
            }
        }
    }
    $timestamp = (string) ($_SERVER['HTTP_X_TIMESTAMP'] ?? '');
    $nonce = (string) ($_SERVER['HTTP_X_NONCE'] ?? '');
    $signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');

    if ($publicKey === '' || $authHeader === '' || $timestamp === '' || $nonce === '' || $signature === '') {
        return ['ok' => false, 'status' => 401, 'error' => 'missing_auth_headers'];
    }
    if (strpos($authHeader, 'Bearer ') !== 0) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_authorization_header'];
    }
    $secretKey = trim(substr($authHeader, 7));
    if (!ctype_digit($timestamp)) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_timestamp'];
    }
    if (strlen($nonce) > 64 || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $nonce)) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_nonce'];
    }

    $stmt = $pdo->prepare("
        SELECT ak.*, p.status AS partner_status, p.company_name, p.domain, p.id AS partner_id, p.user_id AS partner_user_id
        FROM api_keys ak JOIN partners p ON p.id = ak.partner_id
        WHERE ak.public_key = ? LIMIT 1
    ");
    $stmt->execute([$publicKey]);
    $key = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$key || (int) $key['is_active'] !== 1) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_api_key'];
    }
    if ($key['partner_status'] !== 'approved') {
        return ['ok' => false, 'status' => 403, 'error' => 'partner_not_approved'];
    }
    if (strpos($secretKey, 'sk_' . $key['environment'] . '_') !== 0) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_credentials'];
    }
    if (!password_verify($secretKey, (string) $key['secret_key_hash'])) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_credentials'];
    }

    if (abs(time() - (int) $timestamp) > API_V2_TIMESTAMP_WINDOW_SECONDS) {
        return ['ok' => false, 'status' => 401, 'error' => 'stale_timestamp'];
    }

    $expectedSignature = hash_hmac('sha256', $method . "\n" . $path . "\n" . $rawBody . "\n" . $timestamp . "\n" . $nonce, $secretKey);
    if (!hash_equals($expectedSignature, strtolower($signature))) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_signature'];
    }

    $keyId = (int) $key['id'];

    if (!empty($key['ip_whitelist'])) {
        $allowedIps = json_decode((string) $key['ip_whitelist'], true) ?: [];
        if (!empty($allowedIps) && !in_array(apiV2ClientIp(), $allowedIps, true)) {
            return ['ok' => false, 'status' => 403, 'error' => 'ip_not_allowed'];
        }
    }

    $scopes = json_decode((string) $key['scopes'], true) ?: [];
    if (!in_array($requiredScope, $scopes, true)) {
        return ['ok' => false, 'status' => 403, 'error' => 'insufficient_scope'];
    }

    $rateStmt = $pdo->prepare("SELECT COUNT(*) FROM api_v2_nonces WHERE api_key_id = ? AND created_at >= (NOW() - INTERVAL " . API_V2_RATE_LIMIT_WINDOW_SECONDS . " SECOND)");
    $rateStmt->execute([$keyId]);
    if ((int) $rateStmt->fetchColumn() >= API_V2_RATE_LIMIT_MAX) {
        return ['ok' => false, 'status' => 429, 'error' => 'rate_limited'];
    }

    try {
        $nonceStmt = $pdo->prepare("INSERT INTO api_v2_nonces (api_key_id, nonce, endpoint) VALUES (?, ?, ?)");
        $nonceStmt->execute([$keyId, $nonce, $path]);
    } catch (PDOException $e) {
        return ['ok' => false, 'status' => 409, 'error' => 'replayed_request'];
    }

    // No Redis on this host, so the nonce store is a plain MySQL table —
    // this purge keeps it behaving like a 300s TTL cache (a nonce older than
    // the timestamp window can never matter again, since any request that
    // old already fails the stale_timestamp check above) instead of growing
    // forever.
    $pdo->prepare("DELETE FROM api_v2_nonces WHERE api_key_id = ? AND created_at < (NOW() - INTERVAL " . API_V2_TIMESTAMP_WINDOW_SECONDS . " SECOND)")->execute([$keyId]);

    $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$keyId]);

    return ['ok' => true, 'status' => 200, 'error' => null, 'key' => $key, 'partner' => ['id' => (int) $key['partner_id'], 'company_name' => $key['company_name'], 'domain' => $key['domain'], 'user_id' => $key['partner_user_id'] !== null ? (int) $key['partner_user_id'] : null]];
}

/**
 * POST /v2/deposits — initiates a deposit: mints a one-time code and
 * delivers it to the wallet owner's own Maten notification inbox. No money
 * moves here. $input: wallet_address, amount, idempotency_key. The response
 * to the CALLER never contains the code itself — only code_length — so a
 * partner has no way to redeem /v2/transfers-out without the real wallet
 * owner reading their own notification and handing the code over.
 */
/**
 * Resolves the MATEN amount for a deposit/payout call. A partner can pass
 * either `amount` directly (in MATEN) or `fiat_amount` + `fiat_currency`
 * (e.g. 200 KZT), which is converted through the cross-rate-consistent
 * matenFromFiat() — this is what lets a Maten Pay integration collect a
 * fiat amount from their own checkout and have it auto-resolve to the
 * right MATEN amount (e.g. 200 KZT at 5 KZT/MATEN -> 40 MATEN).
 */
function apiV2ResolveMatenAmount(PDO $pdo, array $input): array {
    matenRequireBcmath();
    $amount = trim((string) ($input['amount'] ?? ''));
    $fiatAmount = trim((string) ($input['fiat_amount'] ?? ''));
    $fiatCurrency = strtoupper(trim((string) ($input['fiat_currency'] ?? '')));

    if ($amount !== '') {
        if (!preg_match('/^\d+(\.\d{1,8})?$/', $amount) || bccomp($amount, '0', 8) <= 0) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }
        return ['ok' => true, 'amount' => $amount, 'rate_used' => null];
    }

    if ($fiatAmount !== '' && $fiatCurrency !== '') {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $fiatAmount) || bccomp($fiatAmount, '0', 2) <= 0) {
            return ['ok' => false, 'error' => 'invalid_fiat_amount'];
        }
        if (!preg_match('/^[A-Z]{3}$/', $fiatCurrency)) {
            return ['ok' => false, 'error' => 'invalid_fiat_currency'];
        }
        $rate = matenRate($pdo, $fiatCurrency);
        $matenAmount = matenFromFiat($pdo, $fiatAmount, $fiatCurrency);
        if (bccomp($matenAmount, '0', 8) <= 0) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }
        return ['ok' => true, 'amount' => $matenAmount, 'rate_used' => $rate];
    }

    return ['ok' => false, 'error' => 'missing_amount'];
}

function apiV2CreateDeposit(PDO $pdo, array $key, array $partner, array $input): array {
    matenRequireBcmath();

    $keyId = (int) $key['id'];
    $partnerId = (int) $partner['id'];
    $address = trim((string) ($input['wallet_address'] ?? ''));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

    if ($address === '' || $idempotencyKey === '') {
        return ['status' => 422, 'error_code' => 'missing_fields', 'body' => ['success' => false, 'error_code' => 'missing_fields', 'message' => 'wallet_address and idempotency_key are required.']];
    }
    if (strlen($idempotencyKey) > 80) {
        return ['status' => 422, 'error_code' => 'invalid_idempotency_key', 'body' => ['success' => false, 'error_code' => 'invalid_idempotency_key', 'message' => 'idempotency_key is too long.']];
    }
    if (!preg_match('/^[a-z0-9]{16}$/', $address)) {
        return ['status' => 422, 'error_code' => 'invalid_address', 'body' => ['success' => false, 'error_code' => 'invalid_address', 'message' => 'wallet_address is not a valid MATEN wallet address.']];
    }

    $amountResolution = apiV2ResolveMatenAmount($pdo, $input);
    if (!$amountResolution['ok']) {
        return ['status' => 422, 'error_code' => $amountResolution['error'], 'body' => ['success' => false, 'error_code' => $amountResolution['error'], 'message' => 'Provide either amount (MATEN, up to 8 decimals) or fiat_amount + fiat_currency (KZT/RUB/USD).']];
    }
    $amount = $amountResolution['amount'];
    $rateUsed = $amountResolution['rate_used'];

    $idemStmt = $pdo->prepare("SELECT public_id, amount, expires_at FROM api_v2_deposits WHERE api_key_id = ? AND request_idempotency_key = ? LIMIT 1");
    $idemStmt->execute([$keyId, $idempotencyKey]);
    $existing = $idemStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $expiresAtTs = strtotime((string) $existing['expires_at'] . ' UTC');
        $expired = $expiresAtTs < time();
        return ['status' => 200, 'error_code' => null, 'body' => ['success' => true, 'deposit_id' => $existing['public_id'], 'amount_maten' => (string) $existing['amount'], 'expires_in' => $expired ? 0 : ($expiresAtTs - time()), 'code_length' => API_V2_CODE_LENGTH]];
    }

    $userStmt = $pdo->prepare("SELECT id, balance_maten FROM users WHERE maten_address = ? LIMIT 1");
    $userStmt->execute([$address]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['status' => 404, 'error_code' => 'invalid_address', 'body' => ['success' => false, 'error_code' => 'invalid_address', 'message' => 'No MATEN account found for this wallet address.']];
    }
    $userId = (int) $user['id'];

    // Test-mode keys never move real MATEN (see apiV2TransferOut) — skip the
    // real-balance check too so a partner can integration-test with any of
    // their own real Maten accounts regardless of actual balance.
    if ($key['environment'] !== 'test' && bccomp((string) $user['balance_maten'], $amount, 8) < 0) {
        return ['status' => 409, 'error_code' => 'insufficient_funds', 'body' => ['success' => false, 'error_code' => 'insufficient_funds', 'message' => 'MATEN balance is insufficient for this deposit.']];
    }

    $rateStmt = $pdo->prepare("SELECT COUNT(*) FROM api_v2_deposits WHERE partner_id = ? AND wallet_address = ? AND created_at >= (NOW() - INTERVAL " . API_V2_CODE_WINDOW_MINUTES . " MINUTE)");
    $rateStmt->execute([$partnerId, $address]);
    if ((int) $rateStmt->fetchColumn() >= API_V2_CODE_MAX_PER_WINDOW) {
        return ['status' => 429, 'error_code' => 'too_many_attempts', 'body' => ['success' => false, 'error_code' => 'too_many_attempts', 'message' => 'Too many deposit requests for this address. Try again later.']];
    }

    $code = str_pad((string) random_int(0, (10 ** API_V2_CODE_LENGTH) - 1), API_V2_CODE_LENGTH, '0', STR_PAD_LEFT);
    $codeHash = hash('sha256', $code);
    $publicId = apiV2PublicId('dep');
    $expiresAt = gmdate('Y-m-d H:i:s', time() + API_V2_CODE_TTL_MINUTES * 60);

    try {
        $insertStmt = $pdo->prepare("
            INSERT INTO api_v2_deposits (public_id, partner_id, api_key_id, user_id, wallet_address, amount, code_hash, request_idempotency_key, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$publicId, $partnerId, $keyId, $userId, $address, $amount, $codeHash, $idempotencyKey, $expiresAt]);
    } catch (PDOException $e) {
        return ['status' => 200, 'error_code' => null, 'body' => ['success' => true, 'deposit_id' => $publicId, 'amount_maten' => $amount, 'expires_in' => API_V2_CODE_TTL_MINUTES * 60, 'code_length' => API_V2_CODE_LENGTH]];
    }

    // The code goes ONLY here — the wallet owner's own notification inbox.
    // It is never returned to the caller (see the body below: code_length
    // only). This is the entire security property of the deposit flow: a
    // partner can only ever redeem /v2/transfers-out if the real account
    // owner read this notification and chose to hand the code over.
    $partnerLabel = trim((string) ($partner['company_name'] ?? '')) !== '' ? (string) $partner['company_name'] : (string) ($partner['domain'] ?? 'сайт');
    createUserNotification(
        $pdo,
        $userId,
        'Код подтверждения оплаты',
        "Код: {$code}\nСумма: {$amount} MATEN\nЗапросил: {$partnerLabel}\n\nВведите этот код ТОЛЬКО на сайте, которому вы сами хотите заплатить. Никому его не сообщайте. Код действует " . API_V2_CODE_TTL_MINUTES . ' минут.',
        'api_deposit_code'
    );

    return ['status' => 200, 'error_code' => null, 'body' => array_filter([
        'success' => true,
        'deposit_id' => $publicId,
        'amount_maten' => $amount,
        'rate_used' => $rateUsed,
        'expires_in' => API_V2_CODE_TTL_MINUTES * 60,
        'code_length' => API_V2_CODE_LENGTH,
    ], fn($v) => $v !== null)];
}

/**
 * POST /v2/transfers-out — redeems the code from a deposit and moves the
 * amount fixed at deposit-creation time into the CALLING PARTNER's own
 * escrow account. Destination is never a request parameter.
 * $input: deposit_id, code, idempotency_key.
 */
function apiV2TransferOut(PDO $pdo, array $key, array $partner, array $input): array {
    $keyId = (int) $key['id'];
    $partnerId = (int) $partner['id'];
    $depositPublicId = trim((string) ($input['deposit_id'] ?? ''));
    $code = trim((string) ($input['code'] ?? ''));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

    if ($depositPublicId === '' || $code === '' || $idempotencyKey === '') {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'missing_fields', 'message' => 'deposit_id, code and idempotency_key are required.']];
    }
    if (!preg_match('/^\d{' . API_V2_CODE_LENGTH . '}$/', $code)) {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'invalid_code', 'message' => 'code must be ' . API_V2_CODE_LENGTH . ' digits.']];
    }

    $idemStmt = $pdo->prepare("SELECT status, public_id FROM api_transactions_v2 WHERE api_key_id = ? AND idempotency_key = ? LIMIT 1");
    $idemStmt->execute([$keyId, $idempotencyKey]);
    $cachedTx = $idemStmt->fetch(PDO::FETCH_ASSOC);
    if ($cachedTx) {
        return apiV2TransactionResponse($pdo, (string) $cachedTx['public_id']);
    }

    $pdo->beginTransaction();
    try {
        $depositStmt = $pdo->prepare("
            SELECT * FROM api_v2_deposits
            WHERE public_id = ? AND partner_id = ? AND confirmed_at IS NULL AND canceled_at IS NULL
            FOR UPDATE
        ");
        $depositStmt->execute([$depositPublicId, $partnerId]);
        $deposit = $depositStmt->fetch(PDO::FETCH_ASSOC);

        if (!$deposit) {
            $pdo->rollBack();
            return ['status' => 404, 'body' => ['success' => false, 'error_code' => 'deposit_not_found', 'message' => 'No pending deposit with this id for your account.']];
        }

        if (strtotime((string) $deposit['expires_at'] . ' UTC') < time()) {
            $pdo->rollBack();
            return ['status' => 410, 'body' => ['success' => false, 'error_code' => 'code_expired', 'message' => 'Code expired. Create a new deposit.']];
        }

        if ((int) $deposit['attempts'] >= API_V2_CODE_MAX_ATTEMPTS) {
            $pdo->prepare("UPDATE api_v2_deposits SET canceled_at = COALESCE(canceled_at, NOW()), cancel_reason = COALESCE(cancel_reason, 'too_many_attempts') WHERE id = ?")->execute([(int) $deposit['id']]);
            $pdo->commit();
            return ['status' => 429, 'body' => ['success' => false, 'error_code' => 'payment_canceled', 'message' => 'Too many incorrect attempts. This payment was canceled. Create a new deposit.']];
        }

        if (!hash_equals((string) $deposit['code_hash'], hash('sha256', $code))) {
            $newAttempts = (int) $deposit['attempts'] + 1;
            if ($newAttempts >= API_V2_CODE_MAX_ATTEMPTS) {
                $pdo->prepare("UPDATE api_v2_deposits SET attempts = ?, canceled_at = NOW(), cancel_reason = 'too_many_attempts' WHERE id = ?")->execute([$newAttempts, (int) $deposit['id']]);
                $pdo->commit();
                return ['status' => 429, 'body' => ['success' => false, 'error_code' => 'payment_canceled', 'message' => 'Too many incorrect attempts. This payment was canceled. Create a new deposit.']];
            }
            $pdo->prepare("UPDATE api_v2_deposits SET attempts = ? WHERE id = ?")->execute([$newAttempts, (int) $deposit['id']]);
            $pdo->commit();
            return ['status' => 401, 'body' => ['success' => false, 'error_code' => 'invalid_code', 'message' => 'Incorrect code.']];
        }

        $markStmt = $pdo->prepare("UPDATE api_v2_deposits SET confirmed_at = NOW() WHERE id = ? AND confirmed_at IS NULL");
        $markStmt->execute([(int) $deposit['id']]);
        if ($markStmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['status' => 409, 'body' => ['success' => false, 'error_code' => 'deposit_already_used', 'message' => 'This deposit has already been redeemed.']];
        }

        $userId = (int) $deposit['user_id'];
        $amount = (string) $deposit['amount'];
        $isSimulated = $key['environment'] === 'test';

        if (!$isSimulated) {
            $userStmt = $pdo->prepare("SELECT balance_maten FROM users WHERE id = ? FOR UPDATE");
            $userStmt->execute([$userId]);
            $balance = $userStmt->fetchColumn();
            if ($balance === false || bccomp((string) $balance, $amount, 8) < 0) {
                $pdo->rollBack();
                return ['status' => 409, 'body' => ['success' => false, 'error_code' => 'insufficient_funds', 'message' => 'MATEN balance is insufficient for this transfer.']];
            }

            $debit = $pdo->prepare("UPDATE users SET balance_maten = balance_maten - ? WHERE id = ? AND balance_maten >= ?");
            $debit->execute([$amount, $userId, $amount]);
            if ($debit->rowCount() !== 1) {
                $pdo->rollBack();
                return ['status' => 409, 'body' => ['success' => false, 'error_code' => 'insufficient_funds', 'message' => 'MATEN balance is insufficient for this transfer.']];
            }

            // Self-service partners (partner.user_id set) ARE the Maten account
            // receiving the payment — credit their own wallet directly instead
            // of parking it in escrow behind a manual withdrawal step. Only
            // admin-approved third-party business partners still use escrow.
            if ($partner['user_id'] !== null) {
                $pdo->prepare("UPDATE users SET balance_maten = balance_maten + ? WHERE id = ?")->execute([$amount, (int) $partner['user_id']]);
                $escrowId = null;
            } else {
                $escrowStmt = $pdo->prepare("SELECT id FROM system_escrow_accounts WHERE partner_id = ? AND currency = 'MATEN' FOR UPDATE");
                $escrowStmt->execute([$partnerId]);
                $escrowId = $escrowStmt->fetchColumn();
                if ($escrowId === false) {
                    $pdo->rollBack();
                    error_log('apiV2TransferOut: no escrow account for partner_id=' . $partnerId);
                    return ['status' => 500, 'body' => ['success' => false, 'error_code' => 'internal_error', 'message' => 'Escrow account is not configured.']];
                }
                $escrowId = (int) $escrowId;
                $pdo->prepare("UPDATE system_escrow_accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $escrowId]);
            }
        } else {
            $escrowId = null;
        }

        $publicId = apiV2PublicId('tx2');
        $txStmt = $pdo->prepare("
            INSERT INTO api_transactions_v2
                (public_id, partner_id, api_key_id, deposit_id, idempotency_key, type, user_id, amount, currency, status, escrow_account_id, is_simulated, ip_address)
            VALUES (?, ?, ?, ?, ?, 'escrow_transfer_out', ?, ?, 'MATEN', 'succeeded', ?, ?, ?)
        ");
        $txStmt->execute([
            $publicId, $partnerId, $keyId, (int) $deposit['id'], $idempotencyKey,
            $userId, $amount, $escrowId, $isSimulated ? 1 : 0, apiV2ClientIp(),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('apiV2TransferOut failed: ' . $e->getMessage());
        return ['status' => 500, 'body' => ['success' => false, 'error_code' => 'internal_error', 'message' => 'Transfer could not be processed.']];
    }

    // Fired after commit, outside the DB transaction — delivery involves
    // network I/O and must never hold row locks or roll back real money
    // movement just because a partner's webhook endpoint is slow/down.
    apiV2FireWebhookEvent($pdo, $partnerId, 'deposit.succeeded', [
        'transaction_id' => $publicId,
        'deposit_id' => $depositPublicId,
        'amount' => $amount,
        'currency' => 'MATEN',
        'simulated' => $isSimulated,
    ]);

    // The wallet owner already got the one-time code as a notification when
    // the deposit was created — this second notification confirms the code
    // was actually redeemed and the MATEN really left their balance.
    if (!$isSimulated) {
        $partnerLabel = trim((string) ($partner['company_name'] ?? '')) !== '' ? (string) $partner['company_name'] : (string) ($partner['domain'] ?? 'сайт');
        createUserNotification($pdo, $userId, 'Перевод выполнен успешно', "{$amount} MATEN отправлено через {$partnerLabel}", 'api_transfer_completed');
    }

    return apiV2TransactionResponse($pdo, $publicId);
}

/**
 * POST /v2/payouts — the send/withdraw counterpart to /v2/deposits +
 * /v2/transfers-out (which only ever receive MATEN into the partner). Moves
 * MATEN OUT of the partner's own balance — their personal Maten wallet for a
 * self-service key, or their escrow ledger for an admin-approved business
 * partner — straight into any Maten user's wallet by address. This is what
 * makes the API a full two-way acquiring/payout gateway rather than
 * deposit-only.
 */
function apiV2CreatePayout(PDO $pdo, array $key, array $partner, array $input): array {
    matenRequireBcmath();

    $keyId = (int) $key['id'];
    $partnerId = (int) $partner['id'];
    $address = trim((string) ($input['wallet_address'] ?? ''));
    $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

    if ($address === '' || $idempotencyKey === '') {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'missing_fields', 'message' => 'wallet_address and idempotency_key are required.']];
    }
    if (strlen($idempotencyKey) > 80) {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'invalid_idempotency_key', 'message' => 'idempotency_key is too long.']];
    }
    if (!preg_match('/^[a-z0-9]{16}$/', $address)) {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'invalid_address', 'message' => 'wallet_address is not a valid MATEN wallet address.']];
    }

    $amountResolution = apiV2ResolveMatenAmount($pdo, $input);
    if (!$amountResolution['ok']) {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => $amountResolution['error'], 'message' => 'Provide either amount (MATEN, up to 8 decimals) or fiat_amount + fiat_currency (KZT/RUB/USD).']];
    }
    $amount = $amountResolution['amount'];

    $idemStmt = $pdo->prepare("SELECT public_id FROM api_transactions_v2 WHERE api_key_id = ? AND idempotency_key = ? LIMIT 1");
    $idemStmt->execute([$keyId, $idempotencyKey]);
    $cachedTx = $idemStmt->fetch(PDO::FETCH_ASSOC);
    if ($cachedTx) {
        return apiV2TransactionResponse($pdo, (string) $cachedTx['public_id']);
    }

    $isSimulated = $key['environment'] === 'test';
    $isSelfService = $partner['user_id'] !== null;
    $publicId = apiV2PublicId('tx2');
    $escrowId = null;

    $pdo->beginTransaction();
    try {
        $destStmt = $pdo->prepare("SELECT id FROM users WHERE maten_address = ? LIMIT 1 FOR UPDATE");
        $destStmt->execute([$address]);
        $dest = $destStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dest) {
            $pdo->rollBack();
            return ['status' => 404, 'body' => ['success' => false, 'error_code' => 'invalid_address', 'message' => 'No MATEN account found for this wallet address.']];
        }
        $destUserId = (int) $dest['id'];

        if ($isSelfService && $destUserId === (int) $partner['user_id']) {
            $pdo->rollBack();
            return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'self_transfer', 'message' => 'Cannot pay out to your own wallet address.']];
        }

        if (!$isSimulated) {
            if ($isSelfService) {
                $sourceUserId = (int) $partner['user_id'];
                $debit = $pdo->prepare("UPDATE users SET balance_maten = balance_maten - ? WHERE id = ? AND balance_maten >= ?");
                $debit->execute([$amount, $sourceUserId, $amount]);
                if ($debit->rowCount() !== 1) {
                    $pdo->rollBack();
                    return ['status' => 409, 'body' => ['success' => false, 'error_code' => 'insufficient_funds', 'message' => 'MATEN balance is insufficient for this payout.']];
                }
            } else {
                $escrowStmt = $pdo->prepare("SELECT id, balance FROM system_escrow_accounts WHERE partner_id = ? AND currency = 'MATEN' FOR UPDATE");
                $escrowStmt->execute([$partnerId]);
                $escrow = $escrowStmt->fetch(PDO::FETCH_ASSOC);
                if (!$escrow) {
                    $pdo->rollBack();
                    error_log('apiV2CreatePayout: no escrow account for partner_id=' . $partnerId);
                    return ['status' => 500, 'body' => ['success' => false, 'error_code' => 'internal_error', 'message' => 'Escrow account is not configured.']];
                }
                $escrowId = (int) $escrow['id'];
                $debit = $pdo->prepare("UPDATE system_escrow_accounts SET balance = balance - ? WHERE id = ? AND balance >= ?");
                $debit->execute([$amount, $escrowId, $amount]);
                if ($debit->rowCount() !== 1) {
                    $pdo->rollBack();
                    return ['status' => 409, 'body' => ['success' => false, 'error_code' => 'insufficient_funds', 'message' => 'Escrow balance is insufficient for this payout.']];
                }
            }

            $pdo->prepare("UPDATE users SET balance_maten = balance_maten + ? WHERE id = ?")->execute([$amount, $destUserId]);
        }

        $txStmt = $pdo->prepare("
            INSERT INTO api_transactions_v2
                (public_id, partner_id, api_key_id, deposit_id, idempotency_key, type, user_id, amount, currency, status, escrow_account_id, is_simulated, ip_address)
            VALUES (?, ?, ?, NULL, ?, 'payout', ?, ?, 'MATEN', 'succeeded', ?, ?, ?)
        ");
        $txStmt->execute([
            $publicId, $partnerId, $keyId, $idempotencyKey,
            $destUserId, $amount, $escrowId, $isSimulated ? 1 : 0, apiV2ClientIp(),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('apiV2CreatePayout failed: ' . $e->getMessage());
        return ['status' => 500, 'body' => ['success' => false, 'error_code' => 'internal_error', 'message' => 'Payout could not be processed.']];
    }

    apiV2FireWebhookEvent($pdo, $partnerId, 'payout.succeeded', [
        'transaction_id' => $publicId,
        'wallet_address' => $address,
        'amount' => $amount,
        'currency' => 'MATEN',
        'simulated' => $isSimulated,
    ]);

    if (!$isSimulated) {
        $partnerLabel = trim((string) ($partner['company_name'] ?? '')) !== '' ? (string) $partner['company_name'] : (string) ($partner['domain'] ?? 'сайт');
        createUserNotification($pdo, $destUserId, 'Пополнение получено', "{$amount} MATEN зачислено через {$partnerLabel}", 'api_payout_received');
    }

    return apiV2TransactionResponse($pdo, $publicId);
}

function apiV2TransactionResponse(PDO $pdo, string $publicId): array {
    $stmt = $pdo->prepare("SELECT * FROM api_transactions_v2 WHERE public_id = ? LIMIT 1");
    $stmt->execute([$publicId]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tx) {
        return ['status' => 500, 'body' => ['success' => false, 'error_code' => 'internal_error', 'message' => 'Transaction record missing after commit.']];
    }
    return [
        'status' => 200,
        'body' => [
            'success' => true,
            'transaction_id' => $tx['public_id'],
            'type' => $tx['type'],
            'amount' => $tx['amount'],
            'currency' => $tx['currency'],
            'status' => $tx['status'],
            'simulated' => (bool) $tx['is_simulated'],
            'created_at' => $tx['created_at'],
        ],
    ];
}

/** GET /v2/transactions/{id} — status lookup, scoped to the calling partner. */
function apiV2GetTransaction(PDO $pdo, array $partner, string $publicId): array {
    $stmt = $pdo->prepare("SELECT * FROM api_transactions_v2 WHERE public_id = ? AND partner_id = ? LIMIT 1");
    $stmt->execute([$publicId, (int) $partner['id']]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tx) {
        return ['status' => 404, 'body' => ['success' => false, 'error_code' => 'transaction_not_found', 'message' => 'No such transaction for your account.']];
    }
    return [
        'status' => 200,
        'body' => [
            'success' => true,
            'transaction_id' => $tx['public_id'],
            'type' => $tx['type'],
            'amount' => $tx['amount'],
            'currency' => $tx['currency'],
            'status' => $tx['status'],
            'simulated' => (bool) $tx['is_simulated'],
            'created_at' => $tx['created_at'],
        ],
    ];
}

/** POST /v2/webhooks — register a webhook for this partner. */
function apiV2CreateWebhook(PDO $pdo, array $partner, array $input): array {
    $url = trim((string) ($input['url'] ?? ''));
    $events = $input['events'] ?? [];

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'https://') !== 0) {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'invalid_url', 'message' => 'url must be a valid https:// URL.']];
    }
    if (!is_array($events) || empty($events)) {
        return ['status' => 422, 'body' => ['success' => false, 'error_code' => 'invalid_events', 'message' => 'events must be a non-empty array.']];
    }

    $publicId = apiV2PublicId('wh');
    $secret = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare("
        INSERT INTO webhooks (public_id, partner_id, url, hmac_secret_hash, events, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$publicId, (int) $partner['id'], $url, apiV2EncryptWebhookSecret($secret), json_encode(array_values($events))]);

    return ['status' => 200, 'body' => ['success' => true, 'webhook_id' => $publicId, 'hmac_secret' => $secret, 'url' => $url, 'events' => array_values($events)]];
}

const API_V2_PAGE_SIZE_DEFAULT = 20;
const API_V2_PAGE_SIZE_MAX = 100;

/** Clamps client-supplied limit/page query params to safe bounds. Returns [limit, offset, page]. */
function apiV2NormalizePagination($limitRaw, $pageRaw): array {
    $limit = (int) $limitRaw;
    if ($limit <= 0) {
        $limit = API_V2_PAGE_SIZE_DEFAULT;
    }
    $limit = min($limit, API_V2_PAGE_SIZE_MAX);

    $page = (int) $pageRaw;
    if ($page <= 0) {
        $page = 1;
    }

    return [$limit, ($page - 1) * $limit, $page];
}

/** GET /v2/webhooks — paginated list of this partner's webhooks (secrets never returned again). */
function apiV2ListWebhooks(PDO $pdo, array $partner, $limitRaw = null, $pageRaw = null): array {
    [$limit, $offset, $page] = apiV2NormalizePagination($limitRaw, $pageRaw);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM webhooks WHERE partner_id = ?");
    $countStmt->execute([(int) $partner['id']]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT public_id, url, events, is_active, created_at FROM webhooks WHERE partner_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, (int) $partner['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $webhooks = array_map(static function (array $row): array {
        return [
            'webhook_id' => $row['public_id'],
            'url' => $row['url'],
            'events' => json_decode((string) $row['events'], true) ?: [],
            'is_active' => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);
    return ['status' => 200, 'body' => [
        'success' => true,
        'webhooks' => $webhooks,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'has_more' => $offset + count($rows) < $total],
    ]];
}

/** GET /v2/transactions — paginated list of this partner's transactions, newest first. */
function apiV2ListTransactions(PDO $pdo, array $partner, $limitRaw = null, $pageRaw = null): array {
    [$limit, $offset, $page] = apiV2NormalizePagination($limitRaw, $pageRaw);
    $partnerId = (int) $partner['id'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM api_transactions_v2 WHERE partner_id = ?");
    $countStmt->execute([$partnerId]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT public_id, type, amount, currency, status, is_simulated, created_at FROM api_transactions_v2 WHERE partner_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $partnerId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $transactions = array_map(static function (array $row): array {
        return [
            'transaction_id' => $row['public_id'],
            'type' => $row['type'],
            'amount' => $row['amount'],
            'currency' => $row['currency'],
            'status' => $row['status'],
            'simulated' => (bool) $row['is_simulated'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);
    return ['status' => 200, 'body' => [
        'success' => true,
        'transactions' => $transactions,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'has_more' => $offset + count($rows) < $total],
    ]];
}

/** DELETE /v2/webhooks — deactivate one of this partner's webhooks. */
function apiV2DeleteWebhook(PDO $pdo, array $partner, string $publicId): array {
    $stmt = $pdo->prepare("UPDATE webhooks SET is_active = 0 WHERE public_id = ? AND partner_id = ? AND is_active = 1");
    $stmt->execute([$publicId, (int) $partner['id']]);
    if ($stmt->rowCount() !== 1) {
        return ['status' => 404, 'body' => ['success' => false, 'error_code' => 'webhook_not_found', 'message' => 'No active webhook with this id for your account.']];
    }
    return ['status' => 200, 'body' => ['success' => true]];
}
