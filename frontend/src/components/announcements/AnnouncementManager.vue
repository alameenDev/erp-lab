<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import { announcementsApi as api, announcementDate, announcementError, newAnnouncementRequestId, safeAnnouncementUrl } from '@/utils/labAnnouncements';

const props = defineProps({ visible: Boolean });
const emit = defineEmits(['update:visible']);
const labs = ref([]), query = ref(''), busy = ref(false), loading = ref(false);
const error = ref(''), success = ref(''), confirming = ref(false), history = ref(null);
const tab = ref('compose');
const emptyForm = () => ({ title: '', body: '', level: 'info', audience: 'selected', lab_ids: [], button_label: '', button_url: '', expires_at: '' });
const form = ref(emptyForm());
let requestId = newAnnouncementRequestId();
let disposed = false;
const controller = new AbortController();
const options = () => ({ signal: controller.signal });
const text = (ar, en) => localStorage.getItem('locale') === 'en' ? en : ar;
const filteredLabs = computed(() => labs.value.filter((lab) => lab.name.toLowerCase().includes(query.value.trim().toLowerCase())));
const recipientCount = computed(() => form.value.audience === 'all' ? labs.value.length : form.value.lab_ids.length);
const recipientNames = computed(() => labs.value.filter((lab) => form.value.lab_ids.includes(lab.id)).map((lab) => lab.name).join('، '));
watch(form, () => { requestId = newAnnouncementRequestId(); success.value = ''; }, { deep: true, flush: 'sync' });

async function loadLabs() {
  loading.value = true; error.value = '';
  try { const { data } = await api.get('/lab-announcements/labs', options()); if (!disposed) labs.value = data; }
  catch (e) { if (!disposed) error.value = announcementError(e); }
  finally { if (!disposed) loading.value = false; }
}
async function loadHistory(page = 1) {
  loading.value = true; error.value = '';
  try { const { data } = await api.get('/lab-announcements/history', { ...options(), params: { page } }); if (!disposed) history.value = data; }
  catch (e) { if (!disposed) error.value = announcementError(e); }
  finally { if (!disposed) loading.value = false; }
}
watch(() => props.visible, (visible) => { if (visible) { tab.value = 'compose'; loadLabs(); } });
function review() {
  error.value = ''; success.value = '';
  if (!form.value.title.trim() || !form.value.body.trim() || !recipientCount.value) {
    error.value = text('أدخل العنوان والنص واختر مختبراً واحداً على الأقل.', 'Enter a title, message and at least one recipient.'); return;
  }
  if (form.value.button_url && !safeAnnouncementUrl(form.value.button_url)) {
    error.value = text('الرابط يجب أن يبدأ بـ https:// أو http://.', 'Only HTTPS or HTTP links are allowed.'); return;
  }
  if (Boolean(form.value.button_label.trim()) !== Boolean(form.value.button_url.trim())) {
    error.value = text('أدخل نص الزر والرابط معاً، أو اتركهما فارغين.', 'Provide both a button label and URL, or leave both empty.'); return;
  }
  if (form.value.expires_at && new Date(form.value.expires_at).getTime() <= Date.now()) {
    error.value = text('اختر تاريخ انتهاء مستقبلياً.', 'Choose a future expiry date.'); return;
  }
  confirming.value = true;
}
async function send() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  const payload = { ...form.value, request_id: requestId,
    title: form.value.title.trim(), body: form.value.body.trim(),
    button_label: form.value.button_label.trim() || null, button_url: form.value.button_url.trim() || null,
    expires_at: form.value.expires_at ? new Date(form.value.expires_at).toISOString() : null };
  if (payload.audience === 'all') delete payload.lab_ids;
  try {
    const { data } = await api.post('/lab-announcements', payload, options());
    if (disposed) return;
    form.value = emptyForm();
    success.value = text(`تم إرسال الإشعار إلى ${data.target_count} مختبر.`, `Notification sent to ${data.target_count} labs.`);
    confirming.value = false;
  } catch (e) {
    if (!disposed) { error.value = announcementError(e); confirming.value = false; }
    // Preserve both the draft and its key after a timeout: an unchanged retry is safe.
  } finally { if (!disposed) busy.value = false; }
}
async function cancelNotice(notice) {
  if (!window.confirm(text('إيقاف ظهور هذا الإشعار؟ لا يمكن سحب ما سبق أن شاهده المستخدم.', 'Stop showing this notification? Already viewed messages cannot be recalled.'))) return;
  busy.value = true; error.value = '';
  try { await api.post(`/lab-announcements/${notice.id}/cancel`, {}, options()); if (!disposed) await loadHistory(history.value?.current_page || 1); }
  catch (e) { if (!disposed) error.value = announcementError(e); }
  finally { if (!disposed) busy.value = false; }
}
function close() { if (!busy.value) { confirming.value = false; emit('update:visible', false); } }
onBeforeUnmount(() => { disposed = true; controller.abort(); });
</script>

