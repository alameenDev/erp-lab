<script setup>
import { ref, nextTick, onMounted } from 'vue';
import Settings from '@/views/lab-settings/index.vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useTemplatesStore } from '@/store/modules/template';
import { $http } from '@/plugins/axios';
import { applyCbcHistoryFixture, cbcTest, cbcHistory } from './report-cbc-history-data.mjs';
const store = useLabSettingsStore(), invoices = useinvoicesStore(), report = ref(null), pages = ref([]), saves = ref(0);
const copy = x => JSON.parse(JSON.stringify(x));
let saved = JSON.parse(sessionStorage.getItem('cbc-history-settings') || 'null') || { report_template: 'classic', show_status: true, show_test_names: true, show_last_result: false, previous_results_count: 1, lab_display_name: 'مختبر تجريبي' };
$http.defaults.adapter = async config => {
  if (config.url === '/portal/generate') return { data: { url: location.origin + '/portal/' + 'a'.repeat(48) }, status: 200, statusText: 'OK', headers: {}, config };
  if (config.url !== '/lab-settings') throw new Error('Unexpected API: ' + config.url);
  if (config.method === 'post') {
    const layout = config.data.get('print_table_config[cbc_previous_results_layout]');
    if (!['inline', 'separate_page'].includes(layout)) throw new Error('Missing layout in saved settings');
    saved = { ...copy(store.settings), previous_results_count: Number(config.data.get('previous_results_count')), show_last_result: config.data.get('show_last_result') === '1', print_table_config: { ...store.settings.print_table_config, cbc_previous_results_layout: layout } };
    sessionStorage.setItem('cbc-history-settings', JSON.stringify(saved)); saves.value++;
  }
  return { data: config.method === 'get' ? copy(saved) : { setting: copy(saved) }, status: 200, statusText: 'OK', headers: {}, config };
};
useTemplatesStore().GetTemplates = async () => {};
invoices.printRecord = applyCbcHistoryFixture({ id: 100, patient: { id: 1, name: 'مريض تجريبي', age: 35, age_unit: 'Years', gender: 'Male', code: 'SYNTHETIC' }, registration_date: '2026-10-01', attachments: [] });
const items = () => [invoices.printRecord.tests[0], invoices.printRecord.test_groups[0]?.tests[0], invoices.printRecord.packages[0]?.tests[0]].filter(Boolean);
items().forEach(item => { item.last_result = null; });
let lastOutput;
window.cbcFixture = {
  store, invoices, items, pages: () => pages.value,
  setVisible: value => items().forEach(item => { item.last_result = value; }),
  longPanel: () => {
    const test = cbcTest(3, 'CBC', ['WBC','RBC','HGB','HCT','MCV','MCH','MCHC','RDW','RDW-SD','PLT','MPV','LY','MO','NE','EO','BA','LY#','MO#','NE#','EO#','BA#']);
    test.last_result = true; invoices.printRecord.tests = [test]; invoices.printRecord.test_groups = []; invoices.printRecord.packages = [];
    invoices.printRecord.result_history = cbcHistory([test]);
  },
  prepare: async () => {
    report.value.beginCapture(); await nextTick(); await document.fonts.ready;
    // Inline markers verify separation in actual raster sheets, not just DOM flags.
    document.querySelectorAll('[data-fixture-marker]').forEach(node => node.remove());
    if (!store.settings.print_black_white) for (const section of document.querySelectorAll('#Result .template-section, #Result .cbc-history-section')) {
      const marker = document.createElement('div'); marker.dataset.fixtureMarker = 'true';
      marker.style.cssText = `width:100px;height:4px;background:${section.classList.contains('cbc-history-section') ? '#00ffff' : '#ff00ff'};`;
      section.prepend(marker);
    }
    const before = JSON.stringify(invoices.printRecord);
    const output = await report.value.prepareReport({ withBackground: false, pagesOnly: true });
    const pdf = await report.value.prepareReport({ withBackground: false });
    const result = { count: output.pages.length, sameSheets: JSON.stringify(output.pages) === JSON.stringify(pdf.pages), pdf: await pdf.blob.slice(0, 5).text(), changed: JSON.stringify(invoices.printRecord) !== before, invalidated: output !== lastOutput };
    lastOutput = output; pages.value = output.pages; return result;
  },
};
onMounted(async () => { report.value.beginCapture(); await nextTick(); document.getElementById('Result').style.width = '180mm'; window.cbcFixture.ready = true; });
</script>
<template><main class="p-5" dir="rtl"><Settings :referral-mode="true" /><p id="save-count">{{ saves }}</p><div id="pages"><img v-for="(page, index) in pages" :key="index" :src="page" style="width:600px;max-width:100%;margin:12px auto;display:block;border:1px solid #ddd" :alt="'تقرير تجريبي '+index"></div><Report ref="report" /></main></template>
