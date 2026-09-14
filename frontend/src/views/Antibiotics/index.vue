<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useAntibioticsStore } from "@/store/modules/Antibiotics";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import * as XLSX from "xlsx";
import UiModal from "@/components/ui/Modal.vue";
import AntibioticsModal from "./components/Anti_modal.vue";

const toast = useToast();

const antibioticsStore = useAntibioticsStore();
const authStore = useAuthStore();
const { havePermission } = authStore;
const { Antibiotics, totalCount, record, dialog } = storeToRefs(antibioticsStore);
const { GetAntibiotics, RemoveAntibiotics } = antibioticsStore;

// Import state
const importDialog = ref(false);
const importFile = ref(null);
const importLoading = ref(false);
const importResult = ref(null);

// Loading state
const isLoading = ref(true);

// Filters
const filters = ref({
  scientific_name: "",
  common_name: "",
  short_name: "",
  lab: "",
});

// Filter dropdown visibility
const showFilters = ref(false);

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
  const baseUrl = import.meta.env.VITE_BASE_URL || "/api";
  window.open(`${baseUrl}/antibiotics/download-template`, "_blank");
};

const submitImport = async () => {
  if (!importFile.value) return;
  importLoading.value = true;
  importResult.value = null;
  try {
    const data = await antibioticsStore.ImportAntibiotics(importFile.value);
    importResult.value = data;
    if (data.created > 0) {
      toast.success(data.message);
    }
  } catch (e) {
    const resp = e.response?.data;
    if (resp) {
      importResult.value = resp;
    } else {
      importResult.value = { message: e.message, created: 0, skipped: 0, errors: [] };
    }
  } finally {
    importLoading.value = false;
  }
};

onMounted(async () => {
  isLoading.value = true;
  try {
    await GetAntibiotics();
  } finally {
    isLoading.value = false;
  }
});

const filteredAntibiotics = computed(() => {
  if (!Antibiotics.value) return [];
  return Antibiotics.value.filter((antibiotic) => {
    const matchesScientificName = !filters.value.scientific_name || antibiotic.scientific_name?.toLowerCase().includes(filters.value.scientific_name.toLowerCase());
    const matchesCommonName = !filters.value.common_name || antibiotic.common_name?.toLowerCase().includes(filters.value.common_name.toLowerCase());
    const matchesShortName = !filters.value.short_name || antibiotic.short_name?.toLowerCase().includes(filters.value.short_name.toLowerCase());
    const matchesLab = !filters.value.lab || antibiotic.lab?.toLowerCase().includes(filters.value.lab.toLowerCase());

    return matchesScientificName && matchesCommonName && matchesShortName && matchesLab;
  });
});

// Stats computed
const stats = computed(() => {
  const total = totalCount.value || 0;
  const list = Antibiotics.value || [];
  const uniqueLabs = new Set(list.map(a => a.lab).filter(Boolean));
  const filtered = filteredAntibiotics.value.length;

  return { total, labs: uniqueLabs.size, filtered };
});

// Pagination
const currentPage = ref(1);
const perPage = ref(25);

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredAntibiotics.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => Math.ceil(filteredAntibiotics.value.length / perPage.value));

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.scientific_name) count++;
  if (filters.value.common_name) count++;
  if (filters.value.short_name) count++;
  if (filters.value.lab) count++;
  return count;
});

