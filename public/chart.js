const day = 86_400_000;

export function xpSeries({samples, start, now, days}) {
  let baseline = false, previous = null, running = 0;
  const points = days ? Array.from({length:days}, (_,i) => ({at:start+i*day,count:0,running:null})) : [];
  for (const [at,value] of samples) {
    if (at < start) { baseline = true; previous = value; continue; }
    if (at > now) break;
    if (previous !== null) running += value-previous;
    previous = value;
    if (!days) points.push({at,count:1,running});
    else {
      const point = points[Math.floor((at-start)/day)];
      point.count++;
      point.running = running;
    }
  }
  let last = baseline ? 0 : null;
  for (const point of points) {
    if (point.count) last = point.running;
    else point.running = last;
  }
  return {points,baseline};
}

// Player history arrives as raw snapshots; every other chart is already a display-unit series.
function playerChart({samples, start, now, days}) {
  const {points, baseline} = xpSeries({samples, start, now, days});
  const values = points.map(p => [p.at, p.running === null ? null : p.running/10]);
  if (!days && baseline && (!values.length || values[0][0] > start)) values.unshift([start, 0]);
  return {name:'XP gained', unit:'XP', signed:true, zero:true, daily:days > 0, start, now, end:days ? start+days*day : now, lines:[{points:values}]};
}

// Daily values belong to their own UTC day only; other series hold their last value until the next update.
export function valueAt(points, t, daily) {
  if (daily) return points.find(p => p[0] === Math.floor(t/day)*day)?.[1] ?? null;
  let value = null;
  for (const [at,v] of points) {
    if (at > t) break;
    value = v;
  }
  return value;
}

export function windowValue(points, from, to, daily, measure) {
  if (measure === 'sum') {
    const inside = points.filter(([at,v]) => v !== null && at >= Math.floor(from/day)*day && at <= Math.floor(to/day)*day);
    return inside.length ? inside.reduce((total,[,v]) => total+v, 0) : null;
  }
  const first = valueAt(points, from, daily), last = valueAt(points, to, daily);
  return first === null || last === null ? null : last-first;
}

export function durationLabel(ms) {
  const minutes = Math.round(ms/60_000);
  const days = Math.floor(minutes/1440), hours = Math.floor(minutes/60)%24;
  // Past a day, minutes are noise.
  if (days) return [`${days}d`, hours ? `${hours} hr` : ''].filter(Boolean).join(' ');
  return [hours ? `${hours} hr` : '', minutes%60 ? `${minutes%60}m` : ''].filter(Boolean).join(' ') || '<1m';
}

export function windowLength(from, to, daily, measure) {
  if (!daily) return durationLabel(to-from);
  return `${Math.floor(to/day)-Math.floor(from/day)+(measure === 'sum' ? 1 : 0)}d`;
}

const significant = new Intl.NumberFormat('en-GB', {maximumSignificantDigits:2});
const hour = x => `${String(x).padStart(2,'0')}:00`;
const percent = x => `${significant.format(10**(x-2))}%`;
// Non-time x axes; x is the hour, or log10 of the top percentile plus 2.
export const AXES = {
  hour: {one: x => `${hour(x)} UTC`, range: (a,b) => `${hour(a)}–${hour(b)} UTC`},
  percentile: {one: x => `Top ${percent(x)}`, range: (a,b) => `Top ${percent(a)}–${percent(b)}`},
};

const date = new Intl.DateTimeFormat('en-GB', {timeZone:'UTC', day:'numeric', month:'short'});
const clock = new Intl.DateTimeFormat('en-GB', {timeZone:'UTC', hour:'2-digit', minute:'2-digit'});
const compact = new Intl.NumberFormat('en-GB', {notation:'compact', maximumFractionDigits:1});

