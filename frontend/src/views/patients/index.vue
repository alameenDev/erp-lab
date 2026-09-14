<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import { storeToRefs } from "pinia";
import { usePatientsStore } from "@/store/modules/patients";
import { useContractsStore } from "@/store/modules/contract";
import { useNationalitiesStore } from "@/store/modules/nationalities";
import { useTitlesStore } from "@/store/modules/titles";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { format } from "date-fns";
import * as XLSX from "xlsx";
import PatientModal from "./components/patient-modal.vue";
import LabCardModal from "./components/labCard-modal.vue";

const patientsStore = usePatientsStore();
const contractsStore = useContractsStore();
const nationalitiesStore = useNationalitiesStore();
const titlesStore = useTitlesStore();
const authStore = useAuthStore();

const { records, record, dialog, labDialog, genders, AgeUnits } = storeToRefs(patientsStore);
const patientGenders = computed(() => genders.value.filter((g) => g.label !== "Both"));
const { records: contractRecords } = storeToRefs(contractsStore);
const { nationalities } = storeToRefs(nationalitiesStore);
const { titles } = storeToRefs(titlesStore);

// Computed contracts list for dropdowns
const contracts = computed(() =>
  contractRecords.value?.map(c => ({ label: c.name, value: c.id })) || []
);

// Loading state
const isLoading = ref(true);

// Filters
const filters = ref({
  code: "",
  title: "",
  name: "",
  email: "",
  lab: "",
  phone_number: "",
  lab_card: "",
  address: "",
  nationality: "",
  dob: null,
  contract: "",
  gender: "",
});

// Filter dropdown visibility
const showFilters = ref(false);

// Filtered records
const filteredRecords = computed(() => {
  return records.value.filter((rec) => {
    return (
      (!filters.value.code || rec.code?.toLowerCase().includes(filters.value.code.toLowerCase())) &&
      (!filters.value.title || rec.title?.toLowerCase().includes(filters.value.title.toLowerCase())) &&
      (!filters.value.name || rec.name?.toLowerCase().includes(filters.value.name.toLowerCase())) &&
      (!filters.value.email || rec.email?.toLowerCase().includes(filters.value.email.toLowerCase())) &&
      (!filters.value.lab || rec.lab?.toLowerCase().includes(filters.value.lab.toLowerCase())) &&
      (!filters.value.phone_number || rec.phone_number?.includes(filters.value.phone_number)) &&
      (!filters.value.lab_card || rec.lab_card?.includes(filters.value.lab_card)) &&
      (!filters.value.address || rec.address?.toLowerCase().includes(filters.value.address.toLowerCase())) &&
      (!filters.value.nationality || rec.nationality?.toLowerCase().includes(filters.value.nationality.toLowerCase())) &&
      (!filters.value.gender || rec.gender === filters.value.gender) &&
      (!filters.value.contract || rec.contract?.name === filters.value.contract)
    );
  });
});

// Stats computed
const stats = computed(() => {
  const total = filteredRecords.value.length;
  const male = filteredRecords.value.filter(p => p.gender?.toLowerCase() === 'male' || p.gender === 'ذكر').length;
  const female = filteredRecords.value.filter(p => p.gender?.toLowerCase() === 'female' || p.gender === 'أنثى').length;
  const withContract = filteredRecords.value.filter(p => p.contract?.name).length;

  return { total, male, female, withContract };
});

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.gender) count++;
  if (filters.value.contract) count++;
  if (filters.value.nationality) count++;
  if (filters.value.title) count++;
  if (filters.value.lab) count++;
  if (filters.value.address) count++;
  return count;
});

// Pagination
const currentPage = ref(1);
const perPage = ref(25);

const paginatedRecords = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  const end = start + perPage.value;
  return filteredRecords.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(filteredRecords.value.length / perPage.value));

// Methods
const clearFilters = () => {
  filters.value = {
    code: "",
    title: "",
    name: "",
    email: "",
    lab: "",
    phone_number: "",
    lab_card: "",
    address: "",
    nationality: "",
    dob: null,
    contract: "",
    gender: "",
  };
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const exportToExcel = () => {
  const ws = XLSX.utils.json_to_sheet(filteredRecords.value, {
    header: [
      "id", "code", "title", "name", "email", "lab", "role", "phone_number",
      "lab_card", "address", "contract_name", "nationality", "dob", "gender",
      "age", "age_unit", "passport_no", "national_id_no",
    ],
  });
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Patients");
  XLSX.writeFile(wb, "patients_export.xlsx");
};

const addRecord = () => {
  Object.keys(record.value).forEach((key) => {
    if (typeof record.value[key] === "string") record.value[key] = "";
    else if (typeof record.value[key] === "number") record.value[key] = 0;
    else if (Array.isArray(record.value[key])) record.value[key] = [];
    else record.value[key] = null;
  });
  dialog.value = true;
};

const editRecord = (data) => {
  Object.assign(record.value, data);
  record.value.title_id_fk = titles.value.find((v) => v.label === data?.title)?.value ?? null;
  record.value.age_unit_id_fk = AgeUnits.value.find((v) => v.label === data?.age_unit)?.value ?? null;
  record.value.gender_type_id_fk = genders.value.find((v) => v.label === data?.gender)?.value ?? null;
  record.value.contract_id_fk = contracts.value.find((v) => v.label === data.contract?.name)?.value ?? null;
  record.value.nationality_id_fk = nationalities.value.find((v) => v.label === data?.nationality)?.value ?? null;
  record.value.phone = data?.phone_number || data?.phone || "";
  dialog.value = true;
};

const deleteRecord = async (data) => {
  const result = await showAlertWithConfirm(t("AlertWithConfirm"));
  if (result.value) {
    record.value.id = data.id;
    await patientsStore.RemovePatient();
  }
};

const showLabCard = (data) => {
  Object.assign(record.value, data);
  labDialog.value = true;
};

const onPageChange = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
};

