<?php
require __DIR__ . '/../src/views.php';
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$now = strtotime('2026-11-11 16:00:00 UTC') * 1000;
foreach ([
    '/records' => ['achievements', '/records'],
    '/records?skill=Woodcutting' => ['xp', '/records/Woodcutting'],
    '/records?skill=Overall' => ['xp', '/records/Overall'],
    '/records?section=achievements&skill=Woodcutting' => ['achievements', '/records'],
    '/records?section=rank&skill=Woodcutting' => ['rank', '/records'],
] as $uri => [$section, $canonical]) {
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);
    $s = page_state($uri, $params, $now);
    expect($s['section'] === $section && $s['canonical'] === $canonical, "Incorrect Records route: $uri");
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
echo "Behavior: routing, error recovery, affiliation, data labels and URL chart filters passed\n";
