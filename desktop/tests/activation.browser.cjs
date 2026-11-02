const {chromium}=require('../../frontend/node_modules/playwright');const assert=require('node:assert/strict');const path=require('node:path');const fs=require('node:fs/promises');const {pathToFileURL}=require('node:url');
(async()=>{
  const browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:840,height:900}});
  const out=path.join(__dirname,'../qa/activation');await fs.mkdir(out,{recursive:true});
  try{
    await page.addInitScript(()=>{
      window.testCalls=[];window.testImport='bad';
      window.activation={status:async()=>({channel:'production',allowSoftware:true}),request:async binding=>{window.testCalls.push(binding);return {saved:true};},
        import:async()=>{if(window.testImport==='bad')throw new Error('توقيع الترخيص غير صحيح.');return {channel:'production',allowSoftware:true,claims:{lab:'مختبر الاختبار',type:window.testImport,binding:'tpm',expiresAt:Date.UTC(2027,9,29)}};},
        continue:async()=>{window.testCalls.push('continue');}};
    });
    await page.goto(pathToFileURL(path.join(__dirname,'../assets/activation.html')).href);
    await page.locator('#request').click();assert.deepEqual(await page.evaluate(()=>window.testCalls),['tpm']);
    await page.locator('#software').check();await page.locator('#request').click();assert.deepEqual(await page.evaluate(()=>window.testCalls),['tpm','software']);
    await page.locator('#import').click();await page.locator('#error').waitFor();assert.match(await page.locator('#error').innerText(),/توقيع/);assert.equal(await page.locator('#continue').isVisible(),false);
    await page.evaluate(()=>window.testImport='lifetime');await page.locator('#import').click();await page.locator('#continue').waitFor();assert.match(await page.locator('#details').innerText(),/مدى الحياة/);
    await page.screenshot({path:path.join(out,'lifetime.png'),fullPage:true});await page.locator('#continue').click();assert.ok((await page.evaluate(()=>window.testCalls)).includes('continue'));
    await page.evaluate(()=>window.testImport='annual');await page.locator('#import').click();assert.match(await page.locator('#details').innerText(),/سنوي/);
    await page.setViewportSize({width:640,height:800});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:path.join(out,'annual.png'),fullPage:true});
    console.log('Activation wizard: TPM/software request, signature error, lifetime, annual, continue and layout passed.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
