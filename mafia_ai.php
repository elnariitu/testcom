<?php
declare(strict_types=1);

/*
 * Mafia AI players.
 * Every decision is made from mf_view_seat(): the same filtered view a human in that seat would receive,
 * plus the AI's own suspicion memory. An AI therefore can never "cheat" (a civilian AI does not know who is
 * Mafia, the Doctor does not know either, the Detective only knows their own investigation results, and the
 * Mafia know each other). Decisions are deliberately imperfect.
 */

function mf_ai_init(array &$s): void
{
    $s['ai'] = [];
    $s['ai_pub'] = ['acc' => [], 'def' => [], 'acc_by' => [], 'hist' => []];
    foreach ($s['seats'] as $seat) {
        if (!$seat['occ']) {
            continue;
        }
        $mem = ['susp' => [], 'said' => 0, 'claimed' => false, 'talk' => mf_frand(0.35, 1.0)];
        foreach ($s['seats'] as $other) {
            if ($other['occ'] && $other['seat'] !== $seat['seat']) {
                $mem['susp'][$other['seat']] = 30 + mf_rand(-8, 8);
            }
        }
        $s['ai'][$seat['seat']] = $mem;
    }
}

function mf_ai_takeover(array &$s, int $i, float $now): void
{
    if (!isset($s['ai'][$i])) {
        $s['ai'][$i] = ['susp' => [], 'said' => 0, 'claimed' => false, 'talk' => 0.6];
        foreach ($s['seats'] as $other) {
            if ($other['occ'] && $other['seat'] !== $i) {
                $s['ai'][$i]['susp'][$other['seat']] = 30 + mf_rand(-8, 8);
            }
        }
    }
    mf_ai_plan_seat($s, $i, $now);
}

/* what this AI believes about a player (0..100), using only things it is allowed to know */
function mf_ai_susp(array $s, int $me, int $target, array $view): float
{
    if (($view['me']['role'] ?? '') === 'mafia' && in_array($target, $view['me']['teammates'] ?? [], true)) {
        return 0.0;
    }
    foreach ($view['me']['checks'] ?? [] as $check) {
        if ($check['seat'] === $target) {
            return $check['mafia'] ? 100.0 : 0.0;
        }
    }
    return (float) ($s['ai'][$me]['susp'][$target] ?? 30);
}

function mf_ai_bump(array &$s, int $target, float $delta, ?int $onlyAi = null): void
{
    foreach ($s['ai'] as $a => $mem) {
        if ($onlyAi !== null && $a !== $onlyAi) {
            continue;
        }
        if ($a === $target) {
            continue;
        }
        $v = ($s['ai'][$a]['susp'][$target] ?? 30) + $delta;
        $s['ai'][$a]['susp'][$target] = max(0.0, min(100.0, $v));
    }
}

/* ---------- planning: when each AI acts ---------- */

function mf_ai_add_sched(array &$s, float $at, int $seat, string $kind, array $extra = []): void
{
    $s['sched'][] = array_merge(['at' => $at, 'seat' => $seat, 'kind' => $kind, 'pid' => $s['pid']], $extra);
}

function mf_ai_needs_night(array $s, int $seat): bool
{
    $role = $s['seats'][$seat]['role'];
    return in_array($role, ['mafia', 'doctor', 'detective'], true);
}

function mf_ai_plan(array &$s, float $now): void
{
    $s['sched'] = [];
    foreach ($s['ai'] as $i => $mem) {
        $s['ai'][$i]['said'] = $s['phase'] === 'DISCUSSION' ? 0 : $s['ai'][$i]['said'];
    }
    switch ($s['phase']) {
        case 'NIGHT':
            foreach (mf_alive_seats($s) as $i) {
                if ($s['seats'][$i]['ai'] && mf_ai_needs_night($s, $i)) {
                    mf_ai_add_sched($s, $now + mf_frand(3, 13), $i, 'night');
                }
            }
            break;
        case 'VOTING':
        case 'REVOTE':
            foreach (mf_alive_seats($s) as $i) {
                if ($s['seats'][$i]['ai']) {
                    mf_ai_add_sched($s, $now + mf_frand(3, 15), $i, 'vote');
                }
            }
            break;
        case 'DISCUSSION':
            mf_ai_plan_discussion($s, $now);
            break;
    }
}

