<?php
// Calibrated on 30 hand-rated accounts: Strength or Ranged far above Defence marked a PKer; Attack and Magic did not.
const PKER_DEFENCE_SHARE = .78;
const PKER_WILDERNESS = 5;

function pker_combat(array $params): ?int {
    if (!array_key_exists('combat', $params)) return null;
    $combat = filter_var(parameter($params, 'combat'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 3, 'max_range' => 126]]);
    return $combat === false ? throw new InvalidArgumentException('Combat level must be between 3 and 126') : $combat;
}

// Players who gained XP this week, most first.
function active_pkers(PDO $db): array {
    $players = [];
    foreach (fetch_rows($db, "SELECT g.player_id, p.name, c.type, c.level FROM gains g JOIN players p ON p.id = g.player_id JOIN current c ON c.player_id = g.player_id AND c.type BETWEEN 1 AND 7 WHERE g.period = '7d' AND g.type = 0 ORDER BY g.gain DESC, g.player_id") as $r) {
        $players[$r['player_id']]['name'] = $r['name'];
        $players[$r['player_id']]['levels'][$r['type']] = (int) $r['level'];
    }
    $pkers = [];
    foreach ($players as ['name' => $name, 'levels' => $levels]) {
        // The official hiscores omit levels below 15; an omitted level counts as untrained, as a pure's Defence is.
        $levels += [1 => 1, 2 => 1, 3 => 1, 4 => 10, 5 => 1, 6 => 1, 7 => 1];
        if ($levels[2] > PKER_DEFENCE_SHARE * max($levels[3], $levels[5])) continue;
        $pkers[] = ['name' => $name, 'levels' => $levels, 'combat' => combat_level($levels)];
    }
    return $pkers;
}
