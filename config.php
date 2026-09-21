<?php
// config.php - Общие настройки и функции для всех страниц

date_default_timezone_set('Asia/Almaty'); // UTC+5

ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();

require_once __DIR__ . '/includes/db_config.php';

try {
    $dbDsn = "mysql:host=$db_host" . ($db_port !== '' ? ";port=$db_port" : "") . ";dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dbDsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Maten database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection error.');
}

require_once __DIR__ . '/includes/notifications.php';

// --- Языковые настройки ---
$allowed_langs = ['kk', 'en', 'ru'];
$lang = $_SESSION['lang'] ?? 'ru';
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs, true)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
}

// --- Все переводы ---
$translations = [
    'ru' => [
        'site_title_home' => 'Maten • Современный Кошелек',
        'site_title_transfer' => 'Maten • Перевод',
        'site_title_menu' => 'Maten • Меню',
        'site_title_download' => 'Maten • Как скачать',
        'sign_in' => 'Вход',
        'register' => 'Регистрация',
        'login_title' => 'Вход',
        'register_title' => 'Регистрация',
        'no_account' => 'Нет аккаунта?',
        'has_account' => 'Уже есть аккаунт?',
        'register_cta' => 'Зарегистрироваться',
        'login_cta' => 'Войти',
        'login_field' => 'Email или username',
        'username_field' => 'Username',
        'email_field' => 'Email',
        'password_field' => 'Пароль',
        'confirm_password_field' => 'Подтверждение пароля',
        'passwords_do_not_match' => 'Пароли не совпадают',
        'verification_code_field' => 'Код подтверждения',
        'verification_code_sent' => 'Код подтверждения отправлен на почту',
        'enter_verification_code' => 'Введите код из письма',
        'wallet_address' => 'Адрес кошелька',
        'copy_address' => 'Скопировать адрес',
        'show_qr' => 'Показать QR',
        'qr_title' => 'Ваш QR-код',
        'qr_scan_hint' => 'Отсканируйте для перевода MATEN',
        'balance_title' => 'Ваш баланс',
        'owner_label' => 'Владелец',
        'wallet_brand' => 'MATEN WALLET',
        'transfer_banner_title' => 'Переведи MATEN другу!',
        'transfer_banner_text' => 'Мгновенные переводы без комиссии внутри сети Maten.',
        'transfer_banner_button' => 'Перевести',
        'nav_home' => 'Главный',
        'nav_transfer' => 'Перевод',
        'nav_menu' => 'Меню',
        'transfer_page_label' => 'Transfer',
        'transfer_page_title' => 'Перевод MATEN',
        'back' => 'Назад',
        'transfer_hero_label' => 'Maten Wallet',
        'transfer_hero_title' => 'Отправь MATEN внутри сети',
        'transfer_hero_text' => 'Быстрый внутренний перевод другому пользователю MATEN без комиссии.',
        'address_tab' => 'По адресу',
        'qr_tab' => 'Сканировать QR',
        'recipient_by_address' => 'Адрес получателя',
        'recipient_placeholder' => 'Введите MATEN address',
        'scan_qr_title' => 'Сканирование QR-кода',
        'scan_qr_instruction' => 'Наведите камеру на QR-код получателя',
        'start_scan' => 'Начать сканирование',
        'stop_scan' => 'Остановить',
        'qr_scanned' => 'QR отсканирован!',
        'recipient_card_label' => 'Получатель',
        'recipient_card_default' => 'Maten client',
        'recipient_card_hint' => 'Введите адрес, чтобы увидеть имя аккаунта',
        'transfer_amount' => 'Сумма перевода',
        'message_label' => 'Сообщение',
        'message_placeholder' => 'Например: Спасибо!',
        'quick_thanks' => 'Спасибо!',
        'quick_gift' => 'Тебе подарок, Maten',
        'quick_return' => 'Возвращаю долг',
        'commission' => 'Комиссия',
        'transfer_submit' => 'Перевести',
        'login_required_transfer' => 'Для перевода нужен аккаунт',
        'login_required_text' => 'Войдите в MATEN, чтобы открыть внутренние переводы и отправлять монеты другим пользователям.',
        'menu_label' => 'Profile',
        'menu_title' => 'Меню аккаунта',
        'menu_text' => 'Управление профилем, языком и будущими разделами MATEN.',
        'edit_profile' => 'Редактировать профиль',
        'change_avatar' => 'Изменить аватар',
        'remove_avatar' => 'Убрать аватар',
        'profile_title' => 'Профиль',
        'save_profile' => 'Сохранить изменения',
        'logout_action' => 'Выйти из аккаунта',
        'menu_sections_title' => 'Другие разделы',
        'menu_soon_wallet' => 'История переводов',
        'menu_soon_security' => 'Безопасность аккаунта',
        'menu_soon_support' => 'Поддержка',
        'menu_soon_hint' => 'Скоро здесь появятся дополнительные разделы MATEN.',
        'menu_download' => 'Как скачать?',
        'language_title' => 'Язык',
        'lang_kk' => 'Қазақша',
        'lang_en' => 'English',
        'lang_ru' => 'Русский',
        'account_created' => 'Регистрация успешна!',
        'login_success' => 'Вход выполнен',
        'logout_success' => 'Вы вышли из аккаунта',
        'invalid_email' => 'Некорректный email',
        'password_short' => 'Пароль должен быть не менее 6 символов',
        'username_short' => 'Username должен быть не менее 3 символов',
        'username_invalid' => 'Username может содержать только буквы, цифры, точку и подчёркивание',
        'email_exists' => 'Пользователь с таким email уже существует',
        'username_exists' => 'Такой username уже занят',
        'register_error' => 'Ошибка регистрации',
        'invalid_credentials' => 'Неверный логин или пароль',
        'connect_error' => 'Ошибка соединения',
        'login_first' => 'Сначала войдите или зарегистрируйтесь',
        'section_in_development' => 'Раздел "{key}" в разработке',
        'logout_confirm' => 'Выйти из аккаунта?',
        'address_copied' => 'Адрес скопирован!',
        'login_first_short' => 'Сначала войдите в аккаунт',
        'recipient_required' => 'Укажите адрес получателя',
        'amount_invalid' => 'Введите корректную сумму',
        'sender_not_found' => 'Аккаунт отправителя не найден',
        'recipient_not_found' => 'Получатель не найден',
        'self_transfer' => 'Нельзя отправить перевод самому себе',
        'not_enough_balance' => 'Недостаточно средств для перевода',
        'transfer_sent' => 'Перевод отправлен',
        'profile_updated' => 'Профиль обновлён',
        'save_error' => 'Не удалось сохранить изменения',
        'recipient_lookup_empty' => 'Введите адрес, чтобы увидеть имя аккаунта',
        'recipient_lookup_success' => 'Средства будут отправлены этому MATEN аккаунту',
        'transfer_success_title' => 'Перевод выполнен',
        'sent_amount_label' => 'Отправлено',
        'show_receipt' => 'Показать чек',
        'hide_receipt' => 'Скрыть чек',
        'close_action' => 'Закрыть',
        'receipt_title' => 'Чек перевода',
        'receipt_recipient' => 'Получатель',
        'receipt_amount' => 'Сумма',
        'receipt_message' => 'Сообщение',
        'receipt_time' => 'Время',
        'receipt_address' => 'Адрес',
        'receipt_transaction' => 'Операция №',
        'receipt_fee' => 'Комиссия',
        'receipt_sender' => 'Отправитель',
        'receipt_source' => 'Откуда',
        'receipt_empty_message' => 'Без сообщения',
        'confirm_transfer_title' => 'Подтверждение перевода',
        'confirm_transfer_message' => 'Вы уверены, что хотите перевести {amount} MATEN пользователю {recipient}?',
        'confirm_yes' => 'Да, перевести',
        'confirm_no' => 'Отмена',
        'transfers_section' => 'Переводы',
        'security_section' => 'Безопасность',
        'transfers_empty' => 'История переводов пуста',
        'transfer_sent_you' => 'Вы отправили',
        'transfer_received_you' => 'Вы получили',
        'download_title' => 'Установите Maten',
        'download_subtitle' => 'Как добавить приложение на устройство для быстрого доступа',
        'download_share_btn' => 'Поделиться приложением',
        'download_add_home_btn' => 'Добавить на главный экран',
        'download_android_title' => 'Android (Chrome)',
        'download_ios_title' => 'iOS (Safari)',
        'download_step_1' => 'Шаг 1: Откройте меню',
        'download_step_2' => 'Шаг 2: Нажмите "Добавить на главный экран"',
        'download_step_3' => 'Шаг 3: Подтвердите добавление',
        'download_android_step1_desc' => 'Нажмите на три точки в правом верхнем углу браузера.',
        'download_android_step2_desc' => 'В выпадающем меню выберите "Добавить на главный экран" или "Установить приложение".',
        'download_android_step3_desc' => 'Нажмите "Добавить автоматически" или подтвердите название приложения.',
        'download_ios_step1_desc' => 'Нажмите на кнопку "Поделиться" (квадрат со стрелкой вверх) в нижней панели браузера.',
        'download_ios_step2_desc' => 'В меню поделиться прокрутите вниз и выберите "На экран «Домой»".',
        'download_ios_step3_desc' => 'Нажмите "Добавить" в правом верхнем углу. Иконка появится на рабочем столе.',
        'download_pwa_info' => 'Maten Wallet — это прогрессивное веб-приложение (PWA). Оно работает как обычное приложение, не занимает много места и всегда обновляется автоматически.',
    ],
    'kk' => [
        'site_title_home' => 'Maten • Заманауи әмиян',
        'site_title_transfer' => 'Maten • Аударым',
        'site_title_menu' => 'Maten • Мәзір',
        'site_title_download' => 'Maten • Қалай жүктеуге болады',
        'sign_in' => 'Кіру',
        'register' => 'Тіркелу',
        'login_title' => 'Кіру',
        'register_title' => 'Тіркелу',
        'no_account' => 'Аккаунтыңыз жоқ па?',
        'has_account' => 'Аккаунтыңыз бар ма?',
        'register_cta' => 'Тіркелу',
        'login_cta' => 'Кіру',
        'login_field' => 'Email немесе username',
        'username_field' => 'Username',
        'email_field' => 'Email',
        'password_field' => 'Құпиясөз',
        'confirm_password_field' => 'Құпиясөзді растау',
        'passwords_do_not_match' => 'Құпиясөздер сәйкес келмейді',
        'verification_code_field' => 'Растау коды',
        'verification_code_sent' => 'Растау коды поштаға жіберілді',
        'enter_verification_code' => 'Хаттағы кодты енгізіңіз',
        'wallet_address' => 'Әмиян адресі',
        'copy_address' => 'Адрес көшіру',
        'show_qr' => 'QR көрсету',
        'qr_title' => 'Сіздің QR-кодыңыз',
        'qr_scan_hint' => 'MATEN аудару үшін сканерлеңіз',
        'balance_title' => 'Сіздің баланс',
        'owner_label' => 'Иесі',
        'wallet_brand' => 'MATEN WALLET',
        'transfer_banner_title' => 'MATEN-ді досыңа жібер!',
        'transfer_banner_text' => 'Maten ішінде комиссиясыз жылдам аударымдар.',
        'transfer_banner_button' => 'Жіберу',
        'nav_home' => 'Басты',
        'nav_transfer' => 'Аударым',
        'nav_menu' => 'Мәзір',
        'transfer_page_label' => 'Transfer',
        'transfer_page_title' => 'MATEN аударымы',
        'back' => 'Артқа',
        'transfer_hero_label' => 'Maten Wallet',
        'transfer_hero_title' => 'MATEN-ді желі ішінде жібер',
        'transfer_hero_text' => 'MATEN қолданушысына комиссиясыз ішкі аударым жасаңыз.',
        'address_tab' => 'Адрес бойынша',
        'qr_tab' => 'QR сканерлеу',
        'recipient_by_address' => 'Қабылдаушы адресі',
        'recipient_placeholder' => 'MATEN address енгізіңіз',
        'scan_qr_title' => 'QR-кодты сканерлеу',
        'scan_qr_instruction' => 'Қабылдаушының QR-кодына камераны бағыттаңыз',
        'start_scan' => 'Сканерлеуді бастау',
        'stop_scan' => 'Тоқтату',
        'qr_scanned' => 'QR сканерленді!',
        'recipient_card_label' => 'Қабылдаушы',
        'recipient_card_default' => 'Maten client',
        'recipient_card_hint' => 'Аккаунт атауын көру үшін адрес енгізіңіз',
        'transfer_amount' => 'Аударым сомасы',
        'message_label' => 'Хабарлама',
        'message_placeholder' => 'Мысалы: Рақмет!',
        'quick_thanks' => 'Рақмет!',
        'quick_gift' => 'Саған Maten сыйлығы',
        'quick_return' => 'Қарызды қайтарамын',
        'commission' => 'Комиссия',
        'transfer_submit' => 'Аудару',
        'login_required_transfer' => 'Аударым үшін аккаунт керек',
        'login_required_text' => 'MATEN-ге кіріп, басқа қолданушыларға ішкі аударым жіберіңіз.',
        'menu_label' => 'Profile',
        'menu_title' => 'Аккаунт мәзірі',
        'menu_text' => 'Профиль, тіл және MATEN-нің болашақ бөлімдерін басқару.',
        'edit_profile' => 'Профильді өңдеу',
        'change_avatar' => 'Аватарды өзгерту',
        'remove_avatar' => 'Аватарды алып тастау',
        'profile_title' => 'Профиль',
        'save_profile' => 'Өзгерістерді сақтау',
        'logout_action' => 'Аккаунттан шығу',
        'menu_sections_title' => 'Басқа бөлімдер',
        'menu_soon_wallet' => 'Аударымдар тарихы',
        'menu_soon_security' => 'Аккаунт қауіпсіздігі',
        'menu_soon_support' => 'Қолдау',
        'menu_soon_hint' => 'Жақында бұл жерде MATEN-нің қосымша бөлімдері пайда болады.',
        'menu_download' => 'Қалай жүктеуге болады?',
        'language_title' => 'Тіл',
        'lang_kk' => 'Қазақша',
        'lang_en' => 'English',
        'lang_ru' => 'Русский',
        'account_created' => 'Тіркелу сәтті аяқталды!',
        'login_success' => 'Кіру орындалды',
        'logout_success' => 'Аккаунттан шықтыңыз',
        'invalid_email' => 'Email қате',
        'password_short' => 'Құпиясөз кемінде 6 таңба болуы керек',
        'username_short' => 'Username кемінде 3 таңба болуы керек',
        'username_invalid' => 'Username ішінде тек әріп, сан, нүкте және астыңғы сызу болуы керек',
        'email_exists' => 'Мұндай email-пен қолданушы бар',
        'username_exists' => 'Мұндай username бос емес',
        'register_error' => 'Тіркелу қатесі',
        'invalid_credentials' => 'Логин немесе құпиясөз қате',
        'connect_error' => 'Қосылу қатесі',
        'login_first' => 'Алдымен кіріңіз немесе тіркеліңіз',
        'section_in_development' => '"{key}" бөлімі әзірленуде',
        'logout_confirm' => 'Аккаунттан шығасыз ба?',
        'address_copied' => 'Адрес көшірілді!',
        'login_first_short' => 'Алдымен аккаунтқа кіріңіз',
        'recipient_required' => 'Қабылдаушы адресін жазыңыз',
        'amount_invalid' => 'Дұрыс соманы енгізіңіз',
        'sender_not_found' => 'Жіберуші аккаунты табылмады',
        'recipient_not_found' => 'Қабылдаушы табылмады',
        'self_transfer' => 'Өзіңізге аудару мүмкін емес',
        'not_enough_balance' => 'Баланс жеткіліксіз',
        'transfer_sent' => 'Аударым жіберілді',
        'profile_updated' => 'Профиль жаңартылды',
        'save_error' => 'Өзгерістерді сақтау мүмкін болмады',
        'recipient_lookup_empty' => 'Аккаунт атауын көру үшін адрес енгізіңіз',
        'recipient_lookup_success' => 'Қаражат осы MATEN аккаунтына жіберіледі',
        'transfer_success_title' => 'Аударым орындалды',
        'sent_amount_label' => 'Жіберілді',
        'show_receipt' => 'Чекті көрсету',
        'hide_receipt' => 'Чекті жасыру',
        'close_action' => 'Жабу',
        'receipt_title' => 'Аударым чегі',
        'receipt_recipient' => 'Қабылдаушы',
        'receipt_amount' => 'Сома',
        'receipt_message' => 'Хабарлама',
        'receipt_time' => 'Уақыты',
        'receipt_address' => 'Адрес',
        'receipt_transaction' => 'Операция №',
        'receipt_fee' => 'Комиссия',
        'receipt_sender' => 'Жіберуші',
        'receipt_source' => 'Қайдан',
        'receipt_empty_message' => 'Хабарлама жоқ',
        'confirm_transfer_title' => 'Аударымды растау',
        'confirm_transfer_message' => '{amount} MATEN сомасын {recipient} қолданушысына аударғыңыз келетініне сенімдісіз бе?',
        'confirm_yes' => 'Иә, аудару',
        'confirm_no' => 'Болдырмау',
        'transfers_section' => 'Аударымдар',
        'security_section' => 'Қауіпсіздік',
        'transfers_empty' => 'Аударымдар тарихы бос',
        'transfer_sent_you' => 'Сіз жібердіңіз',
        'transfer_received_you' => 'Сіз алдыңыз',
        'download_title' => 'Maten орнатыңыз',
        'download_subtitle' => 'Жылдам қол жеткізу үшін қосымшаны құрылғыға қосу',
        'download_share_btn' => 'Қосымшамен бөлісу',
        'download_add_home_btn' => 'Басты экранға қосу',
        'download_android_title' => 'Android (Chrome)',
        'download_ios_title' => 'iOS (Safari)',
        'download_step_1' => '1-қадам: Мәзірді ашыңыз',
        'download_step_2' => '2-қадам: "Басты экранға қосу" басыңыз',
        'download_step_3' => '3-қадам: Қосуды растаңыз',
        'download_android_step1_desc' => 'Браузердің жоғарғы оң жақ бұрышындағы үш нүктені басыңыз.',
        'download_android_step2_desc' => 'Ашылмалы мәзірден "Басты экранға қосу" немесе "Қосымшаны орнату" таңдаңыз.',
        'download_android_step3_desc' => '"Автоматты түрде қосу" басыңыз немесе қосымша атауын растаңыз.',
        'download_ios_step1_desc' => 'Браузердің төменгі панеліндегі "Бөлісу" түймесін (жоғары көрсеткі бар шаршы) басыңыз.',
        'download_ios_step2_desc' => 'Бөлісу мәзірінде төмен жылжып, "Басты экранға қосу" таңдаңыз.',
        'download_ios_step3_desc' => 'Жоғарғы оң жақ бұрыштағы "Қосу" басыңыз. Белгіше жұмыс үстелінде пайда болады.',
        'download_pwa_info' => 'Maten Wallet — прогрессивті веб-қосымша (PWA). Ол кәдімгі қосымша сияқты жұмыс істейді, аз орын алады және әрқашан автоматты түрде жаңартылады.',
    ],
    'en' => [
        'site_title_home' => 'Maten • Modern Wallet',
        'site_title_transfer' => 'Maten • Transfer',
        'site_title_menu' => 'Maten • Menu',
        'site_title_download' => 'Maten • How to Download',
        'sign_in' => 'Sign in',
        'register' => 'Register',
        'login_title' => 'Sign in',
        'register_title' => 'Register',
        'no_account' => 'No account yet?',
        'has_account' => 'Already have an account?',
        'register_cta' => 'Register',
        'login_cta' => 'Sign in',
        'login_field' => 'Email or username',
        'username_field' => 'Username',
        'email_field' => 'Email',
        'password_field' => 'Password',
        'confirm_password_field' => 'Confirm password',
        'passwords_do_not_match' => 'Passwords do not match',
        'verification_code_field' => 'Verification code',
        'verification_code_sent' => 'Verification code sent to your email',
        'enter_verification_code' => 'Enter the code from the email',
        'wallet_address' => 'Wallet address',
        'copy_address' => 'Copy address',
        'show_qr' => 'Show QR',
        'qr_title' => 'Your QR Code',
        'qr_scan_hint' => 'Scan to transfer MATEN',
        'balance_title' => 'Your balance',
        'owner_label' => 'Owner',
        'wallet_brand' => 'MATEN WALLET',
        'transfer_banner_title' => 'Send MATEN to a friend!',
        'transfer_banner_text' => 'Instant commission-free transfers inside the Maten network.',
        'transfer_banner_button' => 'Transfer',
        'nav_home' => 'Home',
        'nav_transfer' => 'Transfer',
        'nav_menu' => 'Menu',
        'transfer_page_label' => 'Transfer',
        'transfer_page_title' => 'MATEN Transfer',
        'back' => 'Back',
        'transfer_hero_label' => 'Maten Wallet',
        'transfer_hero_title' => 'Send MATEN inside the network',
        'transfer_hero_text' => 'Fast internal transfer to another MATEN user with zero commission.',
        'address_tab' => 'By Address',
        'qr_tab' => 'Scan QR',
        'recipient_by_address' => 'Recipient address',
        'recipient_placeholder' => 'Enter MATEN address',
        'scan_qr_title' => 'QR Code Scanning',
        'scan_qr_instruction' => 'Point camera at recipient\'s QR code',
        'start_scan' => 'Start Scanning',
        'stop_scan' => 'Stop',
        'qr_scanned' => 'QR Scanned!',
        'recipient_card_label' => 'Recipient',
        'recipient_card_default' => 'Maten client',
        'recipient_card_hint' => 'Enter an address to see the account name',
        'transfer_amount' => 'Transfer amount',
        'message_label' => 'Message',
        'message_placeholder' => 'For example: Thank you!',
        'quick_thanks' => 'Thank you!',
        'quick_gift' => 'A Maten gift for you',
        'quick_return' => 'Paying back',
        'commission' => 'Commission',
        'transfer_submit' => 'Transfer',
        'login_required_transfer' => 'An account is required',
        'login_required_text' => 'Sign in to MATEN to open internal transfers and send coins to other users.',
        'menu_label' => 'Profile',
        'menu_title' => 'Account menu',
        'menu_text' => 'Manage your profile, language and future MATEN sections.',
        'edit_profile' => 'Edit profile',
        'change_avatar' => 'Change avatar',
        'remove_avatar' => 'Remove avatar',
        'profile_title' => 'Profile',
        'save_profile' => 'Save changes',
        'logout_action' => 'Sign out',
        'menu_sections_title' => 'Other sections',
        'menu_soon_wallet' => 'Transfer history',
        'menu_soon_security' => 'Account security',
        'menu_soon_support' => 'Support',
        'menu_soon_hint' => 'More MATEN sections will appear here soon.',
        'menu_download' => 'How to download?',
        'language_title' => 'Language',
        'lang_kk' => 'Қазақша',
        'lang_en' => 'English',
        'lang_ru' => 'Русский',
        'account_created' => 'Registration completed!',
        'login_success' => 'Signed in successfully',
        'logout_success' => 'You signed out',
        'invalid_email' => 'Invalid email',
        'password_short' => 'Password must be at least 6 characters',
        'username_short' => 'Username must be at least 3 characters',
        'username_invalid' => 'Username may contain only letters, numbers, dots and underscores',
        'email_exists' => 'A user with this email already exists',
        'username_exists' => 'This username is already taken',
        'register_error' => 'Registration error',
        'invalid_credentials' => 'Incorrect login or password',
        'connect_error' => 'Connection error',
        'login_first' => 'Please sign in or register first',
        'section_in_development' => 'Section "{key}" is under development',
        'logout_confirm' => 'Sign out of this account?',
        'address_copied' => 'Address copied!',
        'login_first_short' => 'Please sign in first',
        'recipient_required' => 'Enter the recipient address',
        'amount_invalid' => 'Enter a valid amount',
        'sender_not_found' => 'Sender account was not found',
        'recipient_not_found' => 'Recipient was not found',
        'self_transfer' => 'You cannot transfer to yourself',
        'not_enough_balance' => 'Not enough balance',
        'transfer_sent' => 'Transfer sent',
        'profile_updated' => 'Profile updated',
        'save_error' => 'Could not save changes',
        'recipient_lookup_empty' => 'Enter an address to see the account name',
        'recipient_lookup_success' => 'Funds will be sent to this MATEN account',
        'transfer_success_title' => 'Transfer completed',
        'sent_amount_label' => 'Sent',
        'show_receipt' => 'Show receipt',
        'hide_receipt' => 'Hide receipt',
        'close_action' => 'Close',
        'receipt_title' => 'Transfer receipt',
        'receipt_recipient' => 'Recipient',
        'receipt_amount' => 'Amount',
        'receipt_message' => 'Message',
        'receipt_time' => 'Time',
        'receipt_address' => 'Address',
        'receipt_transaction' => 'Transaction ID',
        'receipt_fee' => 'Fee',
        'receipt_sender' => 'Sender',
        'receipt_source' => 'From',
        'receipt_empty_message' => 'No message',
        'confirm_transfer_title' => 'Confirm Transfer',
        'confirm_transfer_message' => 'Are you sure you want to transfer {amount} MATEN to {recipient}?',
        'confirm_yes' => 'Yes, transfer',
        'confirm_no' => 'Cancel',
        'transfers_section' => 'Transfers',
        'security_section' => 'Security',
        'transfers_empty' => 'Transfer history is empty',
        'transfer_sent_you' => 'You sent',
        'transfer_received_you' => 'You received',
        'download_title' => 'Install Maten',
        'download_subtitle' => 'How to add the app to your device for quick access',
        'download_share_btn' => 'Share App',
        'download_add_home_btn' => 'Add to Home Screen',
        'download_android_title' => 'Android (Chrome)',
        'download_ios_title' => 'iOS (Safari)',
        'download_step_1' => 'Step 1: Open the menu',
        'download_step_2' => 'Step 2: Tap "Add to Home Screen"',
        'download_step_3' => 'Step 3: Confirm installation',
        'download_android_step1_desc' => 'Tap the three dots in the top right corner of the browser.',
        'download_android_step2_desc' => 'Select "Add to Home Screen" or "Install App" from the dropdown menu.',
        'download_android_step3_desc' => 'Tap "Add automatically" or confirm the app name.',
        'download_ios_step1_desc' => 'Tap the "Share" button (square with an arrow up) in the bottom bar of Safari.',
        'download_ios_step2_desc' => 'In the share menu, scroll down and select "Add to Home Screen".',
        'download_ios_step3_desc' => 'Tap "Add" in the top right corner. The icon will appear on your home screen.',
        'download_pwa_info' => 'Maten Wallet is a Progressive Web App (PWA). It works like a regular app, takes up little space, and always updates automatically.',
    ],
];

