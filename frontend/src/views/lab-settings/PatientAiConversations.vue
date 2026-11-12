<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { $http } from '@/plugins/axios';
const root = '/lab-settings/ibn-sina/conversations';
const search = ref(''), from = ref(''), to = ref('');
const rows = ref([]), page = ref(1), lastPage = ref(1), total = ref(0), loading = ref(false), error = ref('');
const selected = ref(null), turns = ref([]), turnPage = ref(1), turnLastPage = ref(1), detailLoading = ref(false), detailError = ref('');
let listGeneration = 0, detailGeneration = 0, listController, detailController, selectedId = null;
const date = value => value ? new Date(value).toLocaleString('ar-IQ', { timeZone: 'Asia/Baghdad', dateStyle: 'medium', timeStyle: 'short' }) : '—';
const status = turn => turn.status === 'answered' ? 'تم الرد' : turn.status === 'failed' ? 'تعذر الرد' : new Date(turn.updated_at).getTime() < Date.now() - 120000 ? 'لم يكتمل الرد' : 'قيد الرد';
function close() { detailGeneration++; detailController?.abort(); selectedId = null; selected.value = null; turns.value = []; detailError.value = ''; detailLoading.value = false; }
async function load(target = 1) {
  const current = ++listGeneration; listController?.abort(); listController = new AbortController();
  close(); loading.value = true; error.value = ''; rows.value = []; total.value = 0;
  try {
    const { data } = await $http.get(root, { params: { search: search.value || undefined, from: from.value || undefined, to: to.value || undefined, page: target }, signal: listController.signal });
    if (current !== listGeneration) return;
    rows.value = data.data; page.value = data.current_page; lastPage.value = data.last_page; total.value = data.total;
  } catch (e) {
    if (current === listGeneration) error.value = e.response?.status === 403 ? 'ليس لديك صلاحية الاطلاع على محادثات ابن سينا.' : 'تعذر تحميل السجل. تأكد من الاتصال والتواريخ ثم أعد المحاولة.';
  } finally { if (current === listGeneration) loading.value = false; }
}
async function open(id, target = 1) {
  const current = ++detailGeneration; detailController?.abort(); detailController = new AbortController();
  selectedId = id; selected.value = null; turns.value = []; detailLoading.value = true; detailError.value = '';
  try {
    const { data } = await $http.get(`${root}/${id}`, { params: { page: target }, signal: detailController.signal });
    if (current !== detailGeneration) return;
    selected.value = data.conversation; turns.value = data.data; turnPage.value = data.current_page; turnLastPage.value = data.last_page;
  } catch (e) {
    if (current === detailGeneration) detailError.value = e.response?.status === 403 ? 'ليس لديك صلاحية قراءة هذه المحادثة.' : 'تعذر فتح المحادثة؛ قد تكون غير متاحة أو انقطع الاتصال.';
  } finally { if (current === detailGeneration) detailLoading.value = false; }
}
onMounted(() => load());
onBeforeUnmount(() => { listGeneration++; detailGeneration++; listController?.abort(); detailController?.abort(); });
</script>

