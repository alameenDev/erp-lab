<script setup>
import { ref, onMounted } from "vue";
import { $http } from "@/plugins/axios";
import { useRouter } from "vue-router";

const router = useRouter();
const invoices = ref([]);
const unseenCount = ref(0);
const loading = ref(true);

const load = async () => {
  loading.value = true;
  try {
    const { data } = await $http.get("/referral-portal/invoices");
    invoices.value = data.invoices?.data || [];
    unseenCount.value = data.unseen_ready_count || 0;
  } finally {
    loading.value = false;
  }
};

const openInvoice = (id) => router.push(`/referral-portal/${id}`);

const statusLabel = (s) => (s === "ready" ? "جاهزة" : "قيد الفحص");
const statusClass = (s) => (s === "ready" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700");

onMounted(load);
</script>

<template>
  <div class="max-w-4xl mx-auto p-4" dir="rtl">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-bold text-gray-800">العينات المرسلة</h1>
      <span v-if="unseenCount" class="bg-teal-600 text-white text-xs font-bold rounded-full px-3 py-1">
        {{ unseenCount }} نتيجة جديدة جاهزة
      </span>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-10">جاري التحميل...</div>
    <div v-else-if="!invoices.length" class="text-center text-gray-400 py-10">
      ما عندك عينات مرسلة بعد. اضغط "+ عينة جديدة" من الأعلى للبدء.
    </div>

    <div v-else class="space-y-2">
      <button
        v-for="inv in invoices"
        :key="inv.id"
        @click="openInvoice(inv.id)"
        class="w-full text-right bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between hover:border-teal-300 transition"
      >
        <div>
          <div class="font-bold text-gray-800 flex items-center gap-2">
            {{ inv.patient_name || "مريض" }}
            <span v-if="inv.is_new" class="w-2 h-2 rounded-full bg-teal-500"></span>
          </div>
          <div class="text-xs text-gray-400 mt-1">{{ inv.barcode }}</div>
        </div>
        <span :class="['text-xs font-bold px-3 py-1 rounded-full', statusClass(inv.status)]">
          {{ statusLabel(inv.status) }}
        </span>
      </button>
    </div>
  </div>
</template>
