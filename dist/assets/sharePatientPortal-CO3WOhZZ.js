import{$ as _}from"./index-CHZkuGJF.js";import{m as h}from"./labDocuments-Cf6Xb-D8.js";async function y(n,s,o){const r=n?.patient?.id||n?.patient_id_fk;if(!r)throw new Error("لا يوجد مريض مرتبط بالتقرير.");let e=String(n?.patient?.phone||"").replace(/\D/g,"");if(e.startsWith("00")&&(e=e.slice(2)),e.startsWith("0")&&(e="964"+e.slice(1)),e.startsWith("964")||(e="964"+e),e.length<12||e.length>15)throw new Error("رقم هاتف المريض المسجل غير صالح.");const{data:t}=await _.post("/portal/generate",{patient_id:r});if(!t?.url||!new URL(t.url).pathname.startsWith("/portal/"))throw new Error("تعذر إنشاء رابط بوابة المريض.");const l={lab_name:s?.lab_display_name||o||"المختبر",patient_name:n?.patient?.name||"المريض",invoice_number:n?.id||"",link:t.url,loyalty_points:t.loyalty_points,loyalty_tier:t.loyalty_tier},a=`عزيزي/عزيزتي ${l.patient_name}،
تقريرك الطبي من ${l.lab_name} جاهز.
يمكنك الاطلاع على نتائجك وسجل فحوصاتك عبر بوابة المريض:
${t.url}
سنرفق لك نسخة PDF من التقرير في هذه المحادثة.
نتمنى لك دوام الصحة والعافية.`;let i=h(s?.whatsapp_result_message,l,a);return i.includes(t.url)||(i+=`
`+t.url),{phone:e,message:i,whatsappUrl:`https://wa.me/${e}?text=${encodeURIComponent(i)}`}}async function $(n,s,o,r="result"){const e=n?.patient?.id||n?.patient_id_fk;if(!e)throw new Error("لا يوجد مريض مرتبط بهذه الفاتورة.");let t=String(n?.patient?.phone||"").replace(/[\s()+-]/g,"");if(!t)throw new Error("أضف رقم هاتف المريض أولاً.");t.startsWith("00")&&(t=t.slice(2)),t.startsWith("0")&&(t="964"+t.slice(1)),t.startsWith("964")||(t="964"+t);const l=window.open("","_blank");try{const{data:a}=await _.post("/portal/generate",{patient_id:e});if(!a?.url||!new URL(a.url).pathname.startsWith("/portal/"))throw new Error("تعذر إنشاء رابط بوابة المريض.");const i={lab_name:s?.lab_display_name||o||"المختبر",patient_name:n?.patient?.name||"المريض",invoice_number:n.id,link:a.url,loyalty_points:a.loyalty_points,loyalty_tier:a.loyalty_tier},w=r==="invoice"?s?.whatsapp_invoice_message:s?.whatsapp_result_message,c=`أهلاً ${i.patient_name}، نرحب بك في ${i.lab_name}.
تم تسجيل فاتورتك رقم ${n.id}.
من بوابتك الخاصة تقدر تتابع نقاطك وفواتيرك والتحاليل قيد الإجراء والنتائج عند جاهزيتها:
${a.url}
نتمنى لك دوام الصحة والعافية.`;let p=h(w,i,r==="invoice"?c:`أهلاً ${i.patient_name}
بوابتك لدى ${i.lab_name} لعرض النتائج والنقاط والمكافآت:
${a.url}`);p.includes(a.url)||(p+=`
`+a.url);const m=`https://wa.me/${t}?text=${encodeURIComponent(p)}`;if(l)l.location.href=m;else if(!window.open(m,"_blank"))throw new Error("اسمح بالنوافذ المنبثقة ثم أعد الإرسال.")}catch(a){throw l?.close(),new Error(a.response?.data?.message||a.message||"تعذر إنشاء رابط البوابة؛ لم يتم إرسال رابط بديل.")}}export{y as p,$ as s};
