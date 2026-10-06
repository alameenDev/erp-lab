<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { $http } from '@/plugins/axios';

const role = (() => { try { return Number(JSON.parse(localStorage.getItem('user'))?.role_id); } catch { return 0; } })();
const isAdmin = role === 1, labs = ref([]), labId = ref(''), tab = ref('settings');
const data = ref(null), form = ref(null), loading = ref(false), busy = ref(false), error = ref(''), success = ref('');
const patients = ref([]), search = ref(''), searching = ref(false), selectedPatient = ref('');
const draft = ref({ kind: 'offer', audience: 'all', title: '', body: '' }), review = ref(false);
const history = ref({ data: [], current_page: 1, last_page: 1, total: 0 }), campaigns = ref([]);
const filters = ref({ kind: '', status: '', from: '', to: '' }), expanded = ref(''), attempts = ref([]);
let requestId = '', generation = 0, searchGeneration = 0;
const ready = computed(() => data.value?.health.push_ready && data.value?.settings.enabled);
const canSend = computed(() => ready.value && (draft.value.kind === 'test' || data.value?.settings.announcements_enabled));
const audienceCount = computed(() => draft.value.audience === 'patient' ? (selectedPatient.value ? 1 : 0) : data.value?.stats.offers || 0);
const patientLabel = computed(() => patients.value.find(p => String(p.id) === String(selectedPatient.value))?.name || '');
const statusNames = { queued: 'بانتظار الإرسال', processing: 'قيد الإرسال', accepted: 'قبلته خدمة الإشعارات', failed: 'فشل الإرسال', cancelled: 'ملغى', expired: 'اشتراك منتهي' };
const kindNames = { result: 'جاهزية النتائج', offer: 'إعلان المختبر', test: 'تجريبي' };
const campaignNames = { queued: 'بانتظار التجهيز', processing: 'جاري تجهيز المستلمين', completed: 'اكتملت جدولة المستلمين', cancelled: 'أُوقف المتبقي' };
const reasonNames = { attempts_exhausted: 'نفدت المحاولات التلقائية', message_expired: 'انتهت مهلة إرسال الرسالة', lab_disabled: 'متوقف من إعدادات المختبر',
  campaign_cancelled: 'الحملة متوقفة', patient_opted_out: 'المريض أوقف هذا النوع', access_invalid: 'رابط البوابة منتهي أو يحتاج تحققاً',
  report_not_ready: 'التقرير لم يعد مكتملاً', subscription_expired: 'اشتراك الجهاز منتهي', subscription_inactive: 'اشتراك الجهاز غير متاح', provider_unavailable: 'تعذر الاتصال بخدمة الإشعارات' };
