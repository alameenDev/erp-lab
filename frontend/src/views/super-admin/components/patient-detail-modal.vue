<script setup>
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat, dateTimeFormat } from "@/utils/helper";
import ReportDetailModal from "./report-detail-modal.vue";

const visible = defineModel({ type: Boolean, default: false });

const store = useSuperAdminStore();
const { patientDetail, patientMedicalReports } = storeToRefs(store);

const patient = computed(() => patientDetail.value?.patient);
const stats = computed(() => patientDetail.value?.stats);
const invoices = computed(() => patientDetail.value?.invoices || []);

const remaining = computed(() => {
     if (!stats.value) return 0;
     return (stats.value.total_amount || 0) - (stats.value.total_paid || 0) - (stats.value.total_discount || 0);
});

// Tabs
const activeTab = ref("info");

// Medical reports
const reportsLoaded = ref(false);
const reportDetailVisible = ref(false);

const loadMedicalReports = () => {
     if (!reportsLoaded.value && patient.value?.id) {
          store.GetPatientMedicalReports(patient.value.id);
          reportsLoaded.value = true;
     }
};

const viewReportDetail = (reportId) => {
     store.GetReportDetail(reportId);
     reportDetailVisible.value = true;
};

// Reset when modal closes
watch(visible, (val) => {
     if (!val) {
          activeTab.value = "info";
          reportsLoaded.value = false;
          store.patientMedicalReports = [];
     }
});

// Load reports when switching to medical_reports tab
watch(activeTab, (val) => {
     if (val === "medical_reports") {
          loadMedicalReports();
     }
});
</script>

