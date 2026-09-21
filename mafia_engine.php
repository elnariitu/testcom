<?php
declare(strict_types=1);

/*
 * Mafia: server-authoritative game engine (no database, no HTTP, no output).
 * The whole room lives in one array ($s). Clients only send actions; every action is validated here,
 * and mf_view() is the ONLY function that turns state into something a player may see.
 * Everything a player reads is Russian (the rest of the site stays English).
 */

class MfError extends RuntimeException
{
    public string $codeName;

    public function __construct(string $codeName, string $message)
    {
        parent::__construct($message);
        $this->codeName = $codeName;
    }
}

const MF_SIZES = [4, 6, 10];
const MF_MAX_PLAYERS = 10;
const MF_DUR = ['ROLE_REVEAL' => 5, 'NIGHT' => 20, 'MORNING' => 8, 'DISCUSSION' => 60, 'VOTING' => 20, 'REVOTE' => 20, 'ELIMINATION' => 7];
const MF_STALE = 12;          // no poll for this long = "reconnecting"
const MF_LOBBY_STALE = 30;    // lobby seat is freed after this
const MF_GRACE = 60;          // seat is kept this long during a game
const MF_ABANDON = 180;       // nobody real is connected for this long: the room is closed
const MF_CHAT_MAX = 200;
const MF_ROLE_NAMES = ['mafia' => 'Мафия', 'civilian' => 'Мирный житель', 'doctor' => 'Доктор', 'detective' => 'Детектив'];
const MF_AI_NAMES = ['Алексей', 'Дмитрий', 'Максим', 'Анна', 'София', 'Илья', 'Мария', 'Никита', 'Ольга', 'Артём', 'Елена', 'Кирилл', 'Виктория', 'Павел'];
const MF_DAY_PHASES = ['MORNING', 'DISCUSSION', 'VOTING', 'REVOTE', 'ELIMINATION'];

require_once __DIR__ . '/mafia_ai.php';

/* ---------- random helpers (mt_rand so tests can seed them) ---------- */

function mf_rand(int $a, int $b): int
{
    return $a >= $b ? $a : mt_rand($a, $b);
}

function mf_frand(float $a, float $b): float
{
    return $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
}

function mf_chance(float $p): bool
{
    return mf_frand(0, 1) < $p;
}

function mf_shuffle(array $list): array
{
    $list = array_values($list);
    for ($i = count($list) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    }
    return $list;
}

/* ---------- roles ---------- */

function mf_roles_for(int $size): array
{
    switch ($size) {
        case 4:
            return ['mafia', 'civilian', 'civilian', 'civilian'];
        case 6:
            return ['mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian'];
        case 10:
            return ['mafia', 'mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian', 'civilian', 'civilian', 'civilian'];
    }
    throw new MfError('bad_size', 'Недопустимый размер комнаты.');
}

/* ---------- state ---------- */

function mf_empty_seat(int $i): array
{
    return [
        'seat' => $i, 'occ' => false, 'uid' => 0, 'name' => '', 'ai' => false, 'ready' => false,
        'joined' => 0.0, 'seen' => 0.0, 'conn' => true, 'disc' => null,
        'alive' => true, 'role' => null, 'revealed' => false, 'out' => null, 'took_over' => false, 'chat_t' => [],
    ];
}

function mf_new_state(string $code, int $size, bool $fillAi, bool $public, int $maxHumans, float $now): array
{
    if (!in_array($size, MF_SIZES, true) || $size > MF_MAX_PLAYERS) {
        throw new MfError('bad_size', 'Недопустимый размер комнаты.');
    }
    $seats = [];
    for ($i = 0; $i < $size; $i++) {
        $seats[] = mf_empty_seat($i);
    }
    return [
        'code' => $code, 'size' => $size, 'fill_ai' => $fillAi, 'public' => $public,
        'max_humans' => max(1, min($maxHumans, $size)), 'host_uid' => 0,
        'phase' => 'LOBBY', 'pid' => 1, 'phase_start' => $now, 'phase_end' => null, 'day' => 0,
        'seats' => $seats, 'events' => [], 'chat' => [], 'seq' => 0, 'sched' => [],
        'night' => null, 'votes' => null, 'candidates' => null, 'private' => [], 'ai' => [],
        'vote_rounds' => 0, 'winner' => null, 'released' => [], 'final' => null, 'final_done' => false,
        'created' => $now, 'reveal_roles' => true, 'last_elim' => null, 'morning' => null, 'phases' => ['LOBBY'],
        'started' => 0.0, 'abandon_since' => null, 'mafia_notice' => null,
    ];
}

function mf_seat_of(array $s, int $uid): ?int
{
    if ($uid <= 0) {
        return null;
    }
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && !$seat['ai'] && $seat['uid'] === $uid) {
            return $seat['seat'];
        }
    }
    return null;
}

function mf_count(array $s, string $what): int
{
    $n = 0;
    foreach ($s['seats'] as $seat) {
        if (!$seat['occ']) {
            continue;
        }
        if ($what === 'occ' || ($what === 'humans' && !$seat['ai']) || ($what === 'ai' && $seat['ai'])) {
            $n++;
        }
    }
    return $n;
}

function mf_alive_seats(array $s, ?string $role = null): array
{
    $out = [];
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && $seat['alive'] && ($role === null || $seat['role'] === $role)) {
            $out[] = $seat['seat'];
        }
    }
    return $out;
}

function mf_event(array &$s, string $type, string $text, float $now, array $extra = []): void
{
    $s['seq']++;
    $s['events'][] = array_merge(['id' => $s['seq'], 't' => $now, 'type' => $type, 'text' => $text], $extra);
    if (count($s['events']) > 60) {
        $s['events'] = array_slice($s['events'], -60);
    }
}

