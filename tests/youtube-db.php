<?php
require __DIR__ . '/../src/lib.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test') throw new RuntimeException('YouTube integration tests require labs_records_test');
function youtube_expect(bool $ok, string $why): void {if (!$ok) throw new RuntimeException($why);}
$db->exec('DELETE FROM youtube_videos'); $db->exec('DELETE FROM youtube_feed_failures');
$entry = fn(string $id, string $title, string $published = '2026-11-20T10:00:00+00:00') => "<entry><yt:videoId>$id</yt:videoId><title>$title</title><author><name>Pk Like Elve</name></author><published>$published</published><media:group><media:description>Play https://2004.lostcity.rs/</media:description><media:community><media:statistics views=\"42\"/></media:community></media:group></entry>";
$xml = fn(string ...$entries) => '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/">' . implode('', $entries) . '</feed>';
$elve = 'UCO5AUI4v0z8dknNlEZeDF7Q';
$down = fn(string $id) => $id === $elve ? throw new RuntimeException('feed fetch failed: HTTP/1.1 404 Not Found') : $xml();
$at = 1800000000000;
refresh_youtube_videos($db, $at, $down);
refresh_youtube_videos($db, $at + 23 * 3600000, $down);
youtube_expect(fetch_rows($db, 'SELECT since, error FROM youtube_feed_failures')[0] == ['since' => $at, 'error' => 'feed fetch failed: HTTP/1.1 404 Not Found'], 'A failing feed must be remembered from its first failure without failing the refresh');
try {
    refresh_youtube_videos($db, $at + 86400000, $down);
    youtube_expect(false, 'A feed failing for a day must fail the refresh');
} catch (RuntimeException $e) {
    youtube_expect(str_contains($e->getMessage(), '@PkLikeElve feed failing since 15 Jan 08:00 UTC: feed fetch failed: HTTP/1.1 404 Not Found'), "Unexpected failure message: {$e->getMessage()}");
}
refresh_youtube_videos($db, $at + 86400000, fn(string $id) => $xml(...($id === $elve ? [$entry('aaaaaaaaaaa', 'Any upload'), $entry('bbbbbbbbbbb', 'Lost Citizens Scape: Mining')] : [])));
youtube_expect(fetch_rows($db, 'SELECT COUNT(*) AS n FROM youtube_feed_failures')[0]['n'] == 0, 'A recovered feed must clear its failure');
youtube_expect(array_column(fetch_rows($db, 'SELECT video_id FROM youtube_videos'), 'video_id') === ['aaaaaaaaaaa'], 'Recovered feeds must list their uploads through youtube_listed');
$feeds = fn(string ...$entries) => fn(string $id) => $xml(...($id === $elve ? $entries : []));
refresh_youtube_videos($db, $at, $feeds($entry('ccccccccccc', 'Newest', '2026-11-22T10:00:00+00:00'), $entry('ddddddddddd', 'Deleted soon', '2026-11-21T10:00:00+00:00'), $entry('aaaaaaaaaaa', 'Any upload')));
refresh_youtube_videos($db, $at, $feeds($entry('eeeeeeeeeee', 'Re-upload', '2026-11-23T10:00:00+00:00'), $entry('ccccccccccc', 'Newest', '2026-11-22T10:00:00+00:00'), $entry('fffffffffff', 'Oldest left', '2026-11-20T12:00:00+00:00')));
youtube_expect(array_column(fetch_rows($db, 'SELECT video_id FROM youtube_videos ORDER BY video_id'), 'video_id') === ['aaaaaaaaaaa', 'ccccccccccc', 'eeeeeeeeeee', 'fffffffffff'], 'A video deleted from between its feed\'s oldest and newest entries must be removed, while videos older than the feed stay');
try {
    refresh_youtube_videos($db, $at, fn(string $id) => $id === $elve ? '<!DOCTYPE html><p>Before you continue to YouTube' : $xml());
    youtube_expect(false, 'A feed that is not XML must fail the refresh at once');
} catch (RuntimeException $e) {
    youtube_expect(str_contains($e->getMessage(), '@PkLikeElve: feed is not XML'), "Unexpected failure message: {$e->getMessage()}");
}
$db->exec('DELETE FROM youtube_videos');
foreach (['kept1111111', 'gone1111111', 'flaky000022', 'later111111'] as $v) $db->prepare("INSERT INTO youtube_videos (video_id, channel_id, channel, title, description, views, published_at) VALUES (?, ?, 'Pk Like Elve', '2004scape', '', 0, 0)")->execute([$v, $elve]);
$hour = fn(string $v) => (int) fetch_rows($db, 'SELECT CRC32(?) % 24 AS h', [$v])[0]['h'];
youtube_expect($hour('kept1111111') === $hour('gone1111111') && $hour('gone1111111') === $hour('flaky000022') && $hour('later111111') !== $hour('kept1111111'), 'Fixture videos must share one check hour except later111111');
$checked = [];
sweep_youtube_videos($db, $hour('kept1111111') * 3600000, function (string $v) use (&$checked) {
    $checked[] = $v;
    return $v === 'flaky000022' ? throw new RuntimeException("YouTube oEmbed for $v failed: timed out") : $v === 'gone1111111';
});
sort($checked);
youtube_expect($checked === ['flaky000022', 'gone1111111', 'kept1111111'], 'A run must check only the videos assigned to its hour');
youtube_expect(array_column(fetch_rows($db, 'SELECT video_id FROM youtube_videos ORDER BY video_id'), 'video_id') === ['flaky000022', 'kept1111111', 'later111111'], 'A deleted or private video must be removed, while available, failed and unchecked ones stay');
try {
    sweep_youtube_videos($db, $hour('flaky000022') * 3600000, fn(string $v) => throw new RuntimeException("YouTube oEmbed for $v failed: blocked"));
    youtube_expect(false, 'A run where every check fails must be reported');
} catch (RuntimeException $e) {
    youtube_expect(str_contains($e->getMessage(), 'YouTube video checks all failed: YouTube oEmbed for flaky000022 failed: blocked'), "Unexpected failure message: {$e->getMessage()}");
}
echo "YouTube: feed failures, recovery, listing, deleted uploads and daily checks passed\n";
