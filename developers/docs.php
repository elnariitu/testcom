<?php
declare(strict_types=1);

$lang = (string) ($_GET['lang'] ?? 'ru');
if (!in_array($lang, ['ru', 'kk', 'en'], true)) {
    $lang = 'ru';
}

$T = [
    'ru' => [
        'nav_overview' => 'Обзор', 'nav_docs' => 'Документация API', 'nav_back' => '← на maten.pro',
        'title' => 'Maten API v2',
        'intro' => 'Универсальный API для партнёров — любой одобренный партнёр использует одни и те же эндпоинты со своей парой ключей. Кода, специфичного под конкретного партнёра, в этом API нет.',
        'h_quickstart' => 'Быстрый старт',
        'step1' => '1. Войдите в свой аккаунт на <a href="https://maten.pro/">maten.pro</a> и откройте <strong>Меню → API</strong> — ключи (<code>pk_test_…</code> / <code>sk_test_…</code>) выдаются сразу, без модерации.',
        'step2' => '2. Подписывайте каждый запрос:',
        'step3' => '3. Отправьте его:',
        'h_curl' => 'Пример на curl (полный цикл, bash)',
        'h_auth_headers' => 'Заголовки аутентификации',
        'th_header' => 'Заголовок', 'th_meaning' => 'Значение',
        'auth_api_key' => 'Ваш публичный ключ (<code>pk_test_…</code> / <code>pk_live_…</code>)',
        'auth_authorization' => '<code>Bearer sk_test_…</code> / <code>Bearer sk_live_…</code> — ваш секретный ключ. Только по HTTPS, никогда в URL.',
        'auth_timestamp' => 'Unix-время в секундах. Запросы старше 300 сек отклоняются.',
        'auth_nonce' => 'Случайная строка 8–64 символа, уникальная для каждого запроса (защита от повтора).',
        'auth_signature' => 'Hex HMAC-SHA256 от <code>method\npath\nraw_body\ntimestamp\nnonce</code>, подписанный вашим секретным ключом.',
        'auth_idempotency' => 'Отдельный HTTP-заголовок (не поле JSON-тела и не часть подписи) для денежных операций — повторный запрос с тем же ключом вернёт исходный результат, а не выполнится заново.',
        'test_keys_note' => 'Тестовые ключи (<code>sk_test_…</code>) никогда не двигают реальный MATEN — ответы <code>/v2/transfers-out</code> содержат <code>"simulated": true</code>, баланс не меняется.',
        'rate_limit_badge' => 'лимит запросов', 'rate_limit_text' => '100 запросов / 60 сек на ключ.',
        'scopes_badge' => 'права доступа', 'scopes_text' => 'у каждого ключа есть только те права, что были выданы — ключа «всё сразу» не существует.',
        'h_endpoints' => 'Эндпоинты',
        'ep_partners_badge' => 'публичный, без авторизации',
        'ep_partners_desc' => 'Регистрирует заявку на партнёрский аккаунт с ручным одобрением администратором (статус <code>pending_review</code>). Для обычного использования API он не нужен — self-service ключи выдаются мгновенно и без модерации через <strong>Меню → API</strong> в самом приложении maten.pro.',
        'ep_deposits_badge' => 'право: deposit:create',
        'ep_deposits_desc' => 'Инициирует депозит для одного из ваших пользователей. <strong>В этом вызове деньги ещё не двигаются</strong> — создаётся одноразовый код и отправляется владельцу кошелька в его собственные уведомления Maten. <strong>Код никогда не возвращается вам</strong> (только <code>code_length</code>) — пользователь должен сам прочитать его в своём аккаунте и ввести на вашей странице оплаты. Это гарантирует, что <code>transfers-out</code> нельзя вызвать без реального участия владельца кошелька. Вместо <code>amount</code> можно передать <code>fiat_amount</code> + <code>fiat_currency</code> (KZT/RUB/USD) — сумма в MATEN посчитается автоматически по текущему курсу; ответ вернёт <code>amount_maten</code> и <code>rate_used</code>.',
        'ep_transfers_badge' => 'право: transfer:create',
        'ep_transfers_desc' => 'Погашает код, полученный пользователем от Maten, и переводит сумму, <em>зафиксированную на момент создания депозита</em>, на <strong>ваш собственный</strong> эскроу-счёт (или сразу на ваш Maten-кошелёк, если ваш API-ключ — самообслуживаемый). Получатель никогда не передаётся параметром запроса — он определяется по вашему API-ключу, поэтому перенаправить средства куда-то ещё невозможно.',
        'ep_payouts_badge' => 'право: payout:create',
        'ep_payouts_desc' => 'Отправляет MATEN из вашего собственного баланса (ваш Maten-кошелёк для self-service ключа, либо ваш эскроу-счёт для партнёра с одобрением администратора) на любой адрес Maten-кошелька. Это обратная сторона депозитов — полноценный вывод/выплата, а не только приём. Идемпотентен так же, как остальные денежные вызовы.',
        'ep_tx_badge' => 'право: transaction:read',
        'ep_tx_desc' => 'Возвращает транзакцию, созданную вами. Область действия ограничена вашим партнёром — читать чужие транзакции нельзя.',
        'ep_webhooks_post_badge' => 'право: webhook:manage',
        'ep_webhooks_post_desc' => '<code>hmac_secret</code> возвращается один раз, при создании — сохраните его, чтобы проверять подписи входящих вебхуков.',
        'ep_webhooks_get_desc' => 'Список ваших зарегистрированных вебхуков (секреты повторно не возвращаются после создания), с пагинацией: limit (по умолчанию 20, максимум 100) и page.',
        'ep_tx_list_badge' => 'право: transaction:read',
        'ep_tx_list_desc' => 'Список ваших транзакций (новые сначала), с пагинацией: limit (по умолчанию 20, максимум 100) и page.',
        'ep_webhooks_delete_desc' => 'Деактивирует один из ваших вебхуков.',
        'h_errors' => 'Коды ошибок',
        'th_http' => 'HTTP', 'th_error_code' => 'error_code',
        'h_webhook_sig' => 'Подписи вебхуков',
        'webhook_sig_text' => 'Каждая доставка вебхука подписывается так же, как входящие запросы: заголовок <code>X-Signature</code> содержит <code>hex(HMAC-SHA256(raw_body, ваш_webhook_hmac_secret))</code>. Проверяйте её перед тем, как доверять содержимому.',
        'errors' => [
            'missing_auth_headers' => 'Отсутствует один из обязательных заголовков.',
            'invalid_api_key' => 'Неизвестный или неактивный публичный ключ.',
            'invalid_credentials' => 'Секретный ключ не совпадает или неверный префикс окружения.',
            'stale_timestamp' => '<code>X-Timestamp</code> отличается более чем на 300 сек.',
            'invalid_signature' => 'HMAC-подпись не совпадает.',
            'partner_not_approved' => 'Ваш партнёрский аккаунт ещё не <code>approved</code>.',
            'insufficient_scope' => 'У вашего ключа нет прав, нужных этому эндпоинту.',
            'ip_not_allowed' => 'IP вызывающего не в белом списке этого ключа.',
            'replayed_request' => 'Эта пара (ключ, nonce) уже была использована.',
            'insufficient_funds' => 'На кошельке недостаточно MATEN.',
            'self_transfer' => 'Нельзя отправить выплату на собственный адрес кошелька.',
            'not_found' => 'Нет подходящей записи для вашего партнёра.',
            'code_expired' => 'Истёк срок действия кода депозита (5 мин) — создайте новый депозит.',
            'rate_limited' => 'Слишком много запросов, повторите позже.',
        ],
    ],
    'kk' => [
        'nav_overview' => 'Шолу', 'nav_docs' => 'API құжаттамасы', 'nav_back' => '← maten.pro сайтына',
        'title' => 'Maten API v2',
        'intro' => 'Серіктестерге арналған universal API — кез келген мақұлданған серіктес бірдей эндпоинттерді өз кілт жұбымен қолданады. Бұл API-де нақты серіктеске арналған код жоқ.',
        'h_quickstart' => 'Жылдам бастау',
        'step1' => '1. <a href="https://maten.pro/">maten.pro</a> сайтындағы аккаунтыңызға кіріп, <strong>Меню → API</strong> бөлімін ашыңыз — кілттер (<code>pk_test_…</code> / <code>sk_test_…</code>) бірден, модерациясыз беріледі.',
        'step2' => '2. Әр сұранысқа қол қойыңыз:',
        'step3' => '3. Жіберіңіз:',
        'h_curl' => 'curl мысалы (толық цикл, bash)',
        'h_auth_headers' => 'Аутентификация тақырыптары',
        'th_header' => 'Тақырып', 'th_meaning' => 'Мағынасы',
        'auth_api_key' => 'Сіздің ашық кілтіңіз (<code>pk_test_…</code> / <code>pk_live_…</code>)',
        'auth_authorization' => '<code>Bearer sk_test_…</code> / <code>Bearer sk_live_…</code> — құпия кілтіңіз. Тек HTTPS арқылы, ешқашан URL ішінде емес.',
        'auth_timestamp' => 'Unix уақыты, секундпен. 300 секундтан ескі сұраныстар қабылданбайды.',
        'auth_nonce' => 'Әр сұраныс үшін бірегей 8–64 таңбалы кездейсоқ жол (қайталанудан қорғау).',
        'auth_signature' => '<code>method\npath\nraw_body\ntimestamp\nnonce</code> тізбегінің құпия кілтіңізбен қол қойылған hex HMAC-SHA256 мәні.',
        'auth_idempotency' => 'Ақша қозғалатын шақыруларда бөлек HTTP тақырыбы (JSON денесінің өрісі емес және қолтаңбаның бөлігі емес) — сол кілтпен қайталанған сұраныс қайта орындалмай, бастапқы нәтижені қайтарады.',
        'test_keys_note' => 'Тест кілттері (<code>sk_test_…</code>) шынайы MATEN-ды ешқашан қозғамайды — <code>/v2/transfers-out</code> жауаптарында <code>"simulated": true</code> болады, баланс өзгермейді.',
        'rate_limit_badge' => 'сұраныс лимиті', 'rate_limit_text' => 'бір кілтке 100 сұраныс / 60 секунд.',
        'scopes_badge' => 'құқықтар', 'scopes_text' => 'әр кілтте тек соған берілген құқықтар бар — «бәрін істей алатын» кілт жоқ.',
        'h_endpoints' => 'Эндпоинттер',
        'ep_partners_badge' => 'жария, авторизациясыз',
        'ep_partners_desc' => 'Әкімші қолмен мақұлдайтын серіктес аккаунтына өтінім тіркейді (күйі <code>pending_review</code>). Әдеттегі API қолдану үшін ол қажет емес — self-service кілттер maten.pro қосымшасындағы <strong>Меню → API</strong> арқылы лезде және модерациясыз беріледі.',
        'ep_deposits_badge' => 'құқық: deposit:create',
        'ep_deposits_desc' => 'Қолданушыларыңыздың бірі үшін депозитті бастайды. <strong>Бұл шақыруда ақша әлі қозғалмайды</strong> — бір реттік код жасалады және әмиян иесінің өз Maten хабарландыруларына жіберіледі. <strong>Код сізге ешқашан қайтарылмайды</strong> (тек <code>code_length</code>) — қолданушы оны өз аккаунтынан оқып, сіздің төлем бетіңізге өзі енгізуі керек. Бұл <code>transfers-out</code> шақыруын әмиян иесінің нақты қатысуынсыз шақыру мүмкін еместігіне кепілдік береді. <code>amount</code> орнына <code>fiat_amount</code> + <code>fiat_currency</code> (KZT/RUB/USD) жіберуге болады — MATEN сомасы ағымдағы курс бойынша автоматты есептеледі; жауапта <code>amount_maten</code> және <code>rate_used</code> қайтарылады.',
        'ep_transfers_badge' => 'құқық: transfer:create',
        'ep_transfers_desc' => 'Қолданушы Maten-нен алған кодты өтейді және <em>депозит жасалған кезде бекітілген</em> соманы <strong>өз</strong> эскроу-шотыңызға (немесе API-кілтіңіз өзіндік қызмет көрсету түрінде болса, тікелей Maten-әмияныңызға) аударады. Алушы ешқашан сұраныс параметрі ретінде берілмейді — ол сіздің API-кілтіңіз бойынша анықталады, сондықтан қаражатты басқа жаққа бағыттау мүмкін емес.',
        'ep_payouts_badge' => 'құқық: payout:create',
        'ep_payouts_desc' => 'Өз балансыңыздан (self-service кілт үшін — Maten-әмияныңыздан, әкімші мақұлдаған серіктес үшін — эскроу-шотыңыздан) кез келген Maten-әмиян адресіне MATEN жібереді. Бұл депозиттердің керісінше жағы — тек қабылдау емес, толыққanды шығару/төлем. Басқа ақша қозғалатын шақырулар сияқты идемпотентті.',
        'ep_tx_badge' => 'құқық: transaction:read',
        'ep_tx_desc' => 'Сіз жасаған транзакцияны қайтарады. Тек өз серіктесіңізбен шектелген — басқа серіктестің транзакцияларын оқи алмайсыз.',
        'ep_webhooks_post_badge' => 'құқық: webhook:manage',
        'ep_webhooks_post_desc' => '<code>hmac_secret</code> тек жасалған кезде бір рет қайтарылады — келетін webhook қолтаңбаларын тексеру үшін оны сақтап қойыңыз.',
        'ep_webhooks_get_desc' => 'Тіркелген webhook-тарыңыздың тізімі (құпиялар жасалғаннан кейін қайта қайтарылмайды), беттеумен: limit (әдепкі 20, максимум 100) және page.',
        'ep_tx_list_badge' => 'құқық: transaction:read',
        'ep_tx_list_desc' => 'Транзакцияларыңыздың тізімі (жаңасы алдымен), беттеумен: limit (әдепкі 20, максимум 100) және page.',
        'ep_webhooks_delete_desc' => 'Webhook-тарыңыздың бірін өшіреді.',
        'h_errors' => 'Қате кодтары',
        'th_http' => 'HTTP', 'th_error_code' => 'error_code',
        'h_webhook_sig' => 'Webhook қолтаңбалары',
        'webhook_sig_text' => 'Әр webhook жеткізілімі кіріс сұраныстар сияқты қол қойылады: <code>X-Signature</code> тақырыбында <code>hex(HMAC-SHA256(raw_body, webhook_hmac_secret-іңіз))</code> болады. Мазмұнға сенбес бұрын оны тексеріңіз.',
        'errors' => [
            'missing_auth_headers' => 'Міндетті тақырыптардың бірі жоқ.',
            'invalid_api_key' => 'Белгісіз немесе белсенді емес ашық кілт.',
            'invalid_credentials' => 'Құпия кілт сәйкес келмейді немесе орта префиксі қате.',
            'stale_timestamp' => '<code>X-Timestamp</code> 300 секундтан артық ауытқыды.',
            'invalid_signature' => 'HMAC қолтаңбасы сәйкес келмейді.',
            'partner_not_approved' => 'Серіктес аккаунтыңыз әлі <code>approved</code> емес.',
            'insufficient_scope' => 'Кілтіңізде осы эндпоинтке қажетті құқық жоқ.',
            'ip_not_allowed' => 'Шақырушының IP-і осы кілттің ақ тізімінде жоқ.',
            'replayed_request' => 'Бұл (кілт, nonce) жұбы бұрын қолданылған.',
            'insufficient_funds' => 'Әмиянда MATEN жеткіліксіз.',
            'self_transfer' => 'Төлемді өз әмиян адресіңізге жіберуге болмайды.',
            'not_found' => 'Серіктесіңіз үшін сәйкес жазба жоқ.',
            'code_expired' => 'Депозит кодының мерзімі (5 мин) өтті — жаңа депозит жасаңыз.',
            'rate_limited' => 'Сұраныстар тым жиі, кейінірек қайталаңыз.',
        ],
    ],
    'en' => [
        'nav_overview' => 'Overview', 'nav_docs' => 'API Docs', 'nav_back' => '← maten.pro',
        'title' => 'Maten API v2',
        'intro' => 'A universal partner API — any approved partner uses the same endpoints with their own key pair. There is no partner-specific code anywhere in this API.',
        'h_quickstart' => 'Quickstart',
        'step1' => '1. Log in at <a href="https://maten.pro/">maten.pro</a> and open <strong>Menu → API</strong> — key pairs (<code>pk_test_…</code> / <code>sk_test_…</code>) are issued instantly, no moderation.',
        'step2' => '2. Sign every request:',
        'step3' => '3. Send it:',
        'h_curl' => 'curl example (full round trip, bash)',
        'h_auth_headers' => 'Authentication headers',
        'th_header' => 'Header', 'th_meaning' => 'Meaning',
        'auth_api_key' => 'Your public key (<code>pk_test_…</code> / <code>pk_live_…</code>)',
        'auth_authorization' => '<code>Bearer sk_test_…</code> / <code>Bearer sk_live_…</code> — your secret key. HTTPS only, never in a URL.',
        'auth_timestamp' => 'Unix seconds. Requests older than 300s are rejected.',
        'auth_nonce' => '8–64 char random string, unique per request (replay guard).',
        'auth_signature' => 'Hex HMAC-SHA256 of <code>method\npath\nraw_body\ntimestamp\nnonce</code>, keyed with your secret key.',
        'auth_idempotency' => 'A separate HTTP header (not a JSON body field, and not part of the signature) for money-moving calls — a retried request with the same key returns the original result instead of running twice.',
        'test_keys_note' => 'Test keys (<code>sk_test_…</code>) never move real MATEN — <code>/v2/transfers-out</code> responses carry <code>"simulated": true</code> and no balance changes.',
        'rate_limit_badge' => 'rate limit', 'rate_limit_text' => '100 requests / 60s per key.',
        'scopes_badge' => 'scopes', 'scopes_text' => 'every key only has the scopes it was issued with — there is no "do everything" key.',
        'h_endpoints' => 'Endpoints',
        'ep_partners_badge' => 'public, no auth',
        'ep_partners_desc' => 'Registers a partner account application with manual admin approval (status <code>pending_review</code>). Not needed for normal API use — self-service keys are issued instantly with no moderation from <strong>Menu → API</strong> inside the maten.pro app.',
        'ep_deposits_badge' => 'scope: deposit:create',
        'ep_deposits_desc' => 'Initiates a deposit for one of your users. <strong>No money moves in this call</strong> — it mints a one-time code and delivers it to the wallet owner\'s own Maten notifications. <strong>The code is never returned to you</strong> (only <code>code_length</code>) — the user has to read it in their own account and type it into your checkout. This is what guarantees <code>transfers-out</code> can never be called without the real wallet owner\'s active participation. Instead of <code>amount</code> you can pass <code>fiat_amount</code> + <code>fiat_currency</code> (KZT/RUB/USD) — the MATEN amount is computed automatically at the current rate; the response echoes back <code>amount_maten</code> and <code>rate_used</code>.',
        'ep_transfers_badge' => 'scope: transfer:create',
        'ep_transfers_desc' => 'Redeems the code the user got from Maten and moves the amount <em>fixed at deposit time</em> into <strong>your own</strong> escrow account (or straight to your Maten wallet if your API key is a self-service one). The destination is never a request parameter — it\'s resolved from your API key, so there is no way to redirect funds elsewhere.',
        'ep_payouts_badge' => 'scope: payout:create',
        'ep_payouts_desc' => 'Sends MATEN out of your own balance (your Maten wallet for a self-service key, or your escrow account for an admin-approved business partner) to any Maten wallet address. This is the reverse side of deposits — a full send/withdraw, not just receiving. Idempotent the same way as the other money-moving calls.',
        'ep_tx_badge' => 'scope: transaction:read',
        'ep_tx_desc' => 'Looks up a transaction you created. Scoped to your own partner — you cannot read another partner\'s transactions.',
        'ep_webhooks_post_badge' => 'scope: webhook:manage',
        'ep_webhooks_post_desc' => 'The <code>hmac_secret</code> is returned once, at creation — store it to verify incoming webhook signatures.',
        'ep_webhooks_get_desc' => 'Lists your registered webhooks (secrets are never returned again after creation), paginated: limit (default 20, max 100) and page.',
        'ep_tx_list_badge' => 'scope: transaction:read',
        'ep_tx_list_desc' => 'Lists your transactions, newest first, paginated: limit (default 20, max 100) and page.',
        'ep_webhooks_delete_desc' => 'Deactivates one of your webhooks.',
        'h_errors' => 'Error codes',
        'th_http' => 'HTTP', 'th_error_code' => 'error_code',
        'h_webhook_sig' => 'Webhook signatures',
        'webhook_sig_text' => 'Every webhook delivery is signed the same way as inbound requests: an <code>X-Signature</code> header carries <code>hex(HMAC-SHA256(raw_body, your_webhook_hmac_secret))</code>. Verify it before trusting the payload.',
        'errors' => [
            'missing_auth_headers' => 'One of the required headers is missing.',
            'invalid_api_key' => 'Unknown or inactive public key.',
            'invalid_credentials' => 'Secret key doesn\'t match, or wrong environment prefix.',
            'stale_timestamp' => '<code>X-Timestamp</code> more than 300s off.',
            'invalid_signature' => 'HMAC signature doesn\'t match.',
            'partner_not_approved' => 'Your partner account isn\'t <code>approved</code> yet.',
            'insufficient_scope' => 'Your key doesn\'t have the scope this endpoint needs.',
            'ip_not_allowed' => 'Caller IP isn\'t on this key\'s whitelist.',
            'replayed_request' => 'This exact (key, nonce) pair was already used.',
            'insufficient_funds' => 'Wallet doesn\'t have enough MATEN.',
            'self_transfer' => 'Cannot pay out to your own wallet address.',
            'not_found' => 'No matching record for your partner.',
            'code_expired' => 'Deposit code TTL (5 min) elapsed — create a new deposit.',
            'rate_limited' => 'Slow down and retry later.',
        ],
    ],
];

