<?php
require __DIR__ . '/../src/views.php';
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$now = strtotime('2026-11-11 16:00:00 UTC') * 1000;
foreach ([
    '/records' => ['achievements', '/records'],
    '/records?skill=Woodcutting' => ['xp', '/records/Woodcutting'],
    '/records?skill=Overall' => ['xp', '/records/Overall'],
    '/records?section=achievements&skill=Woodcutting' => ['achievements', '/records'],
    '/records?section=rank&skill=Woodcutting' => ['rank', '/records/rank'],
    '/records?section=rank' => ['rank', '/records/rank'],
    '/records/rank' => ['rank', '/records/rank'],
] as $uri => [$section, $canonical]) {
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);
    $s = page_state($uri, $params, $now);
    expect($s['section'] === $section && $s['canonical'] === $canonical, "Incorrect Records route: $uri");
}
foreach ([
    ['/records/Prayer', ['period' => '24h', 'skill' => 'Prayer'], '/records/Prayer'],
    ['/records', ['skill' => 'Prayer'], '/records/Prayer'],
    ['/records', ['section' => 'rank'], '/records/rank'],
    ['/records/Prayer', ['skill' => 'Fishing', 'period' => '7d'], '/records/Fishing'],
] as [$uri, $params, $canonical]) {
    $s = page_state($uri, $params, $now);
    expect($s['canonical'] === $canonical && $s['query'] === '' && $s['share'] === $canonical, "Records URLs must consume routing parameters and discard the unused period: $uri");
}
$s = page_state('/records/Prayer', ['skill' => 'Prayer', 'ref' => 'friend'], $now);
expect($s['query'] === 'ref=friend', 'Records URL cleanup must preserve unrelated query parameters');
foreach (['/records' => 'achievements', '/records/rank' => 'rank', '/records/Overall' => 'xp'] as $uri => $section) {
    $s = page_state($uri, [], $now);
    expect($s['section'] === $section && $s['share'] === $uri, "Records tab must have its own directly shareable URL: $uri");
    $html = records_view($s + ['clans' => []], ['6h' => [], '24h' => []]);
    expect(str_contains($html, 'href="/records/rank"') && !str_contains($html, 'href="/records?section=rank"'), 'Records tabs must link to distinct paths');
}
$s = page_state('/player/tm', ['skill' => 'Prayer', 'period' => '7d', 'unrelated' => 'ignored'], $now);
expect($s['share'] === '/player/tm?skill=Prayer&period=7d', 'Sharing must retain selected filters without exporting unrelated parameters');
$s = page_state('/youtube', ['page' => '2'], $now);
expect($s['share'] === '/youtube?page=2', 'Sharing a video page must retain its page number');
foreach (['/' => 'home', '/player/tm' => 'player', '/records/Overall' => 'records', '/hiscores' => 'hiscores'] as $uri => $view) {
    $s = page_state($uri, [], $now);
    $html = $view === 'hiscores' ? hiscore_controls($s) : skill_select($s);
    expect(!str_contains($html, '<button') && str_contains($html, 'method="get"'), "Auto-navigating $view filters must not have an Apply button");
    if ($view !== 'hiscores') expect(str_contains($html, 'name="period"') === ($view !== 'records'), "Only pages with a selectable period should submit it: $view");
}
$html = search_view('<bad>', 'Invalid username: <bad>');
expect(str_contains($html, 'value="&lt;bad&gt;"') && str_contains($html, 'Invalid username: &lt;bad&gt;'), 'Invalid searches must retain escaped input and an inline error');
expect(str_contains(account_nav(['view' => 'error']), 'href="/login"'), 'Public error pages must retain Login');
$_SESSION['user'] = ['id' => 'test', 'expires' => time() + 60];
expect(str_contains(account_nav(['view' => 'error']), 'href="/settings"'), 'Signed-in error pages must retain Settings');
unset($_SESSION['user']);
$html = achievements_view(['tm' => ['crandor']]);
expect(substr_count($html, 'class="clan-icon"') === 2 && str_contains($html, 'title="Crandor"'), 'Both Tm achievement links must use the current clan map');
$html = players_view(page_state('/players', [], $now), ['observed' => [], 'online' => []]);
expect(str_contains($html, 'per completed UTC day') && !str_contains($html, 'XP today'), 'Observed-player caption must describe completed UTC days');
$emptyClans = array_fill_keys(array_keys(CLANS), ['members' => [], 'levels' => []]);
foreach (['ehp' => 'Levels gained', 'xp' => 'XP gained'] as $metric => $heading) {
    $html = clan_hiscores_view(page_state('/clans', ['metric' => $metric], $now), $emptyClans);
    expect(str_contains($html, '>' . $heading . '</th>'), "Clan gains must declare their actual unit: $metric");
}
$world = ['today' => 1, 'week' => 1, 'totals' => [100], 'daily' => [], 'skills' => [], 'previous' => null, 'new99s' => [7 => [], 30 => []], 'nineties' => [], 'maxed' => 0, 'online' => ['weekday' => [0 => ['value' => 100, 'days' => 1]], 'all' => [0 => ['value' => 100, 'days' => 1]]]];
$html = stats_view(page_state('/stats', ['day' => 'split'], $now), $world);
expect(str_contains($html, 'aria-label="Hide Weekday"') && str_contains($html, 'hide=weekday') && str_contains($html, 'class="line-weekday"'), 'Chart legend must expose URL links for visible day types');
$html = stats_view(page_state('/stats', ['day' => 'split', 'hide' => 'weekday'], $now), $world);
expect(str_contains($html, 'aria-label="Show Weekday"') && !str_contains($html, 'class="line-weekday"') && str_contains($html, 'Choose a day type'), 'Hidden series and all-hidden state must come from the URL');
expect(str_contains($html, 'day=all&amp;period=24h&amp;hide=weekday'), 'Average/split navigation must preserve day-type choices');
try {page_state('/stats', ['hide' => 'nonsense'], $now); throw new LogicException('Unknown day types must fail');}
catch (InvalidArgumentException $e) {}
$html = xp_records_view(page_state('/records/Overall', [], $now) + ['clans' => []], ['6h' => [], '24h' => []]);
expect(str_contains($html, 'Overall · all skills combined'), 'Overall records must identify the combined skill scope');
expect(!str_contains($html, '6h: largest single logout') && !str_contains($html, 'Times are when Labs received updates'), 'XP boards must not restore the removed explanatory footer');
foreach (['max-crest.png' => 32, 'crown.png' => 16] as $art => $size) {
    $path = __DIR__ . '/../public/assets/records/' . $art;
    $image = getimagesize($path);
    expect($image[0] === $size && $image[1] === $size && $image[2] === IMAGETYPE_PNG && filesize($path) < 1024, "Record artwork must remain a tiny native-resolution PNG: $art");
}
echo "Behavior: routing, error recovery, affiliation, data labels, URL chart filters and pixel artwork passed\n";
