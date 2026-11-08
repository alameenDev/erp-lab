import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const browser=await chromium.launch();
const page=await browser.newPage({viewport:{width:1100,height:1000}});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
await mkdir('test-results/report-monochrome',{recursive:true});
try {
 await page.goto('http://127.0.0.1:5173/tests/report-monochrome-browser.html');
 await page.waitForFunction(()=>Boolean(window.monochromeFixture));
 const pixels=()=>page.evaluate(async()=>{
  const sheets=[];
  for(const source of monochromeFixture.output.pages){
   const img=new Image();img.src=source;await img.decode();
   const c=document.createElement('canvas');c.width=img.width;c.height=img.height;const x=c.getContext('2d');x.drawImage(img,0,0);
   const d=x.getImageData(0,0,c.width,c.height).data;
   let colored=0,contentColored=0,black=0,titleGray=0;
   for(let i=0;i<d.length;i+=4){
    const px=(i/4)%c.width,py=Math.floor(i/4/c.width);
    if(d[i]!==d[i+1]||d[i]!==d[i+2]){
     colored++;
     // Exclude only the known letterhead strips, with a small resampling guard.
     if(px>10/794*c.width && py>65/1123*c.height && py<1078/1123*c.height)contentColored++;
    }
    if(d[i]===0&&d[i+1]===0&&d[i+2]===0)black++;
    if(d[i]===232&&d[i+1]===232&&d[i+2]===232)titleGray++;
   }
   const sample=(u,v)=>Array.from(x.getImageData(Math.floor(u*c.width),Math.floor(v*c.height),1,1).data);
   sheets.push({colored,contentColored,black,titleGray,header:sample(.5,.02),footer:sample(.5,.98),side:sample(.003,.5),width:c.width,height:c.height});
  }return sheets;
 });
 const assertLetterhead = sheets => {
  for(const sheet of sheets){
   assert.deepEqual(sheet.header,[0,124,131,255],'teal letterhead retains original RGB');
   assert.deepEqual(sheet.footer,[255,102,0,255],'orange footer retains original RGB on every sheet');
   assert.deepEqual(sheet.side,[131,71,193,255],'colored side margin is not changed by monochrome conversion');
   assert.ok(sheet.colored>1000);
  }
 };
 const assertMonoContent = sheets => {
  for(const sheet of sheets){assert.equal(sheet.contentColored,0,'results and tables remain neutral');assert.ok(sheet.black>100);}
 };
 for(const template of ['classic','modern']){
  const mono=await page.evaluate(template=>monochromeFixture.render(template,true),template);
  assert.equal(mono.cached,true);assert.equal(mono.pdf,'%PDF-');assert.ok(mono.count>0);
  assert.equal(mono.header.color,'rgb(0, 0, 0)');assert.equal(mono.header.bg,'rgb(255, 255, 255)');
  assert.equal(mono.cell.color,'rgb(0, 0, 0)');assert.equal(mono.cell.border,'rgb(184, 184, 184)');
  assert.equal(mono.cell.font,'15px');assert.equal(mono.header.font,'16px');
  assert.equal(mono.section.bg,'rgb(232, 232, 232)');assert.equal(mono.preview.bg,mono.section.bg);
  assert.match(mono.text,/D-dimer/);assert.match(mono.text,/0\.20/);assert.match(mono.text,/mg\/L/);
  let raster=await pixels();assertLetterhead(raster);assertMonoContent(raster);assert.ok(raster[0].titleGray>1000);
  await page.locator('#mono-sheets img').first().screenshot({path:`test-results/report-monochrome/${template}-colored-letterhead.png`});
  const custom=await page.evaluate(template=>monochromeFixture.render(template,true,{header_bg_color:'#ddd',header_color:'#222',border_color:'#777777'}),template);
  assert.equal(custom.header.bg,'rgb(221, 221, 221)');assert.equal(custom.header.color,'rgb(34, 34, 34)');assert.equal(custom.cell.border,'rgb(119, 119, 119)');assert.equal(custom.preview.bg,custom.section.bg);
  raster=await pixels();assertLetterhead(raster);assertMonoContent(raster);
  const color=await page.evaluate(template=>monochromeFixture.render(template,false,{header_bg_color:'#dbeafe',header_color:'#1e40af'}),template);
  assert.equal(color.header.bg,'rgb(219, 234, 254)');assert.equal(color.header.color,'rgb(30, 64, 175)');
  raster=await pixels();assertLetterhead(raster);assert.ok(raster[0].contentColored>1000,'color mode restores the table colors');
  // Both mode and background switches invalidate prepared-PDF caches.
  await page.evaluate(template=>monochromeFixture.render(template,true,{}, {withBackground:false}),template);
  raster=await pixels();assertMonoContent(raster);assert.equal(raster[0].colored,0,'no form means a neutral white page');
  assert.deepEqual(raster[0].header,[255,255,255,255]);
  await page.evaluate(template=>monochromeFixture.render(template,true),template);
  raster=await pixels();assertLetterhead(raster);assertMonoContent(raster);
  // The letterhead must remain colored on continuation pages and on the fast
  // path that captures content independently before any PDF has been prepared.
  const fast=await page.evaluate(template=>monochromeFixture.render(template,true,{}, {testCount:70,pagesOnly:true}),template);
  assert.ok(fast.count>1);assert.equal(fast.pdf,null);
  raster=await pixels();assertLetterhead(raster);assertMonoContent(raster);
  const fastPages=await page.evaluate(()=>monochromeFixture.output.pages);
  const pdf=await page.evaluate(template=>monochromeFixture.render(template,true,{}, {testCount:70}),template);
  assert.equal(pdf.pdf,'%PDF-');assert.equal(pdf.count,fast.count);
  assert.deepEqual(await page.evaluate(()=>monochromeFixture.output.pages),fastPages,'PDF and fast printing use identical colored-letterhead sheets');
  const plain=await page.evaluate(template=>monochromeFixture.render(template,true,{}, {testCount:70,withBackground:false}),template);
  assert.equal(plain.count,fast.count,'the form cannot change pagination');
  for(const sheet of await pixels()){assert.equal(sheet.colored,0);assert.ok(sheet.black>100);}
  console.log(`PASS ${template}: colored letterhead on every page; neutral results, custom grays, no-form mode, caches, PDF and fast output (${fast.count} pages)`);
 }
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
