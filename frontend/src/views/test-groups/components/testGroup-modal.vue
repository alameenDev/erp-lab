<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { useCategoriesStore } from "@/store/modules/categories";
import { usesamplesStore } from "@/store/modules/samples";
import { useDurationUnitsStore } from "@/store/modules/durationUnits";
import { usetestsStore } from "@/store/modules/tests";
import { useculturesStore } from "@/store/modules/cultures";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";
import FormulaBuilder from "@/components/FormulaBuilder.vue";

const testGroupsStore = usetestGroupsStore();
const categoriesStore = useCategoriesStore();
const samplesStore = usesamplesStore();
const durationUnitsStore = useDurationUnitsStore();
const testsStore = usetestsStore();
const culturesStore = useculturesStore();

const { record, dialog } = storeToRefs(testGroupsStore);
const { categories } = storeToRefs(categoriesStore);
const { samples } = storeToRefs(samplesStore);
const { durationUnitsList } = storeToRefs(durationUnitsStore);
const { tests } = storeToRefs(testsStore);
const { cultures } = storeToRefs(culturesStore);

const { AddtestGroups, UpdatetestGroups } = testGroupsStore;
const { Getcategories } = categoriesStore;
const { Getsamples } = samplesStore;
const { GetdurationUnits } = durationUnitsStore;
const { GetTests } = testsStore;
const { Getcultures } = culturesStore;

const testsSearchQuery = ref("");
const culturesSearchQuery = ref("");
const discountAmount = ref(0);
const _updatingDiscount = ref(false);

const updateCustomerFromDiscount = () => {
  _updatingDiscount.value = true;
  const orig = parseFloat(record.value.original_price) || 0;
  record.value.for_customer_price = Math.max(0, orig - (parseFloat(discountAmount.value) || 0));
  _updatingDiscount.value = false;
};

const updateDiscountFromPrices = () => {
  if (_updatingDiscount.value) return;
  const orig = parseFloat(record.value.original_price) || 0;
  const cust = parseFloat(record.value.for_customer_price) || 0;
  discountAmount.value = Math.max(0, orig - cust);
};

watch(() => record.value.original_price, updateDiscountFromPrices);
watch(() => record.value.for_customer_price, updateDiscountFromPrices);

// On record load (edit mode), heal broken legacy data: if original_price > 0
// but for_customer_price is 0/null/missing, treat it as "no discount" rather
// than "100% discount". Old groups created before the auto-sum logic could
// end up with customer=0 even though no discount was intended.
watch(() => record.value.id, (newId) => {
  if (!newId) {
    // Create mode — reset
    discountAmount.value = 0;
    return;
  }
  const orig = parseFloat(record.value.original_price) || 0;
  const custRaw = record.value.for_customer_price;
  const cust = parseFloat(custRaw);
  if (orig > 0 && (custRaw == null || custRaw === "" || isNaN(cust) || cust === 0)) {
    _updatingDiscount.value = true;
    record.value.for_customer_price = orig;
    discountAmount.value = 0;
    _updatingDiscount.value = false;
    return;
  }
  updateDiscountFromPrices();
});

onMounted(() => {
  Getcategories();
  Getsamples();
  GetdurationUnits();
  GetTests();
  Getcultures();
});

const categoriesList = computed(() => categories.value?.() || categories.value || []);
const samplesList = computed(() => samples.value?.() || samples.value || []);
const testsList = computed(() => {
  const list = testsStore.testGroupTestes?.() || tests.value || [];
  if (!testsSearchQuery.value) return list;
  const q = testsSearchQuery.value.toLowerCase();
  return list.filter(t => t.label?.toLowerCase().includes(q) || t.shortcut?.toLowerCase().includes(q));
});
const culturesList = computed(() => {
  const list = culturesStore.cultureGroupTestes?.() || cultures.value || [];
  if (!culturesSearchQuery.value) return list;
  return list.filter(c => c.label?.toLowerCase().includes(culturesSearchQuery.value.toLowerCase()));
});

// Map over the user's selection array (preserves click order) instead of
// filtering the master testsList (which is in DB id / alphabetical order).
// Otherwise picking [B, C, A] would render as [A, B, C].
const selectedTests = computed(() => {
  const ids = record.value?.test_ids || [];
  if (!ids.length) return [];
  return ids
    .map(id => testsList.value?.find(t => t.value === id))
    .filter(Boolean);
});

