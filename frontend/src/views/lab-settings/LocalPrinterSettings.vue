<script setup>
import {ref, onMounted} from 'vue';
import {PRINT_TYPES, readPrintSettings, savePrintSettings, helperRequest, pairPrintHelper, unpairPrintHelper, routePrintDocument} from '@/utils/localPrint';
const settings = ref(readPrintSettings()), printers = ref([]), code = ref(''), busy = ref(false), checking = ref(false);
const connected = ref(false), error = ref(''), notice = ref('');
const downloadUrl = /^https:\/\//.test(import.meta.env.VITE_PRINT_HELPER_DOWNLOAD_URL || '') ? import.meta.env.VITE_PRINT_HELPER_DOWNLOAD_URL : '';
for (const [kind] of PRINT_TYPES) settings.value.routes[kind] ||= {printer: '', copies: 1};
const readableError = e => /fetch|network|timeout|aborted/i.test(e?.message || '')
  ? 'تعذر الاتصال بالمساعد. شغّل Digital Lab Print على هذه الحاسبة، واسمح للمتصفح بالاتصال بالشبكة المحلية إذا طلب ذلك.' : e.message;
async function refresh() {
  checking.value = true; error.value = '';
  try {
    const health = await helperRequest('/health', {token: ''});
    if (health.protocol !== 1 || health.name !== 'Digital Lab Print') throw new Error('نسخة مساعد الطباعة غير متوافقة.');
    if (settings.value.token) printers.value = (await helperRequest('/printers')).printers;
    connected.value = true;
  } catch (e) {connected.value = false; error.value = readableError(e);}
  finally {checking.value = false;}
}
async function pair() {
  busy.value = true; notice.value = ''; error.value = '';
  try {await pairPrintHelper(code.value.trim());settings.value = readPrintSettings();code.value = '';await refresh();notice.value = 'تم ربط هذه الحاسبة. اختر الطابعات ثم فعّل الطباعة المباشرة واحفظ.';}
  catch (e) {error.value = readableError(e);} finally {busy.value = false;}
}
function save() {
  error.value = ''; notice.value = '';
  try {
    if (settings.value.enabled && !settings.value.token) throw new Error('اربط المساعد بهذه الحاسبة أولاً.');
    savePrintSettings(settings.value); settings.value = readPrintSettings(); notice.value = 'حُفظت اختيارات الطابعات لهذا المتصفح على هذه الحاسبة.';
  } catch (e) {error.value = readableError(e);}
}
function browserOnly() {
  settings.value.enabled = false; save(); notice.value = 'الطباعة الاعتيادية مفعّلة لجميع الأنواع. اختياراتك محفوظة لإعادة التفعيل لاحقاً.';
}
async function unpair() {
  if (!window.confirm('إلغاء ربط هذا المتصفح بمساعد الطباعة؟')) return;
  busy.value = true;
  try {await unpairPrintHelper(); settings.value = readPrintSettings(); printers.value = []; notice.value = 'تم إلغاء الربط والعودة للطباعة الاعتيادية.';}
  catch (e) {error.value = readableError(e) + ' يمكنك إلغاء الربط أيضاً من نافذة المساعد.';} finally {busy.value = false;}
}
async function testPrinter(kind, label) {
  busy.value = true; error.value = ''; notice.value = '';
  try {
    const doc = document.implementation.createHTMLDocument('Digital Lab Print test');
    const style = doc.createElement('style'); style.textContent = `@page{size:${kind === 'barcode' ? '50mm 30mm' : kind === 'thermal' ? '80mm auto' : 'A4'};margin:${kind === 'barcode' ? '2' : '5'}mm}body{font-family:Arial,sans-serif;direction:rtl;color:#000;font-size:12px;margin:0}${kind === 'thermal' ? 'body{width:70mm}' : ''}`;
    doc.head.append(style); const title = doc.createElement('h3'); title.textContent = 'Digital Lab Print';
    const text = doc.createElement('p'); text.textContent = 'طباعة تجربة — ' + label;
    doc.body.append(title, text);
    await routePrintDocument(doc, kind, {settings: {...settings.value, enabled: true, routes: {...settings.value.routes, [kind]: {...settings.value.routes[kind], copies: 1}}}, allowFallback: false});
    notice.value = 'تم إرسال ورقة تجربة واحدة إلى قائمة طباعة Windows. تحقق من خروجها ومقاسها قبل اعتماد الطابعة.';
  } catch (e) {error.value = readableError(e);} finally {busy.value = false;}
}
onMounted(() => {if (settings.value.token) refresh();});
</script>
<template>
  <section class="max-w-6xl mx-auto space-y-5" dir="rtl">
    <header class="rounded-2xl bg-slate-900 p-6 text-white flex flex-wrap justify-between gap-4 items-center">
      <div><p class="text-teal-300 text-sm mb-1">Digital Lab Print</p><h1 class="text-2xl font-bold">الطابعات لهذا الجهاز</h1><p class="text-slate-300 text-sm mt-2">اختر الطابعة مرة واحدة لكل نوع. مقاس الورق وتنسيق المستند يبقيان حسب إعداداته الحالية.</p></div>
      <span class="rounded-full px-3 py-1 text-sm" :class="connected ? 'bg-teal-800 text-teal-100' : 'bg-slate-700 text-slate-200'">{{ checking ? 'جارٍ التحقق…' : connected ? 'المساعد متصل' : 'المساعد غير متصل' }}</span>
    </header>
    <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ error }}</p>
    <p v-if="notice" role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-teal-900">{{ notice }}</p>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
      <h2 class="font-bold text-lg">ربط مساعد الطباعة</h2>
      <p class="text-sm text-slate-600 leading-7">ثبّت Digital Lab Print على Windows وافتحه، ثم اضغط «إنشاء رمز ربط». أدخل الرمز هنا ووافق على عنوان موقعك داخل نافذة المساعد. قد يطلب Chrome أو Edge إذن الاتصال المحلي أول مرة.</p>
      <a v-if="downloadUrl" :href="downloadUrl" class="inline-block text-teal-700 underline" target="_blank" rel="noopener">تحميل مساعد الطباعة لـ Windows</a>
      <p v-else class="text-sm text-slate-500">ملف التثبيت: Digital-Lab-Print-Setup-1.0.0.exe — احصل عليه من مسؤول Digital Lab.</p>
      <div class="flex flex-wrap gap-3 items-end">
        <label class="text-sm">رمز الربط<input v-model="code" inputmode="numeric" maxlength="8" autocomplete="off" placeholder="12345678" class="block border border-slate-300 rounded-lg p-2 mt-1 w-48" dir="ltr" /></label>
        <button type="button" @click="pair" :disabled="busy || !/^\d{8}$/.test(code)" class="bg-teal-700 text-white px-4 py-2 rounded-lg disabled:opacity-50">{{ busy ? 'جارٍ التنفيذ…' : 'ربط الحاسبة' }}</button>
        <button type="button" @click="refresh" :disabled="busy || checking" class="border border-slate-300 px-4 py-2 rounded-lg disabled:opacity-50">تحديث الاتصال والطابعات</button>
        <button v-if="settings.token" type="button" @click="unpair" :disabled="busy" class="text-red-700 px-3 py-2">إلغاء الربط</button>
      </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3"><label class="font-semibold flex items-center gap-2"><input v-model="settings.enabled" type="checkbox" :disabled="!settings.token || busy" class="w-5 h-5 accent-teal-700" /> تفعيل الطباعة المباشرة على هذه الحاسبة</label><button type="button" @click="browserOnly" :disabled="busy" class="text-sm border border-slate-300 rounded-lg px-3 py-2">العودة للطباعة الاعتيادية</button></div>
      <p class="text-sm text-slate-500">اترك النوع على «نافذة الطباعة الاعتيادية» إذا تريد اختيار الطابعة عند كل طباعة. إعدادات هذه الصفحة محلية ولا تغيّر إعدادات باقي حاسبات المختبر.</p>
      <div class="grid md:grid-cols-2 gap-4">
        <article v-for="[kind,label] in PRINT_TYPES" :key="kind" class="rounded-xl border border-slate-200 p-4 space-y-3">
          <h3 class="font-bold text-slate-800">{{ label }}</h3>
          <label class="block text-sm text-slate-600">الطابعة<select v-model="settings.routes[kind].printer" :aria-label="'طابعة ' + label" :disabled="busy" class="w-full border border-slate-300 rounded-lg p-2 mt-1"><option value="">نافذة الطباعة الاعتيادية</option><option v-if="settings.routes[kind].printer && !printers.some(p => p.name === settings.routes[kind].printer)" :value="settings.routes[kind].printer">{{ settings.routes[kind].printer }} — غير متاحة حالياً</option><option v-for="printer in printers" :key="printer.name" :value="printer.name">{{ printer.displayName }}</option></select></label>
          <div class="flex flex-wrap justify-between items-end gap-3"><label class="text-sm text-slate-600">عدد النسخ<input v-model.number="settings.routes[kind].copies" type="number" min="1" max="20" :disabled="busy" :aria-label="'نسخ ' + label" class="block w-20 border border-slate-300 rounded-lg p-2 mt-1" /></label><button type="button" @click="testPrinter(kind,label)" :disabled="busy || !settings.token || !settings.routes[kind].printer" :aria-label="'تجربة ' + label" class="border border-teal-700 text-teal-800 rounded-lg px-3 py-2 disabled:opacity-40">طباعة تجربة</button></div>
          <p v-if="kind === 'barcode'" class="text-xs text-slate-500">عدد النسخ هنا يكرر مجموعة الملصقات كاملة. اتركه 1 للتحكم بعدد ملصقات كل عينة من نافذة الباركود. ورقة التجربة 50 × 30 مم.</p>
        </article>
      </div>
      <button type="button" @click="save" :disabled="busy" class="rounded-xl bg-teal-700 text-white px-6 py-3 font-semibold disabled:opacity-50">حفظ اختيارات الطابعات</button>
    </section>
  </section>
</template>
