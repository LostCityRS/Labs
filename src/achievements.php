<?php
function achievement_id(array $r): string {return $r['kind'] . ':' . $r['type'] . ':' . $r['goal'];}
function achievement_catalog(): array {
    $slot = fn($kind, $type, $goal, $label, $tier, $art = null) => compact('kind', 'type', 'goal', 'label', 'tier', 'art');
    $rows = [$slot('total-level', 0, 1000, 'First 1,000 total level', '1K'), $slot('total-level', 0, 1881, 'First to max (1,881 total level)', 'MAX', 'max-crest.svg'), $slot('combat', 1, 126, 'First 126 combat', '126')];
    foreach ([50 => 'bronze', 100 => 'steel', 200 => 'mithril', 500 => 'rune', 1000 => 'dragon'] as $millions => $metal) {
        $tier = $millions === 1000 ? '1B' : $millions . 'M';
        $rows[] = $slot('total-xp', 0, $millions * 1000000, "First $tier total XP", $tier, "$metal-med.png");
    }
    foreach (SKILLS as $type => $skill) if ($type !== 0) {
        $rows[] = $slot('99', $type, 99, "First 99 $skill", '99');
        $rows[] = $slot('skill-xp', $type, 200000000, "First 200M $skill XP", '200M');
    }
    return array_combine(array_map('achievement_id', $rows), $rows);
}
// These firsts predate Labs tracking; their dates come from the community history, not XP samples.
function historical_records(): array {
    static $records;
    if ($records !== null) return $records;
    $rows = json_decode(file_get_contents(__DIR__ . '/historical-records.json') ?: throw new RuntimeException('Historical records could not be read'), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Historical records must be a list');
    $catalog = achievement_catalog(); $seen = [];
    foreach ($rows as &$r) {
        foreach (['kind', 'type', 'goal', 'name', 'date', 'source', 'note'] as $field) if (!array_key_exists($field, $r)) throw new RuntimeException("Historical record missing $field");
        if (!is_string($r['kind']) || !is_int($r['type']) || !is_int($r['goal']) || !is_string($r['name']) || !preg_match('/^[a-z0-9_]{1,12}$/D', $r['name']) || !is_string($r['date']) || !is_int($r['source']) || $r['source'] < 1 || !is_string($r['note'])) throw new RuntimeException('Invalid historical record fields');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $r['date'], new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $r['date']) throw new RuntimeException('Invalid historical record date: ' . $r['date']);
        $r['at'] = $date->getTimestamp() * 1000;
        $id = achievement_id($r);
        if ($r['kind'] === 'rank') {
            if ($r['type'] !== 0 || $r['goal'] !== 1) throw new RuntimeException('Invalid historical rank record');
            $id .= ':' . $r['date'];
        } elseif (!isset($catalog[$id])) throw new RuntimeException("Unknown historical achievement $id");
        if (isset($seen[$id])) throw new RuntimeException("Duplicate historical achievement $id");
        $seen[$id] = true;
    }
    unset($r);
    return $records = $rows;
}
function record_achievements(): array {
    $catalog = achievement_catalog();
    foreach (historical_records() as $r) if ($r['kind'] !== 'rank') $catalog[achievement_id($r)] += $r;
    return $catalog;
}
function rank_reigns(int $now): array {
    $rows = array_values(array_filter(historical_records(), fn($r) => $r['kind'] === 'rank'));
    if (!$rows) throw new RuntimeException('Rank history is empty');
    usort($rows, fn($a, $b) => $a['at'] <=> $b['at']);
    foreach ($rows as $i => &$r) {
        $r['ongoing'] = $i === count($rows) - 1;
        $r['end'] = $r['ongoing'] ? $now : $rows[$i + 1]['at'];
        if ($r['end'] < $r['at']) throw new RuntimeException('Rank history ends before it starts');
        $r['days'] = intdiv($r['end'] - $r['at'], 86400000);
        $r['height'] = max(1, ($r['end'] - $r['at']) / 86400000 * 1.1);
    }
    unset($r);
    return $rows;
}
function achievement_title(array $r): string {
    $lines = [$r['label']];
    if (!isset($r['name'])) $lines[] = 'No documented holder.';
    else {
        $lines[] = html_entity_decode(display_name($r['name']), ENT_QUOTES, 'UTF-8');
        if (isset($r['reigns'])) foreach ($r['reigns'] as $reign) $lines[] = gmdate('j M Y', (int) ($reign['at'] / 1000)) . ' – ' . gmdate('j M Y', (int) ($reign['end'] / 1000)) . ' · ' . $reign['days'] . ' days' . ($reign['ongoing'] ? ' so far' : '');
        else $lines[] = gmdate('j M Y', (int) ($r['at'] / 1000));
        if ($r['note'] !== '') $lines[] = $r['note'];
    }
    return implode("\n", $lines);
}
function achievement_icon(array $r): string {
    $art = $r['art'] === null ? icon(SKILLS[$r['type']]) : '<img src="/assets/records/' . e($r['art']) . '" width="32" height="32" alt="">';
    if (!isset($r['name'])) $art = '<span class="record-silhouette">' . $art . '</span>';
    $title = e(achievement_title($r));
    return '<span class="achievement-icon" role="img" tabindex="0" title="' . $title . '" aria-label="' . $title . '"><span class="achievement-art">' . $art . '</span><small>' . e($r['tier']) . '</small></span>';
}
function has_historical_record(string $name): bool {
    foreach (historical_records() as $r) if ($r['name'] === $name) return true;
    return false;
}
function historical_badges(string $name, int $now): string {
    $rows = array_filter(record_achievements(), fn($r) => isset($r['name']) && $r['name'] === $name);
    uasort($rows, fn($a, $b) => ($a['kind'] === 'total-xp') <=> ($b['kind'] === 'total-xp') ?: $a['at'] <=> $b['at']);
    $reigns = array_values(array_filter(rank_reigns($now), fn($r) => $r['name'] === $name));
    if ($reigns) array_unshift($rows, ['label' => 'Rank 1 overall', 'type' => 0, 'tier' => '', 'art' => 'crown.svg', 'reigns' => $reigns] + $reigns[0]);
    return $rows ? '<div class="historical-badges" aria-label="Historical records">' . implode('', array_map('achievement_icon', $rows)) . '</div>' : '';
}
function achievement_filter(): string {
    return '<svg class="record-filter" aria-hidden="true" width="0" height="0"><defs><filter id="record-silhouette" x="-15%" y="-15%" width="130%" height="130%" color-interpolation-filters="sRGB"><feMorphology in="SourceAlpha" operator="dilate" radius="0.7" result="edge"/><feFlood flood-color="#68513b"/><feComposite in2="edge" operator="in" result="outline"/><feFlood flood-color="#030202"/><feComposite in2="SourceAlpha" operator="in" result="fill"/><feMerge><feMergeNode in="outline"/><feMergeNode in="fill"/></feMerge></filter></defs></svg>';
}
function achievements_view(): string {
    $html = '<section class="panel achievement-collection"><div class="achievement-grid" aria-label="Achievements">';
    foreach (record_achievements() as $r) $html .= '<div class="achievement-tile">' . achievement_icon($r) . (isset($r['name']) ? player_link($r['name'], []) : '<span class="record-unclaimed" aria-label="No documented holder">—</span>') . '</div>';
    return $html . '</div></section>';
}
function rank_history_view(int $now): string {
    $html = '<h2 class="rank-history-heading">Time at rank one</h2><section class="panel"><div class="rank-timeline">';
    foreach (rank_reigns($now) as $r) {
        $title = e(achievement_title(['label' => 'Rank 1 overall', 'reigns' => [$r]] + $r));
        $html .= '<span class="reign-cell" role="img" tabindex="0" title="' . $title . '" aria-label="' . $title . '" style="height:' . $r['height'] . 'px">' . ($r['height'] >= 30 ? '<span>' . display_name($r['name']) . '</span><b>' . $r['days'] . ' days' . ($r['ongoing'] ? ' so far' : '') . '</b>' : '') . '</span>';
    }
    return $html . '</div></section>';
}
