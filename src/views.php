<?php
require_once __DIR__ . '/data.php';
const PERIOD_LABELS = ['24h' => 'Last 24 hours', '7d' => '7 days ago', '30d' => '30 days ago'];
const NAV = ['home' => 'Home', 'hiscores' => 'Hiscores', 'records' => 'Records', 'players' => 'Players', 'clans' => 'Clans'];

function e($text): string {return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
function fmt($number, int $decimals = 0): string {return number_format($decimals ? (float) $number : floor((float) $number), $decimals, '.', ',');}
function xp($value): string {return fmt($value / 10);}
function ehp($value): string {return fmt(round($value));}
function display_name(string $name): string {return e(ucwords(str_replace('_', ' ', $name)));}
function url(string $path, array $params = []): string {return e($path . ($params ? '?' . http_build_query($params) : ''));}
function player_name(string $name, array $clans): string {
    $label = display_name($name); $key = safe_name($name);
    return $label . implode('', array_map('clan_icon', $clans[$key] ?? []));
}
function clan_icon(string $key): string {
    if (!isset(CLANS[$key])) throw new RuntimeException("Unknown clan $key");
    // Assets are browser-cached for a week, so a redrawn icon needs a new URL.
    $version = filemtime(__DIR__ . '/../public' . CLANS[$key]['icon']) ?: throw new RuntimeException("Missing icon for clan $key");
    return '<img class="clan-icon" src="' . e(CLANS[$key]['icon']) . '?v=' . $version . '" alt="' . e(CLANS[$key]['name']) . '" title="' . e(CLANS[$key]['name']) . '">';}
function clan_link(string $key): string {return '<a href="/clans/' . $key . '">' . e(CLANS[$key]['name']) . clan_icon($key) . '</a>';}
function segments(array $s, string $key, array $options, string $label): string {
    $html = '<nav class="segments" aria-label="' . e($label) . '">';
    foreach ($options as $value => $text) $html .= '<a href="' . url($s['canonical'], [$key => $value] + array_intersect_key($s, ['period' => 1, 'metric' => 1, 'scale' => 1])) . '"' . ($s[$key] === $value ? ' aria-current="true"' : '') . '>' . e($text) . '</a>';
    return $html . '</nav>';
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
    $word = fn($i) => str_starts_with($headers[$i], 'Player') || in_array($headers[$i], ['Name', 'Clan']) ? ' class="word-column"' : '';
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
// $lines: one or more ['points' => [['at', 'value'], ...], 'label' => ?string, 'color' => ?string].
function chart(array $lines, string $name, string $unit, array $s, bool $daily = false): string {
    $points = array_merge(...array_column($lines, 'points'));
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
    $decimals = $unit === 'EHP' ? 0 : (int) min(1, max(0, ceil(log10($scale * 3 / $range))));
    $html = '<svg class="chart' . (count($points) > 60 ? ' dense' : '') . '" viewBox="0 0 770 226" role="img" aria-label="' . e($name) . '"><g class="grid">';
    $lastLabel = null;
    for ($i = 0; $i < 4; $i++) {
        $label = fmt(($max - $i * $range / 3) / $scale, $decimals) . $suffix;
        $html .= '<line x1="62" x2="730" y1="' . (37 + $i * 148 / 3) . '" y2="' . (37 + $i * 148 / 3) . '"/>';
        if ($label !== $lastLabel) $html .= '<text x="50" y="' . (41 + $i * 148 / 3) . '" text-anchor="end">' . $label . '</text>';
        $lastLabel = $label;
    }
    $html .= '</g>';
    foreach ($lines as $l) {
        if (!$l['points']) continue;
        $html .= '<g' . (isset($l['color']) ? ' style="--chart:' . e($l['color']) . '"' : '') . '><polyline class="xp-line" points="' . implode(' ', array_map(fn($p) => $x($p['at']) . ',' . $y($p['value']), $l['points'])) . '"/>';
        foreach ($l['points'] as $p) {
            $px = $x($p['at']); $py = $y($p['value']);
            $anchor = $px > 600 ? 'end' : ($px < 160 ? 'start' : 'middle');
            $tip = (isset($l['label']) ? $l['label'] . ': ' : '') . fmt($unit === 'EHP' ? round($p['value']) : $p['value']) . ' ' . $unit . ' · ' . gmdate($daily ? 'j M Y' : 'j M H:i', (int) ($p['at'] / 1000)) . ' UTC';
            $html .= '<g class="pt" tabindex="0"><circle class="hit" cx="' . $px . '" cy="' . $py . '" r="12"/><circle class="chart-point" cx="' . $px . '" cy="' . $py . '" r="3"/><text class="tip" x="' . $px . '" y="' . ($py < 60 ? $py + 24 : $py - 12) . '" text-anchor="' . $anchor . '">' . e($tip) . '</text></g>';
        }
        $html .= '</g>';
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
        $label = SKILLS[$type] . ' · ' . ehp($value) . ' EHP';
        $html .= '<div class="ehp-slot" tabindex="0" role="img" data-type="' . $type . '" data-ehp="' . $value . '" data-share="' . $share . '" data-label="' . e($label) . '" aria-label="' . e($label . ' · ' . fmt($share, 1) . '%') . '">' . icon(SKILLS[$type]) . '<b>' . fmt($share, 1) . '%</b></div>';
    }
    return $html . '</div><div class="ehp-tooltip" role="tooltip" hidden></div></div></section>';
}
function home_view(array $s, array $d): string {
    $html = '<h1 class="sr-only">Lost City Labs</h1>' . search_form() . '<div class="home-grid"><div><section class="panel"><div class="panel-heading"><h2>Most XP gained</h2>' . period_control($s) . '</div><div class="toolbar">' . skill_select($s) . '</div>' . table(['Rank', 'Player', 'XP · ' . $s['period']], array_map(fn($g, $i) => [$i + 1, player_link($g['name'], $s['clans']), '<span class="gain">+' . xp($g['gain']) . '</span>'], $d['gainers'], array_keys($d['gainers'])), 'Most XP gained') . '</section><section class="panel"><div class="panel-heading"><h2>Newly tracked players</h2></div>' . table(['Player', 'Total level', 'First tracked'], array_map(fn($p) => [player_link($p['name'], $s['clans']), fmt($p['level']), ago($p['first_seen'], $s['now'])], $d['newest']), 'Newly tracked players') . '</section></div><aside><section class="panel"><h2>Recent 99s</h2>' . achievements($d['nineties'], $s['now'], $s['clans']) . '</section>';
    if (isset($d['weekly'])) {
        $html .= '<section class="panel weekly"><h2>Top EHP</h2>' . (!$d['weekly'] ? empty_message('No weekly winners yet.') : '');
        foreach ($d['weekly'] as $r) $html .= '<div class="winner"><small>' . e($r['week']) . '</small>' . player_link($r['name'], $s['clans']) . '<b>' . ehp($r['ehp']) . ' <span>EHP</span></b></div>';
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
        if ($ehp !== null) $row[] = isset($ehp[$type]) ? ehp($ehp[$type]) : '–';
        array_push($row, $stat ? xp($stat['value']) : '–', $stat ? fmt($stat['rank']) : '–', $stat ? '<span class="gain">+' . xp($stat['gain']) . '</span>' : '–');
        $rows[] = $row;
    }
    $history = array_map(fn($h) => ['at' => $h['at'], 'value' => $h['value'] / 10], array_values(array_filter($d['history'], fn($h) => $h['type'] == $s['type'])));
    $headers = $ehp === null ? ['Skill', 'Level'] : ['Skill', 'Level', 'EHP'];
    array_push($headers, 'Experience', 'Rank', 'Gained · ' . $s['period']);
    return '<div class="page-heading"><div><div class="eyebrow">Player</div><h1>' . player_name($s['name'], $s['clans']) . '</h1><span class="muted">Updated ' . ago(max(array_column($d['stats'], 'at')), $s['now']) . '</span></div><div class="rank"><span>Overall rank</span><strong>' . ($overall ? fmt($overall['rank']) : '–') . '</strong></div></div><div class="stats"><div><span>Total level</span><strong>' . ($overall ? fmt($overall['level']) : '–') . '</strong></div><div><span>Total XP</span><strong>' . ($overall ? xp($overall['value']) : '–') . '</strong></div><div><span>Gained · ' . e($s['period']) . '</span><strong class="gain">' . ($overall ? '+' . xp($overall['gain']) : '–') . '</strong></div></div><section class="panel graph-panel"><div class="panel-heading"><h2>' . e($s['skill']) . ' experience</h2>' . period_control($s) . '</div><div class="toolbar">' . skill_select($s) . '</div>' . chart([['points' => $history]], $s['skill'] . ' XP', 'XP', $s) . '</section><div class="detail-grid"><section class="panel skills-panel"><div class="panel-heading"><h2>Skills</h2></div>' . table($headers, $rows, 'Player skills') . '</section><aside><section class="panel"><h2>Recent levels</h2>' . achievements($d['levels'], $s['now'], $s['clans']) . '</section>' . ($ehp === null ? '' : ehp_inventory($ehp, $s['name'])) . '</aside></div>' . held_records($d['records']);
}
function held_records(array $rows): string {
    if (!$rows) return '';
    $when = fn($r) => gmdate('j M Y H:i', (int) ($r['start_at'] / 1000)) . ' – ' . gmdate('j M Y H:i', (int) ($r['end_at'] / 1000)) . ' UTC';
    $cells = array_map(fn($r) => ['<a href="' . url('/records/' . SKILLS[$r['type']]) . '">' . icon(SKILLS[$r['type']]) . e(SKILLS[$r['type']]) . '</a>', e($r['period']), $r['place'] == 1 ? '<span class="gain">Record</span>' : '#' . $r['place'], '<span class="gain" title="' . e($when($r)) . '">' . xp($r['gain']) . '</span>', gmdate('j M Y', (int) ($r['end_at'] / 1000))], $rows);
    return '<section class="panel skills-panel held-records"><div class="panel-heading"><h2>Held records</h2></div>' . table(['Skill', 'Window', 'Place', 'XP', 'Set'], $cells, 'Held records') . '</section>';
}
function hiscores_view(array $s, array $rows): string {
    $maxing = $s['board'] === 'max'; $cells = [];
    foreach ($rows as $i => $r) {
        if (!isset($r['ehp']) || !is_numeric($r['ehp']) || !is_finite((float) $r['ehp']) || $r['ehp'] < 0) throw new RuntimeException('Hiscores contain invalid EHP');
        $row = [$i + 1, player_link($r['name'], $s['clans']), $r['level'] === null ? '–' : fmt($r['level']), $r['value'] === null ? '–' : xp($r['value']), ehp($r['ehp'])];
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
    foreach (['observed' => ['Daily observed players', 'Tracked accounts that had XP today', 'No complete days yet.'], 'online' => ['Players online', 'Sampled at midnight UTC', 'Waiting for the first midnight snapshot.']] as $key => [$title, $note, $pending]) $html .= '<section class="panel graph-panel"><div class="panel-heading"><h2>' . e($title) . '</h2></div><p class="muted">' . e($note) . '</p>' . ($d[$key] ? chart([['points' => $d[$key]]], $title, 'players', $s, true) : empty_message($pending)) . '</section>';
    return $html;
}
function guides_view(array $s, array $d): string {
    $rows = $d[$s['skill']] ?? throw new RuntimeException('Missing skill guide');
    $cells = array_map(fn($r) => [e($r['min_level'] . '–' . $r['max_level']), e(trim(preg_replace('~https?://\S+~', '', $r['method']))), fmt($r['rate'])], $rows);
    return '<div class="page-heading"><h1>EHP</h1></div>' . skill_tabs($s, true) . '<section class="panel guide-copy"><div class="panel-heading"><h2>' . icon($s['skill']) . e($s['skill']) . '</h2></div>' . ($cells ? table(['Level', 'Method', 'XP/h'], $cells, $s['skill'] . ' rates') : empty_message('Passive combat XP.')) . '</section><p class="guide-credit">Based on 2004Tracker by ' . player_link('pasta', $s['clans']) . '</p>';
}
function settings_view(array $s, array $profiles): string {
    $body = '<div class="page-heading"><h1>Settings</h1></div><section class="panel"><h2>Character clans</h2>';
    if ($s['saved']) $body .= '<p class="gain" role="status">Settings saved.</p>';
    $logout = '<form class="logout" action="/logout" method="post"><input type="hidden" name="csrf" value="' . e($s['csrf']) . '"><button type="submit">Logout</button></form>';
    if (!$profiles) return $body . empty_message('No linked characters. Link your characters to Discord in Lost City, then refresh them here.') . '<a href="/login">Refresh linked characters</a></section>' . $logout;
    $rows = [];
    foreach ($profiles as $p) {
        $roster = $p['roster'] === null ? [] : explode(',', $p['roster']);
        // Show what the character currently displays; only someone on two rosters needs a combined entry.
        $current = $p['clan'] ?? (count($roster) === 1 ? $roster[0] : ($roster ? '' : 'none'));
        $options = ($current === '' ? ['' => implode(' + ', array_map(fn($k) => CLANS[$k]['name'], $roster))] : []) + ['none' => 'None'] + array_map(fn($clan) => $clan['name'], CLANS);
        $select = '<select name="clans[' . e($p['name']) . ']" aria-label="Clan for ' . display_name($p['name']) . '">';
        foreach ($options as $value => $label) $select .= '<option value="' . $value . '"' . ($current === (string) $value ? ' selected' : '') . '>' . e($label) . '</option>';
        $rows[] = [player_link($p['name'], $s['clans']), $select . '</select>'];
    }
    return $body . '<form class="settings-form" action="/settings" method="post"><input type="hidden" name="csrf" value="' . e($s['csrf']) . '">' . table(['Player', 'Clan'], $rows, 'Your linked characters') . '<div class="settings-actions"><a href="/login">Refresh linked characters</a><button type="submit">Save settings</button></div></form></section>' . $logout;
}
function clan_hiscores_view(array $s, array $d): string {
    $metric = CLAN_METRICS[$s['metric']]; $lines = []; $rows = [];
    foreach ($d as $key => $members) {
        $gain = fn($m) => end($m['series'])[$s['metric']] - $m['series'][0][$s['metric']];
        $total = array_sum(array_map($gain, $members));
        $lines[] = ['label' => CLANS[$key]['name'], 'color' => CLANS[$key]['color'], 'points' => array_map(fn($p) => ['at' => $p['at'], 'value' => $p['value'] * ($s['scale'] === 'total' ? count($members) : 1)], clan_average($members, $s['metric'], true))];
        $rows[] = ['key' => $key, 'members' => count($members), 'average' => $members ? $total / count($members) : 0, 'total' => $total];
    }
    usort($rows, fn($a, $b) => $b[$s['scale']] <=> $a[$s['scale']]);
    $value = fn($v) => $s['metric'] === 'ehp' ? ehp($v) : fmt($v);
    $cells = array_map(fn($r, $i) => [$i + 1, clan_link($r['key']), fmt($r['members']), '<span class="gain">+' . $value($r['average']) . '</span>', '<span class="gain">+' . $value($r['total']) . '</span>'], $rows, array_keys($rows));
    $legend = '<ul class="legend">' . implode('', array_map(fn($key) => '<li style="--chart:' . e(CLANS[$key]['color']) . '">' . e(CLANS[$key]['name']) . '</li>', array_keys(CLANS))) . '</ul>';
    $controls = segments($s, 'scale', CLAN_SCALES, 'Average or total') . segments($s, 'metric', CLAN_METRICS, 'Metric') . segments($s, 'period', array_combine(array_keys(PERIODS), array_keys(PERIODS)), 'Time period');
    return '<div class="page-heading"><h1>Clans</h1></div><section class="panel graph-panel"><div class="panel-heading"><h2>' . CLAN_SCALES[$s['scale']] . ' ' . $metric . ' gained' . ($s['scale'] === 'average' ? ' per member' : '') . '</h2><div class="clan-controls">' . $controls . '</div></div>' . $legend . chart($lines, CLAN_SCALES[$s['scale']] . ' ' . $metric . ' gained', $metric, $s) . '</section><section class="panel">' . table(['Rank', 'Clan', 'Members', 'Average ' . $metric, 'Total ' . $metric], $cells, 'Clan hiscores', ($s['scale'] === 'average' ? 'Average ' : 'Total ') . $metric) . '</section>';
}
function clan_view(array $s, array $d): string {
    $clan = CLANS[$s['clan']]; $metric = CLAN_METRICS[$s['metric']]; $members = $d['members'];
    $about = array_filter([$clan['established'] === null ? null : 'Established ' . e($clan['established']), $clan['discord'] === null ? null : '<a href="' . e($clan['discord']) . '">Discord</a>', $clan['topic'] === null ? null : '<a href="https://lostcity.rs/t/' . $clan['topic'] . '">Forum thread</a>']);
    $html = '<div class="page-heading"><div><div class="eyebrow">Clan</div><h1>' . e($clan['name']) . clan_icon($s['clan']) . '</h1>' . ($about ? '<span class="muted">' . implode(' · ', $about) . '</span>' : '') . '</div></div>';
    $average = clan_average($members, $s['metric'], false); $gained = clan_average($members, $s['metric'], true);
    $value = fn($v) => $s['metric'] === 'ehp' ? ehp($v) : fmt($v);
    $html .= '<div class="stats"><div><span>Tracked members</span><strong>' . fmt(count($members)) . '</strong></div><div><span>Average ' . $metric . '</span><strong>' . ($average ? $value(end($average)['value']) : '–') . '</strong></div><div><span>Average gained · ' . e($s['period']) . '</span><strong class="gain">' . ($gained ? '+' . $value(end($gained)['value']) : '–') . '</strong></div></div>';
    if ($videos = $d['videos']) {
        $first = array_shift($videos);
        $html .= '<section class="panel videos"><div class="video-main"><iframe src="https://www.youtube-nocookie.com/embed/' . e($first['video_id']) . '" title="' . e($first['title']) . '" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe><p>' . e($first['title']) . ' <small>' . e($first['channel']) . ' · shared ' . gmdate('j M Y', (int) ($first['shared_at'] / 1000)) . '</small></p></div><div class="video-list">';
        foreach ($videos as $v) $html .= '<a href="https://www.youtube.com/watch?v=' . e($v['video_id']) . '" target="_blank" rel="noopener"><img src="https://i.ytimg.com/vi/' . e($v['video_id']) . '/mqdefault.jpg" alt="" loading="lazy" width="320" height="180"><span>' . e($v['title']) . '</span><small>Shared ' . gmdate('j M Y', (int) ($v['shared_at'] / 1000)) . '</small></a>';
        $html .= '</div></section>';
    }
    $html .= '<section class="panel graph-panel"><div class="panel-heading"><h2>Average ' . $metric . ' per member</h2><div class="clan-controls">' . segments($s, 'metric', CLAN_METRICS, 'Metric') . segments($s, 'period', array_combine(array_keys(PERIODS), array_keys(PERIODS)), 'Time period') . '</div></div>' . chart([['points' => $average]], 'Average ' . $metric, $metric, $s) . '</section>';
    usort($members, fn($a, $b) => end($b['series'])['ehp'] <=> end($a['series'])['ehp']);
    $cells = array_map(fn($m) => [player_link($m['name'], $s['clans']), $m['level'] === null ? '–' : fmt($m['level']), ehp(end($m['series'])['ehp']), '<span class="gain">+' . $value(end($m['series'])[$s['metric']] - $m['series'][0][$s['metric']]) . '</span>'], $members);
    return $html . '<section class="panel"><h2>Members</h2>' . table(['Player', 'Total level', 'EHP', $metric . ' gained · ' . $s['period']], $cells, $clan['name'] . ' members', 'EHP') . '</section>';
}
function account_nav(array $s): string {
    if (!array_key_exists('user', $s)) return '';
    if ($s['user'] === null) return '<nav class="account-nav" aria-label="Account"><a href="/login">Login</a></nav>';
    return '<nav class="account-nav" aria-label="Account"><a href="/settings"' . ($s['view'] === 'settings' ? ' aria-current="page"' : '') . '>Settings</a></nav>';
}
function page_body(array $s, $d): string {
    return match ($s['view']) {'home' => home_view($s, $d), 'player' => player_view($s, $d), 'hiscores' => hiscores_view($s, $d), 'records' => records_view($s, $d), 'players' => players_view($s, $d), 'guides' => guides_view($s, $d), 'clans' => $s['clan'] === null ? clan_hiscores_view($s, $d) : clan_view($s, $d)};
}
function document(array $s, string $body, string $notice = ''): string {
    $title = $s['view'] === 'player' ? display_name($s['name']) : (($s['clan'] ?? null) !== null ? e(CLANS[$s['clan']]['name']) : (['guides' => 'EHP', 'settings' => 'Settings', 'error' => 'Error'] + NAV)[$s['view']]);
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . ' · Lost City Labs</title><link rel="icon" href="/assets/stats.png"><link rel="stylesheet" href="/style.css?v=20"><script src="/app.js?v=11" defer></script></head><body>' . $notice . '<div class="shell"><header class="masthead"><a class="brand" href="/"><img class="mark" src="/assets/stats.png" alt="" width="25" height="24"><span>Lost City <b>Labs</b></span></a>' . account_nav($s) . '</header><div class="layout"><aside class="sidebar"><div class="sidebar-heading">Tracking</div><nav aria-label="Main">';
    foreach (NAV as $view => $label) $html .= '<a href="' . ($view === 'home' ? '/' : ($view === 'records' ? '/records/Overall' : '/' . $view)) . '"' . ($view === $s['view'] ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    return $html . '</nav><div class="guide-menu"><div class="sidebar-heading">Guides</div><nav aria-label="Guides"><a href="/guides/ehp"' . ($s['view'] === 'guides' ? ' aria-current="page"' : '') . '>EHP</a></nav></div><a class="external" href="https://2004.lostcity.rs/title" target="_blank" rel="noopener">Lost City ↗</a></aside><main>' . $body . '</main></div><footer><span><a href="https://github.com/LostCityRS/Labs">Source code</a> · Inspired by anders\' original Lost City Labs ❤️</span><a class="footer-discord" href="https://discord.gg/yk4eC8jB8s">Discord</a></footer></div></body></html>';
}
