<?php
// Cron, every 5 minutes.
require __DIR__ . '/lib.php';
transaction($db, 'refreshing gains', fn() => refresh_gains($db, $now));
// The game website has no player count API; its title page shows the total across worlds.
$title = file_get_contents('https://2004.lostcity.rs/title') ?: throw new RuntimeException('Fetching the player count failed');
preg_match('/currently ([\d,]+) people playing/', $title, $match) ?: throw new RuntimeException('Player count not found on /title');
$db->prepare('INSERT INTO online VALUES (?, ?)')->execute([$now, (int) str_replace(',', '', $match[1])]);
