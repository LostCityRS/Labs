<?php
const SKILLS = [0 => 'Overall', 1 => 'Attack', 2 => 'Defence', 3 => 'Strength', 4 => 'Hitpoints', 5 => 'Ranged', 6 => 'Prayer', 7 => 'Magic', 8 => 'Cooking', 9 => 'Woodcutting', 10 => 'Fletching', 11 => 'Fishing', 12 => 'Firemaking', 13 => 'Crafting', 14 => 'Smithing', 15 => 'Mining', 16 => 'Herblore', 17 => 'Agility', 18 => 'Thieving', 21 => 'Runecrafting'];
const PERIODS = ['24h' => 86400000, '7d' => 604800000, '30d' => 2592000000];
const RECORDS = ['6h' => 21600000, '24h' => 86400000];
require_once __DIR__ . '/ehp.php';

function parameter(array $params, string $key, string $default = ''): string {
    $value = $params[$key] ?? $default;
    return is_string($value) ? $value : throw new InvalidArgumentException("Invalid parameter: $key");
}

function safe_name(string $name): string {
    $name = strtolower(str_replace(' ', '_', trim($name)));
    return preg_match('/^[a-z0-9_]{1,12}$/', $name) ? $name : throw new InvalidArgumentException("Invalid username: $name");
}

function page_state(string $uri, array $params, int $now): array {
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) throw new InvalidArgumentException('Invalid URL');
    $path = '/' . trim(rawurldecode($path), '/');
    $parts = explode('/', substr($path, 1));
    $view = match ($parts[0]) {'' , 'home' => 'home', 'hiscores', 'records', 'players', 'player' => $parts[0], 'guides' => 'guides', default => throw new OutOfBoundsException('Page not found')};
    if (($view === 'guides' && $parts !== ['guides', 'ehp']) || ($view === 'player' && (count($parts) < 2 || count($parts) > 3)) || ($view === 'records' && count($parts) > 2) || (in_array($view, ['home', 'hiscores', 'players']) && count($parts) !== 1)) throw new OutOfBoundsException('Page not found');
    $period = parameter($params, 'period', '24h');
    if (!isset(PERIODS[$period])) throw new InvalidArgumentException("Unknown period: $period");
    $defaultSkill = match ($view) {'player' => $parts[2] ?? 'Overall', 'records' => $parts[1] ?? 'Overall', 'guides' => 'Agility', default => 'Overall'};
    $skill = parameter($params, 'skill', $defaultSkill);
    $type = array_search($skill, SKILLS, true);
    if ($type === false || ($view === 'guides' && $type === 0)) throw new InvalidArgumentException("Unknown skill: $skill");
    $name = $view === 'player' ? safe_name($parts[1]) : '';
    $canonical = match ($view) {'home' => '/', 'player' => '/player/' . $name . (isset($parts[2]) ? '/' . $skill : ''), 'records' => '/records/' . $skill, 'guides' => '/guides/ehp', default => '/' . $view};
    return compact('view', 'period', 'skill', 'type', 'name', 'now', 'canonical');
}

function fetch_rows(PDO $db, string $sql, array $args = []): array {
    $query = $db->prepare($sql);
    $query->execute($args);
    return $query->fetchAll();
}

function track_player(PDO $db, string $name, int $now): ?int {
    $get = function (array $headers, bool $retry = true) use ($name, $now, &$get) {
        // Cloudflare caches this route regardless of x-api-key; cached replies never enroll players.
        $url = "https://2004.lostcity.rs/api/hiscores/player/$name?t=$now";
        $body = file_get_contents($url, false, stream_context_create(['http' => ['header' => $headers, 'timeout' => 15]]));
        if ($body === false && $retry && str_contains($http_response_header[0] ?? '', ' 429 ')) {sleep(2); return $get($headers, false);}
        return $body === false ? throw new RuntimeException("Fetching $url failed: " . ($http_response_header[0] ?? 'no response')) : $body;
    };
    $stats = json_decode($get([]) ?: throw new RuntimeException("Hiscores lookup for $name returned nothing"), true, flags: JSON_THROW_ON_ERROR);
    if (!$stats) return null;
    // Keyed lookups subscribe to callbacks but return no stats; the normal lookup must come first.
    sleep(2);
    $get(['x-api-key: ' . (getenv('LABS_API_KEY') ?: throw new RuntimeException('LABS_API_KEY is not set'))]);
    return import_stats($db, $name, $stats, $now);
}

