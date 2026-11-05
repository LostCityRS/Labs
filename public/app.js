const skills = ['Overall', 'Attack', 'Defence', 'Strength', 'Hitpoints', 'Ranged', 'Prayer', 'Magic', 'Cooking', 'Woodcutting', 'Fletching', 'Fishing', 'Firemaking', 'Crafting', 'Smithing', 'Mining', 'Herblore', 'Agility', 'Thieving', 'Runecrafting'];
const typeOf = name => {const i = skills.indexOf(name); return i === 19 ? 21 : i;};
const nameOf = type => skills[type === 21 ? 19 : type];
const periods = {'24h': 'Last 24 hours', '7d': '7 days ago', '30d': '30 days ago'};
const fmt = n => Math.floor(n).toLocaleString('en-GB');
const xp = value => fmt(value / 10);
const short = n => n >= 1e6 ? `${(n / 1e6).toFixed(1)}m` : `${Math.round(n / 1000)}k`;
const display = name => name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/[<>&"]/g, '');
const ago = at => {const m = Math.round((Date.now() - at) / 60000); return m < 60 ? `${m}m ago` : m < 2880 ? `${Math.round(m / 60)}h ago` : `${Math.round(m / 1440)}d ago`;};
const levelXP = level => {let points = 0; for (let i = 1; i < level; i++) points += Math.floor(i + 300 * 2 ** (i / 7)); return Math.floor(points / 4);};
let state = {view: 'home', player: '', period: '24h', skill: 'Overall'};
const nav = [['home', 'Home'], ['player', 'Player'], ['hiscores', 'Hiscores'], ['records', 'Records']];
document.body.innerHTML = `<div class="shell"><header class="masthead"><a class="brand" href="#home"><img class="mark" src="assets/stats.png" alt="" width="25" height="24"><span>Lost City <b>Labs</b></span></a></header><div class="layout"><aside class="sidebar"><div class="sidebar-heading">Hiscores & tracking</div><nav aria-label="Main">${nav.map(([v, label]) => `<a href="#${v}" data-view="${v}">${label}</a>`).join('')}</nav><a class="external" href="https://2004.lostcity.rs/title" target="_blank" rel="noopener">Lost City ↗</a></aside><main id="main"></main></div><footer id="updated"></footer></div>`;
const main = document.querySelector('#main');
const api = async params => {
  const res = await fetch(`api.php?${new URLSearchParams({period: state.period, type: typeOf(state.skill), ...params})}`);
  if (!res.ok && res.status !== 404) throw new Error((await res.json()).error);
  const {updated_at, data} = await res.json();
  document.querySelector('#updated').textContent = updated_at ? `Hiscores last changed ${ago(updated_at)}` : 'No data imported yet';
  return data;
};
const select = (label, id, options, selected) => `<label class="select-label">${label}<select id="${id}">${options.map(o => `<option ${o === selected ? 'selected' : ''}>${o}</option>`).join('')}</select></label>`;
const skillSelect = () => select('Skill', 'skill-filter', skills, state.skill);
const periodControl = () => `<div class="segments" aria-label="Time period">${Object.keys(periods).map(p => `<button data-period="${p}" aria-pressed="${state.period === p}">${p}</button>`).join('')}</div>`;
const link = name => `<button class="text-button" data-player="${name}">${display(name)}</button>`;
const empty = text => `<p class="empty">${text}</p>`;
function table(headers, rows, caption) {
  if (!rows.length) return empty('Nothing yet.');
  const wordColumn = i => headers[i] === 'Player' ? ' class="word-column"' : '';
  return `<div class="table-wrap"><table><caption class="sr-only">${caption}</caption><thead><tr>${headers.map((h, i) => `<th scope="col"${wordColumn(i)}>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(r => `<tr>${r.map((c, i) => i === 0 ? `<th scope="row"${wordColumn(i)}>${c}</th>` : `<td${wordColumn(i)}>${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}
const achievements = list => list.length ? list.map(l => `<div class="achievement">${l.name ? link(l.name) : `<span>${nameOf(l.type)}</span>`}<b>${l.name ? `${nameOf(l.type)} ${l.level}` : `${l.prev_level} → ${l.level}`}</b><small>${ago(l.at)}</small></div>`).join('') : empty('None yet.');
function chart(points) {
  const label = `${state.skill} XP, ${state.period}`;
  if (!points.length) return empty(`No ${state.skill} XP changes in the last ${state.period}.`);
  const end = Date.now(), start = end - {'24h': 1, '7d': 7, '30d': 30}[state.period] * 864e5;
  const min = Math.min(...points.map(p => p.value)), max = Math.max(...points.map(p => p.value)), range = max - min || 1;
  const x = at => 62 + (at - start) / (end - start) * 650, y = v => 185 - (v - min) * 148 / range;
  const grid = [0, 1, 2, 3].map(i => `<line x1="62" x2="730" y1="${37 + i * 148 / 3}" y2="${37 + i * 148 / 3}"/><text x="50" y="${41 + i * 148 / 3}" text-anchor="end">${short((max - i * range / 3) / 10)}</text>`).join('');
  const marks = points.map(p => `<circle class="chart-point" cx="${x(p.at)}" cy="${y(p.value)}" r="3"><title>${new Date(p.at).toLocaleString('en-GB')}: ${xp(p.value)} XP</title></circle>`).join('');
  return `<svg class="chart" viewBox="0 0 770 226" role="img" aria-label="${label}"><g class="grid">${grid}</g><polyline class="xp-line" points="${points.map(p => `${x(p.at)},${y(p.value)}`).join(' ')}"/>${marks}<g class="axis"><text x="62" y="215">${periods[state.period]}</text><text x="720" y="215" text-anchor="end">Now</text></g></svg>`;
}
async function homeView() {
  const d = await api({q: 'home'});
  return `<h1 class="sr-only">Lost City Labs</h1><form id="home-search" class="home-search" role="search"><label for="home-player">Find a player</label><div><input id="home-player" placeholder="Player name" autocomplete="off"><button type="submit">Find</button></div><span id="home-search-error" role="status"></span></form><div class="home-grid"><div><section class="panel"><div class="panel-heading"><h2>Most XP gained</h2>${periodControl()}</div><div class="toolbar">${skillSelect()}</div>${table(['Rank', 'Player', `XP · ${state.period}`], d.gainers.map((g, i) => [i + 1, link(g.name), `<span class="gain">+${xp(g.gain)}</span>`]), 'Most XP gained')}</section><section class="panel"><div class="panel-heading"><h2>Newly tracked players</h2></div>${table(['Player', 'Total level', 'First tracked'], d.newest.map(p => [link(p.name), fmt(p.level), ago(p.first_seen)]), 'Newly tracked players')}</section></div><aside><section class="panel"><h2>Recent 99s</h2>${achievements(d.nineties)}</section><section class="panel"><h2>Recent levels</h2>${achievements(d.levels)}</section></aside></div>`;
}
async function playerView() {
  const d = state.player && await api({q: 'player', name: state.player});
  if (!d) return `<div class="page-heading"><h1>Player</h1></div>${empty(state.player ? `${display(state.player)} is not on the hiscores yet.` : 'Search for a player from Home.')}`;
  const stat = type => d.stats.find(s => s.type === type);
  const overall = stat(0), type = typeOf(state.skill);
  const rows = skills.map(name => {const s = stat(typeOf(name)); return [`<button class="text-button ${state.skill === name ? 'selected-skill' : ''}" data-skill="${name}">${name}</button>`, s ? fmt(s.level) : '–', s ? xp(s.value) : '–', s ? fmt(s.rank) : '–', s ? `<span class="gain">+${xp(s.gain)}</span>` : '–'];});
  const next = d.stats.filter(s => s.type && s.level < 99).slice(0, 3).map(s => {const now = levelXP(s.level), goal = levelXP(s.level + 1), have = s.value / 10; return `<div><span>${nameOf(s.type)} <b>${s.level + 1}</b></span><progress value="${have - now}" max="${goal - now}"></progress><small>${fmt(goal - have)} XP to go</small></div>`;}).join('');
  return `<div class="page-heading"><div><div class="eyebrow">Player</div><h1>${display(state.player)}</h1><span class="muted">Updated ${ago(Math.max(...d.stats.map(s => s.at)))}</span></div><div class="rank"><span>Overall rank</span><strong>${fmt(overall.rank)}</strong></div></div><div class="stats"><div><span>Total level</span><strong>${fmt(overall.level)}</strong></div><div><span>Total XP</span><strong>${xp(overall.value)}</strong></div><div><span>Gained · ${state.period}</span><strong class="gain">+${xp(overall.gain)}</strong></div></div><section class="panel graph-panel"><div class="panel-heading"><h2>${state.skill} experience</h2>${periodControl()}</div>${chart(d.history.filter(h => h.type === type))}</section><div class="detail-grid"><section class="panel skills-panel"><div class="panel-heading"><h2>Skills</h2><span class="muted">Select a skill to graph</span></div>${table(['Skill', 'Level', 'Experience', 'Rank', `Gained · ${state.period}`], rows, 'Player skills')}</section><aside class="side-panels"><section class="panel"><h2>Recent levels</h2>${achievements(d.levels)}</section><section class="panel next-level"><h2>Next level</h2>${next || empty('All 99.')}</section></aside></div>`;
}
async function hiscoresView() {
  const rows = await api({q: 'hiscores'});
  return `<div class="page-heading"><div><div class="eyebrow">2004Scape</div><h1>Hiscores</h1></div>${skillSelect()}</div><section class="panel">${table(['Rank', 'Player', 'Level', 'Experience'], rows.map(r => [fmt(r.rank), link(r.name), fmt(r.level), xp(r.value)]), `${state.skill} hiscores`)}</section>`;
}
async function recordsView() {
  const rows = await api({q: 'records'});
  return `<div class="page-heading"><div><div class="eyebrow">Best rolling windows</div><h1>XP records</h1></div>${periodControl()}</div><div class="toolbar">${skillSelect()}</div><section class="panel">${table(['Rank', 'Player', `XP · ${state.period}`, 'Between'], rows.map((r, i) => [i + 1, link(r.name), `<span class="gain">${xp(r.gain)}</span>`, `${new Date(r.start_at).toLocaleString('en-GB')} – ${new Date(r.end_at).toLocaleString('en-GB')}`]), 'XP records')}</section><p class="footnote">Most XP gained between two logouts no more than ${state.period} apart.</p>`;
}
async function render() {
  document.querySelectorAll('[data-view]').forEach(a => a.setAttribute('aria-current', a.dataset.view === state.view ? 'page' : 'false'));
  try {main.innerHTML = await {home: homeView, player: playerView, hiscores: hiscoresView, records: recordsView}[state.view]();}
  catch (error) {main.innerHTML = empty(`Could not load: ${error.message}`); throw error;}
  document.title = `${state.view === 'player' && state.player ? display(state.player) : nav.find(([v]) => v === state.view)[1]} · Lost City Labs`;
}
const route = () => {const [view, name] = location.hash.slice(1).split('/'); if (nav.some(([v]) => v === view)) state.view = view; if (name) state.player = decodeURIComponent(name); render();};
window.addEventListener('hashchange', route);
document.addEventListener('click', e => {
  const button = e.target.closest('button');
  if (button?.dataset.period) {state.period = button.dataset.period; render();}
  if (button?.dataset.skill) {state.skill = button.dataset.skill; render();}
  if (button?.dataset.player) location.hash = `player/${button.dataset.player}`;
});
document.addEventListener('change', e => {if (e.target.id === 'skill-filter') {state.skill = e.target.value; render();}});
document.addEventListener('submit', async e => {
  e.preventDefault();
  const name = e.target.querySelector('input').value.trim().toLowerCase().replace(/ /g, '_');
  const error = document.querySelector('#home-search-error');
  try {
    if (await api({q: 'player', name}) || await api({q: 'track', name}) !== null) location.hash = `player/${name}`;
    else error.textContent = `${display(name)} is not on the hiscores.`;
  } catch (err) {error.textContent = err.message; throw err;}
});
route();
