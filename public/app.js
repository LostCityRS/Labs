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
