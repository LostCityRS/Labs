<?php
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/views.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test' || $db->query('SELECT COUNT(*) FROM visits')->fetchColumn() != 0) throw new RuntimeException('Visitor tests require a fresh isolated labs_records_test database');
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$start = VISITOR_FILTERED_FROM;
$day = $start + 35 * DAY_MS;
$today = $day + intdiv(DAY_MS, 2);
$ids = array_map(fn($i) => sprintf('00000000-0000-4000-8000-%012d', $i), range(1, 7));
$before = $start - 1;
record_visit($db, $ids[0], $before);
record_visit($db, $ids[6], $before);
$v = visitor_stats($db, $before);
expect($v['trackedDays'] === 0 && $v['daily'] === [] && $v['counts'] === [1 => 0, 7 => 0, 30 => 0], 'Do not report filtered visits before the UTC cutover');
$legacy = [['at' => $start - DAY_MS, 'value' => 2]];
expect($v['legacy'] === $legacy, 'Pre-cutover counts must remain available separately');
$html = visitors_view(page_state('/stats', [], $before), $v);
expect(substr_count($html, '<strong>—</strong>') === 3 && str_contains($html, 'Filtered reporting starts') && str_contains($html, '<details><summary>Legacy visitor counts (includes bots)</summary>'), 'Public page must leave headlines blank and label the collapsed legacy chart before cutover');
$v = visitor_stats($db, $start);
expect($v['trackedDays'] === 1 && $v['daily'] === [['at' => $start, 'value' => 0]] && $v['counts'] === [1 => 0, 7 => 0, 30 => 0], 'First UTC midnight starts exactly one filtered day, even when empty');
record_visit($db, $ids[0], $start);
$v = visitor_stats($db, $start);
expect($v['counts'] === [1 => 1, 7 => 1, 30 => 1] && $v['legacy'] === $legacy, 'No legacy-only ID may reach filtered totals');
expect($v['returning'] === null && str_contains(visitors_view(page_state('/stats', [], $start), $v), '1 of 30 days of filtered history available.'), 'Partial history must be clear on the chart');
foreach (['', 'bad', $ids[0] . "\n", str_replace('4000', '5000', $ids[0])] as $id) {
    try {record_visit($db, $id, $today); throw new LogicException('Bad visitor ID accepted');}
    catch (InvalidArgumentException $e) {}
}
record_visit($db, $ids[0], $day - 30 * DAY_MS);
record_visit($db, $ids[1], $day - 29 * DAY_MS);
record_visit($db, $ids[2], $day - 13 * DAY_MS);
record_visit($db, $ids[3], $day - 7 * DAY_MS);
record_visit($db, $ids[4], $day - 6 * DAY_MS);
record_visit($db, $ids[5], $day - 1);
record_visit($db, $ids[2], $day);
record_visit($db, $ids[2], $today);
record_visit($db, $ids[4], $today);
record_visit($db, $ids[4], $today);
$v = visitor_stats($db, $today);
expect($v['counts'] === [1 => 2, 7 => 3, 30 => 5], 'UTC boundaries and cross-day deduplication must be exact');
expect($v['returning']['count'] === 1 && abs($v['returning']['percent'] - 100 / 3) < 0.001, 'Returning share must use current visitors as denominator');
expect($v['trackedDays'] === 30 && count($v['daily']) === 30 && $v['daily'][0]['at'] === $day - 29 * DAY_MS && $v['legacy'] === $legacy, 'Filtered chart must cover 30 days without displacing legacy history');
expect($v['daily'][1]['value'] === 0 && $v['daily'][29]['value'] === 2 && $v['daily'][28]['value'] === 1, 'Missing days must be zero; repeat loads must count once');
$html = visitors_view(page_state('/stats', [], $today), $v);
expect(!str_contains($html, 'One random browser ID') && !str_contains($html, 'Tracking since'), 'Visitor panel must omit the removed tracking paragraph');
expect(str_contains($html, '33.3%') && str_contains($html, '1 of 3 browsers') && str_contains($html, 'today is still in progress'), 'Stats must explain return share and partial current day');
expect(!str_contains(json_encode($v), $ids[2]), 'Aggregates must never reveal visitor identifiers');
putenv('LABS_SITE_ORIGIN=http://localhost');
$_SERVER['REQUEST_METHOD'] = 'GET';
expect(visitor_routes($db, '/hit', $today) && http_response_code() === 405, 'GET must not count visits');
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['visitor' => $ids[0]];
foreach (['', 'https://other.example'] as $origin) {
    $_SERVER['HTTP_ORIGIN'] = $origin;
    try {visitor_routes($db, '/hit', $today); throw new LogicException('Cross-site ping accepted');}
    catch (AuthorizationException $e) {}
}
expect(!visitor_routes($db, '/stats', $today), 'Other routes must remain untouched');
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
foreach (['Mozilla/5.0 (Linux; Android 6.0.1) Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'semrushBOT/7.0', 'Mozilla/5.0 HeadlessChrome/158.0.0.0', 'python-requests/2.32.5'] as $agent) {
    $_SERVER['HTTP_USER_AGENT'] = $agent;
    expect(visitor_routes($db, '/hit', $today) && http_response_code() === 200, 'Known crawler pings must be distinguished in the access log');
}
expect(visitor_stats($db, $today)['counts'][1] === 2, 'Crawler pings must not write any visits');
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 lc-launcher/1.0.3 Chrome/132.0.6834.83 Electron/34.0.0 Safari/537.36';
foreach ([1, 2] as $_) expect(visitor_routes($db, '/hit', $today) && http_response_code() === 204, 'Same-origin normal browser pings must succeed without content');
expect(visitor_stats($db, $today)['counts'][1] === 3, 'Normal repeat loads must count once');
record_visit($db, $ids[1], $start + DAY_MS);
record_visit($db, $ids[1], $start + 13 * DAY_MS);
expect(visitor_stats($db, $start + 14 * DAY_MS - 1)['returning'] === null, 'Partial fourteenth day must not activate returning visitors');
expect(visitor_stats($db, $start + 14 * DAY_MS)['returning']['count'] === 1, 'Returning visitors activates after 14 full days');
$db->exec('DELETE FROM visits');
echo "Visitors: crawler rejection, clean UTC cutover, legacy isolation, deduplication and returning share passed\n";