function mf_set_phase(array &$s, string $phase, float $now, ?int $dur): void
{
    $s['phase'] = $phase;
    $s['pid']++;
    $s['phase_start'] = $now;
    $s['phase_end'] = $dur === null ? null : $now + $dur;
    $s['phases'][] = $phase;
    if (count($s['phases']) > 400) {
        $s['phases'] = array_slice($s['phases'], -200);
    }
}

function mf_clean_name(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $name) ?? '';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    return mb_substr($name, 0, 20);
}

function mf_unique_name(array $s, string $name): string
{
    $name = mf_clean_name($name);
    if ($name === '') {
        $name = 'Игрок';
    }
    $taken = [];
    foreach ($s['seats'] as $seat) {
        if ($seat['occ']) {
            $taken[mb_strtolower($seat['name'])] = true;
        }
    }
    $base = $name;
    $n = 2;
    while (isset($taken[mb_strtolower($name)])) {
        $name = mb_substr($base, 0, 17) . ' ' . $n++;
    }
    return $name;
}

function mf_pick_host(array &$s, float $now, bool $announce = true): void
{
    $best = null;
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && !$seat['ai'] && $seat['conn'] && ($best === null || $seat['joined'] < $best['joined'])) {
            $best = $seat;
        }
    }
    if ($best === null) {
        foreach ($s['seats'] as $seat) {
            if ($seat['occ'] && !$seat['ai'] && ($best === null || $seat['joined'] < $best['joined'])) {
                $best = $seat;
            }
        }
    }
    $new = $best ? $best['uid'] : 0;
    if ($new !== $s['host_uid']) {
        $had = $s['host_uid'] !== 0;
        $s['host_uid'] = $new;
        if ($best) {
            $s['seats'][$best['seat']]['ready'] = true;
            if ($had && $announce) {
                mf_event($s, 'host', $best['name'] . ' теперь является владельцем комнаты.', $now);
            }
        }
    }
}

/* ---------- lobby ---------- */

function mf_join(array &$s, int $uid, string $name, float $now): int
{
    $existing = mf_seat_of($s, $uid);
    if ($existing !== null) {
        return $existing;
    }
    if ($s['phase'] !== 'LOBBY') {
        throw new MfError('started', 'Игра уже началась.');
    }
    if (mf_count($s, 'humans') >= $s['max_humans']) {
        throw new MfError('full', 'Комната заполнена.');
    }
    $free = null;
    foreach ($s['seats'] as $seat) {
        if (!$seat['occ']) {
            $free = $seat['seat'];
            break;
        }
    }
    if ($free === null) {
        // a robot may be sitting in the seat: humans have priority over AI
        foreach ($s['seats'] as $seat) {
            if ($seat['occ'] && $seat['ai']) {
                $free = $seat['seat'];
                break;
            }
        }
    }
    if ($free === null) {
        throw new MfError('full', 'Комната заполнена.');
    }
    $seat = mf_empty_seat($free);
    $seat['occ'] = true;
    $seat['uid'] = $uid;
    $seat['name'] = mf_unique_name($s, $name);
    $seat['joined'] = $now;
    $seat['seen'] = $now;
    $s['seats'][$free] = $seat;
    if ($s['host_uid'] === 0) {
        $s['host_uid'] = $uid;
        $s['seats'][$free]['ready'] = true;
    }
    mf_event($s, 'join', $seat['name'] . ' подключился.', $now);
    return $free;
}

function mf_free_seat(array &$s, int $i): void
{
    $s['seats'][$i] = mf_empty_seat($i);
}

function mf_leave(array &$s, int $uid, float $now): void
{
    $i = mf_seat_of($s, $uid);
    if ($i === null) {
        return;
    }
    $seat = $s['seats'][$i];
    $s['released'][] = $uid;
    if ($s['phase'] === 'LOBBY') {
        mf_free_seat($s, $i);
        mf_event($s, 'leave', $seat['name'] . ' покинул комнату.', $now);
        if ($s['host_uid'] === $uid) {
            $s['host_uid'] = 0;
            mf_pick_host($s, $now);
        }
        return;
    }
    if ($s['phase'] === 'GAME_OVER') {
        $s['seats'][$i]['uid'] = 0;
        $s['seats'][$i]['conn'] = false;
        if ($s['host_uid'] === $uid) {
            $s['host_uid'] = 0;
            mf_pick_host($s, $now);
        }
        return;
    }
    mf_replace_human($s, $i, $now, true);
    if ($s['host_uid'] === $uid) {
        $s['host_uid'] = 0;
        mf_pick_host($s, $now);
    }
}

function mf_set_ready(array &$s, int $uid, bool $ready): void
{
    $i = mf_seat_of($s, $uid);
    if ($i === null || $s['phase'] !== 'LOBBY') {
        throw new MfError('bad_phase', 'Сейчас это действие недоступно.');
    }
    $s['seats'][$i]['ready'] = $s['host_uid'] === $uid ? true : $ready;
}

function mf_require_host(array $s, int $uid): void
{
    if ($s['host_uid'] !== $uid || mf_seat_of($s, $uid) === null) {
        throw new MfError('not_host', 'Это действие доступно только владельцу комнаты.');
    }
}

function mf_ai_name(array $s): string
{
    $taken = [];
    foreach ($s['seats'] as $seat) {
        if ($seat['occ']) {
            $taken[mb_strtolower($seat['name'])] = true;
        }
    }
    $pool = array_values(array_filter(MF_AI_NAMES, fn($n) => !isset($taken[mb_strtolower($n)])));
    if (!$pool) {
        return 'Игрок ' . mf_rand(10, 99);
    }
    return $pool[mf_rand(0, count($pool) - 1)];
}

