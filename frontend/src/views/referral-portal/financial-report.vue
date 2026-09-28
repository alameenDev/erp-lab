<script setup>
import { computed, onMounted, ref } from 'vue';
import { $http } from '@/plugins/axios';

const connections = ref([]);
const labs = ref([]);
const invoices = ref([]);
const loading = ref(false);
const error = ref('');
const page = ref(1);
const lastPage = ref(1);
const totalInvoices = ref(0);
const expanded = ref(null);
const filters = ref({ lab_id: '', from: '', to: '', status: '' });
const money = value => new Intl.NumberFormat('ar-IQ').format(Number(value || 0)) + ' د.ع';
const date = value => value ? new Date(value).toLocaleDateString('ar-IQ') : '—';
const statusLabel = value => ({ paid: 'مسددة', partial: 'مسددة جزئياً', unpaid: 'غير مسددة' })[value] || '—';
const statusClass = value => ({
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  partial: 'bg-amber-50 text-amber-700 border-amber-200',
  unpaid: 'bg-rose-50 text-rose-700 border-rose-200',
})[value];
const totals = computed(() => labs.value.reduce((sum, lab) => ({
  count: sum.count + Number(lab.invoice_count || 0),
  due: sum.due + Number(lab.total_due || 0),
  paid: sum.paid + Number(lab.total_paid || 0),
  balance: sum.balance + Number(lab.total_balance || 0),
}), { count: 0, due: 0, paid: 0, balance: 0 }));

async function load() {
  if (loading.value) return;
  loading.value = true;
  error.value = '';
  try {
    const params = { page: page.value };
    for (const [key, value] of Object.entries(filters.value)) if (value) params[key] = value;
    const { data } = await $http.get('/referral-portal/financial-report', { params });
    labs.value = data.labs || [];
    invoices.value = data.invoices?.data || [];
    lastPage.value = data.invoices?.last_page || 1;
    totalInvoices.value = data.invoices?.total || 0;
    expanded.value = null;
  } catch (e) {
    error.value = e.response?.data?.message || 'تعذر تحميل التقرير المالي';
  } finally {
    loading.value = false;
  }
}
function applyFilters() { page.value = 1; load(); }
function clearFilters() {
  filters.value = { lab_id: '', from: '', to: '', status: '' };
  page.value = 1;
  load();
}
function goToPage(next) {
  if (next < 1 || next > lastPage.value || loading.value) return;
  page.value = next;
  load();
}
onMounted(async () => {
  try {
    const { data } = await $http.get('/referral-portal/connections');
    connections.value = data || [];
  } catch { /* The report endpoint still returns an actionable error. */ }
  load();
});
</script>

