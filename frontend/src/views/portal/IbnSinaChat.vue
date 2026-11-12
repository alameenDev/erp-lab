<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { $http } from '@/plugins/axios';

const props = defineProps({
  token: { type: String, required: true },
  reports: { type: Array, default: () => [] },
  initialReportId: { type: [Number, String], default: null },
});
const selectedId = ref(props.initialReportId ? String(props.initialReportId) : '');
const messages = ref([]);
const input = ref('');
const consent = ref(false);
const sending = ref(false);
const error = ref('');
const failedMessage = ref('');
const log = ref(null);
const reportRevision = ref(null);
const contextChanged = ref(false);
let generation = 0;
let controller;
const selectedReport = computed(() => props.reports.find(r => String(r.id) === selectedId.value));
const readyCount = r => r?.ready_count ?? (r?.tests || []).filter(t => t.status === 'ready').length;
const pendingCount = r => r?.pending_count ?? (r?.tests || []).filter(t => t.status !== 'ready').length;
const formatDate = value => value ? new Date(value).toLocaleDateString('ar-IQ') : '';
const prompts = computed(() => selectedId.value
  ? ['اشرح لي نتائج هذا التقرير خطوة بخطوة', 'ما معنى النتائج خارج المجال المرجعي؟', 'ما الأسئلة التي أجهزها للطبيب؟']
  : ['كيف أقرأ تقرير التحاليل؟', 'ما معنى المجال المرجعي؟', 'كيف أستعد لمناقشة نتائجي مع الطبيب؟']);

function reset() {
  generation++;
  controller?.abort();
  sending.value = false;
  messages.value = [];
  reportRevision.value = null; contextChanged.value = false;
  input.value = '';
  error.value = '';
  failedMessage.value = '';
}
watch(selectedId, reset);
watch(() => props.initialReportId, value => { selectedId.value = value ? String(value) : ''; });
watch(() => props.token, () => { reset(); consent.value = false; selectedId.value = ''; });
watch(() => props.reports, () => {
  if (selectedId.value && !selectedReport.value) selectedId.value = '';
});
onBeforeUnmount(reset);
async function scrollToLatest() {
  await nextTick();
  if (log.value) log.value.scrollTop = log.value.scrollHeight;
}
async function send(retry = false) {
  const text = (retry ? failedMessage.value : input.value).trim();
  if (!consent.value || sending.value || !text || text.length > 2000) return;
  // Failed requests are retried as the same user turn, never duplicated in the history.
  if (!retry && failedMessage.value) {
    messages.value.pop();
    failedMessage.value = '';
  }
  const history = messages.value.slice(0, retry ? -1 : undefined).slice(-8)
    .map(({ role, content }) => ({ role, content: content.slice(0, 6000) }));
  if (!retry) messages.value.push({ role: 'user', content: text });
  input.value = '';
  sending.value = true;
  error.value = '';
  const requestGeneration = ++generation;
  controller = new AbortController();
  await scrollToLatest();
  try {
    const { data } = await $http.post(`/portal/${props.token}/ai-chat`, {
      message: text, history, consent: true, invoice_id: selectedId.value ? Number(selectedId.value) : null,
      report_revision: reportRevision.value,
    }, { signal: controller.signal, timeout: 55000 });
    if (generation !== requestGeneration) return;
    if (typeof data?.reply !== 'string' || !data.reply.trim()) throw new Error('Empty reply');
    if (data.report?.context_reset) {
      messages.value = [{ role: 'user', content: text }];
      contextChanged.value = true;
    }
    reportRevision.value = data.report?.revision || null;
    messages.value.push({ role: 'assistant', content: data.reply, source: data.report });
    failedMessage.value = '';
  } catch (e) {
    if (generation !== requestGeneration) return;
    failedMessage.value = text;
    error.value = e?.response?.status === 429
      ? 'وصلت للحد المؤقت للمحادثة. انتظر دقيقة ثم حاول مرة أخرى.'
      : e?.response?.data?.message || 'تعذر وصول الرد. يمكنك إعادة المحاولة دون تكرار رسالتك.';
  } finally {
    if (generation === requestGeneration) { sending.value = false; await scrollToLatest(); }
  }
}
function keyboardSend(event) {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); send(); }
}
</script>

