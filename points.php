<?php
declare(strict_types=1);

/*
 * Points earned in the games, and the "top players" list (total points + nickname).
 *  ?action=top      public: the three best players
 *  ?action=submit   signed-in players: points a finished game gave them (the user always comes from the session)
 * Mafia points are awarded by the server itself (mafia.php) and cannot be submitted from a browser.
 */

require_once __DIR__ . '/mafia_engine.php';
require_once __DIR__ . '/mafia_db.php';

const POINTS_GAMES = ['answer-rush', 'timeline-rush', 'history-map', 'who-am-i', 'true-or-trap', 'history-duel', 'capture-the-answer', 'history-millionaire'];
const POINTS_MAX = 25000;         // one game can never be worth more than this
const POINTS_MIN_GAP = 20;        // seconds between two submissions of the same game

$pdo = mf_connect();
mf_schema($pdo);
$action = (string) ($_GET['action'] ?? '');

try {
    if ($action === 'top') {
        mf_out(['ok' => true, 'top' => mf_top_players($pdo, 3)]);
    }
    if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = mf_session_uid();
        if ($uid <= 0 || mf_display_name($pdo, $uid) === '') {
            mf_out(['ok' => false, 'error' => 'auth']);
        }
        $in = mf_input();
        $game = (string) ($in['game'] ?? '');
        $points = (int) round((float) ($in['points'] ?? 0));
        if (!in_array($game, POINTS_GAMES, true) || $points <= 0) {
            mf_out(['ok' => false, 'error' => 'bad_request']);
        }
        $points = min($points, POINTS_MAX);
        $st = $pdo->prepare('SELECT MAX(created) AS last FROM testcom_points_log WHERE user_id = ? AND game = ?');
        $st->execute([$uid, $game]);
        $last = (int) ($st->fetch()['last'] ?? 0);
        if (time() - $last < POINTS_MIN_GAP) {
            mf_out(['ok' => false, 'error' => 'rate']);
        }
        mf_points_add($pdo, $uid, $game, $points);
        mf_out(['ok' => true, 'added' => $points]);
    }
    mf_out(['ok' => false, 'error' => 'bad_request'], 400);
} catch (Throwable $e) {
    error_log('Testcom points error: ' . $e->getMessage());
    mf_out(['ok' => false, 'error' => 'server'], 500);
}
