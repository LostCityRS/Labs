<?php
// Cron, every 5 minutes, including 00:00 UTC.
require __DIR__ . '/lib.php';
if (($argv[1] ?? null) === '--ehp') {rebuild_ehp($db); exit;}
// A count-site outage must not stop XP gains and weekly awards from refreshing.
try {
    refresh_players($db, $now, function () use ($now) {
        // Cloudflare otherwise reuses an old reading across sampling runs.
        $title = file_get_contents("https://2004.lostcity.rs/title?t=$now", false, stream_context_create(['http' => ['timeout' => 10]]));
        if ($title === false) throw new RuntimeException('Fetching the online player count failed');
        preg_match('/currently ([\d,]+) people playing/', $title, $match) ?: throw new RuntimeException('Player count not found on /title');
        return (int) str_replace(',', '', $match[1]);
    });
} finally {
    transaction($db, 'refreshing gains', fn() => refresh_gains($db, $now));
    refresh_ehp_weeks($db, $now);
    // Hourly is plenty for forum-posted videos and keeps us polite to the forum and YouTube.
    if (intdiv($now, 300000) % 12 === 0) refresh_clans($db);
}
