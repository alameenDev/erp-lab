import test from 'node:test';
import assert from 'node:assert/strict';
import {PRINT_TYPES, readPrintSettings, savePrintSettings, printPaper, routePrintDocument, helperRequest} from '../src/utils/localPrint.js';
test('new/corrupt/disabled device settings never contact the helper', async () => {
  let calls=0;globalThis.fetch=()=>{calls++;throw new Error('must not connect');};
  for(const source of [null,'{broken','{}','{"enabled":false}','{"enabled":true,"token":"invalid"}']) {
    globalThis.localStorage={getItem:()=>source};assert.equal(await routePrintDocument({},'barcode'),false);
  }
  assert.equal(calls,0);
});
test('all types remain opt-in, exact printer names and sane copy counts survive local storage', () => {
  let saved;globalThis.localStorage={getItem:()=>saved,setItem:(_k,v)=>saved=v};globalThis.window={dispatchEvent(){}};
  savePrintSettings({enabled:true,token:'a'.repeat(64),routes:{barcode:{printer:'Label / USB #1',copies:99},report:{printer:'A4',copies:0}}});
  const s=readPrintSettings();assert.equal(s.routes.barcode.printer,'Label / USB #1');assert.equal(s.routes.barcode.copies,20);assert.equal(s.routes.report.copies,1);
  for(const [kind] of PRINT_TYPES.filter(([k])=>!['barcode','report'].includes(k))) assert.equal(s.routes[kind].printer,'');
});
test('paper follows the latest document settings: label presets, A4/A5 landscape and 58/80mm receipts', () => {
  for(const [w,h] of [[76.2,38.1],[50,30],[60,40],[40,25]]) assert.deepEqual(printPaper(`@page {size:${w}mm ${h}mm;margin:0}`,'barcode'),{width:w,height:h});
  assert.deepEqual(printPaper('@page{size:A4;margin:0} @page{size:A5 landscape;margin:15mm}'),{width:210,height:148});
  assert.deepEqual(printPaper('@page{size:A4 landscape}','register'),{width:297,height:210});
  for(const w of [58,80])assert.deepEqual(printPaper(`@page{size:auto}@page{size:${w}mm auto;margin:3mm}`,'thermal'),{width:w,height:null});
});
test('desktop uses only the built-in IPC transport and retains normal opt-in settings',async()=>{
  const calls=[];globalThis.window={digitalLabDesktop:{printRequest:async(path,options)=>{calls.push({path,options});return {printers:[]};}}};
  globalThis.fetch=()=>{throw new Error('desktop must not call the browser helper port');};
  globalThis.localStorage={getItem:()=>'{broken'};
  assert.equal(readPrintSettings().enabled,false);assert.equal(readPrintSettings().token.length,64);
  await helperRequest('/printers');assert.equal(calls.length,1);assert.equal(calls[0].path,'/printers');
  delete globalThis.window;
});
