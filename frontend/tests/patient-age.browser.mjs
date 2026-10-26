import { createServer } from 'vite';
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
const out = 'test-results/patient-age';
await mkdir(out, { recursive: true });
const server = process.env.AGE_START_VITE ? await createServer({ server: { host: '127.0.0.1', port: 5173, strictPort: true, fs: { allow: ['..', process.cwd() + '/node_modules'] } } }) : null;
if (server) await server.listen();
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.clock.install({ time: new Date('2026-10-26T09:00:00Z') });
const errors = [], saved = [];
page.on('pageerror', e => errors.push(e.message));
await page.route('**/fonts.googleapis.com/**', route => route.abort());
await page.route('**/fonts.gstatic.com/**', route => route.abort());
const user = { id: 20, name: 'مختبر اختبار العمر', role_id: 2, email: 'age@example.test' };
const permissions = ['patients view', 'patients create', 'patients edit', 'invoices view', 'invoices create'];
await page.route('**/api/**', async route => {
  const request = route.request(), path = new URL(request.url()).pathname.replace(/^\/api/, '');
  let data = [], status = 200;
  if (path === '/user/login') data = { token: 'age-test-token', user, permissions };
  else if (path === '/user/access') data = { user, permissions };
  else if (path === '/age-units') data = [{ id: 1, unit_name: 'Years' }, { id: 2, unit_name: 'Months' }, { id: 3, unit_name: 'Days' }];
  else if (path === '/genders') data = [{ id: 1, gender_type: 'Male' }, { id: 2, gender_type: 'Female' }];
  else if (path === '/titles') data = [{ id: 1, title: 'Mr' }, { id: 2, title: 'Mrs' }];
  else if (path === '/lab-settings') data = { primary_color: '#0d9488', secondary_color: '#14b8a6', lab_display_name: 'مختبر الاختبار' };
  else if (path === '/lab-announcements/pending') data = { data: [], unread_count: 0 };
  else if (path === '/patients/create' || path === '/patients/update') {
    const fields = request.headers()['content-type']?.includes('application/json')
      ? request.postDataJSON()
      : Object.fromEntries([...request.postData().matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g)].map(m => [m[1], m[2]]));
    saved.push(fields);
    const patient = { ...fields, id: 91, age: Number(fields.age), dob_is_estimated: fields.dob_is_estimated === '1' };
    data = path.endsWith('/update') ? { patient } : patient; status = path.endsWith('/update') ? 200 : 201;
  } else if (path === '/invoices') data = { data: [], pagination: { total: 0, current_page: 1, per_page: 25, last_page: 1 } };
  await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });
});
const navigate = path => page.evaluate(async path => { const { default: router } = await import('/src/router/index.js'); await router.push(path); }, path);
const record = () => page.evaluate(async () => { const { usePatientsStore } = await import('/src/store/modules/patients.js'); return JSON.parse(JSON.stringify(usePatientsStore().record)); });
const input = name => page.locator(`[data-${name}]`).last();
const enterDate = async (day, month, year) => { await input('birth-day').fill(String(day)); await input('birth-month').fill(String(month)); await input('birth-year').fill(String(year)); };
try {
  await page.goto('http://127.0.0.1:5173/login', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.locator('#app input').first().waitFor({ timeout: 60000 });
  await page.evaluate(async () => {
    localStorage.setItem('locale', 'ar');
    const { useAuthStore } = await import('/src/store/modules/auth.js');
    await useAuthStore().login({ email: 'age@example.test', password: 'test-password' });
  });
  await navigate('/invoices/create');
  await input('age-value').waitFor({ timeout: 60000 });
  await page.waitForFunction(() => document.querySelector('[data-age-unit]')?.options.length === 4);
  await input('age-value').fill('30');
  assert.equal((await record()).dob, '1996-10-26');
  assert.equal(await page.locator('[data-birth-estimated]').count(), 1);
  await enterDate(27, 10, 2000);
  assert.equal(await input('age-value').inputValue(), '25'); assert.equal((await record()).dob_is_estimated, false);
  await enterDate(20, 10, 2026);
  assert.equal(await input('age-value').inputValue(), '6'); assert.equal(await input('age-unit').inputValue(), '3');
  await enterDate(26, 10, 2026); assert.equal(await input('age-value').inputValue(), '0');
  await enterDate(31, 4, 2026); assert.ok(await page.locator('[data-patient-age] [role=alert]').count()); assert.equal((await record()).age, '');
  await enterDate(27, 10, 2026); assert.ok(await page.locator('[data-patient-age] [role=alert]').count());
  await enterDate(29, 2, 2024); assert.equal(await input('age-value').inputValue(), '2');
  await page.locator('[data-patient-age]').screenshot({ path: `${out}/invoice-age.png` });
  await enterDate(26, 10, 2026);
  await page.evaluate(async () => {
    const { usePatientsStore } = await import('/src/store/modules/patients.js');
    const s = usePatientsStore(); Object.assign(s.record, { name: 'مولود تجريبي', title_id_fk: 1, gender_id_fk: 1 }); await s.AddPatient();
  });
  assert.equal(saved.at(-1).age, '0'); assert.equal(saved.at(-1).dob, '2026-10-26'); assert.equal(saved.at(-1).dob_is_estimated, '0');
  await navigate('/patients');
  await page.evaluate(async () => {
    const { usePatientsStore } = await import('/src/store/modules/patients.js');
    const s = usePatientsStore(); s.record = { id: 91, name: 'مريض اختبار العمر', age: 30, age_unit_id_fk: 1, dob: '1996-10-26', dob_is_estimated: true, gender_type_id_fk: 1, title_id_fk: 1 }; s.dialog = true;
  });
  await input('birth-year').waitFor(); assert.equal(await input('birth-year').inputValue(), '1996');
  await enterDate(15, 12, 1996); assert.equal(await input('age-value').inputValue(), '29');
  const patientForm = page.locator('form').filter({ has: page.locator('[data-patient-age]') });
  await patientForm.locator('button[type=submit]').click();
  await patientForm.waitFor({ state: 'detached' });
  assert.equal(saved.at(-1).dob, '1996-12-15'); assert.equal(saved.at(-1).age, '29'); assert.equal(saved.at(-1).dob_is_estimated, '0');
  await page.evaluate(async () => {
    const { usePatientsStore } = await import('/src/store/modules/patients.js');
    const s = usePatientsStore(); s.resetPatientForm(); s.dialog = true;
  });
  await input('birth-year').waitFor(); assert.equal(await input('birth-year').inputValue(), '', 'new patient starts blank');
  await input('age-value').fill('6'); await input('age-unit').selectOption('2');
  assert.equal((await record()).dob, '2026-04-26'); assert.equal((await record()).dob_is_estimated, true);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('[data-patient-age]').screenshot({ path: `${out}/patient-age-mobile.png` });
  assert.ok((await page.locator('[data-patient-age]').boundingBox()).width <= 390);
  assert.deepEqual(errors, []);
  console.log('PASS invoice/patient age-DOB, precision persistence, newborn zero, invalid/future dates, reset and mobile');
} catch (error) {
  await writeFile(`${out}/failure.txt`, `${error.stack}\nPage errors: ${JSON.stringify(errors)}\nSaved: ${JSON.stringify(saved)}`);
  await page.screenshot({ path: `${out}/failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); if (server) await server.close(); }
