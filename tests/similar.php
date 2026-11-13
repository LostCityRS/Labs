<?php
require_once __DIR__ . '/../src/views.php';
function similar_expect(bool $ok, string $why): void {if (!$ok) throw new RuntimeException($why);}
function similar_xp(int $level): int {
    $points = 0;
    for ($i = 1; $i < $level; $i++) $points += floor($i + 300 * 2 ** ($i / 7));
    return (int) floor($points / 4) * 10;
}
function similar_packet(array $levels, int $at, array $extra = []): array {
    $rows = []; $sum = 0; $total = 0;
    foreach (array_diff_key(SKILLS, [0 => true]) as $t => $_) {
        $level = $levels[$t] ?? ($t === 4 ? 10 : 1);
        $value = $extra[$t] ?? similar_xp($level);
        $rows[] = ['type' => $t, 'level' => $level, 'value' => $value, 'at' => $at, 'rank' => 100];
        $sum += $value; $total += $level;
    }
    return [['type' => 0, 'level' => $total, 'value' => $sum, 'at' => $at, 'rank' => 100], ...$rows];
}
if (realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) return;
$now = strtotime('2026-11-13 08:00 UTC') * 1000; $week = 7 * 86400000;
$profile = fn(array $l, array $before = [], array $extra = []) => similar_profile(similar_packet($l, $now, $extra), $before, $now);
$red = [1=>62,2=>40,3=>93,4=>92,5=>99,6=>52,7=>74,17=>73];
$a = $profile($red);
similar_expect(isset($a['traits']['defence'], $a['traits']['attack'], $a['traits']['prayer']), 'Developed restricted builds must expose Defence, Attack and Prayer');
$novice = $profile([1=>20,2=>20,3=>20,4=>20]);
similar_expect(!$novice['traits'], 'An unfinished novice is not a restricted build or an F2P specialist');
$skiller = $profile([9=>80]);
similar_expect(isset($skiller['traits']['skiller'], $skiller['traits']['hp']), 'Developed combat-3 skillers and 10-HP builds must be recognised');
$mage = $profile([7=>80]);
similar_expect(isset($mage['traits']['hp']) && !isset($mage['traits']['skiller']), '10-HP mages are not combat-3 skillers');
$boundedHp = array_values(array_filter(similar_packet([9=>80,16=>3],$now), fn($r) => !in_array($r['type'],[4,16])));
$bounded = similar_profile($boundedHp,[],$now);
similar_expect(isset($bounded['traits']['hp']) && $bounded['ehp'] === null && !isset(similar_snapshot($boundedHp)['stats'][4]), 'Totals may prove exactly 10 HP without inventing its unknown XP or complete EHP');
$eleven = $profile([4=>11,7=>80]);
similar_expect(similar_closeness('hp', 10, 11) === 0.0 && isset($eleven['traits']['hp']), 'Exact 10-HP builds must be distinct from other low-HP builds');
$ordinaryMage = $profile([4=>70,7=>80]);
$hpWeights = similar_weights($mage,[$mage,$skiller,$ordinaryMage]);
similar_expect(similar_score($mage,$skiller,$hpWeights,$now)['score'] > similar_score($mage,$ordinaryMage,$hpWeights,$now)['score'], 'A 10-HP build must prefer another proven 10-HP account over an ordinary account with incidental combat overlaps');
$defence = $profile([2=>70,4=>60]);
similar_expect(isset($defence['traits']['defence_only']), 'High Defence without offence is a Defence-focused build');
$hybrid = $profile([1=>40,3=>40,4=>75,5=>85,7=>85]);
similar_expect(isset($hybrid['traits']['range_mage'], $hybrid['traits']['range_only']), 'High Ranged/Magic with low melee must match as an unusual combination');
$strength = $profile([3=>90,4=>75]);
similar_expect(isset($strength['traits']['strength_only']), 'Low-Attack Strength specialists must be recognised');
similar_expect(similar_closeness('defence', 40, 41) > 0 && similar_closeness('defence', 40, 45) === 0.0, 'Defence endpoints allow one level, not a different build');
$p1 = $profile([2=>1,3=>90,4=>75]); $p2 = $profile([2=>2,3=>90,4=>75]);
similar_expect($p1['traits']['defence'] === $p2['traits']['defence'], 'One and two Defence share a pure build');
$f2p = $profile([9=>80,21=>70]);
similar_expect(isset($f2p['traits']['f2p']), 'Runecrafting is F2P, not a members-only skill');
$lowMembers = $profile([9=>80,10=>10,16=>3,17=>5,18=>10]);
similar_expect(isset($lowMembers['traits']['f2p']), 'Extremely low members skills still support an F2P-like match');
$member = $profile([9=>80,18=>30]);
similar_expect(!isset($member['traits']['f2p']), 'Developed members skills must remove the F2P-like trait');
$rows = similar_packet([9=>80], $now);
$omit = array_values(array_filter($rows, fn($r) => !in_array($r['type'], SIMILAR_MEMBERS)));
$proven = similar_profile($omit, [], $now);
similar_expect(isset($proven['traits']['f2p']) && $proven['ehp'] !== null, 'Coherent Overall totals may prove omitted members skills are untrained');
$unknown = similar_profile(array_values(array_filter($omit, fn($r) => $r['type'] !== 0)), [], $now);
similar_expect(!isset($unknown['traits']['f2p']) && $unknown['ehp'] === null && !$unknown['shape'], 'Unknown members skills must not be fabricated as zero');
$stale = $rows;
foreach ($stale as &$r) if (in_array($r['type'], SIMILAR_MEMBERS)) $r['at'] -= $week;
unset($r);
$stale = array_values(array_filter($stale, fn($r) => $r['type'] !== 0));
similar_expect(!isset(similar_profile($stale, [], $now)['traits']['f2p']), 'Stale low members stats do not prove a current F2P-like build');
$before = similar_packet(array_replace($red, [3=>85,17=>65]), $now - $week - 1);
$history = $profile($red, $before);
similar_expect(isset($history['stable']['defence'], $history['stable']['attack'], $history['stable']['prayer']), 'Unchanged restrictions while other skills grow for a week must strengthen evidence');
similar_expect((bool) $history['recent'], 'Observed training over the baseline must produce a recent focus');
$changed = $profile(array_replace($red, [2=>41]), $before);
similar_expect(!isset($changed['stable']['defence']), 'A changing Defence level must not be called maintained');
$short = $profile($red, similar_packet(array_replace($red, [3=>85]), $now - 3600000));
similar_expect(!isset($short['stable']['defence']), 'One hour of observation is not a week of maintained restrictions');
$unchanged = $profile($red, similar_packet($red, $now - 2 * $week));
similar_expect(!$unchanged['stable'] && !$unchanged['recent'], 'Unchanged snapshots alone are not training evidence');
$inferredNow = array_values(array_filter(similar_packet($red,$now), fn($r) => $r['type'] !== 2));
$inferredOld = array_values(array_filter(similar_packet(array_replace($red,[3=>85]),$now-$week-1), fn($r) => $r['type'] !== 2));
$inferredHistory = similar_profile($inferredNow,$inferredOld,$now);
similar_expect(isset($inferredHistory['traits']['defence']) && !isset($inferredHistory['stable']['defence']), 'Inferred snapshot XP must never be claimed as observed restriction history');
$decreased = similar_packet($red,$now);
$increasedBefore = similar_packet(array_replace($red,[3=>94]),$now-$week);
try {similar_profile($decreased,$increasedBefore,$now); throw new LogicException('Decreasing observed XP must fail');}
catch (RuntimeException $e) {similar_expect(str_contains($e->getMessage(),'decreasing XP'), 'Observed XP corruption must remain an explicit error');}
$newSkill = similar_packet($red, $now - $week);
$newSkill = array_values(array_filter($newSkill, fn($r) => !in_array($r['type'], [0,17])));
$onlyNew = $profile($red, $newSkill);
similar_expect(!$onlyNew['recent'], 'Newly reported lifetime XP must not become recent gains');
$post = $profile([18=>99], [], [18=>300000000]);
$morePost = $profile([18=>99], [], [18=>350000000]);
$barely99 = $profile([18=>99], [], [18=>130350000]);
similar_expect(isset($post['post99'][18]) && !isset($barely99['post99'][18]), 'Substantial post-99 XP, not barely reaching 99, defines a specialist');
$weights = similar_weights($post, [$post,$morePost,$barely99,$a]);
$match = similar_score($post,$morePost,$weights,$now);
similar_expect(in_array('Thieving beyond 99', $match['reasons']), 'Post-99 matches must explain the shared skill');
$weights = similar_weights($history, [$history,$a,$member]);
similar_expect(similar_score($history,$history,$weights,$now)['score'] > similar_score($history,$a,$weights,$now)['score'], 'Shared maintained restrictions and recent training must strengthen the ranking');
$withoutRecent = $history; $withoutRecent['recent'] = [];
$withoutHistory = $withoutRecent; $withoutHistory['stable'] = [];
similar_expect(similar_score($withoutRecent,$withoutRecent,$weights,$now)['score'] > similar_score($withoutRecent,$withoutHistory,$weights,$now)['score'], 'History must still strengthen highly similar builds when the build-category cap is reached');
$broken = similar_packet([9=>80], $now); $broken[0]['value'] += 100;
$bad = similar_profile($broken, [], $now);
similar_expect($bad['coherent'] === false && !$bad['shape'] && $bad['ehp'] === null, 'Contradictory totals must be flagged and cannot yield trustworthy EHP shares');
$expired = $history; $expired['active_at'] = $now - $week - 1;
similar_expect(similar_score($history,$expired,$weights,$now)['score'] < similar_score($history,$history,$weights,$now)['score'], 'Old training focus must not stay active forever');
try {similar_closeness('misspelled',1,1); throw new LogicException('Unknown traits must fail');}
catch (RuntimeException $e) {similar_expect(str_contains($e->getMessage(),'Unknown similarity trait'), 'Unknown traits must not silently match');}
$plain = ['traits'=>[], 'stable'=>[], 'shape'=>[], 'post99'=>[], 'recent'=>[], 'active_at'=>0, 'ehp'=>1000];
similar_expect(similar_score($plain,$plain,similar_weights($plain,[$plain]),$now)['score'] == 0, 'Total EHP alone must not manufacture matches');
$html = similar_panel([['name'=>'qpla', 'score'=>999, 'reasons'=>['40 Defence', '<unsafe>']]]);
similar_expect(str_contains($html,'href="/player/qpla"') && str_contains($html,'>Qpla</a>') && !str_contains($html,'<small>') && !str_contains($html,'clan-icon') && !str_contains($html,'40 Defence') && !str_contains($html,'unsafe') && !str_contains($html,'999'), 'Panel must show only player names and native links, without explanations, badges or scores');
similar_expect(str_contains(similar_panel([['name'=>'qpla']]), '>Qpla</a>'), 'Rendering names must not require explanatory metadata');
similar_expect(str_contains(similar_panel([]), 'Not enough shared traits yet.'), 'Insufficient evidence needs an honest empty state');
echo "Similar players: build restrictions, 10 HP, F2P inference, missing/stale data, history, recent focus, post-99 and rendering passed\n";
