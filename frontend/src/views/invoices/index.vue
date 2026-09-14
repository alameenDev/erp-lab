<script setup>
import { ref, computed, onMounted, watch, nextTick } from "vue";
import { useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { $http } from "@/plugins/axios";
import { useContractsStore } from "@/store/modules/contract";
import { usePatientsStore } from "@/store/modules/patients";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import { usePrint } from "@/composables/usePrint";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { format } from "date-fns";
import * as XLSX from "xlsx";
import BarcodeComponent from "@/components/BarcodeComponent.vue";
import InvoicesModal from "./componentes/invoices_modal.vue";
import PatientModal from "./componentes/patient_modal.vue";
import DueModal from "./componentes/due_modal.vue";
import JobOrderModal from "./componentes/job_orderModal.vue";
import ThermalReciptModal from "./componentes/thermal_reciptModal.vue";
import PrintInvoice from "./componentes/print_invoice.vue";
import WhatsUpModal from "./componentes/whatsUp_moadal.vue";
import ParcodeModal from "./componentes/parcodeModal.vue";
import TestsListModal from "@/components/TestsListModal.vue";

const router = useRouter();
const invoicesStore = useinvoicesStore();
const contractsStore = useContractsStore();
const patientsStore = usePatientsStore();
const authStore = useAuthStore();
const toast = useToast();
const labSettingsStore = useLabSettingsStore();
const { printWithIframe, printStyles } = usePrint();

const {
  invoices,
  record,
  dialog,
  patientdialog,
  patient,
  Patient_due,
  Patient_dueDialog,
  discountPercentage,
  discountValue,
  testQuesions,
  payment_details,
  selectedContract,
  selectedTests,
  selectedPackages,
  selectedtestGroups,
  selectedCultures,
  selectedreferal,
  selectedCollector,
  printRecord,
  WhatsUpDialog,
  pagination,
  stats,
} = storeToRefs(invoicesStore);

const { contracts } = storeToRefs(contractsStore);
const { record: patientRecord, responseData } = storeToRefs(patientsStore);

// Loading state
const isLoading = ref(true);

// Filters
const filters = ref({
  from_lab: "",
  contract_id_fk: "",
  created_by: "",
  patient_name: "",
  lab: "",
  status: "", // done, pending
  payment_status: "", // paid, unpaid, partial
  date_from: "",
  date_to: "",
});

// Filter dropdown visibility
const showFilters = ref(false);

// Menu state for each row
const openMenuId = ref(null);

// User info
const User = computed(() => {
  try {
    return JSON.parse(localStorage.getItem("user"));
  } catch {
    return null;
  }
});

const Role = computed(() => User.value?.role_id);

// Check access
const canAccess = (roles) => {
  const roleMap = { sample_collector: 6 };
  return roles.some((r) => roleMap[r] === Role.value);
};

const cannotAccess = (roles) => !canAccess(roles);

// Format currency
const formatCurrency = (value) => {
  if (!value && value !== 0) return "0";
  return new Intl.NumberFormat("en-US").format(value);
};

// Pagination (server-side)
const currentPage = ref(1);
const perPage = ref(25);

const totalPages = computed(() => pagination.value.last_page || 1);

// Build params for server-side fetch
const buildParams = () => {
  const params = { page: currentPage.value, per_page: perPage.value };
  if (filters.value.patient_name) params.patient_name = filters.value.patient_name;
  if (filters.value.from_lab) params.from_lab = filters.value.from_lab;
  if (filters.value.created_by) params.created_by = filters.value.created_by;
  if (filters.value.contract_id_fk) params.contract_id_fk = filters.value.contract_id_fk;
  if (filters.value.status) params.status = filters.value.status;
  if (filters.value.payment_status) params.payment_status = filters.value.payment_status;
  if (filters.value.date_from) params.date_from = filters.value.date_from;
  if (filters.value.date_to) params.date_to = filters.value.date_to;
  return params;
};

const fetchInvoices = async () => {
  await invoicesStore.Getinvoices(buildParams());
};

// Methods
const clearFilters = () => {
  filters.value = {
    from_lab: "",
    contract_id_fk: "",
    created_by: "",
    patient_name: "",
    lab: "",
    status: "",
    payment_status: "",
    date_from: "",
    date_to: "",
  };
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.status) count++;
  if (filters.value.payment_status) count++;
  if (filters.value.contract_id_fk) count++;
  if (filters.value.date_from) count++;
  if (filters.value.date_to) count++;
  if (filters.value.from_lab) count++;
  return count;
});

const exportToExcel = () => {
  const ws = XLSX.utils.json_to_sheet(invoices.value, {
    header: ["index", "from_lab", "contract_id_fk", "created_by", "lab"],
  });
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Invoices");
  XLSX.writeFile(wb, "invoices_export.xlsx");
};

