<?php
const HISCORE_BOARDS = ['level' => 'Level', 'max' => 'Road to Max', 'ehp' => 'EHP', 'xp' => 'XP'];
const HISCORE_FILTERS = ['def1' => '1 Def', 'skiller' => 'Skiller', 'f2p' => 'F2P'];
const MEMBER_SKILLS = [10, 16, 17, 18];

function hiscore_options(array $params): array {
    $board = parameter($params, 'board', 'level');
    if (!isset(HISCORE_BOARDS[$board])) throw new InvalidArgumentException("Unknown hiscores view: $board");
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
    $overall = $rows[0] ?? null; $spareXp = null;
    if ($overall && $overall['at'] == $latest) {
        $spareLevels = $overall['level'] - array_sum($lower);
        $spareXp = $overall['value'] - $xp;
        if ($spareLevels < 0 || $spareXp < 0) throw new RuntimeException('Overall totals contradict recorded skill stats');
        foreach ($upper as $type => &$level) $level = min($level, $lower[$type] + $spareLevels);
        unset($level);
    }
    $def1 = $lower[2] > 1 ? false : ($upper[2] === 1 ? true : null);
    $skiller = combat_level($lower) > 3 ? false : (combat_level($upper) === 3 ? true : null);
    $f2p = true;
    foreach (MEMBER_SKILLS as $type) {
        $r = $rows[$type] ?? null;
        if ($r && $r['value'] > 0) {$f2p = false; break;}
        if ((!$r || $r['at'] != $latest) && $spareXp !== 0) $f2p = null;
    }
    return compact('def1', 'skiller', 'f2p');
}
function cache_hiscores(PDO $db, int $player, array $stats): void {
    $flags = account_restrictions($stats);
    $rows = array_column($stats, null, 'type'); $maxed = null;
    $max = (count(SKILLS) - 1) * 99;
    if (isset($rows[0]) && $rows[0]['level'] == $max) {
        $maxed = fetch_rows($db, 'SELECT MIN(at) AS at FROM xp WHERE player_id = ? AND type = 0 AND level = ? AND at > (SELECT MIN(at) FROM xp WHERE player_id = ? AND type = 0 AND level < ?)', [$player, $max, $player, $max])[0]['at'];
    }
    $db->prepare('UPDATE players SET def1 = ?, skiller = ?, f2p = ?, maxed_at = ? WHERE id = ?')->execute([
        $flags['def1'] === null ? null : (int) $flags['def1'], $flags['skiller'] === null ? null : (int) $flags['skiller'], $flags['f2p'] === null ? null : (int) $flags['f2p'], $maxed, $player,
    ]);
}
function hiscores_rows(PDO $db, array $params): array {
    if (parameter($params, 'type', '0') !== '0') throw new InvalidArgumentException('Hiscores use overall views, not individual skills');
    $options = hiscore_options($params);
    $where = $options['board'] === 'ehp' ? '' : ' AND c.player_id IS NOT NULL';
    foreach (HISCORE_FILTERS as $key => $label) if ($options[$key]) $where .= " AND p.$key = 1";
    $max = (count(SKILLS) - 1) * 99;
    $order = match ($options['board']) {
        'level' => 'c.level DESC, c.value DESC',
        'max' => "(c.level = $max) DESC, (p.maxed_at IS NULL), p.maxed_at, c.level DESC, c.value DESC",
        'ehp' => 'e.value DESC',
        'xp' => 'c.value DESC, c.level DESC',
    };
    return fetch_rows($db, "SELECT p.name, c.level, c.value, c.`rank`, e.value AS ehp, e.model AS ehp_model, p.maxed_at FROM ehp e JOIN players p ON p.id = e.player_id LEFT JOIN current c ON c.player_id = e.player_id AND c.type = 0 WHERE e.model = ? AND e.type = 0$where ORDER BY $order, p.name LIMIT 50", [ehp_model()['id']]);
}
