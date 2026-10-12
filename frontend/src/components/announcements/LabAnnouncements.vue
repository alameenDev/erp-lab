<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import AnnouncementManager from './AnnouncementManager.vue';
import { announcementsApi as api, announcementDate, announcementError, currentAnnouncementUser, safeAnnouncementUrl } from '@/utils/labAnnouncements';

const user = ref(currentAnnouncementUser());
const admin = computed(() => Number(user.value?.role_id) === 1);
const eligible = computed(() => user.value && !user.value.is_referral_partner && !user.value.referral_portal_only && ![3, 5].includes(Number(user.value.role_id)));
const managerOpen = ref(false), inboxOpen = ref(false), active = ref(null), unread = ref(0);
const inbox = ref(null), loading = ref(false), acknowledging = ref(false), error = ref('');
const connectionIssue = ref(false), pending = ref([]);
const text = (ar, en) => localStorage.getItem('locale') === 'en' ? en : ar;
const levels = computed(() => ({ info: text('إشعار من الإدارة', 'Administration notice'), success: text('تحديث جديد', 'New update'), warning: text('تنبيه من الإدارة', 'Administration warning'), urgent: text('إشعار عاجل', 'Urgent notice') }));
const snoozed = new Set();
const acknowledged = new Set();
let disposed = false, polling = false, stopped = false, failures = 0, timer;
let activeFromInbox = false;
const controller = new AbortController();
const sessionToken = localStorage.getItem('token');
const sameSession = () => !disposed && localStorage.getItem('token') === sessionToken && String(currentAnnouncementUser()?.id) === String(user.value?.id);
function nextPopup() {
  if (disposed || document.hidden || active.value || inboxOpen.value || managerOpen.value) return;
  activeFromInbox = false;
  active.value = pending.value.find((notice) => !snoozed.has(notice.id)) || null;
}
function stop() {
  stopped = true; clearTimeout(timer); controller.abort();
  active.value = null; inboxOpen.value = false; managerOpen.value = false; unread.value = 0; pending.value = []; inbox.value = null; user.value = null;
}
async function poll() {
  clearTimeout(timer);
  if (disposed || stopped || !eligible.value || admin.value) return;
  if (!sameSession()) { stop(); return; }
  if (polling) return;
  if (document.hidden || !navigator.onLine) { timer = setTimeout(poll, 5000); return; }
  polling = true;
  try {
    const { data } = await api.get('/lab-announcements/pending', { signal: controller.signal });
    if (!sameSession()) { stop(); return; }
    pending.value = data.data.filter((notice) => !acknowledged.has(notice.id));
    unread.value = Math.max(0, data.unread_count - data.data.filter((notice) => acknowledged.has(notice.id)).length);
    failures = 0; connectionIssue.value = false;
    if (active.value && !activeFromInbox && !data.data.some((notice) => notice.id === active.value.id)) active.value = null;
    nextPopup();
  } catch (e) {
    if (disposed || stopped) return;
    if ([401, 403].includes(e.response?.status)) { stop(); return; }
    failures++; connectionIssue.value = true;
  } finally {
    polling = false;
    if (!disposed && !stopped) timer = setTimeout(poll, Math.min(60000, 5000 * 2 ** Math.min(failures, 4)));
  }
}
async function loadInbox(page = 1) {
  if (!sameSession()) { stop(); return; }
  loading.value = true; error.value = '';
  try { const { data } = await api.get('/lab-announcements/inbox', { params: { page }, signal: controller.signal }); if (sameSession()) inbox.value = data; }
  catch (e) { if (sameSession()) error.value = announcementError(e); }
  finally { if (!disposed) loading.value = false; }
}
function openInbox() { inboxOpen.value = true; active.value = null; error.value = ''; loadInbox(); }
function viewNotice(notice) { inboxOpen.value = false; activeFromInbox = true; active.value = notice; error.value = ''; }
function later() {
  if (acknowledging.value) return;
  if (active.value) snoozed.add(active.value.id);
  active.value = null; error.value = ''; nextPopup();
}
async function acknowledge() {
  if (!active.value || acknowledging.value) return;
  if (!sameSession()) { stop(); return; }
  acknowledging.value = true; error.value = '';
  const notice = active.value;
  try {
    await api.post(`/lab-announcements/${notice.id}/read`, {}, { signal: controller.signal });
    if (!sameSession()) { stop(); return; }
    acknowledged.add(notice.id);
    pending.value = pending.value.filter((item) => item.id !== notice.id);
    active.value = null; unread.value = Math.max(0, unread.value - (notice.read_at ? 0 : 1));
    if (inbox.value) inbox.value.data = inbox.value.data.map((item) => item.id === notice.id ? { ...item, read_at: new Date().toISOString() } : item);
    await poll(); nextPopup();
  } catch (e) {
    if (sameSession()) {
      if (e.response?.status === 404) { active.value = null; await poll(); }
      else error.value = announcementError(e);
    }
  } finally { if (!disposed) acknowledging.value = false; }
}
function onVisibility() { if (!document.hidden) { poll(); nextPopup(); } }
function onStorage(event) { if (['token', 'user', null].includes(event.key) && !sameSession()) stop(); }
watch(inboxOpen, (open) => { if (!open) nextPopup(); });
onMounted(() => {
  poll(); window.addEventListener('focus', onVisibility); window.addEventListener('online', onVisibility);
  document.addEventListener('visibilitychange', onVisibility); window.addEventListener('storage', onStorage);
});
onBeforeUnmount(() => {
  disposed = true; clearTimeout(timer); controller.abort();
  window.removeEventListener('focus', onVisibility); window.removeEventListener('online', onVisibility);
  document.removeEventListener('visibilitychange', onVisibility); window.removeEventListener('storage', onStorage);
});
</script>

