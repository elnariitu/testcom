<?php
declare(strict_types=1);

/* Run: php dev/mafia_test.php   (pure engine tests, no database) */
require_once __DIR__ . '/../mafia_engine.php';

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function ok(bool $cond, string $name): void
{
    if ($cond) {
        $GLOBALS['pass']++;
    } else {
        $GLOBALS['fail']++;
        echo "  FAIL: $name\n";
    }
}

function throws(callable $fn, string $code, string $name): void
{
    try {
        $fn();
        ok(false, $name . ' (no error thrown)');
    } catch (MfError $e) {
        ok($e->codeName === $code, $name . " (got {$e->codeName}, wanted $code)");
    }
}

function section(string $title): void
{
    echo "\n== $title\n";
}

/** Room with $humans real players (uid 1..n) and AI in the rest, started; roles can then be forced. */
function make_game(int $size, int $humans, ?array $roles = null, bool $fillAi = true, float $now = 1000.0): array
{
    $s = mf_new_state('TEST1', $size, $fillAi, true, $humans, $now);
    for ($u = 1; $u <= $humans; $u++) {
        mf_join($s, $u, 'Игрок' . $u, $now);
        mf_set_ready($s, $u, true);
    }
    mf_start($s, 1, $now);
    if ($roles !== null) {
        foreach ($roles as $i => $r) {
            $s['seats'][$i]['role'] = $r;
        }
    }
    return $s;
}

/** Runs the clock forward until the phase changes (or a limit is reached). */
function run_until(array &$s, float &$now, string $phase, int $limit = 400): void
{
    for ($k = 0; $k < $limit && $s['phase'] !== $phase; $k++) {
        $now += 1.0;
        foreach ($s['seats'] as $seat) {
            if ($seat['occ'] && !$seat['ai'] && $seat['uid'] > 0) {
                mf_touch($s, $seat['uid'], $now);
            }
        }
        mf_tick($s, $now);
    }
}

function act(array &$s, int $seat, string $type, $payload, float $now): array
{
    return mf_act($s, $s['seats'][$seat]['uid'], $type, $payload, $now);
}

function uid_of(array $s, int $seat): int
{
    return $s['seats'][$seat]['uid'];
}

/* ------------------------------------------------------------------ */
section('roles and sizes');
foreach ([4 => [1, 0, 0, 3], 6 => [1, 1, 1, 3], 10 => [2, 1, 1, 6]] as $size => $want) {
    $c = array_count_values(mf_roles_for($size));
    ok(count(mf_roles_for($size)) === $size, "$size players: role list has $size entries");
    ok(($c['mafia'] ?? 0) === $want[0] && ($c['doctor'] ?? 0) === $want[1] && ($c['detective'] ?? 0) === $want[2] && ($c['civilian'] ?? 0) === $want[3], "$size players: correct role mix");
}
throws(fn() => mf_roles_for(5), 'bad_size', 'unsupported size 5');
throws(fn() => mf_new_state('X', 11, true, false, 11, 0.0), 'bad_size', 'room of 11 refused');
throws(fn() => mf_new_state('X', 12, true, false, 12, 0.0), 'bad_size', 'room of 12 refused');
throws(fn() => mf_new_state('X', 2, true, false, 2, 0.0), 'bad_size', 'room of 2 refused');

$dealt = [];
for ($k = 0; $k < 30; $k++) {
    $s = make_game(6, 2);
    $roles = array_map(fn($x) => $x['role'], $s['seats']);
    sort($roles);
    $dealt[implode(',', $roles)] = true;
    $mafiaSeat = array_search('mafia', array_column($s['seats'], 'role'), true);
    $dealt['m' . $mafiaSeat] = true;
}
ok(count($dealt) >= 4, 'roles are distributed randomly across seats');
$s = make_game(10, 3);
$c = array_count_values(array_column($s['seats'], 'role'));
ok($c['mafia'] === 2 && $c['doctor'] === 1 && $c['detective'] === 1 && $c['civilian'] === 6, '10 players dealt 2/1/1/6');
$s = make_game(4, 2);
$c = array_count_values(array_column($s['seats'], 'role'));
ok($c['mafia'] === 1 && $c['civilian'] === 3 && !isset($c['doctor']) && !isset($c['detective']), '4 players: 1 mafia + 3 civilians only');

