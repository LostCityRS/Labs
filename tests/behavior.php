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
echo "Behavior: Records URLs and search/error recovery passed\n";
