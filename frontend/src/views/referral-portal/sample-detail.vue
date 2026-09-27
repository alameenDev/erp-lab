<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { $http } from "@/plugins/axios";

const route = useRoute();
const router = useRouter();
const invoice = ref(null);
const profile = ref(null);
const loading = ref(true);

const load = async () => {
  loading.value = true;
  try {
    const [invRes, profRes] = await Promise.all([
      $http.get(`/referral-portal/invoices/${route.params.id}`),
      $http.get("/referral-portal/profile"),
    ]);
    invoice.value = invRes.data;
    profile.value = profRes.data;
  } finally {
    loading.value = false;
  }
};

const printResult = async () => {
  await nextTick();
  const el = document.getElementById("referral-result-print");
  if (!el) return;
  const frame = document.createElement("iframe");
  frame.style.cssText = "position:absolute;width:0;height:0;border:none;";
  document.body.appendChild(frame);
  const doc = frame.contentWindow.document;
  doc.open();
  doc.write(`<html><head><title>Result</title><style>
    @page{size:A4;margin:15mm}
    body{font-family:Tajawal,Arial,sans-serif;direction:rtl}
    .hdr{display:flex;align-items:center;gap:12px;border-bottom:2px solid #0f766e;padding-bottom:10px;margin-bottom:16px}
    .hdr img{width:56px;height:56px;object-fit:cover;border-radius:8px}
    .hdr h1{font-size:18px;margin:0;color:#0f766e}
    .hdr p{font-size:12px;margin:2px 0 0;color:#666}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #e5e7eb;padding:8px;font-size:13px;text-align:right}
    th{background:#f8fafc}
  </style></head><body>${el.innerHTML}</body></html>`);
  doc.close();
  frame.onload = () => {
    frame.contentWindow.focus();
    frame.contentWindow.print();
    setTimeout(() => document.body.removeChild(frame), 1000);
  };
};

const statusLabel = (s) => (s === "ready" ? "جاهزة" : "قيد الفحص");

onMounted(load);
</script>

<template>
  <div class="max-w-2xl mx-auto p-4" dir="rtl">
    <button @click="router.push('/referral-portal')" class="text-sm text-teal-700 mb-3">← رجوع</button>

    <div v-if="loading" class="text-center text-gray-400 py-10">جاري التحميل...</div>

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
            {{ t.is_done ? t.result || "—" : "قيد الفحص" }}
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
        <p><strong>رقم العينة:</strong> {{ invoice.barcode }}</p>
        <table>
          <thead>
            <tr>
              <th>الفحص</th>
              <th>النتيجة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(t, i) in invoice.tests" :key="i">
              <td>{{ t.name }}</td>
              <td>{{ t.result || "—" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
