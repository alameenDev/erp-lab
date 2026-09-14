import{_ as W,m as tt,a as nt,o,j as r,b as t,g as h,t as l,F as a,q as p}from"./index-29cb79eb.js";import{h as et,J as lt}from"./html2pdf-d006e935.js";import{u as Z}from"./qrcode.vue.esm-2997d369.js";const it={data(){return{isfromRoute:!1,invoiceId:null,documentHeight:0,pdfUrl:null}},computed:{...tt(Z,["printRecord"])},mounted(){this.invoiceId=this.$route.params.invoiceId,this.invoiceId&&this.getData(),this.documentHeight=document.documentElement.scrollHeight,this.isfromRoute&&this.openprintINvoiceTemplate([])},methods:{...nt(Z,["GetinvoicesById"]),getData(){this.GetinvoicesById(this.patientId).then(()=>{this.generatePDF()})},getPatientReportLink(){return`${window.location.origin}/result/${this.patientId}`},async generatePDF(){document.getElementById("printInvoice").style.display="block";const e=this.$refs.contentToConvert,n={margin:.5,filename:"medical-report.pdf",image:{type:"jpeg",quality:.98},html2canvas:{scale:4},jsPDF:{unit:"in",format:"a4",orientation:"portrait"}};await et().set(n).from(e).outputPdf("datauristring").then(b=>{this.pdfUrl=b}),document.getElementById("printInvoice").style.display="none"},generateBarcodeImage(e){if(!e)return"";const n=document.createElement("canvas");return lt(n,e,{format:"CODE128",displayValue:!1,width:1,height:15}),n.toDataURL("image/png")},openprintINvoiceTemplate(e){this.printRecord=e,setTimeout(function(){var f,g;const n=document.getElementById("printInvoice").innerHTML;(f=document.head)!=null&&f.innerHTML||((g=document.getElementsByTagName("head")[0])==null||g.innerHTML);var b="@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } }";const c=window.open("","_blank");c.document.write(`
                           <html>
                             <head>
                               <title>printINvoice Template</title>
                               <style>
                               ${b}
                                 /* Add your styles here to format the PDF */
                               body {font-family: "Tajawal", sans-serif;;font-size: 14px;margin: 0;padding: 0; color: #000;}
                                  @page { size: A4;  margin: 20mm;}
                                /* Basic reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Tajawal", sans-serif;;
}

/* Container styling */
.container {
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: white;
    border: 1px solid #ddd;
}

/* Header section */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ccc;
    padding-bottom: 10px;
}
 .test-table {
                             width: 100%;
                             border-collapse: collapse;
                             margin: 20px 0;
                        }

                        .test-table th,
                        .test-table td {
                             border: 1px solid #000;
                             padding: 10px;
                             text-align: center;
                        }

                        .test-table th {
                             background-color: #f0f0f0;
                        }
 .summary   {    padding: 16px;}
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
    .section  {display: flex;
    flex-direction: column;
    justify-content: space-between;}
    .sectionItem{ display: flex;}
.border{border: 1px solid black;    margin: 0px 7px; padding: 5px;width: 150px;text-align: center;}
/* Patient information section */
.patient-info {
    display: flex;
    justify-content: space-between;
    padding: 15px 0;
    border-bottom: 1px solid #ccc;
}

.barcode-section {
    text-align: center;    display: flex;
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

.pricing-info th, .pricing-info td {
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
    body {
        margin: 0;
        padding: 0;
    }

    .container {
        width: 100%;
        max-width: 100%;
        border: none;
        box-shadow: none;
    }

    .header, .patient-info, .test-details, .pricing-info {
        page-break-inside: avoid;
    }
}

                               </style>
                             </head>
                             <body>
                               ${n}
                             </body>
                           </html>
                         `);const d=window.open("","","left=10,top=100,width=800,height=900,toolbar=0,scrollbars=0,status=0");c.document.close(),c.onload=()=>{c.print(),c.onafterprint=()=>{c.close()}},setTimeout(()=>{d==null||d.focus(),d==null||d.print()},1e3)},50)}}},ot={key:0,class:"pdf-preview"},rt=["src","height"],st={class:"container",id:"printInvoice",ref:"contentToConvert"},dt={class:"patient-info"},at={class:"qrcode"},ut=["src"],pt={class:"section"},ct={style:{display:"flex","flex-direction":"column","align-items":"center"}},mt=["src"],gt={class:"border"},bt={class:"border"},ft={class:"patient-details"},ht={class:"sectionItem"},yt={class:"border"},vt={class:"border"},xt={class:"border"},wt={key:0},Rt={class:"caption"},It={class:"test-table"},kt={key:1},Tt={class:"caption"},Bt={class:"test-table"},jt={key:2,class:"test-details"},Dt={class:"test-table"},zt={class:"summary"},Ft={style:{width:"100%"}};function Et(e,n,b,c,d,f){var g,y,v,x,w,R,I,k,T,B,j,D,z,F,E,H,P,C,q,L,N,$,S,U,A,M,V,G,J,O,Q,_,K,X,Y;return o(),r(a,null,[d.pdfUrl?(o(),r("div",ot,[t("iframe",{src:d.pdfUrl,width:"100%",height:d.documentHeight,style:{border:"1px solid #ccc"}},null,8,rt)])):h("",!0),t("div",st,[n[14]||(n[14]=t("br",null,null,-1)),n[15]||(n[15]=t("br",null,null,-1)),n[16]||(n[16]=t("br",null,null,-1)),n[17]||(n[17]=t("br",null,null,-1)),n[18]||(n[18]=t("br",null,null,-1)),n[19]||(n[19]=t("br",null,null,-1)),t("section",dt,[t("div",at,[t("img",{src:(g=e.printRecord)==null?void 0:g.pdf_qr_code,alt:"QR Code",class:"qrcode"},null,8,ut)]),t("div",pt,[t("table",null,[t("tr",null,[n[0]||(n[0]=t("th",null,[t("span",null,[t("strong",null,"Barcode")])],-1)),t("td",null,[t("span",ct,[t("img",{src:f.generateBarcodeImage((y=e.printRecord)==null?void 0:y.barcode),alt:"Barcode"},null,8,mt),t("strong",null,l((x=(v=e.printRecord)==null?void 0:v.patient)==null?void 0:x.code),1)])])]),t("tr",null,[n[1]||(n[1]=t("th",null,[t("div",null,[t("strong",null,"age / Sex")])],-1)),t("td",null,[t("div",gt,[t("strong",null,l(((R=(w=e.printRecord)==null?void 0:w.patient)==null?void 0:R.age)+((k=(I=e.printRecord)==null?void 0:I.patient)==null?void 0:k.age_unit))+" / "+l((B=(T=e.printRecord)==null?void 0:T.patient)==null?void 0:B.gender),1)])])]),t("tr",null,[n[2]||(n[2]=t("th",null,[t("div",null,[t("strong",null,"Referred By")])],-1)),t("td",null,[t("div",bt,[t("strong",null,l((D=(j=e.jobOrder)==null?void 0:j.referral)==null?void 0:D.name),1)])])])])]),t("div",ft,[t("div",ht,[t("table",null,[t("tr",null,[n[3]||(n[3]=t("th",null,[t("div",null,[t("strong",null,"patient name")])],-1)),t("td",null,[t("div",yt,[t("strong",null,l((F=(z=e.printRecord)==null?void 0:z.patient)==null?void 0:F.name),1)])])]),t("tr",null,[n[4]||(n[4]=t("th",null,[t("div",null,[t("strong",null,"Request Date")])],-1)),t("td",null,[t("div",vt,[t("strong",null,l(e.dateTimeFormat((E=e.printRecord)==null?void 0:E.registration_date)),1)])])]),t("tr",null,[n[5]||(n[5]=t("th",null,[t("div",null,[t("strong",null,"Result date")])],-1)),t("td",null,[t("div",xt,[t("strong",null,l(e.dateTimeFormat((H=e.printRecord)==null?void 0:H.result_date)),1)])])])])])])]),((C=(P=e.printRecord)==null?void 0:P.test_groups)==null?void 0:C.length)>0?(o(),r("div",wt,[(o(!0),r(a,null,p((q=e.printRecord)==null?void 0:q.test_groups,(i,u)=>(o(),r("section",{class:"test-details",key:u},[t("div",Rt,l(i.group_name),1),t("table",It,[n[6]||(n[6]=t("thead",null,[t("tr",null,[t("th",null,"tests"),t("th",null,"Price")])],-1)),t("tbody",null,[(o(!0),r(a,null,p(i==null?void 0:i.tests,(s,m)=>(o(),r("tr",{key:m},[t("td",null,l(s.report_name),1),t("td",null,l(s.price),1)]))),128)),(o(!0),r(a,null,p(i==null?void 0:i.cultures,(s,m)=>(o(),r("tr",{key:m},[t("td",null,l(s.name),1),t("td",null,l(s.price),1)]))),128))])])]))),128))])):h("",!0),((N=(L=e.printRecord)==null?void 0:L.packages)==null?void 0:N.length)>0?(o(),r("div",kt,[(o(!0),r(a,null,p(($=e.printRecord)==null?void 0:$.packages,(i,u)=>(o(),r("section",{class:"test-details",key:u},[t("div",Tt,l(i.name),1),t("table",Bt,[n[7]||(n[7]=t("thead",null,[t("tr",null,[t("th",null,"tests"),t("th",null,"Price")])],-1)),t("tbody",null,[(o(!0),r(a,null,p(i==null?void 0:i.tests,(s,m)=>(o(),r("tr",{key:m},[t("td",null,l(s.report_name),1),t("td",null,l(s.price),1)]))),128)),(o(!0),r(a,null,p(i==null?void 0:i.cultures,(s,m)=>(o(),r("tr",{key:m},[t("td",null,l(s.name),1),t("td",null,l(s.price),1)]))),128))])])]))),128))])):h("",!0),((U=(S=e.printRecord)==null?void 0:S.tests)==null?void 0:U.length)>0||((M=(A=e.printRecord)==null?void 0:A.cultures)==null?void 0:M.length)>0?(o(),r("section",jt,[t("table",Dt,[n[8]||(n[8]=t("thead",null,[t("tr",null,[t("th",null,"tests"),t("th",null,"Price")])],-1)),t("tbody",null,[(o(!0),r(a,null,p((V=e.printRecord)==null?void 0:V.tests,(i,u)=>(o(),r("tr",{key:u},[t("td",null,l(i.report_name),1),t("td",null,l(i.price),1)]))),128)),(o(!0),r(a,null,p((G=e.printRecord)==null?void 0:G.cultures,(i,u)=>(o(),r("tr",{key:u},[t("td",null,l(i.name),1),t("td",null,l(i.price),1)]))),128)),(o(!0),r(a,null,p((J=e.printRecord)==null?void 0:J.packages,(i,u)=>(o(),r("tr",{key:u},[t("td",null,l(i.name),1),t("td",null,l(i.price),1)]))),128))])])])):h("",!0),t("section",zt,[t("table",Ft,[t("tr",null,[n[9]||(n[9]=t("th",null,"Subtotal:",-1)),t("td",null,[t("strong",null,l((O=e.printRecord)==null?void 0:O.sub_total),1)])]),t("tr",null,[n[10]||(n[10]=t("th",null,"discount:",-1)),t("td",null,[t("strong",null,l((Q=e.printRecord)==null?void 0:Q.discount),1)])]),t("tr",null,[n[11]||(n[11]=t("th",null,"Total :",-1)),t("td",null,[t("strong",null,l((_=e.printRecord)==null?void 0:_.total),1)])]),t("tr",null,[n[12]||(n[12]=t("th",null,"paid:",-1)),t("td",null,[t("strong",null,l((K=e.printRecord)==null?void 0:K.paid),1)])]),t("tr",null,[n[13]||(n[13]=t("th",null,"Due:",-1)),t("td",null,[t("strong",null,l(((X=e.printRecord)==null?void 0:X.total)-((Y=e.printRecord)==null?void 0:Y.paid)),1)])])])]),n[20]||(n[20]=t("br",null,null,-1)),n[21]||(n[21]=t("br",null,null,-1)),n[22]||(n[22]=t("br",null,null,-1)),n[23]||(n[23]=t("br",null,null,-1))],512)],64)}const qt=W(it,[["render",Et],["__scopeId","data-v-405b1c47"]]);export{qt as default};
