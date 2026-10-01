<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePrint } from '@/composables/usePrint';
import ModernPatientHeader from '@/views/medical_reports/componentes/ModernPatientHeader.vue';
import ReportResultFlag from '@/views/medical_reports/componentes/ReportResultFlag.vue';
import ReportTableColumns from '@/views/medical_reports/componentes/ReportTableColumns.vue';
import { customReportColumns } from '@/utils/medicalReportColumns';
const props = defineProps({ settings: { type: Object, required: true } });
const modern = computed(() => props.settings.report_template === 'modern');
const { printStyles } = usePrint();
const viewport = ref(null);
const paper = ref(null);
const scale = ref(1);
const height = ref(420);
let observer;
onMounted(() => {
  const resize = () => {
    scale.value = Math.min(1, (viewport.value?.clientWidth || 680) / 680);
    height.value = (paper.value?.offsetHeight || 420) * scale.value;
  };
  observer = new ResizeObserver(resize);
  observer.observe(viewport.value);
  observer.observe(paper.value);
  resize();
});
onBeforeUnmount(() => observer?.disconnect());
const css = computed(() => printStyles.getPrintTableCss(props.settings.print_table_config).replaceAll('#Result', '.report-template-preview') + printStyles.getReportTemplateCss(props.settings));
const record = { patient: { name: 'مريض تجريبي', age: 35, age_unit: 'Years', gender: 'Male', code: 'LAB-0001' }, registration_date: '2026-01-01T09:00:00', referral: { name: 'Sample Doctor' } };
const rows = [
  { name: 'Triglycerides (Serum)', result: '169', status: 1, label: 'High', range: '40–160\nFasting sample' },
  { name: 'Cholesterol', result: '207', status: 1, label: 'High', range: '0–200' },
  { name: 'HDL', result: '36.3', status: 2, label: 'Normal', range: '35–80' },
];
</script>

<template>
  <component :is="'style'">{{ css }}</component>
  <div ref="viewport" dir="ltr" :style="{ height: height + 'px', overflow: 'hidden' }">
  <div ref="paper" class="report-template-preview bg-white p-4" :class="{ 'report-modern': modern, 'report-monochrome': settings.print_black_white }" :style="{ width: '680px', transform: `scale(${scale})`, transformOrigin: 'top left', filter: settings.print_black_white ? 'grayscale(1)' : 'none' }" dir="ltr">
    <ModernPatientHeader v-if="modern" :record="record" :show-qr="false" />
    <div v-else class="mb-4 border border-slate-300 p-4 text-center"><h4 class="font-bold">{{ settings.lab_display_name || 'Digital Lab' }}</h4><p>مريض تجريبي · LAB-0001</p></div>
    <div v-if="settings.show_test_names !== false" class="section-header border border-slate-300 bg-slate-100 p-2 font-bold"><i v-if="modern" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>Lipid Profile</span><small v-if="modern" class="mr-section-caption">TEST RESULTS</small></div>
    <table class="report-results-table w-full" style="table-layout:fixed; border-collapse:collapse" :class="{ 'report-column-widths': customReportColumns(settings.print_table_config) }">
      <ReportTableColumns :settings="settings" />
      <thead><tr><th>Test</th><th>Result</th><th v-if="modern && settings.show_status !== false">Flag</th><th>Unit</th><th>Reference Range</th><th v-if="settings.show_last_result">Last Result</th><th v-if="!modern && settings.show_status !== false">Status</th></tr></thead>
      <tbody><tr v-for="row in rows" :key="row.name"><td>{{ row.name }}</td><td :style="settings.print_black_white ? {} : { color: row.status === 1 ? '#dc2626 !important' : '#16a34a !important' }">{{ row.result }}</td><td v-if="modern && settings.show_status !== false"><ReportResultFlag :status-id="row.status" :label="row.label" /></td><td>mg/dL</td><td><span class="whitespace-pre-line">{{ row.range }}</span></td><td v-if="settings.show_last_result">—</td><td v-if="!modern && settings.show_status !== false">{{ row.label }}</td></tr></tbody>
    </table>
  </div>
  </div>
</template>
