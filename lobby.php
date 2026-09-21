<?php
declare(strict_types=1);

/*
 * Testcom online lobby: presence, matchmaking rooms, invitations and in-game events.
 * Only nicknames are stored (never e-mails). Tables use the testcom_ prefix in the Maten database.
 * Plain polling over HTTP, so it works on ordinary shared hosting.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- Database (same credential loader as auth.php; SQLite only for the local `php -S` dev server) ---------- */
try {
    $matenConfig = __DIR__ . '/../includes/db_config.php';
    $onayConfig = __DIR__ . '/../db.php';
    if (is_file($matenConfig)) {
        require_once $matenConfig;
        $dsn = "mysql:host=$db_host" . ($db_port !== '' ? ";port=$db_port" : "") . ";dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
    } elseif (is_file($onayConfig)) {
        require_once $onayConfig;
        if (!$pdo instanceof PDO) {
            throw new PDOException('db.php did not create a PDO connection.');
        }
    } elseif (PHP_SAPI === 'cli-server') {
        $pdo = new PDO('sqlite:' . sys_get_temp_dir() . '/testcom_lobby_dev.sqlite');
    } else {
        throw new PDOException('No database config found.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Testcom lobby DB connection failed: ' . $e->getMessage());
    out(['error' => 'unavailable'], 503);
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$sqlite = $driver === 'sqlite';
$autoId = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
$engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

$pdo->exec("CREATE TABLE IF NOT EXISTS testcom_lobby_players (
    client_id VARCHAR(40) PRIMARY KEY, pub VARCHAR(12) NOT NULL, nickname VARCHAR(60) NOT NULL,
    seen INT NOT NULL, room_id INT NULL)$engine");
$pdo->exec("CREATE TABLE IF NOT EXISTS testcom_lobby_rooms (
    id $autoId, game VARCHAR(40) NOT NULL, size INT NOT NULL, ai INT NOT NULL DEFAULT 1,
    status VARCHAR(10) NOT NULL, host_id VARCHAR(40) NOT NULL, seed INT NULL, start_at INT NULL,
    created INT NOT NULL, updated INT NOT NULL)$engine");
$pdo->exec("CREATE TABLE IF NOT EXISTS testcom_lobby_members (
    room_id INT NOT NULL, client_id VARCHAR(40) NOT NULL, nickname VARCHAR(60) NOT NULL,
    slot INT NOT NULL, joined INT NOT NULL, left_at INT NULL, PRIMARY KEY (room_id, client_id))$engine");
$pdo->exec("CREATE TABLE IF NOT EXISTS testcom_lobby_invites (
    id $autoId, room_id INT NOT NULL, from_pub VARCHAR(12) NOT NULL, from_name VARCHAR(60) NOT NULL,
    to_pub VARCHAR(12) NOT NULL, status VARCHAR(10) NOT NULL, created INT NOT NULL)$engine");
$pdo->exec("CREATE TABLE IF NOT EXISTS testcom_lobby_events (
    id $autoId, room_id INT NOT NULL, slot INT NOT NULL, kind VARCHAR(16) NOT NULL,
    body TEXT NULL, created INT NOT NULL" . ($sqlite ? '' : ', KEY room_id (room_id, id)') . ")$engine");
if ($sqlite) {
    $pdo->exec('CREATE INDEX IF NOT EXISTS testcom_lobby_events_room ON testcom_lobby_events (room_id, id)');
}

/* ---------- Helpers ---------- */
$now = time();
$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
$input = is_array($input) ? $input : [];
$action = (string) ($_GET['action'] ?? '');

function q(PDO $pdo, string $sql, array $args = []): PDOStatement {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st;
}

function clientId(array $input): string {
    $id = strtolower((string) ($input['client'] ?? ''));
    if (!preg_match('/^[a-f0-9]{16,40}$/', $id)) {
        out(['error' => 'bad_client'], 400);
    }
    return $id;
}

function cleanName(string $name): string {
    $name = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $name) ?? '';
    $name = trim(mb_substr($name, 0, 40));
    return $name === '' ? 'Player' : $name;
}

const GAMES = ['answer-rush', 'timeline-rush', 'history-map', 'who-am-i', 'true-or-trap', 'history-duel', 'capture-the-answer', 'history-millionaire'];

function touchPlayer(PDO $pdo, string $client, string $name, int $now): array {
    $row = q($pdo, 'SELECT * FROM testcom_lobby_players WHERE client_id = ?', [$client])->fetch();
    if (!$row) {
        $pub = substr(bin2hex(random_bytes(6)), 0, 10);
        q($pdo, 'INSERT INTO testcom_lobby_players (client_id, pub, nickname, seen, room_id) VALUES (?, ?, ?, ?, NULL)', [$client, $pub, $name, $now]);
        return ['client_id' => $client, 'pub' => $pub, 'nickname' => $name, 'seen' => $now, 'room_id' => null];
    }
    q($pdo, 'UPDATE testcom_lobby_players SET nickname = ?, seen = ? WHERE client_id = ?', [$name, $now, $client]);
    $row['nickname'] = $name;
    $row['seen'] = $now;
    return $row;
}

function getRoom(PDO $pdo, $id): ?array {
    if (!$id) {
        return null;
    }
    $row = q($pdo, 'SELECT * FROM testcom_lobby_rooms WHERE id = ?', [$id])->fetch();
    return $row ?: null;
}

function activeMembers(PDO $pdo, int $roomId): array {
    return q($pdo, 'SELECT * FROM testcom_lobby_members WHERE room_id = ? AND left_at IS NULL ORDER BY slot', [$roomId])->fetchAll();
}

function addEvent(PDO $pdo, int $roomId, int $slot, string $kind, $body, int $now): int {
    q($pdo, 'INSERT INTO testcom_lobby_events (room_id, slot, kind, body, created) VALUES (?, ?, ?, ?, ?)',
        [$roomId, $slot, $kind, $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE), $now]);
    return (int) $pdo->lastInsertId();
}

