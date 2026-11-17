// Fragments never reach PHP; old shared bookmarks need a one-time native redirect.
const redirectLegacyBookmark = () => {
  const fragment = location.hash.slice(1);
  if (/^(home|hiscores|player|records|online)(\/|$)/.test(fragment)) location.replace('/' + fragment.replace(/^home$/, '').replace(/^online$/, 'players'));
};
redirectLegacyBookmark();
window.addEventListener('hashchange', redirectLegacyBookmark);

// Background tabs and link previews should not count as visits.
const trackVisit = async () => {
  if (document.visibilityState !== 'visible' || !document.body.hasAttribute('data-track-visits')) return;
  try {
    let visitor = localStorage.getItem('labs_visitor');
    if (visitor === null) {
      visitor = crypto.randomUUID();
      localStorage.setItem('labs_visitor', visitor);
    }
    const response = await fetch('/hit', {method: 'POST', credentials: 'omit', body: new URLSearchParams({visitor})});
    if (response.status !== 204) throw new Error('Visitor ping failed: HTTP ' + response.status);
  } catch (error) {console.error('Labs visitor tracking:', error);}
};
document.addEventListener('visibilitychange', trackVisit);
window.addEventListener('pageshow', trackVisit);

// History restores edited controls after pageshow, even when the server rendered different results.
window.addEventListener('pageshow', () => setTimeout(() => {
  for (const form of document.querySelectorAll('form[method=get]')) form.reset();
}, 0));

document.addEventListener('change', ({target}) => {
  if (target.matches('select') && target.form) target.form.requestSubmit();
});

for (const wrap of document.querySelectorAll('.chart-wrap')) {
  const svg = wrap.querySelector('.chart');
  const height = svg.viewBox.baseVal.height;
  // Reproject only x coordinates so labels, dots and hit areas stay in screen pixels.
  const coordinates = [...svg.querySelectorAll('[x],[x1],[x2],[cx]')].flatMap(el =>
    ['x', 'x1', 'x2', 'cx'].filter(attr => el.hasAttribute(attr)).map(attr => [el, attr, Number(el.getAttribute(attr))]));
  const lines = [...svg.querySelectorAll('polyline')].map(el => [el, [...el.points].map(p => [p.x, p.y])]);
  let width = 0;
  new ResizeObserver(([entry]) => {
    if (width === entry.contentRect.width) return;
    width = entry.contentRect.width;
    const x = value => 62 + (value - 62) / 668 * (width - 82);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    for (const [el, attr, value] of coordinates) el.setAttribute(attr, x(value));
    for (const [el, values] of lines) el.setAttribute('points', values.map(([px, py]) => `${x(px)},${py}`).join(' '));
    const marker = svg.querySelector('.marker text');
    if (marker) marker.setAttribute('x', Math.min(width - marker.getBBox().width / 2, Number(marker.getAttribute('x'))));
  }).observe(wrap);
}
