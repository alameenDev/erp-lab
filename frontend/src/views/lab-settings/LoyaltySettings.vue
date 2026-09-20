<script setup>
import { ref, watch, computed } from 'vue';
import { $http } from '@/plugins/axios';
const props = defineProps({ config: Object });
const emit = defineEmits(['saved']);
const form = ref(null), busy = ref(false), error = ref(''), success = ref('');
watch(() => props.config, value => { if (value) form.value = structuredClone(JSON.parse(JSON.stringify(value))); }, { immediate: true });
const example = computed(() => Math.floor(10000 * Number(form.value?.points_per_currency || 0)));
function reward() { form.value.redemption_catalog.push({key:'reward_'+crypto.randomUUID(),label_ar:'',points:100}); }
function tier() { form.value.tiers.push({key:'tier_'+Date.now(),label_ar:'',label_en:'',min_yearly_points:1000,multiplier:1}); }
async function save() {
  busy.value=true; error.value=''; success.value='';
  try { await $http.post('/lab-settings',{loyalty_config:form.value}); success.value='تم حفظ إعدادات الولاء. تسري القواعد الجديدة على العمليات القادمة.'; emit('saved'); }
  catch(e) { error.value=Object.values(e.response?.data?.errors || {}).flat().join(' — ') || e.response?.data?.message || 'تعذر حفظ الإعدادات'; }
  finally { busy.value=false; }
}
</script>
<template>
<form v-if="form" @submit.prevent="save" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-5" dir="rtl">
 <div><h3 class="text-lg font-bold">برنامج الولاء والمكافآت</h3><p class="text-sm text-slate-500 mt-2">النقاط تُحتسب من الدفعات المسجلة، وليس من قيمة فاتورة غير مدفوعة. حفظ الإعدادات لا يمنح نقاطاً بأثر رجعي.</p></div>
 <label class="flex gap-2 items-center"><input type="checkbox" v-model="form.enabled"> تفعيل اكتساب النقاط واستبدال المكافآت</label>
 <div class="grid gap-4 sm:grid-cols-2">
  <label>النقاط لكل دينار مدفوع<input v-model.number="form.points_per_currency" type="number" min="0" step="0.000001" required><small>مثال: 0.01 يعني 10 نقاط لكل 1,000 دينار. دفعة 10,000 = {{example}} نقطة قبل مضاعف المستوى.</small></label>
  <label>نقاط الترحيب<input v-model.number="form.welcome_bonus" type="number" min="0" step="1" required><small>تُمنح مرة واحدة عند أول فتح لبوابة المريض، عندما يكون البرنامج مفعلاً. صفر لإيقافها.</small></label>
  <label>صلاحية النقاط بالأشهر<input v-model.number="form.points_expiry_months" type="number" min="1" max="60" required><small>من تاريخ منح النقاط الجديدة؛ لا يغيّر صلاحية النقاط السابقة.</small></label>
 </div>
 <div class="border-t pt-4"><h4 class="font-bold">مستويات العضوية</h4><p class="text-sm text-slate-500">تُحدد حسب النقاط المكتسبة خلال آخر 12 شهراً. اجعل المستوى الأول يبدأ من صفر. المضاعف 1.25 يمنح 25% نقاطاً إضافية.</p>
 <div v-for="(row,i) in form.tiers" :key="row.key" class="grid gap-3 sm:grid-cols-4 border rounded-xl p-3 mt-3">
  <label>اسم المستوى<input v-model="row.label_ar" required maxlength="80"></label><label>الحد الأدنى السنوي<input v-model.number="row.min_yearly_points" type="number" min="0" required></label><label>مضاعف النقاط<input v-model.number="row.multiplier" type="number" min="1" max="10" step="0.05" required></label><button type="button" :disabled="form.tiers.length===1" @click="form.tiers.splice(i,1)" class="text-red-700 disabled:opacity-40">حذف المستوى</button>
 </div><button type="button" @click="tier" class="mt-3 text-teal-700 font-bold">+ إضافة مستوى</button></div>
 <div class="border-t pt-4"><h4 class="font-bold">دليل المكافآت</h4><p class="text-sm text-slate-500">اسم المكافأة يظهر للمريض مع النقاط المطلوبة. الاستبدال يسجّل خصم النقاط؛ تقديم الفحص أو الخصم يتم من المختبر ولا يُعدّل الفاتورة تلقائياً.</p>
 <div v-for="(row,i) in form.redemption_catalog" :key="row.key" class="grid gap-3 sm:grid-cols-3 border rounded-xl p-3 mt-3"><label>اسم المكافأة<input v-model="row.label_ar" required maxlength="150" placeholder="مثال: خصم 5,000 دينار"></label><label>النقاط المطلوبة<input v-model.number="row.points" type="number" min="1" required></label><button type="button" @click="form.redemption_catalog.splice(i,1)" class="text-red-700">حذف المكافأة</button></div>
 <p v-if="!form.redemption_catalog.length" class="text-sm mt-2">لا توجد مكافآت متاحة للاستبدال.</p><button type="button" @click="reward" class="mt-3 text-teal-700 font-bold">+ إضافة مكافأة</button></div>
 <p v-if="error" role="alert" class="text-red-700">{{error}}</p><p v-if="success" role="status" class="text-emerald-700">{{success}}</p>
 <button :disabled="busy" class="rounded-xl bg-teal-700 text-white px-5 py-3 disabled:opacity-50">{{busy?'جارٍ الحفظ…':'حفظ إعدادات الولاء'}}</button>
</form>
</template>
<style scoped>
.grid label{display:flex;flex-direction:column;gap:8px;font-size:14px}.grid input{border:1px solid #cbd5e1;border-radius:8px;padding:10px;width:100%;min-width:0}.grid small{color:#64748b;line-height:1.7}
</style>
