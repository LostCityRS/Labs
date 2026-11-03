<?php
// Cron, every 5 minutes: period gains change as time passes even when nobody logs out.
require __DIR__ . '/lib.php';
$db->beginTransaction();
$db->exec('DELETE FROM gains');
$gains = $db->prepare('INSERT INTO gains SELECT ?, c.type, c.player_id, c.value - COALESCE((SELECT x.value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at <= ? ORDER BY x.at DESC LIMIT 1), (SELECT MIN(x.value) FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type)) AS gain FROM current c WHERE c.at > ? HAVING gain > 0');
foreach (PERIODS as $period => $ms) $gains->execute([$period, $now - $ms, $now - $ms]);
$db->commit();
