<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useculturesStore } from "@/store/modules/cultures";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { useResultTypesStore } from "@/store/modules/resultTypes";
import { usePatientsStore } from "@/store/modules/patients";
import { priceListStore } from "@/store/modules/priceList";
import { useDurationUnitsStore } from "@/store/modules/durationUnits";
import { usesamplesStore } from "@/store/modules/samples";
import { useCategoriesStore } from "@/store/modules/categories";
import { usetestsQuestionsStore } from "@/store/modules/tests-questions";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const culturesStore = useculturesStore();
const testGroupsStore = usetestGroupsStore();
const resultTypesStore = useResultTypesStore();
const patientsStore = usePatientsStore();
const priceStore = priceListStore();
const durationUnitsStore = useDurationUnitsStore();
const samplesStore = usesamplesStore();
const categoriesStore = useCategoriesStore();
const testsQuestionsStore = usetestsQuestionsStore();

const { record, dialog, attributes } = storeToRefs(culturesStore);
const { Addcultures, Updatecultures } = culturesStore;
const { GettestGroups, Groups } = testGroupsStore;
const { resultTypes } = storeToRefs(resultTypesStore);
const { GetresultTypes } = resultTypesStore;
const { GetGenders, GetAgeUnits } = patientsStore;
const { GetpriceList } = priceStore;
const { durationUnitsList } = storeToRefs(durationUnitsStore);
const { GetdurationUnits } = durationUnitsStore;
const { samples } = samplesStore;
const { Getsamples } = samplesStore;
const { categories } = categoriesStore;
const { Getcategories } = categoriesStore;
const { GetTestsQuestions } = testsQuestionsStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

onMounted(() => {
  GettestGroups();
  GetresultTypes();
  GetGenders();
  GetAgeUnits();
  GetTestsQuestions();
  GetpriceList();
  GetdurationUnits();
  Getsamples();
  Getcategories();
});

const resetAttributes = () => {
  attributes.value = [
    {
      attribute_name: null,
      order: null,
      result_type_id_fk: null,
      selection_type_options: [""],
    },
  ];
};

const handleSubmit = () => {
  if (record.value.id) {
    Updatecultures().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      resetAttributes();
      dialog.value = false;
    });
  } else {
    Addcultures().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      record.value.result_comments = [""];
      record.value.selection_type_options = [""];
      resetAttributes();
      dialog.value = false;
    });
  }
};

const close = () => {
  clearObjectValues(record.value);
  record.value.selection_type_options = [""];
  record.value.result_comments = [""];
  resetAttributes();
  dialog.value = false;
};

const addComment = (array) => {
  array?.push("");
};

const removeComment = (array, index) => {
  array.splice(index, 1);
};

const addOption = (array) => {
  array.push("");
};

const removeOption = (array, index) => {
  array?.splice(index, 1);
};

