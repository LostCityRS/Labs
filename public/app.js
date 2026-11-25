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
    if (response.status === 204 || (response.status === 200 && response.headers.get('X-Labs-Visit') === 'crawler')) return;
    throw new Error('Unexpected visitor ping response: HTTP ' + response.status);
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