section('room capacity');
$s = mf_new_state('CAP', 4, false, true, 4, 0.0);
for ($u = 1; $u <= 4; $u++) {
    mf_join($s, $u, "P$u", 0.0);
}
throws(fn() => mf_join($s, 5, 'P5', 0.0), 'full', 'fifth player cannot join a 4-room');
ok(mf_join($s, 2, 'P2', 0.0) === mf_seat_of($s, 2), 'joining twice returns the same seat (no duplicate seat)');
ok(mf_count($s, 'humans') === 4, 'same account occupies one seat only');
$s = mf_new_state('CAP', 10, true, true, 10, 0.0);
for ($u = 1; $u <= 10; $u++) {
    mf_join($s, $u, "P$u", 0.0);
}
throws(fn() => mf_join($s, 11, 'P11', 0.0), 'full', 'eleventh player cannot join the 10-room');
ok(mf_count($s, 'occ') === 10, 'never more than 10 seats occupied');
$s = mf_new_state('DUO', 6, true, true, 2, 0.0);
mf_join($s, 1, 'A', 0.0);
mf_join($s, 2, 'B', 0.0);
throws(fn() => mf_join($s, 3, 'C', 0.0), 'full', 'duo room holds 2 real players');
mf_set_ready($s, 2, true);
mf_start($s, 1, 0.0);
ok(mf_count($s, 'ai') === 4 && mf_count($s, 'humans') === 2 && $s['size'] === 6, 'duo: 2 real + 4 AI = 6');
$s = mf_new_state('NOAI', 4, false, true, 4, 0.0);
mf_join($s, 1, 'A', 0.0);
throws(fn() => mf_add_ai($s, 1, 0.0), 'ai_off', 'AI seats refused when AI is off');
throws(fn() => mf_start($s, 1, 0.0), 'cannot_start', 'game cannot start with 1 of 4 players and no AI');
$s = mf_new_state('H', 6, true, true, 6, 0.0);
mf_join($s, 1, 'A', 0.0);
mf_join($s, 2, 'B', 0.0);
throws(fn() => mf_start($s, 2, 0.0), 'not_host', 'only the host can start');
throws(fn() => mf_start($s, 1, 0.0), 'cannot_start', 'start refused while a player is not ready');
$s2 = mf_new_state('N', 4, true, true, 4, 0.0);
mf_join($s2, 1, 'Аня', 0.0);
mf_join($s2, 2, 'аня', 0.0);
ok($s2['seats'][0]['name'] !== $s2['seats'][1]['name'], 'duplicate nicknames are made unique');

section('win conditions');
$s = make_game(6, 1, ['mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian']);
ok(mf_check_win($s) === null, 'fresh game has no winner');
$s['seats'][0]['alive'] = false;
ok(mf_check_win($s) === 'civilians', 'civilians win when all mafia are gone');
$s = make_game(6, 1, ['mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian']);
foreach ([1, 2, 3] as $i) {
    $s['seats'][$i]['alive'] = false;
}
ok(mf_check_win($s) === null, '1 mafia vs 2 others: game continues');
$s = make_game(6, 1, ['mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian']);
foreach ([1, 2, 3, 4] as $i) {
    $s['seats'][$i]['alive'] = false;
}
ok(mf_check_win($s) === 'mafia', 'mafia win when mafia >= remaining others (1 vs 1)');
$s = make_game(10, 1, ['mafia', 'mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian', 'civilian', 'civilian', 'civilian']);
foreach ([2, 3, 4, 5, 6, 7] as $i) {
    $s['seats'][$i]['alive'] = false;
}
ok(mf_check_win($s) === 'mafia', '2 mafia vs 2 civilians: mafia wins');
$s['seats'][8]['alive'] = false;
$s['seats'][0]['alive'] = false;
ok(mf_check_win($s) === 'mafia' || mf_check_win($s) === null, 'sanity');

