// Runs the real Electron renderer and PDF pipeline, without contacting any
// physical printer. The Windows workflow also executes this after packaging.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const {randomUUID} = require('node:crypto');
const {loadDocument} = require('./printing.cjs');
async function run() {
  const out = process.env.DLP_SMOKE_OUTPUT || fs.mkdtempSync(path.join(os.tmpdir(),'digital-lab-print-qa-'));
  fs.mkdirSync(out,{recursive:true});
  const jobs = process.env.DLP_SMOKE_FIXTURES ? JSON.parse(fs.readFileSync(process.env.DLP_SMOKE_FIXTURES,'utf8')) : [{id:randomUUID(),kind:'invoice',paper:{width:210,height:297},html:'<!doctype html><html><head><style>@page{size:A4;margin:12mm}body{font-family:Arial}</style></head><body><h1>Digital Lab Print</h1><p>اختبار تشغيل المساعد</p><script>window.unsafeScript=true</script></body></html>'}];
  const manifest=[];
  for(const job of jobs) {
    const loaded=await loadDocument({...job,id:randomUUID()});
    try {
      assert.equal(await loaded.win.webContents.executeJavaScript('typeof require'), 'undefined');
      assert.equal(await loaded.win.webContents.executeJavaScript('window.unsafeScript === true'), false);
      const bytes=await loaded.win.webContents.printToPDF({printBackground:true,preferCSSPageSize:true,
        pageSize:{width:loaded.paper.width/25.4,height:loaded.paper.height/25.4},margins:{top:0,right:0,bottom:0,left:0}});
      assert.equal(bytes.subarray(0,4).toString(),'%PDF');
      fs.writeFileSync(path.join(out,job.kind+'.pdf'),bytes);
      manifest.push({kind:job.kind,paper:loaded.paper});
    } finally {loaded.close();}
  }
  fs.writeFileSync(path.join(out,'manifest.json'),JSON.stringify(manifest));
  console.log('PASS: real Electron sandbox, Arabic HTML, media sizes and PDF rendering; no physical print submitted.');
}
module.exports={run};
