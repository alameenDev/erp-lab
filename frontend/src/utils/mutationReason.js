export const isMutationCancelled = error => error?.code === 'ERR_CANCELED';

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
  if (!policy) return null;
  // Existing invoice/test delete confirmations already collected the reason.
  if (policy.action === 'delete' && validReason(existing)) return existing.trim();
  const action=policy.action==='delete'?'الحذف':policy.action==='discount'?'الخصم':'التعديل';
  // showModal() uses the browser's native top layer. It remains visible above
  // native <dialog> editors (work-register etc), unlike normal z-index popups.
  return new Promise(resolve=>{
    const dialog=document.createElement('dialog');
    dialog.dataset.mutationReasonDialog='true';
    dialog.dir='rtl';
    dialog.setAttribute('aria-modal','true');
    dialog.style.cssText='width:min(94vw,540px);max-height:90vh;overflow:auto;padding:0;border:1px solid #d9e6e0;border-radius:16px;background:white;box-shadow:0 24px 75px rgba(15,23,42,.3);color:#163a30;';
    const style=document.createElement('style');
    style.textContent='dialog[data-mutation-reason-dialog]::backdrop{background:rgba(15,23,42,.58)}';
    dialog.append(style);
    const form=document.createElement('form');
    form.noValidate=true;
    form.style.cssText='display:grid;gap:15px;padding:24px;';
    const title=document.createElement('h2');
    title.textContent=`سبب ${action} — ${policy.resource}`;
    title.id='mutation-reason-title';
    title.style.cssText='font-size:20px;font-weight:800;margin:0;';
    dialog.setAttribute('aria-labelledby',title.id);
    const subtitle=document.createElement('p');
    subtitle.textContent='السبب إلزامي قبل تنفيذ العملية، وسوف يُحفظ ضمن سجل التدقيق. يمكنك إلغاء العملية دون تغيير البيانات.';
    subtitle.style.cssText='font-size:13px;line-height:1.9;color:#52665d;margin:0';
    const label=document.createElement('label');
    label.textContent=`اكتب سبب ${action} بالتفصيل (10 أحرف على الأقل)`;
    label.htmlFor='mandatory-mutation-reason-input';
    label.style.cssText='display:grid;gap:8px;font-weight:700;font-size:13px';
    const input=document.createElement('textarea');
    input.id='mandatory-mutation-reason-input';
    input.dataset.mutationReasonInput='true';
    input.required=true;
    input.rows=4;
    input.maxLength=1000;
    input.value=validReason(existing)?existing.trim():'';
    input.placeholder='مثال: تصحيح خطأ في تسجيل السعر بعد مراجعة المشرف.';
    input.style.cssText='width:100%;box-sizing:border-box;border:1px solid #aec8bc;border-radius:10px;padding:12px;font:inherit;font-weight:400;resize:vertical;color:#142e25;background:white;outline-offset:3px;';
    const validation=document.createElement('p');
    validation.dataset.mutationReasonError='true';
    validation.setAttribute('role','alert');
    validation.hidden=true;
    validation.style.cssText='color:#b91c1c;font-size:12px;margin:0';
    label.append(input);
    const footer=document.createElement('div');
    footer.style.cssText='display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;';
    const cancel=document.createElement('button');
    cancel.type='button';cancel.dataset.mutationReasonCancel='true';
    cancel.textContent='إلغاء العملية';
    cancel.style.cssText='padding:11px 17px;background:#f1f5f3;color:#344f41;border:1px solid #d8e2db;border-radius:10px;font:inherit;font-weight:700;cursor:pointer;';
    const confirm=document.createElement('button');
    confirm.type='submit';confirm.dataset.mutationReasonConfirm='true';
    confirm.textContent=`تأكيد ${action} وحفظ السبب`;
    confirm.style.cssText=`padding:11px 17px;background:${policy.action==='delete'?'#b91c1c':'#0f766e'};color:white;border:0;border-radius:10px;font:inherit;font-weight:700;cursor:pointer;`;
    footer.append(cancel,confirm);form.append(title,subtitle,label,validation,footer);
    dialog.append(form);
    let finished=false;
    const finish=(value)=>{
      if(finished)return;
      finished=true;
      dialog.close();
      dialog.remove();
      resolve(value);
    };
    cancel.addEventListener('click',()=>finish(null));
    dialog.addEventListener('cancel',e=>{e.preventDefault();finish(null)});
    form.addEventListener('submit',e=>{
      e.preventDefault();
      if(!validReason(input.value)) {
        validation.textContent='يرجى كتابة سبب واضح من 10 إلى 1000 حرف.';
        validation.hidden=false;
        input.setAttribute('aria-invalid','true');
        input.focus();return;
      }
      finish(input.value.trim());
    });
    document.body.append(dialog);
    try{dialog.showModal();input.focus();}catch{dialog.remove();resolve(null);}
  });
}
