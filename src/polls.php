<?php
const POLL_MS = 7 * DAY_MS;

// Each UTC week from "start" gets one poll, recorded the first time that week is viewed, so editing polls.json never swaps a running poll.
// Unplayed polls go first in file order, then the longest-rested one repeats; votes are keyed by slug, so a repeat keeps its history.
// Never reorder an existing poll's options: votes store the option's position.
function current_poll(PDO $db, int $now, string $path = __DIR__ . '/polls.json'): ?array {
    $file = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $start = strtotime($file['start'] . ' UTC') * 1000;
    if ($now < $start) return null;
    $week = $start + intdiv($now - $start, POLL_MS) * POLL_MS;
    $polls = array_column($file['polls'], null, 'slug');
    $slug = fetch_rows($db, 'SELECT poll FROM poll_weeks WHERE week_at = ?', [$week])[0]['poll'] ?? null;
    if ($slug === null) {
        $ran = array_column(fetch_rows($db, 'SELECT poll, MAX(week_at) AS at FROM poll_weeks GROUP BY poll'), 'at', 'poll');
        $next = array_keys($polls);
        usort($next, fn($a, $b) => ($ran[$a] ?? -1) <=> ($ran[$b] ?? -1));
        $db->prepare('INSERT INTO poll_weeks (week_at, poll) VALUES (?, ?) ON DUPLICATE KEY UPDATE week_at = week_at')->execute([$week, $next[0]]);
        error_log("Labs: poll {$next[0]} scheduled for week $week");
        return current_poll($db, $now, $path); // re-read, so a concurrent first view cannot show a different poll
    }
    return ($polls[$slug] ?? throw new RuntimeException("Poll $slug is running this week but missing from polls.json")) + ['ends_at' => $week + POLL_MS];
}

function poll_voter(): string {
    auth_session(true);
    return $_SESSION['poll_voter'] ??= auth_user()['id'] ?? 'a:' . bin2hex(random_bytes(9));
}

function poll_results(PDO $db, int $now, ?string $voter): ?array {
    if (!($poll = current_poll($db, $now))) return null;
    $poll['counts'] = array_fill(0, count($poll['options']), 0);
    $poll['mine'] = null;
    foreach (fetch_rows($db, 'SELECT choice, COUNT(*) AS n, MAX(discord_id = ?) AS mine FROM poll_votes WHERE poll = ? GROUP BY choice', [$voter, $poll['slug']]) as $row) {
        if (!isset($poll['counts'][$row['choice']])) throw new RuntimeException("Poll {$poll['slug']} has votes for missing option {$row['choice']}");
        $poll['counts'][$row['choice']] = (int) $row['n'];
        if ($row['mine']) $poll['mine'] = (int) $row['choice'];
    }
    return $poll;
}

function poll_routes(PDO $db, string $path, int $now): bool {
    if ($path !== '/poll/vote' || $_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    header('Cache-Control: private, no-store');
    $voter = poll_voter();
    require_csrf($_POST);
    $poll = current_poll($db, $now);
    if (!$poll || parameter($_POST, 'poll') !== $poll['slug']) throw new InvalidArgumentException('This poll has ended.');
    $choice = filter_var(parameter($_POST, 'choice'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => count($poll['options']) - 1]]);
    if ($choice === false) throw new InvalidArgumentException('Pick one of the options.');
    // A repeated submit (double click, back button) keeps the first vote.
    $vote = $db->prepare('INSERT INTO poll_votes (poll, discord_id, choice, voted_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE poll = poll');
    $vote->execute([$poll['slug'], $voter, $choice, $now]);
    error_log("Labs: poll {$poll['slug']} vote $choice by $voter" . ($vote->rowCount() ? '' : ' ignored, already voted'));
    header('Location: /#poll', true, 303);
    return true;
}

// In-game stack colours: yellow below 100K, white below 10M, green from there.
function poll_qty(int $qty): string {
    [$class, $text] = $qty >= 10000000 ? ['m', intdiv($qty, 1000000) . 'M'] : ($qty >= 100000 ? ['k', intdiv($qty, 1000) . 'K'] : ['', (string) $qty]);
    return '<span class="qty ' . $class . '">' . $text . '</span>';
}
function poll_ends(int $at, int $now): string {
    $hours = (int) ceil(($at - $now) / 3600000);
    return 'Ends in ' . ($hours < 48 ? $hours . 'h' : ceil($hours / 24) . 'd');
}
function poll_panel(array $s, ?array $p): string {
    if ($p === null) return '';
    $voting = $p['mine'] === null;
    $options = '';
    foreach ($p['options'] as $i => $o) {
        $body = '<span class="poll-icon"><img src="/assets/poll/' . e($o['icon']) . '" alt="">' . (isset($o['qty']) ? poll_qty($o['qty']) : '') . '</span><span>' . e($o['label']) . '</span>';
        $options .= $voting ? '<label class="poll-option"><input type="radio" name="choice" value="' . $i . '" required>' . $body . '</label>' : '<div class="poll-option' . ($p['mine'] === $i ? ' mine' : '') . '">' . $body . '</div>';
    }
    $html = '<section class="panel community-poll" id="poll"><div class="panel-heading"><h2>Community poll</h2><small>' . poll_ends($p['ends_at'], $s['now']) . '</small></div>';
    $group = '<fieldset><legend>' . e($p['question']) . '</legend>' . $options . '</fieldset>';
    if ($voting) return $html . '<form class="poll" method="post" action="/poll/vote"><input type="hidden" name="csrf" value="' . e($s['csrf']) . '"><input type="hidden" name="poll" value="' . e($p['slug']) . '">' . $group . '<button type="submit">Submit vote</button></form></section>';
    $total = array_sum($p['counts']);
    $agree = (int) round($p['counts'][$p['mine']] / $total * 100);
    return $html . $group . '<p class="poll-summary"><b>' . $agree . '%</b> of people agree with you' . (count($p['options']) === 2 ? ', ' . (100 - $agree) . '% disagree' : '') . '<small>' . fmt($total) . ' vote' . ($total === 1 ? '' : 's') . '</small></p></section>';
}