function mf_add_ai(array &$s, int $uid, float $now): void
{
    mf_require_host($s, $uid);
    if ($s['phase'] !== 'LOBBY') {
        throw new MfError('bad_phase', 'Игра уже началась.');
    }
    if (!$s['fill_ai']) {
        throw new MfError('ai_off', 'В этой комнате игроки ИИ отключены.');
    }
    foreach ($s['seats'] as $seat) {
        if (!$seat['occ']) {
            $s['seats'][$seat['seat']] = mf_ai_seat($s, $seat['seat'], $now);
            return;
        }
    }
    throw new MfError('full', 'Свободных мест нет.');
}

function mf_ai_seat(array $s, int $i, float $now): array
{
    $seat = mf_empty_seat($i);
    $seat['occ'] = true;
    $seat['ai'] = true;
    $seat['ready'] = true;
    $seat['name'] = mf_ai_name($s);
    $seat['joined'] = $now;
    $seat['seen'] = $now;
    return $seat;
}

function mf_fill_ai(array &$s, int $uid, float $now): void
{
    mf_require_host($s, $uid);
    if (!$s['fill_ai']) {
        throw new MfError('ai_off', 'В этой комнате игроки ИИ отключены.');
    }
    foreach ($s['seats'] as $seat) {
        if (!$seat['occ']) {
            mf_add_ai($s, $uid, $now);
        }
    }
}

function mf_remove_ai(array &$s, int $uid, int $i): void
{
    mf_require_host($s, $uid);
    if ($s['phase'] !== 'LOBBY' || !isset($s['seats'][$i]) || !$s['seats'][$i]['ai']) {
        throw new MfError('bad_target', 'Нельзя убрать этого игрока.');
    }
    mf_free_seat($s, $i);
}

function mf_kick(array &$s, int $uid, int $i, float $now): void
{
    mf_require_host($s, $uid);
    if ($s['phase'] !== 'LOBBY' || !isset($s['seats'][$i]) || !$s['seats'][$i]['occ'] || $s['seats'][$i]['ai'] || $s['seats'][$i]['uid'] === $uid) {
        throw new MfError('bad_target', 'Нельзя исключить этого игрока.');
    }
    $seat = $s['seats'][$i];
    $s['released'][] = $seat['uid'];
    mf_free_seat($s, $i);
    mf_event($s, 'leave', $seat['name'] . ' исключён из комнаты.', $now);
}

/** Returns an error text if the game cannot start yet, or null when it can. */
function mf_start_problem(array $s): ?string
{
    if ($s['phase'] !== 'LOBBY') {
        return 'Игра уже началась.';
    }
    $humans = mf_count($s, 'humans');
    if ($humans < 1) {
        return 'В комнате нет игроков.';
    }
    if (mf_count($s, 'occ') < $s['size'] && !$s['fill_ai']) {
        return 'Нужно ровно ' . $s['size'] . ' игроков. Ожидание игроков...';
    }
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && !$seat['ai'] && !$seat['ready'] && $seat['uid'] !== $s['host_uid']) {
            return 'Не все игроки готовы.';
        }
    }
    return null;
}

function mf_start(array &$s, int $uid, float $now): void
{
    mf_require_host($s, $uid);
    $problem = mf_start_problem($s);
    if ($problem !== null) {
        throw new MfError('cannot_start', $problem);
    }
    for ($guard = 0; mf_count($s, 'occ') < $s['size'] && $guard < 12; $guard++) {
        mf_add_ai($s, $uid, $now);
    }
    if (mf_count($s, 'occ') !== $s['size']) {
        throw new MfError('cannot_start', 'Нужно ровно ' . $s['size'] . ' игроков.');
    }
    $roles = mf_shuffle(mf_roles_for($s['size']));
    foreach ($s['seats'] as $i => $seat) {
        $s['seats'][$i]['role'] = $roles[$i];
        $s['seats'][$i]['alive'] = true;
        $s['seats'][$i]['revealed'] = false;
        $s['seats'][$i]['out'] = null;
        $s['seats'][$i]['conn'] = true;
        $s['seats'][$i]['disc'] = null;
        $s['seats'][$i]['seen'] = $now;
        $s['seats'][$i]['ready'] = true;
    }
    $s['day'] = 0;
    $s['private'] = [];
    $s['votes'] = null;
    $s['night'] = null;
    $s['winner'] = null;
    $s['final'] = null;
    $s['final_done'] = false;
    $s['vote_rounds'] = 0;
    $s['last_elim'] = null;
    $s['morning'] = null;
    $s['sched'] = [];
    $s['started'] = $now;
    $s['abandon_since'] = null;
    $s['chat'] = array_values(array_filter($s['chat'], fn($m) => $m['ch'] === 'lobby'));
    mf_ai_init($s);
    mf_event($s, 'start', 'Игра начинается...', $now);
    mf_set_phase($s, 'ROLE_REVEAL', $now, MF_DUR['ROLE_REVEAL']);
}

/** After a finished game everyone goes back to the same lobby (AI seats stay, real players must press ready again). */
function mf_rematch(array &$s, float $now): void
{
    if ($s['phase'] !== 'GAME_OVER') {
        return;
    }
    foreach ($s['seats'] as $i => $seat) {
        if (!$seat['occ']) {
            continue;
        }
        if (!$seat['ai'] && ($seat['uid'] === 0 || !$seat['conn'])) {
            mf_free_seat($s, $i);
            continue;
        }
        if ($seat['took_over']) {
            mf_free_seat($s, $i);
            continue;
        }
        $s['seats'][$i] = array_merge(mf_empty_seat($i), [
            'occ' => true, 'uid' => $seat['uid'], 'name' => $seat['name'], 'ai' => $seat['ai'],
            'ready' => $seat['ai'], 'joined' => $seat['joined'], 'seen' => $now,
        ]);
    }
    $s['ai'] = [];
    $s['sched'] = [];
    $s['night'] = null;
    $s['votes'] = null;
    $s['winner'] = null;
    $s['last_elim'] = null;
    $s['morning'] = null;
    $s['candidates'] = null;
    $s['events'] = [];
    $s['chat'] = [];
    $s['host_uid'] = 0;
    mf_pick_host($s, $now, false);
    mf_set_phase($s, 'LOBBY', $now, null);
    mf_event($s, 'lobby', 'Возвращение в комнату.', $now);
}

