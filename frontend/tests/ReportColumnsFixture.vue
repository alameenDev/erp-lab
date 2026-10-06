<script setup>
import { ref, nextTick } from 'vue';
import Settings from '@/views/lab-settings/index.vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useresultStatusStore } from '@/store/modules/result-status';
import { useTemplatesStore } from '@/store/modules/template';
import { customReportColumns, reportColumnKeys, reportColumnWidths } from '@/utils/medicalReportColumns';
import { $http } from '@/plugins/axios';
const store = useLabSettingsStore(), report = ref(null), status = ref('Ready'), checks = ref([]), pages = ref([]), saves = ref(0);
const check = (ok, text) => { if (!ok) throw new Error(text); checks.value.push('PASS ' + text); };
let saved = JSON.parse(sessionStorage.getItem('report-columns-fixture') || 'null') || { report_template: 'classic', show_status: true, show_test_names: true, show_categories: true, show_last_result: true, lab_display_name: 'Digital Lab — معاينة تجريبية' };
// Exercise the actual settings page, multipart save and store normalization.
// All requests are intercepted; this fixture never accesses patient data.
$http.defaults.adapter = async config => {
  if (config.url === '/portal/generate') return { data: { url: location.origin + '/portal/' + 'a'.repeat(48) }, status: 200, statusText: 'OK', headers: {}, config };
  if (config.url !== '/lab-settings') throw new Error('Unexpected API: ' + config.url);
  if (config.method === 'post') {
    if (!(config.data instanceof FormData)) throw new Error('Settings must use multipart FormData');
    const widths = {};
    for (const key of ['test', 'result', 'unit', 'reference', 'status', 'last_result']) {
      const value = Number(config.data.get(`print_table_config[column_widths][${key}]`));
      if (!(value >= 5 && value <= 85)) throw new Error('Missing/invalid saved width: ' + key);
      widths[key] = value;
    }
    saved = { ...store.settings, print_table_config: { ...store.settings.print_table_config, column_widths: widths, custom_column_widths: config.data.get('print_table_config[custom_column_widths]') === '1' } };
    sessionStorage.setItem('report-columns-fixture', JSON.stringify(saved));
    saves.value++;
  }
  return { data: config.method === 'get' ? JSON.parse(JSON.stringify(saved)) : { setting: JSON.parse(JSON.stringify(saved)) }, status: 200, statusText: 'OK', headers: {}, config };
};
useTemplatesStore().GetTemplates = async () => {};
useresultStatusStore().resultStatus = [{ value: 2, label: 'Normal' }];
const test = (name, extra = {}) => ({ name, result: 'Negative', unit: 'IU/mL', result_status_id_fk: 2,
  test_reference_ranges: [{ notes: 'Negative: less than 0.90; Borderline: 0.90–1.10; Positive: greater than 1.10.\nInterpret with the clinical history.', gender: 'both', age_from: 0, age_to: 120, age_unit: 'Years' }], ...extra });
useinvoicesStore().printRecord = { id: 1, patient: { id: 1, name: 'مريض تجريبي', age: 35, age_unit: 'Years', gender: 'Male', code: 'SYNTHETIC-1' }, registration_date: '2026-10-01T09:00:00',
  tests: [test('Human Immunodeficiency Virus (HIV) Antigen / Antibody')],
  test_groups: [{ group_name: 'Virology', tests: [test('HBsAg')], cultures: [test('Group culture')] }],
  cultures: [test('Standalone culture')],
  packages: [{ name: 'Package', tests: [test('Package test'), test('Group input', { result: '4', shortcut: 'X', test_group_name: 'Package group', test_group_id_fk: 5 }), test('Calculated result', { shortcut: 'Y' })], test_groups: [{ test_group_id_fk: 5, group_name: 'Package group', formula: [] }], cultures: [test('Package culture')], formula: [{ name: 'Y', tokens: ['X', '*', '2'] }] }],
  tests_last_results: [{ name: 'HBsAg', result: 'Negative' }] };
async function verify() {
  status.value = 'Rendering'; checks.value = [];
  try {
    report.value.beginCapture(); await nextTick(); await document.fonts.ready;
    const source = document.getElementById('Result');
    const tables = [...source.querySelectorAll('.report-results-table')];
    check(tables.length >= (store.settings.show_test_names ? 6 : 1), 'all result table types are present');
    tables.push(...document.querySelectorAll('.report-template-preview .report-results-table'));
    const enabled = customReportColumns(store.settings.print_table_config), widths = reportColumnWidths(store.settings), keys = reportColumnKeys(store.settings);
    for (const table of tables) {
      check(table.classList.contains('report-column-widths') === enabled, 'custom layout follows the saved switch');
      if (!enabled) { check(!table.querySelector('colgroup'), 'original layout has no width overrides'); continue; }
      check(getComputedStyle(table).tableLayout === 'fixed', 'selected widths override template auto sizing');
      const columns = [...table.querySelectorAll(':scope > colgroup > col')];
      check(columns.map(c => c.dataset.reportColumn).join() === keys.join(), 'semantic order matches the selected template and visible columns');
      const cells = [...table.querySelector('thead tr').children];
      const total = cells.reduce((sum, cell) => sum + cell.getBoundingClientRect().width, 0);
      columns.forEach((column, i) => check(Math.abs(cells[i].getBoundingClientRect().width / total * 100 - widths[keys[i]]) < 0.6, `${keys[i]} measures ${widths[keys[i]]}%`));
      check([...table.querySelectorAll('tbody > tr > td')].every(cell => cell.scrollWidth <= cell.clientWidth + 1), 'long results and ranges wrap inside their cells');
    }
    const output = await report.value.prepareReport({ withBackground: false });
    pages.value = output.pages;
    check(output.pages.length > 0 && output.blob.type === 'application/pdf', 'canonical PDF and print pages are prepared with these widths');
    check(await report.value.prepareReport({ withBackground: false }) === output, 'all actions share the prepared output');
    status.value = 'PASS';
  } catch (error) { status.value = 'FAIL ' + error.message; console.error(error); }
  finally { report.value.endCapture(); }
}
</script>
<template>
  <main dir="rtl" class="p-6"><p class="mb-4 text-sm text-slate-500">بيانات تجريبية فقط · فحص إعدادات أعمدة التقرير</p>
    <Settings :referral-mode="true" />
    <section class="mt-8 rounded-xl border bg-white p-5"><button class="rounded-lg bg-teal-700 p-3 text-white" @click="verify">فحص التقرير المحفوظ</button><span id="save-count" class="mx-3">{{ saves }}</span><p id="status">{{ status }}</p><details><summary>تفاصيل التحقق</summary><pre id="checks" dir="ltr">{{ checks.join('\n') }}</pre></details><div id="pages" class="flex flex-wrap gap-4"><img v-for="(page, i) in pages" :key="i" :src="page" class="w-[400px] max-w-full border" :alt="'صفحة تجريبية ' + (i + 1)"></div></section>
    <Report ref="report" />
  </main>
</template>
<style>body{margin:0;background:#f1f5f9}#checks{white-space:pre-wrap;font-size:12px}</style>
