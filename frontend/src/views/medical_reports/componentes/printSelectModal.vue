<script setup>
import { ref, computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { t } from "@/utils/helper";

const props = defineProps({
     modelValue: Boolean,
     printMode: { type: String, default: "normal" }, // "normal", "background", "whatsapp", "whatsapp-bg", "download", "download-bg"
});
const emit = defineEmits(["update:modelValue", "print"]);

const invoicesStore = useinvoicesStore();
const { printRecord } = storeToRefs(invoicesStore);

const isWhatsapp = computed(() => props.printMode === "whatsapp" || props.printMode === "whatsapp-bg");
const isDownload = computed(() => props.printMode === "download" || props.printMode === "download-bg");

const selectedTests = ref([]);
const selectedCultures = ref([]);
const selectedPackages = ref([]);
const selectedTestGroups = ref([]);

// Select all state per section
const allTests = computed(() => printRecord.value?.tests?.length === selectedTests.value.length && selectedTests.value.length > 0);
const allCultures = computed(() => printRecord.value?.cultures?.length === selectedCultures.value.length && selectedCultures.value.length > 0);
const allPackages = computed(() => printRecord.value?.packages?.length === selectedPackages.value.length && selectedPackages.value.length > 0);
const allTestGroups = computed(() => printRecord.value?.test_groups?.length === selectedTestGroups.value.length && selectedTestGroups.value.length > 0);

const totalSelected = computed(() => selectedTests.value.length + selectedCultures.value.length + selectedPackages.value.length + selectedTestGroups.value.length);
const totalItems = computed(() =>
     (printRecord.value?.tests?.length || 0) +
     (printRecord.value?.cultures?.length || 0) +
     (printRecord.value?.packages?.length || 0) +
     (printRecord.value?.test_groups?.length || 0)
);

// When modal opens, select all by default
watch(() => props.modelValue, (val) => {
     if (val && printRecord.value) {
          selectedTests.value = printRecord.value.tests?.map((_, i) => i) || [];
          selectedCultures.value = printRecord.value.cultures?.map((_, i) => i) || [];
          selectedPackages.value = printRecord.value.packages?.map((_, i) => i) || [];
          selectedTestGroups.value = printRecord.value.test_groups?.map((_, i) => i) || [];
     }
});

const toggleAllTests = () => {
     if (allTests.value) selectedTests.value = [];
     else selectedTests.value = printRecord.value?.tests?.map((_, i) => i) || [];
};
const toggleAllCultures = () => {
     if (allCultures.value) selectedCultures.value = [];
     else selectedCultures.value = printRecord.value?.cultures?.map((_, i) => i) || [];
};
const toggleAllPackages = () => {
     if (allPackages.value) selectedPackages.value = [];
     else selectedPackages.value = printRecord.value?.packages?.map((_, i) => i) || [];
};
const toggleAllTestGroups = () => {
     if (allTestGroups.value) selectedTestGroups.value = [];
     else selectedTestGroups.value = printRecord.value?.test_groups?.map((_, i) => i) || [];
};

const selectAll = () => {
     selectedTests.value = printRecord.value?.tests?.map((_, i) => i) || [];
     selectedCultures.value = printRecord.value?.cultures?.map((_, i) => i) || [];
     selectedPackages.value = printRecord.value?.packages?.map((_, i) => i) || [];
     selectedTestGroups.value = printRecord.value?.test_groups?.map((_, i) => i) || [];
};

const deselectAll = () => {
     selectedTests.value = [];
     selectedCultures.value = [];
     selectedPackages.value = [];
     selectedTestGroups.value = [];
};

const close = () => {
     emit("update:modelValue", false);
};

const doPrint = () => {
     emit("print", {
          tests: selectedTests.value,
          cultures: selectedCultures.value,
          packages: selectedPackages.value,
          testGroups: selectedTestGroups.value,
          mode: props.printMode,
     });
     close();
};
</script>

<template>
     <Teleport to="body">
          <Transition
               enter-active-class="transition duration-200 ease-out"
               enter-from-class="opacity-0"
               enter-to-class="opacity-100"
               leave-active-class="transition duration-150 ease-in"
               leave-from-class="opacity-100"
               leave-to-class="opacity-0"
          >
               <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>

                    <!-- Modal -->
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden border border-slate-200">
                         <!-- Header -->
                         <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/50">
                              <div class="flex items-center gap-3">
                                   <div class="w-9 h-9 rounded-xl flex items-center justify-center" :class="isWhatsapp ? 'bg-green-100' : 'bg-primary-100'">
                                        <svg v-if="isWhatsapp" class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                             <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                        </svg>
                                        <svg v-else class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                   </div>
                                   <div>
                                        <h3 class="text-base font-bold text-slate-800">{{ isWhatsapp ? (t("select_whatsapp_items") || "Select Items to Send") : (isDownload ? (t("select_download_items") || "Select Items to Download") : (t("select_print_items") || "Select Print Items")) }}</h3>
                                        <p class="text-xs text-slate-400">{{ totalSelected }} / {{ totalItems }} {{ t("selected") || "selected" }}</p>
                                   </div>
                              </div>
                              <button @click="close" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                                   <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                   </svg>
                              </button>
                         </div>

                         <!-- Quick Actions -->
                         <div class="flex items-center gap-2 px-6 py-2.5 border-b border-slate-100 bg-white">
                              <button @click="selectAll" class="px-3 py-1.5 text-xs font-medium text-primary-600 bg-primary-50 hover:bg-primary-100 rounded-lg transition-colors">
                                   {{ t("select_all") || "Select All" }}
                              </button>
                              <button @click="deselectAll" class="px-3 py-1.5 text-xs font-medium text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                                   {{ t("deselect_all") || "Deselect All" }}
                              </button>
                         </div>

                         <!-- Content -->
                         <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">

                              <!-- Tests -->
                              <div v-if="printRecord?.tests?.length > 0">
                                   <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                             <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                                             <span class="text-sm font-semibold text-slate-700">{{ t("tests") }}</span>
                                             <span class="text-xs text-slate-400">({{ selectedTests.length }}/{{ printRecord.tests.length }})</span>
                                        </div>
                                        <button @click="toggleAllTests" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                                             {{ allTests ? (t("deselect_all") || "Deselect All") : (t("select_all") || "Select All") }}
                                        </button>
                                   </div>
                                   <div class="space-y-1">
                                        <label
                                             v-for="(test, idx) in printRecord.tests"
                                             :key="'t-' + idx"
                                             class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors"
                                             :class="selectedTests.includes(idx) ? 'bg-primary-50/50 border border-primary-200' : 'bg-slate-50 border border-transparent hover:bg-slate-100'"
                                        >
                                             <input type="checkbox" :value="idx" v-model="selectedTests" class="w-4 h-4 text-primary-600 rounded border-slate-300 focus:ring-primary-500" />
                                             <span class="text-sm text-slate-700 flex-1">{{ test.report_name || test.name }}</span>
                                             <span v-if="test.is_done" class="w-2 h-2 bg-green-500 rounded-full" :title="t('done')"></span>
                                        </label>
                                   </div>
                              </div>

                              <!-- Cultures -->
                              <div v-if="printRecord?.cultures?.length > 0">
                                   <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                             <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                                             <span class="text-sm font-semibold text-slate-700">{{ t("cultures") }}</span>
                                             <span class="text-xs text-slate-400">({{ selectedCultures.length }}/{{ printRecord.cultures.length }})</span>
                                        </div>
                                        <button @click="toggleAllCultures" class="text-xs text-rose-600 hover:text-rose-700 font-medium">
                                             {{ allCultures ? (t("deselect_all") || "Deselect All") : (t("select_all") || "Select All") }}
                                        </button>
                                   </div>
                                   <div class="space-y-1">
                                        <label
                                             v-for="(culture, idx) in printRecord.cultures"
                                             :key="'c-' + idx"
                                             class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors"
                                             :class="selectedCultures.includes(idx) ? 'bg-rose-50/50 border border-rose-200' : 'bg-slate-50 border border-transparent hover:bg-slate-100'"
                                        >
                                             <input type="checkbox" :value="idx" v-model="selectedCultures" class="w-4 h-4 text-rose-600 rounded border-slate-300 focus:ring-rose-500" />
                                             <span class="text-sm text-slate-700 flex-1">{{ culture.name }}</span>
                                             <span v-if="culture.is_done" class="w-2 h-2 bg-green-500 rounded-full" :title="t('done')"></span>
                                        </label>
                                   </div>
                              </div>

                              <!-- Packages -->
                              <div v-if="printRecord?.packages?.length > 0">
                                   <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                             <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                                             <span class="text-sm font-semibold text-slate-700">{{ t("packages") }}</span>
                                             <span class="text-xs text-slate-400">({{ selectedPackages.length }}/{{ printRecord.packages.length }})</span>
                                        </div>
                                        <button @click="toggleAllPackages" class="text-xs text-purple-600 hover:text-purple-700 font-medium">
                                             {{ allPackages ? (t("deselect_all") || "Deselect All") : (t("select_all") || "Select All") }}
                                        </button>
                                   </div>
                                   <div class="space-y-1">
                                        <label
                                             v-for="(pkg, idx) in printRecord.packages"
                                             :key="'p-' + idx"
                                             class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors"
                                             :class="selectedPackages.includes(idx) ? 'bg-purple-50/50 border border-purple-200' : 'bg-slate-50 border border-transparent hover:bg-slate-100'"
                                        >
                                             <input type="checkbox" :value="idx" v-model="selectedPackages" class="w-4 h-4 text-purple-600 rounded border-slate-300 focus:ring-purple-500" />
                                             <div class="flex-1">
                                                  <span class="text-sm text-slate-700">{{ pkg.name }}</span>
                                                  <span class="text-xs text-slate-400 ms-2">({{ (pkg.tests?.length || 0) + (pkg.cultures?.length || 0) }} {{ t("items") || "items" }})</span>
                                             </div>
                                        </label>
                                   </div>
                              </div>

                              <!-- Test Groups -->
                              <div v-if="printRecord?.test_groups?.length > 0">
                                   <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                             <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                             <span class="text-sm font-semibold text-slate-700">{{ t("test-groups") || "Test Groups" }}</span>
                                             <span class="text-xs text-slate-400">({{ selectedTestGroups.length }}/{{ printRecord.test_groups.length }})</span>
                                        </div>
                                        <button @click="toggleAllTestGroups" class="text-xs text-amber-600 hover:text-amber-700 font-medium">
                                             {{ allTestGroups ? (t("deselect_all") || "Deselect All") : (t("select_all") || "Select All") }}
                                        </button>
                                   </div>
                                   <div class="space-y-1">
                                        <label
                                             v-for="(group, idx) in printRecord.test_groups"
                                             :key="'g-' + idx"
                                             class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors"
                                             :class="selectedTestGroups.includes(idx) ? 'bg-amber-50/50 border border-amber-200' : 'bg-slate-50 border border-transparent hover:bg-slate-100'"
                                        >
                                             <input type="checkbox" :value="idx" v-model="selectedTestGroups" class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-500" />
                                             <div class="flex-1">
                                                  <span class="text-sm text-slate-700">{{ group.group_name || group.name }}</span>
                                                  <span class="text-xs text-slate-400 ms-2">({{ (group.tests?.length || 0) + (group.cultures?.length || 0) }} {{ t("items") || "items" }})</span>
                                             </div>
                                        </label>
                                   </div>
                              </div>

                         </div>

                         <!-- Footer -->
                         <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50/50">
                              <p class="text-xs text-slate-400">{{ totalSelected }} {{ t("items") || "items" }} {{ t("selected") || "selected" }}</p>
                              <div class="flex items-center gap-2">
                                   <button @click="close" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg transition-colors">
                                        {{ t("cancel") }}
                                   </button>
                                   <button
                                        @click="doPrint"
                                        :disabled="totalSelected === 0"
                                        class="px-5 py-2 text-sm font-semibold text-white rounded-lg transition-colors shadow-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="isWhatsapp ? 'bg-green-600 hover:bg-green-700' : 'bg-primary-600 hover:bg-primary-700'"
                                   >
                                        <svg v-if="isWhatsapp" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                             <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                        {{ isWhatsapp ? (t("send") || "Send") : (isDownload ? (t("download") || "Download") : (t("print") || "Print")) }}
                                   </button>
                              </div>
                         </div>
                    </div>
               </div>
          </Transition>
     </Teleport>
</template>
