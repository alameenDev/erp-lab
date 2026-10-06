import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const out = 'test-results/patient-portal';
await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const errors = [];
async function scenario({ ios = false, installed = false, denied = false, unsupported = false, unavailable = false, mode = '' } = {}) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 },
    ...(ios ? { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1' } : {}) });
  let server = { subscribed: false, results_enabled: true, offers_enabled: false }, items = [];
  const requests = [];
  await context.addInitScript(({ installed, denied, unsupported }) => {
    const state = window.portalMock = { installed, permission: denied ? 'denied' : 'default', permissionCalls: 0, activation: [], registrations: [], installs: 0, unsubscribes: 0 };
    const matchMedia = window.matchMedia.bind(window);
    window.matchMedia = value => value === '(display-mode: standalone)' ? { matches: state.installed } : matchMedia(value);
    const subscription = { endpoint: 'https://fcm.googleapis.com/fcm/send/synthetic-browser', toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'synthetic', auth: 'synthetic' } }; } };
    const registration = { pushManager: {
      getSubscription: async () => localStorage.getItem('mock-browser-subscription') ? subscription : null,
      subscribe: async () => { localStorage.setItem('mock-browser-subscription', '1'); return subscription; },
    } };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: { register: async (path, options) => { state.registrations.push({ path, options }); return registration; }, ready: Promise.resolve(registration) } });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: unsupported ? undefined : function () {} });
    Object.defineProperty(window, 'Notification', { configurable: true, value: {
      get permission() { return state.permission; },
      requestPermission: () => { state.permissionCalls++; state.activation.push(navigator.userActivation.isActive); state.permission = 'granted'; return Promise.resolve('granted'); },
    } });
    window.offerPortalInstall = () => {
      const event = new Event('beforeinstallprompt', { cancelable: true });
      event.prompt = async () => { state.installs++; state.installed = true; window.dispatchEvent(new Event('appinstalled')); return { outcome: 'accepted' }; };
      window.dispatchEvent(event);
    };
  }, { installed, denied, unsupported });
  await context.route('**/api/portal/**', async route => {
    const req = route.request(), url = new URL(req.url()); requests.push(url.pathname);
    const body = req.method() === 'POST' ? req.postDataJSON() : null;
    let data = {};
    if (url.pathname.endsWith('/app-config')) data = { lab_name: 'مختبر إزمير', public_key: unavailable ? null : 'BA'.repeat(44) };
    else if (url.pathname.endsWith('/manifest.webmanifest')) data = { name: 'بوابة المريض', start_url: '/portal/' + 'a'.repeat(48), scope: '/portal/', display: 'standalone', icons: [] };
    else if (url.pathname.endsWith('/push/status')) data = server;
    else if (url.pathname.endsWith('/push/subscribe')) data = server = { subscribed: true, results_enabled: body.results_enabled, offers_enabled: body.offers_enabled };
    else if (url.pathname.endsWith('/push/unsubscribe')) { server.subscribed = false; data = server; }
    else if (url.pathname.endsWith('/push/test')) { data = { queued: true }; items = [{ id: '00000000-0000-4000-8000-000000000001', title: 'إشعارات بوابتك جاهزة', body: 'إشعار تجريبي', created_at: new Date().toISOString(), read_at: null, kind: 'test' }]; }
    else if (url.pathname.endsWith('/notifications/read')) items.forEach(item => item.read_at = new Date().toISOString());
    else if (url.pathname.endsWith('/notifications')) data = { items, unread: items.filter(item => !item.read_at).length };
    else throw new Error('Unexpected endpoint: ' + url.pathname);
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify(data) });
  });
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(`http://127.0.0.1:5173/tests/patient-portal-app-browser.html?mode=${mode}`);
  if (!['otp', 'expired'].includes(mode)) await page.getByRole('button', { name: 'إعدادات البوابة', exact: true }).waitFor();
  return { context, page, requests, server: () => server };
}

