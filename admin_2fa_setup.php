<?php
declare(strict_types=1);

// Admin 2FA (TOTP) enrollment. Reached directly by an admin who is already
// logged in — not linked from the public site nav. Required before
// admin_partners.php will allow an escrow withdrawal.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/totp.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Sign in to maten.pro first, then reload this page.');
}

$stmt = $pdo->prepare("SELECT id, email, username, role, admin_level FROM users WHERE id = ?");
$stmt->execute([(int) $_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$isAdmin = $currentUser && ((int) ($currentUser['admin_level'] ?? 0) > 0 || ($currentUser['role'] ?? '') === 'admin');
if (!$isAdmin) {
    http_response_code(403);
    exit('Admins only.');
}
$userId = (int) $currentUser['id'];

totpEnsureTable($pdo);

if (empty($_SESSION['admin_2fa_csrf'])) {
    $_SESSION['admin_2fa_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['admin_2fa_csrf'];

function tOut(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$message = null;

$stmt = $pdo->prepare("SELECT * FROM admin_totp_secrets WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Bad CSRF token — reload and try again.');
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'start') {
        $secret = totpGenerateSecret();
        $upsert = $pdo->prepare("
            INSERT INTO admin_totp_secrets (user_id, secret_base32, enabled) VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE secret_base32 = VALUES(secret_base32), enabled = 0, confirmed_at = NULL
        ");
        $upsert->execute([$userId, $secret]);
        header('Location: admin_2fa_setup.php');
        exit;
    }

    if ($action === 'confirm' && $existing) {
        $code = (string) ($_POST['code'] ?? '');
        if (totpVerify((string) $existing['secret_base32'], $code)) {
            $pdo->prepare("UPDATE admin_totp_secrets SET enabled = 1, confirmed_at = NOW() WHERE user_id = ?")->execute([$userId]);
            $message = ['type' => 'ok', 'text' => '2FA enabled. admin_partners.php will now require a code for escrow withdrawals.'];
        } else {
            $message = ['type' => 'err', 'text' => 'Wrong code — try the current 6-digit code from your authenticator app.'];
        }
    }

    if ($action === 'disable') {
        $pdo->prepare("DELETE FROM admin_totp_secrets WHERE user_id = ?")->execute([$userId]);
        header('Location: admin_2fa_setup.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM admin_totp_secrets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
}

$isEnabled = $existing && (int) $existing['enabled'] === 1;
$provisioningUri = $existing ? totpProvisioningUri((string) $existing['secret_base32'], (string) ($currentUser['email'] ?? $currentUser['username']), 'Maten Admin') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Maten Admin — 2FA Setup</title>
<meta name="robots" content="noindex, nofollow">
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #0f1115; color: #e6e6e6; margin: 0; padding: 32px; }
  .wrap { max-width: 560px; margin: 0 auto; }
  h1 { font-size: 20px; }
  .card { background: #161920; border: 1px solid #262a33; border-radius: 8px; padding: 20px; margin-top: 16px; }
  .msg { padding: 12px 16px; border-radius: 6px; margin-top: 12px; }
  .msg.ok { background: #16351f; border: 1px solid #1fa855; }
  .msg.err { background: #3a1717; border: 1px solid #c0392b; }
  code { background: #1a1c22; padding: 2px 6px; border-radius: 4px; font-size: 13px; word-break: break-all; }
  input { background: #1a1c22; border: 1px solid #3a3f4b; color: #e6e6e6; border-radius: 4px; padding: 8px 10px; font-size: 14px; }
  button { background: #2b7cff; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; font-size: 13px; cursor: pointer; }
  button.danger { background: #c0392b; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Admin 2FA (TOTP)</h1>

  <?php if ($message): ?>
    <div class="msg <?= $message['type'] === 'ok' ? 'ok' : 'err' ?>"><?= tOut($message['text']) ?></div>
  <?php endif; ?>

  <?php if ($isEnabled): ?>
    <div class="card">
      <p>2FA is <strong>enabled</strong> for <?= tOut((string) ($currentUser['email'] ?? $currentUser['username'])) ?>.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= tOut($csrfToken) ?>">
        <input type="hidden" name="action" value="disable">
        <button class="danger" onclick="return confirm('Disable 2FA? Escrow withdrawals will be blocked until it is set up again.')">Disable 2FA</button>
      </form>
    </div>
  <?php elseif ($existing): ?>
    <div class="card">
      <p>Scan this into your authenticator app (Google Authenticator, 1Password, Authy…), or enter the secret manually:</p>
      <p><code><?= tOut((string) $existing['secret_base32']) ?></code></p>
      <p style="font-size:12px;color:#9aa0a6;">Provisioning URI: <code><?= tOut($provisioningUri) ?></code></p>
      <form method="post" style="margin-top:14px;">
        <input type="hidden" name="csrf" value="<?= tOut($csrfToken) ?>">
        <input type="hidden" name="action" value="confirm">
        <label>Enter the current 6-digit code to confirm:</label><br>
        <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="\d{6}" required>
        <button type="submit">Confirm & enable</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card">
      <p>2FA is not set up yet. Escrow withdrawals in <a href="admin_partners.php" style="color:#2b7cff;">admin_partners.php</a> are blocked until it is.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= tOut($csrfToken) ?>">
        <input type="hidden" name="action" value="start">
        <button type="submit">Start 2FA setup</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
