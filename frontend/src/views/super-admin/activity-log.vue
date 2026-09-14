<script setup>
import { ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t } from "@/utils/helper";

const store = useSuperAdminStore();
const { activities, activitiesPagination } = storeToRefs(store);

const labFilter = ref("");
const eventFilter = ref("");
const dateFrom = ref("");
const dateTo = ref("");
const searchFilter = ref("");
const expandedRow = ref(null);

const filters = () => ({
     page: activitiesPagination.value.current_page,
     lab_id: labFilter.value || undefined,
     event: eventFilter.value || undefined,
     date_from: dateFrom.value || undefined,
     date_to: dateTo.value || undefined,
     search: searchFilter.value || undefined,
});

const loadActivities = () => {
     store.GetActivities(filters());
};

const changePage = (page) => {
     activitiesPagination.value.current_page = page;
     loadActivities();
};

const applyFilter = () => {
     activitiesPagination.value.current_page = 1;
     loadActivities();
};

const toggleExpand = (id) => {
     expandedRow.value = expandedRow.value === id ? null : id;
};

onMounted(loadActivities);
</script>

<template>
     <div class="space-y-6">
          <!-- Header -->
          <h1 class="text-2xl font-bold text-gray-900">{{ t("system_activity_log") }}</h1>

          <!-- Filters -->
          <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm flex flex-wrap gap-4 items-end">
               <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("search") }}</label>
                    <input
                         v-model="searchFilter"
                         type="text"
                         :placeholder="t('search')"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                         @keyup.enter="applyFilter"
                    />
               </div>
               <div class="min-w-[160px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("event") }}</label>
                    <select
                         v-model="eventFilter"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         @change="applyFilter"
                    >
                         <option value="">{{ t("all") }}</option>
                         <option value="إنشاء">{{ t("create") }}</option>
                         <option value="تعديل">{{ t("edit") }}</option>
                         <option value="حذف">{{ t("delete") }}</option>
                         <option value="تسجيل الدخول">{{ t("login") }}</option>
                    </select>
               </div>
               <div class="min-w-[160px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("from_date") }}</label>
                    <input
                         v-model="dateFrom"
                         type="date"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         @click="$event.target.showPicker()"
                         @change="applyFilter"
                    />
               </div>
               <div class="min-w-[160px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("to_date") }}</label>
                    <input
                         v-model="dateTo"
                         type="date"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         @click="$event.target.showPicker()"
                         @change="applyFilter"
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
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("date") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("user") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("role") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("event") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("description") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("details") }}</th>
                              </tr>
                         </thead>
                         <tbody class="divide-y divide-gray-50">
                              <template v-for="(act, i) in activities" :key="act.id">
                                   <tr class="hover:bg-gray-50/50">
                                        <td class="px-4 py-3 text-gray-500">{{ (activitiesPagination.current_page - 1) * activitiesPagination.per_page + i + 1 }}</td>
                                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ act.created_at?.split("T")[0] || act.created_at?.substring(0, 10) }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ act.causer_name || "-" }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ act.causer_role || "-" }}</td>
                                        <td class="px-4 py-3">
                                             <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                  {{ act.log_name }}
                                             </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ act.description }}</td>
                                        <td class="px-4 py-3">
                                             <button
                                                  v-if="act.properties && Object.keys(act.properties).length"
                                                  @click="toggleExpand(act.id)"
                                                  class="text-blue-600 hover:text-blue-800 text-sm"
                                             >
                                                  {{ expandedRow === act.id ? t("hide") : t("view") }}
                                             </button>
                                        </td>
                                   </tr>
                                   <tr v-if="expandedRow === act.id">
                                        <td colspan="7" class="px-4 py-3 bg-gray-50">
                                             <pre class="text-xs text-gray-600 overflow-x-auto max-h-60">{{ JSON.stringify(act.properties, null, 2) }}</pre>
                                        </td>
                                   </tr>
                              </template>
                              <tr v-if="!activities.length">
                                   <td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ t("no_data") }}</td>
                              </tr>
                         </tbody>
                    </table>
               </div>

               <!-- Pagination -->
               <div v-if="activitiesPagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-sm text-gray-500">{{ t("total") }}: {{ activitiesPagination.total }}</p>
                    <div class="flex gap-1">
                         <button
                              v-for="page in activitiesPagination.last_page"
                              :key="page"
                              @click="changePage(page)"
                              class="px-3 py-1 text-sm rounded-lg"
                              :class="page === activitiesPagination.current_page
                                   ? 'bg-blue-500 text-white'
                                   : 'text-gray-600 hover:bg-gray-100'"
                         >
                              {{ page }}
                         </button>
                    </div>
               </div>
          </div>
     </div>
</template>
