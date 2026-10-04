<script setup>
import { computed, ref, onBeforeUnmount } from 'vue';
import BarcodeLabel from '@/components/barcode/BarcodeLabel.vue';
import { labelConfig, defaultLabel, labelFields, fieldNames, barcodeGeometry, elementBox, labelIssues, barcodePrintCss, roundMm } from '@/utils/barcodeLabels';
import { usePrint } from '@/composables/usePrint';
const props = defineProps({ modelValue: Object, showTests: { type: Boolean, default: true }, labName: String, lang: { type: String, default: 'ar' } });
const emit = defineEmits(['update:modelValue']);
const c = computed(() => labelConfig(props.modelValue, props.showTests));
const en = computed(() => props.lang === 'en');
const tr = (ar, english) => en.value ? english : ar;
const name = key => fieldNames[key][en.value ? 1 : 0];
const selected = ref('barcode'), zoom = ref(140), overflow = ref([]), scanned = ref(''), sampleNumber = ref('1234567890');
const samplePatient = ref('أحمد محمد علي');
const e = computed(() => c.value.elements[selected.value]);
const demo = computed(() => ({ barcode: sampleNumber.value, number: sampleNumber.value, patient: samplePatient.value,
  age: '33 years', gender: 'Female', date: '04/10/2026 14:30', sample: 'EDTA', tests: 'CBC, HbA1c',
  laboratory: props.labName || 'Digital Lab', groups: 'Hematology', custom: c.value.custom_text }));
