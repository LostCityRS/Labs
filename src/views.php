<?php
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/achievements.php';
const PERIOD_LABELS = ['24h' => 'Last 24 hours', '7d' => '7 days ago', '30d' => '30 days ago'];
// The unnamed first group has no heading.
const NAV = ['' => ['home' => 'Home'], 'Rankings' => ['hiscores' => 'Hiscores', 'records' => 'Records', 'clans' => 'Clans'], 'World' => ['stats' => 'World stats', 'players' => 'Players online'], 'Community' => ['guides' => 'EHP rates', 'youtube' => 'YouTube']];

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
    foreach ($options as $value => $text) $html .= '<a href="' . url($s['canonical'], [$key => $value] + array_intersect_key($s, ['period' => 1, 'metric' => 1, 'scale' => 1, 'day' => 1])) . '"' . ($s[$key] === $value ? ' aria-current="true"' : '') . '>' . e($text) . '</a>';
    return $html . '</nav>';
}
function player_link(string $name, array $clans): string {return '<a href="/player/' . rawurlencode($name) . '">' . player_name($name, $clans) . '</a>';}
function icon(string $skill): string {return '<span class="icon" data-skill="' . e($skill) . '" aria-hidden="true" style="background-position:-' . (array_search($skill, array_values(SKILLS), true) * 25) . 'px 0"></span>';}
function empty_message(string $text): string {return '<p class="empty">' . e($text) . '</p>';}
function ago(int $at, int $now): string {
    $minutes = (int) round(($now - $at) / 60000);
    return $minutes < 60 ? $minutes . 'm ago' : ($minutes < 2880 ? round($minutes / 60) . 'h ago' : round($minutes / 1440) . 'd ago');
}
function search_form(string $value = '', string $error = ''): string {
    return '<form id="search" class="panel" action="/search" method="post" role="search"><div><input name="name" aria-label="Player name" placeholder="Player name" autocomplete="off" maxlength="12" required value="' . e($value) . '"><button type="submit">Find player</button></div><span id="search-error" role="status">' . e($error) . '</span></form>';
}
function search_view(string $value, string $error): string {
    return '<div class="page-heading"><h1>Find player</h1></div>' . search_form($value, $error);
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
function recent_99s(array $rows, int $now, array $clans): string {
    if (!$rows) return empty_message('None yet.');
    $html = '<div class="recent-99s">';
    foreach ($rows as $r) $html .= '<div title="' . e(SKILLS[$r['type']] . ' 99 · ' . gmdate('j M Y H:i', (int) ($r['at'] / 1000)) . ' UTC') . '">' . icon(SKILLS[$r['type']]) . player_link($r['name'], $clans) . '<small>' . ago($r['at'], $now) . '</small></div>';
    return $html . '</div>';
}
function achievements(array $rows, int $now, array $clans): string {
    if (!$rows) return empty_message('None yet.');
    $html = '';
    foreach ($rows as $r) $html .= '<div class="achievement">' . (isset($r['name']) ? player_link($r['name'], $clans) : '<span>' . e(SKILLS[$r['type']]) . '</span>') . '<b>' . (isset($r['name']) ? e(SKILLS[$r['type']]) . ' ' . fmt($r['level']) : fmt($r['prev_level']) . ' → ' . fmt($r['level'])) . '</b><small>' . ago($r['at'], $now) . '</small></div>';
    return $html;
}
function chart_frame(string $svg): string {
    return '<div class="chart-wrap">' . $svg . '</div>';
}
function chart_point(float $x, float $y, string $tip): string {
    return '<g class="pt" tabindex="0" aria-label="' . e($tip) . '"><title>' . e($tip) . '</title><circle class="hit" cx="' . $x . '" cy="' . $y . '" r="18"/><circle class="chart-point" cx="' . $x . '" cy="' . $y . '" r="3"/></g>';
}
// $lines: one or more ['points' => [['at', 'value'], ...], 'label' => ?string, 'color' => ?string].
function chart(array $lines, string $name, string $unit, array $s, bool $daily = false, int $height = 226): string {
    $points = array_merge(...array_column($lines, 'points'));
    if (!$points) return empty_message($daily ? "No $name data yet." : "No $name data in the last {$s['period']}.");
    $start = $daily ? $points[0]['at'] : $s['now'] - PERIODS[$s['period']];
    $min = $daily ? 0 : min(array_column($points, 'value'));
    $max = max(array_column($points, 'value'));
    // A single observation needs an axis range, not a second invented observation.
    if ($max == $min) {$min = max(0, $min - 1); $max++;}
    // Whole-number EHP labels need whole-number steps, or small ranges round to one repeated label.
    if ($unit === 'EHP') {$min = floor($min); $max = $min + 3 * max(1, ceil(($max - $min) / 3));}
    $range = $max - $min;
    $x = fn($at) => 62 + ($at - $start) / max(1, $s['now'] - $start) * 650;
    $plot = $height - 78;
    $y = fn($value) => 37 + $plot - ($value - $min) * $plot / $range;
    [$scale, $suffix] = $max >= 1e6 ? [1e6, 'm'] : ($max >= 1e4 ? [1e3, 'k'] : [1, '']);
    $decimals = $unit === 'EHP' ? 0 : (int) min(1, max(0, ceil(log10($scale * 3 / $range))));
    $html = '<svg class="chart' . (count($points) > 60 ? ' dense' : '') . '" viewBox="0 0 770 ' . $height . '" role="img" aria-label="' . e($name) . '"><g class="grid">';
    $lastLabel = null;
    for ($i = 0; $i < 4; $i++) {
        $label = fmt(($max - $i * $range / 3) / $scale, $decimals) . $suffix;
        $html .= '<line x1="62" x2="730" y1="' . (37 + $i * $plot / 3) . '" y2="' . (37 + $i * $plot / 3) . '"/>';
        if ($label !== $lastLabel) $html .= '<text x="50" y="' . (41 + $i * $plot / 3) . '" text-anchor="end">' . $label . '</text>';
        $lastLabel = $label;
    }
    $html .= '</g>';
    foreach ($lines as $l) {
        if (!$l['points']) continue;
        $html .= '<g' . (isset($l['color']) ? ' style="--chart:' . e($l['color']) . '"' : '') . '><polyline class="xp-line" points="' . implode(' ', array_map(fn($p) => $x($p['at']) . ',' . $y($p['value']), $l['points'])) . '"/>';
        foreach ($l['points'] as $p) {
            $px = $x($p['at']); $py = $y($p['value']);
            $tip = (isset($l['label']) ? $l['label'] . ': ' : '') . fmt($unit === 'EHP' ? round($p['value']) : $p['value']) . ' ' . $unit . ' · ' . gmdate($daily ? 'j M Y' : 'j M H:i', (int) ($p['at'] / 1000)) . ' UTC';
            $html .= chart_point($px, $py, $tip);
        }
        $html .= '</g>';
    }
    return chart_frame($html . '<g class="axis"><text x="62" y="' . ($height - 11) . '">' . ($daily ? gmdate('j M Y', (int) ($start / 1000)) : PERIOD_LABELS[$s['period']]) . '</text><text x="720" y="' . ($height - 11) . '" text-anchor="end">' . ($daily ? 'Today (UTC)' : 'Now') . '</text></g></svg>');
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
        $html .= '<div class="ehp-slot" tabindex="0" role="img" data-type="' . $type . '" data-ehp="' . $value . '" data-share="' . $share . '" data-label="' . e($label) . '" title="' . e($label . ' · ' . fmt($share, 1) . '%') . '" aria-label="' . e($label . ' · ' . fmt($share, 1) . '%') . '">' . icon(SKILLS[$type]) . '<b>' . fmt($share, 1) . '%</b></div>';
    }
    return $html . '</div></div></section>';
}
function home_view(array $s, array $d): string {
    $html = '<h1 class="sr-only">Lost City Labs</h1>' . search_form() . '<div class="home-grid"><div><section class="panel"><div class="panel-heading"><h2>Most XP gained</h2>' . period_control($s) . '</div><div class="toolbar">' . skill_select($s) . '</div>' . table(['Rank', 'Player', 'XP'], array_map(fn($g, $i) => [$i + 1, player_link($g['name'], $s['clans']), '<span class="gain">+' . xp($g['gain']) . '</span>'], $d['gainers'], array_keys($d['gainers'])), 'Most XP gained') . '</section><section class="panel"><div class="panel-heading"><h2>Newly tracked players</h2></div>' . table(['Player', 'Total level', 'First tracked'], array_map(fn($p) => [player_link($p['name'], $s['clans']), fmt($p['level']), ago($p['first_seen'], $s['now'])], $d['newest']), 'Newly tracked players') . '</section></div><aside><section class="panel"><h2>Recent 99s</h2>' . recent_99s($d['nineties'], $s['now'], $s['clans']) . '</section>';
    if (isset($d['weekly'])) {
        $html .= '<section class="panel weekly"><h2>Top EHP</h2>' . (!$d['weekly'] ? empty_message('No weekly winners yet.') : '');
        foreach ($d['weekly'] as $r) $html .= '<div class="winner"><small>' . e($r['week']) . '</small>' . player_link($r['name'], $s['clans']) . '<b>' . ehp($r['ehp']) . ' <span>EHP</span></b></div>';
        $html .= '</section>';
    }
    $html .= '<section class="panel recent-videos"><div class="panel-heading"><h2>Recent videos</h2><a href="/youtube">All →</a></div>' . ($d['videos'] ? '' : empty_message('None in the last 12 hours.'));
    foreach ($d['videos'] as $v) $html .= '<a class="video" href="' . youtube_url($v) . '" target="_blank" rel="noopener" title="' . e($v['title']) . '"><img src="' . youtube_thumb($v) . '" alt="" loading="lazy" width="80" height="45"><span>' . e($v['title']) . '</span><small>' . e($v['channel']) . ' · ' . ago($v['published_at'], $s['now']) . '</small></a>';
    return $html . '</section></aside></div>';
}
function player_view(array $s, ?array $d): string {
    if ($d === null) return '<div class="page-heading"><div><h1>' . player_name($s['name'], $s['clans']) . '</h1>' . historical_badges($s['name'], $s['now']) . '</div></div>' . empty_message('Not tracked yet.') . search_form(str_replace('_', ' ', $s['name']));
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
    array_push($headers, 'Experience', 'Rank', 'Gained');
    return '<div class="page-heading"><div><div class="eyebrow">Player</div><h1>' . main_skill($ehp) . player_name($s['name'], $s['clans']) . '</h1>' . historical_badges($s['name'], $s['now']) . '<span class="muted">Updated ' . ago(max(array_column($d['stats'], 'at')), $s['now']) . '</span></div><div class="rank"><span>Overall rank</span><strong>' . ($overall ? fmt($overall['rank']) : '–') . '</strong></div></div><div class="stats"><div><span>Total level</span><strong>' . ($overall ? fmt($overall['level']) : '–') . '</strong></div><div><span>Total XP</span><strong>' . ($overall ? xp($overall['value']) : '–') . '</strong></div><div><span>Gained</span><strong class="gain">' . ($overall ? '+' . xp($overall['gain']) : '–') . '</strong></div></div><div class="detail-grid"><div><section class="panel graph-panel"><div class="panel-heading"><h2>' . e($s['skill']) . ' experience</h2>' . period_control($s) . '</div><div class="toolbar">' . skill_select($s) . '</div>' . chart([['points' => $history]], $s['skill'] . ' XP', 'XP', $s) . '</section><section class="panel skills-panel"><div class="panel-heading"><h2>Skills</h2></div>' . table($headers, $rows, 'Player skills') . '</section></div><aside><section class="panel"><h2>Recent levels</h2>' . achievements($d['levels'], $s['now'], $s['clans']) . '</section>' . ($ehp === null ? '' : ehp_inventory($ehp, $s['name'])) . held_records($d['records']) . '</aside></div>';
}
function main_skill(?array $ehp): string {
    $skills = array_diff_key($ehp ?? [], [0 => true]);
    if (!$skills || max($skills) <= 0) return '';
    $type = array_search(max($skills), $skills, true);
    return '<span class="main-skill">' . icon(SKILLS[$type]) . '</span>';
}
function held_records(array $rows): string {
    if (!$rows) return '';
    $bySkill = [];
    foreach ($rows as $r) $bySkill[$r['type']][$r['period']] = $r;
    $html = '<section class="panel held-records"><h2>XP gain placements</h2><div class="record-slots">';
    foreach (SKILLS as $type => $skill) {
        if (!isset($bySkill[$type])) continue;
        $lines = ''; $tips = [];
        foreach (RECORDS as $period => $ms) {
            if (!isset($bySkill[$type][$period])) continue;
            $r = $bySkill[$type][$period]; $place = $r['place'] == 1 ? 'Record' : '#' . $r['place'];
            $lines .= '<span><small>' . $period . '</small> #' . $r['place'] . '</span>';
            $tips[] = "$period $place · " . xp($r['gain']) . ' XP · ' . gmdate('j M Y', (int) ($r['end_at'] / 1000));
        }
        $html .= '<a class="record-slot" href="' . url('/records/' . $skill) . '" title="' . e($skill . ' · ' . implode(' · ', $tips)) . '">' . icon($skill) . $lines . '</a>';
    }
    return $html . '</div></section>';
}
function week_change(?float $value, string $text, string $sign = '+', string $what = ''): string {
    return $value !== null && round($value) > 0 ? '<small class="gain week" title="' . e($text . $what . ' in the last 7 days') . '">' . $sign . $text . '</small>' : '';
}
function hiscores_view(array $s, array $rows): string {
    $maxing = $s['board'] === 'max'; $cells = [];
    foreach ($rows as $i => $r) {
        if (!isset($r['ehp']) || !is_numeric($r['ehp']) || !is_finite((float) $r['ehp']) || $r['ehp'] < 0) throw new RuntimeException('Hiscores contain invalid EHP');
        // Only the column the board is sorted by shows the 7-day change, so each board keeps one focus.
        $w = $r['week']; $board = $s['board'];
        $row = [$i + 1, player_link($r['name'], $s['clans']), ($r['level'] === null ? '–' : fmt($r['level'])) . ($board === 'level' ? week_change($w['level'], fmt($w['level'] ?? 0)) : '')];
        array_push($row, ...($maxing
            ? [($r['to_max'] === null ? '–' : ehp($r['to_max'])) . week_change($w['to_max'], ehp($w['to_max'] ?? 0), '−', ' EHP closer to max')]
            : [($r['value'] === null ? '–' : xp($r['value'])) . ($board === 'xp' ? week_change(round(($w['xp'] ?? 0) / 1e6), fmt(round(($w['xp'] ?? 0) / 1e6)) . 'M', '+', ' XP') : ''), ehp($r['ehp']) . ($board === 'ehp' ? week_change($w['ehp'], ehp($w['ehp'] ?? 0)) : '')]));
        if ($maxing) $row[] = $r['maxed_at'] === null ? '–' : '<time datetime="' . gmdate('Y-m-d\TH:i:s\Z', (int) ($r['maxed_at'] / 1000)) . '" title="First observed maxed · UTC">' . gmdate('j M Y', (int) ($r['maxed_at'] / 1000)) . '</time>';
        $cells[] = $row;
    }
    $headers = $maxing ? ['Rank', 'Name', 'Level', 'EHP remaining', 'Maxing date'] : ['Rank', 'Name', 'Level', 'Exp', 'EHP'];
    $sorted = ['level' => 'Level', 'max' => 'EHP remaining', 'ehp' => 'EHP', 'xp' => 'Exp'][$s['board']];
    $body = !$rows && $s['skiller'] ? empty_message('No confirmed matches yet.') : table($headers, $cells, HISCORE_BOARDS[$s['board']] . ' hiscores', $sorted, $maxing ? 'ascending' : 'descending');
    return '<div class="page-heading hiscores-heading"><h1>Hiscores</h1>' . hiscore_controls($s) . '</div><section class="panel">' . $body . '</section>';
}
function records_view(array $s, array $d): string {
    $html = '<div class="page-heading"><h1>Records</h1></div><nav class="record-nav" aria-label="Record collection">';
    foreach (['achievements' => ['Achievements', '/records'], 'rank' => ['Rank one', '/records?section=rank'], 'xp' => ['XP gains', '/records/Overall']] as $section => [$label, $href]) $html .= '<a href="' . e($href) . '"' . ($s['section'] === $section ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    return $html . '</nav>' . match ($s['section']) {'achievements' => achievements_view($s['clans']), 'rank' => rank_history_view($s['now']), 'xp' => xp_records_view($s, $d)};
}
function xp_records_view(array $s, array $d): string {
    $html = '<div class="records-skill-filter">' . skill_select($s) . '</div><section class="panel"><div class="panel-heading"><h2>' . icon($s['skill']) . e($s['skill']) . '</h2></div><div class="boards">';
    foreach (['6h' => '6 hour', '24h' => '24 hour'] as $period => $label) {
        $best = $d[$period][0] ?? null;
        $when = fn($r) => gmdate('j M Y H:i:s', (int) ($r['start_at'] / 1000)) . ' – ' . gmdate('j M Y H:i:s', (int) ($r['end_at'] / 1000)) . ' UTC · ' . round(($r['end_at'] - $r['start_at']) / 3600000, 2) . ' hours between logout updates' . ($period === '6h' ? ' · Single logout update' : '');
        $html .= '<div><div class="holder"><small>' . $label . ' record</small>' . ($best ? '<strong title="' . e($when($best)) . '">' . xp($best['gain']) . '</strong>' . player_link($best['name'], $s['clans']) : empty_message('Nobody yet.')) . '</div>' . ($d[$period] ? table(['#', 'Player', 'XP'], array_map(fn($r, $i) => [$i + 1, player_link($r['name'], $s['clans']), '<span class="gain" title="' . e($when($r)) . '">' . xp($r['gain']) . '</span>'], $d[$period], array_keys($d[$period])), $s['skill'] . ' ' . $label . ' records') : '') . '</div>';
    }
    return $html . '</div><p class="muted">6h: largest single logout update between observations at most 6 hours apart. 24h: gains between logout updates at most 24 hours apart. Times are when Labs received updates.</p></section>';
}
function players_view(array $s, array $d): string {
    $html = '<div class="page-heading"><h1>Players</h1></div>';
    foreach (['observed' => ['Daily observed players', 'Tracked accounts observed per completed UTC day', 'No complete days yet.'], 'online' => ['Players online', 'Sampled at midnight UTC', 'Waiting for the first midnight snapshot.']] as $key => [$title, $note, $pending]) $html .= '<section class="panel graph-panel"><div class="panel-heading"><h2>' . e($title) . '</h2></div><p class="muted">' . e($note) . '</p>' . ($d[$key] ? chart([['points' => $d[$key]]], $title, 'players', $s, true) : empty_message($pending)) . '</section>';
    return $html;
}
function guides_view(array $s, array $d): string {
    $rows = $d[$s['skill']] ?? throw new RuntimeException('Missing skill guide');
    $cells = array_map(fn($r) => [e($r['min_level'] . '–' . $r['max_level']), e(trim(preg_replace('~https?://\S+~', '', $r['method']))), fmt($r['rate'])], $rows);
    return '<div class="page-heading"><h1>EHP</h1></div>' . skill_tabs($s, true) . '<section class="panel guide-copy"><div class="panel-heading"><h2>' . icon($s['skill']) . e($s['skill']) . '</h2></div>' . ($cells ? table(['Level', 'Method', 'XP/h'], $cells, $s['skill'] . ' rates') : empty_message('Passive combat XP.')) . '</section><p class="guide-credit">Based on 2004Tracker by ' . player_link('pasta', $s['clans']) . '</p>';
}
function youtube_url(array $v): string {return e('https://www.youtube.com/watch?v=' . rawurlencode($v['video_id']));}
function youtube_thumb(array $v): string {return e('https://i.ytimg.com/vi/' . rawurlencode($v['video_id']) . '/mqdefault.jpg');}
function youtube_age(int $at, int $now): string {
    $days = intdiv($now - $at, 86400000);
    if ($days < 2) return ago($at, $now);
    [$n, $unit] = $days >= 365 ? [intdiv($days, 365), 'year'] : ($days >= 30 ? [intdiv($days, 30), 'month'] : ($days >= 7 ? [intdiv($days, 7), 'week'] : [$days, 'day']));
    return "$n $unit" . ($n > 1 ? 's' : '') . ' ago';
}
function youtube_view(array $s, array $d): string {
    $html = '<div class="page-heading"><div><h1>YouTube</h1><span class="muted">Help the community grow! Likes, subscribes, and comments boost 2004scape content to more people on YouTube!</span></div></div><section class="panel">' . ($d['videos'] ? '' : empty_message('No videos yet.')) . '<div class="yt-grid">';
    foreach ($d['videos'] as $v) {
        $html .= '<article><a href="' . youtube_url($v) . '" target="_blank" rel="noopener"><img src="' . youtube_thumb($v) . '" alt="" loading="lazy" width="320" height="180"></a><div><h2><a href="' . youtube_url($v) . '" target="_blank" rel="noopener">' . e($v['title']) . '</a></h2><small><a href="https://www.youtube.com/channel/' . e($v['channel_id']) . '" target="_blank" rel="noopener">' . e($v['channel']) . '</a></small><small>' . fmt($v['views']) . ' views · <time title="' . gmdate('j M Y H:i', (int) ($v['published_at'] / 1000)) . ' UTC">' . youtube_age($v['published_at'], $s['now']) . '</time></small></div></article>';
    }
    $link = fn(int $page, string $text) => '<a href="' . url('/youtube', $page === 1 ? [] : ['page' => $page]) . '"' . ($page === $d['page'] && is_numeric($text) ? ' aria-current="true"' : '') . '>' . $text . '</a>';
    $first = ($d['page'] - 1) * YOUTUBE_PAGE;
    $html .= '</div><div class="pager"><small>' . fmt($first + min(1, count($d['videos']))) . '–' . fmt($first + count($d['videos'])) . ' of ' . fmt($d['total']) . ' videos</small><nav class="segments" aria-label="Pages">' . ($d['page'] > 1 ? $link($d['page'] - 1, '‹ Newer') : '');
    foreach (range(1, $d['pages']) as $page) $html .= $link($page, (string) $page);
    return $html . ($d['page'] < $d['pages'] ? $link($d['page'] + 1, 'Older ›') : '') . '</nav></div></section>';
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
    $metric = CLAN_METRICS[$s['metric']]; $xp = $s['metric'] === 'xp'; $lines = []; $rows = [];
    foreach ($d as $key => ['members' => $members, 'levels' => $levels]) {
        $total = $xp ? array_sum(array_map(fn($m) => end($m['series'])['xp'], $members)) : (in_array(null, array_column($members, 'level'), true) || !$members ? null : array_sum(array_column($members, 'level')));
        $gain = array_sum(array_map(fn($m) => end($m['series'])[$xp ? 'xp' : 'levels'] - $m['series'][0][$xp ? 'xp' : 'levels'], $members));
        $lines[] = ['label' => CLANS[$key]['name'], 'color' => CLANS[$key]['color'], 'points' => array_map(fn($p) => ['at' => $p['at'], 'value' => $p['value'] * ($s['scale'] === 'total' ? count($members) : 1)], clan_average($members, $s['metric'], true))];
        $rows[] = ['key' => $key, 'members' => count($members), 'level' => clan_total_level($levels, 'total'), 'average' => $total === null || !$members ? null : $total / count($members), 'total' => $total, 'gain' => $gain / ($s['scale'] === 'average' && $members ? count($members) : 1)];
    }
    $average = $s['scale'] === 'average'; $sorted = $xp ? ($average ? 'Average XP' : 'Total XP') : ($average ? 'Average total' : 'Clan level'); $sort = $average ? 'average' : ($xp ? 'total' : 'level');
    usort($rows, fn($a, $b) => ($a[$sort] === null) <=> ($b[$sort] === null) ?: $b[$sort] <=> $a[$sort]);
    $cells = array_map(fn($r, $i) => array_merge([$i + 1, clan_link($r['key']), fmt($r['members'])], $average ? [$r['average'] === null ? '–' : fmt($r['average'], $xp ? 0 : 1)] : ($xp ? [fmt($r['total'])] : [fmt($r['level']), $r['total'] === null ? '–' : fmt($r['total'])]), ['<span class="gain">' . ($r['gain'] < 0 ? '−' : '+') . fmt(abs($r['gain']), $average && !$xp ? 1 : 0) . '</span>']), $rows, array_keys($rows));
    $legend = '<ul class="legend">' . implode('', array_map(fn($key) => '<li style="--chart:' . e(CLANS[$key]['color']) . '">' . e(CLANS[$key]['name']) . '</li>', array_keys(CLANS))) . '</ul>';
    $controls = segments($s, 'scale', CLAN_SCALES, 'Average or total') . segments($s, 'metric', CLAN_METRICS, 'Metric') . segments($s, 'period', array_combine(array_keys(PERIODS), array_keys(PERIODS)), 'Time period');
    return '<div class="page-heading"><h1>Clans</h1></div><section class="panel graph-panel"><div class="panel-heading"><h2>' . CLAN_SCALES[$s['scale']] . ' ' . $metric . ' gained' . ($s['scale'] === 'average' ? ' per member' : '') . '</h2><div class="clan-controls">' . $controls . '</div></div>' . $legend . chart($lines, CLAN_SCALES[$s['scale']] . ' ' . $metric . ' gained', $metric, $s) . '</section><section class="panel">' . table(array_merge(['Rank', 'Clan', 'Members'], $xp ? [$sorted] : ($average ? ['Average total'] : ['Clan level', 'Clan total level']), [$xp ? 'XP gained' : 'Levels gained']), $cells, 'Clan hiscores', $sorted) . '</section>';
}
// The 2004 stats tab, read left to right.
const STATS_TAB = [1, 4, 15, 3, 17, 14, 2, 16, 11, 5, 18, 8, 6, 13, 12, 7, 10, 9, 21];
// Levels come from the clan's combined XP per skill, run through the XP curve with no 99 or 126 cap.
function clan_total_level(array $skills, string $scale): int {
    return array_sum(array_map(fn($sk) => virtual_level($sk[$scale]), $skills));
}
// $cells: type => [value, tooltip], laid out like the in-game stats tab.
function skill_grid(array $cells): string {
    $html = '<div class="level-grid">';
    foreach (STATS_TAB as $type) $html .= '<div class="level-slot" tabindex="0" title="' . e($cells[$type][1]) . '">' . icon(SKILLS[$type]) . '<b>' . $cells[$type][0] . '</b></div>';
    return $html . '</div>';
}
function clan_levels(array $s, array $skills, array $ehp): string {
    $total = $s['scale'] === 'total'; $sum = clan_total_level($skills, $s['scale']); $cells = [];
    foreach (STATS_TAB as $type) {
        $sk = $skills[$type] ?? null;
        $level = $sk ? virtual_level($total ? $sk['total'] : $sk['average']) : null;
        $cells[$type] = [$level === null ? '–' : fmt($level), SKILLS[$type] . ($sk ? ' · ' . fmt($total ? $sk['total'] : $sk['average']) . ' XP' . ($total ? '' : ' average') : ' · no data')];
    }
    $points = array_sum($ehp) / ($total || !$ehp ? 1 : count($ehp));
    return '<section class="panel clan-levels"><div class="panel-heading"><h2>Clan levels</h2>' . segments($s, 'scale', CLAN_SCALES, 'Total or average') . '</div>' . skill_grid($cells) . '<p class="muted clan-total">Total level <b>' . fmt($sum) . '</b> · ' . CLAN_SCALES[$s['scale']] . ' EHP <b>' . ehp($points) . '</b></p></section>';
}
// A chart with numeric x (hours, percentiles) rather than time; $marker draws a labelled vertical line.
// $lines: [['points' => [['x', 'y', 'tip'], ...], 'key' => ?string, 'color' => ?string], ...].
function plot(array $lines, string $name, float $xmax, array $xticks, callable $ylabel, float $ymin, ?array $marker = null): string {
    $ys = array_column(array_merge(...array_column($lines, 'points')), 'y'); $ymax = max($ys); if ($ymax == $ymin) $ymax++;
    $x = fn($v) => 62 + $v / $xmax * 650; $y = fn($v) => 185 - ($v - $ymin) * 148 / ($ymax - $ymin);
    $html = '<svg class="chart" viewBox="0 0 770 226" role="img" aria-label="' . e($name) . '"><g class="grid">';
    for ($i = 0; $i < 4; $i++) $html .= '<line x1="62" x2="730" y1="' . (37 + $i * 148 / 3) . '" y2="' . (37 + $i * 148 / 3) . '"/><text x="50" y="' . (41 + $i * 148 / 3) . '" text-anchor="end">' . e($ylabel($ymax - $i * ($ymax - $ymin) / 3)) . '</text>';
    $html .= '</g>' . ($marker ? '<g class="marker"><line x1="' . $x($marker[0]) . '" x2="' . $x($marker[0]) . '" y1="28" y2="190"/><text x="' . $x($marker[0]) . '" y="22" text-anchor="middle">' . e($marker[1]) . '</text></g>' : '');
    foreach ($lines as $l) {
        $html .= '<g' . (isset($l['key']) ? ' class="line-' . $l['key'] . '"' : '') . (isset($l['color']) ? ' style="--chart:' . e($l['color']) . '"' : '') . '><polyline class="xp-line" points="' . implode(' ', array_map(fn($p) => $x($p['x']) . ',' . $y($p['y']), $l['points'])) . '"/>';
        foreach ($l['points'] as $p) {
            $html .= chart_point($x($p['x']), $y($p['y']), $p['tip']);
        }
        $html .= '</g>';
    }
    foreach (array_keys($xticks) as $i => $v) $html .= '<text class="axis' . ($i % 2 ? ' minor-tick' : '') . '" x="' . $x($v) . '" y="215" text-anchor="' . ($i === 0 ? 'start' : ($i === count($xticks) - 1 ? 'end' : 'middle')) . '">' . e($xticks[$v]) . '</text>';
    return chart_frame($html . '</svg>');
}
const DAY_COLORS = ['weekday' => '#d9886f', 'saturday' => '#e0cf9c', 'sunday' => '#8fb4d9', 'holiday' => '#7fbf7f'];
function stats_view(array $s, array $d): string {
    $html = '<div class="page-heading"><h1>World stats</h1></div><div class="stats"><div><span>XP gained today</span><strong class="gain">+' . fmt($d['today']) . '</strong></div><div><span>Last 7 days</span><strong class="gain">+' . fmt($d['week']) . '</strong></div><div><span>Total XP</span><strong>' . fmt(array_sum($d['totals'])) . '</strong></div></div>';
    $html .= '<section class="panel graph-panel"><h2>XP gained per day</h2>' . chart([['points' => $d['daily']]], 'World XP gained per UTC day', 'XP', $s, true) . '</section>';
    $total = array_sum($d['skills']); $skills = array_filter($d['skills'], fn($v) => $v > 0); arsort($skills); $slots = '';
    // Rank movement against the previous 7 days, once a full previous week exists.
    $before = null;
    if ($d['previous'] !== null) {$previous = $d['previous']; arsort($previous); $before = array_flip(array_keys($previous));}
    $rank = 0;
    foreach ($skills as $type => $xp) {
        $move = $before === null || !isset($before[$type]) ? 0 : $before[$type] - $rank++;
        $arrow = $move > 0 ? '<i class="up" title="Up ' . $move . ' since last week">▲' . $move . '</i>' : ($move < 0 ? '<i class="down" title="Down ' . -$move . ' since last week">▼' . -$move . '</i>' : '');
        $slots .= '<div class="ehp-slot" tabindex="0" role="img" aria-label="' . e(SKILLS[$type] . ' · ' . fmt($xp) . ' XP · ' . fmt($xp / $total * 100, 1) . '%' . ($move ? ' · ' . ($move > 0 ? 'up ' : 'down ') . abs($move) : '')) . '" title="' . e(SKILLS[$type] . ' · ' . fmt($xp) . ' XP') . '">' . icon(SKILLS[$type]) . '<b>' . fmt($xp / $total * 100, 1) . '%' . $arrow . '</b></div>';
    }
    $cells = [];
    foreach (STATS_TAB as $type) {
        $week = $d['new99s'][7][$type] ?? 0; $month = $d['new99s'][30][$type] ?? 0;
        $cells[$type] = ['<span class="pair">' . fmt($d['nineties'][$type] ?? 0) . ($week ? '<small class="gain">+' . fmt($week) . '</small>' : '') . '</span>', SKILLS[$type] . ' · ' . fmt($d['nineties'][$type] ?? 0) . ' players with 99 · +' . fmt($week) . ' this week · +' . fmt($month) . ' this month'];
    }
    $html .= '<div class="stats-pair"><section class="panel"><h2>Popular skills</h2><p class="muted">Share of XP gained in the last 7 days.</p><div class="ehp-grid">' . $slots . '</div></section><section class="panel clan-levels"><h2>99s</h2><p class="muted">Tracked players with 99 in each skill.</p>' . skill_grid($cells) . '<p class="muted clan-total"><b>' . fmt(array_sum($d['nineties'])) . '</b> 99s · <b class="gain">+' . fmt(array_sum($d['new99s'][7])) . '</b> this week · <b>' . fmt($d['maxed']) . '</b> maxed</p></section></div>';
    $n = count($d['totals']); $sum = array_sum($d['totals']); $share = function (float $top) use ($d, $n, $sum) {$k = max(1, (int) round($n * $top / 100)); return array_sum(array_slice($d['totals'], 0, $k)) / $sum * 100;};
    // Log scale: the interesting part is the handful of players at the very top.
    $curve = array_map(fn($p) => ['x' => log10($p) + 2, 'y' => $share($p), 'tip' => 'Top ' . $p . '% hold ' . fmt($share($p), 1) . '% of XP'], [0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10, 20, 50, 100]);
    $html .= '<section class="panel graph-panel"><h2>Who holds the XP</h2>' . plot([['points' => $curve]], 'Share of XP held by the top players', 4, [0 => 'Top 0.01%', 1 => 'Top 0.1%', 2 => 'Top 1%', 3 => 'Top 10%', 4 => 'All players'], fn($v) => fmt($v) . '%', 0) . '</section>';
    $types = $s['day'] === 'all' ? ['all' => 'Average'] : DAY_TYPES; $lines = []; $legend = '';
    $holidays = 'Major US and European holidays: ' . implode(', ', holidays((int) gmdate('Y', (int) ($s['now'] / 1000))));
    foreach ($types as $key => $label) {
        $points = [];
        foreach ($d['online'][$key] ?? [] as $hour => $h) $points[] = ['x' => $hour, 'y' => $h['value'], 'tip' => sprintf('%s %02d:00 UTC · %s online (%d %s)', $label, $hour, fmt($h['value']), $h['days'], $h['days'] === 1 ? 'day' : 'days')];
        if ($points) $lines[] = ['points' => $points, 'key' => $key, 'color' => DAY_COLORS[$key] ?? null];
        // Checkboxes hide lines through CSS alone, so the page still needs no script.
        if ($key !== 'all') $legend .= '<label style="--chart:' . DAY_COLORS[$key] . '"' . ($key === 'holiday' ? ' title="' . e($holidays) . '"' : '') . '><input class="sr-only" type="checkbox" value="' . $key . '" checked' . ($points ? '' : ' disabled') . '><span>' . $label . ($points ? '' : ' (no days yet)') . '</span></label>';
    }
    $now = ($s['now'] % DAY_MS) / 3600000;
    $html .= '<section class="panel graph-panel day-lines"><div class="panel-heading"><h2>When the world plays</h2>' . segments($s, 'day', ONLINE_VIEWS, 'Online view') . '</div><p class="muted">Average players online by hour (UTC).</p>' . ($legend ? '<div class="legend">' . $legend . '</div>' : '') . ($lines ? plot($lines, 'Average players online by UTC hour', 24, [0 => '00:00', 6 => '06:00', 12 => '12:00', 18 => '18:00', 24 => '24:00'], fn($v) => fmt($v), min(array_column(array_merge(...array_column($lines, 'points')), 'y')), [$now, 'Now ' . gmdate('H:i', (int) ($s['now'] / 1000))]) : empty_message('No samples yet.')) . '</section>';
    return $html;
}
function clan_view(array $s, array $d): string {
    $clan = CLANS[$s['clan']]; $metric = CLAN_METRICS[$s['metric']]; $members = $d['members'];
    $about = array_filter([$clan['established'] === null ? null : 'Established ' . e($clan['established']), $clan['discord'] === null ? null : '<a href="' . e($clan['discord']) . '">Discord</a>', $clan['topic'] === null ? null : '<a href="https://lostcity.rs/t/' . $clan['topic'] . '">Forum thread</a>']);
    $html = '<div class="page-heading"><div><div class="eyebrow">Clan</div><h1>' . e($clan['name']) . clan_icon($s['clan']) . '</h1>' . ($about ? '<span class="muted">' . implode(' · ', $about) . '</span>' : '') . '</div></div>';
    $average = clan_average($members, $s['metric'], false); $gained = clan_average($members, $s['metric'], true);
    $value = fn($v) => $s['metric'] === 'ehp' ? ehp($v) : fmt($v);
    $html .= '<div class="stats"><div><span>Tracked members</span><strong>' . fmt(count($members)) . '</strong></div><div><span>Average ' . $metric . '</span><strong>' . ($average ? $value(end($average)['value']) : '–') . '</strong></div><div><span>Average gained</span><strong class="gain">' . ($gained ? '+' . $value(end($gained)['value']) : '–') . '</strong></div></div>';
    if ($videos = $d['videos']) {
        $first = array_shift($videos);
        $html .= '<section class="panel videos"><div class="video-main"><iframe src="https://www.youtube-nocookie.com/embed/' . e($first['video_id']) . '" title="' . e($first['title']) . '" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe><p>' . e($first['title']) . ' <small>' . e($first['channel']) . ' · shared ' . gmdate('j M Y', (int) ($first['shared_at'] / 1000)) . '</small></p></div><div class="video-list">';
        foreach ($videos as $v) $html .= '<a href="https://www.youtube.com/watch?v=' . e($v['video_id']) . '" target="_blank" rel="noopener"><img src="https://i.ytimg.com/vi/' . e($v['video_id']) . '/mqdefault.jpg" alt="" loading="lazy" width="320" height="180"><span>' . e($v['title']) . '</span><small>Shared ' . gmdate('j M Y', (int) ($v['shared_at'] / 1000)) . '</small></a>';
        $html .= '</div></section>';
    }
    $html .= '<div class="clan-split"><section class="panel graph-panel"><div class="panel-heading"><h2>Average ' . $metric . ' per member</h2><div class="clan-controls">' . segments($s, 'metric', CLAN_METRICS, 'Metric') . segments($s, 'period', array_combine(array_keys(PERIODS), array_keys(PERIODS)), 'Time period') . '</div></div>' . chart([['points' => $average]], 'Average ' . $metric, $metric, $s, false, 360) . '</section>' . clan_levels($s, $d['levels'], array_map(fn($m) => end($m['series'])['ehp'], $members)) . '</div>';
    usort($members, fn($a, $b) => end($b['series'])['ehp'] <=> end($a['series'])['ehp']);
    $cells = array_map(fn($m) => [player_link($m['name'], $s['clans']), $m['level'] === null ? '–' : fmt($m['level']), ehp(end($m['series'])['ehp']), '<span class="gain">+' . $value(end($m['series'])[$s['metric']] - $m['series'][0][$s['metric']]) . '</span>'], $members);
    return $html . '<section class="panel"><h2>Members · ' . fmt(count($members)) . '</h2>' . table(['Player', 'Total level', 'EHP', $metric . ' gained'], $cells, $clan['name'] . ' members', 'EHP') . '</section>';
}
function account_nav(array $s): string {
    if (auth_user() === null) return '<nav class="account-nav" aria-label="Account"><a href="/login">Login</a></nav>';
    return '<nav class="account-nav" aria-label="Account"><a href="/settings"' . ($s['view'] === 'settings' ? ' aria-current="page"' : '') . '>Settings</a></nav>';
}
function page_body(array $s, $d): string {
    return match ($s['view']) {'home' => home_view($s, $d), 'player' => player_view($s, $d), 'hiscores' => hiscores_view($s, $d), 'records' => records_view($s, $d), 'players' => players_view($s, $d), 'guides' => guides_view($s, $d), 'clans' => $s['clan'] === null ? clan_hiscores_view($s, $d) : clan_view($s, $d), 'stats' => stats_view($s, $d), 'youtube' => youtube_view($s, $d)};
}
function document(array $s, string $body, string $notice = ''): string {
    $title = $s['view'] === 'player' ? display_name($s['name']) : (($s['clan'] ?? null) !== null ? e(CLANS[$s['clan']]['name']) : (['settings' => 'Settings', 'error' => 'Error'] + array_merge(...array_values(NAV)))[$s['view']]);
    $meta = '';
    if (isset($s['meta'])) {
        [$description, $image] = $s['meta']; $origin = auth_origin('LABS_SITE_ORIGIN');
        $meta = '<meta name="description" content="' . e($description) . '"><meta property="og:site_name" content="Lost City Labs"><meta property="og:title" content="' . ($s['view'] === 'home' ? 'Lost City Labs' : $title . ' · Lost City Labs') . '"><meta property="og:description" content="' . e($description) . '"><meta property="og:url" content="' . e($origin . $s['canonical']) . '"><meta property="og:image" content="' . e($origin . $image) . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image"><meta name="theme-color" content="#dfa18b">';
    }
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . ' · Lost City Labs</title>' . $meta . '<link rel="icon" href="/assets/stats.png" sizes="25x24"><link rel="icon" href="/assets/touch-icon.png" sizes="200x200"><link rel="apple-touch-icon" href="/assets/touch-icon.png"><link rel="stylesheet" href="/style.css?v=35"><script src="/app.js?v=14" defer></script></head><body>' . $notice . (in_array($s['view'], ['records', 'player'], true) ? achievement_filter() : '') . '<div class="shell"><header class="masthead"><a class="brand" href="/"><img class="mark" src="/assets/stats.png" alt="" width="25" height="24"><span>Lost City <b>Labs</b></span></a>' . account_nav($s) . '</header><div class="layout"><aside class="sidebar">';
    foreach (NAV as $group => $links) {
        $html .= ($group === '' ? '' : '<div class="sidebar-heading">' . $group . '</div>') . '<nav aria-label="' . ($group ?: 'Home') . '">';
        foreach ($links as $view => $label) $html .= '<a href="' . (['home' => '/', 'records' => '/records', 'guides' => '/guides/ehp'][$view] ?? '/' . $view) . '"' . ($view === $s['view'] ? ' aria-current="page"' : '') . '>' . $label . '</a>';
        $html .= '</nav>';
    }
    return $html . '<a class="external" href="https://2004.lostcity.rs/title" target="_blank" rel="noopener">Lost City ↗</a></aside><main>' . $body . '</main></div><footer><span><a href="https://github.com/LostCityRS/Labs">Source code</a> · Inspired by anders\' original Lost City Labs ❤️</span><a class="footer-discord" href="https://discord.gg/yk4eC8jB8s">Discord</a></footer></div></body></html>';
}
