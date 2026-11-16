<?php
require __DIR__ . '/../src/views.php';
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
$file = json_decode(file_get_contents(__DIR__ . '/../src/polls.json'), true, flags: JSON_THROW_ON_ERROR);
$slugs = array_column($file['polls'], 'slug');
expect(count($slugs) === count(array_unique($slugs)), 'Poll slugs key the votes, so they must be unique');
foreach ($file['polls'] as $poll) {
    expect(preg_match('/^[a-z0-9-]{1,32}$/D', $poll['slug']) === 1 && count($poll['options']) >= 2 && count($poll['options']) <= 127, "Malformed poll {$poll['slug']}");
    foreach ($poll['options'] as $o) expect($o['label'] !== '' && is_file(__DIR__ . '/../public/assets/poll/' . $o['icon']), "Poll {$poll['slug']} option {$o['label']} needs a label and an existing icon");
}
$now = strtotime($file['start'] . ' UTC') * 1000 + 2 * DAY_MS;
$p = ['slug' => 'x', 'question' => 'A & B?', 'ends_at' => $now + 3 * 86400000 - 1, 'counts' => [113, 44], 'mine' => null, 'options' => [['label' => '10 imps', 'icon' => 'imp.png', 'qty' => 10], ['label' => 'Coins', 'icon' => 'coins_10000.png', 'qty' => 10000000]]];
$s = ['now' => $now, 'user' => null, 'csrf' => null];
$html = poll_panel($s, $p);
expect(str_contains($html, 'href="/login"') && !str_contains($html, 'type="radio"') && !str_contains($html, '%'), 'Logged-out visitors are sent to log in and see no results');
expect(str_contains($html, 'Ends in 3d') && str_contains($html, 'A &amp; B?') && str_contains($html, '<span class="qty ">10</span>') && str_contains($html, '<span class="qty m">10M</span>'), 'Panel shows the escaped question, stack counts and time left');
$html = poll_panel(['user' => ['id' => '1'], 'csrf' => 'token'] + $s, $p);
expect(substr_count($html, 'type="radio"') === 2 && str_contains($html, 'name="csrf" value="token"') && str_contains($html, 'name="poll" value="x"') && str_contains($html, 'Submit vote') && !str_contains($html, '%'), 'Logged-in voters get a CSRF-protected form and no results yet');
$html = poll_panel(['user' => ['id' => '1'], 'csrf' => 'token'] + $s, ['mine' => 1] + $p);
expect(str_contains($html, '<b>28%</b> of people agree with you, 72% disagree') && str_contains($html, '157 votes') && substr_count($html, 'mine') === 1 && !str_contains($html, '<form'), 'Voters see their agreement share and no form');
$three = ['mine' => 0, 'counts' => [1, 1, 2], 'options' => array_fill(0, 3, $p['options'][0])] + $p;
expect(str_contains(poll_panel(['user' => ['id' => '1'], 'csrf' => 'token'] + $s, $three), '<b>25%</b> of people agree with you<small>4 votes</small>'), 'Three-way polls have no disagree share');
expect(poll_ends($now + 5 * 3600000 - 1, $now) === 'Ends in 5h', 'The last two days count down in hours');
echo "Polls: file validity, logged-out/voting/voted rendering and escaping passed\n";