<template>
  <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 pb-16" dir="rtl">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-sm font-semibold text-teal-700">بوابة الإحالة / الحسابات</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">التقرير المالي للمختبرات</h1>
        <p class="mt-2 text-sm text-slate-500">مطالبات المختبرات المرتبطة بك والدفعات المسجلة لديها، مع تفاصيل كل فاتورة.</p>
      </div>
      <button type="button" @click="load" :disabled="loading" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">تحديث البيانات</button>
    </div>

    <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilters">
      <label class="text-xs font-semibold text-slate-600">المختبر
        <select v-model="filters.lab_id" class="mt-1 block h-10 w-full rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-800"><option value="">كل المختبرات</option><option v-for="lab in connections" :key="lab.lab_id" :value="lab.lab_id">{{ lab.lab_name || 'مختبر' }}</option></select>
      </label>
      <label class="text-xs font-semibold text-slate-600">من تاريخ
        <input v-model="filters.from" type="date" class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-2 text-sm" />
      </label>
      <label class="text-xs font-semibold text-slate-600">إلى تاريخ
        <input v-model="filters.to" type="date" :min="filters.from || undefined" class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-2 text-sm" />
      </label>
      <label class="text-xs font-semibold text-slate-600">حالة التسديد
        <select v-model="filters.status" class="mt-1 block h-10 w-full rounded-lg border border-slate-200 bg-white px-2 text-sm"><option value="">الكل</option><option value="unpaid">غير مسددة</option><option value="partial">مسددة جزئياً</option><option value="paid">مسددة</option></select>
      </label>
      <div class="flex items-end gap-2">
        <button type="submit" :disabled="loading" class="h-10 flex-1 rounded-lg bg-teal-700 px-3 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">عرض التقرير</button>
        <button type="button" @click="clearFilters" :disabled="loading" class="h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-600">مسح</button>
      </div>
    </form>

    <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="ملخص المبالغ">
      <div v-for="card in [
        { title: 'عدد الفواتير', value: totals.count, tone: 'text-slate-900' },
        { title: 'إجمالي مطالبة المختبرات', value: money(totals.due), tone: 'text-slate-900' },
        { title: 'المسدد للمختبرات', value: money(totals.paid), tone: 'text-emerald-700' },
        { title: 'المتبقي للمختبرات', value: money(totals.balance), tone: 'text-rose-700' },
      ]" :key="card.title" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-slate-500">{{ card.title }}</p><strong class="mt-2 block text-xl" :class="card.tone">{{ card.value }}</strong>
      </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">الملخص حسب المختبر</h2><p class="mt-1 text-xs text-slate-500">الأرقام تتبع الفلاتر المختارة وتشمل كل الفواتير المطابقة، وليس الصفحة الحالية فقط.</p></div>
      <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-right text-sm">
        <thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="p-4">المختبر</th><th class="p-4">الفواتير</th><th class="p-4">المطلوب</th><th class="p-4">المسدد</th><th class="p-4">المتبقي</th></tr></thead>
        <tbody class="divide-y divide-slate-100"><tr v-for="lab in labs" :key="lab.lab_id"><th class="p-4 font-semibold text-slate-900">{{ lab.lab_name }}</th><td class="p-4">{{ lab.invoice_count }}</td><td class="p-4">{{ money(lab.total_due) }}</td><td class="p-4 text-emerald-700">{{ money(lab.total_paid) }}</td><td class="p-4 font-bold text-rose-700">{{ money(lab.total_balance) }}</td></tr>
          <tr v-if="!labs.length"><td colspan="5" class="p-6 text-center text-slate-500">لا توجد مختبرات أو فواتير مطابقة.</td></tr></tbody>
      </table></div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div><h2 class="font-bold text-slate-900">تفاصيل الفواتير</h2><p class="mt-1 text-xs text-slate-500">{{ totalInvoices }} فاتورة مطابقة · اضغط تفاصيل الفاتورة لعرض الفحوصات والدفعات.</p></div>
        <span v-if="loading" class="text-xs text-teal-700">جاري التحميل...</span>
      </div>
      <div class="overflow-x-auto"><table class="w-full min-w-[950px] text-right text-sm">
        <thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="p-4">الفاتورة / التاريخ</th><th class="p-4">المختبر</th><th class="p-4">المريض</th><th class="p-4">المطلوب</th><th class="p-4">المدفوع</th><th class="p-4">المتبقي</th><th class="p-4">الحالة</th><th class="p-4">التفاصيل</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <template v-for="invoice in invoices" :key="invoice.id">
            <tr class="hover:bg-slate-50/70">
              <td class="p-4"><strong class="block text-slate-900">#{{ invoice.id }} · {{ invoice.barcode }}</strong><span class="text-xs text-slate-500">{{ date(invoice.created_at) }}</span></td>
              <td class="p-4 font-medium">{{ invoice.lab_name || '—' }}</td><td class="p-4">{{ invoice.patient_name || '—' }}</td>
              <td class="p-4">{{ money(invoice.total_due) }}</td><td class="p-4 text-emerald-700">{{ money(invoice.total_paid) }}</td><td class="p-4 font-semibold text-rose-700">{{ money(invoice.balance) }}</td>
              <td class="p-4"><span class="inline-block whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(invoice.payment_status)">{{ statusLabel(invoice.payment_status) }}</span></td>
              <td class="p-4"><button type="button" :aria-expanded="expanded === invoice.id" @click="expanded = expanded === invoice.id ? null : invoice.id" class="font-semibold text-teal-700 hover:underline">{{ expanded === invoice.id ? 'إخفاء' : 'عرض' }}</button></td>
            </tr>
            <tr v-if="expanded === invoice.id" class="bg-slate-50/70"><td colspan="8" class="p-5">
              <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4"><h3 class="mb-3 font-bold text-slate-800">فحوصات الفاتورة</h3><div v-for="(item, i) in invoice.items" :key="i" class="flex justify-between gap-3 border-t border-slate-100 py-2 text-xs"><span>{{ item.name }}</span><strong>{{ money(item.price) }}</strong></div><p v-if="!invoice.items?.length" class="text-xs text-slate-500">لا توجد تفاصيل فحوصات.</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><h3 class="mb-3 font-bold text-slate-800">دفعات المختبر المسجلة</h3><div v-for="(payment, i) in invoice.payments" :key="i" class="flex justify-between gap-3 border-t border-slate-100 py-2 text-xs"><span>{{ date(payment.date) }} · {{ payment.method || 'طريقة غير محددة' }}</span><strong>{{ money(payment.amount) }}</strong></div><p v-if="!invoice.payments?.length" class="text-xs text-slate-500">لم تُسجل دفعات لهذه الفاتورة لدى المختبر.</p></div>
              </div>
              <p v-if="invoice.customer_invoice" class="mt-3 rounded-lg bg-blue-50 p-3 text-xs text-blue-900">حساب المريض لدى جهة الإحالة (منفصل عن مطالبة المختبر): الإجمالي {{ money(invoice.customer_invoice.total) }} · المحصل {{ money(invoice.customer_invoice.collected) }}.</p>
              <p v-else class="mt-3 text-xs text-slate-500">تفاصيل تحصيل المريض غير محفوظة لهذه الفاتورة القديمة.</p>
              <router-link :to="`/referral-portal/${invoice.id}`" class="mt-3 inline-block text-xs font-semibold text-teal-700 hover:underline">فتح الفاتورة والتقرير الطبي</router-link>
            </td></tr>
          </template>
          <tr v-if="!loading && !invoices.length"><td colspan="8" class="p-8 text-center text-slate-500">لا توجد فواتير مطابقة للفلاتر.</td></tr>
        </tbody>
      </table></div>
      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-sm">
        <button type="button" :disabled="page <= 1 || loading" @click="goToPage(page - 1)" class="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-40">السابق</button>
        <span>صفحة {{ page }} من {{ lastPage }}</span>
        <button type="button" :disabled="page >= lastPage || loading" @click="goToPage(page + 1)" class="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-40">التالي</button>
      </div>
    </section>
    <p class="text-xs text-slate-500">حالة التسديد تعتمد على الدفعات التي سجلها المختبر المستلم في النظام. إذا تم دفع مبلغ خارج النظام ولم يُسجل، سيظهر هنا كرصيد غير مسدد.</p>
  </main>
</template>
