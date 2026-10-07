import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {evaluateReportFormulas, formulaDefinitions, reportFormulaValue} from '../src/utils/reportFormulas.js';
const cases=JSON.parse(await readFile(new URL('../../tests/fixtures/report-formulas.json',import.meta.url)));
for(const fixture of cases) test(fixture.name,()=>assert.deepEqual({...evaluateReportFormulas(fixture)},fixture.expected));
test('package includes formulas from attached groups and recomputes after edit',()=>{
 const pkg={tests:[{name:'A',result:4,is_done:true},{name:'B',result:999,is_done:true}],formula:[{name:'C',tokens:['B','+','1']}],test_groups:[{formula:[{name:'B',tokens:['A','*','2']}]}]};
 assert.equal(reportFormulaValue(pkg,pkg.formula[0]),9);
 pkg.tests[0].result=0;assert.equal(reportFormulaValue(pkg,pkg.formula[0]),1);
 pkg.tests[0].result='';assert.equal(reportFormulaValue(pkg,pkg.formula[0]),'');
 assert.equal(formulaDefinitions(pkg).length,2);
});
