# Proby.fun интеграциясының аудиті және жойылуы

Дата: 2026-08-14

## 1. Табылған жерлер

Кодтан "proby" (регистрге тәуелсіз) бойынша толық іздеу жасалды: `MatenWeb` және `ProbiFun` репозиторийлерінің барлық `.php`/`.js`/`.json`/`.sql` файлдары.

### MatenWeb
- Хардкодталған партнер-логика (`if (partner === 'proby')` секілді) **табылмады** — жүйе бастан бастап партнер-агностикалық (`api_partners` кестесі, HMAC auth, партнер аты тек лог/notification мәтінінде қолданылады).
- Комментарийлерде екі жер: [api_v1.php:5](api_v1.php#L5), [includes/api_gateway.php:6](includes/api_gateway.php#L6) — "e.g. proby.fun" деген мысал сөзі.
- Дерекқор: `api_partners` кестесінде **1 жазба** (`id=1, name='probifun'`).
- Байланысты кестелер: `api_transactions` (10 жазба, 1151.00000000 MATEN), `api_topup_codes` (16 жазба), `api_idempotency_keys` (10 жазба) — барлығы `partner_id=1` арқылы байланысқан.

### ProbiFun
- `api/maten-topup-request.php`, `api/maten-topup-confirm.php` — Maten API-ге HMAC-қолтаңбаланған сұраныс жіберетін endpoint-тер.
- `api/_bootstrap.php` — `MATEN_API_KEY`/`MATEN_HMAC_SECRET` константалары (`api/config.local.php`-тен оқылады, git-ке ешқашан салынбайды).
- `index.php`/`app.js` — "Maten Wallet" батырмасы мен UI ағыны.
- Config/`.env`: `PROBY_API_KEY`, `PROBY_WEBHOOK_URL` секілді айнымалылар **табылмады** — керісінше Maten тарапынан берілген кілттер ProbiFun-да сақталған (`maten_api_key`, `maten_hmac_secret`), яғни бағыт бірыңғай: ProbiFun → Maten.

### Cron/webhook
- Проектте cron/scheduled job файлдары жоқ, webhook тіркелімі (Maten → Proby) жоқ.

## 2. Жойылған әрекет

- `api_partners.active` (`id=1, name='probifun'`) → **0** етіп ауыстырылды (revoke). Кілттер (`api_key`, `hmac_secret`) дерекқорда қалды, бірақ енді `apiGatewayAuthenticateRequest()` оларды `active=1` талап ететіндіктен қабылдамайды.
- Транзакция тарихы (`api_transactions`, `api_topup_codes`, `api_idempotency_keys`) **толық сақталды** — қаржылық аудит үшін жойылмады.
- Қолданушылардың `balance_maten`/`balance` мәндері (MATEN шешілген, ProbiFun балансына түскен) **өзгертілмеді** — бұл нақты орындалған транзакциялар, кері қайтарылмайды.

## 3. Растау

```
POST /api/v1/topup-code/request (ескі probifun api_key/hmac_secret арқылы)
→ 401 {"success":false,"error_code":"invalid_api_key"}
```

Ескі кілттер енді ешбір endpoint-ке қолжетімсіз. ProbiFun-дағы "Maten Wallet" батырмасы қазірден бастап пайдаланушыға қате хабарлама көрсетеді (`maten_invalid_api_key` → жалпы серверлік қате ретінде).

## 4. Толық жою (2026-08-27 жаңартуы)

Бастапқы revoke-тен кейін толық жоюға шешім қабылданды:

- `api_partners` кестесіндегі `id=1, name='probifun'` жазбасы **толық өшірілді** (`DELETE`, тек `active=0` емес). Транзакция тарихы (`api_transactions`, `api_topup_codes`, `api_idempotency_keys`, барлығы 10 жазба / 1151 MATEN) **сол қалпында сақталды** — олар `partner_id=1` мәнін FK шектеусіз сақтайды, сондықтан аудит үшін оқылымды күйде қалады.
- `api_v1.php` толық "deprecated" endpoint-ке айналдырылды: барлық маршрут, метод бойынша `410 {"error_code":"deprecated"}` қайтарады, ешбір бизнес-логика орындалмайды (`includes/api_gateway.php` енді шақырылмайды).
- Кодтан "proby" сөзі бойынша толық іздеу (`.php`/`.js`/`.json`, комментарийлер қоса) — **0 нәтиже**. (Бұл `audit-report.md` құжатының өзінде әдейі сақталды — тарихи жазба ретінде, талап тек код базасына қатысты.)

## 5. Салдары

ProbiFun-дың "Maten Wallet" батырмасы қазір жұмыс істемейді (`410 deprecated` жауабын алады) — бұл күтілген, қабылданған салдар. Функция **Бөлім 2c** (партнёрлерді admin арқылы approve ету) аяқталған соң, ProbiFun жаңа `/api/v2/*` жүйесіне серіктес ретінде қайта тіркеліп, жаңа `pk_live_.../sk_live_...` кілт жұбын алғаннан кейін қалпына келтіріледі.
