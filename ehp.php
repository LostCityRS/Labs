<?php
function ehp_model(): array {
    static $model;
    if ($model !== null) return $model;
    $raw = file_get_contents(__DIR__ . '/rates.json');
    $definition = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    if (!isset($definition['version'], $definition['skills']) || !is_string($definition['version']) || !is_array($definition['skills'])) throw new RuntimeException('Invalid EHP model definition');
    $xp = [1 => 0]; $sum = 0;
    for ($level = 1; $level < 99; $level++) {$sum += floor($level + 300 * 2 ** ($level / 7)); $xp[$level + 1] = (int) floor($sum / 4);}
    $curves = [];
    foreach (SKILLS as $type => $name) {
        if ($type === 0) continue;
        $rows = $definition['skills'][$name] ?? throw new RuntimeException("Missing EHP rates for $name");
        if ($type === 4) {if ($rows) throw new RuntimeException('Hitpoints must be explicitly passive'); continue;}
        $next = 1;
        foreach ($rows as $r) {
            if ($r['min_level'] !== $next || $r['max_level'] <= $next || $r['max_level'] > 99 || !is_numeric($r['rate']) || !is_finite((float) $r['rate']) || $r['rate'] <= 0) throw new RuntimeException("Invalid, missing or overlapping EHP bracket: $name at level $next");
            if (!is_array($r['bonus_skills'] ?? null)) throw new RuntimeException("Missing bonus rules for $name");
            $bonuses = [];
            foreach ($r['bonus_skills'] as $b) {
                $target = array_search($b['skill'], SKILLS, true);
                if ($target === false || $target === 0 || $target === $type || isset($bonuses[$target]) || !is_numeric($b['rate']) || !is_finite((float) $b['rate']) || $b['rate'] < 0) throw new RuntimeException("Invalid bonus XP in $name");
                $bonuses[$target] = $b['rate'];
            }
            $curves[$type][] = ['from' => $xp[$next], 'to' => $r['max_level'] === 99 ? INF : $xp[$r['max_level']], 'rate' => $r['rate'], 'bonuses' => $bonuses];
            $next = $r['max_level'];
        }
        if ($next !== 99) throw new RuntimeException("Incomplete EHP rates: $name");
    }
    return $model = ['id' => $definition['version'] . ':' . substr(hash('sha256', $raw), 0, 16), 'curves' => $curves];
}
function ehp_time(int $type, float $xp): float {
    if ($type === 4) return 0;
    if (!is_finite($xp) || $xp < 0) throw new RuntimeException('Invalid XP for EHP curve');
    $curve = ehp_model()['curves'][$type] ?? throw new RuntimeException("No EHP curve for type $type");
    $time = 0;
    foreach ($curve as $r) $time += max(0, min($xp, $r['to']) - $r['from']) / $r['rate'];
    return $time;
}
// Input is recorded xp10 by skill; missing skills remain unknown, not fabricated as zero XP.
function calculate_ehp(array $xp10): array {
    $model = ehp_model(); $scores = []; $bonus = [];
    foreach ($xp10 as $type => $value) {
        if (!isset(SKILLS[$type]) || !is_numeric($value) || !is_finite((float) $value) || $value < 0) throw new RuntimeException('Invalid XP for EHP calculation');
        if ($type === 0) continue;
        $xp = $value / 10;
        $scores[$type] = ehp_time($type, $xp);
        if ($type === 4) continue;
        foreach ($model['curves'][$type] as $r) {
            $hours = max(0, min($xp, $r['to']) - $r['from']) / $r['rate'];
            foreach ($r['bonuses'] as $target => $rate) $bonus[$target] = ($bonus[$target] ?? 0) + $hours * $rate;
        }
    }
    foreach ($bonus as $type => $xp) if (isset($scores[$type])) $scores[$type] -= ehp_time($type, min($xp10[$type] / 10, $xp));
    $scores[0] = array_sum($scores);
    return $scores;
}
function cache_ehp(PDO $db, int $player): void {
    $stats = fetch_rows($db, 'SELECT type, value FROM current WHERE player_id = ?', [$player]);
    $xp = array_column($stats, 'value', 'type');
    if (!isset($xp[0])) throw new RuntimeException("Cannot project player $player without Overall stats");
    $scores = calculate_ehp($xp);
    $query = $db->prepare('INSERT INTO ehp (model, player_id, type, value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    ksort($scores);
    foreach ($scores as $type => $value) $query->execute([ehp_model()['id'], $player, $type, $value]);
}
function ehp_scores(PDO $db, int $player): array {
    $rows = fetch_rows($db, 'SELECT type, value FROM ehp WHERE model = ? AND player_id = ?', [ehp_model()['id'], $player]);
    $scores = array_map('floatval', array_column($rows, 'value', 'type'));
    if (!isset($scores[0])) throw new RuntimeException("Missing EHP projection for player $player; run php refresh.php --ehp");
    return $scores;
}
function rebuild_ehp(PDO $db): void {
    $start = microtime(true); $players = fetch_rows($db, 'SELECT id FROM players ORDER BY id');
    foreach ($players as ['id' => $id]) transaction($db, "rebuilding EHP for $id", function () use ($db, $id) {
        // Imports lock this row too, so a rebuild cannot overwrite a newer callback's score.
        fetch_rows($db, 'SELECT id FROM players WHERE id = ? FOR UPDATE', [$id]);
        cache_ehp($db, $id);
    });
    $missing = fetch_rows($db, 'SELECT p.id FROM players p LEFT JOIN ehp e ON e.player_id = p.id AND e.type = 0 AND e.model = ? WHERE e.player_id IS NULL LIMIT 1', [ehp_model()['id']]);
    if ($missing) throw new RuntimeException('EHP rebuild is incomplete');
    echo 'Rebuilt ' . count($players) . ' players for ' . ehp_model()['id'] . ' in ' . round(microtime(true) - $start, 2) . "s\n";
}
function week_start(int $at): int {
    return (int) strtotime('monday this week 00:00:00 UTC', (int) ($at / 1000)) * 1000;
}
function refresh_ehp_weeks(PDO $db, int $now): void {
    $first = $db->query('SELECT MIN(at) FROM xp')->fetchColumn();
    if ($first === null) return;
    $boundary = week_start($now);
    // Let in-flight pre-midnight imports commit before freezing the completed week's award.
    if ($now - $boundary < 300000) return;
    for ($week = week_start((int) $first); $week + 604800000 <= $boundary; $week += 604800000) {
        if (fetch_rows($db, 'SELECT week_at FROM ehp_weeks WHERE week_at = ?', [$week])) continue;
        $end = $week + 604800000;
        // New skills/players begin at their first observation, never their lifetime XP from zero.
        $query = $db->prepare('SELECT c.player_id, c.type,
            (SELECT value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at < ? ORDER BY at DESC LIMIT 1) AS end_value,
            COALESCE((SELECT value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at <= ? ORDER BY at DESC LIMIT 1),
                     (SELECT value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at < ? ORDER BY at LIMIT 1)) AS start_value
            FROM current c JOIN players p ON p.id = c.player_id WHERE p.first_seen < ? ORDER BY c.player_id, c.type');
        $query->execute([$end, $week, $end, $end]);
        $winner = null; $best = 0; $id = null; $before = []; $after = [];
        // Keep one player's boundary states in memory, not the entire population's history.
        $finish = function () use (&$id, &$before, &$after, &$winner, &$best, $week) {
            if ($id === null) return;
            $gain = calculate_ehp($after)[0] - calculate_ehp($before)[0];
            if ($gain < -.000001) throw new RuntimeException("EHP decreased for player $id in week $week");
            if ($gain > $best) {$winner = $id; $best = $gain;}
        };
        foreach ($query as $r) {
            if ($r['end_value'] === null) continue;
            if ($id !== $r['player_id']) {$finish(); $id = $r['player_id']; $before = []; $after = [];}
            $before[$r['type']] = $r['start_value']; $after[$r['type']] = $r['end_value'];
        }
        $finish();
        $insert = $db->prepare('INSERT INTO ehp_weeks (week_at, start_at, end_at, player_id, gain, model) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE week_at = VALUES(week_at)');
        $insert->execute([$week, max($week, (int) $first), $end, $winner, $best, ehp_model()['id']]);
        if ($insert->rowCount()) error_log("Labs: finalized EHP week $week, winner=" . ($winner === null ? 'none' : $winner) . ", gain=$best, model=" . ehp_model()['id']);
    }
}
function ehp_winners(PDO $db): array {
    $rows = fetch_rows($db, 'SELECT p.name, w.start_at, w.end_at, w.gain AS ehp, w.model FROM ehp_weeks w JOIN players p ON p.id = w.player_id ORDER BY week_at DESC LIMIT 4');
    foreach ($rows as &$r) $r['week'] = gmdate('j M', (int) ($r['start_at'] / 1000)) . ' – ' . gmdate('j M', (int) (($r['end_at'] - 1) / 1000));
    return $rows;
}