<template>
  <section v-if="eligible" class="lab-announcements-bar mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3" :dir="text('rtl', 'ltr')">
    <span class="font-semibold"><i class="pi pi-bell" aria-hidden="true"></i> {{ text('إشعارات الإدارة', 'Administration notifications') }}</span>
    <button v-if="admin" type="button" class="notice-primary" @click="managerOpen = true" data-testid="notice-admin-open">{{ text('إرسال إشعار للمختبرات', 'Send a lab notification') }}</button>
    <template v-else><button type="button" class="notice-secondary" @click="openInbox" data-testid="notice-inbox-open">{{ text('صندوق الإشعارات', 'Notification inbox') }} <span aria-live="polite">({{ unread }})</span></button><span v-if="connectionIssue" role="status" class="text-sm text-amber-700">{{ text('تعذر تحديث الإشعارات مؤقتاً؛ ستتم إعادة المحاولة تلقائياً.', 'Notifications are temporarily unavailable. Retrying automatically.') }}</span></template>
    <AnnouncementManager v-if="admin" v-model:visible="managerOpen" />
  </section>

  <Dialog :visible="Boolean(active)" @update:visible="(visible) => { if (!visible) later(); }" modal :closable="!acknowledging" :closeOnEscape="!acknowledging" :dismissableMask="false"
    :header="levels[active?.level] || levels.info" :style="{ width: '38rem', maxWidth: '94vw' }" :pt="{ mask: { class: 'lab-announcement-mask' } }">
    <article v-if="active" :dir="text('rtl', 'ltr')" :class="['notice-popup', `notice-${active.level}`]" data-testid="notice-popup">
      <h2 class="text-xl font-bold mb-3">{{ active.title }}</h2><p class="notice-body">{{ active.body }}</p><p class="text-sm text-slate-500 mt-4">{{ announcementDate(active.created_at) }}</p>
      <a v-if="safeAnnouncementUrl(active.button_url)" :href="safeAnnouncementUrl(active.button_url)" target="_blank" rel="noopener noreferrer" class="notice-primary inline-block mt-3">{{ active.button_label }}</a>
      <p v-if="error" role="alert" class="notice-error">{{ error }}</p>
      <div class="flex flex-wrap gap-2 mt-5">
        <button v-if="!active.read_at" class="notice-primary" :disabled="acknowledging" @click="acknowledge" data-testid="notice-ack">{{ acknowledging ? text('جارٍ الحفظ…', 'Saving…') : text('تمت القراءة', 'Mark as read') }}</button>
        <button class="notice-secondary" :disabled="acknowledging" @click="later">{{ active.read_at ? text('إغلاق', 'Close') : text('لاحقاً', 'Later') }}</button>
      </div>
    </article>
  </Dialog>

  <Dialog v-model:visible="inboxOpen" modal :header="text('صندوق إشعارات الإدارة', 'Administration inbox')" :style="{ width: '46rem', maxWidth: '95vw' }" :pt="{ mask: { class: 'lab-announcement-mask' } }">
    <div :dir="text('rtl', 'ltr')"><p v-if="error" role="alert" class="notice-error">{{ error }}</p><p v-if="loading" role="status">{{ text('جارٍ التحميل…', 'Loading…') }}</p><p v-if="inbox && !inbox.data.length">{{ text('لا توجد إشعارات حالياً.', 'No notifications yet.') }}</p>
      <button v-for="notice in inbox?.data || []" :key="notice.id" class="notice-inbox-item" @click="viewNotice(notice)"><strong>{{ notice.title }}</strong><span>{{ notice.read_at ? text('مقروء', 'Read') : text('جديد', 'New') }}</span><small>{{ announcementDate(notice.created_at) }}</small></button>
      <div v-if="inbox" class="flex items-center gap-3 mt-4"><button class="notice-secondary" :disabled="loading || inbox.current_page <= 1" @click="loadInbox(inbox.current_page - 1)">{{ text('السابق', 'Previous') }}</button><span>{{ inbox.current_page }} / {{ inbox.last_page }}</span><button class="notice-secondary" :disabled="loading || inbox.current_page >= inbox.last_page" @click="loadInbox(inbox.current_page + 1)">{{ text('التالي', 'Next') }}</button></div>
    </div>
  </Dialog>
