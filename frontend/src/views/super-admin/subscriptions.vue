<script setup>
import { ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat } from "@/utils/helper";
import SubscriptionModal from "./components/subscription-modal.vue";

const store = useSuperAdminStore();
const { subscriptions, subscriptionsPagination, subscriptionDialog } = storeToRefs(store);

const statusFilter = ref("");
const searchFilter = ref("");
const expiringSoon = ref(false);

const filters = () => ({
     page: subscriptionsPagination.value.current_page,
     status: statusFilter.value || undefined,
     search: searchFilter.value || undefined,
     expiring_soon: expiringSoon.value ? 1 : undefined,
});

const loadSubscriptions = () => {
     store.GetSubscriptions(filters());
};

const changePage = (page) => {
     subscriptionsPagination.value.current_page = page;
     loadSubscriptions();
};

const applyFilter = () => {
     subscriptionsPagination.value.current_page = 1;
     loadSubscriptions();
};

const openAdd = () => {
     store.resetSubscriptionRecord();
     subscriptionDialog.value = true;
};

const openEdit = (sub) => {
     store.subscriptionRecord = {
          id: sub.id,
          lab_id_fk: sub.lab_id_fk,
          plan_name: sub.plan_name,
          start_date: sub.start_date?.split("T")[0] || sub.start_date,
          end_date: sub.end_date?.split("T")[0] || sub.end_date,
          status: sub.status,
          max_users: sub.max_users,
          max_invoices_per_month: sub.max_invoices_per_month,
          price: sub.price,
          currency: sub.currency,
          notes: sub.notes,
     };
     subscriptionDialog.value = true;
};

const deleteSubscription = async (sub) => {
     if (!confirm(t("delete_subscription") + "?")) return;
     try {
          await store.RemoveSubscription(sub.id);
          loadSubscriptions();
     } catch (e) {
          console.error(e);
     }
};

const statusBadge = (status) => {
     const map = {
          active: { text: t("subscription_active"), class: "bg-green-100 text-green-700" },
          trial: { text: t("subscription_trial"), class: "bg-blue-100 text-blue-700" },
          expired: { text: t("subscription_expired"), class: "bg-red-100 text-red-700" },
          suspended: { text: t("subscription_suspended"), class: "bg-amber-100 text-amber-700" },
     };
     return map[status] || { text: status, class: "bg-gray-100 text-gray-600" };
};

const onSaved = () => {
     loadSubscriptions();
};

onMounted(loadSubscriptions);
</script>

<template>
     <div class="space-y-6">
          <!-- Header -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
               <h1 class="text-2xl font-bold text-gray-900">{{ t("subscriptions") }}</h1>
               <UiButton @click="openAdd">+ {{ t("add_subscription") }}</UiButton>
          </div>

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
               <div class="min-w-[180px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("status") }}</label>
                    <select
                         v-model="statusFilter"
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
               <div class="flex items-center gap-2">
                    <input id="expiring" v-model="expiringSoon" type="checkbox" class="rounded" @change="applyFilter" />
                    <label for="expiring" class="text-sm text-gray-700">{{ t("expiring_soon") }}</label>
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
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("plan_name") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("status") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("start_date") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("end_date") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("max_users") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("max_invoices_per_month") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("price") }}</th>
                                   <th class="text-start px-4 py-3 font-medium text-gray-600">{{ t("actions") }}</th>
                              </tr>
                         </thead>
                         <tbody class="divide-y divide-gray-50">
                              <tr v-for="(sub, i) in subscriptions" :key="sub.id" class="hover:bg-gray-50/50">
                                   <td class="px-4 py-3 text-gray-500">{{ (subscriptionsPagination.current_page - 1) * subscriptionsPagination.per_page + i + 1 }}</td>
                                   <td class="px-4 py-3 font-medium text-gray-900">{{ sub.lab?.name || "-" }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ sub.plan_name }}</td>
                                   <td class="px-4 py-3">
                                        <span
                                             class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                             :class="statusBadge(sub.status).class"
                                        >
                                             {{ statusBadge(sub.status).text }}
                                        </span>
                                   </td>
                                   <td class="px-4 py-3 text-gray-600">{{ sub.start_date?.split("T")[0] || sub.start_date }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ sub.end_date?.split("T")[0] || sub.end_date }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ sub.max_users || "-" }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ sub.max_invoices_per_month || "-" }}</td>
                                   <td class="px-4 py-3 text-gray-600">{{ commaFormat(sub.price) }} {{ sub.currency }}</td>
                                   <td class="px-4 py-3 flex gap-2">
                                        <button @click="openEdit(sub)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                             {{ t("edit") }}
                                        </button>
                                        <button @click="deleteSubscription(sub)" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                             {{ t("delete") }}
                                        </button>
                                   </td>
                              </tr>
                              <tr v-if="!subscriptions.length">
                                   <td colspan="10" class="px-4 py-8 text-center text-gray-400">{{ t("no_data") }}</td>
                              </tr>
                         </tbody>
                    </table>
               </div>

               <!-- Pagination -->
               <div v-if="subscriptionsPagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-sm text-gray-500">{{ t("total") }}: {{ subscriptionsPagination.total }}</p>
                    <div class="flex gap-1">
                         <button
                              v-for="page in subscriptionsPagination.last_page"
                              :key="page"
                              @click="changePage(page)"
                              class="px-3 py-1 text-sm rounded-lg"
                              :class="page === subscriptionsPagination.current_page
                                   ? 'bg-blue-500 text-white'
                                   : 'text-gray-600 hover:bg-gray-100'"
                         >
                              {{ page }}
                         </button>
                    </div>
               </div>
          </div>

          <SubscriptionModal @saved="onSaved" />
     </div>
</template>
