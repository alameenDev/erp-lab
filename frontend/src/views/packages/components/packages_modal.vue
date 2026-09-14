<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usePackagesStore } from "@/store/modules/packages";
import { usetestsStore } from "@/store/modules/tests";
import { useculturesStore } from "@/store/modules/cultures";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";
import FormulaBuilder from "@/components/FormulaBuilder.vue";

const packagesStore = usePackagesStore();
const testsStore = usetestsStore();
const culturesStore = useculturesStore();
const testGroupsStore = usetestGroupsStore();
const { testGroups } = storeToRefs(testGroupsStore);

const { record, dialog } = storeToRefs(packagesStore);
const { Addpackage, Updatepackage } = packagesStore;
const { TestLists } = testsStore;
const { GetTests } = testsStore;
const { cultureLists } = culturesStore;
const { Getcultures } = culturesStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const testsDropdownOpen = ref(false);
const culturesDropdownOpen = ref(false);
const groupsDropdownOpen = ref(false);
const testsSearch = ref("");
const culturesSearch = ref("");
const groupsSearch = ref("");

onMounted(() => {
  GetTests();
  Getcultures();
  testGroupsStore.GettestGroups();
});

// Whole test groups selectable into a package (alongside individual tests)
const filteredGroups = computed(() => {
  const groups = testGroups.value || [];
  if (!groupsSearch.value) return groups;
  const q = groupsSearch.value.toLowerCase();
  return groups.filter(
    (g) => g.group_name?.toLowerCase().includes(q) || g.shortcut?.toLowerCase().includes(q)
  );
});

const isGroupSelected = (group) => record.value.test_groups?.some((g) => g.id === group.id);

const toggleGroup = (group) => {
  if (!record.value.test_groups) record.value.test_groups = [];
  const index = record.value.test_groups.findIndex((g) => g.id === group.id);
  if (index > -1) record.value.test_groups.splice(index, 1);
  else record.value.test_groups.push({ id: group.id, group_name: group.group_name, tests: group.tests });
};

const filteredTests = computed(() => {
  const tests = TestLists() || [];
  if (!testsSearch.value) return tests;
  const q = testsSearch.value.toLowerCase();
  return tests.filter(
    (t) => t.label?.toLowerCase().includes(q) || t.shortcut?.toLowerCase().includes(q)
  );
});

const filteredCultures = computed(() => {
  const cultures = cultureLists() || [];
  if (!culturesSearch.value) return cultures;
  return cultures.filter((c) =>
    c.label?.toLowerCase().includes(culturesSearch.value.toLowerCase())
  );
});

const isTestSelected = (test) => {
  return record.value.tests?.some((t) => t.id === test.value?.id || t === test.value);
};

const isCultureSelected = (culture) => {
  return record.value.cultures?.some((c) => c.id === culture.value?.id || c === culture.value);
};

const toggleTest = (test) => {
  if (!record.value.tests) record.value.tests = [];
  const index = record.value.tests.findIndex((t) => t.id === test.value?.id || t === test.value);
  if (index > -1) {
    record.value.tests.splice(index, 1);
  } else {
    record.value.tests.push(test.value);
  }
};

const toggleCulture = (culture) => {
  if (!record.value.cultures) record.value.cultures = [];
  const index = record.value.cultures.findIndex((c) => c.id === culture.value?.id || c === culture.value);
  if (index > -1) {
    record.value.cultures.splice(index, 1);
  } else {
    record.value.cultures.push(culture.value);
  }
};

const handleSubmit = () => {
  record.value.is_test = record.value.is_test === true;
  record.value.is_constant_price = record.value.is_constant_price === true;

  if (record.value.id) {
    Updatepackage().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      dialog.value = false;
    });
  } else {
    Addpackage().then(() => {
      alertSuccess(t("alertSuccess"));
      dialog.value = false;
    });
  }
};

const close = () => {
  dialog.value = false;
  clearObjectValues(record.value);
};

