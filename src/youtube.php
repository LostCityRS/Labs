<?php
// Feeds are keyed by channel ID; handles are only there so a reader knows whose channel each is.
const YOUTUBE_CHANNELS = [
    'UCO5AUI4v0z8dknNlEZeDF7Q' => 'PkLikeElve',
    'UC_9TKjx6PmiwOXo_hK1cmmQ' => 'MatJk2004',
    'UCj8dJ3Q3JB753WXV97KKEaA' => 'vividman99',
    'UCG8VPLs8XqrD9H9dbz2vuqw' => 'A-Pures-Way',
    'UCSyceej8t_IAs9UR3SYW-2w' => 'nosaching7270',
    'UCmVJ6Ff47YUkazAViOb-37A' => 'radicalpkzz',
    'UCeW-sjKbyebOOoQ8lv63wTw' => 'AutumnElegy',
    'UCAk3hMAJJ7oAP2ciRM5tAvQ' => 'WildMudkip',
    'UChpl80y8J-csD1R0xOWxSdw' => 'AbyssTTV',
    'UCDnlUv-BXcMrwXLcTwNqU-Q' => 'Michael_Unce',
    'UCV8OD9gwFmFcdbWkvHJEUXw' => 'ZombieMusiq',
    'UCUw68v6UQm_79BWbgbxUyDg' => 'Ellard2G',
    'UCNKMDMGpnHB3LctFsnNyCsQ' => 'aroynt',
    'UCoXjs91IQUuaAqIneSrCVaQ' => 'etradepk',
    'UCOWK0096cCvWPrDSjc12P9w' => 'garlic04',
    'UCs2w89zFPaa9o_rMh0sLVlw' => 'mehlostcity',
    'UCk8-iUxgobzSDyQby2bICBw' => 'player713x',
    'UC-VRSuuv19tFGlqnNaRU4Nw' => 'x704scape',
    'UCkeRcigPv0oAbpq6F-erhjw' => 'wallyisf2p',
    'UCcjLZY8KeEtVlfQHq5fwQgw' => 'cicadagirl4247',
    'UCd-ERGwlCtDWCp1rbXC8bew' => 'noobslayerrs',
    'UCLQogeq4ii1MQJBXBPOQi0w' => 'mysticsof04',
    'UCLO15YDDlJRDEANQQrnwrTQ' => 'index-rs',
    'UCM7n6nbXDC5Fj2t-wHsZYCw' => 'takomoloko',
    'UCU1TmnFeXeW6y4TEenTrzXg' => 'gho_04',
    'UCaGBhG95e4mGyI_vkB0mzQg' => 'classicrsvids',
    'UC6Bjm8mzSKmGGLMWid8nsvA' => 'gemhi',
    'UCUohbSLsBWn8U3VjWalaSuQ' => 'vunleg',
    'UClsstVjrjaTHppucg1Sc7ww' => 'Pious04Scape',
    'UCqYw0GcZ2Qq4kduBGvqiUcg' => 'SOULCLANRS',
    'UCXJYqh62c7nKmrLDDFnWQFg' => 'raves2903',
    'UCD5YTOv91J_q0s_5P12G8iA' => 'hperez1551',
    'UCooYuQxrNY8lAuTKQ0rltyg' => 'gamerf13',
    'UCI-f7htqUxMc9bYS2BPyZkw' => 'grimyvideos',
    'UCWLD44KuoNMCcGt5wiwAKrg' => 'lostcitynerd',
    'UCEZ4xgmuAfVmeOGOFEZ_G4g' => 'ironatica',
    'UCxyKVDRFmqEqDdbmVa7yy4w' => '04carsten',
    'UCwUHOhvC5STQliDEPSDNyZQ' => 'espionage724',
    'UCyGqJfW-bBlq-bHKgkW7pyQ' => 'shiningnoctowls',
    'UCj1ljfWIbMwhUYrszkIk_tw' => 'wartimers',
    'UC3qR4Tm3xXGUX_dYfk5hpeQ' => 'soulclanfam',
    'UCoLXxq1_qKeltYHPnSH_M2Q' => 'gzbeast7394',
    'UCWb2wmoxS0QtFdE1aav_NmA' => 'eigenvalue94',
    'UCD1-T5JIa8MEhArvvtcICWw' => 'lostwaxy',
    'UCCByl3J6EhokxAyjlVzKpxw' => 'repose_',
    'UCnucQ81jeH-O79h-QISyQrA' => 'thislove5369',
    'UCJkecmbvPIWajOT8eTQKVJg' => 'wotan2004',
    'UCgoVz5vf9drQ8UCfpKo35Ew' => 'afterdarkpkelve',
    'UC0lkrWP6aoWAXtOBLy3g0AQ' => 'alchins',
    'UCWkZzL3YOhccpaQDugjOUTQ' => 'KingMage617',
    'UC5pLYis5jDdsclXJk_pzWPA' => 'Kixxi04',
    'UCOe3UqohFuNjVyeIA45DeWw' => 'VintrexRS',
    'UCHOFWLnJgB3H6XVhuJjSB3A' => 'gmgon2004scape',
    'UCGnvd-0Hz-rQQmwc1c7ukzw' => 'tony04scape',
    'UC3fhCeWPwiTzk89gBM9I-ww' => 'DutchMastersEnjoyer',
    'UCrGoKeATPmG1zjX-UQ-kv9g' => 'zeldababy',
    'UCQhdYbG3mBkCKC_bOeMLo3g' => 'RetroCrops',
    'UCSwd8O9zyeDswmzdAeJSJaA' => 'Utaguchimoon',
    'UCY9nzDMIPUmtWxMAxE3te5A' => 'FabioScape',
    'UCQcN_GI8qlU4Bjba2R36PkA' => '2004ScapeClips',
    'UCRXBGoGyBTl5g2ZXSlev3KA' => 'frublox',
    'UCpUEchVmzywwFjcCG2lZ24w' => 'five4me',
    'UCq7q_T2YCSeI9KjttdDb1Cg' => 'RoppanOSRS',
    'UCm9Trxb8r2Ri-hdyi5C8U-w' => 'tempusvincitomnia',
    'UCQyB_P2j8QNGFjXXt6qFzIw' => '04Mystical',
    'UCH2Vhm6_QgICmsGu7nFS-1Q' => 'gametinker',
    'UC6QvYJ5L-yQoDyowkpEs-OA' => 'fable_lc',
    'UCtq02sJaoM26yjrd2u8BY6g' => 'freerunescapevideos',
    'UCfOZekp7_MVe3VPbaLVXAUw' => 'RPGLive365',
    'UCl7FxeciaU48G-K6oIRH0xg' => 'KempQ',
    'UC5wDgj_MKK0szcejebTXs-w' => 'edwardosrs9662',
    'UC_fgZS0tDWhEPYuRRREralw' => 'Pawzosrs',
    'UCHyTn-I1CygBOrTUxnczu1A' => 'Yattbot',
    'UCOhMtFyuw1SvUCv4-2xpPHw' => 'Heckyesjoey',
    'UCnm75aZz3zHbCiiZrWUtSRA' => 'Joeon2004',
    'UCAW_VeptQ_YhE_Qld68voPQ' => 'Art_Will',
    'UCaJRVVrzhOh0LDU0LCRUmqQ' => 'RetroReloaded-01',
    'UCvJ1njiwGPdL9x6hTNfN2vQ' => 'lowerless',
    'UCVjZQCqy_W3BXUS1jv-n5Aw' => 'runeghostl6796',
    'UC_E3c5q5E3kbduSxlhMSgXQ' => 'Feral_Ginger',
    'UCmh8XxEqKgcnK_KB-nbJhXQ' => 'Mark97',
    'UCG95E9iby7gdpsjREKlUr2w' => 'thisismyspacemusic',
    'UCDf6vnwErHPcwICV1VdGzFQ' => 'Zerobeatmissesbeats',
    'UCPGQE5qkk2JUrNNutNln-6A' => 'PixelzGaming',
    'UCI8vdLEFFzmEzjJrI2etsYg' => 'falcondor96',
    'UC6JBwaQHB4ikdJXTPm2kyJg' => 'FreshhStartt',
];
const YOUTUBE_PAGE = 10;

