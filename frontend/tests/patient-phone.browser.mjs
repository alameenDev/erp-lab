import { createServer } from 'vite';
import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';

const out = 'test-results/patient-age';
await mkdir(out, { recursive: true });
const server = process.env.AGE_START_VITE ? await createServer({ server: { host: '127.0.0.1', port: 5173, strictPort: true } }) : null;
if (server) await server.listen();
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1360, height: 1000 } });
const errors = [], saved = [];
page.on('pageerror', e => errors.push(e.message));
const user = { id: 20, name: 'Phone test lab', role_id: 2, email: 'phone@example.test' };
const permissions = ['patients view', 'patients create', 'patients edit'];
let patient = { id: 91, name: 'Phone test patient', title: 'Mr', age: 30, age_unit: 'Years',
  gender: 'Male', phone_number: '+9647701234567', dob: null, dob_is_estimated: false };
await page.route('**/fonts.googleapis.com/**', route => route.abort());
await page.route('**/fonts.gstatic.com/**', route => route.abort());
await page.route('**/api/**', async route => {
  const request = route.request(), path = new URL(request.url()).pathname.replace(/^\/api/, '');
  let data = [], status = 200;
  if (path === '/user/login') data = { token: 'phone-test-token', user, permissions };
  else if (path === '/user/access') data = { user, permissions };
  else if (path === '/age-units') data = [{ id: 1, unit_name: 'Years' }, { id: 2, unit_name: 'Months' }, { id: 3, unit_name: 'Days' }];
  else if (path === '/genders') data = [{ id: 1, gender_type: 'Male' }, { id: 2, gender_type: 'Female' }];
  else if (path === '/titles') data = [{ id: 1, title: 'Mr' }, { id: 2, title: 'Mrs' }];
  else if (path === '/lab-settings') data = { primary_color: '#0d9488' };
  else if (path === '/lab-announcements/pending') data = { data: [], unread_count: 0 };
  else if (path === '/patients') data = [patient];
  else if (path === '/patients/create' || path === '/patients/update') {
    const fields = request.headers()['content-type']?.includes('application/json') ? request.postDataJSON()
      : Object.fromEntries([...request.postData().matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g)].map(m => [m[1], m[2]]));
    saved.push(fields);
    assert.match(fields.phone_number, /^(?:\+964[0-9]{10})?$/, 'request matches the real API phone contract');
    patient = { ...patient, ...fields, id: 91, age: Number(fields.age), phone_number: fields.phone_number || null };
    delete patient.phone; // PatientResource returns phone_number, not the form alias.
    patient.gender = fields.gender_id_fk === '2' ? 'Female' : 'Male';
    patient.title = fields.title_id_fk === '2' ? 'Mrs' : 'Mr';
    patient.age_unit = ({ 1: 'Years', 2: 'Months', 3: 'Days' })[fields.age_unit_id_fk];
    data = path.endsWith('/update') ? { patient } : patient;
    status = path.endsWith('/update') ? 200 : 201;
  }
  await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });
});
const form = page.locator('form').filter({ has: page.locator('[data-patient-age]') });
const phone = form.locator('input[type=tel]');
const save = async () => {
  const before = saved.length;
  await form.locator('button[type=submit]').click();
  await form.waitFor({ state: 'detached' });
  assert.equal(saved.length, before + 1);
};
let labels;
const openEdit = async () => {
  await page.locator('tbody tr').filter({ hasText: patient.name }).getByTitle(labels.update, { exact: true }).click();
  await phone.waitFor();
};
const reloadPatient = async raw => {
  patient.phone_number = raw;
  await page.evaluate(async () => { const { usePatientsStore } = await import('/src/store/modules/patients.js'); await usePatientsStore().GetRecords(); });
  await openEdit();
};
try {
  await page.goto('http://127.0.0.1:5173/login', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.locator('#app input').first().waitFor({ timeout: 60000 });
  labels = await page.evaluate(async () => {
    localStorage.setItem('locale', 'ar');
    const { useAuthStore } = await import('/src/store/modules/auth.js');
    const { default: router } = await import('/src/router/index.js');
    const { t } = await import('/src/utils/helper.js');
    await useAuthStore().login({ email: 'phone@example.test', password: 'test-password' });
    await router.push('/patients');
    return { update: t('update'), addPatient: t('addPatient') };
  });
  await openEdit();
  assert.equal(await phone.inputValue(), '7701234567', 'list edit strips the stored country code before validation');
  await form.locator('input[type=text]').first().fill('Renamed phone patient');
  await save();
  assert.equal(saved.at(-1).phone_number, '+9647701234567', 'unrelated edit preserves the phone');
  assert.equal(saved.at(-1).name, 'Renamed phone patient');
  for (const raw of ['07701234567', '9647701234567', '009647701234567', '+964 770 123 4567', '٠٧٧٠١٢٣٤٥٦٧', null]) {
    await reloadPatient(raw);
    assert.equal(await phone.inputValue(), raw == null ? '' : '7701234567');
    await save();
    assert.equal(saved.at(-1).phone_number, raw == null ? '' : '+9647701234567');
  }
  for (const raw of ['+964 780 765 4321', '009647807654321', '07807654321', '٠٧٨٠٧٦٥٤٣٢١', '۰۷۸۰۷۶۵۴۳۲۱']) {
    await openEdit();
    await phone.fill(raw);
    assert.equal(await phone.inputValue(), '7807654321', 'pasting a complete number does not truncate it');
    await save();
    assert.equal(saved.at(-1).phone_number, '+9647807654321');
  }
  await openEdit();
  const beforeInvalid = saved.length;
  for (const invalid of ['770123', '77012345678', '+971501234567', '+964', '770abc1234567']) {
    await phone.fill(invalid);
    await form.locator('button[type=submit]').click();
    assert.match(await phone.getAttribute('class'), /border-red-400/);
    assert.equal(saved.length, beforeInvalid, 'invalid phone never reaches API');
  }
  await phone.fill(''); await save();
  assert.equal(saved.at(-1).phone_number, '', 'intentional clear is sent explicitly');
  await openEdit(); assert.equal(await phone.inputValue(), '', 'cleared number stays empty on reopen');
  await phone.fill('07701234567'); await save();
  await page.getByRole('button', { name: labels.addPatient, exact: true }).first().click();
  await phone.waitFor();
  assert.equal(await phone.inputValue(), '', 'new patient does not inherit the previous phone');
  await form.locator('input[type=text]').first().fill('New phone patient');
  await form.locator('select').first().selectOption('2');
  await form.locator('[data-age-value]').fill('28');
  await phone.fill('009647701234567');
  await save(); assert.equal(saved.at(-1).phone_number, '+9647701234567');
  await page.setViewportSize({ width: 390, height: 844 });
  await openEdit();
  await form.screenshot({ path: `${out}/patient-phone-mobile.png` });
  assert.equal(await phone.inputValue(), '7701234567');
  await save();
  assert.deepEqual(errors, []);
  console.log('PASS patient list edit, unchanged/corrected/cleared phone, stored formats, full paste, Arabic digits, invalid input, create and mobile');
} catch (error) {
  await writeFile(`${out}/phone-failure.txt`, `${error.stack}\nErrors: ${JSON.stringify(errors)}`);
  await page.screenshot({ path: `${out}/phone-failure.png`, fullPage: true });
  throw error;
} finally { await browser.close(); if (server) await server.close(); }