function mf_ai_plan_discussion(array &$s, float $now): void
{
    $slots = [];
    foreach (mf_alive_seats($s) as $i) {
        if (!$s['seats'][$i]['ai']) {
            continue;
        }
        $talk = $s['ai'][$i]['talk'] ?? 0.6;
        $count = 1;
        if (mf_chance(0.5 * $talk + 0.1)) {
            $count++;
        }
        if ($count === 2 && mf_chance(0.3 * $talk)) {
            $count++;
        }
        for ($k = 0; $k < $count; $k++) {
            $slots[] = ['seat' => $i, 'at' => $now + mf_frand(5, 52)];
        }
    }
    usort($slots, fn($a, $b) => $a['at'] <=> $b['at']);
    $slots = array_slice($slots, 0, 7);
    $prev = $now;
    foreach ($slots as $slot) {
        $at = max($slot['at'], $prev + 3.5);
        if ($at > $now + 57) {
            break;
        }
        mf_ai_add_sched($s, $at, $slot['seat'], 'chat');
        $prev = $at;
    }
}

function mf_ai_plan_seat(array &$s, int $i, float $now): void
{
    if (!$s['seats'][$i]['alive'] || $s['phase_end'] === null) {
        return;
    }
    $left = $s['phase_end'] - $now;
    switch ($s['phase']) {
        case 'NIGHT':
            if (mf_ai_needs_night($s, $i) && $left > 3) {
                mf_ai_add_sched($s, $now + mf_frand(1.5, min(8, $left - 1)), $i, 'night');
            }
            break;
        case 'VOTING':
        case 'REVOTE':
            if (!array_key_exists($i, $s['votes'] ?? []) && $left > 3) {
                mf_ai_add_sched($s, $now + mf_frand(1.5, min(8, $left - 1)), $i, 'vote');
            }
            break;
        case 'DISCUSSION':
            if ($left > 8) {
                mf_ai_add_sched($s, $now + mf_frand(4, min(30, $left - 3)), $i, 'chat');
            }
            break;
    }
}

function mf_ai_plan_mafia_revote(array &$s, float $now): void
{
    $mafia = mf_alive_seats($s, 'mafia');
    sort($mafia);
    $step = 1.0;
    foreach ($mafia as $m) {
        if ($s['seats'][$m]['ai']) {
            mf_ai_add_sched($s, $now + $step + mf_frand(0, 1.5), $m, 'night');
            $step += 2.0;
        }
    }
}

/* ---------- running scheduled actions ---------- */

function mf_ai_run(array &$s, float $now): void
{
    if (!$s['sched']) {
        return;
    }
    $due = [];
    $rest = [];
    foreach ($s['sched'] as $entry) {
        if ($entry['at'] <= $now) {
            $due[] = $entry;
        } else {
            $rest[] = $entry;
        }
    }
    if (!$due) {
        return;
    }
    $s['sched'] = $rest;
    usort($due, fn($a, $b) => $a['at'] <=> $b['at']);
    foreach ($due as $entry) {
        $i = $entry['seat'];
        if ($entry['pid'] !== $s['pid'] || !$s['seats'][$i]['occ'] || !$s['seats'][$i]['ai'] || !$s['seats'][$i]['alive']) {
            continue;
        }
        try {
            if ($entry['kind'] === 'night') {
                mf_ai_night($s, $i, $now);
            } elseif ($entry['kind'] === 'vote') {
                mf_ai_vote($s, $i, $now);
            } elseif ($entry['kind'] === 'chat') {
                mf_ai_chat($s, $i, $now, $entry['sub'] ?? '');
            }
        } catch (MfError $e) {
            // an AI that picked something illegal simply does nothing this time
        }
    }
}

