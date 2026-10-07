import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';

const out = 'test-results/patient-portal';
await mkdir(out, { recursive: true });
const browser = await chromium.launch();
const errors = [];
async function scenario({ ios = false, installed = false, denied = false, unsupported = false, unavailable = false,
  granted = false, bound = false, resultPreference = true, offerPreference = false, failSaveOnce = false,
  slowWorker = false, workerFailure = false, rejectPermission = false, mode = '' } = {}) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 },
    ...(ios ? { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1' } : {}) });
  let server = { subscribed: bound, results_enabled: resultPreference, offers_enabled: offerPreference }, items = [], saveFailures = failSaveOnce ? 1 : 0;
  const requests = [];
  await context.addInitScript(({ ios, installed, denied, unsupported, granted, slowWorker, workerFailure, rejectPermission }) => {
    const state = window.portalMock = { installed, permission: denied ? 'denied' : localStorage.getItem('mock-permission') || (granted ? 'granted' : 'default'),
      permissionCalls: 0, subscribeCalls: 0, activation: [], registrations: [], installs: 0, workerFailure };
    if (granted) localStorage.setItem('mock-browser-subscription', '1');
    const matchMedia = window.matchMedia.bind(window);
    window.matchMedia = value => value === '(display-mode: standalone)' ? { matches: state.installed } : matchMedia(value);
    const subscription = { endpoint: ios ? 'https://web.push.apple.com/QSynthetic' : 'https://fcm.googleapis.com/fcm/send/synthetic-browser',
      toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'synthetic', auth: 'synthetic' } }; } };
    const worker = Object.assign(new EventTarget(), { state: slowWorker ? 'installing' : 'activated' });
    const registration = Object.assign(new EventTarget(), {
      active: slowWorker ? null : worker, installing: slowWorker ? worker : null,
      pushManager: {
        getSubscription: async () => localStorage.getItem('mock-browser-subscription') ? subscription : null,
        subscribe: options => {
          state.subscribeCalls++;
          state.activation.push(navigator.userActivation.isActive);
          if (!options.userVisibleOnly || !options.applicationServerKey?.length) throw new Error('Missing subscription options');
          if (state.permission !== 'granted') state.permissionCalls++;
          if (rejectPermission) {
            state.permission = 'denied';
            return Promise.reject(new DOMException('Permission denied', 'NotAllowedError'));
          }
          state.permission = 'granted'; localStorage.setItem('mock-permission', 'granted');
          localStorage.setItem('mock-browser-subscription', '1');
          return Promise.resolve(subscription);
        },
      },
    });
    window.releasePortalWorker = () => { worker.state = 'activated'; registration.active = worker; registration.installing = null; worker.dispatchEvent(new Event('statechange')); };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: {
      register: async (path, options) => { state.registrations.push({ path, options }); if (state.workerFailure) throw new Error('تعذر تجهيز خدمة الإشعارات'); return registration; },
      // No use of ready: the fixture URL isn't inside /portal/, as can happen
      // while mounting/routing. The actual registration must be awaited.
    } });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: unsupported || (ios && !installed) ? undefined : function () {} });
    Object.defineProperty(window, 'Notification', { configurable: true, value: {
      get permission() { return state.permission; },
      requestPermission: () => { throw new Error('subscribe must trigger the single native prompt directly'); },
    } });
    window.offerPortalInstall = () => {
      const event = new Event('beforeinstallprompt', { cancelable: true });
      event.prompt = async () => { state.installs++; state.installed = true; window.dispatchEvent(new Event('appinstalled')); return { outcome: 'accepted' }; };
      window.dispatchEvent(event);
    };
  }, { ios, installed, denied, unsupported, granted, slowWorker, workerFailure, rejectPermission });
  await context.route('**/api/portal/**', async route => {
    const req = route.request(), url = new URL(req.url()); requests.push(url.pathname);
    const body = req.method() === 'POST' ? req.postDataJSON() : null;
    let data = {};
    if (url.pathname.endsWith('/app-config')) data = { lab_name: 'مختبر إزمير', public_key: unavailable ? null : 'BA'.repeat(44) };
    else if (url.pathname.endsWith('/manifest.webmanifest')) data = { name: 'بوابة المريض', start_url: '/portal/' + 'a'.repeat(48), scope: '/portal/', display: 'standalone', icons: [] };
    else if (url.pathname.endsWith('/push/status')) data = server;
    else if (url.pathname.endsWith('/push/subscribe')) {
      if (saveFailures-- > 0) return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'تعذر حفظ الاشتراك مؤقتاً' }) });
      data = server = { subscribed: true, results_enabled: body.results_enabled, offers_enabled: body.offers_enabled };
    }
    else if (url.pathname.endsWith('/push/unsubscribe')) { server.subscribed = false; data = server; }
    else if (url.pathname.endsWith('/push/test')) { data = { queued: true }; items = [{ id: '00000000-0000-4000-8000-000000000001', title: 'إشعارات بوابتك جاهزة', body: 'إشعار تجريبي', created_at: new Date().toISOString(), read_at: null, kind: 'test' }]; }
    else if (url.pathname.endsWith('/notifications/read')) items.forEach(item => item.read_at = new Date().toISOString());
    else if (url.pathname.endsWith('/notifications')) data = { items, unread: items.filter(item => !item.read_at).length };
    else throw new Error('Unexpected endpoint: ' + url.pathname);
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify(data) });
  });
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5173/tests/patient-portal-app-browser.html?mode=' + mode);
  if (!['otp', 'expired'].includes(mode)) await page.getByRole('button', { name: 'إعدادات البوابة', exact: true }).waitFor();
  return { context, page, requests, server: () => server };
}
const allow = page => page.getByRole('button', { name: 'السماح بالإشعارات', exact: true });
const settings = page => page.getByRole('button', { name: 'إعدادات البوابة', exact: true });
const active = page => page.getByRole('heading', { name: 'مفعّلة على هذا الجهاز', exact: true });

