<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { labelConfig, labelFields, barcodeGeometry, elementStyle, labelIssues } from '@/utils/barcodeLabels';
const props = defineProps({ config: Object, data: { type: Object, required: true }, editable: Boolean, selected: String });
const emit = defineEmits(['select', 'drag', 'overflow']);
const root = ref(null);
const c = computed(() => labelConfig(props.config));
const data = computed(() => ({ ...props.data, custom: c.value.custom_text }));
const geometry = computed(() => barcodeGeometry(data.value.barcode, c.value));
const issues = computed(() => labelIssues(c.value, data.value));
const fields = computed(() => labelFields.filter(key => c.value.elements[key].visible && (props.editable || data.value[key])));
let observer;
const measure = async () => {
  await nextTick();
  if (!root.value?.getBoundingClientRect().width) return;
  const overflow = [...root.value.querySelectorAll('[data-label-text]')].filter(e => e.clientWidth && (e.scrollHeight > e.clientHeight + 1 || e.scrollWidth > e.clientWidth + 1)).map(e => e.dataset.labelText);
  emit('overflow', overflow);
};
watch([c, data], measure, { deep: true, flush: 'post' });
onMounted(() => { measure(); observer = new ResizeObserver(measure); if (root.value) observer.observe(root.value); });
onBeforeUnmount(() => observer?.disconnect());
function start(event, key) { if (props.editable) { emit('select', key); emit('drag', event, key); } }
</script>
<template>
  <div ref="root" class="barcode-label" :data-label-errors="issues.length ? issues.map(i => i.code + ':' + i.key).join(', ') : null"
    :style="{ position:'relative', width:c.width_mm+'mm', height:c.height_mm+'mm', background:'#fff', color:'#000', fontFamily:'Arial,sans-serif', overflow:'hidden', flexShrink:0 }" dir="ltr">
    <div v-for="key in fields" :key="key" :style="elementStyle(c.elements[key], c)"
      :data-label-text="key !== 'barcode' ? key : null" :data-label-field="key"
      :class="{ 'label-draggable': editable, 'label-selected': editable && selected === key }"
      @pointerdown="start($event, key)" :tabindex="editable ? 0 : undefined" @focus="editable && emit('select', key)">
      <template v-if="key === 'barcode'">
        <svg v-if="!geometry.error" xmlns="http://www.w3.org/2000/svg" :viewBox="`0 0 ${geometry.modules} 1`" preserveAspectRatio="none"
          :width="geometry.width+'mm'" :height="geometry.height+'mm'" role="img" :aria-label="data.barcode"
          :style="{ display:'block', width:geometry.width+'mm', height:geometry.height+'mm', maxWidth:'none', margin:'0 auto', background:'#fff' }" shape-rendering="crispEdges">
          <rect x="0" y="0" :width="geometry.modules" height="1" fill="#fff" />
          <rect v-for="(bar, i) in geometry.bars" :key="i" :x="bar.x" y="0" :width="bar.width" height="1" fill="#000" />
        </svg>
        <span v-else style="font-size:8pt;color:#b91c1c">{{ geometry.error === 'narrow' ? 'Increase barcode width' : 'Invalid barcode value' }}</span>
      </template>
      <span v-else dir="auto">{{ data[key] }}</span>
    </div>
  </div>
</template>
<style scoped>
.label-draggable { cursor:move; touch-action:none; user-select:none; outline:1px dashed #94a3b8; outline-offset:-1px; }
.label-selected { outline:2px solid #0d9488; outline-offset:-2px; background-color:rgb(13 148 136 / 5%); }
</style>
