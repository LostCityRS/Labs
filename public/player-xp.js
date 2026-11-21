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

const date = new Intl.DateTimeFormat('en-GB', {timeZone:'UTC', day:'numeric', month:'short'});
const clock = new Intl.DateTimeFormat('en-GB', {timeZone:'UTC', hour:'2-digit', minute:'2-digit'});
const xp = new Intl.NumberFormat('en-GB', {maximumFractionDigits:0, signDisplay:'exceptZero'});
const compact = new Intl.NumberFormat('en-GB', {notation:'compact', maximumFractionDigits:1});

export function durationLabel(ms) {
  const minutes = Math.round(ms/60_000);
  const hours = Math.floor(minutes/60);
  return [hours ? `${hours} hr` : '', minutes%60 ? `${minutes%60}m` : ''].filter(Boolean).join(' ') || '<1m';
}

export function pointIndex(xs, x) {
  const next = xs.findIndex(value => value > x);
  return next < 0 ? xs.length - 1 : next - 1;
}

function render(wrap, data) {
  const {now, start, days} = data;
  const {points, baseline} = xpSeries(data);
  const daily = days > 0;
  const panel = wrap.closest('.player-xp');
  const readout = panel.querySelector('.hover-readout');
  const status = panel.querySelector('.hover-status');
  let width = 0, clear = () => {};

  function draw() {
    const size = Math.floor(wrap.clientWidth);
    if (size === width || size < 120) return;
    width = size;
    if (!points.some(p => p.count)) { wrap.innerHTML = '<p class="empty">No recorded updates.</p>'; return; }
    const height = width < 400 ? 176 : 212, top = 11, left = 43, right = width-12, bottom = height-22;
    const min = Math.min(0,...points.map(p=>p.running)), max = Math.max(0,...points.map(p=>p.running));
    const target = (max-min)/3 || 10;
    const power = 10**Math.floor(Math.log10(target));
    const step = [1,2,2.5,5,10].map(n=>n*power).find(n=>n>=target);
    const lo = Math.floor(min/step)*step, hi = Math.max(step,Math.ceil(max/step)*step);
    const x = t => left+(right-left)*t;
    const y = v => bottom-(bottom-top)*(v-lo)/(hi-lo);
    // Align daily steps with the day boundaries so the cursor never reads the next day's value early.
    const xs = points.map((p,i) => daily ? x(i/points.length) : x((p.at-start)/(now-start)));
    let svg = `<svg class="hover-svg" viewBox="0 0 ${width} ${height}" tabindex="0" role="group" aria-label="Cumulative XP gained. Use left and right arrows to inspect recorded points.">`;
    for (let v=lo; v<=hi; v+=step) svg += `<path class="${v===0?'hover-zero':'hover-grid'}" d="M${left},${y(v)}H${right}"/><text x="${left-6}" y="${y(v)+3}" text-anchor="end">${compact.format(v/10)}</text>`;
    const labels = daily
      ? points.map((p,i)=>[x((i+.5)/points.length),i===points.length-1?'Today':date.format(p.at)]).filter(([px],i)=>i===points.length-1||(x(1-.5/points.length)-px>=49&&i%Math.ceil(points.length/(width<440?4:7))===0))
      : [[left,'24h ago'],[x(.5),'12h ago'],[right,'Now']];
    for (const [px,text] of labels) svg += `<text x="${px}" y="${height-4}" text-anchor="${px<left+24?'start':px>right-25?'end':'middle'}">${text}</text>`;
    let path = baseline ? `M${left},${y(0)}` : '';
    let last = -1;
    points.forEach((p,i)=>{
      if (p.running === null) return;
      path += path ? `H${xs[i]}V${y(p.running)}` : `M${xs[i]},${y(p.running)}`;
      last = i;
    });
    if (daily && last>=0) path += `H${right}`;
    svg += `<path class="hover-line" d="${path}"/>`;
    if (!daily && last>=0) svg += `<path class="hover-line hover-extension" d="M${xs[last]},${y(points[last].running)}H${right}"/>`;
    wrap.innerHTML = svg + `<rect class="hover-range" y="${top}" height="${bottom-top}" visibility="hidden"/><g class="hover-marker" visibility="hidden"><line class="hover-guide" y1="${top}" y2="${bottom}"/><circle class="hover-dot" r="3.6"/></g><rect class="hover-hit" x="${left}" y="${top}" width="${right-left}" height="${bottom-top}"/></svg>`;
    const plot = wrap.querySelector('svg');
    const marker = plot.querySelector('.hover-marker'), guide = marker.querySelector('line'), dot = marker.querySelector('circle');
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
    function reading(px, index = pointIndex(xs,px), exact = false) {
      const p = points[index];
      const at = daily || exact ? p.at : start+(px-left)/(right-left)*day;
      const running = p ? p.running : baseline ? 0 : null;
      return {at, running, index, noUpdate:daily && !p.count};
    }
    function show(px, point, announce = false) {
      const {at, running, index, noUpdate} = point;
      active = index;
      const value = noUpdate ? 'No update' : running===null ? 'No baseline' : `${xp.format(running/10)} XP`;
      const when = daily ? date.format(at) : `${date.format(at)} ${clock.format(at)} UTC`;
      range.setAttribute('visibility','hidden');
      marker.setAttribute('visibility','visible');
      for (const attr of ['x1','x2']) guide.setAttribute(attr,px);
      dot.setAttribute('cx',px); dot.setAttribute('cy',y(running ?? 0));
      dot.setAttribute('visibility',running===null?'hidden':'visible');
      marker.toggleAttribute('data-pinned',pinned);
      readout.innerHTML = `${when} · <b>${value}</b>`;
      if (announce) status.textContent = `${when}, ${value}`;
    }
    function showRange(a, b, announce = false) {
      const from = Math.min(a,b), to = Math.max(a,b);
      const first = reading(from), last = reading(to);
      const span = last.at-first.at;
      const duration = daily ? `${span/day}d` : durationLabel(span);
      const value = first.running===null || last.running===null ? 'No baseline' : `${xp.format((last.running-first.running)/10)}xp`;
      const sameDay = Math.floor(first.at/day)===Math.floor(last.at/day);
      const fromLabel = daily ? date.format(first.at) : `${date.format(first.at)} ${clock.format(first.at)}`;
      const toLabel = daily ? date.format(last.at) : `${sameDay ? '' : date.format(last.at)+' '}${clock.format(last.at)}`;
      active = last.index;
      marker.setAttribute('visibility','hidden');
      range.setAttribute('x',from); range.setAttribute('width',to-from);
      range.setAttribute('visibility','visible');
      readout.innerHTML = `<span class="hover-window" title="${fromLabel}–${toLabel} UTC"><b>${value}</b> ${duration === '<1m' ? '&lt;1m' : duration}</span>`;
      if (announce) status.textContent = `${fromLabel} to ${toLabel} UTC, ${value} in ${duration}`;
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
        show(px,reading(px));
      }
    });
    hit.addEventListener('pointerup', event => {
      if (gesture?.id!==event.pointerId) return;
      const {drag,x:from,clientX} = gesture;
      gesture = null;
      const px = pointerX(event);
      if (drag || Math.abs(event.clientX-clientX)>=5) { pinned = true; showRange(from,px,true); }
      else if (pinned && event.pointerType==='touch') clear();
      else { pinned = !pinned; show(px,reading(px),true); }
    });
    hit.addEventListener('pointerleave', () => { if (!pinned && !gesture) clear(); });
    hit.addEventListener('lostpointercapture', event => { if (gesture?.id===event.pointerId) clear(); });
    plot.addEventListener('keydown', event => {
      if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
      event.preventDefault();
      const index = event.key==='Home' ? 0 : event.key==='End' ? points.length-1 : active < 0
        ? (event.key==='ArrowLeft'?points.length-1:0)
        : Math.max(0,Math.min(points.length-1,active+(event.key==='ArrowLeft'?-1:1)));
      show(xs[index],reading(xs[index],index,true),true);
    });
    plot.addEventListener('blur', () => { if (!pinned) clear(); });
  }
  document.addEventListener('pointerdown', event=>{ if (!wrap.contains(event.target) || !event.target.closest('.hover-hit')) clear(); });
  document.addEventListener('keydown', event=>{ if (event.key==='Escape') clear(); });
  new ResizeObserver(draw).observe(wrap);
  draw();
}

const plot = globalThis.document?.querySelector('.hover-plot');
if (plot) render(plot, JSON.parse(plot.dataset.xp));
