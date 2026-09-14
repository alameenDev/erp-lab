<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { useDurationUnitsStore } from "@/store/modules/durationUnits";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm, clearObjectValues } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import * as XLSX from "xlsx";
import UiModal from "@/components/ui/Modal.vue";
import TestGroupModal from "./components/testGroup-modal.vue";
import ResultCommentsModal from "./components/result_comments_modal.vue";
import TestsModal from "./components/tests_modal.vue";
import CultureModal from "./components/cultures_modal.vue";

const toast = useToast();
const testGroupsStore = usetestGroupsStore();
const durationUnitsStore = useDurationUnitsStore();
const authStore = useAuthStore();
const { havePermission } = authStore;
const { testGroups, totalCount, record, dialog, cultures, culturedialog, resdialog, testdialog, tests, result_comments } = storeToRefs(testGroupsStore);
const { durationUnitsList } = storeToRefs(durationUnitsStore);
const { GettestGroups, RemovetestGroups } = testGroupsStore;

// Import Excel
const importDialog = ref(false);
const importFile = ref(null);
const importLoading = ref(false);
const importResult = ref(null);

const openImportDialog = () => {
  importFile.value = null;
  importResult.value = null;
  importLoading.value = false;
  importDialog.value = true;
};

const onImportFileChange = (e) => {
  importFile.value = e.target.files[0] || null;
  importResult.value = null;
};

const downloadTemplate = () => {
  const link = document.createElement("a");
  link.href = "/api/test_groups/download-template";
  link.download = "test_groups_import_template.xlsx";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const submitImport = async () => {
  if (!importFile.value) return;
  importLoading.value = true;
  importResult.value = null;
  try {
    const data = await testGroupsStore.ImportTestGroups(importFile.value);
    importResult.value = data;
    if (data.created > 0) {
      toast.success(`${data.created} ${t("test-groups")} ${t("alertSuccess")}`);
    }
  } catch (error) {
    const errData = error?.response?.data;
    importResult.value = {
      created: errData?.created ?? 0,
      skipped: errData?.skipped ?? 0,
      errors: errData?.errors || [errData?.message || "Import failed"],
    };
  } finally {
    importLoading.value = false;
  }
};

// Loading state
const isLoading = ref(true);

// Filters
const showFilters = ref(false);
const filters = ref({
  group_name: "",
  category_name: "",
  lab_name: "",
  shortcut: "",
  for_customer_price: "",
  original_price: "",
});

// Pagination
const currentPage = ref(1);
const perPage = ref(25);

// Stats computed
const stats = computed(() => {
  const groups = testGroups.value || [];
  const uniqueCategories = new Set(groups.map(g => g.category_name).filter(Boolean));
  const uniqueSamples = new Set(groups.map(g => g.sample_name).filter(Boolean));
  const totalTests = groups.reduce((sum, g) => sum + (g.tests?.length || 0), 0);
  const totalCultures = groups.reduce((sum, g) => sum + (g.culture?.length || 0), 0);

  return {
    totalGroups: totalCount.value || 0,
    categories: uniqueCategories.size,
    totalTests,
    totalCultures
  };
});

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.group_name) count++;
  if (filters.value.category_name) count++;
  if (filters.value.lab_name) count++;
  if (filters.value.shortcut) count++;
  if (filters.value.for_customer_price) count++;
  if (filters.value.original_price) count++;
  return count;
});

// Filtered test groups
const filteredTestGroups = computed(() => {
  if (!testGroups.value) return [];
  return testGroups.value.filter((group) => {
    const q = filters.value.group_name?.toLowerCase() || "";
    const matchesTests = group.tests?.some((t) => t.name?.toLowerCase().includes(q) || t.shortcut?.toLowerCase().includes(q) || t.report_name?.toLowerCase().includes(q));
    const matchesSearch = !q || group.group_name?.toLowerCase().includes(q) || group.shortcut?.toLowerCase().includes(q) || matchesTests;
    return (
      matchesSearch &&
      (!filters.value.category_name || group.category_name?.toLowerCase().includes(filters.value.category_name.toLowerCase())) &&
      (!filters.value.lab_name || group.lab_name?.toLowerCase().includes(filters.value.lab_name.toLowerCase())) &&
      (!filters.value.shortcut || group.shortcut?.toLowerCase().includes(filters.value.shortcut.toLowerCase())) &&
      (!filters.value.for_customer_price || group.for_customer_price?.toString().includes(filters.value.for_customer_price.toString())) &&
      (!filters.value.original_price || group.original_price?.toString().includes(filters.value.original_price.toString()))
    );
  });
});

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredTestGroups.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => Math.ceil(filteredTestGroups.value.length / perPage.value));