const onTestsScroll = () => {
  // All tests are loaded at once, no need for infinite scroll
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ record?.id ? t("update") : t("add") }} {{ t("Package") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("package_info_desc") }}</p>
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
          <form @submit.prevent="handleSubmit">
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)] space-y-6">
              <!-- Basic Information Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("package_basic_info") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("package_basic_info_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Name -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("name") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="record.name"
                        type="text"
                        required
                        maxlength="255"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                        :placeholder="t('package_name_placeholder')"
                      />
                    </div>
                    <!-- Shortcut -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Shortcut") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="record.shortcut"
                        type="text"
                        required
                        maxlength="255"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                        :placeholder="t('package_shortcut_placeholder')"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tests & Cultures Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("tests_and_cultures") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("tests_and_cultures_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tests MultiSelect -->
                    <div class="relative">
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("tests") }}</label>
                      <div
                        @click="testsDropdownOpen = !testsDropdownOpen"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-white flex items-center justify-between min-h-[48px] transition-all"
                      >
                        <div class="flex flex-wrap gap-1.5 flex-1">
                          <span v-if="!record.tests?.length" class="text-slate-400">{{ t("select_tests") }}</span>
                          <span
                            v-for="(test, idx) in record.tests?.slice(0, 3)"
                            :key="idx"
                            class="px-2 py-0.5 bg-primary-100 text-primary-700 text-xs font-medium rounded-lg"
                          >
                            {{ test.name || test.shortcut }}
                          </span>
                          <span v-if="record.tests?.length > 3" class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg">
                            +{{ record.tests.length - 3 }}
                          </span>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="{ 'rotate-180': testsDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                      <Transition name="dropdown">
                        <div
                          v-show="testsDropdownOpen"
                          class="absolute z-30 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden"
                        >
                          <div class="p-3 border-b border-slate-100 bg-slate-50">
                            <div class="relative">
                              <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                              </svg>
                              <input
                                v-model="testsSearch"
                                type="text"
                                :placeholder="t('search') + '...'"
                                class="w-full ps-9 pe-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none"
                                @click.stop
                              />
                            </div>
                          </div>
                          <div class="max-h-48 overflow-y-auto" @scroll="onTestsScroll">
                            <div
                              v-for="test in filteredTests"
                              :key="test.value?.id"
                              @click.stop="toggleTest(test)"
                              class="px-4 py-2.5 cursor-pointer hover:bg-primary-50 flex items-center gap-3 transition-colors"
                            >
                              <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all"
                                :class="isTestSelected(test) ? 'bg-primary-500 border-primary-500' : 'border-slate-300'">
                                <svg v-if="isTestSelected(test)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                              </div>
                              <div class="flex-1 flex items-center gap-2 flex-wrap">
                                <span class="text-sm text-slate-700">{{ test.label }}</span>
                                <span v-if="test.shortcut" class="text-xs text-slate-500 font-mono">({{ test.shortcut }})</span>
                                <span v-if="test.is_special" class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-semibold rounded-full">تحاليل مخصصة</span>
                              </div>
                            </div>
                            <div v-if="filteredTests.length === 0" class="px-4 py-6 text-center text-slate-500 text-sm">
                              {{ t("noData") }}
                            </div>
                          </div>
                          <div class="p-2 border-t border-slate-100 bg-slate-50">
                            <button
                              type="button"
                              @click="testsDropdownOpen = false"
                              class="w-full px-3 py-2 text-sm font-medium bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors"
                            >
                              {{ t("close") }}
                            </button>
                          </div>
                        </div>
                      </Transition>
                    </div>

                    <!-- Cultures MultiSelect -->
                    <div class="relative">
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("cultures") }}</label>
                      <div
                        @click="culturesDropdownOpen = !culturesDropdownOpen"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-white flex items-center justify-between min-h-[48px] transition-all"
                      >
                        <div class="flex flex-wrap gap-1.5 flex-1">
                          <span v-if="!record.cultures?.length" class="text-slate-400">{{ t("select_cultures") }}</span>
                          <span
                            v-for="(culture, idx) in record.cultures?.slice(0, 3)"
                            :key="idx"
                            class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-lg"
                          >
                            {{ culture.name }}
                          </span>
                          <span v-if="record.cultures?.length > 3" class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg">
                            +{{ record.cultures.length - 3 }}
                          </span>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="{ 'rotate-180': culturesDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                      <Transition name="dropdown">
                        <div
                          v-show="culturesDropdownOpen"
                          class="absolute z-30 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden"
                        >
                          <div class="p-3 border-b border-slate-100 bg-slate-50">
                            <div class="relative">
                              <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                              </svg>
                              <input
                                v-model="culturesSearch"
                                type="text"
                                :placeholder="t('search') + '...'"
                                class="w-full ps-9 pe-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none"
                                @click.stop
                              />
                            </div>
                          </div>
                          <div class="max-h-48 overflow-y-auto">
                            <div
                              v-for="culture in filteredCultures"
                              :key="culture.value?.id"
                              @click.stop="toggleCulture(culture)"
                              class="px-4 py-2.5 cursor-pointer hover:bg-purple-50 flex items-center gap-3 transition-colors"
                            >
                              <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all"
                                :class="isCultureSelected(culture) ? 'bg-purple-500 border-purple-500' : 'border-slate-300'">
                                <svg v-if="isCultureSelected(culture)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                              </div>
                              <span class="text-sm text-slate-700">{{ culture.label }}</span>
                            </div>
                            <div v-if="filteredCultures.length === 0" class="px-4 py-6 text-center text-slate-500 text-sm">
                              {{ t("noData") }}
                            </div>
                          </div>
                          <div class="p-2 border-t border-slate-100 bg-slate-50">
                            <button
                              type="button"
                              @click="culturesDropdownOpen = false"
                              class="w-full px-3 py-2 text-sm font-medium bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors"
                            >
                              {{ t("close") }}
                            </button>
                          </div>
                        </div>
                      </Transition>
                    </div>

                    <!-- Test Groups MultiSelect — a whole group can be part of a package -->
                    <div class="relative">
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("test-groups") || "Test Groups" }}</label>
                      <div
                        @click="groupsDropdownOpen = !groupsDropdownOpen"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-white flex items-center justify-between min-h-[48px] transition-all"
                      >
                        <div class="flex flex-wrap gap-1.5 flex-1">
                          <span v-if="!record.test_groups?.length" class="text-slate-400">{{ t("select") }} {{ t("test-groups") || "Test Groups" }}</span>
                          <span
                            v-for="(group, idx) in record.test_groups?.slice(0, 3)"
                            :key="idx"
                            class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-lg"
                          >
                            {{ group.group_name || group.name }}
                          </span>
                          <span v-if="record.test_groups?.length > 3" class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg">
                            +{{ record.test_groups.length - 3 }}
                          </span>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform" :class="{ 'rotate-180': groupsDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                      <Transition name="dropdown">
                        <div
                          v-show="groupsDropdownOpen"
                          class="absolute z-30 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden"
                        >
                          <div class="p-3 border-b border-slate-100 bg-slate-50">
                            <div class="relative">
                              <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                              </svg>
                              <input
                                v-model="groupsSearch"
                                type="text"
                                :placeholder="t('search') + '...'"
                                class="w-full ps-9 pe-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                                @click.stop
                              />
                            </div>
                          </div>
                          <div class="max-h-48 overflow-y-auto">
                            <div
                              v-for="group in filteredGroups"
                              :key="group.id"
                              @click.stop="toggleGroup(group)"
                              class="px-4 py-2.5 cursor-pointer hover:bg-amber-50 flex items-center gap-3 transition-colors"
                            >
                              <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all shrink-0"
                                :class="isGroupSelected(group) ? 'bg-amber-500 border-amber-500' : 'border-slate-300'">
                                <svg v-if="isGroupSelected(group)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                              </div>
                              <span class="text-sm text-slate-700 flex-1">{{ group.group_name }}</span>
                              <span class="text-xs text-slate-400">{{ group.tests?.length || 0 }} {{ t("tests") }}</span>
                            </div>
                            <div v-if="filteredGroups.length === 0" class="px-4 py-6 text-center text-slate-500 text-sm">
                              {{ t("noData") }}
                            </div>
                          </div>
                          <div class="p-2 border-t border-slate-100 bg-slate-50">
                            <button
                              type="button"
                              @click="groupsDropdownOpen = false"
                              class="w-full px-3 py-2 text-sm font-medium bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors"
                            >
                              {{ t("close") }}
                            </button>
                          </div>
                        </div>
                      </Transition>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Formula Card -->
              <FormulaBuilder v-model="record.formula" :variables="(record.tests || []).map(t2 => ({ key: t2.shortcut || t2.name, label: t2.name }))" />

              <!-- Pricing Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-yellow-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("Pricing") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("pricing_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="flex items-start gap-6">
                    <!-- Constant Price Toggle -->
                    <label class="flex items-center gap-3 cursor-pointer group">
                      <div class="relative">
                        <input
                          type="checkbox"
                          v-model="record.is_constant_price"
                          class="sr-only peer"
                        />
                        <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-primary-500 transition-colors"></div>
                        <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></div>
                      </div>
                      <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900">{{ t("is_constant_price") }}</span>
                    </label>
                    <!-- Price Input -->
                    <Transition name="fade">
                      <div v-if="record.is_constant_price" class="flex-1">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                          {{ t("Original_Price") }} <span class="text-red-500">*</span>
                        </label>
                        <input
                          v-model.number="record.price"
                          type="number"
                          required
                          min="0"
                          class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:bg-white outline-none transition-all"
                          :placeholder="t('enter_price')"
                        />
                      </div>
                    </Transition>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
              <button
                type="button"
                @click="close"
                class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                {{ t("cancel") }}
              </button>
              <button
                type="submit"
                class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                {{ record?.id ? t("save") : t("add") }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
  transform: scale(0.95);
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.fade-enter-active,
.fade-leave-active {
  transition: all 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
