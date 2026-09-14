<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { storeToRefs } from "pinia";
import { useDevicesStore } from "@/store/modules/devices";
import { useAuthStore } from "@/store/modules/auth";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import DeviceModal from "./components/device-modal.vue";

const toast = useToast();
const devicesStore = useDevicesStore();
const authStore = useAuthStore();
const { havePermission } = authStore;

const { devices, deviceResults, totalCount, pendingCount, record, dialog, newTokenDialog, newToken } = storeToRefs(devicesStore);
const { GetDevices, GetDeviceResults, RemoveDevice, ApplyResult, RegenerateToken, startPolling, stopPolling } = devicesStore;

const isLoading = ref(true);
const activeTab = ref("devices");
const lang = computed(() => localStorage.getItem("locale") || "ar");
const onlineCount = computed(() => devices.value.filter((d) => d.status === "online").length);

onMounted(async () => {
     try {
          await GetDevices();
          await GetDeviceResults({ status: "pending,matched" });
          startPolling(30000);
     } finally {
          isLoading.value = false;
     }
});

onUnmounted(() => stopPolling());

const addDevice = () => {
     Object.keys(record.value).forEach((key) => {
          if (key === "connection_type") record.value[key] = "serial";
          else if (key === "connection_config") record.value[key] = { com_port: "", baud_rate: 9600 };
          else record.value[key] = "";
     });
     record.value.id = "";
     dialog.value = true;
};

const editDevice = (device) => {
     Object.assign(record.value, device);
     record.value.connection_config = device.connection_config || { com_port: "", baud_rate: 9600 };
     dialog.value = true;
};

const deleteDevice = (device) => {
     showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
          if (res.value) {
               record.value.id = device.id;
               RemoveDevice().then(() => toast.success(t("alertSuccess")));
          }
     });
};

const regenerateToken = (device) => {
     showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
          if (res.value) {
               RegenerateToken(device.id).then(() => toast.success(t("alertSuccess")));
          }
     });
};

const applyResult = async (result) => {
     try {
          const data = await ApplyResult(result.id);
          toast.success(`${data.applied_count} / ${data.total_results} ${t("apply_results")}`);
     } catch (error) {
          toast.error(error.response?.data?.message || t("error"));
     }
};

const copyToken = () => {
     navigator.clipboard.writeText(newToken.value);
     toast.success(t("copied"));
};

const statusBadge = (status) => {
     const map = { online: "bg-green-100 text-green-700", offline: "bg-gray-100 text-gray-600", error: "bg-red-100 text-red-700" };
     return map[status] || map.offline;
};

const statusLabel = (status) => {
     const map = { online: t("online"), offline: t("offline"), error: t("error") };
     return map[status] || status;
};

const resultStatusBadge = (status) => {
     const map = { pending: "bg-yellow-100 text-yellow-700", matched: "bg-blue-100 text-blue-700", applied: "bg-green-100 text-green-700", failed: "bg-red-100 text-red-700" };
     return map[status] || map.pending;
};

const timeAgo = (dateStr) => {
     if (!dateStr) return "-";
     const diff = Date.now() - new Date(dateStr).getTime();
     const mins = Math.floor(diff / 60000);
     if (mins < 1) return t("just_now") || "just now";
     if (mins < 60) return `${mins} ${t("minutes") || "m"}`;
     const hours = Math.floor(mins / 60);
     if (hours < 24) return `${hours} ${t("hours") || "h"}`;
     return `${Math.floor(hours / 24)} ${t("days") || "d"}`;
};
</script>