// Methods
const clearFilters = () => {
  filters.value = {
    group_name: "",
    category_name: "",
    lab_name: "",
    shortcut: "",
    for_customer_price: "",
    original_price: "",
  };
  currentPage.value = 1;
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const exportToExcel = () => {
  const exportData = filteredTestGroups.value.map((rec, i) => ({
    "#": i + 1,
    [t("Group_Name")]: rec.group_name,
    [t("Category")]: rec.category_name,
    [t("lab/bruanch")]: rec.lab_name,
    [t("Shortcut")]: rec.shortcut,
    [t("Customer_Price")]: rec.for_customer_price,
    [t("Original_Price")]: rec.original_price,
    [t("Test_Duration")]: rec.test_duration,
    [t("Duration_Unit")]: rec.duration_unit,
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "TestGroups");
  XLSX.writeFile(wb, "test_groups_export.xlsx");
};

const convertToArray = (param) => {
  if (!Array.isArray(param)) {
    return param ? [param] : convertStringToArray(param);
  }
  return param;
};

const convertStringToArray = (str) => {
  const formattedStr = str?.replace(/'/g, '"');
  try {
    return JSON.parse(formattedStr);
  } catch (e) {
    return [];
  }
};

const addRecord = () => {
  clearObjectValues(record.value);
  record.value.result_comments = [""];
  dialog.value = true;
};

const editRecord = (rec) => {
  Object.assign(record.value, rec);
  record.value.original_price = rec.original_price;
  record.value.result_comments = convertToArray(rec.result_comments);
  record.value.is_print_alone = rec.is_print_alone == 1;
  record.value.culture_ids = rec.culture?.map((item) => item.id) || [];
  record.value.test_ids = rec.tests?.map((item) => item.id) || [];
  dialog.value = true;
};

const deleteRecord = (rec) => {
  showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
    if (res.value) {
      record.value.id = rec.id;
      RemovetestGroups();
    }
  });
};

const showResult = (result) => {
  result_comments.value = result;
  resdialog.value = true;
};

const showtests = (testsList) => {
  tests.value = testsList;
  testdialog.value = true;
};

const showculture = (cultureList) => {
  cultures.value = cultureList;
  culturedialog.value = true;
};

const onPageChange = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
};

onMounted(async () => {
  await GettestGroups();
  isLoading.value = false;
});
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ stats.totalGroups }} {{ t("test-groups") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("test-groups") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_test_groups") }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-primary-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("categories") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.categories }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("tests") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.totalTests }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Groups -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("test-groups") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.totalGroups }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Categories -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("categories") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.categories }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Total Tests -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("tests") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.totalTests }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Total Cultures -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("cultures") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.totalCultures }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== FILTERS & TABLE CARD ==================== -->
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
                v-model="filters.group_name"
                type="text"
                :placeholder="t('Group_Name') + ' / ' + t('shortcut') + '...'"
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
              v-if="havePermission('test groups create')"
              @click="openImportDialog"
              class="px-4 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span class="hidden sm:inline">{{ t("import_excel") }}</span>
            </button>
            <button
              v-if="havePermission('test groups create')"
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              <!-- Category Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Category") }}</label>
                <input
                  v-model="filters.category_name"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Lab Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("lab/bruanch") }}</label>
                <input
                  v-model="filters.lab_name"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Shortcut Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Shortcut") }}</label>
                <input
                  v-model="filters.shortcut"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Customer Price Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Customer_Price") }}</label>
                <input
                  v-model="filters.for_customer_price"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Original Price Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Original_Price") }}</label>
                <input
                  v-model="filters.original_price"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Loading State -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="inline-flex items-center justify-center">
          <svg class="animate-spin h-10 w-10 text-primary-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-slate-500 mt-3">{{ t("loading") }}...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="totalCount === 0" class="p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">{{ t("no_test_groups_message") }}</p>
        <button
          v-if="havePermission('test groups create')"
          @click="addRecord"
          class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all inline-flex items-center gap-2 shadow-lg shadow-primary-500/25"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ t("add") }}
        </button>
      </div>

      <!-- Table -->
      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">#</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Group_Name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Category") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("lab/bruanch") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Shortcut") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Customer_Price") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Original_Price") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Test_Duration") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Result_Comments") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("tests") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("cultures") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(group, index) in paginatedRecords"
                :key="group.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <!-- Row Number -->
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ (currentPage - 1) * perPage + index + 1 }}
                  </span>
                </td>

                <!-- Group Name -->
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-primary-500/20">
                      <span class="text-white font-bold text-sm">{{ group.group_name?.charAt(0)?.toUpperCase() || 'G' }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="font-semibold text-slate-800 truncate">{{ group.group_name }}</p>
                      <p class="text-xs text-slate-500">{{ group.shortcut || '-' }}</p>
                    </div>
                  </div>
                </td>

                <!-- Category -->
                <td class="px-5 py-4">
                  <span v-if="group.category_name" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">
                    {{ group.category_name }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Lab/Branch -->
                <td class="px-5 py-4">
                  <span class="text-sm font-medium text-slate-700">{{ group.lab_name || '-' }}</span>
                </td>

                <!-- Shortcut -->
                <td class="px-5 py-4">
                  <span v-if="group.shortcut" class="inline-flex items-center px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-mono rounded-lg">
                    {{ group.shortcut }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Customer Price -->
                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-2.5 py-1 bg-green-50 text-green-700 text-sm font-bold rounded-lg">
                    {{ group.for_customer_price?.toLocaleString("en-US") || 0 }}
                  </span>
                </td>

                <!-- Original Price -->
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">{{ group.original_price?.toLocaleString("en-US") || 0 }}</span>
                </td>

                <!-- Duration -->
                <td class="px-5 py-4">
                  <span v-if="group.test_duration" class="text-sm text-slate-600">
                    {{ group.test_duration }} {{ group.duration_unit }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Result Comments -->
                <td class="px-5 py-4 text-center">
                  <button
                    @click="showResult(group.result_comments)"
                    class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                    :title="t('Result_Comments')"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                  </button>
                </td>

                <!-- Tests -->
                <td class="px-5 py-4 text-center">
                  <button
                    @click="showtests(group.tests)"
                    class="p-2 text-primary-500 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-all relative"
                    :title="t('tests')"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span v-if="group.tests?.length" class="absolute -top-1 -end-1 w-4 h-4 bg-primary-600 text-white text-xs rounded-full flex items-center justify-center">
                      {{ group.tests.length }}
                    </span>
                  </button>
                </td>

                <!-- Cultures -->
                <td class="px-5 py-4 text-center">
                  <button
                    @click="showculture(group.culture)"
                    class="p-2 text-purple-500 hover:text-purple-700 hover:bg-purple-50 rounded-lg transition-all relative"
                    :title="t('cultures')"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <span v-if="group.culture?.length" class="absolute -top-1 -end-1 w-4 h-4 bg-purple-600 text-white text-xs rounded-full flex items-center justify-center">
                      {{ group.culture.length }}
                    </span>
                  </button>
                </td>

                <!-- Actions -->
                <td class="px-5 py-4">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      v-if="havePermission('test groups edit')"
                      @click="editRecord(group)"
                      class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                      :title="t('update')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button
                      v-if="havePermission('test groups delete')"
                      @click="deleteRecord(group)"
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
              {{ t("showing") }}
              <span class="font-semibold text-slate-800">{{ (currentPage - 1) * perPage + 1 }}</span>
              -
              <span class="font-semibold text-slate-800">{{ Math.min(currentPage * perPage, filteredTestGroups.length) }}</span>
              {{ t("of") }}
              <span class="font-semibold text-slate-800">{{ filteredTestGroups.length }}</span>
              {{ t("test-groups") }}
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
                {{ t("previous") }}
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
                {{ t("next") }}
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
    <TestGroupModal />
    <ResultCommentsModal />
    <TestsModal />
    <CultureModal />

    <!-- Import Excel Modal -->
    <UiModal v-model="importDialog" :title="t('import_excel')" size="lg">
      <div class="space-y-5">
        <!-- Download Template Banner -->
        <div class="flex items-center gap-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-100">
          <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-slate-800">{{ t("download_template") }}</p>
            <p class="text-sm text-slate-500 mt-0.5">{{ t("download_template_desc") }}</p>
          </div>
          <button
            @click="downloadTemplate"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors flex items-center gap-2 flex-shrink-0"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ t("download") }}
          </button>
        </div>

        <!-- File Upload -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("select_file") }}</label>
          <input
            type="file"
            accept=".xlsx,.xls"
            @change="onImportFileChange"
            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
          />
          <p class="text-xs text-slate-400 mt-1.5">{{ t("accepted_formats") }}</p>
        </div>

        <!-- Import Result -->
        <div v-if="importResult" class="space-y-4">
          <!-- Summary Cards -->
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
              <p class="text-2xl font-bold text-green-700">{{ importResult.created }}</p>
              <p class="text-xs font-medium text-green-600 mt-1">{{ t("created") }}</p>
            </div>
            <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-100">
              <p class="text-2xl font-bold text-amber-700">{{ importResult.skipped }}</p>
              <p class="text-xs font-medium text-amber-600 mt-1">{{ t("skipped") }}</p>
            </div>
          </div>

          <!-- Errors List -->
          <div v-if="importResult.errors?.length" class="bg-red-50 rounded-xl border border-red-100 overflow-hidden">
            <div class="px-4 py-3 bg-red-100/50 border-b border-red-100">
              <p class="text-sm font-semibold text-red-800">{{ t("import_errors") }} ({{ importResult.errors.length }})</p>
            </div>
            <div class="max-h-60 overflow-y-auto p-3 space-y-2">
              <div
                v-for="(error, index) in importResult.errors"
                :key="index"
                class="flex gap-3 p-2.5 bg-white rounded-lg border border-red-100"
              >
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-red-100 text-red-600 text-xs font-bold flex items-center justify-center mt-0.5">
                  {{ index + 1 }}
                </span>
                <p class="text-sm text-red-700 leading-relaxed">{{ error }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
          <button
            type="button"
            @click="importDialog = false"
            class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
          >
            {{ t("cancel") }}
          </button>
          <button
            type="button"
            @click="submitImport"
            :disabled="!importFile || importLoading"
            class="px-5 py-2.5 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors flex items-center gap-2"
          >
            <svg v-if="importLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            {{ importLoading ? t("importing") : t("import") }}
          </button>
        </div>
      </div>
    </UiModal>
  </div>
</template>
