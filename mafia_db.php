<?php
declare(strict_types=1);

/*
 * Shared plumbing for mafia.php and points.php: JSON output, database connection, tables and the signed-in user.
 * The user always comes from the server-side session (testcom_session); the client never says who it is.
 * Tables live in the Maten database with the testcom_ prefix.
 */

function mf_out(array $data, int $code = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function mf_fail(string $error, string $message, int $http = 200, array $extra = []): void
{
    mf_out(array_merge(['ok' => false, 'error' => $error, 'message' => $message], $extra), $http);
}

function mf_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function mf_connect(): PDO
{
    try {
        $matenConfig = __DIR__ . '/../includes/db_config.php';
        $onayConfig = __DIR__ . '/../db.php';
        if (is_file($matenConfig)) {
            require $matenConfig;
            $dsn = "mysql:host=$db_host" . ($db_port !== '' ? ";port=$db_port" : '') . ";dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $GLOBALS['mf_secret'] = hash('sha256', (string) $db_pass . 'testcom-mafia');
        } elseif (is_file($onayConfig)) {
            require $onayConfig;
            if (!$pdo instanceof PDO) {
                throw new PDOException('db.php did not create a PDO connection.');
            }
        } elseif (PHP_SAPI === 'cli-server') {
            $pdo = new PDO('sqlite:' . sys_get_temp_dir() . '/testcom_mafia_dev.sqlite');
        } else {
            throw new PDOException('No database config found.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
        return $pdo;
    } catch (Throwable $e) {
        error_log('Testcom mafia DB connection failed: ' . $e->getMessage());
        mf_fail('unavailable', 'Игра временно недоступна. Попробуйте позже.', 503);
    }
}

function mf_is_sqlite(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

function mf_schema(PDO $pdo): void
{
    try {
        $pdo->query('SELECT 1 FROM testcom_points_log LIMIT 1');
        return;
    } catch (Throwable $e) {
        // tables are missing: create them below
    }
    $sqlite = mf_is_sqlite($pdo);
    $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $long = $sqlite ? 'TEXT' : 'LONGTEXT';
    $idx = fn(string $name, string $table, string $cols) => $sqlite ? "CREATE INDEX IF NOT EXISTS $name ON $table ($cols)" : null;

    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_mafia_rooms (
        code VARCHAR(8) NOT NULL PRIMARY KEY, size INT NOT NULL, status VARCHAR(10) NOT NULL, is_public TINYINT NOT NULL DEFAULT 0,
        humans INT NOT NULL DEFAULT 0, max_humans INT NOT NULL DEFAULT 0, state $long NOT NULL, created INT NOT NULL, updated INT NOT NULL"
        . ($sqlite ? '' : ', KEY status_public (status, is_public, updated)') . ")$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_mafia_members (
        user_id INT NOT NULL PRIMARY KEY, code VARCHAR(8) NOT NULL, joined INT NOT NULL)$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_mafia_invites (
        id $auto, code VARCHAR(8) NOT NULL, from_uid INT NOT NULL, to_uid INT NOT NULL, created INT NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'new'"
        . ($sqlite ? '' : ', KEY to_uid (to_uid, status)') . ")$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_mafia_seen (user_id INT NOT NULL PRIMARY KEY, seen INT NOT NULL)$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_mafia_rate (
        user_id INT NOT NULL, k VARCHAR(16) NOT NULL, win_start INT NOT NULL, cnt INT NOT NULL, PRIMARY KEY (user_id, k))$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_mafia_stats (
        user_id INT NOT NULL PRIMARY KEY, played INT NOT NULL DEFAULT 0, wins INT NOT NULL DEFAULT 0, mafia_wins INT NOT NULL DEFAULT 0,
        civilian_wins INT NOT NULL DEFAULT 0, detective_wins INT NOT NULL DEFAULT 0, doctor_wins INT NOT NULL DEFAULT 0,
        ai_played INT NOT NULL DEFAULT 0, ai_wins INT NOT NULL DEFAULT 0)$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_points (
        user_id INT NOT NULL PRIMARY KEY, total BIGINT NOT NULL DEFAULT 0, updated INT NOT NULL DEFAULT 0)$engine");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_points_log (
        id $auto, user_id INT NOT NULL, game VARCHAR(32) NOT NULL, points INT NOT NULL, created INT NOT NULL"
        . ($sqlite ? '' : ', KEY user_game (user_id, game, created)') . ")$engine");
    if ($sqlite) {
        foreach ([
            $idx('mf_rooms_pub', 'testcom_mafia_rooms', 'status, is_public, updated'),
            $idx('mf_inv_to', 'testcom_mafia_invites', 'to_uid, status'),
            $idx('mf_plog', 'testcom_points_log', 'user_id, game, created'),
        ] as $sql) {
            $pdo->exec($sql);
        }
        // local development only: a minimal users table so the sign-in path can be exercised
        $pdo->exec("CREATE TABLE IF NOT EXISTS testcom_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL DEFAULT '', role TEXT NOT NULL DEFAULT 'user', nickname TEXT NULL)");
    }
}

/** Reads the signed-in user from the session, then releases the session lock right away. */
function mf_session_uid(): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('testcom_session');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }
    $uid = (int) ($_SESSION['testcom_user_id'] ?? 0);
    session_write_close();
    return $uid;
}

/** Display name of a registered user: nickname, never the e-mail. */
function mf_display_name(PDO $pdo, int $uid): string
{
    $st = $pdo->prepare('SELECT nickname FROM testcom_users WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if ($row === false) {
        return '';
    }
    $name = mf_clean_name((string) ($row['nickname'] ?? ''));
    return $name !== '' ? $name : 'Игрок' . ($uid % 1000);
}

function mf_require_user(PDO $pdo): array
{
    $uid = mf_session_uid();
    if ($uid <= 0) {
        mf_fail('auth', 'Чтобы играть в Мафию, войдите в свой аккаунт.', 401);
    }
    $name = mf_display_name($pdo, $uid);
    if ($name === '') {
        mf_fail('auth', 'Чтобы играть в Мафию, войдите в свой аккаунт.', 401);
    }
    return ['id' => $uid, 'name' => $name];
}

/** Opaque public id of a user (used in invitations instead of the database id). */
function mf_pub(int $uid): string
{
    $secret = $GLOBALS['mf_secret'] ?? 'testcom-mafia-dev';
    return substr(hash_hmac('sha256', (string) $uid, $secret), 0, 12);
}

/** Allows $limit hits per $window seconds for this user and key. */
function mf_rate(PDO $pdo, int $uid, string $key, int $limit, int $window): bool
{
    $now = time();
    $st = $pdo->prepare('SELECT win_start, cnt FROM testcom_mafia_rate WHERE user_id = ? AND k = ?');
    $st->execute([$uid, $key]);
    $row = $st->fetch();
    if (!$row) {
        $pdo->prepare('INSERT INTO testcom_mafia_rate (user_id, k, win_start, cnt) VALUES (?, ?, ?, 1)')->execute([$uid, $key, $now]);
        return true;
    }
    if ($now - (int) $row['win_start'] >= $window) {
        $pdo->prepare('UPDATE testcom_mafia_rate SET win_start = ?, cnt = 1 WHERE user_id = ? AND k = ?')->execute([$now, $uid, $key]);
        return true;
    }
    if ((int) $row['cnt'] >= $limit) {
        return false;
    }
    $pdo->prepare('UPDATE testcom_mafia_rate SET cnt = cnt + 1 WHERE user_id = ? AND k = ?')->execute([$uid, $key]);
    return true;
}

function mf_points_add(PDO $pdo, int $uid, string $game, int $points): void
{
    if ($points <= 0) {
        return;
    }
    $ignore = mf_is_sqlite($pdo) ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $now = time();
    $pdo->prepare("$ignore INTO testcom_points (user_id, total, updated) VALUES (?, 0, ?)")->execute([$uid, $now]);
    $pdo->prepare('UPDATE testcom_points SET total = total + ?, updated = ? WHERE user_id = ?')->execute([$points, $now, $uid]);
    $pdo->prepare('INSERT INTO testcom_points_log (user_id, game, points, created) VALUES (?, ?, ?, ?)')->execute([$uid, $game, $points, $now]);
}

/** Top players by total points earned in all games. */
function mf_top_players(PDO $pdo, int $limit = 3): array
{
    $st = $pdo->prepare("SELECT p.user_id, p.total, u.nickname FROM testcom_points p
        JOIN testcom_users u ON u.id = p.user_id WHERE p.total > 0 ORDER BY p.total DESC, p.updated ASC LIMIT " . (int) $limit);
    $st->execute();
    $out = [];
    foreach ($st->fetchAll() as $i => $row) {
        $name = mf_clean_name((string) ($row['nickname'] ?? ''));
        $out[] = ['rank' => $i + 1, 'name' => $name !== '' ? $name : 'Игрок' . ((int) $row['user_id'] % 1000), 'total' => (int) $row['total']];
    }
    return $out;
}
