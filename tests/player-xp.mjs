import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const js = readFileSync(new URL('../public/player-xp.js', import.meta.url));
const {xpSeries, pointIndex, durationLabel} = await import('data:text/javascript;base64,' + js.toString('base64'));
const day = 86_400_000;
const start = 10 * day;
const samples = [[start-2000, 999900], [start-1000, 1000000], [start, 1000500], [start+3000, 1008300], [start+2*day, 1010000]];
const {points, baseline} = xpSeries({samples, start, now:start+day-1, days:0});
assert.equal(baseline, true);
assert.deepEqual(points.map(p => [p.at,p.running]), [[start,500],[start+3000,8300]]);
const x = points.map(p => p.at);
assert.equal(pointIndex(x,start-1), -1);
assert.equal(pointIndex(x,start+2999), 0);
assert.equal(pointIndex(x,start+3000), 1);
assert.equal(pointIndex(x,start+6000), 1);
assert.equal((points[1].running-points[0].running)/10, 780);
assert.equal(durationLabel((4*60+58)*60_000), '4 hr 58m');
assert.equal(durationLabel(12_000), '<1m');
for (const days of [7,30]) {
  const daily = xpSeries({samples, start, now:start+(days-1)*day+10000, days}).points;
  assert.equal(daily.length, days);
  assert.deepEqual(daily.slice(0,3).map(p => [p.count,p.running]), [[2,8300],[0,8300],[1,10000]]);
  assert.equal(daily.at(-1).at, start+(days-1)*day);
  assert.equal(daily.at(-1).running, 10000);
}
const young = xpSeries({samples:samples.slice(-2), start, now:start+6*day, days:7});
assert.equal(young.baseline, false);
assert.equal(young.points[0].running, 0);
const late = xpSeries({samples:samples.slice(-1), start, now:start+6*day, days:7});
assert.deepEqual(late.points.slice(0,3).map(p=>p.running), [null,null,0]);
console.log('Player XP: baselines, raw updates, UTC buckets, as-of readings, and range format passed');
