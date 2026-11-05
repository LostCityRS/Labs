// Fragments never reach PHP; old shared bookmarks need a one-time native redirect.
const redirectLegacyBookmark = () => {
  const fragment = location.hash.slice(1);
  if (/^(home|hiscores|player|records|online)(\/|$)/.test(fragment)) location.replace('/' + fragment.replace(/^home$/, '').replace(/^online$/, 'players'));
};
redirectLegacyBookmark();
window.addEventListener('hashchange', redirectLegacyBookmark);

const showPieTooltip = e => {
  const slice = e.target.closest('.pie-slice');
  if (!slice) return;
  const tip = slice.closest('.pie-wrap').querySelector('.pie-tooltip');
  tip.textContent = slice.dataset.label;
  tip.hidden = false;
};
const hidePieTooltip = e => {
  const slice = e.target.closest('.pie-slice');
  if (slice && !slice.contains(e.relatedTarget)) slice.closest('.pie-wrap').querySelector('.pie-tooltip').hidden = true;
};
document.addEventListener('pointerover', showPieTooltip);
document.addEventListener('focusin', showPieTooltip);
document.addEventListener('pointerout', hidePieTooltip);
document.addEventListener('focusout', hidePieTooltip);
