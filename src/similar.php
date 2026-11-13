<?php
const SIMILAR_MEMBERS = [10, 16, 17, 18];
const SIMILAR_TRAITS = ['defence' => 40, 'attack' => 10, 'prayer' => 10, 'hp' => 45, 'skiller' => 55, 'defence_only' => 45, 'range_mage' => 20, 'strength_only' => 20, 'range_only' => 20, 'combat_focus' => 15, 'f2p' => 40];

function similar_model(): string {
    return ehp_model()['id'] . ':' . substr(hash_file('sha256', __FILE__), 0, 16);
}

function similar_snapshot(array $rows): array {
    $latest = $rows ? max(array_column($rows, 'at')) : null;
    $stats = array_column(array_filter($rows, fn($r) => $r['at'] == $latest), null, 'type');
    $overall = $stats[0] ?? null; $coherent = null;
    $missing = array_diff_key(SKILLS, $stats);
    $upper = array_fill_keys(array_keys(SKILLS), 99);
    foreach ($stats as $t => $r) $upper[$t] = (int) $r['level'];
    if ($overall && count(array_unique(array_column($stats, 'at'))) === 1) {
        $known = array_diff_key($stats, [0 => true]);
        $remainingXp = (int) $overall['value'] - array_sum(array_column($known, 'value'));
        $remainingLevels = (int) $overall['level'] - array_sum(array_column($known, 'level'));
        $minLevels = array_sum(array_map(fn($t) => $t === 4 ? 10 : 1, array_keys($missing)));
        $minXp = isset($missing[4]) ? 11540 : 0;
        $coherent = $remainingXp >= $minXp && $remainingLevels >= $minLevels;
        if (!$missing) $coherent = $remainingXp === 0 && $remainingLevels === 0;
        if (count($missing) === 1) $coherent = $coherent && min(99, virtual_level(max(0, $remainingXp) / 10)) === $remainingLevels;
        if ($remainingXp === $minXp) $coherent = $coherent && $remainingLevels === $minLevels;
        foreach ($coherent ? $missing : [] as $t => $_) {
            $minimum = $t === 4 ? 10 : 1;
            $upper[$t] = min(99, $minimum + $remainingLevels - $minLevels, virtual_level(($remainingXp - $minXp + ($t === 4 ? 11540 : 0)) / 10));
            // Only coherent totals, not an absent hiscores entry, can prove an omitted skill.
            if ($remainingXp === $minXp || count($missing) === 1) {
                $value = count($missing) === 1 ? $remainingXp : ($t === 4 ? 11540 : 0);
                $level = min(99, virtual_level($value / 10));
                $stats[$t] = ['type' => $t, 'level' => $level, 'value' => $value, 'at' => $overall['at']];
                $upper[$t] = $level;
            }
        }
    }
    return ['stats' => array_diff_key($stats, [0 => true]), 'upper' => $upper, 'complete' => count($stats) === count(SKILLS) && $coherent !== false, 'coherent' => $coherent];
}

