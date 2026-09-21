<?php
declare(strict_types=1);

// Public developer overview page. Maten Pay API is self-service: every
// logged-in maten.pro user issues their own key pair instantly from
// Menu -> API, no admin approval step. This page used to also host a
// "request API access" form backed by a pending_review/admin-approval
// partner flow, but that contradicted the self-service model shown inside
// the app, so it's gone — this is now a plain, static overview + docs link.

$lang = (string) ($_GET['lang'] ?? 'ru');
if (!in_array($lang, ['ru', 'kk', 'en'], true)) {
    $lang = 'ru';
}

$T = [
    'ru' => [
        'nav_overview' => 'Обзор', 'nav_docs' => 'Документация API', 'nav_back' => '← на maten.pro',
        'title' => 'Maten Pay API',
        'lead' => 'Принимайте MATEN как способ оплаты на своём сайте. Ключи выдаются мгновенно и без модерации — специального запроса или одобрения администратором не требуется.',
        'how_title' => 'Как получить ключи',
        'how_1' => 'Войдите (или зарегистрируйтесь) в свой аккаунт на <a href="https://maten.pro/">maten.pro</a>.',
        'how_2' => 'Откройте <strong>Меню → API</strong>.',
        'how_3' => 'Нажмите «Сгенерировать ключ» — вы сразу получите пару <code>pk_live_…</code> / <code>sk_live_…</code>. Никакой заявки и ожидания одобрения.',
        'cta' => 'Открыть Meню → API',
        'docs_title' => 'Дальше — документация',
        'docs_text' => 'Полное описание эндпоинтов, подпись запросов (HMAC) и коды ошибок — в <a href="/developers/docs.php?lang={lang}">документации API</a>.',
    ],
    'kk' => [
        'nav_overview' => 'Шолу', 'nav_docs' => 'API құжаттамасы', 'nav_back' => '← maten.pro сайтына',
        'title' => 'Maten Pay API',
        'lead' => 'MATEN-ды өз сайтыңызда төлем әдісі ретінде қабылдаңыз. Кілттер лезде және модерациясыз беріледі — арнайы өтінім немесе әкімшінің мақұлдауы қажет емес.',
        'how_title' => 'Кілттерді қалай алуға болады',
        'how_1' => '<a href="https://maten.pro/">maten.pro</a> сайтындағы аккаунтыңызға кіріңіз (немесе тіркеліңіз).',
        'how_2' => '<strong>Меню → API</strong> бөлімін ашыңыз.',
        'how_3' => '«Кілт жасау» батырмасын басыңыз — бірден <code>pk_live_…</code> / <code>sk_live_…</code> жұбын аласыз. Ешқандай өтінім немесе мақұлдауды күту жоқ.',
        'cta' => 'Меню → API ашу',
        'docs_title' => 'Одан әрі — құжаттама',
        'docs_text' => 'Эндпоинттердің толық сипаттамасы, сұранысқа қол қою (HMAC) және қате кодтары — <a href="/developers/docs.php?lang={lang}">API құжаттамасында</a>.',
    ],
    'en' => [
        'nav_overview' => 'Overview', 'nav_docs' => 'API Docs', 'nav_back' => '← maten.pro',
        'title' => 'Maten Pay API',
        'lead' => 'Accept MATEN as a payment method on your site. Keys are issued instantly with no moderation — there is no request form and nothing to wait on an admin to approve.',
        'how_title' => 'How to get your keys',
        'how_1' => 'Log in (or sign up) at <a href="https://maten.pro/">maten.pro</a>.',
        'how_2' => 'Open <strong>Menu → API</strong>.',
        'how_3' => 'Tap "Generate key" — you get a <code>pk_live_…</code> / <code>sk_live_…</code> pair right away. No application, nothing to wait on.',
        'cta' => 'Open Menu → API',
        'docs_title' => 'Next — the docs',
        'docs_text' => 'Full endpoint reference, request signing (HMAC), and error codes are in the <a href="/developers/docs.php?lang={lang}">API documentation</a>.',
    ],
];
$t = $T[$lang];
function raw(string $s): string { return $s; }
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maten Developers</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', -apple-system, Segoe UI, Roboto, sans-serif; background: #0a0a0a; color: #e6e6e6; margin: 0; overflow-x: hidden; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 32px 20px 80px; }
  .top-bar { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,.08); flex-wrap: wrap; }
  .top-bar img { width: 34px; height: 34px; border-radius: 9px; }
  .top-bar strong { font-size: 17px; }
  .lang-switch { margin-left: auto; display: flex; gap: 6px; }
  .lang-switch a { color: rgba(255,255,255,.55); text-decoration: none; font-size: 13px; padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(255,255,255,.12); }
  .lang-switch a.is-active { color: #00ff66; border-color: rgba(0,255,102,.4); background: rgba(0,255,102,.08); }
  h1 { font-size: 30px; margin: 0 0 6px; }
  .lead { color: #9aa0a6; margin: 0 0 32px; line-height: 1.5; }
  nav a { color: rgba(255,255,255,.6); text-decoration: none; margin-right: 18px; font-size: 14px; }
  nav a:hover { color: #00ff66; }
  a { color: #00ff66; }
  .card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 28px; margin-top: 24px; }
  .card h2 { font-size: 18px; margin: 0 0 16px; }
  .card ol { color: #c7cad1; line-height: 1.9; padding-left: 20px; margin: 0; }
  .cta { display: inline-block; margin-top: 22px; background: #00ff66; color: #0a0a0a; border: none; border-radius: 20px; padding: 12px 22px; font-size: 15px; font-weight: 600; text-decoration: none; }
  code { background: rgba(255,255,255,.06); padding: 2px 6px; border-radius: 4px; font-size: 13px; word-break: break-word; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <img src="/logo.png" alt="Maten" onerror="this.style.display='none'">
    <strong>Maten Pay</strong>
    <div class="lang-switch">
      <a href="?lang=kk" class="<?= $lang === 'kk' ? 'is-active' : '' ?>">ҚАЗ</a>
      <a href="?lang=ru" class="<?= $lang === 'ru' ? 'is-active' : '' ?>">РУС</a>
      <a href="?lang=en" class="<?= $lang === 'en' ? 'is-active' : '' ?>">ENG</a>
    </div>
  </div>
  <nav><a href="/developers/?lang=<?= htmlspecialchars($lang) ?>"><?= raw($t['nav_overview']) ?></a><a href="/developers/docs.php?lang=<?= htmlspecialchars($lang) ?>"><?= raw($t['nav_docs']) ?></a><a href="/"><?= raw($t['nav_back']) ?></a></nav>
  <h1><?= raw($t['title']) ?></h1>
  <p class="lead"><?= raw($t['lead']) ?></p>

  <div class="card">
    <h2><?= raw($t['how_title']) ?></h2>
    <ol>
      <li><?= raw($t['how_1']) ?></li>
      <li><?= raw($t['how_2']) ?></li>
      <li><?= raw($t['how_3']) ?></li>
    </ol>
    <a class="cta" href="https://maten.pro/?page=api"><?= raw($t['cta']) ?></a>
  </div>

  <div class="card">
    <h2><?= raw($t['docs_title']) ?></h2>
    <p style="color:#c7cad1;margin:0;"><?= raw(str_replace('{lang}', $lang, $t['docs_text'])) ?></p>
  </div>
</div>
</body>
</html>