function mf_ai_pick(array $scores, float $mistake): ?int
{
    if (!$scores) {
        return null;
    }
    if (mf_chance($mistake)) {
        $keys = array_keys($scores);
        return $keys[mf_rand(0, count($keys) - 1)];
    }
    arsort($scores);
    return array_key_first($scores);
}

function mf_ai_night(array &$s, int $i, float $now): void
{
    $v = mf_view_seat($s, $i, $now);
    $role = $v['me']['role'];
    $alive = [];
    foreach ($v['seats'] as $row) {
        if (!$row['empty'] && $row['alive']) {
            $alive[] = $row['seat'];
        }
    }
    if ($role === 'mafia') {
        $mates = $v['me']['teammates'] ?? [];
        $night = $v['me']['night'] ?? ['done' => true];
        if ($night['done']) {
            return;
        }
        $cands = array_values(array_filter($alive, fn($c) => $c !== $i && !in_array($c, $mates, true)));
        if (!$cands) {
            return;
        }
        if (($night['round'] ?? 1) === 2) {
            foreach ($night['votes'] ?? [] as $vote) {
                if ($vote['seat'] !== $i && in_array($vote['target'], $cands, true)) {
                    mf_night_mafia($s, $i, $vote['target'], $now);
                    return;
                }
            }
        }
        $scores = [];
        foreach ($cands as $c) {
            $threat = 0;
            foreach ($s['ai_pub']['acc'] as $acc) {
                if ($acc['from'] === $c && ($acc['to'] === $i || in_array($acc['to'], $mates, true))) {
                    $threat += 12;
                }
            }
            $scores[$c] = $threat + mf_frand(0, 30);
        }
        $t = mf_ai_pick($scores, 0.1);
        if ($t !== null) {
            mf_night_mafia($s, $i, $t, $now);
        }
    } elseif ($role === 'doctor') {
        if ($v['me']['night']['done'] ?? true) {
            return;
        }
        $last = $v['me']['last_protect'] ?? null;
        $scores = [];
        foreach ($alive as $c) {
            if ($c === $last) {
                continue;
            }
            $trust = $c === $i ? 55.0 : 100 - mf_ai_susp($s, $i, $c, $v);
            $active = 4 * count(array_filter($s['ai_pub']['acc'], fn($a) => $a['from'] === $c));
            $scores[$c] = $trust * 0.35 + $active + ($c === $i ? 10 : 0) + mf_frand(0, 30);
        }
        $t = mf_ai_pick($scores, 0.12);
        if ($t !== null) {
            mf_night_doctor($s, $i, $t, $now);
        }
    } elseif ($role === 'detective') {
        if ($v['me']['night']['done'] ?? true) {
            return;
        }
        $checked = array_map(fn($c) => $c['seat'], $v['me']['checks'] ?? []);
        $scores = [];
        foreach ($alive as $c) {
            if ($c === $i || in_array($c, $checked, true)) {
                continue;
            }
            $scores[$c] = mf_ai_susp($s, $i, $c, $v) + mf_frand(0, 25);
        }
        $t = mf_ai_pick($scores, 0.15);
        if ($t !== null) {
            mf_night_detective($s, $i, $t, $now);
        }
    }
}

function mf_ai_vote(array &$s, int $i, float $now): void
{
    $v = mf_view_seat($s, $i, $now);
    if (!isset($v['vote']) || $v['vote']['voted']) {
        return;
    }
    $cands = [];
    foreach ($v['seats'] as $row) {
        if (!$row['empty'] && $row['alive'] && $row['seat'] !== $i) {
            $cands[] = $row['seat'];
        }
    }
    if ($v['vote']['candidates'] !== null) {
        $cands = array_values(array_intersect($cands, $v['vote']['candidates']));
    }
    $role = $v['me']['role'];
    if ($role === 'mafia') {
        $mates = $v['me']['teammates'] ?? [];
        $cands = array_values(array_filter($cands, fn($c) => !in_array($c, $mates, true)));
    }
    if (!$cands) {
        return;
    }
    if ($role === 'detective') {
        foreach ($v['me']['checks'] ?? [] as $check) {
            if ($check['mafia'] && in_array($check['seat'], $cands, true) && mf_chance(0.85)) {
                mf_vote($s, $i, $check['seat'], $now);
                return;
            }
        }
    }
    $scores = [];
    foreach ($cands as $c) {
        $scores[$c] = mf_ai_susp($s, $i, $c, $v) + mf_frand(-22, 22);
    }
    $t = mf_ai_pick($scores, $role === 'mafia' ? 0.3 : 0.18);
    if ($t !== null) {
        mf_vote($s, $i, $t, $now);
    }
}

