<script setup>
import { ref, nextTick, onMounted } from 'vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import PrintSelectModal from '@/views/medical_reports/componentes/printSelectModal.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useresultStatusStore } from '@/store/modules/result-status';
import { useTemplatesStore } from '@/store/modules/template';
import { $http } from '@/plugins/axios';
import { printMedicalReportPages } from '@/utils/medicalReportPages';
import { reportFormChoice, downloadMedicalReportFile } from '@/utils/medicalReportOutput';
import { prepareMedicalReportWhatsApp } from '@/utils/sharePatientPortal';

const params = new URLSearchParams(location.search);
const direct = params.get('mode') !== 'staff';
const chosenForm = reportFormChoice(params.get('form'));
const report = ref(null), pages = ref([]), status = ref('Loading'), checks = ref([]), checksum = ref(''), modal = ref(false), selectedForm = ref('');
const bg = document.createElement('canvas'); bg.width = 794; bg.height = 1123;
const context = bg.getContext('2d'); context.fillStyle = '#fff'; context.fillRect(0, 0, 794, 1123);
context.fillStyle = '#007c83'; context.fillRect(0, 0, 794, 80); context.fillRect(0, 1080, 794, 43);
const settings = { report_template: params.get('template') || 'modern', show_test_names: true, show_categories: true, show_status: true,
  print_margins: { top: 35, bottom: 25, left: 12, right: 18 }, report_background: bg.toDataURL(),
  patient_header_config: { name_size: 18, info_size: 12, qr_size: 70 },
  print_table_config: { header_font_size: 12, body_font_size: 12, cell_padding: 6 } };
const sample = { id: 14, lab_id_fk: 1, patient: { id: 1, name: 'مريض تجريبي', phone: '07700000000', age: 35, age_unit: 'Years', gender: 'Male', code: 'SAMPLE-14' },
  registration_date: '2026-10-01T09:00:00', tests: [], cultures: [], packages: [], tests_last_results: [],
  test_groups: [{ group_name: 'Test group', tests: Array.from({ length: 40 }, (_, i) => ({ test_id_fk: i + 1, name: 'Test ' + (i + 1), result: i, unit: 'mg/dL', result_status_id_fk: 2,
    test_reference_ranges: [{ from: 0, to: 100, gender: 'both', age_from: 0, age_to: 120, age_unit: 'Years' }] })) }] };
