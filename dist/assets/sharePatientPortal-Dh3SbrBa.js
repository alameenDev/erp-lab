import{$ as h}from"./index-BQONPrgV.js";import{m as c}from"./labDocuments-Cf6Xb-D8.js";async function y(a,i,m,r="result"){const l=a?.patient?.id||a?.patient_id_fk;if(!l)throw new Error("لا يوجد مريض مرتبط بهذه الفاتورة.");let e=String(a?.patient?.phone||"").replace(/[\s()+-]/g,"");if(!e)throw new Error("أضف رقم هاتف المريض أولاً.");e.startsWith("00")&&(e=e.slice(2)),e.startsWith("0")&&(e="964"+e.slice(1)),e.startsWith("964")||(e="964"+e);const o=window.open("","_blank");try{const{data:t}=await h.post("/portal/generate",{patient_id:l});if(!t?.url||!new URL(t.url).pathname.startsWith("/portal/"))throw new Error("تعذر إنشاء رابط بوابة المريض.");const n={lab_name:i?.lab_display_name||m||"المختبر",patient_name:a?.patient?.name||"المريض",invoice_number:a.id,link:t.url,loyalty_points:t.loyalty_points,loyalty_tier:t.loyalty_tier},_=r==="invoice"?i?.whatsapp_invoice_message:i?.whatsapp_result_message,w=`أهلاً ${n.patient_name}، نرحب بك في ${n.lab_name}.
تم تسجيل فاتورتك رقم ${a.id}.
من بوابتك الخاصة تقدر تتابع نقاطك وفواتيرك والتحاليل قيد الإجراء والنتائج عند جاهزيتها:
${t.url}
نتمنى لك دوام الصحة والعافية.`;let s=c(_,n,r==="invoice"?w:`أهلاً ${n.patient_name}
بوابتك لدى ${n.lab_name} لعرض النتائج والنقاط والمكافآت:
${t.url}`);s.includes(t.url)||(s+=`
`+t.url);const p=`https://wa.me/${e}?text=${encodeURIComponent(s)}`;if(o)o.location.href=p;else if(!window.open(p,"_blank"))throw new Error("اسمح بالنوافذ المنبثقة ثم أعد الإرسال.")}catch(t){throw o?.close(),new Error(t.response?.data?.message||t.message||"تعذر إنشاء رابط البوابة؛ لم يتم إرسال رابط بديل.")}}export{y as s};
