function r(t){if(Array.isArray(t))return t.map(r).filter(Boolean).join(`
`);if(typeof t!="string")return"";const n=t.trim();if(!n||n==="null")return"";if(n.startsWith("["))try{const e=JSON.parse(n);if(Array.isArray(e))return r(e)}catch{}return n}function l(t){if(typeof t=="string")try{t=JSON.parse(t)}catch{t=[t]}return[...new Set((Array.isArray(t)?t:[]).map(r).filter(Boolean))]}function m(t,n){const e=r(t),a=r(n);return!a||e===a||`
${e}
`.includes(`
${a}
`)?e:e?`${e}
${a}`:a}function o(t){return![!1,0,"0","false"].includes(t?.print_table_config?.show_comments)}function i(t){return r(t).replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#39;").replaceAll("{","&#123;").replaceAll("}","&#125;").replace(/\r?\n/g,"<br>")}const c=t=>[t.name,String(t.name||"").trim().replace(/\s+/g,"_").replace(/[^\w؀-ۿ]/g,"").slice(0,64)].map(n=>`{{sub_test.${n}.comment}}`);function s(t){const n=t?.content?.html||"";return[...n.includes("{{comment}}")?[]:[{label:t?.report_name||t?.name,text:r(t?.comment)}],...(t?.sub_tests||[]).filter(e=>!c(e).some(a=>n.includes(a))).map(e=>({label:e.name,text:r(e.comment)}))].filter(e=>e.text)}export{r as a,i as b,l as c,m as d,o as r,s as t};
