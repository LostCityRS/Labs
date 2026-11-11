<?php
require __DIR__ . '/../src/views.php';
function check(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$now = strtotime('2026-11-11 14:17:00 UTC') * 1000;
$catalog = achievement_catalog(); $rows = record_achievements();
check(count($catalog) === 46, 'All 46 achievement slots must exist');
check(count(array_filter($rows, fn($r) => isset($r['name']))) === 27, 'Expected 27 documented firsts');
check(count(historical_records()) === 35, 'Expected 27 firsts and eight rank handovers');
foreach ($rows as $r) {
    check(in_array($r['kind'], ['99', 'skill-xp', 'total-level', 'total-xp', 'combat'], true), 'Unexpected achievement kind');
    if ($r['kind'] === 'skill-xp') check($r['goal'] === 200000000, 'Skill XP honours must only include 200M');
    if ($r['kind'] === 'total-level') check(in_array($r['goal'], [1000, 1881], true), 'Intermediate total levels are excluded');
    if ($r['kind'] === 'combat') check($r['goal'] === 126, 'All-combat-99s must be excluded');
}
$helms = array_values(array_filter($rows, fn($r) => $r['kind'] === 'total-xp'));
check(array_column($helms, 'art') === ['bronze-med.png', 'steel-med.png', 'mithril-med.png', 'rune-med.png', 'dragon-med.png'], 'Chosen equipment progression must be med helms to dragon');
foreach ($catalog as $r) if ($r['art'] !== null) check(is_file(__DIR__ . '/../public/assets/records/' . $r['art']), 'Missing artwork ' . $r['art']);
$reigns = rank_reigns($now);
check(count($reigns) === 8, 'All eight reigns must be shown');
check(array_column($reigns, 'days') === [15, 1, 17, 74, 199, 44, 183, 92], 'Reign durations are incorrect');
check($reigns[7]['ongoing'] && $reigns[7]['end'] === $now, 'Last reign must extend to request time');
check(rank_reigns($now + 86400000)[7]['days'] === 93, 'Ongoing tenure must increase with time');
foreach ($reigns as $i => $r) {
    check($r['height'] > 0, 'Every reign must remain discoverable');
    if ($i < 7) check(abs($r['height'] - $r['days'] * 1.1) < 0.001, 'Closed reign height must be proportional');
}
try {rank_reigns(0); throw new LogicException('A clock before the recorded history should fail');}
catch (RuntimeException $e) {check(str_contains($e->getMessage(), 'before'), 'Invalid chronology should report its cause');}
$html = rank_history_view($now);
check(str_contains($html, '92 days so far'), 'Ongoing tenure must be visible');
check(!str_contains($html, 'End unknown') && !str_contains($html, 'Intervals inferred'), 'Removed notes must not reappear');
check(substr_count($html, 'class="reign-cell"') === 8, 'The ongoing reign must be a regular table cell');
$html = achievements_view([]);
check(substr_count($html, 'class="achievement-tile"') === 46, 'Selected layout must show every icon');
check(substr_count($html, 'href="/player/') === 27, 'Documented holder names must be visible and linked');
check(!str_contains($html, '<h2>Skills') && !str_contains($html, '<h2>Overall'), 'Achievements must be one flat collection');
foreach (['tm' => 2, 'neverlosebro' => 1, 'the_inducted' => 4, 'detective' => 1, 'not_a_holder' => 0] as $name => $count) check(substr_count(historical_badges($name, $now), 'class="achievement-icon"') === $count, "Incorrect badge count for $name");
check(str_contains(historical_badges('detective', $now), '92 days so far'), 'Rank badge must include ongoing duration');
check(!str_contains(historical_badges('neverlosebro', $now), 'so far'), 'Past reigns must not be labelled ongoing');
$title = achievement_title($rows['skill-xp:9:200000000']);
check(str_contains($title, "Tm\n24 May 2026") && str_contains($title, '23 May 2026'), 'Reported dates and source discrepancies must remain available');
check(str_contains(achievement_title($rows['total-xp:0:1000000000']), 'No documented holder'), 'Undocumented must not be presented as proven unclaimed');
check(str_contains(achievement_icon($rows['total-xp:0:1000000000']), 'record-silhouette'), 'Undocumented icons need silhouettes');
foreach (['/records' => 'achievements', '/records?section=rank' => 'rank', '/records/Overall' => 'xp', '/records/Woodcutting' => 'xp'] as $uri => $expected) {
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);
    $state = page_state($uri, $params, $now);
    check($state['section'] === $expected, "Wrong records route $uri");
    check($state['canonical'] === parse_url($uri, PHP_URL_PATH), "Wrong canonical URL $uri");
}
try {page_state('/records?section=invalid', ['section' => 'invalid'], $now); throw new LogicException('Invalid section should fail');}
catch (InvalidArgumentException $e) {}
$html = player_view(page_state('/player/tm', [], $now) + ['clans' => []], null);
check(str_contains($html, 'historical-badges') && str_contains($html, 'Not tracked yet'), 'Historical badges must survive missing current XP stats');
$html = chart_point(100, 100, 'Point < 10');
check(str_contains($html, '<title>Point &lt; 10</title>'), 'Charts must use escaped SVG native tooltips');
check(!str_contains(chart_frame('<svg></svg>'), 'tooltip'), 'Chart custom tooltip scaffolding must be removed');
$html = ehp_inventory([0 => 100, 9 => 100], 'tm');
check(str_contains($html, 'title="Woodcutting') && !str_contains($html, 'ehp-tooltip'), 'EHP tiles must use native title attributes');
echo "Achievements: catalogue, data, titles, profiles, routing and ongoing rank tenure passed\n";
