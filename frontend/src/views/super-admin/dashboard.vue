<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat } from "@/utils/helper";
import { Bar, Line, Doughnut } from "vue-chartjs";
import {
     Chart as ChartJS,
     Title,
     Tooltip,
     Legend,
     BarElement,
     CategoryScale,
     LinearScale,
     ArcElement,
     PointElement,
     LineElement,
     Filler,
} from "chart.js";

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement, PointElement, LineElement, Filler);

const store = useSuperAdminStore();
const { kpis, revenueTrend, labComparison } = storeToRefs(store);
const isLoading = ref(true);

const lang = computed(() => localStorage.getItem("locale") || "ar");

const kpiCards = computed(() => [
     {
          label: t("total_labs"),
          value: kpis.value.total_labs,
          change: kpis.value.changes?.new_labs || 0,
          icon: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4",
          color: "blue",
     },
     {
          label: t("total_users"),
          value: kpis.value.total_users,
          icon: "M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z",
          color: "indigo",
     },
     {
          label: t("total_invoices"),
          value: kpis.value.total_invoices,
          change: kpis.value.changes?.invoices_this_month || 0,
          icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
          color: "emerald",
     },
     {
          label: t("total_revenue"),
          value: commaFormat(kpis.value.total_revenue),
          change: kpis.value.changes?.revenue_this_month || 0,
          icon: "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
          color: "amber",
          isCurrency: true,
     },
     {
          label: t("active_subscriptions"),
          value: kpis.value.active_subscriptions,
          icon: "M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z",
          color: "green",
     },
     {
          label: t("total_patients"),
          value: kpis.value.total_patients,
          icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z",
          color: "purple",
     },
]);

const revenueTrendChart = computed(() => {
     const labels = revenueTrend.value.map((item) => item.month);
     return {
          data: {
               labels,
               datasets: [
                    {
                         label: t("total_revenue"),
                         data: revenueTrend.value.map((item) => item.revenue),
                         borderColor: "#3b82f6",
                         backgroundColor: "rgba(59,130,246,0.1)",
                         fill: true,
                         tension: 0.4,
                    },
                    {
                         label: t("total_paid"),
                         data: revenueTrend.value.map((item) => item.paid),
                         borderColor: "#10b981",
                         backgroundColor: "rgba(16,185,129,0.1)",
                         fill: true,
                         tension: 0.4,
                    },
               ],
          },
          options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: { legend: { position: "top" } },
               scales: { y: { beginAtZero: true } },
          },
     };
});

const labComparisonChart = computed(() => {
     const top10 = labComparison.value.slice(0, 10);
     return {
          data: {
               labels: top10.map((l) => l.lab_name),
               datasets: [
                    {
                         label: t("total_revenue"),
                         data: top10.map((l) => l.revenue),
                         backgroundColor: "#3b82f6",
                    },
                    {
                         label: t("total_paid"),
                         data: top10.map((l) => l.paid),
                         backgroundColor: "#10b981",
                    },
               ],
          },
          options: {
               responsive: true,
               maintainAspectRatio: false,
               indexAxis: "y",
               plugins: { legend: { position: "top" } },
               scales: { x: { beginAtZero: true } },
          },
     };
});

const subscriptionStatusChart = computed(() => {
     const active = kpis.value.active_subscriptions || 0;
     const total = kpis.value.total_labs || 1;
     const noSub = total - active;
     return {
          data: {
               labels: [t("subscription_active"), t("no_subscription")],
               datasets: [
                    {
                         data: [active, noSub > 0 ? noSub : 0],
                         backgroundColor: ["#10b981", "#94a3b8"],
                    },
               ],
          },
          options: {
               responsive: true,
               maintainAspectRatio: false,
               plugins: { legend: { position: "bottom" } },
          },
     };
});

onMounted(async () => {
     await Promise.all([store.GetKpis(), store.GetRevenueTrend(), store.GetLabComparison()]);
     isLoading.value = false;
});
</script>