const dateFormat = (date) => {
  if (!date) return "";
  try {
    return format(new Date(date), "yyyy-MM-dd");
  } catch {
    return date;
  }
};

const addRecord = () => {
  router.push("/invoices/create");
};

const editRecord = (data) => {
  router.push(`/invoices/${data.id}/edit`);
};

// Tests list modal
const testsListOpen = ref(false);
const testsListLoading = ref(false);
const testsListInvoice = ref(null);
const showTestsList = async (invoice) => {
  testsListOpen.value = true;
  testsListLoading.value = true;
  testsListInvoice.value = { patient: invoice.patient };
  try {
    await invoicesStore.GetinvoicesById(invoice.id);
    testsListInvoice.value = printRecord.value;
  } finally {
    testsListLoading.value = false;
  }
};

const deleteRecord = async (data) => {
  const result = await showAlertWithConfirm(t("AlertWithConfirm"));
  if (result.value) {
    record.value.id = data.id;
    try {
      await invoicesStore.Removeinvoices();
      toast.success(t("invoice_deleted_successfully") || "Invoice deleted successfully", { duration: 3000 });
    } catch (error) {
      toast.error(error?.response?.data?.message || t("error_deleting_invoice") || "Failed to delete invoice", { duration: 4000 });
    }
  }
};

const showPatient = (p) => {
  patient.value = p;
  patientdialog.value = true;
};

const showPatientDue = (data) => {
  Patient_due.value = data;
  Patient_dueDialog.value = true;
};

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

const closeMenu = () => {
  openMenuId.value = null;
};

const onPageChange = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  fetchInvoices();
};

// Print functions - using usePrint composable for clean, reusable code
const openJobTemplateAsPDF = async (data) => {
  openMenuId.value = null;
  await printWithIframe(
    "job",
    printStyles.jobOrder,
    "Print Job Order",
    100,
    () => invoicesStore.GetinvoicesById(data.id)
  );
};

const openthermalRecord = async (data) => {
  openMenuId.value = null;
  await printWithIframe(
    "thermalRecord",
    printStyles.thermalReceipt,
    "Thermal Receipt",
    100,
    () => invoicesStore.GetinvoicesById(data.id)
  );
};

const openprintINvoiceTemplate = async (data) => {
  openMenuId.value = null;
  await printWithIframe(
    "printInvoice",
    printStyles.invoice,
    "Print Invoice",
    100,
    () => invoicesStore.GetinvoicesById(data.id)
  );
};

const printParcode = (data) => {
  printWithIframe("parcode", printStyles.getBarcodeCss(labSettingsStore.settings.barcode_config), "Print Barcode", 100, () => invoicesStore.GetinvoicesById(data.id));
};

const sendWhatsUp = (data) => {
  printRecord.value = data;
  const labName = User.value?.name;
  const patientName = data?.patient?.name;
  const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;
  const url = `${appBaseUrl}/invoice/${data.id}`;
  const message = `اهلا بكم في مختبر ${labName} عزيزي ${patientName} يمكنك الحصول على الفاتورة من خلال الضغط على الرابط ادناه \n\n${url}`;
  const encodedMessage = encodeURIComponent(message);
  let phoneNumber = data?.patient?.phone;
  if (phoneNumber?.startsWith("0")) {
    phoneNumber = phoneNumber.substring(1);
  }
  const whatsAppUrl = `https://wa.me/+964${phoneNumber}?text=${encodedMessage}`;
  window.open(whatsAppUrl, "_blank");
};

// Watchers - debounced server-side filter fetch
let filterTimer = null;
watch(
  filters,
  () => {
    currentPage.value = 1;
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
      fetchInvoices();
    }, 300);
  },
  { deep: true }
);

// Barcode scan
const scanInput = ref("");
const scanResult = ref(null);
const scanLoading = ref(false);
const scanNotFound = ref(false);

