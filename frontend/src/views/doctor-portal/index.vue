<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { $http } from '@/plugins/axios';

const router = useRouter();
const patients = ref([]);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const date = value => value ? new Date(value).toLocaleDateString('ar-IQ') : '—';

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await $http.get('/doctor-portal/patients', { params: { page: page.value, search: search.value.trim() } });
    patients.value = data.data || [];
    lastPage.value = data.last_page || 1;
    total.value = data.total || 0;
  } catch (e) { error.value = e.response?.data?.message || 'تعذر تحميل المرضى'; }
  finally { loading.value = false; }
}
function find() { page.value = 1; load(); }
function changePage(next) { if (next < 1 || next > lastPage.value) return; page.value = next; load(); }
onMounted(load);
</script>

<template>
  <main class="mx-auto max-w-7xl space-y-6 px-4 py-7" dir="rtl">
    <div><p class="text-sm font-semibold text-teal-700">بوابة الطبيب</p><h1 class="mt-1 text-2xl font-bold text-slate-900">المرضى المحالون من قبلك</h1><p class="mt-2 text-sm text-slate-500">اختر اسم المريض لمراجعة جميع فواتيره ونتائجه المسجلة بإحالتك.</p></div>
    <form class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="find">
      <input v-model="search" type="search" placeholder="ابحث باسم المريض أو رقمه..." class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-teal-600" />
      <button type="submit" :disabled="loading" class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">بحث</button>
    </form>
    <p v-if="error" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">قائمة المرضى</h2><span class="text-sm text-slate-500">{{ total }} مريض</span></div>
      <div v-if="loading" class="p-10 text-center text-slate-500">جاري تحميل المرضى...</div>
      <div v-else-if="!patients.length" class="p-10 text-center text-slate-500">لا توجد إحالات مطابقة.</div>
      <div v-else class="divide-y divide-slate-100">
        <button v-for="patient in patients" :key="patient.id" type="button" @click="router.push(`/doctor-portal/patients/${patient.id}`)" class="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-4 text-right hover:bg-teal-50/50">
          <span><strong class="block text-slate-900">{{ patient.name || 'مريض' }}</strong><small class="mt-1 block text-slate-500">رقم المريض: {{ patient.code || '—' }} · آخر زيارة: {{ date(patient.last_visit) }}</small></span>
          <span class="flex items-center gap-3 text-xs"><span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{{ patient.invoice_count }} فاتورة</span><span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">{{ patient.ready_count }} نتيجة جاهزة</span><span class="font-semibold text-teal-700">عرض النتائج ←</span></span>
        </button>
      </div>
      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-sm">
        <button type="button" :disabled="page <= 1 || loading" @click="changePage(page - 1)" class="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-40">السابق</button><span>{{ page }} / {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" @click="changePage(page + 1)" class="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-40">التالي</button>
      </div>
    </section>
  </main>
</template>
