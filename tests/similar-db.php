<?php
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/similar.php';
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'labs_records_test') throw new RuntimeException('Similarity integration tests require labs_records_test');
$week = 7 * 86400000;
$red = [1=>60,2=>40,3=>93,4=>92,5=>99,6=>52,7=>74,17=>73];
$fixtures = [
    'sim_red'=>$red, 'sim_blood'=>array_replace($red,[3=>80,4=>79,5=>86]),
    'sim_qpla'=>array_replace($red,[3=>88,6=>43,7=>94]), 'sim_def41'=>array_replace($red,[2=>41,3=>91]),
    'sim_pray'=>array_replace($red,[6=>43]), 'sim_str'=>array_replace($red,[3=>90]),
    '123456'=>$red, 'sim_hp10'=>[7=>80], 'sim_f2p'=>[9=>80,21=>70], 'sim_thief'=>[18=>99],
];
$ids = [];
foreach ($fixtures as $name => $levels) {
    if (fetch_rows($db, 'SELECT id FROM players WHERE name = ?', [(string)$name])) throw new RuntimeException('Similarity fixtures already exist; use a fresh test database');
    $before = $levels;
    if (isset($before[3])) $before[3] = min($before[3], 70);
    import_stats($db, (string)$name, similar_packet($before,$now-$week-3600000), $now-$week-3600000);
    import_stats($db, (string)$name, similar_packet($levels,$now), $now, true);
    $ids[(string)$name] = (int) fetch_rows($db,'SELECT id FROM players WHERE name = ?',[(string)$name])[0]['id'];
}
$rows = similar_players($db,$ids['sim_red'],$now);
similar_expect(count($rows) === 5, 'Rank the top five, not a fixed arbitrary candidate pool');
similar_expect(!in_array('sim_red',array_column($rows,'name'),true), 'Never recommend the target account to itself');
similar_expect(in_array('123456',array_column($rows,'name'),true), 'Numeric account names must remain strings');
foreach ($rows as $r) similar_expect(array_keys($r) === ['name','reasons'] && $r['reasons'], 'Public matches must expose reasons and never internal scores');
similar_expect($rows === similar_players($db,$ids['sim_red'],$now), 'Ranking and tie-breaking must be deterministic');
$data = get_data($db,['q'=>'player','name'=>'sim_red'],$now);
similar_expect($data['similar'] === $rows, 'The real player API must carry the same top five');
$html = player_view(page_state('/player/sim_red',[],$now)+['clans'=>[]],$data);
similar_expect(substr_count($html,'class="similar-player"') === 5 && str_contains($html,'<h2>Similar players</h2>'), 'The full player page must render five matches in its aside');
$db->prepare('UPDATE similarity SET model = ? WHERE player_id = ?')->execute(['obsolete',$ids['sim_hp10']]);
try {similar_players($db,$ids['sim_red'],$now); throw new LogicException('Missing models must fail explicitly');}
catch (RuntimeException $e) {similar_expect(str_contains($e->getMessage(),'--similar'), 'Missing models must explain the repair command');}
rebuild_similar($db,$now);
similar_expect(similar_players($db,$ids['sim_red'],$now) === $rows, 'Rebuild must preserve the callback-produced ranking');
$cached = fetch_rows($db,'SELECT profile FROM similarity WHERE player_id = ?',[$ids['sim_red']])[0]['profile'];
$p = json_decode($cached,true,flags:JSON_THROW_ON_ERROR);
similar_expect(isset($p['stable']['defence']) && $p['recent'], 'Database history must strengthen maintained builds and populate recent focus');
$db->prepare('UPDATE similarity SET at = ? WHERE player_id = ?')->execute([$now-3*3600000,$ids['sim_red']]);
try {similar_players($db,$ids['sim_red'],$now); throw new LogicException('Stale recent projections must fail explicitly');}
catch (RuntimeException $e) {similar_expect(str_contains($e->getMessage(),'hourly refresh'), 'Stale recent projections must point to the refresh job');}
rebuild_similar($db,$now,true);
$after = $now + $week + 1;
rebuild_similar($db,$after,true);
$p = json_decode(fetch_rows($db,'SELECT profile FROM similarity WHERE player_id = ?',[$ids['sim_red']])[0]['profile'],true,flags:JSON_THROW_ON_ERROR);
similar_expect(!$p['recent'], 'Hourly refresh must age out training focus even without a new callback');
// A duplicate timestamp must neither add history nor perturb the cached ranking.
$beforeCount = $db->query('SELECT COUNT(*) FROM xp')->fetchColumn();
import_stats($db,'sim_red',similar_packet($red,$now),$now,true);
similar_expect($db->query('SELECT COUNT(*) FROM xp')->fetchColumn() === $beforeCount, 'Duplicate callbacks must retain history idempotency');
echo "Similarity DB: callbacks, real history, top-five API/SSR, numeric names, deterministic ranking, model/staleness errors and rolling refresh passed\n";
