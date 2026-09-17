export const documentDefaults = {
 invoice: {paper:"A4",orientation:"portrait",margin:15,font_size:12,color:"#1f2937",accent:"#0f766e",show_barcode:true,show_qr:true,footer:""},
 thermal: {width:80,margin:3,font_size:11,show_barcode:true,show_qr:true,footer:"Thank you for choosing our lab"},
};
export function documentConfig(settings,kind) {
 return {...documentDefaults[kind],...(settings?.document_config?.[kind] || {})};
}
const number=(value,min,max,fallback)=>Number.isFinite(Number(value))?Math.min(max,Math.max(min,Number(value))):fallback;
const color=(v,fallback)=>/^#[0-9a-f]{6}$/i.test(v||"")?v:fallback;
export function documentCss(settings,kind) {
 const c=documentConfig(settings,kind);
 const size=number(c.font_size,8,24,12);
 const margin=number(c.margin,0,30,kind==="thermal"?3:15);
 const font=["Tajawal","Cairo","Amiri","Inter"].includes(settings?.font_family)?settings.font_family:"Tajawal";
 if(kind==="thermal")return `
 @page {size:${Number(c.width)===58?58:80}mm auto;margin:${margin}mm;}
 body,.thermal-receipt{width:${(Number(c.width)===58?58:80)-2*margin}mm!important;max-width:100%!important;padding:0!important;font-family:"${font}",sans-serif!important;}
 .thermal-receipt,.thermal-receipt td,.thermal-receipt th,.thermal-receipt .row,.thermal-receipt .sum-row{font-size:${size}px!important;}
 .bc-row{display:${c.show_barcode?"flex":"none"}!important}.qr{display:${c.show_qr?"block":"none"}!important}
 `;
 return `
 @page {size:${c.paper==="A5"?"A5":"A4"} ${c.orientation==="landscape"?"landscape":"portrait"};margin:${margin}mm;}
 body{padding:0!important;font-family:"${font}",sans-serif!important;color:${color(c.color,"#1f2937")}!important;}
 .inv,.inv td,.inv th,.inv .info-cell{font-size:${size}px!important;color:${color(c.color,"#1f2937")};}
 .inv-title{color:${color(c.accent,"#0f766e")}!important;border-color:${color(c.accent,"#0f766e")}!important;}
 .hdr-bc{display:${c.show_barcode?"flex":"none"}!important}.hdr-qr{display:${c.show_qr?"flex":"none"}!important}
 `;
}
export function messageTemplate(template,values,fallback) {
 const source=typeof template==="string"&&template.trim()?template:fallback;
 return source.replace(/\{(lab_name|patient_name|invoice_number|link|loyalty_points|loyalty_tier)\}/g,(_,key)=>String(values[key]??""));
}
