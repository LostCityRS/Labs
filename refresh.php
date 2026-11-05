<?php
// Cron, every 5 minutes, including 00:00 UTC.
require __DIR__ . '/lib.php';
if (($argv[1] ?? null) === '--ehp') {rebuild_ehp($db); exit;}
refresh_players($db, $now, function () use ($now) {
    // Bypass cached pages so the midnight reading reaches the game website.
    $title = file_get_contents("https://2004.lostcity.rs/title?t=$now", false, stream_context_create(['http' => ['timeout' => 10]]));
    if ($title === false) throw new RuntimeException('Fetching the midnight player count failed');
    preg_match('/currently ([\d,]+) people playing/', $title, $match) ?: throw new RuntimeException('Player count not found on /title');
    return (int) str_replace(',', '', $match[1]);
});
transaction($db, 'refreshing gains', fn() => refresh_gains($db, $now));
refresh_ehp_weeks($db, $now);
