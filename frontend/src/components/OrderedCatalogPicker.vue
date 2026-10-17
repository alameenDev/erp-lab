<script setup>
import { computed, ref } from "vue";
import { t } from "@/utils/helper";

const props = defineProps({
  modelValue: { default: () => [] },
  options: { type: Array, default: () => [] },
  label: { type: String, required: true },
});
const emit = defineEmits(["update:modelValue"]);
const search = ref("");
const keyOf = (value) => String(value?.id ?? value);
const values = computed(() => Array.isArray(props.modelValue) ? props.modelValue : []);
const positions = computed(() => new Map(values.value.map((value, index) => [keyOf(value), index + 1])));
const filtered = computed(() => {
  const query = search.value.trim().toLowerCase();
  return props.options.filter((option) => `${option.label} ${option.shortcut || ""}`.toLowerCase().includes(query));
});
const selected = computed(() => {
  const catalog = new Map(props.options.map((option) => [keyOf(option.value), option]));
  return values.value.map((value) => catalog.get(keyOf(value)) || {
    value,
    label: value?.name || value?.group_name || `${t("selection_unavailable")} #${keyOf(value)}`,
    shortcut: value?.shortcut,
    children: value?.tests,
  });
});
const toggle = (option) => {
  const index = values.value.findIndex((value) => keyOf(value) === keyOf(option.value));
  if (index >= 0) remove(index);
  else emit("update:modelValue", [...values.value, option.value]);
};
const remove = (index) => emit("update:modelValue", values.value.filter((_, i) => i !== index));
const move = (index, offset) => {
  const next = [...values.value];
  const destination = index + offset;
  if (destination < 0 || destination >= next.length) return;
  [next[index], next[destination]] = [next[destination], next[index]];
  emit("update:modelValue", next);
};
</script>

