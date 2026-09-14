<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useculturesStore } from "@/store/modules/cultures";
import { useAuthStore } from "@/store/modules/auth";
import { usesamplesStore } from "@/store/modules/samples";
import { useDurationUnitsStore } from "@/store/modules/durationUnits";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import * as XLSX from "xlsx";
import UiModal from "@/components/ui/Modal.vue";
import CultureModal from "./components/culture_modal.vue";
import ResultCommentsModal from "./components/result_comments_modal.vue";
import AttributesModal from "./components/atrributesmodal.vue";

const toast = useToast();

const culturesStore = useculturesStore();
const authStore = useAuthStore();
const samplesStore = usesamplesStore();
const durationUnitsStore = useDurationUnitsStore();
const testGroupsStore = usetestGroupsStore();

const { havePermission } = authStore;
const { cultures, record, dialog, totalCount, resdialog, attrdialog, result_comments, attributesList, attributes } = storeToRefs(culturesStore);
const { Getcultures, Removecultures } = culturesStore;
const { samples } = samplesStore;
const { durationUnitsList } = storeToRefs(durationUnitsStore);
const { Groups } = testGroupsStore;

// Import state
const importDialog = ref(false);
const importFile = ref(null);
const importLoading = ref(false);
const importResult = ref(null);

// Loading state
const isLoading = ref(true);

// Filters
const showFilters = ref(false);
const filters = ref({
  name: "",
  lab: "",
  category: "",
  test_group_name: "",
  precautions: "",
});

// Pagination
const currentPage = ref(1);
const perPage = ref(25);

// Stats computed
const stats = computed(() => {
  const list = cultures.value || [];
  const totalPrice = list.reduce((sum, c) => sum + (c.price || 0), 0);
  const uniqueCategories = new Set(list.map(c => c.category).filter(Boolean));
  const uniqueGroups = new Set(list.map(c => c.test_group_name).filter(Boolean));

  return {
    total: totalCount.value || 0,
    totalPrice,
    totalCategories: uniqueCategories.size,
    totalGroups: uniqueGroups.size,
  };
});

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.name) count++;
  if (filters.value.lab) count++;
  if (filters.value.category) count++;
  if (filters.value.test_group_name) count++;
  if (filters.value.precautions) count++;
  return count;
});

// Filtered records
const filteredRecords = computed(() => {
  if (!cultures.value) return [];
  return cultures.value.filter((culture) => {
    return (
      (!filters.value.name || culture.name?.toLowerCase().includes(filters.value.name.toLowerCase())) &&
      (!filters.value.lab || culture.lab?.toLowerCase().includes(filters.value.lab.toLowerCase())) &&
      (!filters.value.category || culture.category?.toLowerCase().includes(filters.value.category.toLowerCase())) &&
      (!filters.value.test_group_name || culture.test_group_name?.toLowerCase().includes(filters.value.test_group_name.toLowerCase())) &&
      (!filters.value.precautions || culture.precautions?.toLowerCase().includes(filters.value.precautions.toLowerCase()))
    );
  });
});

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return filteredRecords.value.slice(start, start + perPage.value);
});

const totalPages = computed(() => Math.ceil(filteredRecords.value.length / perPage.value));

// Methods
const clearFilters = () => {
  filters.value = { name: "", lab: "", category: "", test_group_name: "", precautions: "" };
  currentPage.value = 1;
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const exportToExcel = () => {
  const exportData = filteredRecords.value.map((c, index) => ({
    "#": index + 1,
    [t("name")]: c.name,
    [t("lab/bruanch")]: c.lab,
    [t("Category")]: c.category,
    [t("Group_Name")]: c.test_group_name,
    [t("precautions")]: c.precautions,
    [t("Original_Price")]: c.price,
  }));
  const ws = XLSX.utils.json_to_sheet(exportData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Cultures");
  XLSX.writeFile(wb, "cultures_export.xlsx");
};

const addRecord = () => {
  Object.keys(record.value).forEach((key) => {
    record.value[key] = null;
  });
  record.value.id = null;
  record.value.selection_type_options = [""];
  record.value.result_comments = [""];
  attributes.value = [{ attribute_name: null, order: null, result_type_id_fk: null, selection_type_options: [""] }];
  dialog.value = true;
};

const editRecord = (rec) => {
  Object.assign(record.value, rec);
  record.value.result_comments = rec.result_comments;
  record.value.sample_id_fk = samples()?.find((q) => q.label === rec?.sample)?.value;
  record.value.duration_unit_id_fk = durationUnitsList.value?.find((q) => q.label === rec.duration_unit)?.value;
  record.value.attribute_id_fk = rec.attribute?.id;
  record.value.test_group_id_fk = Groups()?.find((q) => q.label === rec?.test_group_name)?.value;
  record.value.price = rec.price;
  rec.attribute.selection_type_options = rec.attribute?.selection_type_options || [""];
  attributes.value = rec.attribute;
  dialog.value = true;
};

const deleteRecord = (rec) => {
  showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
    if (res.value) {
      record.value.id = rec.id;
      Removecultures();
    }
  });
};