/* ---------- talking ---------- */

function mf_ai_pick_text(array $list, array $s): string
{
    $recent = [];
    foreach (array_slice($s['chat'], -12) as $m) {
        $recent[$m['text']] = true;
    }
    $fresh = array_values(array_filter($list, fn($t) => !isset($recent[$t])));
    $pool = $fresh ?: $list;
    return $pool[mf_rand(0, count($pool) - 1)];
}

function mf_ai_chat(array &$s, int $i, float $now, string $sub): void
{
    if (!$s['seats'][$i]['alive'] || ($s['ai'][$i]['said'] ?? 0) >= 3 || $s['phase'] !== 'DISCUSSION') {
        return;
    }
    $v = mf_view_seat($s, $i, $now);
    $role = $v['me']['role'];
    $mates = $v['me']['teammates'] ?? [];
    $names = [];
    $cands = [];
    foreach ($v['seats'] as $row) {
        if (!$row['empty']) {
            $names[$row['seat']] = $row['name'];
            if ($row['alive'] && $row['seat'] !== $i) {
                $cands[] = $row['seat'];
            }
        }
    }
    if (!$cands) {
        return;
    }
    $text = null;
    if ($sub === 'defend') {
        $text = mf_ai_pick_text(['Я не мафия, давайте разберёмся спокойно.', 'Почему ты так решил? Я мирный.', 'Это необоснованное обвинение.', 'У вас нет причин меня подозревать.'], $s);
    }
    if ($text === null && $role === 'detective' && !$s['ai'][$i]['claimed']) {
        foreach ($v['me']['checks'] ?? [] as $check) {
            if ($check['mafia'] && in_array($check['seat'], $cands, true) && mf_chance(0.35 + ($s['day'] >= 2 ? 0.15 : 0))) {
                $text = 'Я проверил ' . $names[$check['seat']] . ' — он связан с мафией.';
                $s['ai'][$i]['claimed'] = true;
                break;
            }
        }
    }
    if ($text === null) {
        $susp = [];
        foreach ($cands as $c) {
            if ($role === 'mafia' && in_array($c, $mates, true)) {
                continue;
            }
            $susp[$c] = mf_ai_susp($s, $i, $c, $v);
        }
        arsort($susp);
        $top = $susp ? array_slice(array_keys($susp), 0, 3) : [];
        $options = ['unsure' => 1.3];
        if ($top) {
            $options['accuse'] = ($susp[$top[0]] >= 38 || $role === 'mafia') ? 3.0 : 1.0;
            $options['innocent'] = 1.0;
        }
        $le = $s['last_elim'] ?? null;
        if ($le && $le['kind'] === 'vote' && $le['votes']) {
            $options['question'] = 1.6;
        } else {
            $options['question'] = 0.7;
        }
        $sum = array_sum($options);
        $roll = mf_frand(0, $sum);
        $kind = 'unsure';
        foreach ($options as $k => $w) {
            if ($roll < $w) {
                $kind = $k;
                break;
            }
            $roll -= $w;
        }
        switch ($kind) {
            case 'accuse':
                $t = ($role === 'mafia' && mf_chance(0.5)) ? $top[mf_rand(0, count($top) - 1)] : $top[0];
                $text = mf_ai_pick_text(['Я подозреваю ' . $names[$t] . '.', $names[$t] . ' ведёт себя подозрительно.', 'Мне кажется, ' . $names[$t] . ' — мафия.', 'Что-то мне не нравится ' . $names[$t] . '.'], $s);
                break;
            case 'innocent':
                $low = array_slice(array_keys(array_reverse($susp, true)), 0, 2);
                $t = $low[mf_rand(0, count($low) - 1)];
                $text = mf_ai_pick_text(['Возможно, ' . $names[$t] . ' мирный.', $names[$t] . ' пока не вызывает у меня подозрений.'], $s);
                break;
            case 'question':
                $asked = null;
                if ($le && $le['kind'] === 'vote' && $le['votes']) {
                    foreach ($le['votes'] as $vote) {
                        if ($vote['to'] === $le['seat'] && $vote['from'] !== $i && isset($names[$vote['from']]) && $s['seats'][$vote['from']]['alive']) {
                            $asked = $vote;
                            break;
                        }
                    }
                }
                if ($asked !== null) {
                    $text = $names[$asked['from']] . ', почему ты голосовал против ' . $names[$asked['to']] . '?';
                } else {
                    $text = mf_ai_pick_text(['Кто-нибудь заметил что-то странное?', 'Кому вы доверяете?', 'Почему все молчат?'], $s);
                }
                break;
            default:
                $text = mf_ai_pick_text(['Я пока не уверен.', 'Пока слишком мало информации.', 'Не будем спешить с выводами.', 'Слушаю вас внимательно.'], $s);
        }
    }
    $s['ai'][$i]['said']++;
    mf_push_chat($s, $i, $text, 'day', $now);
}

