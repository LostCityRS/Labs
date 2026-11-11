<?php
require __DIR__ . '/../src/lib.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test' || $db->query('SELECT COUNT(*) FROM players')->fetchColumn() != 0) throw new RuntimeException('Records tests require a fresh isolated labs_records_test database');
function record_expect(bool $condition, string $message): void {if (!$condition) throw new RuntimeException($message);}
function packet(array $values, array $types): array {
    $rows = []; $levels = 0;
    foreach ($values as $type => $value) {
        $level = min(99, virtual_level($value / 10)); $levels += $level;
        if (in_array($type, $types, true)) $rows[] = ['type' => $type, 'level' => $level, 'value' => $value, 'rank' => 100];
    }
    return [['type' => 0, 'level' => $levels, 'value' => array_sum($values), 'rank' => 100], ...$rows];
}
$at = strtotime('2026-11-06 00:00 UTC') * 1000; $hour = 3600000;
$values = array_fill_keys(array_diff(array_keys(SKILLS), [0]), 0); $values[4] = 11540;
$all = array_keys($values);
import_stats($db, 'multiskill', packet($values, $all), $at);
$id = (int) $db->query("SELECT id FROM players WHERE name = 'multiskill'")->fetchColumn();
$values[1] += 1000; $values[8] += 2000; $values[9] += 3000;
import_stats($db, 'multiskill', packet($values, $all), $at + $hour, true);
record_expect($db->query('SELECT COUNT(*) FROM records')->fetchColumn() == 0, 'Enrollment is not a logout boundary');
$values[1] += 2000; $values[8] += 3000; $values[9] += 5000;
import_stats($db, 'multiskill', packet($values, [1, 8, 9]), $at + 2 * $hour, true);
$get = fn(string $period, int $type) => fetch_rows($db, 'SELECT gain, start_at, end_at FROM records WHERE player_id = ? AND period = ? AND type = ?', [$id, $period, $type])[0] ?? throw new RuntimeException("Missing $period record for player $id type $type");
record_expect($get('6h', 0)['gain'] == 10000, 'Overall must sum 2000 + 3000 + 5000, not take the best skill');
record_expect($get('6h', 0)['gain'] > max($get('6h', 1)['gain'], $get('6h', 8)['gain'], $get('6h', 9)['gain']), 'Multi-skill Overall must exceed every individual gain');
$values[8] += 7000; $values[11] += 1000;
$last = $at + 4 * $hour;
import_stats($db, 'multiskill', packet($values, [8, 11]), $last, true);
record_expect($get('6h', 0)['gain'] == 10000, 'Do not combine skill bests earned in different sessions');
record_expect($get('24h', 0)['gain'] == 18000, '24h Overall must sum all skill gains over the same observed window');
record_expect($get('24h', 0)['start_at'] == $at + $hour && $get('24h', 0)['end_at'] == $last, 'Overall must retain its actual shared window');
record_expect(import_stats($db, 'multiskill', packet($values, [8, 11]), $last, true) === 0, 'Duplicate callbacks must be idempotent');
$late = $last + RECORDS['6h'] + 1;
$values[7] += 100000;
import_stats($db, 'multiskill', packet($values, [7]), $late, true);
record_expect($get('6h', 0)['gain'] == 10000, 'A callback over six hours later cannot set a 6h record');
import_stats($db, 'multiskill', packet($values, [1, 8]), $late + $hour, true);
$values[1] += 10000; $values[8] += 20000;
import_stats($db, 'multiskill', packet($values, [1, 8]), $late + 2 * $hour, true);
record_expect($get('6h', 0)['gain'] == 30000 && $get('6h', 0)['start_at'] == $late + $hour, 'An unchanged logout must remain a usable boundary');
$before = fetch_rows($db, 'SELECT period, type, player_id, gain, start_at, end_at FROM records ORDER BY period, type, player_id');
rebuild_records($db);
record_expect(fetch_rows($db, 'SELECT period, type, player_id, gain, start_at, end_at FROM records ORDER BY period, type, player_id') === $before, 'Rebuilt records must exactly match live callback calculations');
foreach ($before as $r) record_expect($r['end_at'] - $r['start_at'] <= RECORDS[$r['period']], 'Every record must fit its time window');
$best = $get('24h', 0);
$values[8] += 1000000;
import_stats($db, 'multiskill', packet($values, [8]), $late + 2 * $hour + RECORDS['24h'] + 1, true);
record_expect($get('24h', 0) === $best, 'A gap over 24h cannot establish an in-window gain');
$boards = get_data($db, ['q' => 'records', 'type' => '0'], $late);
record_expect($boards['6h'][0]['gain'] == 30000 && $boards['24h'][0]['gain'] == $best['gain'], 'The Overall API must expose the combined records, not an individual skill board');
echo "Records: sum-of-skills Overall, shared windows, enrollment exclusion, idempotency, unchanged boundaries and rebuild parity passed\n";
