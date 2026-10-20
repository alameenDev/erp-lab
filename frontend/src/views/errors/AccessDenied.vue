<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/store/modules/auth';
import { landingPage, readStoredJson } from '@/utils/accessControl';
const router = useRouter();
const auth = useAuthStore();
async function refresh() {
  await auth.refreshAccess();
  await router.replace(landingPage(readStoredJson('user'), auth.rolePermissions));
}
</script>

<template>
  <section dir="rtl" class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm" role="status">
    <h1 class="mb-3 text-xl font-bold text-slate-800">صلاحيات الوصول</h1>
    <p class="leading-8 text-slate-600">أنت مسجل دخولك بنجاح. هذه الصفحة غير متاحة ضمن صلاحيات حسابك. اختر صفحة مسموحة من القائمة، أو اطلب من مدير المختبر تحديث صلاحياتك.</p>
    <button type="button" class="mt-6 rounded-lg bg-teal-700 px-5 py-3 text-white" @click="refresh">تحديث الصلاحيات والمتابعة</button>
  </section>
</template>