<template>
     <div class="space-y-6">
          <!-- ==================== HEADER SECTION ==================== -->
          <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 shadow-2xl">
               <div class="absolute inset-0">
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')]"></div>
                    <div class="absolute -top-24 -end-24 w-96 h-96 bg-primary-500/20 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-12 -start-12 w-64 h-64 bg-primary-600/15 rounded-full blur-2xl"></div>
               </div>

               <div class="relative p-6 lg:p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                         <div class="flex-1">
                              <div class="flex items-center gap-3 mb-3">
                                   <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
                                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                        </svg>
                                   </div>
                                   <div>
                                        <span class="px-3 py-1 bg-primary-500/20 text-primary-300 text-xs font-semibold rounded-full">{{ totalCount }} {{ t("devices") }}</span>
                                   </div>
                              </div>
                              <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">{{ t("devices") }}</h1>
                              <p class="text-slate-400 text-sm lg:text-base">{{ t("device_management") }}</p>
                         </div>

                         <div class="flex gap-3 flex-wrap">
                              <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
                                   <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center">
                                             <div class="w-2.5 h-2.5 bg-green-400 rounded-full animate-pulse"></div>
                                        </div>
                                        <span class="text-xs text-slate-400">{{ t("online") }}</span>
                                   </div>
                                   <p class="text-2xl font-bold text-white">{{ onlineCount }}</p>
                              </div>
                              <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[120px]">
                                   <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 rounded-lg bg-amber-500/30 flex items-center justify-center">
                                             <svg class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                             </svg>
                                        </div>
                                        <span class="text-xs text-slate-400">{{ t("pending_results") }}</span>
                                   </div>
                                   <p class="text-2xl font-bold text-white">{{ pendingCount }}</p>
                              </div>
                         </div>
                    </div>
               </div>
          </div>

          <!-- ==================== ACTION BAR ==================== -->
          <div class="flex items-center justify-between">
               <div class="flex gap-2">
                    <button @click="activeTab = 'devices'" :class="['px-4 py-2 text-sm font-medium rounded-xl transition-colors', activeTab === 'devices' ? 'bg-primary-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50']">
                         {{ t("devices") }} ({{ totalCount }})
                    </button>
                    <button @click="activeTab = 'results'; GetDeviceResults({ status: 'pending,matched' })" :class="['px-4 py-2 text-sm font-medium rounded-xl transition-colors', activeTab === 'results' ? 'bg-amber-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50']">
                         {{ t("pending_results") }} ({{ pendingCount }})
                    </button>
               </div>
               <button v-if="havePermission('devices create')" @click="addDevice" class="flex items-center gap-2 px-4 py-2.5 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t("add") }}
               </button>
          </div>

          <!-- ==================== DEVICES TABLE ==================== -->
          <div v-if="activeTab === 'devices'" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
               <table class="w-full">
                    <thead>
                         <tr class="bg-slate-50/80 border-b border-slate-200/80">
                              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("name") }}</th>
                              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("device_type") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("status") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("last_seen") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("pending_results") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("actions") }}</th>
                         </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                         <tr v-for="device in devices" :key="device.id" class="hover:bg-slate-50/50 transition-colors">
                              <td class="px-5 py-4 text-sm text-slate-500">{{ device.index }}</td>
                              <td class="px-5 py-4">
                                   <p class="text-sm font-semibold text-slate-800">{{ device.name }}</p>
                                   <p class="text-xs text-slate-400 mt-0.5">{{ device.serial_number || '-' }}</p>
                              </td>
                              <td class="px-5 py-4 text-sm text-slate-600 capitalize">{{ device.device_type || '-' }}</td>
                              <td class="px-5 py-4 text-center">
                                   <span :class="['px-2.5 py-1 text-xs font-medium rounded-full', statusBadge(device.status)]">{{ statusLabel(device.status) }}</span>
                              </td>
                              <td class="px-5 py-4 text-center text-xs text-slate-500">{{ timeAgo(device.last_seen_at) }}</td>
                              <td class="px-5 py-4 text-center">
                                   <span v-if="device.pending_results_count > 0" class="px-2.5 py-1 text-xs font-bold bg-amber-100 text-amber-700 rounded-full">{{ device.pending_results_count }}</span>
                                   <span v-else class="text-xs text-slate-400">0</span>
                              </td>
                              <td class="px-5 py-4">
                                   <div class="flex items-center justify-center gap-1">
                                        <button v-if="havePermission('devices edit')" @click="editDevice(device)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" :title="t('edit')">
                                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                             </svg>
                                        </button>
                                        <button v-if="havePermission('devices edit')" @click="regenerateToken(device)" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" :title="t('regenerate_token')">
                                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                             </svg>
                                        </button>
                                        <button v-if="havePermission('devices delete')" @click="deleteDevice(device)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" :title="t('delete')">
                                             <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                             </svg>
                                        </button>
                                   </div>
                              </td>
                         </tr>
                         <tr v-if="!devices.length">
                              <td colspan="7" class="px-5 py-16 text-center">
                                   <svg class="w-16 h-16 mx-auto text-slate-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                   </svg>
                                   <p class="text-slate-400 text-sm">{{ t("no_data") }}</p>
                              </td>
                         </tr>
                    </tbody>
               </table>
          </div>

          <!-- ==================== PENDING RESULTS TABLE ==================== -->
          <div v-if="activeTab === 'results'" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
               <table class="w-full">
                    <thead>
                         <tr class="bg-slate-50/80 border-b border-slate-200/80">
                              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("barcode") }}</th>
                              <th class="px-5 py-4 text-start text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("devices") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("tests") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("status") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("date") }}</th>
                              <th class="px-5 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ t("actions") }}</th>
                         </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                         <tr v-for="result in deviceResults" :key="result.id" class="hover:bg-slate-50/50 transition-colors">
                              <td class="px-5 py-4">
                                   <p class="text-sm font-mono font-semibold text-slate-800">{{ result.specimen_barcode }}</p>
                              </td>
                              <td class="px-5 py-4 text-sm text-slate-600">{{ result.device?.name || '-' }}</td>
                              <td class="px-5 py-4 text-center text-sm text-slate-600">{{ result.parsed_results?.length || 0 }}</td>
                              <td class="px-5 py-4 text-center">
                                   <span :class="['px-2.5 py-1 text-xs font-medium rounded-full', resultStatusBadge(result.status)]">{{ result.status }}</span>
                              </td>
                              <td class="px-5 py-4 text-center text-xs text-slate-500">{{ timeAgo(result.created_at) }}</td>
                              <td class="px-5 py-4 text-center">
                                   <button v-if="result.status === 'matched'" @click="applyResult(result)" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                                        {{ t("apply_results") }}
                                   </button>
                                   <span v-else-if="result.status === 'pending'" class="text-xs text-amber-600">{{ t("no_match") }}</span>
                                   <span v-else-if="result.status === 'applied'" class="text-xs text-green-600">{{ t("done") }}</span>
                              </td>
                         </tr>
                         <tr v-if="!deviceResults.length">
                              <td colspan="6" class="px-5 py-16 text-center">
                                   <svg class="w-16 h-16 mx-auto text-slate-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                   </svg>
                                   <p class="text-slate-400 text-sm">{{ t("no_data") }}</p>
                              </td>
                         </tr>
                    </tbody>
               </table>
          </div>

          <!-- Device Modal -->
          <DeviceModal />

          <!-- Token Display Modal -->
          <Teleport to="body">
               <Transition name="modal">
                    <div v-if="newTokenDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                         <div class="fixed inset-0 bg-black/50" @click="newTokenDialog = false"></div>
                         <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
                              <div class="flex items-center gap-3 mb-4">
                                   <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                   </div>
                                   <div>
                                        <h3 class="text-lg font-semibold text-slate-800">{{ t("api_token") || "API Token" }}</h3>
                                        <p class="text-xs text-amber-600">{{ t("token_warning") }}</p>
                                   </div>
                              </div>
                              <div class="bg-slate-50 rounded-lg p-3 mb-4 flex items-center gap-2">
                                   <code class="text-xs font-mono text-slate-700 flex-1 break-all select-all">{{ newToken }}</code>
                                   <button @click="copyToken" class="p-2 text-primary-600 hover:bg-primary-50 rounded-lg shrink-0" :title="t('copy')">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                   </button>
                              </div>
                              <button @click="newTokenDialog = false" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                                   {{ t("close") }}
                              </button>
                         </div>
                    </div>
               </Transition>
          </Teleport>
     </div>
</template>
