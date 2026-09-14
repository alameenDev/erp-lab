<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useReportsStore } from "@/store/modules/reports";
import { uselabsStore } from "@/store/modules/labs";
import { useReferralsStore } from "@/store/modules/referrals";
import { useContractsStore } from "@/store/modules/contract";
import { useUsersStore } from "@/store/modules/users";
import { t, decodeToken } from "@/utils/helper";
import * as XLSX from "xlsx";
import TestsModal from "./components/tests_Modal.vue";
import CulturesModal from "./components/cultures_Modal.vue";
import ContractsModal from "./components/contractsModal.vue";
import PackagesModal from "./components/packagesModal.vue";
import ReferalsModal from "./components/referals.vue";

const reportsStore = useReportsStore();
const labsStore = uselabsStore();
const referralsStore = useReferralsStore();
const contractsStore = useContractsStore();
const usersStore = useUsersStore();

const { record, reports, totalCount, testdialog, tests } = storeToRefs(reportsStore);
const { labs, collectorsList } = storeToRefs(labsStore);
const { records } = storeToRefs(referralsStore);
const { records: contractsList } = storeToRefs(contractsStore);
const { branches } = storeToRefs(usersStore);

const { Getreports } = reportsStore;
const { Getlabs, collectors } = labsStore;
const { GetRecords: GetReferrals } = referralsStore;
const { GetRecords: GetContracts } = contractsStore;
const { GetRecords: GetBranches } = usersStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");
const User = computed(() => {
  try {
    return decodeToken();
  } catch {
    return null;
  }
});
const Role = computed(() => User.value?.role_id);

const branchData = ref({});
const isLoading = ref(true);
const showFilters = ref(false);

// Active tab
const activeTab = ref("tests");

// Filters for each tab
const testFilters = ref({ name: "", count: "", total: "" });
const cultureFilters = ref({ name: "", count: "", total: "" });
const packagesFilters = ref({ name: "", count: "", total: "" });
const testGroupsFilters = ref({ name: "", count: "", total: "" });
const contractsFilters = ref({ name: "", count: "", discount_percentage: "" });
const referalsFilters = ref({ name: "", count: "", discount_percentage: "" });
const patientsFilters = ref({ name: "", count: "", total_invoices: "", total_due: "", total_paid: "" });
const usersFilters = ref({ name: "", count: "", total_invoices: "" });
const payment_methodsFilters = ref({ name: "", count: "", total: "" });
const invoicesFilters = ref({ patient_name: "", due: "", subtotal: "", total: "", discount: "", contract: "", discount_percentage: "", referal: "", commission: "" });

// Global filters
const testGlobalFilter = ref("");
const cultureGlobalFilter = ref("");
const packagesGlobalFilter = ref("");
const testGroupsGlobalFilter = ref("");
const contractsGlobalFilter = ref("");
const referalsGlobalFilter = ref("");
const patientsGlobalFilter = ref("");
const usersGlobalFilter = ref("");
const payment_methodsGlobalFilter = ref("");
const invoicesGlobalFilter = ref("");

// Report types
const reports_Type = computed(() => {
  if (lang.value === "en") {
    return [
      { value: "accounting", label: "Accounting" },
      { value: "sample_collectors", label: "Sample Collectors" },
      { value: "referrals", label: "Referrals" },
      { value: "from_lab", label: "From Lab" },
      { value: "to_lab", label: "To Lab" },
      { value: "contracts", label: "Contracts" },
    ];
  }
  return [
    { value: "accounting", label: "الحسابات" },
    { value: "sample_collectors", label: "جامع العينات" },
    { value: "referrals", label: "الاحالات" },
    { value: "from_lab", label: "من مختبر" },
    { value: "to_lab", label: "الى مختبر" },
    { value: "contracts", label: "جهات العقد" },
  ];
});