function similar_profile(array $rows, array $before, int $now): array {
    $snapshot = similar_snapshot($rows); $s = $snapshot['stats']; $u = $snapshot['upper'];
    $l = array_map(fn($r) => (int) $r['level'], $s);
    foreach (array_diff_key($u, $l, [0 => true]) as $t => $cap) if ($cap === ($t === 4 ? 10 : 1)) $l[$t] = $cap;
    $values = array_map(fn($r) => (int) $r['value'], $s);
    $ehp = calculate_ehp($values);
    $total = $snapshot['complete'] ? $ehp[0] : null;
    $shape = $total !== null && $total >= 100 ? array_map(fn($h) => $h / $total, array_diff_key($ehp, [0 => true])) : [];
    $traits = []; $restricted = [];
    $add = function (string $key, float $value, array $skills) use (&$traits, &$restricted) {$traits[$key] = $value; $restricted[$key] = $skills;};
    $offence = max($l[3] ?? 0, $l[5] ?? 0, $l[7] ?? 0);
    $developed = max(array_intersect_key($l, array_flip([8,9,10,11,12,13,14,15,16,17,18,21])) ?: [0]) >= 60;
    if ($offence >= 70) {
        if (isset($l[2]) && ($l[2] <= 41 || abs($l[2] - 45) <= 1 || abs($l[2] - 60) <= 1)) {
            $add('defence', $l[2] <= 2 ? 1 : $l[2], [2]);
        }
        if (isset($l[1]) && $l[1] <= 65 && $offence - $l[1] >= 20) $add('attack', $l[1], [1]);
        if (isset($l[6]) && $l[6] <= 60) $add('prayer', $l[6], [6]);
    }
    if (isset($l[4]) && (($l[4] === 10 && ($offence >= 50 || $developed)) || ($l[4] <= 40 && $offence >= 70 && $offence - $l[4] >= 30))) $add('hp', $l[4], [4]);
    if ($developed && combat_level($u) === 3) $add('skiller', 1, [1,2,3,4,5,6,7]);
    if (($l[2] ?? 0) >= 40 && max($u[1], $u[3]) <= 5 && max($u[5], $u[7]) <= 10) $add('defence_only', $l[2], [1,3,5,7]);
    if (min($l[5] ?? 0, $l[7] ?? 0) >= 70 && max($u[1], $u[3]) <= min($l[5], $l[7]) - 25) $add('range_mage', min($l[5], $l[7]), [1,3]);
    if (($l[3] ?? 0) >= 70 && $u[1] <= 40 && $u[2] <= 2 && max($u[5], $u[7]) <= $l[3] - 25) $add('strength_only', $l[3], [1,2,5,7]);
    if (($l[5] ?? 0) >= 70 && max($u[1], $u[3]) <= $l[5] - 25) $add('range_only', $l[5], [1,3]);
    if ($shape && array_sum(array_intersect_key($shape, array_flip([1,2,3,5,6,7]))) >= .8) $add('combat_focus', 1, []);
    if ($offence >= 60 || $developed) {
        if (max(array_intersect_key($u, array_flip(SIMILAR_MEMBERS))) <= 10) $add('f2p', 1, SIMILAR_MEMBERS);
    }
    // Inferred XP is useful for a snapshot, but cannot establish observed training or maintenance.
    $old = array_column($before, null, 'type');
    $observed = array_intersect_key(array_column($rows, null, 'type'), $s);
    $observed = array_filter($observed, fn($r) => $r['at'] == max(array_column($rows, 'at')));
    $stable = []; $recent = []; $delta = []; $activeAt = 0;
    foreach (array_intersect_key($observed, $old) as $t => $r) {
        if ($r['value'] < $old[$t]['value']) throw new RuntimeException('Similarity history has decreasing XP');
        if ($r['value'] > $old[$t]['value']) {$delta[$t] = $r['value'] - $old[$t]['value']; $activeAt = max($activeAt, (int) $r['at']);}
    }
    foreach ($restricted as $key => $skills) {
        if (!$skills) continue;
        $maintained = true;
        foreach ($skills as $t) if (!isset($observed[$t], $old[$t]) || $old[$t]['at'] > $now - 7 * 86400000 || $observed[$t]['at'] - $old[$t]['at'] < 7 * 86400000 || $observed[$t]['value'] != $old[$t]['value']) $maintained = false;
        if ($maintained && array_sum(array_diff_key($delta, array_flip($skills))) >= 10000) $stable[$key] = true;
    }
    if ($activeAt >= $now - 7 * 86400000) {
        $common = array_intersect_key($observed, $old);
        $then = calculate_ehp(array_map(fn($r) => $r['value'], array_intersect_key($old, $common)));
        $after = calculate_ehp(array_map(fn($r) => $r['value'], $common));
        foreach ($common as $t => $_) $recent[$t] = max(0, $after[$t] - $then[$t]);
        $hours = array_sum($recent);
        $recent = $hours >= 2 ? array_map(fn($h) => $h / $hours, $recent) : [];
    }
    $post99 = [];
    foreach ($values as $t => $value) if ($value >= 1.5 * 130344310) $post99[$t] = $value / 130344310 - 1;
    return ['traits' => $traits, 'stable' => $stable, 'shape' => $shape, 'post99' => $post99, 'recent' => $recent, 'active_at' => $activeAt, 'ehp' => $total, 'coherent' => $snapshot['coherent']];
}

function similar_closeness(string $key, float $a, float $b): float {
    return match ($key) {
        'defence' => abs($a - $b) <= 1 ? 1 - .2 * abs($a - $b) : 0,
        'hp' => ($a === 10.0) !== ($b === 10.0) ? 0 : exp(-abs($a - $b) / 8),
        'attack' => exp(-abs($a - $b) / 10),
        'prayer' => exp(-abs($a - $b) / 6),
        'range_mage', 'strength_only', 'range_only', 'defence_only' => exp(-abs($a - $b) / 20),
        'skiller', 'combat_focus', 'f2p' => 1,
        default => throw new RuntimeException("Unknown similarity trait: $key"),
    };
}

