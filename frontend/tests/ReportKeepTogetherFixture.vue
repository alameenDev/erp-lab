<script setup>
import { ref, nextTick, onMounted } from 'vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useresultStatusStore } from '@/store/modules/result-status';
import { useTemplatesStore } from '@/store/modules/template';
import { $http } from '@/plugins/axios';
import { usePrint } from '@/composables/usePrint';
import { renderMedicalReportPages, createMedicalReportPdf, printMedicalReportPages, reportGeometry } from '@/utils/medicalReportPages';
const params = new URLSearchParams(location.search), mode = params.get('mode') || 'group';
const pages = ref([]), checks = ref([]), status = ref('Loading'), report = ref(null);
const check = (ok, message) => { if (!ok) throw new Error(message); checks.value.push('PASS ' + message); };
$http.defaults.adapter = async config => { throw new Error('Unexpected API call: ' + config.url); };
useTemplatesStore().GetTemplates = async () => {};
useresultStatusStore().resultStatus = [{ value: 2, label: 'Normal' }];
const settings = { report_template: params.get('template') || 'classic', show_test_names: mode !== 'merged', show_categories: true, show_status: true,
  print_margins: { top: 35, bottom: 25, left: 15, right: 15 }, patient_header_config: { name_size: 18, info_size: 12, qr_size: 70 },
  print_table_config: { header_font_size: 12, body_font_size: 12, cell_padding: 5 } };
useLabSettingsStore().settings = settings;
const test = (name, extra = {}) => ({ name, result: 'Negative', unit: '-', result_status_id_fk: 2,
  test_reference_ranges: [{ notes: 'Negative', gender: 'both', age_from: 0, age_to: 120, age_unit: 'Years' }], ...extra });
const virology = ['Human Immunodeficiency Virus (HIV)', 'Hepatitis B Virus (HBs Ag)', 'Hepatitis C Virus (HCV)'].map(name => test(name));
const record = { id: 1, patient: { name: 'مريض تجريبي', age: 35, age_unit: 'Years', gender: 'Male', code: 'SYNTHETIC-1' },
  registration_date: '2026-10-01T09:00:00', tests: [test('Previous analysis')], test_groups: [], packages: [], cultures: [], tests_last_results: [] };
if (['group', 'merged', 'long'].includes(mode)) record.test_groups = [{ group_name: 'Virology', tests: mode === 'long' ? Array.from({ length: 45 }, (_, i) => test('Virology result ' + (i + 1))) : virology }];
if (mode === 'first') { record.tests = []; record.test_groups = [{ group_name: 'Virology', tests: virology }]; }
if (mode === 'analysis') record.tests.push(test('Human Immunodeficiency Virus (HIV) — Full analysis name', { category: 'Virology' }));
if (mode === 'package') record.packages = [{ name: 'Virology package', tests: [virology[0], test('Package group input', { test_group_name: 'Package group', test_group_id_fk: 5, shortcut: 'X', result: 4 }), test('Calculated result', { shortcut: 'Y' })],
  test_groups: [{ test_group_id_fk: 5, group_name: 'Package group', formula: [] }], cultures: [test('Package culture')], formula: [{ name: 'Y', tokens: ['X', '*', '2'] }] }];