<template>
  <section class="ai-journal" dir="rtl" aria-labelledby="ai-journal-title">
    <header class="journal-header">
      <div><span class="eyebrow">بوابة المريض · سجل الاستخدام</span><h2 id="ai-journal-title">محادثات ابن سينا</h2><p>شاهد ما يكتبه المرضى وردود المساعد، مع سياق التحاليل وتوقيت كل رسالة.</p></div>
      <button type="button" @click="load(page)" :disabled="loading">تحديث السجل</button>
    </header>
    <p class="privacy-note">تظهر الرسائل المُرسلة بعد تفعيل هذا التحديث وموافقة المريض على الحفظ والمشاركة مع المختبر. المحادثات القديمة والمسودات غير المُرسلة غير متوفرة. الاطلاع مخصّص للمخوّلين ويُسجَّل في سجل النشاط.</p>
    <form class="filters" @submit.prevent="load(1)">
      <label class="search">اسم المريض أو رقمه<input v-model="search" maxlength="100" placeholder="ابحث بالاسم أو كود المريض" type="search"></label>
      <label>آخر نشاط من<input v-model="from" type="date" :max="to || undefined"></label>
      <label>إلى<input v-model="to" type="date" :min="from || undefined"></label>
      <button class="primary" :disabled="loading">بحث</button>
    </form>
    <div class="count"><strong>{{ total }}</strong> محادثة <span>· جميع الأوقات بتوقيت بغداد</span></div>
    <p v-if="error" class="error" role="alert">{{ error }} <button @click="load(page)">إعادة المحاولة</button></p>
    <p v-else-if="loading" class="empty" role="status">جارٍ تحميل المحادثات…</p>
    <p v-else-if="!rows.length" class="empty">لا توجد محادثات مطابقة. سيظهر السجل عند إرسال أول رسالة بعد موافقة المريض.</p>
    <div v-else class="journal-layout">
      <div class="list-panel">
        <div class="conversation-list" aria-label="قائمة المحادثات">
          <button v-for="row in rows" :key="row.id" type="button" class="conversation" :class="{ active: selected?.id === row.id }" @click="open(row.id)" :aria-pressed="selected?.id === row.id">
            <span class="row-heading"><strong>{{ row.patient_name }}</strong><small>{{ row.message_count }} رسالة من المريض</small></span>
            <span class="meta">كود المريض: {{ row.patient_code || '—' }} · {{ row.invoice_id ? `تقرير #${row.invoice_id}` : 'محادثة عامة' }}</span>
            <span class="preview" dir="auto">{{ row.last_message }}</span>
            <span class="row-footer"><time>{{ date(row.updated_at) }}</time><span class="status">{{ status({ status: row.last_status, updated_at: row.updated_at }) }}</span></span>
            <span class="open-label">عرض المحادثة ←</span>
          </button>
        </div>
        <nav v-if="lastPage > 1" class="pagination" aria-label="صفحات المحادثات"><button @click="load(page - 1)" :disabled="page <= 1 || loading">السابق</button><span>{{ page }} / {{ lastPage }}</span><button @click="load(page + 1)" :disabled="page >= lastPage || loading">التالي</button></nav>
      </div>
      <section class="transcript" aria-label="تفاصيل المحادثة" :aria-busy="detailLoading">
        <p v-if="detailLoading" class="empty" role="status">جارٍ فتح المحادثة…</p>
        <div v-else-if="detailError" class="error" role="alert">{{ detailError }} <button @click="open(selectedId)">إعادة فتح المحادثة</button></div>
        <template v-else-if="selected">
          <header class="transcript-header"><div><h3>{{ selected.patient_name }}</h3><p>بدأت {{ date(selected.created_at) }}</p></div><button @click="close" aria-label="إغلاق المحادثة">إغلاق</button></header>
          <div class="scope"><strong>{{ selected.invoice_id ? `التحاليل المختارة من تقرير #${selected.invoice_id}` : 'محادثة عامة بدون تقرير' }}</strong><p v-if="selected.scope.analyses.length">{{ selected.scope.analyses.join('، ') }}</p><p>وافق المريض على حفظ المحادثة وإتاحتها للمختبر في {{ date(selected.consented_at) }}.</p></div>
          <div class="turns">
            <div v-for="turn in turns" :key="turn.id" class="turn">
              <article class="bubble patient"><div class="bubble-heading"><strong>المريض</strong><time>{{ date(turn.created_at) }}</time></div><p dir="auto">{{ turn.message }}</p></article>
              <article v-if="turn.reply" class="bubble assistant" :class="{ urgent: turn.urgent }"><div class="bubble-heading"><strong>ابن سينا · ذكاء اصطناعي</strong><time>{{ date(turn.updated_at) }}</time></div><span v-if="turn.urgent" class="urgent-tag">رد يتضمن تنبيهاً عاجلاً</span><p dir="auto">{{ turn.reply }}</p></article>
              <p v-else class="reply-status">{{ status(turn) }} — رسالة المريض محفوظة.</p>
            </div>
          </div>
          <nav v-if="turnLastPage > 1" class="pagination" aria-label="صفحات الرسائل"><button @click="open(selected.id, turnPage - 1)" :disabled="turnPage <= 1">رسائل أقدم</button><span>{{ turnPage }} / {{ turnLastPage }}</span><button @click="open(selected.id, turnPage + 1)" :disabled="turnPage >= turnLastPage">رسائل أحدث</button></nav>
          <p class="transcript-note">هذه ردود مساعد آلي؛ لا تمثّل تشخيصاً معتمداً أو متابعة مباشرة لحالة المريض.</p>
        </template>
        <p v-else class="empty">اختَر محادثة لقراءة رسائل المريض وردود ابن سينا.</p>
      </section>
    </div>
  </section>
