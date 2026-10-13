import test from 'node:test';
import assert from 'node:assert/strict';
import { resultHistoryRows, reportHistoryRows, previousResultsCount, historyDisplayDate } from '../src/utils/resultHistory.js';

test('history resolves persisted test identity, never a same-name match', () => {
  const rows = [{value:'0',date:'2026-01-02'}];
  const record = {result_history:{test_11:rows,culture_11:[{value:'Negative'}]}};
  assert.equal(resultHistoryRows(record,{id:99,test_id_fk:11,name:'CBC'}),rows);
  assert.deepEqual(resultHistoryRows(record,{id:12,name:'CBC'}),[]);
  assert.deepEqual(resultHistoryRows(record,{name:'CBC'}),[]);
  assert.equal(resultHistoryRows(record,{culture_id_fk:11},'culture')[0].value,'Negative');
  assert.deepEqual(resultHistoryRows(null,{id:11}),[]);
});

const settings = {show_last_result:true, previous_results_count:3};
const scalar = (invoice, value, extra = {}) => ({invoice_id:invoice, date:`2026-10-${String(invoice).padStart(2,'0')}`, value, field:'Glucose', unit:'mg/dL', is_component:false, ...extra});

test('report visibility is independent from complete editor history and legacy names', () => {
  const rows = [scalar(1,'0'), scalar(2,'Negative'), scalar(3,'1.20'), scalar(4,'5')];
  const record = {id:9,result_history:{test_11:rows},tests_last_results:[{name:'Glucose',result:'wrong-name-match'}]};
  assert.deepEqual(reportHistoryRows(record,{id:11},'test',{}),[]);
  assert.deepEqual(reportHistoryRows(record,{id:11},'test',{...settings,show_last_result:false}),[]);
  assert.equal(resultHistoryRows(record,{id:11}),rows);
  assert.deepEqual(reportHistoryRows(record,{id:12,name:'Glucose'},'test',settings),[]);
  assert.deepEqual(reportHistoryRows(record,{id:11},'test',settings).map(r=>r.value),['5','1.20','Negative']);
  assert.equal(rows[0].value,'0', 'selection must not reorder the source');
});

test('latest examinations retain zeros, text and changed units, excluding empty/current and duplicate rows', () => {
  const rows = [scalar(2,'0'),scalar(1,'Older'),scalar(3,'Negative',{unit:'IU/L'}),scalar(4,''),scalar(5,null),scalar(6,undefined),scalar(3,'Negative',{unit:'IU/L'}),scalar(9,'current')];
  const record={id:9,result_history:{culture_11:rows}};
  assert.deepEqual(reportHistoryRows(record,{culture_id_fk:11},'culture',settings).map(r=>r.value),['Negative','0','Older']);
  assert.equal(reportHistoryRows(record,{id:11},'culture',settings)[0].unit,'IU/L');
  assert.deepEqual(reportHistoryRows(record,{id:11},'culture',{...settings,previous_results_count:1}).map(r=>r.value),['Negative']);
});

test('each selected compound examination keeps every component without duplicating scalar history', () => {
  const rows=[1,2,3,4].flatMap(i=>[scalar(i,`${i}-WBC`,{field:'WBC',is_component:true}),scalar(i,`${i}-HGB`,{field:'HGB',is_component:true})]);
  const record={result_history:{test_11:rows}};
  const selected=reportHistoryRows(record,{id:11},'test',settings,'components');
  assert.deepEqual(selected.map(r=>r.value),['4-WBC','4-HGB','3-WBC','3-HGB','2-WBC','2-HGB']);
  assert.deepEqual(reportHistoryRows(record,{id:11},'test',settings,'scalar'),[]);
  assert.equal(resultHistoryRows(record,{id:11}).length,8);
  // Filter after choosing examinations so scalar/component columns agree.
  rows.push(scalar(5,'scalar'));
  assert.equal(reportHistoryRows(record,{id:11},'test',{...settings,previous_results_count:1},'components').length,0);
});

test('counts have safe defaults and dates retain the recorded calendar day', () => {
  for(const count of [null,undefined,0,-1,11,'bad',1.5]) assert.equal(previousResultsCount({previous_results_count:count}),1);
  assert.equal(previousResultsCount({previous_results_count:'3'}),3);
  assert.equal(historyDisplayDate('2026-10-01T00:00:00+03:00'),'01/10/2026');
  assert.equal(historyDisplayDate(null),'—');
});
