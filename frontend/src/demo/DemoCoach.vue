<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { lessons, answerQuestion, normalizeArabic } from './guide';
import { examples, enterDemo } from './session';
const router = useRouter(), route = useRoute();
const read = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch { return fallback; } };
const open = ref(false), tab = ref('lessons'), query = ref(''), question = ref(''), notice = ref(''), busy = ref(false), confirmReset = ref(false);
const progress = ref(read('demo-progress', {}));
const activeId = ref(localStorage.getItem('demo-lesson') || ''), stepIndex = ref(Number(localStorage.getItem('demo-step') || 0));
const ready = ref(false), targetFound = ref(false);
const exampleOnly = ref(localStorage.getItem('demo-example-only') === 'true');
const libraryOpen = ref(false), panel = ref(null), panelTop = ref(false);
const compact = computed(() => Boolean(active.value) && tab.value === 'lessons' && !libraryOpen.value);
const messages = ref(read('demo-chat', [{ role:'assistant', text:'أهلاً بيك! اكتب شنو تريد تتعلم عن البرنامج. أشرح لك الميزة وأفتح لك تدريبها أو مثال جاهز. هذا مساعد تعليمي من دليل البرنامج.', matches:[] }]));
const active = computed(() => lessons.find(l => l.id === activeId.value));
const step = computed(() => exampleOnly.value ? null : active.value?.steps[stepIndex.value]);
const signedIn = computed(() => route.path !== '/' && Boolean(localStorage.getItem('token')));
const visible = computed(() => lessons.filter(l => normalizeArabic(l.title + ' ' + l.keywords).includes(normalizeArabic(query.value))));
const completed = computed(() => Object.values(progress.value).filter(v => v === 'done').length);
const isLessonRoute = computed(() => {
  if (!active.value) return false;
  if (['results','print','devices'].includes(active.value.id)) return route.path.startsWith('/medical_reports') || route.path === active.value.route;
  if (active.value.id === 'portal') return route.path.startsWith('/portal/') || route.path === active.value.route;
  return route.path.startsWith(active.value.route);
});
let marked = null, observer = null, timer;
function unmark() { marked?.classList.remove('demo-mark'); marked = null; }
function highlight() {
  unmark(); targetFound.value = false;
  if (!open.value || !isLessonRoute.value || !step.value?.selector) return;
  marked = [...document.querySelectorAll(step.value.selector)].find(el => el.getClientRects().length);
  if (marked) { marked.classList.add('demo-mark'); targetFound.value = true; }
  positionPanel();
}
function positionPanel() {
  if (!compact.value || !panel.value || !marked) return;
  const target = marked.getBoundingClientRect(), box = panel.value.getBoundingClientRect();
  if (target.left < box.right && target.right > box.left && target.top < box.bottom && target.bottom > box.top) {
    panelTop.value = target.top > innerHeight / 2;
  }
}
watch(exampleOnly, value => localStorage.setItem('demo-example-only', String(value)));
watch([activeId, stepIndex], () => { ready.value = false; localStorage.setItem('demo-lesson', activeId.value); localStorage.setItem('demo-step', stepIndex.value); nextTick(highlight); });
watch(() => route.fullPath, () => { ready.value = false; nextTick(highlight); });
watch(open, () => nextTick(highlight));
watch(progress, value => localStorage.setItem('demo-progress', JSON.stringify(value)), { deep:true });
function goNext(skipped = false) {
  if (!active.value || (!skipped && !step.value.read && !ready.value)) return;
  if (skipped) progress.value[activeId.value] = 'skipped';
  if (stepIndex.value + 1 < active.value.steps.length) stepIndex.value++;
  else {
    if (progress.value[activeId.value] !== 'skipped') progress.value[activeId.value] = 'done';
    notice.value = progress.value[activeId.value] === 'done' ? 'أكملت الدرس. تقدر تعيده أو تختار الموضوع التالي.' : 'حُفظ الدرس كمتخطّى؛ تقدر تعيده بأي وقت.';
    activeId.value = ''; stepIndex.value = 0; unmark();
  }
}
function lessonPath(item, showExample) {
  const data = examples();
  if (showExample) {
    if (item.example === 'results') return `/medical_reports/update-result/${data.invoices?.[1]}`;
    if (item.example === 'report') return `/medical_reports/update-result/${data.invoices?.[0]}`;
    if (item.example === 'invoice') return `/invoices/${data.invoices?.[0]}/edit`;
    if (item.example === 'portal') return `/portal/${data.portal}`;
  }
  return item.route;
}
async function teach(item, showExample = false, resume = false) {
  notice.value = ''; tab.value = 'lessons'; open.value = true; libraryOpen.value = false; panelTop.value = false;
  const identity = item.identity || 'lab'; activeId.value = item.id; exampleOnly.value = showExample;
  if (!resume) { stepIndex.value = 0; if (!showExample) delete progress.value[item.id]; }
  const destination = lessonPath(item, showExample);
  if (localStorage.getItem('demo-identity') !== identity) {
    busy.value = true;
    try { await enterDemo('resume', identity); location.assign(router.resolve(destination).href); }
    catch(e) { notice.value = e.response?.data?.message || e.message; } finally { busy.value = false; }
  } else await router.push(destination);
  nextTick(highlight);
}
function ask() {
  const text = question.value.trim().slice(0,1000); if (!text) return;
  const answer = answerQuestion(text);
  messages.value.push({ role:'user', text }, { role:'assistant', text:answer.text, matches:answer.matches.map(x => x.id) });
  messages.value = messages.value.slice(-30); question.value = '';
  localStorage.setItem('demo-chat', JSON.stringify(messages.value));
  nextTick(() => document.querySelector('.demo-messages')?.scrollTo({ top:100000, behavior:'smooth' }));
}
function onSuccess(event) {
  const expected = step.value?.request;
  if (isLessonRoute.value && expected && expected.path === event.detail.path && expected.method === event.detail.method) ready.value = true;
}
function onInteraction(event) {
  if (!isLessonRoute.value || !step.value?.selector || event.type !== step.value.event) return;
  if (event.target.closest?.(step.value.selector) && (event.type !== 'input' || String(event.target.value || '').trim().length >= 3)) ready.value = true;
}
function openCoach() { open.value = true; }
function onNotice(event) { notice.value = event.detail; open.value = true; }
async function reset() {
  busy.value = true;
  try { await enterDemo('reset'); location.assign(router.resolve('/dashboard').href); }
  catch(e) { notice.value = e.response?.data?.message || e.message; }
  finally { busy.value = false; confirmReset.value = false; }
}
onMounted(() => {
  window.addEventListener('demo-api-success', onSuccess); window.addEventListener('demo-notice', onNotice); window.addEventListener('demo-open', openCoach);
  document.addEventListener('click', onInteraction, true); document.addEventListener('input', onInteraction, true);
  window.addEventListener('resize', positionPanel); document.addEventListener('scroll', positionPanel, true);
  observer = new MutationObserver(() => { clearTimeout(timer); timer = setTimeout(highlight, 120); });
  observer.observe(document.body, { childList:true, subtree:true });
  if (active.value) open.value = true;
});
onBeforeUnmount(() => {
  unmark(); observer?.disconnect(); clearTimeout(timer);
  window.removeEventListener('demo-api-success', onSuccess); window.removeEventListener('demo-notice', onNotice); window.removeEventListener('demo-open', openCoach);
  document.removeEventListener('click', onInteraction, true); document.removeEventListener('input', onInteraction, true);
  window.removeEventListener('resize', positionPanel); document.removeEventListener('scroll', positionPanel, true);
});
</script>
<template>
<div v-if="signedIn" class="demo-coach-root" dir="rtl">
  <div class="demo-ribbon"><span>تجربة تعليمية · بيانات تجريبية</span><button @click="router.push('/')">مركز التدريب</button><button @click="open=!open">{{open ? 'تصغير الدليل' : 'افتح الدليل'}}</button></div>
  <button v-if="!open" class="demo-launcher" @click="open=true">✦ اسأل دليل المختبر</button>
  <aside v-else ref="panel" class="demo-coach" :class="{'demo-coach-compact':compact,'demo-coach-top':compact && panelTop}" aria-label="دليل التدريب">
    <header><div><strong>دليل المختبر الرقمي</strong><small>شرح وتطبيق داخل البرنامج</small></div><button aria-label="تصغير دليل التدريب" @click="open=false">×</button></header>
    <nav><button :aria-pressed="tab==='lessons'" @click="tab='lessons'">الدروس {{completed}}/{{lessons.length}}</button><button :aria-pressed="tab==='chat'" @click="tab='chat'">اسألني عن البرنامج</button></nav>
    <div v-if="notice" class="demo-notice" role="status">{{ notice }}<button aria-label="إغلاق التنبيه" @click="notice=''">×</button></div>
    <div v-if="tab==='lessons'" class="demo-lessons">
      <section v-if="active && exampleOnly" class="demo-step"><strong>{{active.title}} — مثال عملي</strong><p>{{active.explanation}}</p><p class="demo-hint">أنت تتصفح المثال. تقدر تجرب عليه بحرية، أو تبدأ الدرس الموجّه.</p><button class="demo-text-action" @click="teach(active)">ابدأ التدريب خطوة بخطوة</button></section>
      <section v-if="active && step" class="demo-step">
        <p class="demo-step-number">{{active.title}} · {{stepIndex+1}} / {{active.steps.length}}</p><progress :value="stepIndex" :max="active.steps.length" :aria-label="'تقدم درس '+active.title" /><p>{{step.text}}</p>
        <p v-if="!isLessonRoute" class="demo-hint">أنت خارج شاشة الدرس. <button @click="teach(active,false,true)">ارجع للتدريب</button></p>
        <p v-else-if="step.selector && !targetFound && !ready" class="demo-hint">ننتظر ظهور الحقل أو الزر المطلوب؛ أغلق النافذة الأخرى أو ارجع للخطوة السابقة.</p>
        <p v-if="step.request && !ready" class="demo-hint">بانتظار نجاح الحفظ داخل البرنامج…</p><p v-if="ready" class="demo-success" role="status">✓ تم تنفيذ الخطوة بنجاح</p>
        <div class="demo-step-actions"><button :disabled="stepIndex===0" @click="stepIndex--">السابق</button><button :disabled="!isLessonRoute || (!step.read && !ready)" @click="goNext()">{{stepIndex===active.steps.length-1 ? 'إنهاء الدرس' : 'التالي'}}</button><button @click="goNext(true)">تخطي</button></div>
        <button class="demo-text-action" @click="teach(active)">ابدأ الدرس من جديد</button><button class="demo-text-action" @click="activeId=''; unmark()">إيقاف الدرس</button>
      </section>
      <button v-if="active" class="demo-text-action" @click="libraryOpen=!libraryOpen">{{libraryOpen?'إخفاء قائمة الدروس':'تصفح الدروس الأخرى'}}</button>
      <template v-if="!active || libraryOpen">
      <label class="demo-search">ابحث عن درس<input v-model="query" type="search" placeholder="فاتورة، طباعة، بوابة المريض…" /></label>
      <div class="demo-lesson-list"><article v-for="item in visible" :key="item.id"><div><strong>{{item.title}}</strong><span v-if="progress[item.id]">{{progress[item.id]==='done'?'✓ مكتمل':'متخطّى'}}</span></div><p>{{item.explanation}}</p><button :disabled="busy" @click="teach(item)">علّمني خطوة بخطوة</button><button :disabled="busy" @click="teach(item,true)">{{item.example?'أراويك مثال':'افتح الشاشة'}}</button></article><p v-if="!visible.length">ماكو درس مطابق. جرّب كلمة ثانية.</p></div>
      </template>
    </div>
    <div v-else class="demo-chat">
      <div class="demo-messages" aria-live="polite"><article v-for="(message,index) in messages" :key="index" :class="message.role"><small>{{message.role==='user'?'أنت':'دليل المختبر'}}</small><p>{{message.text}}</p><div v-if="message.matches?.length"><div v-for="id in message.matches" :key="id"><template v-if="lessons.find(l=>l.id===id)"><strong>{{lessons.find(l=>l.id===id).title}}</strong><button @click="teach(lessons.find(l=>l.id===id))">علّمني</button><button @click="teach(lessons.find(l=>l.id===id),true)">أراويك</button></template></div></div></article></div>
      <form @submit.prevent="ask"><label for="demo-question">شنو تحب تتعلم؟</label><div><input id="demo-question" v-model="question" maxlength="1000" placeholder="شلون أضيف تعليق للنتيجة؟" autocomplete="off" /><button :disabled="!question.trim()">إرسال</button></div></form><small class="demo-chat-note">الإجابات من دليل الميزات، وليست استشارة طبية. لا تكتب بيانات مرضى حقيقية.</small>
    </div>
    <footer><button :disabled="busy" @click="teach(lessons.find(l=>l.id==='patients'))">حساب المختبر</button><button :disabled="busy" @click="confirmReset=!confirmReset">إعادة ضبط التجربة</button><div v-if="confirmReset" class="demo-reset"><p>تبدأ مساحة جديدة وتُغلق مساحة التدريب الحالية. يُمسح تقدم الدروس من هذا المتصفح.</p><button :disabled="busy" @click="reset">نعم، ابدأ من جديد</button><button @click="confirmReset=false">إلغاء</button></div></footer>
  </aside>
