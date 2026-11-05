<?php
require __DIR__ . '/../lib.php';
header('Content-Type: application/json');
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    $bad = $e instanceof InvalidArgumentException;
    http_response_code($bad ? 400 : 500);
    echo json_encode(['error' => $bad ? $e->getMessage() : 'Server error']);
});
$fetch = function (string $sql, array $args = []) use ($db) {
    $query = $db->prepare($sql);
    $query->execute($args);
    return $query->fetchAll();
};
$period = $_GET['period'] ?? '24h';
$type = (int) ($_GET['type'] ?? 0);
if (!isset(PERIODS[$period])) throw new InvalidArgumentException("Unknown period $period");
// A lookup with our key enrolls the player for logout callbacks but returns no stats, so stats come from a normal lookup first.
$track = function () use ($db, $env, $now) {
    $name = safe_name($_GET['name'] ?? '');
    $get = function (array $headers, bool $retry = true) use ($name, $now, &$get) {
        // Cloudflare caches this route regardless of x-api-key; a cached reply would never reach the game server.
        $url = "https://2004.lostcity.rs/api/hiscores/player/$name?t=$now";
        $body = file_get_contents($url, false, stream_context_create(['http' => ['header' => $headers, 'user_agent' => 'LostCityLabs (+https://labs.lostcity.rs)']]));
        // Another search may have used the rate limit (one request per two seconds).
        if ($body === false && $retry && str_contains($http_response_header[0] ?? '', ' 429 ')) {sleep(2); return $get($headers, false);}
        return $body === false ? throw new RuntimeException("Fetching $url failed: " . ($http_response_header[0] ?? 'no response')) : $body;
    };
    $stats = json_decode($get([]) ?: throw new RuntimeException("Hiscores lookup for $name returned nothing"), true, flags: JSON_THROW_ON_ERROR);
    if (!$stats) return null;
    sleep(2); // the game website allows one request per two seconds
    $get(['x-api-key: ' . $env('LABS_API_KEY')]);
    return import_stats($db, $name, $stats, $now);
};
$result = match ($_GET['q'] ?? '') {
    'home' => [
        'gainers' => $fetch('SELECT name, gain FROM gains JOIN players ON id = player_id WHERE period = ? AND type = ? ORDER BY gain DESC LIMIT 10', [$period, $type]),
        'newest' => $fetch('SELECT name, first_seen, c.level FROM players JOIN current c ON c.player_id = id AND c.type = 0 ORDER BY first_seen DESC LIMIT 5'),
        'nineties' => $fetch('SELECT name, type, level, prev_level, at FROM levels JOIN players ON id = player_id WHERE level = 99 ORDER BY at DESC LIMIT 5'),
    ],
    'track' => $track(),
    'player' => ($id = $fetch('SELECT id FROM players WHERE name = ?', [safe_name($_GET['name'] ?? '')])[0]['id'] ?? null) === null ? null : [
        'stats' => $fetch("SELECT c.type, c.level, c.value, c.at, COALESCE(g.gain, 0) AS gain, c.`rank` FROM current c LEFT JOIN gains g ON g.player_id = c.player_id AND g.type = c.type AND g.period = ? WHERE c.player_id = ?", [$period, $id]),
        'history' => $fetch('SELECT type, at, value FROM xp WHERE player_id = ? AND at >= ? ORDER BY at', [$id, $now - PERIODS[$period]]),
        'levels' => $fetch('SELECT type, level, prev_level, at FROM levels WHERE player_id = ? ORDER BY at DESC LIMIT 5', [$id]),
    ],
    'hiscores' => $fetch('SELECT name, level, value, `rank` FROM current JOIN players ON id = player_id WHERE type = ? ORDER BY level DESC, value DESC LIMIT 50', [$type]),
    'records' => array_map(fn($p) => $fetch('SELECT name, gain, start_at, end_at FROM records JOIN players ON id = player_id WHERE period = ? AND type = ? ORDER BY gain DESC LIMIT 5', [$p, $type]), ['6h' => '6h', '24h' => '24h']),
    default => throw new InvalidArgumentException('Unknown query'),
};
if ($result === null) http_response_code(404);
echo json_encode($result);