$t = $T[$lang];

function raw(string $s): string {
    // Deliberately NOT htmlspecialchars — these strings carry trusted inline markup (<code>, <a>, <strong>, <em>).
    return $s;
}

$errorRows = [
    ['401', 'missing_auth_headers'],
    ['401', 'invalid_api_key'],
    ['401', 'invalid_credentials'],
    ['401', 'stale_timestamp'],
    ['401', 'invalid_signature'],
    ['403', 'partner_not_approved'],
    ['403', 'insufficient_scope'],
    ['403', 'ip_not_allowed'],
    ['409', 'replayed_request'],
    ['409', 'insufficient_funds'],
    ['422', 'self_transfer'],
    ['404', 'not_found', 'invalid_address / deposit_not_found / transaction_not_found'],
    ['410', 'code_expired'],
    ['429', 'rate_limited', 'rate_limited / too_many_attempts'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maten API Docs</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', -apple-system, Segoe UI, Roboto, sans-serif; background: #0a0a0a; color: #e6e6e6; margin: 0; line-height: 1.6; overflow-x: hidden; }
  .wrap { max-width: 820px; margin: 0 auto; padding: 32px 20px 100px; }
  .top-bar { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,.08); flex-wrap: wrap; }
  .top-bar img { width: 34px; height: 34px; border-radius: 9px; }
  .top-bar strong { font-size: 17px; }
  .lang-switch { margin-left: auto; display: flex; gap: 6px; }
  .lang-switch a { color: rgba(255,255,255,.55); text-decoration: none; font-size: 13px; padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(255,255,255,.12); }
  .lang-switch a.is-active { color: #00ff66; border-color: rgba(0,255,102,.4); background: rgba(0,255,102,.08); }
  nav { margin-bottom: 8px; }
  nav a { color: rgba(255,255,255,.6); text-decoration: none; margin-right: 18px; font-size: 14px; }
  nav a:hover { color: #00ff66; }
  h1 { font-size: 28px; margin: 28px 0 6px; }
  h2 { font-size: 20px; margin: 40px 0 10px; border-top: 1px solid rgba(255,255,255,.08); padding-top: 32px; }
  h3 { font-size: 15px; color: #9aa0a6; margin: 24px 0 8px; text-transform: uppercase; letter-spacing: 0.04em; }
  p, li { color: #c7cad1; }
  a { color: #00ff66; }
  code { background: rgba(255,255,255,.06); padding: 2px 6px; border-radius: 4px; font-size: 13px; color: #ffd479; word-break: break-word; }
  pre { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 16px; overflow-x: auto; font-size: 13px; max-width: 100%; }
  pre code { background: none; padding: 0; color: #d6d9e0; word-break: normal; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 13px; }
  th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid rgba(255,255,255,.08); vertical-align: top; }
  th { color: #9aa0a6; }
  .badge { display: inline-block; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; padding: 2px 10px; font-size: 12px; margin-right: 6px; }
  .method-post { color: #4fa8ff; font-weight: 700; }
  .method-get { color: #00ff66; font-weight: 700; }
  .method-delete { color: #ff5c5c; font-weight: 700; }
  @media (max-width: 480px) {
    table, thead, tbody, tr { display: block; }
    th { display: none; }
    tr { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.08); }
    td { display: block; border-bottom: none; padding: 2px 0; }
  }
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
  <p><?= raw($t['intro']) ?></p>

  <h2><?= raw($t['h_quickstart']) ?></h2>
  <p><?= raw($t['step1']) ?></p>
  <p><?= raw($t['step2']) ?></p>
  <pre><code>signature = HMAC_SHA256(
  method + "\n" + path + "\n" + raw_body + "\n" + timestamp + "\n" + nonce,
  secret_key
)</code></pre>
  <p><?= raw($t['step3']) ?></p>
  <pre><code>POST /api/v2/deposits HTTP/1.1
Host: maten.pro
Content-Type: application/json
X-Api-Key: pk_test_...
Authorization: Bearer sk_test_...
X-Timestamp: 1735689600
X-Nonce: a1b2c3d4e5f6a1b2
X-Signature: &lt;computed hex hmac&gt;
Idempotency-Key: 3f29a1c4-3b5e-4a2d-9c1f-7e8d6a5b4c3d

{"wallet_address":"...","amount":"10.00"}</code></pre>

  <h3><?= raw($t['h_curl']) ?></h3>
  <pre><code>PUBLIC_KEY=pk_test_...
SECRET_KEY=sk_test_...
PATH=/api/v2/deposits
BODY='{"wallet_address":"abcd1234abcd1234","amount":"10.00"}'
TS=$(date +%s)
NONCE=$(openssl rand -hex 8)
IDEMPOTENCY_KEY=$(uuidgen)
SIG=$(printf 'POST\n%s\n%s\n%s\n%s' "$PATH" "$BODY" "$TS" "$NONCE" | openssl dgst -sha256 -hmac "$SECRET_KEY" | sed 's/^.* //')

curl -s https://maten.pro$PATH \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: $PUBLIC_KEY" \
  -H "Authorization: Bearer $SECRET_KEY" \
  -H "X-Timestamp: $TS" \
  -H "X-Nonce: $NONCE" \
  -H "X-Signature: $SIG" \
  -H "Idempotency-Key: $IDEMPOTENCY_KEY" \
  -d "$BODY"</code></pre>

  <h2><?= raw($t['h_auth_headers']) ?></h2>
  <table>
    <tr><th><?= raw($t['th_header']) ?></th><th><?= raw($t['th_meaning']) ?></th></tr>
    <tr><td><code>X-Api-Key</code></td><td><?= raw($t['auth_api_key']) ?></td></tr>
    <tr><td><code>Authorization</code></td><td><?= raw($t['auth_authorization']) ?></td></tr>
    <tr><td><code>X-Timestamp</code></td><td><?= raw($t['auth_timestamp']) ?></td></tr>
    <tr><td><code>X-Nonce</code></td><td><?= raw($t['auth_nonce']) ?></td></tr>
    <tr><td><code>X-Signature</code></td><td><?= raw($t['auth_signature']) ?></td></tr>
    <tr><td><code>Idempotency-Key</code></td><td><?= raw($t['auth_idempotency']) ?></td></tr>
  </table>
  <p><?= raw($t['test_keys_note']) ?></p>

  <h2><span class="badge"><?= raw($t['rate_limit_badge']) ?></span><?= raw($t['rate_limit_text']) ?> <span class="badge"><?= raw($t['scopes_badge']) ?></span><?= raw($t['scopes_text']) ?></h2>

  <h2><?= raw($t['h_endpoints']) ?></h2>

  <h3><span class="method-post">POST</span> /api/v2/partners <span class="badge"><?= raw($t['ep_partners_badge']) ?></span></h3>
  <p><?= raw($t['ep_partners_desc']) ?></p>
  <pre><code>{ "company_name": "Acme Inc.", "domain": "example.com", "contact_email": "dev@example.com", "business_description": "..." }
→ { "success": true, "partner_id": "ptn_...", "status": "pending_review" }</code></pre>

  <h3><span class="method-post">POST</span> /api/v2/deposits <span class="badge"><?= raw($t['ep_deposits_badge']) ?></span></h3>
  <p><?= raw($t['ep_deposits_desc']) ?></p>
  <pre><code>Idempotency-Key: 3f29a1c4-...
{ "wallet_address": "abcd1234abcd1234", "amount": "10.00000000" }
→ { "success": true, "deposit_id": "dep_...", "amount_maten": "10.00000000", "expires_in": 300, "code_length": 6 }

// Or let Maten convert a fiat amount at the current cross-rate-consistent price:
{ "wallet_address": "abcd1234abcd1234", "fiat_amount": "200", "fiat_currency": "KZT" }
→ { "success": true, "deposit_id": "dep_...", "amount_maten": "40.00000000", "rate_used": "5.00000000", "expires_in": 300, "code_length": 6 }</code></pre>

  <h3><span class="method-post">POST</span> /api/v2/transfers-out <span class="badge"><?= raw($t['ep_transfers_badge']) ?></span></h3>
  <p><?= raw($t['ep_transfers_desc']) ?></p>
  <pre><code>Idempotency-Key: 7c14e9b2-...
{ "deposit_id": "dep_...", "code": "757597" }
→ { "success": true, "transaction_id": "tx2_...", "amount": "10.00000000", "currency": "MATEN", "status": "succeeded", "simulated": false, "created_at": "..." }</code></pre>

  <h3><span class="method-post">POST</span> /api/v2/payouts <span class="badge"><?= raw($t['ep_payouts_badge']) ?></span></h3>
  <p><?= raw($t['ep_payouts_desc']) ?></p>
  <pre><code>Idempotency-Key: a08d5f31-...
{ "wallet_address": "abcd1234abcd1234", "amount": "5.00000000" }
→ { "success": true, "transaction_id": "tx2_...", "type": "payout", "amount": "5.00000000", "currency": "MATEN", "status": "succeeded", "simulated": false, "created_at": "..." }

// fiat_amount + fiat_currency works here too, same as /deposits.</code></pre>

  <h3><span class="method-get">GET</span> /api/v2/transactions <span class="badge"><?= raw($t['ep_tx_list_badge']) ?></span></h3>
  <p><?= raw($t['ep_tx_list_desc']) ?></p>
  <pre><code>GET /api/v2/transactions?limit=20&amp;page=1
→ { "success": true, "transactions": [ { "transaction_id": "tx2_...", "type": "payout", "amount": "5.00000000", "currency": "MATEN", "status": "succeeded", "simulated": false, "created_at": "..." }, ... ], "pagination": { "page": 1, "limit": 20, "total": 42, "has_more": true } }</code></pre>

  <h3><span class="method-get">GET</span> /api/v2/transactions/{id} <span class="badge"><?= raw($t['ep_tx_badge']) ?></span></h3>
  <p><?= raw($t['ep_tx_desc']) ?></p>

  <h3><span class="method-post">POST</span> /api/v2/webhooks <span class="badge"><?= raw($t['ep_webhooks_post_badge']) ?></span></h3>
  <pre><code>{ "url": "https://example.com/webhooks/maten", "events": ["deposit.succeeded"] }
→ { "success": true, "webhook_id": "wh_...", "hmac_secret": "...", "url": "...", "events": [...] }</code></pre>
  <p><?= raw($t['ep_webhooks_post_desc']) ?></p>

  <h3><span class="method-get">GET</span> /api/v2/webhooks <span class="badge"><?= raw($t['ep_webhooks_post_badge']) ?></span></h3>
  <p><?= raw($t['ep_webhooks_get_desc']) ?></p>

  <h3><span class="method-delete">DELETE</span> /api/v2/webhooks/{id} <span class="badge"><?= raw($t['ep_webhooks_post_badge']) ?></span></h3>
  <p><?= raw($t['ep_webhooks_delete_desc']) ?></p>

  <h2><?= raw($t['h_errors']) ?></h2>
  <table>
    <tr><th><?= raw($t['th_http']) ?></th><th><?= raw($t['th_error_code']) ?></th><th><?= raw($t['th_meaning']) ?></th></tr>
    <?php foreach ($errorRows as $row): [$http, $key] = $row; $codeLabel = $row[2] ?? $key; ?>
    <tr><td><?= htmlspecialchars($http) ?></td><td><code><?= htmlspecialchars($codeLabel) ?></code></td><td><?= raw($t['errors'][$key]) ?></td></tr>
    <?php endforeach; ?>
  </table>

  <h2><?= raw($t['h_webhook_sig']) ?></h2>
  <p><?= raw($t['webhook_sig_text']) ?></p>
</div>
</body>
</html>
