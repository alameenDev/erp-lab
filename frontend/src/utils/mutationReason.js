/**
 * Mandatory explanation for deliberate interactive edits and deletes.
 * The prompt is shown at the actual API action, rather than on a form that
 * might be off-screen. Silent result autosaves and transport operations are
 * deliberately excluded. Critical invoice/test operations are validated again
 * by Laravel; this UI guard is not a substitute for server permissions.
 */
const labels = {
  invoices: 'الفاتورة',
  tests: 'التحليل',
  cultures: 'الزرع',
  packages: 'الباقة',
  test_groups: 'كروب التحاليل',
  patients: 'المريض',
  users: 'المستخدم',
  roles: 'الصلاحيات',
  referrals: 'الإحالة',
  contracts: 'العقد',
  labs: 'المختبر',
  categories: 'التصنيف',
  samples: 'العينة',
  templates: 'قالب الطباعة',
  'price_list': 'قائمة الأسعار',
  'payment-methods': 'طريقة الدفع',
  'discount-types': 'نوع الخصم',
  'test-questions': 'سؤال التحليل',
  'tests-questions': 'سؤال التحليل',
  'work-register': 'سجل العمل اليومي',
  'lab-settings': 'إعدادات المختبر',
  'doctor-portal': 'الكشفية الطبية',
  doctors: 'الطبيب',
  'doctor-bookings': 'موعد الطبيب',
  'owner-portal': 'بوابة المالك',
  'promo-codes': 'كود الخصم',
  bookings: 'الحجز',
  antibiotics: 'المضاد الحيوي',
};
export function reasonValue(data) {
  if (typeof FormData !== 'undefined' && data instanceof FormData) return data.get('change_reason');
  if (data && typeof data === 'object') return data.change_reason;
  if (typeof data === 'string') {
    try { return JSON.parse(data).change_reason; } catch { return new URLSearchParams(data).get('change_reason'); }
  }
  return undefined;
}
export function attachReason(data, reason) {
  if (typeof FormData !== 'undefined' && data instanceof FormData) {
    data.set('change_reason',reason); return data;
  }
  if (data === null || data === undefined) return {change_reason:reason};
  if (typeof data === 'object') return {...data,change_reason:reason};
  if (typeof data === 'string') {
    try {return JSON.stringify({...JSON.parse(data),change_reason:reason});}
    catch {
      const params=new URLSearchParams(data);params.set('change_reason',reason);
      return params.toString();
    }
  }
  return data;
}
export function mutationReasonPolicy({method='get',url='',data=null,skipMutationReason=false}={}) {
  if (skipMutationReason) return null;
  const verb=String(method).toLowerCase();
  const uri=String(url||'').split('?')[0].replace(/^https?:\/\/[^/]+/,'').replace(/^\/api\//,'/').replace(/^\//,'');
  if (/(?:\?|&)silent=1(?:&|$)/.test(String(url))) return null;
  if (/^(?:user\/(?:login|logout|register)|device\/|log\/|activity\/|owner-portal\/notifications|portal\/|push\/)/.test(uri)) return null;
  const parts=uri.split('/');
  const entity=labels[parts[0]] || 'السجل';
  const deleteOperation=verb==='delete' || /(?:^|\/)(?:delete|destroy|remove)$/.test(uri) && ['post','put','patch'].includes(verb);
  if(deleteOperation)return {action:'delete',resource:entity};
  const explicitUpdate=/(?:^|\/)(?:update|edit|reset)$/.test(uri);
  const updateMethod=['put','patch'].includes(verb) ||
    (verb==='post' && (explicitUpdate ||
      /^doctors\/\d+$/.test(uri) ||
      /^lab-settings$/.test(uri) ||
      /^referral-portal\/profile$/.test(uri) ||
      /^invoices\/(?:update-result|add-payment|sign)$/.test(uri)));
  if(updateMethod)return {action:'update',resource:entity};
  if(verb==='post' && uri==='invoices/create') {
    const raw=typeof data==='string'?(()=>{try{return JSON.parse(data)}catch{return {}}})():data;
    const discount=Number(raw?.discount || 0);
    if(discount>0)return {action:'discount',resource:'الفاتورة'};
  }
  return null;
}
export function validReason(value) {
  const s=typeof value==='string'?value.trim():'';
  return s.length>=10 && s.length<=1000;
}
export async function requestMutationReason(policy,existing=null) {
  if(!policy)return null;
  if(validReason(existing))return existing.trim();
  const {default:Swal}=await import('sweetalert2');
  const action=policy.action==='delete'?'الحذف':policy.action==='discount'?'الخصم':'التعديل';
  const {isConfirmed,value}=await Swal.fire({
    title:`سبب ${action} — ${policy.resource}`,
    text:'لا يمكن تنفيذ العملية بدون كتابة السبب. سيتم حفظه مع سجل التعديلات.',
    icon:policy.action==='delete'?'warning':'question',
    input:'textarea',
    inputLabel:`اكتب سبب ${action} (إلزامي)`,
    inputPlaceholder:'اشرح سبب التغيير بشكل واضح، مثال: تصحيح خطأ في التسجيل بموافقة المشرف.',
    inputAttributes:{maxlength:'1000','aria-label':'سبب التعديل أو الحذف'},
    showCancelButton:true,
    confirmButtonText:`حفظ السبب وتنفيذ ${action}`,
    cancelButtonText:'إلغاء العملية',
    reverseButtons:true,
    confirmButtonColor:policy.action==='delete'?'#b91c1c':'#0f766e',
    allowOutsideClick:false,
    inputValidator:v=>validReason(v)?undefined:'يرجى كتابة سبب واضح من 10 إلى 1000 حرف.',
  });
  return isConfirmed && validReason(value) ? value.trim() : null;
}