const selectedCultures = computed(() => {
  const ids = record.value?.culture_ids || [];
  if (!ids.length) return [];
  return ids
    .map(id => culturesList.value?.find(c => c.value === id))
    .filter(Boolean);
});

// Auto-sum selected test + culture customer-facing prices into original_price.
// IMPORTANT: tests have BOTH `price` (wholesale/B2B/cost) and `for_customer_price`
// (retail/patient-facing). For 100% of seeded tests, for_customer_price > price.
// Cultures use `price_for_customer` (different column name from tests).
// We sum the RETAIL price so the group total matches what the invoice modal
// shows (resolveItemPrice prefers for_customer_price too). Summing wholesale
// here would create a discrepancy where the test-groups page shows one number
// and the invoice page shows a higher one.
const customerPriceOf = (item) => {
  if (!item) return 0;
  // Try retail first (works for both tests and cultures), fall back to wholesale
  const retail = item.for_customer_price ?? item.price_for_customer;
  if (retail != null && retail !== "" && Number(retail) > 0) return Number(retail);
  return parseFloat(item.price) || 0;
};
const totalTestsPrice = computed(() => {
  let sum = 0;
  const tIds = Array.isArray(record.value.test_ids) ? record.value.test_ids : [];
  const cIds = Array.isArray(record.value.culture_ids) ? record.value.culture_ids : [];
  tIds.forEach(id => {
    const t = testsList.value?.find(x => x.value === id);
    if (t) sum += customerPriceOf(t);
  });
  cIds.forEach(id => {
    const c = culturesList.value?.find(x => x.value === id);
    if (c) sum += customerPriceOf(c);
  });
  return sum;
});

// When tests/cultures change, auto-update both prices in BOTH create and edit
// modes — children are the source of truth. The discount the user already
// entered is preserved (customer = original − discount). This is consistent
// with the invoice-modal fix where the displayed group price is always the
// sum of its children, so the test-group's saved price should match.
watch(totalTestsPrice, (val) => {
  if (val <= 0) return;
  _updatingDiscount.value = true;
  record.value.original_price = val;
  const d = parseFloat(discountAmount.value) || 0;
  record.value.for_customer_price = Math.max(0, val - d);
  _updatingDiscount.value = false;
});

const toggleTest = (testId) => {
  if (!record.value) return;
  if (!record.value.test_ids) record.value.test_ids = [];
  const index = record.value.test_ids.indexOf(testId);
  if (index === -1) {
    record.value.test_ids.push(testId);
  } else {
    record.value.test_ids.splice(index, 1);
  }
};

const toggleCulture = (cultureId) => {
  if (!record.value) return;
  if (!record.value.culture_ids) record.value.culture_ids = [];
  const index = record.value.culture_ids.indexOf(cultureId);
  if (index === -1) {
    record.value.culture_ids.push(cultureId);
  } else {
    record.value.culture_ids.splice(index, 1);
  }
};

const addComment = () => {
  if (!record.value.result_comments) record.value.result_comments = [];
  record.value.result_comments.push("");
};

const removeComment = (index) => {
  record.value.result_comments.splice(index, 1);
};

const onTestsScroll = () => {
  // All tests are loaded at once, no need for infinite scroll
};

const cleanBeforeSend = () => {
  record.value.is_print_alone = record.value.is_print_alone ? 1 : 0;
  record.value.test_ids = (record.value.test_ids || []).filter(id => id !== "" && id != null);
  record.value.culture_ids = (record.value.culture_ids || []).filter(id => id !== "" && id != null);
  record.value.result_comments = (record.value.result_comments || []).filter(c => c !== "" && c != null);
  record.value.original_price = record.value.original_price || null;
  record.value.category_id_fk = record.value.category_id_fk || null;
  record.value.sample_id_fk = record.value.sample_id_fk || null;
  record.value.duration_unit_id_fk = record.value.duration_unit_id_fk || null;
};

const create = () => {
  cleanBeforeSend();
  AddtestGroups().then(() => {
    alertSuccess(t("alertSuccess"));
    close();
  });
};

const update = () => {
  cleanBeforeSend();
  UpdatetestGroups().then(() => {
    alertSuccess(t("alertSuccess"));
    close();
  });
};

