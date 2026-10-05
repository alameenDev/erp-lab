import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const browser = await chromium.launch();
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
try {
  await page.goto('http://127.0.0.1:5173/tests/report-whatsapp-browser.html');
  await page.waitForFunction(() => Boolean(window.fixture));
  const checks = await page.evaluate(async () => {
    const { store, $http, sendMedicalReportWhatsApp: send, nextTick } = window.fixture;
    const passed = [];
    const check = (ok, message) => { if (!ok) throw new Error(message); passed.push(message); };
    const clone = value => JSON.parse(JSON.stringify(value));
    const originalClick = HTMLAnchorElement.prototype.click;
    const originalAdapter = $http.defaults.adapter;
    const originalError = console.error;
    let database, events, mode, tab, releaseSave;
    const output = { blob: new Blob(['%PDF-1.4\nsynthetic'], { type: 'application/pdf' }), filename: 'synthetic.pdf', withBackground: false };
    const params = { page: 3, per_page: 25, search: 'synthetic' };
    const reset = (failure = '') => {
      mode = failure; events = []; releaseSave = null;
      database = { id: 14, sent_to_patient: false, patient: { id: 1, phone: '07700000000', name: 'Synthetic patient' } };
      store.invoices = [clone(database), { id: 15, sent_to_patient: false }];
      store.printRecord = clone(database);
      store.lastParams = clone(params);
      store.stats = { total: 2, sent: 0, completed_sent: 0, pending_sent: 2 };
      tab = { closed: false, opener: {}, close() { this.closed = true; }, location: {
        set href(url) { if (mode === 'navigation') throw new Error('Navigation failed'); events.push(['navigate', url]); },
      } };
    };
    const run = (overrides = {}) => send({ record: store.printRecord, settings: {}, output, tab, markSent: store.changeInvoiceStatus, ...overrides });
    const rejectBeforeSave = async (overrides, label) => {
      let rejected = false;
      try { await run(overrides); } catch { rejected = true; }
      check(rejected && !database.sent_to_patient && !store.invoices[0].sent_to_patient && !events.some(e => e[0] === 'save'), label);
    };
    try {
      // All patients, API replies and windows are synthetic; no real messages are sent.
      HTMLAnchorElement.prototype.click = function () { events.push(['download', this.download]); };
      console.error = () => {}; // Expected failure paths below log their errors.
      $http.defaults.adapter = async config => {
        let data;
        const url = config.url.replace(/^\//, '');
        if (url === 'portal/generate') {
          if (mode === 'portal') throw new Error('Portal unavailable');
          data = { url: 'https://example.test/portal/synthetic-token' };
        } else if (url === 'invoices/send') {
          events.push(['save', JSON.parse(config.data)]);
          if (mode === 'save') throw new Error('Status save failed');
          if (mode === 'delayed-save') await new Promise(resolve => { releaseSave = resolve; });
          database.sent_to_patient = true;
          database.public_with_background = JSON.parse(config.data).with_background;
          data = { message: 'invoice sent' };
        } else if (url === 'invoices') {
          check(JSON.stringify(config.params) === JSON.stringify(params), 'refresh keeps filters and pagination');
          if (mode === 'refresh') throw new Error('Refresh unavailable');
          data = { data: [clone(database), { id: 15, sent_to_patient: false }], stats: {
            total: 2, sent: Number(database.sent_to_patient), completed_sent: Number(database.sent_to_patient), pending_sent: 2 - Number(database.sent_to_patient),
          } };
        } else throw new Error('Unexpected API: ' + config.url);
        return { data, status: 200, statusText: 'OK', headers: {}, config };
      };

      reset('delayed-save');
      const sending = run();
      for (let i = 0; i < 100 && !releaseSave; i++) await new Promise(resolve => setTimeout(resolve, 5));
      check(Boolean(releaseSave), 'status persistence is requested after WhatsApp handoff');
      await nextTick();
      check(document.getElementById('sent').className === 'bg-red-100', 'indicator stays red until save succeeds');
      check(events.map(e => e[0]).join(',') === 'download,navigate,save', 'download and WhatsApp navigation precede status persistence');
      releaseSave();
      check(!(await sending).statusError, 'successful handoff and persistence complete');
      await nextTick();
      check(document.getElementById('sent').className === 'bg-green-100' && store.printRecord.sent_to_patient, 'saved status turns list and open report green');
      check(!store.invoices[1].sent_to_patient, 'other invoices remain unchanged');
      check(database.public_with_background === false && events[2][1].with_background === false, 'without-form choice is preserved in the saved report');
      check(new URL(events[1][1]).searchParams.get('text').includes('form=0'), 'WhatsApp link matches the saved form');
      check(store.stats.sent === 1 && store.stats.pending_sent === 1, 'sent and pending totals update');
      mode = '';
      await run();
      check(store.stats.sent === 1 && store.stats.completed_sent === 1, 'resending does not double-count the report');
      store.invoices = [];
      await store.Getinvoices();
      await nextTick();
      check(document.getElementById('sent').className === 'bg-green-100', 'reload restores the persisted green indicator');

      reset(); await rejectBeforeSave({ tab: null }, 'blocked popup never marks sent');
      reset(); tab.closed = true; await rejectBeforeSave({}, 'closed popup never marks sent');
      reset(); await rejectBeforeSave({ output: { ...output, blob: new Blob(['invalid']) } }, 'invalid PDF never marks sent');
      reset(); store.printRecord.patient.phone = ''; await rejectBeforeSave({}, 'invalid phone never marks sent');
      reset('portal'); await rejectBeforeSave({}, 'portal failure never marks sent');
      reset('navigation'); await rejectBeforeSave({}, 'failed WhatsApp navigation never marks sent');
      reset('save');
      check(Boolean((await run()).statusError), 'failed persistence returns a distinct status error');
      check(!tab.closed && !store.invoices[0].sent_to_patient && !database.sent_to_patient, 'save failure keeps WhatsApp open and indicator red');
      reset('refresh');
      check(!(await run()).statusError, 'refresh failure does not undo successful persistence');
      await nextTick();
      check(document.getElementById('sent').className === 'bg-green-100' && store.stats.sent === 1, 'indicator and count update even when refresh fails');
      await run({ output: { ...output, withBackground: true } });
      check(store.stats.sent === 1 && database.public_with_background === true, 'repeat handoff keeps counts stable and saves updated form choice');
      return passed;
    } finally {
      HTMLAnchorElement.prototype.click = originalClick;
      $http.defaults.adapter = originalAdapter;
      console.error = originalError;
    }
  });
  assert.deepEqual(errors, []);
  console.log(checks.map(message => 'PASS ' + message).join('\n'));
} finally {
  await browser.close();
}
