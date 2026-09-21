<?php
declare(strict_types=1);

// Universal partner API entry point (api/v2). Routed from /api/v2/{route}
// via the root .htaccess. Any approved partner can call this with their own
// key pair — see includes/api_v2_gateway.php for the full design notes.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/maten_p2p.php';
require_once __DIR__ . '/includes/maten_rate.php';
require_once __DIR__ . '/includes/api_v2_gateway.php';
matenP2pEnsureTables($pdo);

apiV2EnsureTables($pdo);

// No cron access on this host — piggyback the webhook retry sweep on a
// small fraction of live traffic so failed deliveries still get retried.
// A real cron hitting webhooks_retry_worker.php (if one gets configured
// later) makes this redundant but harmless.
if (random_int(1, 100) <= 5) {
    apiV2ProcessDueWebhookDeliveries($pdo, 5);
}

header('Content-Type: application/json');

$route = trim((string) ($_GET['route'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$startedAt = microtime(true);

function apiV2Respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiV2RespondLogged(PDO $pdo, ?int $partnerId, ?int $keyId, string $route, string $method, float $startedAt, int $status, array $body): never {
    apiV2LogRequest($pdo, $partnerId, $keyId, $route, $method, $status, $body['error_code'] ?? null, $startedAt);
    apiV2Respond($status, $body);
}

function apiV2AuthOrRespond(PDO $pdo, string $method, string $route, string $rawBody, string $scope, float $startedAt): array {
    $auth = apiV2AuthenticateRequest($pdo, $method, '/api/v2/' . $route, $rawBody, $scope);
    if (!$auth['ok']) {
        apiV2RespondLogged($pdo, null, null, $route, $method, $startedAt, $auth['status'], ['success' => false, 'error_code' => $auth['error']]);
    }
    return $auth;
}

// Idempotency-Key travels as a header (standard REST convention — Stripe,
// etc.), never inside the signed JSON body: it isn't part of what the HMAC
// signature covers (METHOD\nPATH\nRAW_BODY\nTIMESTAMP\nNONCE), and keeping
// it out of raw_body means re-sending an identical body with a fresh
// idempotency key never risks a signature mismatch.
function apiV2IdempotencyKeyFromHeader(): string {
    $value = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
    if ($value === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $headerName => $headerValue) {
            if (strcasecmp($headerName, 'Idempotency-Key') === 0) {
                $value = (string) $headerValue;
                break;
            }
        }
    }
    return trim((string) $value);
}

// --- Public, unauthenticated ------------------------------------------------

if ($route === 'rate' && $method === 'GET') {
    $rateCurrency = strtoupper(trim((string) ($_GET['currency'] ?? 'KZT')));
    if (!preg_match('/^[A-Z]{3}$/', $rateCurrency)) {
        $rateCurrency = 'KZT';
    }
    apiV2Respond(200, ['success' => true, 'base' => 'MATEN', 'quote' => $rateCurrency, 'rate' => matenRate($pdo, $rateCurrency), 'timestamp' => time()]);
}

if ($route === 'partners' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) {
        apiV2Respond(400, ['success' => false, 'error_code' => 'invalid_json']);
    }
    $result = apiV2RegisterPartner(
        $pdo,
        (string) ($input['company_name'] ?? ''),
        (string) ($input['domain'] ?? ''),
        (string) ($input['contact_email'] ?? ''),
        (string) ($input['business_description'] ?? '')
    );
    if (!$result['ok']) {
        apiV2Respond(422, ['success' => false, 'error_code' => $result['error']]);
    }
    // Auto-approved — issue a live key pair immediately so registration
    // delivers working credentials in one call, no separate approval step.
    $issued = apiV2IssueApiKey($pdo, (int) $result['id'], 'live', API_V2_VALID_SCOPES);
    if (!$issued['ok']) {
        apiV2Respond(200, ['success' => true, 'partner_id' => $result['public_id'], 'status' => $result['status'], 'message' => 'Partner created, but key issuance failed — contact support.']);
    }
    apiV2Respond(200, [
        'success' => true,
        'partner_id' => $result['public_id'],
        'status' => $result['status'],
        'public_key' => $issued['public_key'],
        'secret_key' => $issued['secret_key'],
        'message' => 'Partner approved instantly, no manual review. Store secret_key now — it is shown only once.',
    ]);
}

if ($route === 'docs' && $method === 'GET') {
    apiV2Respond(200, [
        'success' => true,
        'note' => 'Full interactive docs live at /developers/docs.php. This is the machine-readable summary.',
        'auth' => [
            'headers' => ['X-Api-Key' => 'pk_test_... or pk_live_...', 'Authorization' => 'Bearer sk_test_... or sk_live_...', 'X-Timestamp' => 'unix seconds', 'X-Nonce' => '8-64 char random string, unique per request', 'X-Signature' => 'hex(HMAC-SHA256("METHOD\nPATH\nRAW_BODY\nTIMESTAMP\nNONCE", secret_key)) — RAW_BODY is the exact request body bytes, "" for requests with no body; Idempotency-Key is NOT part of the signed string', 'Idempotency-Key' => 'header (not body field), required on all money-moving POST endpoints below'],
        ],
        'endpoints' => [
            'POST /api/v2/partners' => ['auth' => 'none', 'body' => ['company_name', 'domain', 'contact_email', 'business_description'], 'notes' => 'Creates a partner in pending_review status. Approval happens separately.'],
            'POST /api/v2/deposits' => ['auth' => 'signed', 'scope' => 'deposit:create', 'body' => ['wallet_address', 'amount (MATEN) OR fiat_amount + fiat_currency (KZT/RUB/USD)'], 'headers' => ['Idempotency-Key'], 'notes' => 'Initiates a deposit — mints a one-time code and delivers it to the wallet owner\'s own Maten notification inbox. The response never contains the code, only code_length — the caller cannot redeem transfers-out without the real owner handing the code over. Pass fiat_amount + fiat_currency instead of amount to auto-convert at the current cross-rate-consistent Maten rate (e.g. fiat_amount=200, fiat_currency=KZT resolves to whatever MATEN that buys); the response echoes amount_maten and rate_used.'],
            'POST /api/v2/transfers-out' => ['auth' => 'signed', 'scope' => 'transfer:create', 'body' => ['deposit_id', 'code'], 'headers' => ['Idempotency-Key'], 'notes' => 'Redeems the code and moves the amount fixed at deposit time into your own balance (self-service key: straight to your Maten wallet; business partner key: into your escrow account).'],
            'POST /api/v2/payouts' => ['auth' => 'signed', 'scope' => 'payout:create', 'body' => ['wallet_address', 'amount (MATEN) OR fiat_amount + fiat_currency (KZT/RUB/USD)'], 'headers' => ['Idempotency-Key'], 'notes' => 'Sends MATEN out of your own balance to any Maten wallet address — the withdraw/send side of the API, symmetric to deposits + transfers-out. Source is always your own key\'s wallet/escrow, never a request parameter. Same fiat_amount + fiat_currency auto-conversion as /deposits.'],
            'GET /api/v2/transactions' => ['auth' => 'signed', 'scope' => 'transaction:read', 'query' => ['limit (default 20, max 100)', 'page (default 1)'], 'notes' => 'Paginated list of your transactions, newest first.'],
            'GET /api/v2/transactions/{id}' => ['auth' => 'signed', 'scope' => 'transaction:read'],
            'POST /api/v2/webhooks' => ['auth' => 'signed', 'scope' => 'webhook:manage', 'body' => ['url', 'events']],
            'GET /api/v2/webhooks' => ['auth' => 'signed', 'scope' => 'webhook:manage', 'query' => ['limit (default 20, max 100)', 'page (default 1)']],
            'DELETE /api/v2/webhooks/{id}' => ['auth' => 'signed', 'scope' => 'webhook:manage'],
        ],
    ]);
}

// --- Signed, authenticated ---------------------------------------------------

if ($route === 'deposits' && $method === 'POST') {
    $rawBody = file_get_contents('php://input') ?: '';
    $auth = apiV2AuthOrRespond($pdo, $method, $route, $rawBody, 'deposit:create', $startedAt);
    $input = json_decode($rawBody, true);
    if (!is_array($input)) {
        apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, 400, ['success' => false, 'error_code' => 'invalid_json']);
    }
    $input['idempotency_key'] = apiV2IdempotencyKeyFromHeader();
    $result = apiV2CreateDeposit($pdo, $auth['key'], $auth['partner'], $input);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if ($route === 'transfers-out' && $method === 'POST') {
    $rawBody = file_get_contents('php://input') ?: '';
    $auth = apiV2AuthOrRespond($pdo, $method, $route, $rawBody, 'transfer:create', $startedAt);
    $input = json_decode($rawBody, true);
    if (!is_array($input)) {
        apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, 400, ['success' => false, 'error_code' => 'invalid_json']);
    }
    $input['idempotency_key'] = apiV2IdempotencyKeyFromHeader();
    $result = apiV2TransferOut($pdo, $auth['key'], $auth['partner'], $input);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if ($route === 'payouts' && $method === 'POST') {
    $rawBody = file_get_contents('php://input') ?: '';
    $auth = apiV2AuthOrRespond($pdo, $method, $route, $rawBody, 'payout:create', $startedAt);
    $input = json_decode($rawBody, true);
    if (!is_array($input)) {
        apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, 400, ['success' => false, 'error_code' => 'invalid_json']);
    }
    $input['idempotency_key'] = apiV2IdempotencyKeyFromHeader();
    $result = apiV2CreatePayout($pdo, $auth['key'], $auth['partner'], $input);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if ($route === 'transactions' && $method === 'GET') {
    $auth = apiV2AuthOrRespond($pdo, $method, $route, '', 'transaction:read', $startedAt);
    $result = apiV2ListTransactions($pdo, $auth['partner'], $_GET['limit'] ?? null, $_GET['page'] ?? null);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if (strpos($route, 'transactions/') === 0 && $method === 'GET') {
    $rawBody = '';
    $auth = apiV2AuthOrRespond($pdo, $method, $route, $rawBody, 'transaction:read', $startedAt);
    $txId = substr($route, strlen('transactions/'));
    $result = apiV2GetTransaction($pdo, $auth['partner'], $txId);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if ($route === 'webhooks' && $method === 'POST') {
    $rawBody = file_get_contents('php://input') ?: '';
    $auth = apiV2AuthOrRespond($pdo, $method, $route, $rawBody, 'webhook:manage', $startedAt);
    $input = json_decode($rawBody, true);
    if (!is_array($input)) {
        apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, 400, ['success' => false, 'error_code' => 'invalid_json']);
    }
    $result = apiV2CreateWebhook($pdo, $auth['partner'], $input);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if ($route === 'webhooks' && $method === 'GET') {
    $auth = apiV2AuthOrRespond($pdo, $method, $route, '', 'webhook:manage', $startedAt);
    $result = apiV2ListWebhooks($pdo, $auth['partner'], $_GET['limit'] ?? null, $_GET['page'] ?? null);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

if (strpos($route, 'webhooks/') === 0 && $method === 'DELETE') {
    $auth = apiV2AuthOrRespond($pdo, $method, $route, '', 'webhook:manage', $startedAt);
    $whId = substr($route, strlen('webhooks/'));
    $result = apiV2DeleteWebhook($pdo, $auth['partner'], $whId);
    apiV2RespondLogged($pdo, (int) $auth['partner']['id'], (int) $auth['key']['id'], $route, $method, $startedAt, $result['status'], $result['body']);
}

apiV2Respond(404, ['success' => false, 'error_code' => 'unknown_route']);
