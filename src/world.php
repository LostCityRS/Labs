<?php
const WEEKDAYS = ['all' => 'All days', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
const DAY_MS = 86400000;

function weekday(int $at): string {return array_keys(WEEKDAYS)[1 + (intdiv($at, DAY_MS) + 3) % 7];} // 1 Jan 1970 was a Thursday

// XP gained per skill for xp rows in [$from, $to); a skill's first observation has no previous value and adds nothing.
function xp_gained(PDO $db, int $from, int $to): array {
    return array_map('floatval', array_column(fetch_rows($db, 'SELECT x.type, SUM(x.value - (SELECT p.value FROM xp p WHERE p.player_id = x.player_id AND p.type = x.type AND p.at < x.at ORDER BY p.at DESC LIMIT 1)) / 10 AS gain FROM xp x WHERE x.type > 0 AND x.at >= ? AND x.at < ? GROUP BY x.type', [$from, $to]), 'gain', 'type'));
}

// Freezes each finished UTC day once; type 0 holds the day's total and marks the day as done.
function refresh_world_xp(PDO $db, int $now): void {
    $first = $db->query('SELECT MIN(at) FROM xp')->fetchColumn();
    if ($first === null) return;
    // Same grace as weekly awards: let pre-midnight imports commit first.
    for ($day = intdiv((int) $first, DAY_MS) * DAY_MS; $day + DAY_MS + 300000 <= $now; $day += DAY_MS) {
        if (fetch_rows($db, 'SELECT day FROM world_xp WHERE day = ? AND type = 0', [$day])) continue;
        $gains = xp_gained($db, $day, $day + DAY_MS);
        transaction($db, "freezing world XP for $day", function () use ($db, $day, $gains) {
            $insert = $db->prepare('INSERT INTO world_xp (day, type, gain) VALUES (?, ?, ?)');
            foreach ([0 => array_sum($gains)] + $gains as $type => $gain) $insert->execute([$day, $type, $gain]);
        });
        error_log("Labs: froze world XP for day $day: " . array_sum($gains));
    }
}

// XP per skill for the 7 days before the current 7-day window, or null until all of them are frozen.
function previous_week(PDO $db, int $today): ?array {
    $from = $today - 13 * DAY_MS; $to = $today - 6 * DAY_MS;
    if ((int) fetch_rows($db, 'SELECT COUNT(*) AS n FROM world_xp WHERE type = 0 AND day >= ? AND day < ?', [$from, $to])[0]['n'] !== 7) return null;
    return array_map('floatval', array_column(fetch_rows($db, 'SELECT type, SUM(gain) AS gain FROM world_xp WHERE type > 0 AND day >= ? AND day < ? GROUP BY type', [$from, $to]), 'gain', 'type'));
}

function world_stats(PDO $db, int $now, string $weekday): array {
    $today = intdiv($now, DAY_MS) * DAY_MS;
    $first = (int) ($db->query('SELECT MIN(at) FROM xp')->fetchColumn() ?: throw new RuntimeException('No XP history yet'));
    $live = xp_gained($db, $today, $now + 1);
    $days = fetch_rows($db, 'SELECT day AS at, gain AS value FROM world_xp WHERE type = 0 ORDER BY day');
    $missing = (int) (($today - intdiv($first, DAY_MS) * DAY_MS) / DAY_MS) - count($days);
    if ($missing > 0) throw new RuntimeException("$missing finished days have no frozen world XP; run refresh");
    $week = $live;
    foreach (fetch_rows($db, 'SELECT type, SUM(gain) AS gain FROM world_xp WHERE type > 0 AND day >= ? GROUP BY type', [$today - 6 * DAY_MS]) as $r) $week[$r['type']] = ($week[$r['type']] ?? 0) + $r['gain'];
    $totals = array_map('floatval', array_column(fetch_rows($db, 'SELECT SUM(value) / 10 AS xp FROM current WHERE type > 0 GROUP BY player_id ORDER BY xp DESC'), 'xp'));
    $online = fetch_rows($db, 'SELECT FLOOR(MOD(at, 86400000) / 3600000) AS hour, AVG(players) AS value, COUNT(DISTINCT FLOOR(at / 86400000)) AS days FROM online' . ($weekday === 'all' ? '' : ' WHERE MOD(FLOOR(at / 86400000) + 3, 7) = ?') . ' GROUP BY hour ORDER BY hour', $weekday === 'all' ? [] : [array_search($weekday, array_keys(WEEKDAYS)) - 1]);
    return [
        'first' => $first,
        'today' => array_sum($live),
        'week' => array_sum($week),
        // The first day started mid-day, so it would read as a slump.
        'daily' => array_values(array_filter($days, fn($d) => $d['at'] > $first)),
        'skills' => $week,
        'players' => count($totals),
        'totals' => $totals,
        'nineties' => array_map('intval', array_column(fetch_rows($db, 'SELECT type, COUNT(*) AS n FROM current WHERE type > 0 AND level >= 99 GROUP BY type'), 'n', 'type')),
        'new99s' => array_map(fn($days) => array_map('intval', array_column(fetch_rows($db, 'SELECT type, COUNT(*) AS n FROM levels WHERE level = 99 AND at >= ? GROUP BY type', [$now - $days * DAY_MS]), 'n', 'type')), [7 => 7, 30 => 30]),
        'previous' => previous_week($db, $today),
        'maxed' => (int) $db->query('SELECT COUNT(*) FROM current WHERE type = 0 AND level >= ' . ((count(SKILLS) - 1) * 99))->fetchColumn(),
        'online' => $online,
    ];
}
