// Isolated synthetic data only; no login, API or patient data is used.
import { chromium } from 'playwright';
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const out = 'barcode-qa';
await mkdir(out, { recursive:true });
const server=spawn(process.execPath,['node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','5183'],{stdio:'pipe'});
let browser;
try {
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('Vite startup timeout')),30000);server.stdout.on('data',s=>{if(s.toString().includes('Local:')){clearTimeout(timer);resolve();}});server.on('error',reject);});
  browser=await chromium.launch({headless:true});
  const page=await browser.newPage({viewport:{width:1380,height:1100}}), errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(()=>{window.print=function(){window.top.__printedHtml=document.documentElement.outerHTML;};});
  await page.goto('http://127.0.0.1:5183/tests/fixtures/barcode-designer.html');
  await page.locator('.label-draggable').first().waitFor();
  for(const size of ['76.2 × 38.1 mm','50 × 30 mm','60 × 40 mm','40 × 25 mm']) {
    await page.getByRole('button',{name:size,exact:true}).click();
    await page.waitForTimeout(150);
    const problems=await page.locator('[role=status]').allTextContents();
    assert.deepEqual(problems,[],`Preset ${size}: ${problems.join()}`);
  }
  await page.getByRole('button',{name:'50 × 30 mm',exact:true}).click();
  const patient=page.locator('.label-draggable[data-label-field=patient]');
  await patient.focus();const before=await page.evaluate(()=>window.qaConfig.value.elements.patient.x);
  await patient.press('ArrowRight');assert.ok((await page.evaluate(()=>window.qaConfig.value.elements.patient.x))>before);
  await page.getByRole('button',{name:'50 × 30 mm',exact:true}).click();
  // Pointer dragging changes physical coordinates without changing the sample identifier.
  const box=await patient.boundingBox();await page.mouse.move(box.x+box.width/2,box.y+box.height/2);await page.mouse.down();await page.mouse.move(box.x+box.width/2+5,box.y+box.height/2,{steps:3});await page.mouse.up();
  assert.ok((await page.evaluate(()=>window.qaConfig.value.elements.patient.x))>2);
  await page.getByRole('button',{name:'50 × 30 mm',exact:true}).click();
  await page.getByRole('button',{name:'طباعة ملصق تجريبي',exact:true}).click();
  await page.waitForFunction(()=>!!window.__printedHtml);
  const printed=await page.evaluate(()=>window.__printedHtml);
  await writeFile(`${out}/print.html`,printed);
  const printPage=await browser.newPage();await printPage.setContent(printed);
  await printPage.evaluate(()=>{const label=document.querySelector('.barcode-label');label.parentNode.append(label.cloneNode(true));});
  assert.equal(await printPage.locator('.barcode-label').count(),2);
  assert.ok(await printPage.locator('.barcode-label').first().isVisible());
  const measured=await printPage.locator('.barcode-label').first().boundingBox();
  assert.ok(Math.abs(measured.width-50*96/25.4)<1);
  await printPage.pdf({path:`${out}/labels.pdf`,preferCSSPageSize:true,printBackground:true});
  await page.screenshot({path:`${out}/designer-desktop.png`,fullPage:true});
  // Input scanning comparison preserves exact values, including zeros.
  await page.getByLabel('اضغط هنا وامسح الملصق بالقارئ').fill('1234567890');
  assert.ok((await page.locator('[role=status]').last().textContent()).includes('مطابق تماماً'));
  // Extremely long names are reported and block printing instead of silently truncating.
  await page.getByLabel('اسم للتجربة فقط').fill('اسم طويل جداً '.repeat(8));await page.waitForTimeout(150);
  assert.equal(await page.getByRole('button',{name:'طباعة ملصق تجريبي',exact:true}).isDisabled(),true);
  await page.getByLabel('اسم للتجربة فقط').fill('أحمد محمد علي');
  await page.setViewportSize({width:390,height:844});await page.screenshot({path:`${out}/designer-mobile.png`,fullPage:true});
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));
  assert.deepEqual(errors,[]);
  console.log('PASS: preset fit, keyboard/pointer positioning, real print iframe, two physical pages, scan comparison, overflow blocking and mobile width.');
} finally { await browser?.close();server.kill(); }
