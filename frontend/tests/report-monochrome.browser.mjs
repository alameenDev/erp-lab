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
  let colored=0,black=0,titleGray=0;
  for(const source of monochromeFixture.output.pages){
   const img=new Image();img.src=source;await img.decode();
   const c=document.createElement('canvas');c.width=img.width;c.height=img.height;const x=c.getContext('2d');x.drawImage(img,0,0);
   const d=x.getImageData(0,0,c.width,c.height).data;
   for(let i=0;i<d.length;i+=4){if(d[i]!==d[i+1]||d[i]!==d[i+2])colored++;if(d[i]===0&&d[i+1]===0&&d[i+2]===0)black++;if(d[i]===232&&d[i+1]===232&&d[i+2]===232)titleGray++;}
  }return {colored,black,titleGray};
 });
 for(const template of ['classic','modern']){
  const mono=await page.evaluate(template=>monochromeFixture.render(template,true),template);
  assert.equal(mono.cached,true);assert.equal(mono.pdf,'%PDF-');assert.ok(mono.count>0);
  assert.equal(mono.header.color,'rgb(0, 0, 0)');assert.equal(mono.header.bg,'rgb(255, 255, 255)');
  assert.equal(mono.cell.color,'rgb(0, 0, 0)');assert.equal(mono.cell.border,'rgb(184, 184, 184)');
  assert.equal(mono.cell.font,'15px');assert.equal(mono.header.font,'16px');
  assert.equal(mono.section.bg,'rgb(232, 232, 232)');assert.equal(mono.preview.bg,mono.section.bg);
  assert.match(mono.text,/D-dimer/);assert.match(mono.text,/0\.20/);assert.match(mono.text,/mg\/L/);
  const raster=await pixels();assert.equal(raster.colored,0,'all final sheet pixels, including letterhead, must be neutral');assert.ok(raster.black>100);assert.ok(raster.titleGray>1000);
  await page.locator('#mono-sheets img').first().screenshot({path:`test-results/report-monochrome/${template}-default.png`});
  const custom=await page.evaluate(template=>monochromeFixture.render(template,true,{header_bg_color:'#ddd',header_color:'#222',border_color:'#777777'}),template);
  assert.equal(custom.header.bg,'rgb(221, 221, 221)');assert.equal(custom.header.color,'rgb(34, 34, 34)');assert.equal(custom.cell.border,'rgb(119, 119, 119)');assert.equal(custom.preview.bg,custom.section.bg);assert.equal((await pixels()).colored,0);
  const color=await page.evaluate(template=>monochromeFixture.render(template,false,{header_bg_color:'#dbeafe',header_color:'#1e40af'}),template);
  assert.equal(color.header.bg,'rgb(219, 234, 254)');assert.equal(color.header.color,'rgb(30, 64, 175)');assert.ok((await pixels()).colored>1000,'disabling B&W restores colored settings and letterhead');
  // Turn back on: no stale colored page may survive the prepared-PDF cache.
  await page.evaluate(template=>monochromeFixture.render(template,true),template);assert.equal((await pixels()).colored,0);
 }
 assert.deepEqual(errors,[]);
 console.log('PASS classic/modern: reference palette, custom grays, original colors restored, source/settings preview, real report/PDF, all raster pixels neutral and cache invalidation');
}finally{await browser.close();}
