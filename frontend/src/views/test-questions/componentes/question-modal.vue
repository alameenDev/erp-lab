<script setup>
import { ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { usetestsQuestionsStore } from "@/store/modules/tests-questions";
import { useAnswerTypesStore } from "@/store/modules/answerTypes";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const testsQuestionsStore = usetestsQuestionsStore();
const answerTypesStore = useAnswerTypesStore();
const { record, dialog } = storeToRefs(testsQuestionsStore);
const { answerTypes } = storeToRefs(answerTypesStore);
const { AddTestsQuestions, UpdateTestsQuestions } = testsQuestionsStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const answerTypeDropdownOpen = ref(false);

const selectedAnswerType = computed(() => {
  if (!record.value.answer_type_id_fk) return null;
  return answerTypes.value?.find((type) => type.value === record.value.answer_type_id_fk);
});

const selectAnswerType = (type) => {
  record.value.answer_type_id_fk = type.value;
  answerTypeDropdownOpen.value = false;
};

const addSelectionValue = () => {
  if (!record.value.answer_type_selection_values) {
    record.value.answer_type_selection_values = [];
  }
  record.value.answer_type_selection_values.push("");
};

const removeSelectionValue = (index) => {
  record.value.answer_type_selection_values.splice(index, 1);
};

const handleSubmit = () => {
  if (record.value.id) {
    UpdateTestsQuestions().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      record.value.answer_type_selection_values = [""];
      dialog.value = false;
    });
  } else {
    AddTestsQuestions().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      record.value.answer_type_selection_values = [""];
      dialog.value = false;
    });
  }
};

const close = () => {
  clearObjectValues(record.value);
  record.value.answer_type_selection_values = [""];
  dialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                  <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ record?.id ? t("update") : t("add") }} {{ t("question") }}</h2>
                  <p class="text-slate-400 text-sm">{{ t("question_info_desc") }}</p>
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
              <div class="bg-white rounded-2xl border border-slate-200/80">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("question_basic_info") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("question_basic_info_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Question -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("question") }} <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="record.question"
                        type="text"
                        required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                        :placeholder="t('question_placeholder')"
                      />
                    </div>

                    <!-- Answer Type -->
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ t("answer_type") }} <span class="text-red-500">*</span>
                      </label>
                      <div class="relative">
                        <button
                          type="button"
                          @click="answerTypeDropdownOpen = !answerTypeDropdownOpen"
                          class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-start flex items-center justify-between focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white transition-all"
                        >
                          <span :class="selectedAnswerType ? 'text-slate-700' : 'text-slate-400'">
                            {{ selectedAnswerType?.label || t("select") }}
                          </span>
                          <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                          </svg>
                        </button>
                        <Transition
                          enter-active-class="transition ease-out duration-100"
                          enter-from-class="transform opacity-0 scale-95"
                          enter-to-class="transform opacity-100 scale-100"
                          leave-active-class="transition ease-in duration-75"
                          leave-from-class="transform opacity-100 scale-100"
                          leave-to-class="transform opacity-0 scale-95"
                        >
                          <div
                            v-if="answerTypeDropdownOpen"
                            class="absolute z-10 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-xl max-h-48 overflow-y-auto"
                          >
                            <button
                              v-for="type in answerTypes"
                              :key="type.value"
                              type="button"
                              @click="selectAnswerType(type)"
                              class="w-full px-4 py-3 text-start hover:bg-slate-50 text-sm transition-colors first:rounded-t-xl last:rounded-b-xl"
                              :class="{ 'bg-primary-50 text-primary-700': record.answer_type_id_fk === type.value }"
                            >
                              {{ type.label }}
                            </button>
                          </div>
                        </Transition>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Selection Values Card (when answer_type_id_fk == 5) -->
              <div v-if="record.answer_type_id_fk == 5" class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-purple-50 to-violet-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h3 class="font-semibold text-slate-800">{{ t("answer_type_selection_values") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("selection_values_desc") }}</p>
                    </div>
                  </div>
                </div>
                <div class="p-5">
                  <div class="space-y-3">
                    <div
                      v-for="(value, index) in record.answer_type_selection_values"
                      :key="index"
                      class="flex gap-3"
                    >
                      <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-purple-500/25 flex-shrink-0">
                        {{ index + 1 }}
                      </div>
                      <input
                        v-model="record.answer_type_selection_values[index]"
                        type="text"
                        maxlength="255"
                        class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white outline-none transition-all"
                        :placeholder="t('selection_value_placeholder')"
                      />
                      <button
                        type="button"
                        @click="removeSelectionValue(index)"
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
                    @click="addSelectionValue"
                    class="mt-4 px-4 py-2.5 text-sm font-medium text-purple-600 hover:bg-purple-50 rounded-xl transition-all flex items-center gap-2"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t("add_selection_value") }}
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
