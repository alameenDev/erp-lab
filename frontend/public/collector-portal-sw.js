/* Collector notifications only. Never cache API responses, patient data or login credentials. */
const OFFLINE_CACHE = 'collector-offline-v1';
const OFFLINE = '/collector-app/offline.html';
function binding(value, write = false) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('collector-push-binding', 1);
    request.onupgradeneeded = () => request.result.createObjectStore('settings');
    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction('settings', write ? 'readwrite' : 'readonly');
      const store = tx.objectStore('settings');
      const result = write ? store.put(value, 'collector') : store.get('collector');
      tx.oncomplete = () => { db.close(); resolve(write ? value : result.result); };
      tx.onerror = () => { db.close(); reject(tx.error); };
    };
  });
}
self.addEventListener('install', event => event.waitUntil(caches.open(OFFLINE_CACHE).then(cache => cache.add(OFFLINE)).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (event.request.mode === 'navigate' && url.origin === self.location.origin && url.pathname.startsWith('/collector-portal/')) {
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
  }
});
self.addEventListener('message', event => {
  if (event.data?.type !== 'COLLECTOR_BIND' || !event.source?.url || new URL(event.source.url).origin !== self.location.origin) return;
  event.waitUntil((async () => {
    try {
      const id = event.data.collectorId;
      if (id !== null && !/^\d+$/.test(String(id))) throw new Error('Invalid collector');
      const previous = await binding();
      await binding(id === null ? null : String(id), true);
      if (String(previous) !== String(id) || id === null) for (const notice of await self.registration.getNotifications()) notice.close();
      event.ports[0]?.postMessage({ ok:true });
    } catch { event.ports[0]?.postMessage({ ok:false }); }
  })());
});
function destination(value) {
  const url = new URL(value, self.location.origin);
  if (url.origin !== self.location.origin || url.pathname !== '/collector-portal/notifications' || !/^[a-f0-9-]{36}$/i.test(url.searchParams.get('notice') || '')) throw new Error('Invalid notification link');
  return url.href;
}
self.addEventListener('push', event => event.waitUntil((async () => {
  try {
    const payload = event.data.json();
    if (!payload.collector_id || String(await binding()) !== String(payload.collector_id)) return;
    const url = destination(payload.url);
    await self.registration.showNotification(String(payload.title || 'ساحب العينات').slice(0,180), {
      body:String(payload.body || '').slice(0,500), icon:'/logo-192x192.png', badge:'/logo-192x192.png',
      lang:'ar', dir:'rtl', tag:'collector-'+payload.id, data:{ url, collectorId:String(payload.collector_id) },
    });
  } catch { /* Invalid or obsolete payloads must not reveal another collector's data. */ }
})()));
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil((async () => {
    if (String(await binding()) !== event.notification.data?.collectorId) return;
    const url = destination(event.notification.data.url);
    const tabs = await self.clients.matchAll({ type:'window', includeUncontrolled:true });
    const tab = tabs.find(client => client.url === url);
    if (tab) return tab.focus();
    return self.clients.openWindow(url);
  })());
});