const addAttrOption = () => {
  attributes.value?.push({
    attribute_name: null,
    order: null,
    result_type_id_fk: null,
    selection_type_options: [""],
  });
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ record?.id ? t("update") : t("add") }} {{ t("culture") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("culture_info_desc") }}</p>
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
                      <h3 class="font-semibold text-slate-800">{{ t("culture_basic_info") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("culture_basic_info_desc") }}</p>
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
                        :placeholder="t('culture_name_placeholder')"
                      />
                    </div>
                    <!-- Precautions -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("precautions") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="record.precautions"
                        type="text"
                        required
                        maxlength="255"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                        :placeholder="t('precautions_placeholder')"
                      />
                    </div>
                    <!-- Category -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Category") }} <span class="text-red-500">*</span>
                      </label>
                      <select
                        v-model="record.category_id_fk"
                        required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                      >
                        <option value="" disabled>{{ t("select") }}</option>
                        <option v-for="cat in categories()" :key="cat.value" :value="cat.value">
                          {{ cat.label }}
                        </option>
                      </select>
                    </div>
                    <!-- Sample -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Sample") }} <span class="text-red-500">*</span>
                      </label>
                      <select
                        v-model="record.sample_id_fk"
                        required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                      >
                        <option value="" disabled>{{ t("select") }}</option>
                        <option v-for="smp in samples()" :key="smp.value" :value="smp.value">
                          {{ smp.label }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Duration & Pricing Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-yellow-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("duration_and_pricing") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("duration_and_pricing_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Test Duration -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Test_Duration") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model.number="record.test_duration"
                        type="number"
                        required
                        min="0"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:bg-white outline-none transition-all"
                      />
                    </div>
                    <!-- Duration Unit -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Duration_Unit") }} <span class="text-red-500">*</span>
                      </label>
                      <select
                        v-model="record.duration_unit_id_fk"
                        required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:bg-white outline-none transition-all"
                      >
                        <option value="" disabled>{{ t("select") }}</option>
                        <option v-for="unit in durationUnitsList" :key="unit.value" :value="unit.value">
                          {{ unit.label }}
                        </option>
                      </select>
                    </div>
                    <!-- Customer Price -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Customer_Price") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model.number="record.price_for_customer"
                        type="number"
                        required
                        min="0"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:bg-white outline-none transition-all"
                      />
                    </div>
                    <!-- Original Price -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("Original_Price") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model.number="record.price"
                        type="number"
                        required
                        min="0"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:bg-white outline-none transition-all"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Result Comments Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-purple-50 to-violet-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("Result_Comments") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("result_comments_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="space-y-3">
                    <div
                      v-for="(comment, index) in record.result_comments"
                      :key="index"
                      class="flex gap-3"
                    >
                      <input
                        v-model="record.result_comments[index]"
                        type="text"
                        maxlength="255"
                        class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white outline-none transition-all"
                        :placeholder="t('comment_placeholder')"
                      />
                      <button
                        type="button"
                        @click="removeComment(record.result_comments, index)"
                        class="p-3 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-xl transition-all"
                      >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="addComment(record.result_comments)"
                    class="mt-4 px-4 py-2.5 text-sm font-medium text-purple-600 hover:bg-purple-50 rounded-xl transition-all flex items-center gap-2"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t("add_comment") }}
                  </button>
                </div>
              </div>

              <!-- Attributes Card -->
              <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("attributes") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("attributes_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5 space-y-4">
                  <div
                    v-for="(attr, index) in attributes"
                    :key="index"
                    class="relative p-5 bg-gradient-to-r from-blue-50/50 to-indigo-50/50 rounded-xl border border-blue-100"
                  >
                    <button
                      type="button"
                      @click="removeOption(attributes, index)"
                      class="absolute -top-2 -start-2 p-1.5 bg-red-500 text-white rounded-full hover:bg-red-600 shadow-lg transition-all"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <!-- Attribute Name -->
                      <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("attribute_name") }} <span class="text-red-500">*</span></label>
                        <input
                          v-model="attr.attribute_name"
                          type="text"
                          required
                          maxlength="255"
                          class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all"
                        />
                      </div>
                      <!-- Order -->
                      <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Order") }} <span class="text-red-500">*</span></label>
                        <input
                          v-model.number="attr.order"
                          type="number"
                          required
                          min="1"
                          class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all"
                        />
                      </div>
                      <!-- Result Type -->
                      <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Result_Type") }} <span class="text-red-500">*</span></label>
                        <select
                          v-model="attr.result_type_id_fk"
                          required
                          class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all"
                        >
                          <option value="" disabled>{{ t("select") }}</option>
                          <option v-for="type in resultTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                          </option>
                        </select>
                      </div>
                    </div>

                    <!-- Selection Type Options -->
                    <div v-show="attr.result_type_id_fk === 4" class="mt-4 p-4 bg-white rounded-xl border border-blue-100">
                      <label class="block text-sm font-medium text-slate-700 mb-3">{{ t("Selection_Type_Options") }}</label>
                      <div class="space-y-2">
                        <div
                          v-for="(option, i) in attr.selection_type_options"
                          :key="i"
                          class="flex gap-3"
                        >
                          <input
                            v-model="attr.selection_type_options[i]"
                            type="text"
                            maxlength="255"
                            class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white outline-none transition-all"
                          />
                          <button
                            type="button"
                            @click="removeOption(attr.selection_type_options, i)"
                            class="p-2.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-xl transition-all"
                          >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </div>
                      </div>
                      <button
                        type="button"
                        @click="addOption(attr.selection_type_options)"
                        class="mt-3 px-3 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-all flex items-center gap-2"
                      >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t("add_option") }}
                      </button>
                    </div>
                  </div>

                  <button
                    type="button"
                    @click="addAttrOption"
                    class="px-4 py-2.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-xl transition-all flex items-center gap-2"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t("add_attribute") }}
                  </button>
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
</style>
