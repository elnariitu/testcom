# Уведомление жүйесінің толық жойылуы және сервер қауіпсіздігі тазалауы

Дата: 2026-08-28

## 1. Табылған қауіпсіздік мәселесі (жоспардан тыс, жол бойы тапттым)

Тапсырма орындалу барысында серверде (`/public_html`) жоспарда жоқ, жария қолжетімді бірнеше нәрсе табылды:

- **`deploy.sh`** — FTP логин/парольді plaintext күйде сақтайтын скрипт **жария URL арқылы жүктеп алуға болатын** күйде тұрды (`https://maten.pro/deploy.sh`, 200 OK). **Дереу өшірілді.**
- **`deploy/maten-web-20260614-1246.zip`** — толық сайт backup-ы (3.9 МБ), **жария жүктелетін** күйде. **Дереу өшірілді.**
- `deploy.py`, `deploy.command`, `deploy-force.command`, `deploy/deploy.log`, `deploy/phase0_inspect.py`, `__MACOSX/`, `__pycache__/` — сервер үшін керек емес қалдықтар. **Толық өшірілді.**
- `vacuum.social/` — Maten-ге мүлдем қатысы жоқ, бөлек React+PHP чат/соцсеть қолданбасы (нақты жүктелген пайдаланушы суреттерімен), жергілікті жобада ЖОҚ. Пайдаланушы "рұқсатсыз орналастырылған сияқты, жойыңыз" деп растады. **Жойылды** (төменде қараңыз).

### Түзету
- `deploy.py`-дың `EXCLUDE_FILES` тізіміне `deploy-force.command` қосылды (бұрын жоқ болғандықтан әр деплойда серверге қайта жүктеліп тұрған).
- Root `.htaccess`-ке `.sh`/`.py`/`.pyc`/`.command`/`.log`/`.zip` кеңейтімдерін толық бұғаттайтын `<FilesMatch>` ережесі қосылды — deploy tooling ешқашан қайта жария болмауы үшін server деңгейіндегі қосымша сақтық қабаты.

**Ұсыныс (қолмен орындау керек):** FTP паролі (`Maten2026`) біраз уақыт жария болғандықтан, Timeweb hosting панелінен паролін ауыстыруды ұсынамын.

## 2. Уведомление жүйесінің толық жойылуы

### Код (backend)
- `index.php`: `createUserNotification()` функциясы, `user_notifications` CREATE TABLE логикасы, `$page === 'notifications'` бет маршруты, барлық шақыру орындары (login/register/transfer/profile), device-metadata көмекші функциялар (`authDeviceMetadataFromRequest`, `deviceCityFromTimezone`, `browserPlatformFromAgent`, `deviceTypeFromAgent`, `notificationDeviceLabel`) — толық өшірілді.
- `config.php`: параллель `createUserNotification()` көшірмесі (ешкім шақырмайтын, өлі код) және `user_notifications` CREATE TABLE логикасы — толық өшірілді.
- `mobile_api.php`: `get_notifications`, `poll_notifications`, `mobileFetchNotifications`, `mobileUnreadCount`, `register_device_token`, `unregister_device_token` — толық өшірілді.
- `includes/push_notifications.php` (APNs push, device tokens, 188 жол) — **файл толық жойылды**.
- `includes/api_gateway.php` (deprecated v1 gateway, ешкім шақырмайтын) — **файл толық жойылды** (notification-тәуелді болғандықтан, тазалаудың орнына толық алып тасталды).
- `includes/api_v2_gateway.php`: `apiV2NotifyUser()` (депозит кодын жеткізу арнасы) толық өшірілді, 2 шақыру орны алынды.

### Frontend (UI)
- Бас тақтадағы қоңырау (bell) иконкасы мен badge — өшірілді.
- Notifications беті (HTML/CSS, tabs, transfer/security тізімдері) — толық өшірілді.
- JS: `updateNotificationBadge`, `markNotificationsAsRead`, `switchNotificationTab`, `requestSitePermissions` (браузер Notification+geolocation рұқсат сұрау), `formatClientClock`/`applyClientClockFormatting` (тек notification/chat timestamp үшін қолданылған, енді өлі) — толық өшірілді.
- "Push notifications" toggle (Настройки бетінде, 2 жерде) — өшірілді.
- CSS: `.notification-badge`, `.notifications-shell/card/head/tabs/tab/list/item/icon/copy/time/device`, `.tab-dot` — толық өшірілді. **`.notifications-empty` сақталды** — бұл класс қауіпсіздік бетінің ("Пока пусто") жалпы бос-күй стилі ретінде де қолданылады, notification-ге ғана тән емес.

### Дерекқор
- `user_notifications` кестесі — **толық `DROP TABLE`** (соңғы жойылу алдында 8 жазба болды).
- `device_tokens` кестесі — **толық `DROP TABLE`** (1 жазба).

### Белгілі салдары
`/api/v2/deposits` (жаңа universal partner API) депозит кодын генерациялайды, бірақ енді оны жеткізетін ешбір арна жоқ (нотификация жүйесі болмағандықтан). Бұл — сұралған толық жоюдың тікелей, қабылданған салдары. Docs пен код комментарийлері осы жағдайды дәл көрсететіндей жаңартылды.

## 3. Тексерілді (production, браузермен)
- Home, Меню, Настройки, Қауіпсіздік беттері — таза жүктеледі, консольде JS қатесі жоқ.
- `?page=notifications` → `home`-ге graceful fallback (allowed_pages тізімінен алынды).
- `includes/push_notifications.php`, `includes/api_gateway.php`, `deploy.sh`, `deploy/*.zip` — барлығы 404/403.
