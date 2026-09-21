<?php
declare(strict_types=1);

/*
 * Mafia endpoint (plain HTTP polling, so it works on ordinary shared hosting).
 * The server owns everything: rooms, roles, phases, timers, votes. A client only sends actions and receives
 * the filtered view of its own seat (see mf_view in mafia_engine.php).
 * Every request runs inside one database transaction that locks the room row.
 */

require_once __DIR__ . '/mafia_engine.php';
require_once __DIR__ . '/mafia_db.php';

$pdo = mf_connect();
mf_schema($pdo);
$action = (string) ($_GET['action'] ?? '');
$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? mf_input() : [];

/* Local development only (php -S with the SQLite fallback): sign in as a test user without the site's login. */
if ($action === 'dev_login' && PHP_SAPI === 'cli-server' && mf_is_sqlite($pdo)) {
    $n = max(1, min(50, (int) ($_GET['user'] ?? 1)));
    $email = "dev$n@example.local";
    $pdo->prepare("INSERT OR IGNORE INTO testcom_users (email, nickname) VALUES (?, ?)")->execute([$email, (string) ($_GET['name'] ?? "Тест$n")]);
    $row = $pdo->query("SELECT id FROM testcom_users WHERE email = " . $pdo->quote($email))->fetch();
    session_name('testcom_session');
    session_start();
    $_SESSION['testcom_user_id'] = (int) $row['id'];
    session_write_close();
    mf_out(['ok' => true, 'id' => (int) $row['id']]);
}

/* Local development only: a shared clock offset lets an automated test skip ahead in game time. */
$devWarp = 0.0;
if (PHP_SAPI === 'cli-server' && mf_is_sqlite($pdo)) {
    $warpFile = sys_get_temp_dir() . '/testcom_mafia_warp';
    if ($action === 'dev_warp') {
        $add = (float) ($_GET['add'] ?? 0);
        $cur = is_file($warpFile) ? (float) file_get_contents($warpFile) : 0.0;
        file_put_contents($warpFile, (string) ($add === 0.0 && isset($_GET['reset']) ? 0.0 : $cur + $add));
        mf_out(['ok' => true]);
    }
    $devWarp = is_file($warpFile) ? (float) file_get_contents($warpFile) : 0.0;
}

$user = mf_require_user($pdo);
$uid = $user['id'];
$now = microtime(true) + $devWarp;
$t = time();

try {
    switch ($action) {
        case 'hub':
            mf_out(mf_hub($pdo, $user, $t));
        case 'create':
            mf_out(mf_create($pdo, $user, $in, $now));
        case 'join':
            mf_out(mf_join_room($pdo, $user, mf_code((string) ($in['code'] ?? '')), $now));
        case 'quick':
            mf_out(mf_quick($pdo, $user, (int) ($in['size'] ?? 0), $now));
        case 'state':
            mf_out(mf_state($pdo, $user, $now));
        case 'leave':
            mf_out(mf_leave_room($pdo, $user, $now));
        case 'invite':
            mf_out(mf_invite($pdo, $user, (string) ($in['to'] ?? ''), $t));
        case 'answer_invite':
            mf_out(mf_answer_invite($pdo, $user, (int) ($in['id'] ?? 0), !empty($in['accept']), $now));
        case 'ready':
        case 'add_ai':
        case 'fill_ai':
        case 'remove_ai':
        case 'kick':
        case 'start':
        case 'rematch':
        case 'act':
            mf_out(mf_room_action($pdo, $user, $action, $in, $now));
        default:
            mf_fail('bad_request', 'Неизвестный запрос.', 400);
    }
} catch (MfError $e) {
    if ($e->codeName === 'not_member' || $e->codeName === 'no_room') {
        $pdo->prepare('DELETE FROM testcom_mafia_members WHERE user_id = ?')->execute([$uid]);
    }
    mf_fail($e->codeName, $e->getMessage());
} catch (Throwable $e) {
    error_log('Testcom mafia error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    mf_fail('server', 'Ошибка сервера. Попробуйте ещё раз.', 500);
}

/* ------------------------------------------------------------------ */

function mf_code(string $code): string
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    return $code;
}