function similar_label(string $key, float $value): string {
    return match ($key) {
        'defence' => $value === 1.0 ? '1–2 Defence' : (int) $value . ' Defence',
        'attack' => (int) $value . ' Attack', 'prayer' => (int) $value . ' Prayer', 'hp' => (int) $value . ' HP build',
        'skiller' => 'Combat-3 skiller', 'defence_only' => 'Defence-focused', 'range_mage' => 'Ranged / Magic build',
        'strength_only' => 'Strength-focused build', 'range_only' => 'Ranged without melee', 'combat_focus' => 'Combat-focused', 'f2p' => 'F2P-like skills',
        default => throw new RuntimeException("Unknown similarity trait: $key"),
    };
}

function similar_weights(array $target, iterable $profiles): array {
    $counts = ['traits' => [], 'shape' => [], 'post99' => []]; $sizes = ['traits' => 0, 'shape' => 0, 'post99' => 0];
    foreach ($profiles as $p) {
        foreach ($sizes as $group => $_) {
            if ($group !== 'traits' && !$p[$group]) continue;
            $sizes[$group]++;
            foreach ($target[$group] as $key => $value) {
                if (!isset($p[$group][$key])) continue;
                $match = $group === 'traits' ? similar_closeness($key, $value, $p[$group][$key]) >= .7 : $p[$group][$key] >= .8 * $value;
                if ($match) $counts[$group][$key] = ($counts[$group][$key] ?? 0) + 1;
            }
        }
    }
    $weights = ['traits' => [], 'shape' => [], 'post99' => []];
    foreach ($weights as $group => $_) foreach ($target[$group] as $key => $value) {
        if ($group === 'shape' && $value < .1) continue;
        $count = $counts[$group][$key] ?? 0;
        if (!$count || !$sizes[$group]) throw new RuntimeException('Similarity population excludes its target');
        $rarity = log($sizes[$group] / $count, 2);
        if ($group === 'traits') $weights[$group][$key] = SIMILAR_TRAITS[$key] * min(1, .35 + $rarity / 4);
        elseif ($group === 'post99') $weights[$group][$key] = $value * max(.25, $rarity);
        elseif ($rarity >= 1) $weights[$group][$key] = $value * $rarity;
    }
    arsort($weights['shape']); $weights['shape'] = array_slice($weights['shape'], 0, 3, true);
    return $weights;
}

function similar_score(array $a, array $b, array $weights, int $now): array {
    $build = 0; $history = 0; $reasons = [];
    foreach ($weights['traits'] as $key => $weight) {
        if (!isset($b['traits'][$key])) continue;
        $close = similar_closeness($key, $a['traits'][$key], $b['traits'][$key]);
        $points = $weight * $close;
        $maintained = isset($a['stable'][$key], $b['stable'][$key]);
        if ($maintained) $history = max($history, 8 * $close);
        $build += $points;
        if ($close >= .7 && $points >= 3) $reasons[($maintained ? 'Maintained ' : '') . similar_label($key, $b['traits'][$key])] = $points * ($maintained ? 1.15 : 1);
    }
    $signature = 0;
    foreach (['shape', 'post99'] as $group) {
        $sum = array_sum($weights[$group]); $points = 0;
        foreach ($weights[$group] as $t => $weight) {
            if (!isset($b[$group][$t])) continue;
            $x = $a[$group][$t]; $y = $b[$group][$t];
            $close = $group === 'shape' ? min(1, $y / $x) * exp(-abs($x - $y) / .15) : exp(-abs(log($x / $y)));
            $part = 35 * $weight / $sum * $close; $points += $part;
            if ($close >= .5 && $part >= 3) $reasons[SKILLS[$t] . ($group === 'shape' ? '-focused EHP' : ' beyond 99')] = $part;
        }
        $signature = max($signature, $points);
    }
    $score = min(65, $build) + $signature + $history;
    if ($a['active_at'] >= $now - 7 * 86400000 && $b['active_at'] >= $now - 7 * 86400000) {
        foreach ($a['recent'] as $t => $share) {
            $other = $b['recent'][$t] ?? 0;
            if (min($share, $other) < .4) continue;
            $points = 8 * min($share, $other) * exp(-abs($share - $other) / .2);
            $score += $points; $reasons['Recently training ' . SKILLS[$t]] = $points;
        }
    }
    if ($score >= 10 && $a['ehp'] !== null && $b['ehp'] !== null && min($a['ehp'], $b['ehp']) > 0) $score += 4 * min($a['ehp'], $b['ehp']) / max($a['ehp'], $b['ehp']);
    // An exact restricted identity should outrank several incidental overlaps with an unrestricted account.
    foreach (['skiller' => 1, 'hp' => 10] as $key => $value) {
        if (isset($a['traits'][$key]) && $a['traits'][$key] == $value && (!isset($b['traits'][$key]) || $b['traits'][$key] != $value)) $score *= .35;
    }
    arsort($reasons);
    return ['score' => $score, 'reasons' => array_slice(array_keys($reasons), 0, 3)];
}

