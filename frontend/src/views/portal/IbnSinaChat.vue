<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { $http } from '@/plugins/axios';

const props = defineProps({ token: { type: String, required: true }, reports: { type: Array, default: () => [] }, initialReportId: [Number, String] });
const emit = defineEmits(['book-doctor']);
const readyCount = r => r?.ready_count ?? (r?.tests || []).filter(t => t.status === 'ready').length;
const defaultReport = () => String(props.initialReportId || props.reports.find(r => readyCount(r))?.id || props.reports[0]?.id || '');
const selectedId = ref(defaultReport());
const selectedReport = computed(() => props.reports.find(r => String(r.id) === selectedId.value));
const messages = ref([]), input = ref(''), consent = ref(false), sending = ref(false), error = ref(''), failedMessage = ref('');
const log = ref(null), reportRevision = ref(null), contextChanged = ref(false), concern = ref('');
const phase = ref('choose'), mode = ref('all'), choosing = ref(false), choices = ref([]), selectedKeys = ref([]);
const optionsLoading = ref(false), optionsError = ref(''), selectionRevision = ref(null), pending = ref(0);
let generation = 0, optionsGeneration = 0, controller, optionsController;
const urgentActive = computed(() => [...messages.value].reverse().find(m => m.role === 'assistant')?.urgent === true);
const formatDate = value => value ? new Date(value).toLocaleDateString('ar-IQ') : '';
const selectionSummary = computed(() => !selectedId.value ? 'أريد محادثة عامة بدون تقرير.' : mode.value === 'all'
  ? `أريد شرح كل التحاليل الجاهزة في هذا التقرير (${choices.value.length}).`
  : `أريد شرح: ${choices.value.filter(c => selectedKeys.value.includes(c.key)).map(c => c.name).join('، ')}.`);
const prompts = computed(() => phase.value === 'intake'
  ? ['سويت التحاليل للاطمئنان، وما عندي أعراض حالياً.', 'أفضل شرح النتائج بدون ذكر تفاصيل إضافية.']
  : ['وضح لي أكثر وبطريقة أبسط', 'شنو الأسئلة اللي أجهزها للطبيب؟', 'هل يوجد طبيب مناسب ضمن أطباء المختبر؟']);