const formatDate = (date) => {
  if (!date) return "";
  try {
    return format(new Date(date), "yyyy-MM-dd");
  } catch {
    return date;
  }
};

// Watchers - reset to page 1 when filters change
watch(
  filters,
  () => {
    currentPage.value = 1;
  },
  { deep: true }
);

const route = useRoute();

onMounted(async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      patientsStore.GetRecords(),
      contractsStore.GetRecords(),
      nationalitiesStore.GetNationalities(),
      titlesStore.GetTitles()
    ]);
    const editId = route.query.edit;
    if (editId) {
      const target = records.value.find((p) => String(p.id) === String(editId));
      if (target) editRecord(target);
    } else if (route.query.create) {
      addRecord();
    }
  } finally {
    isLoading.value = false;
  }
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ stats.total }} {{ t("patients") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("patientsManagment") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_patient_records") || "Manage patient records and information" }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("gender") === 'Male' ? 'Male' : 'ذكر' }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.male }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-pink-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-pink-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("gender") === 'Female' ? 'Female' : 'أنثى' }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.female }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Patients -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total_patients") || "Total Patients" }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Male Patients -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("male_patients") || "Male" }}</p>
            <p class="text-2xl font-bold text-blue-600">{{ stats.male }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Female Patients -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("female_patients") || "Female" }}</p>
            <p class="text-2xl font-bold text-pink-600">{{ stats.female }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-pink-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- With Contract -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("with_contract") || "With Contract" }}</p>
            <p class="text-2xl font-bold text-green-600">{{ stats.withContract }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
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
                v-model="filters.name"
                type="text"
                :placeholder="t('patient_name') + '...'"
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
              v-if="authStore.havePermission('patients create')"
              @click="addRecord"
              class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all flex items-center gap-2 shadow-lg shadow-primary-500/25"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              {{ t("addPatient") }}
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
              <!-- Code Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("code") }}</label>
                <input
                  v-model="filters.code"
                  type="text"
                  :placeholder="t('code') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Gender Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("gender") }}</label>
                <select
                  v-model="filters.gender"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option v-for="gender in patientGenders" :key="gender.value" :value="gender.label">
                    {{ gender.label }}
                  </option>
                </select>
              </div>

              <!-- Contract Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("contract") }}</label>
                <select
                  v-model="filters.contract"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option v-for="contract in contracts" :key="contract.value" :value="contract.label">
                    {{ contract.label }}
                  </option>
                </select>
              </div>

              <!-- Nationality Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("nationality") }}</label>
                <select
                  v-model="filters.nationality"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option v-for="nat in nationalities" :key="nat.value" :value="nat.label">
                    {{ nat.label }}
                  </option>
                </select>
              </div>

              <!-- Phone Number -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("phone_number") }}</label>
                <input
                  v-model="filters.phone_number"
                  type="text"
                  :placeholder="t('phone_number') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Lab/Branch -->
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
      <div v-else-if="records.length === 0" class="p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">{{ t("no_patients_message") || "No patients found. Add your first patient to get started." }}</p>
        <button
          v-if="authStore.havePermission('patients create')"
          @click="addRecord"
          class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all inline-flex items-center gap-2 shadow-lg shadow-primary-500/25"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ t("addPatient") }}
        </button>
      </div>

      <!-- Table -->
      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">#</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("code") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("phone_number") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("gender") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("age") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("lab/bruanch") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("contract") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(patient, index) in paginatedRecords"
                :key="patient.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ (currentPage - 1) * perPage + index + 1 }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-2.5 py-1 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg">
                    {{ patient.code }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm">
                      {{ patient.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div>
                      <p class="font-medium text-slate-900">{{ patient.name }}</p>
                      <p class="text-xs text-slate-500">{{ patient.email || '-' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600" dir="ltr">{{ patient.phone_number || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span
                    v-if="patient.gender"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
                    :class="patient.gender?.toLowerCase() === 'male' || patient.gender === 'ذكر'
                      ? 'bg-blue-100 text-blue-700'
                      : 'bg-pink-100 text-pink-700'"
                  >
                    <span
                      class="w-2 h-2 rounded-full"
                      :class="patient.gender?.toLowerCase() === 'male' || patient.gender === 'ذكر' ? 'bg-blue-500' : 'bg-pink-500'"
                    ></span>
                    {{ patient.gender }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">
                    {{ patient.age || '-' }}
                    <span v-if="patient.age_unit" class="text-slate-400">{{ patient.age_unit }}</span>
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm font-medium text-slate-700">{{ patient.lab || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span v-if="patient.contract?.name" class="inline-flex items-center px-2.5 py-1 bg-green-50 text-green-700 text-xs font-medium rounded-lg">
                    {{ patient.contract.name }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-1">
                    <!-- Edit -->
                    <button
                      v-if="authStore.havePermission('patients edit')"
                      @click="editRecord(patient)"
                      class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                      :title="t('update')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <!-- Delete -->
                    <button
                      v-if="authStore.havePermission('patients delete')"
                      @click="deleteRecord(patient)"
                      class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all"
                      :title="t('delete')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                    <!-- Lab Card -->
                    <button
                      @click="showLabCard(patient)"
                      class="p-2 text-primary-500 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-all"
                      :title="t('lab_card')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
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
              {{ t("patients") }}
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
    <PatientModal />
    <LabCardModal />
  </div>
</template>