const scanBarcode = async () => {
  const barcode = scanInput.value.trim();
  if (!barcode) return;

  scanLoading.value = true;
  scanNotFound.value = false;
  scanResult.value = null;

  try {
    // Search in already-loaded invoices first
    const found = invoices.value.find((inv) => inv.barcode === barcode);
    let invoiceId = found?.id;

    // If not found on current page, search by barcode via API
    if (!invoiceId) {
      const { data } = await $http.get("/invoices", { params: { patient_name: barcode, per_page: 1 } });
      const match = data?.data?.find((inv) => inv.barcode === barcode);
      invoiceId = match?.id;
    }

    if (invoiceId) {
      await invoicesStore.GetinvoicesById(invoiceId);
      const data = printRecord.value;
      const allTests = [];
      data?.tests?.forEach((t) => allTests.push({ name: t.report_name || t.name, is_done: t.is_done }));
      data?.cultures?.forEach((c) => allTests.push({ name: c.name, is_done: c.is_done }));
      data?.packages?.forEach((p) => {
        allTests.push({ name: p.name, is_done: p.is_done });
      });
      data?.test_groups?.forEach((g) => {
        allTests.push({ name: g.group_name, is_done: g.is_done });
      });

      const total = allTests.length;
      const done = allTests.filter((t) => t.is_done).length;
      scanResult.value = {
        invoice: data,
        tests: allTests,
        total,
        done,
        percentage: total > 0 ? Math.round((done / total) * 100) : 0,
      };
    } else {
      scanNotFound.value = true;
    }
  } catch {
    scanNotFound.value = true;
  }
  scanLoading.value = false;
};

const clearScan = () => {
  scanInput.value = "";
  scanResult.value = null;
  scanNotFound.value = false;
};

