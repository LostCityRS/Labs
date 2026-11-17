<?php
function visitor_routes(PDO $db, string $path, int $now): bool {
    if ($path !== '/hit') return false;
    header('Cache-Control: no-store');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {header('Allow: POST'); http_response_code(405); return true;}
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== auth_origin('LABS_SITE_ORIGIN')) throw new AuthorizationException('Visitor ping must come from this site.');
    record_visit($db, parameter($_POST, 'visitor'), $now);
    http_response_code(204);
    return true;
}

function record_visit(PDO $db, string $visitor, int $now): void {
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $visitor)) throw new InvalidArgumentException('Invalid visitor ID');
    $db->prepare('INSERT INTO visits (day, visitor) VALUES (?, UNHEX(?)) ON DUPLICATE KEY UPDATE day = day')->execute([intdiv($now, DAY_MS) * DAY_MS, str_replace('-', '', $visitor)]);
}

function visitor_stats(PDO $db, int $now): array {
    $today = intdiv($now, DAY_MS) * DAY_MS;
    $started = $db->query('SELECT MIN(day) FROM visits')->fetchColumn();
    $counts = [];
    foreach ([1, 7, 30] as $days) $counts[$days] = (int) fetch_rows($db, 'SELECT COUNT(DISTINCT visitor) AS n FROM visits WHERE day >= ? AND day <= ?', [$today - ($days - 1) * DAY_MS, $today])[0]['n'];
    $returning = null;
    if ($started !== null && $started <= $today - 13 * DAY_MS && $counts[7] > 0) {
        $both = fetch_rows($db, 'SELECT COUNT(*) AS n FROM (SELECT visitor FROM visits WHERE day >= ? AND day <= ? GROUP BY visitor HAVING MIN(day) < ? AND MAX(day) >= ?) v', [$today - 13 * DAY_MS, $today, $today - 6 * DAY_MS, $today - 6 * DAY_MS])[0]['n'];
        $returning = ['count' => (int) $both, 'percent' => $both / $counts[7] * 100];
    }
    $daily = [];
    if ($started !== null) {
        $rows = array_column(fetch_rows($db, 'SELECT day, COUNT(*) AS n FROM visits WHERE day >= ? AND day <= ? GROUP BY day ORDER BY day', [$today - 29 * DAY_MS, $today]), 'n', 'day');
        for ($day = max((int) $started, $today - 29 * DAY_MS); $day <= $today; $day += DAY_MS) $daily[] = ['at' => $day, 'value' => (int) ($rows[$day] ?? 0)];
    }
    return ['started' => $started === null ? null : (int) $started, 'counts' => $counts, 'returning' => $returning, 'daily' => $daily];
}
