<?php
require __DIR__ . '/../src/views.php';
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$now = strtotime('2026-11-11 16:00:00 UTC') * 1000;
expect(YOUTUBE_CHANNELS['UCDnlUv-BXcMrwXLcTwNqU-Q'] === 'Michael_Unce', 'Michael Unce must be registered with his verified channel ID');
expect(youtube_listed('Michael_Unce', 'These 5 Skills Took Hundreds Of Hours For 99', 'Play Lost City here: https://2004.lostcity.rs/title'), 'Michael Unce uploads may identify Lost City only in the description');
expect(youtube_listed('Michael_Unce', 'My Best Week On 2004Scape', ''), 'Michael Unce 2004scape titles must be listed case-insensitively');
expect(!youtube_listed('Michael_Unce', 'Loot From 5500 Enhanced Crystal Key Chests', 'Old School RuneScape'), 'Unrelated Michael Unce OSRS uploads must not be listed');
expect(YOUTUBE_CHANNELS['UCV8OD9gwFmFcdbWkvHJEUXw'] === 'ZombieMusiq', 'ZombieMusiq must be registered with his verified channel ID');
expect(youtube_listed('ZombieMusiq', 'Loot from 2 hours of Fletching arrow shafts', 'Play here: https://2004.lostcity.rs/'), 'ZombieMusiq uploads with 2004.lostcity.rs in the description must be listed');
expect(youtube_listed('ZombieMusiq', 'Pyromaniac sets Ardougne ablaze', 'Play 2004.LostCity.rs today'), 'The description match must be case-insensitive');
expect(!youtube_listed('ZombieMusiq', 'Completing 15 quests at once (2004Scape)', 'No link here'), 'ZombieMusiq titles alone must not list a video without 2004.lostcity.rs in the description');
expect(!youtube_listed('ZombieMusiq', 'Cook\'s assistant quest guide', 'Play at https://lostcity.rs/t/some-thread'), 'A lostcity.rs link without the 2004 host must not list a ZombieMusiq video');
expect(YOUTUBE_CHANNELS['UCUw68v6UQm_79BWbgbxUyDg'] === 'Ellard2G', 'Ellard must be registered with the verified channel ID');
expect(youtube_listed('Ellard2G', '2004Scape = Lost City', ''), 'Ellard uploads may identify 2004scape in the title');
expect(youtube_listed('Ellard2G', 'New XP video', 'Playing on 2004SCAPE'), 'Ellard uploads may identify 2004scape only in the description');
expect(!youtube_listed('Ellard2G', 'Lost City gameplay', 'https://2004.lostcity.rs/'), 'Ellard uploads must mention 2004scape, not just Lost City');
expect(!youtube_listed('Ellard2G', 'Battlefield 1', ''), 'Unrelated Ellard uploads must not be listed');
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
$player = ['stats' => [['type' => 0, 'at' => $now, 'rank' => 1112, 'level' => 500, 'value' => 10000, 'gain' => 0]], 'history' => [], 'levels' => [], 'records' => [], 'similar' => []];
$html = player_view($s + ['clans' => []], $player);
expect(str_contains($html, 'href="https://2004.lostcity.rs/hiscores?category=0&amp;rank=1112"') && str_contains($html, '>1,112</a>'), 'The overall rank must link to the official rank search with an unformatted rank');
$player['stats'][0]['type'] = 1;
$html = player_view($s + ['clans' => []], $player);
expect(str_contains($html, '<span>Overall rank</span><strong>–</strong>'), 'A missing overall rank must not link to the official rank search');
expect(str_contains($html, '<h2>Recent levels</h2><p class="empty">None yet.</p>'), 'Players with no recorded levels must retain the empty state');
$player['levels'] = [['type' => 7, 'at' => strtotime('2026-11-11 00:15:00 UTC') * 1000, 'prev_level' => 76, 'level' => 77]];
$html = player_view($s + ['clans' => []], $player);
expect(preg_match('~<h2>Recent levels</h2>(.*?)</section>~s', $html, $match) === 1, 'Player profiles must show recent levels');
expect(str_contains($match[1], icon('Magic')) && str_contains($match[1], '<span class="sr-only">Magic level </span><b>77</b>'), 'Recent levels must show the skill icon and new level with an accessible skill name');
expect(str_contains($match[1], '<time datetime="2026-11-11" title="11 Nov 2026 UTC">11 Nov</time>') && !str_contains($match[1], '76') && !str_contains($match[1], 'ago'), 'Recent levels must show the UTC date without the previous level or relative age');
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
$_SESSION['user'] = ['id' => 'test'];
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
$world = ['visitors' => ['started' => null, 'counts' => [1 => 0, 7 => 0, 30 => 0], 'returning' => null, 'daily' => []], 'today' => 1234567, 'week' => 1999999, 'totals' => [2999999], 'daily' => [], 'skills' => [], 'previous' => null, 'new99s' => [7 => [], 30 => []], 'nineties' => [], 'maxed' => 0, 'online' => ['weekday' => [0 => ['value' => 100, 'days' => 1]], 'all' => [0 => ['value' => 100, 'days' => 1]]]];
http_response_code(200);
expect(str_contains(document(['view' => 'stats'], ''), ' data-track-visits'), 'Successful public pages must enable visitor tracking');
expect(!str_contains(document(['view' => 'error'], ''), ' data-track-visits'), 'Error documents must never enable tracking');
http_response_code(404);
expect(!str_contains(document(page_state('/player/missing', [], $now), ''), ' data-track-visits'), 'Missing players must not count as visits');
http_response_code(200);
$html = stats_view(page_state('/stats', ['day' => 'split'], $now), $world);
expect(str_ends_with($html, visitors_view(page_state('/stats', [], $now), $world['visitors'])), 'Labs visitors must be the last section on the stats page');
expect(str_contains($html, '<strong class="gain">+1M</strong>') && str_contains($html, '<strong>2M</strong>'), 'Weekly and total world XP must use millions rounded down');
expect(str_contains($html, '<strong class="gain">+1,234,567</strong>'), 'Today\'s XP must retain its exact value');
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
