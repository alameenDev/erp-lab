<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { ActivityStore } from "@/store/modules/activityLogger";
import { t } from "@/utils/helper";
import * as XLSX from "xlsx";

const activityStore = ActivityStore();
const { records, totalCount } = storeToRefs(activityStore);
const { GetRecords } = activityStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const globalFilter = ref("");
const showFilters = ref(false);
const Filters = ref({
  log_name: "",
  description: "",
  activityDate: "",
  causer_name: "",
  causer_id: "",
  causer_role: "",
});

const currentPage = ref(1);
const itemsPerPage = ref(25);

onMounted(() => {
  GetRecords();
});

const dateTimeFormat = (date) => {
  if (!date) return "-";
  return new Date(date).toLocaleString(lang.value === "ar" ? "ar-u-nu-latn" : "en-US");
};

// Stats computations
const totalActivities = computed(() => records.value?.length || 0);
const uniqueUsers = computed(() => {
  const users = new Set(records.value?.map(r => r.causer_id).filter(Boolean));
  return users.size;
});
const todayActivities = computed(() => {
  const today = new Date().toDateString();
  return records.value?.filter(r => new Date(r.created_at).toDateString() === today).length || 0;
});
const uniqueActions = computed(() => {
  const actions = new Set(records.value?.map(r => r.log_name).filter(Boolean));
  return actions.size;
});

const filteredRecords = computed(() => {
  if (!records.value) return [];
  return records.value.filter((record) => {
    const matchesGlobal = globalFilter.value
      ? Object.values(record).some((value) =>
          String(value).toLowerCase().includes(globalFilter.value.toLowerCase())
        )
      : true;

    const matchesColumn =
      (!Filters.value.log_name ||
        record.log_name?.toLowerCase().includes(Filters.value.log_name.toLowerCase())) &&
      (!Filters.value.description ||
        record.description?.toLowerCase().includes(Filters.value.description.toLowerCase())) &&
      (!Filters.value.causer_name ||
        record.causer_name?.toLowerCase().includes(Filters.value.causer_name.toLowerCase())) &&
      (!Filters.value.causer_id ||
        record.causer_id?.toString().includes(Filters.value.causer_id)) &&
      (!Filters.value.causer_role ||
        record.causer_role?.toString().includes(Filters.value.causer_role)) &&
      (!Filters.value.activityDate ||
        dateTimeFormat(record.created_at).includes(Filters.value.activityDate));

    return matchesGlobal && matchesColumn;
  });
});

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredRecords.value.slice(start, start + itemsPerPage.value);
});

const totalPages = computed(() => Math.ceil(filteredRecords.value.length / itemsPerPage.value));

// Pagination display
const displayedPages = computed(() => {
  const pages = [];
  const total = totalPages.value;
  const current = currentPage.value;

  if (total <= 5) {
    for (let i = 1; i <= total; i++) pages.push(i);
  } else {
    if (current <= 3) {
      pages.push(1, 2, 3, 4, "...", total);
    } else if (current >= total - 2) {
      pages.push(1, "...", total - 3, total - 2, total - 1, total);
    } else {
      pages.push(1, "...", current - 1, current, current + 1, "...", total);
    }
  }
  return pages;
});

const goToPage = (page) => {
  if (page !== "..." && page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (Filters.value.log_name) count++;
  if (Filters.value.description) count++;
  if (Filters.value.causer_name) count++;
  if (Filters.value.causer_id) count++;
  if (Filters.value.causer_role) count++;
  if (Filters.value.activityDate) count++;
  return count;
});

const clearFilters = () => {
  Filters.value = {
    log_name: "",
    description: "",
    activityDate: "",
    causer_name: "",
    causer_id: "",
    causer_role: "",
  };
  globalFilter.value = "";
  currentPage.value = 1;
};

