<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useReportsStore } from "@/store/modules/reports";
import { useUsersStore } from "@/store/modules/users";
import { useAuthStore } from "@/store/modules/auth";
import { t } from "@/utils/helper";
import { $http } from "@/plugins/axios";
import { Bar, Doughnut, Line, Pie } from "vue-chartjs";
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

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
  PointElement,
  LineElement,
  Filler
);

const router = useRouter();
const reportsStore = useReportsStore();
const usersStore = useUsersStore();
const authStore = useAuthStore();

const {
  record,
  summary,
  dailyRevenue,
  testsStats,
  culturesStats,
  mostRequestedTests,
  doctorReferrals,
  labToLab,
  accountingData,
  todayStats,
  comparison,
  recentInvoices,
} = storeToRefs(reportsStore);

const { branches } = storeToRefs(usersStore);

const branchData = ref({});
const isLoading = ref(true);
const activeTab = ref("overview");

// Subscription state
const subscriptionData = ref(null);
const subscriptionExpired = ref(false);
const subscriptionDaysLeft = ref(0);
const subscriptionMessage = ref("");
const salesPhone = ref("07838334835");

const User = computed(() => {
  try {
    return JSON.parse(localStorage.getItem("user"));
  } catch {
    return null;
  }
});

const Role = computed(() => User.value?.role_id);
const lang = computed(() => localStorage.getItem("locale") || "ar");

const currentDate = computed(() => {
  const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
  const locale = lang.value === "ar" ? "ar-u-nu-latn" : "en-US";
  return new Date().toLocaleDateString(locale, options);
});

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return t("good_morning");
  if (hour < 18) return t("good_afternoon");
  return t("good_evening");
});

// Color palette
const colors = {
  primary: "#14b8a6",
  primaryLight: "rgba(20, 184, 166, 0.15)",
  success: "#22c55e",
  successLight: "rgba(34, 197, 94, 0.15)",
  warning: "#f59e0b",
  warningLight: "rgba(245, 158, 11, 0.15)",
  danger: "#ef4444",
  dangerLight: "rgba(239, 68, 68, 0.15)",
  blue: "#3b82f6",
  blueLight: "rgba(59, 130, 246, 0.15)",
  purple: "#8b5cf6",
  purpleLight: "rgba(139, 92, 246, 0.15)",
};

// Format currency
const formatCurrency = (value) => {
  if (!value) return "0";
  return new Intl.NumberFormat("en-US").format(value);
};

// Format date
const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  const locale = lang.value === "ar" ? "ar-u-nu-latn" : "en-US";
  return date.toLocaleDateString(locale, {
    month: "short",
    day: "numeric",
  });
};

// KPI Cards Data
const kpiCards = computed(() => [
  {
    id: "revenue",
    label: "total_revenue",
    value: formatCurrency(summary.value?.total_revenue),
    icon: "currency",
    color: "primary",
    change: comparison.value?.revenue?.change || 0,
    subtitle: t("total_paid") + ": " + formatCurrency(summary.value?.total_paid),
  },
  {
    id: "invoices",
    label: "total_invoices",
    value: summary.value?.total_invoices || 0,
    icon: "invoice",
    color: "blue",
    change: comparison.value?.invoices?.change || 0,
    subtitle: t("today_invoices") + ": " + (todayStats.value?.invoices || 0),
  },
  {
    id: "patients",
    label: "patients",
    value: summary.value?.total_patients || 0,
    icon: "patients",
    color: "purple",
    change: comparison.value?.patients?.change || 0,
    subtitle: t("today_invoices") + ": " + (todayStats.value?.patients || 0),
  },
  {
    id: "tests",
    label: "tests",
    value: testsStats.value?.total || 0,
    icon: "test",
    color: "success",
    change: 0,
    subtitle: t("test_completed") + ": " + (testsStats.value?.completed || 0),
  },
]);