/* ---------- observing the public game ---------- */

function mf_ai_on_chat(array &$s, int $speaker, string $text, float $now): void
{
    if (!$s['ai']) {
        return;
    }
    $defend = (bool) preg_match('/невинов|не мафи|мирн|довер|защища/iu', $text);
    $accuse = !$defend && (bool) preg_match('/подозр|мафи|виновн|странн|врёт|врет|лжёт|лжет|убийц/iu', $text);
    if (!$defend && !$accuse) {
        return;
    }
    foreach ($s['seats'] as $seat) {
        $j = $seat['seat'];
        if (!$seat['occ'] || $j === $speaker || $seat['name'] === '' || mb_stripos($text, $seat['name']) === false) {
            continue;
        }
        if ($accuse) {
            $s['ai_pub']['acc'][] = ['from' => $speaker, 'to' => $j, 'day' => $s['day']];
            $s['ai_pub']['acc'] = array_slice($s['ai_pub']['acc'], -60);
            mf_ai_bump($s, $j, 4.0);
            if ($seat['ai'] && $seat['alive']) {
                mf_ai_bump($s, $speaker, 6.0, $j);
                if (($s['ai'][$j]['said'] ?? 0) < 3 && $s['phase'] === 'DISCUSSION' && mf_chance(0.7)) {
                    mf_ai_add_sched($s, $now + mf_frand(3, 7), $j, 'chat', ['sub' => 'defend']);
                }
            }
        } else {
            $s['ai_pub']['def'][$speaker][] = $j;
            mf_ai_bump($s, $j, -3.0);
        }
    }
}

function mf_ai_observe_night(array &$s, ?int $died): void
{
    // Nothing certain can be learned from a night death without extra guesswork; suspicion stays as it is.
}

function mf_ai_observe_votes(array &$s, array $votes): void
{
    if (!$s['ai']) {
        return;
    }
    $s['ai_pub']['hist'][] = ['day' => $s['day'], 'votes' => $votes];
    $s['ai_pub']['hist'] = array_slice($s['ai_pub']['hist'], -6);
}

function mf_ai_observe_elimination(array &$s, array $info): void
{
    if (!$s['ai'] || $info['kind'] !== 'vote' || !$s['reveal_roles']) {
        return;
    }
    $out = $info['seat'];
    $wasMafia = $s['seats'][$out]['role'] === 'mafia';
    foreach ($info['votes'] as $vote) {
        if ($vote['to'] === $out) {
            mf_ai_bump($s, $vote['from'], $wasMafia ? -10.0 : 9.0);
        }
    }
    foreach ($s['ai_pub']['def'] as $speaker => $targets) {
        if (in_array($out, $targets, true)) {
            mf_ai_bump($s, $speaker, $wasMafia ? 15.0 : -4.0);
        }
    }
}
