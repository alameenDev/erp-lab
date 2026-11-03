import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';

// Execute the shipped worker with deterministic browser boundaries; no push is sent.
const handlers = {}, values = new Map(), notices = [], opened = [];
const db = {
  close() {}, createObjectStore() {},
  transaction() {
    const tx = { objectStore:() => ({
      get(key) { const request = {}; queueMicrotask(() => { request.result = values.get(key); tx.oncomplete(); }); return request; },
      put(value, key) { const request = {}; queueMicrotask(() => { values.set(key,value); tx.oncomplete(); }); return request; },
    }) };
    return tx;
  },
};
const self = {
  location:{ origin:'https://lab.example' },
  addEventListener(type, fn) { handlers[type] = fn; },
  clients:{ claim:async () => {}, matchAll:async () => [], openWindow:async url => opened.push(url) },
  skipWaiting:async () => {},
  registration:{
    showNotification:async (title, options) => notices.push({ title, ...options, close() { this.closed = true; } }),
    getNotifications:async () => notices.filter(n => !n.closed),
  },
};
const context = vm.createContext({ self, URL, indexedDB:{ open() { const request = {}; queueMicrotask(() => { request.result = db; request.onsuccess(); }); return request; } },
  caches:{ open:async () => ({ add:async () => {} }), match:async () => 'offline-only' }, fetch:async () => { throw new Error('offline'); } });
vm.runInContext(await readFile(new URL('../public/collector-portal-sw.js', import.meta.url), 'utf8'), context);
async function dispatch(type, fields) { const waits = []; handlers[type]({ ...fields, waitUntil:p => waits.push(p) }); await Promise.all(waits); }
async function bind(collectorId, origin = 'https://lab.example') {
  let acknowledged;
  await dispatch('message', { data:{ type:'COLLECTOR_BIND', collectorId }, source:{ url:origin+'/collector-portal/' }, ports:[{ postMessage:value => { acknowledged = value; } }] });
  return acknowledged;
}
const payload = { id:'11111111-1111-4111-8111-111111111111', collector_id:90, title:'Invoice changed', body:'Before 10000, after 12000', url:'/collector-portal/notifications?notice=11111111-1111-4111-8111-111111111111' };
const push = data => dispatch('push', { data:{ json:() => data } });
await push(payload); assert.equal(notices.length,0,'no consent means no visible notification');
assert.equal((await bind(90)).ok,true);
await push({...payload,collector_id:91}); assert.equal(notices.length,0,'wrong collector is discarded');
await push({...payload,url:'https://evil.example/collector-portal/notifications?notice='+payload.id}); assert.equal(notices.length,0,'external links are discarded');
await push(payload); assert.equal(notices.length,1); assert.equal(notices[0].data.collectorId,'90');
await dispatch('notificationclick', { notification:notices[0] }); assert.equal(opened.length,1); assert.equal(new URL(opened[0]).pathname,'/collector-portal/notifications');
await push(payload); await bind(null); assert.ok(notices.every(n => n.closed),'logout closes already shown notifications');
await push(payload); assert.equal(notices.length,2,'logout discards late messages');
await bind(91); await dispatch('notificationclick', { notification:notices[1] }); assert.equal(opened.length,1,'account switch blocks stale clicks');
await bind(90,'https://evil.example'); assert.equal(values.get('collector'),'91','foreign origin cannot rebind');
let response;
handlers.fetch({ request:{ url:'https://lab.example/api/collector-portal/overview', mode:'cors' }, respondWith:p => { response = p; } });
assert.equal(response,undefined,'API data never passes through cache');
handlers.fetch({ request:{ url:'https://lab.example/portal/patient', mode:'navigate' }, respondWith:p => { response = p; } });
assert.equal(response,undefined,'patient portal is unaffected');
handlers.fetch({ request:{ url:'https://lab.example/collector-portal/', mode:'navigate' }, respondWith:p => { response = p; } });
assert.equal(await response,'offline-only');
console.log('PASS collector worker binding, logout, account switch, push links, click isolation and offline scope');
