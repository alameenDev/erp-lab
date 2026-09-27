<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { useinvoicesStore } from '@/store/modules/invoices';
import { referralWorkspace } from '@/utils/referralWorkspace';
import { $http } from '@/plugins/axios';
import PrintInvoice from '@/views/invoices/componentes/printInvoice_modal.vue';
const route = useRoute(), store = useinvoicesStore();
const invoice = ref(null), error = ref(''), loading = ref(false);
let timer;
async function load() {
 if(loading.value) return; loading.value = true;
 try { const {data} = await $http.get('/referral-portal/workspace/invoices/' + route.params.id); invoice.value = data; referralWorkspace.destinationLabId = data.lab_id_fk; error.value = ''; }
 catch(e) { error.value = e.response?.data?.message || 'تعذر تحديث الفاتورة'; }
 finally { loading.value = false; }
}
function print() { store.printRecord = invoice.value; store.printInvoiceDialog = true; }
onMounted(() => { load(); timer = setInterval(() => { if(!document.hidden && !store.printInvoiceDialog) load(); },30000); });
onBeforeUnmount(() => { clearInterval(timer); store.printInvoiceDialog = false; store.printRecord = []; });
</script>
<template>
 <div class="max-w-7xl mx-auto p-4 space-y-4" dir="rtl">
  <router-link to="/referral-portal" class="text-teal-700">العودة إلى الفواتير</router-link>
  <p v-if="error" role="alert" class="text-red-700">{{error}}</p>
  <div v-if="invoice" class="bg-white border rounded-2xl p-6 space-y-4">
   <div class="flex justify-between flex-wrap gap-3"><h1 class="text-xl font-bold">فاتورة {{invoice.id}} — {{invoice.patient?.name}}</h1><button @click="load" :disabled="loading" class="text-teal-700">تحديث النتائج</button></div>
   <p>{{invoice.is_done ? 'النتائج مكتملة' : 'الفحوصات قيد الإجراء'}} · آخر تحديث: {{new Date(invoice.updated_at).toLocaleString('ar-IQ')}}</p>
   <p>المجموع: {{invoice.total}} · المدفوع: {{invoice.paid}} · المتبقي: {{Number(invoice.total)-Number(invoice.paid)}}</p>
   <div class="flex gap-3 flex-wrap"><button @click="print" class="px-5 py-3 rounded-xl bg-teal-700 text-white">الفاتورة والوصل الحراري والباركود</button><router-link v-if="invoice.is_done" :to="`/referral-portal/reports/${invoice.id}?form=1`" class="px-5 py-3 rounded-xl border border-teal-700 text-teal-700">التقرير الطبي والطباعة</router-link></div>
   <table class="w-full text-right"><thead><tr><th class="p-3">الفحص</th><th class="p-3">الحالة</th></tr></thead><tbody><tr v-for="(item,index) in [...invoice.tests,...invoice.cultures,...invoice.packages,...invoice.test_groups]" :key="index" class="border-t"><td class="p-3">{{item.name || item.group_name}}</td><td class="p-3">{{item.is_done ? 'مكتمل' : 'قيد الإجراء'}}</td></tr></tbody></table>
  </div>
  <PrintInvoice />
 </div>
</template>