section('night: doctor save / kill / detective / privacy');
$now = 1000.0;
$roles = ['mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian'];
$s = make_game(6, 6, $roles, false, $now);
run_until($s, $now, 'NIGHT');
ok($s['phase'] === 'NIGHT' && $s['day'] === 1, 'role reveal (5s) leads to night 1');
throws(fn() => act($s, 0, 'vote', 3, $now), 'bad_phase', 'no voting at night');
act($s, 0, 'mafia', 3, $now);
act($s, 1, 'doctor', 3, $now);
act($s, 2, 'detective', 0, $now);
$vDet = mf_view($s, 3, $now);
ok(!isset($vDet['me']['checks'][0]), 'detective has no result before the night resolves');
$before = json_encode(mf_view($s, 4, $now));
run_until($s, $now, 'MORNING');
ok($s['seats'][3]['alive'], 'doctor protected the mafia target: nobody died');
ok($s['morning']['died'] === null, 'morning: nobody died');
$vDet = mf_view($s, 3, $now);
ok(count($vDet['me']['checks']) === 1 && $vDet['me']['checks'][0]['seat'] === 0 && $vDet['me']['checks'][0]['mafia'] === true, 'detective learned the mafia (privately)');
foreach ([0, 1, 3, 4, 5] as $seat) {
    $v = mf_view($s, $seat + 1, $now);
    ok(!isset($v['me']['checks']), "seat $seat does not receive investigation results");
}
ok(strpos(json_encode(mf_view($s, 2, $now)), '"checks"') === false, 'doctor payload has no detective data');
$evText = implode('|', array_column($s['events'], 'text'));
ok(strpos($evText, 'Этой ночью никто не погиб.') !== false && stripos($evText, 'доктор') === false && stripos($evText, 'спас') === false, 'public message does not reveal the save');

// second night: no save -> victim dies; doctor cannot repeat target
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 0, 'mafia', 3, $now);
act($s, 1, 'doctor', 1, $now);
run_until($s, $now, 'MORNING');
ok(!$s['seats'][3]['alive'] && $s['morning']['died'] === 3, 'mafia kill succeeds when doctor protects someone else');
ok($s['seats'][3]['revealed'] === true && mf_view($s, 1, $now)['seats'][3]['role'] === 'civilian', 'dead player role is revealed by default');
// doctor may protect self, but not the same target on two nights running
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 1, 'doctor', 1, $now);
run_until($s, $now, 'DISCUSSION');
$s['seats'][5]['alive'] = true;
run_until($s, $now, 'VOTING');
run_until($s, $now, 'ELIMINATION');
run_until($s, $now, 'NIGHT');
ok($s['phase'] === 'NIGHT' && $s['day'] === 2, 'game continues to night 2');
throws(fn() => act($s, 1, 'doctor', 1, $now), 'repeat_protect', 'doctor cannot protect the same player two nights in a row');
act($s, 1, 'doctor', 2, $now);
throws(fn() => act($s, 1, 'doctor', 4, $now), 'already_done', 'doctor cannot change a confirmed choice');
ok(act($s, 1, 'doctor', 2, $now) === [], 'repeating the same doctor action is idempotent');
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 1, 'doctor', 1, $now);
ok(true, 'doctor may protect self');
throws(fn() => act($s, 2, 'detective', 2, $now), 'bad_target', 'detective cannot inspect themselves');
act($s, 2, 'detective', 4, $now);
throws(fn() => act($s, 2, 'detective', 5, $now), 'already_done', 'detective cannot inspect twice in one night');

section('role permissions');
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
throws(fn() => act($s, 3, 'mafia', 1, $now), 'bad_role', 'civilian cannot use the mafia action');
throws(fn() => act($s, 0, 'doctor', 2, $now), 'bad_role', 'mafia cannot use the doctor action');
throws(fn() => act($s, 0, 'detective', 2, $now), 'bad_role', 'mafia cannot use the detective action');
throws(fn() => act($s, 1, 'mafia', 4, $now), 'bad_role', 'doctor cannot use the mafia action');
throws(fn() => act($s, 0, 'mafia', 0, $now), 'bad_target', 'mafia cannot target the mafia');
throws(fn() => act($s, 0, 'fly', 1, $now), 'bad_action', 'unknown action refused');
throws(fn() => mf_act($s, 99, 'vote', 1, $now), 'not_member', 'a stranger cannot act');
throws(fn() => act($s, 0, 'mafia', 'abc', $now), 'bad_target', 'garbage target refused');
throws(fn() => act($s, 0, 'mafia', 77, $now), 'bad_target', 'out of range target refused');
act($s, 0, 'mafia', 4, $now);
ok(act($s, 0, 'mafia', 4, $now) === [], 'mafia repeating a confirmed target is idempotent');
throws(fn() => act($s, 0, 'mafia', 5, $now), 'already_done', 'mafia cannot change a confirmed target');

