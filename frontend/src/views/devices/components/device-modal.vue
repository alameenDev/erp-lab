<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useDevicesStore } from "@/store/modules/devices";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";
import { useToast } from "@/composables/useToast";

const toast = useToast();
const devicesStore = useDevicesStore();
const { record, dialog } = storeToRefs(devicesStore);
const { AddDevice, UpdateDevice } = devicesStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const deviceTypes = [
     { label: "Hematology", value: "hematology" },
     { label: "Chemistry", value: "chemistry" },
     { label: "Immunoassay", value: "immunoassay" },
     { label: "Urinalysis", value: "urinalysis" },
     { label: "Coagulation", value: "coagulation" },
     { label: "Microbiology", value: "microbiology" },
     { label: "Other", value: "other" },
];

const handleSubmit = async () => {
     try {
          if (record.value.id) {
               await UpdateDevice();
               toast.success(t("alertSuccess"));
          } else {
               await AddDevice();
               toast.success(t("alertSuccess"));
          }
          dialog.value = false;
          clearObjectValues(record.value);
          record.value.connection_type = "serial";
          record.value.connection_config = { com_port: "", baud_rate: 9600 };
     } catch (error) {
          console.error(error);
     }
};

const close = () => {
     dialog.value = false;
     clearObjectValues(record.value);
     record.value.connection_type = "serial";
     record.value.connection_config = { com_port: "", baud_rate: 9600 };
};
</script>

<template>
     <Teleport to="body">
          <Transition name="modal">
               <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="close"></div>
                    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
                         <!-- Header -->
                         <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                              <h3 class="text-lg font-semibold text-gray-800">
                                   {{ record?.id ? t("update") : t("add") }} {{ t("devices") || "Device" }}
                              </h3>
                              <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                                   <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                   </svg>
                              </button>
                         </div>

                         <!-- Body -->
                         <form @submit.prevent="handleSubmit" class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                              <div class="grid grid-cols-1 gap-4">
                                   <!-- Name -->
                                   <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("name") }} <span class="text-red-500">*</span></label>
                                        <input v-model="record.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" :placeholder="'Mindray BC-5000'" />
                                   </div>

                                   <!-- Device Type -->
                                   <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("device_type") || "Device Type" }}</label>
                                        <select v-model="record.device_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                             <option value="">{{ t("select") }}</option>
                                             <option v-for="dt in deviceTypes" :key="dt.value" :value="dt.value">{{ dt.label }}</option>
                                        </select>
                                   </div>

                                   <!-- Serial Number -->
                                   <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("serial_number") || "Serial Number" }}</label>
                                        <input v-model="record.serial_number" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                   </div>

                                   <!-- Connection Type -->
                                   <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("connection_type") || "Connection Type" }}</label>
                                        <select v-model="record.connection_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                             <option value="serial">Serial (RS-232)</option>
                                             <option value="tcp">TCP/IP</option>
                                        </select>
                                   </div>

                                   <!-- Serial Config -->
                                   <template v-if="record.connection_type === 'serial'">
                                        <div class="grid grid-cols-2 gap-3">
                                             <div>
                                                  <label class="block text-sm font-medium text-gray-700 mb-1">COM Port</label>
                                                  <input v-model="record.connection_config.com_port" type="text" placeholder="COM3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-sm font-medium text-gray-700 mb-1">Baud Rate</label>
                                                  <select v-model="record.connection_config.baud_rate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                       <option :value="9600">9600</option>
                                                       <option :value="19200">19200</option>
                                                       <option :value="38400">38400</option>
                                                       <option :value="57600">57600</option>
                                                       <option :value="115200">115200</option>
                                                  </select>
                                             </div>
                                        </div>
                                   </template>

                                   <!-- TCP Config -->
                                   <template v-if="record.connection_type === 'tcp'">
                                        <div class="grid grid-cols-2 gap-3">
                                             <div>
                                                  <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                                                  <input v-model="record.connection_config.ip" type="text" placeholder="192.168.1.100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                                                  <input v-model="record.connection_config.port" type="number" placeholder="5000" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                             </div>
                                        </div>
                                   </template>
                              </div>

                              <!-- Footer -->
                              <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                                   <button type="button" @click="close" class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                        {{ t("close") }}
                                   </button>
                                   <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                                        {{ record.id ? t("save") : t("add") }}
                                   </button>
                              </div>
                         </form>
                    </div>
               </div>
          </Transition>
     </Teleport>
</template>
