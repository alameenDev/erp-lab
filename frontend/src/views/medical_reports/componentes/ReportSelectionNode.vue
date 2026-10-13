<script setup>
import { computed } from 'vue';
import { reportNodeKeys } from '@/utils/reportItemSelection';
const props = defineProps({ node: Object, selected: Set, busy: Boolean });
const emit = defineEmits(['toggle']);
const keys = computed(() => reportNodeKeys(props.node));
const count = computed(() => keys.value.filter(k => props.selected.has(k)).length);
</script>
<template>
  <div :data-report-selection="node.key" class="border-t border-slate-100">
    <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-blue-50/40" :class="node.children.length ? 'bg-slate-50/60' : ''">
      <input type="checkbox" class="h-4 w-4 shrink-0 accent-blue-600" :aria-label="node.name" :disabled="busy"
        :checked="count === keys.length" :indeterminate="count > 0 && count < keys.length"
        @change="emit('toggle', keys)" />
      <span class="min-w-0 flex-1 text-sm text-slate-800" :class="node.children.length ? 'font-bold' : ''">{{ node.name }}
        <small v-if="node.children.length" class="ms-2 font-normal text-slate-500">{{ count }} / {{ keys.length }}</small>
      </span>
      <span class="shrink-0 text-xs" :class="[true, 1, '1', 'true'].includes(node.item.is_done) ? 'text-emerald-700' : 'text-amber-700'">
        {{ [true, 1, '1', 'true'].includes(node.item.is_done) ? 'مكتمل' : 'قيد الإجراء' }}
      </span>
    </label>
    <div v-if="node.children.length" class="ms-5 border-s border-slate-200 sm:ms-7">
      <ReportSelectionNode v-for="child in (node.visibleChildren || node.children)" :key="child.key"
        :node="child" :selected="selected" :busy="busy" @toggle="emit('toggle', $event)" />
    </div>
  </div>
</template>