function joinRoom(PDO $pdo, array $room, array $player, int $now): bool {
    $taken = array_map(fn($m) => (int) $m['slot'], activeMembers($pdo, (int) $room['id']));
    if (count($taken) >= (int) $room['size']) {
        return false;
    }
    $slot = 0;
    while (in_array($slot, $taken, true)) {
        $slot++;
    }
    q($pdo, 'DELETE FROM testcom_lobby_members WHERE room_id = ? AND client_id = ?', [$room['id'], $player['client_id']]);
    q($pdo, 'INSERT INTO testcom_lobby_members (room_id, client_id, nickname, slot, joined, left_at) VALUES (?, ?, ?, ?, ?, NULL)',
        [$room['id'], $player['client_id'], $player['nickname'], $slot, $now]);
    q($pdo, 'UPDATE testcom_lobby_players SET room_id = ? WHERE client_id = ?', [$room['id'], $player['client_id']]);
    q($pdo, 'UPDATE testcom_lobby_rooms SET updated = ? WHERE id = ?', [$now, $room['id']]);
    return true;
}

function newRoom(PDO $pdo, string $game, int $size, int $ai, string $status, array $player, int $now): array {
    q($pdo, 'INSERT INTO testcom_lobby_rooms (game, size, ai, status, host_id, seed, start_at, created, updated) VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, ?)',
        [$game, $size, $ai, $status, $player['client_id'], $now, $now]);
    $room = getRoom($pdo, (int) $pdo->lastInsertId());
    joinRoom($pdo, $room, $player, $now);
    return $room;
}

function beginPlay(PDO $pdo, array $room, int $delay, int $now): void {
    q($pdo, "UPDATE testcom_lobby_rooms SET status = 'play', seed = ?, start_at = ?, updated = ? WHERE id = ?",
        [random_int(1, 2147483000), $now + $delay, $now, $room['id']]);
}