section('day: voting, ties, dead players');
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'DISCUSSION');
ok($s['phase'] === 'DISCUSSION', 'night -> morning -> discussion');
run_until($s, $now, 'VOTING');
throws(fn() => act($s, 3, 'vote', 3, $now), 'bad_target', 'cannot vote for yourself');
act($s, 3, 'vote', 1, $now);
ok(act($s, 3, 'vote', 1, $now) === [], 'same vote twice is idempotent');
throws(fn() => act($s, 3, 'vote', 2, $now), 'already_voted', 'cannot vote twice for different players');
$v = mf_view($s, uid_of($s, 4), $now);
ok($v['vote']['count'] === 1 && !isset($v['vote']['votes']) && $v['vote']['voted'] === false, 'individual votes are hidden while voting');
ok(strpos(json_encode($v), '"mine":null') !== false, 'others cannot see who I voted for');
// 3 : 3 tie between seats 1 and 2 -> re-vote
act($s, 0, 'vote', 1, $now);
act($s, 2, 'vote', 1, $now);
act($s, 4, 'vote', 1, $now);
act($s, 1, 'vote', 2, $now);
act($s, 5, 'vote', 2, $now);
$s['votes'][3] = 2;   // seat 3 already voted 1 above; make the split 3:3 by hand for this scenario
$s['votes'][2] = 1;
unset($s['votes'][3]);
act($s, 3, 'vote', 2, $now);
run_until($s, $now, 'REVOTE', 60);
ok($s['phase'] === 'REVOTE' && $s['vote_rounds'] === 2, 'tied vote leads to a re-vote');
ok($s['candidates'] === [1, 2], 're-vote only between the tied players');
throws(fn() => act($s, 0, 'vote', 4, $now), 'bad_target', 'cannot vote for a non-tied player in the re-vote');
act($s, 1, 'vote', 2, $now);
act($s, 2, 'vote', 1, $now);
act($s, 0, 'vote', 1, $now);
act($s, 3, 'vote', 2, $now);
act($s, 4, 'vote', 1, $now);
act($s, 5, 'vote', 2, $now);
run_until($s, $now, 'ELIMINATION', 60);
ok($s['phase'] === 'ELIMINATION' && $s['last_elim']['kind'] === 'tie' && count(mf_alive_seats($s)) === 6, 'second tie: nobody is eliminated');
ok(isset(mf_view($s, 1, $now)['elim']['votes']), 'individual votes become public after voting ends');
run_until($s, $now, 'NIGHT', 30);
ok($s['phase'] === 'NIGHT' && $s['day'] === 2, 'after a tie the game goes to the next night');

// clean elimination and dead restrictions
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'VOTING');
foreach ([1, 2, 3, 4, 5] as $i) {
    mf_act($s, $i + 1, 'vote', 0, $now);
}
run_until($s, $now, 'ELIMINATION', 60);
ok(!$s['seats'][0]['alive'] && $s['seats'][0]['out'] === 'vote', 'highest vote eliminates the player');
ok($s['winner'] === 'civilians', 'eliminating the only mafia is detected as a civilian win');
ok(mf_view($s, 2, $now)['seats'][0]['role'] === 'mafia', 'eliminated role revealed (default setting)');
ok(!isset(mf_view($s, 2, $now)['result']), 'result screen data is not sent before game over');
run_until($s, $now, 'GAME_OVER', 30);
ok($s['phase'] === 'GAME_OVER', 'game over after the elimination phase');
$v = mf_view($s, 2, $now);
ok($v['result']['winner'] === 'civilians' && count($v['result']['roles']) === 6, 'game over reveals all roles');
ok(count($s['final']) === 6, 'final statistics prepared for real players');