<template>
  <section class="ordered-picker" :aria-label="label">
    <p class="order-help">{{ t("selection_order_help") }}</p>
    <div class="picker-columns">
      <div class="catalog-panel">
        <label class="panel-heading">
          <span>{{ t("selection_add") }} · {{ label }}</span>
          <input v-model="search" type="search" :placeholder="t('selection_search')" :aria-label="t('selection_search') + ' · ' + label" />
        </label>
        <div class="catalog-list">
          <button
            v-for="option in filtered"
            :key="keyOf(option.value)"
            type="button"
            class="catalog-option"
            :class="{ 'is-selected': positions.has(keyOf(option.value)) }"
            :aria-pressed="positions.has(keyOf(option.value))"
            @click="toggle(option)"
          >
            <span class="option-mark" aria-hidden="true">{{ positions.get(keyOf(option.value)) || "+" }}</span>
            <span class="item-name"><bdi>{{ option.label }}</bdi><small v-if="option.shortcut"><bdi>{{ option.shortcut }}</bdi></small></span>
            <span v-if="option.is_special" class="special-badge">{{ t("selection_special") }}</span>
          </button>
          <p v-if="!filtered.length" class="empty-state">{{ t("selection_no_matches") }}</p>
        </div>
      </div>

      <div class="selection-panel">
        <div class="panel-heading selection-heading">
          <span>{{ t("selection_ordered") }} · {{ label }}</span>
          <span class="selection-count" aria-live="polite">{{ values.length }}</span>
        </div>
        <ol v-if="selected.length" class="selected-list">
          <li v-for="(option, index) in selected" :key="keyOf(option.value)" class="selected-row">
            <span class="order-number">{{ index + 1 }}</span>
            <div class="item-name">
              <bdi>{{ option.label }}</bdi>
              <small v-if="option.shortcut"><bdi>{{ option.shortcut }}</bdi></small>
              <details v-if="option.children?.length" class="group-children">
                <summary>{{ t("tests") }} ({{ option.children.length }})</summary>
                <ol><li v-for="(child, childIndex) in option.children" :key="child.id"><span>{{ childIndex + 1 }}.</span> <bdi>{{ child.name }}</bdi></li></ol>
              </details>
            </div>
            <div class="row-actions">
              <button type="button" :disabled="index === 0" :aria-label="t('selection_move_up') + ' · ' + option.label" :title="t('selection_move_up')" @click="move(index, -1)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 14 6-6 6 6" /></svg>
              </button>
              <button type="button" :disabled="index === selected.length - 1" :aria-label="t('selection_move_down') + ' · ' + option.label" :title="t('selection_move_down')" @click="move(index, 1)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 10 6 6 6-6" /></svg>
              </button>
              <button type="button" class="remove-button" :aria-label="t('selection_remove') + ' · ' + option.label" :title="t('selection_remove')" @click="remove(index)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
              </button>
            </div>
          </li>
        </ol>
        <div v-else class="empty-state selection-empty">{{ t("selection_empty") }}</div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ordered-picker { min-width: 0; color: #334155; }
.order-help { margin: 0 0 16px; font-size: 13px; line-height: 1.8; color: #64748b; }
.picker-columns { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 16px; }
.catalog-panel, .selection-panel { min-width: 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; }
.panel-heading { display: flex; flex-direction: column; gap: 10px; padding: 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; }
.panel-heading input { width: 100%; min-width: 0; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; font-size: 13px; font-weight: 400; }
.selection-heading { flex-direction: row; align-items: center; justify-content: space-between; background: #f0fdfa; }
.selection-count { background: #ccfbf1; color: #115e59; border-radius: 20px; min-width: 28px; padding: 3px 8px; text-align: center; }
.catalog-list { max-height: 320px; overflow-y: auto; }
.catalog-option { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 54px; padding: 10px 12px; text-align: start; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.catalog-option:hover { background: #f8fafc; }
.catalog-option.is-selected { background: #f0fdfa; }
.option-mark, .order-number { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 28px; height: 28px; border-radius: 8px; font-weight: 700; }
.option-mark { border: 1px solid #cbd5e1; color: #64748b; }
.is-selected .option-mark, .order-number { border-color: #0f766e; background: #0f766e; color: white; }
.item-name { min-width: 0; flex: 1; overflow-wrap: anywhere; line-height: 1.6; }
.item-name > bdi { display: block; font-weight: 500; }
.item-name small { display: block; font-size: 11px; color: #64748b; }
.special-badge { padding: 2px 5px; background: #fef3c7; border-radius: 5px; font-size: 10px; }
.selected-list { margin: 0; padding: 0; list-style: none; max-height: 400px; overflow-y: auto; }
.selected-row { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.selected-row:last-child { border-bottom: 0; }
.row-actions { display: flex; flex-shrink: 0; gap: 2px; }
.row-actions button { display: grid; place-items: center; width: 30px; height: 32px; border: 1px solid #e2e8f0; border-radius: 6px; color: #475569; background: white; }
.row-actions button:hover:enabled { background: #f1f5f9; }
.row-actions button:disabled { opacity: .3; cursor: default; }
.row-actions .remove-button { color: #dc2626; }
.row-actions .remove-button:hover { background: #fef2f2; }
.row-actions svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
button:focus-visible, input:focus-visible, summary:focus-visible { outline: 2px solid #0f766e; outline-offset: 2px; }
.empty-state { padding: 28px 16px; text-align: center; font-size: 13px; line-height: 1.8; color: #64748b; }
.selection-empty { min-height: 160px; display: grid; place-items: center; }
.group-children { margin-top: 6px; font-size: 12px; }
.group-children summary { cursor: pointer; color: #0f766e; }
.group-children ol { padding: 4px 0 0; list-style: none; }
.group-children li { padding: 3px 0; }
@media (max-width: 639px) {
  .picker-columns { grid-template-columns: minmax(0, 1fr); }
  .catalog-list { max-height: 210px; }
  .selected-row { gap: 8px; padding: 10px; }
}
</style>