<template>
  <section class="ibn-sina" aria-labelledby="ibn-sina-title" dir="rtl" data-ibn-sina>
    <header class="chat-header">
      <div class="avatar" aria-hidden="true"><i class="pi pi-comments"></i></div>
      <div class="identity"><p class="eyebrow">خلّينا نفهم نتائجك</p><h2 id="ibn-sina-title">ابن سينا</h2><p>مساعد طبي بالذكاء الاصطناعي</p></div>
      <button type="button" class="new-chat" @click="reset" :disabled="!messages.length && !sending" aria-label="بدء محادثة جديدة"><i class="pi pi-refresh" aria-hidden="true"></i><span>محادثة جديدة</span></button>
    </header>

    <div class="report-picker">
      <label for="ibn-sina-report">أي تقرير تحب نشرحه؟</label>
      <select id="ibn-sina-report" v-model="selectedId">
        <option value="">سؤال عام — بدون مشاركة تقرير</option>
        <option v-for="report in reports" :key="report.id" :value="String(report.id)">تقرير #{{ report.id }} · {{ formatDate(report.date) }} · {{ readyCount(report) }} جاهز</option>
      </select>
      <p v-if="selectedReport" class="context-note">{{ readyCount(selectedReport) }} تحليل جاهز للشرح<span v-if="pendingCount(selectedReport)"> · {{ pendingCount(selectedReport) }} قيد الإنجاز ولا يدخل في الشرح</span>. تغيير التقرير يبدأ محادثة جديدة.</p>
      <p v-else class="context-note">اختر تقريراً لشرح نتائجك الخاصة، أو ابدأ بسؤال صحي عام.</p>
    </div>

    <div v-if="!consent" class="consent-panel">
      <i class="pi pi-lock" aria-hidden="true"></i>
      <div><strong>قبل أن نبدأ</strong><p>عند الإرسال تُشارك رسائلك والنتائج المنشورة من التقرير المختار، مع العمر والجنس إن توفرا، مع مزوّد الذكاء الاصطناعي الذي يعتمده المختبر. لا نرسل اسمك أو هاتفك أو أسعار فواتيرك تلقائياً. تجنب كتابة معلومات تعريفية داخل المحادثة.</p>
      <label class="consent-check"><input v-model="consent" type="checkbox">أوافق على استخدام هذه البيانات لشرح النتائج</label></div>
    </div>

    <p v-if="contextChanged" role="status" class="report-picker context-note">تغيّرت النتائج المنشورة؛ جددنا سياق المحادثة حتى يعتمد الشرح على أحدث نتائج متاحة فقط.</p>
    <div ref="log" class="chat-log" role="log" aria-label="محادثتك مع ابن سينا" aria-live="polite" :aria-busy="sending">
      <div v-if="!messages.length" class="welcome">
        <div class="welcome-icon" aria-hidden="true"><i class="pi pi-sparkles"></i></div>
        <h3>أهلاً بيك، أنا ابن سينا</h3>
        <p>أساعدك تفهم معنى التحاليل، وأوضح لك النتائج المتاحة بلغة بسيطة، ونجهّز سوا أسئلتك للطبيب.</p>
        <div class="suggestions"><button v-for="prompt in prompts" :key="prompt" type="button" @click="input = prompt">{{ prompt }}<i class="pi pi-arrow-up-left" aria-hidden="true"></i></button></div>
      </div>
      <article v-for="(message, index) in messages" :key="index" :class="['message', message.role]">
        <p class="speaker">{{ message.role === 'assistant' ? 'ابن سينا · ذكاء اصطناعي' : 'أنت' }}</p>
        <p class="message-content" dir="auto">{{ message.content }}</p>
        <p v-if="message.source" class="source">استند الشرح إلى النتائج المنشورة من تقرير #{{ message.source.invoice_id }} · {{ message.source.ready }} جاهز<span v-if="message.source.pending"> · {{ message.source.pending }} قيد الإنجاز</span></p>
      </article>
      <p v-if="sending" class="typing" role="status"><span class="typing-dot"></span>ابن سينا يجهّز الشرح…</p>
    </div>

    <div v-if="error" class="chat-error" role="alert"><p>{{ error }}</p><button type="button" @click="send(true)" :disabled="sending || !consent">إعادة المحاولة</button></div>
    <form class="composer" @submit.prevent="send()">
      <label class="sr-only" for="ibn-sina-message">رسالتك إلى ابن سينا</label>
      <textarea id="ibn-sina-message" v-model="input" rows="2" maxlength="2000" placeholder="اكتب سؤالك عن النتائج هنا…" @keydown="keyboardSend" :disabled="sending"></textarea>
      <div class="composer-actions"><span>{{ input.length }}/2000</span><button type="submit" :disabled="!consent || sending || !input.trim()"><i class="pi pi-send" aria-hidden="true"></i>{{ sending ? 'جارٍ الرد…' : 'إرسال' }}</button></div>
    </form>
    <footer class="chat-note">ابن سينا مساعد آلي وليس طبيباً بشرياً؛ قد يخطئ، ولا يقدّم تشخيصاً أو وصفة علاج. راجع الطبيب لتقييم حالتك. عند الأعراض الشديدة اطلب الطوارئ ولا تنتظر المحادثة. تبقى المحادثة في هذه الصفحة وتُمسح عند مغادرتها.</footer>
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
</style>