// Labs covers lostcity.rs only, yet lostcitylabs.com (Lost Citizens) and 2004sp videos also say 2004scape and Lost City; PkLikeElve posts nothing else.
function youtube_listed(string $handle, string $title, string $description): bool {
    $text = "$title\n$description";
    return !preg_match('~lostcitylabs\.com/play|lost citizens scape|\b2004sp\b~i', $text) && ($handle === 'PkLikeElve' || preg_match('~\b(20)?04 ?scape|lostcity\.rs|lost ?city\b~i', $text));
}

function youtube_page(PDO $db, int $page): array {
    $total = (int) fetch_rows($db, 'SELECT COUNT(*) AS n FROM youtube_videos')[0]['n'];
    $pages = max(1, (int) ceil($total / YOUTUBE_PAGE));
    if ($page > $pages) throw new OutOfBoundsException('Page not found');
    return ['page' => $page, 'videos' => fetch_rows($db, 'SELECT * FROM youtube_videos ORDER BY published_at DESC LIMIT ' . YOUTUBE_PAGE . ' OFFSET ' . ($page - 1) * YOUTUBE_PAGE), 'total' => $total, 'pages' => $pages];
}

function youtube_feed(string $id): string {
    $body = @file_get_contents("https://www.youtube.com/feeds/videos.xml?channel_id=$id", false, stream_context_create(['http' => ['timeout' => 15]]));
    return $body !== false ? $body : throw new RuntimeException('feed fetch failed: ' . ($http_response_header[0] ?? error_get_last()['message']));
}

