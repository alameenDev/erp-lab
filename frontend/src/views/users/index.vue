<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useUsersStore } from "@/store/modules/users";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm } from "@/utils/helper";
import * as XLSX from "xlsx";
import UsersModal from "./components/users-modal.vue";
import AuthCodeModal from "./components/authCodeModal.vue";
import AssignRoleModel from "./components/assignRoleModel.vue";

const usersStore = useUsersStore();
const authStore = useAuthStore();
const { havePermission } = authStore;
const { records, UserRoles, totalCount, record, dialog, assignDialog } = storeToRefs(usersStore);
const { GetRecords, GetUserRoles, RemoveUser } = usersStore;

// Loading state
const isLoading = ref(true);

// Filters
const globalFilter = ref("");
const filters = ref({
  name: "",
  email: "",
  role: "",
  phone: "",
});
const showFilters = ref(false);
const currentPage = ref(1);
const perPage = ref(25);

// Stats
const stats = computed(() => {
  const list = records.value || [];
  const total = list.length;
  const admins = list.filter(u => u.role?.toLowerCase() === 'admin').length;
  const labs = list.filter(u => u.role?.toLowerCase() === 'lab').length;
  const doctors = list.filter(u => u.role?.toLowerCase() === 'doctor').length;
  return { total, admins, labs, doctors };
});

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.name) count++;
  if (filters.value.email) count++;
  if (filters.value.role) count++;
  if (filters.value.phone) count++;
  return count;
});

onMounted(async () => {
  isLoading.value = true;
  try {
    await Promise.all([GetUserRoles(), GetRecords()]);
  } finally {
    isLoading.value = false;
  }
});

const filteredRecords = computed(() => {
  if (!records.value) return [];
  return records.value.filter((rec) => {
    const matchesGlobalFilter = globalFilter.value
      ? rec.name?.toLowerCase().includes(globalFilter.value.toLowerCase()) ||
        rec.email?.toLowerCase().includes(globalFilter.value.toLowerCase()) ||
        rec.phone?.toLowerCase().includes(globalFilter.value.toLowerCase()) ||
        rec.role?.toLowerCase().includes(globalFilter.value.toLowerCase())
      : true;

    const matchesName = !filters.value.name || rec.name?.toLowerCase().includes(filters.value.name.toLowerCase());
    const matchesEmail = !filters.value.email || rec.email?.toLowerCase().includes(filters.value.email.toLowerCase());
    const matchesPhone = !filters.value.phone || rec.phone?.toLowerCase().includes(filters.value.phone.toLowerCase());
    const matchesRole = !filters.value.role || rec.role === filters.value.role;

    return matchesGlobalFilter && matchesName && matchesEmail && matchesPhone && matchesRole;
  });
});

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredRecords.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => Math.ceil(filteredRecords.value.length / perPage.value));

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const clearFilters = () => {
  filters.value = { name: "", email: "", role: "", phone: "" };
  globalFilter.value = "";
  currentPage.value = 1;
};