// Revenue Line Chart
const revenueChartData = computed(() => {
  const data = dailyRevenue.value || [];
  const labels = data.map((d) => formatDate(d.date));
  const amounts = data.map((d) => d.total_amount || 0);
  const paidAmounts = data.map((d) => d.paid_amount || 0);

  return {
    labels: labels.length ? labels : ["No Data"],
    datasets: [
      {
        label: t("total_revenue"),
        data: amounts.length ? amounts : [0],
        borderColor: colors.primary,
        backgroundColor: colors.primaryLight,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: colors.primary,
        pointBorderColor: "#fff",
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      },
      {
        label: t("total_paid"),
        data: paidAmounts.length ? paidAmounts : [0],
        borderColor: colors.success,
        backgroundColor: "transparent",
        borderDash: [5, 5],
        tension: 0.4,
        pointBackgroundColor: colors.success,
        pointBorderColor: "#fff",
        pointBorderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
      },
    ],
  };
});

const revenueChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { intersect: false, mode: "index" },
  plugins: {
    legend: {
      display: true,
      position: "top",
      align: "end",
      labels: { usePointStyle: true, padding: 20, color: "#64748b", font: { size: 12 } },
    },
    tooltip: {
      backgroundColor: "#1e293b",
      padding: 12,
      cornerRadius: 8,
      titleFont: { size: 13 },
      bodyFont: { size: 12 },
    },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: "#94a3b8", font: { size: 11 } } },
    y: {
      grid: { color: "#f1f5f9", drawBorder: false },
      ticks: { color: "#94a3b8", font: { size: 11 } },
      beginAtZero: true,
    },
  },
};

// Tests & Cultures Status Chart
const testsChartData = computed(() => ({
  labels: [t("test_completed"), t("test_pending"), t("cultures_completed"), t("cultures_pending")],
  datasets: [
    {
      data: [
        testsStats.value?.completed ?? 0,
        testsStats.value?.pending ?? 0,
        culturesStats.value?.completed ?? 0,
        culturesStats.value?.pending ?? 0,
      ],
      backgroundColor: [colors.success, colors.warning, colors.blue, colors.danger],
      borderRadius: 8,
      borderSkipped: false,
      barThickness: 40,
    },
  ],
}));

const testsChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { backgroundColor: "#1e293b", padding: 12, cornerRadius: 8 },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: "#94a3b8", font: { size: 10 } } },
    y: {
      grid: { color: "#f1f5f9", drawBorder: false },
      ticks: { color: "#94a3b8", font: { size: 11 } },
      beginAtZero: true,
    },
  },
};

// Payment Status Doughnut
const paymentChartData = computed(() => ({
  labels: [t("total_paid"), t("total_due")],
  datasets: [
    {
      data: [summary.value?.total_paid ?? 0, summary.value?.total_remaining ?? 0],
      backgroundColor: [colors.success, colors.danger],
      borderWidth: 0,
      cutout: "78%",
    },
  ],
}));

const paymentChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "bottom",
      labels: { usePointStyle: true, padding: 20, color: "#64748b", font: { size: 12 } },
    },
    tooltip: { backgroundColor: "#1e293b", padding: 12, cornerRadius: 8 },
  },
};

// Top Tests Horizontal Bar
const topTestsChartData = computed(() => {
  const tests = mostRequestedTests.value || [];
  return {
    labels: tests.map((t) => t.test_name || "Unknown").slice(0, 5),
    datasets: [
      {
        label: t("count"),
        data: tests.map((t) => t.total_done || 0).slice(0, 5),
        backgroundColor: [colors.primary, colors.blue, colors.purple, colors.success, colors.warning],
        borderRadius: 6,
        barThickness: 24,
      },
    ],
  };
});

const topTestsChartOptions = {
  indexAxis: "y",
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { backgroundColor: "#1e293b", padding: 12, cornerRadius: 8 },
  },
  scales: {
    x: {
      grid: { color: "#f1f5f9", drawBorder: false },
      ticks: { color: "#94a3b8", font: { size: 11 } },
      beginAtZero: true,
    },
    y: { grid: { display: false }, ticks: { color: "#64748b", font: { size: 11 } } },
  },
};

