<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum as commaFormat } from "@/utils/helper";

const visible = defineModel({ type: Boolean, default: false });

const store = useSuperAdminStore();
const { reportDetail } = storeToRefs(store);

const tests = computed(() => reportDetail.value?.tests || []);
const cultures = computed(() => reportDetail.value?.cultures || []);
const packages = computed(() => reportDetail.value?.packages || []);
const testGroups = computed(() => reportDetail.value?.test_groups || []);

const patientName = computed(() => reportDetail.value?.patient?.name || "");
const barcode = computed(() => reportDetail.value?.barcode || "");
</script>

<template>
     <UiModal v-model="visible" :title="t('medical_reports') + ' - ' + patientName" size="2xl">
          <div v-if="!reportDetail" class="py-12 text-center text-gray-400">
               {{ t("loading") }}...
          </div>

          <div v-else class="space-y-6">
               <!-- Invoice Info -->
               <div class="bg-gray-50 rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                         <div>
                              <span class="text-gray-500">{{ t("Barcode") }}</span>
                              <p class="font-semibold text-gray-900 font-mono">{{ barcode }}</p>
                         </div>
                         <div>
                              <span class="text-gray-500">{{ t("Registration_date") }}</span>
                              <p class="font-semibold text-gray-900">{{ reportDetail.registration_date?.split("T")[0] }}</p>
                         </div>
                         <div>
                              <span class="text-gray-500">{{ t("lab/bruanch") }}</span>
                              <p class="font-semibold text-gray-900">{{ reportDetail.lab }}</p>
                         </div>
                         <div>
                              <span class="text-gray-500">{{ t("theStatus") }}</span>
                              <span
                                   class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                   :class="reportDetail.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                              >
                                   {{ reportDetail.is_done ? t("done") : t("pendening") }}
                              </span>
                         </div>
                    </div>
               </div>

               <!-- Tests -->
               <div v-if="tests.length">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                         <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                              <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                              </svg>
                         </div>
                         {{ t("tests") }} ({{ tests.length }})
                    </h3>
                    <div class="overflow-x-auto border border-gray-100 rounded-xl">
                         <table class="w-full text-sm">
                              <thead class="bg-gray-50 border-b border-gray-100">
                                   <tr>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("test_name") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("Result") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("normal_range") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("theStatus") }}</th>
                                   </tr>
                              </thead>
                              <tbody class="divide-y divide-gray-50">
                                   <template v-for="test in tests" :key="test.id">
                                        <!-- Main test row -->
                                        <tr class="hover:bg-gray-50/50">
                                             <td class="px-4 py-2.5 font-medium text-gray-900">{{ test.report_name || test.name }}</td>
                                             <td class="px-4 py-2.5 text-gray-700">{{ test.result || "-" }}</td>
                                             <td class="px-4 py-2.5 text-gray-500 text-xs">
                                                  <template v-if="test.test_reference_ranges?.length">
                                                       <span v-for="(range, ri) in test.test_reference_ranges" :key="ri">
                                                            {{ range.min_value }} - {{ range.max_value }} {{ range.unit || "" }}
                                                            <br v-if="ri < test.test_reference_ranges.length - 1" />
                                                       </span>
                                                  </template>
                                                  <template v-else>-</template>
                                             </td>
                                             <td class="px-4 py-2.5">
                                                  <span
                                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                       :class="test.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                                  >
                                                       {{ test.is_done ? t("done") : t("pendening") }}
                                                  </span>
                                             </td>
                                        </tr>
                                        <!-- Sub-tests -->
                                        <tr v-for="sub in (test.sub_tests || [])" :key="'sub-' + sub.name" class="bg-gray-50/30">
                                             <td class="px-4 py-2 ps-8 text-gray-600 text-xs">{{ sub.report_name || sub.name }}</td>
                                             <td class="px-4 py-2 text-gray-700 text-xs">{{ sub.result || "-" }}</td>
                                             <td class="px-4 py-2 text-gray-500 text-xs">
                                                  <template v-if="sub.test_reference_ranges?.length">
                                                       <span v-for="(range, ri) in sub.test_reference_ranges" :key="ri">
                                                            {{ range.min_value }} - {{ range.max_value }} {{ range.unit || "" }}
                                                       </span>
                                                  </template>
                                                  <template v-else>-</template>
                                             </td>
                                             <td class="px-4 py-2">
                                                  <span
                                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                       :class="sub.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                                  >
                                                       {{ sub.is_done ? t("done") : t("pendening") }}
                                                  </span>
                                             </td>
                                        </tr>
                                   </template>
                              </tbody>
                         </table>
                    </div>
               </div>

               <!-- Cultures -->
               <div v-if="cultures.length">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                         <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                              <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                              </svg>
                         </div>
                         {{ t("cultures") }} ({{ cultures.length }})
                    </h3>
                    <div class="overflow-x-auto border border-gray-100 rounded-xl">
                         <table class="w-full text-sm">
                              <thead class="bg-gray-50 border-b border-gray-100">
                                   <tr>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("name") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("Result") }}</th>
                                        <th class="text-start px-4 py-2.5 font-medium text-gray-600">{{ t("theStatus") }}</th>
                                   </tr>
                              </thead>
                              <tbody class="divide-y divide-gray-50">
                                   <tr v-for="culture in cultures" :key="culture.id" class="hover:bg-gray-50/50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ culture.name }}</td>
                                        <td class="px-4 py-2.5 text-gray-700">{{ culture.result || "-" }}</td>
                                        <td class="px-4 py-2.5">
                                             <span
                                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  :class="culture.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                             >
                                                  {{ culture.is_done ? t("done") : t("pendening") }}
                                             </span>
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
               </div>

               <!-- Packages -->
               <div v-if="packages.length">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                         <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                              <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                              </svg>
                         </div>
                         {{ t("packages") }} ({{ packages.length }})
                    </h3>
                    <div v-for="pkg in packages" :key="pkg.id" class="border border-gray-100 rounded-xl mb-3 overflow-hidden">
                         <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                              <span class="font-semibold text-gray-800">{{ pkg.name }}</span>
                              <span
                                   class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                   :class="pkg.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                              >
                                   {{ pkg.is_done ? t("done") : t("pendening") }}
                              </span>
                         </div>
                         <div v-if="pkg.tests?.length" class="overflow-x-auto">
                              <table class="w-full text-sm">
                                   <thead class="bg-gray-50/50 border-b border-gray-100">
                                        <tr>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("test_name") }}</th>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("Result") }}</th>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("normal_range") }}</th>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("theStatus") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody class="divide-y divide-gray-50">
                                        <tr v-for="pTest in pkg.tests" :key="pTest.id" class="hover:bg-gray-50/50">
                                             <td class="px-4 py-2 text-gray-900">{{ pTest.report_name || pTest.name }}</td>
                                             <td class="px-4 py-2 text-gray-700">{{ pTest.result || "-" }}</td>
                                             <td class="px-4 py-2 text-gray-500 text-xs">
                                                  <template v-if="pTest.test_reference_ranges?.length">
                                                       <span v-for="(range, ri) in pTest.test_reference_ranges" :key="ri">
                                                            {{ range.min_value }} - {{ range.max_value }} {{ range.unit || "" }}
                                                       </span>
                                                  </template>
                                                  <template v-else>-</template>
                                             </td>
                                             <td class="px-4 py-2">
                                                  <span
                                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                       :class="pTest.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                                  >
                                                       {{ pTest.is_done ? t("done") : t("pendening") }}
                                                  </span>
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                         </div>
                    </div>
               </div>

               <!-- Test Groups -->
               <div v-if="testGroups.length">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                         <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                              <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                              </svg>
                         </div>
                         {{ t("test_groups") }} ({{ testGroups.length }})
                    </h3>
                    <div v-for="group in testGroups" :key="group.id" class="border border-gray-100 rounded-xl mb-3 overflow-hidden">
                         <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                              <span class="font-semibold text-gray-800">{{ group.group_name }}</span>
                              <span
                                   class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                   :class="group.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                              >
                                   {{ group.is_done ? t("done") : t("pendening") }}
                              </span>
                         </div>
                         <div v-if="group.tests?.length" class="overflow-x-auto">
                              <table class="w-full text-sm">
                                   <thead class="bg-gray-50/50 border-b border-gray-100">
                                        <tr>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("test_name") }}</th>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("Result") }}</th>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("normal_range") }}</th>
                                             <th class="text-start px-4 py-2 font-medium text-gray-600">{{ t("theStatus") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody class="divide-y divide-gray-50">
                                        <tr v-for="gTest in group.tests" :key="gTest.id" class="hover:bg-gray-50/50">
                                             <td class="px-4 py-2 text-gray-900">{{ gTest.report_name || gTest.name }}</td>
                                             <td class="px-4 py-2 text-gray-700">{{ gTest.result || "-" }}</td>
                                             <td class="px-4 py-2 text-gray-500 text-xs">
                                                  <template v-if="gTest.test_reference_ranges?.length">
                                                       <span v-for="(range, ri) in gTest.test_reference_ranges" :key="ri">
                                                            {{ range.min_value }} - {{ range.max_value }} {{ range.unit || "" }}
                                                       </span>
                                                  </template>
                                                  <template v-else>-</template>
                                             </td>
                                             <td class="px-4 py-2">
                                                  <span
                                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                       :class="gTest.is_done ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                                  >
                                                       {{ gTest.is_done ? t("done") : t("pendening") }}
                                                  </span>
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                         </div>
                    </div>
               </div>

               <!-- No data -->
               <div v-if="!tests.length && !cultures.length && !packages.length && !testGroups.length" class="py-8 text-center text-gray-400">
                    {{ t("no_data") }}
               </div>
          </div>
     </UiModal>
</template>