<template>
     <UiModal v-model="visible" :title="t('patient_details') || 'Patient Details'" size="2xl">
          <div v-if="!patientDetail" class="py-12 text-center text-gray-400">
               {{ t("loading") }}...
          </div>

          <div v-else class="space-y-6">
               <!-- Tabs -->
               <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
                    <button
                         @click="activeTab = 'info'"
                         class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg transition-all"
                         :class="activeTab === 'info'
                              ? 'bg-white text-gray-900 shadow-sm'
                              : 'text-gray-500 hover:text-gray-700'"
                    >
                         {{ t("patient_details") }}
                    </button>
                    <button
                         @click="activeTab = 'medical_reports'"
                         class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg transition-all"
                         :class="activeTab === 'medical_reports'
                              ? 'bg-white text-gray-900 shadow-sm'
                              : 'text-gray-500 hover:text-gray-700'"
                    >
                         {{ t("medical_reports") }}
                    </button>
               </div>

               <!-- ========== INFO TAB ========== -->
               <div v-if="activeTab === 'info'" class="space-y-6">
                    <!-- Patient Info -->
                    <div class="bg-gray-50 rounded-xl p-5">
                         <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                              <div>
                                   <span class="text-gray-500">{{ t("name") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.name }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("code") }}</span>
                                   <p class="font-semibold text-gray-900 font-mono">{{ patient?.code }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("phone") }}</span>
                                   <p class="font-semibold text-gray-900" dir="ltr">{{ patient?.phone || "-" }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("email") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.email || "-" }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("gender") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.gender || "-" }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("age") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.age }} {{ patient?.age_unit || "" }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("nationality") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.nationality || "-" }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("lab") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.lab_name }}</p>
                              </div>
                              <div>
                                   <span class="text-gray-500">{{ t("address") }}</span>
                                   <p class="font-semibold text-gray-900">{{ patient?.address || "-" }}</p>
                              </div>
                         </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                         <div class="bg-blue-50 rounded-xl p-4 text-center">
                              <p class="text-2xl font-bold text-blue-700">{{ commaFormat(stats?.total_invoices) }}</p>
                              <p class="text-xs text-blue-500 mt-1">{{ t("total_invoices") }}</p>
                         </div>
                         <div class="bg-green-50 rounded-xl p-4 text-center">
                              <p class="text-2xl font-bold text-green-700">{{ commaFormat(stats?.total_paid) }}</p>
                              <p class="text-xs text-green-500 mt-1">{{ t("total_paid") }}</p>
                         </div>
                         <div class="bg-red-50 rounded-xl p-4 text-center">
                              <p class="text-2xl font-bold text-red-700">{{ commaFormat(remaining) }}</p>
                              <p class="text-xs text-red-500 mt-1">{{ t("total_due") }}</p>
                         </div>
                         <div class="bg-purple-50 rounded-xl p-4 text-center">
                              <p class="text-2xl font-bold text-purple-700">{{ commaFormat(stats?.done_count) }}</p>
                              <p class="text-xs text-purple-500 mt-1">{{ t("done") }}</p>
                         </div>
                    </div>

                    <!-- Invoices Table -->
                    <div>
                         <h3 class="text-lg font-semibold text-gray-800 mb-3">{{ t("invoices") }}</h3>
                         <div class="overflow-x-auto border border-gray-100 rounded-xl">
                              <table class="w-full text-sm">
                                   <thead class="bg-gray-50 border-b border-gray-100">
                                        <tr>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">#</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("lab") }}</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("total") }}</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("total_paid") }}</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("discount") }}</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("total_due") }}</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("status") }}</th>
                                             <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("date") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody class="divide-y divide-gray-50">
                                        <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-gray-50/50">
                                             <td class="px-4 py-2.5 text-gray-500">{{ inv.id }}</td>
                                             <td class="px-4 py-2.5 text-gray-600">{{ inv.lab_name }}</td>
                                             <td class="px-4 py-2.5 text-gray-900 font-medium">{{ commaFormat(inv.total) }}</td>
                                             <td class="px-4 py-2.5 text-green-600">{{ commaFormat(inv.paid) }}</td>
                                             <td class="px-4 py-2.5 text-amber-600">{{ commaFormat(inv.discount) }}</td>
                                             <td class="px-4 py-2.5 text-red-600">{{ commaFormat(inv.remaining) }}</td>
                                             <td class="px-4 py-2.5">
                                                  <span
                                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                       :class="inv.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                                  >
                                                       {{ inv.is_done ? t("done") : t("pendening") }}
                                                  </span>
                                             </td>
                                             <td class="px-4 py-2.5 text-gray-500">{{ inv.created_at?.split("T")[0] }}</td>
                                        </tr>
                                        <tr v-if="!invoices.length">
                                             <td colspan="8" class="px-4 py-6 text-center text-gray-400">{{ t("no_data") }}</td>
                                        </tr>
                                   </tbody>
                              </table>
                         </div>
                    </div>
               </div>

               <!-- ========== MEDICAL REPORTS TAB ========== -->
               <div v-if="activeTab === 'medical_reports'" class="space-y-4">
                    <div v-if="!patientMedicalReports.length" class="py-8 text-center text-gray-400">
                         {{ t("no_data") }}
                    </div>

                    <div v-else class="overflow-x-auto border border-gray-100 rounded-xl">
                         <table class="w-full text-sm">
                              <thead class="bg-gray-50 border-b border-gray-100">
                                   <tr>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">#</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("Barcode") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("Registration_date") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("lab/bruanch") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("Created_By") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("theStatus") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("is_sent_to_patient") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("actions") }}</th>
                                   </tr>
                              </thead>
                              <tbody class="divide-y divide-gray-50">
                                   <tr v-for="(report, i) in patientMedicalReports" :key="report.id" class="hover:bg-gray-50/50">
                                        <td class="px-4 py-2.5 text-gray-500">{{ i + 1 }}</td>
                                        <td class="px-4 py-2.5 text-gray-600 font-mono text-xs">{{ report.barcode }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ dateTimeFormat(report.registration_date) }}</td>
                                        <td class="px-4 py-2.5 text-gray-600">{{ report.lab }}</td>
                                        <td class="px-4 py-2.5 text-gray-600">{{ report.created_by?.name }}</td>
                                        <td class="px-4 py-2.5">
                                             <span
                                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  :class="report.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                             >
                                                  {{ report.is_done ? t("done") : t("pendening") }}
                                             </span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                             <span
                                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  :class="report.sent_to_patient ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                             >
                                                  {{ report.sent_to_patient ? t("done") : t("pendening") }}
                                             </span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                             <button
                                                  @click="viewReportDetail(report.id)"
                                                  class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 text-sm font-medium rounded-lg hover:bg-blue-100 transition-colors"
                                             >
                                                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                       <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                       <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                  </svg>
                                                  {{ t("view") }}
                                             </button>
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
               </div>
          </div>
     </UiModal>

     <ReportDetailModal v-model="reportDetailVisible" />
</template>
