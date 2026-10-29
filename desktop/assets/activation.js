const $=id=>document.getElementById(id);
async function action(fn){document.querySelectorAll('button').forEach(b=>b.disabled=true);$('error').hidden=true;try{await fn();}catch(e){$('error').textContent=e.message.replace(/^Error invoking remote method '[^']+': (Error: )?/,'');$('error').hidden=false;}finally{document.querySelectorAll('button').forEach(b=>b.disabled=false);}}
function show(s){$('software-option').hidden=!s.allowSoftware;$('activated').hidden=!s.claims;$('status').textContent=s.channel==='qa'?'نسخة اختبار غير مخصصة للبيع':'التفعيل يعمل بدون إرسال بيانات المختبر';if(s.claims){$('details').textContent=s.claims.lab+' — '+(s.claims.type==='lifetime'?'مدى الحياة — بدون تاريخ انتهاء':'سنوي — ينتهي '+new Date(s.claims.expiresAt).toLocaleDateString('ar-IQ'))+' — '+(s.claims.binding==='tpm'?'حماية TPM':'ربط برمجي');}}
$('request').onclick=()=>action(async()=>{const r=await window.activation.request($('software').checked?'software':'tpm');if(!r.canceled)$('status').textContent='تم حفظ الطلب. استورد ملف التفعيل بعد استلامه من Digital Lab.';});
$('import').onclick=()=>action(async()=>{const s=await window.activation.import();if(!s.canceled)show(s);});
$('continue').onclick=()=>action(()=>window.activation.continue());
action(async()=>show(await window.activation.status()));