// Filtered data computeds
const filteredTests = computed(() => {
  return reports.value?.tests?.filter((r) => {
    const globalMatch = testGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(testGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(testFilters.value).every((key) => {
      if (!testFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(testFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredCultures = computed(() => {
  return reports.value?.cultures?.filter((r) => {
    const globalMatch = cultureGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(cultureGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(cultureFilters.value).every((key) => {
      if (!cultureFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(cultureFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredPackages = computed(() => {
  return reports.value?.packages?.filter((r) => {
    const globalMatch = packagesGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(packagesGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(packagesFilters.value).every((key) => {
      if (!packagesFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(packagesFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredTestGroups = computed(() => {
  return reports.value?.test_groups?.filter((r) => {
    const globalMatch = testGroupsGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(testGroupsGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(testGroupsFilters.value).every((key) => {
      if (!testGroupsFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(testGroupsFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredContracts = computed(() => {
  return reports.value?.contracts?.filter((r) => {
    const globalMatch = contractsGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(contractsGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(contractsFilters.value).every((key) => {
      if (!contractsFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(contractsFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredReferals = computed(() => {
  return reports.value?.referals?.filter((r) => {
    const globalMatch = referalsGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(referalsGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(referalsFilters.value).every((key) => {
      if (!referalsFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(referalsFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredPatients = computed(() => {
  return reports.value?.patients?.filter((r) => {
    const globalMatch = patientsGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(patientsGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(patientsFilters.value).every((key) => {
      if (!patientsFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(patientsFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredUsers = computed(() => {
  return reports.value?.users?.filter((r) => {
    const globalMatch = usersGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(usersGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(usersFilters.value).every((key) => {
      if (!usersFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(usersFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredPaymentMethods = computed(() => {
  return reports.value?.payment_methods?.filter((r) => {
    const globalMatch = payment_methodsGlobalFilter.value
      ? Object.values(r).some((v) => String(v).toLowerCase().includes(payment_methodsGlobalFilter.value.toLowerCase()))
      : true;
    const columnMatch = Object.keys(payment_methodsFilters.value).every((key) => {
      if (!payment_methodsFilters.value[key]) return true;
      return String(r[key]).toLowerCase().includes(payment_methodsFilters.value[key].toLowerCase());
    });
    return globalMatch && columnMatch;
  }) || [];
});

const filteredInvoices = computed(() => {
  return reports.value?.invoices?.filter((r) => {
    const globalMatch = invoicesGlobalFilter.value
      ? r.patient_name?.toLowerCase().includes(invoicesGlobalFilter.value.toLowerCase()) ||
        r.due?.toString().includes(invoicesGlobalFilter.value) ||
        r.subtotal?.toString().includes(invoicesGlobalFilter.value) ||
        r.total?.toString().includes(invoicesGlobalFilter.value) ||
        r.contract?.name?.toLowerCase().includes(invoicesGlobalFilter.value.toLowerCase()) ||
        r.referal?.name?.toLowerCase().includes(invoicesGlobalFilter.value.toLowerCase())
      : true;
    return globalMatch;
  }) || [];
});

// Methods
const showtests = (testsData) => {
  tests.value = testsData;
  testdialog.value = true;
};

const clearTestFilters = () => {
  testGlobalFilter.value = "";
  testFilters.value = { name: "", count: "", total: "" };
};

const clearCultureFilters = () => {
  cultureGlobalFilter.value = "";
  cultureFilters.value = { name: "", count: "", total: "" };
};

const clearPackagesFilters = () => {
  packagesGlobalFilter.value = "";
  packagesFilters.value = { name: "", count: "", total: "" };
};

const clearTestGroupsFilters = () => {
  testGroupsGlobalFilter.value = "";
  testGroupsFilters.value = { name: "", count: "", total: "" };
};

const clearContractsFilters = () => {
  contractsGlobalFilter.value = "";
  contractsFilters.value = { name: "", count: "", discount_percentage: "" };
};

const clearReferalsFilters = () => {
  referalsGlobalFilter.value = "";
  referalsFilters.value = { name: "", count: "", discount_percentage: "" };
};

const clearPatientsFilters = () => {
  patientsGlobalFilter.value = "";
  patientsFilters.value = { name: "", count: "", total_invoices: "", total_due: "", total_paid: "" };
};

const clearUsersFilters = () => {
  usersGlobalFilter.value = "";
  usersFilters.value = { name: "", count: "", total_invoices: "" };
};

const clearPaymentMethodsFilters = () => {
  payment_methodsGlobalFilter.value = "";
  payment_methodsFilters.value = { name: "", count: "", total: "" };
};

const clearInvoicesFilters = () => {
  invoicesGlobalFilter.value = "";
  invoicesFilters.value = { patient_name: "", due: "", subtotal: "", total: "", discount: "", contract: "", discount_percentage: "", referal: "", commission: "" };
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const exportToExcel = () => {
  let data = [];
  if (activeTab.value === 'tests') data = filteredTests.value;
  else if (activeTab.value === 'cultures') data = filteredCultures.value;
  else if (activeTab.value === 'packages') data = filteredPackages.value;
  else if (activeTab.value === 'test_groups') data = filteredTestGroups.value;
  else if (activeTab.value === 'contracts') data = filteredContracts.value;
  else if (activeTab.value === 'referals') data = filteredReferals.value;
  else if (activeTab.value === 'patients') data = filteredPatients.value;
  else if (activeTab.value === 'users') data = filteredUsers.value;
  else if (activeTab.value === 'payment_methods') data = filteredPaymentMethods.value;
  else if (activeTab.value === 'invoices') data = filteredInvoices.value;

  const ws = XLSX.utils.json_to_sheet(data);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Reports");
  XLSX.writeFile(wb, `reports_${activeTab.value}.xlsx`);
};

const fetchReports = async () => {
  isLoading.value = true;
  try {
    await Getreports();
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  // Set date range to last 30 days for better initial data
  const today = new Date();
  const thirtyDaysAgo = new Date(today);
  thirtyDaysAgo.setDate(today.getDate() - 30);

  // Reset record fully to avoid stale state from previous SPA navigation
  record.value.start_date = thirtyDaysAgo.toISOString().split("T")[0];
  record.value.end_date = today.toISOString().split("T")[0];
  record.value.report_type = "accounting";
  record.value.id = null;
  record.value.lab_id = null;
  // Clear stale reports so the template shows loading, not stale "no data"
  reports.value = null;
  totalCount.value = "";
  branchData.value = { id: User.value?.id, name: User.value?.name };

  try {
    await Promise.all([
      fetchReports(),
      Getlabs().catch(() => {}),
      GetReferrals().catch(() => {}),
      collectors().catch(() => {}),
      GetContracts().catch(() => {}),
      GetBranches().catch(() => {}),
    ]);
  } catch (error) {
    console.error("Error loading reports page:", error);
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="space-y-6" :style="lang === 'en' ? 'direction:ltr' : 'direction: rtl'">
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                {{ t("reports") }}
              </span>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("reports") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_reports") || "Analyze laboratory performance and financial data" }}</p>
          </div>

          <!-- Stats Cards -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("total_invoices") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ reports?.total_invoices?.toLocaleString("en-US") || 0 }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("profit") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ reports?.profit?.toLocaleString("en-US") || 0 }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
      <!-- Subtotal -->
      <div v-if="reports?.subtotal >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("Subtotal") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ reports?.subtotal?.toLocaleString("en-US") }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Discount -->
      <div v-if="reports?.discount >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("discount") }}</p>
            <p class="text-2xl font-bold text-indigo-600">{{ reports?.discount?.toLocaleString("en-US") }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Total Amount -->
      <div v-if="reports?.total_amount >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total_amount") }}</p>
            <p class="text-2xl font-bold text-blue-600">{{ reports?.total_amount?.toLocaleString("en-US") }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Paid Amount -->
      <div v-if="reports?.paid_amount >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("paid_amount") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ reports?.paid_amount?.toLocaleString("en-US") }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
            <svg class="w-6 h-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Due -->
      <div v-if="reports?.due >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("Due") }}</p>
            <p class="text-2xl font-bold text-purple-600">{{ reports?.due?.toLocaleString("en-US") }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Total Invoices -->
      <div v-if="reports?.total_invoices >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total_invoices") }}</p>
            <p class="text-2xl font-bold text-red-600">{{ reports?.total_invoices }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Profit -->
      <div v-if="reports?.profit >= 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("profit") }}</p>
            <p class="text-2xl font-bold text-green-600">{{ reports?.profit?.toLocaleString("en-US") }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== FILTERS SECTION ==================== -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <div class="p-5">
        <div class="flex flex-wrap items-end gap-4">
          <!-- From Date -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("from_date") }}</label>
            <input
              type="date"
              v-model="record.start_date"
              @click="$event.target.showPicker()"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
            />
          </div>

          <!-- To Date -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("to_date") }}</label>
            <input
              type="date"
              v-model="record.end_date"
              @click="$event.target.showPicker()"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
            />
          </div>

          <!-- Report Type -->
          <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("report_type") }}</label>
            <select
              v-model="record.report_type"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
            >
              <option value="">{{ t("select") }}</option>
              <option v-for="type in reports_Type" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
          </div>

          <!-- Buttons -->
          <div class="flex items-center gap-3">
            <button
              @click="toggleFilters"
              class="px-4 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
              :class="{ 'bg-primary-50 border-primary-200 text-primary-700': showFilters }"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <span class="hidden sm:inline">{{ t("filters") }}</span>
            </button>
            <button
              @click="fetchReports"
              class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all flex items-center gap-2 shadow-lg shadow-primary-500/25"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              {{ t("search") }}
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

      <!-- Collapsible Advanced Filters -->
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
              <!-- Branch (if not branch lab role) -->
              <div v-if="Role !== 4">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("branch") }}</label>
                <select
                  v-model="record.lab_id"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option :value="branchData.id">{{ branchData.name }}</option>
                  <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
              </div>

              <!-- Lab (for from_lab or to_lab) -->
              <div v-if="record.report_type === 'from_lab' || record.report_type === 'to_lab'">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("lab") }}</label>
                <select
                  v-model="record.id"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("select") }}</option>
                  <option v-for="lab in labs" :key="lab.id" :value="lab.id">{{ lab.name }}</option>
                </select>
              </div>

              <!-- Referrals -->
              <div v-if="record.report_type === 'referrals'">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("referrals") }}</label>
                <select
                  v-model="record.id"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("select") }}</option>
                  <option v-for="referral in records" :key="referral.id" :value="referral.id">{{ referral.name }}</option>
                </select>
              </div>

              <!-- Sample Collector -->
              <div v-if="record.report_type === 'sample_collectors'">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("sample_collector") }}</label>
                <select
                  v-model="record.id"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("select") }}</option>
                  <option v-for="collector in collectorsList" :key="collector.id" :value="collector.id">{{ collector.name }}</option>
                </select>
              </div>

              <!-- Contracts -->
              <div v-if="record.report_type === 'contracts'">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("contracts") }}</label>
                <select
                  v-model="record.id"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("select") }}</option>
                  <option v-for="contract in contractsList" :key="contract.id" :value="contract.id">{{ contract.name }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- ==================== LOADING STATE ==================== -->
    <div v-if="isLoading" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-12">
      <div class="text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-50 mb-4">
          <svg class="w-8 h-8 text-primary-600 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-slate-600 font-medium">{{ t("loading") }}...</p>
      </div>
    </div>

    <!-- ==================== NO DATA STATE ==================== -->
    <div v-else-if="!reports || totalCount === 0" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-12">
      <div class="text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500">{{ t("no_reports_message") || "No reports found. Try adjusting your filters." }}</p>
      </div>
    </div>

    <!-- ==================== REPORTS DATA ==================== -->
    <div v-else class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
      <!-- Tab Navigation -->
      <div class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
        <nav class="flex gap-1 p-2 overflow-x-auto">
          <button
            v-if="reports?.tests"
            @click="activeTab = 'tests'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'tests'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            {{ t("tests") }}
          </button>
          <button
            v-if="reports?.cultures"
            @click="activeTab = 'cultures'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'cultures'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            {{ t("cultures") }}
          </button>
          <button
            v-if="reports?.packages"
            @click="activeTab = 'packages'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'packages'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            {{ t("packages") }}
          </button>
          <button
            v-if="reports?.test_groups?.length"
            @click="activeTab = 'test_groups'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'test_groups'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            {{ t("test_groups") }}
          </button>
          <button
            v-if="reports?.contracts"
            @click="activeTab = 'contracts'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'contracts'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            {{ t("contracts") }}
          </button>
          <button
            v-if="reports?.referals"
            @click="activeTab = 'referals'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'referals'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            {{ t("referals") }}
          </button>
          <button
            v-if="reports?.patients"
            @click="activeTab = 'patients'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'patients'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            {{ t("patients") }}
          </button>
          <button
            v-if="reports?.users"
            @click="activeTab = 'users'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'users'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            {{ t("users") }}
          </button>
          <button
            v-if="reports?.payment_methods"
            @click="activeTab = 'payment_methods'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'payment_methods'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            {{ t("paymentMethods") }}
          </button>
          <button
            v-if="reports?.invoices"
            @click="activeTab = 'invoices'"
            :class="[
              'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
              activeTab === 'invoices'
                ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
            ]"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            {{ t("invoices") }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="p-5">
        <!-- Tests Tab -->
        <div v-if="activeTab === 'tests' && reports?.tests">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="testGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearTestFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Total") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredTests.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="test in filteredTests" :key="test.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ test.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ test.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ test.total?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Cultures Tab -->
        <div v-if="activeTab === 'cultures' && reports?.cultures">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="cultureGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearCultureFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Total") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredCultures.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="culture in filteredCultures" :key="culture.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ culture.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ culture.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ culture.total?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Packages Tab -->
        <div v-if="activeTab === 'packages' && reports?.packages">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="packagesGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearPackagesFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Total") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredPackages.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="pkg in filteredPackages" :key="pkg.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ pkg.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ pkg.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ pkg.total?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Test Groups Tab -->
        <div v-if="activeTab === 'test_groups' && reports?.test_groups">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="testGroupsGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearTestGroupsFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Total") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredTestGroups.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="tg in filteredTestGroups" :key="tg.name" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ tg.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ tg.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ tg.total?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Contracts Tab -->
        <div v-if="activeTab === 'contracts' && reports?.contracts">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="contractsGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearContractsFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("discount_percentage") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredContracts.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="contract in filteredContracts" :key="contract.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ contract.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ contract.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ contract.discount_percentage }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Referals Tab -->
        <div v-if="activeTab === 'referals' && reports?.referals">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="referalsGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearReferalsFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("discount_percentage") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredReferals.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="referal in filteredReferals" :key="referal.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ referal.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ referal.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ referal.discount_percentage }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Patients Tab -->
        <div v-if="activeTab === 'patients' && reports?.patients">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="patientsGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearPatientsFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("total_invoices") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Due") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("paid") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredPatients.length">
                  <td colspan="5" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="patient in filteredPatients" :key="patient.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ patient.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ patient.count }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ patient.total_invoices?.toLocaleString("en-US") }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-red-600">{{ patient.total_due?.toLocaleString("en-US") }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-green-600">{{ patient.total_paid?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Users Tab -->
        <div v-if="activeTab === 'users' && reports?.users">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="usersGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearUsersFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("total_invoices") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredUsers.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ user.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ user.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ user.total_invoices?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Payment Methods Tab -->
        <div v-if="activeTab === 'payment_methods' && reports?.payment_methods">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="payment_methodsGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearPaymentMethodsFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("count") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Total") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredPaymentMethods.length">
                  <td colspan="3" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="method in filteredPaymentMethods" :key="method.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ method.name }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ method.count }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ method.total?.toLocaleString("en-US") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Invoices Tab -->
        <div v-if="activeTab === 'invoices' && reports?.invoices">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex-1 relative">
              <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                v-model="invoicesGlobalFilter"
                :placeholder="t('search') + '...'"
                class="w-full ps-12 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
              />
            </div>
            <button @click="clearInvoicesFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-all">
              {{ t("Clear Filters") }}
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("patient_name") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Due") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Subtotal") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Total") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("discount") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("contract") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("referal") }}</th>
                  <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("tests") }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredInvoices.length">
                  <td colspan="8" class="px-5 py-8 text-center text-slate-500">{{ t("noData") }}</td>
                </tr>
                <tr v-for="invoice in filteredInvoices" :key="invoice.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ invoice.patient_name }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-red-600">{{ invoice.due?.toLocaleString("en-US") }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ invoice.subtotal?.toLocaleString("en-US") }}</td>
                  <td class="px-5 py-4 text-sm font-semibold text-primary-600">{{ invoice.total?.toLocaleString("en-US") }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ invoice.discount?.toLocaleString("en-US") }}</td>
                  <td class="px-5 py-4">
                    <span v-if="invoice.contract?.name" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">
                      {{ invoice.contract?.name }}
                    </span>
                    <span v-else class="text-slate-400">-</span>
                  </td>
                  <td class="px-5 py-4">
                    <span v-if="invoice.referal?.name" class="inline-flex items-center px-2.5 py-1 bg-purple-50 text-purple-700 text-xs font-medium rounded-lg">
                      {{ invoice.referal?.name }}
                    </span>
                    <span v-else class="text-slate-400">-</span>
                  </td>
                  <td class="px-5 py-4">
                    <button
                      @click="showtests(invoice.tests)"
                      class="p-2 text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-all"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <TestsModal />
    <CulturesModal />
    <PackagesModal />
    <ContractsModal />
    <ReferalsModal />
  </div>
</template>
