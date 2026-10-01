<script setup>
import { ref, nextTick, onMounted } from 'vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useresultStatusStore } from '@/store/modules/result-status';
import { useTemplatesStore } from '@/store/modules/template';
import { $http } from '@/plugins/axios';
import { usePrint } from '@/composables/usePrint';
import { renderMedicalReportPages, createMedicalReportPdf, printMedicalReportPages } from '@/utils/medicalReportPages';

$http.defaults.adapter = async config => { throw new Error('Unexpected network request: ' + config.url); };
useTemplatesStore().GetTemplates = async () => {};
useresultStatusStore().resultStatus = [{ value: 2, label: 'Normal' }];
const store = useLabSettingsStore();
store.settings = { report_template: 'modern', show_test_names: true, show_categories: true, show_status: true,
  print_margins: { top: 20, bottom: 20, left: 15, right: 15 },
  patient_header_config: { name_size: 18, info_size: 12, qr_size: 70 },
  print_table_config: { header_font_size: 12, body_font_size: 12, cell_padding: 5 } };
const invoices = useinvoicesStore();
const { printStyles } = usePrint();
const mode = ref('mixed'), pages = ref([]), status = ref('Ready'), checks = ref([]), busy = ref(false), report = ref(null);
const test = (name, extra = {}) => ({ name, result: 4, unit: 'mg/dL', result_status_id_fk: 2,
  test_reference_ranges: [{ from: 0, to: 10, gender: 'both', age_from: 0, age_to: 120, age_unit: 'Years' }], ...extra });
const group = (label, isolated = false, count = 1) => ({ group_name: label + ' group', is_print_alone: isolated,
  tests: Array.from({ length: count }, (_, i) => test(label + ' result ' + (i + 1))), formula: [] });