function mf_new_code(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 5; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function mf_real_humans(array $s): int
{
    $n = 0;
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && !$seat['ai'] && $seat['uid'] > 0) {
            $n++;
        }
    }
    return $n;
}

function mf_membership(PDO $pdo, int $uid): ?string
{
    $st = $pdo->prepare('SELECT code FROM testcom_mafia_members WHERE user_id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    return $row ? (string) $row['code'] : null;
}

/** Runs $fn(array &$s) on the locked room, then stores the room. Deletes the room when no real player is left. */
function mf_room_tx(PDO $pdo, string $code, callable $fn)
{
    $sqlite = mf_is_sqlite($pdo);
    $pdo->exec($sqlite ? 'BEGIN IMMEDIATE' : 'START TRANSACTION');
    try {
        $st = $pdo->prepare('SELECT state FROM testcom_mafia_rooms WHERE code = ?' . ($sqlite ? '' : ' FOR UPDATE'));
        $st->execute([$code]);
        $row = $st->fetch();
        if (!$row) {
            throw new MfError('no_room', 'Комната не найдена или уже закрыта.');
        }
        $before = (string) $row['state'];
        $s = json_decode($before, true);
        if (!is_array($s)) {
            throw new MfError('no_room', 'Комната повреждена.');
        }
        $result = $fn($s);
        mf_settle($pdo, $s);
        $humans = mf_real_humans($s);
        if ($humans === 0) {
            $pdo->prepare('DELETE FROM testcom_mafia_rooms WHERE code = ?')->execute([$code]);
            $pdo->prepare('DELETE FROM testcom_mafia_members WHERE code = ?')->execute([$code]);
        } else {
            $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $status = $s['phase'] === 'LOBBY' ? 'lobby' : ($s['phase'] === 'GAME_OVER' ? 'over' : 'play');
            if ($json !== $before) {
                $pdo->prepare('UPDATE testcom_mafia_rooms SET state = ?, status = ?, humans = ?, max_humans = ?, updated = ? WHERE code = ?')
                    ->execute([$json, $status, $humans, $s['max_humans'], time(), $code]);
            }
        }
        $pdo->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $ignore) {
        }
        throw $e;
    }
}

/** Side effects the pure engine only announces: released seats and finished-match results. */
function mf_settle(PDO $pdo, array &$s): void
{
    if ($s['released']) {
        $ids = array_values(array_unique(array_map('intval', $s['released'])));
        foreach ($ids as $id) {
            $pdo->prepare('DELETE FROM testcom_mafia_members WHERE user_id = ? AND code = ?')->execute([$id, $s['code']]);
        }
        $s['released'] = [];
    }
    if ($s['phase'] === 'GAME_OVER' && is_array($s['final']) && !$s['final_done']) {
        $s['final_done'] = true;
        foreach ($s['final'] as $row) {
            mf_record_result($pdo, (int) $row['uid'], $row);
        }
    }
}

