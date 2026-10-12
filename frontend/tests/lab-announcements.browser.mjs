import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { chromium } from 'playwright';

const output = 'test-results/lab-announcements';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const notices = [], receipts = new Set(), payloads = [], pageErrors = [];
let failNextSend = false;
let releaseDelayedPoll = null, delayNextPoll = false;
const labs = [{ id: 11, name: 'مختبر تجريبي أول' }, { id: 12, name: 'مختبر تجريبي ثانٍ' }];
const url = 'http://127.0.0.1:5173/tests/lab-announcements-browser.html';

async function contextFor(id, role) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  await context.addInitScript(({ id, role }) => {
    localStorage.setItem('token', `synthetic-${id}`);
    localStorage.setItem('user', JSON.stringify({ id, role_id: role, name: 'Synthetic account' }));
    localStorage.setItem('locale', 'ar');
  }, { id, role });
  await context.route('**/api/lab-announcements**', async (route) => {
    const request = route.request(), path = new URL(request.url()).pathname;
    const send = (body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
    if (path.endsWith('/labs')) return send(labs);
    if (path.endsWith('/pending')) {
      const data = notices.filter((notice) => notice.targets.includes(id) && !receipts.has(`${id}:${notice.id}`));
      const snapshot = { lab_id: id, unread_count: data.length, data: data.slice(0, 20) };
      if (delayNextPoll) { delayNextPoll = false; await new Promise((resolve) => { releaseDelayedPoll = resolve; }); }
      return send(snapshot);
    }
    if (path.endsWith('/inbox')) {
      const data = notices.filter((notice) => notice.targets.includes(id)).map((notice) => ({ ...notice, read_at: receipts.has(`${id}:${notice.id}`) ? '2026-10-12 10:00:00' : null }));
      return send({ data, current_page: 1, last_page: 1, total: data.length });
    }
    if (path.endsWith('/history')) return send({ data: notices, current_page: 1, last_page: 1, total: notices.length });
    if (path.endsWith('/read')) { receipts.add(`${id}:${Number(path.split('/').at(-2))}`); return send({ message: 'Read' }); }
    if (request.method() === 'POST' && path.replace(/\/$/, '').endsWith('/lab-announcements')) {
      const payload = request.postDataJSON(); payloads.push(payload);
      let notice = notices.find((item) => item.request_id === payload.request_id);
      if (!notice) {
        notice = { ...payload, id: notices.length + 1, created_at: '2026-10-12 09:00:00', read_at: null,
          targets: payload.audience === 'all' ? labs.map((lab) => lab.id) : payload.lab_ids };
        notices.push(notice);
      }
      if (failNextSend) { failNextSend = false; return route.abort('failed'); }
      return send({ id: notice.id, target_count: notice.targets.length }, 201);
    }
    return send({ message: 'Unexpected route' }, 404);
  });
  const page = await context.newPage();
  page.on('pageerror', (error) => pageErrors.push(error.message));
  await page.goto(url);
  return { context, page };
}

