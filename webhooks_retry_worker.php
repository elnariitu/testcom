<?php
declare(strict_types=1);

// Cron entry point for webhook delivery retries. Timeweb (and most shared
// PHP hosts) let you schedule a URL hit from the hosting control panel —
// point a cron job at this URL every 1–5 minutes for prompt retries. The
// api_v2.php request handler also does a low-probability opportunistic
// sweep on its own, so delivery still eventually happens even without this
// being configured — this just makes it faster and more reliable.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api_v2_gateway.php';

apiV2EnsureTables($pdo);

header('Content-Type: application/json');

$processed = apiV2ProcessDueWebhookDeliveries($pdo, 25);

echo json_encode(['processed' => $processed, 'timestamp' => time()]);
