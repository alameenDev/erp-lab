<script setup>
import './collection.css';
import { ref, onMounted, provide } from 'vue';
import { $http } from '@/plugins/axios';
import { useAuthStore } from '@/store/modules/auth';
import { useCollectorPortalApp } from '@/composables/useCollectorPortalApp';
const context=ref(null), error=ref(''), id=ref(null);
const app=useCollectorPortalApp(id); provide('collectorContext',context); provide('collectorApp',app);
onMounted(async()=>{try{context.value=(await $http.get('/collector-portal/context')).data;id.value=context.value.collector.id;}catch(e){error.value=e.response?.data?.message||'تعذر الاتصال بالمختبر';}});
const tabs=[['tasks','الطلبات'],['new','فاتورة جديدة'],['submissions','فواتيري'],['statistics','إحصائياتي'],['notifications','الإشعارات'],['app','التطبيق']];
</script>
<template><div class="collector-shell" dir="rtl"><header><div><small>Digital Lab · ساحب العينات</small><h1>{{context?.collector.name || 'مرحباً بك'}}</h1><p>{{context?.lab.name}}</p></div><button @click="useAuthStore().logout()">خروج</button></header><main><p v-if="error" role="alert">{{error}}</p><RouterView v-if="context" /></main><nav><RouterLink v-for="[key,label] in tabs" :key="key" :to="'/collector-portal/'+key">{{label}}<b v-if="key==='notifications'&&app.unread"> {{app.unread}}</b></RouterLink></nav></div></template>
