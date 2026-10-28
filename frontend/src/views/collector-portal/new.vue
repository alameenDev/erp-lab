<script setup>
import { inject,ref } from 'vue';
import { collectorWorkspace } from '@/utils/collectorWorkspace';
import InvoiceForm from '@/views/invoices/form.vue';
const context=inject('collectorContext'),source=ref(null),started=ref(false);
function start(){collectorWorkspace.sourceLabId=source.value;started.value=true;}
</script>
<template><div class="collection-panel"><div v-if="!started" class="collection-card"><h2>إرسال فاتورة إلى المختبر</h2><p>تُراجع المعلومات في قائمة الانتظار قبل اعتماد الفاتورة وإدخال النتائج.</p><label class="my-4">جهة المريض<select v-model="source"><option :value="null">مريض مباشر / سحب منزلي</option><option v-for="lab in context.sources" :key="lab.id" :value="lab.id">{{lab.name}}</option></select></label><button @click="start">إدخال بيانات الفاتورة</button></div><InvoiceForm v-else /></div></template>