// Payment Methods Pie Chart
const paymentMethodsChartData = computed(() => {
  const methods = accountingData.value?.paymentMethods || [];
  return {
    labels: methods.map((m) => m.name || "Unknown"),
    datasets: [
      {
        data: methods.map((m) => m.total || 0),
        backgroundColor: [colors.primary, colors.blue, colors.purple, colors.success, colors.warning],
        borderWidth: 0,
      },
    ],
  };
});

// Quick Actions
const quickActions = [
  { label: "new_invoice", icon: "plus", route: "/invoices/create", color: "primary", permission: "invoices create" },
  { label: "add_patient", icon: "user-plus", route: "/patients", color: "blue", permission: "patients create" },
  { label: "medical_reports", icon: "clipboard", route: "/medical_reports", color: "success", permission: "invoices view" },
  { label: "reports", icon: "chart", route: "/reports", color: "purple", permission: "reports view" },
];

// Navigation
const navigateTo = (route) => router.push(route);
const hasPermission = (permission) => authStore.havePermission(permission);

// Fetch Data
const handleSearch = async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      reportsStore.Getreports(),
      reportsStore.GetDashboardStats(),
      reportsStore.GetComparisonData(),
    ]);
  } finally {
    isLoading.value = false;
  }
};

// Completion rate
const completionRate = computed(() => {
  const total = (testsStats.value?.total || 0) + (culturesStats.value?.total || 0);
  const completed = (testsStats.value?.completed || 0) + (culturesStats.value?.completed || 0);
  if (total === 0) return 0;
  return Math.round((completed / total) * 100);
});

// Collection rate
const collectionRate = computed(() => {
  const total = summary.value?.total_revenue || 0;
  const paid = summary.value?.total_paid || 0;
  if (total === 0) return 0;
  return Math.round((paid / total) * 100);
});

