<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { usePrint } from "@/composables/usePrint";
import { $http } from "@/plugins/axios";

const route = useRoute();
const { printWithCustomContent } = usePrint();
const router = useRouter();
const invoice = ref(null);
const profile = ref(null);
const loading = ref(true);
const error = ref('');

const load = async () => {
  loading.value = true;
  try {
    const [invRes, profRes] = await Promise.all([
      $http.get(`/referral-portal/invoices/${route.params.id}`),
      $http.get("/referral-portal/profile"),
    ]);
    invoice.value = invRes.data;
    profile.value = profRes.data;
  } catch (e) { error.value = e?.response?.data?.message || 'تعذر تحميل التقرير'; } finally {
    loading.value = false;
  }
};

const printResult = async () => {
  await nextTick();
  const el = document.getElementById("referral-result-print");
  if (!el) return;
  await printWithCustomContent(el.innerHTML, `
    @page{size:A4;margin:15mm} body{font-family:Arial,sans-serif;direction:rtl;font-size:12px}
    .hdr{display:flex;gap:12px;border-bottom:2px solid #0f766e;padding-bottom:10px}
    .hdr img{width:56px;height:56px;object-fit:contain}.hdr h1{font-size:18px}
    table{width:100%;border-collapse:collapse;margin-top:12px} th,td{border:1px solid #ddd;padding:8px;text-align:right;white-space:pre-wrap}
    thead{display:table-header-group} tr{break-inside:avoid} th{background:#f1f5f9}
  `, 'Medical report');
};

const statusLabel = (s) => (s === "ready" ? "جاهزة" : "قيد الفحص");

onMounted(load);
</script>

<template>
  <div class="max-w-2xl mx-auto p-4" dir="rtl">
    <button @click="router.push('/referral-portal')" class="text-sm text-teal-700 mb-3">← رجوع</button>

    <div v-if="loading" class="text-center text-gray-400 py-10">جاري التحميل...</div>

    <p v-else-if="error" role="alert" class="text-red-700">{{ error }}</p>
    <template v-else-if="invoice">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
        <div class="flex items-center justify-between mb-3">
          <div class="font-bold text-gray-800">{{ invoice.patient?.name }}</div>
          <span class="text-xs font-bold px-3 py-1 rounded-full bg-teal-50 text-teal-700">{{ statusLabel(invoice.status) }}</span>
        </div>
        <div class="text-xs text-gray-400">{{ invoice.barcode }}</div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50 mb-4">
        <div v-for="(t, i) in invoice.tests" :key="i" class="flex items-center justify-between px-4 py-3">
          <span class="text-sm text-gray-700">{{ t.name }}</span>
          <span class="text-sm font-bold" :class="t.is_done ? 'text-emerald-700' : 'text-gray-400'">
            {{ invoice.status === 'ready' && t.is_done ? t.result ?? "—" : "قيد الفحص" }}
          </span>
        </div>
      </div>

      <button
        v-if="invoice.status === 'ready'"
        @click="printResult"
        class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl"
      >
        🖨 طباعة النتيجة الكاملة
      </button>
      <p v-else class="text-center text-gray-400 text-sm">النتيجة لسه قيد الفحص بالمختبر الرئيسي</p>

      <!-- Hidden print template, branded with THIS referral lab's own identity -->
      <div id="referral-result-print" class="hidden">
        <div class="hdr">
          <img v-if="profile?.logo" :src="profile.logo" />
          <div>
            <h1>{{ profile?.display_name || "المختبر" }}</h1>
            <p v-if="profile?.phone">{{ profile.phone }}</p>
            <p v-if="profile?.address">{{ profile.address }}</p>
          </div>
        </div>
        <p><strong>المريض:</strong> {{ invoice.patient?.name }}</p>
        <p><strong>رمز المريض:</strong> {{ invoice.patient?.code }} — <strong>الميلاد:</strong> {{ invoice.patient?.dob || 'غير مسجل' }} — <strong>الجنس:</strong> {{ invoice.patient?.gender || 'غير مسجل' }}</p>
        <p><strong>مختبر إجراء الفحص:</strong> {{ invoice.performing_lab }} — <strong>تاريخ التسجيل:</strong> {{ invoice.created_at }} — <strong>تاريخ النتيجة:</strong> {{ invoice.result_date || 'غير مسجل' }}</p>
        <p><strong>رقم العينة:</strong> {{ invoice.barcode }}</p>
        <table>
          <thead>
            <tr>
              <th>الفحص</th>
              <th>النتيجة</th><th>الوحدة</th><th>المديات المرجعية حسب الفئة</th><th>الملاحظات</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(t, i) in invoice.tests" :key="i"><tr>
              <td>{{ t.name }}</td>
              <td>{{ t.result ?? "—" }}<div>{{ t.result_status_text }}</div></td>
              <td>{{ t.unit || '—' }}</td>
              <td><div v-for="(r, n) in t.reference_ranges" :key="n">{{ r.gender }} / {{ r.age_from ?? '—' }}–{{ r.age_to ?? '—' }} {{ r.age_unit }}: {{ r.from ?? '—' }}–{{ r.to ?? '—' }}<div>{{ r.notes }}</div></div><span v-if="!t.reference_ranges?.length">غير مسجل</span></td>
              <td>{{ t.comment || '—' }}</td>
            </tr><tr v-for="(s, j) in t.sub_tests" :key="`sub-${j}`"><td>{{ s.name }}</td><td>{{ s.value ?? '—' }}</td><td>{{ s.unit || '—' }}</td><td>راجع المدى الخاص بالفحص</td><td>{{ s.comment || '—' }}</td></tr></template>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