try {
  const { page, context, server } = await scenario();
  const dialog = page.getByRole('dialog');
  await dialog.waitFor();
  await page.getByRole('heading', { name: 'بوابتك، أقرب إليك' }).waitFor();
  assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0, 'no automatic native permission prompt');
  assert.equal(await page.getByRole('switch', { name: 'إشعارات العروض والمكافآت' }).isChecked(), false);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: `${out}/welcome-mobile.png`, fullPage: true });
  await page.evaluate(() => offerPortalInstall());
  await page.getByRole('button', { name: 'إضافة البوابة للجهاز', exact: true }).click();
  assert.equal(await page.evaluate(() => portalMock.installs), 1);
  await page.getByRole('button', { name: 'تفعيل الإشعارات', exact: true }).click();
  await page.getByText('تم تفعيل إشعاراتك على هذا الجهاز.', { exact: true }).waitFor();
  assert.deepEqual(await page.evaluate(() => portalMock.activation), [true], 'native permission is triggered by the patient click');
  assert.equal(server().offers_enabled, false);
  await page.getByRole('switch', { name: 'إشعارات العروض والمكافآت' }).check();
  await page.getByRole('button', { name: 'حفظ التفضيلات', exact: true }).click();
  await page.getByText('تم حفظ تفضيلات إشعاراتك.').waitFor();
  assert.equal(server().offers_enabled, true);
  await page.screenshot({ path: `${out}/settings-mobile.png`, fullPage: true });
  await page.getByRole('button', { name: 'إرسال إشعار تجريبي', exact: true }).click();
  await page.getByText(/أُضيف الإشعار التجريبي للإرسال/).waitFor();
  await page.getByRole('button', { name: 'إيقاف على هذا الجهاز', exact: true }).click();
  await page.getByText(/تم إيقاف إشعاراتك على هذا الجهاز/).waitFor();
  assert.equal(server().subscribed, false);
  assert.equal(await page.evaluate(() => localStorage.getItem('mock-browser-subscription')), '1', 'do not revoke another family member subscription');
  await page.getByRole('button', { name: 'إغلاق', exact: true }).click();
  await page.reload();
  await page.getByRole('button', { name: 'إعدادات البوابة', exact: true }).waitFor();
  await page.waitForTimeout(500);
  assert.equal(await page.getByRole('dialog').count(), 0, 'dismissed welcome does not return');
  assert.match(await page.getByRole('link', { name: 'عرض التقرير والنتائج' }).getAttribute('href'), /#portal=a{48}$/);
  await page.getByRole('button', { name: 'الإشعارات', exact: true }).click();
  await page.getByText('إشعارات بوابتك جاهزة', { exact: true }).waitFor();
  await page.screenshot({ path: `${out}/inbox-mobile.png`, fullPage: true });
  await context.close();
  console.log('PASS first-visit onboarding, consent, install, preferences, test, opt-out, inbox and unchanged report links');

  for (const options of [{ ios: true }, { ios: true, installed: true }, { denied: true }, { unsupported: true }, { unavailable: true }]) {
    const { page, context } = await scenario(options);
    if (options.denied) await page.getByRole('button', { name: 'إعدادات البوابة', exact: true }).click();
    await page.getByRole('dialog').waitFor();
    if (options.ios && !options.installed) {
      assert.equal(await page.getByRole('button', { name: 'تفعيل الإشعارات', exact: true }).isDisabled(), true);
      await page.getByRole('button', { name: 'طريقة الإضافة للآيفون' }).click();
      await page.getByText('ثلاث خطوات بسيطة').waitFor();
      await page.screenshot({ path: `${out}/iphone-install.png`, fullPage: true });
    } else if (options.installed) {
      await page.getByRole('button', { name: 'تفعيل الإشعارات', exact: true }).click();
      await page.getByText('تم تفعيل إشعاراتك على هذا الجهاز.', { exact: true }).waitFor();
    } else assert.equal(await page.getByRole('button', { name: 'تفعيل الإشعارات', exact: true }).isDisabled(), true);
    await context.close();
    console.log('PASS device/support state ' + JSON.stringify(options));
  }
  for (const mode of ['otp', 'expired']) {
    const { page, context, requests } = await scenario({ mode });
    await page.locator('#app').getByText(mode === 'otp' ? 'لحماية بياناتك الطبية، يرجى التحقق من رقم هاتفك' : 'الرابط منتهي الصلاحية', { exact: true }).waitFor();
    assert.equal(await page.locator('.portal-app-toolbar').count(), 0);
    assert.equal(requests.filter(path => /app-config|push\/|notifications/.test(path)).length, 0);
    assert.equal(await page.evaluate(() => portalMock.registrations.length), 0);
    assert.match(await page.locator('link[rel="manifest"]').getAttribute('href'), /\/portal\/a{48}\/manifest.webmanifest$/);
    await context.close();
  }
  assert.deepEqual(errors, []);
  console.log('PASS expired/OTP-gated links cannot register notifications or install the staff app');
} finally { await browser.close(); }
