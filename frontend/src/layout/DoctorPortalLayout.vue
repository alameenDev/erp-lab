<script setup>
import { useAuthStore } from '@/store/modules/auth';
const authStore = useAuthStore();
const doctor = JSON.parse(localStorage.getItem('user') || '{}');
</script>

<template>
  <div class="min-h-screen bg-slate-50" dir="rtl">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4">
        <router-link to="/doctor-portal" class="text-lg font-bold text-teal-800">بوابة الطبيب</router-link>
        <div class="flex items-center gap-3 text-sm"><span class="text-slate-600">{{ doctor.name }}</span><button type="button" @click="authStore.logout()" class="rounded-lg border border-slate-200 px-3 py-2 text-red-600 hover:bg-red-50">خروج</button></div>
      </div>
    </header>
    <router-view />
  </div>
</template>