const showResult = (res) => {
  result_comments.value = res;
  resdialog.value = true;
};

const showAttr = (attr) => {
  attributesList.value = attr;
  attrdialog.value = true;
};

const onPageChange = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
};

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
  window.open(`${baseUrl}/cultures/download-template`, "_blank");
};

const submitImport = async () => {
  if (!importFile.value) return;
  importLoading.value = true;
  importResult.value = null;
  try {
    const data = await culturesStore.ImportCultures(importFile.value);
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
  await Getcultures();
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ stats.total }} {{ t("cultures") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("cultures") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_cultures") }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-primary-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("total") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.total }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("categories") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.totalCategories }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("total_value") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.totalPrice.toLocaleString("en-US") }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Cultures -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total") }} {{ t("cultures") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Categories -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("categories") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.totalCategories }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Groups -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("groups") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.totalGroups }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Filtered Results -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("filtered_results") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ filteredRecords.length }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
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
                v-model="filters.name"
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
              v-if="havePermission('cultures create')"
              @click="openImportDialog"
              class="px-4 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium rounded-xl transition-all flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span class="hidden sm:inline">{{ t("import") }}</span>
            </button>
            <button
              v-if="havePermission('cultures create')"
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
              <!-- Lab Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("lab/bruanch") }}</label>
                <input
                  v-model="filters.lab"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
              <!-- Category Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Category") }}</label>
                <input
                  v-model="filters.category"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
              <!-- Group Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("Group_Name") }}</label>
                <input
                  v-model="filters.test_group_name"
                  type="text"
                  :placeholder="t('search') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>
              <!-- Precautions Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("precautions") }}</label>
                <input
                  v-model="filters.precautions"
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
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">{{ t("no_cultures_desc") }}</p>
        <button
          v-if="havePermission('cultures create')"
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
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider w-20">#</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("lab/bruanch") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Category") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Group_Name") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("attributes") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Result_Comments") }}</th>
                <th class="px-5 py-4 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider w-32">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(culture, index) in paginatedRecords"
                :key="culture.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <!-- Row Number -->
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ (currentPage - 1) * perPage + index + 1 }}
                  </span>
                </td>

                <!-- Name -->
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-primary-500/20">
                      <span class="text-white font-bold text-sm">{{ culture.name?.charAt(0)?.toUpperCase() || 'C' }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="font-semibold text-slate-800 truncate">{{ culture.name }}</p>
                      <p v-if="culture.precautions" class="text-xs text-slate-500 truncate">{{ culture.precautions }}</p>
                    </div>
                  </div>
                </td>

                <!-- Lab/Branch -->
                <td class="px-5 py-4">
                  <span v-if="culture.lab" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">
                    {{ culture.lab }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Category -->
                <td class="px-5 py-4">
                  <span v-if="culture.category" class="inline-flex items-center px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg">
                    {{ culture.category }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Group Name -->
                <td class="px-5 py-4">
                  <span v-if="culture.test_group_name" class="inline-flex items-center px-2.5 py-1 bg-green-50 text-green-700 text-xs font-medium rounded-lg">
                    {{ culture.test_group_name }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Attributes -->
                <td class="px-5 py-4 text-center">
                  <button
                    @click="showAttr(culture.attribute)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-50 text-primary-700 hover:bg-primary-100 rounded-lg transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span class="text-xs font-medium">{{ t("view") }}</span>
                  </button>
                </td>

                <!-- Result Comments -->
                <td class="px-5 py-4 text-center">
                  <button
                    @click="showResult(culture.result_comments)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-lg transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span class="text-xs font-medium">{{ t("view") }}</span>
                  </button>
                </td>

                <!-- Actions -->
                <td class="px-5 py-4">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      v-if="havePermission('cultures edit')"
                      @click="editRecord(culture)"
                      class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                      :title="t('update')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button
                      v-if="havePermission('cultures delete')"
                      @click="deleteRecord(culture)"
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
              <span class="font-semibold text-slate-800">{{ Math.min(currentPage * perPage, filteredRecords.length) }}</span>
              {{ t("of") }}
              <span class="font-semibold text-slate-800">{{ filteredRecords.length }}</span>
              {{ t("cultures") }}
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
    <UiModal v-model="importDialog" :title="t('import') + ' ' + t('cultures')" max-width="lg">
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

    <!-- Modals -->
    <CultureModal />
    <ResultCommentsModal />
    <AttributesModal />
  </div>
</template>