onMounted(async () => {
  if (!User.value) {
    router.push("/");
    return;
  }

  const today = new Date();
  const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  record.value.start_date = firstDayOfMonth.toISOString().split("T")[0];
  record.value.end_date = today.toISOString().split("T")[0];
  record.value.report_type = "quick_summary";

  if (User.value.role_id === 1) {
    record.value.lab_id = 2;
    branchData.value = { id: 2, name: "Central Medical Laboratory" };
  } else {
    record.value.lab_id = User.value.id;
    branchData.value = { id: User.value.id, name: User.value.name };
  }

  try {
    const promises = [
      reportsStore.Getreports(),
      reportsStore.GetDashboardStats(),
      reportsStore.GetComparisonData(),
      usersStore.GetRecords(),
    ];

    // Check subscription for non-admin users
    if (User.value.role_id !== 1) {
      promises.push(
        $http.get("/subscription/my").then(({ data }) => {
          subscriptionData.value = data.subscription;
          subscriptionExpired.value = data.expired || false;
          subscriptionDaysLeft.value = data.days_left || 0;
          subscriptionMessage.value = data.message || "";
          salesPhone.value = data.sales_phone || "07838334835";
        }).catch(() => {})
      );
    }

    await Promise.all(promises);
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="space-y-6">
    <!-- Loading Overlay -->
    <UiLoading :loading="isLoading" overlay :message="t('loading') + '...'" />

    <!-- ==================== HEADER SECTION ==================== -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 shadow-2xl">
      <!-- Background Elements -->
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')]"></div>
        <div class="absolute -top-24 -end-24 w-96 h-96 bg-primary-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-12 -start-12 w-64 h-64 bg-primary-600/15 rounded-full blur-2xl"></div>
      </div>

      <div class="relative p-6 lg:p-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <!-- Welcome Text -->
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
                <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ currentDate }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">
              {{ greeting }}, <span class="text-primary-400">{{ User?.name || t("user") }}</span>
            </h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("dashboard_welcome_message") }}</p>
          </div>

          <!-- Today's Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[130px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-primary-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("today_invoices") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ todayStats?.invoices || 0 }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[130px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("total_revenue") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ formatCurrency(todayStats?.revenue) }}</p>
            </div>
          </div>
        </div>

        <!-- Subscription Expired Notice -->
        <div v-if="subscriptionExpired" class="mt-4 flex items-center gap-3 bg-red-500/20 backdrop-blur-sm border border-red-400/30 rounded-xl px-5 py-3">
          <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
          <p class="text-sm text-white flex-1">
            {{ subscriptionMessage }}
          </p>
          <a :href="'tel:' + salesPhone" class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-white/15 hover:bg-white/25 rounded-lg text-sm font-bold text-white transition-colors" dir="ltr">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            {{ salesPhone }}
          </a>
        </div>

        <!-- Subscription Expiring Soon Notice -->
        <div v-else-if="subscriptionDaysLeft > 0 && subscriptionDaysLeft <= 7" class="mt-4 flex items-center gap-3 bg-amber-500/20 backdrop-blur-sm border border-amber-400/30 rounded-xl px-5 py-3">
          <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm text-white flex-1">
            {{ lang === 'ar' ? 'اشتراكك ينتهي خلال' : 'Your subscription expires in' }}
            <span class="font-bold text-amber-300 mx-1">{{ subscriptionDaysLeft }}</span>
            {{ lang === 'ar' ? 'يوم. يرجى التواصل مع قسم المبيعات للتجديد:' : 'days. Please contact sales to renew:' }}
          </p>
          <a :href="'tel:' + salesPhone" class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-white/15 hover:bg-white/25 rounded-lg text-sm font-bold text-white transition-colors" dir="ltr">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            {{ salesPhone }}
          </a>
        </div>
      </div>
    </div>

    <!-- ==================== FILTERS ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[150px]">
          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("from_date") }}</label>
          <input
            type="date"
            v-model="record.start_date"
            @click="$event.target.showPicker()"
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
          />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("to_date") }}</label>
          <input
            type="date"
            v-model="record.end_date"
            @click="$event.target.showPicker()"
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
          />
        </div>
        <div v-if="Role != 4" class="flex-1 min-w-[150px]">
          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("branch") }}</label>
          <select
            v-model="record.lab_id"
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer"
          >
            <option :value="branchData.id">{{ branchData.name }}</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
          </select>
        </div>
        <button
          @click="handleSearch"
          :disabled="isLoading"
          class="px-6 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white font-medium rounded-xl transition-all flex items-center gap-2 disabled:opacity-50 shadow-lg shadow-primary-500/25"
        >
          <svg v-if="isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          {{ t("refresh") }}
        </button>
      </div>
    </div>

    <!-- ==================== KPI CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div
        v-for="kpi in kpiCards"
        :key="kpi.id"
        class="group bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
      >
        <div class="flex items-start justify-between mb-4">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110"
            :class="{
              'bg-primary-50 text-primary-600': kpi.color === 'primary',
              'bg-blue-50 text-blue-600': kpi.color === 'blue',
              'bg-purple-50 text-purple-600': kpi.color === 'purple',
              'bg-green-50 text-green-600': kpi.color === 'success',
            }"
          >
            <!-- Currency Icon -->
            <svg v-if="kpi.icon === 'currency'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Invoice Icon -->
            <svg v-if="kpi.icon === 'invoice'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <!-- Patients Icon -->
            <svg v-if="kpi.icon === 'patients'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <!-- Test Icon -->
            <svg v-if="kpi.icon === 'test'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
          </div>
          <div
            v-if="kpi.change !== 0"
            class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold"
            :class="kpi.change >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'"
          >
            <svg v-if="kpi.change >= 0" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
            {{ Math.abs(kpi.change) }}%
          </div>
        </div>
        <p class="text-sm font-medium text-slate-500 mb-1">{{ t(kpi.label) }}</p>
        <p class="text-3xl font-bold text-slate-800 mb-2">{{ kpi.value }}</p>
        <p class="text-xs text-slate-400">{{ kpi.subtitle }}</p>
      </div>
    </div>

    <!-- ==================== CHARTS ROW 1 ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Revenue Trend Chart -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-semibold text-slate-800">{{ t("weekly_overview") }}</h2>
              <p class="text-xs text-slate-500">{{ t("revenue_payment_trends") }}</p>
            </div>
          </div>
        </div>
        <div class="h-[300px]">
          <Line :data="revenueChartData" :options="revenueChartOptions" />
        </div>
      </div>

      <!-- Payment Status Chart -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ t("payment_status") }}</h2>
            <p class="text-xs text-slate-500">{{ t("collection_rate") }}: {{ collectionRate }}%</p>
          </div>
        </div>
        <div class="h-[250px] flex items-center justify-center">
          <Doughnut :data="paymentChartData" :options="paymentChartOptions" />
        </div>
      </div>
    </div>

    <!-- ==================== CHARTS ROW 2 ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Tests & Cultures Status -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-semibold text-slate-800">{{ t("tests_cultures_status") }}</h2>
              <p class="text-xs text-slate-500">{{ t("completion_rate") }}: {{ completionRate }}%</p>
            </div>
          </div>
          <!-- Completion Progress -->
          <div class="hidden sm:flex items-center gap-3">
            <div class="w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-primary-500 to-primary-600 rounded-full transition-all" :style="{ width: completionRate + '%' }"></div>
            </div>
            <span class="text-sm font-semibold text-slate-700">{{ completionRate }}%</span>
          </div>
        </div>
        <div class="h-[280px]">
          <Bar :data="testsChartData" :options="testsChartOptions" />
        </div>
      </div>

      <!-- Top Tests -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ t("top_tests") }}</h2>
            <p class="text-xs text-slate-500">{{ t("most_requested_tests") }}</p>
          </div>
        </div>
        <div class="h-[250px]">
          <Bar :data="topTestsChartData" :options="topTestsChartOptions" />
        </div>
      </div>
    </div>

    <!-- ==================== FINANCIAL SUMMARY & QUICK ACTIONS ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Financial Summary -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ t("financial_summary") }}</h2>
            <p class="text-xs text-slate-500">{{ t("financial_overview_period") }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <!-- Total Revenue -->
          <div class="relative overflow-hidden bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl p-5 text-white">
            <div class="absolute -end-4 -bottom-4 w-20 h-20 bg-white/10 rounded-full"></div>
            <div class="relative">
              <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-white/80 mb-1">{{ t("total_revenue") }}</p>
              <p class="text-xl font-bold">{{ formatCurrency(summary?.total_revenue) }}</p>
            </div>
          </div>

          <!-- Total Paid -->
          <div class="relative overflow-hidden bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-5 text-white">
            <div class="absolute -end-4 -bottom-4 w-20 h-20 bg-white/10 rounded-full"></div>
            <div class="relative">
              <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-white/80 mb-1">{{ t("total_paid") }}</p>
              <p class="text-xl font-bold">{{ formatCurrency(summary?.total_paid) }}</p>
            </div>
          </div>

          <!-- Total Due -->
          <div class="relative overflow-hidden bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-5 text-white">
            <div class="absolute -end-4 -bottom-4 w-20 h-20 bg-white/10 rounded-full"></div>
            <div class="relative">
              <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-white/80 mb-1">{{ t("total_due") }}</p>
              <p class="text-xl font-bold">{{ formatCurrency(summary?.total_remaining) }}</p>
            </div>
          </div>

          <!-- Profit -->
          <div class="relative overflow-hidden bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-5 text-white">
            <div class="absolute -end-4 -bottom-4 w-20 h-20 bg-white/10 rounded-full"></div>
            <div class="relative">
              <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-white/80 mb-1">{{ t("profit") }}</p>
              <p class="text-xl font-bold">{{ formatCurrency(accountingData?.profit) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ t("quick_actions") }}</h2>
            <p class="text-xs text-slate-500">{{ t("common_operations") }}</p>
          </div>
        </div>
        <div class="space-y-3">
          <template v-for="action in quickActions" :key="action.label">
            <button
              v-if="hasPermission(action.permission)"
              @click="navigateTo(action.route)"
              class="w-full flex items-center gap-4 p-4 rounded-xl bg-slate-50 hover:bg-slate-100 transition-all group text-start"
            >
              <div
                class="w-11 h-11 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110"
                :class="{
                  'bg-primary-100 text-primary-600': action.color === 'primary',
                  'bg-blue-100 text-blue-600': action.color === 'blue',
                  'bg-green-100 text-green-600': action.color === 'success',
                  'bg-purple-100 text-purple-600': action.color === 'purple',
                }"
              >
                <!-- Plus Icon -->
                <svg v-if="action.icon === 'plus'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <!-- User Plus Icon -->
                <svg v-if="action.icon === 'user-plus'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <!-- Clipboard Icon -->
                <svg v-if="action.icon === 'clipboard'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <!-- Chart Icon -->
                <svg v-if="action.icon === 'chart'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <span class="font-medium text-slate-700 group-hover:text-slate-900 flex-1">{{ t(action.label) }}</span>
              <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-600 group-hover:translate-x-1 transition-all rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- ==================== RECENT ACTIVITY & TOP REFERRALS ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Recent Invoices -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-semibold text-slate-800">{{ t("recent_invoices") }}</h2>
              <p class="text-xs text-slate-500">{{ t("latest_transactions") }}</p>
            </div>
          </div>
          <button @click="navigateTo('/invoices')" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
            {{ t("view_all") }} →
          </button>
        </div>
        <div class="space-y-3 max-h-[320px] overflow-y-auto">
          <div
            v-for="invoice in recentInvoices"
            :key="invoice.id"
            class="flex items-center gap-4 p-3 rounded-xl hover:bg-slate-50 transition-colors cursor-pointer"
            @click="navigateTo('/invoices')"
          >
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 font-semibold text-sm">
              #{{ invoice.id }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-slate-800 truncate">{{ invoice.patient }}</p>
              <p class="text-xs text-slate-500">{{ invoice.tests_count }} tests • {{ formatDate(invoice.date) }}</p>
            </div>
            <div class="text-end">
              <p class="font-semibold text-slate-800">{{ formatCurrency(invoice.total) }}</p>
              <span
                class="text-xs px-2 py-0.5 rounded-full"
                :class="invoice.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
              >
                {{ invoice.status === 'completed' ? t('done') : t('pendening') }}
              </span>
            </div>
          </div>
          <div v-if="!recentInvoices?.length" class="text-center py-8 text-slate-500">
            {{ t("noData") }}
          </div>
        </div>
      </div>

      <!-- Top Referrals -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-semibold text-slate-800">{{ t("top_referrals") }}</h2>
              <p class="text-xs text-slate-500">{{ t("doctor_referrals_period") }}</p>
            </div>
          </div>
        </div>
        <div class="space-y-3 max-h-[320px] overflow-y-auto">
          <div
            v-for="(referral, index) in doctorReferrals?.slice(0, 8)"
            :key="index"
            class="flex items-center gap-4 p-3 rounded-xl hover:bg-slate-50 transition-colors"
          >
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm">
              {{ referral.doctor_name?.charAt(0) || 'D' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-slate-800 truncate">{{ referral.doctor_name }}</p>
              <p class="text-xs text-slate-500">{{ t("referrals") }}</p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-lg font-bold text-slate-800">{{ referral.referred_patients_count }}</span>
              <span class="text-xs text-slate-500">{{ t("patients") }}</span>
            </div>
          </div>
          <div v-if="!doctorReferrals?.length" class="text-center py-8 text-slate-500">
            {{ t("noData") }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
