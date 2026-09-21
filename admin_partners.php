<?php
declare(strict_types=1);

// Admin-only partner management for the /api/v2 universal API (Section 2c).
// Approve/reject/suspend partners, issue and revoke API keys, view escrow
// balances and recent request logs. Not linked from the public site nav —
// reached directly by an admin who is already logged in.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api_v2_gateway.php';
require_once __DIR__ . '/includes/totp.php';

apiV2EnsureTables($pdo);
apiV2EnsureEscrowWithdrawalsTable($pdo);

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

if (empty($_SESSION['admin_partners_csrf'])) {
    $_SESSION['admin_partners_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['admin_partners_csrf'];

function apOut(string $html): string {
    return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
}

$flash = $_SESSION['admin_partners_flash'] ?? null;
unset($_SESSION['admin_partners_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        http_response_code(400);
        exit('Bad CSRF token — reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    $partnerId = (int) ($_POST['partner_id'] ?? 0);

    if ($action === 'approve' && $partnerId > 0) {
        $ok = apiV2ApprovePartner($pdo, $partnerId, (int) $currentUser['id']);
        $_SESSION['admin_partners_flash'] = $ok ? ['type' => 'ok', 'text' => 'Partner approved.'] : ['type' => 'err', 'text' => 'Could not approve (already decided?).'];
    } elseif ($action === 'reject' && $partnerId > 0) {
        $stmt = $pdo->prepare("UPDATE partners SET status = 'rejected' WHERE id = ? AND status = 'pending_review'");
        $stmt->execute([$partnerId]);
        $_SESSION['admin_partners_flash'] = ['type' => $stmt->rowCount() === 1 ? 'ok' : 'err', 'text' => $stmt->rowCount() === 1 ? 'Partner rejected.' : 'Could not reject.'];
    } elseif ($action === 'suspend' && $partnerId > 0) {
        $stmt = $pdo->prepare("UPDATE partners SET status = 'suspended' WHERE id = ? AND status = 'approved'");
        $stmt->execute([$partnerId]);
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("UPDATE api_keys SET is_active = 0, revoked_at = NOW() WHERE partner_id = ? AND is_active = 1")->execute([$partnerId]);
        }
        $_SESSION['admin_partners_flash'] = ['type' => $stmt->rowCount() === 1 ? 'ok' : 'err', 'text' => $stmt->rowCount() === 1 ? 'Partner suspended, all keys revoked.' : 'Could not suspend.'];
    } elseif ($action === 'reinstate' && $partnerId > 0) {
        $stmt = $pdo->prepare("UPDATE partners SET status = 'approved' WHERE id = ? AND status = 'suspended'");
        $stmt->execute([$partnerId]);
        $_SESSION['admin_partners_flash'] = ['type' => $stmt->rowCount() === 1 ? 'ok' : 'err', 'text' => $stmt->rowCount() === 1 ? 'Partner reinstated (issue a new key — old ones stay revoked).' : 'Could not reinstate.'];
    } elseif ($action === 'issue_key' && $partnerId > 0) {
        $environment = (string) ($_POST['environment'] ?? 'test');
        $scopes = array_values(array_filter((array) ($_POST['scopes'] ?? [])));
        $result = apiV2IssueApiKey($pdo, $partnerId, $environment, $scopes);
        if ($result['ok']) {
            $_SESSION['admin_partners_flash'] = [
                'type' => 'ok',
                'text' => 'Key issued. Copy the secret now — it will never be shown again.',
                'secret' => $result,
            ];
        } else {
            $_SESSION['admin_partners_flash'] = ['type' => 'err', 'text' => 'Could not issue key: ' . $result['error']];
        }
    } elseif ($action === 'revoke_key') {
        $publicKey = (string) ($_POST['public_key'] ?? '');
        $ok = apiV2RevokeApiKey($pdo, $publicKey);
        $_SESSION['admin_partners_flash'] = ['type' => $ok ? 'ok' : 'err', 'text' => $ok ? 'Key revoked.' : 'Could not revoke (already inactive?).'];
    } elseif ($action === 'withdraw_escrow' && $partnerId > 0) {
        $totpSecret = totpSecretFor($pdo, $userId);
        if ($totpSecret === null) {
            $_SESSION['admin_partners_flash'] = ['type' => 'err', 'text' => '2FA is not enabled for your account — set it up at admin_2fa_setup.php before withdrawing escrow.'];
        } elseif (!totpVerify($totpSecret, (string) ($_POST['totp_code'] ?? ''))) {
            $_SESSION['admin_partners_flash'] = ['type' => 'err', 'text' => 'Wrong or expired 2FA code — try again.'];
        } else {
            $amount = trim((string) ($_POST['amount'] ?? ''));
            $note = trim((string) ($_POST['note'] ?? ''));
            $result = apiV2AdminWithdrawEscrow($pdo, $partnerId, $amount, $userId, $note);
            $_SESSION['admin_partners_flash'] = $result['ok']
                ? ['type' => 'ok', 'text' => 'Escrow withdrawal of ' . $amount . ' MATEN recorded.']
                : ['type' => 'err', 'text' => 'Could not withdraw: ' . $result['error']];
        }
    }

    header('Location: admin_partners.php');
    exit;
}

$partners = $pdo->query("SELECT * FROM partners ORDER BY (status = 'pending_review') DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$keysByPartner = [];
foreach ($pdo->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC) as $k) {
    $keysByPartner[(int) $k['partner_id']][] = $k;
}
$escrowByPartner = [];
foreach ($pdo->query("SELECT * FROM system_escrow_accounts")->fetchAll(PDO::FETCH_ASSOC) as $e) {
    $escrowByPartner[(int) $e['partner_id']] = $e;
}
$recentLogs = $pdo->query("SELECT l.*, p.company_name FROM api_request_logs l LEFT JOIN partners p ON p.id = l.partner_id ORDER BY l.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$adminTotpEnabled = totpIsEnabledFor($pdo, $userId);

$statusColors = ['pending_review' => '#b8860b', 'approved' => '#1fa855', 'suspended' => '#c0392b', 'rejected' => '#777'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Maten — Partner Admin</title>
<meta name="robots" content="noindex, nofollow">
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #0f1115; color: #e6e6e6; margin: 0; padding: 24px; }
  h1 { font-size: 20px; margin: 0 0 20px; }
  h2 { font-size: 15px; color: #9aa0a6; margin: 32px 0 10px; text-transform: uppercase; letter-spacing: 0.05em; }
  .flash { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
  .flash.ok { background: #16351f; border: 1px solid #1fa855; }
  .flash.err { background: #3a1717; border: 1px solid #c0392b; }
  .secret-box { margin-top: 10px; padding: 10px; background: #1a1c22; border-radius: 4px; font-family: monospace; font-size: 13px; word-break: break-all; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 8px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #262a33; vertical-align: top; }
  th { color: #9aa0a6; font-weight: 600; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; color: #fff; }
  .card { background: #161920; border: 1px solid #262a33; border-radius: 8px; padding: 16px; margin-bottom: 14px; }
  form.inline { display: inline; }
  button { background: #2b7cff; color: #fff; border: none; border-radius: 4px; padding: 6px 12px; font-size: 12px; cursor: pointer; margin-right: 4px; }
  button.danger { background: #c0392b; }
  button.ghost { background: transparent; border: 1px solid #3a3f4b; }
  .mono { font-family: monospace; font-size: 12px; color: #9aa0a6; }
  .scopes-check label { display: inline-block; margin-right: 12px; font-size: 12px; }
  select, input[type=text] { background: #1a1c22; border: 1px solid #3a3f4b; color: #e6e6e6; border-radius: 4px; padding: 4px 8px; font-size: 12px; }
</style>
</head>
<body>
<h1>Maten Partner Admin — <?= apOut((string) ($currentUser['username'] ?? $currentUser['email'])) ?></h1>

<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>">
    <?= apOut($flash['text']) ?>
    <?php if (!empty($flash['secret'])): $s = $flash['secret']; ?>
      <div class="secret-box">
        public_key: <?= apOut($s['public_key']) ?><br>
        secret_key: <strong><?= apOut($s['secret_key']) ?></strong><br>
        environment: <?= apOut($s['environment']) ?> · scopes: <?= apOut(implode(', ', $s['scopes'])) ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<h2>Partners</h2>
<?php foreach ($partners as $p): $pid = (int) $p['id']; $color = $statusColors[$p['status']] ?? '#777'; ?>
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <strong><?= apOut($p['company_name']) ?></strong>
        <span class="mono"><?= apOut($p['domain']) ?> · <?= apOut($p['contact_email']) ?> · <?= apOut($p['public_id']) ?></span>
      </div>
      <span class="badge" style="background:<?= $color ?>"><?= apOut($p['status']) ?></span>
    </div>
    <?php if ($p['business_description']): ?><p class="mono"><?= apOut($p['business_description']) ?></p><?php endif; ?>

    <div style="margin-top:10px;">
      <?php if ($p['status'] === 'pending_review'): ?>
        <form class="inline" method="post"><input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>"><input type="hidden" name="partner_id" value="<?= $pid ?>"><input type="hidden" name="action" value="approve"><button>Approve</button></form>
        <form class="inline" method="post"><input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>"><input type="hidden" name="partner_id" value="<?= $pid ?>"><input type="hidden" name="action" value="reject"><button class="danger">Reject</button></form>
      <?php elseif ($p['status'] === 'approved'): ?>
        <form class="inline" method="post"><input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>"><input type="hidden" name="partner_id" value="<?= $pid ?>"><input type="hidden" name="action" value="suspend"><button class="danger">Suspend</button></form>
      <?php elseif ($p['status'] === 'suspended'): ?>
        <form class="inline" method="post"><input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>"><input type="hidden" name="partner_id" value="<?= $pid ?>"><input type="hidden" name="action" value="reinstate"><button>Reinstate</button></form>
      <?php endif; ?>
    </div>

    <?php if ($p['status'] === 'approved'): $escrow = $escrowByPartner[$pid] ?? null; ?>
      <p class="mono">Escrow balance: <strong><?= $escrow ? apOut($escrow['balance']) : '0.00000000' ?> MATEN</strong></p>

      <?php if ($adminTotpEnabled): ?>
        <form method="post" style="margin-top:8px; margin-bottom: 10px;">
          <input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>">
          <input type="hidden" name="partner_id" value="<?= $pid ?>">
          <input type="hidden" name="action" value="withdraw_escrow">
          <input type="text" name="amount" placeholder="amount" style="width:100px;" required>
          <input type="text" name="note" placeholder="note (optional)" style="width:160px;">
          <input type="text" name="totp_code" placeholder="2FA code" inputmode="numeric" maxlength="6" style="width:80px;" required>
          <button type="submit" class="danger" onclick="return confirm('Withdraw escrow — this is bookkeeping only, the actual payout to the partner is a manual step you do outside Maten. Continue?')">Withdraw escrow</button>
        </form>
      <?php else: ?>
        <p class="mono">Escrow withdrawal is locked — <a href="admin_2fa_setup.php" style="color:#2b7cff;">set up 2FA</a> first.</p>
      <?php endif; ?>

      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>">
        <input type="hidden" name="partner_id" value="<?= $pid ?>">
        <input type="hidden" name="action" value="issue_key">
        <select name="environment"><option value="test">test</option><option value="live">live</option></select>
        <span class="scopes-check">
          <?php foreach (API_V2_VALID_SCOPES as $sc): ?>
            <label><input type="checkbox" name="scopes[]" value="<?= apOut($sc) ?>"> <?= apOut($sc) ?></label>
          <?php endforeach; ?>
        </span>
        <button type="submit">Issue key</button>
      </form>

      <table style="margin-top:10px;">
        <tr><th>public_key</th><th>env</th><th>scopes</th><th>status</th><th>last used</th><th></th></tr>
        <?php foreach ($keysByPartner[$pid] ?? [] as $k): ?>
          <tr>
            <td class="mono"><?= apOut($k['public_key']) ?></td>
            <td><?= apOut($k['environment']) ?></td>
            <td class="mono"><?= apOut(implode(', ', json_decode((string) $k['scopes'], true) ?: [])) ?></td>
            <td><?= (int) $k['is_active'] === 1 ? 'active' : 'revoked' ?></td>
            <td class="mono"><?= apOut((string) ($k['last_used_at'] ?? '—')) ?></td>
            <td>
              <?php if ((int) $k['is_active'] === 1): ?>
                <form class="inline" method="post"><input type="hidden" name="csrf" value="<?= apOut($csrfToken) ?>"><input type="hidden" name="public_key" value="<?= apOut($k['public_key']) ?>"><input type="hidden" name="action" value="revoke_key"><button class="ghost danger">Revoke</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (empty($partners)): ?><p class="mono">No partners registered yet.</p><?php endif; ?>

<h2>Recent API requests (last 50)</h2>
<table>
  <tr><th>time</th><th>partner</th><th>endpoint</th><th>method</th><th>status</th><th>error</th><th>ms</th></tr>
  <?php foreach ($recentLogs as $l): ?>
    <tr>
      <td class="mono"><?= apOut((string) $l['created_at']) ?></td>
      <td><?= apOut((string) ($l['company_name'] ?? '—')) ?></td>
      <td class="mono"><?= apOut((string) $l['endpoint']) ?></td>
      <td><?= apOut((string) $l['method']) ?></td>
      <td><?= (int) $l['status_code'] ?></td>
      <td class="mono"><?= apOut((string) ($l['error_code'] ?? '')) ?></td>
      <td><?= (int) ($l['duration_ms'] ?? 0) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
</body>
</html>