/* ---------- presence ---------- */

function mf_touch(array &$s, int $uid, float $now): bool
{
    $i = mf_seat_of($s, $uid);
    if ($i === null) {
        return false;
    }
    $seat = &$s['seats'][$i];
    $back = !$seat['conn'];
    if (!$back && $now - $seat['seen'] < 3.0 && $now >= $seat['seen']) {
        return true;   // polled a moment ago: avoids rewriting the room on every poll
    }
    $seat['seen'] = $now;
    $seat['conn'] = true;
    $seat['disc'] = null;
    if ($back && $s['phase'] !== 'LOBBY') {
        mf_event($s, 'back', $seat['name'] . ' вернулся в игру.', $now);
    }
    if ($s['host_uid'] === 0) {
        mf_pick_host($s, $now);
    }
    return true;
}

/** Turns a real player's seat into an AI seat (same character, same role) or removes the player from the game. */
function mf_replace_human(array &$s, int $i, float $now, bool $voluntary): void
{
    $seat = $s['seats'][$i];
    if ($seat['uid'] > 0) {
        $s['released'][] = $seat['uid'];
    }
    if ($s['fill_ai'] && $seat['alive']) {
        $s['seats'][$i]['uid'] = 0;
        $s['seats'][$i]['ai'] = true;
        $s['seats'][$i]['took_over'] = true;
        $s['seats'][$i]['conn'] = true;
        $s['seats'][$i]['disc'] = null;
        $s['seats'][$i]['seen'] = $now;
        mf_event($s, 'ai', $seat['name'] . ($voluntary ? ' покинул игру.' : ' отключился.') . ' Его место занял ИИ.', $now);
        mf_ai_takeover($s, $i, $now);
        return;
    }
    $s['seats'][$i]['uid'] = 0;
    $s['seats'][$i]['conn'] = false;
    if ($seat['alive']) {
        $s['seats'][$i]['alive'] = false;
        $s['seats'][$i]['out'] = 'left';
        $s['seats'][$i]['revealed'] = $s['reveal_roles'];
        mf_event($s, 'out', $seat['name'] . ' покидает игру.', $now, ['seat' => $i]);
        mf_after_removal($s, $now);
    }
}

/** A player disappeared outside the normal flow: the win condition may have changed. */
function mf_after_removal(array &$s, float $now): void
{
    $winner = mf_check_win($s);
    if ($winner === null) {
        return;
    }
    $s['winner'] = $winner;
    if (!in_array($s['phase'], ['MORNING', 'ELIMINATION', 'GAME_OVER'], true)) {
        mf_finish($s, $now);
    }
}

function mf_presence(array &$s, float $now): void
{
    if ($s['phase'] === 'GAME_OVER') {
        return;
    }
    foreach ($s['seats'] as $i => $seat) {
        if (!$seat['occ'] || $seat['ai'] || $seat['uid'] === 0) {
            continue;
        }
        $silent = $now - $seat['seen'];
        if ($s['phase'] === 'LOBBY') {
            if ($silent > MF_LOBBY_STALE && $seat['uid'] !== $s['host_uid']) {
                $s['released'][] = $seat['uid'];
                mf_free_seat($s, $i);
                mf_event($s, 'leave', $seat['name'] . ' покинул комнату.', $now);
            } elseif ($silent > MF_LOBBY_STALE) {
                $s['seats'][$i]['conn'] = false;
            }
            continue;
        }
        if ($silent > MF_STALE && $seat['conn']) {
            $s['seats'][$i]['conn'] = false;
            $s['seats'][$i]['disc'] = $seat['seen'];
        }
        if (!$s['seats'][$i]['conn'] && $s['seats'][$i]['disc'] !== null && $now - $s['seats'][$i]['disc'] >= MF_GRACE) {
            mf_replace_human($s, $i, $now, false);
        }
    }
    if ($s['phase'] === 'LOBBY') {
        $host = mf_seat_of($s, $s['host_uid']);
        if ($host === null || !$s['seats'][$host]['conn']) {
            mf_pick_host($s, $now);
        }
        return;
    }
    $host = mf_seat_of($s, $s['host_uid']);
    if ($host === null || !$s['seats'][$host]['conn']) {
        mf_pick_host($s, $now);
    }
    // nobody real is here any more
    $anyone = false;
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && !$seat['ai'] && $seat['uid'] > 0 && $seat['conn']) {
            $anyone = true;
        }
    }
    if ($anyone) {
        $s['abandon_since'] = null;
    } elseif ($s['abandon_since'] === null) {
        $s['abandon_since'] = $now;
    } elseif ($now - $s['abandon_since'] >= MF_ABANDON) {
        $s['winner'] = 'abandoned';
        mf_finish($s, $now);
    }
}

/* ---------- win condition ---------- */

function mf_check_win(array $s): ?string
{
    $mafia = count(mf_alive_seats($s, 'mafia'));
    $others = count(mf_alive_seats($s)) - $mafia;
    if ($mafia === 0) {
        return 'civilians';
    }
    if ($mafia >= $others) {
        return 'mafia';
    }
    return null;
}

