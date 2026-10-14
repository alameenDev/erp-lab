import test from 'node:test';
import assert from 'node:assert/strict';
import { barcodeSamples, barcodeCopies, sampleLabelCount, invoiceBarcodeLabels } from '../src/utils/barcodeSamples.js';
import { labelConfig } from '../src/utils/barcodeLabels.js';
const a={id:1,name:'Complete blood count',shortcut:'CBC',category_id_fk:1,category:'Blood',sample_name:'EDTA'};
const b={id:2,name:'Glucose',shortcut:'GLU',category_id_fk:2,category:'Chemistry',sample_name:'Serum'};
const c={id:3,name:'HbA1c',category_id_fk:2,category:'Chemistry',sample:{sample_name:'EDTA'}};
const record={barcode:'0001234',patient:{name:'Patient A'},tests:[a],test_groups:[{group_name:'Blood panel',tests:[a]}],packages:[{name:'Package',tests:[b,c],test_groups:[{group_name:'Metabolic',tests:[b,c],cultures:[{id:1,name:'Culture',category:{id:3,name:'Microbiology'},sample:'Urine'}]}]}]};
test('sample picker combines categories and nested packages with independent copy counts',()=>{
 const groups=barcodeSamples(record);
 assert.deepEqual(groups.map(x=>x.sampleName),['EDTA','Serum','Urine']);
 assert.deepEqual(groups[0].analyses.map(x=>x.name),['Complete blood count','HbA1c']);
 assert.equal(groups[0].analyses[0].shortcut,'CBC');
 assert.equal(groups[1].analyses.length,1);
 assert.deepEqual(groups[1].groupNames,['Package','Metabolic']);
 assert.equal(groups[2].analyses[0].name,'Culture');
 const copies={'sample:edta':3,'sample:serum':2,'sample:urine':0};
 assert.equal(sampleLabelCount(groups,copies),5);
 const output=invoiceBarcodeLabels(record,{barcode_config:{}},groups,copies);
 assert.equal(output.length,5);
 assert.equal(output.filter(x=>x.tests==='CBC, HbA1c'&&x.sample==='EDTA').length,3);
 assert.equal(output.filter(x=>x.tests==='GLU'&&x.sample==='Serum').length,2);
 assert.ok(output.every(x=>x.barcode==='0001234'&&x.patient==='Patient A'&&!x.tests.includes('Culture')));
 assert.deepEqual(invoiceBarcodeLabels(record,{},groups,{}),[]);
});
test('missing samples and encoded snapshots remain printable with bounded whole copies',()=>{
 const groups=barcodeSamples({test_groups:JSON.stringify([{tests:JSON.stringify(JSON.stringify([{id:2,name:'Unknown'}]))}]),cultures:{one:{id:2,name:'Same id culture'}}});
 assert.equal(groups.length,1);assert.equal(groups[0].sampleName,'Other');
 assert.equal(groups[0].analyses.length,2);
 assert.deepEqual([-2,'',NaN,1.7,25,Infinity].map(barcodeCopies),[0,0,0,1,20,20]);
 assert.deepEqual(barcodeSamples({tests:'invalid'}),[]);
});
test('normalizes sample names across snapshot shapes without merging different sample types',()=>{
 const groups=barcodeSamples({tests:[a,{...b,sample_name:' edta '},{...c,sample_name:{sample_name:'EDTA'}},{id:4,name:'Urine protein',sample:'Urine'}]});
 assert.equal(groups.length,2);assert.equal(groups[0].analyses.length,3);
 assert.equal(groups[1].sampleName,'Urine');
});
test('disabled option retains existing sample printing and old settings default to no popup',()=>{
 const legacy={barcode:'0001234',tests:[a,b]};
 assert.equal(labelConfig({}).show_category_dialog,false);
 assert.equal(labelConfig({show_category_dialog:'0'}).show_category_dialog,false);
 assert.equal(labelConfig({show_category_dialog:'1'}).show_category_dialog,true);
 const config={...labelConfig({}),copies:2,show_category_dialog:true};
 assert.equal(labelConfig(config).show_category_dialog,true);
 const output=invoiceBarcodeLabels(legacy,{barcode_config:config},barcodeSamples(legacy),null);
 assert.equal(output.length,4);assert.deepEqual(output.map(x=>x.sample),['EDTA','EDTA','Serum','Serum']);
 assert.deepEqual(invoiceBarcodeLabels({}, {}, [], null),[]);
});

test('direct printing includes nested package tests and cultures by sample type',()=>{
 const output=invoiceBarcodeLabels(record,{barcode_config:{copies:2,version:2}},barcodeSamples(record),null);
 assert.equal(output.length,6);
 assert.deepEqual(output.map(x=>x.sample),['EDTA','EDTA','Serum','Serum','Urine','Urine']);
 assert.equal(output[0].tests,'CBC, HbA1c');
});
