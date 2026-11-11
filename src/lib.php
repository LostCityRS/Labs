<?php
require_once __DIR__ . '/data.php';
const RECORD_UPDATE = ' ON DUPLICATE KEY UPDATE start_at = IF(VALUES(gain) > gain, VALUES(start_at), start_at), end_at = IF(VALUES(gain) > gain, VALUES(end_at), end_at), gain = GREATEST(gain, VALUES(gain))';
$env = fn(string $key) => getenv($key) ?: throw new RuntimeException("$key is not set");
$db = new PDO($env('LABS_DB_DSN'), $env('LABS_DB_USER'), getenv('LABS_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
// INSERT ... SELECT otherwise locks every row it reads, so concurrent logouts and the gains refresh deadlock constantly.
$db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
$now = (int) (microtime(true) * 1000);
ini_set('user_agent', 'LostCityLabs (+https://labs.lostcity.rs)'); // Cloudflare rejects requests without one

// InnoDB can still pick a deadlock victim under load; MySQL expects that transaction to be retried.
function transaction(PDO $db, string $what, callable $work) {
    for ($try = 1; ; $try++) {
        $db->beginTransaction();
        try {
            $result = $work();
            $db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if (!($e instanceof PDOException) || $e->getCode() !== '40001' || $try === 5) throw $e;
            error_log("Labs: deadlock $what, retry $try");
            usleep(random_int(20000, 200000));
        }
    }
}

// $stats: rows as returned by /api/hiscores/player/:username, observed at $at (unix ms).
// Import history oldest first, then rebuild records: historical rows are not live logout callbacks.
function import_stats(PDO $db, string $name, array $stats, int $at, bool $observe = false): int {
    return transaction($db, "importing $name", function () use ($db, $name, $stats, $at, $observe) {
        $imported = import_once($db, $name, $stats, $at, $observe);
        // Search enrollment and historical imports are not evidence that someone played today.
        if ($observe) $db->prepare('INSERT INTO daily_observed (day, player_id) SELECT ?, id FROM players WHERE name = ? ON DUPLICATE KEY UPDATE player_id = VALUES(player_id)')->execute([intdiv($at, 86400000) * 86400000, $name]);
        return $imported;
    });
}

function refresh_players(PDO $db, int $at, callable $online): void {
    $day = intdiv($at, 86400000) * 86400000;
    $db->prepare('INSERT INTO daily_players (day, started_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE day = VALUES(day)')->execute([$day, $at]);
    $count = $online();
    if (!is_int($count) || $count < 0) throw new RuntimeException('Invalid online player count');
    $db->prepare('INSERT INTO online (at, players) VALUES (?, ?) ON DUPLICATE KEY UPDATE at = VALUES(at)')->execute([$at, $count]);
    error_log("Labs: online sample $count at $at");
    if ($at - $day < 60000) {
        $snapshot = $db->prepare('UPDATE daily_players SET online = ?, sampled_at = ? WHERE day = ? AND online IS NULL');
        $snapshot->execute([$count, $at, $day]);
        if ($snapshot->rowCount()) error_log("Labs: midnight online snapshot $count at $at");
    }
}

function import_once(PDO $db, string $name, array $stats, int $at, bool $logout): int {
    $previous = $db->prepare('SELECT level, value, at FROM xp WHERE player_id = ? AND type = ? AND at < ? ORDER BY at DESC LIMIT 1');
    $insert = $db->prepare('INSERT IGNORE INTO xp VALUES (?, ?, ?, ?, ?)');
    $levelUp = $db->prepare('INSERT INTO levels VALUES (?, ?, ?, ?, ?)');
    $current = $db->prepare('INSERT INTO current VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE level = IF(VALUES(at) >= at, VALUES(level), level), value = IF(VALUES(at) >= at, VALUES(value), value), `rank` = IF(VALUES(at) >= at, VALUES(`rank`), `rank`), at = GREATEST(at, VALUES(at))');
    $windowStart = $db->prepare('SELECT value, at FROM xp WHERE player_id = ? AND type = ? AND at >= ? AND at < ? AND at > ? ORDER BY at LIMIT 1');
    $record = $db->prepare('INSERT INTO records VALUES (?, ?, ?, ?, ?, ?)' . RECORD_UPDATE);
    $imported = 0;
    $db->prepare('INSERT INTO players (name, first_seen) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)')->execute([$name, $at]);
    $id = (int) $db->lastInsertId();
    $enrolled = $db->prepare('SELECT first_seen FROM players WHERE id = ?');
    $enrolled->execute([$id]);
    $first = (int) $enrolled->fetchColumn();
    foreach ($stats as ['type' => $type, 'level' => $level, 'value' => $value, 'rank' => $rank]) {
        $current->execute([$id, $type, $at, $level, $value, $rank]);
        $previous->execute([$id, $type, $at]);
        $prev = $previous->fetch();
        if ($prev && $value < $prev['value']) throw new RuntimeException("XP decreased for $name type $type at $at: {$prev['value']} -> $value");
        $insert->execute([$id, $type, $at, $level, $value]);
        if ($insert->rowCount() === 0) continue; // already imported
        $imported++;
        if ($prev && $type > 0 && $level > $prev['level']) $levelUp->execute([$id, $type, $level, $prev['level'], $at]);
        if (!$logout) continue;
        // An enrollment lookup can return XP from before the lookup, even midway through a session.
        if ($prev && $prev['at'] > $first && $value > $prev['value']) $record->execute(['6h', $type, $id, $value - $prev['value'], $prev['at'], $at]);
        // An unchanged logout still proves a boundary; dropping it can discard a whole session.
        $windowStart->execute([$id, $type, $at - RECORDS['24h'], $at, $first]);
        $start = $windowStart->fetch();
        if ($start && $value > $start['value']) $record->execute(['24h', $type, $id, $value - $start['value'], $start['at'], $at]);
    }
    refresh_gains($db, $at, $id);
    cache_ehp($db, $id);
    return $imported;
}

function rebuild_records(PDO $db): void {
    transaction($db, 'rebuilding records', function () use ($db) {
        $db->exec("DELETE FROM records WHERE period IN ('6h', '24h')");
        foreach (['6h' => ['DESC', ''], '24h' => ['ASC', ' AND b.at >= x.at - ' . RECORDS['24h']]] as $period => [$order, $cutoff]) {
            $db->prepare("INSERT INTO records SELECT ?, x.type, x.player_id, x.value - s.value, s.at, x.at FROM xp x JOIN players p ON p.id = x.player_id JOIN xp s ON s.player_id = x.player_id AND s.type = x.type AND s.at = (SELECT b.at FROM xp b WHERE b.player_id = x.player_id AND b.type = x.type AND b.at > p.first_seen AND b.at < x.at$cutoff ORDER BY b.at $order LIMIT 1) WHERE x.value > s.value ORDER BY x.at" . RECORD_UPDATE)->execute([$period]);
        }
    });
    error_log('Labs: rebuilt single-update 6h and bounded-window 24h records');
}

// Gains change as time passes, so cron refreshes everyone; a new logout refreshes just that player.
function refresh_gains(PDO $db, int $now, ?int $player = null): void {
    $only = $player === null ? '' : ' AND c.player_id = ' . $player;
    $db->exec('DELETE c FROM gains c WHERE 1' . $only);
    $gains = $db->prepare('INSERT INTO gains SELECT ?, c.type, c.player_id, c.value - COALESCE((SELECT x.value FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type AND x.at <= ? ORDER BY x.at DESC LIMIT 1), (SELECT MIN(x.value) FROM xp x WHERE x.player_id = c.player_id AND x.type = c.type)) AS gain FROM current c WHERE c.at > ?' . $only . ' HAVING gain > 0 ON DUPLICATE KEY UPDATE gain = VALUES(gain)'); // a logout can re-add a player between our DELETE and INSERT
    foreach (PERIODS as $period => $ms) $gains->execute([$period, $now - $ms, $now - $ms]);
}
