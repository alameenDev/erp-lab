<script setup>
import { ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat } from "@/utils/helper";
import LabDetailModal from "./components/lab-detail-modal.vue";

const store = useSuperAdminStore();
const { labs, labsPagination } = storeToRefs(store);

const search = ref("");
const subscriptionStatus = ref("");
const labDetailVisible = ref(false);

const filters = () => ({
     page: labsPagination.value.current_page,
     search: search.value || undefined,
     subscription_status: subscriptionStatus.value || undefined,
});

const loadLabs = () => {
     store.GetLabs(filters());
};

const changePage = (page) => {
     labsPagination.value.current_page = page;
     loadLabs();
};

const applyFilter = () => {
     labsPagination.value.current_page = 1;
     loadLabs();
};

const viewLabDetail = (labId) => {
     store.GetLabDetail(labId);
     labDetailVisible.value = true;
};

const statusBadge = (sub) => {
     if (!sub) return { text: t("no_subscription"), class: "bg-gray-100 text-gray-600" };
     const map = {
          active: { text: t("subscription_active"), class: "bg-green-100 text-green-700" },
          trial: { text: t("subscription_trial"), class: "bg-blue-100 text-blue-700" },
          expired: { text: t("subscription_expired"), class: "bg-red-100 text-red-700" },
          suspended: { text: t("subscription_suspended"), class: "bg-amber-100 text-amber-700" },
     };
     return map[sub.status] || { text: sub.status, class: "bg-gray-100 text-gray-600" };
};

onMounted(loadLabs);
</script>

<template>
     <div class="space-y-6">
          <!-- Header -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
               <h1 class="text-2xl font-bold text-gray-900">{{ t("lab_management") }}</h1>
          </div>

          <!-- Filters -->
          <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm flex flex-wrap gap-4 items-end">
               <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("search") }}</label>
                    <input
                         v-model="search"
                         type="text"
                         :placeholder="t('search')"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                         @keyup.enter="applyFilter"
                    />
               </div>
               <div class="min-w-[180px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("subscription_status") }}</label>
                    <select
                         v-model="subscriptionStatus"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                         @change="applyFilter"
                    >
                         <option value="">{{ t("all") }}</option>
                         <option value="active">{{ t("subscription_active") }}</option>
                         <option value="trial">{{ t("subscription_trial") }}</option>
                         <option value="expired">{{ t("subscription_expired") }}</option>
                         <option value="suspended">{{ t("subscription_suspended") }}</option>
                    </select>
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
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("name") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("email") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("phone") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("subscriptions") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("invoice_count") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("patient_count") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("total_revenue") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("created_at") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("actions") }}</th>
                              </tr>
                         </thead>
                         <tbody class="divide-y divide-gray-50">
                              <tr v-for="(lab, i) in labs" :key="lab.id" class="hover:bg-gray-50/50">
                                   <td class="px-4 py-3 text-gray-500">{{ (labsPagination.current_page - 1) * labsPagination.per_page + i + 1 }}</td>
                                   <td class="px-4 py-3 font-medium text-gray-900">{{ lab.name }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ lab.email }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ lab.phone_number }}</td>
                                   <td class="px-4 py-3">
                                        <span
                                             class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                             :class="statusBadge(lab.subscription).class"
                                        >
                                             {{ statusBadge(lab.subscription).text }}
                                        </span>
                                   </td>
                                   <td class="px-4 py-3 text-gray-600">{{ commaFormat(lab.invoice_count) }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ commaFormat(lab.patient_count) }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ commaFormat(lab.total_revenue) }}</td>
                                   <td class="px-4 py-3 text-gray-500">{{ lab.created_at?.split("T")[0] }}</td>
                                   <td class="px-4 py-3">
                                        <button
                                             @click="viewLabDetail(lab.id)"
                                             class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                        >
                                             {{ t("view") }}
                                        </button>
                                   </td>
                              </tr>
                              <tr v-if="!labs.length">
                                   <td colspan="10" class="px-4 py-8 text-center text-gray-400">{{ t("no_data") }}</td>
                              </tr>
                         </tbody>
                    </table>
               </div>

               <!-- Pagination -->
               <div v-if="labsPagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-sm text-gray-500">
                         {{ t("total") }}: {{ labsPagination.total }}
                    </p>
                    <div class="flex gap-1">
                         <button
                              v-for="page in labsPagination.last_page"
                              :key="page"
                              @click="changePage(page)"
                              class="px-3 py-1 text-sm rounded-lg"
                              :class="page === labsPagination.current_page
                                   ? 'bg-blue-500 text-white'
                                   : 'text-gray-600 hover:bg-gray-100'"
                         >
                              {{ page }}
                         </button>
                    </div>
               </div>
          </div>

          <LabDetailModal v-model="labDetailVisible" />
     </div>
</template>