function clearConversation() {
  generation++; optionsGeneration++; controller?.abort(); optionsController?.abort();
  sending.value = false; messages.value = []; input.value = ''; error.value = ''; failedMessage.value = '';
  reportRevision.value = null; selectionRevision.value = null; concern.value = ''; contextChanged.value = false;
  phase.value = 'choose'; mode.value = 'all'; choosing.value = false; selectedKeys.value = []; choices.value = [];
  pending.value = 0; optionsError.value = ''; optionsLoading.value = false;
}
async function loadChoices() {
  optionsController?.abort();
  if (!selectedId.value) return;
  const current = ++optionsGeneration;
  optionsController = new AbortController(); optionsLoading.value = true; optionsError.value = '';
  try {
    const { data } = await $http.get(`/portal/${props.token}/ai-options`, { params: { invoice_id: Number(selectedId.value) }, signal: optionsController.signal });
    if (current !== optionsGeneration) return;
    choices.value = data.analyses || []; selectionRevision.value = data.revision; pending.value = data.pending || 0;
  } catch (e) {
    if (current === optionsGeneration) optionsError.value = e?.response?.data?.message || 'تعذر تحميل التحاليل. حاول مرة أخرى.';
  } finally { if (current === optionsGeneration) optionsLoading.value = false; }
}
function restart() { clearConversation(); loadChoices(); }
watch(selectedId, restart, { immediate: true });
watch(() => props.initialReportId, value => { if (value) selectedId.value = String(value); });
watch(() => props.token, () => { consent.value = false; selectedId.value = defaultReport(); restart(); });
watch(() => props.reports, () => { if (selectedId.value && !selectedReport.value) selectedId.value = defaultReport(); });
onBeforeUnmount(() => { generation++; optionsGeneration++; controller?.abort(); optionsController?.abort(); });
async function scrollToLatest() { await nextTick(); if (log.value) log.value.scrollTop = log.value.scrollHeight; }
function chooseScope(scope) {
  if (selectedId.value && (optionsLoading.value || optionsError.value || !choices.value.length)) return;
  if (scope === 'selected' && !selectedKeys.value.length) return;
  mode.value = scope; phase.value = 'intake'; choosing.value = false; scrollToLatest();
}
function generalChat() { selectedId.value = ''; nextTick(() => chooseScope('all')); }
function phoneLink(value, whatsapp = false) {
  const digits = String(value || '').replace(/[^\d+]/g, '');
  if (!/^\+?\d{7,15}$/.test(digits)) return null;
  // WhatsApp needs a country code; do not invent one for an incomplete number.
  if (whatsapp && digits.startsWith('0')) return null;
  return whatsapp ? `https://wa.me/${digits.replace(/^\+/, '')}` : `tel:${digits}`;
}
async function send(retry = false) {
  const text = (retry ? failedMessage.value : input.value).trim();
  if (phase.value === 'choose' || !consent.value || sending.value || !text || text.length > 2000) return;
  if (!retry && failedMessage.value) { messages.value.pop(); failedMessage.value = ''; }
  const history = messages.value.slice(0, retry ? -1 : undefined).slice(-8).map(({ role, content }) => ({ role, content: content.slice(0, 6000) }));
  if (!history.length) concern.value = text;
  if (!retry) messages.value.push({ role: 'user', content: text });
  phase.value = 'chat'; input.value = ''; sending.value = true; error.value = '';
  const current = ++generation; controller = new AbortController(); await scrollToLatest();
  try {
    const { data } = await $http.post(`/portal/${props.token}/ai-chat`, {
      message: text, history, consent: true, guided: true, concern: concern.value,
      invoice_id: selectedId.value ? Number(selectedId.value) : null,
      report_revision: reportRevision.value, selection_revision: selectionRevision.value,
      ...(mode.value === 'selected' ? { analysis_keys: selectedKeys.value } : {}),
    }, { signal: controller.signal, timeout: 55000 });
    if (current !== generation) return;
    if (typeof data?.reply !== 'string' || !data.reply.trim()) throw new Error('Empty reply');
    if (data.report?.context_reset) { messages.value = [{ role: 'user', content: text }]; contextChanged.value = true; }
    reportRevision.value = data.report?.revision || null;
    messages.value.push({ role: 'assistant', content: data.reply, source: data.report, doctors: data.urgent ? [] : (data.doctors || []), urgent: data.urgent === true });
    failedMessage.value = '';
  } catch (e) {
    if (current !== generation) return;
    if (e?.response?.status === 409 && e.response.data?.context_reset) { restart(); contextChanged.value = true; return; }
    failedMessage.value = text;
    error.value = e?.response?.status === 429 ? 'وصلت للحد المؤقت للمحادثة. انتظر دقيقة ثم حاول مرة أخرى.'
      : e?.response?.data?.message || 'تعذر وصول الرد. يمكنك إعادة المحاولة دون تكرار رسالتك.';
  } finally { if (current === generation) { sending.value = false; await scrollToLatest(); } }
}
function keyboardSend(event) { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); send(); } }
</script>

