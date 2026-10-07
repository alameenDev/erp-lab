<script setup>
import {ref,onMounted,watch,nextTick} from 'vue';
import Editor from '@/views/medical_reports/update-result.vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import {useinvoicesStore} from '@/store/modules/invoices';
import {useTemplatesStore} from '@/store/modules/template';
import {useresultStatusStore} from '@/store/modules/result-status';
import {useLabSettingsStore} from '@/store/modules/labSettings';
import {$http} from '@/plugins/axios';
import {evaluateReportFormulas} from '@/utils/reportFormulas';
const mode=new URLSearchParams(location.search).get('mode')||'group';
const input={id:11,test_id_fk:11,name:'Measured A',shortcut:'A',result:'4',is_done:true,result_type_id_fk:1,unit:'mg/dL',is_print_alone:false,test_reference_ranges:[]};
const b={...input,id:12,name:'Calculated B',shortcut:'B',result:'999',is_done:false};
const c={...input,id:13,name:'Calculated C',shortcut:'C',result:null,is_done:false};
const formulas=[{name:'B',tokens:['A','*','2']},{name:'C',tokens:['B','+','B']}];
const container={name:'Synthetic package',group_name:'Synthetic group',test_group_id_fk:21,package_id_fk:31,tests:[input,b,c],cultures:[],formula:formulas};
if(mode==='attached'){
 container.formula=[];container.test_groups=[{group_name:'Attached group',test_group_id_fk:21,formula:formulas}];
 for(const test of container.tests){test.test_group_name='Attached group';test.test_group_id_fk=21;}
}
const record={id:1,barcode:'SYNTHETIC',is_done:false,patient:{id:1,name:'مريض تجريبي',age:30,age_unit:'Years',gender:'Male'},tests:[],cultures:[],packages:mode==='group'?[]:[container],test_groups:mode==='group'?[container]:[],attachments:[],registration_date:'2026-10-07T09:00:00'};
const invoices=useinvoicesStore();invoices.GetinvoicesById=async()=>JSON.parse(JSON.stringify(record));
useTemplatesStore().GetTemplates=async()=>{};
const statuses=useresultStatusStore();statuses.resultStatus=[{value:2,label:'normal'}];statuses.GetresultStatus=async()=>{};
useLabSettingsStore().settings={show_test_names:true,show_categories:true,show_status:true,report_template:'classic'};
window.formulaFixture={saves:[],settings:useLabSettingsStore().settings,get record(){return invoices.updateResultRecord;}};
$http.defaults.adapter=async config=>{
 let data={};
 if(config.url.includes('/previous-results'))data={};
 else if(config.url.includes('/update-result')){
  const field=mode==='group'?'test_groups':'packages';
  const parents=JSON.parse(config.data.get(field));window.formulaFixture.saves.push(parents);
  data={is_done:Object.values(evaluateReportFormulas(parents[0])).every(s=>s.complete)};
 }else throw new Error('Unexpected network '+config.url);
 return {data,status:200,statusText:'OK',headers:{},config};
};
watch(()=>invoices.updateResultRecord,rec=>{invoices.printRecord=rec;},{immediate:true});
const print=ref(null);
onMounted(async()=>{await nextTick();print.value.beginCapture();});
</script>
<template><Editor/><div id="print-check"><Report ref="print"/></div></template>
