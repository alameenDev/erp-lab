const c={invoice:{paper:"A4",orientation:"portrait",margin:15,font_size:12,color:"#1f2937",accent:"#0f766e",show_barcode:!0,show_qr:!0,footer:""},thermal:{width:80,margin:3,font_size:11,show_barcode:!0,show_qr:!0,footer:"Thank you for choosing our lab"}};function s(o,r){return{...c[r],...o?.document_config?.[r]||{}}}const m=(o,r,t,e)=>Number.isFinite(Number(o))?Math.min(t,Math.max(r,Number(o))):e,i=(o,r)=>/^#[0-9a-f]{6}$/i.test(o||"")?o:r;function l(o,r){const t=s(o,r),e=m(t.font_size,8,24,12),n=m(t.margin,0,30,r==="thermal"?3:15),a=["Tajawal","Cairo","Amiri","Inter"].includes(o?.font_family)?o.font_family:"Tajawal";return r==="thermal"?`
 @page {size:${Number(t.width)===58?58:80}mm auto;margin:${n}mm;}
 body,.thermal-receipt{width:${(Number(t.width)===58?58:80)-2*n}mm!important;max-width:100%!important;padding:0!important;font-family:"${a}",sans-serif!important;}
 .thermal-receipt,.thermal-receipt td,.thermal-receipt th,.thermal-receipt .row,.thermal-receipt .sum-row{font-size:${e}px!important;}
 .bc-row{display:${t.show_barcode?"flex":"none"}!important}.qr{display:${t.show_qr?"block":"none"}!important}
 `:`
 @page {size:${t.paper==="A5"?"A5":"A4"} ${t.orientation==="landscape"?"landscape":"portrait"};margin:${n}mm;}
 body{padding:0!important;font-family:"${a}",sans-serif!important;color:${i(t.color,"#1f2937")}!important;}
 .inv,.inv td,.inv th,.inv .info-cell{font-size:${e}px!important;color:${i(t.color,"#1f2937")};}
 .inv-title{color:${i(t.accent,"#0f766e")}!important;border-color:${i(t.accent,"#0f766e")}!important;}
 .hdr-bc{display:${t.show_barcode?"flex":"none"}!important}.hdr-qr{display:${t.show_qr?"flex":"none"}!important}
 `}function p(o,r,t){return(typeof o=="string"&&o.trim()?o:t).replace(/\{(lab_name|patient_name|invoice_number|link|loyalty_points|loyalty_tier)\}/g,(n,a)=>String(r[a]??""))}export{s as a,c as b,l as d,p as m};
