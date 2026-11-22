import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const js = readFileSync(new URL('../public/chart.js', import.meta.url));
const {xpSeries, valueAt, windowValue, windowLength, durationLabel, AXES} = await import('data:text/javascript;base64,' + js.toString('base64'));
const day = 86_400_000;
const start = 10 * day;
const samples = [[start-2000, 999900], [start-1000, 1000000], [start, 1000500], [start+3000, 1008300], [start+2*day, 1010000]];
const {points, baseline} = xpSeries({samples, start, now:start+day-1, days:0});
assert.equal(baseline, true);
assert.deepEqual(points.map(p => [p.at,p.running]), [[start,500],[start+3000,8300]]);
for (const days of [7,30]) {
  const daily = xpSeries({samples, start, now:start+(days-1)*day+10000, days}).points;
  assert.equal(daily.length, days);
  assert.deepEqual(daily.slice(0,3).map(p => [p.count,p.running]), [[2,8300],[0,8300],[1,10000]]);
  assert.equal(daily.at(-1).running, 10000);
}
assert.equal(xpSeries({samples:samples.slice(-2), start, now:start+6*day, days:7}).points[0].running, 0);
assert.deepEqual(xpSeries({samples:samples.slice(-1), start, now:start+6*day, days:7}).points.slice(0,3).map(p=>p.running), [null,null,0]);

// Updates hold until the next one; nothing is read before the first.
const updates = [[start,50],[start+3000,830]];
assert.equal(valueAt(updates, start-1, false), null);
assert.equal(valueAt(updates, start+2999, false), 50);
assert.equal(valueAt(updates, start+6000, false), 830);
assert.equal(windowValue(updates, start+1000, start+6000, false, 'delta'), 780);
assert.equal(durationLabel((4*60+58)*60_000), '4 hr 58m');
assert.equal(durationLabel(12_000), '<1m');
assert.equal(durationLabel(84*3_600_000+7*60_000), '3d 12 hr');
assert.equal(durationLabel(48*3_600_000), '2d');

// A daily value belongs to its own day only; a missing day is no data, not the previous day.
const days = [[start,5],[start+day,7],[start+3*day,11]];
assert.equal(valueAt(days, start+day+5, true), 7);
assert.equal(valueAt(days, start+2*day+5, true), null);
assert.equal(windowValue(days, start+5, start+3*day+5, true, 'sum'), 23);
assert.equal(windowLength(start+5, start+3*day+5, true, 'sum'), '4d');
assert.equal(windowValue(days, start+5, start+day+5, true, 'delta'), 2);
assert.equal(windowLength(start+5, start+day+5, true, 'delta'), '1d');
assert.equal(windowValue(days, start+5, start+2*day+5, true, 'delta'), null);
// Hour buckets and log-scale percentiles read as labels, not times.
assert.equal(AXES.hour.one(9), '09:00 UTC');
assert.equal(AXES.hour.range(6,14), '06:00–14:00 UTC');
assert.equal(AXES.percentile.one(2), 'Top 1%');
assert.equal(AXES.percentile.range(Math.log10(0.5)+2, 3), 'Top 0.5%–10%');
const hours = [[0,100],[1,120],[23,90]];
assert.equal(valueAt(hours, 5.5, false), 120);
assert.equal(windowValue(hours, 0.3, 23.9, false, 'delta'), -10);
console.log('Charts: hour and percentile axes, player baselines, UTC buckets, held and daily readings, window sums, changes and durations passed');
