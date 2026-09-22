<?php
declare(strict_types=1);

// Testcom's own auth system. Uses an isolated session (separate cookie name from
// MatenWeb) and its own database tables (testcom_users, testcom_guests), but
// reuses MatenWeb's existing DB credential loader so no secrets are duplicated here.

session_name('testcom_session');
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $matenConfig = __DIR__ . '/../includes/db_config.php';
    $onayConfig = __DIR__ . '/../db.php';

    if (is_file($matenConfig)) {
        require_once $matenConfig;
        // $db_host, $db_port, $db_user, $db_pass, $db_name are now defined.
        $dsn = "mysql:host=$db_host" . ($db_port !== '' ? ";port=$db_port" : "") . ";dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } elseif (is_file($onayConfig)) {
        require_once $onayConfig;
        if (!$pdo instanceof PDO) {
            throw new PDOException('OnayFUN db.php did not create a PDO connection.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        throw new PDOException('No database config found for Testcom auth.');
    }
} catch (PDOException $e) {
    error_log('Testcom auth DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection error.']);
    exit;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS testcom_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Idempotent "add column if missing" for installs where the table already existed
// without these columns (MySQL versions here may not support ADD COLUMN IF NOT EXISTS).
try {
    $pdo->exec("ALTER TABLE testcom_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
} catch (PDOException $e) {
    // 42S21 / 1060 = column already exists — fine, ignore.
}
try {
    $pdo->exec("ALTER TABLE testcom_users ADD COLUMN nickname VARCHAR(100) NULL");
} catch (PDOException $e) {
    // already exists — ignore.
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS testcom_guests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nickname VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS testcom_test_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        score INT NOT NULL DEFAULT 0,
        max_score INT NOT NULL DEFAULT 0,
        answered_count INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL DEFAULT 0,
        essay_words INT NOT NULL DEFAULT 0,
        status VARCHAR(40) NOT NULL DEFAULT 'completed',
        details_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_testcom_history_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

try {
    $pdo->exec("ALTER TABLE testcom_test_history ADD COLUMN details_json LONGTEXT NULL");
} catch (PDOException $e) {
    // already exists — ignore.
}

// The admin promo code(s) are kept out of the (public) repo: they live in testcom.local.php,
// which is git-ignored and uploaded to the server by deploy.py. Without that file the
// promo feature is simply disabled. 'promo_admin_code' can be one code (string) or several
// (an array), so more than one code can unlock admin at the same time.
$testcomLocal = is_file(__DIR__ . '/testcom.local.php') ? require __DIR__ . '/testcom.local.php' : [];
$testcomPromoRaw = is_array($testcomLocal) ? ($testcomLocal['promo_admin_code'] ?? '') : '';
$testcomPromoCodes = array_values(array_filter(array_map(
    'trim',
    is_array($testcomPromoRaw) ? $testcomPromoRaw : [(string) $testcomPromoRaw]
), fn($c) => $c !== ''));
define('TESTCOM_PROMO_ADMIN_CODES', $testcomPromoCodes);

function testcomJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function testcomRespond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function testcomRequireAdmin(PDO $pdo): array {
    if (empty($_SESSION['testcom_user_id'])) {
        testcomRespond(['error' => 'Sign-in required.'], 401);
    }
    $stmt = $pdo->prepare('SELECT id, email, role FROM testcom_users WHERE id = ?');
    $stmt->execute([$_SESSION['testcom_user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        testcomRespond(['error' => 'Admin access required.'], 403);
    }
    return $user;
}

$action = $_GET['action'] ?? '';
$needsBody = in_array($action, ['register', 'login', 'redeem_promo', 'track_guest', 'update_nickname', 'record_history', 'delete_history'], true);
$input = $needsBody ? testcomJsonInput() : [];

switch ($action) {
    case 'register': {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $password2 = (string) ($input['password2'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            testcomRespond(['error' => 'Please enter a valid email address.'], 400);
        }
        if (strlen($password) < 6) {
            testcomRespond(['error' => 'Password must be at least 6 characters.'], 400);
        }
        if ($password !== $password2) {
            testcomRespond(['error' => 'Passwords do not match.'], 400);
        }

        $stmt = $pdo->prepare('SELECT id FROM testcom_users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            testcomRespond(['error' => 'This email is already registered.'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO testcom_users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, $hash]);

        $_SESSION['testcom_user_id'] = (int) $pdo->lastInsertId();
        $_SESSION['testcom_email'] = $email;
        session_regenerate_id(true);
        testcomRespond(['success' => true, 'email' => $email, 'role' => 'user']);
        break;
    }

    case 'login': {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM testcom_users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            testcomRespond(['error' => 'Incorrect email or password.'], 401);
        }

        $_SESSION['testcom_user_id'] = (int) $user['id'];
        $_SESSION['testcom_email'] = $email;
        session_regenerate_id(true);
        testcomRespond(['success' => true, 'email' => $email, 'role' => $user['role']]);
        break;
    }

    case 'logout': {
        $_SESSION = [];
        session_destroy();
        testcomRespond(['success' => true]);
        break;
    }

    case 'me': {
        if (!empty($_SESSION['testcom_user_id'])) {
            $stmt = $pdo->prepare('SELECT role FROM testcom_users WHERE id = ?');
            $stmt->execute([$_SESSION['testcom_user_id']]);
            $user = $stmt->fetch();
            $role = $user['role'] ?? 'user';
            testcomRespond(['loggedIn' => true, 'email' => $_SESSION['testcom_email'], 'role' => $role]);
        }
        testcomRespond(['loggedIn' => false]);
        break;
    }

    case 'record_history': {
        if (empty($_SESSION['testcom_user_id'])) {
            testcomRespond(['success' => false, 'guest' => true]);
        }
        $score = max(0, (int) ($input['score'] ?? 0));
        $maxScore = max(0, (int) ($input['maxScore'] ?? 0));
        $answeredCount = max(0, (int) ($input['answeredCount'] ?? 0));
        $totalQuestions = max(0, (int) ($input['totalQuestions'] ?? 0));
        $essayWords = max(0, (int) ($input['essayWords'] ?? 0));
        $status = (string) ($input['status'] ?? 'completed');
        $details = $input['details'] ?? null;
        $detailsJson = is_array($details)
            ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        if ($detailsJson !== null && strlen($detailsJson) > 1024 * 1024) {
            $detailsJson = null;
        }
        if (!in_array($status, ['completed', 'finished_early', 'disqualified'], true)) {
            $status = 'completed';
        }
        $stmt = $pdo->prepare(
            'INSERT INTO testcom_test_history (user_id, score, max_score, answered_count, total_questions, essay_words, status, details_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['testcom_user_id'], $score, $maxScore, $answeredCount, $totalQuestions, $essayWords, $status, $detailsJson]);
        testcomRespond(['success' => true]);
        break;
    }

    case 'history': {
        if (empty($_SESSION['testcom_user_id'])) {
            testcomRespond(['history' => []]);
        }
        $stmt = $pdo->prepare(
            'SELECT id, score, max_score, answered_count, total_questions, essay_words, status, details_json, created_at
             FROM testcom_test_history
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 10'
        );
        $stmt->execute([$_SESSION['testcom_user_id']]);
        testcomRespond(['history' => $stmt->fetchAll()]);
        break;
    }

    case 'delete_history': {
        if (empty($_SESSION['testcom_user_id'])) {
            testcomRespond(['error' => 'Sign-in required.'], 401);
        }
        $id = max(0, (int) ($input['id'] ?? 0));
        if ($id <= 0) {
            testcomRespond(['error' => 'Invalid history item.'], 400);
        }
        $stmt = $pdo->prepare('DELETE FROM testcom_test_history WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $_SESSION['testcom_user_id']]);
        testcomRespond(['success' => true]);
        break;
    }

    case 'redeem_promo': {
        if (empty($_SESSION['testcom_user_id'])) {
            testcomRespond(['error' => 'Sign in first, then redeem your promo code.'], 401);
        }
        $code = trim((string) ($input['code'] ?? ''));
        $validCode = $code !== '' && array_reduce(
            TESTCOM_PROMO_ADMIN_CODES,
            fn($found, $known) => $found || strcasecmp($code, $known) === 0,
            false
        );
        if (!$validCode) {
            testcomRespond(['error' => 'Invalid promo code.'], 400);
        }
        $stmt = $pdo->prepare("UPDATE testcom_users SET role = 'admin' WHERE id = ?");
        $stmt->execute([$_SESSION['testcom_user_id']]);
        testcomRespond(['success' => true, 'role' => 'admin']);
        break;
    }

    case 'update_nickname': {
        if (empty($_SESSION['testcom_user_id'])) {
            testcomRespond(['error' => 'Sign-in required.'], 401);
        }
        $nickname = trim((string) ($input['nickname'] ?? ''));
        if ($nickname === '') {
            testcomRespond(['error' => 'Nickname required.'], 400);
        }
        $stmt = $pdo->prepare('UPDATE testcom_users SET nickname = ? WHERE id = ?');
        $stmt->execute([$nickname, $_SESSION['testcom_user_id']]);
        testcomRespond(['success' => true]);
        break;
    }

    case 'track_guest': {
        $nickname = trim((string) ($input['nickname'] ?? ''));
        if ($nickname === '') {
            testcomRespond(['error' => 'Nickname required.'], 400);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO testcom_guests (nickname) VALUES (?)
             ON DUPLICATE KEY UPDATE last_seen_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$nickname]);
        testcomRespond(['success' => true]);
        break;
    }

    case 'counts': {
        testcomRequireAdmin($pdo);
        $userCount = (int) $pdo->query('SELECT COUNT(*) AS c FROM testcom_users')->fetch()['c'];
        $adminCount = (int) $pdo->query("SELECT COUNT(*) AS c FROM testcom_users WHERE role = 'admin'")->fetch()['c'];
        $guestCount = (int) $pdo->query('SELECT COUNT(*) AS c FROM testcom_guests')->fetch()['c'];
        testcomRespond(['userCount' => $userCount, 'adminCount' => $adminCount, 'guestCount' => $guestCount]);
        break;
    }

    case 'list_users': {
        testcomRequireAdmin($pdo);
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $stmt = $pdo->prepare('SELECT id, email, nickname, role, created_at FROM testcom_users WHERE email LIKE ? OR nickname LIKE ? ORDER BY created_at DESC');
            $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        } else {
            $stmt = $pdo->query('SELECT id, email, nickname, role, created_at FROM testcom_users ORDER BY created_at DESC');
        }
        testcomRespond(['users' => $stmt->fetchAll()]);
        break;
    }

    case 'list_admins': {
        testcomRequireAdmin($pdo);
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $stmt = $pdo->prepare("SELECT id, email, nickname, role, created_at FROM testcom_users WHERE role = 'admin' AND (email LIKE ? OR nickname LIKE ?) ORDER BY created_at DESC");
            $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        } else {
            $stmt = $pdo->query("SELECT id, email, nickname, role, created_at FROM testcom_users WHERE role = 'admin' ORDER BY created_at DESC");
        }
        testcomRespond(['admins' => $stmt->fetchAll()]);
        break;
    }

    case 'list_guests': {
        testcomRequireAdmin($pdo);
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $stmt = $pdo->prepare('SELECT id, nickname, created_at, last_seen_at FROM testcom_guests WHERE nickname LIKE ? ORDER BY last_seen_at DESC');
            $stmt->execute(['%' . $q . '%']);
        } else {
            $stmt = $pdo->query('SELECT id, nickname, created_at, last_seen_at FROM testcom_guests ORDER BY last_seen_at DESC');
        }
        testcomRespond(['guests' => $stmt->fetchAll()]);
        break;
    }

    default:
        testcomRespond(['error' => 'Unknown action.'], 400);
}