function get_data(PDO $db, array $params, int $now) {
    $period = parameter($params, 'period', '24h');
    $type = filter_var(parameter($params, 'type', '0'), FILTER_VALIDATE_INT);
    if (!isset(PERIODS[$period])) throw new InvalidArgumentException("Unknown period: $period");
    if ($type === false || !isset(SKILLS[$type])) throw new InvalidArgumentException('Unknown skill type');
    $fetch = fn(string $sql, array $args = []) => fetch_rows($db, $sql, $args);
    return match (parameter($params, 'q')) {
        'home' => [
            'gainers' => $fetch('SELECT name, gain FROM gains JOIN players ON id = player_id WHERE period = ? AND type = ? ORDER BY gain DESC LIMIT 10', [$period, $type]),
            'newest' => $fetch('SELECT name, first_seen, c.level FROM players JOIN current c ON c.player_id = id AND c.type = 0 ORDER BY first_seen DESC LIMIT 5'),
            'nineties' => $fetch('SELECT name, type, level, prev_level, at FROM levels JOIN players ON id = player_id WHERE level = 99 ORDER BY at DESC LIMIT 5'),
            'weekly' => ehp_winners($db),
        ],
        'track' => track_player($db, safe_name(parameter($params, 'name')), $now),
        'player' => ($id = $fetch('SELECT id FROM players p WHERE name = ? AND EXISTS (SELECT 1 FROM current c WHERE c.player_id = p.id)', [safe_name(parameter($params, 'name'))])[0]['id'] ?? null) === null ? null : [
            'stats' => $fetch('SELECT c.type, c.level, c.value, c.at, COALESCE(g.gain, 0) AS gain, c.`rank` FROM current c LEFT JOIN gains g ON g.player_id = c.player_id AND g.type = c.type AND g.period = ? WHERE c.player_id = ?', [$period, $id]),
            'history' => $fetch('SELECT type, at, value FROM xp WHERE player_id = ? AND at >= ? ORDER BY at', [$id, $now - PERIODS[$period]]),
            'levels' => $fetch('SELECT type, level, prev_level, at FROM levels WHERE player_id = ? ORDER BY at DESC LIMIT 5', [$id]),
            'ehp' => ehp_scores($db, (int) $id),
            'ehp_model' => ehp_model()['id'],
        ],
        'online' => [
            'online' => $fetch('SELECT day AS at, online AS value FROM daily_players WHERE online IS NOT NULL ORDER BY day'),
            // Late-committing pre-midnight callbacks must not be frozen out of yesterday's count.
            'observed' => $fetch('SELECT d.day AS at, COUNT(o.player_id) AS value FROM daily_players d LEFT JOIN daily_observed o ON o.day = d.day WHERE d.started_at < d.day + 60000 AND d.day < ? GROUP BY d.day ORDER BY d.day', [intdiv($now, 86400000) * 86400000]),
        ],
        'hiscores' => $fetch('SELECT p.name, c.level, c.value, c.`rank`, e.value AS ehp, e.model AS ehp_model FROM ehp e JOIN players p ON p.id = e.player_id LEFT JOIN current c ON c.player_id = e.player_id AND c.type = e.type WHERE e.model = ? AND e.type = ? ORDER BY e.value DESC, p.name LIMIT 50', [ehp_model()['id'], $type]),
        'records' => array_map(fn($p) => $fetch('SELECT name, gain, start_at, end_at FROM records JOIN players ON id = player_id WHERE period = ? AND type = ? ORDER BY gain DESC LIMIT 5', [$p, $type]), ['6h' => '6h', '24h' => '24h']),
        'guides' => json_decode(file_get_contents(__DIR__ . '/rates.json'), true, flags: JSON_THROW_ON_ERROR)['skills'],
        default => throw new InvalidArgumentException('Unknown query'),
    };
}
