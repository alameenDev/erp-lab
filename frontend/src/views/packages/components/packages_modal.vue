<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePackagesStore } from "@/store/modules/packages";
import { usetestsStore } from "@/store/modules/tests";
import { useculturesStore } from "@/store/modules/cultures";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";
import FormulaBuilder from "@/components/FormulaBuilder.vue";
import OrderedCatalogPicker from "@/components/OrderedCatalogPicker.vue";

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

const selectionTab = ref("tests");
watch(dialog, () => { selectionTab.value = "tests"; });
const testsOptions = computed(() => TestLists().map(option => ({
  ...option,
  value: { ...option.value, name: option.label, shortcut: option.shortcut },
})));
const culturesOptions = computed(() => cultureLists().map(option => ({
  ...option,
  value: { ...option.value, name: option.label },
})));
const groupsOptions = computed(() => (testGroups.value || []).map(group => ({
  label: group.group_name,
  shortcut: group.shortcut,
  children: group.tests || [],
  value: { id: group.id, group_name: group.group_name, tests: group.tests },
})));

onMounted(() => {
  GetTests();
  Getcultures();
  testGroupsStore.GettestGroups();
});

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
            <div class="p-4 sm:p-6 overflow-y-auto max-h-[calc(90vh-200px)] space-y-6">
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
                      <h3 class="font-semibold text-slate-800">{{ t("selection_package_contents") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("tests_and_cultures_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="flex flex-wrap gap-2 mb-4" role="group" :aria-label="t('selection_package_contents')">
                    <button v-for="tab in [{ key: 'tests', label: t('tests'), count: record.tests?.length || 0 }, { key: 'groups', label: t('test-groups'), count: record.test_groups?.length || 0 }, { key: 'cultures', label: t('cultures'), count: record.cultures?.length || 0 }]" :key="tab.key" type="button" @click="selectionTab = tab.key" :aria-pressed="selectionTab === tab.key" class="px-4 py-2 rounded-lg text-sm font-medium border" :class="selectionTab === tab.key ? 'bg-teal-50 border-teal-600 text-teal-800' : 'border-slate-200 text-slate-600'">{{ tab.label }} ({{ tab.count }})</button>
                  </div>
                  <OrderedCatalogPicker v-show="selectionTab === 'tests'" v-model="record.tests" :options="testsOptions" :label="t('tests')" />
                  <OrderedCatalogPicker v-show="selectionTab === 'groups'" v-model="record.test_groups" :options="groupsOptions" :label="t('test-groups')" />
                  <OrderedCatalogPicker v-show="selectionTab === 'cultures'" v-model="record.cultures" :options="culturesOptions" :label="t('cultures')" />
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
