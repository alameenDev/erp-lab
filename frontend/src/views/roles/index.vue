<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useRolesstore } from "@/store/modules/roles";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm } from "@/utils/helper";
import * as XLSX from "xlsx";
import RoleModal from "./components/role_model.vue";

const rolesStore = useRolesstore();
const authStore = useAuthStore();
const { havePermission } = authStore;
const { roles, totalCount, record, dialog, permissions } = storeToRefs(rolesStore);
const { GetRoles, RemoveRoles, GetPermissions } = rolesStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const globalFilter = ref("");
const showFilters = ref(false);
const Filters = ref({
  name: "",
});

const currentPage = ref(1);
const itemsPerPage = ref(25);

onMounted(() => {
  GetRoles();
  GetPermissions();
});

// Stats computations
const totalRoles = computed(() => roles.value?.length || 0);
const totalPermissions = computed(() => permissions.value?.length || 0);
const adminRoles = computed(() => roles.value?.filter(r => r.name?.toLowerCase().includes('admin'))?.length || 0);
const avgPermissions = computed(() => {
  if (!roles.value?.length) return 0;
  const total = roles.value.reduce((sum, r) => sum + (r.permissions?.length || 0), 0);
  return Math.round(total / roles.value.length);
});

const filteredRecords = computed(() => {
  if (!roles.value) return [];
  return roles.value.filter((rec) => {
    const matchesGlobalFilter = globalFilter.value
      ? rec.name?.toLowerCase().includes(globalFilter.value.toLowerCase())
      : true;

    const matchesColumnFilters =
      !Filters.value.name || rec.name?.toLowerCase().includes(Filters.value.name.toLowerCase());

    return matchesGlobalFilter && matchesColumnFilters;
  });
});

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredRecords.value.slice(start, end);
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
      pages.push(1, 2, 3, 4, '...', total);
    } else if (current >= total - 2) {
      pages.push(1, '...', total - 3, total - 2, total - 1, total);
    } else {
      pages.push(1, '...', current - 1, current, current + 1, '...', total);
    }
  }
  return pages;
});

const goToPage = (page) => {
  if (page !== '...' && page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

const clearFilters = () => {
  Filters.value = { name: "" };
  globalFilter.value = "";
  currentPage.value = 1;
};

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (Filters.value.name) count++;
  return count;
});

const exportToExcel = () => {
  const exportData = filteredRecords.value.map((rec, index) => ({
    "#": index + 1,
    [t("name")]: rec.name,
    [t("permissions")]: rec.permissions?.length || 0,
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Roles");
  XLSX.writeFile(wb, "roles_export.xlsx");
};

const addRecord = () => {
  Object.keys(record.value).forEach((key) => {
    record.value[key] = null;
  });
  record.value.id = null;
  record.value.permissions = [];
  dialog.value = true;
};

const editRecord = (rec) => {
  Object.assign(record.value, rec);
  record.value.permissions = rec.permissions?.map(p => p.id) || [];
  dialog.value = true;
};

const deleteRecord = (rec) => {
  showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
    if (res.value) {
      record.value.id = rec.id;
      RemoveRoles();
    }
  });
};

// Get role badge color based on role name
const getRoleBadgeClass = (role) => {
  const name = role?.name?.toLowerCase() || '';
  if (name.includes('admin')) return 'bg-red-100 text-red-700';
  if (name.includes('lab')) return 'bg-purple-100 text-purple-700';
  if (name.includes('doctor')) return 'bg-blue-100 text-blue-700';
  if (name.includes('patient')) return 'bg-green-100 text-green-700';
  if (name.includes('branch')) return 'bg-amber-100 text-amber-700';
  if (name.includes('sample')) return 'bg-cyan-100 text-cyan-700';
  return 'bg-slate-100 text-slate-700';
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ totalRoles }} {{ t("role") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("role_permissions") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_roles") }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("role") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ totalRoles }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-purple-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-purple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("permissions") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ totalPermissions }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Roles -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("role") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ totalRoles }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Total Permissions -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("permissions") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ totalPermissions }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Admin Roles -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("admins") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ adminRoles }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Avg Permissions -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("avg_permissions") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ avgPermissions }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
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
                :placeholder="t('name') + '...'"
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
              @click="exportToExcel"
              class="px-4 py-2.5 bg-green-50 hover:bg-green-100 text-green-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">{{ t("Export to Excel") }}</span>
            </button>
            <button
              v-if="havePermission('roles create')"
              @click="addRecord"
              class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all flex items-center gap-2 shadow-lg shadow-primary-500/25"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              {{ t("add") }}
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <!-- Name Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("name") }}</label>
                <input
                  v-model="Filters.name"
                  type="text"
                  :placeholder="t('name') + '...'"
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
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">{{ t("role_permissions") }}</h3>
              <p class="text-sm text-slate-500">{{ filteredRecords.length }} {{ t("record") }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- No Data Message -->
      <div v-if="totalCount === 0" class="p-12 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-slate-800 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6">{{ t("no_roles_message") }}</p>
        <button
          v-if="havePermission('roles create')"
          @click="addRecord"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-500 text-white rounded-xl hover:bg-primary-600 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          {{ t("add") }} {{ t("role") }}
        </button>
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
                {{ t("name") }}
              </th>
              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">
                {{ t("permissions") }}
              </th>
              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-32">
                {{ t("actions") }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="paginatedRecords.length === 0">
              <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                {{ t("noData") }}
              </td>
            </tr>
            <tr
              v-for="(role, index) in paginatedRecords"
              :key="role.id"
              class="group hover:bg-primary-50/50 transition-colors duration-150"
            >
              <td class="px-5 py-4 text-center">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                  {{ (currentPage - 1) * itemsPerPage + index + 1 }}
                </span>
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="getRoleBadgeClass(role)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </div>
                  <div>
                    <p class="font-semibold text-slate-800">{{ role.name }}</p>
                    <p class="text-xs text-slate-500">ID: {{ role.id }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-4 text-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-purple-100 text-purple-700">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                  </svg>
                  {{ role.permissions?.length || 0 }}
                </span>
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center justify-center gap-1">
                  <button
                    v-if="havePermission('roles edit')"
                    @click="editRecord(role)"
                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                    :title="t('edit')"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    v-if="havePermission('roles delete')"
                    @click="deleteRecord(role)"
                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    :title="t('delete')"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
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

    <!-- Modal -->
    <RoleModal />
  </div>
</template>