const geometry = computed(() => barcodeGeometry(sampleNumber.value, c.value));
const problems = computed(() => [...labelIssues(c.value, demo.value), ...overflow.value.map(key => ({ code:'text', key }))]);
const messages = computed(() => [...new Set(problems.value.map(i => {
  if (i.code === 'narrow') return tr(`الرمز يحتاج عرضاً لا يقل عن ${i.minimum} مم لهذه القيمة ودقة الطابعة.`, `This value needs at least ${i.minimum} mm at the selected DPI.`);
  if (i.code === 'invalid') return tr('قيمة الباركود لا تناسب نوع الرمز المختار؛ لا يتم تغيير رقم العينة تلقائياً.', 'The sample value is invalid for this symbology. It will not be changed automatically.');
  if (i.code === 'overlap') return tr(`يوجد تداخل بين ${name(i.key)} و${name(i.other)}.`, `${name(i.key)} overlaps ${name(i.other)}.`);
  return name(i.key) + ': ' + (i.code === 'outside' ? tr('خارج حدود الملصق.', 'Outside the label.') : tr('النص لا يسع بالكامل؛ كبّر المساحة أو صغّر الخط.', 'Text does not fit; enlarge the box or reduce the font.'));
}))]);
const update = patch => emit('update:modelValue', { ...c.value, ...patch });
const field = patch => update({ elements: { ...c.value.elements, [selected.value]: { ...e.value, ...patch } } });
function number(key, event, element = false) {
  const value = event.target.value;
  if (value === '' || !Number.isFinite(Number(value))) return;
  (element ? field : update)({ [key]: Number(value) });
}
function preset(width, height) { emit('update:modelValue', defaultLabel(width, height, {}, props.showTests)); }
const center = () => { const b = elementBox(e.value, c.value); field({ x: roundMm(Math.max(0, (c.value.width_mm - b.width) / 2 - c.value.offset_x)) }); };
let endDrag;
function drag(event, key) {
  if (event.button !== 0) return;
  endDrag?.(); selected.value = key;
  const node = event.currentTarget, root = node.closest('.barcode-label');
  const ratio = c.value.width_mm / root.getBoundingClientRect().width;
  const start = { x:event.clientX, y:event.clientY, item: { ...c.value.elements[key] } };
  const move = ev => {
    const box = elementBox(start.item, c.value);
    field({ x: roundMm(Math.max(0, Math.min(c.value.width_mm - box.width - c.value.offset_x, start.item.x + (ev.clientX-start.x)*ratio))),
      y: roundMm(Math.max(0, Math.min(c.value.height_mm - box.height - c.value.offset_y, start.item.y + (ev.clientY-start.y)*ratio))) });
  };
  node.setPointerCapture(event.pointerId);
  endDrag = () => { node.removeEventListener('pointermove', move); node.removeEventListener('pointerup', endDrag); node.removeEventListener('pointercancel', endDrag); endDrag = null; };
  node.addEventListener('pointermove', move); node.addEventListener('pointerup', endDrag); node.addEventListener('pointercancel', endDrag);
}
onBeforeUnmount(() => endDrag?.());
function keyboard(event) {
  const steps = { ArrowLeft:[-1,0], ArrowRight:[1,0], ArrowUp:[0,-1], ArrowDown:[0,1] };
  if (!steps[event.key]) return;
  event.preventDefault();
  const [x,y] = steps[event.key], step = event.shiftKey ? 1 : 0.1;
  field({ x: roundMm(Math.max(0, e.value.x + x*step)), y: roundMm(Math.max(0, e.value.y + y*step)) });
}
const { printWithIframe } = usePrint();
const printTest = () => printWithIframe('barcode-test-print', barcodePrintCss(c.value), 'Barcode calibration label');
</script>
<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6 space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div><h3 class="text-lg font-bold text-slate-900">{{ tr('مصمم ملصقات الباركود', 'Barcode label designer') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ tr('مقاسات حقيقية، ترتيب بالسحب، ومعاينة مطابقة للطباعة. تُحفظ الإعدادات لهذا المختبر فقط بزر حفظ الإعدادات.', 'Physical dimensions, drag-to-position and a shared print preview. Use Save settings to save for this laboratory.') }}</p></div>
      <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">{{ c.width_mm }} × {{ c.height_mm }} mm</span>
    </div>
    <div class="flex flex-wrap gap-2">
      <button v-for="size in [[50,30],[60,40],[76.2,38.1],[40,25]]" :key="size.join('x')" type="button" class="rounded-lg border px-3 py-2 text-sm hover:bg-teal-50" @click="preset(...size)">{{ size[0] }} × {{ size[1] }} mm</button>
      <button type="button" class="rounded-lg border px-3 py-2 text-sm text-slate-600" @click="preset(c.width_mm,c.height_mm)">{{ tr('إعادة ترتيب العناصر', 'Reset layout') }}</button>
    </div>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <label class="bl-field">{{ tr('عرض الملصق (مم)', 'Label width (mm)') }}<input type="number" :value="c.width_mm" min="25" max="150" step="0.1" @change="number('width_mm',$event)"></label>
      <label class="bl-field">{{ tr('ارتفاع الملصق (مم)', 'Label height (mm)') }}<input type="number" :value="c.height_mm" min="15" max="150" step="0.1" @change="number('height_mm',$event)"></label>
      <label class="bl-field">{{ tr('دقة الطابعة', 'Printer resolution') }}<select :value="c.dpi" @change="number('dpi',$event)"><option v-for="dpi in [203,300,600]" :value="dpi" :key="dpi">{{ dpi }} DPI</option></select></label>
      <label class="bl-field">{{ tr('نسخ لكل عينة', 'Copies per sample') }}<input type="number" :value="c.copies" min="1" max="20" @change="number('copies',$event)"></label>
    </div>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_310px]">
      <div class="min-w-0 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500"><span>{{ tr('اسحب أي عنصر أو حدده واستخدم الأسهم للتحريك الدقيق.', 'Drag an element, or focus it and use arrow keys to nudge.') }}</span><label class="flex items-center gap-2">{{ tr('تكبير المعاينة', 'Preview zoom') }}<select v-model.number="zoom" class="rounded border p-1"><option v-for="z in [75,100,140,175,200]" :value="z" :key="z">{{ z }}%</option></select></label></div>
        <div class="overflow-auto rounded-xl border border-slate-200 bg-slate-100 p-6" style="min-height:220px" @keydown="keyboard">
          <div :style="{ width:(c.width_mm*96/25.4*zoom/100)+'px', height:(c.height_mm*96/25.4*zoom/100)+'px' }" class="relative mx-auto">
            <div :style="{ transform:`scale(${zoom/100})`, transformOrigin:'top left', position:'absolute', top:0, left:0 }" class="shadow-lg">
              <BarcodeLabel :config="c" :data="demo" editable :selected="selected" @select="selected=$event" @drag="drag" @overflow="overflow=$event" />
            </div>
          </div>
        </div>
        <p class="text-xs text-slate-500">{{ tr('تكبير المعاينة لا يغيّر مقاس الطباعة. الإحداثيات تبدأ من أعلى يسار الملصق.', 'Preview zoom does not change print size. Coordinates start at the top left.') }}</p>
        <div class="grid grid-cols-2 gap-2">
          <label v-for="key in labelFields" :key="key" class="flex items-center gap-2 rounded-lg border p-2 text-sm" :class="selected===key ? 'border-teal-500 bg-teal-50' : 'border-slate-200'">
            <input type="checkbox" :checked="c.elements[key].visible" :disabled="key==='barcode'" @change="update({elements:{...c.elements,[key]:{...c.elements[key],visible:$event.target.checked}}})">
            <button type="button" class="flex-1 text-start" @click="selected=key">{{ name(key) }}</button>
          </label>
        </div>
      </div>
      <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <h4 class="font-bold text-slate-800">{{ name(selected) }}</h4>
        <div class="grid grid-cols-2 gap-3">
          <label v-for="(caption,key) in {x:tr('من اليسار (مم)','Left (mm)'),y:tr('من الأعلى (مم)','Top (mm)'),width:tr('العرض (مم)','Width (mm)'),height:tr('الارتفاع (مم)','Height (mm)')}" :key="key" class="bl-field">{{ caption }}<input type="number" :value="e[key]" :min="['x','y'].includes(key)?0:1" max="150" step="0.1" @change="number(key,$event,true)"></label>
        </div>
        <label class="bl-field">{{ tr('اتجاه العنصر', 'Rotation') }}<select :value="e.rotation" @change="number('rotation',$event,true)"><option v-for="r in [0,90,180,270]" :key="r" :value="r">{{ r }}°</option></select></label>
        <button type="button" class="w-full rounded-lg border bg-white p-2 text-sm" @click="center">{{ tr('توسيط أفقياً', 'Center horizontally') }}</button>
        <template v-if="selected==='barcode'">
          <label class="bl-field">{{ tr('نوع الرمز', 'Symbology') }}<select :value="c.format" @change="update({format:$event.target.value})"><option value="CODE128">CODE 128</option><option value="CODE39">CODE 39</option></select></label>
          <label class="bl-field">{{ tr('المساحة البيضاء بكل جانب (وحدات)', 'Quiet zone per side (modules)') }}<input type="number" :value="c.quiet_modules" min="10" max="30" @change="number('quiet_modules',$event)"></label>
          <p class="text-xs leading-6 text-slate-600">{{ tr('تغيير العرض يكبّر أو يصغّر الخطوط بخطوات تناسب دقة الطابعة، مع الحفاظ على المساحة البيضاء. الأسود على الأبيض ثابت لسهولة القراءة.', 'Width changes use whole printer dots and preserve quiet zones. Black bars on white remain fixed for readability.') }}</p>
          <p v-if="!geometry.error" class="rounded-lg bg-white p-2 text-xs leading-6" dir="ltr">{{ geometry.dots }} dots/module · {{ roundMm(geometry.moduleMm) }} mm<br>{{ tr('العرض الفعلي:', 'Actual width:') }} {{ roundMm(geometry.width) }} mm</p>
        </template>
        <template v-else>
          <label class="bl-field">{{ tr('حجم الخط (pt)', 'Font size (pt)') }}<input type="number" :value="e.font" min="4" max="32" step="0.5" @change="number('font',$event,true)"></label>
          <label class="bl-field">{{ tr('محاذاة النص', 'Text alignment') }}<select :value="e.align" @change="field({align:$event.target.value})"><option value="left">{{ tr('يسار','Left') }}</option><option value="center">{{ tr('وسط','Center') }}</option><option value="right">{{ tr('يمين','Right') }}</option></select></label>
          <label class="flex gap-2 text-sm"><input type="checkbox" :checked="e.bold" @change="field({bold:$event.target.checked})">{{ tr('خط عريض','Bold') }}</label>
          <label v-if="selected==='custom'" class="bl-field">{{ tr('النص الإضافي','Custom text') }}<textarea :value="c.custom_text" maxlength="120" @input="update({custom_text:$event.target.value})" /></label>
        </template>
      </div>
    </div>
    <details class="rounded-xl border p-4"><summary class="cursor-pointer text-sm font-bold">{{ tr('معايرة موضع الطباعة', 'Print position calibration') }}</summary><div class="mt-3 grid grid-cols-2 gap-3"><label class="bl-field">{{ tr('إزاحة أفقية (مم)','Horizontal offset (mm)') }}<input type="number" :value="c.offset_x" min="-10" max="10" step="0.1" @change="number('offset_x',$event)"></label><label class="bl-field">{{ tr('إزاحة عمودية (مم)','Vertical offset (mm)') }}<input type="number" :value="c.offset_y" min="-10" max="10" step="0.1" @change="number('offset_y',$event)"></label></div></details>
    <div v-if="messages.length" role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><p class="font-bold mb-2">{{ tr('عدّل هذه النقاط قبل الطباعة', 'Fix these items before printing') }}</p><ul class="list-disc ps-5 space-y-1"><li v-for="m in messages" :key="m">{{ m }}</li></ul></div>
    <div class="rounded-xl border border-teal-200 bg-teal-50/50 p-4 space-y-3">
      <h4 class="font-bold text-slate-800">{{ tr('اختبار الطباعة والقراءة', 'Print & scan test') }}</h4>
      <div class="grid gap-3 sm:grid-cols-2"><label class="bl-field">{{ tr('رقم للتجربة فقط', 'Preview sample number') }}<input v-model="sampleNumber" maxlength="255" dir="ltr"></label><label class="bl-field">{{ tr('اسم للتجربة فقط', 'Preview patient name') }}<input v-model="samplePatient" maxlength="120"></label></div>
      <p class="text-xs leading-6 text-slate-600">{{ tr('اختر نفس مقاس الورق في تعريف الطابعة، والطباعة بنسبة 100% دون Fit to page، وأوقف رؤوس وتذييلات المتصفح. اطبع الاختبار ثم امسحه بالقارئ. بيانات الاختبار لا تُحفظ كمريض أو نتيجة.', 'Select matching media in the printer driver, use 100% scale (no fit to page), and disable browser headers/footers. Print a test and scan it. Test data is never saved as a patient or result.') }}</p>
      <button type="button" :disabled="messages.length>0" @click="printTest" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-40">{{ tr('طباعة ملصق تجريبي','Print test label') }}</button>
      <label class="bl-field">{{ tr('اضغط هنا وامسح الملصق بالقارئ', 'Focus here and scan the printed label') }}<input v-model="scanned" dir="ltr" autocomplete="off" @keydown.enter.prevent></label>
      <p v-if="scanned" role="status" :class="scanned===sampleNumber?'text-teal-700':'text-red-700'" class="text-sm font-bold">{{ scanned===sampleNumber ? tr('الرقم المقروء مطابق تماماً ✓','Scanned value matches exactly ✓') : tr('الرقم المقروء غير مطابق؛ تحقق من إعدادات القارئ والطباعة.','Scanned value does not match; check reader and print settings.') }}</p>
    </div>
    <div id="barcode-test-print" class="hidden"><BarcodeLabel :config="c" :data="demo" /></div>
  </section>
</template>
<style scoped>
.bl-field { display:flex; flex-direction:column; gap:6px; font-size:12px; color:#475569; font-weight:600; }
.bl-field input,.bl-field select,.bl-field textarea { width:100%; min-width:0; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#0f172a; font-size:14px; font-weight:400; }
.bl-field input:focus,.bl-field select:focus,.bl-field textarea:focus { outline:2px solid #0d9488; outline-offset:1px; }
</style>
