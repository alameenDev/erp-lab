import test from 'node:test';
import assert from 'node:assert/strict';
import {
  ownerAuditGroups, ownerAuditCount, ownerAuditSummary, ownerChangeLabel,
  ownerReadableValue, ownerChangeNarrative
} from '../src/utils/ownerAuditPresentation.js';

const archived = {
  event:'updated',invoice_id:74,financial_changed:true,results_changed:true,
  snapshot:{
    before:{invoice:{total:65000,paid:12000},analyses:{
      test_9_1:{name:'CBC',result:'12.5',is_done:false},
      test_group_5_1:{name:'فحوصات الدم',test_group_tests:[{name:'WBC',result:'6.2'}]},
    },payments:{'8':{amount:12000}}},
    after:{invoice:{total:70000,paid:17000},analyses:{
      test_9_1:{name:'CBC',result:'14.1',is_done:true},
      test_group_5_1:{name:'فحوصات الدم',test_group_tests:[{name:'WBC',result:'7.5'}]},
    },payments:{'8':{amount:17000}}},
    changes:[
      {field:'invoice.total',before:65000,after:70000,kind:'changed'},
      {field:'payments.8.amount',before:12000,after:17000,kind:'changed'},
      {field:'analyses.test_9_1.result',before:'12.5',after:'14.1',kind:'changed'},
      {field:'analyses.test_9_1.is_done',before:false,after:true,kind:'changed'},
      {field:'analyses.test_group_5_1.test_group_tests.0.result',before:'6.2',after:'7.5',kind:'changed'},
    ]
  }
};

test('clear summaries identify the named test and old/new clinical result values',()=>{
  const groups=ownerAuditGroups(archived);
  assert.deepEqual(groups.map(g=>[g.key,g.changes.length]),[['results',3],['financial',2]]);
  const c=groups[0].changes[0];
  assert.equal(c.label,'نتيجة تحليل CBC');
  assert.match(c.narrative,/12\.5/);
  assert.match(c.narrative,/14\.1/);
  assert.match(ownerAuditSummary(archived,true),/CBC/);
  assert.equal(ownerAuditCount(archived),5);
});
test('nested package/group tests use saved clinical names rather than indices',()=>{
  const c=ownerAuditGroups(archived)[0].changes[2];
  assert.equal(c.label,'نتيجة تحليل WBC');
  assert.doesNotMatch(c.label,/test_group_tests|\.0\./);
});
test('money, bool, null and attachments are understandable and never display credential URLs',()=>{
  assert.match(ownerReadableValue(65000,'invoice.total'),/65/);
  assert.match(ownerReadableValue(65000,'invoice.total'),/د.ع/);
  assert.equal(ownerReadableValue(false,'analyses.test_9_1.is_done'),'لا');
  assert.equal(ownerReadableValue(null,'invoice.total'),'غير مسجل');
  assert.equal(ownerReadableValue('https://example.test/secure/file?token=private','invoice.attachments.0.file'),'ملف محفوظ');
  assert.equal(ownerReadableValue(100,'invoice.attachments.0.print_width_percent'),'100%');
});
test('new, removed and deleted invoice events never claim an unrelated operation',()=>{
  assert.equal(ownerAuditSummary({event:'deleted',invoice_id:41},false),'حذف الفاتورة #41');
  assert.match(ownerAuditSummary({event:'clicked',button:'حفظ'},false),/لا يؤكد حفظ/);
  assert.match(ownerChangeNarrative({field:'invoice.notes',before:null,after:'ملاحظة جديدة'}),/إضافة/);
  assert.match(ownerChangeNarrative({field:'invoice.notes',before:'قديمة',after:null}),/إزالة/);
});
test('old records lacking verified changes are not portrayed as successful edits',()=>{
  assert.deepEqual(ownerAuditGroups({event:'action',description:'طباعة التقرير'}),[]);
  assert.match(ownerAuditSummary({event:'action',description:'طباعة التقرير'},true),/طباعة التقرير/);
});
test('legacy simple changes and encoded complex clinical results remain readable',()=>{
  const record={details:{changes:[{field:'result',label:'CBC',before:'12',after:'13'}]}};
  assert.equal(ownerAuditGroups(record)[0].changes[0].beforeText,'12');
  assert.equal(ownerReadableValue('[{"name":"WBC","result":"6.4"}]','analyses.test_group_2_1.test_group_tests'), '1 عنصر: WBC');
});