function mf_finish(array &$s, float $now): void
{
    if ($s['phase'] === 'GAME_OVER') {
        return;
    }
    if ($s['winner'] === null) {
        $s['winner'] = mf_check_win($s) ?? 'abandoned';
    }
    mf_set_phase($s, 'GAME_OVER', $now, null);
    $s['sched'] = [];
    $hasAi = mf_count($s, 'ai') > 0;
    $final = [];
    if ($s['winner'] !== 'abandoned') {
        foreach ($s['seats'] as $seat) {
            if (!$seat['occ'] || $seat['ai'] || $seat['uid'] <= 0 || $seat['took_over']) {
                continue;
            }
            $team = $seat['role'] === 'mafia' ? 'mafia' : 'civilians';
            $final[] = ['uid' => $seat['uid'], 'role' => $seat['role'], 'won' => $team === $s['winner'], 'alive' => $seat['alive'], 'ai_match' => $hasAi];
        }
    }
    $s['final'] = $final;
    mf_event($s, 'over', 'Игра окончена.', $now);
}

/* ---------- state machine ---------- */

function mf_tick(array &$s, float $now): void
{
    mf_presence($s, $now);
    if ($s['phase'] === 'LOBBY' || $s['phase'] === 'GAME_OVER') {
        return;
    }
    mf_ai_run($s, $now);
    for ($guard = 0; $guard < 6 && $s['phase_end'] !== null && $now >= $s['phase_end'] && $s['phase'] !== 'GAME_OVER'; $guard++) {
        mf_advance($s, $now);
        mf_ai_run($s, $now);
    }
    mf_check_early($s, $now);
}

function mf_check_early(array &$s, float $now): void
{
    if (!in_array($s['phase'], ['VOTING', 'REVOTE'], true)) {
        return;
    }
    foreach (mf_alive_seats($s) as $i) {
        $seat = $s['seats'][$i];
        $waiting = $seat['ai'] || $seat['conn'];
        if ($waiting && !array_key_exists($i, $s['votes'])) {
            return;
        }
    }
    mf_advance($s, $now);
    mf_ai_run($s, $now);
}

function mf_advance(array &$s, float $now): void
{
    switch ($s['phase']) {
        case 'ROLE_REVEAL':
            mf_start_night($s, $now);
            break;
        case 'NIGHT':
            mf_resolve_night($s, $now);
            break;
        case 'MORNING':
            if ($s['winner'] !== null) {
                mf_finish($s, $now);
            } else {
                mf_event($s, 'phase', 'Начинается обсуждение.', $now);
                mf_set_phase($s, 'DISCUSSION', $now, MF_DUR['DISCUSSION']);
                mf_ai_plan($s, $now);
            }
            break;
        case 'DISCUSSION':
            mf_start_vote($s, $now, 'VOTING', null);
            break;
        case 'VOTING':
        case 'REVOTE':
            mf_tally($s, $now);
            break;
        case 'ELIMINATION':
            if ($s['winner'] !== null) {
                mf_finish($s, $now);
            } else {
                mf_start_night($s, $now);
            }
            break;
    }
}

function mf_start_night(array &$s, float $now): void
{
    $s['day']++;
    $s['night'] = ['round' => 1, 'mv' => [], 'target' => null, 'decided' => false, 'protect' => null, 'check' => null, 'extended' => false, 'protect_done' => false, 'check_done' => false];
    $s['votes'] = null;
    $s['morning'] = null;
    mf_event($s, 'phase', 'Город засыпает...', $now);
    mf_set_phase($s, 'NIGHT', $now, MF_DUR['NIGHT']);
    mf_ai_plan($s, $now);
}

/* Mafia's target: every alive Mafia confirms one; same target = chosen, different = a short re-vote, still different = nobody. */
function mf_mafia_settle(array &$s, float $now, bool $final): void
{
    $n = &$s['night'];
    if ($n['decided']) {
        return;
    }
    $mafia = mf_alive_seats($s, 'mafia');
    $confirmed = [];
    foreach ($mafia as $m) {
        if (isset($n['mv'][$m])) {
            $confirmed[$m] = $n['mv'][$m];
        }
    }
    $needed = 0;
    foreach ($mafia as $m) {
        if ($s['seats'][$m]['ai'] || $s['seats'][$m]['conn']) {
            $needed++;
        }
    }
    if (!$final) {
        if ($needed === 0 || count($confirmed) < $needed) {
            return;
        }
    }
    $targets = array_unique(array_values($confirmed));
    if (count($targets) === 1) {
        $n['target'] = $targets[0];
        $n['decided'] = true;
        return;
    }
    if (count($targets) === 0) {
        if ($final) {
            $n['decided'] = true;
        }
        return;
    }
    // the Mafia disagree
    if ($n['round'] === 1 && !$final) {
        $n['round'] = 2;
        $n['mv'] = [];
        $s['mafia_notice'] = ['pid' => $s['pid'], 'text' => 'Мнения разошлись. Выберите одну цель ещё раз.'];
        if ($s['phase_end'] - $now < 8) {
            $s['phase_end'] = $now + 8;
        }
        mf_ai_plan_mafia_revote($s, $now);
        return;
    }
    $n['target'] = null;
    $n['decided'] = true;
    $s['mafia_notice'] = ['pid' => $s['pid'], 'text' => 'Мафия не смогла договориться. Этой ночью никто не пострадает.'];
}

function mf_resolve_night(array &$s, float $now): void
{
    mf_set_phase($s, 'NIGHT_RESOLUTION', $now, null);
    mf_mafia_settle($s, $now, true);
    $n = $s['night'];
    $target = $n['target'];
    $protect = $n['protect'];
    // detective's result is delivered privately
    $detSeat = null;
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && $seat['role'] === 'detective') {
            $detSeat = $seat['seat'];
        }
    }
    if ($detSeat !== null && $n['check'] !== null) {
        $s['private'][$detSeat]['checks'][] = ['night' => $s['day'], 'seat' => $n['check'], 'mafia' => $s['seats'][$n['check']]['role'] === 'mafia'];
    }
    foreach ($s['seats'] as $seat) {
        if ($seat['occ'] && $seat['role'] === 'doctor') {
            $s['private'][$seat['seat']]['last'] = $protect;
        }
    }
    $died = null;
    if ($target !== null && $target !== $protect && $s['seats'][$target]['alive']) {
        $died = $target;
        $s['seats'][$target]['alive'] = false;
        $s['seats'][$target]['out'] = 'night';
        $s['seats'][$target]['revealed'] = $s['reveal_roles'];
    }
    $s['morning'] = ['died' => $died];
    $s['mafia_notice'] = null;
    $s['winner'] = mf_check_win($s);
    mf_event($s, 'phase', 'Наступает утро...', $now);
    if ($died !== null) {
        mf_event($s, 'death', 'Этой ночью город потерял игрока: ' . $s['seats'][$died]['name'] . '.', $now, ['seat' => $died]);
    } else {
        mf_event($s, 'death', 'Этой ночью никто не погиб.', $now);
    }
    mf_set_phase($s, 'MORNING', $now, MF_DUR['MORNING']);
    mf_ai_observe_night($s, $died);
}

