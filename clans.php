<?php
// established/discord/topic are null when unknown; rosters and extra videos live in clan_roster and clan_videos.
const CLANS = [
    'crandor' => ['name' => 'Crandor', 'icon' => '/assets/clans/crandor.png', 'color' => '#d7b84a', 'established' => 'at Lost City launch', 'discord' => null, 'topic' => 13384],
    'soul' => ['name' => 'Soul', 'icon' => '/assets/clans/soul.png', 'color' => '#00c2ee', 'established' => 'January 2025', 'discord' => 'https://discord.gg/eD5n4NZTue', 'topic' => 8285],
    'lit' => ['name' => 'LiT', 'icon' => '/assets/clans/lit.png', 'color' => '#e0614a', 'established' => null, 'discord' => null, 'topic' => null],
    'aggression' => ['name' => 'Aggression', 'icon' => '/assets/clans/aggression.png', 'color' => '#a98be0', 'established' => null, 'discord' => null, 'topic' => null],
];
const CLAN_METRICS = ['ehp' => 'EHP', 'xp' => 'XP'];
const CLAN_SCALES = ['average' => 'Average', 'total' => 'Total'];
const CLAN_STEPS = ['24h' => 24, '7d' => 28, '30d' => 30];

function clan_options(array $params, bool $hiscores): array {
    $metric = parameter($params, 'metric', 'ehp');
    if (!isset(CLAN_METRICS[$metric])) throw new InvalidArgumentException("Unknown metric: $metric");
    $scale = $hiscores ? parameter($params, 'scale', 'average') : 'average';
    if (!isset(CLAN_SCALES[$scale])) throw new InvalidArgumentException("Unknown scale: $scale");
    return compact('metric', 'scale');
}

// Known clan rosters tag characters until their owner picks a clan (or None) in Settings.
function clan_map(PDO $db): array {
    $clans = [];
    foreach (fetch_rows($db, 'SELECT name, clan FROM clan_roster ORDER BY clan') as $row) $clans[$row['name']][] = $row['clan'];
    foreach (fetch_rows($db, 'SELECT name, clan FROM character_clans WHERE clan IS NOT NULL') as $row) {
        if ($row['clan'] !== 'none' && !isset(CLANS[$row['clan']])) throw new RuntimeException('Unknown stored clan');
        $clans[$row['name']] = $row['clan'] === 'none' ? [] : [$row['clan']];
    }
    return array_filter($clans);
}