const clearFilters = () => {
  filters.value = { scientific_name: "", common_name: "", short_name: "", lab: "" };
  currentPage.value = 1;
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const exportToExcel = () => {
  const exportData = filteredAntibiotics.value.map((rec, i) => ({
    "#": i + 1,
    [t("scientific_name")]: rec.scientific_name,
    [t("common_name")]: rec.common_name,
    [t("short_name")]: rec.short_name,
    [t("lab/bruanch")]: rec.lab,
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Antibiotics");
  XLSX.writeFile(wb, "antibiotics_export.xlsx");
};

const addRecord = () => {
  Object.keys(record.value).forEach((key) => { record.value[key] = null; });
  record.value.id = null;
  dialog.value = true;
};

const editRecord = (rec) => {
  Object.assign(record.value, rec);
  dialog.value = true;
};

const deleteRecord = (rec) => {
  showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
    if (res.value) {
      record.value.id = rec.id;
      RemoveAntibiotics();
    }
  });
};

const onPageChange = (page) => {
  currentPage.value = page;
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ stats.total }} {{ t("Antibiotics") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("Antibiotics") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_antibiotics") }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("labs") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.labs }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("filtered") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.filtered }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Antibiotics -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total_antibiotics") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Labs -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("labs") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.labs }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Filtered Results -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("filtered_results") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.filtered }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Current Page -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("current_page") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ currentPage }} / {{ totalPages || 1 }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
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
                v-model="filters.scientific_name"
                type="text"
                :placeholder="t('scientific_name') + '...'"
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
              v-if="havePermission('antibiotics create')"
              @click="openImportDialog"
              class="px-4 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span class="hidden sm:inline">{{ t("import") }}</span>
            </button>
            <button
              v-if="havePermission('antibiotics create')"
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
              <!-- Scientific Name Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("scientific_name") }}</label>
                <input
                  v-model="filters.scientific_name"
                  type="text"
                  :placeholder="t('scientific_name') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Common Name Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("common_name") }}</label>
                <input
                  v-model="filters.common_name"
                  type="text"
                  :placeholder="t('common_name') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Short Name Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("short_name") }}</label>
                <input
                  v-model="filters.short_name"
                  type="text"
                  :placeholder="t('short_name') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Lab Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("lab/bruanch") }}</label>
                <input
                  v-model="filters.lab"
                  type="text"
                  :placeholder="t('lab/bruanch') + '...'"
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
      <div v-else-if="totalCount === 0" class="p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">{{ t("no_antibiotics_desc") }}</p>
        <button
          v-if="havePermission('antibiotics create')"
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
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("scientific_name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("common_name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("short_name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("lab/bruanch") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(antibiotic, index) in paginatedRecords"
                :key="antibiotic.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ (currentPage - 1) * perPage + index + 1 }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                      </svg>
                    </div>
                    <span class="font-medium text-slate-700">{{ antibiotic.scientific_name }}</span>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">{{ antibiotic.common_name || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span v-if="antibiotic.short_name" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">
                    {{ antibiotic.short_name }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-5 py-4">
                  <span v-if="antibiotic.lab" class="inline-flex items-center px-2.5 py-1 bg-green-50 text-green-700 text-xs font-medium rounded-lg">
                    {{ antibiotic.lab }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-1">
                    <!-- Edit -->
                    <button
                      v-if="havePermission('antibiotics edit')"
                      @click="editRecord(antibiotic)"
                      class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                      :title="t('update')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>

                    <!-- Delete -->
                    <button
                      v-if="havePermission('antibiotics delete')"
                      @click="deleteRecord(antibiotic)"
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
              <span class="font-semibold text-slate-800">{{ Math.min(currentPage * perPage, filteredAntibiotics.length) }}</span>
              {{ t("of") }}
              <span class="font-semibold text-slate-800">{{ filteredAntibiotics.length }}</span>
              {{ t("Antibiotics") }}
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

    <!-- Import Modal -->
    <UiModal v-model="importDialog" :title="t('import') + ' ' + t('Antibiotics')" max-width="lg">
      <div class="space-y-5">
        <!-- Download Template Banner -->
        <div class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-200 rounded-xl">
          <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-blue-900">{{ t("download_template") }}</p>
            <p class="text-xs text-blue-700 mt-0.5">{{ t("download_template_desc") }}</p>
          </div>
          <button @click="downloadTemplate" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors flex-shrink-0">
            {{ t("download") }}
          </button>
        </div>

        <!-- File Upload -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("select_file") }}</label>
          <input type="file" accept=".xlsx,.xls" @change="onImportFileChange" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 file:mr-4 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" />
        </div>

        <!-- Import Result -->
        <div v-if="importResult" class="space-y-3">
          <div class="flex gap-3">
            <div class="flex-1 p-3 rounded-xl" :class="importResult.created > 0 ? 'bg-green-50 border border-green-200' : 'bg-slate-50 border border-slate-200'">
              <p class="text-xs font-medium" :class="importResult.created > 0 ? 'text-green-600' : 'text-slate-500'">{{ t("imported") }}</p>
              <p class="text-xl font-bold" :class="importResult.created > 0 ? 'text-green-700' : 'text-slate-700'">{{ importResult.created }}</p>
            </div>
            <div class="flex-1 p-3 rounded-xl" :class="importResult.skipped > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-slate-50 border border-slate-200'">
              <p class="text-xs font-medium" :class="importResult.skipped > 0 ? 'text-amber-600' : 'text-slate-500'">{{ t("skipped") }}</p>
              <p class="text-xl font-bold" :class="importResult.skipped > 0 ? 'text-amber-700' : 'text-slate-700'">{{ importResult.skipped }}</p>
            </div>
          </div>
          <div v-if="importResult.errors?.length" class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-sm font-semibold text-red-800 mb-2">{{ t("import_errors") }} ({{ importResult.errors.length }})</p>
            <div class="max-h-48 overflow-y-auto space-y-1">
              <p v-for="(err, i) in importResult.errors" :key="i" class="text-xs text-red-700">{{ i + 1 }}. {{ err }}</p>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-3 pt-2">
          <button @click="importDialog = false" class="px-5 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all">
            {{ t("close") }}
          </button>
          <button @click="submitImport" :disabled="!importFile || importLoading" class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            <svg v-if="importLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ importLoading ? t("importing") + "..." : t("import") }}
          </button>
        </div>
      </div>
    </UiModal>

    <AntibioticsModal />
  </div>
</template>