function render(wrap, input) {
  const data = input.samples ? playerChart(input) : input;
  const {start, end, now, daily, lines, unit, measure = 'delta', marker: nowMarker} = data;
  const axis = data.axis && AXES[data.axis.format];
  const scope = wrap.closest('section');
  const readout = scope.querySelector('.hover-readout'), status = scope.querySelector('.hover-status');
  const number = (v, sign) => new Intl.NumberFormat('en-GB', {maximumFractionDigits:data.decimals ?? 0, signDisplay:sign ? 'exceptZero' : 'auto'}).format(v);
  const withUnit = n => unit === '%' ? `${n}%` : `${n} ${unit}`;
  const format = v => v === null ? 'No data' : withUnit(number(v, data.signed));
  const formatWindow = v => v === null ? 'No data' : unit === 'XP' ? `${number(v, true)}xp` : withUnit(number(v, true));
  const items = (values, html) => values.map((v,i) => lines.length > 1 ? `<span class="chart-item"><span class="chart-key" style="--chart:${lines[i].color}">${lines[i].label}</span> <b>${html(v)}</b></span>` : `<b>${html(v)}</b>`).join(' ');
  const keys = lines[0].points.filter(p => p[1] !== null);
  const heldAt = t => keys.findLast(p => p[0] <= t)?.[0] ?? keys[0][0];
  let width = 0, clear = () => {};

  function draw() {
    const size = Math.floor(wrap.clientWidth);
    if (size === width || size < 120) return;
    width = size;
    const values = lines.flatMap(l => l.points.map(p => p[1])).filter(v => v !== null);
    if (!values.length) { wrap.innerHTML = '<p class="empty">No data yet.</p>'; return; }
    const height = data.height && width >= 400 ? data.height : width < 400 ? 176 : 212, top = nowMarker ? 26 : 11, left = 43, right = width-12, bottom = height-22;
    const min = Math.min(...values, ...(data.zero ? [0] : [])), max = Math.max(...values, ...(data.zero ? [0] : []));
    const target = (max-min)/3 || 10;
    const power = 10**Math.floor(Math.log10(target));
    let step = [1,2,2.5,5,10].map(n=>n*power).find(n=>n>=target);
    if (values.every(Number.isInteger)) step = Math.max(1, Math.ceil(step));
    const lo = Math.floor(min/step)*step, hi = Math.max(lo+step, Math.ceil(max/step)*step);
    const x = t => left+(right-left)*(t-start)/(end-start);
    const time = px => start+(px-left)/(right-left)*(end-start);
    const y = v => bottom-(bottom-top)*(v-lo)/(hi-lo);
    let svg = `<svg class="hover-svg" viewBox="0 0 ${width} ${height}" tabindex="0" role="group" aria-label="${data.name}. Use left and right arrows to inspect points.">`;
    for (let i=0; i<=Math.round((hi-lo)/step); i++) {
      const v = lo+i*step;
      svg += `<path class="${v===0?'hover-zero':'hover-grid'}" d="M${left},${y(v)}H${right}"/><text x="${left-6}" y="${y(v)+3}" text-anchor="end">${compact.format(v)}${unit === '%' ? '%' : ''}</text>`;
    }
    let labels;
    if (axis) {
      labels = data.axis.ticks.filter((_,i,all) => width >= 440 || i%2 === 0 || i === all.length-1).map(([at,text]) => [x(at),text]);
    } else if (daily) {
      const days = Array.from({length:Math.round((end-start)/day)}, (_,i) => start+i*day);
      const every = Math.ceil(days.length/(width<440?4:7)), lastX = x(days.at(-1)+day/2);
      labels = days.map((t,i) => [x(t+day/2), t === Math.floor(now/day)*day ? 'Today' : date.format(t), i])
        .filter(([px,,i]) => i === days.length-1 || (lastX-px >= 49 && i%every === 0));
    } else {
      const span = end-start, unitMs = span <= 2*day ? 3_600_000 : day, ago = ms => `${ms/unitMs}${unitMs === day ? 'd' : 'h'} ago`;
      labels = [[left, ago(span)], ...(Number.isInteger(span/2/unitMs) ? [[x(start+span/2), ago(span/2)]] : []), [right, 'Now']];
    }
    const anchor = px => px<left+24 ? 'start' : px>right-25 ? 'end' : 'middle';
    for (const [px,text] of labels) svg += `<text x="${px}" y="${height-4}" text-anchor="${anchor(px)}">${text}</text>`;
    if (nowMarker) svg += `<g class="marker"><line x1="${x(nowMarker[0])}" x2="${x(nowMarker[0])}" y1="${top-6}" y2="${bottom}"/><text x="${x(nowMarker[0])}" y="${top-10}" text-anchor="${anchor(x(nowMarker[0]))}">${nowMarker[1]}</text></g>`;
    for (const line of lines) {
      let path = '';
      for (const [at,v] of line.points) if (v !== null) path += path ? `H${x(at)}V${y(v)}` : `M${x(at)},${y(v)}`;
      if (path) svg += `<path class="hover-line" d="${path}H${right}"${line.color ? ` style="--chart:${line.color}"` : ''}/>`;
    }
    const dots = lines.map(l => `<circle class="hover-dot" r="3.6"${l.color ? ` style="--dot:${l.color}"` : ''}/>`).join('');
    wrap.innerHTML = svg + `<rect class="hover-range" y="${top}" height="${bottom-top}" visibility="hidden"/><g class="hover-marker" visibility="hidden"><line class="hover-guide" y1="${top}" y2="${bottom}"/>${dots}</g><rect class="hover-hit" x="${left}" y="${top}" width="${right-left}" height="${bottom-top}"/></svg>`;
    const plot = wrap.querySelector('svg');
    const marker = plot.querySelector('.hover-marker'), guide = marker.querySelector('line'), circles = [...marker.querySelectorAll('circle')];
    const range = plot.querySelector('.hover-range'), hit = plot.querySelector('.hover-hit');
    let pinned = false, active = -1, gesture = null;
    clear = () => {
      marker.setAttribute('visibility','hidden');
      marker.removeAttribute('data-pinned');
      range.setAttribute('visibility','hidden');
      readout.textContent = status.textContent = '';
      pinned = false; active = -1; gesture = null;
    };
    clear();
    function show(px, announce = false) {
      const t = time(px);
      const values = lines.map(l => valueAt(l.points, t, daily));
      const when = axis ? axis.one(heldAt(t)) : daily ? date.format(t) : `${date.format(t)} ${clock.format(t)} UTC`;
      active = keys.findLastIndex(p => p[0] <= t);
      range.setAttribute('visibility','hidden');
      marker.setAttribute('visibility','visible');
      for (const attr of ['x1','x2']) guide.setAttribute(attr,px);
      values.forEach((v,i) => {
        circles[i].setAttribute('cx',px); circles[i].setAttribute('cy',y(v ?? lo));
        circles[i].setAttribute('visibility',v===null?'hidden':'inherit');
      });
      marker.toggleAttribute('data-pinned',pinned);
      readout.innerHTML = `${when} · ${items(values, format)}`;
      if (announce) status.textContent = readout.textContent;
    }
    function showRange(a, b, announce = false) {
      const from = time(Math.min(a,b)), to = time(Math.max(a,b));
      const values = lines.map(l => windowValue(l.points, from, to, daily, measure));
      const length = axis ? axis.range(heldAt(from), heldAt(to)) : windowLength(from, to, daily, measure);
      const sameDay = Math.floor(from/day) === Math.floor(to/day);
      const fromLabel = daily ? date.format(from) : `${date.format(from)} ${clock.format(from)}`;
      const toLabel = daily ? date.format(to) : `${sameDay ? '' : date.format(to)+' '}${clock.format(to)}`;
      marker.setAttribute('visibility','hidden');
      range.setAttribute('x',Math.min(a,b)); range.setAttribute('width',Math.abs(b-a));
      range.setAttribute('visibility','visible');
      readout.innerHTML = `<span class="hover-window" title="${fromLabel}–${toLabel} UTC">${items(values, formatWindow)} <span class="chart-item">${length === '<1m' ? '&lt;1m' : length}</span></span>`;
      if (announce) status.textContent = `${fromLabel} to ${toLabel} UTC, ${readout.textContent}`;
    }
    function pointerX(event) {
      const bounds = plot.getBoundingClientRect();
      return Math.max(left,Math.min(right,(event.clientX-bounds.left)/bounds.width*width));
    }
    hit.addEventListener('pointerdown', event => {
      if (gesture || !event.isPrimary || event.button!==0) return;
      gesture = {id:event.pointerId,x:pointerX(event),clientX:event.clientX,drag:false};
      hit.setPointerCapture(event.pointerId);
    });
    hit.addEventListener('pointermove', event => {
      const px = pointerX(event);
      if (gesture?.id===event.pointerId) {
        gesture.drag ||= Math.abs(event.clientX-gesture.clientX)>=5;
        if (gesture.drag) showRange(gesture.x,px);
      } else if (!pinned && event.pointerType!=='touch') {
        show(px);
      }
    });
    hit.addEventListener('pointerup', event => {
      if (gesture?.id!==event.pointerId) return;
      const {drag,x:from,clientX} = gesture;
      gesture = null;
      const px = pointerX(event);
      if (drag || Math.abs(event.clientX-clientX)>=5) { pinned = true; showRange(from,px,true); }
      else if (pinned && event.pointerType==='touch') clear();
      else { pinned = !pinned; show(px,true); }
    });
    hit.addEventListener('pointerleave', () => { if (!pinned && !gesture) clear(); });
    hit.addEventListener('lostpointercapture', event => { if (gesture?.id===event.pointerId) clear(); });
    plot.addEventListener('keydown', event => {
      if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key) || !keys.length) return;
      event.preventDefault();
      const index = event.key==='Home' ? 0 : event.key==='End' ? keys.length-1 : active < 0
        ? (event.key==='ArrowLeft'?keys.length-1:0)
        : Math.max(0,Math.min(keys.length-1,active+(event.key==='ArrowLeft'?-1:1)));
      show(Math.max(left,x(keys[index][0])),true);
    });
    plot.addEventListener('blur', () => { if (!pinned) clear(); });
  }
  document.addEventListener('pointerdown', event=>{ if (!wrap.contains(event.target) || !event.target.closest('.hover-hit')) clear(); });
  document.addEventListener('keydown', event=>{ if (event.key==='Escape') clear(); });
  new ResizeObserver(draw).observe(wrap);
  draw();
}

for (const wrap of globalThis.document?.querySelectorAll('.hover-plot') ?? []) render(wrap, JSON.parse(wrap.dataset.chart));