<template>
  <section class="ibn-sina" dir="rtl" aria-labelledby="ibn-sina-title" data-ibn-sina>
    <header class="chat-header">
      <div class="avatar" aria-hidden="true"><i class="pi pi-comments"></i></div>
      <div class="identity"><p class="eyebrow">نسمعك ونفهم نتائجك سوا</p><h2 id="ibn-sina-title">ابن سينا</h2><p>مساعد طبي بالذكاء الاصطناعي</p></div>
      <button type="button" class="new-chat" @click="restart" aria-label="بدء محادثة جديدة"><i class="pi pi-refresh" aria-hidden="true"></i><span>محادثة جديدة</span></button>
    </header>
    <div class="report-picker">
      <label for="ibn-sina-report">أي تقرير تحب نشرحه؟</label>
      <select id="ibn-sina-report" v-model="selectedId">
        <option value="">محادثة عامة — بدون تقرير</option>
        <option v-for="report in reports" :key="report.id" :value="String(report.id)">تقرير #{{ report.id }} · {{ formatDate(report.date) }} · {{ readyCount(report) }} جاهز</option>
      </select>
      <p class="context-note">نبدأ بأحدث تقرير جاهز. تغيير التقرير يبدأ محادثة جديدة.</p>
    </div>
    <p v-if="contextChanged" role="status" class="report-picker context-note">تغيّرت النتائج المنشورة؛ جددنا القائمة والمحادثة. اختَر التحاليل مرة أخرى حتى نشرح أحدث نتائج فقط.</p>
    <div ref="log" class="chat-log" role="log" aria-label="محادثتك مع ابن سينا" aria-live="polite" :aria-busy="sending">
      <article class="message assistant welcome-message">
        <p class="speaker">ابن سينا · ذكاء اصطناعي</p>
        <h3>أهلاً بيك، أنا ابن سينا 👋</h3>
        <p class="message-content">خلّينا نفهم نتائجك سوا، وبالطريقة اللي تناسبك.</p>
        <p v-if="optionsLoading" class="context-note" role="status">لحظة، أجهّز قائمة تحاليلك…</p>
        <div v-else-if="optionsError" role="alert" class="options-error">{{ optionsError }} <button type="button" @click="loadChoices">إعادة تحميل التحاليل</button></div>
        <template v-else-if="selectedId && choices.length">
          <p class="message-content">عندك {{ choices.length }} تحليل جاهز بهذا التقرير. تحب نشرحها كلها، لو تختار تحاليل معيّنة؟</p>
          <p v-if="pending" class="context-note">{{ pending }} فحص قيد الإنجاز ولا يدخل في الشرح.</p>
          <div v-if="phase === 'choose'" class="flow-actions">
            <button type="button" class="primary-choice" @click="chooseScope('all')">اشرح كل التحاليل الجاهزة</button>
            <button type="button" @click="choosing = !choosing" :aria-expanded="choosing">أختار تحاليل معيّنة</button>
          </div>
          <fieldset v-if="phase === 'choose' && choosing" class="analysis-choices">
            <legend>اختَر التحاليل اللي تحب نشرحها</legend>
            <label v-for="analysis in choices" :key="analysis.key">
              <input type="checkbox" v-model="selectedKeys" :value="analysis.key" :disabled="!analysis.readable">
              <span>{{ analysis.name }}<small v-if="analysis.container">{{ analysis.container }}</small><small v-if="!analysis.readable">النتيجة تحتاج مراجعة التقرير الأصلي</small></span>
            </label>
            <button type="button" class="primary-choice" @click="chooseScope('selected')" :disabled="!selectedKeys.length">متابعة بالتحاليل المحددة ({{ selectedKeys.length }})</button>
          </fieldset>
        </template>
        <template v-else>
          <p class="message-content">{{ selectedId ? 'نتائج هذا التقرير بعد ما جاهزة للشرح. تقدر تختار تقرير ثاني أو تحچي وياي بشكل عام.' : 'تقدر تحچي وياي عن استفسارك الصحي، بدون مشاركة تقرير.' }}</p>
          <button v-if="phase === 'choose'" type="button" class="primary-choice" @click="generalChat">ابدأ محادثة عامة</button>
        </template>
      </article>
      <template v-if="phase !== 'choose'">
        <article class="message user"><p class="speaker">أنت</p><p class="message-content">{{ selectionSummary }}</p></article>
        <article class="message assistant intake-message"><p class="speaker">ابن سينا · ذكاء اصطناعي</p><p class="message-content">قبل ما نبدأ، شنو اللي مضايقك أو شنو سبب إجراء التحاليل؟ إذا عندك أعراض، احچيلي عنها ومن متى بدت. وإذا مجرد فحص للاطمئنان، هم تكدر تكلي.</p><p class="context-note">تقدر تطلب الشرح بدون مشاركة تفاصيل إضافية.</p></article>
      </template>
      <article v-for="(message, index) in messages" :key="index" :class="['message', message.role, { urgent: message.urgent }]" data-chat-message>
        <p class="speaker">{{ message.role === 'assistant' ? 'ابن سينا · ذكاء اصطناعي' : 'أنت' }}</p>
        <p class="message-content" dir="auto">{{ message.content }}</p>
        <p v-if="message.source" class="source">التحاليل المختارة: {{ message.source.selected_count ?? message.source.ready }} من تقرير #{{ message.source.invoice_id }}<span v-if="message.source.pending"> · {{ message.source.pending }} قيد الإنجاز</span></p>
        <div v-if="message.doctors?.length && !urgentActive" class="doctor-recommendations">
          <h4>أطباء ضمن دليل المختبر يمكنك مراجعتهم</h4>
          <div v-for="doctor in message.doctors" :key="doctor.id" class="doctor-card">
            <strong>{{ doctor.name }}</strong><p>{{ doctor.specialty }}</p>
            <div class="doctor-links"><a v-if="phoneLink(doctor.phone)" :href="phoneLink(doctor.phone)" dir="ltr">{{ doctor.phone }}</a><a v-if="phoneLink(doctor.whatsapp, true)" :href="phoneLink(doctor.whatsapp, true)" target="_blank" rel="noopener noreferrer">واتساب</a></div>
            <button v-if="doctor.bookable" type="button" @click="emit('book-doctor', doctor.id)">طلب حجز مع {{ doctor.name }}</button>
          </div>
          <p class="context-note">طلب الحجز يحتاج تأكيداً من المختبر؛ الموعد غير مضمون بمجرد إرسال الطلب.</p>
        </div>
      </article>
      <p v-if="sending" class="typing" role="status"><span class="typing-dot"></span>أقرأ كلامك وأجهّز الشرح…</p>
    </div>
    <div v-if="phase !== 'choose' && (!consent || !messages.length)" class="consent-panel">
      <i class="pi pi-lock" aria-hidden="true"></i><div><strong>خصوصيتك أولاً</strong><p>عند الإرسال تُشارك رسائلك والتحاليل المختارة والعمر والجنس إن توفرا مع مزوّد الذكاء الاصطناعي المعتمد من المختبر. لا نرسل اسمك أو هاتفك تلقائياً. تجنّب كتابة معلومات تعريفية.</p><label class="consent-check"><input type="checkbox" v-model="consent">أوافق على استخدام هذه البيانات لشرح النتائج</label></div>
    </div>
    <div v-if="phase !== 'choose' && !sending && !error" class="suggestions quick-replies"><button v-for="prompt in prompts" :key="prompt" type="button" @click="input = prompt">{{ prompt }}</button></div>
    <div v-if="error" class="chat-error" role="alert"><p>{{ error }}</p><button type="button" @click="send(true)" :disabled="sending || !consent">إعادة المحاولة</button></div>
    <form v-if="phase !== 'choose'" class="composer" @submit.prevent="send()">
      <label class="sr-only" for="ibn-sina-message">رسالتك إلى ابن سينا</label>
      <textarea id="ibn-sina-message" v-model="input" rows="2" maxlength="2000" :placeholder="phase === 'intake' ? 'احچيلي شنو عندك ومن متى…' : 'اكتب سؤالك، نكمل سوا…'" @keydown="keyboardSend" :disabled="sending"></textarea>
      <div class="composer-actions"><span>{{ input.length }}/2000</span><button type="submit" :disabled="!consent || sending || !input.trim()"><i class="pi pi-send" aria-hidden="true"></i>{{ sending ? 'جارٍ الرد…' : 'إرسال' }}</button></div>
    </form>
    <footer class="chat-note">ابن سينا مساعد آلي وليس طبيباً بشرياً؛ قد يخطئ، ولا يقدّم تشخيصاً أو وصفة علاج. عند الأعراض الشديدة اطلب الطوارئ ولا تنتظر المحادثة. تبقى المحادثة في هذه الصفحة وتُمسح عند مغادرتها.</footer>
  </section>
