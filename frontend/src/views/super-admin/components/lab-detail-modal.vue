<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat } from "@/utils/helper";
import { Line } from "vue-chartjs";
import {
     Chart as ChartJS,
     Title,
     Tooltip,
     Legend,
     PointElement,
     LineElement,
     CategoryScale,
     LinearScale,
     Filler,
} from "chart.js";

ChartJS.register(Title, Tooltip, Legend, PointElement, LineElement, CategoryScale, LinearScale, Filler);

const visible = defineModel({ type: Boolean });

const store = useSuperAdminStore();
const { labDetail } = storeToRefs(store);

const lab = computed(() => labDetail.value?.lab);
const invoiceStats = computed(() => labDetail.value?.invoice_stats);
const branchLabs = computed(() => labDetail.value?.branch_labs || []);
const recentInvoices = computed(() => labDetail.value?.recent_invoices || []);
const monthlyRevenue = computed(() => labDetail.value?.monthly_revenue || []);

const subscription = computed(() => lab.value?.subscription);

const statusBadge = (status) => {
     const map = {
          active: { text: t("subscription_active"), class: "bg-green-100 text-green-700" },
          trial: { text: t("subscription_trial"), class: "bg-blue-100 text-blue-700" },
          expired: { text: t("subscription_expired"), class: "bg-red-100 text-red-700" },
          suspended: { text: t("subscription_suspended"), class: "bg-amber-100 text-amber-700" },
     };
     return map[status] || { text: status || t("no_subscription"), class: "bg-gray-100 text-gray-600" };
};

const revenueChart = computed(() => ({
     data: {
          labels: monthlyRevenue.value.map((m) => m.month),
          datasets: [
               {
                    label: t("total_revenue"),
                    data: monthlyRevenue.value.map((m) => m.revenue),
                    borderColor: "#3b82f6",
                    backgroundColor: "rgba(59,130,246,0.1)",
                    fill: true,
                    tension: 0.4,
               },
          ],
     },
     options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } },
     },
}));
</script>

<template>
     <UiModal v-model="visible" :title="t('lab_details')" size="xl">
          <div v-if="labDetail" class="space-y-6">
               <!-- Lab Info -->
               <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-xl p-4">
                         <p class="text-sm text-gray-500">{{ t("name") }}</p>
                         <p class="font-semibold text-gray-900">{{ lab?.name }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                         <p class="text-sm text-gray-500">{{ t("email") }}</p>
                         <p class="font-semibold text-gray-900">{{ lab?.email }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                         <p class="text-sm text-gray-500">{{ t("phone") }}</p>
                         <p class="font-semibold text-gray-900">{{ lab?.phone_number || "-" }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                         <p class="text-sm text-gray-500">{{ t("subscription_status") }}</p>
                         <span
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1"
                              :class="statusBadge(subscription?.status).class"
                         >
                              {{ statusBadge(subscription?.status).text }}
                         </span>
                    </div>
               </div>

               <!-- Stats -->
               <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                         <p class="text-2xl font-bold text-blue-700">{{ commaFormat(invoiceStats?.total || 0) }}</p>
                         <p class="text-xs text-blue-500">{{ t("invoices") }}</p>
                    </div>
                    <div class="bg-green-50 rounded-xl p-3 text-center">
                         <p class="text-2xl font-bold text-green-700">{{ commaFormat(invoiceStats?.revenue || 0) }}</p>
                         <p class="text-xs text-green-500">{{ t("total_revenue") }}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-3 text-center">
                         <p class="text-2xl font-bold text-emerald-700">{{ commaFormat(invoiceStats?.paid || 0) }}</p>
                         <p class="text-xs text-emerald-500">{{ t("total_paid") }}</p>
                    </div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center">
                         <p class="text-2xl font-bold text-purple-700">{{ commaFormat(labDetail?.patient_count || 0) }}</p>
                         <p class="text-xs text-purple-500">{{ t("patients") }}</p>
                    </div>
               </div>

               <!-- Mini Revenue Chart -->
               <div v-if="monthlyRevenue.length" class="bg-white border border-gray-100 rounded-xl p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ t("monthly_revenue") }}</h4>
                    <div class="h-40">
                         <Line :data="revenueChart.data" :options="revenueChart.options" />
                    </div>
               </div>

               <!-- Branch Labs -->
               <div v-if="branchLabs.length">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ t("branch_labs") }} ({{ branchLabs.length }})</h4>
                    <div class="overflow-x-auto">
                         <table class="w-full text-sm">
                              <thead class="bg-gray-50">
                                   <tr>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("name") }}</th>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("email") }}</th>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("phone") }}</th>
                                   </tr>
                              </thead>
                              <tbody class="divide-y divide-gray-50">
                                   <tr v-for="branch in branchLabs" :key="branch.id">
                                        <td class="px-3 py-2">{{ branch.name }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ branch.email }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ branch.phone_number || "-" }}</td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
               </div>

               <!-- Recent Invoices -->
               <div v-if="recentInvoices.length">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ t("invoices") }} ({{ t("recent") }})</h4>
                    <div class="overflow-x-auto">
                         <table class="w-full text-sm">
                              <thead class="bg-gray-50">
                                   <tr>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("barcode") }}</th>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("patient") }}</th>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("total") }}</th>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("paid") }}</th>
                                        <th class="text-start px-3 py-2 text-gray-600">{{ t("status") }}</th>
                                   </tr>
                              </thead>
                              <tbody class="divide-y divide-gray-50">
                                   <tr v-for="inv in recentInvoices" :key="inv.id">
                                        <td class="px-3 py-2 font-mono text-xs">{{ inv.barcode }}</td>
                                        <td class="px-3 py-2">
                                             {{ [inv.patient?.first_name, inv.patient?.middle_name, inv.patient?.last_name].filter(Boolean).join(" ") }}
                                        </td>
                                        <td class="px-3 py-2">{{ commaFormat(inv.total) }}</td>
                                        <td class="px-3 py-2">{{ commaFormat(inv.paid) }}</td>
                                        <td class="px-3 py-2">
                                             <span
                                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  :class="inv.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                             >
                                                  {{ inv.is_done ? t("done") : t("pending") }}
                                             </span>
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
               </div>
          </div>
          <div v-else class="py-12 text-center text-gray-400">{{ t("loading") }}</div>
     </UiModal>
</template>
