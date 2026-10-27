<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { $http } from '@/plugins/axios';
import { useAuthStore } from '@/store/modules/auth';
import UiModal from '@/components/ui/Modal.vue';
import OwnerValue from './OwnerValue.vue';

const route = useRoute(), router = useRouter(), auth = useAuthStore();
const me = ref({ owner:{}, labs:[] }), labId = ref(''), loading = ref(true), error = ref(''), notice = ref('');
const section = computed(() => route.params.section || 'overview');
const today = new Intl.DateTimeFormat('en-CA', { timeZone:'Asia/Baghdad', year:'numeric', month:'2-digit', day:'2-digit' }).format(new Date());
const range = ref({ from:today, to:today }), draftRange = ref({ ...range.value });
const report = ref(null), rows = ref([]), summary = ref({}), page = ref(1), pagination = ref({ page:1, last_page:1, total:0 });
const filters = ref({ search:'', status:'', result_status:'', actor_id:'', change_type:'', invoice_id:'', event:'', doctor_id:'', lab_referral_id:'', test_id:'' });
const roles = ref([]), modal = ref(false), detail = ref(null), detailLoading = ref(false), detailKind = ref('');
const teamModal = ref(false), saving = ref(false), formError = ref(''), staffForm = ref({});
const nav = [
  { key:'overview', label:'الرئيسية', icon:'pi-home', subtitle:'صورة واضحة لكل مختبراتك' },
  { key:'invoices', label:'الفواتير', icon:'pi-wallet', subtitle:'الفواتير والتحصيل والذمم' },
  { key:'analyses', label:'التحاليل', icon:'pi-chart-bar', subtitle:'تفاصيل الفحوص والكروبات والباقات' },
  { key:'results', label:'النتائج', icon:'pi-check-square', subtitle:'متابعة إنجاز التقارير الطبية' },
  { key:'referrals', label:'الإحالات', icon:'pi-share-alt', subtitle:'الأطباء والمختبرات المحيلة' },
  { key:'activity', label:'حركة الموظفين', icon:'pi-history', subtitle:'سجل العمليات ومن نفّذها' },
  { key:'changes', label:'الرقابة والتعديلات', icon:'pi-shield', subtitle:'المحذوفات والتغييرات المالية والنتائج' },
  { key:'team', label:'فريق العمل', icon:'pi-users', subtitle:'إنشاء حسابات الموظفين وإدارتها' },
  { key:'subscriptions', label:'اشتراكاتي', icon:'pi-calendar', subtitle:'مواعيد انتهاء اشتراكات المختبرات' },
];
const activeNav = computed(() => nav.find(n => n.key === section.value) || nav[0]);
const moreOpen = ref(false), referralType = ref('doctor_referrals'), analysisSearch = ref('');
const selectedLab = computed(() => me.value.labs.find(l => String(l.id) === String(labId.value)));
const labName = computed(() => selectedLab.value?.name || 'كل مختبراتي');
const visibleLabs = computed(() => selectedLab.value ? [selectedLab.value] : me.value.labs);
const analysisRows = computed(() => (report.value?.analyses || []).filter(a => `${a.name} ${a.shortcut || ''}`.toLowerCase().includes(analysisSearch.value.toLowerCase())));
const expired = computed(() => me.value.labs.filter(l => !l.subscription || l.subscription.days_left <= 14 || !['active','trial'].includes(l.subscription.status)));
const maxTrend = computed(() => Math.max(1, ...(report.value?.trend || []).map(d => d.count)));
const money = value => value === null || value === undefined ? 'غير متوفر' : new Intl.NumberFormat('ar-IQ', { maximumFractionDigits:2 }).format(Number(value));
const number = value => new Intl.NumberFormat('ar-IQ').format(Number(value) || 0);
const date = value => value ? new Intl.DateTimeFormat('ar-IQ', { timeZone:'Asia/Baghdad', dateStyle:'medium', timeStyle:'short' }).format(new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z')) : '—';
const states = { paid:'مدفوع', partial:'مدفوع جزئياً', unpaid:'غير مدفوع', credit:'رصيد زائد' };
const events = { created:'إنشاء', updated:'تعديل', deleted:'حذف', failed:'محاولة فاشلة', action:'إجراء', clicked:'نقرة', login:'تسجيل دخول' };
const kind = { test:'تحليل', culture:'زرع', group:'كروب', package:'باقة' };
const params = () => Object.fromEntries(Object.entries({ ...range.value, ...filters.value, lab_id:labId.value, page:page.value }).filter(([,v]) => v !== '' && v !== undefined));
const errorText = e => Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'تعذر تحميل البيانات. أعد المحاولة.';
let revision = 0, detailRevision = 0;

async function load() {
  const version = ++revision;
  loading.value = true; error.value = ''; rows.value = []; summary.value = {}; report.value = null;
  if (section.value === 'team' && !labId.value) { loading.value = false; return; }
  if (section.value === 'subscriptions') { loading.value = false; return; }
  try {
    const endpoint = ['overview','analyses','referrals'].includes(section.value) ? 'overview' : section.value === 'results' ? 'invoices' : section.value;
    const { data } = await $http.get(`/owner-portal/${endpoint}`, { params:params() });
    if (version !== revision) return;
    if (endpoint === 'overview') report.value = data;
    else if (endpoint === 'team') { rows.value = data.staff; roles.value = data.roles; }
    else { rows.value = data.data; summary.value = data.summary || {}; pagination.value = data.pagination; notice.value = data.note || ''; }
  } catch (e) { if (version === revision) error.value = errorText(e); }
  finally { if (version === revision) loading.value = false; }
}
async function bootstrap() {
  try {
    const { data } = await $http.get('/owner-portal/me'); me.value = data;
    if (labId.value && !data.labs.some(l => String(l.id) === String(labId.value))) labId.value = '';
    await load();
  } catch(e) { error.value = errorText(e); loading.value = false; }
}
function go(key) { moreOpen.value = false; router.push('/owner-portal/' + (key === 'overview' ? '' : key)); }
function openInvoices(query = {}) { router.push({ path:'/owner-portal/invoices', query }); }
function applyRange() { range.value = { ...draftRange.value }; page.value = 1; load(); }
function preset(days) {
  const start = new Date(today + 'T12:00:00Z'); start.setUTCDate(start.getUTCDate() - days + 1);
  range.value = { from:start.toISOString().slice(0,10), to:today }; draftRange.value = { ...range.value }; page.value = 1; load();
}
function search() { page.value = 1; load(); }
function paginate(offset) { page.value += offset; load(); window.scrollTo({ top:0, behavior:'smooth' }); }
async function openDetail(id, type = 'invoices') {
  const version = ++detailRevision; modal.value = true; detailLoading.value = true; detail.value = null; detailKind.value = type;
  try { const { data } = await $http.get(`/owner-portal/${type}/${id}`, { params:{ lab_id:labId.value || undefined } }); if (version === detailRevision) detail.value = data; }
  catch(e) { if (version === detailRevision) detail.value = { error:errorText(e) }; }
  finally { if (version === detailRevision) detailLoading.value = false; }
}
function editStaff(row = {}) {
  staffForm.value = { id:row.id, name:row.name || '', email:row.email || '', password:'', role_id:row.role_id || '', status:row.status ?? 1 };
  formError.value = ''; teamModal.value = true;
}
async function saveStaff() {
  saving.value = true; formError.value = '';
  try {
    const id = staffForm.value.id, payload = { ...staffForm.value, lab_id:Number(labId.value) }; delete payload.id;
    if (!payload.password) delete payload.password;
    await $http[id ? 'put' : 'post']('/owner-portal/team' + (id ? `/${id}` : ''), payload);
    teamModal.value = false; await load();
  } catch(e) { formError.value = errorText(e); } finally { saving.value = false; }
}
async function logout() { try { await $http.post('/user/logout'); } finally { auth.clearSession(); router.push('/login'); } }
const changes = computed(() => detailKind.value === 'changes' ? detail.value?.snapshot?.changes || [] : detail.value?.details?.changes || []);
const rolePermissions = computed(() => roles.value.find(r => r.id === Number(staffForm.value.role_id))?.permissions || []);
watch(() => route.fullPath, () => {
  page.value = 1; modal.value = false; detailRevision++;
  for (const key of Object.keys(filters.value)) filters.value[key] = route.query[key] || '';
  load();
});
watch(labId, () => { page.value = 1; modal.value = false; teamModal.value = false; detailRevision++; load(); });
onMounted(bootstrap);
</script>

<template>
  <div class="owner-app" dir="rtl">
    <aside class="owner-sidebar">
      <a href="/owner-portal" @click.prevent="go('overview')" class="owner-brand"><span class="owner-mark">D<span>+</span></span><div>Digital Lab<small>بوابة المالك</small></div></a>
      <p class="owner-nav-caption">إدارة أعمالك</p>
      <nav aria-label="أقسام بوابة المالك"><button v-for="item in nav" :key="item.key" :class="{ active:section === item.key }" @click="go(item.key)"><i :class="['pi', item.icon]" aria-hidden="true"></i>{{ item.label }}<span v-if="section === item.key" class="nav-dot"></span></button></nav>
      <div class="owner-sidebar-footer"><span class="owner-avatar">{{ me.owner.name?.slice(0,1) || 'م' }}</span><div>{{ me.owner.name || 'حساب المالك' }}<small>{{ number(me.labs.length) }} مختبر مرتبط</small></div><button @click="logout" title="تسجيل الخروج" aria-label="تسجيل الخروج"><i class="pi pi-sign-out"></i></button></div>
    </aside>
    <main class="owner-main">
      <header class="owner-header"><div><p class="owner-eyebrow">DIGITAL LAB · OWNER</p><h1>{{ activeNav.label }}</h1><p>{{ activeNav.subtitle }}</p></div><div class="owner-header-actions"><button class="owner-icon-button" @click="bootstrap" :disabled="loading" aria-label="تحديث البيانات"><i class="pi pi-refresh" :class="{ 'pi-spin':loading }"></i></button><span class="owner-avatar">{{ me.owner.name?.slice(0,1) || 'م' }}</span></div></header>
      <section class="owner-controls" aria-label="نطاق التقرير">
        <label class="owner-lab-select"><i class="pi pi-building"></i><select v-model="labId" aria-label="المختبر"><option value="">كل مختبراتي</option><option v-for="lab in me.labs" :key="lab.id" :value="lab.id">{{ lab.name }}</option></select></label>
        <div v-if="!['team','subscriptions'].includes(section)" class="owner-presets"><button @click="preset(1)" :class="{ active:range.from === today && range.to === today }">اليوم</button><button @click="preset(7)">٧ أيام</button><button @click="preset(30)">٣٠ يوماً</button></div>
        <details v-if="!['team','subscriptions'].includes(section)" class="owner-date-menu"><summary><i class="pi pi-calendar"></i> تحديد الفترة</summary><form @submit.prevent="applyRange"><label>من<input v-model="draftRange.from" type="date" :max="today" required></label><label>إلى<input v-model="draftRange.to" type="date" :min="draftRange.from" :max="today" required></label><button class="owner-primary">تطبيق</button></form></details>
      </section>
      <div v-if="error" class="owner-error" role="alert"><i class="pi pi-exclamation-circle"></i>{{ error }}<button @click="bootstrap">إعادة المحاولة</button></div>
      <div v-if="!me.labs.length && !loading && !error" class="owner-empty"><i class="pi pi-building"></i><h2>مختبراتك تظهر هنا</h2><p>يرجى من الأدمن الرئيسي ربط المختبرات بحسابك لبدء المتابعة.</p></div>
      <div v-else-if="loading" class="owner-skeleton" role="status" aria-label="جاري تحميل البيانات"><div></div><div></div><div></div><div></div></div>
      <template v-else-if="!error">
        <template v-if="section === 'overview' && report">
          <section class="owner-hero"><div><span class="owner-live"><span></span> نظرة على {{ labName }}</span><h2>أهلاً، {{ me.owner.name }}<br><span>أعمالك أمامك، بوضوح.</span></h2><p>{{ range.from }} — {{ range.to }} · توقيت بغداد</p></div><div class="owner-hero-total"><span>صافي قيمة الفواتير</span><strong>{{ money(report.summary.total) }}</strong><small>دينار عراقي</small><span class="owner-comparison" :class="report.comparison.percent < 0 ? 'down' : 'up'">{{ report.comparison.percent === null ? 'لا توجد قاعدة مقارنة' : `${report.comparison.percent > 0 ? '+' : ''}${report.comparison.percent}% زيارات` }}</span></div></section>
          <div v-if="expired.length" class="owner-alert"><i class="pi pi-calendar"></i><span>راجع اشتراكات {{ number(expired.length) }} من مختبراتك.</span><button @click="go('subscriptions')">عرض التفاصيل ←</button></div>
          <div class="owner-kpis">
            <button @click="openInvoices()" class="owner-kpi"><span class="kpi-icon teal"><i class="pi pi-file"></i></span><span>الفواتير / الزيارات</span><strong>{{ number(report.summary.invoice_count) }}</strong><small>عرض جميع الفواتير ←</small></button>
            <div class="owner-kpi"><span class="kpi-icon blue"><i class="pi pi-users"></i></span><span>المرضى</span><strong>{{ number(report.summary.patient_count) }}</strong><small>مرضى مختلفون خلال الفترة</small></div>
            <button @click="go('analyses')" class="owner-kpi"><span class="kpi-icon violet"><i class="pi pi-chart-bar"></i></span><span>التحاليل المصروفة</span><strong>{{ number(report.summary.analysis_count) }}</strong><small>تشمل مكونات الكروبات والباقات</small></button>
            <button @click="openInvoices({ status:'unpaid' })" class="owner-kpi"><span class="kpi-icon amber"><i class="pi pi-wallet"></i></span><span>المبالغ المتبقية</span><strong>{{ money(report.summary.balance) }} <em>د.ع</em></strong><small>على فواتير الفترة المحددة</small></button>
          </div>
          <div class="owner-two-columns">
            <section class="owner-card"><div class="owner-card-heading"><div><h2>حركة الزيارات</h2><p>{{ report.meta.trend_interval === 'month' ? 'عدد الفواتير لكل شهر' : 'عدد الفواتير لكل يوم' }}</p></div><span class="owner-badge neutral">{{ number(report.summary.invoice_count) }} زيارة</span></div><div class="owner-chart" role="img" :aria-label="`حركة الزيارات: ${report.summary.invoice_count} زيارة`"><div v-for="day in report.trend" :key="day.period" class="owner-chart-day" :title="`${day.period}: ${day.count} زيارة`"><span>{{ number(day.count) }}</span><div class="owner-bar-track"><div :style="{ height:(day.count / maxTrend * 100) + '%' }"></div></div><small>{{ day.period.slice(5) }}</small></div></div></section>
            <section class="owner-card"><div class="owner-card-heading"><h2>حالة النتائج</h2><button class="owner-text-button" @click="go('results')">التفاصيل ←</button></div><div class="owner-completion"><strong>{{ number(report.results.completed) }}<small>تقارير مكتملة</small></strong><span class="owner-round-icon"><i class="pi pi-check"></i></span></div><div class="owner-progress"><div :style="{ width:(report.summary.invoice_count ? report.results.completed / report.summary.invoice_count * 100 : 0) + '%' }"></div></div><button class="owner-pending" @click="router.push({ path:'/owner-portal/results', query:{ result_status:'pending' } })"><span><i class="pi pi-clock"></i> تقارير غير مكتملة</span><b>{{ number(report.results.pending) }}</b></button><div class="owner-mini-finance"><div><small>المدفوع على فواتير الفترة</small><b>{{ money(report.summary.paid) }} د.ع</b></div><div><small>المحصّل خلال الفترة</small><b>{{ money(report.summary.collections_in_period) }} د.ع</b></div></div></section>
          </div>
          <section class="owner-card owner-insights"><div class="owner-card-heading"><div><span class="owner-eyebrow">متابعة الأداء</span><h2>{{ report.comparison.percent === null ? 'نبني صورة الأداء معك' : report.comparison.percent < 0 ? 'الزيارات أقل من الفترة السابقة' : 'تابع اتجاه نمو مختبرك' }}</h2></div><i class="pi pi-chart-line"></i></div><p>{{ number(report.comparison.current) }} زيارة مقابل {{ number(report.comparison.previous) }} في {{ report.comparison.from }} — {{ report.comparison.to }}.</p><p class="owner-muted">{{ report.comparison.note }}</p><div class="owner-source-drops" v-if="report.comparison.sources_decreased.length"><span v-for="source in report.comparison.sources_decreased" :key="source.name">{{ source.name }} <b>−{{ number(source.decrease) }} زيارة</b></span></div><ul><li v-for="tip in report.comparison.suggestions" :key="tip"><i class="pi pi-arrow-up-left"></i>{{ tip }}</li></ul></section>
        </template>

        <template v-if="['invoices','results'].includes(section)">
          <form class="owner-filter-bar" @submit.prevent="search"><input v-model="filters.search" placeholder="اسم المريض أو رقم الفاتورة أو الباركود" aria-label="البحث عن فاتورة"><select v-if="section === 'invoices'" v-model="filters.status" aria-label="حالة الدفع"><option value="">كل حالات الدفع</option><option v-for="(label, key) in states" :key="key" :value="key">{{ label }}</option></select><select v-else v-model="filters.result_status" aria-label="حالة النتائج"><option value="">كل النتائج</option><option value="done">مكتملة</option><option value="pending">غير مكتملة</option></select><button class="owner-primary">بحث</button></form>
          <div class="owner-list-heading"><h2>{{ section === 'results' ? 'تقارير المرضى' : 'سجل الفواتير' }}</h2><span>{{ number(pagination.total) }} فاتورة</span></div>
          <div v-if="!rows.length" class="owner-empty"><i class="pi pi-inbox"></i><p>لا توجد فواتير ضمن الاختيار الحالي.</p></div>
          <div class="owner-invoice-list"><button v-for="row in rows" :key="row.id" class="owner-invoice-row" @click="openDetail(row.id)"><span class="invoice-avatar"><i :class="['pi', section === 'results' ? 'pi-check-square' : 'pi-file']"></i></span><div class="owner-row-main"><strong>{{ row.patient_name }}</strong><small>#{{ row.id }} · {{ row.lab_name }}</small><small>{{ date(row.date) }}</small></div><div class="owner-row-amount"><strong>{{ money(row.total) }} <small>د.ع</small></strong><span v-if="section === 'invoices'" :class="['owner-badge', row.payment_status]">{{ states[row.payment_status] }}</span><span v-else :class="['owner-badge', row.is_done ? 'paid' : 'partial']">{{ row.is_done ? 'مكتملة' : 'غير مكتملة' }}</span><small v-if="section === 'invoices'">المتبقي {{ money(row.balance) }} د.ع</small></div><i class="pi pi-angle-left row-arrow"></i></button></div>
        </template>

        <template v-if="section === 'analyses' && report">
          <div class="owner-section-intro"><h2>{{ number(report.summary.analysis_count) }} تحليل خلال الفترة</h2><p>عدد مرات صرف الفحص، ويشمل مكونات الكروبات والباقات.</p></div>
          <input v-model="analysisSearch" class="owner-search" placeholder="ابحث باسم التحليل أو المختصر" aria-label="البحث عن تحليل">
          <p v-if="report.summary.estimated_membership_invoices" class="owner-alert">توجد {{ number(report.summary.estimated_membership_invoices) }} فاتورة قديمة استُكملت مكوناتها من تعريف الكروب أو الباقة الحالي.</p>
          <div class="owner-analysis-grid"><article v-for="item in analysisRows" :key="item.kind + item.id" class="owner-card owner-analysis"><div><span class="owner-badge neutral">{{ kind[item.kind] }}</span><h3>{{ item.name }}</h3><small>{{ item.shortcut || '—' }}</small></div><strong>{{ number(item.count) }}<small>مرة</small></strong><div class="owner-analysis-sources"><span>مباشر <b>{{ number(item.direct) }}</b></span><span>كروبات <b>{{ number(item.in_groups) }}</b></span><span>باقات <b>{{ number(item.in_packages) }}</b></span></div><button v-if="item.kind === 'test' && item.id" class="owner-text-button" @click="openInvoices({ test_id:item.id })">فواتير هذا التحليل ←</button></article></div>
          <div v-if="!analysisRows.length" class="owner-empty">لا توجد تحاليل مطابقة.</div>
          <section class="owner-card owner-table-card"><h2>البنود المصروفة وأسعارها</h2><div class="owner-table-scroll"><table><thead><tr><th>البند</th><th>النوع</th><th>العدد</th><th>قبل الخصم</th><th>الصافي الموزع</th></tr></thead><tbody><tr v-for="item in report.items" :key="item.kind + item.id"><td>{{ item.name }}</td><td>{{ kind[item.kind] }}</td><td>{{ number(item.count) }}</td><td>{{ money(item.price) }}</td><td>{{ money(item.net_allocated) }}</td></tr></tbody></table></div><p class="owner-muted">المبالغ بالدينار العراقي. الصافي يوزّع خصم الفاتورة على البنود.</p></section>
        </template>

        <template v-if="section === 'referrals' && report">
          <div class="owner-segment"><button :class="{ active:referralType === 'doctor_referrals' }" @click="referralType = 'doctor_referrals'">إحالات الأطباء</button><button :class="{ active:referralType === 'lab_referrals' }" @click="referralType = 'lab_referrals'">إحالات المختبرات</button></div>
          <div class="owner-analysis-grid"><article class="owner-card owner-referral" v-for="item in report[referralType]" :key="`${item.lab_id}:${item.id}`"><div class="owner-referral-head"><span class="invoice-avatar"><i :class="['pi', referralType === 'doctor_referrals' ? 'pi-user' : 'pi-building']"></i></span><div><h3>{{ item.name }}</h3><small>{{ item.lab_name }}</small></div><span class="owner-badge neutral">{{ number(item.invoice_count) }} فاتورة</span></div><strong>{{ money(item.total) }} <small>د.ع</small></strong><p>قيمة الفواتير المحالة</p><dl class="owner-finance-lines"><div><dt>المدفوع على الفواتير</dt><dd>{{ money(item.paid) }}</dd></div><div><dt>المتبقي</dt><dd>{{ money(item.balance) }}</dd></div><div><dt>العمولة التقديرية</dt><dd>{{ money(item.commission_estimate) }}</dd></div></dl><button class="owner-text-button" @click="openInvoices(referralType === 'doctor_referrals' ? { doctor_id:item.id } : { lab_referral_id:item.id })">عرض الفواتير ←</button></article></div><div v-if="!report[referralType]?.length" class="owner-empty">لا توجد إحالات خلال الفترة.</div><p class="owner-muted">العمولة تقديرية حسب النسبة المسجلة، ولا تمثل دفعة مسددة للطبيب أو المختبر المحيل.</p>
        </template>

        <template v-if="['activity','changes'].includes(section)">
          <div v-if="section === 'changes'" class="owner-audit-kpis"><div><small>فواتير محذوفة</small><b>{{ number(summary.deleted_count) }}</b><span>{{ money(summary.deleted_total) }} د.ع</span></div><div><small>زيادات التعديل</small><b class="text-teal">+{{ money(summary.increases) }}</b><span>دينار عراقي</span></div><div><small>تخفيضات التعديل</small><b class="text-rose">−{{ money(summary.decreases) }}</b><span>دينار عراقي</span></div><div><small>الأثر الصافي مع الحذف</small><b>{{ money(summary.net_change) }}</b><span>فرق الدفعات: {{ money(summary.payments_change) }}</span></div></div>
          <div v-else class="owner-list-heading"><h2>{{ number(summary.total) }} عملية مسجلة</h2><span>{{ number(summary.employees) }} مستخدم · {{ number(summary.failed) }} محاولة فاشلة</span></div>
          <form class="owner-filter-bar" @submit.prevent="search"><input v-model="filters.search" placeholder="اسم الموظف أو المريض أو تفاصيل العملية" aria-label="بحث السجل"><input v-model="filters.invoice_id" type="number" min="1" placeholder="رقم الفاتورة" aria-label="رقم الفاتورة"><select v-if="section === 'changes'" v-model="filters.change_type" aria-label="نوع التغيير"><option value="">كل التغييرات</option><option value="deleted">الفواتير المحذوفة</option><option value="financial">التغييرات المالية</option><option value="results">تغييرات النتائج والتحاليل</option></select><select v-else v-model="filters.event" aria-label="نوع الحركة"><option value="">كل الحركات</option><option v-for="(label,key) in events" :key="key" :value="key">{{ label }}</option></select><button class="owner-primary">بحث</button></form>
          <p v-if="section === 'changes'" class="owner-audit-note"><i class="pi pi-info-circle"></i>{{ notice }}<span v-if="Number(summary.unknown_amounts)"> {{ number(summary.unknown_amounts) }} سجل لا تتوفر له قيمة مالية كاملة.</span></p>
          <div v-if="!rows.length" class="owner-empty"><i class="pi pi-shield"></i><p>لا توجد سجلات مطابقة للفترة والفلاتر.</p></div>
          <div class="owner-timeline"><button v-for="row in rows" :key="row.id" class="owner-timeline-item" @click="openDetail(row.id, section)"><span :class="['timeline-icon', row.event === 'deleted' || row.event === 'failed' ? 'danger' : '']"><i :class="['pi', row.event === 'deleted' ? 'pi-trash' : row.event === 'updated' ? 'pi-pencil' : 'pi-history']"></i></span><div class="owner-row-main"><div class="owner-timeline-title"><strong>{{ section === 'changes' ? row.actor_name || 'غير مسجل' : row.actor }}</strong><span :class="['owner-badge', row.event === 'deleted' || row.event === 'failed' ? 'unpaid' : 'neutral']">{{ events[row.event] || 'عملية' }}</span></div><p>{{ section === 'changes' ? `فاتورة #${row.invoice_id} · ${row.patient_name || 'اسم غير مسجل'}` : row.description }}</p><small>{{ date(row.occurred_at || row.date) }}</small><span v-if="section === 'changes'" class="owner-change-amount">{{ money(row.before_total) }} ← {{ money(row.after_total) }} د.ع</span></div><i class="pi pi-angle-left"></i></button></div>
        </template>

        <template v-if="section === 'team'">
          <div v-if="!labId" class="owner-empty"><i class="pi pi-building"></i><h2>اختر المختبر أولاً</h2><p>حدد مختبراً من القائمة أعلاه لإنشاء حسابات موظفيه وإدارتها.</p></div>
          <template v-else><div class="owner-list-heading"><div><h2>فريق {{ labName }}</h2><p>{{ number(rows.length) }} حساب موظف</p></div><button class="owner-primary" @click="editStaff()"><i class="pi pi-plus"></i> إضافة موظف</button></div><div class="owner-analysis-grid"><article class="owner-card owner-team-card" v-for="row in rows" :key="row.id"><span class="owner-avatar">{{ row.name.slice(0,1) }}</span><h3>{{ row.name }}</h3><p>{{ row.role?.name }}</p><small dir="ltr">{{ row.email }}</small><span :class="['owner-badge', row.status ? 'paid' : 'unpaid']">{{ row.status ? 'فعال' : 'معطل' }}</span><button class="owner-secondary" @click="editStaff(row)">إدارة الحساب</button></article></div><div v-if="!rows.length" class="owner-empty">لا توجد حسابات موظفين لهذا المختبر بعد.</div></template>
        </template>

        <template v-if="section === 'subscriptions'">
          <div class="owner-section-intro"><h2>مختبراتك واشتراكات النظام</h2><p>ربط مختبر إضافي أو تجديد الاشتراك يتم من خلال الأدمن الرئيسي.</p></div><div class="owner-analysis-grid"><article v-for="lab in visibleLabs" :key="lab.id" class="owner-card owner-subscription"><div class="owner-referral-head"><span class="invoice-avatar"><i class="pi pi-building"></i></span><h3>{{ lab.name }}</h3></div><span :class="['owner-badge', lab.subscription && ['active','trial'].includes(lab.subscription.status) ? 'paid' : 'unpaid']">{{ !lab.subscription ? 'لا يوجد اشتراك' : ({ active:'فعال', trial:'تجريبي', expired:'منتهي', suspended:'موقوف', cancelled:'ملغي' }[lab.subscription.status] || lab.subscription.status) }}</span><h2>{{ lab.subscription?.plan || '—' }}</h2><dl class="owner-finance-lines"><div><dt>تاريخ الانتهاء</dt><dd>{{ lab.subscription?.end_date || '—' }}</dd></div><div><dt>الأيام المتبقية</dt><dd>{{ lab.subscription ? number(Math.max(0, lab.subscription.days_left || 0)) : '—' }}</dd></div><div><dt>حساب المختبر</dt><dd>{{ lab.active ? 'فعال' : 'معطل' }}</dd></div></dl><button class="owner-secondary" @click="labId = lab.id; go('overview')">عرض المختبر ←</button></article></div>
        </template>

        <div v-if="['invoices','results','activity','changes'].includes(section) && pagination.last_page > 1" class="owner-pagination"><button :disabled="page <= 1" @click="paginate(-1)">السابق</button><span>{{ number(page) }} / {{ number(pagination.last_page) }}</span><button :disabled="page >= pagination.last_page" @click="paginate(1)">التالي</button></div>
        <footer class="owner-content-footer"><span>Digital Lab · بوابة المالك</span><span>جميع المبالغ بالدينار العراقي</span></footer>
      </template>
    </main>
    <nav class="owner-bottom-nav" aria-label="التنقل السريع"><button v-for="item in [nav[0],nav[1],nav[3],nav[6]]" :key="item.key" :class="{ active:section === item.key }" @click="go(item.key)"><i :class="['pi', item.icon]"></i><span>{{ item.key === 'changes' ? 'الرقابة' : item.label }}</span></button><button @click="moreOpen = !moreOpen" :class="{ active:moreOpen }" aria-label="المزيد من الأقسام"><i class="pi pi-th-large"></i><span>المزيد</span></button></nav>
    <div v-if="moreOpen" class="owner-more"><button v-for="item in nav" :key="item.key" @click="go(item.key)"><i :class="['pi', item.icon]"></i>{{ item.label }}</button><button @click="logout"><i class="pi pi-sign-out"></i>تسجيل الخروج</button></div>

    <UiModal v-model="modal" size="xl"><template #header><h2>{{ detailKind === 'invoices' ? 'تفاصيل الفاتورة والنتائج' : 'تفاصيل العملية — قبل وبعد' }}</h2></template><div class="owner-modal-content" dir="rtl"><p v-if="detailLoading" role="status">جاري التحميل...</p><p v-else-if="detail?.error" role="alert">{{ detail.error }}</p><template v-else-if="detail">
      <template v-if="detailKind === 'invoices'"><h2>{{ detail.patient_name }}</h2><p>#{{ detail.id }} · {{ detail.lab_name }} · {{ date(detail.date) }}</p><div class="owner-detail-finance"><div>الإجمالي<b>{{ money(detail.total) }}</b></div><div>المدفوع<b>{{ money(detail.paid) }}</b></div><div>المتبقي<b>{{ money(detail.balance) }}</b></div></div><h3>البنود المصروفة</h3><div v-for="item in detail.items" :key="item.id" class="owner-detail-item"><strong>{{ item.name }} <small>{{ kind[item.kind] }}</small></strong><span>{{ money(item.net_allocated) }} د.ع</span><p>{{ item.analyses?.map(a => a.name).join('، ') }}</p></div><h3>الدفعات المسجلة</h3><p v-if="!detail.payments.length">لا توجد دفعات.</p><div v-for="payment in detail.payments" :key="payment.id" class="owner-detail-item"><strong>{{ money(payment.amount) }} د.ع</strong><span>{{ payment.method }} · {{ date(payment.date) }}</span></div><h3>النتائج الحالية</h3><details v-for="(result,key) in detail.results" :key="key" class="owner-result"><summary>{{ result.name || key }} <span :class="['owner-badge', result.is_done ? 'paid' : 'partial']">{{ result.is_done ? 'مكتمل' : 'غير مكتمل' }}</span></summary><OwnerValue :value="Object.fromEntries(Object.entries(result).filter(([k]) => ['result','sub_tests','unit','normal_range','comment','result_status_text','test_group_tests','test_group_cultures','package_tests','package_cultures','attribute'].includes(k)))" /></details></template>
      <template v-else><h3>{{ detail.actor_name || detail.actor || 'غير مسجل' }}</h3><p>{{ date(detail.occurred_at || detail.date) }} · {{ events[detail.event] }}</p><p v-if="detail.invoice_id">فاتورة #{{ detail.invoice_id }} · {{ detail.patient_name }}</p><p v-if="detailKind === 'changes'" class="owner-audit-note">فرق قيمة الفاتورة: {{ money(detail.total_delta) }} د.ع · فرق الدفعات: {{ money(detail.paid_delta) }} د.ع</p><p v-if="!changes.length">{{ detail.description }} — لا توجد تغييرات حقول مسجلة لهذه العملية.</p><div class="owner-diff" v-for="(change,i) in changes" :key="i"><strong>{{ change.label || change.field }}</strong><div><section><small>قبل</small><OwnerValue :value="change.before" /></section><section><small>بعد</small><OwnerValue :value="change.after" /></section></div></div></template>
    </template></div></UiModal>
    <UiModal v-model="teamModal" size="lg"><template #header><h2>{{ staffForm.id ? 'إدارة حساب الموظف' : 'موظف جديد' }}</h2></template><form class="owner-form" @submit.prevent="saveStaff" dir="rtl"><p>المختبر: <strong>{{ labName }}</strong></p><div v-if="formError" class="owner-error" role="alert">{{ formError }}</div><label>الاسم الكامل<input v-model="staffForm.name" required maxlength="150"></label><label>البريد الإلكتروني<input v-model="staffForm.email" type="email" required dir="ltr" autocomplete="off"></label><label>{{ staffForm.id ? 'كلمة مرور جديدة (اتركها فارغة للإبقاء عليها)' : 'كلمة المرور' }}<input v-model="staffForm.password" type="password" :required="!staffForm.id" minlength="10" maxlength="128" autocomplete="new-password"></label><label>الدور والصلاحيات<select v-model="staffForm.role_id" required><option value="">اختر الدور</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></label><details v-if="staffForm.role_id"><summary>عرض صلاحيات الدور ({{ number(rolePermissions.length) }})</summary><ul><li v-for="permission in rolePermissions" :key="permission">{{ permission }}</li></ul></details><p v-if="!roles.length" class="owner-muted">يجب إنشاء دور موظف وصلاحياته داخل المختبر أولاً.</p><label>حالة الحساب<select v-model.number="staffForm.status"><option :value="1">فعال</option><option :value="0">معطل</option></select></label><button class="owner-primary" :disabled="saving || !roles.length">{{ saving ? 'جاري الحفظ...' : 'حفظ الحساب' }}</button></form></UiModal>
  </div>
</template>

<style src="./owner.css"></style>
