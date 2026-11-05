const skills = ['Overall', 'Attack', 'Defence', 'Strength', 'Hitpoints', 'Ranged', 'Prayer', 'Magic', 'Cooking', 'Woodcutting', 'Fletching', 'Fishing', 'Firemaking', 'Crafting', 'Smithing', 'Mining', 'Herblore', 'Agility', 'Thieving', 'Runecrafting'];
const typeOf = name => {const i = skills.indexOf(name); return i === 19 ? 21 : i;};
const nameOf = type => skills[type === 21 ? 19 : type];
const periods = {'24h': 'Last 24 hours', '7d': '7 days ago', '30d': '30 days ago'};
const fmt = n => Math.floor(n).toLocaleString('en-GB');
const xp = value => fmt(value / 10);
const display = name => name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/[<>&"]/g, '');
const ago = at => {const m = Math.round((Date.now() - at) / 60000); return m < 60 ? `${m}m ago` : m < 2880 ? `${Math.round(m / 60)}h ago` : `${Math.round(m / 1440)}d ago`;};
let state = {view: 'home', player: '', period: '24h', skill: 'Overall'};
const nav = [['home', 'Home'], ['hiscores', 'Hiscores'], ['records', 'Records'], ['players', 'Players']];
document.body.innerHTML = `<div class="shell"><header class="masthead"><a class="brand" href="/"><img class="mark" src="/assets/stats.png" alt="" width="25" height="24"><span>Lost City <b>Labs</b></span></a></header><div class="layout"><aside class="sidebar"><div class="sidebar-heading">Tracking</div><nav aria-label="Main">${nav.map(([v, label]) => `<a href="${v === 'home' ? '/' : `/${v}`}" data-view="${v}">${label}</a>`).join('')}</nav><a class="external" href="https://2004.lostcity.rs/title" target="_blank" rel="noopener">Lost City ↗</a></aside><main id="main"></main></div><footer><span><a href="https://github.com/LostCityRS/Labs">Source code</a> · Inspired by anders' original Lost City Labs ❤️</span></footer></div>`;
const main = document.querySelector('#main');
const api = async params => {
  const res = await fetch(`/api.php?${new URLSearchParams({period: state.period, type: typeOf(state.skill), ...params})}`);
  if (!res.ok && res.status !== 404) throw new Error((await res.json()).error);
  return res.json();
};
const select = (label, id, options, selected) => `<label class="select-label">${label}<select id="${id}">${options.map(o => `<option ${o === selected ? 'selected' : ''}>${o}</option>`).join('')}</select></label>`;
const skillSelect = () => select('Skill', 'skill-filter', skills, state.skill);
const periodControl = () => `<div class="segments" aria-label="Time period">${Object.keys(periods).map(p => `<button data-period="${p}" aria-pressed="${state.period === p}">${p}</button>`).join('')}</div>`;
const link = name => `<a href="/player/${name}">${display(name)}</a>`;
const empty = text => `<p class="empty">${text}</p>`;
const search = (value = '') => `<form id="search" class="panel" role="search"><div><input aria-label="Player name" placeholder="Player name" autocomplete="off" value="${value}"><button type="submit">Find player</button></div><span id="search-error" role="status"></span></form>`;
function table(headers, rows, caption) {
  if (!rows.length) return empty('Nothing yet.');
  const wordColumn = i => headers[i].startsWith('Player') ? ' class="word-column"' : '';
  return `<div class="table-wrap"><table><caption class="sr-only">${caption}</caption><thead><tr>${headers.map((h, i) => `<th scope="col"${wordColumn(i)}>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(r => `<tr>${r.map((c, i) => i === 0 ? `<th scope="row"${wordColumn(i)}>${c}</th>` : `<td${wordColumn(i)}>${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}
const achievements = list => list.length ? list.map(l => `<div class="achievement">${l.name ? link(l.name) : `<span>${nameOf(l.type)}</span>`}<b>${l.name ? `${nameOf(l.type)} ${l.level}` : `${l.prev_level} → ${l.level}`}</b><small>${ago(l.at)}</small></div>`).join('') : empty('None yet.');
// points: [{at, value}] in display units, e.g. XP or players.
function chart(points, name, unit, from = null, daily = false) {
  if (!points.length) return empty(daily ? `No ${name} data yet.` : `No ${name} data in the last ${state.period}.`);
  const end = Date.now(), start = from ?? end - {'24h': 1, '7d': 7, '30d': 30}[state.period] * 864e5;
  const min = daily ? 0 : Math.min(...points.map(p => p.value)), max = Math.max(...points.map(p => p.value)), range = max - min || 1;
  const date = at => new Date(at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC'});
  const x = at => 62 + (at - start) / (end - start) * 650, y = v => 185 - (v - min) * 148 / range;
  // Enough decimals that neighbouring gridlines get different labels.
  const [scale, suffix] = max >= 1e6 ? [1e6, 'm'] : max >= 1e4 ? [1e3, 'k'] : [1, ''];
  const decimals = Math.min(4, Math.max(0, Math.ceil(Math.log10(scale * 3 / range))));
  const axis = v => `${(v / scale).toLocaleString('en-GB', {minimumFractionDigits: decimals, maximumFractionDigits: decimals})}${suffix}`;
  const labels = [0, 1, 2, 3].map(i => axis(max - i * range / 3));
  const grid = labels.map((text, i) => `<line x1="62" x2="730" y1="${37 + i * 148 / 3}" y2="${37 + i * 148 / 3}"/>${text === labels[i - 1] ? '' : `<text x="50" y="${41 + i * 148 / 3}" text-anchor="end">${text}</text>`}`).join('');
  const marks = points.map(p => {
    const px = x(p.at), py = y(p.value), anchor = px > 600 ? 'end' : px < 160 ? 'start' : 'middle';
    return `<g class="pt"><circle class="hit" cx="${px}" cy="${py}" r="12"/><circle class="chart-point" cx="${px}" cy="${py}" r="3"/><text class="tip" x="${px}" y="${py < 60 ? py + 24 : py - 12}" text-anchor="${anchor}">${fmt(p.value)} ${unit} · ${daily ? `${date(p.at)} UTC` : new Date(p.at).toLocaleString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'})}</text></g>`;
  }).join('');
  return `<svg class="chart${points.length > 60 ? ' dense' : ''}" viewBox="0 0 770 226" role="img" aria-label="${name}"><g class="grid">${grid}</g><polyline class="xp-line" points="${points.map(p => `${x(p.at)},${y(p.value)}`).join(' ')}"/>${marks}<g class="axis"><text x="62" y="215">${daily ? date(start) : periods[state.period]}</text><text x="720" y="215" text-anchor="end">${daily ? 'Today (UTC)' : 'Now'}</text></g></svg>`;
}
async function homeView() {
  const d = await api({q: 'home'});
  return `<h1 class="sr-only">Lost City Labs</h1>${search()}<div class="home-grid"><div><section class="panel"><div class="panel-heading"><h2>Most XP gained</h2>${periodControl()}</div><div class="toolbar">${skillSelect()}</div>${table(['Rank', 'Player', `XP · ${state.period}`], d.gainers.map((g, i) => [i + 1, link(g.name), `<span class="gain">+${xp(g.gain)}</span>`]), 'Most XP gained')}</section><section class="panel"><div class="panel-heading"><h2>Newly tracked players</h2></div>${table(['Player', 'Total level', 'First tracked'], d.newest.map(p => [link(p.name), fmt(p.level), ago(p.first_seen)]), 'Newly tracked players')}</section></div><aside><section class="panel"><h2>Recent 99s</h2>${achievements(d.nineties)}</section></aside></div>`;
}
async function playerView() {
  const d = state.player && await api({q: 'player', name: state.player});
  if (!d) return `<div class="page-heading"><h1>${display(state.player)}</h1></div>${empty('Not tracked yet.')}${search(display(state.player))}`;
  const stat = type => d.stats.find(s => s.type === type);
  const overall = stat(0), type = typeOf(state.skill);
  const rows = skills.map(name => {const s = stat(typeOf(name)); return [`<a href="/player/${state.player}/${name}"${state.skill === name ? ' class="selected-skill"' : ''}>${name}</a>`, s ? fmt(s.level) : '–', s ? xp(s.value) : '–', s ? fmt(s.rank) : '–', s ? `<span class="gain">+${xp(s.gain)}</span>` : '–'];});
  return `<div class="page-heading"><div><div class="eyebrow">Player</div><h1>${display(state.player)}</h1><span class="muted">Updated ${ago(Math.max(...d.stats.map(s => s.at)))}</span></div><div class="rank"><span>Overall rank</span><strong>${fmt(overall.rank)}</strong></div></div><div class="stats"><div><span>Total level</span><strong>${fmt(overall.level)}</strong></div><div><span>Total XP</span><strong>${xp(overall.value)}</strong></div><div><span>Gained · ${state.period}</span><strong class="gain">+${xp(overall.gain)}</strong></div></div><section class="panel graph-panel"><div class="panel-heading"><h2>${state.skill} experience</h2>${periodControl()}</div>${chart(d.history.filter(h => h.type === type).map(h => ({at: h.at, value: h.value / 10})), `${state.skill} XP`, 'XP')}</section><div class="detail-grid"><section class="panel skills-panel"><div class="panel-heading"><h2>Skills</h2><span class="muted">Select a skill to graph</span></div>${table(['Skill', 'Level', 'Experience', 'Rank', `Gained · ${state.period}`], rows, 'Player skills')}</section><aside><section class="panel"><h2>Recent levels</h2>${achievements(d.levels)}</section></aside></div>`;
}
async function hiscoresView() {
  const rows = await api({q: 'hiscores'});
  return `<div class="page-heading"><h1>Hiscores</h1>${skillSelect()}</div><section class="panel">${table(['Rank', 'Player', 'Level', 'Experience'], rows.map(r => [fmt(r.rank), link(r.name), fmt(r.level), xp(r.value)]), `${state.skill} hiscores`)}</section>`;
}
const icon = name => `<span class="icon" style="background-position:-${skills.indexOf(name) * 25}px 0"></span>`;
async function recordsView() {
  const d = await api({q: 'records'});
  const when = r => `${new Date(r.start_at).toLocaleString('en-GB')} – ${new Date(r.end_at).toLocaleString('en-GB')}`;
  const board = (period, label) => {const [best] = d[period]; return `<div><div class="holder"><small>${label} record</small>${best ? `<strong title="${when(best)}">${xp(best.gain)}</strong>${link(best.name)}` : empty('Nobody yet.')}</div>${d[period].length ? table(['#', 'Player', 'XP'], d[period].map((r, i) => [i + 1, link(r.name), `<span class="gain" title="${when(r)}">${xp(r.gain)}</span>`]), `${state.skill} ${label} records`) : ''}</div>`;};
  return `<div class="page-heading"><h1>XP records</h1></div><section class="panel"><nav class="skill-icons" aria-label="Skill">${skills.map(name => `<a href="/records/${name}" title="${name}"${state.skill === name ? ' aria-current="page"' : ''}>${icon(name)}<span class="sr-only">${name}</span></a>`).join('')}</nav><div class="panel-heading"><h2>${icon(state.skill)}${state.skill}</h2></div><div class="boards">${board('6h', '6 hour')}${board('24h', '24 hour')}</div></section>`;
}
async function playersView() {
  const d = await api({q: 'online'});
  const graph = (title, points, note, pending) => `<section class="panel graph-panel"><div class="panel-heading"><h2>${title}</h2></div><p class="muted">${note}</p>${points.length ? chart(points, title, 'players', points[0].at, true) : empty(pending)}</section>`;
  return `<div class="page-heading"><h1>Players</h1></div>${graph('Daily observed players', d.observed, 'Distinct tracked accounts that sent updates each UTC day, finalized at 00:00. Partial days are excluded.', 'No complete days yet.')}${graph('Players online at 00:00 UTC', d.online, 'All worlds, sampled once per day at midnight UTC.', 'Waiting for the first midnight snapshot.')}`;
}
async function render() {
  document.querySelectorAll('[data-view]').forEach(a => a.setAttribute('aria-current', a.dataset.view === state.view ? 'page' : 'false'));
  try {main.innerHTML = await {home: homeView, player: playerView, hiscores: hiscoresView, records: recordsView, players: playersView}[state.view]();}
  catch (error) {main.innerHTML = empty(`Could not load: ${error.message}`); throw error;}
  document.title = `${state.view === 'player' ? display(state.player) : nav.find(([v]) => v === state.view)[1]} · Lost City Labs`;
}
const route = () => {
  // Keep previously shared bookmarks working without leaving hash URLs in the address bar.
  if (/^#(home|hiscores|player|records|online)(\/|$)/.test(location.hash)) {
    const legacy = location.hash.slice(1).replace(/^home$/, '').replace(/^online$/, 'players');
    history.replaceState(null, '', `/${legacy}`);
  }
  const [view, name, skill] = location.pathname.slice(1).split('/');
  state.view = view === 'player' && name ? 'player' : nav.some(([v]) => v === view) ? view : 'home';
  if (state.view === 'player') {state.player = decodeURIComponent(name); state.skill = skills.includes(skill) ? skill : 'Overall';}
  if (state.view === 'records') state.skill = skills.includes(name) ? name : 'Overall';
  render();
};
const navigate = path => {history.pushState(null, '', path); route();};
window.addEventListener('popstate', route);
document.addEventListener('click', e => {
  const a = e.target.closest('a');
  if (a && a.origin === location.origin && !a.target && !a.hasAttribute('download') && e.button === 0 && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
    e.preventDefault();
    navigate(a.pathname + a.search + a.hash);
    return;
  }
  const button = e.target.closest('button');
  if (button?.dataset.period) {state.period = button.dataset.period; render();}
});
document.addEventListener('change', e => {if (e.target.id === 'skill-filter') {state.skill = e.target.value; render();}});
document.addEventListener('submit', async e => {
  e.preventDefault();
  const name = e.target.querySelector('input').value.trim().toLowerCase().replace(/ /g, '_');
  const error = document.querySelector('#search-error');
  error.textContent = '';
  try {
    if (await api({q: 'player', name}) || await api({q: 'track', name}) !== null) {const path = `/player/${name}`; location.pathname === path ? route() : navigate(path);}
    else error.textContent = `${display(name)} is not on the hiscores.`;
  } catch (err) {error.textContent = err.message; throw err;}
});
route();