function record(scenario) {
  const data = { id: 1, patient: { name: 'مريض تجريبي', age: 35, age_unit: 'Years', gender: 'Male', code: 'PAGES-1' },
    registration_date: '2026-10-01T09:00:00', tests: [], cultures: [], packages: [], test_groups: [], tests_last_results: [] };
  const a = group('A', true), b = group('B', '1'), n = group('N');
  if (scenario === 'only') data.test_groups = [a];
  if (scenario === 'first') data.test_groups = [a, n];
  if (scenario === 'last') data.test_groups = [n, a];
  if (scenario === 'adjacent') data.test_groups = [a, b];
  if (scenario === 'long') data.test_groups = [n, group('A', 1, 65), group('N')];
  if (scenario === 'mixed' || scenario === 'off') {
    data.tests = [test('N standalone')];
    data.test_groups = [a, n];
    a.cultures = [test('A culture')];
    data.packages = [{ name: 'Package', formula: [], cultures: [test('N package culture')],
      tests: [test('N package own'), test('B input', { shortcut: 'X', test_group_name: 'Package group', test_group_id_fk: 5 }),
        test('B calculated', { shortcut: 'Y', test_group_name: 'Package group', test_group_id_fk: 5 }),
        test('N package group', { test_group_name: 'Other package group', test_group_id_fk: 6 })],
      test_groups: [{ test_group_id_fk: 5, group_name: 'Package group', is_print_alone: true, formula: [{ name: 'Y', tokens: ['X', '*', '1'] }] },
        { test_group_id_fk: 6, group_name: 'Other package group', is_print_alone: false }] }];
    data.cultures = [test('N culture')];
    if (scenario === 'off') { a.is_print_alone = false; data.packages[0].test_groups[0].is_print_alone = false; }
  }
  if (scenario === 'culture') { data.test_groups = [a]; data.cultures = [test('N culture')]; }
  if (scenario === 'history') {
    data.test_groups = [a];
    data.tests_last_results = [{ name: 'N history', result: 3, result_date: '2026-09-01' }];
  }
  return data;
}
invoices.printRecord = record(mode.value);
const assert = (ok, message) => { if (!ok) throw new Error(message); checks.value.push('PASS ' + message); };
const colors = { N: [238, 103, 11], A: [217, 19, 173], B: [29, 91, 231] };
async function render() {
  busy.value = true; status.value = 'Rendering'; checks.value = [];
  try {
    invoices.printRecord = record(mode.value);
    report.value.beginCapture(); await nextTick();
    const source = document.getElementById('Result');
    // Synthetic markers identify which results really appear on each rasterized
    // sheet. Assertions inspect output pixels, not just the DOM break flags.
    const visibleLabels = new Set();
    source.querySelectorAll('[data-report-section] table > tbody > tr').forEach(row => {
      const cell = row.children[0], name = cell?.textContent.trim(), label = name?.[0];
      if (!colors[label]) return;
      visibleLabels.add(label);
      const marker = document.createElement('span');
      marker.style.cssText = `display:inline-block;width:16px;height:8px;margin-right:4px;background:rgb(${colors[label]})!important;`;
      cell.prepend(marker);
    });
    const css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(store.settings.patient_header_config)
      + printStyles.getPrintTableCss(store.settings.print_table_config) + printStyles.getReportTemplateCss(store.settings);
    pages.value = await renderMedicalReportPages({ element: source, css, margins: store.settings.print_margins });
    const membership = [];
    for (const [index, src] of pages.value.entries()) {
      const img = new Image(); img.src = src; await img.decode();
      const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
      const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
      const pixels = ctx.getImageData(0, 0, img.width, img.height).data;
      const counts = { N: 0, A: 0, B: 0 };
      for (let i = 0; i < pixels.length; i += 4) {
        for (const [key, rgb] of Object.entries(colors)) {
          if (rgb.every((v, j) => pixels[i + j] === v)) counts[key]++;
        }
      }
      const labels = Object.keys(counts).filter(key => counts[key] > 50);
      assert(labels.length > 0, `sheet ${index + 1} contains results (no empty leading/trailing sheets)`);
      if (mode.value !== 'off') assert(labels.length === 1, `sheet ${index + 1} has only ${labels.join(',')} results`);
      membership.push(...labels);
    }
    assert([...visibleLabels].every(label => membership.includes(label)), 'every result section survives pagination');
    const expected = { mixed: ['N', 'A', 'N', 'B', 'N'], first: ['A', 'N'], last: ['N', 'A'], adjacent: ['A', 'B'],
      only: ['A'], long: ['N', 'A', 'N'], culture: ['A', 'N'], history: ['A', 'N'] };
    if (mode.value !== 'off') {
      const order = membership.filter((label, i) => i === 0 || label !== membership[i - 1]);
      assert(JSON.stringify(order) === JSON.stringify(expected[mode.value]), 'group order and both page boundaries are preserved');
    }
    if (mode.value === 'only') assert(pages.value.length === 1, 'single isolated group uses one sheet');
    if (mode.value === 'off') assert(pages.value.length < 5 && membership.length > pages.value.length, 'disabled isolation lets groups share sheets with normal pagination');
    if (mode.value === 'long') assert(membership.filter(label => label === 'A').length > 1, 'long group continues on dedicated sheets');
    const pdf = await createMedicalReportPdf({ pages: pages.value });
    assert(pdf.getNumberOfPages() === pages.value.length, 'download PDF uses the same sheets');
    status.value = 'PASS';
  } catch (error) { status.value = 'FAIL ' + error.message; console.error(error); }
  finally { report.value.endCapture(); busy.value = false; }
}
onMounted(render);
const print = () => printMedicalReportPages(pages.value, window.open('about:blank', '_blank'));
</script>
<template>
  <main>
    <h1>طباعة الكروب في صفحة منفصلة</h1><p>بيانات تجريبية للتحقق من فصل الصفحات</p>
    <label>النموذج <select aria-label="النموذج" v-model="store.settings.report_template"><option value="modern">الجديد</option><option value="classic">القديم</option></select></label>
    <label>الحالة <select aria-label="الحالة" v-model="mode"><option v-for="name in ['mixed','first','last','adjacent','only','long','off','culture','history']" :key="name">{{ name }}</option></select></label>
    <label><input type="checkbox" v-model="store.settings.show_test_names" /> إظهار أسماء التحاليل</label>
    <button :disabled="busy" @click="render">تحديث المعاينة</button><button :disabled="busy || !pages.length" @click="print">طباعة المعاينة</button>
    <p id="status" role="status">{{ status }}</p><pre id="checks">{{ checks.join('\n') }}</pre>
    <div id="pages"><img v-for="(src, i) in pages" :key="i" :src="src" :alt="'صفحة ' + (i + 1)" /></div>
    <Report ref="report" />
  </main>
</template>
<style>
body{margin:20px;background:#edf1f5;font-family:Arial}h1{font-size:24px}label,button{display:inline-block;margin:12px}button,select{padding:8px}button{background:#0f766e;color:white;border-radius:6px}button:disabled{opacity:.5}#checks{white-space:pre-wrap;font-size:12px}#pages{display:flex;flex-wrap:wrap;gap:18px;margin-top:20px}#pages img{width:360px;max-width:100%;box-shadow:0 2px 10px #0002}#status{font-weight:bold}
</style>