// --- Вспомогательные функции ---
function t(string $key): string {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $translations['ru'][$key] ?? $key;
}

function fallbackUsername(string $email): string {
    $base = trim((string) strstr($email, '@', true));
    return $base !== '' ? $base : 'maten_user';
}

function normalizeUsername(string $username): string {
    $username = trim($username);
    return preg_replace('/\s+/u', '', $username) ?? '';
}

function stringLength(string $value): int {
    if (preg_match_all('/./u', $value, $matches)) {
        return count($matches[0]);
    }
    return strlen($value);
}

function firstCharacter(string $value): string {
    if (preg_match('/./u', $value, $match)) {
        return strtoupper($match[0]);
    }
    return 'M';
}

function resolveDisplayName(array $user): string {
    $username = trim((string) ($user['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }
    return fallbackUsername((string) ($user['email'] ?? ''));
}

function formatBalance($balance): string {
    $balance = floatval($balance);
    if (floor($balance) == $balance) {
        return number_format($balance, 0, '.', ' ');
    }
    return rtrim(rtrim(number_format($balance, 8, '.', ' '), '0'), '.');
}

// --- Проверка и создание таблиц ---
function ensureTablesExist(PDO $pdo, string $db_name): void {
    // Проверка колонок в users
    $columnsToCheck = [
        'username' => "ALTER TABLE users ADD COLUMN username VARCHAR(64) NULL AFTER email",
        'avatar_data' => "ALTER TABLE users ADD COLUMN avatar_data LONGTEXT NULL AFTER username",
        'verified' => "ALTER TABLE users ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_data",
        'verified_at' => "ALTER TABLE users ADD COLUMN verified_at TIMESTAMP NULL AFTER verified"
    ];
    
    foreach ($columnsToCheck as $column => $sql) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $stmt->execute([$db_name, $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }
    
    // Таблица верификаций
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pending_verifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            verification_code VARCHAR(10) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            temp_data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_verification_code (verification_code),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Обновляем username из email
    $pdo->exec("
        UPDATE users 
        SET username = SUBSTRING_INDEX(email, '@', 1)
        WHERE (username IS NULL OR username = '') AND email IS NOT NULL AND email <> ''
    ");
}

// Выполняем проверку таблиц
ensureTablesExist($pdo, $db_name);