// Each feed holds a channel's latest 15 uploads, so views stop updating once a video drops out of it.
// YouTube answers healthy feeds with 404s and 500s for hours at a time; the 15 uploads cover that gap, so only a day-long failure is reported.
function refresh_youtube_videos(PDO $db, int $now, callable $feed): void {
    $errors = [];
    $upsert = $db->prepare('INSERT INTO youtube_videos (video_id, channel_id, channel, title, description, views, published_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE channel = VALUES(channel), title = VALUES(title), description = VALUES(description), views = VALUES(views)');
    $db->prepare('DELETE FROM youtube_feed_failures WHERE channel_id NOT IN (' . implode(',', array_fill(0, count(YOUTUBE_CHANNELS), '?')) . ')')->execute(array_keys(YOUTUBE_CHANNELS));
    foreach (YOUTUBE_CHANNELS as $id => $handle) {
        try {
            $body = $feed($id);
        } catch (RuntimeException $e) {
            $db->prepare('INSERT INTO youtube_feed_failures (channel_id, since, error) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE error = VALUES(error)')->execute([$id, $now, $e->getMessage()]);
            continue;
        }
        $db->prepare('DELETE FROM youtube_feed_failures WHERE channel_id = ?')->execute([$id]);
        try {
            $feedXml = @simplexml_load_string($body);
            if ($feedXml === false) throw new RuntimeException('feed is not XML: ' . trim(error_get_last()['message']));
            $feedTimes = [];
            foreach ($feedXml->entry as $entry) {
                $yt = $entry->children('yt', true); $media = $entry->children('media', true)->group;
                $video = (string) $yt->videoId;
                $published = strtotime((string) $entry->published) ?: throw new RuntimeException("video $video has no publish time");
                $feedTimes[$video] = $published * 1000;
                if (!youtube_listed($handle, (string) $entry->title, (string) $media->description)) continue;
                $views = $media->community->statistics->attributes()['views'] ?? throw new RuntimeException("video $video has no view count");
                $upsert->execute([$video, $id, (string) $entry->author->name, (string) $entry->title, (string) $media->description, (int) $views, $published * 1000]);
                if ($upsert->rowCount() === 1) error_log("Labs: YouTube video $video added from @$handle");
            }
            // Deleted and private uploads drop out of the feed, so a stored video missing from between its oldest and newest entries is gone.
            $stored = $feedTimes ? fetch_rows($db, 'SELECT video_id FROM youtube_videos WHERE channel_id = ? AND published_at BETWEEN ? AND ?', [$id, min($feedTimes), max($feedTimes)]) : [];
            foreach (array_diff(array_column($stored, 'video_id'), array_keys($feedTimes)) as $gone) {
                $db->prepare('DELETE FROM youtube_videos WHERE video_id = ?')->execute([$gone]);
                error_log("Labs: YouTube video $gone removed from @$handle; it left the channel's feed");
            }
        } catch (Throwable $e) {
            $errors[] = "@$handle: " . $e->getMessage();
        }
    }
    foreach (fetch_rows($db, 'SELECT channel_id, since, error FROM youtube_feed_failures WHERE since <= ?', [$now - 86400000]) as $f) $errors[] = '@' . YOUTUBE_CHANNELS[$f['channel_id']] . ' feed failing since ' . gmdate('j M H:i', (int) ($f['since'] / 1000)) . " UTC: {$f['error']}";
    if ($errors) throw new RuntimeException('YouTube refresh failed: ' . implode('; ', $errors));
}

// oEmbed answers 401 for videos that only refuse embedding; 403 and 404 mean deleted or private.
function youtube_gone(string $id): bool {
    @file_get_contents('https://www.youtube.com/oembed?format=json&url=' . rawurlencode("https://www.youtube.com/watch?v=$id"), false, stream_context_create(['http' => ['timeout' => 15]]));
    $status = $http_response_header[0] ?? throw new RuntimeException("YouTube oEmbed for $id failed: " . error_get_last()['message']);
    if (preg_match('~^HTTP/\S+ (200|401) ~', $status)) return false;
    return preg_match('~^HTTP/\S+ (403|404) ~', $status) ? true : throw new RuntimeException("YouTube oEmbed for $id answered $status");
}

// Feeds only show each channel's latest 15 uploads, so every video is also checked once a day, a twenty-fourth of them each hour.
// A check that fails is retried the next day; only a run where every check fails points at a broken check rather than a bad moment.
function sweep_youtube_videos(PDO $db, int $now, callable $gone): void {
    $videos = fetch_rows($db, 'SELECT video_id, channel FROM youtube_videos WHERE CRC32(video_id) % 24 = ?', [intdiv($now, 3600000) % 24]);
    $errors = [];
    foreach ($videos as $v) {
        try {
            if (!$gone($v['video_id'])) continue;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
            continue;
        }
        $db->prepare('DELETE FROM youtube_videos WHERE video_id = ?')->execute([$v['video_id']]);
        error_log("Labs: YouTube video {$v['video_id']} removed from {$v['channel']}; YouTube reports it deleted or private");
    }
    if ($videos && count($errors) === count($videos)) throw new RuntimeException('YouTube video checks all failed: ' . implode('; ', $errors));
}