function leaveRoom(PDO $pdo, string $client, int $now): void {
    $p = q($pdo, 'SELECT * FROM testcom_lobby_players WHERE client_id = ?', [$client])->fetch();
    if (!$p || !$p['room_id']) {
        return;
    }
    $room = getRoom($pdo, $p['room_id']);
    q($pdo, 'UPDATE testcom_lobby_players SET room_id = NULL WHERE client_id = ?', [$client]);
    if (!$room) {
        return;
    }
    if ($room['status'] === 'play') {
        $m = q($pdo, 'SELECT * FROM testcom_lobby_members WHERE room_id = ? AND client_id = ? AND left_at IS NULL', [$room['id'], $client])->fetch();
        if ($m) {
            q($pdo, 'UPDATE testcom_lobby_members SET left_at = ? WHERE room_id = ? AND client_id = ?', [$now, $room['id'], $client]);
            addEvent($pdo, (int) $room['id'], (int) $m['slot'], 'leave', null, $now);
        }
        return;
    }
    q($pdo, 'DELETE FROM testcom_lobby_members WHERE room_id = ? AND client_id = ?', [$room['id'], $client]);
    $rest = activeMembers($pdo, (int) $room['id']);
    if (!$rest) {
        q($pdo, 'DELETE FROM testcom_lobby_rooms WHERE id = ?', [$room['id']]);
        return;
    }
    if ($room['host_id'] === $client) {
        q($pdo, 'UPDATE testcom_lobby_rooms SET host_id = ? WHERE id = ?', [$rest[0]['client_id'], $room['id']]);
    }
    q($pdo, "UPDATE testcom_lobby_rooms SET status = 'lobby', updated = ? WHERE id = ? AND status = 'search'", [$now, $room['id']]);
}

function purge(PDO $pdo, int $now): void {
    $stale = q($pdo, 'SELECT client_id FROM testcom_lobby_players WHERE seen < ?', [$now - 40])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($stale as $client) {
        leaveRoom($pdo, (string) $client, $now);
        q($pdo, 'DELETE FROM testcom_lobby_players WHERE client_id = ?', [$client]);
    }
    q($pdo, 'DELETE FROM testcom_lobby_invites WHERE created < ?', [$now - 90]);
    q($pdo, 'DELETE FROM testcom_lobby_events WHERE created < ?', [$now - 1800]);
    $old = q($pdo, 'SELECT id FROM testcom_lobby_rooms WHERE created < ?', [$now - 10800])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($old as $id) {
        q($pdo, 'UPDATE testcom_lobby_players SET room_id = NULL WHERE room_id = ?', [$id]);
        q($pdo, 'DELETE FROM testcom_lobby_members WHERE room_id = ?', [$id]);
        q($pdo, 'DELETE FROM testcom_lobby_rooms WHERE id = ?', [$id]);
    }
}

