import { createApp, ref, h } from 'vue';
import Designer from '/src/views/lab-settings/BarcodeLabelDesigner.vue';
import { defaultLabel, barcodePrintCss } from '/src/utils/barcodeLabels.js';
import '/src/assets/main.css';
const config=ref(defaultLabel());
window.qaConfig=config;
window.qaPrintCss=()=>barcodePrintCss(config.value);
createApp({setup(){return ()=>h('main',{style:'padding:24px;max-width:1400px;margin:auto',dir:'rtl'},[h(Designer,{modelValue:config.value,'onUpdate:modelValue':value=>config.value=value,labName:'مختبر العاصمة'})]);}}).mount('#app');