function mf_start_vote(array &$s, float $now, string $phase, ?array $candidates): void
{
    $s['votes'] = [];
    $s['candidates'] = $candidates;
    $s['vote_rounds']++;
    mf_event($s, 'phase', $phase === 'REVOTE' ? 'Ничья! Переголосование между названными игроками.' : 'Время голосовать.', $now);
    mf_set_phase($s, $phase, $now, MF_DUR[$phase]);
    mf_ai_plan($s, $now);
}

function mf_tally(array &$s, float $now): void
{
    $round = $s['phase'] === 'REVOTE' ? 2 : 1;
    $counts = [];
    foreach ($s['votes'] as $from => $to) {
        if ($to !== null) {
            $counts[$to] = ($counts[$to] ?? 0) + 1;
        }
    }
    arsort($counts);
    $tally = [];
    foreach ($counts as $seat => $c) {
        $tally[] = ['seat' => $seat, 'count' => $c];
    }
    $votes = [];
    foreach ($s['votes'] as $from => $to) {
        if ($to !== null) {
            $votes[] = ['from' => $from, 'to' => $to];
        }
    }
    $top = $tally ? $tally[0]['count'] : 0;
    $leaders = array_values(array_map(fn($t) => $t['seat'], array_filter($tally, fn($t) => $t['count'] === $top)));
    $info = ['round' => $round, 'tally' => $tally, 'votes' => $votes, 'kind' => 'none', 'seat' => null];
    if ($top > 0 && count($leaders) > 1 && $round === 1) {
        $s['last_elim'] = $info;
        mf_ai_observe_votes($s, $votes);
        $s['last_elim']['kind'] = 'tie';
        mf_start_vote($s, $now, 'REVOTE', $leaders);
        return;
    }
    if ($top > 0 && count($leaders) === 1) {
        $out = $leaders[0];
        $info['kind'] = 'vote';
        $info['seat'] = $out;
        $s['seats'][$out]['alive'] = false;
        $s['seats'][$out]['out'] = 'vote';
        $s['seats'][$out]['revealed'] = $s['reveal_roles'];
        mf_event($s, 'out', 'Город сделал свой выбор: ' . $s['seats'][$out]['name'] . ' покидает игру.', $now, ['seat' => $out]);
    } else {
        $info['kind'] = $top > 0 ? 'tie' : 'none';
        mf_event($s, 'out', $top > 0 ? 'Голоса разделились. Никто не покидает игру.' : 'Никто не проголосовал. Никто не покидает игру.', $now);
    }
    $s['last_elim'] = $info;
    $s['winner'] = mf_check_win($s);
    mf_set_phase($s, 'ELIMINATION', $now, MF_DUR['ELIMINATION']);
    mf_ai_observe_votes($s, $votes);
    mf_ai_observe_elimination($s, $info);
}

/* ---------- actions ---------- */

function mf_need_alive(array $s, int $seat): void
{
    if (!$s['seats'][$seat]['alive']) {
        throw new MfError('dead', 'Вы выбыли из игры и не можете это сделать.');
    }
}

function mf_act(array &$s, int $uid, string $type, $payload, float $now): array
{
    $me = mf_seat_of($s, $uid);
    if ($me === null) {
        throw new MfError('not_member', 'Вы не участвуете в этой игре.');
    }
    mf_touch($s, $uid, $now);
    switch ($type) {
        case 'vote':
            mf_vote($s, $me, mf_target($s, $payload), $now);
            return [];
        case 'mafia':
            mf_night_mafia($s, $me, mf_target($s, $payload), $now);
            return [];
        case 'doctor':
            mf_night_doctor($s, $me, mf_target($s, $payload), $now);
            return [];
        case 'detective':
            mf_night_detective($s, $me, mf_target($s, $payload), $now);
            return [];
        case 'chat':
            mf_chat($s, $me, (string) $payload, $now);
            return [];
    }
    throw new MfError('bad_action', 'Неизвестное действие.');
}

function mf_target(array $s, $value): int
{
    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
        throw new MfError('bad_target', 'Выберите игрока.');
    }
    $i = (int) $value;
    if (!isset($s['seats'][$i]) || !$s['seats'][$i]['occ']) {
        throw new MfError('bad_target', 'Такого игрока нет.');
    }
    return $i;
}

function mf_vote(array &$s, int $voter, int $target, float $now): void
{
    if (!in_array($s['phase'], ['VOTING', 'REVOTE'], true)) {
        throw new MfError('bad_phase', 'Сейчас не время голосования.');
    }
    mf_need_alive($s, $voter);
    if (!$s['seats'][$target]['alive']) {
        throw new MfError('bad_target', 'Этот игрок уже выбыл.');
    }
    if ($target === $voter) {
        throw new MfError('bad_target', 'Нельзя голосовать за себя.');
    }
    if ($s['candidates'] !== null && !in_array($target, $s['candidates'], true)) {
        throw new MfError('bad_target', 'В этом голосовании можно выбрать только названных игроков.');
    }
    if (array_key_exists($voter, $s['votes'])) {
        if ($s['votes'][$voter] === $target) {
            return;
        }
        throw new MfError('already_voted', 'Вы уже проголосовали.');
    }
    $s['votes'][$voter] = $target;
}

