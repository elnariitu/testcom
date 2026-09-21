<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api_v2_gateway.php';
require_once __DIR__ . '/includes/maten_p2p.php';

apiV2EnsureTables($pdo);
matenP2pEnsureTables($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Сначала войдите в maten.pro.');
}
$adminStmt = $pdo->prepare("SELECT id, username, role, admin_level FROM users WHERE id = ? LIMIT 1");
$adminStmt->execute([(int) $_SESSION['user_id']]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
if (!$admin || ((int) ($admin['admin_level'] ?? 0) <= 0 && ($admin['role'] ?? '') !== 'admin')) {
    http_response_code(403);
    exit('Доступ только для администратора.');
}

function panelOut(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function panelAmount(mixed $value): string {
    $truncated = floor(((float) $value) * 100) / 100;
    return number_format($truncated, 2, '.', ' ');
}

$users = $pdo->query("SELECT id, username, email, maten_address, balance_maten, role, admin_level, last_seen_at FROM users ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBalance = (string) ($pdo->query("SELECT COALESCE(SUM(balance_maten), 0) FROM users")->fetchColumn() ?: '0');
$onlineCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE last_seen_at >= (NOW() - INTERVAL 5 MINUTE)")->fetchColumn();
$partners = $pdo->query("SELECT p.id, p.public_id, p.company_name, p.domain, p.contact_email, p.status, p.created_at, u.username FROM partners p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
$apiTransactions = $pdo->query("SELECT t.public_id, t.type, t.amount, t.currency, t.status, t.is_simulated, t.created_at, p.company_name FROM api_transactions_v2 t LEFT JOIN partners p ON p.id = t.partner_id ORDER BY t.created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$transfers = $pdo->query("SELECT n.user_id, u.username, n.title, n.body, n.type, n.created_at FROM user_notifications n LEFT JOIN users u ON u.id = n.user_id WHERE n.type IN ('transfer_sent','transfer_received') ORDER BY n.created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$p2pOrders = $pdo->query("SELECT o.public_id, o.amount_maten, o.total_fiat, o.currency, o.status, o.created_at, b.username buyer, s.username seller FROM p2p_trade_orders o LEFT JOIN users b ON b.id=o.buyer_id LEFT JOIN users s ON s.id=o.seller_id ORDER BY o.created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$tabs = ['users' => 'Пользователи', 'partners' => 'Мерчанты', 'api' => 'API', 'transfers' => 'Переводы', 'p2p' => 'P2P'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Maten Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#090d0b;color:#eef4f0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:1280px;margin:auto;padding:18px 16px 60px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.top h1{font-size:22px;margin:0}.top a{color:#7dffad;text-decoration:none}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}.stat,.panel{background:#121916;border:1px solid #26332d;border-radius:8px}.stat{padding:14px}.stat span{display:block;color:#819188;font-size:12px}.stat strong{display:block;font-size:21px;margin-top:5px}.tabs{display:flex;gap:8px;overflow:auto;padding-bottom:10px}.tabs button{border:1px solid #2b3a33;background:#141d19;color:#afbbb5;padding:10px 14px;border-radius:7px;white-space:nowrap;font-weight:700}.tabs button.active{background:#00c957;color:#041109;border-color:#00c957}.panel{display:none;overflow:hidden}.panel.active{display:block}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:left;padding:11px 12px;border-bottom:1px solid #202c27;font-size:13px;vertical-align:top}th{color:#809087;font-size:11px;text-transform:uppercase;background:#101612;position:sticky;top:0}.online{color:#56ee91}.offline{color:#87958e}.pill{display:inline-block;padding:3px 8px;border:1px solid #33443c;border-radius:10px;font-size:11px}.mono{font-family:ui-monospace,monospace;font-size:12px}.empty{padding:24px;color:#839088}@media(max-width:720px){.wrap{padding:14px 10px 40px}.stats{grid-template-columns:1fr 1fr}.stat strong{font-size:17px}.top{align-items:flex-start}.panel{border-radius:7px}table{min-width:680px}th,td{padding:10px 9px}}
</style>
</head>
<body><main class="wrap">
<div class="top"><div><h1>Maten Admin</h1><small>Администратор: <?= panelOut($admin['username']) ?></small></div><a href="index.php?page=menu">На сайт</a></div>
<section class="stats">
 <div class="stat"><span>Пользователей</span><strong><?= $totalUsers ?></strong></div>
 <div class="stat"><span>Онлайн за 5 минут</span><strong class="online"><?= $onlineCount ?></strong></div>
 <div class="stat"><span>Баланс пользователей</span><strong><?= panelAmount($totalBalance) ?> M</strong></div>
 <div class="stat"><span>Мерчантов</span><strong><?= count($partners) ?></strong></div>
</section>
<nav class="tabs"><?php foreach ($tabs as $id=>$label): ?><button type="button" data-tab="<?= $id ?>" class="<?= $id==='users'?'active':'' ?>"><?= $label ?></button><?php endforeach; ?></nav>
<section id="users" class="panel active"><div class="table-wrap"><table><thead><tr><th>ID / пользователь</th><th>Email</th><th>Адрес</th><th>Баланс</th><th>Роль</th><th>Активность</th></tr></thead><tbody><?php foreach($users as $u): $isOnline=!empty($u['last_seen_at'])&&strtotime((string)$u['last_seen_at'])>=time()-300; ?><tr><td><strong>#<?= (int)$u['id'] ?> <?= panelOut($u['username']) ?></strong></td><td><?= panelOut($u['email']) ?></td><td class="mono"><?= panelOut($u['maten_address']) ?></td><td><?= panelAmount($u['balance_maten']) ?> M</td><td><?= ((int)$u['admin_level']>0||$u['role']==='admin')?'Админ':'Пользователь' ?></td><td class="<?= $isOnline?'online':'offline' ?>"><?= $isOnline?'Онлайн':'Был: '.panelOut($u['last_seen_at']?:'нет данных') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section id="partners" class="panel"><div class="table-wrap"><table><thead><tr><th>Мерчант</th><th>Домен</th><th>Владелец</th><th>Статус</th><th>Создан</th></tr></thead><tbody><?php foreach($partners as $p): ?><tr><td><strong><?= panelOut($p['company_name']) ?></strong><br><span class="mono"><?= panelOut($p['public_id']) ?></span></td><td><?= panelOut($p['domain']) ?></td><td><?= panelOut($p['username']?:$p['contact_email']) ?></td><td><span class="pill"><?= panelOut($p['status']) ?></span></td><td><?= panelOut($p['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section id="api" class="panel"><div class="table-wrap"><table><thead><tr><th>Операция</th><th>Мерчант</th><th>Тип</th><th>Сумма</th><th>Статус</th><th>Время</th></tr></thead><tbody><?php foreach($apiTransactions as $t): ?><tr><td class="mono"><?= panelOut($t['public_id']) ?></td><td><?= panelOut($t['company_name']?:'—') ?></td><td><?= panelOut($t['type']) ?><?= $t['is_simulated']?' (test)':'' ?></td><td><?= panelAmount($t['amount']) ?> <?= panelOut($t['currency']) ?></td><td><?= panelOut($t['status']) ?></td><td><?= panelOut($t['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section id="transfers" class="panel"><div class="table-wrap"><table><thead><tr><th>Пользователь</th><th>Направление</th><th>Детали</th><th>Время</th></tr></thead><tbody><?php foreach($transfers as $t): ?><tr><td>#<?= (int)$t['user_id'] ?> <?= panelOut($t['username']) ?></td><td><?= panelOut($t['title']) ?></td><td><?= panelOut($t['body']) ?></td><td><?= panelOut($t['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section id="p2p" class="panel"><div class="table-wrap"><table><thead><tr><th>Ордер</th><th>Стороны</th><th>MATEN</th><th>Фиат</th><th>Статус</th><th>Время</th></tr></thead><tbody><?php foreach($p2pOrders as $o): ?><tr><td class="mono"><?= panelOut($o['public_id']) ?></td><td><?= panelOut($o['seller']) ?> → <?= panelOut($o['buyer']) ?></td><td><?= panelAmount($o['amount_maten']) ?></td><td><?= panelAmount($o['total_fiat']) ?> <?= panelOut($o['currency']) ?></td><td><span class="pill"><?= panelOut($o['status']) ?></span></td><td><?= panelOut($o['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</main><script>document.querySelectorAll('[data-tab]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('.panel').forEach(x=>x.classList.toggle('active',x.id===b.dataset.tab));}));</script></body></html>
