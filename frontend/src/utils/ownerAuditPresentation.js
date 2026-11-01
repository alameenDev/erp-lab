/**
 * Owner-friendly interpretation of the immutable server audit snapshots.
 * This is PRESENTATION ONLY: never infer an edit not supported by a saved diff.
 * Old records without snapshots remain explicitly marked as limited history.
 */
const fieldNames = {
  invoice:'الفاتورة', patient:'المريض', analyses:'التحاليل', payments:'الدفعات',
  total:'إجمالي الفاتورة', sub_total:'المجموع قبل الخصم', paid:'المدفوع', price:'السعر', amount:'المبلغ',
  balance:'المتبقي', discount:'الخصم', loyalty_discount:'خصم الولاء', discount_type_id_fk:'نوع الخصم',
  result:'النتيجة', value:'القيمة', is_done:'اكتمال النتيجة', result_status_id_fk:'حالة النتيجة',
  result_status_text:'وصف النتيجة', comment:'تعليق', notes:'ملاحظات', tests_comment:'ملاحظات التحاليل',
  cultures_comment:'ملاحظات الزروع', package_comment:'ملاحظات الباقات', packages_comment:'ملاحظات الباقات',
  name:'الاسم', report_name:'اسم التقرير', unit:'الوحدة', normal_range:'المعدل الطبيعي', reference_range:'المجال المرجعي',
  sub_tests:'الفحوصات الفرعية', attribute:'خصائص الفحص', package_tests:'تحاليل الباقة',
  package_cultures:'زروع الباقة', test_group_tests:'تحاليل الكروب', test_group_cultures:'زروع الكروب',
  registration_date:'تاريخ التسجيل', result_date:'تاريخ النتيجة', barcode:'الباركود',
  patient_id_fk:'رقم المريض', referral_id_fk:'جهة الإحالة', payment_method_id_fk:'طريقة الدفع',
  sent_to_patient:'بدء إرسال النتيجة', report_sent_at:'وقت الإرسال', report_saved_at:'وقت حفظ PDF',
  report_printed_at:'وقت طباعة التقرير', is_signed:'توقيع التقرير', signed_by_id_fk:'الموظف الموقّع',
  attachments:'المرفقات', file:'ملف المرفق', download_name:'اسم الملف', mime_type:'نوع الملف',
  print_width_percent:'نسبة العرض عند الطباعة', print_max_height_mm:'أقصى ارتفاع للطباعة',
  print_auto_crop:'ملاءمة محتوى PDF', is_printed:'حالة الطباعة', status:'الحالة',
  test_id_fk:'معرّف التحليل', culture_id_fk:'معرّف الزرع', test_group_id_fk:'معرّف الكروب',
  package_id_fk:'معرّف الباقة', quantity:'الكمية', is_special_test:'تحليل خاص',
  result_date_fk:'تاريخ النتيجة', show_result_date:'إظهار تاريخ النتيجة',
  report_background:'خلفية التقرير', deleted_at:'وقت الحذف', created_by:'المنشئ',
  phone:'رقم الهاتف', age:'العمر', gender:'الجنس', address:'العنوان',
  question:'السؤال', questions:'أسئلة الفحص', answer:'الإجابة', answer_text:'الإجابة',
  is_print_alone:'طباعة بصفحة مستقلة', sort_order:'الترتيب',
};
const analysisKinds = {test:'تحليل',culture:'زرع',test_group:'كروب تحاليل',package:'باقة'};
const nestedKinds = {
  sub_tests:'فحص فرعي',attribute:'خاصية',test_group_tests:'تحليل',test_group_cultures:'زرع',
  package_tests:'تحليل',package_cultures:'زرع',
};
const monetaryFields = new Set(['total','sub_total','paid','price','amount','loyalty_discount']);
const truthFields = new Set(['is_done','is_printed','is_signed','sent_to_patient','print_auto_crop','is_special_test','show_result_date','is_print_alone']);
const noisyFields = new Set(['id','invoice_id_fk','lab_id_fk','test_id_fk','culture_id_fk','test_group_id_fk','package_id_fk',
  'created_by','result_doc','pdf_qr_code','sha256','storage_path','encoding','original_size','stored_size','extension']);
const sections = [
  {key:'results',title:'نتائج التحاليل والفحوصات',icon:'pi-heart',description:'أي نتيجة تغيّرت، وما كانت قيمتها السابقة'},
  {key:'financial',title:'المبالغ والتسديدات',icon:'pi-wallet',description:'الأسعار والإجمالي والخصومات والدفعات'},
  {key:'invoice',title:'بيانات الفاتورة والمريض',icon:'pi-file-edit',description:'بيانات التسجيل والمريض والإحالة'},
  {key:'attachments',title:'المرفقات والتقارير',icon:'pi-paperclip',description:'ملفات وتقارير وخصائص الطباعة'},
  {key:'other',title:'تغييرات إضافية',icon:'pi-list',description:'الحقول الأخرى المحفوظة في السجل'},
];

