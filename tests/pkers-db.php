<?php
require __DIR__ . '/../src/lib.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test') throw new RuntimeException('PKer integration tests require labs_records_test');
function pker_expect(bool $ok, string $why): void {if (!$ok) throw new RuntimeException($why);}
foreach (['gains', 'current', 'players'] as $table) $db->exec("DELETE FROM $table");
// name => [7-day overall gain or null, type => level]
$accounts = [
    'zerker' => [500, [1 => 60, 2 => 45, 3 => 90, 4 => 85, 5 => 80, 6 => 52, 7 => 70]],
    'pure' => [900, [1 => 60, 3 => 80, 4 => 70, 5 => 75, 7 => 60]],
    'main' => [700, [1 => 70, 2 => 70, 3 => 80, 4 => 78, 5 => 60, 6 => 50, 7 => 60]],
    'edge' => [300, [1 => 60, 2 => 78, 3 => 100, 4 => 80]],
    'mage_tank' => [800, [2 => 40, 4 => 60, 7 => 90]],
    'idle_pure' => [null, [1 => 60, 3 => 80, 4 => 70]],
    '317' => [100, [3 => 20, 4 => 20]],
];
foreach ($accounts as $name => [$gain, $levels]) {
    $db->prepare('INSERT INTO players (name, first_seen) VALUES (?, 1)')->execute([$name]);
    $id = $db->lastInsertId();
    foreach ($levels + [0 => 500] as $type => $level) $db->prepare('INSERT INTO current VALUES (?, ?, 1, ?, 0, 1)')->execute([$id, $type, $level]);
    if ($gain !== null) $db->prepare("INSERT INTO gains VALUES ('7d', 0, ?, ?), ('7d', 3, ?, 1), ('24h', 0, ?, 999999)")->execute([$id, $gain, $id, $id]);
}
$pkers = active_pkers($db);
pker_expect(array_column($pkers, 'name') === ['pure', 'zerker', 'edge', '317'], 'Active PKers must be players with XP this week whose Defence is at most 78% of Strength or Ranged, most weekly XP first; got ' . json_encode(array_column($pkers, 'name')));
$pure = $pkers[0];
ksort($pure['levels']);
pker_expect($pure['levels'] === [1 => 60, 2 => 1, 3 => 80, 4 => 70, 5 => 75, 7 => 60], 'An omitted Defence level must count as 1 while other omitted levels stay unknown');
pker_expect($pure['combat'] === [63, 65], 'Combat must span an omitted Prayer level of 1 to 14; got ' . json_encode($pure['combat']));
pker_expect($pkers[1]['combat'] === [87, 87], 'Fully recorded stats must give one combat level; got ' . json_encode($pkers[1]['combat']));
echo "PKers: activity, Defence share, omitted levels and combat range passed\n";