try {
  const { page, context, server, requests } = await scenario();
  await allow(page).waitFor();
  assert.equal(await page.locator('link[rel="manifest"]').count(), 1);
  assert.equal(await page.locator('link[rel="manifest"]').getAttribute('href'), '/api/portal/' + 'a'.repeat(48) + '/manifest.webmanifest');
  assert.equal(await page.locator('meta[name="apple-mobile-web-app-capable"]').getAttribute('content'), 'yes');
  assert.equal(await page.locator('meta[name="apple-mobile-web-app-title"]').getAttribute('content'), 'بوابة المريض');
  // Both a server-rendered first visit and SPA navigation restore the staff
  // identity on exit. Switching patients must replace the launch manifest.
  const headCases = await page.evaluate(async () => {
    const { mountPatientPortalHead } = await import('/src/utils/patientPortalHead.js');
    return [false, true].map(serverRendered => {
      const doc = document.implementation.createHTMLDocument('fixture');
      doc.head.innerHTML = serverRendered
        ? '<link rel="manifest" href="/api/portal/old/manifest.webmanifest" data-portal-app-head="true"><meta name="apple-mobile-web-app-capable" content="yes" data-portal-app-head="true">'
        : '<link rel="manifest" href="/manifest.json"><meta name="apple-mobile-web-app-title" content="Staff">';
      const head = mountPatientPortalHead('a'.repeat(48), '/api', doc);
      const first = doc.querySelector('[rel="manifest"]').getAttribute('href');
      head.update('b'.repeat(48));
      const second = doc.querySelector('[rel="manifest"]').getAttribute('href');
      head.restore();
      return { first, second, manifest: doc.querySelector('[rel="manifest"]').getAttribute('href'),
        capable: Boolean(doc.querySelector('[name="apple-mobile-web-app-capable"]')),
        title: doc.querySelector('[name="apple-mobile-web-app-title"]')?.content || null };
    });
  });
  for (const [index, state] of headCases.entries()) {
    assert.equal(state.first, '/api/portal/' + 'a'.repeat(48) + '/manifest.webmanifest');
    assert.equal(state.second, '/api/portal/' + 'b'.repeat(48) + '/manifest.webmanifest');
    assert.equal(state.manifest, '/manifest.json');
    assert.equal(state.capable, false);
    assert.equal(state.title, index === 0 ? 'Staff' : null);
  }
  assert.equal(await page.locator('dialog').count(), 0, 'no custom popup, including first visit');
  assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0, 'no permission request without a gesture');
  assert.equal(await page.evaluate(() => portalMock.subscribeCalls), 0);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  assert.equal(await page.getByRole('link', { name: 'عرض التقرير والنتائج' }).isVisible(), true, 'reports remain immediately accessible');
  await page.screenshot({ path: out + '/inline-mobile.png', fullPage: false });
  await allow(page).click();
  await page.getByText('تم تفعيل إشعاراتك على هذا الجهاز.', { exact: true }).waitFor();
  assert.deepEqual(await page.evaluate(() => portalMock.activation), [true], 'subscribe runs directly in the patient click');
  assert.equal(await page.evaluate(() => portalMock.permissionCalls), 1);
  assert.equal(server().results_enabled, true, 'ready-result alerts default on immediately after permission');
  assert.equal(server().offers_enabled, true, 'new subscription includes the advertised lab announcements after consent');
  await settings(page).click();
  assert.equal(await page.getByRole('switch', { name: 'إشعارات جاهزية النتائج' }).isChecked(), true);
  assert.equal(await page.getByRole('switch', { name: 'إشعارات العروض والمكافآت' }).isChecked(), true);
  await page.getByRole('switch', { name: 'إشعارات العروض والمكافآت' }).uncheck();
  await page.getByRole('button', { name: 'حفظ التفضيلات', exact: true }).click();
  await page.getByText('تم حفظ تفضيلات إشعاراتك.').waitFor();
  assert.equal(server().offers_enabled, false);
  await page.screenshot({ path: out + '/settings-mobile.png', fullPage: false });
  await page.evaluate(() => offerPortalInstall());
  await page.getByRole('button', { name: 'إضافة البوابة للجهاز', exact: true }).click();
  assert.equal(await page.evaluate(() => portalMock.installs), 1);
  await page.getByRole('button', { name: 'إرسال إشعار تجريبي', exact: true }).click();
  await page.getByText(/أُضيف الإشعار التجريبي للإرسال/).waitFor();
  await page.getByRole('button', { name: 'إيقاف على هذا الجهاز', exact: true }).click();
  await page.getByText(/تم إيقاف إشعاراتك على هذا الجهاز/).waitFor();
  assert.equal(server().subscribed, false);
  assert.equal(await page.evaluate(() => localStorage.getItem('mock-browser-subscription')), '1', 'family browser endpoint is preserved');
  const writes = requests.filter(path => path.endsWith('/push/subscribe')).length;
  await page.reload();
  await allow(page).waitFor();
  assert.equal(requests.filter(path => path.endsWith('/push/subscribe')).length, writes, 'explicit opt-out is not restored');
  assert.equal(await page.evaluate(() => portalMock.subscribeCalls), 0);
  assert.match(await page.getByRole('link', { name: 'عرض التقرير والنتائج' }).getAttribute('href'), /#portal=a{48}$/);
  await page.getByRole('button', { name: 'الإشعارات', exact: true }).click();
  await page.getByRole('heading', { name: 'إشعارات بوابتك جاهزة', exact: true }).waitFor();
  await page.screenshot({ path: out + '/inbox-mobile.png', fullPage: false });
  await context.close();
  console.log('PASS inline consent, default results, optional offers, install, inbox, opt-out and report preservation');

  for (const options of [{ ios: true }, { ios: true, installed: true }, { denied: true }, { unsupported: true }, { unavailable: true }, { rejectPermission: true }]) {
    const { page, context, server } = await scenario(options);
    assert.equal(await page.locator('dialog').count(), 0);
    if (options.ios && !options.installed) {
      assert.equal(await allow(page).count(), 0);
      await page.getByRole('button', { name: 'خطوات التفعيل للآيفون' }).click();
      await page.getByText('مرة واحدة على الآيفون', { exact: true }).waitFor();
      await page.screenshot({ path: out + '/iphone-install.png', fullPage: false });
      assert.equal(await page.evaluate(() => portalMock.subscribeCalls), 0);
    } else if (options.installed || options.rejectPermission) {
      await allow(page).click();
      if (options.rejectPermission) {
        await page.getByRole('alert').waitFor(); assert.equal(server().subscribed, false);
        assert.equal(await active(page).count(), 0);
      } else {
        await active(page).waitFor();
        assert.deepEqual(await page.evaluate(() => portalMock.activation), [true]);
        await page.screenshot({ path: out + '/iphone-active.png', fullPage: false });
      }
    } else { await allow(page).waitFor(); assert.equal(await allow(page).isDisabled(), true); }
    await context.close();
    console.log('PASS device state ' + JSON.stringify(options));
  }

  {
    const { page, context, server, requests } = await scenario({ ios: true, installed: true, failSaveOnce: true });
    await allow(page).click();
    await page.getByRole('alert').waitFor();
    assert.equal(server().subscribed, false);
    assert.equal(await active(page).count(), 0, 'native permission alone is not a saved subscription');
    await page.reload();
    await active(page).waitFor();
    assert.equal(server().subscribed, true);
    assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0, 'save resumes without another permission prompt');
    assert.equal(await page.evaluate(() => portalMock.subscribeCalls), 0, 'no new subscription outside a user gesture');
    assert.equal(requests.filter(path => path.endsWith('/push/subscribe')).length, 2);
    await context.close();
    console.log('PASS iPhone permission followed by failed API save recovers automatically on reopening');
  }
  {
    const { page, context, requests } = await scenario({ granted: true });
    await allow(page).waitFor();
    assert.equal(requests.filter(path => path.endsWith('/push/subscribe')).length, 0, 'origin permission must not subscribe another patient');
    await allow(page).click(); await active(page).waitFor();
    assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0);
    await context.close();
  }
  {
    const { page, context } = await scenario({ granted: true, bound: true, resultPreference: false, offerPreference: false });
    await active(page).waitFor();
    await settings(page).click();
    assert.equal(await page.getByRole('switch', { name: 'إشعارات جاهزية النتائج' }).isChecked(), false, 'preserve existing opt-out');
    assert.equal(await page.getByRole('switch', { name: 'إشعارات العروض والمكافآت' }).isChecked(), false, 'preserve existing announcement opt-out');
    assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0);
    await context.close();
  }
  {
    const { page, context } = await scenario({ slowWorker: true });
    assert.equal(await page.getByRole('button', { name: 'جاري التجهيز…', exact: true }).isDisabled(), true);
    assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0);
    await page.evaluate(() => releasePortalWorker());
    await allow(page).click(); await active(page).waitFor();
    await context.close();
    console.log('PASS slow service-worker activation cannot race the native consent');
  }
  {
    const { page, context } = await scenario({ workerFailure: true });
    await page.getByRole('alert').waitFor();
    assert.equal(await page.evaluate(() => portalMock.permissionCalls), 0);
    await page.evaluate(() => { portalMock.workerFailure = false; });
    await page.getByRole('button', { name: 'إعادة تجهيز الإشعارات' }).click();
    await allow(page).click(); await active(page).waitFor();
    await context.close();
    console.log('PASS worker errors are visible and recoverable');
  }
  for (const mode of ['catalog', 'catalog-empty', 'catalog-error']) {
    const {page,context}=await scenario({mode});
    await page.getByRole('button',{name:'الباقات',exact:true}).click();
    const catalog=page.locator('[data-portal-packages]');
    if(mode==='catalog-error') {
      await catalog.getByRole('alert').waitFor();
      assert.equal(await catalog.getByText('لا توجد باقات متاحة حالياً',{exact:true}).count(),0);
      await catalog.getByRole('button',{name:'إعادة المحاولة',exact:true}).click();
    }
    if(mode==='catalog-empty') await catalog.getByText('لا توجد باقات متاحة حالياً',{exact:true}).waitFor();
    else {
      await catalog.getByText('باقة الفحص الشامل',{exact:true}).waitFor();
      await catalog.getByText('اسأل المختبر عن السعر',{exact:true}).waitFor();
      await catalog.getByText('٠ د.ع',{exact:true}).waitFor();
      await page.screenshot({path:out+'/packages-'+mode+'.png',fullPage:true,animations:'disabled'});
    }
    assert.equal(await catalog.getByText(/Hidden/).count(),0,'individual tests and groups are never displayed');
    assert.equal(await catalog.getByRole('button',{name:'التحاليل',exact:true}).count(),0);
    await page.getByRole('button',{name:'الفواتير والتحاليل',exact:true}).click();
    assert.equal(await page.getByRole('link',{name:'عرض التقرير والنتائج'}).isVisible(),true);
    await context.close();
  }
  console.log('PASS actual package catalog only, zero/null prices, empty state, retry and report access');
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
  console.log('PASS existing OTP, expiry and portal isolation');
} finally { await browser.close(); }
