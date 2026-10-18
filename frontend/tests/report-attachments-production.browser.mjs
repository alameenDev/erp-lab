import {build} from 'vite';
import {chromium} from 'playwright';
import {jsPDF} from 'jspdf';
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {mkdir, readFile, writeFile} from 'node:fs/promises';
import {resolve, extname, sep} from 'node:path';

// Exercise the actual production bundle, not Vite's development worker loader.
const root = resolve('test-results/attachment-production');
await build({build: {outDir: root, emptyOutDir: true, rollupOptions: {input: resolve('tests/report-attachments-browser.html')}}});
const blocked = [], errors = [], workers = [];
const server = createServer(async (request, response) => {
  const pathname = new URL(request.url, 'http://localhost').pathname;
  // Reproduce a host that cannot serve .mjs worker modules.
  if (pathname.endsWith('.mjs')) { blocked.push(pathname); response.writeHead(404).end(); return; }
  const file = resolve(root, '.' + decodeURIComponent(pathname));
  if (!file.startsWith(root + sep)) { response.writeHead(403).end(); return; }
  try {
    const content = await readFile(file);
    response.setHeader('Content-Type', {'.html':'text/html', '.js':'text/javascript', '.css':'text/css', '.png':'image/png'}[extname(file)] || 'application/octet-stream');
    response.end(content);
  } catch { response.writeHead(404).end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const browser = await chromium.launch();
try {
  const page = await browser.newPage({viewport: {width: 1360, height: 1000}});
  page.on('pageerror', error => errors.push(error.message));
  page.on('worker', worker => workers.push(worker.url()));
  await page.goto(`http://127.0.0.1:${server.address().port}/tests/report-attachments-browser.html`, {waitUntil: 'networkidle'});
  await page.waitForFunction(() => window.attachmentFixture?.store?.updateResultRecord?.id === 1);
  await page.getByRole('button', {name: /الملفات/}).click();
  const pdf = new jsPDF();
  for (let i = 1; i <= 2; i++) {
    if (i > 1) pdf.addPage();
    pdf.setFontSize(24); pdf.text('ATTACHED PDF - PAGE ' + i, 20, 35);
    pdf.setFontSize(16); pdf.text('Synthetic patient - Glucose: 7 mmol/L', 20, 60);
  }
  await page.locator('[data-report-new-attachment] input[type=file]').setInputFiles({name: '7884.pdf', mimeType: 'application/pdf', buffer: Buffer.from(pdf.output('arraybuffer'))});
  await page.waitForFunction(() => document.querySelectorAll('[data-report-saved-attachment]').length === 1);
  assert.equal(await page.evaluate(() => attachmentFixture.saved().attachments[0].name), '');
  const name = page.getByRole('textbox', {name: 'اسم المرفق في التقرير'});
  await name.fill('نتائج الفحص الخارجي');
  await page.waitForFunction(() => attachmentFixture.saved().attachments[0].name === 'نتائج الفحص الخارجي');
  const output = await page.evaluate(() => attachmentFixture.prepare(true));
  assert.equal(output.signature, '%PDF-');
  assert.equal(output.count, 3, 'result and both PDF pages are printed');
  assert.deepEqual(await page.locator('#attachment-output [data-attachment-name]').allTextContents(), ['نتائج الفحص الخارجي', 'نتائج الفحص الخارجي']);
  assert.doesNotMatch(await page.locator('#attachment-output').innerText(), /7884\.pdf/);
  assert.equal(await page.locator('#attachment-output .report-attachment img').count(), 2);
  assert.equal(await page.locator('#attachment-output .report-attachment a').count(), 0);
  await mkdir('test-results/report-pages', {recursive: true});
  const bytes = await page.evaluate(async () => Array.from(new Uint8Array(await attachmentFixture.output.blob.arrayBuffer())));
  await writeFile('test-results/report-pages/attachments-production.pdf', Buffer.from(bytes));
  await name.fill('');
  await page.waitForFunction(() => attachmentFixture.saved().attachments[0].name === '');
  assert.equal((await page.evaluate(() => attachmentFixture.prepare(false))).count, 3);
  assert.equal(await page.locator('#attachment-output [data-attachment-name]').count(), 0, 'a cleared name prints no filename or placeholder');
  assert.deepEqual(blocked, [], 'production does not request a standalone .mjs worker');
  assert.ok(workers.length >= 2 && workers.every(url => url.startsWith('blob:') || url.startsWith('data:')), 'each render uses its own bundled inline worker');
  assert.equal(page.workers().length, 0, 'PDF workers are terminated after rendering');
  assert.deepEqual(errors, []);
  console.log('PASS production attachments: .mjs blocked, inline worker, all PDF pages, saved name editing/clearing, no filename fallback');
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
