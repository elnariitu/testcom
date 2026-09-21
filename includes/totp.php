<?php
declare(strict_types=1);

/**
 * Minimal RFC 6238 TOTP (and RFC 4648 base32) implementation — no external
 * dependency. Used to gate sensitive admin actions (escrow withdrawal) with
 * a standard authenticator-app (Google Authenticator, 1Password, etc) code.
 */

function totpBase32Encode(string $bytes): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0');
        }
        $output .= $alphabet[bindec($chunk)];
    }
    return $output;
}

function totpBase32Decode(string $base32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $base32) ?? '');
    $bits = '';
    foreach (str_split($base32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byteBits) {
        if (strlen($byteBits) === 8) {
            $bytes .= chr(bindec($byteBits));
        }
    }
    return $bytes;
}

function totpGenerateSecret(): string {
    return totpBase32Encode(random_bytes(20));
}

function totpCodeAt(string $secretBase32, int $timestamp, int $period = 30, int $digits = 6): string {
    $key = totpBase32Decode($secretBase32);
    $counter = intdiv($timestamp, $period);
    $counterBytes = pack('J', $counter); // 64-bit big-endian
    $hash = hash_hmac('sha1', $counterBytes, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    $code = $truncated % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

/** Accepts the current 30s window plus $window steps before/after (clock drift tolerance). */
function totpVerify(string $secretBase32, string $code, int $window = 1, int $period = 30): bool {
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totpCodeAt($secretBase32, $now + $i * $period, $period), $code)) {
            return true;
        }
    }
    return false;
}

function totpProvisioningUri(string $secretBase32, string $accountLabel, string $issuer = 'Maten'): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountLabel)
        . '?secret=' . rawurlencode($secretBase32)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function totpEnsureTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_totp_secrets (
            user_id INT NOT NULL PRIMARY KEY,
            secret_base32 VARCHAR(64) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function totpIsEnabledFor(PDO $pdo, int $userId): bool {
    totpEnsureTable($pdo);
    $stmt = $pdo->prepare("SELECT enabled FROM admin_totp_secrets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() === 1;
}

function totpSecretFor(PDO $pdo, int $userId): ?string {
    totpEnsureTable($pdo);
    $stmt = $pdo->prepare("SELECT secret_base32 FROM admin_totp_secrets WHERE user_id = ? AND enabled = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $secret = $stmt->fetchColumn();
    return $secret === false ? null : (string) $secret;
}
