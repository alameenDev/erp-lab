import test from 'node:test';
import assert from 'node:assert/strict';
import { reportMonochromePalette, getReportMonochromeCss, grayscaleReportPixels } from '../src/utils/reportMonochrome.js';

test('reference palette uses black text, gray title, white rows and visible neutral rules', () => {
  assert.deepEqual(reportMonochromePalette(), {header:'#ffffff',body:'#ffffff',section:'#e8e8e8',border:'#b8b8b8',headerText:'#000000',bodyText:'#000000',sectionText:'#000000',sectionBorder:'#404040'});
});
test('saved application defaults also use the reference palette', () => {
  assert.deepEqual(reportMonochromePalette({header_bg_color:'#f1f5f9',body_bg_color:'#ffffff',header_color:'#0f172a',body_color:'#1e293b',border_color:'#e2e8f0'}),reportMonochromePalette());
});
test('custom legible grays are preserved without modifying source settings', () => {
  const config={header_bg_color:'#ddd',body_bg_color:'#fafafa',header_color:'#222',body_color:'#333333',border_color:'#777777'};
  const before=JSON.stringify(config), p=reportMonochromePalette(config);
  assert.equal(p.header,'#dddddd');assert.equal(p.section,'#dddddd');assert.equal(p.headerText,'#222222');assert.equal(p.bodyText,'#333333');assert.equal(p.border,'#777777');assert.equal(JSON.stringify(config),before);
});
test('custom colors become neutral and faint text is corrected', () => {
  const p=reportMonochromePalette({header_bg_color:'#005a9e',header_color:'#eeeeee',body_color:'#f8f8f8',border_color:'#fff'});
  for(const value of Object.values(p)) assert.match(value,/^#([\da-f]{2})\1\1$/);
  assert.equal(p.bodyText,'#000000');assert.equal(p.border,'#c0c0c0');
  const dark=reportMonochromePalette({header_bg_color:'#000',header_color:'#000'});assert.equal(dark.headerText,'#ffffff');
});
test('malformed colors cannot inject CSS and alpha colors are composited safely', () => {
  const css=getReportMonochromeCss({print_black_white:true,print_table_config:{header_bg_color:'red;}body{display:none',border_color:'url(evil)'}});
  assert.ok(!css.includes('evil'));assert.ok(!css.includes('display:none'));
  assert.equal(reportMonochromePalette({header_bg_color:'#0000'}).header,'#ffffff');
});
test('mode is scoped, keeps typography sizes/geometry and leaves color mode untouched', () => {
  for(const mode of [false,0,'0',undefined]) assert.equal(getReportMonochromeCss({print_black_white:mode}),'');
  const css=getReportMonochromeCss({print_black_white:true});
  assert.ok(css.includes('.report-monochrome'));assert.ok(css.includes('.report-template-preview'));assert.ok(css.includes('background-color: #e8e8e8'));assert.ok(css.includes('print-color-adjust: exact'));
  assert.ok(!/font-size:|font-family:|table-layout:|padding:|width:/.test(css));
});
test('final sheet conversion preserves every gray and alpha without thresholding', () => {
  const data=new Uint8ClampedArray([0,0,0,255,232,232,232,255,255,255,255,255,255,0,0,128,0,124,131,255]);
  assert.strictEqual(grayscaleReportPixels(data),data);
  assert.deepEqual([...data],[0,0,0,255,232,232,232,255,255,255,255,255,54,54,54,128,98,98,98,255]);
  for(let n=0;n<256;n++){const p=new Uint8ClampedArray([n,n,n,255]);assert.deepEqual([...grayscaleReportPixels(p)],[n,n,n,255]);}
});

test('reset/null table settings are safe', () => {
  assert.deepEqual(reportMonochromePalette(null), reportMonochromePalette());
  assert.ok(getReportMonochromeCss({print_black_white:true,print_table_config:null}).includes('#e8e8e8'));
});
