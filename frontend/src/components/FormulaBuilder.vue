<script setup>
import { computed } from "vue";
import { t } from "@/utils/helper";

const props = defineProps({
  modelValue: { type: [Array, null], default: () => [] },
  variables: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue"]);

const formulas = computed({
  get: () => Array.isArray(props.modelValue) ? props.modelValue : [],
  set: (v) => emit("update:modelValue", v),
});

const operators = ["+", "-", "*", "/", "(", ")"];
const numberPad = ["7", "8", "9", "4", "5", "6", "1", "2", "3", "0", "."];

const addFormula = () => {
  formulas.value = [...formulas.value, { name: "", tokens: [] }];
};

const removeFormula = (idx) => {
  const next = [...formulas.value];
  next.splice(idx, 1);
  formulas.value = next;
};

const pushToken = (idx, token) => {
  const next = [...formulas.value];
  const f = { ...next[idx] };
  f.tokens = [...(f.tokens || []), token];
  next[idx] = f;
  formulas.value = next;
};

const popToken = (idx) => {
  const next = [...formulas.value];
  const f = { ...next[idx] };
  f.tokens = (f.tokens || []).slice(0, -1);
  next[idx] = f;
  formulas.value = next;
};

const clearTokens = (idx) => {
  const next = [...formulas.value];
  const f = { ...next[idx] };
  f.tokens = [];
  next[idx] = f;
  formulas.value = next;
};

const updateName = (idx, name) => {
  const next = [...formulas.value];
  next[idx] = { ...next[idx], name };
  formulas.value = next;
};

const tokenChipClass = (tok) => {
  if (operators.includes(tok)) return "bg-blue-500 text-white";
  if (/^[\d.]+$/.test(tok)) return "bg-slate-700 text-white";
  return "bg-emerald-500 text-white";
};
</script>

<template>
  <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-teal-50">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="font-semibold text-slate-800">{{ t("formula") }}</h3>
          <p class="text-sm text-slate-500">{{ t("formula_desc") }}</p>
        </div>
        <button
          type="button"
          @click="addFormula"
          class="px-4 py-2 text-sm font-medium bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl hover:shadow-lg hover:shadow-emerald-500/30 transition-all flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ t("add") }}
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="p-6 space-y-5">
      <!-- Empty state -->
      <div v-if="formulas.length === 0" class="text-center py-10 border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/50">
        <div class="w-14 h-14 rounded-2xl bg-emerald-100 flex items-center justify-center mx-auto mb-3">
          <svg class="w-7 h-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>
        <p class="text-sm text-slate-500">{{ t("formula_hint") }}</p>
      </div>

      <!-- Formula items -->
      <div
        v-for="(f, idx) in formulas"
        :key="'f-' + idx"
        class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50/50 overflow-hidden shadow-sm"
      >
        <!-- Item header: result name -->
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
          <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 text-sm font-bold flex items-center justify-center">
            {{ idx + 1 }}
          </span>
          <div class="flex-1">
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide font-semibold mb-0.5">{{ t("formula_name") || "اسم النتيجة" }}</label>
            <select
              :value="f.name"
              @change="updateName(idx, $event.target.value)"
              class="w-full px-3 py-1.5 text-sm bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none"
            >
              <option value="">{{ t("select") }}</option>
              <option v-for="v in variables" :key="'name-' + v.key" :value="v.key">{{ v.label }} ({{ v.key }})</option>
            </select>
          </div>
          <button
            type="button"
            @click="removeFormula(idx)"
            class="p-2 text-red-500 hover:text-white hover:bg-red-500 rounded-lg transition-all"
            :title="t('delete')"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
            </svg>
          </button>
        </div>

        <!-- Display + Calculator grid -->
        <div class="p-5 space-y-4">
          <!-- Expression display -->
          <div class="rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 p-4 min-h-[64px] flex items-center justify-end shadow-inner">
            <div v-if="!(f.tokens || []).length" class="text-slate-500 text-sm italic">{{ t("formula_placeholder") || "اختر متغير أو رقم..." }}</div>
            <div v-else class="flex flex-wrap items-center gap-1.5 justify-end" dir="ltr">
              <span
                v-for="(tok, ti) in f.tokens"
                :key="'tok-' + ti"
                :class="tokenChipClass(tok)"
                class="px-2.5 py-1 text-sm font-mono font-bold rounded-md shadow-sm"
              >{{ tok }}</span>
            </div>
          </div>

          <!-- Calculator grid: variables left, keypad right -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Variables column -->
            <div class="md:col-span-2 space-y-2">
              <label class="text-[10px] text-slate-500 uppercase tracking-wide font-semibold">{{ t("variables") }}</label>
              <div v-if="variables.length === 0" class="text-xs text-slate-400 italic p-3 border border-dashed border-slate-200 rounded-lg text-center">
                {{ t("noData") || "اختر فحوصات أولاً" }}
              </div>
              <div v-else class="flex flex-wrap gap-1.5 max-h-44 overflow-y-auto p-1">
                <button
                  v-for="v in variables"
                  :key="'v-' + v.key"
                  type="button"
                  @click="pushToken(idx, v.key)"
                  class="px-3 py-1.5 bg-emerald-100 text-emerald-700 text-xs font-mono font-semibold rounded-lg hover:bg-emerald-500 hover:text-white transition-colors shadow-sm border border-emerald-200"
                  :title="v.label"
                >{{ v.key }}</button>
              </div>
            </div>

            <!-- Keypad column -->
            <div class="md:col-span-3 space-y-2">
              <label class="text-[10px] text-slate-500 uppercase tracking-wide font-semibold">{{ t("operators") || "العمليات" }}</label>
              <div class="grid grid-cols-6 gap-1.5">
                <button
                  v-for="op in operators"
                  :key="'op-' + op"
                  type="button"
                  @click="pushToken(idx, op)"
                  class="h-10 bg-blue-100 text-blue-700 text-base font-bold rounded-lg hover:bg-blue-500 hover:text-white transition-colors border border-blue-200 shadow-sm"
                >{{ op }}</button>
              </div>
              <div class="grid grid-cols-6 gap-1.5 mt-2">
                <button
                  v-for="n in numberPad"
                  :key="'n-' + n"
                  type="button"
                  @click="pushToken(idx, n)"
                  class="h-10 bg-slate-100 text-slate-800 text-base font-bold rounded-lg hover:bg-slate-700 hover:text-white transition-colors border border-slate-200 shadow-sm"
                >{{ n }}</button>
                <button
                  type="button"
                  @click="popToken(idx)"
                  class="h-10 bg-amber-100 text-amber-700 text-sm font-semibold rounded-lg hover:bg-amber-500 hover:text-white transition-colors border border-amber-200 shadow-sm flex items-center justify-center"
                  :title="t('backspace') || 'حذف'"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M21 12a2 2 0 01-2 2H8.83a2 2 0 01-1.41-.59l-3.83-3.83a1 1 0 010-1.41l3.83-3.83A2 2 0 018.83 4H19a2 2 0 012 2v6z" />
                  </svg>
                </button>
              </div>
              <button
                type="button"
                @click="clearTokens(idx)"
                class="w-full h-9 bg-red-50 text-red-600 text-sm font-semibold rounded-lg hover:bg-red-500 hover:text-white transition-colors border border-red-200 shadow-sm mt-2"
              >{{ t("clear") || "مسح الكل" }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