function mf_record_result(PDO $pdo, int $uid, array $row): void
{
    $ignore = mf_is_sqlite($pdo) ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $pdo->prepare("$ignore INTO testcom_mafia_stats (user_id) VALUES (?)")->execute([$uid]);
    $won = !empty($row['won']);
    if (!empty($row['ai_match'])) {
        // matches with AI players are counted separately from the competitive numbers
        $pdo->prepare('UPDATE testcom_mafia_stats SET ai_played = ai_played + 1, ai_wins = ai_wins + ? WHERE user_id = ?')->execute([$won ? 1 : 0, $uid]);
    } else {
        $role = (string) $row['role'];
        $pdo->prepare('UPDATE testcom_mafia_stats SET played = played + 1, wins = wins + ?, mafia_wins = mafia_wins + ?,
            civilian_wins = civilian_wins + ?, detective_wins = detective_wins + ?, doctor_wins = doctor_wins + ? WHERE user_id = ?')
            ->execute([$won ? 1 : 0, $won && $role === 'mafia' ? 1 : 0, $won && $role === 'civilian' ? 1 : 0, $won && $role === 'detective' ? 1 : 0, $won && $role === 'doctor' ? 1 : 0, $uid]);
    }
    // points for the shared leaderboard: full value for matches between real players, half with AI
    $points = ($won ? 1200 : 250) + (!empty($row['alive']) ? 150 : 0);
    if (!empty($row['ai_match'])) {
        $points = intdiv($points, 2);
    }
    mf_points_add($pdo, $uid, 'mafia', $points);
}

function mf_stats(PDO $pdo, int $uid): array
{
    $st = $pdo->prepare('SELECT played, wins, mafia_wins, civilian_wins, detective_wins, doctor_wins, ai_played, ai_wins FROM testcom_mafia_stats WHERE user_id = ?');
    $st->execute([$uid]);
    $row = $st->fetch() ?: ['played' => 0, 'wins' => 0, 'mafia_wins' => 0, 'civilian_wins' => 0, 'detective_wins' => 0, 'doctor_wins' => 0, 'ai_played' => 0, 'ai_wins' => 0];
    $row = array_map('intval', $row);
    $row['win_rate'] = $row['played'] > 0 ? (int) round(100 * $row['wins'] / $row['played']) : 0;
    $st = $pdo->prepare('SELECT total FROM testcom_points WHERE user_id = ?');
    $st->execute([$uid]);
    $p = $st->fetch();
    $row['points'] = $p ? (int) $p['total'] : 0;
    return $row;
}

/* ---------------- hub: menu data ---------------- */

function mf_hub(PDO $pdo, array $user, int $t): array
{
    $uid = $user['id'];
    $ignore = mf_is_sqlite($pdo) ? 'INSERT OR REPLACE INTO testcom_mafia_seen (user_id, seen) VALUES (?, ?)' : 'REPLACE INTO testcom_mafia_seen (user_id, seen) VALUES (?, ?)';
    $pdo->prepare($ignore)->execute([$uid, $t]);
    if (random_int(1, 40) === 1) {
        mf_cleanup($pdo, $t);
    }
    $room = null;
    $code = mf_membership($pdo, $uid);
    if ($code !== null) {
        $st = $pdo->prepare('SELECT code, status, size FROM testcom_mafia_rooms WHERE code = ?');
        $st->execute([$code]);
        $r = $st->fetch();
        if ($r) {
            $room = ['code' => $r['code'], 'status' => $r['status'], 'size' => (int) $r['size']];
        } else {
            $pdo->prepare('DELETE FROM testcom_mafia_members WHERE user_id = ?')->execute([$uid]);
        }
    }
    // people who have the Mafia screen open right now
    $st = $pdo->prepare('SELECT s.user_id, u.nickname FROM testcom_mafia_seen s JOIN testcom_users u ON u.id = s.user_id
        WHERE s.seen >= ? AND s.user_id <> ? ORDER BY s.seen DESC LIMIT 40');
    $st->execute([$t - 25, $uid]);
    $online = [];
    foreach ($st->fetchAll() as $row) {
        $name = mf_clean_name((string) ($row['nickname'] ?? ''));
        $rc = mf_membership($pdo, (int) $row['user_id']);
        $online[] = ['pub' => mf_pub((int) $row['user_id']), 'name' => $name !== '' ? $name : 'Игрок' . ((int) $row['user_id'] % 1000), 'busy' => $rc !== null];
    }
    $st = $pdo->prepare("SELECT i.id, i.code, u.nickname FROM testcom_mafia_invites i JOIN testcom_users u ON u.id = i.from_uid
        JOIN testcom_mafia_rooms r ON r.code = i.code
        WHERE i.to_uid = ? AND i.status = 'new' AND i.created >= ? AND r.status = 'lobby' AND r.humans < r.max_humans ORDER BY i.id DESC LIMIT 5");
    $st->execute([$uid, $t - 180]);
    $invites = [];
    foreach ($st->fetchAll() as $row) {
        $name = mf_clean_name((string) ($row['nickname'] ?? ''));
        $invites[] = ['id' => (int) $row['id'], 'code' => $row['code'], 'from' => $name !== '' ? $name : 'Игрок'];
    }
    return [
        'ok' => true, 'me' => ['name' => $user['name']], 'room' => $room, 'online' => $online, 'invites' => $invites,
        'stats' => mf_stats($pdo, $uid), 'top' => mf_top_players($pdo, 3),
    ];
}

function mf_cleanup(PDO $pdo, int $t): void
{
    $pdo->prepare('DELETE FROM testcom_mafia_rooms WHERE updated < ?')->execute([$t - 4 * 3600]);
    $pdo->exec('DELETE FROM testcom_mafia_members WHERE code NOT IN (SELECT code FROM testcom_mafia_rooms)');
    $pdo->prepare('DELETE FROM testcom_mafia_invites WHERE created < ?')->execute([$t - 900]);
    $pdo->prepare('DELETE FROM testcom_mafia_seen WHERE seen < ?')->execute([$t - 3600]);
    $pdo->prepare('DELETE FROM testcom_mafia_rate WHERE win_start < ?')->execute([$t - 3600]);
}

/* ---------------- rooms ---------------- */

/** A player can sit in one room only. Leaves an unfinished lobby, refuses to abandon a running game silently. */
function mf_leave_previous(PDO $pdo, int $uid, string $except, float $now): void
{
    $old = mf_membership($pdo, $uid);
    if ($old === null || $old === $except) {
        return;
    }
    try {
        mf_room_tx($pdo, $old, function (array &$s) use ($uid, $now) {
            if (mf_seat_of($s, $uid) === null) {
                return null;
            }
            if (!in_array($s['phase'], ['LOBBY', 'GAME_OVER'], true)) {
                throw new MfError('in_game', 'Вы уже участвуете в игре. Вернитесь в неё или выйдите из неё.');
            }
            mf_leave($s, $uid, $now);
            return null;
        });
    } catch (MfError $e) {
        if ($e->codeName === 'in_game') {
            throw new MfError('in_game', 'Вы уже участвуете в игре (комната ' . $old . '). Вернитесь в неё или выйдите из неё.');
        }
    }
    $pdo->prepare('DELETE FROM testcom_mafia_members WHERE user_id = ?')->execute([$uid]);
}

function mf_bind_member(PDO $pdo, int $uid, string $code, int $t): void
{
    $pdo->prepare('DELETE FROM testcom_mafia_members WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('INSERT INTO testcom_mafia_members (user_id, code, joined) VALUES (?, ?, ?)')->execute([$uid, $code, $t]);
}

function mf_create(PDO $pdo, array $user, array $in, float $now): array
{
    $uid = $user['id'];
    if (!mf_rate($pdo, $uid, 'create', 4, 60)) {
        throw new MfError('rate', 'Слишком много комнат. Подождите минуту.');
    }
    $mode = (string) ($in['mode'] ?? '');
    $size = (int) ($in['size'] ?? 6);
    $fill = !empty($in['fill_ai']);
    $public = !empty($in['public']);
    $max = $size;
    $auto = false;
    if ($mode === 'duo') {
        $size = 6;
        $fill = true;
        $max = 2;
    } elseif ($mode === 'ai') {
        $size = 6;
        $fill = true;
        $max = 1;
        $public = false;
        $auto = true;
    }
    if (!in_array($size, MF_SIZES, true)) {
        throw new MfError('bad_size', 'Выберите режим: 4, 6 или 10 игроков.');
    }
    mf_leave_previous($pdo, $uid, '', $now);
    $code = null;
    for ($try = 0; $try < 8 && $code === null; $try++) {
        $candidate = mf_new_code();
        $s = mf_new_state($candidate, $size, $fill, $public, $max, $now);
        mf_join($s, $uid, $user['name'], $now);
        try {
            $pdo->prepare('INSERT INTO testcom_mafia_rooms (code, size, status, is_public, humans, max_humans, state, created, updated) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)')
                ->execute([$candidate, $size, 'lobby', $public ? 1 : 0, $s['max_humans'], json_encode($s, JSON_UNESCAPED_UNICODE), time(), time()]);
            $code = $candidate;
        } catch (PDOException $e) {
            // code already taken: try another one
        }
    }
    if ($code === null) {
        throw new MfError('server', 'Не удалось создать комнату. Попробуйте ещё раз.');
    }
    mf_bind_member($pdo, $uid, $code, time());
    $view = mf_room_tx($pdo, $code, function (array &$s) use ($uid, $now, $auto) {
        if ($auto) {
            mf_start($s, $uid, $now);
        }
        mf_tick($s, $now);
        return mf_view($s, $uid, $now);
    });
    return ['ok' => true, 'code' => $code, 'view' => $view];
}

function mf_join_room(PDO $pdo, array $user, string $code, float $now): array
{
    $uid = $user['id'];
    if (!mf_rate($pdo, $uid, 'join', 15, 60)) {
        throw new MfError('rate', 'Слишком много попыток. Подождите минуту.');
    }
    if (!preg_match('/^[A-Z0-9]{4,8}$/', $code)) {
        throw new MfError('no_room', 'Комната с таким кодом не найдена.');
    }
    mf_leave_previous($pdo, $uid, $code, $now);
    $view = mf_room_tx($pdo, $code, function (array &$s) use ($pdo, $user, $now) {
        mf_join($s, $user['id'], $user['name'], $now);
        mf_touch($s, $user['id'], $now);
        mf_tick($s, $now);
        mf_bind_member($pdo, $user['id'], $s['code'], time());
        return mf_view($s, $user['id'], $now);
    });
    return ['ok' => true, 'code' => $code, 'view' => $view];
}

function mf_quick(PDO $pdo, array $user, int $size, float $now): array
{
    $uid = $user['id'];
    if (!mf_rate($pdo, $uid, 'join', 15, 60)) {
        throw new MfError('rate', 'Слишком много попыток. Подождите минуту.');
    }
    $sql = "SELECT code FROM testcom_mafia_rooms WHERE is_public = 1 AND status = 'lobby' AND humans >= 1 AND humans < max_humans AND updated >= ?"
        . ($size > 0 ? ' AND size = ' . (int) $size : '') . ' ORDER BY humans DESC, updated DESC LIMIT 6';
    $st = $pdo->prepare($sql);
    $st->execute([time() - 40]);
    foreach ($st->fetchAll() as $row) {
        try {
            mf_leave_previous($pdo, $uid, (string) $row['code'], $now);
            return mf_join_room($pdo, $user, (string) $row['code'], $now);
        } catch (MfError $e) {
            if ($e->codeName === 'in_game') {
                throw $e;
            }
        }
    }
    return ['ok' => false, 'error' => 'no_match', 'message' => 'Подходящая игра пока не найдена.'];
}

function mf_state(PDO $pdo, array $user, float $now): array
{
    $uid = $user['id'];
    $code = mf_membership($pdo, $uid);
    if ($code === null) {
        return ['ok' => true, 'view' => null];
    }
    $view = mf_room_tx($pdo, $code, function (array &$s) use ($uid, $now) {
        if (mf_seat_of($s, $uid) === null) {
            throw new MfError('not_member', 'Вы больше не участвуете в этой игре.');
        }
        mf_touch($s, $uid, $now);
        mf_tick($s, $now);
        return mf_view($s, $uid, $now);
    });
    return ['ok' => true, 'code' => $code, 'view' => $view, 'now' => $now];
}

function mf_leave_room(PDO $pdo, array $user, float $now): array
{
    $uid = $user['id'];
    $code = mf_membership($pdo, $uid);
    if ($code !== null) {
        try {
            mf_room_tx($pdo, $code, function (array &$s) use ($uid, $now) {
                mf_leave($s, $uid, $now);
                mf_tick($s, $now);
                return null;
            });
        } catch (MfError $e) {
            // the room is already gone
        }
    }
    $pdo->prepare('DELETE FROM testcom_mafia_members WHERE user_id = ?')->execute([$uid]);
    return ['ok' => true];
}

function mf_room_action(PDO $pdo, array $user, string $action, array $in, float $now): array
{
    $uid = $user['id'];
    $code = mf_membership($pdo, $uid);
    if ($code === null) {
        throw new MfError('not_member', 'Вы не находитесь в комнате.');
    }
    if ($action === 'act' && !mf_rate($pdo, $uid, 'act', 60, 10)) {
        throw new MfError('rate', 'Слишком много действий. Подождите немного.');
    }
    $view = mf_room_tx($pdo, $code, function (array &$s) use ($uid, $action, $in, $now) {
        if (mf_seat_of($s, $uid) === null) {
            throw new MfError('not_member', 'Вы больше не участвуете в этой игре.');
        }
        mf_touch($s, $uid, $now);
        mf_tick($s, $now);
        // an action addressed to an earlier phase (a late or duplicated request) is ignored, not applied to the wrong phase
        if (isset($in['pid']) && $action === 'act' && (int) $in['pid'] !== $s['pid'] && ($in['type'] ?? '') !== 'chat') {
            throw new MfError('stale', 'Фаза игры уже сменилась.');
        }
        switch ($action) {
            case 'ready':
                mf_set_ready($s, $uid, !empty($in['ready']));
                break;
            case 'add_ai':
                mf_add_ai($s, $uid, $now);
                break;
            case 'fill_ai':
                mf_fill_ai($s, $uid, $now);
                break;
            case 'remove_ai':
                mf_remove_ai($s, $uid, (int) ($in['seat'] ?? -1));
                break;
            case 'kick':
                mf_kick($s, $uid, (int) ($in['seat'] ?? -1), $now);
                break;
            case 'start':
                mf_start($s, $uid, $now);
                break;
            case 'rematch':
                mf_rematch($s, $now);
                break;
            case 'act':
                $type = (string) ($in['type'] ?? '');
                mf_act($s, $uid, $type, $type === 'chat' ? (string) ($in['text'] ?? '') : ($in['target'] ?? null), $now);
                break;
        }
        mf_tick($s, $now);
        return mf_view($s, $uid, $now);
    });
    return ['ok' => true, 'view' => $view];
}

/* ---------------- invitations ---------------- */

function mf_invite(PDO $pdo, array $user, string $pub, int $t): array
{
    $uid = $user['id'];
    if (!mf_rate($pdo, $uid, 'invite', 12, 60)) {
        throw new MfError('rate', 'Слишком много приглашений. Подождите минуту.');
    }
    $code = mf_membership($pdo, $uid);
    if ($code === null) {
        throw new MfError('not_member', 'Сначала создайте комнату.');
    }
    $st = $pdo->prepare('SELECT status, humans, max_humans FROM testcom_mafia_rooms WHERE code = ?');
    $st->execute([$code]);
    $room = $st->fetch();
    if (!$room || $room['status'] !== 'lobby') {
        throw new MfError('bad_phase', 'Приглашать можно только в комнату, где игра ещё не началась.');
    }
    if ((int) $room['humans'] >= (int) $room['max_humans']) {
        throw new MfError('full', 'Комната заполнена.');
    }
    $st = $pdo->prepare('SELECT user_id FROM testcom_mafia_seen WHERE seen >= ? AND user_id <> ?');
    $st->execute([$t - 25, $uid]);
    $target = 0;
    foreach ($st->fetchAll() as $row) {
        if (hash_equals(mf_pub((int) $row['user_id']), $pub)) {
            $target = (int) $row['user_id'];
        }
    }
    if ($target === 0) {
        throw new MfError('offline', 'Этот игрок сейчас не в сети.');
    }
    if (mf_membership($pdo, $target) === $code) {
        throw new MfError('already', 'Этот игрок уже в вашей комнате.');
    }
    $st = $pdo->prepare("SELECT id FROM testcom_mafia_invites WHERE code = ? AND to_uid = ? AND status = 'new' AND created >= ?");
    $st->execute([$code, $target, $t - 120]);
    if (!$st->fetch()) {
        $pdo->prepare('INSERT INTO testcom_mafia_invites (code, from_uid, to_uid, created, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$code, $uid, $target, $t, 'new']);
    }
    return ['ok' => true];
}

function mf_answer_invite(PDO $pdo, array $user, int $id, bool $accept, float $now): array
{
    $uid = $user['id'];
    $st = $pdo->prepare("SELECT code FROM testcom_mafia_invites WHERE id = ? AND to_uid = ? AND status = 'new'");
    $st->execute([$id, $uid]);
    $row = $st->fetch();
    if (!$row) {
        throw new MfError('gone', 'Приглашение уже недействительно.');
    }
    $pdo->prepare('UPDATE testcom_mafia_invites SET status = ? WHERE id = ?')->execute([$accept ? 'accepted' : 'declined', $id]);
    if (!$accept) {
        return ['ok' => true];
    }
    return mf_join_room($pdo, $user, (string) $row['code'], $now);
}
