<script setup>
import { computed } from 'vue';
import OwnerValue from './OwnerValue.vue';
import { ownerAuditGroups, ownerAuditSummary, ownerAuditCount, ownerReadableValue } from '@/utils/ownerAuditPresentation';

const props=defineProps({
  record:{type:Object,required:true},
  kind:{type:String,default:'changes'},
});
const groups=computed(()=>ownerAuditGroups(props.record));
const count=computed(()=>ownerAuditCount(props.record));
const description=computed(()=>ownerAuditSummary(props.record,true));
const actor=computed(()=>props.record.actor_name||props.record.actor||'اسم الموظف غير مسجل');
const invoiceId=computed(()=>props.record.invoice_id||props.record.invoice?.id);
const patient=computed(()=>props.record.patient_name||props.record.invoice?.patient_name);
const isDenied=computed(()=>props.record.event==='failed'||props.record.status==='failed');
const time=computed(()=>{
  const v=props.record.occurred_at||props.record.date;
  if(!v)return 'التاريخ غير مسجل';
  const iso=String(v).includes('T')?String(v):String(v).replace(' ','T')+'Z';
  const date=new Date(iso);
  return Number.isNaN(date.getTime())?'التاريخ غير مسجل':
    new Intl.DateTimeFormat('ar-IQ',{timeZone:'Asia/Baghdad',dateStyle:'medium',timeStyle:'short',hour12:true}).format(date);
});
const renderMore = value => value!==null && value!==undefined && typeof value==='object' ||
  typeof value==='string' && /^[\[{]/.test(value.trim());
const hasFinancial = computed(()=>props.kind==='changes'&&(props.record.before_total!==null||props.record.after_total!==null));
function money(v){return v===null||v===undefined?'غير متوفر':new Intl.NumberFormat('ar-IQ',{maximumFractionDigits:2}).format(Number(v))+' د.ع'}
const groupClass = key => ({results:'owner-audit-results',financial:'owner-audit-financial',invoice:'owner-audit-invoice',attachments:'owner-audit-attachments',other:'owner-audit-other'}[key]||'');
</script>
<template>
  <section class="owner-readable-audit" aria-label="شرح سجل التغيير">
    <div class="owner-audit-intro">
      <p class="owner-audit-eyebrow"><i class="pi pi-verified" aria-hidden="true"></i> سجل تدقيق محفوظ من النظام</p>
      <h3 class="owner-audit-headline">{{ description }}</h3>
      <div class="owner-audit-meta">
        <span><i class="pi pi-user" aria-hidden="true"></i> {{ actor }}</span>
        <span><i class="pi pi-clock" aria-hidden="true"></i> {{ time }} (بغداد)</span>
        <span v-if="invoiceId"><i class="pi pi-file" aria-hidden="true"></i> فاتورة #{{ invoiceId }}</span>
        <span v-if="patient"><i class="pi pi-id-card" aria-hidden="true"></i> {{ patient }}</span>
        <span v-if="record.barcode || record.invoice?.barcode" dir="ltr">{{ record.barcode||record.invoice.barcode }}</span>
      </div>
      <p v-if="isDenied" class="owner-audit-warning">الطلب لم يكتمل. لا يدل هذا السجل على تغيير ناجح في بيانات الفاتورة.</p>
      <p v-else-if="record.event==='clicked'" class="owner-audit-warning">هذا سجل ضغط زر فقط، وليس إثباتاً لحفظ تغييرات.</p>
      <p v-else class="owner-audit-intro-note">التفاصيل أدناه من القيم المحفوظة قبل العملية وبعدها، وليست تقديراً أو وصفاً عاماً.</p>
    </div>

    <div v-if="hasFinancial" class="owner-audit-finance-comparison" aria-label="ملخص الأثر المالي">
      <div><small>الإجمالي قبل</small><strong>{{ money(record.before_total) }}</strong></div>
      <i class="pi pi-arrow-left" aria-hidden="true"></i>
      <div><small>الإجمالي بعد</small><strong>{{ money(record.after_total) }}</strong></div>
      <div class="owner-audit-finance-delta"><small>الفرق على الإجمالي</small><strong>{{ money(record.total_delta) }}</strong></div>
      <p class="owner-audit-finance-note">الفرق المالي ناتج عن سجل التعديل، ولا يعني بالضرورة تحصيلاً نقدياً. فرق الدفعات: {{ money(record.paid_delta) }}</p>
    </div>

    <div v-if="groups.length" class="owner-audit-group-list">
      <div class="owner-audit-section-count"><strong>تفاصيل التغيير</strong><span>{{ count }} حقل تغيّر، موزّعة حسب نوع العملية</span></div>
      <details v-for="(group,index) in groups" :key="group.key" class="owner-audit-group" :class="groupClass(group.key)" :open="index===0 || undefined">
        <summary>
          <span class="owner-audit-group-icon"><i :class="['pi',group.icon]" aria-hidden="true"></i></span>
          <span class="owner-audit-group-label"><strong>{{ group.title }}</strong><small>{{ group.description }}</small></span>
          <span class="owner-audit-count">{{ group.changes.length }}</span>
          <i class="pi pi-chevron-down owner-audit-chevron" aria-hidden="true"></i>
        </summary>
        <div class="owner-audit-group-body">
          <article v-for="(change,index) in group.changes" :key="change.field+'-'+index" class="owner-diff owner-audit-field">
            <div class="owner-audit-field-heading">
              <strong>{{ change.label }}</strong>
              <span class="owner-audit-change-type">{{ change.before===null||change.before===undefined?'إضافة':change.after===null||change.after===undefined?'إزالة':'تعديل' }}</span>
            </div>
            <p class="owner-audit-sentence">{{ change.narrative }}</p>
            <div class="owner-audit-values">
              <section><small>القيمة السابقة</small><p>{{ change.beforeText }}</p></section>
              <section><small>القيمة الجديدة</small><p>{{ change.afterText }}</p></section>
            </div>
            <details v-if="renderMore(change.before)||renderMore(change.after)" class="owner-audit-extra">
              <summary>عرض العناصر التفصيلية المسجّلة</summary>
              <div class="owner-audit-extended">
                <section><small>قبل</small><OwnerValue :value="change.before" /></section>
                <section><small>بعد</small><OwnerValue :value="change.after" /></section>
              </div>
            </details>
          </article>
        </div>
      </details>
    </div>
    <div v-else class="owner-audit-limited">
      <i class="pi pi-info-circle" aria-hidden="true"></i>
      <div><strong>لا توجد مقارنة حقول متاحة لهذا السجل</strong>
        <p>{{ record.event==='failed' ? 'المحاولة لم تكتمل.' : record.event==='clicked' ? 'الزر تم الضغط عليه فقط، وليس هناك تغيير محفوظ مثبت.' : 'قد يكون سجلاً قديماً أو عملية عرض/طباعة؛ لا يمكن إعادة إنشاء فروقات لم تُحفظ وقت العملية.' }}</p>
      </div>
    </div>
    <p class="owner-audit-footnote"><i class="pi pi-lock" aria-hidden="true"></i> البيانات الحساسة مثل كلمات المرور ورموز الدخول محجوبة عن سجل التدقيق. لا يُسمح بتعديل السجل من هذه الواجهة.</p>
  </section>
</template>
<style scoped>
.owner-readable-audit{font-family:inherit;color:#263a32}
.owner-audit-intro{padding:18px;background:#f6f9f7;border:1px solid #dce9e2;border-radius:15px}
.owner-audit-eyebrow{color:#0c8064;font-size:11px;font-weight:800;display:flex;align-items:center;gap:7px}
.owner-audit-headline{font-size:17px;line-height:1.8;font-weight:850;color:#183d32;margin:8px 0!important;overflow-wrap:anywhere}
.owner-audit-meta{display:flex;flex-wrap:wrap;gap:8px 14px;font-size:12px;color:#566e64}
.owner-audit-meta span{display:flex;align-items:center;gap:6px;min-width:0;overflow-wrap:anywhere}
.owner-audit-intro-note{margin:12px 0 0!important;font-size:11px!important;color:#687e72!important}
.owner-audit-warning{background:#fff3e4;padding:9px 11px;border-radius:8px;font-size:12px!important;color:#825216!important;margin-top:12px!important}
.owner-audit-finance-comparison{display:grid;grid-template-columns:1fr auto 1fr 1fr;align-items:center;gap:10px;border:1px solid #e4e9e5;border-radius:12px;padding:14px;margin:15px 0;background:white}
.owner-audit-finance-comparison>div{display:grid;gap:5px;min-width:0}
.owner-audit-finance-comparison small{color:#718277;font-size:11px}
.owner-audit-finance-comparison strong{font-size:14px;overflow-wrap:anywhere}
.owner-audit-finance-delta{border-right:1px solid #dfe7e1;padding-right:12px}
.owner-audit-finance-note{grid-column:1/-1;color:#718277!important;font-size:11px!important;margin:0!important}
.owner-audit-section-count{display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px;margin:20px 0 10px;font-size:13px;color:#445b51}
.owner-audit-section-count span{font-size:11px;color:#708478}
.owner-audit-group{border:1px solid #dfe8e2;border-radius:13px;margin:10px 0;overflow:hidden;background:white}
.owner-audit-group>summary{list-style:none;display:flex;align-items:center;gap:11px;padding:13px 15px;cursor:pointer}
.owner-audit-group>summary::-webkit-details-marker{display:none}
.owner-audit-group>summary:focus-visible{outline:3px solid #84c4b3;outline-offset:-3px}
.owner-audit-group[open]>summary{background:#f7f9f7;border-bottom:1px solid #e9eee9}
.owner-audit-group-icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;background:#edf7f3;border-radius:9px;color:#167c65;flex-shrink:0}
.owner-audit-group-label{flex:1;min-width:0;display:grid;gap:4px}
.owner-audit-group-label strong{font-size:13px;color:#25463a}
.owner-audit-group-label small{font-size:11px;color:#718378}
.owner-audit-count{font-size:12px;color:#336d59;background:#edf8f2;padding:4px 10px;border-radius:20px;font-weight:800}
.owner-audit-chevron{color:#789185;font-size:11px;transition:transform .15s}
.owner-audit-group[open] .owner-audit-chevron{transform:rotate(180deg)}
.owner-audit-group-body{padding:10px 14px}
.owner-audit-field{margin:8px 0!important;padding:13px!important;background:#fff;border-color:#e9eee9!important}
.owner-audit-field-heading{display:flex!important;justify-content:space-between!important;align-items:start!important;gap:10px!important;margin:0!important}
.owner-audit-field-heading strong{font-size:13px!important;color:#2d463a!important;direction:rtl!important;text-align:right!important;overflow-wrap:anywhere}
.owner-audit-change-type{font-size:10px;border-radius:30px;padding:3px 9px;background:#f0f4f1;color:#5c7567;white-space:nowrap}
.owner-audit-sentence{font-size:12px!important;color:#526d5e!important;line-height:1.8!important;margin:8px 0!important;overflow-wrap:anywhere}
.owner-audit-values{display:grid!important;grid-template-columns:1fr 1fr;gap:10px;margin:10px 0 0!important}
.owner-audit-values section{border-radius:9px;padding:12px;min-width:0;background:#f6f9f7}
.owner-audit-values section:first-child{background:#fff5f2}
.owner-audit-values small{display:block;font-size:11px;color:#7b867c}
.owner-audit-values p{font-size:13px;line-height:1.7;color:#1f3d31;white-space:pre-wrap;overflow-wrap:anywhere;margin:6px 0 0!important}
.owner-audit-extra{margin-top:10px;font-size:11px;color:#668070}
.owner-audit-extra>summary{cursor:pointer;color:#327d68;font-weight:750}
.owner-audit-extended{display:grid!important;grid-template-columns:1fr 1fr;gap:10px;padding-top:10px;margin:0!important}
.owner-audit-extended section{padding:10px;border:1px solid #e7ece8;border-radius:9px;min-width:0;overflow-wrap:anywhere}
.owner-audit-limited{display:flex;gap:12px;padding:16px;margin:16px 0;background:#f7f9f7;border-radius:12px;color:#586d5f;font-size:12px}
.owner-audit-limited p{font-size:12px!important;line-height:1.8!important;color:#718277!important}
.owner-audit-footnote{font-size:11px!important;color:#879486!important;line-height:1.8!important;margin-top:15px!important}
@media(max-width:630px){.owner-audit-finance-comparison{grid-template-columns:1fr 1fr}.owner-audit-finance-comparison>.pi{display:none}.owner-audit-finance-delta{border-right:0;padding:0}.owner-audit-values,.owner-audit-extended{grid-template-columns:1fr!important}}
@media print{.owner-audit-group:not([open]) .owner-audit-group-body{display:block}}
</style>