const date = value => value ? new Intl.DateTimeFormat('ar-IQ', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const params = extra => ({ ...(isAdmin ? { lab_id: labId.value } : {}), ...extra });
const message = e => Object.values(e.response?.data?.errors || {}).flat().join(' — ') || e.response?.data?.message || 'تعذر الاتصال. حاول مجدداً.';
const clear = () => { error.value = ''; success.value = ''; };

async function loadHistory(page = 1) {
  const current = generation;
  const [h, c] = await Promise.all([
    $http.get('/lab-notifications/history', { params: params({ ...filters.value, page }) }),
    $http.get('/lab-notifications/campaigns', { params: params() }),
  ]);
  if (current === generation) { history.value = h.data; campaigns.value = c.data; expanded.value = ''; }
}
async function refresh(resetForm = false) {
  if (isAdmin && !labId.value) return;
  const current = generation; loading.value = true; error.value = '';
  try {
    const response = await $http.get('/lab-notifications/settings', { params: params() });
    if (current !== generation) return;
    data.value = response.data;
    if (resetForm || !form.value) form.value = { ...response.data.settings };
    await loadHistory();
  } catch (e) { if (current === generation) error.value = message(e); }
  finally { if (current === generation) loading.value = false; }
}
async function save() {
  busy.value = true; clear();
  try {
    const response = await $http.put('/lab-notifications/settings', { ...form.value, ...params() });
    form.value = { ...response.data.settings }; data.value.settings = { ...form.value };
    success.value = 'تم حفظ إعدادات إشعارات المختبر.';
  } catch (e) { error.value = message(e); }
  finally { busy.value = false; }
}
async function findPatients() {
  const current = ++searchGeneration, lab = generation; searching.value = true;
  try {
    const response = await $http.get('/lab-notifications/patients', { params: params({ q: search.value, kind: draft.value.kind }) });
    if (current === searchGeneration && lab === generation) patients.value = response.data;
  } catch (e) { if (current === searchGeneration && lab === generation) error.value = message(e); }
  finally { if (current === searchGeneration && lab === generation) searching.value = false; }
}
watch(() => [draft.value.kind, draft.value.audience], () => {
  review.value = false; selectedPatient.value = ''; patients.value = []; search.value = '';
  if (draft.value.kind === 'test' && draft.value.audience !== 'patient') { draft.value.audience = 'patient'; return; }
  if (draft.value.audience === 'patient' && data.value) void findPatients();
});
watch([draft, selectedPatient], () => { requestId = ''; review.value = false; }, { deep: true });
watch(labId, () => {
  generation++; searchGeneration++; data.value = null; form.value = null; patients.value = []; selectedPatient.value = '';
  history.value = { data: [], current_page: 1, last_page: 1, total: 0 }; campaigns.value = []; review.value = false;
  requestId = ''; clear(); void refresh(true);
});
function preview() {
  clear();
  if (!canSend.value) { error.value = 'فعّل خدمة الإشعارات وهذا النوع من الإعدادات أولاً.'; return; }
  if (!audienceCount.value) { error.value = 'اختر مريضاً مشتركاً أو تحقق من وجود مشتركين بالإعلانات.'; return; }
  review.value = true;
}
async function send() {
  if (busy.value || !review.value) return;
  busy.value = true; clear(); requestId ||= crypto.randomUUID();
  try {
    const response = await $http.post('/lab-notifications/campaigns', {
      ...params(), ...draft.value, patient_id: draft.value.audience === 'patient' ? Number(selectedPatient.value) : null, request_id: requestId,
    });
    success.value = response.data.message; review.value = false; requestId = '';
    await refresh();
  } catch (e) { error.value = message(e); } // keep request_id for a safe retry after a lost response
  finally { busy.value = false; }
}
async function cancel(id) {
  busy.value = true; clear();
  try {
    const response = await $http.post('/lab-notifications/campaigns/' + id + '/cancel', params());
    success.value = response.data.message; await refresh();
  } catch (e) { error.value = message(e); }
  finally { busy.value = false; }
}
async function details(id) {
  if (expanded.value === id) { expanded.value = ''; return; }
  busy.value = true; error.value = '';
  try { const response = await $http.get('/lab-notifications/history/' + id, { params: params() }); attempts.value = response.data; expanded.value = id; }
  catch (e) { error.value = message(e); }
  finally { busy.value = false; }
}
async function retry(id) {
  busy.value = true; clear();
  try {
    await $http.post('/lab-notifications/history/' + id + '/retry', params());
    success.value = 'أُعيدت جدولة المحاولات الفاشلة. يُفحص سماح المريض وجاهزية التقرير قبل الإرسال.'; await refresh();
  } catch (e) { error.value = message(e); }
  finally { busy.value = false; }
}
async function filterHistory(page = 1) {
  loading.value = true; error.value = '';
  try { await loadHistory(page); } catch (e) { error.value = message(e); } finally { loading.value = false; }
}
onMounted(async () => {
  if (isAdmin) {
    try { labs.value = (await $http.get('/lab-notifications/labs')).data; } catch (e) { error.value = message(e); }
  } else await refresh(true);
});
</script>

<template>
<section class="notification-center" dir="rtl" aria-label="إدارة إشعارات المختبر">
  <header class="nc-header">
    <div><span class="nc-eyebrow">بوابة المريض</span><h2>إشعارات المختبر</h2><p>أبلغ مرضاك بجاهزية النتائج وتابع رسائلك من مكان واحد.</p></div>
    <button type="button" class="nc-button" :disabled="loading || busy || (isAdmin && !labId)" @click="refresh()"><i class="pi pi-refresh" aria-hidden="true"></i> تحديث الحالة</button>
  </header>
  <label v-if="isAdmin" class="nc-lab">المختبر
    <select v-model="labId" aria-label="المختبر" :disabled="busy"><option value="">اختر المختبر لإدارة إشعاراته</option><option v-for="lab in labs" :key="lab.id" :value="lab.id">{{ lab.name }}</option></select>
  </label>
  <p v-if="error" class="nc-feedback error" role="alert">{{ error }}</p>
  <p v-if="success" class="nc-feedback success" role="status">{{ success }}</p>
  <p v-if="loading && !data" class="nc-empty" role="status">جاري تحميل إعدادات الإشعارات…</p>
  <template v-if="data && form">
    <div class="nc-health">
      <div><strong>{{ data.lab.name }}</strong><span :class="['nc-dot', ready ? 'on' : 'off']"></span>{{ ready ? 'خدمة الإشعارات مفعّلة' : 'خدمة الإشعارات متوقفة' }}</div>
      <small>آخر تشغيل للإرسال: {{ date(data.health.last_worker_run) }}</small>
    </div>
    <p v-if="!data.health.push_ready" class="nc-feedback warning">مفاتيح خدمة الإشعارات غير مجهزة على الخادم. يلزم إكمال إعداد الخدمة قبل الإرسال.</p>
    <p v-else-if="!data.health.worker_recent" class="nc-feedback warning">لم نرصد معالجة حديثة لطابور الإرسال. تأكد من تشغيل مهمة الإرسال كل دقيقة في الاستضافة، ثم حدّث الحالة.</p>
    <div class="nc-stats">
      <div><span>مرضى مشتركون</span><strong>{{ data.stats.patients }}</strong><small>{{ data.stats.subscriptions }} اشتراك جهاز صالح</small></div>
      <div><span>تنبيهات النتائج</span><strong>{{ data.stats.results }}</strong><small>مريض وافق على النتائج</small></div>
      <div><span>إعلانات المختبر</span><strong>{{ data.stats.offers }}</strong><small>مريض وافق على الإعلانات</small></div>
      <div><span>بانتظار الإرسال</span><strong>{{ data.stats.queued }}</strong><small>{{ data.stats.failed }} محاولة فاشلة</small></div>
    </div>
    <nav class="nc-tabs" aria-label="أقسام الإشعارات">
      <button v-for="item in [{id:'settings',name:'الإعدادات'},{id:'send',name:'إرسال إشعار'},{id:'history',name:'سجل الإرسال'}]" :key="item.id" type="button" :class="{selected:tab === item.id}" :aria-current="tab === item.id ? 'page' : undefined" @click="tab = item.id">{{ item.name }}</button>
    </nav>

    <div v-if="tab === 'settings'" class="nc-columns">
      <form class="nc-card" @submit.prevent="save">
        <h3>التحكم بالإشعارات</h3>
        <label class="nc-toggle"><span><b>إشعارات بوابة المريض</b><small>إيقافها يمنع الإرسال على الأجهزة دون إلغاء اشتراكات المرضى.</small></span><input v-model="form.enabled" type="checkbox" role="switch" aria-label="إشعارات بوابة المريض" :disabled="busy"></label>
        <label class="nc-toggle"><span><b>تنبيه جاهزية النتائج تلقائياً</b><small>بعد اعتماد جميع التحاليل والكروبات والباقات وصيرورة التقرير جاهزاً للعرض.</small></span><input v-model="form.results_enabled" type="checkbox" role="switch" aria-label="تنبيه جاهزية النتائج تلقائياً" :disabled="busy"></label>
        <label class="nc-toggle"><span><b>إعلانات المختبر</b><small>رسائل يدوية إلى المرضى الموافقين على إعلانات المختبر فقط.</small></span><input v-model="form.announcements_enabled" type="checkbox" role="switch" aria-label="إعلانات المختبر" :disabled="busy"></label>
        <div class="nc-divider"></div><h3>رسالة اكتمال الفحوصات</h3>
        <label>عنوان الإشعار<input v-model="form.result_title" required maxlength="120" :disabled="busy"></label>
        <label>نص إشعار الجاهزية<textarea v-model="form.result_body" required maxlength="500" rows="4" :disabled="busy"></textarea><small>{{ form.result_body.length }}/500</small></label>
        <p class="nc-hint">الضغط على الإشعار يفتح بوابة المريض الخاصة به. استخدم نصاً عاماً لأن الإشعار قد يظهر على شاشة القفل.</p>
        <div class="nc-actions"><button class="nc-button primary" :disabled="busy">{{ busy ? 'جاري الحفظ…' : 'حفظ إعدادات الإشعارات' }}</button><button type="button" class="nc-link" :disabled="busy" @click="form.result_title = data.defaults.result_title; form.result_body = data.defaults.result_body">استعادة النص الافتراضي</button></div>
      </form>
      <aside class="nc-card nc-preview">
        <span class="nc-eyebrow">معاينة الإشعار</span>
        <div class="nc-notification"><i class="pi pi-bell" aria-hidden="true"></i><div><small>{{ data.lab.name }} · الآن</small><h4>{{ form.result_title || 'عنوان الإشعار' }}</h4><p>{{ form.result_body || 'نص الإشعار' }}</p></div></div>
        <h3>متى يستلم المريض التنبيه؟</h3>
        <ol class="nc-steps"><li>اكتمال واعتماد جميع الفحوصات.</li><li>جاهزية التقرير للعرض داخل البوابة.</li><li>إضافة إشعار واحد إلى طابور الإرسال.</li><li>إرساله للأجهزة المشتركة بعد إعادة فحص الصلاحية.</li></ol>
        <p class="nc-hint">حفظ التقرير المكتمل مجدداً لا يكرر التنبيه. وصول قيم من الجهاز وحده لا يتجاوز اعتماد نتائج التقرير.</p>
      </aside>
    </div>

    <div v-if="tab === 'send'" class="nc-columns">
      <form class="nc-card" @submit.prevent="preview">
        <h3>رسالة جديدة</h3>
        <label>نوع الإشعار<select v-model="draft.kind" aria-label="نوع الإشعار" :disabled="busy"><option value="offer">إعلان للمختبر</option><option value="test">إشعار تجريبي لمريض واحد</option></select></label>
        <label>المستلمون<select v-model="draft.audience" :disabled="busy || draft.kind === 'test'"><option value="all">جميع المشتركين بإعلانات هذا المختبر</option><option value="patient">مريض محدد</option></select></label>
        <div v-if="draft.audience === 'patient'" class="nc-recipient">
          <label>بحث باسم المريض أو رقم ملفه<div class="nc-search"><input v-model="search" placeholder="اكتب الاسم أو رقم الملف" :disabled="busy" @keydown.enter.prevent="findPatients"><button type="button" class="nc-button" :disabled="searching || busy" @click="findPatients">{{ searching ? 'بحث…' : 'بحث' }}</button></div></label>
          <label>المريض المشترك<select v-model="selectedPatient" aria-label="المريض المشترك" required :disabled="busy"><option value="">اختر المريض</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.name }} · {{ patient.code }} · {{ patient.devices }} جهاز</option></select></label>
          <small>تظهر الاشتراكات المسموح لها بهذا النوع، ذات رابط بوابة صالح.</small>
        </div>
        <label>عنوان الرسالة<input v-model="draft.title" required maxlength="120" :disabled="busy" placeholder="مثال: إعلان من المختبر"></label>
        <label>نص الرسالة<textarea v-model="draft.body" aria-label="نص الرسالة" required maxlength="500" rows="5" :disabled="busy" placeholder="اكتب رسالتك للمرضى…"></textarea><small>{{ draft.body.length }}/500</small></label>
        <p v-if="!canSend" class="nc-feedback warning">هذا النوع متوقف أو خدمة الإشعارات غير جاهزة. راجع الإعدادات أولاً.</p>
        <button class="nc-button primary" :disabled="busy || !canSend">مراجعة الرسالة والمستلمين</button>
      </form>
      <aside class="nc-card nc-preview">
        <span class="nc-eyebrow">معاينة قبل الإرسال</span>
        <div class="nc-notification"><i class="pi pi-bell" aria-hidden="true"></i><div><small>{{ data.lab.name }} · الآن</small><h4>{{ draft.title || 'عنوان الرسالة' }}</h4><p>{{ draft.body || 'سيظهر نص رسالتك هنا.' }}</p></div></div>
        <div v-if="review" class="nc-confirm">
          <h3>تأكيد الإرسال</h3><p>{{ draft.audience === 'patient' ? patientLabel : 'جميع المشتركين بإعلانات المختبر' }}</p>
          <strong>{{ audienceCount }} مريض</strong><small>عدد متوقع؛ يُعاد فحص السماح وصلاحية الرابط قبل الإرسال لكل جهاز.</small>
          <button type="button" class="nc-button primary" :disabled="busy" @click="send">{{ busy ? 'جاري جدولة الإرسال…' : 'تأكيد وإرسال الإشعار' }}</button>
          <button type="button" class="nc-link" :disabled="busy" @click="review = false">الرجوع لتعديل الرسالة</button>
        </div>
        <p class="nc-hint">تفتح الرسالة بوابة المستلم نفسه. يُرسل الإعلان فقط لمن وافق عليه؛ يمكن للمريض إيقافه من إعداداته.</p>
      </aside>
    </div>

    <div v-if="tab === 'history'" class="nc-history">
      <section class="nc-card">
        <div class="nc-section-heading"><h3>طلبات الإرسال الأخيرة</h3><span>آخر 15 طلباً</span></div>
        <p v-if="!campaigns.length" class="nc-empty">لم تُرسل حملات من هذه الصفحة بعد.</p>
        <article v-for="campaign in campaigns" :key="campaign.id" class="nc-campaign">
          <div><b>{{ campaign.title }}</b><small>{{ date(campaign.created_at) }} · بواسطة {{ campaign.actor_name }}</small><small>{{ campaign.queued_patients }} مريض تمت جدولته من {{ campaign.audience_count }} عند الطلب</small></div>
          <span class="nc-badge">{{ campaignNames[campaign.status] }}</span>
          <button v-if="campaign.status !== 'cancelled'" type="button" class="nc-link danger" :disabled="busy" @click="cancel(campaign.id)">إيقاف المتبقي</button>
        </article>
      </section>
      <section class="nc-card">
        <div class="nc-section-heading"><h3>سجل إشعارات المرضى</h3><span>{{ history.total }} إشعار</span></div>
        <form class="nc-filters" @submit.prevent="filterHistory()">
          <label>نوع الرسالة<select v-model="filters.kind"><option value="">كل الأنواع</option><option v-for="(label, key) in kindNames" :key="key" :value="key">{{ label }}</option></select></label>
          <label>حالة المحاولة<select v-model="filters.status"><option value="">كل الحالات</option><option v-for="(label, key) in statusNames" :key="key" :value="key">{{ label }}</option></select></label>
          <label>من تاريخ<input v-model="filters.from" type="date"></label><label>إلى تاريخ<input v-model="filters.to" type="date"></label>
          <button class="nc-button" :disabled="loading">تطبيق الفلتر</button>
        </form>
        <p class="nc-hint">«قبلته خدمة الإشعارات» تعني قبول مزوّد الدفع، ولا تؤكد ظهوره أو قراءته على الجهاز. «فُتح في البوابة» يخص قائمة الإشعارات داخل البوابة.</p>
        <p v-if="!history.data.length" class="nc-empty">لا توجد إشعارات مطابقة لهذا الاختيار.</p>
        <article v-for="notice in history.data" :key="notice.id" class="nc-notice">
          <div class="nc-section-heading"><div><span class="nc-type">{{ kindNames[notice.kind] }}</span><h4>{{ notice.title }}</h4></div><small>{{ date(notice.created_at) }}</small></div>
          <p>{{ notice.body }}</p><div class="nc-patient"><b>{{ notice.patient_name }}</b><span>ملف {{ notice.patient_code || '—' }}</span><span v-if="notice.invoice_id">فاتورة {{ notice.invoice_id }} <span v-if="notice.invoice_barcode">· {{ notice.invoice_barcode }}</span></span></div>
          <div class="nc-badges"><template v-for="(count, status) in notice.counts" :key="status"><span v-if="count" :class="['nc-badge', status]">{{ statusNames[status] }}: {{ count }}</span></template><span v-if="!Object.values(notice.counts).some(Boolean)" class="nc-badge">في البوابة فقط · لا يوجد جهاز مستلم</span><span v-if="notice.read_at" class="nc-badge read">فُتح في البوابة: {{ date(notice.read_at) }}</span></div>
          <div class="nc-actions"><button type="button" class="nc-link" :disabled="busy" @click="details(notice.id)">{{ expanded === notice.id ? 'إخفاء التفاصيل' : 'تفاصيل المحاولات' }}</button><button v-if="notice.counts.failed" type="button" class="nc-link" :disabled="busy" @click="retry(notice.id)">إعادة المحاولات الفاشلة</button></div>
          <div v-if="expanded === notice.id" class="nc-attempts">
            <p v-if="!attempts.length" class="nc-hint">لا توجد محاولات دفع لهذا الإشعار.</p>
            <div v-for="attempt in attempts" :key="attempt.id"><b>{{ statusNames[attempt.status] }}</b><span>{{ attempt.attempts }} محاولة</span><small>{{ reasonNames[attempt.status_reason] || '—' }}</small><small>آخر تحديث: {{ date(attempt.updated_at) }}</small><small v-if="attempt.next_attempt_at">المحاولة التالية: {{ date(attempt.next_attempt_at) }}</small></div>
          </div>
        </article>
        <div class="nc-pagination"><button type="button" class="nc-button" :disabled="loading || history.current_page <= 1" @click="filterHistory(history.current_page - 1)">السابق</button><span>{{ history.current_page }} / {{ history.last_page }}</span><button type="button" class="nc-button" :disabled="loading || history.current_page >= history.last_page" @click="filterHistory(history.current_page + 1)">التالي</button></div>
      </section>
    </div>
  </template>
