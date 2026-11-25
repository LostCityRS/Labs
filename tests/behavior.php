<?php
require __DIR__ . '/../src/views.php';
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$now = strtotime('2026-11-11 16:00:00 UTC') * 1000;
foreach ([
    ['Michael_Unce', 'These 5 Skills Took Hundreds Of Hours For 99', "Play Lost City here: https://2004.lostcity.rs/title\n2004 XP/EHP Tracker: https://www.lostcitylabs.com/", true],
    ['Ellard2G', 'My Best Week On 2004Scape', '', true],
    ['Ellard2G', 'Edgeville dungeon is not safe on 04scape', '', true],
    ['nosaching7270', 'solo KQ infodump for Lost City 2004', '', true],
    ['AutumnElegy', "I'm finally rank 1 in something :D", 'drama thread: https://lostcity.rs/t/internal-information', true],
    ['tony04scape', '2004Scape IRONMAN CARD SHARK (Tony) QUEST GRIND', 'JOIN LOST CITIZENS #1 LOST CITY COMMUNITY SERVER', true],
    ['PkLikeElve', 'Watch what happens when he panics', 'I made this amazing video with CapCut.', true],
    ['garlic04', '2005scape - July Updates', 'Play 2004scape: https://2004.lostcity.rs/roadmap', true],
    ['Michael_Unce', 'Loot From 5500 Enhanced Crystal Key Chests', 'Old School RuneScape', false],
    ['WildMudkip', '[OSRS] HCIM 146 - Wildy (2004/2277)', '', false],
    ['garlic04', 'Becoming Top Wizard on a 9-5 | 2005 RuneScape', 'Lost City fans: https://www.lostcitylabs.com/play', false],
    ['garlic04', 'Lost Citizens Scape: Slayer: Lets Kill HELLHOUNDS!', '#2004scape #lostcity', false],
    ['ZombieMusiq', 'Completing RuneScape on PlayStation2 trailer', "https://2004.lostcity.rs/\nPS2 port: https://github.com/johnson-cooper/2004sp-ps2port/releases", false],
] as [$handle, $title, $description, $listed]) expect(youtube_listed($handle, $title, $description) === $listed, "YouTube listing of '$title' must be " . var_export($listed, true));
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
$history = [['type'=>0, 'at'=>$now, 'value'=>1100], ['type'=>6, 'at'=>$now, 'value'=>200]];
$chart = player_xp_chart($s, $history);
preg_match('/data-chart="([^"]+)"/', $chart, $payload);
$chartData = json_decode(html_entity_decode($payload[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
expect($chartData === ['samples'=>[[$now,200]], 'start'=>strtotime('2026-11-05 UTC') * 1000, 'now'=>$now, 'days'=>7], 'Player XP chart must use only the selected skill and six previous UTC days plus today');
expect(player_xp_start('24h', $now) === $now - DAY_MS, '24h must remain rolling');
preg_match('/data-chart="([^"]+)"/', chart([['points' => [['at' => DAY_MS, 'value' => 5], ['at' => 3 * DAY_MS, 'value' => 7.5]]]], 'World XP', 'XP', $s, true, ['measure' => 'sum']), $payload);
$chartData = json_decode(html_entity_decode($payload[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
expect($chartData['start'] === DAY_MS && $chartData['end'] === 4 * DAY_MS && $chartData['measure'] === 'sum' && $chartData['lines'][0]['points'] === [[DAY_MS, 5], [3 * DAY_MS, 7.5]], 'Daily charts must cover whole UTC days and keep their window measure');
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
$s = page_state('/pkers', ['combat' => '90'], $now);
expect($s['combat'] === 90 && $s['share'] === '/pkers?combat=90' && page_state('/pkers', [], $now)['combat'] === null, 'Active PKers must read and share the chosen combat level');
foreach (['2', '127', '90.5', ''] as $bad) {
    try {page_state('/pkers', ['combat' => $bad], $now); expect(false, "Combat level '$bad' must be rejected");}
    catch (InvalidArgumentException $e) {expect($e->getMessage() === 'Combat level must be between 3 and 126', "Unexpected combat level error: {$e->getMessage()}");}
}
$pkers = array_map(fn($p) => $p + ['levels' => [1 => 60, 2 => 1, 3 => 80, 4 => 70, 5 => 75, 6 => 43, 7 => 60]], [['name' => 'near_low', 'combat' => 85], ['name' => 'too_high', 'combat' => 96], ['name' => 'exact', 'combat' => 90]]);
$html = pkers_view($s + ['clans' => []], $pkers);
expect(str_contains($html, '<strong class="pk-count">2 active PKers</strong>') && strpos($html, '/player/near_low') < strpos($html, '/player/exact') && !str_contains($html, 'too_high'), 'Active PKers must list, in the given order, only players within five combat levels');
expect(str_contains($html, '<span>85</span>') && str_contains($html, '<span>90</span>') && str_contains($html, '<div class="level-slot" title="Defence">' . icon('Defence') . '<b>1</b></div>') && str_contains($html, '<div class="level-slot" title="Ranged">' . icon('Ranged') . '<b>75</b></div>') && !str_contains($html, '&lt;15'), 'PKer cards must show one combat level and every combat stat');
expect(str_contains($html, '<a href="/pkers?combat=90" title="Combat 90" class="you" style="--h:5%">') && str_contains($html, '<a href="/pkers?combat=85" title="Combat 85" class="near" style="--h:5%">') && str_contains($html, '<a href="/pkers?combat=84" title="Combat 84" style="--h:0%">') && str_contains($html, '<a href="/pkers?combat=96" title="Combat 96" style="--h:5%">') && str_contains($html, '<i data-v="20" style="bottom:100%">'), 'The combat histogram must count each PKer at their combat level and mark the chosen bracket');
expect(str_contains($html, '<form class="pk-form" action="/pkers">') && str_contains($html, 'name="combat" type="number" min="3" max="126" value="90"'), 'Choosing a combat level must be a plain page load');
expect(str_contains(pkers_view(['combat' => null, 'clans' => []], $pkers), '>3 active PKers<') && str_contains(pkers_view(['combat' => 30, 'clans' => []], $pkers), 'No active PKers in this range.') && str_contains(pkers_view(['combat' => null, 'clans' => []], []), 'No active PKers yet.'), 'Active PKers must list everyone without a combat level and say when nobody matches');
$s = page_state('/youtube', ['page' => '2'], $now);
expect($s['share'] === '/youtube?page=2', 'Sharing a video page must retain its page number');
$stats = fn(array $levels, array $xp10 = [], ?array $overall = null, array $stale = []) => array_merge(array_map(fn($type) => ['type' => $type, 'level' => $levels[$type], 'value' => $xp10[$type] ?? ($levels[$type] > 1 ? 1000000 : 0), 'at' => in_array($type, $stale, true) ? 1 : 2], array_keys($levels)), $overall === null ? [] : [['type' => 0, 'level' => $overall[0], 'value' => $overall[1], 'at' => 2]]);
$all = array_fill_keys(array_keys(array_diff_key(SKILLS, [0 => true])), 1); $all[4] = 10;
$pure = $all; $pure[1] = 60; $pure[3] = 80; $pure[5] = 70;
expect(account_restrictions($stats($pure, [4 => 11540]))['pure'] === true && account_restrictions($stats($pure, [4 => 11540]))['f2p'] === true, 'A fully recorded 1 Defence account without members XP must be a confirmed F2P pure');
expect(account_restrictions($stats(array_replace($pure, [2 => 2]), [4 => 11540]))['pure'] === false, 'Defence above 1 is not a pure');
expect(account_restrictions($stats(array_diff_key($pure, [2 => true]), [4 => 11540]))['pure'] === null, 'An omitted Defence level leaves pure status unknown');
$p2p = array_replace($pure, [17 => 30]);
expect(account_restrictions($stats($p2p, [4 => 11540]))['f2p'] === false, 'Any members-skill XP rules out F2P');
expect(account_restrictions($stats($p2p, [4 => 11540, 17 => 133000], null, [17]))['f2p'] === false, 'Members XP from an older reading still rules out F2P');
$partial = array_diff_key($pure, [16 => true, 17 => true]);
$partialXp = array_sum(array_map(fn($r) => $r['value'], $stats($partial, [4 => 11540])));
expect(account_restrictions($stats($partial, [4 => 11540]))['f2p'] === null, 'Omitted members skills leave F2P unknown without Overall totals');
expect(account_restrictions($stats($partial, [4 => 11540], [array_sum($partial) + 2, $partialXp]))['f2p'] === true, 'Overall XP with nothing unaccounted for proves omitted members skills are untrained');
expect(account_restrictions($stats($partial, [4 => 11540], [array_sum($partial) + 3, $partialXp + 100]))['f2p'] === null, 'Unaccounted Overall XP may belong to an omitted members skill');
$s = page_state('/hiscores', ['board' => 'ehp', 'account' => 'pure'], $now);
$html = hiscore_controls($s);
expect($s['account'] === 'pure' && str_contains($html, '<option value="pure" selected>Pure</option>') && str_contains($html, '<option value="f2p">F2P</option>') && str_contains($html, '<option value="all">All players</option>'), 'The account dropdown must offer every account type and keep the selected one');
expect($s['share'] === '/hiscores?board=ehp&account=pure', 'Sharing a filtered hiscores page must keep its account type');
expect(page_state('/hiscores', ['board' => 'max', 'skiller' => '1'], $now)['query'] === 'board=max&account=skiller' && page_state('/hiscores', ['skiller' => '0'], $now)['query'] === '', 'Old skiller links must redirect to the account filter');
foreach ([['account' => 'ironman'], ['skiller' => '2']] as $bad) {
    try {page_state('/hiscores', $bad, $now); expect(false, 'Invalid account filters must be rejected: ' . http_build_query($bad));} catch (InvalidArgumentException) {}
}
try {hiscore_options(['skiller' => '1']); expect(false, 'The API must reject the retired skiller filter');} catch (InvalidArgumentException $e) {expect(str_contains($e->getMessage(), 'use account'), 'The retired skiller filter error must name its replacement');}
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
$world = ['visitors' => ['trackedDays' => 0, 'counts' => [1 => 0, 7 => 0, 30 => 0], 'returning' => null, 'daily' => [], 'legacy' => []], 'today' => 1234567, 'week' => 1999999, 'totals' => [2999999], 'daily' => [], 'skills' => [], 'previous' => null, 'new99s' => [7 => [], 30 => []], 'nineties' => [], 'maxed' => 0, 'online' => ['weekday' => [0 => ['value' => 100, 'days' => 1]], 'all' => [0 => ['value' => 100, 'days' => 1]]]];
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
expect(str_contains($html, 'aria-label="Hide Weekday"') && str_contains($html, 'hide=weekday') && str_contains($html, '&quot;label&quot;:&quot;Weekday&quot;'), 'Chart legend must expose URL links for visible day types');
$html = stats_view(page_state('/stats', ['day' => 'split', 'hide' => 'weekday'], $now), $world);
expect(str_contains($html, 'aria-label="Show Weekday"') && !str_contains($html, '&quot;label&quot;:&quot;Weekday&quot;') && str_contains($html, 'Choose a day type'), 'Hidden series and all-hidden state must come from the URL');
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
