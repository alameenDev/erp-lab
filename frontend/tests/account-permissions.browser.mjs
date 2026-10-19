import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

await mkdir('test-results/account-permissions', { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const failures = [];
page.on('pageerror', error => failures.push(error.message));
let permissions = ['patients view'];
let user = { id: 20, name: 'حساب اختبار الصلاحيات', role_id: 50, email: 'staff@example.test', roles: [], permissions: [] };
let denyWith401 = false;
const requests = [];
const roles = [{ id: 50, name: 'الاستقبال', permissions: [], can_edit: true, can_delete: true }, { id: 51, name: 'قارئ النتائج', permissions: [], can_edit: true, can_delete: true }];
const rows = [{ id: 21, name: 'موظف تجريبي', role: 'الاستقبال', role_id: 50, email: 'second@example.test' }];
await page.route('**/api/**', async route => {
  const request = route.request();
  const path = new URL(request.url()).pathname.replace(/^\/api/, '');
  const body = request.postDataJSON();
  requests.push({ path, method: request.method(), body });
  let data = [], status = 200;
  if (path === '/user/login') data = { token: 'test-account-token', user, permissions };
  else if (path === '/user/access') data = { user, permissions };
  else if (path === '/lab-settings') data = { primary_color: '#0d9488', secondary_color: '#14b8a6', lab_display_name: 'مختبر الاختبار', print_margins: { top: 20, bottom: 20, left: 15, right: 15 } };
  else if (path === '/lab-announcements/pending') data = { data: [], unread_count: 0 };
  else if (path === '/user/show') data = rows;
  else if (path === '/roles/show') data = roles;
  else if (path === '/roles') data = { roles };
  else if (path === '/permissions') data = { permissions: [{ name: 'patients', permissions: [{ id: 101, name: 'view', full_name: 'patients view' }] }] };
  else if (path === '/roles/assign-role') { rows[0].role_id = body.role_id; rows[0].role = roles.find(role => role.id === body.role_id).name; data = { success: true }; }
  else if (path === '/roles/create') {
    if (roles.some(role => role.name === body.role)) { status = 422; data = { message: 'اسم الدور مستخدم', errors: { role: ['اسم الدور مستخدم'] } }; }
    else { roles.push({ id: 52, name: body.role, permissions: body.permissions, can_edit: true, can_delete: true }); data = { success: true }; }
  }
  else if (path === '/roles/update') { roles.find(role => role.id === body.role_id).permissions = body.permissions; data = { success: true }; }
  else if (path === '/roles/delete') { assert.ok(body.role_id, 'role_id is sent for deletion'); data = { success: true }; }
  else if (path === '/permission-probe') { status = denyWith401 ? 401 : 403; data = { message: 'غير مسموح لهذا الحساب' }; }
  else if (path === '/invoices') data = { data: [], pagination: { total: 0, current_page: 1, per_page: 25, last_page: 1 } };
  else if (path === '/reports/dashboard-stats') { status = 403; data = { message: 'Forbidden' }; }
  await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });
});

const navigate = path => page.evaluate(async path => { const { default: router } = await import('/src/router/index.js'); await router.push(path); }, path);
const token = () => page.evaluate(() => localStorage.getItem('token'));
try {
  await page.goto('http://127.0.0.1:5173/login');
  await page.evaluate(async () => {
    localStorage.setItem('locale', 'ar');
    localStorage.setItem('rolePermissions', JSON.stringify(['super admin dashboard', 'reports view']));
    const { useAuthStore } = await import('/src/store/modules/auth.js');
    await useAuthStore().login({ email: 'staff@example.test', password: 'test-password' });
  });
  await page.waitForURL('**/patients');
  await page.locator('aside').waitFor();
  assert.equal(await token(), 'test-account-token');
  assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('rolePermissions'))), ['patients view']);
  assert.equal(requests.some(request => request.path === '/reports/dashboard-stats'), false, 'login never requests forbidden dashboard');
  await page.screenshot({ path: 'test-results/account-permissions/restricted-login.png', fullPage: true });

  // An optional API denial must not eject the user or navigate away.
  await page.evaluate(async () => { const { $http } = await import('/src/plugins/axios.js'); await $http.get('/permission-probe').catch(() => {}); });
  assert.match(page.url(), /\/patients$/);
  assert.equal(await token(), 'test-account-token');
  await navigate('/roles');
  await page.waitForURL('**/access-denied');
  assert.equal(await token(), 'test-account-token');

  // No grants still means a valid login; direct grants survive a reload.
  permissions = [];
  await navigate('/patients');
  await page.waitForURL('**/access-denied');
  await page.getByRole('heading', { name: 'صلاحيات الوصول', exact: true }).waitFor();
  await page.screenshot({ path: 'test-results/account-permissions/no-permissions.png', fullPage: true });
  permissions = ['samples view'];
  await navigate('/samples');
  await page.reload();
  await page.waitForURL('**/samples');
  assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('rolePermissions'))), ['samples view']);

  // A child item remains reachable without the parent menu's tests permission.
  permissions = ['packages view'];
  await navigate('/packages');
  await page.locator('aside').getByText('الفحوصات', { exact: true }).first().waitFor().catch(() => {});
  assert.equal(await token(), 'test-account-token');

  // User editors can assign a role without access to the roles administration page.
  permissions = ['users view', 'users edit'];
  await navigate('/users');
  await page.locator('tbody').getByText('موظف تجريبي', { exact: true }).waitFor();
  await page.locator('tbody button.text-purple-500').click();
  const roleSelect = page.locator('form select').filter({ has: page.locator('option', { hasText: 'قارئ النتائج' }) });
  await roleSelect.selectOption('51');
  await roleSelect.locator('xpath=ancestor::form').locator('button[type="submit"]').click();
  await page.locator('tbody').getByText('قارئ النتائج', { exact: true }).waitFor();
  assert.ok(requests.some(request => request.path === '/roles/assign-role' && request.body.role_id === 51));
  await page.screenshot({ path: 'test-results/account-permissions/role-assignment.png', fullPage: true });

  permissions = ['roles view', 'roles create', 'roles edit', 'roles delete'];
  await navigate('/roles');
  await page.locator('tbody').getByText('الاستقبال', { exact: true }).waitFor();
  // Use the mounted store to open the actual role editor, preserving real UI save behavior.
  await page.evaluate(async () => { const { useRolesstore } = await import('/src/store/modules/roles.js'); const store = useRolesstore(); store.record = { id: 50, role: 'الاستقبال', name: 'الاستقبال', permissions: [101] }; store.dialog = true; });
  await page.locator('input[type="checkbox"]').first().waitFor();
  await page.locator('input[type="checkbox"]').first().uncheck();
  await page.locator('.fixed.inset-0.z-50').getByRole('button', { name: /حفظ/ }).click();
  await page.waitForFunction(() => !document.querySelector('.fixed.inset-0.z-50 input[type="checkbox"]'));
  assert.deepEqual(requests.findLast(request => request.path === '/roles/update').body.permissions, [], 'removing all permissions is sent explicitly');

  // A real unauthenticated response still clears the session.
  denyWith401 = true;
  await page.evaluate(async () => { const { $http } = await import('/src/plugins/axios.js'); await $http.get('/permission-probe').catch(() => {}); });
  await page.waitForURL('**/login');
  assert.equal(await token(), null);
  assert.deepEqual(failures, []);
  console.log('PASS: custom/empty/direct roles, forbidden actions, guarded routes, reload, assignment UI, empty role save, genuine session expiry.');
} finally { await browser.close(); }
