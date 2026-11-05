<?php
$env = fn(string $key) => getenv($key) ?: throw new RuntimeException("$key is not set");
$db = new PDO($env('LABS_DB_DSN'), $env('LABS_DB_USER'), getenv('LABS_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
const PERIODS = ['24h' => 86400000, '7d' => 604800000, '30d' => 2592000000];
const RECORDS = ['6h' => 21600000, '24h' => 86400000];
$now = (int) (microtime(true) * 1000);

function safe_name(string $name): string {
    $name = strtolower(str_replace(' ', '_', trim($name)));
    return preg_match('/^[a-z0-9_]{1,12}$/', $name) ? $name : throw new InvalidArgumentException("Invalid username: $name");
}

// $stats: rows as returned by /api/hiscores/player/:username, observed at $at (unix ms).
// Records only check windows ending at $at, so history must be imported oldest first.
function import_stats(PDO $db, string $name, array $stats, int $at): int {
    $previous = $db->prepare('SELECT level, value FROM xp WHERE player_id = ? AND type = ? AND at < ? ORDER BY at DESC LIMIT 1');
    $insert = $db->prepare('INSERT IGNORE INTO xp VALUES (?, ?, ?, ?, ?)');
    $levelUp = $db->prepare('INSERT INTO levels VALUES (?, ?, ?, ?, ?)');
    $current = $db->prepare('INSERT INTO current VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE level = IF(VALUES(at) >= at, VALUES(level), level), value = IF(VALUES(at) >= at, VALUES(value), value), `rank` = IF(VALUES(at) >= at, VALUES(`rank`), `rank`), at = GREATEST(at, VALUES(at))');
    $windowStart = $db->prepare('SELECT value, at FROM xp WHERE player_id = ? AND type = ? AND at >= ? AND at < ? ORDER BY at LIMIT 1');
    $record = $db->prepare('INSERT INTO records VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE start_at = IF(VALUES(gain) > gain, VALUES(start_at), start_at), end_at = IF(VALUES(gain) > gain, VALUES(end_at), end_at), gain = GREATEST(gain, VALUES(gain))');
    $imported = 0;
    $db->beginTransaction();
    $db->prepare('INSERT INTO players (name, first_seen) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)')->execute([$name, $at]);
    $id = (int) $db->lastInsertId();
    foreach ($stats as ['type' => $type, 'level' => $level, 'value' => $value, 'rank' => $rank]) {
        $current->execute([$id, $type, $at, $level, $value, $rank]);
        $previous->execute([$id, $type, $at]);
        $prev = $previous->fetch();
        if ($prev && $value == $prev['value']) continue; // every logout resends unchanged skills
        if ($prev && $value < $prev['value']) throw new RuntimeException("XP decreased for $name type $type at $at: {$prev['value']} -> $value");
        $insert->execute([$id, $type, $at, $level, $value]);
        if ($insert->rowCount() === 0) continue; // already imported
        $imported++;
        if ($prev && $type > 0 && $level > $prev['level']) $levelUp->execute([$id, $type, $level, $prev['level'], $at]);
        foreach (RECORDS as $period => $ms) {
            $windowStart->execute([$id, $type, $at - $ms, $at]);
            $start = $windowStart->fetch();
            if ($start && $value > $start['value']) $record->execute([$period, $type, $id, $value - $start['value'], $start['at'], $at]);
        }
    }
    refresh_gains($db, $at, $id);
    $db->commit();
    return $imported;
}

// Gains change as time passes, so cron refreshes everyone; a new logout refreshes just that player.
function refresh_gains(PDO $db, int $now, ?int $player = null): void {
    $only = $player === null ? '' : ' AND c.player_id = ' . $player;
    $db->exec('DELETE c FROM gains c WHERE 1' . $only);
    $gains = $db->prepare('INSERT INTO gains SELECT ?, c.type, c.player_id, c.value - COALESCE((SELECT x.value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at <= ? ORDER BY x.at DESC LIMIT 1), (SELECT MIN(x.value) FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type)) AS gain FROM current c WHERE c.at > ?' . $only . ' HAVING gain > 0');
    foreach (PERIODS as $period => $ms) $gains->execute([$period, $now - $ms, $now - $ms]);
}
