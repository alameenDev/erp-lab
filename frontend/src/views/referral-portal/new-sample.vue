<script setup>
import { ref, onMounted } from 'vue';
import { $http } from '@/plugins/axios';
import { referralWorkspace } from '@/utils/referralWorkspace';
import InvoiceForm from '@/views/invoices/form.vue';
const connections = ref([]), destination = ref(null), started = ref(false), error = ref('');
onMounted(async () => {
 try { const {data} = await $http.get('/referral-portal/connections'); connections.value = data; if(data.length === 1) destination.value = data[0].lab_id; }
 catch(e) { error.value = e.response?.data?.message || 'تعذر تحميل المختبرات'; }
});
function start() { referralWorkspace.destinationLabId = destination.value; started.value = true; }
</script>
<template>
 <div class="max-w-7xl mx-auto p-4" dir="rtl">
  <div v-if="!started" class="bg-white border rounded-2xl p-6 space-y-4">
   <h1 class="font-bold text-xl">فاتورة إحالة جديدة</h1>
   <p>اختر المختبر الذي سيجري الفحوصات. تظهر الفحوصات والأسعار المتاحة لجهة إحالتك فقط.</p>
   <p v-if="error" role="alert" class="text-red-700">{{error}}</p>
   <label class="block">المختبر المستلم <select v-model="destination" class="block w-full border rounded-lg p-3"><option :value="null">اختر المختبر</option><option v-for="lab in connections" :key="lab.lab_id" :value="lab.lab_id">{{lab.lab_name}}</option></select></label>
   <button :disabled="!destination" @click="start" class="bg-teal-700 text-white rounded-lg px-6 py-3 disabled:opacity-40">إنشاء الفاتورة</button>
  </div>
  <InvoiceForm v-else />
 </div>
</template>