</section>
</template>

<style scoped>
.notification-center{--nc-accent:#0f766e;color:#172b3a;min-width:0}
.nc-header,.nc-health,.nc-section-heading,.nc-actions,.nc-campaign,.nc-pagination{display:flex;align-items:center;justify-content:space-between;gap:16px}
.nc-header{margin:8px 0 24px}.nc-header h2{font-size:26px;font-weight:800;margin:4px 0 8px}.nc-header p,.nc-hint{color:#64748b;font-size:13px;line-height:1.9}
.nc-eyebrow{font-size:12px;font-weight:700;color:var(--nc-accent)}.nc-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 17px;border:1px solid #dce5e9;border-radius:11px;background:white;font:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}.nc-button.primary{background:var(--nc-accent);color:white;border-color:var(--nc-accent)}button:disabled{opacity:.5;cursor:not-allowed}.nc-link{font:inherit;font-size:13px;color:var(--nc-accent);font-weight:700;background:none;border:none;cursor:pointer}.nc-link.danger{color:#b45309}
.nc-health{padding:15px 20px;border:1px solid #dce5e9;border-radius:14px;background:white;flex-wrap:wrap;font-size:13px}.nc-health>div{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.nc-health strong{margin-inline-end:8px}.nc-health small{color:#64748b}.nc-dot{width:8px;height:8px;border-radius:50%}.nc-dot.on{background:#10b981}.nc-dot.off{background:#f59e0b}
.nc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0 24px}.nc-stats>div{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px}.nc-stats span,.nc-stats small{display:block;font-size:12px;color:#64748b}.nc-stats strong{display:block;font-size:28px;font-weight:800;margin:5px 0;color:#0f766e}
.nc-tabs{display:flex;gap:6px;border-bottom:1px solid #dce5e9;margin-bottom:22px;overflow:auto}.nc-tabs button{padding:13px 20px;font:inherit;font-size:14px;font-weight:700;white-space:nowrap;color:#64748b;border:0;border-bottom:3px solid transparent;background:none;cursor:pointer}.nc-tabs button.selected{border-bottom-color:#0f766e;color:#0f766e}
.nc-columns{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:22px;align-items:start}.nc-card{background:white;border:1px solid #e2e8f0;border-radius:18px;padding:24px;min-width:0}.nc-card h3{font-size:17px;font-weight:800;margin:0 0 18px}.nc-card label,.nc-lab{display:block;font-weight:600;font-size:13px;margin-bottom:18px}.nc-card input:not([type=checkbox]),.nc-card textarea,.nc-card select,.nc-lab select{display:block;width:100%;box-sizing:border-box;border:1px solid #d7e0e6;border-radius:10px;padding:11px 12px;margin-top:8px;color:#172b3a;background:white;font:inherit;font-weight:400;min-width:0}.nc-card textarea{line-height:1.9;resize:vertical}.nc-card input:focus,.nc-card textarea:focus,.nc-card select:focus{outline:2px solid #99d7d0;outline-offset:1px}.nc-card label>small,.nc-recipient>small{display:block;font-size:11px;color:#64748b;margin-top:6px}
.nc-toggle{display:flex!important;align-items:center;justify-content:space-between;gap:24px;padding:10px 0 20px;border-bottom:1px solid #f1f5f9}.nc-toggle span{min-width:0}.nc-toggle b{font-size:14px}.nc-toggle small{display:block;color:#64748b;font-weight:400;font-size:12px;line-height:1.9;margin-top:5px}.nc-toggle input{appearance:none;flex-shrink:0;width:42px;height:24px;border-radius:20px;background:#cbd5e1;cursor:pointer;position:relative;transition:background .15s}.nc-toggle input:before{content:'';position:absolute;width:18px;height:18px;top:3px;left:3px;border-radius:50%;background:white;box-shadow:0 1px 3px #0002;transition:transform .15s}.nc-toggle input:checked{background:#0f766e}.nc-toggle input:checked:before{transform:translateX(18px)}.nc-divider{height:1px;background:#e2e8f0;margin:24px 0}
.nc-preview{background:#f7faf9}.nc-notification{display:flex;gap:12px;background:white;border:1px solid #dce5e9;padding:18px;border-radius:15px;box-shadow:0 8px 22px #0f766e08;margin:16px 0 28px;overflow-wrap:anywhere}.nc-notification>i{font-size:20px;padding:10px;border-radius:12px;background:#e3f3ef;color:#0f766e;align-self:flex-start}.nc-notification small{color:#64748b;font-size:11px}.nc-notification h4,.nc-notice h4{font-size:15px;font-weight:800;margin:8px 0}.nc-notification p,.nc-notice>p{font-size:13px;line-height:1.9;margin:0;white-space:pre-wrap}.nc-steps{padding-inline-start:22px;color:#334155;font-size:13px;line-height:2.3}.nc-actions{justify-content:flex-start;flex-wrap:wrap;margin-top:18px}.nc-feedback{border-radius:12px;padding:12px 16px;font-size:13px;line-height:1.8;margin:14px 0}.nc-feedback.error{background:#fff1f2;color:#9f1239}.nc-feedback.success{background:#ecfdf5;color:#047857}.nc-feedback.warning{background:#fffbeb;color:#92400e}.nc-empty{text-align:center;padding:28px 12px;color:#64748b;font-size:14px}
.nc-search{display:flex;gap:8px;align-items:end}.nc-search input{flex:1}.nc-recipient{padding:15px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:18px}.nc-confirm{border:1px solid #b8dcd6;border-radius:14px;padding:18px;background:#edf8f5;display:grid;gap:14px}.nc-confirm h3{margin:0}.nc-confirm small{line-height:1.8;color:#475569}.nc-confirm strong{font-size:24px;color:#0f766e}.nc-history{display:grid;gap:20px}.nc-section-heading{align-items:flex-start;flex-wrap:wrap}.nc-section-heading h3{margin:0}.nc-section-heading>span,.nc-section-heading>small{color:#64748b;font-size:12px}
.nc-campaign{border-top:1px solid #e2e8f0;padding:18px 0;flex-wrap:wrap}.nc-campaign:first-of-type{margin-top:16px}.nc-campaign>div{flex:1;min-width:190px}.nc-campaign b{font-size:14px}.nc-campaign small{display:block;color:#64748b;font-size:11px;margin-top:6px}.nc-badge{display:inline-block;background:#f1f5f9;color:#475569;border-radius:8px;padding:5px 9px;font-size:11px}.nc-badge.accepted,.nc-badge.read{background:#e5f5ee;color:#166534}.nc-badge.failed,.nc-badge.expired{background:#fff1f2;color:#9f1239}.nc-badge.queued,.nc-badge.processing{background:#fff7df;color:#854d0e}.nc-filters{display:flex;align-items:end;gap:12px;margin:22px 0;flex-wrap:wrap}.nc-filters label{flex:1;min-width:135px;margin:0}.nc-filters select,.nc-filters input{padding:9px!important;font-size:12px!important}.nc-notice{padding:20px 0;border-top:1px solid #e2e8f0;overflow-wrap:anywhere}.nc-type{font-size:11px;font-weight:700;color:#0f766e}.nc-patient,.nc-badges{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:14px;font-size:12px}.nc-patient>span{color:#64748b}.nc-attempts{margin-top:16px;display:grid;gap:8px}.nc-attempts>div{display:flex;align-items:center;flex-wrap:wrap;gap:10px;padding:12px;background:#f8fafc;border-radius:10px;font-size:12px}.nc-attempts small{color:#64748b}.nc-pagination{justify-content:center;padding-top:20px;font-size:13px}
@media(max-width:800px){.nc-columns{grid-template-columns:1fr}.nc-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.nc-header{align-items:flex-start;flex-direction:column}.nc-card{padding:18px}.nc-health{padding:14px}.nc-header h2{font-size:23px}.nc-stats>div{padding:14px}.nc-stats strong{font-size:25px}.nc-tabs button{padding:12px 15px}.nc-filters label{min-width:120px}.nc-toggle{gap:12px}}
</style>
