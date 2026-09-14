import{m as P,_ as B,r as U,o as s,c as y,w as v,j as d,b as e,d as i,t as o,F as I,q as C,f as F,g as $,i as q,n as j,a as Q,N as ue,O as pe,C as ce,P as me,e as z,Q as ee,M as be,s as he,p as ge,k as J,u as ye}from"./index-29cb79eb.js";import{u as H}from"./qrcode.vue.esm-2997d369.js";import{u as le,B as ne}from"./BarcodeComponent-ba1969db.js";import{u as fe}from"./patients-de2b99e0.js";import{p as ie,u as de}from"./print_Result-a4e1229f.js";import{u as _e}from"./contracts-10e90514.js";import{J as Ve,h as ke}from"./html2pdf-d006e935.js";import{u as te,w as we}from"./xlsx-f5126985.js";const ve={data(){return{}},computed:{...P(le,["resultStatus"]),...P(H,["pationtHistoryList","pationtHistoryDialog"])},methods:{close(){this.pationtHistoryDialog=!1},print(){const t=document.getElementById("history").innerHTML,l=document.body.innerHTML;document.body.innerHTML=t,window.print(),document.body.innerHTML=l,window.location.reload()}}},Re={key:0,class:"invoices"},Ue={class:"invoice"},$e={key:0,class:"table"},Ie={class:"name"},Ce={key:1,class:"card flex justify-content-center mb-5"},De={key:0,class:"table"},xe={class:"name"},Te={key:1,class:"card flex justify-content-center mb-5"},Fe={key:1,class:"invoices",id:"history"},Me={class:"invoice"},Se={key:0,class:"table"},Ae={class:"name"},Le={key:1,class:"card flex justify-content-center mb-5"},je={key:0,class:"table"},ze={class:"name"},Pe={key:1,class:"card flex justify-content-center mb-5"},He={key:2,class:"card flex justify-content-center mb-5"},Be={class:"flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function We(t,l,r,h,b,m){const f=U("InlineMessage"),p=U("TabPanel"),k=U("TabView"),c=U("Button"),g=U("Dialog");return s(),y(g,{visible:t.pationtHistoryDialog,"onUpdate:visible":l[2]||(l[2]=V=>t.pationtHistoryDialog=V),modal:"",header:t.t("pationtHistory"),style:q([{width:"70rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>[t.pationtHistoryList?(s(),d("div",Re,[e("div",Ue,[i(k,null,{default:v(()=>[i(p,{header:t.t("tests")},{default:v(()=>[t.pationtHistoryList.tests.length>0?(s(),d("table",$e,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("theDate")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(t.pationtHistoryList.tests,V=>(s(),d("tr",{key:V},[e("td",Ie,o(V.name),1),e("td",null,o(V.result??"---"),1),e("td",null,o(V.status??"---"),1),e("td",null,o(t.dateTimeFormat(V.updated_at)),1)]))),128))])])):(s(),d("div",Ce,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),_:1},8,["header"]),i(p,{header:t.t("cultures")},{default:v(()=>[t.pationtHistoryList.cultures.length>0?(s(),d("table",De,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("theDate")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(t.pationtHistoryList.cultures,V=>(s(),d("tr",{key:V},[e("td",xe,o(V.name),1),e("td",null,o(V.result),1),e("td",null,o(V.status??"---"),1),e("td",null,o(t.dateTimeFormat(V.updated_at)),1)]))),128))])])):(s(),d("div",Te,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),_:1},8,["header"])]),_:1}),l[3]||(l[3]=e("br",null,null,-1))])])):$("",!0),t.pationtHistoryList?(s(),d("div",Fe,[e("div",Me,[e("div",null,[e("h2",null,o(t.t("tests")),1),t.pationtHistoryList.tests.length>0?(s(),d("table",Se,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("theDate")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(t.pationtHistoryList.tests,V=>(s(),d("tr",{key:V},[e("td",Ae,o(V.name),1),e("td",null,o(V.result??"---"),1),e("td",null,o(V.status??"---"),1),e("td",null,o(t.dateTimeFormat(V.updated_at)),1)]))),128))])])):(s(),d("div",Le,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),e("div",null,[e("h2",null,o(t.t("cultures")),1),t.pationtHistoryList.cultures.length>0?(s(),d("table",je,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("theDate")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(t.pationtHistoryList.cultures,V=>(s(),d("tr",{key:V},[e("td",ze,o(V.name),1),e("td",null,o(V.result),1),e("td",null,o(V.status??"---"),1),e("td",null,o(t.dateTimeFormat(V.updated_at)),1)]))),128))])])):(s(),d("div",Pe,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),l[4]||(l[4]=e("br",null,null,-1))])])):(s(),d("div",He,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})])),e("div",Be,[i(c,{size:"small",label:t.t("close"),severity:"danger",onClick:l[0]||(l[0]=V=>m.close())},null,8,["label"]),i(c,{size:"small",label:t.t("print"),severity:"primary",onClick:l[1]||(l[1]=V=>m.print())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const Ee=B(ve,[["render",We],["__scopeId","data-v-19bb7e89"]]);const Ne={computed:{...P(H,["patientdialog","patient"])},methods:{close(){this.patient=[],this.patientdialog=!1}},watch:{}},Oe={key:0,class:"table table-bordered table-striped m-0"},Ge={nowrap:"nowrap"},Je={nowrap:"nowrap"},qe={nowrap:"nowrap"},Qe={nowrap:"nowrap"},Ye={nowrap:"nowrap"},Ke={nowrap:"nowrap"},Xe={nowrap:"nowrap"},Ze={nowrap:"nowrap"},et={nowrap:"nowrap"},tt={nowrap:"nowrap"},lt={nowrap:"nowrap"},nt={nowrap:"nowrap",class:"direction-ltr"},ot={nowrap:"nowrap"},st={nowrap:"nowrap"},at={key:1,class:"card flex justify-content-center mb-5"},dt={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function it(t,l,r,h,b,m){var c;const f=U("InlineMessage"),p=U("Button"),k=U("Dialog");return s(),y(k,{visible:t.patientdialog,"onUpdate:visible":l[1]||(l[1]=g=>t.patientdialog=g),modal:"",header:(c=t.patient)!=null&&c.name?t.patient.name:"",style:q([{width:"50rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>{var g,V,T,M,S,A,L,u;return[e("div",null,[t.patient?(s(),d("table",Oe,[e("tbody",null,[e("tr",null,[e("th",Ge,o(t.t("name")),1),e("td",Je,o((g=t.patient)==null?void 0:g.name),1)]),e("tr",null,[e("th",qe,o(t.t("national_id_no")),1),e("td",Qe,o((V=t.patient)==null?void 0:V.national_id_no),1)]),e("tr",null,[e("th",Ye,o(t.t("gender")),1),e("td",Ke,o((T=t.patient)==null?void 0:T.gender),1)]),e("tr",null,[e("th",Xe,o(t.t("dob")),1),e("td",Ze,o((M=t.patient)==null?void 0:M.dob),1)]),e("tr",null,[e("th",et,o(t.t("age")),1),e("td",tt,o(((S=t.patient)==null?void 0:S.age_unit)+" "+((A=t.patient)==null?void 0:A.age)),1)]),e("tr",null,[e("th",lt,o(t.t("phone_number")),1),e("td",nt,o((L=t.patient)==null?void 0:L.phone),1)]),e("tr",null,[e("th",ot,o(t.t("address")),1),e("td",st,o((u=t.patient)==null?void 0:u.address),1)])])])):(s(),d("div",at,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),e("div",dt,[i(p,{size:"small",label:t.t("close"),severity:"danger",onClick:l[0]||(l[0]=R=>m.close())},null,8,["label"])])]}),_:1},8,["visible","header","style"])}const rt=B(Ne,[["render",it],["__scopeId","data-v-bcc43f1a"]]);const ut={computed:{...P(H,["record","Patient_dueDialog"])},methods:{close(){this.record=[],this.Patient_dueDialog=!1}},watch:{}},pt={key:0,class:"table table-bordered table-striped m-0"},ct={key:0,class:"table table-bordered table-striped m-0"},mt={key:1,class:"card flex justify-content-center mb-5"},bt={class:"flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function ht(t,l,r,h,b,m){const f=U("InlineMessage"),p=U("Button"),k=U("Dialog");return s(),y(k,{visible:t.Patient_dueDialog,"onUpdate:visible":l[1]||(l[1]=c=>t.Patient_dueDialog=c),modal:"",header:t.t("the_tests"),style:q([{width:"50rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>[e("div",null,[(s(!0),d(I,null,C(t.record.test_groups,c=>(s(),d("div",{style:{"text-align":"center"},key:c},[e("div",null,[e("p",{style:{"font-weight":"bold"},class:j(c.is_done?"done":"pendening")},o(c.name),3),t.record?(s(),d("table",pt,[e("tbody",null,[(s(!0),d(I,null,C(c.tests,g=>(s(),d("tr",{key:g},[e("td",{nowrap:"nowrap",class:j(c.is_done?"done":"pendening")},o(g.name),3)]))),128)),(s(!0),d(I,null,C(c.cultures,g=>(s(),d("tr",{key:g},[e("td",{nowrap:"nowrap",class:j(c.is_done?"done":"pendening")},o(g.name),3)]))),128))])])):$("",!0)])]))),128)),l[2]||(l[2]=e("br",null,null,-1)),t.record?(s(),d("table",ct,[e("tbody",null,[(s(!0),d(I,null,C(t.record.tests,c=>(s(),d("tr",{key:c},[e("td",{nowrap:"nowrap",class:j(c.is_done?"done":"pendening")},o(c.name),3)]))),128)),(s(!0),d(I,null,C(t.record.cultures,c=>(s(),d("tr",{key:c},[e("td",{nowrap:"nowrap",class:j(c.is_done?"done":"pendening")},o(c.name),3)]))),128)),(s(!0),d(I,null,C(t.record.packages,c=>(s(),d("tr",{key:c},[e("td",{nowrap:"nowrap",class:j(c.is_done?"done":"pendening")},o(c.name),3)]))),128))])])):(s(),d("div",mt,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),e("div",bt,[i(p,{size:"small",label:t.t("close"),severity:"danger",onClick:l[0]||(l[0]=c=>m.close())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const gt=B(ut,[["render",ht],["__scopeId","data-v-e9ae2b48"]]);const yt={computed:{...P(H,["printRecord","samplesTests"])},components:{BarcodeComponent:ne},methods:{}},ft={class:"container",id:"parcode"},_t={class:"details"},Vt={class:"barcode"},kt={class:"text-center",style:{"line-height":"0"}},wt={class:"test-list"};function vt(t,l,r,h,b,m){const f=U("BarcodeComponent");return s(),d("div",ft,[(s(!0),d(I,null,C(t.samplesTests,(p,k)=>{var c,g,V,T,M,S,A,L,u,R,n;return s(),d("div",{class:j(["label-container",k>0?"page-break":""]),style:{"font-size":"2.5px"},key:k},[e("p",_t,[e("span",null,o(p==null?void 0:p.sample_name),1)]),e("div",Vt,[e("div",null,[e("span",null,o((c=t.printRecord)==null?void 0:c.barcode),1)]),e("div",null,[i(f,{value:(g=t.printRecord)==null?void 0:g.barcode},null,8,["value"])])]),e("div",kt,[e("p",null,o((T=(V=t.printRecord)==null?void 0:V.patient)==null?void 0:T.name),1),e("p",null,o((S=(M=t.printRecord)==null?void 0:M.patient)==null?void 0:S.gender)+" / "+o(((L=(A=t.printRecord)==null?void 0:A.patient)==null?void 0:L.age)+((R=(u=t.printRecord)==null?void 0:u.patient)==null?void 0:R.age_unit))+"     "+o(t.dateTimeFormat((n=t.printRecord)==null?void 0:n.registration_date)),1)]),e("div",wt,[(s(!0),d(I,null,C(p.test,_=>(s(),d("span",{key:_},o(_.name)+"-",1))),128)),(s(!0),d(I,null,C(p.culture,_=>(s(),d("span",{key:_},o(_.name)+"-",1))),128))])],2)}),128))])}const Rt=B(yt,[["render",vt],["__scopeId","data-v-55fa591a"]]);const Ut={computed:{...P(H,["printRecord","WhatsUpDialog","Pdfurl"])},components:{printResult:ie},methods:{...Q(H,["pdf"]),...Q(fe,["whatsapp"]),async sendMsg(){var l,r,h;const t=document.getElementById("Result").outerHTML;await this.pdf(t,(l=this.printRecord)==null?void 0:l.id),this.whatsapp((h=(r=this.printRecord)==null?void 0:r.patient)==null?void 0:h.phone,this.Pdfurl.path).then(b=>{this.alertSuccess(this.t("alertSuccess")),this.close()})},close(){this.WhatsUpDialog=!1}},watch:{}},$t={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function It(t,l,r,h,b,m){const f=U("printResult"),p=U("Button"),k=U("Dialog");return s(),y(k,{visible:t.WhatsUpDialog,"onUpdate:visible":l[2]||(l[2]=c=>t.WhatsUpDialog=c),modal:"",header:t.t("sendMessage"),style:q([{width:"70rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>[e("div",null,[i(f)]),e("div",$t,[i(p,{size:"small",label:t.t("close"),severity:"danger",onClick:l[0]||(l[0]=c=>m.close())},null,8,["label"]),i(p,{size:"small",label:t.t("sendMessage"),severity:"success",onClick:l[1]||(l[1]=c=>m.sendMsg())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const Ct=B(Ut,[["render",It],["__scopeId","data-v-ed6af92a"]]);const Dt={class:"grid"},xt={class:"col-6"},Tt={class:"col-6"},Ft={class:"col-6 mt-3"},Mt={class:"col-6 mt-3"},St={__name:"PrintMarginDialog",props:{visible:{},visibleModifiers:{}},emits:ue(["preview"],["update:visible"]),setup(t,{emit:l}){const r=pe(t,"visible"),h=ce({top:20,bottom:20,left:15,right:15});me(()=>{const m=localStorage.getItem("printMargins");m&&(h.value=JSON.parse(m))});async function b(){await localStorage.setItem("printMargins",JSON.stringify(h.value)),r.value=!1}return(m,f)=>{const p=U("InputNumber"),k=U("Button"),c=U("Dialog");return s(),y(c,{visible:r.value,"onUpdate:visible":f[4]||(f[4]=g=>r.value=g),modal:"",header:"ضبط هوامش الطباعة",style:{width:"500px"}},{footer:v(()=>[i(k,{label:"حفظ",icon:"pi pi-save",class:"p-button-success",onClick:b})]),default:v(()=>[e("div",Dt,[e("div",xt,[f[5]||(f[5]=e("label",null,"الهامش العلوي (mm)",-1)),i(p,{modelValue:h.value.top,"onUpdate:modelValue":f[0]||(f[0]=g=>h.value.top=g),suffix:"mm",min:0},null,8,["modelValue"])]),e("div",Tt,[f[6]||(f[6]=e("label",null,"الهامش السفلي (mm)",-1)),i(p,{modelValue:h.value.bottom,"onUpdate:modelValue":f[1]||(f[1]=g=>h.value.bottom=g),suffix:"mm",min:0},null,8,["modelValue"])]),e("div",Ft,[f[7]||(f[7]=e("label",null,"الهامش الأيسر (mm)",-1)),i(p,{modelValue:h.value.left,"onUpdate:modelValue":f[2]||(f[2]=g=>h.value.left=g),suffix:"mm",min:0},null,8,["modelValue"])]),e("div",Mt,[f[8]||(f[8]=e("label",null,"الهامش الأيمن (mm)",-1)),i(p,{modelValue:h.value.right,"onUpdate:modelValue":f[3]||(f[3]=g=>h.value.right=g),suffix:"mm",min:0},null,8,["modelValue"])])])]),_:1},8,["visible"])}}},At=B(St,[["__scopeId","data-v-6acbc125"]]);const Lt={computed:{...P(H,["attachments","AttachDialog"])},methods:{close(){this.attachments=[],this.AttachDialog=!1},href(t){window.open(t,"_blank")}}},jt={key:0,class:"table table-bordered table-striped m-0"},zt={key:1,class:"card flex justify-content-center mb-5"},Pt={class:"flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function Ht(t,l,r,h,b,m){const f=U("Button"),p=U("InlineMessage"),k=U("Dialog");return s(),y(k,{visible:t.AttachDialog,"onUpdate:visible":l[1]||(l[1]=c=>t.AttachDialog=c),modal:"",header:t.t("attachments"),style:q([{width:"50rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>[e("div",null,[t.attachments.length>0?(s(),d("table",jt,[e("thead",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("file")),1)]),e("tbody",null,[(s(!0),d(I,null,C(t.attachments,c=>(s(),d("tr",{key:c},[e("td",null,o(c.name),1),e("td",null,[i(f,{style:{width:"44%"},icon:"pi pi-link",as:"a",label:t.t("show_result_date"),href:c.file,target:"_blank",rel:"noopener",onClick:g=>m.href(c.file)},null,8,["label","href","onClick"])])]))),128))])])):(s(),d("div",zt,[i(p,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),e("div",Pt,[i(f,{size:"small",label:t.t("close"),severity:"danger",onClick:l[0]||(l[0]=c=>m.close())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const Bt=B(Lt,[["render",Ht],["__scopeId","data-v-4d3cb34d"]]);const Wt={components:{BarcodeComponent:ne},computed:{...P(H,["printRecord"]),printAlone(){var t;return Array.isArray((t=this.printRecord)==null?void 0:t.test_groups_all)?this.printRecord.test_groups_all.map(l=>{var r;return{name:l==null?void 0:l.name,category:l==null?void 0:l.category,testlength:(r=l==null?void 0:l.tests_print_alone)==null?void 0:r.length,tests_print_alone:l==null?void 0:l.tests_print_alone}}):[]},NotprintAlone(){var t;return Array.isArray((t=this.printRecord)==null?void 0:t.test_groups_all)?this.printRecord.test_groups_all.map(l=>{var r;return{name:l==null?void 0:l.name,category:l==null?void 0:l.category,testlength:((r=l==null?void 0:l.tests_not_print_alone)==null?void 0:r.length)||0,tests_not_print_alone:(l==null?void 0:l.tests_not_print_alone)||[]}}):[]},cultures(){var t,l;return(l=(t=this.printRecord)==null?void 0:t.test_groups_all)==null?void 0:l.map(r=>{var h;return{name:r==null?void 0:r.name,category:r==null?void 0:r.category,culturesLength:(h=r==null?void 0:r.cultures)==null?void 0:h.length,cultures:r==null?void 0:r.cultures}})}},methods:{}},Et={class:"report-container",id:"job"},Nt={key:0,class:"printPage"},Ot={class:"head"},Gt={class:"header-block"},Jt={class:"barcode-row"},qt={class:"barcode-box"},Qt={class:"barcode"},Yt={class:"barcode-box"},Kt={class:"barcode"},Xt={class:"header-row"},Zt={class:"header-row"},el={class:"header-row"},tl={class:"tests-section"},ll={key:0,class:"caption"},nl={key:1,class:"caption"},ol={class:"tests-table"};function sl(t,l,r,h,b,m){var p,k,c,g,V,T,M,S,A,L,u,R,n,_,a,W,O,X,E,Y,w,x,N,Z,oe;const f=U("BarcodeComponent");return s(),d("div",Et,[l[18]||(l[18]=e("br",null,null,-1)),((p=m.NotprintAlone)==null?void 0:p.length)>0?(s(),d("div",Nt,[e("div",Ot,[e("div",Gt,[e("div",Jt,[e("div",qt,[l[0]||(l[0]=e("strong",null,"Barcode:",-1)),e("div",Qt,[i(f,{value:(k=t.printRecord)==null?void 0:k.barcode},null,8,["value"]),e("span",null,o((c=t.printRecord)==null?void 0:c.barcode),1)])]),e("div",Yt,[l[1]||(l[1]=e("strong",null,"Patient Code:",-1)),e("div",Kt,[i(f,{value:(V=(g=t.printRecord)==null?void 0:g.patient)==null?void 0:V.code},null,8,["value"]),e("span",null,o((M=(T=t.printRecord)==null?void 0:T.patient)==null?void 0:M.code),1)])])]),l[11]||(l[11]=e("hr",{class:"barcode-divider"},null,-1)),e("div",Xt,[e("div",null,[l[2]||(l[2]=e("strong",null,"Patient Name:",-1)),F(" "+o((A=(S=t.printRecord)==null?void 0:S.patient)==null?void 0:A.name),1)]),e("div",null,[l[3]||(l[3]=e("strong",null,"Phone:",-1)),F(" "+o((u=(L=t.printRecord)==null?void 0:L.patient)==null?void 0:u.phone),1)]),e("div",null,[l[4]||(l[4]=e("strong",null,"Gender , Age:",-1)),F(" "+o((n=(R=t.printRecord)==null?void 0:R.patient)==null?void 0:n.gender)+" , "+o(((a=(_=t.printRecord)==null?void 0:_.patient)==null?void 0:a.age)+((O=(W=t.printRecord)==null?void 0:W.patient)==null?void 0:O.age_unit)),1)])]),e("div",Zt,[e("div",null,[l[5]||(l[5]=e("strong",null,"Registration Date:",-1)),F(" "+o(t.dateTimeFormat((X=t.printRecord)==null?void 0:X.registration_date)),1)]),e("div",null,[l[6]||(l[6]=e("strong",null,"Result Date:",-1)),F(" "+o(t.dateTimeFormat((E=t.printRecord)==null?void 0:E.result_date)),1)]),e("div",null,[l[7]||(l[7]=e("strong",null,"Referred By:",-1)),F(" "+o((w=(Y=t.printRecord)==null?void 0:Y.referral)==null?void 0:w.name),1)])]),e("div",el,[e("div",null,[l[8]||(l[8]=e("strong",null,"Total:",-1)),F(" "+o((x=t.printRecord)==null?void 0:x.total),1)]),e("div",null,[l[9]||(l[9]=e("strong",null,"Paid:",-1)),F(" "+o((N=t.printRecord)==null?void 0:N.paid),1)]),e("div",null,[l[10]||(l[10]=e("strong",null,"Due:",-1)),F(" "+o(((Z=t.printRecord)==null?void 0:Z.total)-((oe=t.printRecord)==null?void 0:oe.paid)),1)])])])]),(s(!0),d(I,null,C(m.NotprintAlone,(G,re)=>{var se,ae;return s(),d("section",{class:"test-details",key:re},[e("div",tl,[G.name?(s(),d("div",ll,o(G.name),1)):$("",!0),l[16]||(l[16]=e("br",null,null,-1)),G.category?(s(),d("div",nl,o(G.category),1)):$("",!0),e("table",ol,[l[15]||(l[15]=e("thead",null,[e("tr",null,[e("th",null,"Test name"),e("th",null,"Unit"),e("th",null,"Sample type"),e("th",null,"Result"),e("th",null,"Signature")])],-1)),e("tbody",null,[(s(!0),d(I,null,C(G==null?void 0:G.tests_not_print_alone,(D,K)=>(s(),d("tr",{key:K},[e("td",null,o(D==null?void 0:D.report_name),1),e("td",null,o(D==null?void 0:D.unit),1),e("td",null,o(D==null?void 0:D.sample_name),1),e("td",null,o(D==null?void 0:D.result),1),l[12]||(l[12]=e("td",null,null,-1))]))),128)),(s(!0),d(I,null,C((se=t.printRecord)==null?void 0:se.cultures,(D,K)=>(s(),d("tr",{key:K},[e("td",null,o(D==null?void 0:D.name),1),e("td",null,o(D==null?void 0:D.unit),1),e("td",null,o(D==null?void 0:D.sample_name),1),e("td",null,o(D==null?void 0:D.result),1),l[13]||(l[13]=e("td",null,null,-1))]))),128)),(s(!0),d(I,null,C((ae=t.printRecord)==null?void 0:ae.packages,(D,K)=>(s(),d("tr",{key:K},[e("td",null,o(D==null?void 0:D.name),1),e("td",null,o(D==null?void 0:D.unit),1),e("td",null,o(D==null?void 0:D.sample_name),1),e("td",null,o(D==null?void 0:D.result),1),l[14]||(l[14]=e("td",null,null,-1))]))),128))])])])])}),128)),l[17]||(l[17]=e("div",{class:"footer"},[e("div",null,"Receptionist"),e("div",null,"Sample receiver"),e("div",null,"Sample responsible")],-1))])):$("",!0)])}const al=B(Wt,[["render",sl],["__scopeId","data-v-eac73bfe"]]);const dl={computed:{...P(H,["printRecord","print_work_sheetDialog","selectedItems","isAllSelected"])},methods:{generateBarcodeImage(t){if(!t)return"";const l=document.createElement("canvas");return Ve(l,t,{format:"CODE128",displayValue:!0,width:1,height:15}),l.toDataURL("image/png")},checkAll(){var t,l,r;this.isAllSelected?this.selectedItems=[]:this.selectedItems=[...(t=this.printRecord)==null?void 0:t.tests,...(l=this.printRecord)==null?void 0:l.cultures,...(r=this.printRecord)==null?void 0:r.packages],this.isAllSelected=!this.isAllSelected},print(){setTimeout(function(){var b,m;const t=document.getElementById("worksheet").innerHTML;(b=document.head)!=null&&b.innerHTML||((m=document.getElementsByTagName("head")[0])==null||m.innerHTML);var l=`@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } } 
 
                               body {font-family: Arial, sans-serif;font-size: 14px;margin: 0;padding: 0; color: #000;}
                                  @page { size: A4;  margin: 20mm;}
                                .report-container {
      border: 1px solid black;
      padding: 20px;
      width: 80%;
      margin: auto;
    }
      .commit td{
      border:none !important;
      }
         .border{border: 1px solid black;   min-height: 25px;  margin: 0px 7px; padding: 5px;width: 150px;text-align: center;}
    .header-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid black;
      padding-bottom: 10px;
      margin-bottom: 10px;
    }
    
    .barcode {
      text-align: center;
    }
    .barcode img {
      height: 50px;
    }
    .details {
      text-align: left;
      font-size: 14px;
    }
    .details div {
      margin-bottom: 8px;
    }
    .test-section {
      margin-top: 20px;
      border-top: 1px solid black;
      padding-top: 10px;
    }
    .test-section h3 {
      background-color: #f0f0f0;
      padding: 5px;
      margin-bottom: 0;
      text-align: center;
    }
    .table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .table, .table th, .table td {
      border: 1px solid black;
    }
    th, td {
      text-align: center;
      padding: 8px;
    }
    .comment {
      font-weight: bold;
      padding-left: 10px;
    }     `;const r=document.createElement("iframe");r.style.position="absolute",r.style.width="0px",r.style.height="0px",r.style.border="none",document.body.appendChild(r);const h=r.contentWindow.document;h.open(),h.write(`
             <html>
                 <head>
                     <title>Print Job</title>
                     <style>${l}</style>
                 </head>
                 <body>${t}</body>
             </html>
         `),h.close(),r.contentWindow.focus(),r.contentWindow.print(),r.contentWindow.onafterprint=()=>{document.body.removeChild(r)}},50)},close(){this.print_work_sheetDialog=!1,this.selectedItems=[],this.isAllSelected=!1}},watch:{selectedItems(){var t,l,r;this.isAllSelected=this.selectedItems.length===((t=this.printRecord)==null?void 0:t.tests.length)+((l=this.printRecord)==null?void 0:l.cultures.length)+((r=this.printRecord)==null?void 0:r.packages.length)}}},il={class:"table table-bordered"},rl={style:{width:"87%","text-align":"-webkit-auto"}},ul={key:0,class:"pi pi-check-circle text-success"},pl={key:1,class:"pi pi-power-off text-danger"},cl=["value"],ml={key:0,class:"pi pi-check-circle text-success"},bl={key:1,class:"pi pi-power-off text-danger"},hl=["value"],gl={key:0,class:"pi pi-check-circle text-success"},yl={key:1,class:"pi pi-power-off text-danger"},fl=["value"],_l={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"},Vl={class:"report-container",id:"worksheet"},kl={class:"header-section"},wl={class:"details"},vl=["src"],Rl={class:"border"},Ul={class:"details"},$l={class:"border"},Il={class:"border"},Cl={class:"table"};function Dl(t,l,r,h,b,m){var k,c,g,V,T,M,S,A,L,u;const f=U("Button"),p=U("Dialog");return s(),d(I,null,[i(p,{visible:t.print_work_sheetDialog,"onUpdate:visible":l[6]||(l[6]=R=>t.print_work_sheetDialog=R),modal:"",header:t.t("print_work_sheet"),style:q([{width:"80rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>[e("div",null,[e("table",il,[e("thead",null,[e("tr",null,[e("th",rl,o(t.t("the_tests")),1),e("th",null,o(t.t("done")),1),e("th",null,[i(f,{size:"small",icon:t.isAllSelected?"pi pi-check-circle":"pi pi-circle",severity:"success",onClick:l[0]||(l[0]=R=>m.checkAll())},null,8,["icon"])])])]),e("tbody",null,[(s(!0),d(I,null,C(t.printRecord.tests,(R,n)=>(s(),d("tr",{key:n,class:j({"odd-row":n%2===0,"even-row":n%2!==0})},[e("td",{class:j({"text-danger":!R.is_done,"text-success":R.is_done}),style:{width:"87%","text-align":"-webkit-auto"}},o(R.name),3),e("td",null,[R.is_done?(s(),d("i",ul)):$("",!0),R.is_done?$("",!0):(s(),d("i",pl))]),e("td",null,[z(e("input",{type:"checkbox",value:R,"onUpdate:modelValue":l[1]||(l[1]=_=>t.selectedItems=_)},null,8,cl),[[ee,t.selectedItems]])])],2))),128)),(s(!0),d(I,null,C(t.printRecord.cultures,(R,n)=>(s(),d("tr",{key:n,class:j({"even-row":n%2===0,"odd-row":n%2!==0})},[e("td",{class:j({"text-danger":!R.is_done,"text-success":R.is_done}),style:{width:"87%","text-align":"-webkit-auto"}},o(R.name),3),e("td",null,[R.is_done?(s(),d("i",ml)):$("",!0),R.is_done?$("",!0):(s(),d("i",bl))]),e("td",null,[z(e("input",{type:"checkbox",value:R,"onUpdate:modelValue":l[2]||(l[2]=_=>t.selectedItems=_)},null,8,hl),[[ee,t.selectedItems]])])],2))),128)),(s(!0),d(I,null,C(t.printRecord.packages,(R,n)=>(s(),d("tr",{key:n,class:j({"odd-row":n%2===0,"even-row":n%2!==0})},[e("td",{class:j({"text-danger":!R.is_done,"text-success":R.is_done}),style:{width:"87%","text-align":"-webkit-auto"}},o(R.name),3),e("td",null,[R.is_done?(s(),d("i",gl)):$("",!0),R.is_done?$("",!0):(s(),d("i",yl))]),e("td",null,[z(e("input",{type:"checkbox",value:R,"onUpdate:modelValue":l[3]||(l[3]=_=>t.selectedItems=_)},null,8,fl),[[ee,t.selectedItems]])])],2))),128))])])]),e("div",_l,[i(f,{size:"small",label:t.t("close"),severity:"danger",onClick:l[4]||(l[4]=R=>m.close())},null,8,["label"]),i(f,{size:"small",label:t.t("print"),severity:"success",onClick:l[5]||(l[5]=R=>m.print()),disabled:t.selectedItems.length==0},null,8,["label","disabled"])])]),_:1},8,["visible","header","style"]),e("div",Vl,[l[17]||(l[17]=e("br",null,null,-1)),l[18]||(l[18]=e("br",null,null,-1)),l[19]||(l[19]=e("br",null,null,-1)),l[20]||(l[20]=e("br",null,null,-1)),l[21]||(l[21]=e("br",null,null,-1)),l[22]||(l[22]=e("br",null,null,-1)),e("div",kl,[e("div",wl,[e("table",null,[e("tr",null,[l[7]||(l[7]=e("th",null,[e("span",null,[e("strong",null,"Barcode")])],-1)),e("td",null,[e("span",null,[e("div",null,[e("span",null,[e("img",{src:m.generateBarcodeImage((k=t.printRecord)==null?void 0:k.barcode),alt:"Barcode"},null,8,vl)])])])])]),e("tr",null,[l[8]||(l[8]=e("th",null,[e("div",null,[e("strong",null,"age/ Sex")])],-1)),e("td",null,[e("div",Rl,[e("strong",null,o(((g=(c=t.printRecord)==null?void 0:c.patient)==null?void 0:g.age)+((T=(V=t.printRecord)==null?void 0:V.patient)==null?void 0:T.age_unit))+" / "+o((S=(M=t.printRecord)==null?void 0:M.patient)==null?void 0:S.gender),1)])])])])]),e("div",Ul,[e("table",null,[e("tr",null,[l[9]||(l[9]=e("th",null,[e("div",null,[e("strong",null,"patient name")])],-1)),e("td",null,[e("div",$l,[e("strong",null,o((L=(A=t.printRecord)==null?void 0:A.patient)==null?void 0:L.name),1)])])]),e("tr",null,[l[10]||(l[10]=e("th",null,[e("div",null,[e("strong",null,"Request Date")])],-1)),e("td",null,[e("div",Il,[e("strong",null,o(t.dateTimeFormat((u=t.printRecord)==null?void 0:u.registration_date)),1)])])])])])]),(s(!0),d(I,null,C(t.selectedItems,R=>(s(),d("div",{class:"test-section",key:R},[e("h3",null,o(R.name),1),e("table",Cl,[l[16]||(l[16]=e("thead",null,[e("tr",null,[e("th",null,"name"),e("th",null,"Result"),e("th",null,"Unit"),e("th",null,"tests reference ranges"),e("th",null,"the Status")])],-1)),e("tbody",null,[e("tr",null,[e("td",null,o(R.name),1),l[11]||(l[11]=e("td",null,null,-1)),l[12]||(l[12]=e("td",null,null,-1)),l[13]||(l[13]=e("td",null,null,-1)),l[14]||(l[14]=e("td",null,null,-1))]),l[15]||(l[15]=e("tr",{class:"commit"},[e("td",null,"Comment:"),e("td"),e("td"),e("td"),e("td")],-1))])])]))),128)),l[23]||(l[23]=e("br",null,null,-1)),l[24]||(l[24]=e("br",null,null,-1)),l[25]||(l[25]=e("br",null,null,-1)),l[26]||(l[26]=e("br",null,null,-1))])],64)}const xl=B(dl,[["render",Dl],["__scopeId","data-v-69c547a4"]]);const Tl={data(){return{attachments:[{name:"",file:null}],showAttributes:[],template:{},selectedTemplateId:null,selectedTemplate:null,templateFields:{}}},computed:{...P(de,["templates","record","dialog","pagination"]),...P(le,["resultStatus"]),...P(H,["updateResultRecord","updateResultModalDialog","testQuesions","cultursComment","testsComment","package_comment"])},mounted(){this.GetTemplates(),this.GetresultStatus()},methods:{...Q(le,["GetresultStatus"]),...Q(H,["updateResult","questions"]),...Q(de,["GetTemplates"]),injectTableStyles(){if(document.getElementById("custom-table-styles"))return;const t=document.createElement("style");t.id="custom-table-styles",t.innerHTML=`
                                   #custom-table-container table {
                                        width: 100%;
                                        border-collapse: collapse;
                                        margin-top: 10px;
                                        background: #fff;
                                   }
                                   #custom-table-container th, 
                                   #custom-table-container td {
                                        border: 1px solid #ccc;
                                        padding: 10px;
                                        text-align: center;
                                   }
                                   #custom-table-container th {
                                        background: #f8f9fa;
                                        font-weight: bold;
                                   }
                                   #custom-table-container .editable-field {
                                        width: 90%;
                                        padding: 5px;
                                        border: 1px solid #ddd;
                                        font-size: 14px;
                                        text-align: center;
                                        background-color: white;
                                        color: black;
                                   }
                              `,document.head.appendChild(t)},async loadTemplate(){try{const t=this.updateResultRecord.tests[0];let l=t.content.html||"<p>لا يوجد قالب</p>";Array.isArray(t.sub_tests)&&t.sub_tests.forEach((r,h)=>{const b=new RegExp(`{{sub_test\\.${r.name}\\.value}}`,"g");let m="";r.type===4&&Array.isArray(r.sup_test_reference_options)&&r.sup_test_reference_options.length>0?m=`
      <select 
        class="editable-field"
        data-key="sub_tests[${h}].value"
        data-test-id="${t.test_id_fk}">
        ${r.sup_test_reference_options.map(f=>`
          <option value="${f}" ${f===r.value?"selected":""}>
            ${f}
          </option>
        `).join("")}
      </select>
    `:m=`
      <input 
        type="text"
        class="editable-field"
        value="${r.value||""}" 
        data-key="sub_tests[${h}].value" 
        data-test-id="${t.test_id_fk}" />
    `,l=l.replace(b,m)}),this.selectedTemplate=`
      <div id="custom-table-container">
        ${l}
      </div>
    `,this.$nextTick(()=>{const r=this;document.querySelectorAll(".editable-field").forEach(h=>{h.addEventListener("input",b=>{const m=b.target.dataset.key,f=b.target.value,p=m.match(/sub_tests\[(\d+)\]\.(.+)/);if(p){const k=parseInt(p[1]),c=p[2],g=r.updateResultRecord.tests[0];g&&g.sub_tests[k]&&(g.sub_tests[k][c]=f)}})})}),this.injectTableStyles()}catch(t){console.error("❌ Error loading template:",t)}},getFilteredRanges(t){var b;const l=(b=this.updateResultRecord)==null?void 0:b.patient,r=(m,f)=>{switch(f){case"Days":return m;case"Months":return m*30;case"Years":return m*365;default:return m}},h=r(l.age,l.age_unit);return t.filter(m=>{const f=m.gender.toLowerCase()===l.gender.toLowerCase(),p=r(m.age_from,m.age_unit),k=r(m.age_to,m.age_unit),c=h>=p&&h<=k;return f&&c})},addAttachment(){this.attachments.push({name:"",file:null})},toggleAttributes(t){this.showAttributes[t]=!this.showAttributes[t]},removeAttachment(t){this.attachments.splice(t,1)},update(){this.updateResultRecord.attachments=this.attachments.map(t=>({name:t.name?t.name:null,file:t.file?t.file:null}))??[],this.updateResultRecord.tests=this.updateResultRecord.tests,this.updateResultRecord.cultures=this.updateResultRecord.cultures,this.updateResultRecord.packages=this.updateResultRecord.packages,this.updateResultRecord.test_groups=this.updateResultRecord.test_groups,this.updateResult().then(()=>{this.alertSuccess(this.t("alertSuccess")),this.clearObjectValues(this.updateResultRecord),this.testsComment=[],this.cultursComment=[],this.updateResultModalDialog=!1})},onFileChange(t,l){const r=t.files?t.files[0]:null;r&&(this.attachments[l].file=r)},close(){this.updateResultModalDialog=!1}},watch:{updateResultModalDialog(t){t&&(console.log("✅ Dialog Opened"),this.$nextTick(()=>{this.updateResultRecord.tests&&this.updateResultRecord.tests.length>0?(this.selectedTemplateId=this.updateResultRecord.tests[0].test_id_fk,console.log("🚀 Calling loadTemplate() with ID:",this.selectedTemplateId),this.loadTemplate()):console.warn("⚠️ updateResultRecord.tests is empty!")}))}}},Fl={class:"uploadFile"},Ml={class:"add d-flex"},Sl={class:"table"},Al={class:"p-2"},Ll={class:"details"},jl={key:0},zl={key:0,style:{direction:"ltr"}},Pl=["innerHTML"],Hl={key:1,class:"table"},Bl={key:0},Wl={key:1},El={key:1,class:"card flex justify-content-center mb-5"},Nl={style:{"margin-top":"40px"}},Ol={style:{color:"#004e54"}},Gl={class:"table"},Jl={class:"mb-2"},ql={key:1,class:"card flex justify-content-center mb-5"},Ql={style:{"margin-top":"40px"}},Yl={style:{color:"#004e54"}},Kl={class:"table"},Xl={key:1,class:"card flex justify-content-center mb-5"},Zl={class:"details"},en={class:"table"},tn=["onClick"],ln={key:0},nn={colspan:"9"},on={class:"table table-bordered attrTable"},sn={class:"mb-2"},an={key:1,class:"card flex justify-content-center mb-5"},dn={class:"details",style:{display:"none"}},rn={class:"table"},un={class:"mb-2"},pn={key:1,class:"card flex justify-content-center mb-5"},cn={class:"details",style:{display:"none"}},mn={class:"table"},bn={key:1,class:"card flex justify-content-center mb-5"},hn={class:"comment"},gn={for:""},yn={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function fn(t,l,r,h,b,m){const f=U("Button"),p=U("InputText"),k=U("FileUpload"),c=U("Checkbox"),g=U("Dropdown"),V=U("InputNumber"),T=U("MultiSelect"),M=U("TabPanel"),S=U("TabView"),A=U("InlineMessage"),L=U("Dialog");return s(),y(L,{visible:t.updateResultModalDialog,"onUpdate:visible":l[7]||(l[7]=u=>t.updateResultModalDialog=u),modal:"",header:t.t("updateResult"),style:q([{width:"100rem"},t.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:v(()=>[l[24]||(l[24]=e("hr",null,null,-1)),l[25]||(l[25]=e("br",null,null,-1)),e("div",Fl,[e("div",null,[e("div",Ml,[l[8]||(l[8]=e("h3",null,"رفع الملفات",-1)),i(f,{icon:"pi pi-plus",class:"p-button-rounded p-button-success p-button-text",onClick:m.addAttachment},null,8,["onClick"])]),e("table",Sl,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("file")),1),e("th",null,o(t.t("delete")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(b.attachments,(u,R)=>(s(),d("tr",{key:R},[e("td",Al,[i(p,{class:"w-full",type:"text",modelValue:u.name,"onUpdate:modelValue":n=>u.name=n},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(k,{class:"w-full",required:"",mode:"basic",onSelect:n=>m.onFileChange(n,R),chooseLabel:"ملف"},null,8,["onSelect"])]),e("td",null,[i(f,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text",onClick:n=>m.removeAttachment(R)},null,8,["onClick"])])]))),128))])])])]),l[26]||(l[26]=e("br",null,null,-1)),l[27]||(l[27]=e("hr",null,null,-1)),e("div",Ll,[e("h1",null,o(t.t("tests")),1),t.updateResultRecord.tests.length>0?(s(),y(S,{key:0},{default:v(()=>[(s(!0),d(I,null,C(t.updateResultRecord.tests,(u,R)=>(s(),y(M,{header:u.name,key:R},{default:v(()=>{var n;return[e("div",null,[((n=u.sub_tests)==null?void 0:n.length)>0?(s(),d("div",jl,[b.selectedTemplate?(s(),d("div",zl,[e("div",{innerHTML:b.selectedTemplate,class:"dynamic-template"},null,8,Pl)])):$("",!0)])):(s(),d("table",Hl,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("done")),1),e("th",null,o(t.t("tests-reference-ranges")),1),e("th",null,o(t.t("Unit")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("last_result")),1),e("th",null,o(t.t("Test_Group_Comment")),1),e("th",null,o(t.t("Original_Price")),1),e("th",null,o(t.t("name")),1)])]),e("tbody",null,[e("tr",null,[e("td",null,[i(c,{modelValue:u.is_done,"onUpdate:modelValue":_=>u.is_done=_,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[(s(!0),d(I,null,C(m.getFilteredRanges(u.test_reference_ranges),_=>(s(),d("span",{key:_.id},[e("p",null,[_.notes?(s(),d("div",Bl,[(s(!0),d(I,null,C(_.notes.split(`
`),(a,W)=>(s(),d("p",{key:W},o(a),1))),128))])):(s(),d("p",Wl,o(_.from+"-"+_.to),1))]),(s(!0),d(I,null,C(_.test_reference_options,a=>(s(),d("p",{key:a},[e("span",null,o(a),1)]))),128))]))),128))]),e("td",null,o(u.unit??"---"),1),e("td",null,[i(g,{modelValue:u.result_status_id_fk,"onUpdate:modelValue":_=>u.result_status_id_fk=_,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[u.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:u.result,"onUpdate:modelValue":_=>u.result=_},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),u.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:u.result,"onUpdate:modelValue":_=>u.result=_},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),u.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:u.result,"onUpdate:modelValue":_=>u.result=_},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),u.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:u.result,"onUpdate:modelValue":_=>u.result=_,options:u.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):u.result_type_id_fk==null||u.result_type_id_fk==""?(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:u.result,"onUpdate:modelValue":_=>u.result=_},null,8,["modelValue","onUpdate:modelValue"])):$("",!0)]),e("td",null,[i(c,{modelValue:u.last_result,"onUpdate:modelValue":_=>u.last_result=_,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:u.comment,"onUpdate:modelValue":_=>u.comment=_},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,o(u.price??0),1),e("td",null,o(u.name??"---"),1)])])])),l[9]||(l[9]=e("br",null,null,-1)),e("div",null,[e("label",null,o(t.t("Result_Comments")),1),e("span",null,[i(T,{modelValue:t.updateResultRecord.tests_comment,"onUpdate:modelValue":l[0]||(l[0]=_=>t.updateResultRecord.tests_comment=_),options:t.updateResultRecord.result_comments_tests,class:"w-full mt-2"},null,8,["modelValue","options"])])]),l[10]||(l[10]=e("br",null,null,-1)),l[11]||(l[11]=e("hr",null,null,-1)),l[12]||(l[12]=e("br",null,null,-1))])]}),_:2},1032,["header"]))),128))]),_:1})):(s(),d("div",El,[i(A,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})])),e("div",Nl,[l[15]||(l[15]=e("hr",null,null,-1)),e("h2",Ol,o(t.t("packages")),1),t.updateResultRecord.packages.length>0?(s(),y(S,{key:0},{default:v(()=>[(s(!0),d(I,null,C(t.updateResultRecord.packages,(u,R)=>(s(),y(M,{header:u.name,key:R},{default:v(()=>[e("table",Gl,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Original_Price")),1),e("th",null,o(t.t("Test_Group_Comment")),1),e("th",null,o(t.t("last_result")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("done")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(u.tests,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128)),(s(!0),d(I,null,C(u.cultures,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(c,{modelValue:n.last_result,"onUpdate:modelValue":a=>n.last_result=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128))])]),l[13]||(l[13]=e("br",null,null,-1)),e("div",null,[e("label",Jl,o(t.t("Result_Comments")),1),e("span",null,[i(T,{modelValue:t.updateResultRecord.package_comment,"onUpdate:modelValue":l[1]||(l[1]=n=>t.updateResultRecord.package_comment=n),options:t.updateResultRecord.result_package_comments,class:"w-full mt-2"},null,8,["modelValue","options"])])]),l[14]||(l[14]=e("br",null,null,-1))]),_:2},1032,["header"]))),128))]),_:1})):(s(),d("div",ql,[i(A,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),e("div",Ql,[l[17]||(l[17]=e("hr",null,null,-1)),e("h2",Yl,o(t.t("test-groups")),1),t.updateResultRecord.test_groups.length>0?(s(),y(S,{key:0},{default:v(()=>[(s(!0),d(I,null,C(t.updateResultRecord.test_groups,(u,R)=>(s(),y(M,{header:u.group_name,key:R},{default:v(()=>[e("table",Kl,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Original_Price")),1),e("th",null,o(t.t("Test_Group_Comment")),1),e("th",null,o(t.t("last_result")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("done")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(u.tests,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(c,{modelValue:n.last_result,"onUpdate:modelValue":a=>n.last_result=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128))])]),l[16]||(l[16]=e("br",null,null,-1))]),_:2},1032,["header"]))),128))]),_:1})):(s(),d("div",Xl,[i(A,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))])]),l[28]||(l[28]=e("br",null,null,-1)),e("div",Zl,[e("h1",null,o(t.t("cultures")),1),t.updateResultRecord.cultures.length>0?(s(),y(S,{key:0},{default:v(()=>[(s(!0),d(I,null,C(t.updateResultRecord.cultures,(u,R)=>(s(),y(M,{header:u.name,key:R},{default:v(()=>[e("table",en,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Original_Price")),1),e("th",null,o(t.t("Test_Group_Comment")),1),e("th",null,o(t.t("last_result")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("done")),1),e("th",null,o(t.t("Show_Attributes")),1)])]),e("tbody",null,[e("tr",null,[e("td",null,o(u.name),1),e("td",null,o(u.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:u.comment,"onUpdate:modelValue":n=>u.comment=n},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(c,{modelValue:u.last_result,"onUpdate:modelValue":n=>u.last_result=n,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[u.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:u.result,"onUpdate:modelValue":n=>u.result=n},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),u.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:u.result,"onUpdate:modelValue":n=>u.result=n},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),u.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:u.result,"onUpdate:modelValue":n=>u.result=n},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),u.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:u.result,"onUpdate:modelValue":n=>u.result=n,options:u.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:u.result,"onUpdate:modelValue":n=>u.result=n},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:u.result_status_id_fk,"onUpdate:modelValue":n=>u.result_status_id_fk=n,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:u.is_done,"onUpdate:modelValue":n=>u.is_done=n,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[e("i",{class:j(b.showAttributes[R]?"pi pi-angle-up":"pi pi-angle-down"),style:{color:"slateblue","font-weight":"bold"},onClick:n=>m.toggleAttributes(R)},null,10,tn)])]),b.showAttributes[R]?(s(),d("tr",ln,[e("td",nn,[e("table",on,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Order")),1),e("th",null,o(t.t("Result")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(u.attribute,n=>(s(),d("tr",{key:n},[e("td",null,o(n.name),1),e("td",null,o(n.order),1),e("td",null,[n.result_type_id_fk==1?(s(),y(p,{key:0,class:"w-full",required:"",type:"number",modelValue:n.result,"onUpdate:modelValue":_=>n.result=_},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:1,class:"w-full",required:"",type:"text",modelValue:n.result,"onUpdate:modelValue":_=>n.result=_},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:2,modelValue:n.result,"onUpdate:modelValue":_=>n.result=_,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:3,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":_=>n.result=_},null,8,["modelValue","onUpdate:modelValue"]))])]))),128))])])])])):$("",!0)])]),l[18]||(l[18]=e("br",null,null,-1)),e("div",null,[e("label",sn,o(t.t("Result_Comments")),1),e("span",null,[i(T,{modelValue:t.updateResultRecord.cultures_comment,"onUpdate:modelValue":l[2]||(l[2]=n=>t.updateResultRecord.cultures_comment=n),options:t.updateResultRecord.result_comments_cultures,class:"w-full mt-2"},null,8,["modelValue","options"])])]),l[19]||(l[19]=e("br",null,null,-1))]),_:2},1032,["header"]))),128))]),_:1})):(s(),d("div",an,[i(A,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),l[29]||(l[29]=e("br",null,null,-1)),e("div",dn,[e("h1",null,o(t.t("packages")),1),t.updateResultRecord.packages.length>0?(s(),y(S,{key:0},{default:v(()=>[(s(!0),d(I,null,C(t.updateResultRecord.packages,(u,R)=>(s(),y(M,{header:u.name,key:R},{default:v(()=>[e("table",rn,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Original_Price")),1),e("th",null,o(t.t("Test_Group_Comment")),1),e("th",null,o(t.t("last_result")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("done")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(u.tests,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128)),(s(!0),d(I,null,C(u.cultures,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(c,{modelValue:n.last_result,"onUpdate:modelValue":a=>n.last_result=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128))])]),l[20]||(l[20]=e("br",null,null,-1)),e("div",null,[e("label",un,o(t.t("Result_Comments")),1),e("span",null,[i(T,{modelValue:t.updateResultRecord.package_comment,"onUpdate:modelValue":l[3]||(l[3]=n=>t.updateResultRecord.package_comment=n),options:t.updateResultRecord.result_package_comments,class:"w-full mt-2"},null,8,["modelValue","options"])])]),l[21]||(l[21]=e("br",null,null,-1))]),_:2},1032,["header"]))),128))]),_:1})):(s(),d("div",pn,[i(A,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),l[30]||(l[30]=e("br",null,null,-1)),e("div",cn,[e("h1",null,o(t.t("test-groups")),1),t.updateResultRecord.test_groups.length>0?(s(),y(S,{key:0},{default:v(()=>[(s(!0),d(I,null,C(t.updateResultRecord.test_groups,(u,R)=>(s(),y(M,{header:u.group_name,key:R},{default:v(()=>[e("table",mn,[e("thead",null,[e("tr",null,[e("th",null,o(t.t("name")),1),e("th",null,o(t.t("Original_Price")),1),e("th",null,o(t.t("Test_Group_Comment")),1),e("th",null,o(t.t("last_result")),1),e("th",null,o(t.t("Result")),1),e("th",null,o(t.t("Result_Type")),1),e("th",null,o(t.t("done")),1)])]),e("tbody",null,[(s(!0),d(I,null,C(u.tests,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[i(c,{modelValue:n.last_result,"onUpdate:modelValue":a=>n.last_result=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128)),(s(!0),d(I,null,C(u.cultures,(n,_)=>(s(),d("tr",{key:_},[e("td",null,o(n.name),1),e("td",null,o(n.price??0),1),e("td",null,[i(p,{class:"w-full",type:"text",modelValue:n.comment,"onUpdate:modelValue":a=>n.comment=a},null,8,["modelValue","onUpdate:modelValue"])]),e("td",null,[n.result_type_id_fk==1?(s(),y(V,{key:0,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==2?(s(),y(V,{key:1,class:"w-full",type:"number",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==3?(s(),y(p,{key:2,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"])):$("",!0),n.result_type_id_fk==4?(s(),y(g,{key:3,modelValue:n.result,"onUpdate:modelValue":a=>n.result=a,options:n.selection_type_options,placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):(s(),y(p,{key:4,class:"w-full",type:"text",modelValue:n.result,"onUpdate:modelValue":a=>n.result=a},null,8,["modelValue","onUpdate:modelValue"]))]),e("td",null,[i(g,{modelValue:n.result_status_id_fk,"onUpdate:modelValue":a=>n.result_status_id_fk=a,options:t.resultStatus,optionLabel:"label",optionValue:"value",placeholder:t.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),e("td",null,[i(c,{modelValue:n.is_done,"onUpdate:modelValue":a=>n.is_done=a,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])])]))),128))])]),l[22]||(l[22]=e("br",null,null,-1)),l[23]||(l[23]=e("br",null,null,-1))]),_:2},1032,["header"]))),128))]),_:1})):(s(),d("div",bn,[i(A,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})]))]),l[31]||(l[31]=e("br",null,null,-1)),e("div",hn,[e("label",gn,o(t.t("resultComment")),1),i(p,{class:"w-full mt-3",type:"text",modelValue:t.updateResultRecord.comments,"onUpdate:modelValue":l[4]||(l[4]=u=>t.updateResultRecord.comments=u)},null,8,["modelValue"])]),e("div",yn,[i(f,{size:"small",label:t.t("close"),severity:"danger",onClick:l[5]||(l[5]=u=>m.close())},null,8,["label"]),i(f,{size:"small",label:t.t("update"),severity:"success",onClick:l[6]||(l[6]=u=>m.update())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const _n=B(Tl,[["render",fn],["__scopeId","data-v-bffce25c"]]);const Vn={data(){return{marginDialogVisible:!1,Filters:{registration_date:"",from_lab:"",contract_id_fk:"",created_by:"",barcode:"",signed_by:"",patient_name:"",lab:""},globalFilter:"",menuRefs:{},currentRecordId:null,patientImages:JSON.parse(localStorage.getItem("patientImages")||"{}")}},components:{whatsUp:Ct,PrintMarginDialog:At,pationtHistoryModal:Ee,patientModal:rt,job_orderModal:al,DueModal:gt,printResult:ie,parcodModal:Rt,workSheetModal:xl,updateResultModal:_n,BarcodeComponent:ne,attachment:Bt,Menu:be},mounted(){this.pagination.current_page=1,this.patientId=this.$route.params.patientId,this.patientId?(this.patient_medical_records(this.patientId),localStorage.removeItem("token"),localStorage.removeItem("user")):this.Getinvoices()},computed:{...P(_e,["contracts"]),...P(ye,["havePermission"]),...P(H,["invoices","attachments","AttachDialog","Pdfurl","pagination","record","pationtHistoryDialog","patientdialog","patient","Patient_due","Patient_dueDialog","discountPercentage","discountValue","testQuesions","tests_ids","payment_details","selectedContract","selectedTests","selectedPackages","selectedCultures","selectedreferal","patient","selectedCollector","printRecord","WhatsUpDialog","parcodeDialog","print_work_sheetDialog","selectedItems","isAllSelected","updateResultModalDialog","updateResultRecord"]),filteredRecords(){return this.invoices.filter(t=>{var h,b,m,f,p,k,c,g,V,T,M,S,A,L,u,R,n,_,a,W,O;const l=this.globalFilter?((h=t.barcode)==null?void 0:h.toLowerCase().includes(this.globalFilter.toLowerCase()))||((b=t.from_lab)==null?void 0:b.toLowerCase().includes(this.globalFilter.toLowerCase()))||((f=(m=t.contract)==null?void 0:m.name)==null?void 0:f.toLowerCase().includes(this.globalFilter.toLowerCase()))||((k=(p=t.created_by)==null?void 0:p.name)==null?void 0:k.toLowerCase().includes(this.globalFilter.toLowerCase()))||((g=(c=t.signed_by)==null?void 0:c.name)==null?void 0:g.toLowerCase().includes(this.globalFilter.toLowerCase()))||((T=(V=t.patient)==null?void 0:V.name)==null?void 0:T.toLowerCase().includes(this.globalFilter.toLowerCase()))||((S=(M=t.patient)==null?void 0:M.lab)==null?void 0:S.toLowerCase().includes(this.globalFilter.toLowerCase())):!0,r=(!this.Filters.registration_date||this.isSameDate(t.registration_date,this.Filters.registration_date))&&(!this.Filters.lab||this.isSameDate(t.lab,this.Filters.lab))&&(!this.Filters.created_by||((L=(A=t.created_by)==null?void 0:A.name)==null?void 0:L.toLowerCase().includes(this.Filters.created_by.toLowerCase())))&&(!this.Filters.signed_by||((R=(u=t.signed_by)==null?void 0:u.name)==null?void 0:R.toLowerCase().includes(this.Filters.signed_by.toLowerCase())))&&(!this.Filters.contract_id_fk||((n=t.contract)==null?void 0:n.id)===this.Filters.contract_id_fk)&&(!this.Filters.from_lab||((_=t.from_lab)==null?void 0:_.toLowerCase().includes(this.Filters.from_lab.toLowerCase())))&&(!this.Filters.barcode||((a=t.barcode)==null?void 0:a.toLowerCase().includes(this.Filters.barcode.toLowerCase())))&&(!this.Filters.patient_name||((O=(W=t.patient)==null?void 0:W.name)==null?void 0:O.toLowerCase().includes(this.Filters.patient_name.toLowerCase())));return l&&r})}},methods:{...Q(H,["Getinvoices","patient_medical_records","patientHistory","sign","pdf","getsamples","changeInvoiceStatus"]),onPageChange(t){this.pagination.current_page=t.page+1,this.Getinvoices()},PDF(t){this.printRecord=t,setTimeout(function(){const l=document.getElementById("Result").innerHTML,r={margin:1,filename:`invoice-${this.invoiceNumber}.pdf`,image:{type:"jpeg",quality:.98},html2canvas:{scale:2},jsPDF:{unit:"in",format:"letter",orientation:"portrait"}};ke().from(l).set(r).save()},50)},showAttach(t){this.attachments=t,this.AttachDialog=!0},setMenuRef(t,l){t&&(this.menuRefs[l]=t)},toggleMenu(t,l){const r=this.menuRefs[l];r&&r.toggle(t)},getMenuItems(t){const l=[];return l.push({label:this.t("Patient_details"),icon:"pi pi-eye",command:()=>this.showPatient(t==null?void 0:t.patient)}),l.push({label:t.is_done?this.t("done"):this.t("pendening"),icon:t.is_done?"pi pi-check":"pi pi-clock",command:()=>this.showPatient_due(t),class:t.is_done?"text-success":"text-warning"}),this.patientId||l.push({label:this.t("job_order"),icon:"pi pi-book",command:()=>this.openJobTemplateAsPDF(t)}),l.length>0&&l.push({separator:!0}),this.patientId||l.push({label:this.t("Signature"),icon:t.is_signed?"pi pi-wave-pulse":"pi pi-exclamation-circle",command:()=>this.signInvoice(t.id),class:t.is_signed?"text-success":""}),l.push({label:this.t("attachments"),icon:"pi pi-file",command:()=>this.showAttach(t==null?void 0:t.attachments)}),this.patientId||l.push({label:this.t("print_work_sheet"),icon:"pi pi-print",command:()=>this.print_work_sheet(t)}),l.push({separator:!0}),l.push({label:"صورة الهيدر",icon:"pi pi-image",command:()=>this.openHeaderImageUpload(t.id)}),l.push({label:"صورة الفوتر",icon:"pi pi-image",command:()=>this.openFooterImageUpload(t.id)}),l},showPatient(t){this.patient=t,this.patientdialog=!0},signInvoice(t){this.sign(t).then(l=>{this.alertSuccess(this.t("alertSuccess"))})},updateResult(t){this.updateResultRecord=t,this.updateResultModalDialog=!0},showPatient_due(t){this.record=t,this.Patient_dueDialog=!0},deleteRecord(t){he(this.t("AlertWithConfirm")).then(l=>{l.value&&(this.record.id=t.id,this.Removeinvoices())})},clearFilters(){this.Filters={registration_date:"",from_lab:"",contract_id_fk:"",created_by:"",barcode:"",signed_by:"",lab:""},this.globalFilter=""},exportToExcel(){const t=te.json_to_sheet(this.filteredRecords,{header:["index","registration_date","from_lab","contract_id_fk","created_by","barcode","signed_by","lab"]}),l=te.book_new();te.book_append_sheet(l,t,"Sheet1"),we(l,"export.xlsx")},PationtHistory(t){this.patientHistory(t.patient.id).then(l=>{this.pationtHistoryDialog=!0})},addRecord(){this.selectedContract=[],this.selectedreferal=[],this.selectedTests=[],this.selectedPackages=[],this.selectedCultures=[],this.testQuesions=[],this.payment_details=[{amount:null,contract_id_fk:null,payment_method_id_fk:null}],this.patient=[],this.discountPercentage=0,this.discountValue=0,this.clearObjectValues(this.record),this.dialog=!0},openJobTemplateAsPDF(t){this.printRecord=t,setTimeout(function(){const l=document.getElementById("job").innerHTML,r=`
                  @page { size: portrait; margin: 0 !important; }
                  @media print {
                      body, .page {
                          margin: 0px !important;
                          box-shadow: 0;
                          -webkit-print-color-adjust: exact;
                          color: #000;

                  } body {font-family: "Tajawal",    text-transform: capitalize; sans-serif ;font-size: 14px;margin: 0;padding: 0; color: #000;}
                                            @page { size: A4;  margin: 20mm;}
                                           h1 { font-size: 24px; }
                                           p { font-size: 14px; }
                                           .report-container {
              font-family: Arial, sans-serif;
              width: 100%;
              margin: 0 auto;
          }
 .head {
  margin-bottom: 20px;
}

.header-block {
  border: 1px solid #ccc;
  padding: 15px;
  border-radius: 6px;
  background-color: #fdfdfd;
}

.barcode-row {
  display: flex;
  justify-content: space-around;
  margin-bottom: 10px;
}

.barcode-box {
  text-align: center;
  font-size: 13px;
}

.barcode {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 5px;
  font-size: 12px;
}

.barcode-divider {
  margin-top: 10px;
  margin-bottom: 15px;
  border: none;
  border-top: 1px dashed #bbb;
}

.header-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 10px;
}

.header-row > div {
  flex: 1 1 22%;
  font-size: 13px;
}

.caption {
  background: #eaeaea;
  padding: 6px;
  font-weight: bold;
  text-align: center;
  border-radius: 4px;
  margin-bottom: 10px;
  color: #333;
}

.tests-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}

.tests-table th,
.tests-table td {
  border: 1px solid #ccc;
  padding: 6px 8px;
  font-size: 13px;
  text-align: center;
}

.tests-table th {
  background-color: #f0f0f0;
}

.footer {
  margin-top: 30px;
  display: flex;
  justify-content: space-around;
  font-weight: bold;
  font-size: 14px;
  padding-top: 10px;
  border-top: 1px solid #ccc;
}

                                   `,h=document.createElement("iframe");h.style.position="absolute",h.style.width="0px",h.style.height="0px",h.style.border="none",document.body.appendChild(h);const b=h.contentWindow.document;b.open(),b.write(`
                  <html>
                      <head>
                          <title>Print Job</title>
                          <style>${r}</style>
                      </head>
                      <body>${l}</body>
                  </html>
              `),b.close(),h.contentWindow.focus(),h.contentWindow.print(),h.contentWindow.onafterprint=()=>{document.body.removeChild(h)}},50)},openprintResultTemplate(t){this.printRecord=[],this.printRecord=t,Object.assign(this.printRecord,t),setTimeout(function(){const l=document.getElementById("Result").innerHTML,r=JSON.parse(localStorage.getItem("printMargins")||"{}"),h=r.top??20,b=r.bottom??20,m=r.left??15,f=r.right??15;var p=`@page { size: portrait;   margin: ${h}mm ${f}mm ${b}mm ${m}mm !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    };

                                                          /* Add your styles here to format the PDF */
                                                        body {font-family: Arial, sans-serif;font-size: 14px;margin: 0;padding: 0; color: #000;}
                                                           @page { size: A4;  margin: 20mm;}
                                                         /* Basic reset */
                         * {
                             margin: 0;
                             padding: 0;
                             box-sizing: border-box;
                             font-family: Arial, sans-serif;
                         }
                            body {text-transform: capitalize;
               font-family: Arial, sans-serif;
               font-size: 14px;
               margin: 0;
               padding: 0;
               color: #000;
               font-weight: bold;
               direction: ltr;
          }

          /* Basic reset */
          * {
               margin: 0;
               padding: 0;
               box-sizing: border-box;
               font-family: Arial, sans-serif;
          }
          .sign {
               width: 100%;
               display: flex;
               justify-content: center;
               margin: 9px 0;
          }
          /* Container styling */
          .container {
               width: 100%;
               max-width: 800px;
               margin: 10 auto;
               padding: 20px;
               background-color: white;
               border: 1px solid #ddd;
               direction: ltr;
               font-weight: bold;
          }
          .caption {
               width: 100%;
               font-weight: bold;
               border: 1px solid black;
               padding: 5px;
               text-align: center;
               color: black;border-radius: 5px;
               background: #dddddce0;
          }
          /* Header section */
          .header {
               display: flex;
               justify-content: space-between;
               align-items: center;
               border-bottom: 1px solid #ccc;
               padding-bottom: 10px;
          }
          .section table tr {
               margin-bottom: 1px;

          }
            tr,th{font-size: 12px !important;}
          .sections {
               border-top: 1px solid #000;
               border-bottom: 1px solid #000;

          }
          .test-table {
               width: 100%;
               border-collapse: collapse;
               margin: 20px 0;
          }

          .test-table th,
          .test-table td {
               border: 1px solid rgba(0, 0, 0, 0.158);
               padding: 10px;
               text-align: center;

          }

          .test-table th {
               background-color: transparent;
          }
          .summary {
               padding: 16px;
          }
          .logo img {
               max-width: 150px;
          }

          .lab-details {
               text-align: right;
          }

          .lab-details h2 {
               font-size: 18px;
               margin-bottom: 5px;
          }

          .lab-details p {
               font-size: 14px;
               margin-bottom: 2px;
          }
          .section {
               display: flex;
               flex-direction: column;
               justify-content: space-between;
          }
          .sectionItem {
               display: flex;
          }
          .border {
               border: 1px solid black;
               min-height: 25px;
               margin: 0px 7px;
               padding: 5px;
               width: 150px;
               text-align: center;
          }
          /* Patient information section */
          .patient-info {
               display: flex;
               justify-content: space-between;
               padding: 15px 0;
               border-bottom: 1px solid #ccc;
          }

          .barcode-section {
               text-align: center;
               display: flex;
          }

          .barcode-section img {
               max-width: 100px;
               margin: 10px 0;
          }

          .patient-details {
               display: flex;
               flex-direction: column;
               justify-content: space-between;
               font-size: 14px;
          }

          /* Test details section */
          .test-details {
               padding: 15px 0;
               border-bottom: 1px solid #ccc;
               font-size: 16px;
               text-align: center;
          }

          /* Pricing information section */
          .pricing-info table {
               width: 100%;
               margin-top: 20px;
               border-collapse: collapse;
               font-size: 14px;
          }

          .pricing-info th,
          .pricing-info td {
               border: 1px solid #ddd;
               padding: 8px;
               text-align: center;
          }

          .pricing-info th {
               background-color: #f9f9f9;
               font-weight: bold;
          }

          /* For printing */
          @media print {
          .sections th,.sections tr{font-size:1px}
               body {
                    margin: 0;
                    padding: 0;
               }

               .container {
                    width: 100%;
                    max-width: 100%;
                    border: none;
                    box-shadow: none;
                    padding:10px;
                    margin:10px;
               }

               .header,
               .patient-info,
               .test-details,
               .pricing-info {
                    page-break-inside: avoid;
               }
               @media print {
                    .page-break {
                       margin-top: 200px;
                        margin-bottom: 200px;
                         page-break-before: always;
                    }
               }
          }
          .page-break {
               page-break-before: always;
          }   .caption {
               width: 100%;
               font-weight: bold;
               border: 1px solid black;
               padding: 5px;border-radius: 5px;
               text-align: center;
               color: black;}

          body {
               margin: 0;
               padding: 0;
          }
                             }`;const k=document.createElement("iframe");k.style.position="absolute",k.style.width="0px",k.style.height="0px",k.style.border="none",document.body.appendChild(k);const c=k.contentWindow.document;c.open(),c.write(`
                                      <html>
                                          <head>
                                              <title>Print Job</title>
                                              <style>${p}  .page-break {
               page-break-before: always;
          }  </style>
                                          </head>
                                          <body>${l}</body>
                                      </html>
                                  `),c.close(),k.contentWindow.focus(),k.contentWindow.print(),k.contentWindow.onafterprint=()=>{document.body.removeChild(k)}},50)},printParcode(t){this.printRecord=t,this.getsamples(t.id).then(l=>{setTimeout(function(){const r=document.getElementById("parcode").innerHTML;var h=`
                                        @page { size: 25mm 8mm;  margin: 0 !important;}
                                        @media print {
                                             body,  .page {   margin: 0px !important;   box-shadow: 0;  text-transform: capitalize;
                                        //    -webkit-print-color-adjust: exact;       color: #000;    } } ;
                                        //         @page {  size: 25mm 8mm;   margin: 0;      }
                                        //             body {margin: 0; padding: 0; font-family: Arial, sans-serif;  color: #000;      }
                                        //             .page-break {     display: block;     page-break-before: always; }
                                        //    .label-container { width:25mm;  height:8mm;
                                        //    text-align: center;
                                        //     border: 1px solid #000;

                                        //      }
                                        @page {
                                     size: 25mm 8mm;
                                     margin: 0;
                                   }

                                   @media print {
                                     body {
                                       margin: 0;
                                       padding: 0;
                                       box-sizing: border-box;
                                     }

                                     .container {
                                       width: 20mm;
                                       height: 8mm;
                                       display: flex;
                                       flex-direction: column;
                                       align-items: center;
                                       justify-content: center;
                                       line-height: 0.1;
                                       margin: 0;
                                       padding:0;
                                       overflow: hidden; /* Ensure no content overflows */
                                       page-break-inside: avoid; /* Avoid breaking the container */
                                     }

                                     .label-container {
                                       width: 100%;
                                       height: 100%;
                                       display: flex;
                                       flex-direction: column;
                                       align-items: center;
                                       justify-content: center;
                                       overflow: hidden; /* Ensure no content overflows */
                                     }

                                     .details,
                                     .barcode,
                                     .text-center,
                                     .test-list {
                                       text-align: center;
                                       margin: 0;
                                       padding:0;
                                    font-size: 10px; /* زوّد أو قلل حسب حجم الـ SVG */
                                   font-weight: bold; /* يعطي وضوح أفضل */

                                     }
                                      svg {
                                        width: 100% !important;
                                        height: 40px !important; /* غير القيمة حسب ما تحب */
                                        }
                                     .page-break {
                                       page-break-before: always;
                                     }
                                   }

                                                                         `;const b=document.createElement("iframe");b.style.position="absolute",document.body.appendChild(b);const m=b.contentWindow.document;m.open(),m.write(`
                                           <html>
                                               <head>
                                                   <title>Print Job</title>
                                                   <style>${h}</style>
                                               </head>
                                               <body>${r}</body>
                                           </html>
                                       `),document.innerHTML=r,m.close(),b.contentWindow.focus(),b.contentWindow.print(),b.contentWindow.onafterprint=()=>{document.body.removeChild(b)}},50)})},parcodeDialog(t){this.record=t,this.parcodeDialog=!0},print_work_sheet(t){this.printRecord=t,this.print_work_sheetDialog=!0,this.selectedItems=[],this.isAllSelected=!1},async sendWhatsUp(t){var p,k,c,g;Object.assign(this.printRecord,t);const l=this.User.name,r=(k=(p=this.printRecord)==null?void 0:p.patient)==null?void 0:k.name,h=`${window.location.origin}/result/${t.id}`,b=`اهلا بكم في مختبر ${l}  عزيزي  ${r} يمكنك الحصول على النتيجة من خلال الضغط على الرابط ادناه ${h}`,f=`https://wa.me/+964${(g=(c=this.printRecord)==null?void 0:c.patient)==null?void 0:g.phone}?text=${b}`;await window.open(f,"_blank"),this.changeInvoiceStatus(t.id)},printPDFForWhatsApp(t){this.printRecord=[],this.printRecord=t,Object.assign(this.printRecord,t);const l=this;setTimeout(function(){console.log("🔍 Current record ID:",t.id),console.log("📦 All patient images:",l.patientImages);let r=document.getElementById("Result").innerHTML;const h=l.patientImages[t.id]||{};if(console.log("🖼️ Patient images for this record:",h),console.log("📸 Header image exists:",!!h.header),console.log("📸 Footer image exists:",!!h.footer),h.header?(console.log("✅ Adding header image..."),r=`<div style="text-align: center; margin-bottom: 20px; page-break-inside: avoid;">
                              <img src="${h.header}" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" />
                         </div>`+r):console.log("⚠️ No header image found for this patient"),h.footer){console.log("✅ Adding footer image...");const T=`<div style="position: fixed; bottom: 0; left: 0; right: 0; text-align: center; page-break-inside: avoid;">
                              <img src="${h.footer}" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" />
                         </div>`;r=r+T}else console.log("⚠️ No footer image found for this patient");const b=JSON.parse(localStorage.getItem("printMargins")||"{}"),m=b.top??20,f=b.bottom??20,p=b.left??15,k=b.right??15;var c=`@page { size: portrait;   margin: ${m}mm ${k}mm ${f}mm ${p}mm !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    };

                                                          /* Add your styles here to format the PDF */
                                                        body {font-family: Arial, sans-serif;font-size: 14px;margin: 0;padding: 0; color: #000;}
                                                           @page { size: A4;  margin: 20mm;}
                                                         /* Basic reset */
                         * {
                             margin: 0;
                             padding: 0;
                             box-sizing: border-box;
                             font-family: Arial, sans-serif;
                         }
                            body {text-transform: capitalize;
               font-family: Arial, sans-serif;
               font-size: 14px;
               margin: 0;
               padding: 0;
               color: #000;
               font-weight: bold;
               direction: ltr;
          }

          /* Basic reset */
          * {
               margin: 0;
               padding: 0;
               box-sizing: border-box;
               font-family: Arial, sans-serif;
          }
          .sign {
               width: 100%;
               display: flex;
               justify-content: center;
               margin: 9px 0;
          }
          /* Container styling */
          .container {
               width: 100%;
               max-width: 800px;
               margin: 10 auto;
               padding: 20px;
               background-color: white;
               border: 1px solid #ddd;
               direction: ltr;
               font-weight: bold;
          }
          .caption {
               width: 100%;
               font-weight: bold;
               border: 1px solid black;
               padding: 5px;
               text-align: center;
               color: black;border-radius: 5px;
               background: #dddddce0;
          }
          /* Header section */
          .header {
               display: flex;
               justify-content: space-between;
               align-items: center;
               border-bottom: 1px solid #ccc;
               padding-bottom: 10px;
          }
          .section table tr {
               margin-bottom: 1px;

          }
            tr,th{font-size: 12px !important;}
          .sections {
               border-top: 1px solid #000;
               border-bottom: 1px solid #000;

          }
          .test-table {
               width: 100%;
               border-collapse: collapse;
               margin: 20px 0;
          }

          .test-table th,
          .test-table td {
               border: 1px solid rgba(0, 0, 0, 0.158);
               padding: 10px;
               text-align: center;

          }

          .test-table th {
               background-color: transparent;
          }
          .summary {
               padding: 16px;
          }
          .logo img {
               max-width: 150px;
          }

          .lab-details {
               text-align: right;
          }

          .lab-details h2 {
               font-size: 18px;
               margin-bottom: 5px;
          }

          .lab-details p {
               font-size: 14px;
               margin-bottom: 2px;
          }
          .section {
               display: flex;
               flex-direction: column;
               justify-content: space-between;
          }
          .sectionItem {
               display: flex;
          }
          .border {
               border: 1px solid black;
               min-height: 25px;
               margin: 0px 7px;
               padding: 5px;
               width: 150px;
               text-align: center;
          }
          /* Patient information section */
          .patient-info {
               display: flex;
               justify-content: space-between;
               padding: 15px 0;
               border-bottom: 1px solid #ccc;
          }

          .barcode-section {
               text-align: center;
               display: flex;
          }

          .barcode-section img {
               max-width: 100px;
               margin: 10px 0;
          }

          .patient-details {
               display: flex;
               flex-direction: column;
               justify-content: space-between;
               font-size: 14px;
          }

          /* Test details section */
          .test-details {
               padding: 15px 0;
               border-bottom: 1px solid #ccc;
               font-size: 16px;
               text-align: center;
          }

          /* Pricing information section */
          .pricing-info table {
               width: 100%;
               margin-top: 20px;
               border-collapse: collapse;
               font-size: 14px;
          }

          .pricing-info th,
          .pricing-info td {
               border: 1px solid #ddd;
               padding: 8px;
               text-align: center;
          }

          .pricing-info th {
               background-color: #f9f9f9;
               font-weight: bold;
          }

          /* For printing */
          @media print {
          .sections th,.sections tr{font-size:1px}
               body {
                    margin: 0;
                    padding: 0;
               }

               .container {
                    width: 100%;
                    max-width: 100%;
                    border: none;
                    box-shadow: none;
                    padding:10px;
                    margin:10px;
               }

               .header,
               .patient-info,
               .test-details,
               .pricing-info {
                    page-break-inside: avoid;
               }
               @media print {
                    .page-break {
                       margin-top: 200px;
                        margin-bottom: 200px;
                         page-break-before: always;
                    }
               }
          }
          .page-break {
               page-break-before: always;
          }   .caption {
               width: 100%;
               font-weight: bold;
               border: 1px solid black;
               padding: 5px;border-radius: 5px;
               text-align: center;
               color: black;}

          body {
               margin: 0;
               padding: 0;
          }
                             }`;const g=document.createElement("iframe");g.style.position="absolute",g.style.width="0px",g.style.height="0px",g.style.border="none",document.body.appendChild(g);const V=g.contentWindow.document;V.open(),V.write(`
                                      <html>
                                          <head>
                                              <title>Print Job</title>
                                              <style>${c}  .page-break {
               page-break-before: always;
          }  </style>
                                          </head>
                                          <body>${r}</body>
                                      </html>
                                  `),V.close(),g.contentWindow.focus(),g.contentWindow.print(),g.contentWindow.onafterprint=()=>{document.body.removeChild(g)}},50)},openHeaderImageUpload(t){this.currentRecordId=t,this.$refs.headerImageInput.click()},openFooterImageUpload(t){this.currentRecordId=t,this.$refs.footerImageInput.click()},uploadHeaderImage(t){const l=t.target.files[0];if(l&&this.currentRecordId){const r=new FileReader;r.onload=h=>{this.patientImages[this.currentRecordId]||(this.patientImages[this.currentRecordId]={}),this.patientImages[this.currentRecordId].header=h.target.result,localStorage.setItem("patientImages",JSON.stringify(this.patientImages)),this.$toast.add({severity:"success",summary:"نجح",detail:"تم تحميل صورة الهيدر بنجاح",life:3e3}),t.target.value=""},r.readAsDataURL(l)}},uploadFooterImage(t){const l=t.target.files[0];if(l&&this.currentRecordId){const r=new FileReader;r.onload=h=>{this.patientImages[this.currentRecordId]||(this.patientImages[this.currentRecordId]={}),this.patientImages[this.currentRecordId].footer=h.target.result,localStorage.setItem("patientImages",JSON.stringify(this.patientImages)),this.$toast.add({severity:"success",summary:"نجح",detail:"تم تحميل صورة الفوتر بنجاح",life:3e3}),t.target.value=""},r.readAsDataURL(l)}},async sendWhatsAppMessage(t){var l,r,h;try{console.log("📱 Opening WhatsApp...");const b=((l=this.User)==null?void 0:l.name)||"المختبر",m=((r=t==null?void 0:t.patient)==null?void 0:r.name)||"المريض",f=`اهلا بكم في مختبر ${b}
عزيزي ${m}
إليك نتائج الفحوصات الطبية`,p=encodeURIComponent(f);let k=(h=t==null?void 0:t.patient)==null?void 0:h.phone;k&&k.startsWith("0")&&(k=k.substring(1));const c=`https://wa.me/+964${k}?text=${p}`;window.open(c,"_blank"),this.changeInvoiceStatus(t.id),console.log("✅ WhatsApp opened successfully!")}catch(b){console.error("❌ Error in sendWhatsAppMessage:",b),alert("حدث خطأ أثناء فتح WhatsApp. يرجى المحاولة مرة أخرى.")}},isSameDate(t,l){const r=this.formatDate(t),h=this.formatDate(l);return r===h},formatDate(t){const l=new Date(t),r=l.getFullYear(),h=String(l.getMonth()+1).padStart(2,"0"),b=String(l.getDate()).padStart(2,"0");return`${r}-${h}-${b}`},downloadPDF(t){this.printRecord=t;const l=document.getElementById("Result").innerHTML,r=new Blob([l],{type:"application/pdf"}),h=URL.createObjectURL(r),b=document.createElement("a");b.href=h,b.download="document.pdf",document.body.appendChild(b),b.click(),document.body.removeChild(b),URL.revokeObjectURL(h)}},watch:{Filters:{deep:!0,handler(){this.pagination.current_page=1,this.Getinvoices(this.Filters)}}}},kn={class:"card filterTable pb-0"},wn={class:"flex justify-content-between mb-2"},vn={key:0,class:"card flex justify-content-center mb-5"},Rn={key:1},Un={class:"flex justify-content-end mb-2"},$n={class:"globalSearch"},In=["placeholder"],Cn={class:"btns"},Dn={class:"noData p-d-flex p-ai-center p-jc-center",style:{height:"100px"}},xn=["placeholder"],Tn={class:"boldText"},Fn=["placeholder"],Mn={class:"boldText"},Sn=["placeholder"],An={class:"boldText"},Ln={class:"boldText"},jn=["placeholder"],zn={class:"boldText"},Pn=["placeholder"],Hn=["onClick"],Bn=["placeholder"],Wn={class:"boldText"};function En(t,l,r,h,b,m){var Y;const f=U("InlineMessage"),p=U("Button"),k=U("Column"),c=U("Calendar"),g=U("Dropdown"),V=U("BarcodeComponent"),T=U("Menu"),M=U("DataTable"),S=U("pationtHistoryModal"),A=U("PrintMarginDialog"),L=U("patient-modal"),u=U("due-modal"),R=U("job_orderModal"),n=U("printResult"),_=U("whatsUp"),a=U("parcodModal"),W=U("workSheetModal"),O=U("updateResultModal"),X=U("attachment"),E=ge("tooltip");return s(),d("div",kn,[e("div",wn,[e("h5",null,o(t.t("medical_reports")),1)]),((Y=t.pagination.total)==null?void 0:Y.toLocaleString())==0?(s(),d("div",vn,[i(f,{severity:"info"},{default:v(()=>[F(o(t.t("noData")),1)]),_:1})])):(s(),d("div",Rn,[l[13]||(l[13]=e("br",null,null,-1)),l[14]||(l[14]=e("br",null,null,-1)),e("div",Un,[i(p,{label:"هوامش الطباعة",icon:"pi pi-print",onClick:l[0]||(l[0]=w=>b.marginDialogVisible=!0)})]),e("div",$n,[z(e("input",{"onUpdate:modelValue":l[1]||(l[1]=w=>b.Filters.patient_name=w),placeholder:t.t("search")+"...",class:"search p-inputtext p-component mb-2"},null,8,In),[[J,b.Filters.patient_name]]),e("div",Cn,[i(p,{label:t.t("Clear Filters"),icon:"pi pi-filter-slash",onClick:m.clearFilters,class:"p-button-secondary mb-2"},null,8,["label","onClick"]),t.havePermission("invoices export")?(s(),y(p,{key:0,label:t.t("Export to Excel"),icon:"pi pi-file-excel",onClick:m.exportToExcel,class:"p-button-success mb-2"},null,8,["label","onClick"])):$("",!0)])]),l[15]||(l[15]=e("br",null,null,-1)),l[16]||(l[16]=e("br",null,null,-1)),l[17]||(l[17]=e("div",null,null,-1)),i(M,{size:"small",value:m.filteredRecords,scrollable:"",scrollHeight:"600px",responsiveLayout:"scroll",paginator:!0,lazy:!0,rows:t.pagination.per_page,totalRecords:t.pagination.total,first:(t.pagination.current_page-1)*t.pagination.per_page,onPage:m.onPageChange},{empty:v(()=>[e("div",Dn,o(t.t("noData")),1)]),default:v(()=>[i(k,{class:"text-center",header:"#",field:"index"}),i(k,{class:"text-center",field:"patient.name",style:{"min-width":"250px"}},{header:v(()=>[e("p",null,o(t.t("Pationt_name")),1),z(e("input",{"onUpdate:modelValue":l[2]||(l[2]=w=>b.Filters.patient_name=w),placeholder:t.t("search")+"...",class:"p-inputtext p-component"},null,8,xn),[[J,b.Filters.patient_name]])]),body:v(w=>{var x,N;return[(x=w.data.patient)!=null&&x.name?(s(),y(p,{key:0,style:{background:"green","min-width":"200px"},label:(N=w.data.patient)==null?void 0:N.name,class:"p-eye-button mx-1"},null,8,["label"])):$("",!0)]}),_:1}),i(k,{class:"text-center",field:"registration_date",style:{"min-width":"150px"}},{header:v(()=>[e("p",null,o(t.t("Registration_date")),1),i(c,{modelValue:b.Filters.registration_date,"onUpdate:modelValue":l[3]||(l[3]=w=>b.Filters.registration_date=w),placeholder:t.t("search")+"...",showIcon:"",dateFormat:"yy-mm-dd"},null,8,["modelValue","placeholder"])]),body:v(w=>[e("p",Tn,o(t.dateTimeFormat(w.data.registration_date)),1)]),_:1}),i(k,{class:"text-center",field:"lab",style:{"min-width":"100px"}},{header:v(()=>[e("p",null,o(t.t("lab/bruanch")),1),z(e("input",{"onUpdate:modelValue":l[4]||(l[4]=w=>b.Filters.lab=w),placeholder:t.t("search")+"...",class:"p-inputtext p-component"},null,8,Fn),[[J,b.Filters.lab]])]),body:v(w=>{var x;return[e("p",Mn,o((x=w.data)==null?void 0:x.lab),1)]}),_:1}),i(k,{class:"text-center",field:"created_by.name",style:{"min-width":"100px"}},{header:v(()=>[e("p",null,o(t.t("Created_By")),1),z(e("input",{"onUpdate:modelValue":l[5]||(l[5]=w=>b.Filters.created_by=w),placeholder:t.t("search")+"...",class:"p-inputtext p-component"},null,8,Sn),[[J,b.Filters.created_by]])]),body:v(w=>{var x;return[e("p",An,o((x=w.data.created_by)==null?void 0:x.name),1)]}),_:1}),t.contracts?(s(),y(k,{key:0,class:"text-center",field:"contract.name",style:{"min-width":"100px"}},{header:v(()=>[e("p",null,o(t.t("contract")),1),i(g,{modelValue:b.Filters.contract_id_fk,"onUpdate:modelValue":l[6]||(l[6]=w=>b.Filters.contract_id_fk=w),options:t.contracts,optionLabel:"label",optionValue:"value",placeholder:t.t("search")+"..."},null,8,["modelValue","options","placeholder"])]),body:v(w=>{var x;return[e("p",Ln,o((x=w.data.contract)==null?void 0:x.name),1)]}),_:1})):$("",!0),i(k,{class:"text-center",field:"from_lab",style:{"min-width":"100px"}},{header:v(()=>[e("p",null,o(t.t("from_lab")),1),z(e("input",{"onUpdate:modelValue":l[7]||(l[7]=w=>b.Filters.from_lab=w),placeholder:t.t("search")+"...",class:"p-inputtext p-component"},null,8,jn),[[J,b.Filters.from_lab]])]),body:v(w=>{var x;return[e("p",zn,o((x=w.data)==null?void 0:x.from_lab),1)]}),_:1}),t.patientId?$("",!0):(s(),y(k,{key:1,class:"text-center",field:"barcode",style:{"min-width":"150px"}},{header:v(()=>[e("p",null,o(t.t("Barcode")),1),z(e("input",{"onUpdate:modelValue":l[8]||(l[8]=w=>b.Filters.barcode=w),placeholder:t.t("search")+"...",class:"p-inputtext p-component"},null,8,Pn),[[J,b.Filters.barcode]])]),body:v(w=>{var x,N;return[e("button",{class:"Parcode-button mx-1",onClick:Z=>m.printParcode(w.data)},[e("span",null,o((x=w.data)==null?void 0:x.barcode),1),e("div",null,[i(V,{value:(N=w.data)==null?void 0:N.barcode},null,8,["value"])])],8,Hn)]}),_:1})),i(k,{class:"text-center",field:"signed_by.name",style:{"min-width":"100px"}},{header:v(()=>[e("p",null,o(t.t("sign_by")),1),z(e("input",{"onUpdate:modelValue":l[9]||(l[9]=w=>b.Filters.signed_by=w),placeholder:t.t("search")+"...",class:"p-inputtext p-component"},null,8,Bn),[[J,b.Filters.signed_by]])]),body:v(w=>{var x;return[e("p",Wn,o((x=w.data.signed_by)==null?void 0:x.name),1)]}),_:1}),i(k,{class:"text-center",field:"tests",style:{"min-width":"120px"}},{header:v(()=>[e("p",null,o(t.t("the_tests")),1)]),body:v(w=>[i(p,{label:w.data.is_done?t.t("done"):t.t("pendening"),class:j(["p-eye-button mx-1",w.data.is_done?"done":"pendening"]),onClick:x=>m.showPatient_due(w.data)},null,8,["label","class","onClick"])]),_:1}),i(k,{class:"text-center",field:"sent_to_patient",style:{"min-width":"80px"}},{header:v(()=>[e("p",null,o(t.t("theStatus")),1)]),body:v(w=>[e("i",{class:j(["pi pi-circle-fill",w.data.sent_to_patient==!0?"sucess":"danger"])},null,2)]),_:1}),i(k,{class:"text-center",field:"all_actions",style:{"min-width":"280px"}},{header:v(()=>[e("p",null,o(t.t("actions")),1)]),body:v(w=>[t.havePermission("invoices edit")&&!t.patientId?z((s(),y(p,{key:0,icon:"pi pi-pencil",class:"p-button-rounded p-button-text",onClick:x=>m.updateResult(w.data)},null,8,["onClick"])),[[E,t.t("updateResult"),void 0,{top:!0}]]):$("",!0),z(i(p,{icon:"pi pi-print",class:"p-button-rounded p-button-text",onClick:x=>m.openprintResultTemplate(w.data)},null,8,["onClick"]),[[E,t.t("Result"),void 0,{top:!0}]]),!t.patientId&&t.havePermission("invoices send whatsapp")?z((s(),y(p,{key:1,icon:"pi pi-file-pdf",class:"p-button-rounded p-button-text p-button-help",onClick:x=>m.printPDFForWhatsApp(w.data)},null,8,["onClick"])),[[E,t.t("Print PDF"),void 0,{top:!0}]]):$("",!0),!t.patientId&&t.havePermission("invoices send whatsapp")?z((s(),y(p,{key:2,icon:"pi pi-whatsapp",class:"p-button-rounded p-button-text p-button-success",onClick:x=>m.sendWhatsAppMessage(w.data)},null,8,["onClick"])),[[E,t.t("send_whatsapp"),void 0,{top:!0}]]):$("",!0),z(i(p,{icon:"pi pi-calendar",class:"p-button-rounded p-button-text",onClick:x=>m.PationtHistory(w.data)},null,8,["onClick"]),[[E,t.t("pationtHistory"),void 0,{top:!0}]]),i(p,{icon:"pi pi-ellipsis-v",class:"p-button-rounded p-button-text",onClick:x=>m.toggleMenu(x,w.data.id),"aria-haspopup":"true","aria-controls":"menu_"+w.data.id},null,8,["onClick","aria-controls"]),i(T,{ref:x=>m.setMenuRef(x,w.data.id),id:"menu_"+w.data.id,model:m.getMenuItems(w.data),popup:!0},null,8,["id","model"])]),_:1})]),_:1},8,["value","rows","totalRecords","first","onPage"])])),i(S),i(A,{visible:b.marginDialogVisible,"onUpdate:visible":l[10]||(l[10]=w=>b.marginDialogVisible=w),onPreview:t.generatePDF},null,8,["visible","onPreview"]),i(L),i(u),i(R),i(n),i(_),i(a),i(W),i(O),i(X),e("input",{ref:"headerImageInput",type:"file",accept:"image/*",style:{display:"none"},onChange:l[11]||(l[11]=(...w)=>m.uploadHeaderImage&&m.uploadHeaderImage(...w))},null,544),e("input",{ref:"footerImageInput",type:"file",accept:"image/*",style:{display:"none"},onChange:l[12]||(l[12]=(...w)=>m.uploadFooterImage&&m.uploadFooterImage(...w))},null,544)])}const Xn=B(Vn,[["render",En],["__scopeId","data-v-462e8169"]]);export{Xn as default};