sample.result_history = { test_1: Array.from({ length: 35 }, (_, i) => ({ date: '2026-09-01', date_source: 'result', field: 'Test 1', value: String(i), unit: 'mg/dL' })) };
const store = useLabSettingsStore(), invoices = useinvoicesStore();
// A direct report must load the invoice lab settings even if the current
// browser has a stale/different lab's settings in Pinia.
store.settings = direct ? { report_template: 'classic', print_margins: { top: 0, bottom: 0, left: 0, right: 0 } } : settings;
invoices.printRecord = sample;
useresultStatusStore().resultStatus = [{ value: 2, label: 'Normal' }];
useTemplatesStore().GetTemplates = async () => {};
let uploaded = null;
$http.defaults.adapter = async config => {
  let data;
  if (config.url === '/invoices/public/14' || config.url === '/invoices/14') data = sample;
  else if (config.url === '/lab-settings/1') data = settings;
  else if (config.url === '/portal/generate') data = { url: location.origin + '/portal/synthetic-token' };
  else if (config.url === '/invoices/pdf') { uploaded = config.data.get('result_doc'); data = { path: '/synthetic-report.pdf' }; }
  else throw new Error('Unexpected API request: ' + config.url);
  return { data, status: 200, statusText: 'OK', headers: {}, config };
};
const assert = (ok, message) => { if (!ok) throw new Error(message); checks.value.push('PASS ' + message); };
const fingerprint = bytes => bytes.reduce((hash, byte) => ((hash * 31) ^ byte) >>> 0, 0);
let output;
onMounted(async () => {
  try {
    if (direct) {
      for (let i = 0; i < 1200 && !document.querySelector('.report-page-preview img'); i++) await new Promise(requestAnimationFrame);
      assert(Boolean(document.querySelector('.report-page-preview img')), 'direct report loads invoice lab settings and preview');
    }
    output = await report.value.prepareReport(direct ? {} : { withBackground: chosenForm });
    const repeated = await report.value.prepareReport();
    assert(output === repeated && output.blob === repeated.blob, 'all actions reuse the same prepared PDF and sheets');
    assert(output.withBackground === chosenForm, 'form selection survives the entry point');
    pages.value = output.pages;
    assert(document.querySelectorAll('#Result .result-history').length === 1, 'history appears only below the matching analysis');
    assert(document.querySelectorAll('#Result .result-history tbody tr').length === 35, 'all previous measurements are included in the report source');
    assert(document.querySelector('#Result .result-history').textContent.includes('01/09/2026'), 'history displays the recorded calendar date');
    assert(pages.value.length > 1, 'report spans multiple sheets');
    if (direct) assert([...document.querySelectorAll('.report-page-preview img')].every((img, i) => img.src === output.pages[i]), 'direct preview uses the exact export pages');
    assert(document.getElementById('Result').classList.contains('report-modern') === (settings.report_template === 'modern'), 'saved template applies independently of stale store settings');
    for (const [i, src] of output.pages.entries()) {
      const img = new Image(); img.src = src; await img.decode();
      const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
      const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
      const expected = chosenForm ? '0,124,131,255' : '255,255,255,255';
      for (const y of [30, img.height - 30]) assert([...ctx.getImageData(30, y, 1, 1).data].join(',') === expected, `sheet ${i + 1}: form mode applies to header/footer`);
    }
    const bytes = new Uint8Array(await output.blob.arrayBuffer());
    checksum.value = bytes.length + ':' + fingerprint(bytes);
    assert(await output.blob.slice(0, 5).text() === '%PDF-', 'saved output is a real PDF');
    await invoices.pdf(output.blob, sample.id);
    const uploadedBytes = new Uint8Array(await uploaded.arrayBuffer());
    assert(bytes.length === uploadedBytes.length && bytes.every((v, i) => v === uploadedBytes[i]), 'server-save receives byte-identical PDF');
    const share = await prepareMedicalReportWhatsApp(sample, settings, 'Sample Lab', { withBackground: output.withBackground });
    const message = new URL(share.whatsappUrl).searchParams.get('text');
    assert(message.includes('form=' + (chosenForm ? '1' : '0')) && message.includes('report=14'), 'WhatsApp portal link preserves this report form');
    assert(share.phone === '9647700000000', 'WhatsApp uses the registered patient phone');
    status.value = 'PASS';
  } catch (error) { status.value = 'FAIL ' + error.message; console.error(error); }
});
async function execute(selection) {
  selectedForm.value = String(selection.withBackground);
  const next = await report.value.prepareReport({ withBackground: selection.withBackground });
  assert(next.withBackground === selection.withBackground, 'modal applies the same form to the selected action');
  if (next.withBackground !== output.withBackground) assert(next.blob !== output.blob, 'changing the form invalidates the cached PDF');
  output = next; pages.value = next.pages; modal.value = false;
}
const print = () => printMedicalReportPages(output.pages, window.open('about:blank', '_blank'));
</script>
<template>
  <main><h1>توحيد الطباعة والحفظ والإرسال</h1><p>بيانات تجريبية — نفس الصفحات ونفس الفورمة لكل العمليات</p>
    <p id="status" role="status">{{ status }}</p><pre id="checks">{{ checks.join('\n') }}</pre><p id="checksum">{{ checksum }}</p>
    <button :disabled="status !== 'PASS'" @click="downloadMedicalReportFile(output)">حفظ الملف التجريبي</button>
    <button :disabled="status !== 'PASS'" @click="print">طباعة الملف التجريبي</button>
    <button :disabled="status !== 'PASS'" @click="modal = true">اختيار العملية</button><p id="selected-form">{{ selectedForm }}</p>
    <div v-if="!direct" id="pages"><img v-for="(src, i) in pages" :key="i" :src="src" :alt="'صفحة ' + (i + 1)" /></div>
    <Report ref="report" /><PrintSelectModal v-model="modal" :has-background="true" :can-share="true" @execute="execute" />
  </main>
</template>
<style>body{font-family:Arial;background:#edf1f5;margin:20px}h1{font-size:24px}button{background:#0f766e;color:white;padding:10px;margin:8px;border-radius:6px}pre{font-size:12px;white-space:pre-wrap}#pages{display:flex;gap:15px;flex-wrap:wrap}#pages img{width:330px;box-shadow:0 2px 8px #0002}#status{font-weight:bold}</style>
