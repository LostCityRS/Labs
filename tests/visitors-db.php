<?php
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/views.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test' || $db->query('SELECT COUNT(*) FROM visits')->fetchColumn() != 0) throw new RuntimeException('Visitor tests require a fresh isolated labs_records_test database');
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$today = strtotime('2026-11-16 12:00 UTC') * 1000;
$day = intdiv($today, DAY_MS) * DAY_MS;
$ids = array_map(fn($i) => sprintf('00000000-0000-4000-8000-%012d', $i), range(1, 6));
$empty = visitor_stats($db, $today);
expect($empty === ['started' => null, 'counts' => [1 => 0, 7 => 0, 30 => 0], 'returning' => null, 'daily' => []], 'Empty history must not fabricate visits');
expect(str_contains(visitors_view(page_state('/stats', [], $today), $empty), 'No visits recorded yet.'), 'Empty stats must explain tracking has not started');
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
expect(count($v['daily']) === 30 && $v['daily'][0]['at'] === $day - 29 * DAY_MS, 'Chart must cover the last 30 UTC days');
expect($v['daily'][1]['value'] === 0 && $v['daily'][29]['value'] === 2 && $v['daily'][28]['value'] === 1, 'Missing days must be zero; repeat loads must count once');
$html = visitors_view(page_state('/stats', [], $today), $v);
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
expect(visitor_routes($db, '/hit', $today) && http_response_code() === 204, 'Same-origin ping must succeed without content');
$db->exec('DELETE FROM visits');
record_visit($db, $ids[0], $day - 12 * DAY_MS);
record_visit($db, $ids[0], $today);
expect(visitor_stats($db, $today)['returning'] === null, 'Incomplete comparison history must not report a misleading percentage');
$db->exec('DELETE FROM visits');
echo "Visitors: deduplication, UTC windows, returning share, zero days, validation, origin and method checks passed\n";
