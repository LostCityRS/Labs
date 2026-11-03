<?php
require __DIR__ . '/../db.php';
header('Content-Type: application/json');
$fetch = function (string $sql, array $args = []) use ($db) {
    $query = $db->prepare($sql);
    $query->execute($args);
    return $query->fetchAll();
};
$period = $_GET['period'] ?? '24h';
$type = (int) ($_GET['type'] ?? 0);
if (!isset(PERIODS[$period])) throw new InvalidArgumentException("Unknown period $period");
$rank = '(SELECT COUNT(*) + 1 FROM current r WHERE r.type = c.type AND (r.level > c.level OR r.level = c.level AND r.value > c.value))';
$result = match ($_GET['q'] ?? '') {
    'home' => [
        'gainers' => $fetch('SELECT name, gain FROM gains JOIN players ON id = player_id WHERE period = ? AND type = ? ORDER BY gain DESC LIMIT 10', [$period, $type]),
        // The first import's players were not new, just new to Labs.
        'newest' => $fetch('SELECT name, first_seen, c.level FROM players JOIN current c ON c.player_id = id AND c.type = 0 WHERE first_seen > (SELECT MIN(first_seen) FROM players) ORDER BY first_seen DESC LIMIT 5'),
        'nineties' => $fetch('SELECT name, type, level, prev_level, at FROM levels JOIN players ON id = player_id WHERE level = 99 ORDER BY at DESC LIMIT 5'),
        'levels' => $fetch('SELECT name, type, level, prev_level, at FROM levels JOIN players ON id = player_id WHERE level < 99 ORDER BY at DESC LIMIT 8'),
    ],
    'player' => ($id = $fetch('SELECT id FROM players WHERE name = ?', [strtolower(trim($_GET['name'] ?? ''))])[0]['id'] ?? null) === null ? null : [
        'stats' => $fetch("SELECT c.type, c.level, c.value, c.at, COALESCE(g.gain, 0) AS gain, $rank AS `rank` FROM current c LEFT JOIN gains g ON g.player_id = c.player_id AND g.type = c.type AND g.period = ? WHERE c.player_id = ?", [$period, $id]),
        'history' => $fetch('SELECT type, at, value FROM xp WHERE player_id = ? AND at >= ? ORDER BY at', [$id, (int) (microtime(true) * 1000) - PERIODS[$period]]),
        'levels' => $fetch('SELECT type, level, prev_level, at FROM levels WHERE player_id = ? ORDER BY at DESC LIMIT 5', [$id]),
    ],
    'hiscores' => $fetch('SELECT name, level, value FROM current JOIN players ON id = player_id WHERE type = ? ORDER BY level DESC, value DESC LIMIT 50', [$type]),
    'records' => $fetch('SELECT name, gain, start_at, end_at FROM records JOIN players ON id = player_id WHERE period = ? AND type = ? ORDER BY gain DESC LIMIT 50', [$period, $type]),
    default => throw new InvalidArgumentException('Unknown query'),
};
if ($result === null) http_response_code(404);
echo json_encode(['updated_at' => $db->query('SELECT MAX(at) FROM xp')->fetchColumn(), 'data' => $result]);
