<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import Picker from '@/views/lab-settings/ReportTemplatePicker.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useresultStatusStore } from '@/store/modules/result-status';
import { useTemplatesStore } from '@/store/modules/template';
import { $http } from '@/plugins/axios';
import { usePrint } from '@/composables/usePrint';
import { renderMedicalReportPages, createMedicalReportPdf, printMedicalReportPages } from '@/utils/medicalReportPages';

// Never call a production API from this fixture.
$http.defaults.adapter = async config => { throw new Error('Unexpected network request: ' + config.url); };
useTemplatesStore().GetTemplates = async () => {};
useresultStatusStore().resultStatus = [{value:1,label:'High'},{value:2,label:'Normal'},{value:4,label:'Low'}];
const store=useLabSettingsStore();
store.settings={report_template:'modern',show_test_names:true,show_categories:true,show_status:true,show_last_result:false,print_black_white:false,
 print_margins:{top:20,bottom:20,left:15,right:15},patient_header_config:{name_size:20,info_size:13,qr_size:90,line_height:1.7},print_table_config:{header_font_size:13,body_font_size:13,cell_padding:9,header_bg_color:'#e8f4f4',border_color:'#d5e3e7'}};
const invoices=useinvoicesStore();
const test=(id,name,result,status,from,to,extra={})=>({id,name,result,unit:'mg/dL',result_status_id_fk:status,test_reference_ranges:[{id,from,to,gender:'both',age_from:0,age_to:120,age_unit:'Years'}],...extra});
const lipid=[test(1,'Triglycerides',169,1,40,160),test(2,'Cholesterol',207,1,0,200),test(3,'HDL',36.3,2,35,80),test(4,'LDL',136.9,1,0,129),test(5,'VLDL',33.8,1,7,32)];
const mode=ref('reference');const pages=ref([]);const status=ref('Ready');const busy=ref(false);const report=ref(null);const checks=ref([]);
const record=(scenario)=>({id:1,lab_id_fk:1,patient:{name:'مريض تجريبي',age:35,age_unit:'Years',gender:'Male',code:'20260042'},registration_date:'2026-09-30T09:00:00',referral:{name:'Sample Doctor'},
 tests:scenario==='all'?[test(6,'Standalone zero',0,4,1,5)]:[],
 test_groups:[{group_name:'Lipid Profile',tests:scenario==='long'?Array.from({length:65},(_,i)=>test(i+100,'Test '+(i+1),i,2,0,100)):lipid,formula:[]}],
 cultures:scenario==='all'?[test(7,'Culture result','No growth',null,null,null,{test_reference_ranges:[]})]:[],
 packages:scenario==='all'?[{name:'Package',tests:[test(8,'Package test',12,2,0,20),test(9,'Group input',2,2,0,5,{shortcut:'A',test_group_name:'Package group',test_group_id_fk:5}),test(10,'Formula target','',null,0,6,{shortcut:'B',test_group_name:'Package group',test_group_id_fk:5})],test_groups:[{test_group_id_fk:5,group_name:'Package group',formula:[{name:'B',tokens:['A','*','2']}]}],cultures:[test(11,'Package culture','Negative',null,null,null,{test_reference_ranges:[]})],formula:[]}]:[],
 tests_last_results:[],
});
invoices.printRecord=record(mode.value);
const {printStyles}=usePrint();
const css=()=>printStyles.getResultCss()+printStyles.getPatientHeaderCss(store.settings.patient_header_config)+printStyles.getPrintTableCss(store.settings.print_table_config)+printStyles.getReportTemplateCss(store.settings)+(store.settings.print_black_white?printStyles.getBlackWhiteCss():'');
const assert=(condition,message)=>{if(!condition)throw new Error(message);checks.value.push('PASS '+message);};
async function render(){busy.value=true;status.value='Rendering';checks.value=[];try{
 invoices.printRecord=record(mode.value);report.value.beginCapture();await nextTick();
 const source=document.getElementById('Result');
 assert(source.classList.contains('report-modern')===(store.settings.report_template==='modern'),'selected template applies to the report');
 const headers=[...source.querySelector('.report-results-table thead tr').children].map(c=>c.textContent);
 assert(headers.join('|')===(store.settings.show_status?(store.settings.report_template==='modern'?'Test|Result|Flag|Unit|Reference Ranges':'Test|Result|Unit|Reference Ranges|Status'):'Test|Result|Unit|Reference Ranges'),'column order and status visibility');
 const tables=[...source.querySelectorAll('.report-results-table')];
 assert(tables.every(t=>[...t.querySelectorAll('tbody > tr')].every(tr=>tr.children.length===t.querySelector('thead tr').children.length)),'all standard, group, package, formula and culture rows align');
 if(mode.value==='all'){assert(source.textContent.includes('Standalone zero')&&source.textContent.includes('Package culture')&&source.textContent.includes('Formula target'),'all result scenarios render');const row=[...source.querySelectorAll('tr')].find(r=>r.children[0]?.textContent==='Standalone zero');assert(row.children[1].textContent==='0','zero result preserved');}
 pages.value=await renderMedicalReportPages({element:source,css:css(),margins:store.settings.print_margins});
 const pdf=await createMedicalReportPdf({pages:pages.value});assert(pdf.getNumberOfPages()===pages.value.length,'PDF uses the exact preview sheets');
 if(mode.value==='long')assert(pages.value.length>1,'long reports paginate');
 status.value='PASS';
}catch(e){status.value='FAIL '+e.message;console.error(e);}finally{report.value.endCapture();busy.value=false;}}
onMounted(render);
const print=()=>printMedicalReportPages(pages.value,window.open('about:blank','_blank'));
</script>
<template>
  <main class="fixture">
    <aside dir="rtl">
      <h1>معاينة نموذج نتيجة المريض</h1><p>بيانات تجريبية فقط</p>
      <Picker v-model="store.settings.report_template" lang="ar" />
      <label>سيناريو التقرير <select v-model="mode"><option value="reference">التصميم المرجعي</option><option value="all">كل أنواع النتائج</option><option value="long">تقرير متعدد الصفحات</option></select></label>
      <label><input type="checkbox" v-model="store.settings.show_status" /> إظهار الحالة</label>
      <label><input type="checkbox" v-model="store.settings.print_black_white" /> أبيض وأسود</label>
      <button :disabled="busy" @click="render">تحديث المعاينة</button>
      <button :disabled="busy || !pages.length" @click="print">طباعة المعاينة</button>
      <p id="status" role="status">{{ status }}</p><pre id="checks" dir="ltr">{{ checks.join('\n') }}</pre>
    </aside>
    <section id="pages"><img v-for="(page,i) in pages" :key="i" :src="page" :alt="'صفحة التقرير '+(i+1)" /></section>
    <Report ref="report" />
  </main>
</template>
<style>
body{margin:0;background:#edf1f5;font-family:Arial,sans-serif}.fixture{display:flex;gap:24px;align-items:flex-start;padding:24px}.fixture aside{width:360px;flex-shrink:0}.fixture h1{font-size:22px;margin-bottom:8px}.fixture aside>label{display:block;margin:18px 0}.fixture select{width:100%;padding:8px}.fixture aside>button{border-radius:8px;background:#0f766e;color:#fff;padding:10px;margin:8px 0 8px 8px}.fixture button:disabled{opacity:.5}.fixture pre{font-size:12px;white-space:pre-wrap;line-height:1.6}.fixture #pages{width:794px;max-width:100%;flex-shrink:0}.fixture #pages img{display:block;width:100%;margin-bottom:20px;box-shadow:0 2px 12px #0001}.fixture #status{font-weight:bold;margin:12px 0}
@media(max-width:800px){.fixture{display:block;padding:12px}.fixture aside{width:100%;margin-bottom:20px}.fixture #pages{width:100%}}
</style>