<template>
  <Dialog :visible="visible" @update:visible="close" modal :closable="!busy" :closeOnEscape="!busy" :dismissableMask="false"
    :header="text('إشعارات المختبرات', 'Lab notifications')" :style="{ width: '56rem', maxWidth: '95vw' }"
    :pt="{ mask: { class: 'lab-announcement-mask' } }">
    <div :dir="text('rtl', 'ltr')" class="announcement-manager">
      <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" class="notice-secondary" :disabled="busy" @click="tab = 'compose'">{{ text('إرسال إشعار', 'Compose') }}</button>
        <button type="button" class="notice-secondary" :disabled="busy || loading" @click="tab = 'history'; loadHistory()">{{ text('سجل الإشعارات', 'Sent history') }}</button>
      </div>
      <p v-if="error" role="alert" class="notice-error">{{ error }}</p>
      <p v-if="success" role="status" class="notice-success">{{ success }}</p>
      <p v-if="loading" role="status">{{ text('جارٍ التحميل…', 'Loading…') }}</p>
      <form v-if="tab === 'compose'" @submit.prevent="review">
        <fieldset :disabled="busy || confirming || loading" class="notice-fields">
          <label>{{ text('عنوان الإشعار', 'Title') }}<input v-model="form.title" required maxlength="200" data-testid="notice-title" /></label>
          <label>{{ text('تفاصيل الإشعار', 'Message') }}<textarea v-model="form.body" required maxlength="10000" rows="6" data-testid="notice-body"></textarea></label>
          <div class="notice-grid">
            <label>{{ text('نوع الإشعار', 'Type') }}
              <select v-model="form.level"><option value="info">{{ text('معلومة', 'Information') }}</option><option value="success">{{ text('تحديث جديد', 'Update') }}</option><option value="warning">{{ text('تنبيه', 'Warning') }}</option><option value="urgent">{{ text('عاجل', 'Urgent') }}</option></select>
            </label>
            <label>{{ text('تاريخ الانتهاء — اختياري', 'Expiry — optional') }}<input v-model="form.expires_at" type="datetime-local" /></label>
          </div>
          <fieldset class="notice-audience">
            <legend>{{ text('المختبرات المستلمة', 'Recipients') }}</legend>
            <label class="notice-inline"><input v-model="form.audience" type="radio" value="selected" />{{ text('مختبر واحد أو عدة مختبرات', 'Selected labs') }}</label>
            <label class="notice-inline"><input v-model="form.audience" type="radio" value="all" data-testid="notice-all" />{{ text('جميع المختبرات الحالية', 'All current labs') }} ({{ labs.length }})</label>
            <template v-if="form.audience === 'selected'">
              <input v-model="query" type="search" :placeholder="text('ابحث باسم المختبر', 'Search labs')" :aria-label="text('البحث عن مختبر', 'Search labs')" />
              <div class="notice-lab-list">
                <label v-for="lab in filteredLabs" :key="lab.id" class="notice-inline"><input v-model="form.lab_ids" type="checkbox" :value="lab.id" :data-testid="`notice-lab-${lab.id}`" />{{ lab.name }}</label>
                <p v-if="!filteredLabs.length && !loading">{{ text('لا توجد مختبرات مطابقة.', 'No matching labs.') }}</p>
              </div>
            </template>
            <strong>{{ text('عدد المختبرات المحددة:', 'Selected labs:') }} {{ recipientCount }}</strong>
          </fieldset>
          <div class="notice-grid">
            <label>{{ text('نص زر الرابط — اختياري', 'Link label — optional') }}<input v-model="form.button_label" maxlength="80" /></label>
            <label>{{ text('الرابط — اختياري', 'URL — optional') }}<input v-model="form.button_url" type="url" maxlength="2048" placeholder="https://" dir="ltr" /></label>
          </div>
          <details class="notice-preview"><summary>{{ text('معاينة الإشعار', 'Preview notification') }}</summary><h3>{{ form.title }}</h3><p class="notice-body">{{ form.body }}</p><span v-if="form.button_label">{{ form.button_label }}</span></details>
          <p class="text-sm text-slate-500">{{ text('يظهر عند الدخول، وخلال ثوانٍ للمستخدم الموجود داخل الموقع. لا يتطلب سماح إشعارات المتصفح.', 'Shown on sign-in and within seconds while the site is open. Browser notification permission is not required.') }}</p>
          <button type="submit" class="notice-primary" :disabled="!recipientCount" data-testid="notice-review">{{ text('مراجعة الإرسال', 'Review and send') }}</button>
        </fieldset>
      </form>
      <div v-else>
        <p v-if="history && !history.data.length">{{ text('لا توجد إشعارات مرسلة بعد.', 'No sent notifications yet.') }}</p>
        <article v-for="notice in history?.data || []" :key="notice.id" class="notice-history-item">
          <h3 class="font-bold">{{ notice.title }}</h3><p class="notice-body">{{ notice.body }}</p>
          <p class="text-sm">{{ announcementDate(notice.created_at) }} · {{ text('المستلمون:', 'Recipients:') }} {{ notice.target_count }} · {{ text('مختبرات أكدت القراءة:', 'Labs acknowledging:') }} {{ notice.read_lab_count }}</p>
          <p v-if="notice.expires_at">{{ text('ينتهي:', 'Expires:') }} {{ announcementDate(notice.expires_at) }}</p>
          <strong v-if="notice.cancelled_at">{{ text('متوقف', 'Stopped') }}</strong>
          <button v-else type="button" class="notice-secondary" :disabled="busy" @click="cancelNotice(notice)">{{ text('إيقاف الإشعار', 'Stop notification') }}</button>
        </article>
        <div v-if="history" class="flex items-center gap-3 mt-4"><button class="notice-secondary" :disabled="loading || history.current_page <= 1" @click="loadHistory(history.current_page - 1)">{{ text('السابق', 'Previous') }}</button><span>{{ history.current_page }} / {{ history.last_page }}</span><button class="notice-secondary" :disabled="loading || history.current_page >= history.last_page" @click="loadHistory(history.current_page + 1)">{{ text('التالي', 'Next') }}</button></div>
      </div>
      <div v-if="confirming" role="alert" class="notice-confirm">
        <h3 class="font-bold">{{ text('تأكيد إرسال الإشعار', 'Confirm notification') }}</h3>
        <p>{{ form.title }}</p><p>{{ text('سيُرسل إلى', 'Send to') }} {{ recipientCount }} {{ text('مختبر', 'labs') }}.</p>
        <p v-if="form.audience === 'selected'" class="notice-body">{{ recipientNames }}</p>
        <strong v-else>{{ text('جميع المختبرات الحالية', 'All current labs') }}</strong>
        <div class="flex gap-2 mt-3"><button class="notice-primary" :disabled="busy" @click="send" data-testid="notice-send">{{ busy ? text('جارٍ الإرسال…', 'Sending…') : text('تأكيد الإرسال', 'Send now') }}</button><button class="notice-secondary" :disabled="busy" @click="confirming = false">{{ text('رجوع', 'Back') }}</button></div>
      </div>
    </div>
  </Dialog>
</template>