function mf_night_guard(array $s, int $seat, string $role): void
{
    if ($s['phase'] !== 'NIGHT') {
        throw new MfError('bad_phase', 'Сейчас не ночь.');
    }
    if ($s['seats'][$seat]['role'] !== $role) {
        throw new MfError('bad_role', 'Это действие недоступно для вашей роли.');
    }
    mf_need_alive($s, $seat);
}

function mf_night_mafia(array &$s, int $seat, int $target, float $now): void
{
    mf_night_guard($s, $seat, 'mafia');
    if (!$s['seats'][$target]['alive'] || $s['seats'][$target]['role'] === 'mafia') {
        throw new MfError('bad_target', 'Нельзя выбрать этого игрока.');
    }
    $n = &$s['night'];
    if ($n['decided']) {
        if ($n['target'] === $target) {
            return;
        }
        throw new MfError('already_done', 'Решение мафии уже принято.');
    }
    if (isset($n['mv'][$seat])) {
        if ($n['mv'][$seat] === $target) {
            return;
        }
        throw new MfError('already_done', 'Вы уже подтвердили выбор.');
    }
    $n['mv'][$seat] = $target;
    mf_mafia_settle($s, $now, false);
}

function mf_night_doctor(array &$s, int $seat, int $target, float $now): void
{
    mf_night_guard($s, $seat, 'doctor');
    if (!$s['seats'][$target]['alive']) {
        throw new MfError('bad_target', 'Этот игрок уже выбыл.');
    }
    $n = &$s['night'];
    if ($n['protect_done']) {
        if ($n['protect'] === $target) {
            return;
        }
        throw new MfError('already_done', 'Вы уже сделали выбор этой ночью.');
    }
    $last = $s['private'][$seat]['last'] ?? null;
    if ($last !== null && $last === $target) {
        throw new MfError('repeat_protect', 'Нельзя защищать одного и того же игрока две ночи подряд.');
    }
    $n['protect'] = $target;
    $n['protect_done'] = true;
}

function mf_night_detective(array &$s, int $seat, int $target, float $now): void
{
    mf_night_guard($s, $seat, 'detective');
    if ($target === $seat) {
        throw new MfError('bad_target', 'Нельзя проверять самого себя.');
    }
    if (!$s['seats'][$target]['alive']) {
        throw new MfError('bad_target', 'Этот игрок уже выбыл.');
    }
    $n = &$s['night'];
    if ($n['check_done']) {
        if ($n['check'] === $target) {
            return;
        }
        throw new MfError('already_done', 'Вы уже проверили игрока этой ночью.');
    }
    $n['check'] = $target;
    $n['check_done'] = true;
}

