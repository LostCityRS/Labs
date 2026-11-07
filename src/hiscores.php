<?php
const HISCORE_BOARDS = ['level' => 'Level', 'max' => 'Road to Max', 'ehp' => 'EHP', 'xp' => 'XP'];
const HISCORE_FILTERS = ['skiller' => 'Skiller'];

function hiscore_options(array $params): array {
    $board = parameter($params, 'board', 'level');
    if (!isset(HISCORE_BOARDS[$board])) throw new InvalidArgumentException("Unknown hiscores view: $board");
    foreach (['def1', 'f2p'] as $key) if (array_key_exists($key, $params)) throw new InvalidArgumentException("Unsupported hiscores filter: $key");
    $options = ['board' => $board];
    foreach (HISCORE_FILTERS as $key => $label) {
        $value = parameter($params, $key, '0');
        if ($value !== '0' && $value !== '1') throw new InvalidArgumentException("Invalid hiscores filter: $key");
        $options[$key] = $value === '1';
    }
    return $options;
}
function combat_level(array $levels): int {
    return (int) floor(($levels[2] + $levels[4] + floor($levels[6] / 2)) / 4 + .325 * max($levels[1] + $levels[3], $levels[5] + floor($levels[5] / 2), $levels[7] + floor($levels[7] / 2)));
}
function account_restrictions(array $stats): array {
    $rows = array_column($stats, null, 'type');
    $latest = max(array_column($stats, 'at'));
    $lower = []; $upper = []; $xp = 0;
    // Missing stats are bounds, not assigned levels; coherent Overall totals can sometimes prove them.
    foreach (SKILLS as $type => $name) {
        if ($type === 0) continue;
        $r = $rows[$type] ?? null;
        $lower[$type] = $r ? (int) $r['level'] : ($type === 4 ? 10 : 1);
        $upper[$type] = $r && $r['at'] == $latest ? $lower[$type] : 99;
        $xp += $r ? (int) $r['value'] : ($type === 4 ? 11540 : 0);
    }
    $overall = $rows[0] ?? null;
    if ($overall && $overall['at'] == $latest) {
        $spareLevels = $overall['level'] - array_sum($lower);
        $spareXp = $overall['value'] - $xp;
        if ($spareLevels < 0 || $spareXp < 0) throw new RuntimeException('Overall totals contradict recorded skill stats');
        foreach ($upper as $type => &$level) $level = min($level, $lower[$type] + $spareLevels);
        unset($level);
    }
    return ['skiller' => combat_level($lower) > 3 ? false : (combat_level($upper) === 3 ? true : null)];
}
function cache_hiscores(PDO $db, int $player, array $stats): void {
    $flags = account_restrictions($stats);
    $rows = array_column($stats, null, 'type'); $maxed = null;
    $max = (count(SKILLS) - 1) * 99;
    if (isset($rows[0]) && $rows[0]['level'] == $max) {
        $maxed = fetch_rows($db, 'SELECT MIN(at) AS at FROM xp WHERE player_id = ? AND type = 0 AND level = ? AND at > (SELECT MIN(at) FROM xp WHERE player_id = ? AND type = 0 AND level < ?)', [$player, $max, $player, $max])[0]['at'];
    }
    $db->prepare('UPDATE players SET skiller = ?, maxed_at = ? WHERE id = ?')->execute([
        $flags['skiller'] === null ? null : (int) $flags['skiller'], $maxed, $player,
    ]);
}
function hiscores_rows(PDO $db, array $params, int $now): array {
    if (parameter($params, 'type', '0') !== '0') throw new InvalidArgumentException('Hiscores use overall views, not individual skills');
    $options = hiscore_options($params);
    $where = $options['board'] === 'ehp' ? '' : ' AND c.player_id IS NOT NULL';
    foreach (HISCORE_FILTERS as $key => $label) if ($options[$key]) $where .= " AND p.$key = 1";
    $max = (count(SKILLS) - 1) * 99;
    $order = match ($options['board']) {
        'level' => 'c.level DESC, c.value DESC',
        'max' => "(c.level = $max) DESC, (p.maxed_at IS NULL), p.maxed_at, (r.value IS NULL), r.value, c.level DESC",
        'ehp' => 'e.value DESC',
        'xp' => 'c.value DESC, c.level DESC',
    };
    $rows = fetch_rows($db, "SELECT p.id, p.name, c.level, c.value, c.`rank`, e.value AS ehp, e.model AS ehp_model, p.maxed_at, r.value AS to_max FROM ehp e JOIN players p ON p.id = e.player_id LEFT JOIN current c ON c.player_id = e.player_id AND c.type = 0 LEFT JOIN ehp r ON r.model = e.model AND r.player_id = e.player_id AND r.type = " . EHP_TO_MAX . " WHERE e.model = ? AND e.type = 0$where ORDER BY $order, p.name LIMIT 50", [ehp_model()['id']]);
    return hiscores_week($db, $rows, $now - 7 * 86400000);
}
// Adds each player's progress over the last 7 days; skills first seen since then start at their first observation, like weekly awards.
function hiscores_week(PDO $db, array $rows, int $since): array {
    if (!$rows) return $rows;
    $ids = array_column($rows, 'id'); $in = implode(',', array_fill(0, count($ids), '?'));
    $before = []; $now = [];
    foreach (fetch_rows($db, "SELECT x.player_id, x.type, x.level, x.value FROM xp x JOIN (SELECT player_id, type, COALESCE(MAX(CASE WHEN at <= ? THEN at END), MIN(at)) AS at FROM xp WHERE player_id IN ($in) GROUP BY player_id, type) m USING (player_id, type, at)", [$since, ...$ids]) as $r) $before[$r['player_id']][$r['type']] = $r;
    foreach (fetch_rows($db, "SELECT player_id, type, value FROM current WHERE type > 0 AND player_id IN ($in)", $ids) as $r) $now[$r['player_id']][$r['type']] = $r['value'];
    foreach ($rows as &$r) {
        $then = array_map(fn($x) => $x['value'], array_diff_key($before[$r['id']] ?? [], [0 => true]));
        $was = $before[$r['id']][0] ?? null;
        $remaining = $then ? ehp_to_max($then) : null;
        $r['week'] = [
            'level' => $was && $r['level'] !== null ? $r['level'] - $was['level'] : null,
            'xp' => $was && $r['value'] !== null ? ($r['value'] - $was['value']) / 10 : null,
            'ehp' => $then ? calculate_ehp($now[$r['id']] ?? [])[0] - calculate_ehp($then)[0] : null,
            'to_max' => $remaining !== null && $r['to_max'] !== null ? $remaining - $r['to_max'] : null,
        ];
    }
    return $rows;
}