const exportToExcel = () => {
  const exportData = filteredRecords.value.map((rec, index) => ({
    "#": index + 1,
    [t("name")]: rec.name,
    [t("email")]: rec.email,
    [t("phone_number")]: rec.phone || "----",
    [t("address")]: rec.address,
    [t("userRole")]: rec.role,
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Users");
  XLSX.writeFile(wb, "users_export.xlsx");
};

const addRecord = () => {
  Object.keys(record.value).forEach((key) => { record.value[key] = null; });
  record.value.id = null;
  record.value.discount_percentage = 0;
  dialog.value = true;
};

const editRecord = (rec) => {
  Object.assign(record.value, rec);
  record.value.role_id = UserRoles.value?.find((v) => v.label === rec.role)?.value ?? "";
  dialog.value = true;
};

const assignRole = (rec) => {
  Object.assign(record.value, rec);
  assignDialog.value = true;
};

const deleteRecord = (rec) => {
  showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
    if (res.value) {
      record.value.id = rec.id;
      RemoveUser();
    }
  });
};

const onPageChange = (page) => {
  currentPage.value = page;
};

const getRoleBadgeClass = (role) => {
  const r = role?.toLowerCase();
  if (r === 'admin') return 'bg-red-100 text-red-700';
  if (r === 'lab') return 'bg-purple-100 text-purple-700';
  if (r === 'doctor') return 'bg-blue-100 text-blue-700';
  if (r === 'patient') return 'bg-green-100 text-green-700';
  if (r === 'branch lab') return 'bg-amber-100 text-amber-700';
  return 'bg-slate-100 text-slate-700';
};

const getRoleDotClass = (role) => {
  const r = role?.toLowerCase();
  if (r === 'admin') return 'bg-red-500';
  if (r === 'lab') return 'bg-purple-500';
  if (r === 'doctor') return 'bg-blue-500';
  if (r === 'patient') return 'bg-green-500';
  if (r === 'branch lab') return 'bg-amber-500';
  return 'bg-slate-500';
};
</script>

<template>
  <div class="space-y-6">
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ stats.total }} {{ t("users") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("usersManagment") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_users") || "Manage system users and roles" }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[100px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-red-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("admins") || "Admins" }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.admins }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[100px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-purple-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-purple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("labs") || "Labs" }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.labs }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[100px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("doctors") || "Doctors" }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.doctors }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Users -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("users") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Admins -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("admins") || "Admins" }}</p>
            <p class="text-2xl font-bold text-red-600">{{ stats.admins }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Labs -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("labs") || "Labs" }}</p>
            <p class="text-2xl font-bold text-purple-600">{{ stats.labs }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Doctors -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("doctors") || "Doctors" }}</p>
            <p class="text-2xl font-bold text-blue-600">{{ stats.doctors }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
              @click="toggleFilters"
              class="px-4 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2 relative"
              :class="{ 'bg-primary-50 border-primary-200 text-primary-700': showFilters || activeFiltersCount > 0 }"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <span class="hidden sm:inline">{{ t("filters") || "Filters" }}</span>
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
              v-if="havePermission('users create')"
              @click="addRecord"
              class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all flex items-center gap-2 shadow-lg shadow-primary-500/25"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              {{ t("addUser") }}
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
                  v-model="filters.name"
                  type="text"
                  :placeholder="t('name') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
              <!-- Email Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("email") }}</label>
                <input
                  v-model="filters.email"
                  type="text"
                  :placeholder="t('email') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
              <!-- Phone Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("phone_number") }}</label>
                <input
                  v-model="filters.phone"
                  type="text"
                  :placeholder="t('phone_number') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
              <!-- Role Filter -->
              <div v-if="UserRoles">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("userRole") }}</label>
                <select
                  v-model="filters.role"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option v-for="role in UserRoles" :key="role.value" :value="role.label">{{ role.label }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- ==================== DATA TABLE ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-50 mb-4">
          <svg class="w-8 h-8 text-primary-600 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-slate-500">{{ t("loading") }}...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredRecords.length === 0" class="p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">{{ t("no_users_message") || "No users found. Add your first user to get started." }}</p>
        <button
          v-if="havePermission('users create')"
          @click="addRecord"
          class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all inline-flex items-center gap-2 shadow-lg shadow-primary-500/25"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ t("addUser") }}
        </button>
      </div>

      <!-- Table -->
      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">#</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("email") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("phone_number") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("address") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("userRole") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(user, index) in paginatedRecords"
                :key="user.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ (currentPage - 1) * perPage + index + 1 }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm font-medium text-slate-800">{{ user.name }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">{{ user.email }}</span>
                </td>
                <td class="px-5 py-4" dir="ltr">
                  <span class="text-sm text-slate-600">{{ user.phone || "----" }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">{{ user.address || "----" }}</span>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
                    :class="getRoleBadgeClass(user.role)"
                  >
                    <span class="w-2 h-2 rounded-full" :class="getRoleDotClass(user.role)"></span>
                    {{ user.role || "----" }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-1">
                    <!-- Edit -->
                    <button
                      v-if="havePermission('users edit')"
                      @click="editRecord(user)"
                      class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                      :title="t('update')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <!-- Assign Role -->
                    <button
                      v-if="havePermission('users edit')"
                      @click="assignRole(user)"
                      class="p-2 text-purple-500 hover:text-purple-700 hover:bg-purple-50 rounded-lg transition-all"
                      :title="t('assign_role_user')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </button>
                    <!-- Delete -->
                    <button
                      v-if="havePermission('users delete')"
                      @click="deleteRecord(user)"
                      class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all"
                      :title="t('delete')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-slate-200 bg-gradient-to-r from-slate-50 to-white">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-slate-600">
              {{ t("showing") || "Showing" }}
              <span class="font-semibold text-slate-800">{{ (currentPage - 1) * perPage + 1 }}</span>
              -
              <span class="font-semibold text-slate-800">{{ Math.min(currentPage * perPage, filteredRecords.length) }}</span>
              {{ t("of") || "of" }}
              <span class="font-semibold text-slate-800">{{ filteredRecords.length }}</span>
              {{ t("users") }}
            </p>
            <div class="flex items-center gap-2">
              <button
                @click="onPageChange(currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2"
              >
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t("previous") || "Previous" }}
              </button>
              <div class="flex items-center gap-1">
                <template v-for="page in totalPages" :key="page">
                  <button
                    v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                    @click="onPageChange(page)"
                    class="w-10 h-10 rounded-xl text-sm font-medium transition-all"
                    :class="page === currentPage
                      ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                      : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300'"
                  >
                    {{ page }}
                  </button>
                  <span
                    v-else-if="page === currentPage - 2 || page === currentPage + 2"
                    class="px-2 text-slate-400"
                  >...</span>
                </template>
              </div>
              <button
                @click="onPageChange(currentPage + 1)"
                :disabled="currentPage >= totalPages"
                class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-2"
              >
                {{ t("next") || "Next" }}
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <UsersModal />
    <AuthCodeModal />
    <AssignRoleModel />
  </div>
</template>
