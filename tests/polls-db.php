<?php
require __DIR__ . '/../src/lib.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test' || $db->query('SELECT COUNT(*) FROM poll_votes')->fetchColumn() != 0) throw new RuntimeException('Poll tests require a fresh isolated labs_records_test database');
function expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
function vote(PDO $db, array $post, int $now, ?string $user = '111', ?string $voter = null): ?string {
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $post; $_COOKIE = ['labs_session' => 'testsession']; session_id('testsession');
    file_put_contents(getenv('LABS_SESSION_PATH') . '/sess_testsession', ($user === null ? '' : 'user|' . serialize(['id' => $user])) . 'csrf|' . serialize('token') . ($voter === null ? '' : 'poll_voter|' . serialize($voter)));
    try {poll_routes($db, '/poll/vote', $now); return null;}
    catch (Throwable $e) {return get_class($e);}
    finally {if (session_status() === PHP_SESSION_ACTIVE) session_abort();}
}
putenv('LABS_SESSION_PATH=' . ($sessions = sys_get_temp_dir() . '/labs-poll-sessions-' . bin2hex(random_bytes(4)))); mkdir($sessions);
putenv('LABS_SITE_ORIGIN=http://localhost');
$_COOKIE = [];
$anonymous = poll_voter();
expect(preg_match('/^a:[a-f0-9]{18}$/D', $anonymous) === 1 && auth_user() === null, 'Anonymous voting creates a voter key, not a login');
session_write_close(); auth_session(true);
expect(poll_voter() === $anonymous, 'The anonymous voter key must survive another request');
$_SESSION['user'] = ['id' => '999'];
session_regenerate_id(true);
expect(poll_voter() === $anonymous, 'Signing in must not reset a browser\'s existing voter key');
session_abort();
$file = json_decode(file_get_contents(__DIR__ . '/../src/polls.json'), true, flags: JSON_THROW_ON_ERROR);
$slugs = array_column($file['polls'], 'slug'); $n = count($slugs);
$start = strtotime($file['start'] . ' UTC') * 1000; $week = 7 * DAY_MS; $now = $start + DAY_MS;
expect(current_poll($db, $start - 1) === null, 'No poll before the start date');
$first = $slugs[0]; $ok = ['csrf' => 'token', 'poll' => $first, 'choice' => '1'];
expect(current_poll($db, $start)['slug'] === $first && current_poll($db, $start + $week - 1)['ends_at'] === $start + $week, 'The first week runs the first poll until the boundary');
expect(vote($db, $ok, $now) === null && poll_results($db, $now, '111')['mine'] === 1, 'A logged-in vote must be saved');
expect(vote($db, ['choice' => '0'] + $ok, $now) === null && poll_results($db, $now, '111')['mine'] === 1, 'Voting twice must keep the first vote');
expect(vote($db, $ok, $now, null, $anonymous) === null && poll_results($db, $now, $anonymous)['mine'] === 1, 'Anonymous visitors can vote without signing in');
expect(vote($db, ['choice' => '0'] + $ok, $now, null, $anonymous) === null && poll_results($db, $now, $anonymous)['mine'] === 1, 'An anonymous browser cannot replace or duplicate its first vote');
expect(vote($db, ['csrf' => 'wrong'] + $ok, $now, null, $anonymous) === 'AuthorizationException', 'Anonymous voting still requires a valid CSRF token');
expect(vote($db, ['csrf' => 'wrong'] + $ok, $now, '222') === 'AuthorizationException', 'Voting requires the CSRF token');
expect(vote($db, ['choice' => '9'] + $ok, $now, '222') === 'InvalidArgumentException' && vote($db, ['choice' => 'x'] + $ok, $now, '222') === 'InvalidArgumentException', 'Unknown options are rejected');
expect(vote($db, ['poll' => $slugs[1]] + $ok, $now, '222') === 'InvalidArgumentException', 'Only the poll running this week accepts votes');
expect(vote($db, ['choice' => '1'] + $ok, $now, '222') === null && vote($db, ['choice' => '0'] + $ok, $now, '333') === null, 'Other users can vote');
$r = poll_results($db, $now, null);
expect($r['counts'] === [1, 3] && $r['mine'] === null && poll_results($db, $now, '333')['mine'] === 0, 'Results count each option and only mark the viewer\'s own vote');
for ($i = 1; $i < $n; $i++) expect(current_poll($db, $start + $i * $week)['slug'] === $slugs[$i], 'Polls run in file order');
$loop = $start + $n * $week;
expect(current_poll($db, $loop)['slug'] === $first && poll_results($db, $loop, '111')['counts'] === [1, 3], 'When the list runs out it loops, keeping earlier votes');
expect(vote($db, ['choice' => '0'] + $ok, $loop, '111') === null && poll_results($db, $loop, '111')['counts'] === [1, 3], 'Someone who already voted cannot vote again on the repeat');
// Appending a poll mid-loop must neither swap the running poll nor wait behind the repeats.
$appended = sys_get_temp_dir() . '/labs-polls-appended.json';
file_put_contents($appended, json_encode(['polls' => [...$file['polls'], ['slug' => 'new-poll'] + $file['polls'][0]]] + $file));
expect(current_poll($db, $loop + DAY_MS, $appended)['slug'] === $first, 'Adding a poll must not change the poll already running');
expect(current_poll($db, $loop + $week, $appended)['slug'] === 'new-poll', 'A new poll runs next, before older polls repeat');
expect(current_poll($db, $loop + 2 * $week, $appended)['slug'] === $slugs[1], 'Then the longest-rested poll repeats');
file_put_contents($appended, json_encode(['polls' => array_slice($file['polls'], 2)] + $file));
try {current_poll($db, $loop + 2 * $week, $appended); throw new LogicException('Removing the running poll must fail explicitly');}
catch (RuntimeException $e) {expect(str_contains($e->getMessage(), "{$slugs[1]} is running this week"), 'The error must name the missing running poll');}
$db->prepare('INSERT INTO poll_votes VALUES (?, ?, ?, ?)')->execute([$first, '444', 5, $now]);
try {poll_results($db, $now, null); throw new LogicException('Votes for a removed option must fail explicitly');}
catch (RuntimeException $e) {expect(str_contains($e->getMessage(), 'missing option 5'), 'The error must name the missing option');}
echo "Polls DB: anonymous/session/account voting, duplicate/CSRF/option/week rejection, results, scheduled rotation, looping, mid-loop additions and removal errors passed\n";
