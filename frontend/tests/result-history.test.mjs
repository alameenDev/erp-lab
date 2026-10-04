import test from 'node:test';
import assert from 'node:assert/strict';
import { resultHistoryRows } from '../src/utils/resultHistory.js';

test('history resolves persisted test identity, never a same-name match', () => {
  const rows = [{value:'0',date:'2026-01-02'}];
  const record = {result_history:{test_11:rows,culture_11:[{value:'Negative'}]}};
  assert.equal(resultHistoryRows(record,{id:99,test_id_fk:11,name:'CBC'}),rows);
  assert.deepEqual(resultHistoryRows(record,{id:12,name:'CBC'}),[]);
  assert.deepEqual(resultHistoryRows(record,{name:'CBC'}),[]);
  assert.equal(resultHistoryRows(record,{culture_id_fk:11},'culture')[0].value,'Negative');
  assert.deepEqual(resultHistoryRows(null,{id:11}),[]);
});
