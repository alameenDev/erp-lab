import test from 'node:test';
import assert from 'node:assert/strict';
import {portalReportCounts, filterPortalReports} from '../src/utils/patientPortalReports.js';
const reports=[{id:1,status:'pending',tests:[{id:'a',status:'ready'},{id:'b',status:'pending'}]},{id:2,status:'ready',tests:[{id:'c',status:'ready'}]}];
test('filters and counts individual analyses independently of the invoice status',()=>{
 assert.deepEqual(portalReportCounts(reports),{all:3,ready:2,pending:1});
 assert.deepEqual(filterPortalReports(reports,'all').flatMap(r=>r.visibleTests.map(t=>t.id)),['a','b','c']);
 assert.deepEqual(filterPortalReports(reports,'ready').flatMap(r=>r.visibleTests.map(t=>t.id)),['a','c']);
 assert.deepEqual(filterPortalReports(reports,'pending').flatMap(r=>r.visibleTests.map(t=>t.id)),['b']);
 assert.equal(reports[0].tests.length,2);assert.deepEqual(portalReportCounts([]),{all:0,ready:0,pending:0});
});