if (mode === 'template') record.tests = [test('Custom test', { sub_tests: [{ id: 1 }], content: { html: '<p>Previous analysis</p><h2>Virology</h2><table><thead><tr><th>Test</th><th>Result</th></tr></thead><tbody><tr><td>HIV</td><td>Negative</td></tr><tr><td>HBs Ag</td><td>Negative</td></tr><tr><td>HCV</td><td>Negative</td></tr></tbody></table>' } })];
useinvoicesStore().printRecord = record;
const { printStyles } = usePrint();
const css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(settings.patient_header_config) + printStyles.getPrintTableCss(settings.print_table_config) + printStyles.getReportTemplateCss(settings);
const bg = document.createElement('canvas'); bg.width = 794; bg.height = 1123;
const bgctx = bg.getContext('2d'); bgctx.fillStyle = '#fff'; bgctx.fillRect(0, 0, 794, 1123); bgctx.fillStyle = '#007c83'; bgctx.fillRect(0, 0, 794, 65); bgctx.fillRect(0, 1080, 794, 43);
let pdf;
onMounted(async () => {
 try {
  report.value.beginCapture(); await nextTick();
  const source = document.getElementById('Result');
  const style = document.createElement('style'); style.textContent = css + '\n#Result{width:180mm!important;min-height:0!important;padding:0!important;margin:0!important} .pw-cell{padding:0!important}'; document.head.append(style);
  await document.fonts.ready;
  const wrapper = source.querySelector('table.print-wrapper'), header = wrapper.querySelector(':scope > thead');
  const capacity = reportGeometry(settings.print_margins).content.height / 2 - header.getBoundingClientRect().height;
  let targets, filler, anchor;
  if (mode === 'package') targets = [...source.querySelectorAll('[data-report-section][data-report-bundle="package:0"]')];
  else if (mode === 'merged') targets = [...source.querySelectorAll('[data-report-block="merged:1"]')];
  else if (mode === 'template') targets = [source.querySelector('.template-section h2'), source.querySelector('.template-section table')];
  else targets = [[...source.querySelectorAll('[data-report-section]')].at(-1)];
  anchor = targets[0];
  check(Boolean(anchor), 'target analysis/group/package is rendered');
  filler = mode === 'merged' ? source.querySelector('[data-report-block="merged:0"]') : mode === 'template' ? source.querySelector('.template-section p') : source.querySelector('[data-report-section]');
  // Put the real section at the bottom of page one: its title fits, its data
  // does not. This recreates the reported Virology orphan deterministically.
  if (mode === 'first') {
    anchor.style.marginTop = '24px';
    const row = anchor.querySelector('table > tbody > tr');
    row.style.height = (row.getBoundingClientRect().height + capacity - anchor.getBoundingClientRect().height - 4) + 'px';
    check(anchor.getBoundingClientRect().top > header.getBoundingClientRect().bottom + 1, 'first block has leading white space');
  } else {
  const remaining = mode === 'merged' ? 14 : 34;
  const bodyTop = header.getBoundingClientRect().bottom;
  const delta = capacity - remaining - (anchor.getBoundingClientRect().top - bodyTop);
  check(delta > 0, 'fixture leaves insufficient room for the next complete block');
  if (mode === 'merged') filler.style.height = (filler.getBoundingClientRect().height + delta) + 'px';
  else filler.style.paddingBottom = delta + 'px';
  // Padding changes margin collapse around the source table; compensate after
  // the browser has laid it out so the boundary is identical in both designs.
  for (let attempt = 0; attempt < 3; attempt++) {
    const correction = capacity - remaining - (anchor.getBoundingClientRect().top - header.getBoundingClientRect().bottom);
    if (Math.abs(correction) < 1) break;
    if (mode === 'merged') filler.style.height = (filler.getBoundingClientRect().height + correction) + 'px';
    else filler.style.paddingBottom = (parseFloat(filler.style.paddingBottom) + correction) + 'px';
  }
  check(Math.abs(capacity - (anchor.getBoundingClientRect().top - header.getBoundingClientRect().bottom) - remaining) < 3, 'block begins just before the page boundary');
  }
  if (mode !== 'long') check(targets.at(-1).getBoundingClientRect().bottom - anchor.getBoundingClientRect().top < capacity, 'complete target fits on an empty report page');
  const elements = new Set();
  targets.forEach(target => {
    if (target.matches('tr,h2')) elements.add(target);
    target.querySelectorAll('.section-header,h2,table > thead > tr,table > tbody > tr').forEach(node => elements.add(node));
    if (target.matches('table')) target.querySelectorAll('thead > tr,tbody > tr').forEach(node => elements.add(node));
  });
  const markers = [...elements].map((node, i) => {
    const rgb = [191, 20 + i * 3, 131];
    const cell = node.matches('tr') ? node.children[0] : node;
    cell.style.setProperty('border-left', `6px solid rgb(${rgb})`, 'important');
    return { rgb, label: node.textContent.trim().slice(0, 45), pages: [] };
  });
  pages.value = await renderMedicalReportPages({ element: source, css, margins: settings.print_margins, background: bg.toDataURL() });
  for (const [index, src] of pages.value.entries()) {
    const img = new Image(); img.src = src; await img.decode();
    const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
    const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
    const pixels = ctx.getImageData(0, 0, img.width, img.height).data, counts = new Map();
    for (let i = 0; i < pixels.length; i += 4) if (pixels[i] === 191 && pixels[i + 2] === 131) counts.set(pixels[i + 1], (counts.get(pixels[i + 1]) || 0) + 1);
    markers.forEach(marker => { if ((counts.get(marker.rgb[1]) || 0) > 10) marker.pages.push(index); });
    check([...ctx.getImageData(30, 30, 1, 1).data].join(',') === '0,124,131,255', `page ${index + 1}: fixed form remains in place`);
  }
  check(markers.length >= 2, 'heading and detailed results are measured');
  markers.forEach(marker => check(marker.pages.length === 1, `complete row/title appears once: ${marker.label}`));
  const expectedPage = mode === 'first' ? 0 : 1;
  check(markers[0].pages[0] === expectedPage, 'block starts on the correct non-empty page');
  if (mode === 'first') check(pages.value.length === 1, 'no blank leading or trailing page');
  if (mode !== 'long') check(markers.every(marker => marker.pages[0] === expectedPage), 'all titles, results, ranges, cultures and formulas stay on the same sheet');
  else {
    check(markers.slice(0, 3).every(marker => marker.pages[0] === 1), 'oversized group title, column header and first result stay together');
    check(new Set(markers.flatMap(marker => marker.pages)).size > 1, 'oversized group continues between complete result rows');
  }
  pdf = await createMedicalReportPdf({ pages: pages.value });
  check(pdf.getNumberOfPages() === pages.value.length, 'saved PDF uses exactly the verified pages');
  status.value = 'PASS';
 } catch (error) { status.value = 'FAIL ' + error.message; console.error(error); }
 finally { report.value.endCapture(); }
});
const print = () => printMedicalReportPages(pages.value, window.open('about:blank', '_blank'));
</script>
<template><main><h1>بقاء العنوان والنتائج في ورقة واحدة</h1><p>مثال Virology — بيانات تجريبية</p><p id="status">{{ status }}</p><details><summary>تفاصيل الفحص</summary><pre id="checks">{{ checks.join('\n') }}</pre></details><button :disabled="status !== 'PASS'" @click="print">طباعة المعاينة</button><div id="pages"><img v-for="(src,i) in pages" :key="i" :src="src" :alt="'صفحة ' + (i+1)" /></div><Report ref="report" /></main></template>
<style>body{margin:20px;background:#edf1f5;font-family:Arial}h1{font-size:24px}#status{font-weight:bold}button{padding:10px;background:#007c83;color:white;margin:12px 0}#pages{display:flex;flex-wrap:wrap;gap:18px}#pages img{width:420px;box-shadow:0 2px 10px #0002}pre{white-space:pre-wrap;font-size:12px}</style>
