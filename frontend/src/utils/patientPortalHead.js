// The production response already contains these tags. This also handles
// client-side navigation into a portal without a document reload.
export function mountPatientPortalHead(token, apiBase = '/api', doc = document) {
  const restores = [];
  function claim(selector, tag, attributes, serverFallback = null) {
    let element = doc.querySelector(selector);
    let previous = element?.cloneNode(true) || null;
    if (element?.dataset.portalAppHead === 'true') previous = serverFallback;
    if (!element) { element = doc.createElement(tag); doc.head.appendChild(element); }
    for (const [key, value] of Object.entries(attributes)) element.setAttribute(key, value);
    restores.push(() => { if (previous) element.replaceWith(previous); else element.remove(); });
    return element;
  }
  const staffManifest = doc.createElement('link');
  staffManifest.rel = 'manifest'; staffManifest.href = '/manifest.json';
  const manifest = claim('link[rel="manifest"]', 'link', { rel: 'manifest' }, staffManifest);
  for (const [name, content] of Object.entries({
    'apple-mobile-web-app-capable': 'yes', 'mobile-web-app-capable': 'yes', 'apple-mobile-web-app-title': 'بوابة المريض',
  })) claim(`meta[name="${name}"]`, 'meta', { name, content });
  const update = value => manifest.setAttribute('href', `${apiBase.replace(/\/$/, '')}/portal/${encodeURIComponent(value)}/manifest.webmanifest`);
  update(token);
  return { update, restore: () => restores.reverse().forEach(restore => restore()) };
}
