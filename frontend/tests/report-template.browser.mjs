// CI-only regression of the actual report component using synthetic data.
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const out='test-results/report-pages';
await mkdir(out,{recursive:true});
const browser=await chromium.launch();
const context=await browser.newContext({viewport:{width:1280,height:1000}});
const page=await context.newPage();
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
const ready=async()=>{
  await page.waitForFunction(()=>/^(PASS|FAIL)/.test(document.getElementById('status')?.textContent||''),null,{timeout:120000});
  assert.equal(await page.locator('#status').innerText(),'PASS',await page.locator('#checks').innerText());
};
const render=async()=>{await page.getByRole('button',{name:'تحديث المعاينة',exact:true}).click();await ready();};
const save=async(name)=>{
  await page.screenshot({path:`${out}/${name}-preview.png`,fullPage:true});
  const src=await page.locator('#pages img').first().getAttribute('src');
  await writeFile(`${out}/${name}-sheet.png`,Buffer.from(src.split(',')[1],'base64'));
};
try{
  await page.goto('http://127.0.0.1:5173/tests/report-template-browser.html');await ready();await save('modern');
  await page.getByRole('radio',{name:/النموذج القديم/}).check();await render();await save('classic');
  await page.getByRole('radio',{name:/النموذج الجديد/}).check();
  await page.getByRole('combobox').selectOption('all');await render();await save('all-results');
  await page.getByRole('checkbox',{name:'إظهار الحالة',exact:true}).uncheck();await render();
  await page.getByRole('checkbox',{name:'إظهار الحالة',exact:true}).check();
  await page.getByRole('checkbox',{name:'أبيض وأسود',exact:true}).check();await render();await save('monochrome');
  await page.getByRole('checkbox',{name:'أبيض وأسود',exact:true}).uncheck();
  await page.getByRole('combobox').selectOption('long');await render();await save('multi-page');
  assert.ok(await page.locator('#pages img').count()>1);
  assert.deepEqual(errors,[],'no browser or rendering errors');
  console.log('PASS template switching, flag visibility, all result types, zero values, monochrome, pagination and PDF parity');
}finally{await browser.close();}