onMounted(async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      fetchInvoices(),
      contractsStore.GetRecords()
    ]);
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="space-y-6" @click="closeMenu">
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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div>
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ stats.total }} {{ t("invoices") }}
                </span>
              </div>
            </div>
            <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("invoices") }}</h1>
            <p class="text-slate-400 text-sm lg:text-base">{{ t("manage_invoices") || "Manage patient invoices and billing" }}</p>
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("done") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.completed_sent }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-500/30 flex items-center justify-center">
                  <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-xs text-slate-400">{{ t("pendening") }}</span>
              </div>
              <p class="text-2xl font-bold text-white">{{ stats.pending_sent }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== STATS CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Invoices -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total_invoices") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Total Amount -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("total_amount") }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ formatCurrency(stats.total_amount) }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Paid Amount -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("paid_amount") }}</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.paid_amount) }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Due Amount -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500 mb-1">{{ t("Due") }}</p>
            <p class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.due_amount) }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
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
                v-model="filters.patient_name"
                type="text"
                :placeholder="t('Pationt_name') + ' / ' + t('barcode') + '...'"
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
              v-if="authStore.havePermission('invoices create')"
              @click.stop="addRecord"
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
              <!-- Status Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("theStatus") }}</label>
                <select
                  v-model="filters.status"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option value="done">{{ t("done") }}</option>
                  <option value="pending">{{ t("pendening") }}</option>
                </select>
              </div>

              <!-- Payment Status Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("payment_status") || "Payment Status" }}</label>
                <select
                  v-model="filters.payment_status"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option value="paid">{{ t("paid") || "Paid" }}</option>
                  <option value="unpaid">{{ t("unpaid") || "Unpaid" }}</option>
                  <option value="partial">{{ t("partial") || "Partial" }}</option>
                </select>
              </div>

              <!-- Contract Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("contract") }}</label>
                <select
                  v-model="filters.contract_id_fk"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                >
                  <option value="">{{ t("all") }}</option>
                  <option v-for="contract in contracts" :key="contract.id" :value="contract.id">
                    {{ contract.name }}
                  </option>
                </select>
              </div>

              <!-- From Lab Filter -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("from_lab") }}</label>
                <input
                  v-model="filters.from_lab"
                  type="text"
                  :placeholder="t('from_lab') + '...'"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Date From -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("date_from") || "From Date" }}</label>
                <input
                  v-model="filters.date_from"
                  type="date"
                  @click="$event.target.showPicker()"
                  class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                />
              </div>

              <!-- Date To -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("date_to") || "To Date" }}</label>
                <input
                  v-model="filters.date_to"
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
      <div v-else-if="invoices.length === 0" class="p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-10 h-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ t("noData") }}</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">{{ t("no_invoices_message") || "No invoices found. Create your first invoice to get started." }}</p>
        <button
          v-if="authStore.havePermission('invoices create')"
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
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Pationt_name") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("lab/bruanch") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Created_By") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("contract") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("from_lab") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("Barcode") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("theStatus") }}</th>
                <th class="px-5 py-4 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider">{{ t("actions") }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="invoice in invoices"
                :key="invoice.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <td class="px-5 py-4">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-sm font-medium group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                    {{ invoice.index }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <button
                    v-if="invoice?.patient?.name"
                    @click.stop="showPatient(invoice?.patient)"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary-50 text-primary-700 text-sm font-medium rounded-lg hover:bg-primary-100 transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ invoice?.patient?.name }}
                  </button>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm font-medium text-slate-700">{{ invoice?.lab }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">{{ invoice?.created_by?.name }}</span>
                </td>
                <td class="px-5 py-4">
                  <span v-if="invoice?.contract?.name" class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">
                    {{ invoice?.contract?.name }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-slate-600">{{ invoice?.from_lab || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <button
                    v-if="cannotAccess(['sample_collector'])"
                    @click.stop="printParcode(invoice)"
                    class="text-center hover:opacity-80 transition-opacity"
                  >
                    <span class="text-xs text-slate-500 block mb-1">{{ invoice?.barcode }}</span>
                    <BarcodeComponent :value="invoice?.barcode" />
                  </button>
                  <div v-else class="text-center">
                    <span class="text-xs text-slate-500 block mb-1">{{ invoice?.barcode }}</span>
                    <BarcodeComponent :value="invoice?.barcode" />
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold"
                    :class="invoice.sent_to_patient
                      ? 'bg-green-100 text-green-700'
                      : 'bg-amber-100 text-amber-700'"
                  >
                    <span
                      class="w-2 h-2 rounded-full"
                      :class="invoice.sent_to_patient ? 'bg-green-500' : 'bg-amber-500'"
                    ></span>
                    {{ invoice.sent_to_patient ? t('done') : t('pendening') }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-1">
                    <!-- Patient Due -->
                    <button
                      @click.stop="showPatientDue(invoice)"
                      class="p-2 text-primary-500 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-all"
                      :title="t('Patient_due')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                      </svg>
                    </button>

                    <!-- Edit -->
                    <button
                      v-if="authStore.havePermission('invoices edit')"
                      @click.stop="editRecord(invoice)"
                      class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-all"
                      :title="t('update')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>

                    <!-- View Tests -->
                    <button
                      @click.stop="showTestsList(invoice)"
                      class="p-2 text-slate-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-all"
                      :title="t('view_tests') || 'عرض الفحوصات'"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                      </svg>
                    </button>

                    <!-- Edit Patient -->
                    <button
                      v-if="invoice.patient?.id"
                      @click.stop="router.push({ path: '/patients', query: { edit: invoice.patient.id } })"
                      class="p-2 text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-all"
                      :title="t('edit_patient')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </button>

                    <!-- Edit Medical Reports -->
                    <button
                      @click.stop="router.push(`/medical_reports/update-result/${invoice.id}`)"
                      class="p-2 text-emerald-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-all"
                      :title="t('edit_medical_reports')"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </button>

                    <!-- More Actions Menu -->
                    <div class="relative">
                      <button
                        @click.stop="toggleMenu(invoice.id)"
                        class="p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-all"
                      >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                      </button>

                      <!-- Dropdown Menu -->
                      <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div
                          v-if="openMenuId === invoice.id"
                          class="absolute end-0 z-50 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden"
                        >
                          <button
                            v-if="authStore.havePermission('invoices job order')"
                            @click.stop="openJobTemplateAsPDF(invoice)"
                            class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors"
                          >
                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            {{ t("job_order") }}
                          </button>
                          <button
                            v-if="authStore.havePermission('invoices thermal reciept print')"
                            @click.stop="openthermalRecord(invoice)"
                            class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors"
                          >
                            <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                              </svg>
                            </div>
                            {{ t("thermal_recipt") }}
                          </button>
                          <button
                            v-if="authStore.havePermission('invoices print')"
                            @click.stop="openprintINvoiceTemplate(invoice)"
                            class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors"
                          >
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                              </svg>
                            </div>
                            {{ t("printInvoice") }}
                          </button>
                          <button
                            v-if="authStore.havePermission('invoices send whatsapp')"
                            @click.stop="sendWhatsUp(invoice)"
                            class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors"
                          >
                            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                              </svg>
                            </div>
                            {{ t("send") }} WhatsApp
                          </button>
                          <hr v-if="authStore.havePermission('invoices delete')" class="my-2 border-slate-100" />
                          <button
                            v-if="authStore.havePermission('invoices delete')"
                            @click.stop="deleteRecord(invoice)"
                            class="w-full px-4 py-2.5 text-start text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors"
                          >
                            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                              <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                            </div>
                            {{ t("delete") }}
                          </button>
                        </div>
                      </Transition>
                    </div>
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
              <span class="font-semibold text-slate-800">{{ pagination.from || 0 }}</span>
              -
              <span class="font-semibold text-slate-800">{{ pagination.to || 0 }}</span>
              {{ t("of") || "of" }}
              <span class="font-semibold text-slate-800">{{ pagination.total }}</span>
              {{ t("invoices") }}
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
    <InvoicesModal />
    <PatientModal />
    <DueModal />
    <JobOrderModal />
    <ThermalReciptModal />
    <PrintInvoice />
    <WhatsUpModal />
    <ParcodeModal />
    <TestsListModal v-model="testsListOpen" :invoice="testsListInvoice" :loading="testsListLoading" />
  </div>
</template>
