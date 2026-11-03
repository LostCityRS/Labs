<?php
// Run from cron every few minutes: imports hiscore changes from the game website, then refreshes period gains.
require __DIR__ . '/db.php';
ini_set('user_agent', 'LostCityLabs (+https://labs.lostcity.rs)'); // Cloudflare rejects requests without one
const PAGE = 10000; // must match the game endpoint's row limit
$api = $env('LABS_GAME_API');
$now = (int) (microtime(true) * 1000);
$player = $db->prepare('INSERT INTO players (name, first_seen) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
$insert = $db->prepare('INSERT IGNORE INTO xp VALUES (?, ?, ?, ?, ?)');
$previous = $db->prepare('SELECT level, value FROM xp WHERE player_id = ? AND type = ? AND at < ? ORDER BY at DESC LIMIT 1');
$levelUp = $db->prepare('INSERT INTO levels VALUES (?, ?, ?, ?, ?)');
$current = $db->prepare('INSERT INTO current VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE level = IF(VALUES(at) >= at, VALUES(level), level), value = IF(VALUES(at) >= at, VALUES(value), value), at = GREATEST(at, VALUES(at))');
$windowStart = $db->prepare('SELECT value, at FROM xp WHERE player_id = ? AND type = ? AND at >= ? AND at < ? ORDER BY at LIMIT 1');
$record = $db->prepare('INSERT INTO records VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE start_at = IF(VALUES(gain) > gain, VALUES(start_at), start_at), end_at = IF(VALUES(gain) > gain, VALUES(end_at), end_at), gain = GREATEST(gain, VALUES(gain))');
$imported = 0;
do {
    $since = (int) $db->query('SELECT COALESCE(MAX(at), 0) FROM xp')->fetchColumn();
    $url = "$api/api/hiscores/updated?since=$since";
    $rows = json_decode(file_get_contents($url) ?: throw new RuntimeException("Fetching $url failed"), true, flags: JSON_THROW_ON_ERROR);
    $db->beginTransaction();
    foreach ($rows as ['username' => $name, 'type' => $type, 'level' => $level, 'value' => $value, 'date' => $date]) {
        $at = (int) (new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('Uv');
        $player->execute([$name, $now]);
        $id = (int) $db->lastInsertId();
        $insert->execute([$id, $type, $at, $level, $value]);
        if ($insert->rowCount() === 0) continue; // `since` is inclusive, so the boundary rows repeat
        $imported++;
        $previous->execute([$id, $type, $at]);
        $prev = $previous->fetch();
        if ($prev && $value < $prev['value']) throw new RuntimeException("XP decreased for $name type $type at $at: {$prev['value']} -> $value");
        if ($prev && $type > 0 && $level > $prev['level']) $levelUp->execute([$id, $type, $level, $prev['level'], $at]);
        $current->execute([$id, $type, $at, $level, $value]);
        // Only windows ending at this row are checked, so history must be imported oldest first.
        foreach (PERIODS as $period => $ms) {
            $windowStart->execute([$id, $type, $at - $ms, $at]);
            $start = $windowStart->fetch();
            if ($start && $value > $start['value']) $record->execute([$period, $type, $id, $value - $start['value'], $start['at'], $at]);
        }
    }
    $db->commit();
    $next = (int) $db->query('SELECT COALESCE(MAX(at), 0) FROM xp')->fetchColumn();
    if (count($rows) >= PAGE && $next === $since) throw new RuntimeException("More than " . PAGE . " rows share date $since; raise the endpoint limit");
} while (count($rows) >= PAGE);

$db->beginTransaction();
$db->exec('DELETE FROM gains');
$gains = $db->prepare('INSERT INTO gains SELECT ?, c.type, c.player_id, c.value - COALESCE((SELECT x.value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at <= ? ORDER BY x.at DESC LIMIT 1), (SELECT MIN(x.value) FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type)) AS gain FROM current c WHERE c.at > ? HAVING gain > 0');
foreach (PERIODS as $period => $ms) $gains->execute([$period, $now - $ms, $now - $ms]);
$db->commit();
echo date('c'), " imported $imported rows, cursor $next\n";