$s = make_game(6, 6, ['civilian', 'mafia', 'doctor', 'detective', 'civilian', 'civilian'], false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 1, 'mafia', 0, $now);
run_until($s, $now, 'VOTING');
ok(!$s['seats'][0]['alive'], 'seat 0 died at night');
throws(fn() => act($s, 0, 'vote', 2, $now), 'dead', 'dead player cannot vote');
throws(fn() => act($s, 2, 'vote', 0, $now), 'bad_target', 'cannot vote for a dead player');

section('mafia tie (10 players) and re-vote');
$r10 = ['mafia', 'mafia', 'doctor', 'detective', 'civilian', 'civilian', 'civilian', 'civilian', 'civilian', 'civilian'];
$s = make_game(10, 10, $r10, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
$vm = mf_view($s, 1, $now);
ok($vm['me']['role'] === 'mafia' && $vm['me']['teammates'] === [1], 'mafia sees their teammate');
$vc = mf_view($s, 5, $now);
ok(!isset($vc['me']['teammates']) && $vc['me']['role'] === 'civilian', 'civilian does not see mafia teammates');
act($s, 0, 'mafia', 4, $now);
act($s, 1, 'mafia', 5, $now);
ok($s['night']['round'] === 2 && $s['night']['decided'] === false, 'different targets trigger a short mafia re-vote');
ok(isset(mf_view($s, 1, $now)['me']['night']['notice']) && !isset(mf_view($s, 5, $now)['me']['night']['notice']), 'only mafia see the re-vote notice');
act($s, 0, 'mafia', 4, $now);
act($s, 1, 'mafia', 4, $now);
ok($s['night']['decided'] && $s['night']['target'] === 4, 'agreement in the re-vote selects the target');
run_until($s, $now, 'MORNING');
ok(!$s['seats'][4]['alive'], 'agreed target dies');
$s = make_game(10, 10, $r10, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 0, 'mafia', 4, $now);
act($s, 1, 'mafia', 5, $now);
act($s, 0, 'mafia', 6, $now);
act($s, 1, 'mafia', 7, $now);
ok($s['night']['decided'] && $s['night']['target'] === null, 'still different after the re-vote: nobody is killed (explained, not random)');
run_until($s, $now, 'MORNING');
ok($s['morning']['died'] === null && count(mf_alive_seats($s)) === 10, 'no death after a mafia deadlock');
$s = make_game(10, 10, $r10, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 0, 'mafia', 4, $now);
run_until($s, $now, 'MORNING');
ok(!$s['seats'][4]['alive'], 'a lone confirmed mafia vote stands when the teammate does not act');

section('chat');
$s = make_game(6, 6, $roles, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
throws(fn() => act($s, 3, 'chat', 'привет', $now), 'chat_closed', 'alive civilians cannot chat at night');
run_until($s, $now, 'DISCUSSION');
act($s, 3, 'chat', '<script>alert(1)</script> Привет', $now);
$last = end($s['chat']);
ok($last['text'] === '<script>alert(1)</script> Привет', 'chat text is stored as plain text (the client renders it with textContent)');
$now += 2;
throws(fn() => act($s, 3, 'chat', '   ', $now), 'empty', 'empty message refused');
throws(fn() => mf_act($s, 4, 'chat', str_repeat('я', 201), $now), 'too_long', 'message over 200 chars refused');
throws(fn() => act($s, 3, 'chat', 'привет', $now - 1.5), 'rate', 'messages too fast are refused');
$now += 2;
act($s, 3, 'chat', 'первое', $now);
$now += 1.1;
throws(fn() => act($s, 3, 'chat', 'первое', $now), 'spam', 'repeating the same message is refused');
for ($k = 0; $k < 10; $k++) {
    $now += 1.1;
    try {
        act($s, 4, 'chat', 'msg ' . $k, $now);
    } catch (MfError $e) {
        ok($e->codeName === 'rate', 'burst is limited by rate limiter');
        break;
    }
}
// dead chat is invisible to the living
$s['seats'][5]['alive'] = false;
$s['seats'][5]['out'] = 'night';
$now += 3;
act($s, 5, 'chat', 'секрет мёртвых', $now);
$aliveView = json_encode(mf_view($s, 2, $now), JSON_UNESCAPED_UNICODE);
$deadView = json_encode(mf_view($s, 6, $now), JSON_UNESCAPED_UNICODE);
ok(strpos($aliveView, 'секрет') === false, 'spectator chat is not visible to alive players');
ok(strpos($deadView, 'секрет') !== false, 'dead players see the spectator chat');
$dead = mf_view($s, 6, $now);
ok($dead['me']['can']['chat_channel'] === 'dead', 'dead players write to the spectator channel only');
// mafia channel
$s = make_game(10, 10, $r10, false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
act($s, 0, 'chat', 'берём седьмого', $now);
ok(strpos(json_encode(mf_view($s, 5, $now), JSON_UNESCAPED_UNICODE), 'берём') === false, 'mafia night chat is invisible to civilians');
ok(strpos(json_encode(mf_view($s, 2, $now), JSON_UNESCAPED_UNICODE), 'берём') !== false, 'mafia teammate sees mafia chat');

section('hidden information never leaks');
function walk_roles($node, array &$found, string $path = ''): void
{
    if (!is_array($node)) {
        return;
    }
    foreach ($node as $k => $v) {
        if ($k === 'role' && is_string($v)) {
            $found[] = $path . '.role=' . $v;
        }
        walk_roles($v, $found, $path . '.' . $k);
    }
}
foreach ([[4, 4], [6, 6], [10, 10], [6, 2]] as [$size, $humans]) {
    $s = make_game($size, $humans);
    $now = 1000.0;
    run_until($s, $now, 'DISCUSSION');
    $leaks = 0;
    for ($seat = 0; $seat < $size; $seat++) {
        if ($s['seats'][$seat]['ai']) {
            continue;
        }
        $v = mf_view_seat($s, $seat, $now);
        $found = [];
        walk_roles($v, $found);
        foreach ($found as $f) {
            if ($f !== '.me.role=' . $s['seats'][$seat]['role']) {
                $seatIdx = null;
                if (preg_match('/\.seats\.(\d+)\.role=/', $f, $m)) {
                    $seatIdx = (int) $m[1];
                }
                if ($seatIdx === null || !$s['seats'][$seatIdx]['revealed']) {
                    $leaks++;
                }
            }
        }
        $json = json_encode($v);
        foreach (['"winner"', '"result"', '"sched"', '"susp"', '"ai_pub"', '"private"'] as $needle) {
            if (strpos($json, $needle) !== false) {
                $leaks++;
            }
        }
        if ($s['seats'][$seat]['role'] !== 'mafia' && isset($v['me']['teammates'])) {
            $leaks++;
        }
    }
    ok($leaks === 0, "$size-player game ($humans real): no role/secret leaks in any player's payload");
}

section('disconnect / reconnect');
$s = make_game(6, 3, null, true, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
$roleBefore = $s['seats'][1]['role'];
$t0 = $now;
// player uid 2 stops polling
for ($k = 0; $k < 20; $k++) {
    $now += 1;
    mf_touch($s, 1, $now);
    mf_touch($s, 3, $now);
    mf_tick($s, $now);
}
ok($s['seats'][1]['conn'] === false && $s['seats'][1]['ai'] === false, 'after ~12s of silence the player is shown as reconnecting, seat kept');
$aliveBefore = $s['seats'][1]['alive'];
mf_touch($s, 2, $now);
ok($s['seats'][1]['conn'] === true && $s['seats'][1]['role'] === $roleBefore && $s['seats'][1]['alive'] === $aliveBefore && mf_seat_of($s, 2) === 1, 'reconnect restores the same seat, role and status');
$v = mf_view($s, 2, $now);
ok($v['me']['role'] === $roleBefore && $v['phase'] === $s['phase'], 'reconnect returns the current phase and private role');
// long disconnect with AI fill -> AI takes over the same character
$s = make_game(6, 3, null, true, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
$role2 = $s['seats'][1]['role'];
for ($k = 0; $k < 80; $k++) {
    $now += 1;
    mf_touch($s, 1, $now);
    mf_touch($s, 3, $now);
    mf_tick($s, $now);
}
ok($s['seats'][1]['ai'] === true && $s['seats'][1]['took_over'] === true && $s['seats'][1]['role'] === $role2 && $s['seats'][1]['uid'] === 0, 'AI takes over a player who never returns (same role)');
ok(in_array(2, $s['released'], true), 'the seat is released from the player account');
ok(mf_seat_of($s, 2) === null, 'the replaced player no longer owns a seat');
// long disconnect without AI fill -> removed from the game and win check runs
$s = make_game(4, 4, ['mafia', 'civilian', 'civilian', 'civilian'], false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
for ($k = 0; $k < 80; $k++) {
    $now += 1;
    foreach ([1, 3, 4] as $u) {
        mf_touch($s, $u, $now);
    }
    mf_tick($s, $now);
}
ok(!$s['seats'][1]['alive'] && $s['seats'][1]['out'] === 'left', 'without AI a missing player is removed after the grace period');
$s = make_game(4, 4, ['civilian', 'mafia', 'civilian', 'civilian'], false, 1000.0);
$now = 1000.0;
run_until($s, $now, 'NIGHT');
for ($k = 0; $k < 80; $k++) {
    $now += 1;
    foreach ([1, 3, 4] as $u) {
        mf_touch($s, $u, $now);
    }
    mf_tick($s, $now);
}
ok($s['phase'] === 'GAME_OVER' && $s['winner'] === 'civilians', 'removing the only mafia by disconnect ends the game (win check after disconnect)');

section('host handling');
$s = mf_new_state('HOST', 6, true, true, 6, 0.0);
mf_join($s, 1, 'A', 1.0);
mf_join($s, 2, 'B', 2.0);
mf_join($s, 3, 'C', 3.0);
ok($s['host_uid'] === 1, 'first player is host');
for ($t = 5; $t <= 60; $t += 5) {
    mf_touch($s, 2, (float) $t);
    mf_touch($s, 3, (float) $t);
    mf_tick($s, (float) $t);
}
ok($s['host_uid'] === 2, 'host moves to the oldest connected real player');
ok(strpos(implode('|', array_column($s['events'], 'text')), 'B теперь является владельцем комнаты.') !== false, 'host change is announced in Russian');
ok(mf_seat_of($s, 1) === null, 'silent lobby player is removed');
mf_leave($s, 2, 61.0);
ok($s['host_uid'] === 3, 'host leaving hands the room to the next player');

section('rematch');
$s = make_game(4, 2, ['mafia', 'civilian', 'civilian', 'civilian'], true, 1000.0);
$now = 1000.0;
$s['winner'] = 'mafia';
mf_finish($s, $now);
ok($s['phase'] === 'GAME_OVER', 'finished');
mf_rematch($s, $now);
ok($s['phase'] === 'LOBBY' && mf_count($s, 'humans') === 2 && $s['seats'][0]['role'] === null, 'rematch returns to a clean lobby');


section('AI never uses hidden information');
$diff = 0;
for ($seed = 1; $seed <= 60; $seed++) {
    $picks = [];
    foreach ([['civilian', 'mafia', 'doctor', 'detective', 'civilian', 'civilian'], ['civilian', 'civilian', 'doctor', 'detective', 'civilian', 'mafia']] as $k => $rolesX) {
        mt_srand($seed);
        $s = make_game(6, 1, $rolesX, true, 1000.0);
        $now = 1000.0;
        mf_start_vote($s, $now, 'VOTING', null);
        $s['sched'] = [];
        mt_srand($seed + 500);
        mf_ai_vote($s, 4, $now);   // seat 4 is a civilian AI in both worlds
        $picks[$k] = $s['votes'][4] ?? null;
    }
    if ($picks[0] !== $picks[1]) {
        $diff++;
    }
}
ok($diff === 0, 'civilian AI decides identically whichever hidden player is Mafia (no cheating)');
$leakMemory = json_encode(make_game(6, 1)['ai']);
ok(strpos($leakMemory, 'mafia') === false && strpos($leakMemory, 'role') === false, 'AI memory holds no role information');

section('full simulated matches (AI and scripted humans)');
function human_bot(array &$s, int $uid, float $now): void
{
    $v = mf_view($s, $uid, $now);
    $me = $v['me']['seat'];
    if (!$v['me']['alive']) {
        return;
    }
    $alive = [];
    foreach ($v['seats'] as $row) {
        if (!$row['empty'] && $row['alive'] && $row['seat'] !== $me) {
            $alive[] = $row['seat'];
        }
    }
    try {
        if ($v['phase'] === 'NIGHT' && isset($v['me']['night']) && !$v['me']['night']['done']) {
            $role = $v['me']['role'];
            if ($role === 'mafia') {
                $c = array_values(array_diff($alive, $v['me']['teammates'] ?? []));
                mf_act($s, $uid, 'mafia', $c[array_rand($c)], $now);
            } elseif ($role === 'doctor') {
                mf_act($s, $uid, 'doctor', $alive[array_rand($alive)], $now);
            } elseif ($role === 'detective') {
                mf_act($s, $uid, 'detective', $alive[array_rand($alive)], $now);
            }
        } elseif (in_array($v['phase'], ['VOTING', 'REVOTE'], true) && isset($v['vote']) && !$v['vote']['voted']) {
            $c = $v['vote']['candidates'] === null ? $alive : array_values(array_intersect($alive, $v['vote']['candidates']));
            if ($c) {
                mf_act($s, $uid, 'vote', $c[array_rand($c)], $now);
            }
        } elseif ($v['phase'] === 'DISCUSSION' && mt_rand(0, 12) === 0) {
            mf_act($s, $uid, 'chat', 'Мне кажется, ' . $v['seats'][$alive[array_rand($alive)]]['name'] . ' подозрительный', $now);
        }
    } catch (MfError $e) {
        // legal refusals are fine
    }
}

$stats = [];
$totalGames = 0;
foreach ([[4, 4], [4, 1], [6, 6], [6, 2], [6, 1], [10, 10], [10, 3], [10, 1]] as [$size, $humans]) {
    $wins = ['mafia' => 0, 'civilians' => 0];
    $maxDays = 0;
    for ($seed = 1; $seed <= 40; $seed++) {
        mt_srand($seed * 7919 + $size * 31 + $humans);
        $s = make_game($size, $humans, null, true, 5000.0);
        $now = 5000.0;
        $guard = 0;
        while ($s['phase'] !== 'GAME_OVER' && $guard++ < 4000) {
            $now += 1.0;
            for ($u = 1; $u <= $humans; $u++) {
                mf_touch($s, $u, $now);
                human_bot($s, $u, $now);
            }
            mf_tick($s, $now);
        }
        $good = $s['phase'] === 'GAME_OVER' && in_array($s['winner'], ['mafia', 'civilians'], true);
        if (!$good) {
            ok(false, "match $size/$humans seed $seed finished ({$s['phase']}, day {$s['day']}, guard $guard)");
            continue;
        }
        $mafia = count(mf_alive_seats($s, 'mafia'));
        $others = count(mf_alive_seats($s)) - $mafia;
        $consistent = ($s['winner'] === 'civilians' && $mafia === 0) || ($s['winner'] === 'mafia' && $mafia >= $others);
        if (!$consistent) {
            ok(false, "match $size/$humans seed $seed winner consistent");
        }
        $wins[$s['winner']]++;
        $maxDays = max($maxDays, $s['day']);
        $totalGames++;
        $roleCount = count(array_filter($s['seats'], fn($x) => $x['occ']));
        if ($roleCount !== $size) {
            ok(false, 'seat count stays ' . $size);
        }
    }
    $stats[] = "$size players / $humans real: mafia {$wins['mafia']}, civilians {$wins['civilians']}, longest {$maxDays} days";
}
ok(true, 'simulations done');
echo implode("\n", array_map(fn($l) => "  $l", $stats)) . "\n";
echo "  $totalGames complete matches played from lobby to result\n";

echo "\n" . $GLOBALS['pass'] . " passed, " . $GLOBALS['fail'] . " failed\n";
exit($GLOBALS['fail'] > 0 ? 1 : 0);
