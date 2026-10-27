<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { $http } from '@/plugins/axios';
import UiModal from '@/components/ui/Modal.vue';
const props = defineProps({ labId:{ default:'' } });
const emit = defineEmits(['read', 'archive']);
const route = useRoute();
const rows = ref([]), page = ref(1), lastPage = ref(1), kind = ref(''), loading = ref(false), error = ref(''), detail = ref(null), detailError = ref(''), detailLoading = ref(false), modal = ref(false);
let revision = 0, detailRevision = 0;
const labels = { patient_count:'عدد المرضى', invoice_count:'الفواتير / الزيارات', total:'إجمالي قيمة الفواتير', paid:'المدفوع على فواتير اليوم', balance:'المتبقي على فواتير اليوم', collections_in_period:'التحصيل خلال اليوم', analysis_count:'التحاليل المصروفة', completed:'التقارير المكتملة', pending:'التقارير غير المكتملة', referral_count:'إجمالي الإحالات', doctor_referrals:'إحالات الأطباء', lab_referrals:'إحالات المختبرات', deleted_count:'الفواتير المحذوفة', deleted_total:'قيمة المحذوفات', increases:'زيادات تعديل المبالغ', decreases:'تخفيضات تعديل المبالغ' };
const monetary = ['total','paid','balance','collections_in_period','deleted_total','increases','decreases'];
const amount = value => value === null || value === undefined ? 'غير مسجل' : new Intl.NumberFormat('ar-IQ', { maximumFractionDigits:2 }).format(value);
const date = value => new Intl.DateTimeFormat('ar-IQ', { timeZone:'Asia/Baghdad', dateStyle:'medium', timeStyle:'short' }).format(new Date(value));
const metrics = computed(() => Object.entries(labels).filter(([key]) => detail.value?.data?.summary?.[key] !== undefined));
const errorText = e => e.response?.data?.message || 'تعذر تحميل الإشعارات. أعد المحاولة.';
async function load() {
  const version = ++revision; loading.value = true; error.value = ''; rows.value = [];
  try {
    const { data } = await $http.get('/owner-portal/notifications', { params:{ lab_id:props.labId || undefined, kind:kind.value || undefined, page:page.value } });
    if (version !== revision) return;
    rows.value = data.data; lastPage.value = data.pagination.last_page;
  } catch(e) { if (version === revision) error.value = errorText(e); } finally { if (version === revision) loading.value = false; }
}
async function open(id) {
  const version = ++detailRevision; detail.value = null; detailError.value = ''; modal.value = true; detailLoading.value = true;
  try {
    const { data } = await $http.get('/owner-portal/notifications/'+encodeURIComponent(id));
    if (version !== detailRevision) return;
    detail.value = data;
    await $http.post(`/owner-portal/notifications/${encodeURIComponent(id)}/read`);
    if (version === detailRevision) { const row = rows.value.find(r => r.id === id); if (row) row.read_at = new Date().toISOString(); emit('read'); }
  } catch(e) { if (version === detailRevision) detailError.value = errorText(e); } finally { if (version === detailRevision) detailLoading.value = false; }
}
function changePage(offset) { page.value += offset; load(); }
watch([() => props.labId, kind], () => { page.value = 1; load(); }, { immediate:true });
watch(() => route.query.notice, id => { if (typeof id === 'string') open(id); }, { immediate:true });
watch(modal, value => { if (!value) detailRevision++; });
onBeforeUnmount(() => { revision++; detailRevision++; });
</script>
<template>
  <section>
    <div class="owner-list-heading"><div><h2>كل ما يحتاج متابعتك</h2><p class="owner-muted">إشعارات العمليات وملخصات الأيام محفوظة هنا.</p></div><button class="owner-icon-button" @click="load" aria-label="تحديث الإشعارات"><i class="pi pi-refresh" aria-hidden="true"></i></button></div>
    <select v-model="kind" class="owner-notice-filter" aria-label="نوع الإشعار"><option value="">كل الإشعارات</option><option value="invoice_deleted">حذف الفواتير</option><option value="invoice_amount">تعديل المبالغ</option><option value="daily_summary">ملخصات الأيام</option></select>
    <div v-if="error" class="owner-error" role="alert">{{ error }}<button @click="load">إعادة المحاولة</button></div>
    <p v-else-if="loading" class="owner-empty" role="status">جاري تحميل الإشعارات...</p>
    <div v-else-if="!rows.length" class="owner-empty"><i class="pi pi-bell" aria-hidden="true"></i><h3>لا توجد إشعارات بعد</h3><p>ستظهر هنا عمليات حذف الفواتير وتعديل مبالغها وملخصات أيام العمل.</p></div>
    <div v-else class="owner-notice-list"><button v-for="row in rows" :key="row.id" :class="['owner-notice-row', { unread:!row.read_at }]" @click="open(row.id)"><span :class="['timeline-icon', { danger:row.kind === 'invoice_deleted' }]"><i :class="['pi', row.kind === 'daily_summary' ? 'pi-chart-bar' : row.kind === 'invoice_deleted' ? 'pi-trash' : 'pi-bell']" aria-hidden="true"></i></span><div><div class="owner-notice-title"><strong>{{ row.title }}</strong><span v-if="!row.read_at" class="owner-unread-dot" aria-label="غير مقروء"></span></div><p>{{ row.body }}</p><small>{{ date(row.created_at) }}</small></div><i class="pi pi-angle-left" aria-hidden="true"></i></button></div>
    <div v-if="lastPage > 1" class="owner-pagination"><button :disabled="page <= 1" @click="changePage(-1)">السابق</button><span>{{ page }} / {{ lastPage }}</span><button :disabled="page >= lastPage" @click="changePage(1)">التالي</button></div>
    <UiModal v-model="modal" size="lg"><template #header><h2>{{ detail?.title || 'تفاصيل الإشعار' }}</h2></template><div dir="rtl" class="owner-modal-content">
      <p v-if="detailLoading" role="status">جاري التحميل...</p><p v-if="detailError" class="owner-error" role="alert">{{ detailError }}</p>
      <template v-if="detail"><p>{{ detail.data.lab_name }} · {{ detail.data.day || date(detail.created_at) }}</p>
        <template v-if="detail.kind === 'daily_summary'"><div class="owner-digest-grid"><div v-for="[key,label] in metrics" :key="key"><span>{{ label }}</span><strong>{{ amount(detail.data.summary[key]) }} <small v-if="monetary.includes(key)">د.ع</small></strong></div></div><p class="owner-muted">{{ detail.data.note }}</p><small class="owner-muted">أُعدّ في {{ date(detail.data.generated_at) }} · توقيت بغداد</small></template>
        <template v-else><p>{{ detail.body }}</p><dl v-if="detail.data.invoice_id" class="owner-finance-lines"><div><dt>رقم الفاتورة</dt><dd>#{{ detail.data.invoice_id }}</dd></div><div><dt>نفّذ العملية</dt><dd>{{ detail.data.actor_name || 'غير مسجل' }}</dd></div><div><dt>المبلغ قبل العملية</dt><dd>{{ amount(detail.data.before_total) }} د.ع</dd></div><div><dt>المبلغ بعد العملية</dt><dd>{{ amount(detail.data.after_total) }} د.ع</dd></div></dl><button v-if="detail.data.archive_id" class="owner-primary" @click="emit('archive', detail.data.archive_id); modal = false">عرض سجل العملية الكامل</button></template>
      </template>
    </div></UiModal>
  </section>
</template>
