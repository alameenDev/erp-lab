import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { scopedStorage } from '../src/demo/storage.js';
import { lessons, answerQuestion } from '../src/demo/guide.js';
function memoryStorage() { const m=new Map(); return {get length(){return m.size},key:i=>[...m.keys()][i]??null,getItem:k=>m.get(k)??null,setItem:(k,v)=>m.set(k,String(v)),removeItem:k=>m.delete(k)}; }
test('demo logout/reset preserves live auth, language and paired printers',()=>{
 const native=memoryStorage(); for(const key of ['token','user','locale','digital-lab-print-v1']) native.setItem(key,'LIVE');
 const demo=scopedStorage(native,'demo:'); assert.equal(demo.getItem('token'),null);
 demo.setItem('token','DEMO'); demo.setItem('locale','ar'); assert.equal(demo.length,2);
 assert.equal(demo.key(0),'token'); demo.clear(); assert.equal(demo.length,0);
 for(const key of ['token','user','locale','digital-lab-print-v1']) assert.equal(native.getItem(key),'LIVE');
});
test('Arabic spelling variants retrieve actual features and unknown questions do not invent answers',()=>{
 for(const question of ['شلون أضيف تعليق للنتيجة؟','اريد تعليق للنتيجه']) assert.equal(answerQuestion(question).matches[0].id,'results');
 assert.equal(answerQuestion('اشرح الباركود').matches[0].id,'barcode');
 assert.equal(answerQuestion('الطقس غدا').matches.length,0);
 assert.equal(answerQuestion('<script>alert(1)</script>').matches.length,0);
});
test('curriculum links resolve to existing screens and save steps wait for actual requests',()=>{
 assert.equal(new Set(lessons.map(l=>l.id)).size,lessons.length);
 const router=fs.readFileSync(new URL('../src/router/index.js',import.meta.url),'utf8');
 for(const l of lessons){assert.ok(router.includes(l.route),l.route);assert.ok(l.steps.length>=2);}
 for(const id of ['patients','invoices','results','tests','groups','packages','settings']) {
   const end=lessons.find(l=>l.id===id).steps.at(-1); assert.ok(end.request);assert.equal(end.read,undefined);
 }
});