</template>
<style scoped>
.ibn-sina{border:1px solid #cfe5e0;border-radius:22px;background:#fff;overflow:hidden;box-shadow:0 8px 30px #123f3810;color:#183a36;min-width:0}
.chat-header{display:flex;align-items:center;gap:12px;padding:24px 20px;background:linear-gradient(125deg,#e9f7f3,#f7fbfa);border-bottom:1px solid #dcebe6}
.avatar{width:48px;height:48px;display:grid;place-items:center;background:#176b5b;color:white;border-radius:16px;font-size:22px;flex-shrink:0}
.identity{flex:1}.identity h2{font-size:24px;font-weight:800;line-height:1.5}.identity>p:last-child{font-size:12px;color:#4b6c64}.eyebrow{font-size:11px;color:#477b6c}
.new-chat{display:flex;align-items:center;gap:7px;font-size:12px;color:#346459;padding:8px;border-radius:10px}.new-chat:disabled{opacity:.45}
.report-picker{padding:18px 20px;border-bottom:1px solid #e7efec}.report-picker label{display:block;font-size:13px;font-weight:700;margin-bottom:8px}.report-picker select{width:100%;min-width:0;background:#f8fbfa;border:1px solid #cbded7;padding:12px;border-radius:12px;font-size:13px}.context-note{font-size:11px;color:#5c766d;margin-top:8px;line-height:1.8}
.consent-panel{display:flex;gap:10px;padding:16px;margin:16px 20px 0;background:#f4f8fc;border:1px solid #dce7f1;border-radius:12px;font-size:12px;line-height:1.9;color:#3e5770}.consent-panel>i{margin-top:4px}.consent-panel strong{font-size:13px}.consent-check{display:flex;align-items:flex-start;gap:8px;margin-top:12px;font-weight:700;cursor:pointer}.consent-check input{margin-top:5px;accent-color:#176b5b;width:16px;height:16px;flex-shrink:0}
.chat-log{padding:20px;max-height:65vh;min-height:260px;overflow-y:auto;overscroll-behavior:contain}.welcome{padding:8px 0;text-align:center}.welcome-icon{margin:0 auto 12px;width:42px;height:42px;border-radius:50%;background:#e6f5ee;display:grid;place-items:center;color:#176b5b}.welcome h3{font-size:18px;font-weight:700}.welcome>p{max-width:390px;margin:8px auto 20px;font-size:13px;line-height:1.9;color:#61716b}
.suggestions{display:grid;gap:8px;text-align:right}.suggestions button{display:flex;justify-content:space-between;align-items:center;gap:12px;border:1px solid #dce8e1;border-radius:12px;padding:12px 14px;font-size:12px;background:#fff}.suggestions button:hover{background:#f0f8f4}
.message{padding:14px 16px;border-radius:16px;margin-bottom:16px;max-width:94%;overflow-wrap:anywhere}.message.user{background:#176b5b;color:#fff;margin-inline-end:0;margin-inline-start:auto;border-end-end-radius:4px}.message.assistant{background:#f3f7f5;border:1px solid #e2ebe7;border-end-start-radius:4px;margin-inline-end:auto}.speaker{font-size:10px;font-weight:700;margin-bottom:8px;opacity:.75}.message-content{white-space:pre-wrap;font-size:14px;line-height:2;text-align:start;unicode-bidi:plaintext}.source{font-size:10px;color:#55766a;margin-top:12px;padding-top:10px;border-top:1px solid #d9e6df;line-height:1.8}.typing{display:flex;align-items:center;gap:8px;font-size:12px;color:#54786a}.typing-dot{width:8px;height:8px;border-radius:50%;background:#428d75}
.chat-error{margin:0 20px 12px;background:#fff5f3;color:#983f32;border:1px solid #f1d9d1;border-radius:12px;padding:12px;font-size:12px;line-height:1.8}.chat-error button{text-decoration:underline;font-weight:700;margin-top:6px}.composer{margin:0 20px;border:1px solid #bdd5cb;border-radius:14px;padding:12px;background:#fff}.composer textarea{width:100%;resize:vertical;max-height:180px;min-height:55px;outline:none;border:0;font-size:14px;background:transparent;line-height:1.8}.composer-actions{display:flex;align-items:center;justify-content:space-between;gap:12px}.composer-actions span{font-size:10px;color:#7c8d84}.composer-actions button{display:flex;gap:8px;align-items:center;background:#176b5b;color:#fff;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700}.composer-actions button:disabled{opacity:.45;cursor:not-allowed}.chat-note{font-size:10px;color:#68776e;line-height:1.9;padding:14px 20px 18px}
button:focus-visible,select:focus-visible,textarea:focus-visible,input:focus-visible{outline:3px solid #65ad96;outline-offset:3px}
@media(max-width:480px){.chat-header{padding:18px 14px;gap:10px}.new-chat span{display:none}.report-picker{padding:14px}.chat-log{padding:14px}.message{max-width:100%}.consent-panel{margin:14px 14px 0;padding:12px}.composer{margin:0 14px}.chat-note{padding:12px 14px}.chat-error{margin-inline:14px}}

.welcome-message h3{font-size:18px;font-weight:800;margin-bottom:10px}.flow-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.flow-actions button,.primary-choice{padding:11px 14px;border:1px solid #bed9ce;border-radius:10px;font-size:12px;font-weight:700;background:white;color:#176b5b}.primary-choice,.flow-actions .primary-choice{background:#176b5b;color:white;border-color:#176b5b}.primary-choice:disabled{opacity:.4;cursor:not-allowed}.analysis-choices{margin-top:16px;border-top:1px solid #d8e8df;padding-top:12px}.analysis-choices legend{font-size:12px;font-weight:700;padding-block:12px}.analysis-choices label{display:flex;align-items:flex-start;gap:10px;padding:11px 8px;background:white;border-radius:8px;margin-bottom:7px;cursor:pointer;font-size:13px}.analysis-choices input{margin-top:4px;accent-color:#176b5b;flex-shrink:0}.analysis-choices small{display:block;font-size:10px;color:#687c72;margin-top:3px}.analysis-choices .primary-choice{margin-top:8px;width:100%}.quick-replies{padding:14px 20px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}.quick-replies button{font-size:11px;line-height:1.8;padding:9px 12px}.options-error{font-size:12px;line-height:1.8;color:#993f35}.options-error button{text-decoration:underline;font-weight:bold}.doctor-recommendations{margin-top:16px;border-top:1px solid #cfe0d6;padding-top:14px}.doctor-recommendations h4{font-size:12px;font-weight:700;margin-bottom:10px}.doctor-card{padding:14px;border:1px solid #d6e6dd;border-radius:12px;background:white;margin-top:8px}.doctor-card strong{font-size:14px}.doctor-card>p{font-size:12px;color:#507669;margin-top:4px}.doctor-links{display:flex;flex-wrap:wrap;gap:12px;font-size:12px;margin-block:10px;color:#176b5b}.doctor-card button{width:100%;padding:10px;background:#eaf5ef;color:#176b5b;border-radius:8px;font-size:12px;font-weight:700}.message.urgent{background:#fff5ef;border-color:#efc8b8}.consent-panel{margin-bottom:12px}
</style>
