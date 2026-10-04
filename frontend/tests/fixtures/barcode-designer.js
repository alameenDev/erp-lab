import { createApp, ref } from 'vue';
import Designer from '/src/views/lab-settings/BarcodeLabelDesigner.vue';
import { defaultLabel, barcodePrintCss } from '/src/utils/barcodeLabels.js';
import '/src/assets/main.css';
const config=ref(defaultLabel());
window.qaConfig=config;
window.qaPrintCss=()=>barcodePrintCss(config.value);
createApp({components:{Designer},setup(){return {config}},template:'<main style="padding:24px;max-width:1400px;margin:auto" dir="rtl"><Designer v-model="config" lab-name="مختبر العاصمة" /></main>'}).mount('#app');
