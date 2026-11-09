// Fragments never reach PHP; old shared bookmarks need a one-time native redirect.
const redirectLegacyBookmark = () => {
  const fragment = location.hash.slice(1);
  if (/^(home|hiscores|player|records|online)(\/|$)/.test(fragment)) location.replace('/' + fragment.replace(/^home$/, '').replace(/^online$/, 'players'));
};
redirectLegacyBookmark();
window.addEventListener('hashchange', redirectLegacyBookmark);

const showEhpTooltip = e => {
  const slot = e.target.closest('[data-ehp]');
  if (!slot) return;
  const tip = slot.closest('.ehp-wrap').querySelector('.ehp-tooltip');
  tip.textContent = slot.dataset.label;
  tip.hidden = false;
};
const hideEhpTooltip = e => {
  const slot = e.target.closest('[data-ehp]');
  if (slot && !slot.contains(e.relatedTarget)) slot.closest('.ehp-wrap').querySelector('.ehp-tooltip').hidden = true;
};
document.addEventListener('pointerover', showEhpTooltip);
document.addEventListener('focusin', showEhpTooltip);
document.addEventListener('pointerout', hideEhpTooltip);
document.addEventListener('focusout', hideEhpTooltip);

for (const wrap of document.querySelectorAll('.chart-wrap')) {
  const svg = wrap.querySelector('.chart');
  const tip = wrap.querySelector('.chart-tooltip');
  const points = [...svg.querySelectorAll('.pt')];
  const height = svg.viewBox.baseVal.height;
  // Reproject only x coordinates so labels, dots and hit areas stay in screen pixels.
  const coordinates = [...svg.querySelectorAll('[x],[x1],[x2],[cx]')].flatMap(el =>
    ['x', 'x1', 'x2', 'cx'].filter(attr => el.hasAttribute(attr)).map(attr => [el, attr, Number(el.getAttribute(attr))]));
  const lines = [...svg.querySelectorAll('polyline')].map(el => [el, [...el.points].map(p => [p.x, p.y])]);
  let active = null;
  const show = point => {
    active?.classList.remove('active');
    active = point;
    tip.hidden = !point;
    if (!point) return;
    point.classList.add('active');
    tip.textContent = point.getAttribute('aria-label');
    const dot = point.querySelector('.chart-point');
    const x = dot.cx.baseVal.value, y = dot.cy.baseVal.value;
    tip.style.left = `${Math.max(0, Math.min(wrap.clientWidth - tip.offsetWidth, x - tip.offsetWidth / 2))}px`;
    tip.style.top = `${y >= tip.offsetHeight + 12 ? y - tip.offsetHeight - 12 : y + 12}px`;
  };
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
    show(active);
  }).observe(wrap);
  const nearest = e => {
    const rect = svg.getBoundingClientRect();
    const x = e.clientX - rect.left, y = e.clientY - rect.top;
    if (x < 50 || x > width - 20 || y < 28 || y > height - 35) return show(null);
    let closest = null, distance = Infinity;
    for (const point of points) {
      if (!point.getClientRects().length) continue;
      const dot = point.querySelector('.chart-point');
      const d = Math.hypot(dot.cx.baseVal.value - x, dot.cy.baseVal.value - y);
      if (d < distance) {closest = point; distance = d;}
    }
    show(closest);
  };
  svg.addEventListener('pointermove', e => {if (e.pointerType !== 'touch') nearest(e);});
  svg.addEventListener('pointerdown', e => {
    // Touch focus changes would dismiss the tooltip selected by the same tap.
    if (e.pointerType === 'touch') e.preventDefault();
    nearest(e);
  });
  svg.addEventListener('pointerleave', e => {
    if (e.pointerType !== 'touch') show(svg.contains(document.activeElement) ? document.activeElement.closest('.pt') : null);
  });
  svg.addEventListener('focusin', e => show(e.target.closest('.pt')));
  svg.addEventListener('focusout', () => show(null));
  svg.addEventListener('keydown', e => {if (e.key === 'Escape') show(null);});
  document.addEventListener('pointerdown', e => {if (!wrap.contains(e.target)) show(null);});
  wrap.closest('.panel').addEventListener('change', () => show(null));
}
