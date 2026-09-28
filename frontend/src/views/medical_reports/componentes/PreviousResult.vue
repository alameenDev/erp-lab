<script setup>
import { computed } from "vue";

const props = defineProps({
  entry: { type: Object, default: null },
  field: { type: String, default: "" },
  kind: { type: String, default: "" },
  loading: Boolean,
  error: Boolean,
});
const fieldEntry = computed(() => {
  if (!props.field || !props.entry) return null;
  const entries = props.kind === "attribute" ? props.entry.attributes : props.entry.sub_tests;
  const key = props.field.trim().toLowerCase();
  return Array.isArray(entries)
    ? entries.find(item => String(item.name || item.attribute_name || "").trim().toLowerCase() === key)
    : entries?.[key];
});
const value = computed(() => {
  if (!props.entry) return null;
  if (props.field) {
    return props.kind === "attribute" ? fieldEntry.value?.result : fieldEntry.value?.value;
  }
  return props.entry.result;
});
const hasValue = computed(() => value.value !== null && value.value !== undefined && value.value !== "" && value.value !== "null");
const date = computed(() => {
  const raw = props.field ? fieldEntry.value?.date : props.entry?.date;
  if (!raw || !hasValue.value) return "";
  const d = new Date(raw);
  return Number.isNaN(d.getTime()) ? String(raw) : new Intl.DateTimeFormat("ar-IQ", { day: "numeric", month: "short", year: "numeric" }).format(d);
});
</script>

<template>
  <div class="min-w-[136px] max-w-56 rounded-lg border px-3 py-1.5 text-start text-xs leading-5"
    :class="hasValue ? 'border-cyan-200 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-slate-50 text-slate-500'">
    <span class="block font-medium">آخر نتيجة <span v-if="date" class="font-normal">· {{ date }}</span></span>
    <strong v-if="hasValue" class="block break-words text-sm" dir="auto">{{ value }}</strong>
    <span v-else>{{ loading ? 'جاري التحميل...' : error ? 'تعذر تحميل السابقة' : 'لا توجد نتيجة سابقة' }}</span>
  </div>
</template>
