import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFile } from 'node:fs/promises';
import { portalDevice, portalPreferenceKey, vapidBytes } from '../src/utils/patientPortalApp.js';

test('iPhone and desktop-mode iPad require the installed app before enabling push', () => {
  const env = { navigator: { userAgent: 'iPhone', serviceWorker: {}, maxTouchPoints: 5 }, isSecureContext: true, PushManager: {}, Notification: {}, matchMedia: () => ({ matches: false }) };
  assert.deepEqual(portalDevice(env), { ios: true, installed: false, push: true, embedded: false });
  env.navigator.standalone = true;
  assert.equal(portalDevice(env).installed, true);
  env.navigator.userAgent = 'Macintosh'; env.navigator.platform = 'MacIntel';
  assert.equal(portalDevice(env).ios, true);
  delete env.PushManager; assert.equal(portalDevice(env).push, false);
});

test('welcome preferences are isolated without storing bearer tokens in keys', async () => {
  const key = await portalPreferenceKey('a'.repeat(48));
  assert.notEqual(key, await portalPreferenceKey('b'.repeat(48)));
  assert.equal(key, await portalPreferenceKey('a'.repeat(48)));
  assert.ok(!key.includes('a'.repeat(48)));
  assert.deepEqual(vapidBytes('AAEC_w'), new Uint8Array([0, 1, 2, 255]));
});

test('portal service worker does not cache records and opens only the matching patient portal', async () => {
  const handlers = {}, shown = [], opened = [], focused = [];
  const token = 'a'.repeat(48), origin = 'https://lab.example.test';
  const url = `${origin}/portal/${token}`;
  const self = { location: { origin }, addEventListener: (name, fn) => handlers[name] = fn,
    registration: { showNotification: async (...args) => shown.push(args) }, skipWaiting() {},
    clients: { claim: async () => {}, matchAll: async () => [{ url: origin + '/medical_reports', focus: () => focused.push('staff') }], openWindow: async value => opened.push(value) } };
  vm.runInNewContext(await readFile(new URL('../public/portal/sw.js', import.meta.url), 'utf8'), { self, URL });
  assert.equal(handlers.fetch, undefined, 'no medical data caching or unrelated navigation interception');
  let pending;
  handlers.push({ data: { json: () => ({ title: 'Ready', body: 'Open your portal', url }) }, waitUntil: p => pending = p });
  await pending; assert.equal(shown.length, 1); assert.equal(shown[0][1].data.url, url);
  handlers.notificationclick({ notification: { data: { url }, close() {} }, waitUntil: p => pending = p });
  await pending; assert.deepEqual(opened, [url]); assert.deepEqual(focused, []);
  for (const bad of ['https://evil.example/portal/' + token, origin + '/medical_reports', 'javascript:alert(1)']) {
    handlers.push({ data: { json: () => ({ url: bad }) }, waitUntil: () => assert.fail('invalid URL accepted') });
    handlers.notificationclick({ notification: { data: { url: bad }, close() {} }, waitUntil: () => assert.fail('invalid click accepted') });
  }
  assert.equal(shown.length, 1);
});
