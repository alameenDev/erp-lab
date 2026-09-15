<script setup>
import { computed } from 'vue';
import { documentCss } from '@/utils/labDocuments';
import { usePrint } from '@/composables/usePrint';
const props = defineProps({ settings: Object, documents: Object, kind: String });
const { printStyles } = usePrint();
const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const source = computed(() => {
  const thermal = props.kind === 'thermal';
  const config = props.documents[props.kind];
  const css = printStyles[thermal ? 'thermalReceipt' : 'invoice'] || '';
  return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>${css}\n${documentCss({...props.settings,document_config:props.documents},props.kind)}
  html{background:#fff}body{margin:12px!important}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #ddd}h2,p{text-align:center}.preview-code{font-family:monospace;letter-spacing:3px;text-align:center}.hdr-bc,.bc-row{justify-content:center}</style></head><body><main class="${thermal?'thermal-receipt':'inv'}"><h2 class="inv-title">${escape(props.settings.lab_display_name || 'المختبر')}</h2><p>فاتورة تجريبية — LAB-0001</p><p>اسم المريض: مثال للمعاينة</p><div class="${thermal?'bc-row':'hdr-bc'}"><span class="preview-code">||| || ||| || |||</span></div><table><thead><tr><th>التحليل</th><th>المبلغ</th></tr></thead><tbody><tr><td>Vitamin D3</td><td>25,000</td></tr><tr><td>CBC</td><td>10,000</td></tr></tbody></table><p>المجموع: 35,000</p><p class="${thermal?'qr':'hdr-qr'}">QR — رابط النتائج</p><p>${escape(config.footer)}</p></main></body></html>`;
});
</script>
<template>
  <iframe :srcdoc="source" sandbox="" title="معاينة الفاتورة ببيانات تجريبية" class="w-full h-[440px] bg-white border rounded-xl" />
</template>
