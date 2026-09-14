<script setup>
import { ref, computed, nextTick, defineExpose, defineProps, defineEmits } from "vue";

const props = defineProps({
     items: { type: Array, required: true },
     onSelect: { type: Function, required: true },
});
const emit = defineEmits(["escape"]);

const selected = ref(0);

// Reset selection when items change
const normalizedItems = computed(() => props.items || []);

const select = (i) => {
     const item = normalizedItems.value[i];
     if (item) props.onSelect(item);
};

const up = () => {
     if (!normalizedItems.value.length) return;
     selected.value = (selected.value + normalizedItems.value.length - 1) % normalizedItems.value.length;
};

const down = () => {
     if (!normalizedItems.value.length) return;
     selected.value = (selected.value + 1) % normalizedItems.value.length;
};

const enter = () => {
     select(selected.value);
};

const reset = () => {
     selected.value = 0;
};

defineExpose({ up, down, enter, reset });
</script>

<template>
     <div class="suggestion-popup bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden min-w-[240px] max-w-[320px] max-h-[320px] overflow-y-auto text-sm">
          <div v-if="!items.length" class="px-3 py-2 text-slate-400 text-xs italic">
               No matches
          </div>
          <button
               v-for="(item, i) in items"
               :key="item.id || item.insertText || i"
               type="button"
               @click="select(i)"
               @mouseenter="selected = i"
               :class="[
                    'w-full flex items-center gap-2.5 px-3 py-2 text-start transition-colors cursor-pointer',
                    i === selected ? 'bg-primary-50 text-primary-900' : 'hover:bg-slate-50 text-slate-700',
               ]"
          >
               <div v-if="item.icon" class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-base"
                    :class="i === selected ? 'bg-primary-100 text-primary-700' : 'bg-slate-100 text-slate-500'"
                    v-html="item.icon"></div>
               <div class="flex-1 min-w-0">
                    <div class="font-semibold truncate">{{ item.label }}</div>
                    <div v-if="item.description" class="text-xs text-slate-400 truncate">{{ item.description }}</div>
               </div>
               <kbd v-if="item.shortcut" class="text-[10px] font-mono text-slate-400 shrink-0">{{ item.shortcut }}</kbd>
          </button>
     </div>
</template>

<style scoped>
.suggestion-popup {
     direction: ltr;
}
</style>