function cache_similar(PDO $db, int $player, array $stats, int $now): void {
    $before = fetch_rows($db, 'SELECT x.type, x.level, x.value, x.at FROM current c JOIN xp x ON x.player_id = c.player_id AND x.type = c.type AND x.at = COALESCE((SELECT b.at FROM xp b WHERE b.player_id = c.player_id AND b.type = c.type AND b.at <= ? ORDER BY b.at DESC LIMIT 1), (SELECT MIN(b.at) FROM xp b WHERE b.player_id = c.player_id AND b.type = c.type)) WHERE c.player_id = ?', [$now - 7 * 86400000, $player]);
    $profile = similar_profile($stats, $before, $now);
    if ($profile['coherent'] === false) error_log("Labs: inconsistent Overall totals for player $player; similarity cannot infer omitted skills or EHP shares");
    $db->prepare('INSERT INTO similarity (player_id, model, at, profile) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE model = VALUES(model), at = VALUES(at), profile = VALUES(profile)')->execute([$player, similar_model(), $now, json_encode($profile, JSON_THROW_ON_ERROR)]);
}

function rebuild_similar(PDO $db, int $now, bool $activeOnly = false): void {
    $start = microtime(true);
    $players = fetch_rows($db, 'SELECT p.id FROM players p WHERE EXISTS (SELECT 1 FROM current c WHERE c.player_id = p.id' . ($activeOnly ? ' AND c.at >= ' . ($now - 14 * 86400000) : '') . ') ORDER BY p.id');
    foreach ($players as ['id' => $id]) transaction($db, "refreshing similar players for $id", function () use ($db, $id, $now) {
        fetch_rows($db, 'SELECT id FROM players WHERE id = ? FOR UPDATE', [$id]);
        cache_similar($db, (int) $id, fetch_rows($db, 'SELECT type, level, value, at FROM current WHERE player_id = ?', [$id]), $now);
    });
    error_log('Labs: refreshed similarity for ' . count($players) . ' players, model=' . similar_model() . ', seconds=' . round(microtime(true) - $start, 2));
}

function similar_players(PDO $db, int $player, int $now): array {
    $missing = fetch_rows($db, 'SELECT p.id FROM players p LEFT JOIN similarity s ON s.player_id = p.id AND s.model = ? WHERE s.player_id IS NULL AND EXISTS (SELECT 1 FROM current c WHERE c.player_id = p.id) LIMIT 1', [similar_model()]);
    if ($missing) throw new RuntimeException('Missing similarity projection for player ' . $missing[0]['id'] . '; run php src/refresh.php --similar');
    $row = fetch_rows($db, 'SELECT profile FROM similarity WHERE player_id = ? AND model = ?', [$player, similar_model()]);
    if (!$row) throw new RuntimeException("Missing similarity target $player");
    $target = json_decode($row[0]['profile'], true, flags: JSON_THROW_ON_ERROR);
    $population = function () use ($db) {
        $q = $db->prepare('SELECT s.player_id, p.name, s.at, s.profile FROM similarity s JOIN players p ON p.id = s.player_id WHERE s.model = ?');
        $q->execute([similar_model()]);
        while ($r = $q->fetch()) yield $r + ['data' => json_decode($r['profile'], true, flags: JSON_THROW_ON_ERROR)];
    };
    $weights = similar_weights($target, (function () use ($population) {foreach ($population() as $r) yield $r['data'];})());
    $best = [];
    foreach ($population() as $r) {
        if ($r['at'] < $now - 2 * 3600000 && $r['data']['recent'] && $r['data']['active_at'] >= $now - 7 * 86400000) throw new RuntimeException('Recent similarity projection is stale; check the hourly refresh');
        if ((int) $r['player_id'] === $player) continue;
        $match = similar_score($target, $r['data'], $weights, $now);
        if ($match['score'] < 5 || !$match['reasons']) continue;
        $best[] = ['name' => $r['name']] + $match;
        usort($best, fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['name'], $b['name']));
        $best = array_slice($best, 0, 5);
    }
    return array_map(fn($r) => array_diff_key($r, ['score' => true]), $best);
}
