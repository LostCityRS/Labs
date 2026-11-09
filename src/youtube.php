<?php
// Feeds are keyed by channel ID; handles are only there so a reader knows whose channel each is.
const YOUTUBE_CHANNELS = ['UCO5AUI4v0z8dknNlEZeDF7Q' => 'PkLikeElve', 'UC_9TKjx6PmiwOXo_hK1cmmQ' => 'MatJk2004', 'UCj8dJ3Q3JB753WXV97KKEaA' => 'vividman99', 'UCG8VPLs8XqrD9H9dbz2vuqw' => 'A-Pures-Way', 'UCSyceej8t_IAs9UR3SYW-2w' => 'nosaching7270', 'UCmVJ6Ff47YUkazAViOb-37A' => 'radicalpkzz', 'UCeW-sjKbyebOOoQ8lv63wTw' => 'AutumnElegy', 'UCAk3hMAJJ7oAP2ciRM5tAvQ' => 'WildMudkip', 'UChpl80y8J-csD1R0xOWxSdw' => 'AbyssTTV'];
const YOUTUBE_PAGE = 10;

// Creators label Lost City uploads differently, so each channel gets the rule that catches its own; PkLikeElve posts nothing else.
function youtube_listed(string $handle, string $title, string $description): bool {
    return match ($handle) {
        'PkLikeElve' => true,
        'WildMudkip' => str_contains($title, '2004'),
        'AbyssTTV' => (bool) preg_match('~2004scape|lost city~i', "$title\n$description"),
        default => stripos($title, '2004scape') !== false,
    };
}

function youtube_page(PDO $db, int $page): array {
    $total = (int) fetch_rows($db, 'SELECT COUNT(*) AS n FROM youtube_videos')[0]['n'];
    $pages = max(1, (int) ceil($total / YOUTUBE_PAGE));
    if ($page > $pages) throw new OutOfBoundsException('Page not found');
    return ['page' => $page, 'videos' => fetch_rows($db, 'SELECT * FROM youtube_videos ORDER BY published_at DESC LIMIT ' . YOUTUBE_PAGE . ' OFFSET ' . ($page - 1) * YOUTUBE_PAGE), 'total' => $total, 'pages' => $pages];
}

// Each feed holds a channel's latest 15 uploads, so views stop updating once a video drops out of it.
function refresh_youtube_videos(PDO $db): void {
    $errors = [];
    $upsert = $db->prepare('INSERT INTO youtube_videos (video_id, channel_id, channel, title, description, views, published_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE channel = VALUES(channel), title = VALUES(title), description = VALUES(description), views = VALUES(views)');
    foreach (YOUTUBE_CHANNELS as $id => $handle) {
        try {
            $body = file_get_contents("https://www.youtube.com/feeds/videos.xml?channel_id=$id", false, stream_context_create(['http' => ['timeout' => 15]]));
            $feed = simplexml_load_string($body ?: throw new RuntimeException('feed fetch failed: ' . ($http_response_header[0] ?? 'no response'))) ?: throw new RuntimeException('feed is not XML');
            foreach ($feed->entry as $entry) {
                $yt = $entry->children('yt', true); $media = $entry->children('media', true)->group;
                if (!youtube_listed($handle, (string) $entry->title, (string) $media->description)) continue;
                $video = (string) $yt->videoId;
                $views = $media->community->statistics->attributes()['views'] ?? throw new RuntimeException("video $video has no view count");
                $published = strtotime((string) $entry->published) ?: throw new RuntimeException("video $video has no publish time");
                $upsert->execute([$video, $id, (string) $entry->author->name, (string) $entry->title, (string) $media->description, (int) $views, $published * 1000]);
                if ($upsert->rowCount() === 1) error_log("Labs: YouTube video $video added from @$handle");
            }
        } catch (Throwable $e) {
            $errors[] = "@$handle: " . $e->getMessage();
        }
    }
    if ($errors) throw new RuntimeException('YouTube refresh failed: ' . implode('; ', $errors));
}
