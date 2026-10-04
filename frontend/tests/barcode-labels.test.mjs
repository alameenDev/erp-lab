import test from 'node:test';
import assert from 'node:assert/strict';
import { labelConfig, defaultLabel, barcodeGeometry, labelIssues, elementBox, barcodePrintCss, sampleGroups, labelData } from '../src/utils/barcodeLabels.js';
const data = { barcode:'0012345678', number:'0012345678', patient:'Patient', age:'33', gender:'Female', date:'04/10/2026 14:30', sample:'EDTA', tests:'CBC' };
test('legacy inch settings migrate without losing visibility or label size', () => {
  const c = labelConfig({label_width:2,label_height:1,name_size:8}, false);
  assert.equal(c.width_mm,50.8); assert.equal(c.height_mm,25.4); assert.equal(c.elements.patient.font,8); assert.equal(c.elements.tests.visible,false);
  assert.deepEqual(labelConfig(c),c);
  const large=labelConfig({label_width:10,label_height:10}); assert.deepEqual(labelConfig(large),large);
  const short=labelConfig({label_height:0.5}); assert.equal(labelConfig(short).height_mm,12.7);
});
test('presets fit populated fields, preserve leading zeroes and quantize to printer dots', () => {
  for(const [w,h] of [[76.2,38.1],[50,30],[60,40],[40,25]]) for(const dpi of [203,300,600]) {
    const c = {...defaultLabel(w,h),dpi};
    assert.deepEqual(labelIssues(c,data),[]);
    const g = barcodeGeometry(data.barcode,c);
    assert.equal(g.error,undefined); assert.ok(Number.isInteger(g.dots) && g.dots >= 2);
    assert.ok(g.width <= c.elements.barcode.width);
    assert.equal(g.bars[0].x,c.quiet_modules);
    assert.equal(g.modules - g.bars.at(-1).x - g.bars.at(-1).width,c.quiet_modules);
    assert.notDeepEqual(g.bars,barcodeGeometry('12345678',c).bars);
  }
});
test('narrow or invalid codes are blocked instead of truncated, rewritten or stretched', () => {
  const c=defaultLabel(); c.elements.barcode.width=10;
  assert.equal(barcodeGeometry(data.barcode,c).error,'narrow');
  c.format='CODE39'; c.elements.barcode.width=140;
  assert.equal(barcodeGeometry('abc123',c).error,'invalid');
  assert.equal(barcodeGeometry('ABC123',c).error,undefined);
  assert.equal(barcodeGeometry('',c).error,'invalid');
});
test('rotation, offsets, overlaps and hidden fields are included in preflight', () => {
  const c=defaultLabel(); c.elements.barcode.rotation=90;
  assert.equal(elementBox(c.elements.barcode,c).height,c.elements.barcode.width);
  assert.ok(labelIssues(c,data).some(i=>i.code==='outside'));
  c.elements.barcode.rotation=0; c.elements.patient.y=c.elements.barcode.y;
  assert.ok(labelIssues(c,data).some(i=>i.code==='overlap'));
  c.elements.patient.visible=false;
  assert.ok(!labelIssues(c,data).some(i=>i.key==='patient'||i.other==='patient'));
  c.offset_x=-10; assert.ok(labelIssues(c,data).some(i=>i.code==='outside'));
});
test('each sample includes nested groups and packages and preserves its invoice identifier', () => {
  const record={barcode:'00001234',patient:{name:'Patient',age:0},tests:[{sample_name:'EDTA',shortcut:'CBC'}],packages:[{name:'Panel',tests:[{sample_name:'Serum',name:'ALT'}]}],test_groups:[{group_name:'Blood',tests:[{sample_name:'EDTA',shortcut:'CBC'}]}]};
  const groups=sampleGroups(record); assert.equal(groups.length,2); assert.deepEqual(groups[0].tests,['CBC']);
  assert.deepEqual(groups[0].groupNames,['Blood']);
  const label=labelData(record,groups[0],'Lab','Date');
  assert.equal(label.barcode,'00001234'); assert.equal(label.age,'0'); assert.equal(label.laboratory,'Lab');
  assert.equal(sampleGroups({}).length,1);
});
test('print uses actual millimetres and one page per label without forced SVG scaling', () => {
  const css=barcodePrintCss(defaultLabel(50,30));
  assert.match(css,/size:50mm 30mm/); assert.match(css,/break-before:page/);
  assert.doesNotMatch(css,/svg[^}]*width:\s*100%/);
});