function roomInfo(PDO $pdo, ?array $room, string $client, int $now): ?array {
    if (!$room) {
        return null;
    }
    $members = q($pdo, 'SELECT m.slot, m.nickname, m.client_id, m.left_at, p.pub FROM testcom_lobby_members m
                        LEFT JOIN testcom_lobby_players p ON p.client_id = m.client_id
                        WHERE m.room_id = ? ORDER BY m.slot', [$room['id']])->fetchAll();
    $list = [];
    foreach ($members as $m) {
        if ($room['status'] !== 'play' && $m['left_at'] !== null) {
            continue;
        }
        $list[] = ['slot' => (int) $m['slot'], 'pub' => $m['pub'], 'name' => $m['nickname'], 'left' => $m['left_at'] !== null, 'me' => $m['client_id'] === $client, 'host' => $m['client_id'] === $room['host_id']];
    }
    return [
        'id' => (int) $room['id'], 'game' => $room['game'], 'size' => (int) $room['size'], 'ai' => (int) $room['ai'],
        'status' => $room['status'], 'seed' => $room['seed'] !== null ? (int) $room['seed'] : null,
        'start_at' => $room['start_at'] !== null ? (int) $room['start_at'] : null, 'members' => $list, 'now' => $now,
    ];
}

function myRoom(PDO $pdo, array $player): ?array {
    $fresh = q($pdo, 'SELECT room_id FROM testcom_lobby_players WHERE client_id = ?', [$player['client_id']])->fetch();
    return getRoom($pdo, $fresh ? $fresh['room_id'] : null);
}

/* ---------- Actions ---------- */
$client = clientId($input);
$name = cleanName((string) ($input['name'] ?? ''));
$pdo->beginTransaction();
try {
    $player = touchPlayer($pdo, $client, $name, $now);

    switch ($action) {
        case 'sync': {
            purge($pdo, $now);
            $online = q($pdo, 'SELECT p.pub, p.nickname, r.status FROM testcom_lobby_players p
                               LEFT JOIN testcom_lobby_rooms r ON r.id = p.room_id
                               WHERE p.seen >= ? AND p.client_id <> ? ORDER BY p.nickname LIMIT 100', [$now - 25, $client])->fetchAll();
            $players = array_map(fn($r) => ['pub' => $r['pub'], 'name' => $r['nickname'], 'state' => $r['status'] === 'play' ? 'busy' : ($r['status'] ? 'party' : 'idle')], $online);
            $invites = q($pdo, "SELECT i.id, i.from_name, r.game, r.size FROM testcom_lobby_invites i
                                JOIN testcom_lobby_rooms r ON r.id = i.room_id
                                WHERE i.to_pub = ? AND i.status = 'pending' AND r.status IN ('lobby', 'search') ORDER BY i.id", [$player['pub']])->fetchAll();
            $room = myRoom($pdo, $player);
            if ($room && $room['status'] === 'play' && (int) $room['start_at'] < $now - 1200) {
                leaveRoom($pdo, $client, $now);
                $room = null;
            }
            $result = ['ok' => true, 'now' => $now, 'me' => ['pub' => $player['pub']], 'online' => $players, 'invites' => $invites, 'room' => roomInfo($pdo, $room, $client, $now)];
            break;
        }

        case 'queue': {
            $game = (string) ($input['game'] ?? '');
            $size = (int) ($input['size'] ?? 0);
            $ai = empty($input['ai']) ? 0 : 1;
            if (!in_array($game, GAMES, true) || !in_array($size, [2, 4], true)) {
                throw new RuntimeException('bad_request');
            }
            $room = myRoom($pdo, $player);
            if ($room) {
                if ($room['status'] === 'play') {
                    throw new RuntimeException('in_game');
                }
                if ($room['host_id'] !== $client) {
                    throw new RuntimeException('not_host');
                }
                if (count(activeMembers($pdo, (int) $room['id'])) > $size) {
                    throw new RuntimeException('too_many');
                }
                q($pdo, "UPDATE testcom_lobby_rooms SET game = ?, size = ?, ai = ?, status = 'search', updated = ? WHERE id = ?", [$game, $size, $ai, $now, $room['id']]);
            } else {
                $lock = $sqlite ? '' : ' FOR UPDATE';
                $found = q($pdo, "SELECT r.* FROM testcom_lobby_rooms r
                                  JOIN testcom_lobby_players h ON h.client_id = r.host_id
                                  WHERE r.status = 'search' AND r.game = ? AND r.size = ? AND h.seen >= ?
                                    AND (SELECT COUNT(*) FROM testcom_lobby_members m WHERE m.room_id = r.id AND m.left_at IS NULL) < r.size
                                  ORDER BY r.id LIMIT 1" . $lock, [$game, $size, $now - 15])->fetch();
                if ($found && joinRoom($pdo, $found, $player, $now)) {
                    $room = $found;
                } else {
                    $room = newRoom($pdo, $game, $size, $ai, 'search', $player, $now);
                }
            }
            $room = getRoom($pdo, (int) $room['id']);
            if (count(activeMembers($pdo, (int) $room['id'])) >= (int) $room['size']) {
                beginPlay($pdo, $room, 4, $now);
                $room = getRoom($pdo, (int) $room['id']);
            }
            $result = ['ok' => true, 'room' => roomInfo($pdo, $room, $client, $now)];
            break;
        }

        case 'start': {
            $room = myRoom($pdo, $player);
            if (!$room || $room['host_id'] !== $client || !in_array($room['status'], ['lobby', 'search'], true)) {
                throw new RuntimeException('not_host');
            }
            if (!(int) $room['ai'] && count(activeMembers($pdo, (int) $room['id'])) < (int) $room['size']) {
                throw new RuntimeException('need_players');
            }
            beginPlay($pdo, $room, 3, $now);
            $result = ['ok' => true, 'room' => roomInfo($pdo, getRoom($pdo, (int) $room['id']), $client, $now)];
            break;
        }

        case 'stop_search': {
            $room = myRoom($pdo, $player);
            if (!$room || $room['host_id'] !== $client || $room['status'] !== 'search') {
                throw new RuntimeException('not_host');
            }
            q($pdo, "UPDATE testcom_lobby_rooms SET status = 'lobby', updated = ? WHERE id = ?", [$now, $room['id']]);
            $result = ['ok' => true, 'room' => roomInfo($pdo, getRoom($pdo, (int) $room['id']), $client, $now)];
            break;
        }

        case 'leave': {
            leaveRoom($pdo, $client, $now);
            $result = ['ok' => true];
            break;
        }

        case 'invite': {
            $game = (string) ($input['game'] ?? '');
            $size = (int) ($input['size'] ?? 0);
            $ai = empty($input['ai']) ? 0 : 1;
            if ($size < 2) {
                throw new RuntimeException('solo');
            }
            if (!in_array($game, GAMES, true) || !in_array($size, [2, 4], true)) {
                throw new RuntimeException('bad_request');
            }
            $target = q($pdo, 'SELECT p.*, r.status AS rstatus FROM testcom_lobby_players p LEFT JOIN testcom_lobby_rooms r ON r.id = p.room_id
                               WHERE p.pub = ? AND p.seen >= ?', [(string) ($input['to'] ?? ''), $now - 25])->fetch();
            if (!$target || $target['client_id'] === $client) {
                throw new RuntimeException('offline');
            }
            if ($target['rstatus'] === 'play') {
                throw new RuntimeException('busy');
            }
            $room = myRoom($pdo, $player);
            if (!$room) {
                $room = newRoom($pdo, $game, $size, $ai, 'lobby', $player, $now);
            } elseif ($room['host_id'] !== $client || $room['status'] === 'play') {
                throw new RuntimeException('not_host');
            }
            if (count(activeMembers($pdo, (int) $room['id'])) >= (int) $room['size']) {
                throw new RuntimeException('full');
            }
            $dupe = q($pdo, "SELECT id FROM testcom_lobby_invites WHERE room_id = ? AND to_pub = ? AND status = 'pending'", [$room['id'], $target['pub']])->fetch();
            if (!$dupe) {
                q($pdo, 'INSERT INTO testcom_lobby_invites (room_id, from_pub, from_name, to_pub, status, created) VALUES (?, ?, ?, ?, ?, ?)',
                    [$room['id'], $player['pub'], $player['nickname'], $target['pub'], 'pending', $now]);
            }
            $result = ['ok' => true, 'room' => roomInfo($pdo, getRoom($pdo, (int) $room['id']), $client, $now)];
            break;
        }

        case 'answer_invite': {
            $invite = q($pdo, "SELECT * FROM testcom_lobby_invites WHERE id = ? AND to_pub = ? AND status = 'pending'", [(int) ($input['id'] ?? 0), $player['pub']])->fetch();
            if (!$invite) {
                throw new RuntimeException('gone');
            }
            $accept = !empty($input['accept']);
            q($pdo, 'UPDATE testcom_lobby_invites SET status = ? WHERE id = ?', [$accept ? 'accepted' : 'declined', $invite['id']]);
            if (!$accept) {
                $result = ['ok' => true, 'room' => null];
                break;
            }
            $room = getRoom($pdo, (int) $invite['room_id']);
            if (!$room || !in_array($room['status'], ['lobby', 'search'], true)) {
                throw new RuntimeException('gone');
            }
            leaveRoom($pdo, $client, $now);
            $player['room_id'] = null;
            if (!joinRoom($pdo, $room, $player, $now)) {
                throw new RuntimeException('full');
            }
            $room = getRoom($pdo, (int) $room['id']);
            if (count(activeMembers($pdo, (int) $room['id'])) >= (int) $room['size'] && $room['status'] === 'search') {
                beginPlay($pdo, $room, 4, $now);
                $room = getRoom($pdo, (int) $room['id']);
            }
            $result = ['ok' => true, 'room' => roomInfo($pdo, $room, $client, $now)];
            break;
        }

        case 'emit': {
            $room = myRoom($pdo, $player);
            if (!$room || $room['status'] !== 'play') {
                throw new RuntimeException('no_match');
            }
            $m = q($pdo, 'SELECT slot FROM testcom_lobby_members WHERE room_id = ? AND client_id = ? AND left_at IS NULL', [$room['id'], $client])->fetch();
            if (!$m) {
                throw new RuntimeException('no_match');
            }
            $kind = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($input['kind'] ?? '')));
            $body = $input['body'] ?? null;
            if ($kind === '' || strlen(json_encode($body)) > 1500) {
                throw new RuntimeException('bad_request');
            }
            $result = ['ok' => true, 'id' => addEvent($pdo, (int) $room['id'], (int) $m['slot'], $kind, $body, $now)];
            break;
        }

        case 'events': {
            $room = myRoom($pdo, $player);
            if (!$room || $room['status'] !== 'play') {
                $result = ['ok' => true, 'events' => [], 'now' => $now, 'ended' => true];
                break;
            }
            // players that stopped polling are treated as gone
            $members = q($pdo, 'SELECT m.slot, m.client_id, p.seen FROM testcom_lobby_members m
                                LEFT JOIN testcom_lobby_players p ON p.client_id = m.client_id
                                WHERE m.room_id = ? AND m.left_at IS NULL AND m.client_id <> ?', [$room['id'], $client])->fetchAll();
            foreach ($members as $m) {
                if ((int) $m['seen'] < $now - 14) {
                    q($pdo, 'UPDATE testcom_lobby_members SET left_at = ? WHERE room_id = ? AND client_id = ?', [$now, $room['id'], $m['client_id']]);
                    addEvent($pdo, (int) $room['id'], (int) $m['slot'], 'leave', null, $now);
                }
            }
            $mine = q($pdo, 'SELECT slot FROM testcom_lobby_members WHERE room_id = ? AND client_id = ?', [$room['id'], $client])->fetch();
            $rows = q($pdo, 'SELECT id, slot, kind, body FROM testcom_lobby_events WHERE room_id = ? AND id > ? AND slot <> ? ORDER BY id LIMIT 300',
                [$room['id'], (int) ($input['after'] ?? 0), $mine ? (int) $mine['slot'] : -1])->fetchAll();
            $events = array_map(fn($e) => ['id' => (int) $e['id'], 'slot' => (int) $e['slot'], 'kind' => $e['kind'], 'body' => $e['body'] !== null ? json_decode($e['body'], true) : null], $rows);
            $result = ['ok' => true, 'events' => $events, 'now' => $now];
            break;
        }

        default:
            throw new RuntimeException('unknown_action');
    }
    $pdo->commit();
    out($result);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->commit();   // keep the presence update even when the action is refused
    }
    out(['ok' => false, 'error' => $e->getMessage()], 200);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Testcom lobby error: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'server'], 500);
}