</div>
</template>
<style>
.demo-ribbon{position:fixed;top:0;left:50%;transform:translateX(-50%);z-index:2100;display:flex;gap:12px;align-items:center;background:#113f39;color:white;padding:6px 15px;border-radius:0 0 12px 12px;font-size:11px;box-shadow:0 3px 12px #0002;white-space:nowrap}.demo-ribbon button{text-decoration:underline}.demo-launcher{position:fixed;bottom:22px;left:22px;z-index:2100;background:#176c60;color:white;padding:15px 22px;border-radius:30px;box-shadow:0 8px 28px #164d4530;font-size:14px}.demo-coach{position:fixed;left:18px;bottom:18px;width:370px;max-width:calc(100vw - 24px);max-height:calc(100dvh - 65px);z-index:2200;background:white;border:1px solid #bdd3cc;box-shadow:0 20px 70px #133b3b30;border-radius:18px;overflow:hidden;display:flex;flex-direction:column;color:#193b35;font-family:inherit;font-size:13px}.demo-coach header{padding:17px 18px;background:#174f46;color:white;display:flex;justify-content:space-between}.demo-coach header strong{font-size:16px}.demo-coach header small{display:block;opacity:.75;margin-top:4px}.demo-coach header>button{font-size:25px;padding:0 8px}.demo-coach nav{display:flex;border-bottom:1px solid #e0eae7}.demo-coach nav button{flex:1;padding:12px 5px;font-size:12px}.demo-coach nav button[aria-pressed=true]{color:#16725f;background:#edf6f1;border-bottom:2px solid #16725f;font-weight:bold}.demo-lessons{overflow:auto;padding:15px;min-height:100px}.demo-search{display:block;font-size:12px}.demo-search input,.demo-chat input{border:1px solid #d0ded8;border-radius:9px;padding:10px;font-size:12px;width:100%;margin:7px 0;outline-offset:2px}.demo-lesson-list article{border-bottom:1px solid #e5eee8;padding:17px 0}.demo-lesson-list article>div{display:flex;justify-content:space-between;gap:10px}.demo-lesson-list article span{font-size:10px;color:#268064}.demo-lesson-list p{font-size:12px;line-height:1.85;color:#65776e;margin:8px 0}.demo-lesson-list button,.demo-messages button{border:1px solid #bad4c9;border-radius:8px;color:#216453;padding:7px 9px;font-size:11px;margin-inline-end:6px;margin-top:5px}.demo-step{background:#edf6f1;border:1px solid #c4e0cf;border-radius:12px;padding:15px;margin-bottom:18px}.demo-step-number{font-weight:700;font-size:12px}.demo-step>p{line-height:1.9;margin-bottom:10px}.demo-step progress{width:100%;height:5px;accent-color:#21705c;margin:8px 0 12px}.demo-hint{font-size:11px;color:#866432}.demo-hint button{text-decoration:underline}.demo-success{color:#1e7151;font-size:12px}.demo-step-actions{display:flex;gap:8px;margin:13px 0}.demo-step-actions button{border-radius:7px;border:1px solid #bad0c3;padding:7px 10px;background:white}.demo-step-actions button:nth-child(2){background:#176c60;color:white}.demo-coach button:disabled{opacity:.4;cursor:not-allowed}.demo-text-action{font-size:10px;text-decoration:underline;margin-inline-end:12px}.demo-coach footer{padding:12px;border-top:1px solid #e1ebe5;font-size:11px;display:flex;flex-wrap:wrap;justify-content:space-between;background:#f9fbfa}.demo-reset{background:#fff4e8;padding:10px;margin-top:10px;border-radius:8px;width:100%;line-height:1.9}.demo-reset button{padding:8px;text-decoration:underline}.demo-chat{display:flex;flex-direction:column;overflow:hidden;min-height:200px}.demo-messages{overflow:auto;min-height:140px;max-height:50vh;padding:16px}.demo-messages article{padding:12px;margin-bottom:12px;border-radius:12px;background:#f0f5f2;line-height:1.85;white-space:pre-wrap}.demo-messages article.user{background:#dff0e6;margin-inline-start:25px}.demo-messages small{color:#647c6d;font-size:10px}.demo-messages strong{display:block;font-size:11px;margin-top:9px}.demo-chat form{padding:8px 14px 0}.demo-chat form>div{display:flex;gap:6px;align-items:center}.demo-chat form button{padding:10px;background:#176c60;color:white;border-radius:8px}.demo-chat-note{padding:4px 14px 12px;font-size:10px;color:#718174}.demo-notice{background:#fff5dc;padding:12px;line-height:1.8;font-size:12px}.demo-notice button{float:left;padding:0 7px}.demo-mark{outline:3px solid #12a576!important;outline-offset:5px!important;scroll-margin:85px}@media(max-width:480px){.demo-coach{bottom:10px;left:12px;width:calc(100vw - 24px)}.demo-ribbon{width:100%;justify-content:center;border-radius:0;font-size:9px;padding:5px 3px;gap:8px}}@media print{.demo-coach-root{display:none!important}.demo-mark{outline:none!important}}
.demo-coach-compact{max-height:min(520px,55dvh)}.demo-coach-compact header{padding:10px 14px}.demo-coach-compact header small{display:none}.demo-coach-compact .demo-step{margin-bottom:8px}.demo-coach-top{top:48px;bottom:auto}@media(max-width:480px){.demo-coach-compact{max-height:45dvh}}
</style>