const close = () => {
  clearObjectValues(record.value);
  record.value.result_comments = [""];
  record.value.test_ids = [];
  record.value.culture_ids = [];
  discountAmount.value = 0;
  dialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-slate-50 rounded-2xl shadow-2xl w-full max-w-6xl my-8 overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ record?.id ? t("update") : t("add") }} {{ t("test-groups") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("test_group_info_desc") }}</p>
                </div>
              </div>
              <button @click="close" class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-all">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <form @submit.prevent="record.id ? update() : create()">
            <div class="p-6 space-y-6">
              <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                <!-- ==================== LEFT COLUMN (8 cols) ==================== -->
                <div class="xl:col-span-8 space-y-6">
                  <!-- Basic Information Card -->
                  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
                      <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                        <div class="flex-1">
                          <h3 class="font-semibold text-slate-800">{{ t("test_group_basic_info") }}</h3>
                          <p class="text-sm text-slate-500">{{ t("test_group_basic_info_desc") }}</p>
                        </div>
                        <span class="px-3 py-1 bg-primary-100 text-primary-700 text-xs font-semibold rounded-full">{{ t("step") }} 1</span>
                      </div>
                    </div>
                    <div class="p-6">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Group Name -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Group_Name") }} <span class="text-red-500">*</span></label>
                          <input
                            v-model="record.group_name"
                            type="text"
                            required
                            maxlength="255"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                            :placeholder="t('Group_Name')"
                          />
                        </div>

                        <!-- Shortcut -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Shortcut") }} <span class="text-red-500">*</span></label>
                          <input
                            v-model="record.shortcut"
                            type="text"
                            required
                            maxlength="255"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                            :placeholder="t('Shortcut')"
                          />
                        </div>

                        <!-- Category -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Category") }}</label>
                          <select
                            v-model="record.category_id_fk"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                          >
                            <option value="">{{ t("select") }}</option>
                            <option v-for="cat in categoriesList" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                          </select>
                        </div>

                        <!-- Sample -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Sample") }}</label>
                          <select
                            v-model="record.sample_id_fk"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                          >
                            <option value="">{{ t("select") }}</option>
                            <option v-for="sample in samplesList" :key="sample.value" :value="sample.value">{{ sample.label }}</option>
                          </select>
                        </div>

                        <!-- Test Duration -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Test_Duration") }} <span class="text-red-500">*</span></label>
                          <input
                            v-model="record.test_duration"
                            type="number"
                            required
                            min="0"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                            :placeholder="t('Test_Duration')"
                          />
                        </div>

                        <!-- Duration Unit -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Duration_Unit") }}</label>
                          <select
                            v-model="record.duration_unit_id_fk"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                          >
                            <option value="">{{ t("select") }}</option>
                            <option v-for="unit in durationUnitsList" :key="unit.value" :value="unit.value">{{ unit.label }}</option>
                          </select>
                        </div>

                        <!-- Precautions -->
                        <div class="md:col-span-2">
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Precautions") }}</label>
                          <input
                            v-model="record.precautions"
                            type="text"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                            :placeholder="t('Precautions')"
                          />
                        </div>

                        <!-- Print Alone Checkbox -->
                        <div class="md:col-span-2">
                          <label class="flex items-center gap-3 cursor-pointer p-3 bg-slate-50 rounded-xl border border-slate-200 hover:bg-slate-100 transition-all">
                            <input
                              type="checkbox"
                              v-model="record.is_print_alone"
                              class="w-5 h-5 text-primary-600 border-slate-300 rounded focus:ring-primary-500"
                            />
                            <span class="text-sm font-medium text-slate-700">{{ t("Print_Alone") }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Tests & Cultures Selection Card -->
                  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                      <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                          </svg>
                        </div>
                        <div class="flex-1">
                          <h3 class="font-semibold text-slate-800">{{ t("tests_and_cultures") }}</h3>
                          <p class="text-sm text-slate-500">{{ t("tests_and_cultures_desc") }}</p>
                        </div>
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">{{ t("step") }} 2</span>
                      </div>
                    </div>
                    <div class="p-6">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tests Selection -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("tests") }}</label>
                          <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <!-- Search -->
                            <div class="p-3 border-b border-slate-100 bg-slate-50">
                              <div class="relative">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                  </svg>
                                </span>
                                <input
                                  v-model="testsSearchQuery"
                                  type="text"
                                  :placeholder="t('search') + '...'"
                                  class="w-full ps-9 pe-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                                />
                              </div>
                            </div>
                            <!-- Selected Tests -->
                            <div v-if="selectedTests.length > 0" class="p-3 border-b border-slate-100 bg-primary-50/50">
                              <div class="flex flex-wrap gap-2">
                                <span
                                  v-for="test in selectedTests"
                                  :key="test.value"
                                  class="inline-flex items-center gap-1 px-2.5 py-1 bg-primary-100 text-primary-700 text-xs font-medium rounded-lg"
                                >
                                  {{ test.label }}
                                  <button type="button" @click="toggleTest(test.value)" class="hover:text-primary-900">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                  </button>
                                </span>
                              </div>
                            </div>
                            <!-- Tests List -->
                            <div class="max-h-48 overflow-y-auto" @scroll="onTestsScroll">
                              <label
                                v-for="test in testsList"
                                :key="test.value"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors"
                              >
                                <input
                                  type="checkbox"
                                  :checked="record.test_ids?.includes(test.value)"
                                  @change="toggleTest(test.value)"
                                  class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500"
                                />
                                <div class="flex-1 flex items-center gap-2 flex-wrap">
                                  <span class="text-sm text-slate-700">{{ test.label }}</span>
                                  <span v-if="test.shortcut" class="text-xs text-slate-500 font-mono">({{ test.shortcut }})</span>
                                  <span v-if="test.is_special" class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-semibold rounded-full">تحاليل مخصصة</span>
                                </div>
                              </label>
                              <div v-if="testsList.length === 0" class="p-4 text-center text-slate-500 text-sm">
                                {{ t("noData") }}
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Cultures Selection -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("cultures") }}</label>
                          <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <!-- Search -->
                            <div class="p-3 border-b border-slate-100 bg-slate-50">
                              <div class="relative">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                  </svg>
                                </span>
                                <input
                                  v-model="culturesSearchQuery"
                                  type="text"
                                  :placeholder="t('search') + '...'"
                                  class="w-full ps-9 pe-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"
                                />
                              </div>
                            </div>
                            <!-- Selected Cultures -->
                            <div v-if="selectedCultures.length > 0" class="p-3 border-b border-slate-100 bg-purple-50/50">
                              <div class="flex flex-wrap gap-2">
                                <span
                                  v-for="culture in selectedCultures"
                                  :key="culture.value"
                                  class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-100 text-purple-700 text-xs font-medium rounded-lg"
                                >
                                  {{ culture.label }}
                                  <button type="button" @click="toggleCulture(culture.value)" class="hover:text-purple-900">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                  </button>
                                </span>
                              </div>
                            </div>
                            <!-- Cultures List -->
                            <div class="max-h-48 overflow-y-auto">
                              <label
                                v-for="culture in culturesList"
                                :key="culture.value"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors"
                              >
                                <input
                                  type="checkbox"
                                  :checked="record.culture_ids?.includes(culture.value)"
                                  @change="toggleCulture(culture.value)"
                                  class="w-4 h-4 text-purple-600 border-slate-300 rounded focus:ring-purple-500"
                                />
                                <span class="text-sm text-slate-700">{{ culture.label }}</span>
                              </label>
                              <div v-if="culturesList.length === 0" class="p-4 text-center text-slate-500 text-sm">
                                {{ t("noData") }}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Formula Card -->
                  <FormulaBuilder v-model="record.formula" :variables="selectedTests.map(t => ({ key: t.shortcut || t.label, label: t.label }))" />

                  <!-- Result Comments Card -->
                  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50">
                      <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
                          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                          </svg>
                        </div>
                        <div class="flex-1">
                          <h3 class="font-semibold text-slate-800">{{ t("Result_Comments") }}</h3>
                          <p class="text-sm text-slate-500">{{ t("result_comments_desc") }}</p>
                        </div>
                        <span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">{{ t("step") }} 3</span>
                      </div>
                    </div>
                    <div class="p-6">
                      <div v-if="!record.result_comments?.length" class="text-center py-6 text-slate-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        <p>{{ t("no_comments") }}</p>
                      </div>
                      <div v-else class="space-y-3">
                        <div
                          v-for="(comment, index) in record.result_comments"
                          :key="index"
                          class="flex items-center gap-3"
                        >
                          <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                            {{ index + 1 }}
                          </span>
                          <input
                            v-model="record.result_comments[index]"
                            type="text"
                            maxlength="255"
                            class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                            :placeholder="t('Result_Comments') + ' ' + (index + 1)"
                          />
                          <button
                            type="button"
                            @click="removeComment(index)"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all"
                          >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </div>
                      </div>
                      <button
                        type="button"
                        @click="addComment"
                        class="mt-4 w-full py-2.5 border-2 border-dashed border-slate-300 rounded-xl text-slate-600 hover:border-primary-400 hover:text-primary-600 hover:bg-primary-50/50 transition-all flex items-center justify-center gap-2"
                      >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t("add") }} {{ t("Result_Comments") }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- ==================== RIGHT COLUMN (4 cols) ==================== -->
                <div class="xl:col-span-4">
                  <div class="xl:sticky xl:top-6 space-y-6">
                    <!-- Pricing Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                      <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-green-50 to-emerald-50">
                        <div class="flex items-center gap-3">
                          <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/25">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                          </div>
                          <div>
                            <h3 class="font-semibold text-slate-800">{{ t("pricing") }}</h3>
                            <p class="text-sm text-slate-500">{{ t("pricing_desc") }}</p>
                          </div>
                        </div>
                      </div>
                      <div class="p-6 space-y-4">
                        <!-- B2B / Wholesale Price -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Original_Price") }}</label>
                          <div class="relative">
                            <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400 font-medium text-sm">IQD</span>
                            <input
                              v-model="record.original_price"
                              type="number"
                              min="0"
                              class="w-full ps-14 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                              placeholder="0"
                            />
                          </div>
                        </div>

                        <!-- Discount -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("it_discount") || "الخصم" }}</label>
                          <div class="relative">
                            <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400 font-medium text-sm">IQD</span>
                            <input
                              v-model.number="discountAmount"
                              @input="updateCustomerFromDiscount()"
                              type="number"
                              min="0"
                              class="w-full ps-14 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                              placeholder="0"
                            />
                          </div>
                        </div>

                        <!-- Customer Price -->
                        <div>
                          <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Customer_Price") }} <span class="text-red-500">*</span></label>
                          <div class="relative">
                            <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400 font-medium text-sm">IQD</span>
                            <input
                              v-model="record.for_customer_price"
                              type="number"
                              required
                              min="0"
                              class="w-full ps-14 pe-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                              placeholder="0"
                            />
                          </div>
                        </div>

                        <!-- Price Summary -->
                        <div v-if="record.original_price > 0" class="pt-3 border-t border-slate-100 space-y-2">
                          <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ t("Original_Price") }}</span>
                            <span class="font-medium text-slate-700">{{ Number(record.original_price || 0).toLocaleString() }} IQD</span>
                          </div>
                          <div v-if="discountAmount > 0" class="flex justify-between text-sm">
                            <span class="text-red-500">{{ t("it_discount") || "الخصم" }}</span>
                            <span class="font-medium text-red-500">- {{ Number(discountAmount).toLocaleString() }} IQD</span>
                          </div>
                          <div class="flex justify-between text-sm pt-2 border-t border-dashed border-slate-200">
                            <span class="font-semibold text-slate-700">{{ t("Customer_Price") }}</span>
                            <span class="font-bold text-primary-600 text-base">{{ Number(record.for_customer_price || 0).toLocaleString() }} IQD</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Test Group Comment Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                      <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-purple-50 to-violet-50">
                        <div class="flex items-center gap-3">
                          <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                          </div>
                          <div>
                            <h3 class="font-semibold text-slate-800">{{ t("Test_Group_Comment") }}</h3>
                            <p class="text-sm text-slate-500">{{ t("test_group_comment_desc") }}</p>
                          </div>
                        </div>
                      </div>
                      <div class="p-6">
                        <textarea
                          v-model="record.test_group_comment"
                          rows="4"
                          maxlength="255"
                          class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all resize-none"
                          :placeholder="t('Test_Group_Comment')"
                        ></textarea>
                      </div>
                    </div>

                    <!-- Action Buttons Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                      <div class="p-6 space-y-3">
                        <button
                          type="submit"
                          class="w-full py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-semibold rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2"
                        >
                          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          </svg>
                          {{ record?.id ? t("save") : t("add") }}
                        </button>
                        <button
                          type="button"
                          @click="close"
                          class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition-all flex items-center justify-center gap-2"
                        >
                          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                          {{ t("cancel") }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
