<?php
require_once __DIR__ . '/data.php';
const PERIOD_LABELS = ['24h' => 'Last 24 hours', '7d' => '7 days ago', '30d' => '30 days ago'];
const NAV = ['home' => 'Home', 'hiscores' => 'Hiscores', 'records' => 'Records', 'players' => 'Players'];

function e($text): string {return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
function fmt($number, int $decimals = 0): string {return number_format($decimals ? (float) $number : floor((float) $number), $decimals, '.', ',');}
function xp($value): string {return fmt($value / 10);}
function display_name(string $name): string {return e(ucwords(str_replace('_', ' ', $name)));}
function url(string $path, array $params = []): string {return e($path . ($params ? '?' . http_build_query($params) : ''));}
function player_name(string $name, array $clans): string {
    $label = display_name($name); $key = safe_name($name);
    if (!isset($clans[$key])) return $label;
    $clan = CLANS[$clans[$key]] ?? throw new RuntimeException('Unknown player clan');
    return $label . '<img class="clan-icon" src="' . e($clan['icon']) . '" width="16" height="16" alt="' . e($clan['name']) . '" title="' . e($clan['name']) . '">';
}
function player_link(string $name, array $clans): string {return '<a href="/player/' . rawurlencode($name) . '">' . player_name($name, $clans) . '</a>';}
function icon(string $skill): string {return '<span class="icon" aria-hidden="true" style="background-position:-' . (array_search($skill, array_values(SKILLS), true) * 25) . 'px 0"></span>';}
function empty_message(string $text): string {return '<p class="empty">' . e($text) . '</p>';}
function ago(int $at, int $now): string {
    $minutes = (int) round(($now - $at) / 60000);
    return $minutes < 60 ? $minutes . 'm ago' : ($minutes < 2880 ? round($minutes / 60) . 'h ago' : round($minutes / 1440) . 'd ago');
}
function search_form(string $value = '', string $error = ''): string {
    return '<form id="search" class="panel" action="/search" method="post" role="search"><div><input name="name" aria-label="Player name" placeholder="Player name" autocomplete="off" maxlength="12" required value="' . e($value) . '"><button type="submit">Find player</button></div><span id="search-error" role="status">' . e($error) . '</span></form>';
}
function skill_select(array $s): string {
    $action = $s['view'] === 'player' ? '/player/' . $s['name'] : $s['canonical'];
    $html = '<form class="skill-filter" action="' . url($action) . '" method="get"><input type="hidden" name="period" value="' . e($s['period']) . '"><label class="select-label">Skill<select name="skill">';
    foreach (SKILLS as $name) $html .= '<option' . ($name === $s['skill'] ? ' selected' : '') . '>' . e($name) . '</option>';
    return $html . '</select></label><button type="submit">Apply</button></form>';
}
function hiscore_controls(array $s): string {
    $html = '<form class="skill-filter hiscore-controls" action="/hiscores" method="get"><label class="select-label"><span class="sr-only">Hiscores view</span><select name="board">';
    foreach (HISCORE_BOARDS as $key => $label) $html .= '<option value="' . $key . '"' . ($s['board'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
    $html .= '</select></label><select name="skiller" aria-label="Account type" title="Skiller includes only confirmed combat level 3 accounts."><option value="0"' . (!$s['skiller'] ? ' selected' : '') . '>All players</option><option value="1"' . ($s['skiller'] ? ' selected' : '') . '>Skiller</option></select>';
    return $html . '<button type="submit">Apply</button></form>';
}
function period_control(array $s): string {
    $html = '<nav class="segments" aria-label="Time period">';
    foreach (PERIODS as $period => $ms) $html .= '<a href="' . url($s['canonical'], ['period' => $period, 'skill' => $s['skill']]) . '"' . ($s['period'] === $period ? ' aria-current="true"' : '') . '>' . e($period) . '</a>';
    return $html . '</nav>';
}
function skill_tabs(array $s, bool $guides = false): string {
    $html = '<nav class="skill-tabs" aria-label="Skill">';
    foreach (SKILLS as $type => $name) {
        if ($guides && $type === 0) continue;
        $href = $guides ? url('/guides/ehp', ['skill' => $name]) : url('/records/' . $name);
        $html .= '<a href="' . $href . '"' . ($s['skill'] === $name ? ' aria-current="page"' : '') . '>' . icon($name) . e($name) . '</a>';
    }
    return $html . '</nav>';
}
function table(array $headers, array $rows, string $caption, ?string $sorted = null, string $direction = 'descending'): string {
    if (!$rows) return empty_message('Nothing yet.');
    $word = fn($i) => str_starts_with($headers[$i], 'Player') || $headers[$i] === 'Name' ? ' class="word-column"' : '';
    $html = '<div class="table-wrap"><table><caption class="sr-only">' . e($caption) . '</caption><thead><tr>';
    foreach ($headers as $i => $h) $html .= '<th scope="col"' . ($h === $sorted ? ' aria-sort="' . e($direction) . '"' : '') . ($h === 'Maxing date' ? ' title="Overall max (' . fmt((count(SKILLS) - 1) * 99) . ' total), first observed in UTC"' : '') . $word($i) . '>' . e($h) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $i => $cell) $html .= $i === 0 ? '<th scope="row"' . $word($i) . '>' . $cell . '</th>' : '<td' . $word($i) . '>' . $cell . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}
function achievements(array $rows, int $now, array $clans): string {
    if (!$rows) return empty_message('None yet.');
    $html = '';
    foreach ($rows as $r) $html .= '<div class="achievement">' . (isset($r['name']) ? player_link($r['name'], $clans) : '<span>' . e(SKILLS[$r['type']]) . '</span>') . '<b>' . (isset($r['name']) ? e(SKILLS[$r['type']]) . ' ' . fmt($r['level']) : fmt($r['prev_level']) . ' → ' . fmt($r['level'])) . '</b><small>' . ago($r['at'], $now) . '</small></div>';
    return $html;
}
function chart(array $points, string $name, string $unit, array $s, bool $daily = false): string {
    if (!$points) return empty_message($daily ? "No $name data yet." : "No $name data in the last {$s['period']}.");
    $start = $daily ? $points[0]['at'] : $s['now'] - PERIODS[$s['period']];
    $min = $daily ? 0 : min(array_column($points, 'value'));
    $max = max(array_column($points, 'value'));
    // A single observation needs an axis range, not a second invented observation.
    if ($max == $min) {$min = max(0, $min - 1); $max++;}
    $range = $max - $min;
    $x = fn($at) => 62 + ($at - $start) / max(1, $s['now'] - $start) * 650;
    $y = fn($value) => 185 - ($value - $min) * 148 / $range;
    [$scale, $suffix] = $max >= 1e6 ? [1e6, 'm'] : ($max >= 1e4 ? [1e3, 'k'] : [1, '']);
    $decimals = (int) min(1, max(0, ceil(log10($scale * 3 / $range))));
    $html = '<svg class="chart' . (count($points) > 60 ? ' dense' : '') . '" viewBox="0 0 770 226" role="img" aria-label="' . e($name) . '"><g class="grid">';
    $lastLabel = null;
    for ($i = 0; $i < 4; $i++) {
        $label = fmt(($max - $i * $range / 3) / $scale, $decimals) . $suffix;
        $html .= '<line x1="62" x2="730" y1="' . (37 + $i * 148 / 3) . '" y2="' . (37 + $i * 148 / 3) . '"/>';
        if ($label !== $lastLabel) $html .= '<text x="50" y="' . (41 + $i * 148 / 3) . '" text-anchor="end">' . $label . '</text>';
        $lastLabel = $label;
    }
    $line = implode(' ', array_map(fn($p) => $x($p['at']) . ',' . $y($p['value']), $points));
    $html .= '</g><polyline class="xp-line" points="' . $line . '"/>';
    foreach ($points as $p) {
        $px = $x($p['at']); $py = $y($p['value']);
        $anchor = $px > 600 ? 'end' : ($px < 160 ? 'start' : 'middle');
        $tip = fmt($p['value']) . ' ' . $unit . ' · ' . gmdate($daily ? 'j M Y' : 'j M H:i', (int) ($p['at'] / 1000)) . ' UTC';
        $html .= '<g class="pt" tabindex="0"><circle class="hit" cx="' . $px . '" cy="' . $py . '" r="12"/><circle class="chart-point" cx="' . $px . '" cy="' . $py . '" r="3"/><text class="tip" x="' . $px . '" y="' . ($py < 60 ? $py + 24 : $py - 12) . '" text-anchor="' . $anchor . '">' . e($tip) . '</text></g>';
    }
    return $html . '<g class="axis"><text x="62" y="215">' . ($daily ? gmdate('j M Y', (int) ($start / 1000)) : PERIOD_LABELS[$s['period']]) . '</text><text x="720" y="215" text-anchor="end">' . ($daily ? 'Today (UTC)' : 'Now') . '</text></g></svg>';
}
function ehp_inventory(array $ehp, string $name): string {
    $skills = array_filter($ehp, fn($value, $type) => $type !== 0 && $value > 0, ARRAY_FILTER_USE_BOTH);
    if (!$skills) return empty_message('No EHP yet.');
    arsort($skills);
    $total = array_sum($skills);
    $html = '<section class="panel ehp-breakdown"><h2>EHP by skill</h2><div class="ehp-wrap"><div class="ehp-grid" role="group" aria-label="EHP by skill for ' . display_name($name) . '">';
    foreach ($skills as $type => $value) {
        $share = $value / $total * 100;
        $label = SKILLS[$type] . ' · ' . fmt($value, 1) . ' EHP';
        $html .= '<div class="ehp-slot" tabindex="0" role="img" data-type="' . $type . '" data-ehp="' . $value . '" data-share="' . $share . '" data-label="' . e($label) . '" aria-label="' . e($label . ' · ' . fmt($share, 1) . '%') . '">' . icon(SKILLS[$type]) . '<b>' . fmt($share, 1) . '%</b></div>';
    }
    return $html . '</div><div class="ehp-tooltip" role="tooltip" hidden></div></div></section>';
}
function home_view(array $s, array $d): string {
    $html = '<h1 class="sr-only">Lost City Labs</h1>' . search_form() . '<div class="home-grid"><div><section class="panel"><div class="panel-heading"><h2>Most XP gained</h2>' . period_control($s) . '</div><div class="toolbar">' . skill_select($s) . '</div>' . table(['Rank', 'Player', 'XP · ' . $s['period']], array_map(fn($g, $i) => [$i + 1, player_link($g['name'], $s['clans']), '<span class="gain">+' . xp($g['gain']) . '</span>'], $d['gainers'], array_keys($d['gainers'])), 'Most XP gained') . '</section><section class="panel"><div class="panel-heading"><h2>Newly tracked players</h2></div>' . table(['Player', 'Total level', 'First tracked'], array_map(fn($p) => [player_link($p['name'], $s['clans']), fmt($p['level']), ago($p['first_seen'], $s['now'])], $d['newest']), 'Newly tracked players') . '</section></div><aside><section class="panel"><h2>Recent 99s</h2>' . achievements($d['nineties'], $s['now'], $s['clans']) . '</section>';
    if (isset($d['weekly'])) {
        $html .= '<section class="panel weekly"><h2>Top EHP</h2>' . (!$d['weekly'] ? empty_message('No weekly winners yet.') : '');
        foreach ($d['weekly'] as $r) $html .= '<div class="winner"><small>' . e($r['week']) . '</small>' . player_link($r['name'], $s['clans']) . '<b>' . fmt($r['ehp'], 1) . ' <span>EHP</span></b></div>';
        $html .= '</section>';
    }
    return $html . '</aside></div>';
}
function player_view(array $s, ?array $d): string {
    if ($d === null) return '<div class="page-heading"><h1>' . player_name($s['name'], $s['clans']) . '</h1></div>' . empty_message('Not tracked yet.') . search_form(str_replace('_', ' ', $s['name']));
    $stats = array_column($d['stats'], null, 'type');
    $overall = $stats[0] ?? null;
    // EHP must come from an explicit provider; XP is never silently relabelled as EHP.
    $ehp = $d['ehp'] ?? null;
    if ($ehp !== null) {
        foreach ($ehp as $type => $value) if (!isset(SKILLS[$type]) || !is_numeric($value) || !is_finite((float) $value) || $value < 0) throw new RuntimeException('Invalid EHP breakdown');
        if (!isset($ehp[0]) || abs($ehp[0] - (array_sum($ehp) - $ehp[0])) > .001) throw new RuntimeException('EHP breakdown does not reconcile to Overall');
    }
    $rows = [];
    foreach (SKILLS as $type => $name) {
        $stat = $stats[$type] ?? null;
        $row = ['<span' . ($s['skill'] === $name ? ' class="current-skill"' : '') . '>' . icon($name) . e($name) . '</span>'];
        $row[] = $stat ? fmt($stat['level']) : '–';
        if ($ehp !== null) $row[] = isset($ehp[$type]) ? fmt($ehp[$type], 1) : '–';
        array_push($row, $stat ? xp($stat['value']) : '–', $stat ? fmt($stat['rank']) : '–', $stat ? '<span class="gain">+' . xp($stat['gain']) . '</span>' : '–');
        $rows[] = $row;
    }
    $history = array_map(fn($h) => ['at' => $h['at'], 'value' => $h['value'] / 10], array_values(array_filter($d['history'], fn($h) => $h['type'] == $s['type'])));
    $headers = $ehp === null ? ['Skill', 'Level'] : ['Skill', 'Level', 'EHP'];
    array_push($headers, 'Experience', 'Rank', 'Gained · ' . $s['period']);
    return '<div class="page-heading"><div><div class="eyebrow">Player</div><h1>' . player_name($s['name'], $s['clans']) . '</h1><span class="muted">Updated ' . ago(max(array_column($d['stats'], 'at')), $s['now']) . '</span></div><div class="rank"><span>Overall rank</span><strong>' . ($overall ? fmt($overall['rank']) : '–') . '</strong></div></div><div class="stats"><div><span>Total level</span><strong>' . ($overall ? fmt($overall['level']) : '–') . '</strong></div><div><span>Total XP</span><strong>' . ($overall ? xp($overall['value']) : '–') . '</strong></div><div><span>Gained · ' . e($s['period']) . '</span><strong class="gain">' . ($overall ? '+' . xp($overall['gain']) : '–') . '</strong></div></div><section class="panel graph-panel"><div class="panel-heading"><h2>' . e($s['skill']) . ' experience</h2>' . period_control($s) . '</div><div class="toolbar">' . skill_select($s) . '</div>' . chart($history, $s['skill'] . ' XP', 'XP', $s) . '</section><div class="detail-grid"><section class="panel skills-panel"><div class="panel-heading"><h2>Skills</h2></div>' . table($headers, $rows, 'Player skills') . '</section><aside><section class="panel"><h2>Recent levels</h2>' . achievements($d['levels'], $s['now'], $s['clans']) . '</section>' . ($ehp === null ? '' : ehp_inventory($ehp, $s['name'])) . '</aside></div>';
}
function hiscores_view(array $s, array $rows): string {
    $maxing = $s['board'] === 'max'; $cells = [];
    foreach ($rows as $i => $r) {
        if (!isset($r['ehp']) || !is_numeric($r['ehp']) || !is_finite((float) $r['ehp']) || $r['ehp'] < 0) throw new RuntimeException('Hiscores contain invalid EHP');
        $row = [$i + 1, player_link($r['name'], $s['clans']), $r['level'] === null ? '–' : fmt($r['level']), $r['value'] === null ? '–' : xp($r['value']), fmt($r['ehp'], 1)];
        if ($maxing) $row[] = $r['maxed_at'] === null ? '–' : '<time datetime="' . gmdate('Y-m-d\TH:i:s\Z', (int) ($r['maxed_at'] / 1000)) . '" title="First observed maxed · UTC">' . gmdate('j M Y', (int) ($r['maxed_at'] / 1000)) . '</time>';
        $cells[] = $row;
    }
    $headers = array_merge(['Rank', 'Name', 'Level', 'Exp', 'EHP'], $maxing ? ['Maxing date'] : []);
    $sorted = ['level' => 'Level', 'max' => 'Maxing date', 'ehp' => 'EHP', 'xp' => 'Exp'][$s['board']];
    $body = !$rows && $s['skiller'] ? empty_message('No confirmed matches yet.') : table($headers, $cells, HISCORE_BOARDS[$s['board']] . ' hiscores', $sorted, $maxing ? 'ascending' : 'descending');
    return '<div class="page-heading hiscores-heading"><h1>Hiscores</h1>' . hiscore_controls($s) . '</div><section class="panel">' . $body . '</section>';
}
function records_view(array $s, array $d): string {
    $html = '<div class="page-heading"><h1>XP records</h1></div>' . skill_tabs($s) . '<section class="panel"><div class="panel-heading"><h2>' . icon($s['skill']) . e($s['skill']) . '</h2></div><div class="boards">';
    foreach (['6h' => '6 hour', '24h' => '24 hour'] as $period => $label) {
        $best = $d[$period][0] ?? null;
        $when = fn($r) => gmdate('j M Y H:i', (int) ($r['start_at'] / 1000)) . ' – ' . gmdate('j M Y H:i', (int) ($r['end_at'] / 1000)) . ' UTC';
        $html .= '<div><div class="holder"><small>' . $label . ' record</small>' . ($best ? '<strong title="' . e($when($best)) . '">' . xp($best['gain']) . '</strong>' . player_link($best['name'], $s['clans']) : empty_message('Nobody yet.')) . '</div>' . ($d[$period] ? table(['#', 'Player', 'XP'], array_map(fn($r, $i) => [$i + 1, player_link($r['name'], $s['clans']), '<span class="gain" title="' . e($when($r)) . '">' . xp($r['gain']) . '</span>'], $d[$period], array_keys($d[$period])), $s['skill'] . ' ' . $label . ' records') : '') . '</div>';
    }
    return $html . '</div></section>';
}
function players_view(array $s, array $d): string {
    $html = '<div class="page-heading"><h1>Players</h1></div>';
    foreach (['observed' => ['Daily observed players', 'Tracked accounts that had XP today', 'No complete days yet.'], 'online' => ['Players online', 'Sampled at midnight UTC', 'Waiting for the first midnight snapshot.']] as $key => [$title, $note, $pending]) $html .= '<section class="panel graph-panel"><div class="panel-heading"><h2>' . e($title) . '</h2></div><p class="muted">' . e($note) . '</p>' . ($d[$key] ? chart($d[$key], $title, 'players', $s, true) : empty_message($pending)) . '</section>';
    return $html;
}
function guides_view(array $s, array $d): string {
    $rows = $d[$s['skill']] ?? throw new RuntimeException('Missing skill guide');
    $cells = array_map(fn($r) => [e($r['min_level'] . '–' . $r['max_level']), e(trim(preg_replace('~https?://\S+~', '', $r['method']))), fmt($r['rate'])], $rows);
    return '<div class="page-heading"><h1>EHP</h1></div>' . skill_tabs($s, true) . '<section class="panel guide-copy"><div class="panel-heading"><h2>' . icon($s['skill']) . e($s['skill']) . '</h2></div>' . ($cells ? table(['Level', 'Method', 'XP/h'], $cells, $s['skill'] . ' rates') : empty_message('Passive combat XP.')) . '</section><p class="guide-credit">Based on 2004Tracker by ' . player_link('pasta', $s['clans']) . '</p>';
}
function settings_view(array $s, array $profiles): string {
    $body = '<div class="page-heading"><h1>Settings</h1><a href="/login">Refresh linked characters</a></div><section class="panel"><h2>Character clans</h2><p class="muted settings-note">Choose a clan for each linked Lost City character. Its icon will appear beside that character throughout Labs.</p>';
    if ($s['saved']) $body .= '<p class="gain" role="status">Settings saved.</p>';
    if (!$profiles) return $body . empty_message('No linked characters. Link your characters to Discord in Lost City, then refresh them here.') . '</section>';
    $rows = [];
    foreach ($profiles as $p) {
        $select = '<select name="clans[' . e($p['name']) . ']" aria-label="Clan for ' . display_name($p['name']) . '"><option value=""' . ($p['clan'] === null ? ' selected' : '') . '>None</option>';
        foreach (CLANS as $key => $clan) $select .= '<option value="' . $key . '"' . ($p['clan'] === $key ? ' selected' : '') . '>' . e($clan['name']) . '</option>';
        $rows[] = [player_link($p['name'], $s['clans']), $select . '</select>'];
    }
    return $body . '<form action="/settings" method="post"><input type="hidden" name="csrf" value="' . e($s['csrf']) . '">' . table(['Player', 'Clan'], $rows, 'Your linked characters') . '<button class="settings-save" type="submit">Save settings</button></form></section>';
}
function account_nav(array $s): string {
    if (!array_key_exists('user', $s)) return '';
    if ($s['user'] === null) return '<nav class="account-nav" aria-label="Account"><a href="/login">Login</a></nav>';
    return '<nav class="account-nav" aria-label="Account"><a href="/settings"' . ($s['view'] === 'settings' ? ' aria-current="page"' : '') . '>Settings</a><form action="/logout" method="post"><input type="hidden" name="csrf" value="' . e($s['csrf']) . '"><button type="submit">Logout</button></form></nav>';
}
function page_body(array $s, $d): string {
    return match ($s['view']) {'home' => home_view($s, $d), 'player' => player_view($s, $d), 'hiscores' => hiscores_view($s, $d), 'records' => records_view($s, $d), 'players' => players_view($s, $d), 'guides' => guides_view($s, $d)};
}
function document(array $s, string $body, string $notice = ''): string {
    $title = $s['view'] === 'player' ? display_name($s['name']) : (['guides' => 'EHP', 'settings' => 'Settings', 'error' => 'Error'] + NAV)[$s['view']];
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . ' · Lost City Labs</title><link rel="icon" href="/assets/stats.png"><link rel="stylesheet" href="/style.css?v=16"><script src="/app.js?v=11" defer></script></head><body>' . $notice . '<div class="shell"><header class="masthead"><a class="brand" href="/"><img class="mark" src="/assets/stats.png" alt="" width="25" height="24"><span>Lost City <b>Labs</b></span></a>' . account_nav($s) . '</header><div class="layout"><aside class="sidebar"><div class="sidebar-heading">Tracking</div><nav aria-label="Main">';
    foreach (NAV as $view => $label) $html .= '<a href="' . ($view === 'home' ? '/' : ($view === 'records' ? '/records/Overall' : '/' . $view)) . '"' . ($view === $s['view'] ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    return $html . '</nav><div class="guide-menu"><div class="sidebar-heading">Guides</div><nav aria-label="Guides"><a href="/guides/ehp"' . ($s['view'] === 'guides' ? ' aria-current="page"' : '') . '>EHP</a></nav></div><a class="external" href="https://2004.lostcity.rs/title" target="_blank" rel="noopener">Lost City ↗</a></aside><main>' . $body . '</main></div><footer><span><a href="https://github.com/LostCityRS/Labs">Source code</a> · Inspired by anders\' original Lost City Labs ❤️</span><a class="footer-discord" href="https://discord.gg/yk4eC8jB8s">Discord</a></footer></div></body></html>';
}