const exportToExcel = () => {
  const exportData = filteredRecords.value.map((rec, i) => ({
    "#": i + 1,
    [t("causer_id")]: rec.causer_id,
    [t("causer_name")]: rec.causer_name,
    [t("userRole")]: rec.causer_role,
    [t("log_name")]: rec.log_name,
    [t("description")]: rec.description,
    [t("activityDate")]: dateTimeFormat(rec.created_at),
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Activity");
  XLSX.writeFile(wb, "activity_log_export.xlsx");
};

// Get action badge color
const getActionBadgeClass = (action) => {
  const name = action?.toLowerCase() || "";
  if (name.includes("create") || name.includes("add")) return "bg-green-100 text-green-700";
  if (name.includes("update") || name.includes("edit")) return "bg-blue-100 text-blue-700";
  if (name.includes("delete") || name.includes("remove")) return "bg-red-100 text-red-700";
  if (name.includes("login") || name.includes("auth")) return "bg-purple-100 text-purple-700";
  return "bg-slate-100 text-slate-700";
};

// Get role badge color
const getRoleBadgeClass = (role) => {
  const name = role?.toLowerCase() || "";
  if (name.includes("admin")) return "bg-red-100 text-red-700";
  if (name.includes("lab")) return "bg-purple-100 text-purple-700";
  if (name.includes("doctor")) return "bg-blue-100 text-blue-700";
  if (name.includes("patient")) return "bg-green-100 text-green-700";
  return "bg-slate-100 text-slate-700";
};
</script>

<template>
  <div class="space-y-6" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
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
          <!-- Title Section -->
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ totalActivities }} {{ t("record") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("activityLogger") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_activity_logs") }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("today") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ todayActivities }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-purple-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-purple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("users") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ uniqueUsers }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Activities -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("activities") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ totalActivities }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Today's Activities -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("today") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ todayActivities }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Unique Users -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("active_users") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ uniqueUsers }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Unique Actions -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("action_types") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ uniqueActions }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== FILTERS & ACTIONS ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <!-- Main Search Bar -->
      <div class="p-5">
        <div class="flex flex-wrap items-end gap-4">
          <!-- Search Input -->
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("search") }}</label>
            <div class="relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="globalFilter"
                type="text"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-3">
            <!-- Toggle Filters Button -->
            <button
              @click="showFilters = !showFilters"
              class="px-4 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2 relative"
              :class="{ 'bg-primary-50 border-primary-200 text-primary-700': showFilters || activeFiltersCount > 0 }"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <span class="hidden sm:inline">{{ t("filters") }}</span>
              <span
                v-if="activeFiltersCount > 0"
                class="absolute -top-2 -end-2 w-5 h-5 bg-primary-600 text-white text-xs font-bold rounded-full flex items-center justify-center"
              >
                {{ activeFiltersCount }}
              </span>
            </button>
            <button
              v-if="activeFiltersCount > 0"
              @click="clearFilters"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span class="hidden sm:inline">{{ t("Clear Filters") }}</span>
            </button>
            <button
              @click="GetRecords"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span class="hidden sm:inline">{{ t("refresh") }}</span>
            </button>
            <button
              @click="exportToExcel"
              class="px-4 py-2.5 bg-green-50 hover:bg-green-100 text-green-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">{{ t("Export to Excel") }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Collapsible Filters Panel -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="max-h-0 opacity-0"
        enter-to-class="max-h-96 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="max-h-96 opacity-100"
        leave-to-class="max-h-0 opacity-0"
      >
        <div v-if="showFilters" class="overflow-hidden">
          <div class="px-5 pb-5 pt-2 border-t border-slate-100 bg-slate-50/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
              <!-- Causer Name Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("causer_name") }}</label>
                <input
                  v-model="Filters.causer_name"
                  type="text"
                  :placeholder="t('causer_name') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Causer ID Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("causer_id") }}</label>
                <input
                  v-model="Filters.causer_id"
                  type="text"
                  :placeholder="t('causer_id') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Role Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("userRole") }}</label>
                <input
                  v-model="Filters.causer_role"
                  type="text"
                  :placeholder="t('userRole') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Log Name Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("log_name") }}</label>
                <input
                  v-model="Filters.log_name"
                  type="text"
                  :placeholder="t('log_name') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Description Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("description") }}</label>
                <input
                  v-model="Filters.description"
                  type="text"
                  :placeholder="t('description') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Date Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("activityDate") }}</label>
                <input
                  v-model="Filters.activityDate"
                  type="date"
                  @click="$event.target.showPicker()"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- ==================== DATA TABLE ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <!-- Table Header -->
      <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">{{ t("activityLogger") }}</h3>
              <p class="text-sm text-slate-500">{{ filteredRecords.length }} {{ t("record") }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- No Data Message -->
      <div v-if="totalCount === 0" class="p-12 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-slate-800 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6">{{ t("no_activity_message") }}</p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-slate-50/80">
              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-16">
                #
              </th>
              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">
                {{ t("causer_name") }}
              </th>
              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">
                {{ t("userRole") }}
              </th>
              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">
                {{ t("log_name") }}
              </th>
              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">
                {{ t("description") }}
              </th>
              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">
                {{ t("activityDate") }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="paginatedRecords.length === 0">
              <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                {{ t("noData") }}
              </td>
            </tr>
            <tr
              v-for="(record, index) in paginatedRecords"
              :key="record.id"
              class="group hover:bg-primary-50/50 transition-colors duration-150"
            >
              <td class="px-5 py-4 text-center">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                  {{ (currentPage - 1) * itemsPerPage + index + 1 }}
                </span>
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-semibold text-sm">
                    {{ record.causer_name?.charAt(0)?.toUpperCase() || "U" }}
                  </div>
                  <div>
                    <p class="font-semibold text-slate-800">{{ record.causer_name || "-" }}</p>
                    <p class="text-xs text-slate-500">ID: {{ record.causer_id || "-" }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                  :class="getRoleBadgeClass(record.causer_role)"
                >
                  {{ record.causer_role || "-" }}
                </span>
              </td>
              <td class="px-5 py-4">
                <span
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                  :class="getActionBadgeClass(record.log_name)"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                  {{ record.log_name || "-" }}
                </span>
              </td>
              <td class="px-5 py-4">
                <p class="text-slate-600 text-sm max-w-xs truncate" :title="record.description">
                  {{ record.description || "-" }}
                </p>
              </td>
              <td class="px-5 py-4 text-center">
                <div class="flex flex-col items-center">
                  <span class="text-sm font-medium text-slate-700">
                    {{ dateTimeFormat(record.created_at) }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="filteredRecords.length > 0" class="px-5 py-4 border-t border-slate-100 bg-slate-50/30">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="text-sm text-slate-600">
            {{ t("showing") }}
            <span class="font-semibold text-slate-800">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
            {{ t("to") }}
            <span class="font-semibold text-slate-800">{{ Math.min(currentPage * itemsPerPage, filteredRecords.length) }}</span>
            {{ t("of") }}
            <span class="font-semibold text-slate-800">{{ filteredRecords.length }}</span>
            {{ t("results") }}
          </div>

          <div class="flex items-center gap-1">
            <!-- Previous Button -->
            <button
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-5 h-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <!-- Page Numbers -->
            <template v-for="page in displayedPages" :key="page">
              <button
                v-if="page !== '...'"
                @click="goToPage(page)"
                :class="[
                  'min-w-[40px] h-10 rounded-lg font-medium transition-all duration-200',
                  currentPage === page
                    ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/30'
                    : 'text-slate-600 hover:bg-slate-100'
                ]"
              >
                {{ page }}
              </button>
              <span v-else class="px-2 text-slate-400">...</span>
            </template>

            <!-- Next Button -->
            <button
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage >= totalPages"
              class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-5 h-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
