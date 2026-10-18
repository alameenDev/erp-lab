import {chromium} from 'playwright';
import {jsPDF} from 'jspdf';
import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';

await mkdir('test-results/report-pages',{recursive:true});
const pdf=new jsPDF();
for(let i=1;i<=2;i++){
 if(i>1)pdf.addPage();
 pdf.setFillColor(i===1?15:25,i===1?118:80,110);pdf.rect(15,20,180,30,'F');
 pdf.setTextColor(255,255,255);pdf.setFontSize(18);pdf.text('ATTACHED LAB REPORT - PAGE '+i,22,38);
 pdf.setTextColor(0);pdf.setFontSize(14);pdf.text('Synthetic patient - attachment verification',20,75);
 pdf.text('Glucose: 7 mmol/L',20,95);pdf.text('This content must appear in the medical report.',20,115);
}
const pdfFile={name:'external-report.pdf',mimeType:'application/pdf',buffer:Buffer.from(pdf.output('arraybuffer'))};
const browser=await chromium.launch(),page=await browser.newPage({viewport:{width:1360,height:1000}}),errors=[];
page.on('pageerror',e=>{errors.push(e.message);console.error(e.stack);});
const fixture=()=>page.evaluate(()=>({saved:attachmentFixture.saved().attachments,uploads:attachmentFixture.uploads,calls:attachmentFixture.calls}));
const waitSaved=async count=>page.waitForFunction(count=>attachmentFixture.saved().attachments.length===count&&document.querySelectorAll('[data-report-saved-attachment]').length===count,count);
const pick=async(file,index=0)=>page.locator('[data-report-new-attachment] input[type=file]').nth(index).setInputFiles(file);
try{
 await page.goto('http://127.0.0.1:5173/tests/report-attachments-browser.html');
 await page.waitForFunction(()=>attachmentFixture.store?.updateResultRecord?.id===1);
 await page.getByRole('button',{name:/الملفات/}).click();
 const png=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=1000;c.height=1400;const x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,1000,1400);x.fillStyle='#0f766e';x.fillRect(50,50,900,180);x.fillStyle='#fff';x.font='42px Arial';x.fillText('ATTACHED SCAN',100,150);x.fillStyle='#111';x.font='32px Arial';x.fillText('Glucose: 7 mmol/L',100,330);return c.toDataURL().split(',')[1];});
 const imageFile={name:'scan.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')};
 await page.evaluate(()=>attachmentFixture.pauseNext=true);
 await pick(imageFile);
 await page.waitForFunction(()=>attachmentFixture.pending);
 await page.locator('[data-report-new-attachment] input[type=text]').fill('صورة الفحص');
 await page.locator('[data-report-add-attachment]').click();
 await pick(pdfFile,1);
 await page.evaluate(()=>{attachmentFixture.store.updateResultRecord.tests[0].result='8';attachmentFixture.release();});
 await waitSaved(2);
 await page.waitForFunction(()=>attachmentFixture.saved().tests[0].result==='8'&&attachmentFixture.saved().attachments.some(a=>a.name==='صورة الفحص'));
 assert.deepEqual((await fixture()).uploads,['scan.png','external-report.pdf'],'in-flight selection does not lose or duplicate files');
 assert.equal((await fixture()).calls.filter(url=>url==='/invoices/1').length,1,'autosave never replaces the editor via a refetch');
 await pick({name:'notes.txt',mimeType:'text/plain',buffer:Buffer.from('Synthetic document')});
 await waitSaved(3);
 await page.locator('[data-report-saved-attachment]').first().scrollIntoViewIfNeeded();
 await page.screenshot({path:'test-results/report-pages/attachments-editor-desktop.png'});
 let output=await page.evaluate(()=>attachmentFixture.prepare(false));
 assert.equal(output.signature,'%PDF-');assert.equal(output.count,5,'result + scan + two PDF pages + generic file');
 assert.deepEqual(await page.locator('#attachment-output [data-attachment-name]').allTextContents(),['صورة الفحص','external-report.pdf','external-report.pdf','notes.txt']);
 assert.equal(await page.locator('#attachment-output [data-attachment-name]').evaluateAll(nodes=>nodes.every(n=>getComputedStyle(n).textAlign==='center')),true);
 output=await page.evaluate(()=>attachmentFixture.prepare(true));assert.equal(output.count,5);
 const exported=await page.evaluate(async()=>({pages:attachmentFixture.output.pages,pdf:Array.from(new Uint8Array(await attachmentFixture.output.blob.arrayBuffer()))}));
 for(let i=0;i<exported.pages.length;i++)await writeFile(`test-results/report-pages/attachments-page-${i+1}.png`,Buffer.from(exported.pages[i].split(',')[1],'base64'));
 await writeFile('test-results/report-pages/attachments-combined.pdf',Buffer.from(exported.pdf));
 const rejected=await page.evaluate(async()=>{const results=[];for(const file of ['javascript:alert(1)',location.origin+'/synthetic-attachments/missing.pdf']){try{await attachmentFixture.prepareReportAttachments([{name:'Missing',file,mime_type:'application/pdf'}]);results.push(false);}catch{results.push(true);}}return results;});
 assert.deepEqual(rejected,[true,true],'invalid/missing attachments cannot silently disappear from print');
 const locked=new jsPDF({encryption:{userPassword:'synthetic-password',ownerPassword:'synthetic-owner',userPermissions:['print']}});locked.text('Protected synthetic report',20,20);
 const protectedMessage=await page.evaluate(async bytes=>{attachmentFixture.files.set('/synthetic-attachments/protected.pdf',new Blob([new Uint8Array(bytes)],{type:'application/pdf'}));try{await attachmentFixture.prepareReportAttachments([{name:'Protected',file:location.origin+'/synthetic-attachments/protected.pdf',mime_type:'application/pdf'}]);return '';}catch(error){return error.message;}},Array.from(new Uint8Array(locked.output('arraybuffer'))));
 assert.match(protectedMessage,/كلمة مرور/,'protected PDF reports an actionable error without hanging');
 await page.setViewportSize({width:390,height:844});
 await page.locator('[data-report-saved-attachment]').first().scrollIntoViewIfNeeded();
 await page.screenshot({path:'test-results/report-pages/attachments-editor-mobile.png',fullPage:true});
 for(let i=0;i<3;i++)await page.locator('[data-report-saved-attachment] button').first().click();
 await waitSaved(0);await page.waitForFunction(()=>attachmentFixture.saved().attachments.length===0);
 await page.evaluate(()=>attachmentFixture.failNext=true);
 await pick(imageFile);
 await page.waitForFunction(()=>document.body.textContent.includes('تعذر الحفظ')||document.body.textContent.includes('فشل الحفظ'));
 assert.equal(await page.evaluate(()=>attachmentFixture.store.updateResultRecord.attachments.filter(a=>a.file instanceof File).length),1,'failed save retains selected File');
 await page.locator('[data-report-new-attachment] input[type=text]').fill('إعادة المحاولة');
 await waitSaved(1);
 assert.deepEqual(errors,[]);
 console.log('PASS attachments: autosave upload, in-flight additions/rename/results, no duplicates, deletion, failed-upload retry, centered names, scan/two-page PDF/generic QR, shared form and mobile');
}catch(error){await page.screenshot({path:'test-results/report-pages/attachments-failure.png',fullPage:true}).catch(()=>{});throw error;}
finally{await browser.close();}