try {
  const { page: admin } = await contextFor(1, 1);
  await admin.getByTestId('notice-admin-open').click();
  await admin.getByTestId('notice-title').fill('صيانة دورية تجريبية');
  const body = '<img src=x onerror="window.__noticeXss=1">\nرسالة تجريبية فقط.';
  await admin.getByTestId('notice-body').fill(body);
  await admin.getByTestId('notice-lab-11').check();
  await admin.getByTestId('notice-review').click();
  await admin.getByTestId('notice-send').click();
  await admin.getByRole('status').filter({ hasText: 'تم إرسال' }).waitFor();
  assert.deepEqual(payloads[0].lab_ids, [11]);
  assert.equal(payloads[0].audience, 'selected');
  await admin.screenshot({ path: `${output}/admin.png`, fullPage: true });

  // A failed response after the server has accepted the send must reuse its key.
  await admin.getByTestId('notice-title').fill('إشعار لجميع المختبرات');
  await admin.getByTestId('notice-body').fill('Synthetic retry verification');
  await admin.getByTestId('notice-all').check();
  await admin.getByTestId('notice-review').click();
  failNextSend = true;
  await admin.getByTestId('notice-send').click();
  await admin.getByRole('alert').waitFor();
  await admin.getByTestId('notice-review').click();
  await admin.getByTestId('notice-send').click();
  await admin.getByRole('status').filter({ hasText: 'تم إرسال' }).waitFor();
  assert.equal(payloads[1].request_id, payloads[2].request_id);
  assert.equal(notices.length, 2);
  assert.equal(payloads[2].audience, 'all');
  assert.equal('lab_ids' in payloads[2], false);

  const { page: lab } = await contextFor(11, 2);
  await lab.getByTestId('notice-popup').waitFor();
  assert.match(await lab.getByTestId('notice-popup').innerText(), /صيانة دورية/);
  assert.equal(await lab.getByTestId('notice-popup').locator('img').count(), 0);
  assert.equal(await lab.evaluate(() => window.__noticeXss), undefined);
  await lab.screenshot({ path: `${output}/popup.png`, fullPage: true });
  await lab.getByTestId('notice-ack').click();
  await lab.getByRole('heading', { name: 'إشعار لجميع المختبرات', exact: true }).waitFor();
  await lab.getByTestId('notice-ack').click();
  await lab.getByTestId('notice-popup').waitFor({ state: 'hidden' });
  await lab.reload();
  await lab.getByTestId('notice-inbox-open').waitFor();
  await lab.waitForTimeout(300);
  assert.equal(await lab.getByTestId('notice-popup').count(), 0);
  await lab.getByTestId('notice-inbox-open').click();
  await lab.locator('.notice-inbox-item').first().waitFor();
  assert.equal(await lab.locator('.notice-inbox-item').count(), 2);
  await lab.reload();

  const { page: other } = await contextFor(12, 2);
  await other.getByTestId('notice-popup').waitFor();
  assert.match(await other.getByTestId('notice-popup').innerText(), /إشعار لجميع المختبرات/);
  assert.doesNotMatch(await other.getByTestId('notice-popup').innerText(), /صيانة دورية/);
  await other.getByTestId('notice-ack').click();

  // A new send appears without navigation or reload of the lab page.
  await lab.bringToFront();
  notices.push({ id: 3, title: 'وصل أثناء العمل', body: 'Live polling', level: 'info', targets: [11], created_at: '2026-10-12 09:05:00', read_at: null });
  await lab.getByRole('heading', { name: 'وصل أثناء العمل', exact: true }).waitFor({ timeout: 12000 });

  // Simulate a stale in-flight poll finishing after a successful acknowledgement.
  delayNextPoll = true;
  await lab.evaluate(() => window.dispatchEvent(new Event('focus')));
  for (let i = 0; i < 50 && !releaseDelayedPoll; i++) await lab.waitForTimeout(20);
  assert.ok(releaseDelayedPoll, 'a background poll must be in flight');
  await lab.getByTestId('notice-ack').click();
  await lab.getByTestId('notice-popup').waitFor({ state: 'hidden' });
  releaseDelayedPoll(); releaseDelayedPoll = null;
  await lab.waitForTimeout(300);
  assert.equal(await lab.getByTestId('notice-popup').count(), 0, 'a stale poll must not resurrect a read popup');
  assert.ok(receipts.has('11:3'));

  // The component must stop displaying notifications as soon as the session changes.
  await lab.evaluate(() => {
    localStorage.removeItem('token');
    window.dispatchEvent(new StorageEvent('storage', { key: 'token' }));
  });
  await lab.getByTestId('notice-inbox-open').waitFor({ state: 'hidden' });
  const { page: patient } = await contextFor(31, 3);
  assert.equal(await patient.locator('.lab-announcements-bar').count(), 0);
  assert.deepEqual(pageErrors, []);
  console.log('PASS: compose, targeted/all delivery, retry idempotency, XSS escaping, persistent receipts, inbox, live polling, stale-response race, logout and patient exclusion.');
} finally {
  releaseDelayedPoll?.();
  await browser.close();
}
