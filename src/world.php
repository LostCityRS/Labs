<?php
const ONLINE_VIEWS = ['all' => 'Average', 'split' => 'By day type'];
const DAY_TYPES = ['weekday' => 'Weekday', 'saturday' => 'Saturday', 'sunday' => 'Sunday', 'holiday' => 'Holiday'];
const DAY_MS = 86400000;

function online_options(array $params): array {
    $day = parameter($params, 'day', 'all');
    if (!isset(ONLINE_VIEWS[$day])) throw new InvalidArgumentException("Unknown view: $day");
    $hide = parameter($params, 'hide');
    if ($hide !== '' && array_diff(explode(',', $hide), array_keys(DAY_TYPES))) throw new InvalidArgumentException("Unknown hidden day type: $hide");
    return compact('day', 'hide');
}

// Major US and European public holidays as 'm-d' => name.
function holidays(int $year): array {
    // Anonymous Gregorian algorithm for Easter Sunday.
    $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - intdiv($b, 4) - $g + 15) % 30; $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - $c % 4) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $easter = gmmktime(0, 0, 0, intdiv($h + $l - 7 * $m + 114, 31), ($h + $l - 7 * $m + 114) % 31 + 1, $year);
    $on = fn(string $rule) => gmdate('m-d', strtotime("$rule $year 00:00 UTC"));
    return ['01-01' => "New Year's Day", gmdate('m-d', $easter - 2 * 86400) => 'Good Friday', gmdate('m-d', $easter + 86400) => 'Easter Monday', '05-01' => 'May Day', $on('last monday of may') => 'Memorial Day (US)', '07-04' => 'Independence Day (US)', $on('first monday of september') => 'Labor Day (US)', $on('fourth thursday of november') => 'Thanksgiving (US)', '12-24' => 'Christmas Eve', '12-25' => 'Christmas Day', '12-26' => 'Boxing Day', '12-31' => "New Year's Eve"];
}
function day_type(int $day): string {
    if (isset(holidays((int) gmdate('Y', intdiv($day, 1000)))[gmdate('m-d', intdiv($day, 1000))])) return 'holiday';
    return ['6' => 'saturday', '0' => 'sunday'][gmdate('w', intdiv($day, 1000))] ?? 'weekday';
}
// Average players online per UTC hour, overall and per day type; 'days' counts the distinct days behind each hour.
function online_by_hour(PDO $db): array {
    $hours = [];
    foreach (fetch_rows($db, 'SELECT FLOOR(at / 86400000) * 86400000 AS day, FLOOR(MOD(at, 86400000) / 3600000) AS hour, SUM(players) AS players, COUNT(*) AS samples FROM online GROUP BY day, hour') as $r) {
        foreach (['all', day_type((int) $r['day'])] as $type) {
            $h = &$hours[$type][(int) $r['hour']];
            $h['players'] = ($h['players'] ?? 0) + $r['players']; $h['samples'] = ($h['samples'] ?? 0) + $r['samples']; $h['days'] = ($h['days'] ?? 0) + 1;
            unset($h);
        }
    }
    return array_map(function ($byHour) {ksort($byHour); return array_map(fn($h) => ['value' => $h['players'] / $h['samples'], 'days' => $h['days']], $byHour);}, $hours);
}

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

function world_stats(PDO $db, int $now): array {
    $today = intdiv($now, DAY_MS) * DAY_MS;
    $first = (int) ($db->query('SELECT MIN(at) FROM xp')->fetchColumn() ?: throw new RuntimeException('No XP history yet'));
    $live = xp_gained($db, $today, $now + 1);
    $days = fetch_rows($db, 'SELECT day AS at, gain AS value FROM world_xp WHERE type = 0 ORDER BY day');
    $missing = (int) (($today - intdiv($first, DAY_MS) * DAY_MS) / DAY_MS) - count($days);
    if ($missing > 0) throw new RuntimeException("$missing finished days have no frozen world XP; run refresh");
    $week = $live;
    foreach (fetch_rows($db, 'SELECT type, SUM(gain) AS gain FROM world_xp WHERE type > 0 AND day >= ? GROUP BY type', [$today - 6 * DAY_MS]) as $r) $week[$r['type']] = ($week[$r['type']] ?? 0) + $r['gain'];
    $totals = array_map('floatval', array_column(fetch_rows($db, 'SELECT SUM(value) / 10 AS xp FROM current WHERE type > 0 GROUP BY player_id ORDER BY xp DESC'), 'xp'));
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
        'online' => online_by_hour($db),
    ];
}
