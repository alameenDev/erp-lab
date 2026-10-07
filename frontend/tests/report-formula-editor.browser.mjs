import {chromium} from 'playwright';import assert from 'node:assert/strict';import {mkdir} from 'node:fs/promises';
await mkdir('test-results/report-pages',{recursive:true});
const browser=await chromium.launch();const errors=[];
try{
 for(const mode of ['group','package','attached']){
  const page=await browser.newPage({viewport:{width:1360,height:1000}});page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:5173/tests/report-formula-editor-browser.html?mode='+mode);
  const b=page.locator('[data-formula-name="B"]'),c=page.locator('[data-formula-name="C"]');
  await b.waitFor();
  const number=loc=>loc.locator('span').last().textContent().then(s=>s.trim());
  assert.equal(await number(b),'8');assert.equal(await number(c),'16');
  const printValue=async name=>page.locator('#print-check #Result table tbody tr').evaluateAll((rows,label)=>{
   const row=rows.find(r=>r.children[0]?.textContent.trim()===label);return row?.children[1]?.textContent.trim();
  },name);
  assert.equal(await printValue('Calculated B'),'8');assert.equal(await printValue('Calculated C'),'16');
  for(const template of ['modern','classic']) {
   await page.evaluate(template=>{formulaFixture.settings.report_template=template;formulaFixture.settings.show_test_names=false;},template);
   await page.waitForTimeout(50);
   assert.equal(await printValue('Calculated B'),'8','merged '+template);assert.equal(await printValue('Calculated C'),'16');
  }
  await page.evaluate(()=>{formulaFixture.settings.show_test_names=true;});
  await page.waitForTimeout(1200);assert.equal(await page.evaluate(()=>formulaFixture.saves.length),0,'opening a report must not save or approve it');
  const input=page.locator(mode==='group'?'[data-tg-row]':'input[data-pkg-row]').first();
  await input.fill('0');assert.equal(await number(b),'0');assert.equal(await number(c),'0');
  assert.equal(await printValue('Calculated C'),'0');
  await page.waitForFunction(()=>formulaFixture.saves.length>0&&formulaFixture.record.is_done===true);
  await page.screenshot({path:'test-results/report-pages/formula-editor-'+mode+'.png',fullPage:true});
  const sourceRow=page.locator(mode==='group'?'[data-tg-test-row]':'[data-pkg-test-row]').first();
  await sourceRow.locator('input[type=checkbox]').uncheck();
  await b.getByText('بانتظار اعتماد التحاليل المرتبطة',{exact:true}).waitFor();
  await input.fill('');await b.getByText('بانتظار إدخال نتائج التحاليل المرتبطة',{exact:true}).waitFor();assert.equal(await number(b),'—');
  assert.equal(await printValue('Calculated B'),'');
  await page.close();console.log('PASS formula values on load, editor/print parity, reactive zero, approval and missing input: '+mode);
 }
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
