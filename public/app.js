// Fragments never reach PHP; old shared bookmarks need a one-time native redirect.
const redirectLegacyBookmark = () => {
  const fragment = location.hash.slice(1);
  if (/^(home|hiscores|player|records|online)(\/|$)/.test(fragment)) location.replace('/' + fragment.replace(/^home$/, '').replace(/^online$/, 'players'));
};
redirectLegacyBookmark();
window.addEventListener('hashchange', redirectLegacyBookmark);

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