// Each tracked member's account state over the period; a skill first seen mid-period starts at its first observation.
function clan_members(PDO $db, string $clan, string $period, int $now): array {
    $names = array_keys(array_filter(clan_map($db), fn($clans) => in_array($clan, $clans, true)));
    if (!$names) return [];
    $members = fetch_rows($db, 'SELECT p.id, p.name, (SELECT c.level FROM current c WHERE c.player_id = p.id AND c.type = 0) AS level FROM players p WHERE p.name IN (' . implode(',', array_fill(0, count($names), '?')) . ') AND EXISTS (SELECT 1 FROM xp x WHERE x.player_id = p.id AND x.type > 0) ORDER BY p.name', array_map('strval', $names));
    if (!$members) return [];
    $start = $now - PERIODS[$period];
    $ids = array_column($members, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $state = []; $events = [];
    foreach (fetch_rows($db, "SELECT x.player_id, x.type, x.value FROM xp x JOIN (SELECT player_id, type, MAX(at) AS at FROM xp WHERE player_id IN ($in) AND type > 0 AND at <= ? GROUP BY player_id, type) m USING (player_id, type, at)", [...$ids, $start]) as $r) $state[$r['player_id']][$r['type']] = $r['value'];
    foreach (fetch_rows($db, "SELECT player_id, type, at, value FROM xp WHERE player_id IN ($in) AND type > 0 AND at > ? AND at <= ? ORDER BY at", [...$ids, $start, $now]) as $r) {
        $state[$r['player_id']][$r['type']] ??= $r['value'];
        $events[] = $r;
    }
    $times = [];
    for ($i = 0, $steps = CLAN_STEPS[$period]; $i <= $steps; $i++) $times[] = $start + intdiv(($now - $start) * $i, $steps);
    $series = []; $e = 0;
    foreach ($times as $t) {
        for (; $e < count($events) && $events[$e]['at'] <= $t; $e++) $state[$events[$e]['player_id']][$events[$e]['type']] = $events[$e]['value'];
        foreach ($state as $id => $values) $series[$id][] = ['at' => $t, 'ehp' => calculate_ehp($values)[0], 'xp' => array_sum($values) / 10];
    }
    return array_map(fn($m) => $m + ['series' => $series[$m['id']] ?? throw new RuntimeException("No clan state for player {$m['id']}")], $members);
}

function clan_average(array $members, string $metric, bool $gain): array {
    if (!$members) return [];
    $points = [];
    foreach ($members[0]['series'] as $i => $point) {
        $sum = 0;
        foreach ($members as $m) $sum += $m['series'][$i][$metric] - ($gain ? $m['series'][0][$metric] : 0);
        $points[] = ['at' => $point['at'], 'value' => $sum / count($members)];
    }
    return $points;
}

function clan_videos(PDO $db, string $clan): array {
    return fetch_rows($db, 'SELECT video_id, title, channel, shared_at FROM clan_videos WHERE clan = ? AND title IS NOT NULL ORDER BY shared_at DESC', [$clan]);
}

// YouTube serves its bot wall to our server, but oEmbed still answers; removed or private videos return 401/403/404.
function youtube_video(string $id): array {
    $body = @file_get_contents('https://www.youtube.com/oembed?format=json&url=' . rawurlencode("https://www.youtube.com/watch?v=$id"), false, stream_context_create(['http' => ['timeout' => 15]]));
    if ($body === false && preg_match('~^HTTP/\S+ (401|403|404) ~', $http_response_header[0] ?? '')) return ['title' => null, 'channel' => null];
    $video = json_decode($body ?: throw new RuntimeException("YouTube oEmbed for $id failed: " . ($http_response_header[0] ?? 'no response')), true, flags: JSON_THROW_ON_ERROR);
    return ['title' => $video['title'] ?? throw new RuntimeException("YouTube oEmbed for $id has no title"), 'channel' => $video['author_name'] ?? null];
}

// Adds videos from the clan owner's own posts in its forum thread, so replies cannot add videos to the clan page.
function refresh_clan_videos(PDO $db): void {
    $errors = [];
    foreach (array_filter(CLANS, fn($clan) => $clan['topic'] !== null) as $key => $clan) {
        try {
            $get = fn(string $path) => json_decode(file_get_contents("https://lostcity.rs$path", false, stream_context_create(['http' => ['timeout' => 15]])) ?: throw new RuntimeException("Fetching forum $path failed: " . ($http_response_header[0] ?? 'no response')), true, flags: JSON_THROW_ON_ERROR);
            $owner = $get("/t/{$clan['topic']}.json")['details']['created_by']['username'] ?? throw new RuntimeException("Forum topic {$clan['topic']} has no author");
            $thread = $get("/t/{$clan['topic']}.json?username_filters=" . rawurlencode($owner))['post_stream'];
            $posts = $thread['posts'];
            foreach (array_chunk(array_diff($thread['stream'], array_column($posts, 'id')), 20) as $chunk) array_push($posts, ...$get("/t/{$clan['topic']}/posts.json?" . http_build_query(['post_ids' => $chunk]))['post_stream']['posts']);
            $shared = [];
            foreach ($posts as $post) {
                if ($post['username'] !== $owner || !preg_match_all('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $post['cooked'], $m)) continue;
                // Several videos in one post keep their posted order instead of tying.
                foreach (array_unique($m[1]) as $i => $id) $shared[$id] ??= (int) (strtotime($post['created_at']) * 1000) + $i;
            }
            $known = array_flip(array_column(fetch_rows($db, 'SELECT video_id FROM clan_videos WHERE clan = ?', [$key]), 'video_id'));
            foreach (array_diff_key($shared, $known) as $id => $at) {
                $video = youtube_video($id);
                $db->prepare('INSERT INTO clan_videos (clan, video_id, title, channel, shared_at) VALUES (?, ?, ?, ?, ?)')->execute([$key, $id, $video['title'], $video['channel'], $at]);
                error_log("Labs: clan $key video $id " . ($video['title'] === null ? 'unavailable' : 'added'));
            }
        } catch (Throwable $e) {
            $errors[] = "$key: " . $e->getMessage();
        }
    }
    if ($errors) throw new RuntimeException('Clan video refresh failed: ' . implode('; ', $errors));
}