<template>
     <div class="space-y-6">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 rounded-2xl p-6 text-white">
               <h1 class="text-2xl font-bold">{{ t("super_admin_panel") }}</h1>
               <p class="text-blue-200 mt-1">{{ t("system_overview") }}</p>
          </div>

          <!-- KPI Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
               <div
                    v-for="card in kpiCards"
                    :key="card.label"
                    class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition-shadow"
               >
                    <div class="flex items-start justify-between">
                         <div>
                              <p class="text-sm text-gray-500">{{ card.label }}</p>
                              <p class="text-2xl font-bold mt-1 text-gray-900">
                                   {{ card.value }}
                                   <span v-if="card.isCurrency" class="text-sm font-normal text-gray-400">IQD</span>
                              </p>
                              <p v-if="card.change" class="text-xs text-green-600 mt-1">
                                   +{{ commaFormat(card.change) }} {{ t("new_labs_this_month") }}
                              </p>
                         </div>
                         <div
                              class="w-12 h-12 rounded-xl flex items-center justify-center"
                              :class="{
                                   'bg-blue-50 text-blue-500': card.color === 'blue',
                                   'bg-indigo-50 text-indigo-500': card.color === 'indigo',
                                   'bg-emerald-50 text-emerald-500': card.color === 'emerald',
                                   'bg-amber-50 text-amber-500': card.color === 'amber',
                                   'bg-green-50 text-green-500': card.color === 'green',
                                   'bg-purple-50 text-purple-500': card.color === 'purple',
                              }"
                         >
                              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                              </svg>
                         </div>
                    </div>
               </div>
          </div>

          <!-- Charts Row 1 -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
               <!-- Revenue Trend -->
               <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t("revenue_trend") }}</h3>
                    <div class="h-72">
                         <Line
                              v-if="revenueTrend.length"
                              :data="revenueTrendChart.data"
                              :options="revenueTrendChart.options"
                         />
                         <div v-else class="flex items-center justify-center h-full text-gray-400">
                              {{ t("loading") }}
                         </div>
                    </div>
               </div>

               <!-- Subscription Status -->
               <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t("subscription_status") }}</h3>
                    <div class="h-72">
                         <Doughnut
                              v-if="!isLoading"
                              :data="subscriptionStatusChart.data"
                              :options="subscriptionStatusChart.options"
                         />
                    </div>
               </div>
          </div>

          <!-- Charts Row 2 — Lab Comparison -->
          <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
               <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t("top_labs_by_revenue") }}</h3>
               <div class="h-96">
                    <Bar
                         v-if="labComparison.length"
                         :data="labComparisonChart.data"
                         :options="labComparisonChart.options"
                    />
                    <div v-else class="flex items-center justify-center h-full text-gray-400">
                         {{ t("loading") }}
                    </div>
               </div>
          </div>

          <!-- Quick Actions -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
               <router-link
                    to="/super-admin/labs"
                    class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4"
               >
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center">
                         <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                         </svg>
                    </div>
                    <div>
                         <p class="font-semibold text-gray-900">{{ t("lab_management") }}</p>
                         <p class="text-sm text-gray-500">{{ kpis.total_labs }} {{ t("total_labs") }}</p>
                    </div>
               </router-link>

               <router-link
                    to="/super-admin/subscriptions"
                    class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4"
               >
                    <div class="w-12 h-12 rounded-xl bg-green-50 text-green-500 flex items-center justify-center">
                         <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                         </svg>
                    </div>
                    <div>
                         <p class="font-semibold text-gray-900">{{ t("subscriptions") }}</p>
                         <p class="text-sm text-gray-500">{{ kpis.active_subscriptions }} {{ t("subscription_active") }}</p>
                    </div>
               </router-link>

               <router-link
                    to="/super-admin/activity-log"
                    class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4"
               >
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-500 flex items-center justify-center">
                         <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                         </svg>
                    </div>
                    <div>
                         <p class="font-semibold text-gray-900">{{ t("system_activity_log") }}</p>
                    </div>
               </router-link>
          </div>
     </div>
</template>
