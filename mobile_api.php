<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/maten_p2p.php';
require_once __DIR__ . '/includes/maten_rate.php';
matenP2pEnsureTables($pdo);

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'message' => ''];

function mobileUserPayload(?array $user): ?array {
    if (!$user) {
        return null;
    }
    return [
        'id' => (int) ($user['id'] ?? 0),
        'email' => (string) ($user['email'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'display_name' => resolveDisplayName($user),
        'maten_address' => (string) ($user['maten_address'] ?? ''),
        'balance' => formatBalance($user['balance_maten'] ?? 0),
        'avatar' => $user['avatar_data'] ?? null,
        'role' => ((int) ($user['admin_level'] ?? 0) > 0) ? 'admin' : 'user',
    ];
}

function mobileFetchUser(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT id, email, username, avatar_data, role, admin_level, maten_address, balance_maten FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function mobileMarketRate(PDO $pdo): float {
    return matenKztRateFloat($pdo);
}

if ($action === 'bootstrap') {
    $payload = [
        'success' => true,
        'logged_in' => false,
        'lang' => $lang,
        'market_rate_kzt' => mobileMarketRate($pdo),
    ];
    if (isset($_SESSION['user_id'])) {
        $user = mobileFetchUser($pdo, (int) $_SESSION['user_id']);
        if ($user) {
            $payload['logged_in'] = true;
            $payload['user'] = mobileUserPayload($user);
        }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'set_lang') {
        $newLang = $_POST['lang'] ?? '';
        if (in_array($newLang, $allowed_langs, true)) {
            $_SESSION['lang'] = $newLang;
            $lang = $newLang;
        }
        echo json_encode(['success' => true, 'lang' => $lang], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