</template>

<style>
.notice-primary,.notice-secondary{border-radius:.6rem;padding:.65rem 1rem;font-weight:600;cursor:pointer;border:1px solid #cbd5e1;line-height:1.5}
.notice-primary{background:#0f766e;color:#fff;border-color:#0f766e}.notice-secondary{background:#fff;color:#334155}
.notice-primary:disabled,.notice-secondary:disabled{opacity:.5;cursor:not-allowed}
.notice-primary:focus-visible,.notice-secondary:focus-visible,.notice-inbox-item:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}
.notice-fields{display:grid;gap:1rem;border:0;padding:0;min-width:0}.notice-fields>label,.notice-grid>label{display:grid;gap:.4rem;font-weight:600}
.notice-fields input:not([type=checkbox]):not([type=radio]),.notice-fields textarea,.notice-fields select{display:block;width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.7rem;background:#fff;color:#0f172a;font-weight:400}
.notice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.notice-audience{border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem;display:grid;gap:.8rem}
.notice-inline{display:flex;align-items:center;gap:.65rem;cursor:pointer}.notice-inline input{width:1rem;height:1rem;flex-shrink:0}.notice-lab-list{display:grid;gap:.65rem;max-height:12rem;overflow:auto;padding:.5rem}
.notice-body{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.9}.notice-preview,.notice-history-item{border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem;margin-block:.75rem}
.notice-confirm{border:1px solid #0f766e;background:#f0fdfa;border-radius:.75rem;padding:1rem;margin-top:1rem}.notice-error{background:#fef2f2;color:#991b1b;padding:.75rem;border-radius:.5rem;margin-block:.75rem}.notice-success{background:#f0fdf4;color:#166534;padding:.75rem;border-radius:.5rem;margin-block:.75rem}
.notice-popup{border-inline-start:4px solid #0f766e;padding-inline-start:1rem}.notice-urgent{border-color:#dc2626}.notice-warning{border-color:#d97706}
.notice-inbox-item{display:grid;grid-template-columns:1fr auto;gap:.5rem;width:100%;text-align:start;padding:1rem;border:1px solid #e2e8f0;border-radius:.75rem;margin-bottom:.75rem;background:#fff;cursor:pointer}.notice-inbox-item small{grid-column:1/-1}
@media(max-width:640px){.notice-grid{grid-template-columns:1fr}}
@media print{.lab-announcements-bar,.lab-announcement-mask{display:none!important}}
</style>
