import test from 'node:test';
import assert from 'node:assert/strict';
import { commentText, commentOptions, appendComment, commentHtml, reportCommentsVisible, templateExtraComments } from '../src/utils/resultComments.js';
import { selectReportItems } from '../src/utils/reportItemSelection.js';

test('legacy blank and array comments normalize without printing null or preset objects', () => {
  for (const value of [null, undefined, '', '  ', 'null', {}]) assert.equal(commentText(value), '');
  assert.equal(commentText('["تعليق أول", "Second line"]'), 'تعليق أول\nSecond line');
  assert.deepEqual(commentOptions([' A ', '', null, 'A', 'B']), ['A', 'B']);
  assert.deepEqual(commentOptions('["A", "A"]'), ['A']);
});
test('choosing presets preserves custom text and does not duplicate repeated choices', () => {
  assert.equal(appendComment('Custom', 'Ready\nSecond'), 'Custom\nReady\nSecond');
  assert.equal(appendComment('Custom\nReady\nSecond', 'Ready\nSecond'), 'Custom\nReady\nSecond');
  assert.equal(appendComment('', ''), '');
});
test('visibility accepts saved boolean and multipart representations with visible legacy default', () => {
  assert.equal(reportCommentsVisible({}), true);
  for (const value of [false, 0, '0', 'false']) assert.equal(reportCommentsVisible({print_table_config:{show_comments:value}}), false);
  for (const value of [undefined, true, '1', 1]) assert.equal(reportCommentsVisible({print_table_config:{show_comments:value}}), true);
});
test('custom template slots escape markup and dollar substitutions; fallback avoids duplicate comments', () => {
  assert.equal(commentHtml('<img onerror="bad">\n$&'), '&lt;img onerror=&quot;bad&quot;&gt;<br>$&amp;');
  const test = {name:'CBC',comment:'Top',content:{html:'{{comment}} {{sub_test.White_cells.comment}}'},sub_tests:[{name:'White cells',comment:'Placed'},{name:'RBC',comment:'Extra'}]};
  assert.deepEqual(templateExtraComments(test), [{label:'RBC',text:'Extra'}]);
  test.content.html='';
  assert.deepEqual(templateExtraComments(test).map(c=>c.text), ['Top','Placed','Extra']);
});
test('partial selection carries only selected comments and omits general comments for incomplete sections', () => {
  const record = { tests:[{name:'A',comment:'A note'},{name:'B',comment:'B note'}],tests_comment:'A and B',packages:[{name:'P',comment:'Package note',tests:[{name:'C',comment:'C note'},{name:'D',comment:'D note'}]}], package_comments:'All package tests' };
  const result=selectReportItems(record,{selectedKeys:['tests:0','packages:0/tests:1']});
  assert.equal(result.tests[0].comment,'A note');
  assert.equal(result.packages[0].tests[0].comment,'D note');
  assert.equal(result.packages[0].comment,null);
  assert.equal(result.tests_comment,null); assert.equal(result.package_comments,null);
  assert.equal(record.packages[0].comment,'Package note');
});