</template>
<style scoped>
.ai-journal{color:#183a36;min-width:0}.journal-header{display:flex;justify-content:space-between;align-items:center;gap:18px;background:linear-gradient(120deg,#e4f3ee,#f6faf9);padding:26px;border:1px solid #d3e6de;border-radius:20px}.eyebrow{font-size:11px;color:#527b6c}.journal-header h2{font-size:24px;font-weight:800;margin:6px 0}.journal-header p{font-size:13px;line-height:1.9;color:#516f64}.ai-journal button{border:1px solid #ceded7;border-radius:10px;padding:9px 14px;font-size:12px;background:white;cursor:pointer}.ai-journal button:disabled{opacity:.45;cursor:default}.ai-journal button:focus-visible,input:focus-visible{outline:3px solid #49a386;outline-offset:2px}.privacy-note{font-size:12px;line-height:1.9;padding:16px 4px;color:#64766f}.filters{display:flex;align-items:end;flex-wrap:wrap;gap:12px;padding:18px;background:white;border:1px solid #dce7e1;border-radius:16px}.filters label{display:flex;flex-direction:column;gap:7px;font-size:12px;flex:1;min-width:140px}.filters .search{flex:2}.filters input{width:100%;min-width:0;border:1px solid #cbded4;border-radius:9px;padding:11px;font-size:13px;background:#fafcfb}.filters .primary{background:#176b5b;color:white;padding:12px 26px}.count{padding:18px 4px;font-size:13px}.count strong{font-size:20px}.count span{color:#6c7d74;font-size:11px}.journal-layout{display:grid;grid-template-columns:minmax(240px,1fr) minmax(0,1.7fr);gap:18px;align-items:start}.conversation-list{display:grid;gap:10px}.ai-journal .conversation{display:block;text-align:right;width:100%;padding:16px;border-radius:14px;min-width:0}.conversation.active{border-color:#176b5b;background:#edf8f2}.row-heading,.row-footer{display:flex;gap:10px;justify-content:space-between;align-items:center}.row-heading strong{font-size:14px}.row-heading small{font-size:10px;color:#55786a}.meta{display:block;font-size:10px;color:#6b7d74;margin-top:9px}.preview{display:block;white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;line-height:1.9;margin:12px 0;color:#354d43;text-align:start}.row-footer{font-size:10px;color:#6c7e75}.status{background:#eef4f0;border-radius:6px;padding:4px 6px;white-space:nowrap}.open-label{display:block;font-size:11px;font-weight:700;color:#176b5b;margin-top:12px}.transcript{min-width:0;background:white;border:1px solid #dae6df;border-radius:18px;overflow:hidden}.transcript-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px;border-bottom:1px solid #e4ece7}.transcript-header h3{font-weight:800;font-size:17px}.transcript-header p{font-size:11px;color:#6e8076;margin-top:5px}.scope{padding:16px 20px;background:#f7faf8;font-size:12px;line-height:1.9;overflow-wrap:anywhere}.scope p:last-child{font-size:10px;color:#61796d;margin-top:8px}.turns{padding:20px;max-height:680px;overflow-y:auto}.bubble{padding:15px;border-radius:14px;margin-bottom:12px;overflow-wrap:anywhere}.bubble.patient{background:#176b5b;color:#fff;margin-inline-start:25px}.bubble.assistant{background:#f0f5f2;margin-inline-end:25px;border:1px solid #e1ebe4}.bubble-heading{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;font-size:10px;opacity:.85}.bubble p{font-size:14px;line-height:2;white-space:pre-wrap;text-align:start;unicode-bidi:plaintext;margin-top:10px}.turn{margin-bottom:22px}.bubble.urgent{background:#fff6ef;border-color:#edd1b9}.urgent-tag{display:block;font-size:10px;color:#944c26;margin-top:8px}.reply-status{font-size:11px;color:#8d6244;padding:4px 10px}.pagination{display:flex;align-items:center;justify-content:center;gap:14px;padding:14px;font-size:12px}.empty{padding:38px 20px;text-align:center;color:#6a8074;font-size:13px;line-height:2}.error{padding:18px;color:#a34136;background:#fff4f1;border-radius:12px;font-size:13px;line-height:2}.transcript-note{padding:14px 20px;font-size:10px;line-height:1.9;color:#6c8074;border-top:1px solid #e4ece7}@media(max-width:800px){.journal-layout{grid-template-columns:1fr}.journal-header{align-items:start;padding:20px}.journal-header h2{font-size:21px}.journal-header button{flex-shrink:0}.filters{padding:14px}.filters .search{flex-basis:100%}.turns{padding:14px}.bubble.patient{margin-inline-start:12px}.bubble.assistant{margin-inline-end:12px}.transcript{grid-row:1}.journal-header{flex-wrap:wrap}}
</style>