function mf_clean_text(string $text): string
{
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

function mf_chat_channel(array $s, int $seat, string $wanted): string
{
    $me = $s['seats'][$seat];
    switch ($s['phase']) {
        case 'LOBBY':
            return 'lobby';
        case 'GAME_OVER':
            return 'day';
    }
    if (!$me['alive']) {
        return 'dead';
    }
    if (in_array($s['phase'], MF_DAY_PHASES, true)) {
        return 'day';
    }
    if ($s['phase'] === 'NIGHT' && $me['role'] === 'mafia' && count(mf_alive_seats($s, 'mafia')) > 1) {
        return 'mafia';
    }
    throw new MfError('chat_closed', 'Сейчас чат недоступен.');
}

function mf_chat(array &$s, int $seat, string $text, float $now, string $wanted = ''): void
{
    $text = mf_clean_text($text);
    if ($text === '') {
        throw new MfError('empty', 'Введите сообщение.');
    }
    if (mb_strlen($text) > MF_CHAT_MAX) {
        throw new MfError('too_long', 'Сообщение слишком длинное (максимум ' . MF_CHAT_MAX . ' символов).');
    }
    $channel = mf_chat_channel($s, $seat, $wanted);
    $times = array_values(array_filter($s['seats'][$seat]['chat_t'], fn($t) => $now - $t < 20));
    if ($times && $now - end($times) < 1.0) {
        throw new MfError('rate', 'Не так быстро. Подождите секунду.');
    }
    if (count($times) >= 8) {
        throw new MfError('rate', 'Слишком много сообщений. Подождите немного.');
    }
    $times[] = $now;
    $s['seats'][$seat]['chat_t'] = $times;
    for ($i = count($s['chat']) - 1, $k = 0; $i >= 0 && $k < 6; $i--, $k++) {
        if ($s['chat'][$i]['seat'] === $seat && $s['chat'][$i]['text'] === $text && $s['chat'][$i]['ch'] === $channel) {
            throw new MfError('spam', 'Не повторяйте одно и то же сообщение.');
        }
    }
    mf_push_chat($s, $seat, $text, $channel, $now);
    if (!$s['seats'][$seat]['ai'] && $channel === 'day' && $s['phase'] !== 'LOBBY') {
        mf_ai_on_chat($s, $seat, $text, $now);
    }
}

function mf_push_chat(array &$s, ?int $seat, string $text, string $channel, float $now, bool $system = false): void
{
    $s['seq']++;
    $s['chat'][] = ['id' => $s['seq'], 'seat' => $seat, 'text' => $text, 'ch' => $channel, 't' => $now, 'sys' => $system];
    if (count($s['chat']) > 120) {
        $s['chat'] = array_slice($s['chat'], -100);
    }
}

/* ---------- what a player is allowed to see ---------- */

function mf_view(array $s, int $uid, float $now): array
{
    $me = mf_seat_of($s, $uid);
    if ($me === null) {
        throw new MfError('not_member', 'Вы не участвуете в этой игре.');
    }
    return mf_view_seat($s, $me, $now);
}

function mf_view_seat(array $s, int $me, float $now): array
{
    $over = $s['phase'] === 'GAME_OVER';
    $mine = $s['seats'][$me];
    $isMafia = $mine['role'] === 'mafia';
    $seats = [];
    foreach ($s['seats'] as $seat) {
        if (!$seat['occ']) {
            $seats[] = ['seat' => $seat['seat'], 'empty' => true];
            continue;
        }
        $row = [
            'seat' => $seat['seat'], 'empty' => false, 'name' => $seat['name'], 'ai' => $seat['ai'],
            'alive' => $seat['alive'], 'conn' => $seat['ai'] ? true : $seat['conn'], 'ready' => $seat['ready'],
            'host' => !$seat['ai'] && $seat['uid'] === $s['host_uid'] && $s['host_uid'] !== 0,
            'you' => $seat['seat'] === $me, 'out' => $seat['out'],
        ];
        if ($over || $seat['revealed']) {
            $row['role'] = $seat['role'];
        }
        $seats[] = $row;
    }
    $view = [
        'code' => $s['code'], 'size' => $s['size'], 'fill_ai' => $s['fill_ai'], 'public' => $s['public'],
        'max_humans' => $s['max_humans'], 'phase' => $s['phase'], 'pid' => $s['pid'], 'day' => $s['day'],
        'now' => $now, 'phase_start' => $s['phase_start'], 'phase_end' => $s['phase_end'],
        'seats' => $seats, 'me' => ['seat' => $me, 'alive' => $mine['alive'], 'host' => $s['host_uid'] !== 0 && $mine['uid'] === $s['host_uid']],
    ];
    if ($s['phase'] !== 'LOBBY') {
        $view['me']['role'] = $mine['role'];
        if ($isMafia) {
            $mates = [];
            foreach ($s['seats'] as $seat) {
                if ($seat['occ'] && $seat['role'] === 'mafia' && $seat['seat'] !== $me) {
                    $mates[] = $seat['seat'];
                }
            }
            $view['me']['teammates'] = $mates;
        }
        if ($mine['role'] === 'detective') {
            $view['me']['checks'] = $s['private'][$me]['checks'] ?? [];
        }
        if ($mine['role'] === 'doctor') {
            $view['me']['last_protect'] = $s['private'][$me]['last'] ?? null;
        }
    }
    // night: only what belongs to this player
    if ($s['phase'] === 'NIGHT' && $s['night'] !== null && $mine['alive']) {
        $n = $s['night'];
        $night = ['done' => false];
        if ($isMafia) {
            $night['round'] = $n['round'];
            $night['votes'] = [];
            foreach ($n['mv'] as $m => $t) {
                if ($m === $me || $s['seats'][$m]['role'] === 'mafia') {
                    $night['votes'][] = ['seat' => $m, 'target' => $t];
                }
            }
            $night['done'] = isset($n['mv'][$me]) || $n['decided'];
            $night['choice'] = $n['mv'][$me] ?? null;
            if ($s['mafia_notice'] !== null && $s['mafia_notice']['pid'] === $s['pid']) {
                $night['notice'] = $s['mafia_notice']['text'];
            }
        } elseif ($mine['role'] === 'doctor') {
            $night['done'] = $n['protect_done'];
            $night['choice'] = $n['protect'];
        } elseif ($mine['role'] === 'detective') {
            $night['done'] = $n['check_done'];
            $night['choice'] = $n['check'];
        }
        $view['me']['night'] = $night;
    }
    if (in_array($s['phase'], ['VOTING', 'REVOTE'], true) && $s['votes'] !== null) {
        $view['vote'] = [
            'count' => count($s['votes']), 'total' => count(mf_alive_seats($s)),
            'mine' => $s['votes'][$me] ?? null, 'voted' => array_key_exists($me, $s['votes']),
            'candidates' => $s['candidates'], 'round' => $s['phase'] === 'REVOTE' ? 2 : 1,
        ];
    }
    if (in_array($s['phase'], ['ELIMINATION', 'REVOTE'], true) && $s['last_elim'] !== null) {
        $view['elim'] = $s['last_elim'];
    }
    if ($s['phase'] === 'MORNING' && $s['morning'] !== null) {
        $view['morning'] = $s['morning'];
    }
    $view['events'] = array_map(fn($e) => ['id' => $e['id'], 'type' => $e['type'], 'text' => $e['text'], 'seat' => $e['seat'] ?? null], $s['events']);
    $chat = [];
    foreach ($s['chat'] as $m) {
        $ok = false;
        switch ($m['ch']) {
            case 'lobby':
            case 'day':
                $ok = true;
                break;
            case 'dead':
                $ok = $over || !$mine['alive'];
                break;
            case 'mafia':
                $ok = $over || $isMafia;
                break;
        }
        if ($ok) {
            $chat[] = ['id' => $m['id'], 'seat' => $m['seat'], 'text' => $m['text'], 'ch' => $m['ch'], 'sys' => $m['sys']];
        }
    }
    $view['chat'] = $chat;
    $can = ['chat' => false, 'chat_channel' => null];
    try {
        $can['chat_channel'] = mf_chat_channel($s, $me, '');
        $can['chat'] = true;
    } catch (MfError $e) {
    }
    $view['me']['can'] = $can;
    if ($over) {
        $roles = [];
        foreach ($s['seats'] as $seat) {
            if ($seat['occ']) {
                $roles[] = ['seat' => $seat['seat'], 'name' => $seat['name'], 'role' => $seat['role'], 'ai' => $seat['ai'], 'alive' => $seat['alive']];
            }
        }
        $view['result'] = [
            'winner' => $s['winner'], 'roles' => $roles,
            'stats' => ['days' => $s['day'], 'votes' => $s['vote_rounds'], 'players' => $s['size']],
        ];
    }
    return $view;
}
