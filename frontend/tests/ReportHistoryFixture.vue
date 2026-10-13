<script setup>
import { ref, nextTick } from 'vue';
import Settings from '@/views/lab-settings/index.vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useresultStatusStore } from '@/store/modules/result-status';
import { useTemplatesStore } from '@/store/modules/template';
import { resultHistoryRows } from '@/utils/resultHistory';
import { $http } from '@/plugins/axios';
const store = useLabSettingsStore(), invoices = useinvoicesStore();
const report = ref(null), status = ref('Ready'), checks = ref([]), pages = ref([]), saves = ref(0);
const check = (ok, text) => { if (!ok) throw new Error(text); checks.value.push('PASS ' + text); };
let saved = JSON.parse(sessionStorage.getItem('report-history-fixture') || 'null') || { report_template:'classic', show_status:true, show_test_names:true, show_categories:true, show_last_result:false, previous_results_count:1, lab_display_name:'مختبر تجريبي' };
$http.defaults.adapter = async config => {
  if (config.url === '/portal/generate') return { data:{url:location.origin+'/portal/'+'a'.repeat(48)},status:200,statusText:'OK',headers:{},config };
  if (config.url !== '/lab-settings') throw new Error('Unexpected API: '+config.url);
  if (config.method === 'post') {
    if (!(config.data instanceof FormData)) throw new Error('Expected multipart settings');
    const count = Number(config.data.get('previous_results_count'));
    if (!(count >= 1 && count <= 10)) throw new Error('Invalid/missing saved count');
    saved = {...store.settings, previous_results_count:count, show_last_result:config.data.get('show_last_result') === '1'};
    sessionStorage.setItem('report-history-fixture',JSON.stringify(saved)); saves.value++;
  }
  return {data:config.method==='get'?JSON.parse(JSON.stringify(saved)):{setting:JSON.parse(JSON.stringify(saved))},status:200,statusText:'OK',headers:{},config};
};
useTemplatesStore().GetTemplates = async () => {};
useresultStatusStore().resultStatus = [{value:2,label:'Normal'}];
const test = (id, name, extra={}) => ({id,name,result:'Current',unit:'IU/mL',result_status_id_fk:2,test_reference_ranges:[],...extra});
const scalarIds = ['test_101','test_102','test_103','test_104','test_105','test_106','test_107','culture_201','culture_202','culture_203'];
const history = Object.fromEntries(scalarIds.map(key=>[key, [1,2,3,4,5].map(i=>({invoice_id:100-i, date:`2026-09-${String(30-i).padStart(2,'0')}`, field:key,value:`H-${key}-${i}`,unit:'IU/L',is_component:false}))]));
for (const id of [108,109]) history[`test_${id}`] = [1,2,3,4,5].flatMap(i=>['WBC','HGB'].map(field=>({invoice_id:100-i,date:`2026-09-${String(30-i).padStart(2,'0')}`,field,value:`H-${id}-${field}-${i}`,unit:'10*3/uL',is_component:true})));
invoices.printRecord = {id:1000,patient:{id:1,name:'مريض تجريبي',age:35,age_unit:'Years',gender:'Male',code:'SYNTHETIC'},registration_date:'2026-10-01T09:00:00',
  tests:[test(101,'Same name'),test(102,'Same name'),test(108,'Compound test'),test(109,'Template CBC',{sub_tests:[{name:'WBC',value:'7'}],content:{html:'<h3>Template CBC</h3><p>Current WBC</p>'}})],
  test_groups:[{group_name:'Virology',tests:[test(103,'Group analysis')],cultures:[test(202,'Group culture')]}],
  cultures:[test(201,'Standalone culture')],
  packages:[{name:'Package',tests:[test(104,'Package analysis'),test(105,'Package group input',{result:'4',shortcut:'X',test_group_name:'Package group',test_group_id_fk:5}),test(106,'Group formula',{shortcut:'Y',test_group_name:'Package group',test_group_id_fk:5}),test(107,'Package formula',{shortcut:'Z'})],test_groups:[{test_group_id_fk:5,group_name:'Package group',formula:[{name:'Y',tokens:['X','*','2']}]}],cultures:[test(203,'Package culture')],formula:[{name:'Z',tokens:['X','*','3']}]}],
  result_history:history,tests_last_results:[{name:'Same name',result:'LEGACY-MUST-NOT-PRINT',result_date:'2026-01-01'}]};
async function verify() {
  status.value='Rendering';checks.value=[];
  try {
    report.value.beginCapture(); await nextTick(); await document.fonts.ready;
    const source=document.getElementById('Result'), enabled=store.settings.show_last_result, count=store.settings.previous_results_count;
    check(!source.textContent.includes('LEGACY-MUST-NOT-PRINT'),'legacy duplicate footer is absent');
    check(resultHistoryRows(invoices.printRecord,{id:101}).length===5,'complete editor history is unchanged');
    for (const key of scalarIds) {
      for (let i=1;i<=5;i++) {
        const matches=[...source.querySelectorAll('.report-previous-result')].filter(el=>el.textContent.includes(`H-${key}-${i}`));
        check(matches.length === (enabled && i<=count?1:0),`${key}: result ${i} follows count and is not duplicated`);
        if(matches.length) check(matches[0].querySelector('time')?.textContent === `${30-i}/09/2026`,`${key}: date is displayed`);
      }
    }
    for(const id of [108,109]) for(const field of ['WBC','HGB']) for(let i=1;i<=5;i++) {
      check(source.textContent.includes(`H-${id}-${field}-${i}`)===(enabled && i<=count),`${id} ${field}: compound/template history ${i} follows selection`);
    }
    if(!enabled) check(source.querySelectorAll('.report-previous-results,.result-history').length===0 && !source.textContent.includes('Previous Results'),'hiding history removes every previous-results section');
    const output=await report.value.prepareReport({withBackground:false});
    check(output.pages.length>0 && output.blob.type==='application/pdf','canonical PDF and print sheets are generated');
    check(await report.value.prepareReport({withBackground:false})===output,'print, save and WhatsApp share the same prepared output');
    pages.value=output.pages;status.value='PASS';
  } catch(error) {status.value='FAIL '+error.message;console.error(error);} finally {report.value.endCapture();}
}
</script>
<template>
  <main dir="rtl" class="p-6"><Settings :referral-mode="true" /><section class="my-6 rounded-xl border bg-white p-5"><button class="rounded-lg bg-teal-700 p-3 text-white" @click="verify">فحص سجل التقرير</button><span id="save-count">{{ saves }}</span><p id="status">{{ status }}</p><details><summary>تفاصيل التحقق</summary><pre id="checks" dir="ltr">{{ checks.join('\n') }}</pre></details><div id="pages" class="flex flex-wrap gap-4"><img v-for="(page,i) in pages" :key="i" :src="page" class="w-[400px] max-w-full border" :alt="'صفحة تجريبية '+(i+1)"></div></section><Report ref="report" /></main>
</template>
<style>body{margin:0;background:#f1f5f9}#checks{white-space:pre-wrap;font-size:12px}</style>
