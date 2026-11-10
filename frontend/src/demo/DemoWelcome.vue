<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { enterDemo } from './session';
import { lessons } from './guide';
const router = useRouter();
const busy = ref(false), error = ref('');
const resumable = computed(() => Boolean(localStorage.getItem('demo-secret')));
async function start(resume) {
  busy.value = true; error.value = '';
  try { await enterDemo(resume ? 'resume' : 'start'); await router.push('/dashboard'); window.dispatchEvent(new Event('demo-open')); }
  catch (e) { error.value = e.response?.data?.message || e.message || 'تعذر بدء التجربة. حاول مجدداً.'; }
  finally { busy.value = false; }
}
</script>
<template>
  <main class="demo-welcome" dir="rtl">
    <nav><strong>Digital Lab <span>المختبر الرقمي</span></strong><span class="demo-pill">مساحة تعليمية مستقلة</span></nav>
    <section class="demo-hero">
      <div><p class="demo-eyebrow">تعلّم البرنامج وأنت تستخدمه</p><h1>يومك الأول بالمختبر،<br><em>خطوة بخطوة.</em></h1><p class="demo-lead">جرّب استقبال مريض، إنشاء فاتورته وإدخال نتائجه وطباعة تقريره. نفس واجهات البرنامج، ببيانات تدريب خاصة بتجربتك.</p>
      <div class="demo-actions"><button :disabled="busy" @click="start(false)">{{ busy ? 'نجهّز مختبرك التجريبي…' : 'ابدأ تجربة جديدة ←' }}</button><button v-if="resumable" class="secondary" :disabled="busy" @click="start(true)">كمّل تجربتي</button></div>
      <p role="alert" v-if="error" class="demo-error">{{ error }}</p><p class="demo-fine">بدون إدخال بيانات حقيقية · التجربة صالحة 24 ساعة · حفظ تقدم التعليم على هذا المتصفح</p></div>
      <aside class="demo-preview"><div class="demo-preview-top">دليل المختبر الرقمي <span>عربي</span></div><p class="demo-bubble user">شلون أضيف مريض وأسوي له فاتورة؟</p><p class="demo-bubble">أشرح لك الخطوات، أو نطبقها سوا داخل البرنامج. نبدأ بمعلومات المريض وبعدين نختار التحاليل.</p><div class="demo-example-actions"><span>اشرحلي</span><span>علّمني خطوة بخطوة</span><span>أراويك مثال</span></div><div class="demo-mini-stats"><div><strong>{{ lessons.length }}</strong><small>موضوع تعليمي</small></div><div><strong>3</strong><small>حالات دفع جاهزة</small></div><div><strong>24</strong><small>ساعة للتجربة</small></div></div></aside>
    </section>
    <section class="demo-curriculum"><h2>من الاستقبال إلى متابعة عمل المختبر</h2><p>الدليل يفتح لك الشاشة المناسبة ويشرح الحقول. تقدر تتخطى الدرس أو ترجع له بأي وقت.</p><div class="demo-topic-grid"><article v-for="item in lessons" :key="item.id"><span>{{ String(lessons.indexOf(item)+1).padStart(2,'0') }}</span><h3>{{ item.title }}</h3><p>{{ item.explanation }}</p></article></div></section>
    <footer>الرسائل والإشعارات وربط الأجهزة محاكاة تعليمية. معاينة التقارير وتنزيل PDF وطباعة المتصفح تعمل على البيانات التجريبية.</footer>
  </main>
</template>
<style>
.demo-welcome{font-family:inherit;background:#f4f8f8;color:#173735;min-height:100vh;padding:0 clamp(20px,6vw,100px)}.demo-welcome nav{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:28px 0;border-bottom:1px solid #dce9e7}.demo-welcome nav strong{font-size:22px}.demo-welcome nav strong span{font-size:13px;margin-inline-start:12px;font-weight:400}.demo-pill{background:#e1eeeb;padding:8px 13px;border-radius:25px;font-size:12px}.demo-hero{display:grid;grid-template-columns:1.2fr 1fr;gap:7vw;align-items:center;padding:80px 0}.demo-eyebrow{font-size:14px;color:#39776a;margin-bottom:18px}.demo-hero h1{font-size:clamp(34px,4vw,59px);line-height:1.45;font-weight:800;margin:0}.demo-hero em{font-style:normal;color:#198071}.demo-lead{font-size:18px;line-height:1.9;margin:22px 0;max-width:630px;color:#526b68}.demo-actions{display:flex;flex-wrap:wrap;gap:12px}.demo-actions button{border:0;border-radius:12px;background:#176c60;color:white;padding:14px 25px;font-weight:700;cursor:pointer}.demo-actions button.secondary{background:white;border:1px solid #bfd7d1;color:#176c60}.demo-actions button:disabled{opacity:.6;cursor:wait}.demo-fine{font-size:12px;color:#617a76;margin-top:18px}.demo-error{color:#a22c2c;background:#fff0ed;padding:12px;border-radius:8px;margin-top:15px}.demo-preview{background:white;border:1px solid #d9e8e5;border-radius:22px;box-shadow:0 20px 60px #244f4610;padding:25px;transform:rotate(-1deg)}.demo-preview-top{display:flex;justify-content:space-between;font-weight:700;border-bottom:1px solid #e7efed;padding-bottom:19px}.demo-preview-top span{font-size:11px;color:#176c60}.demo-bubble{line-height:1.9;font-size:14px;padding:16px;border-radius:14px;background:#f0f5f4;margin-top:16px}.demo-bubble.user{background:#e2f1eb;margin-inline-start:30px}.demo-example-actions{display:flex;gap:6px;flex-wrap:wrap;margin:18px 0}.demo-example-actions span{font-size:11px;border:1px solid #c9ded7;border-radius:20px;padding:7px 10px;color:#216354}.demo-mini-stats{display:grid;grid-template-columns:repeat(3,1fr);text-align:center;border-top:1px solid #e7efed;padding-top:20px}.demo-mini-stats strong{display:block;font-size:26px}.demo-mini-stats small{font-size:11px;color:#6f817c}.demo-curriculum h2{font-size:27px;font-weight:700}.demo-curriculum>p{color:#677d76;margin:15px 0 25px}.demo-topic-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.demo-topic-grid article{background:white;padding:22px;border:1px solid #e0e9e6;border-radius:15px}.demo-topic-grid article>span{font-size:12px;color:#398271}.demo-topic-grid h3{font-weight:700;margin:10px 0;font-size:17px}.demo-topic-grid p{font-size:13px;color:#62776f;line-height:1.9}.demo-welcome footer{padding:40px 0;font-size:13px;color:#657c73;line-height:1.9}@media(max-width:800px){.demo-hero{grid-template-columns:1fr;padding:40px 0;gap:30px}.demo-topic-grid{grid-template-columns:1fr 1fr}.demo-preview{transform:none}.demo-welcome nav strong span{display:none}}@media(max-width:450px){.demo-topic-grid{grid-template-columns:1fr}}@media print{.demo-welcome{display:none}}
</style>