function decode(value) {
  let current=value;
  for(let i=0;i<2 && typeof current==='string' && /^[\[{]/.test(current.trim());i++){
    try {current=JSON.parse(current);} catch {break;}
  }
  return current;
}
function getPath(obj,parts) {
  let current=decode(obj);
  for(const part of parts) {
    if(current==null)return undefined;
    current=decode(current)?.[part];
  }
  return decode(current);
}
function first(...values) {
  return values.find(value=>value!==null && value!==undefined && String(value).trim()!=='');
}
function safeName(value) {
  return typeof value==='string' && value.trim() ? value.trim().slice(0,110) : '';
}
function parseAnalysisKey(key) {
  const match=String(key||'').match(/^(test_group|test|culture|package)_(\d+)_(\d+)$/);
  return match ? {type:analysisKinds[match[1]],index:match[3]}:null;
}
function analysisContext(parts, snapshot={}) {
  if(parts[0]!=='analyses' || !parts[1])return null;
  const base=['analyses',parts[1]];
  const b=getPath(snapshot.before,base)||{};
  const a=getPath(snapshot.after,base)||{};
  const match=parseAnalysisKey(parts[1]);
  const parentName=safeName(first(a?.name,b?.name)) ||
    (match ? `${match.type} #${match.index}` : 'فحص');
  let currentName=parentName, currentType=match?.type || 'فحص', nestedAt=-1;
  for(let i=2;i<parts.length-1;i++){
    if(!(parts[i] in nestedKinds) || !/^\d+$/.test(parts[i+1]||''))continue;
    const path=[...base,...parts.slice(2,i+2)];
    const child=first(getPath(snapshot.after,path),getPath(snapshot.before,path))||{};
    currentType=nestedKinds[parts[i]];
    currentName=safeName(first(child?.name,child?.attribute_name,child?.report_name)) || `${currentType} ${Number(parts[i+1])+1}`;
    nestedAt=i+1;
    i++;
  }
  return {parentName,currentName,currentType,nestedAt};
}
export function ownerChangeCategory(field='') {
  const parts=String(field).split('.');
  const tail=parts.at(-1);
  if(parts[0]==='payments' || (parts[0]==='invoice' && ['total','sub_total','paid','discount','discount_type_id_fk','loyalty_discount','payment_method_id_fk'].includes(tail)) ||
      (parts[0]==='analyses' && ['price','amount'].includes(tail))) return 'financial';
  if(parts[0]==='analyses' || ['is_done','result_status_text'].includes(tail))return 'results';
  if(parts.includes('attachments') || /^(result_doc|pdf_qr_code|report_.*_at|is_printed)$/.test(tail)) return 'attachments';
  if(['invoice','patient'].includes(parts[0]))return 'invoice';
  return 'other';
}
export function ownerChangeLabel(change,snapshot={}) {
  const parts=String(change?.field||'').split('.').filter(Boolean);
  const tail=parts.at(-1)||'';
  const friendly=fieldNames[tail]||tail.replace(/_fk$/,'').replace(/_/g,' ');
  if(parts[0]==='analyses') {
    const ctx=analysisContext(parts,snapshot);
    if(!ctx)return friendly;
    if(parts.length===2)return ctx.parentName;
    const target=`${ctx.currentType} ${ctx.currentName}`.replace(/^(.+) \1$/,'$1');
    if(tail==='result'||(tail==='value'&&ctx.nestedAt>=0))return `نتيجة ${target}`;
    if(tail==='is_done')return `اكتمال ${target}`;
    if(tail==='name')return `اسم ${target}`;
    if(tail==='price')return `سعر ${target}`;
    if(tail==='comment')return `ملاحظة ${target}`;
    if(['test_group_tests','package_tests','test_group_cultures','package_cultures','sub_tests','attribute'].includes(tail))
      return `محتويات ${target}`;
    return `${friendly} — ${target}`;
  }
  if(parts[0]==='payments') {
    if(parts.length===2)return 'دفعة مالية';
    return `${friendly} (دفعة مالية)`;
  }
  if(parts.includes('attachments'))return `${friendly} (مرفق ${Number(parts[2]||0)+1})`;
  if(parts[0]==='patient')return tail==='name'?'اسم المريض':friendly;
  return friendly||'تفصيل إضافي';
}
export function ownerReadableValue(value,field='') {
  const v=decode(value),tail=String(field).split('.').at(-1);
  if(v===null||v===undefined)return 'غير مسجل';
  if(v==='')return 'فارغ';
  if(v==='[محجوب]')return 'بيانات محجوبة للحماية';
  if(tail==='file'||tail==='storage_path'||tail==='result_doc'||tail==='pdf_qr_code')return v ? 'ملف محفوظ' : 'غير موجود';
  if(truthFields.has(tail))return v===true||v===1||v==='1'?'نعم':'لا';
  if(typeof v==='boolean')return v?'نعم':'لا';
  if(typeof v==='object') {
    if(Array.isArray(v)) {
      if(!v.length)return 'لا توجد عناصر';
      const names=v.map(x=>safeName(x?.name||x?.report_name||x?.attribute_name||x?.download_name)).filter(Boolean);
      return names.length ? `${v.length} عنصر: ${names.slice(0,4).join('، ')}${names.length>4?' وغيرها':''}` : `${v.length} عنصر/سجل`;
    }
    const name=safeName(v.name||v.report_name||v.download_name);
    return name || `بيانات تفصيلية (${Object.keys(v).length} حقول)`;
  }
  if(monetaryFields.has(tail) && typeof v!=='boolean' && String(v).trim()!=='' && Number.isFinite(Number(v)))
    return new Intl.NumberFormat('ar-IQ',{maximumFractionDigits:2}).format(Number(v))+' د.ع';
  if(tail==='print_width_percent')return String(v)+'%';
  if(tail==='print_max_height_mm')return String(v)+' ملم';
  if(tail==='status')return ({pending:'قيد الانتظار',complete:'مكتمل',completed:'مكتمل',cancelled:'ملغى',confirmed:'مؤكد'})[v]||String(v);
  return String(v);
}
export function ownerChangeNarrative(change,snapshot={}) {
  const label=ownerChangeLabel(change,snapshot);
  const before=ownerReadableValue(change?.before,change?.field);
  const after=ownerReadableValue(change?.after,change?.field);
  if(change?.kind==='added'||change?.before===null||change?.before===undefined)return `إضافة ${label}: ${after}`;
  if(change?.kind==='removed'||change?.after===null||change?.after===undefined)return `إزالة ${label} (سابقاً: ${before})`;
  return `تغيير ${label} من «${before}» إلى «${after}»`;
}
export function ownerAuditGroups(record={}) {
  const snapshot=record?.snapshot||record?.details||{};
  const changes=Array.isArray(snapshot?.changes)?snapshot.changes:[];
  const groups=sections.map(section=>({...section,changes:[]}));
  const byKey=Object.fromEntries(groups.map(group=>[group.key,group]));
  for(const change of changes) {
    if(!change || typeof change!=='object')continue;
    const field=String(change.field||'');
    const label=ownerChangeLabel(change,snapshot);
    const entry={...change,label,category:ownerChangeCategory(field),beforeText:ownerReadableValue(change.before,field),
      afterText:ownerReadableValue(change.after,field),narrative:ownerChangeNarrative(change,snapshot),technical:field};
    byKey[entry.category].changes.push(entry);
  }
  return groups.filter(g=>g.changes.length);
}
export function ownerAuditSummary(record={},withChanges=false) {
  const event=record.event;
  if(event==='deleted')return record.invoice_id ? `حذف الفاتورة #${record.invoice_id}`:'حذف البيانات';
  if(event==='failed')return `محاولة لم تكتمل: ${record.description||'إجراء في النظام'}`;
  if(event==='clicked')return `ضغط زر: ${record.button||record.description||'إجراء في الواجهة'} (لا يؤكد حفظ تغيير)`;
  if(!withChanges) {
    if(record.results_changed && record.financial_changed)return 'تعديل نتائج التحاليل والمبالغ المالية';
    if(record.results_changed)return 'تعديل نتائج التحاليل أو حالة اكتمالها';
    if(record.financial_changed)return 'تعديل معلومات الفاتورة أو تسديداتها';
    return record.description||'تعديل بيانات الفاتورة';
  }
  const groups=ownerAuditGroups(record);
  const all=groups.flatMap(g=>g.changes).filter(c=>!noisyFields.has(c.technical.split('.').at(-1)));
  const primary=all.find(c=>c.category==='results' && /\.(result|value)$/.test(c.technical)) ||
    all.find(c=>c.category==='financial') || all[0];
  if(!primary)return record.description||'عملية مسجلة دون تفاصيل حقول';
  if(all.length===1)return primary.narrative;
  return `${primary.narrative}، مع ${all.length-1} تغيير آخر`;
}
export function ownerAuditCount(record={}) {
  return ownerAuditGroups(record).reduce((sum,g)=>sum+g.changes.length,0);
}
