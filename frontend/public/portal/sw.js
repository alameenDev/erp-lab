// Restricted to /portal/. No fetch handler and no caching of medical records.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));

function portalUrl(value) {
  try {
    const url = new URL(value, self.location.origin);
    return url.origin === self.location.origin && /^\/portal\/[A-Za-z0-9]{48}\/?$/.test(url.pathname)
      && !url.username && !url.password ? url.href : null;
  } catch { return null; }
}

self.addEventListener('push', event => {
  let data;
  try { data = event.data?.json(); } catch { return; }
  const url = portalUrl(data?.url);
  if (!url) return;
  event.waitUntil(self.registration.showNotification(String(data.title || 'بوابة المريض').slice(0, 120), {
    body: String(data.body || 'يوجد تحديث جديد في بوابتك.').slice(0, 500),
    icon: '/logo-192x192.png', badge: '/logo-192x192.png', tag: String(data.tag || 'portal-update').slice(0, 100),
    dir: 'rtl', lang: 'ar', data: { url },
  }));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = portalUrl(event.notification.data?.url);
  if (!url) return;
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const current = windows.find(client => client.url === url);
    if (current) return current.focus();
    return self.clients.openWindow(url);
  })());
});
