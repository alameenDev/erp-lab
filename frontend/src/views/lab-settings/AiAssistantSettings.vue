<script setup>
import { ref, watch } from 'vue';
import { $http } from '@/plugins/axios';
const props = defineProps({ config: Object });
const emit = defineEmits(['saved']);
const form = ref(null), busy = ref(false), error = ref(''), success = ref('');
const newApiKey = ref('');
watch(() => props.config, value => {
  form.value = value ? structuredClone(JSON.parse(JSON.stringify(value))) : { enabled: false, base_url: '', model: 'gpt-4o-mini', promo_code_note: '' };
  newApiKey.value = '';
}, { immediate: true });

async function save() {
  busy.value = true; error.value = ''; success.value = '';
  try {
    const payload = { ...form.value };
    if (newApiKey.value) payload.api_key = newApiKey.value;
    else delete payload.api_key; // keep the currently stored key unchanged
    delete payload.has_api_key;
    await $http.post('/lab-settings', { ai_config: payload });
    success.value = 'تم حفظ إعدادات المساعد الذكي.';
    newApiKey.value = '';
    emit('saved');
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat().join(' — ') || e.response?.data?.message || 'تعذر حفظ الإعدادات';
  } finally {
    busy.value = false;
  }
}
</script>
<template>
<form v-if="form" @submit.prevent="save" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-5" dir="rtl">
  <div>
    <h3 class="text-lg font-bold">المساعد الذكي (بوابة المريض)</h3>
    <p class="text-sm text-slate-500 mt-2">
      يتيح للمريض بوصف حالته والحصول على معلومات عامة (وليست تشخيصاً)، مع اقتراح أطباء وفحوصات من مختبرك فقط.
      يحتاج سيرفر متوافق مع OpenAI (chat completions API).
    </p>
  </div>

  <label class="flex gap-2 items-center">
    <input type="checkbox" v-model="form.enabled" />
    تفعيل المساعد الذكي بالبوابة
  </label>

  <div class="grid gap-4 sm:grid-cols-2">
    <label>
      رابط السيرفر (Base URL)
      <input v-model="form.base_url" type="url" placeholder="https://api.openai.com/v1" required />
      <small>بدون / بالنهاية. مثال: https://api.openai.com/v1</small>
    </label>
    <label>
      اسم النموذج (Model)
      <input v-model="form.model" type="text" placeholder="gpt-4o-mini" />
    </label>
    <label class="sm:col-span-2">
      API Key
      <input v-model="newApiKey" type="password" :placeholder="form.has_api_key ? 'محفوظ حالياً - اتركه فارغاً للإبقاء عليه' : 'أدخل مفتاح API'" autocomplete="new-password" />
      <small v-if="form.has_api_key">يوجد مفتاح محفوظ حالياً؛ اكتب مفتاحاً جديداً فقط إذا تريد تغييره.</small>
    </label>
    <label class="sm:col-span-2">
      ملاحظة الخصم (اختياري)
      <textarea v-model="form.promo_code_note" rows="2" maxlength="1000" placeholder="مثال: استخدم كود AI10 عند حجز موعد عبر البوابة للحصول على خصم 10%"></textarea>
      <small>إذا كتبت شي هنا، المساعد راح يذكره للمريض بنفس الصياغة عند المناسب - أنشئ الكود بنفسك من صفحة أكواد الخصم أولاً.</small>
    </label>
  </div>

  <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
  <p v-if="success" role="status" class="text-emerald-700">{{ success }}</p>
  <button :disabled="busy" class="rounded-xl bg-teal-700 text-white px-5 py-3 disabled:opacity-50">
    {{ busy ? 'جارٍ الحفظ…' : 'حفظ إعدادات المساعد الذكي' }}
  </button>
</form>
</template>
<style scoped>
.grid label{display:flex;flex-direction:column;gap:8px;font-size:14px}
.grid input,.grid textarea{border:1px solid #cbd5e1;border-radius:8px;padding:10px;width:100%;min-width:0}
.grid small{color:#64748b;line-height:1.7}
</style>
