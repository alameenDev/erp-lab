import { createApp, ref, h } from 'vue';
import { createPinia } from 'pinia';
import Printer from '/src/components/barcode/InvoiceBarcodeLabels.vue';
import Designer from '/src/views/lab-settings/BarcodeLabelDesigner.vue';
import SavedInvoice from '/src/views/invoices/componentes/printInvoice_modal.vue';
import { defaultLabel } from '/src/utils/barcodeLabels.js';
import { useinvoicesStore } from '/src/store/modules/invoices.js';
import { $http } from '/src/plugins/axios.js';
import '/src/assets/main.css';
localStorage.setItem('locale','ar');
const config=ref({...defaultLabel(),show_category_dialog:true});
const blood={id:1,name:'صورة الدم الكاملة',shortcut:'CBC',category_id_fk:1,category:'أمراض الدم',sample_name:'EDTA'};
const serum={id:2,name:'سكر الدم',shortcut:'GLU',category_id_fk:2,category:'الكيمياء السريرية',sample_name:'Serum'};
const sugar={id:3,name:'السكر التراكمي',shortcut:'HbA1c',category_id_fk:2,category:'الكيمياء السريرية',sample_name:'EDTA'};
const record={id:1,barcode:'0001234',patient:{name:'مريض تجريبي',age:33},tests:[blood],cultures:[{id:1,name:'زرع البول',category_id_fk:3,category:'الأحياء المجهرية',sample:'Urine'}],test_groups:[{group_name:'صورة الدم',tests:[blood]}],packages:[{name:'فحص السكر',tests:[serum,sugar],test_groups:[{group_name:'مجموعة السكر',tests:[serum,sugar]}]}]};
const second={id:2,barcode:'0005678',patient:{name:'مريض آخر',age:20},tests:[serum]};
window.qa={config,requests:[],fail:false,delay:0};
$http.defaults.adapter=async request=>{
 window.qa.requests.push(request.url);
 let data;
 if(request.url==='/lab-settings') data={barcode_config:config.value,lab_display_name:'مختبر تجريبي'};
 else if(request.url.startsWith('/invoices/')) {
  if(window.qa.delay) await new Promise(resolve=>setTimeout(resolve,window.qa.delay));
  if(window.qa.fail) throw new Error('Synthetic load failure');
  data=request.url.endsWith('/2')?second:record;
 } else data={};
 return {data:JSON.parse(JSON.stringify(data)),status:200,statusText:'OK',headers:{},config:request};
};
const pinia=createPinia();
createApp({setup(){
 const printer=ref(null);const invoices=useinvoicesStore();window.qa.invoices=invoices;
 const openSaved=()=>{invoices.printRecord=JSON.parse(JSON.stringify(record));invoices.printInvoiceDialog=true;};
 return ()=>h('main',{class:'p-6 max-w-5xl mx-auto space-y-5'},[
  h('h1',{class:'text-2xl font-bold'},'طباعة باركود العينات'),
  h('button',{id:'open-a',class:'rounded-lg bg-teal-700 text-white p-3',onClick:()=>printer.value.open({id:1})},'باركود الفاتورة الأولى'),
  h('button',{id:'open-b',class:'rounded-lg border p-3 mx-2',onClick:()=>printer.value.open({id:2})},'باركود الفاتورة الثانية'),
  h('button',{id:'open-saved',class:'rounded-lg border p-3',onClick:openSaved},'فتح الفاتورة المحفوظة'),
  h(Printer,{ref:printer}),h(SavedInvoice),
  h('details',[h('summary','إعدادات الباركود'),h(Designer,{modelValue:config.value,'onUpdate:modelValue':value=>config.value=value,labName:'مختبر تجريبي'})]),
 ]);
}}).use(pinia).mount('#app');
