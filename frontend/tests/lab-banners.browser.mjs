import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const output = 'test-results/lab-banners';
await fs.mkdir(output, { recursive: true });
// Entire test uses synthetic accounts, mocked API responses and generated image fixtures.
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j1xkAAAAASUVORK5CYII=', 'base64');
const sample = { name: 'banner.png', mimeType: 'image/png', buffer: png };
const state = { banners: [], keys: [], failCreateOnce: true, failFeed: false, reads: 0 };
const contexts = [], pages = [], errors = [];
const browser = await chromium.launch({ headless: true });
async function pageFor(id, role, locale = 'ar') {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } }); contexts.push(context);
  await context.addInitScript(({ id, role, locale }) => {
    localStorage.setItem('locale', locale);
    if (id) { localStorage.setItem('token', `synthetic-${id}`); localStorage.setItem('user', JSON.stringify({ id, role_id: role, name: 'Synthetic Lab', creator_id: role === 7 ? 11 : null })); }
  }, { id, role, locale });
  const page = await context.newPage(); pages.push(page);
  page.on('pageerror', (error) => errors.push(error.message));
  await page.route(/\/storage\/lab-banners\//, (route) => route.fulfill({ status: 200, contentType: 'image/png', body: png }));
  await page.route(/\/api\/lab-banners(?:[/?]|$)/, async (route) => {
    const request = route.request(), url = new URL(request.url()), path = url.pathname, method = request.method();
    const json = (body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
    if (path.endsWith('/labs')) return json([{ id: 11, name: 'Synthetic Lab One' }, { id: 22, name: 'Synthetic Lab Two' }]);
    if (path.endsWith('/active')) {
      state.reads++;
      if (state.failFeed) return json({ message: 'Synthetic temporary outage' }, 500);
      return json({ data: state.banners.filter((banner) => banner.enabled && (banner.audience === 'all' || banner.targets.some((lab) => lab.id === id)))
        .sort((a, b) => a.sort_order - b.sort_order || b.id - a.id), refresh_after: 60 });
    }
    if (method === 'GET') return json({ data: [...state.banners], current_page: 1, last_page: 1, total: state.banners.length });
    if (method === 'DELETE') {
      const target = Number(path.split('/').pop()); state.banners = state.banners.filter((banner) => banner.id !== target);
      return json({ message: 'Deleted' });
    }
    assert.match(request.headers()['content-type'], /multipart\/form-data; boundary=/);
    const raw = request.postDataBuffer().toString('utf8');
    const field = (name) => { const start = raw.indexOf(`name="${name}"\r\n\r\n`); if (start < 0) return ''; return raw.slice(start + `name="${name}"\r\n\r\n`.length).split('\r\n--')[0]; };
    const targetId = /\/(\d+)$/.exec(path)?.[1];
    const key = field('request_id'); if (!targetId) state.keys.push(key);
    const existing = targetId ? state.banners.find((banner) => banner.id === Number(targetId)) : state.banners.find((banner) => banner.request_id === key);
    if (!existing || targetId) {
      if (!targetId) assert.ok(raw.includes('name="image"; filename="banner.png"'), 'main image uploaded as file');
      const labIds = [...raw.matchAll(/name="lab_ids\[\]"\r\n\r\n(\d+)/g)].map((match) => Number(match[1]));
      const banner = { id: existing?.id || state.banners.length + 1, request_id: key || existing?.request_id,
        version: existing ? existing.version + 1 : 1, title: field('title'), description: field('description'),
        image_url: '/storage/lab-banners/desktop.png', mobile_image_url: raw.includes('name="mobile_image";') ? '/storage/lab-banners/phone.png' : existing?.mobile_image_url || null,
        button_label: field('button_label'), button_url: field('button_url'), enabled: field('enabled') === '1',
        audience: field('audience'), sort_order: Number(field('sort_order')),
        targets: labIds.map((labId) => ({ id: labId, name: `Synthetic Lab ${labId}` })), starts_at: null, ends_at: null };
      if (field('remove_mobile_image') === '1') banner.mobile_image_url = null;
      if (existing) state.banners[state.banners.indexOf(existing)] = banner; else state.banners.push(banner);
    }
    if (!targetId && state.failCreateOnce) { state.failCreateOnce = false; return route.abort('failed'); }
    return json({ banner: targetId ? state.banners.find((banner) => banner.id === Number(targetId)) : state.banners.find((banner) => banner.request_id === key), message: 'Saved' }, targetId ? 200 : 201);
  });
  await page.goto('http://127.0.0.1:5173/tests/lab-banners-browser.html');
  return page;
}
try {
  const admin = await pageFor(1, 1);
  await admin.getByTestId('banner-manager-open').click();
  await admin.getByTestId('banner-add').click();
  await admin.getByTestId('banner-title').fill('Targeted laboratory offer');
  await admin.getByTestId('banner-description').fill('Details about the new laboratory offer. '.repeat(5));
  await admin.getByTestId('banner-upload').setInputFiles(sample);
  await admin.getByTestId('banner-mobile-upload').setInputFiles({ ...sample, name: 'mobile.png' });
  await admin.getByTestId('banner-button-label').fill('Learn more');
  await admin.getByTestId('banner-button-url').fill('https://example.test/offer');
  await admin.getByTestId('banner-audience-selected').check();
  await admin.getByTestId('banner-lab-11').check();
  await admin.getByTestId('banner-preview-mobile').click();
  await admin.screenshot({ path: `${output}/admin-preview.png`, fullPage: true });
  await admin.getByTestId('banner-save').click();
  await admin.getByTestId('banner-error').waitFor();
  await admin.getByTestId('banner-save').click();
  await admin.getByTestId('banner-item-1').waitFor();
  assert.equal(state.banners.length, 1); assert.equal(state.keys[0], state.keys[1]);
  assert.deepEqual(state.banners[0].targets.map((lab) => lab.id), [11]);
  assert.equal(state.banners[0].mobile_image_url, '/storage/lab-banners/phone.png');
  await admin.getByTestId('banner-edit-1').click();
  await admin.getByTestId('banner-title').fill('<img src=x onerror="window.bannerXss=1"> Safe text');
  await admin.getByTestId('banner-save').click(); await admin.getByTestId('banner-item-1').waitFor();
  await admin.getByTestId('banner-add').click();
  await admin.getByTestId('banner-title').fill('General laboratory news');
  await admin.getByTestId('banner-upload').setInputFiles(sample);
  await admin.locator('input[type=number]').fill('10');
  await admin.getByTestId('banner-save').click(); await admin.getByTestId('banner-item-2').waitFor();
  assert.equal(state.banners[1].audience, 'all');

  const lab = await pageFor(11, 2);
  await lab.getByTestId('lab-banner-card').waitFor();
  const noticeBox = await lab.getByTestId('notifications').boundingBox();
  const bannerBox = await lab.getByTestId('lab-home-banners').boundingBox();
  const welcomeBox = await lab.getByTestId('welcome').boundingBox();
  assert.ok(bannerBox.y >= noticeBox.y + noticeBox.height && welcomeBox.y >= bannerBox.y + bannerBox.height);
  assert.equal(await lab.locator('h2 img').count(), 0); assert.equal(await lab.evaluate(() => window.bannerXss), undefined);
  assert.equal(await lab.getByTestId('banner-link').getAttribute('target'), '_blank');
  assert.equal(await lab.getByTestId('banner-link').getAttribute('rel'), 'noopener noreferrer');
  for (const width of [1440, 768, 390, 320]) {
    await lab.setViewportSize({ width, height: 1000 });
    await lab.waitForTimeout(150);
    const dimensions = await lab.evaluate(() => ({ viewport: innerWidth, scroll: document.documentElement.scrollWidth, src: document.querySelector('[data-testid="banner-image"]').currentSrc }));
    assert.ok(dimensions.scroll <= dimensions.viewport + 1, `no horizontal overflow at ${width}`);
    assert.ok(dimensions.src.endsWith(width <= 640 ? '/phone.png' : '/desktop.png'));
    assert.ok(await lab.getByTestId('banner-link').isVisible());
    await lab.screenshot({ path: `${output}/lab-${width}.png`, fullPage: true });
  }
  await lab.getByTestId('banner-next').click();
  assert.match(await lab.getByTestId('lab-banner-card').innerText(), /General laboratory news/);
  assert.equal(await lab.getByTestId('banner-link').count(), 0);
  await lab.emulateMedia({ media: 'print' }); assert.equal(await lab.getByTestId('lab-home-banners').isVisible(), false);
  await lab.emulateMedia({ media: 'screen' });
  await lab.evaluate(() => window.setBannerView('invoices'));
  await lab.getByTestId('lab-home-banners').waitFor({ state: 'detached' });
  await lab.evaluate(() => window.setBannerView('dashboard')); await lab.getByTestId('lab-home-banners').waitFor();
  await lab.evaluate(() => { localStorage.removeItem('token'); window.dispatchEvent(new StorageEvent('storage', { key: 'token' })); });
  await lab.getByTestId('lab-home-banners').waitFor({ state: 'detached' });

  const other = await pageFor(22, 2, 'en');
  await other.getByTestId('lab-banner-card').waitFor();
  assert.match(await other.getByTestId('lab-banner-card').innerText(), /General laboratory news/);
  assert.equal(await other.getByTestId('lab-home-banners').getAttribute('dir'), 'ltr');
  assert.equal(await other.getByTestId('banner-next').count(), 0);
  await other.setViewportSize({ width: 390, height: 900 }); await other.screenshot({ path: `${output}/lab-english.png`, fullPage: true });
  const before = state.reads;
  const patient = await pageFor(33, 3); await patient.waitForTimeout(200);
  assert.equal(await patient.getByTestId('lab-home-banners').count(), 0); assert.equal(state.reads, before);
  state.failFeed = true;
  const unavailable = await pageFor(11, 2); await unavailable.waitForTimeout(200);
  assert.equal(await unavailable.getByTestId('lab-home-banners').count(), 0);
  assert.ok(await unavailable.getByTestId('welcome').isVisible());
  assert.equal(await unavailable.getByRole('dialog').count(), 0);
  state.failFeed = false;
  await admin.getByTestId('banner-delete-1').click();
  await admin.getByTestId('banner-confirm-delete').click();
  await admin.getByTestId('banner-item-1').waitFor({ state: 'detached' });
  assert.equal(state.banners.length, 1);
  assert.deepEqual(errors, []);
  console.log('PASS: multipart image uploads, mobile preview, targeted/all audience, retry, edit/delete, escaped text, responsive 1440/768/390/320, picture sources, CTA, print exclusion, route lifecycle, logout, patient exclusion and silent feed failure.');
} catch (error) {
  await fs.writeFile(`${output}/failure.txt`, `${error.stack}\nPage errors:\n${errors.join('\n')}`);
  for (let i = 0; i < pages.length; i++) await pages[i].screenshot({ path: `${output}/failure-${i}.png`, fullPage: true }).catch(() => {});
  throw error;
} finally {
  for (const context of contexts) await context.close();
  await browser.close();
}
