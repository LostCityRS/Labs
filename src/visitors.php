<?php
// The deployment day can contain both filtered and unfiltered visits.
const VISITOR_FILTERED_FROM = 1795651200000;

function visitor_routes(PDO $db, string $path, int $now): bool {
    if ($path !== '/hit') return false;
    header('Cache-Control: no-store');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {header('Allow: POST'); http_response_code(405); return true;}
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== auth_origin('LABS_SITE_ORIGIN')) throw new AuthorizationException('Visitor ping must come from this site.');
    if (preg_match('~bot\b|\b(?:crawler|spider|headlesschrome|googleother|google-inspectiontool|bingpreview|facebookexternalhit)\b|^(?:curl/|python-requests/|scrapy/)~i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        header('X-Labs-Visit: crawler');
        http_response_code(200);
        return true;
    }
    record_visit($db, parameter($_POST, 'visitor'), $now);
    http_response_code(204);
    return true;
}

function record_visit(PDO $db, string $visitor, int $now): void {
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $visitor)) throw new InvalidArgumentException('Invalid visitor ID');
    $db->prepare('INSERT INTO visits (day, visitor) VALUES (?, UNHEX(?)) ON DUPLICATE KEY UPDATE day = day')->execute([intdiv($now, DAY_MS) * DAY_MS, str_replace('-', '', $visitor)]);
}

function visitor_daily(PDO $db, int $from, int $through, bool $legacy = false): array {
    if ($from > $through) return [];
    $rows = array_column(fetch_rows($db, 'SELECT day, COUNT(*) AS n FROM visits WHERE day >= ? AND day <= ? GROUP BY day ORDER BY day', [$from, $through]), 'n', 'day');
    if ($legacy) {
        if (!$rows) return [];
        $from = (int) array_key_first($rows);
    }
    $daily = [];
    for ($day = $from; $day <= $through; $day += DAY_MS) $daily[] = ['at' => $day, 'value' => (int) ($rows[$day] ?? 0)];
    return $daily;
}

function visitor_stats(PDO $db, int $now): array {
    $today = intdiv($now, DAY_MS) * DAY_MS;
    $start = VISITOR_FILTERED_FROM;
    $trackedDays = min(30, max(0, intdiv($today - $start, DAY_MS) + 1));
    $counts = [1 => 0, 7 => 0, 30 => 0];
    if ($trackedDays) {
        foreach ($counts as $days => $_) $counts[$days] = (int) fetch_rows($db, 'SELECT COUNT(DISTINCT visitor) AS n FROM visits WHERE day >= ? AND day <= ?', [max($start, $today - ($days - 1) * DAY_MS), $today])[0]['n'];
    }
    $returning = null;
    if ($today >= $start + 14 * DAY_MS && $counts[7] > 0) {
        $both = fetch_rows($db, 'SELECT COUNT(*) AS n FROM (SELECT visitor FROM visits WHERE day >= ? AND day <= ? GROUP BY visitor HAVING MIN(day) < ? AND MAX(day) >= ?) v', [$today - 13 * DAY_MS, $today, $today - 6 * DAY_MS, $today - 6 * DAY_MS])[0]['n'];
        $returning = ['count' => (int) $both, 'percent' => $both / $counts[7] * 100];
    }
    $daily = visitor_daily($db, max($start, $today - 29 * DAY_MS), $today);
    $legacy = visitor_daily($db, $start - 30 * DAY_MS, min($today, $start - DAY_MS), true);
    return ['started' => $trackedDays ? $start : null] + compact('trackedDays', 'counts', 'returning', 'daily', 'legacy');
}
