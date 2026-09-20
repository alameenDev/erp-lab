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
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
              <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-3 mb-2">
                <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">
                  {{ isEditMode ? t("update") : t("new_invoice") }}
                </span>
              </div>
              <h1 class="text-2xl lg:text-3xl font-bold text-white">
                {{ isEditMode ? t("update") + " " + t("invoices") : t("create_new_invoice") }}
              </h1>
              <p class="text-slate-400 text-sm mt-1">{{ t("fill_invoice_details") || "Fill in the details to create a new invoice" }}</p>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-3">
            <button
              @click="goBack"
              class="px-5 py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-md text-white font-medium rounded-xl transition-all border border-white/10 flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              {{ t("cancel") }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-12">
      <div class="text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-50 mb-4">
          <svg class="w-8 h-8 text-primary-600 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <p class="text-slate-600 font-medium">{{ t("loading") }}...</p>
        <p class="text-slate-400 text-sm mt-1">{{ t("loading_invoice_data") || "Please wait while we load the invoice data" }}</p>
      </div>
    </div>

    <!-- Form Content -->
    <div v-else>
      <InvoicesModal
        :is-page-mode="true"
        @saved="onSaved"
        @cancelled="goBack"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useinvoicesStore } from "@/store/modules/invoices";
import { usePatientsStore } from "@/store/modules/patients";
import { storeToRefs } from "pinia";
import { t } from "@/utils/helper";
import { $http } from "@/plugins/axios";
import InvoicesModal from "./componentes/invoices_modal.vue";

const route = useRoute();
const router = useRouter();
const invoicesStore = useinvoicesStore();
const patientsStore = usePatientsStore();

const {
  record,
  selectedTests,
  selectedPackages,
  selectedCultures,
  selectedtestGroups,
  selectedContract,
  selectedreferal,
  selectedCollector,
  payment_details,
  discountPercentage,
  discountValue,
  testQuesions,
} = storeToRefs(invoicesStore);
const { responseData } = storeToRefs(patientsStore);

const isEditMode = computed(() => !!route.params.id);
const loading = ref(false);

// Navigation
const returnTarget = () => {
     if (route.query.return === "update-result") {
          const id = route.params.id || route.query.return_id;
          if (id) return `/medical_reports/update-result/${id}`;
     }
     return "/invoices";
};
const goBack = () => router.push(returnTarget());
const onSaved = () => router.push(returnTarget());

// After creating an invoice, the print modal opens. Redirect when user closes it.
const { printInvoiceDialog } = storeToRefs(invoicesStore);
watch(printInvoiceDialog, (newVal, oldVal) => {
     if (oldVal === true && newVal === false) {
          router.push(returnTarget());
     }
});

// Format date to YYYY-MM-DD
const dateFormat = (date) => {
  if (!date) return "";
  try {
    return new Date(date).toISOString().split("T")[0];
  } catch {
    return date;
  }
};

// Load invoice for edit mode
const loadInvoice = async (id) => {
  loading.value = true;
  try {
    const { data } = await $http.get(`/invoices/${id}`);
    if (!data) return;

    // Populate store with invoice data
    Object.assign(record.value, data);
    payment_details.value = data.paidDetails || [{ amount: null, contract_id_fk: null, payment_method_id_fk: null }];

    // Set selections
    selectedContract.value = data.contract;
    selectedreferal.value = data.referral;
    selectedCollector.value = data.sample_collector;

    // Set record IDs
    // referral_id_fk = users.id (transformReferral exposes user_id alongside referrals.id; dropdown optionValue="user_id")
    record.value.referral_id_fk = data.referral?.user_id ?? data.referral?.id ?? null;
    record.value.contract_id_fk = data.contract?.id;
    record.value.sample_collector_id_fk = data.sample_collector?.id;
    // from_lab_id_fk = users.id; backend exposes flat field at top-level of invoice payload
    record.value.from_lab_id_fk = data.from_lab_id_fk ?? null;

    // Format dates
    record.value.registration_date = dateFormat(data.registration_date);
    record.value.result_date = dateFormat(data.result_date);

    // Set selected items
    selectedTests.value = data.tests || [];
    selectedPackages.value = data.packages || [];
    selectedCultures.value = data.cultures || [];
    selectedtestGroups.value = data.test_groups || [];

    // Set patient data
    responseData.value = data.patient;

    // Set discount based on type (2 = percentage, 3 = fixed value)
    const dt = Number(data.discount_type_id_fk);
    discountPercentage.value = dt === 2 ? Number(data.discount) || null : null;
    discountValue.value = dt === 3 ? Number(data.discount) || null : null;
    record.value.discount_type_id_fk = data.discount_type_id_fk ?? null;
    record.value.discount = data.discount ?? null;
    record.value.loyalty_discount = data.loyalty_discount ?? 0;
    record.value.loyalty_points_spent = data.loyalty_points_spent ?? 0;
    record.value.promo_code_id_fk = data.promo_code_id_fk ?? null;
    record.value.promo_code = data.promo_code ?? null;

    // Map test questions
    testQuesions.value = selectedTests.value?.map((test) => ({
      test_name: test.name,
      test_id_fk: test.test_id_fk,
      questions: test.questions?.map((q) => ({
        question: q.question.question,
        answer_type: q.question.answer_type,
        answer_type_id_fk: q.question.answer_type_id_fk,
        answer_type_selection_values: q.question.answer_type_selection_values,
        answer: q.answer,
      })),
    })) || [];
  } catch (error) {
    console.error("Error loading invoice:", error);
  } finally {
    loading.value = false;
  }
};

// Clear patient form fields (patientStore.record) — prevents prev patient's
// data showing up in the inline patient form on the next invoice create.
const clearPatientRecord = () => {
  const r = patientsStore.record;
  if (!r) return;
  Object.keys(r).forEach((k) => {
    if (typeof r[k] === "string") r[k] = "";
    else if (typeof r[k] === "number") r[k] = 0;
    else if (Array.isArray(r[k])) r[k] = [];
    else r[k] = null;
  });
};

// Reset form for create mode
const resetForm = () => {
  // Use store's reset method
  invoicesStore.resetRecord();
  testQuesions.value = [];
  discountPercentage.value = 0;
  discountValue.value = 0;
  // Preserve preselected patient when navigating with ?patient_id=
  if (!route.query.patient_id) {
    responseData.value = null;
    clearPatientRecord();
  }

  // Set today's date
  record.value.registration_date = invoicesStore.getTodayDate();
  // If patient came via query (e.g. from update-result), link record to that patient
  if (route.query.patient_id && responseData.value?.id) {
    record.value.patient_id_fk = responseData.value.id;
  }
};

onMounted(async () => {
  if (route.params.id) {
    await loadInvoice(route.params.id);
  } else {
    resetForm();
  }
});

// Clear cross-page patient + invoice state on leaving — prevents leakage
// into the next /invoices/create visit (e.g. when user clicks "New Invoice"
// after returning from /invoices/<id>/edit or update-result).
onBeforeUnmount(() => {
  responseData.value = null;
  invoicesStore.resetRecord();
  clearPatientRecord();
  testQuesions.value = [];
  discountPercentage.value = 0;
  discountValue.value = 0;
});
</script>
