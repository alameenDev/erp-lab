<script setup>
import { ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat } from "@/utils/helper";
import PatientDetailModal from "./components/patient-detail-modal.vue";

const store = useSuperAdminStore();
const { patients, patientsPagination } = storeToRefs(store);

const search = ref("");
const labId = ref("");
const patientDetailVisible = ref(false);

const filters = () => ({
     page: patientsPagination.value.current_page,
     search: search.value || undefined,
     lab_id: labId.value || undefined,
});

const loadPatients = () => {
     store.GetPatients(filters());
};

const changePage = (page) => {
     patientsPagination.value.current_page = page;
     loadPatients();
};

const applyFilter = () => {
     patientsPagination.value.current_page = 1;
     loadPatients();
};

const viewPatientDetail = (patientId) => {
     store.GetPatientDetail(patientId);
     patientDetailVisible.value = true;
};

onMounted(loadPatients);
</script>

<template>
     <div class="space-y-6">
          <!-- Header -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
               <h1 class="text-2xl font-bold text-gray-900">{{ t("patients") }}</h1>
               <div class="text-sm text-gray-500">
                    {{ t("total") }}: {{ commaFormat(patientsPagination.total) }}
               </div>
          </div>

          <!-- Filters -->
          <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm flex flex-wrap gap-4 items-end">
               <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("search") }}</label>
                    <input
                         v-model="search"
                         type="text"
                         :placeholder="t('search') + ' (' + t('name') + ', ' + t('phone') + ', ' + t('code') + ')'"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                         @keyup.enter="applyFilter"
                    />
               </div>
               <UiButton @click="applyFilter" class="h-10">{{ t("search") }}</UiButton>
          </div>

          <!-- Table -->
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
               <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                         <thead class="bg-gray-50 border-b border-gray-100">
                              <tr>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">#</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("code") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("name") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("phone") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("gender") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("age") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("lab") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("invoice_count") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("total_revenue") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("created_at") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("actions") }}</th>
                              </tr>
                         </thead>
                         <tbody class="divide-y divide-gray-50">
                              <tr v-for="(patient, i) in patients" :key="patient.id" class="hover:bg-gray-50/50">
                                   <td class="px-4 py-3 text-gray-500">{{ (patientsPagination.current_page - 1) * patientsPagination.per_page + i + 1 }}</td>
                                   <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ patient.code }}</td>
                                   <td class="px-4 py-3 font-medium text-gray-900">{{ patient.name }}</td>
                                   <td class="px-4 py-3 text-gray-600" dir="ltr">{{ patient.phone }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ patient.gender }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ patient.age }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ patient.lab_name }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ commaFormat(patient.invoice_count) }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ commaFormat(patient.total_amount) }}</td>
                                   <td class="px-4 py-3 text-gray-500">{{ patient.created_at?.split("T")[0] }}</td>
                                   <td class="px-4 py-3">
                                        <button
                                             @click="viewPatientDetail(patient.id)"
                                             class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                        >
                                             {{ t("view") }}
                                        </button>
                                   </td>
                              </tr>
                              <tr v-if="!patients.length">
                                   <td colspan="11" class="px-4 py-8 text-center text-gray-400">{{ t("no_data") }}</td>
                              </tr>
                         </tbody>
                    </table>
               </div>

               <!-- Pagination -->
               <div v-if="patientsPagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-sm text-gray-500">
                         {{ t("total") }}: {{ patientsPagination.total }}
                    </p>
                    <div class="flex gap-1">
                         <button
                              v-for="page in patientsPagination.last_page"
                              :key="page"
                              @click="changePage(page)"
                              class="px-3 py-1 text-sm rounded-lg"
                              :class="page === patientsPagination.current_page
                                   ? 'bg-blue-500 text-white'
                                   : 'text-gray-600 hover:bg-gray-100'"
                         >
                              {{ page }}
                         </button>
                    </div>
               </div>
          </div>

          <PatientDetailModal v-model="patientDetailVisible" />
     </div>
</template>
