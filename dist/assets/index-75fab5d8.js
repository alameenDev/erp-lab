import{m as E,_ as te,r as P,o as d,j as c,F as S,q as I,n as X,b as t,t as n,d as a,a as H,w as T,f as R,g as D,l as Je,$ as Ye,L as Ke,e as A,v as J,c as N,h as Xe,i as Te,M as Ze,s as et,p as tt,k as se,u as lt}from"./index-29cb79eb.js";import{u as K,Q as nt}from"./qrcode.vue.esm-2997d369.js";import{u as Ie}from"./labs-9a5ad303.js";import{u as Fe}from"./contracts-10e90514.js";import{u as ze}from"./referrals-fc21b634.js";import{u as xe}from"./cultures-40543049.js";import{u as Ge}from"./tests-b879c0b1.js";import{u as Ae}from"./packages-89c39907.js";import{u as Ee}from"./payment-methods-6fc8aa5e.js";import{B as Se,u as Ne}from"./BarcodeComponent-ba1969db.js";import{u as $e}from"./patients-de2b99e0.js";import{u as We}from"./testGroups-57d5a280.js";import{u as Qe,a as Oe}from"./titles-4d1f443b.js";import He from"./print_invoice-7183c84a.js";import{J as st}from"./html2pdf-d006e935.js";import{u as Me,w as ot}from"./xlsx-f5126985.js";const it={computed:{...E(K,["printRecord","samplesTests"])},components:{BarcodeComponent:Se},methods:{}},at={class:"container",id:"parcode"},dt={class:"details"},rt={class:"barcode"},ut={class:"text-center",style:{"line-height":"0"}},pt={class:"test-list"};function ct(e,l,r,p,i,s){const u=P("BarcodeComponent");return d(),c("div",at,[(d(!0),c(S,null,I(e.samplesTests,(f,_)=>{var y,g,M,F,v,$,L,U,B,z,x;return d(),c("div",{class:X(["label-container",_>0?"page-break":""]),style:{"font-size":"2.5px"},key:_},[t("p",dt,[t("span",null,n(f==null?void 0:f.sample_name),1)]),t("div",rt,[t("div",null,[t("span",null,n((y=e.printRecord)==null?void 0:y.barcode),1)]),t("div",null,[a(u,{value:(g=e.printRecord)==null?void 0:g.barcode},null,8,["value"])])]),t("div",ut,[t("p",null,n((F=(M=e.printRecord)==null?void 0:M.patient)==null?void 0:F.name),1),t("p",null,n(($=(v=e.printRecord)==null?void 0:v.patient)==null?void 0:$.gender)+" / "+n(((U=(L=e.printRecord)==null?void 0:L.patient)==null?void 0:U.age)+((z=(B=e.printRecord)==null?void 0:B.patient)==null?void 0:z.age_unit))+"     "+n(e.dateTimeFormat((x=e.printRecord)==null?void 0:x.registration_date)),1)]),t("div",pt,[(d(!0),c(S,null,I(f.test,o=>(d(),c("span",{key:o},n(o.name)+"-",1))),128)),(d(!0),c(S,null,I(f.culture,o=>(d(),c("span",{key:o},n(o.name)+"-",1))),128))])],2)}),128))])}const qe=te(it,[["render",ct],["__scopeId","data-v-226f4d29"]]);const mt={computed:{...E(K,["printRecord","printInvoiceDialog"])},components:{parcodModal:qe,BarcodeComponent:Se},methods:{...H(K,["getsamples"]),printParcode(e){this.printRecord=e,this.getsamples(e.id).then(l=>{setTimeout(function(){const r=document.getElementById("parcode").innerHTML;var p=`
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
                                   //   line-height: 0;
                                  overflow: hidden; /* Ensure no content overflows */
                                }

                                .details,
                                .barcode,
                                .text-center,
                                .test-list {
                                  text-align: center;
                                  margin: 0;
                                  padding:0;
                           

                                }
svg{
height:5px !important;
}
                                .page-break {
                                  page-break-before: always;
                                }
                              }

                                                                    `;const i=document.createElement("iframe");i.style.position="absolute",document.body.appendChild(i);const s=i.contentWindow.document;s.open(),s.write(`
                                      <html>
                                          <head>
                                              <title>Print Job</title>
                                              <style>${p}</style>
                                          </head>
                                          <body>${r}</body>
                                      </html>
                                  `),document.innerHTML=r,s.close(),i.contentWindow.focus(),i.contentWindow.print(),i.contentWindow.onafterprint=()=>{document.body.removeChild(i)}},50)})},openprintINvoiceTemplate(e){this.printRecord=e,setTimeout(function(){const l=document.getElementById("printInvoice").innerHTML;var r=`@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } }


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
                                    border: 1px solid #e5e7eb;
                                  padding: 10px;
                                  text-align: center;
                             }

                             .test-table th {
                                  background-color: #f0f0f0;
                             }
      .summary   {    padding: 16px; }
       .summary td{ text-align:right;} .summary th{ text-align:left;}
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
     .border{border: 1px solid black;      min-height: 25px;  margin: 0px 7px; padding: 5px;width: 150px;text-align: center;}
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
     } `;const p=document.createElement("iframe");p.style.position="absolute",p.style.width="0px",p.style.height="0px",p.style.border="none",document.body.appendChild(p);const i=p.contentWindow.document;i.open(),i.write(`
             <html>
                 <head>
                     <title>printInvoice</title>
                     <style>${r}</style>
                 </head>
                 <body>${l}</body>
             </html>
         `),i.close(),p.contentWindow.focus(),p.contentWindow.print(),p.contentWindow.onafterprint=()=>{document.body.removeChild(p)}},50)},openthermalRecord(e){this.printRecord=e,setTimeout(function(){const l=document.getElementById("thermalRecord").innerHTML,r=` @media print {   body,  .page {   width:Statement; margin: auto; !important;   box-shadow: 0; text-align:center;   -webkit-print-color-adjust: exact;       color: #000;    } } ;
     .elements{width:50%;    font-size: xx-small;}

                  body {
                         font-family: "Tajawal", sans-serif;;
                               font-size: xx-small;
                         margin: 0;
                         padding: 0;
                         background-color: #f8f8f8;
                         display: flex;
                         justify-content: center;
                    }
                    header {
                         text-align: center;

                    }
                    .logo {
                         max-width: 40px;
                         margin-bottom: 1px;
                    }

                    .divider {
                         border: 1px solid #000;
                         margin:3px 0;
                    }
                    .patient-details p { margin: 5px 0;      margin-bottom: 10px;  display: flex; align-items: center; justify-content: space-between;
                    }
                .patient-details .parcod {
                         margin: 5px 0;
                          display: flex;
                             justify-content: space-between;
                    }

                    .barcode {

                         font-weight: bold;
                         text-align: center;
                         margin-bottom: 10px;
                    }

                    .test-table {
                         width: 100%;
                         border-collapse: collapse;

                    }

                    .test-table th,
                    .test-table td {
                         border: 1px solid #e5e7eb;
                         padding: 3px;
                         text-align: center;        font-size: xx-small;
                    }

                   .summaryItm{display: flex; justify-content: space-between;     border-bottom: 1px solid;}
                    .footer {
                         text-align: center;
                         margin-top: 2px;
                    }

                    .qrcode {

                    }
     .test-details{page-break-inside: avoid; text-align:center;}
                    /* Responsiveness */
                    @media screen and (max-width: 768px) {
                         .container {
                              width: 100%;
                              padding: 10px;
                         }

                         .logo {
                              max-width: 100px;
                         }

                         .barcode {
                              font-size: 16px;
                         }

                         .test-table th,
                         .test-table td {
                              padding: 5px;
                         }

                         .qrcode {
                              max-width: 80px;
                         }
                    }

                     `,p=document.createElement("iframe");p.style.position="absolute",p.style.width="0px",p.style.height="0px",p.style.border="none",document.body.appendChild(p);const i=p.contentWindow.document;i.open(),i.write(`
             <html>
                 <head>
                     <title>Print thermal</title>
                     <style>${r}</style>
                 </head>
                 <body>${l}</body>
             </html>
         `),i.close(),p.contentWindow.focus(),p.contentWindow.print(),p.contentWindow.onafterprint=()=>{document.body.removeChild(p)}},50)},close(){this.printInvoiceDialog=!1}}},ht={class:"parcode"},bt={style:{display:"flex","flex-direction":"column","align-items":"center"}},ft={id:"print"},gt={key:0},_t={class:"caption"},yt={class:"test-table table"},vt={key:1},wt={class:"caption"},kt={class:"test-table table"},Vt={key:2,class:"test-details"},Rt={class:"test-table table"},Ct={class:"summary",style:{"margin-top":"5px"}},Pt={class:"receipt"},$t={class:"row"},Tt={class:"value"},St={class:"row"},Ut={class:"value"},It={class:"row"},Mt={class:"value"},Ft={class:"row"},Dt={class:"value"},Lt={class:"row"},jt={class:"value"},Bt={class:"flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function zt(e,l,r,p,i,s){const u=P("BarcodeComponent"),f=P("Button"),_=P("Dialog"),y=P("parcodModal");return d(),c(S,null,[a(_,{visible:e.printInvoiceDialog,"onUpdate:visible":l[4]||(l[4]=g=>e.printInvoiceDialog=g),modal:"",style:{width:"70rem"}},{default:T(()=>{var g,M,F,v,$,L,U,B,z,x,o,m,k,h,w,b,W;return[t("div",null,[t("section",ht,[t("span",{onClick:l[0]||(l[0]=V=>s.printParcode(e.printRecord)),style:{margin:"auto",cursor:"pointer"}},[t("div",bt,[a(u,{value:(g=e.printRecord)==null?void 0:g.barcode},null,8,["value"]),R(" "+n((M=e.printRecord)==null?void 0:M.barcode),1)])]),t("span",null,[a(f,{label:e.t("thermal_recipt"),icon:"pi pi-receipt",class:"p-button-rounded mx-1",onClick:l[1]||(l[1]=V=>s.openthermalRecord(e.printRecord))},null,8,["label"])])]),l[13]||(l[13]=t("br",null,null,-1)),l[14]||(l[14]=t("hr",null,null,-1)),t("div",ft,[((F=e.printRecord)==null?void 0:F.test_groups.length)>0?(d(),c("div",gt,[(d(!0),c(S,null,I((v=e.printRecord)==null?void 0:v.test_groups,(V,q)=>(d(),c("section",{class:"test-details",key:q},[t("div",_t,n(V.group_name),1),t("table",yt,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("the_tests")),1)])]),t("tbody",null,[(d(!0),c(S,null,I(V==null?void 0:V.tests,(O,Y)=>(d(),c("tr",{key:Y},[t("td",null,n(O.price),1),t("td",null,n(O.report_name),1)]))),128)),(d(!0),c(S,null,I(V==null?void 0:V.cultures,(O,Y)=>(d(),c("tr",{key:Y},[t("td",null,n(O.price),1),t("td",null,n(O.name),1)]))),128))])])]))),128))])):D("",!0),l[10]||(l[10]=t("br",null,null,-1)),(($=e.printRecord)==null?void 0:$.packages.length)>0?(d(),c("div",vt,[(d(!0),c(S,null,I((L=e.printRecord)==null?void 0:L.packages,(V,q)=>(d(),c("section",{class:"test-details",key:q},[t("div",wt,n(V.name),1),t("table",kt,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("the_tests")),1)])]),t("tbody",null,[(d(!0),c(S,null,I(V==null?void 0:V.tests,(O,Y)=>(d(),c("tr",{key:Y},[t("td",null,n(O.price),1),t("td",null,n(O.report_name),1)]))),128)),(d(!0),c(S,null,I(V==null?void 0:V.cultures,(O,Y)=>(d(),c("tr",{key:Y},[t("td",null,n(O.price),1),t("td",null,n(O.name),1)]))),128))])])]))),128))])):D("",!0),l[11]||(l[11]=t("br",null,null,-1)),((U=e.printRecord)==null?void 0:U.tests.length)>0||((B=e.printRecord)==null?void 0:B.cultures.length)>0?(d(),c("div",Vt,[t("table",Rt,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("the_tests")),1)])]),t("tbody",null,[(d(!0),c(S,null,I((z=e.printRecord)==null?void 0:z.tests,(V,q)=>(d(),c("tr",{key:q},[t("td",null,n(V.price),1),t("td",null,n(V.report_name),1)]))),128)),(d(!0),c(S,null,I((x=e.printRecord)==null?void 0:x.cultures,(V,q)=>(d(),c("tr",{key:q},[t("td",null,n(V.price),1),t("td",null,n(V.name),1)]))),128)),(d(!0),c(S,null,I((o=e.printRecord)==null?void 0:o.packages,(V,q)=>(d(),c("tr",{key:q},[t("td",null,n(V.price),1),t("td",null,n(V.name),1)]))),128))])])])):D("",!0),l[12]||(l[12]=t("br",null,null,-1)),t("div",Ct,[t("div",Pt,[t("div",$t,[l[5]||(l[5]=t("span",{class:"label"},"Subtotal",-1)),t("span",Tt,"IQD "+n((m=e.printRecord)==null?void 0:m.sub_total),1)]),t("div",St,[l[6]||(l[6]=t("span",{class:"label"},"discount percentage",-1)),t("span",Ut,"IQD "+n((k=e.printRecord)==null?void 0:k.discount),1)]),t("div",It,[l[7]||(l[7]=t("span",{class:"label"},"Total",-1)),t("span",Mt,"IQD "+n((h=e.printRecord)==null?void 0:h.total),1)]),t("div",Ft,[l[8]||(l[8]=t("span",{class:"label"},"paid",-1)),t("span",Dt,"IQD "+n((w=e.printRecord)==null?void 0:w.paid),1)]),t("div",Lt,[l[9]||(l[9]=t("span",{class:"label"},"Due",-1)),t("span",jt,"IQD "+n(((b=e.printRecord)==null?void 0:b.total)-((W=e.printRecord)==null?void 0:W.paid)),1)])])])])]),t("div",Bt,[a(f,{size:"small",label:e.t("close"),severity:"danger",onClick:l[2]||(l[2]=V=>s.close())},null,8,["label"]),a(f,{icon:"pi pi-print",size:"small",severity:"success",onClick:l[3]||(l[3]=V=>s.openprintINvoiceTemplate(e.printRecord))})])]}),_:1},8,["visible"]),a(y)],64)}const xt=te(mt,[["render",zt],["__scopeId","data-v-16e720cf"]]);const Gt={data(){return{errorMessage:"",selectedTest:null,selectedCulture:null,selectedPackage:null,selectedtestGroup:null,search_pationt:"",isNameShow:!1,isCodeShow:!1,isphoneShow:!1,perceInput:!1,showTests:[],valueInput:!1,paid:0,index:0,Contract_payment:0,paymentPercent:0,price_list_id:null,showInlineMessage:!0,showAddPatientForm:!1,age_unitErrormessage:"",genderErrormessage:"",titleErrormessage:"",isAddPatientNameShow:!1,selectedPatientFromSearch:null}},components:{printInvoiceModal:xt},computed:{...E(Ke,["hideLoading"]),...E(Ne,["resultStatus"]),...E(K,["record","dialog","searchNameTotalCount","searchCodeTotalCount","searchPhoneTotalCount","searchRecords","discountPercentage","discountValue","testQuesions","tests_ids","payment_details","selectedContract","selectedTests","selectedPackages","selectedtestGroups","selectedCultures","selectedreferal","selectedCollector","patient","printInvoiceDialog","printRecord"]),...E(We,["testGroups"]),...E($e,{patientRecord:"record",responseData:"responseData",selectedFile:"selectedFile",AgeUnits:"AgeUnits",genders:"genders"}),...E(Qe,["nationalities"]),...E(Oe,["titles"]),...E(Ge,["tests","pagination"]),...Je(Ie,["lab"]),...E(Ie,["collectorsList"]),...E(ze,["records"]),...E(Fe,["contracts","contractsList"]),...E(xe,["cultures"]),...E(Ae,["packagesList"]),...E(Ee,["paymentMethods"]),Contract_discount_percentage(){var e,l;return((l=(e=this.contractsList)==null?void 0:e.find(r=>r.id===this.record.contract_id_fk))==null?void 0:l.discount_percentage)??0},items(){return this.tests},fromLap(){return this.records.filter(e=>e.role==="Lab")},theDoctorReferal(){return this.records.filter(e=>e.role==="Doctor")},payment_percent(){var e,l;return((l=(e=this.contractsList)==null?void 0:e.find(r=>r.id===this.record.contract_id_fk))==null?void 0:l.payment_percent)??0},commission(){var e,l;return((l=(e=this.records)==null?void 0:e.find(r=>r.id===this.record.referral_id_fk))==null?void 0:l.commission)??0},maximumInvoiceAmount(){var e,l;return((l=(e=this.contractsList)==null?void 0:e.find(r=>r.id===this.record.contract_id_fk))==null?void 0:l.maximum_payment_per_invoice)??0},price_list_id_fk(){return this.price_list_id},Sample_collection_fees(){var e,l;return((l=(e=this.collectorsList)==null?void 0:e.find(r=>r.id===this.record.sample_collector_id_fk))==null?void 0:l.commission)??0},discountedTotal(){const e=this.baseTotal+this.payment_percent+this.commission+this.Sample_collection_fees-this.discountValue;return e>0?e:0},baseTotal(){var y,g,M,F;const e=v=>{if(!this.price_list_id_fk||!v.prices)return v.price||v.original_price||0;const $=v.prices.find(L=>L.price_list_id===this.price_list_id_fk);return($==null?void 0:$.price_for_customer)||v.price||v.original_price||0},l=(y=this.selectedTests)==null?void 0:y.map(v=>({...v,thePrice:e(v)})),r=(g=this.selectedCultures)==null?void 0:g.map(v=>({...v,thePrice:e(v)})),p=(M=this.selectedPackages)==null?void 0:M.map(v=>({...v,thePrice:e(v)})),i=(F=this.selectedtestGroups)==null?void 0:F.map(v=>{var U,B;const $=((U=v.tests)==null?void 0:U.reduce((z,x)=>z+e(x),0))||0,L=((B=v.culture)==null?void 0:B.reduce((z,x)=>z+e(x),0))||0;return{...v,thePrice:e(v)+$+L}}),s=(l==null?void 0:l.reduce((v,$)=>v+$.thePrice,0))||0,u=(r==null?void 0:r.reduce((v,$)=>v+$.thePrice,0))||0,f=(p==null?void 0:p.reduce((v,$)=>v+$.thePrice,0))||0,_=(i==null?void 0:i.reduce((v,$)=>v+$.thePrice,0))||0;return s+u+f+_},due(){const e=this.record.total-this.the_paid;return e>0?e:0},the_paid(){return this.payment_details.reduce((e,l)=>e+l.amount,0)},ErrorMessage(){return this.errorMessage}},created(){const e=new Date,l=e.getFullYear(),r=String(e.getMonth()+1).padStart(2,"0"),p=String(e.getDate()).padStart(2,"0");this.record.registration_date=`${l}-${r}-${p}`},mounted(){this.Getlabs(),this.collectors(),this.GetContracts(),this.GetRecords(),this.GetTests(),this.Getcultures(),this.Getpackages(),this.GetpaymentMethods(),this.GetresultStatus(),this.GettestGroups(),this.GetAgeUnits(),this.GetGenders(),this.GetTitles(),this.GetNationalities()},methods:{...H(We,["GettestGroups"]),...H(Ee,["GetpaymentMethods"]),...H(Ne,["GetresultStatus"]),...H(Ge,["GetTests"]),...H(K,["Addinvoices","Updateinvoices","searchByname","searchBycode","searchByPhone","questions"]),...H(Ie,["Getlabs","collectors"]),...H(ze,["GetRecords"]),...H(Fe,["GetContracts"]),...H(Ae,["Getpackages"]),...H(xe,["Getcultures"]),...H($e,["AddPatient","GetAgeUnits","GetGenders","searchByname"]),...H(Qe,["GetNationalities"]),...H(Oe,["GetTitles"]),cheackMaximunInvoice(){this.getTotal(),this.record.total>this.maximumInvoiceAmount?(this.errorMessage=this.t("MUximunInvoiceError"),this.record.contract_id_fk=null):this.errorMessage=""},toggleAddPatientForm(){this.showAddPatientForm=!this.showAddPatientForm,this.showAddPatientForm&&(this.clearObjectValues(this.patientRecord),this.age_unitErrormessage="",this.genderErrormessage="",this.titleErrormessage="",this.isAddPatientNameShow=!1,this.selectedPatientFromSearch=null)},searchPatientByname(e){e?(this.searchByname(e),this.isAddPatientNameShow=!0):this.isAddPatientNameShow=!1},createNewPatient(){this.patientRecord.age_unit_id_fk&&this.patientRecord.gender_type_id_fk&&this.patientRecord.title_id_fk?(this.patientRecord.phone_number=this.patientRecord.phone,this.patientRecord.gender_id_fk=this.patientRecord.gender_type_id_fk,this.AddPatient().then(()=>{this.alertSuccess(this.t("alertSuccess")),this.patient=this.responseData,this.showAddPatientForm=!1,this.clearObjectValues(this.patientRecord)}),this.age_unitErrormessage="",this.genderErrormessage="",this.titleErrormessage=""):this.patientRecord.age_unit_id_fk==""||this.patientRecord.age_unit_id_fk==null?this.age_unitErrormessage=this.t("errorMessage"):this.patientRecord.gender_type_id_fk==""||this.patientRecord.gender_type_id_fk==null?this.genderErrormessage=this.t("errorMessage"):(this.patientRecord.title_id_fk==""||this.patientRecord.title_id_fk==null)&&(this.titleErrormessage=this.t("errorMessage"))},onFileChange(e){this.selectedFile=e.target.files[0]},changeTitle(e){e!==""&&e!=null&&(this.genderErrormessage=""),e==1?this.patientRecord.title_id_fk=1:this.patientRecord.title_id_fk=2},ageChange(e){e!==""&&e!=null&&(this.age_unitErrormessage="")},titleChange(e){e!==""&&e!=null&&(this.titleErrormessage="")},async fetchTests(e){try{const{data:l}=await Ye.get("/tests",{params:{name:e}});this.tests=l.data}catch(l){console.error("Error fetching tests:",l)}},onFilter(e){const l=e.value;clearTimeout(this.searchTimeout),this.searchTimeout=setTimeout(()=>{this.fetchTests(l)},300)},addSelection(e,l){console.log(e,l)},onScroll(e){console.log("Scrolled!",e)},toggletests(e){this.showTests[e]=!this.showTests[e]},toggleCultures(e){this.showCultures[e]=!this.showCultures[e]},removeTest(e,l){const r=this.selectedPackages[e];r.tests=r.tests.filter(p=>p.id!==l)},removeCulture(e,l){const r=this.selectedPackages[e];r.cultures=r.cultures.filter(p=>p.id!==l)},checkpaid(e){this.index=e},price_list(e){var l,r;this.price_list_id=((r=(l=this.fromLap)==null?void 0:l.find(p=>p.id===e))==null?void 0:r.price_list_id_fk)??null},addRecord(){this.clearObjectValues(this.patientRecord),this.thepatientDialog=!0},updateDiscountFromPercentage(){this.perceInput=!0,this.valueInput=!1},updateDiscountFromValue(){this.valueInput=!0,this.perceInput=!1},getTotal(){this.record.total=this.baseTotal+this.payment_percent+this.commission+this.Sample_collection_fees-this.discountValue},show_Questions(){this.tests_ids=this.selectedTests.map(e=>e.id??e.test_id_fk),this.tests_ids.length>0&&this.questions()},addSelection(e,l){var r,p;if(l){const i={...l,thePrice:this.price_list_id_fk?(p=(r=l.prices)==null?void 0:r.find(s=>s.price_list_id===this.price_list_id_fk))==null?void 0:p.price_for_customer:l.price};e==="test"?this.selectedTests.find(u=>u.id===l.id)||this.selectedTests.push(i):e==="package"?this.selectedPackages.find(u=>u.id===l.id)||this.selectedPackages.push(i):e==="culture"?this.selectedCultures.find(u=>u.id===l.id)||this.selectedCultures.push(i):e==="testGroup"&&(this.selectedtestGroups.find(u=>u.id===l.id)||this.selectedtestGroups.push(i))}},getComponentType(e){switch(e){case"number":return"input";case"text":return"input";case"checkbox":return"checkbox";case"selections":return"Dropdown";default:return"input"}},handleQuestionChange(e){e.selectedQuestion&&(e.selectedQuestion.answer=null)},removeSelection(e,l){e==="test"?this.selectedTests.splice(l,1):e==="package"?this.selectedPackages.splice(l,1):e==="culture"?this.selectedCultures.splice(l,1):e==="testGroup"&&this.selectedtestGroups.splice(l,1)},searchitemByname(e){e?(this.searchByname(e),this.isNameShow=!0,this.showInlineMessage=!0,setTimeout(()=>{this.showInlineMessage=!1},3e3)):this.isNameShow=!1},searchitemBycode(e){e?(this.searchBycode(e),this.isCodeShow=!0):this.isCodeShow=!1},searchitemByphone(e){e?(this.searchByPhone(e),this.isphoneShow=!0):this.isphoneShow=!1},create(){var l,r,p,i,s;this.record.patient_id_fk=((l=this.patient)==null?void 0:l.id)??null,this.record.show_patient_card_id=!!((r=this.record)!=null&&r.show_patient_card_id),this.record.show_result_date=!!((p=this.record)!=null&&p.show_result_date),this.record.show_patient_pic=!!((i=this.record)!=null&&i.show_patient_pic),this.record.payment_details=this.payment_details,this.perceInput?(this.record.discount=this.discountPercentage,this.record.discount_type_id_fk=2):this.valueInput?(this.record.discount=this.discountValue,this.record.discount_type_id_fk=3):(this.record.discount=null,this.record.discount_type_id_fk=null);const e=u=>{if(!this.price_list_id_fk||!u.prices)return u.price||u.original_price||0;const f=u.prices.find(_=>_.price_list_id===this.price_list_id_fk);return(f==null?void 0:f.price_for_customer)||u.price||u.original_price||0};this.record.tests=(s=this.selectedTests)==null?void 0:s.map(u=>{var f;return{test_id_fk:u.id,price:e(u),result:u.result??null,comment:u.comment??null,result_status_id_fk:u.result_status_id_fk??null,to_lab_id_fk:null,is_sample_received:u.is_sample_received??!1,questions:(f=this.testQuesions)==null?void 0:f.filter(_=>_.test_id_fk===u.id).flatMap(_=>{var y;return(y=_.questions)==null?void 0:y.map(g=>({question:{id:g.id,question:g.question,answer_type:g.answer_type,answer_type_id_fk:g.answer_type_id_fk,answer_type_selection_values:g.answer_type_selection_values},answer:g.answer_type=="checkbox"?!!g.answer:g.answer}))})}}),this.record.cultures=this.selectedCultures.map(u=>({culture_id_fk:u.id,price:e(u),result:u.result??null,comment:u.comment??null,result_status_id_fk:u.result_status_id_fk??null,to_lab_id_fk:u.lab??null,is_sample_received:u.is_sample_received??!1,questions:null})),this.record.packages=this.selectedPackages.map(u=>({package_id_fk:u.id,price:e(u),result:u.result??null,package_tests:u.tests??[],package_cultures:u.cultures??[],comment:u.comment??null,result_status_id_fk:u.result_status_id_fk??null,to_lab_id_fk:u.to_lab??null,is_sample_received:test.is_sample_received??!1,questions:null})),this.record.test_groups=this.selectedtestGroups.map(u=>({test_group_id_fk:u.id,price:e(u),result:u.result??null,test_group_tests:u.tests??[],test_group_cultures:u.culture??[],comment:u.comment??null,result_status_id_fk:u.result_status_id_fk??null,to_lab_id_fk:u.to_lab??null,is_sample_received:u.is_sample_received??!1,questions:null})),this.Addinvoices().then(()=>{this.dialog=!1,this.alertSuccess(this.t("alertSuccess")),this.clearObjectValues(this.record)})},addRow(){this.payment_details.push({amount:null,contract_id_fk:null,payment_method_id_fk:null})},removeRow(e){this.payment_details.splice(e,1)},update(){var e,l,r,p,i;this.record.patient_id_fk=((e=this.patient)==null?void 0:e.id)??null,this.record.show_patient_card_id=!!((l=this.record)!=null&&l.show_patient_card_id),this.record.show_result_date=!!((r=this.record)!=null&&r.show_result_date),this.record.show_patient_pic=!!((p=this.record)!=null&&p.show_patient_pic),this.record.payment_details=this.payment_details,this.perceInput?(this.record.discount=this.discountPercentage,this.record.discount_type_id_fk=2):this.valueInput?(this.record.discount=this.discountValue,this.record.discount_type_id_fk=3):(this.record.discount=null,this.record.discount_type_id_fk=null),this.record.tests=(i=this.selectedTests)==null?void 0:i.map(s=>{var u;return{test_id_fk:s.id??s.test_id_fk,result:s.result??null,comment:s.comment??null,result_status_id_fk:s.result_status_id_fk??null,to_lab_id_fk:null,price:s.price??null,is_sample_received:s.is_sample_received??!1,questions:(u=this.testQuesions)==null?void 0:u.filter(f=>f.test_id_fk===s.id).flatMap(f=>{var _;return(_=f.questions)==null?void 0:_.map(y=>({question:{id:y.id,question:y.question,answer_type:y.answer_type,answer_type_id_fk:y.answer_type_id_fk,answer_type_selection_values:y.answer_type_selection_values},answer:y.answer_type=="checkbox"?!!y.answer:y.answer}))})}}),this.record.cultures=this.selectedCultures.map(s=>({culture_id_fk:s.id??s.culture_id_fk,result:s.result??null,comment:s.comment??null,result_status_id_fk:s.result_status_id_fk??null,to_lab_id_fk:s.lab??null,price:s.price??null,is_sample_received:s.is_sample_received??!1,questions:null})),this.record.packages=this.selectedPackages.map(s=>{var u,f,_,y;return{package_id_fk:s.id,result:s.result??null,package_tests:s.tests??[],package_cultures:s.cultures??[],comment:s.comment??null,result_status_id_fk:s.result_status_id_fk??null,to_lab_id_fk:s.to_lab??null,price:this.price_list_id_fk&&(f=(u=s.prices)==null?void 0:u.find(g=>g.price_list_id==price_list_id_fk))!=null&&f.price_for_customer?(y=(_=s.prices)==null?void 0:_.find(g=>g.price_list_id==price_list_id_fk))==null?void 0:y.price_for_customer:s==null?void 0:s.price,is_sample_received:s.is_sample_received??!1,questions:null}}),this.record.test_groups=this.selectedtestGroups.map(s=>{var u,f,_,y;return{test_group_id_fk:s.id,result:s.result??null,test_group_tests:s.tests??[],test_group_cultures:s.culture??[],comment:s.comment??null,result_status_id_fk:s.result_status_id_fk??null,to_lab_id_fk:s.to_lab??null,price:this.price_list_id_fk&&(f=(u=s.prices)==null?void 0:u.find(g=>g.price_list_id==price_list_id_fk))!=null&&f.price_for_customer?(y=(_=s.prices)==null?void 0:_.find(g=>g.price_list_id==price_list_id_fk))==null?void 0:y.price_for_customer:s==null?void 0:s.price,is_sample_received:s.is_sample_received??!1,questions:null}}),this.Updateinvoices().then(()=>{this.dialog=!1,this.alertSuccess(this.t("alertSuccess")),this.clearObjectValues(this.record)})},close(){this.selectedContract=[],this.selectedreferal=[],this.selectedTests=[],this.selectedPackages=[],this.selectedCultures=[],this.payment_details=[{amount:null,contract_id_fk:null,payment_method_id_fk:null}],this.patient=[],this.discountPercentage=0,this.discountValue=0,this.errorMessage="",this.clearObjectValues(this.record),this.dialog=!1},onScroll(e){e.target.scrollHeight-e.target.scrollTop===e.target.clientHeight&&this.pagination.current_page<this.pagination.last_page&&(this.pagination.current_page++,this.GetTests())}},watch:{baseTotal:function(e){this.record.sub_total=e,this.record.total=this.baseTotal-this.discountValue,this.record.total=this.record.total>0?this.record.total:0},"record.total":function(e){this.record.total=this.discountedTotal>0?this.discountedTotal:0},discountPercentage:function(e){this.discountPercentage!==null&&this.discountPercentage>=0?(this.discountValue=this.baseTotal*this.discountPercentage/100,this.record.total=this.baseTotal-this.discountValue,this.record.total=this.record.total>0?this.record.total:0):this.discountValue=0},discountValue:function(e){this.discountValue!==null&&this.discountValue>=0?this.discountPercentage=this.discountValue/this.baseTotal*100:this.discountPercentage=0},patient:function(e){e&&(this.isNameShow=!1,this.isCodeShow=!1,this.isphoneShow=!1)},"record.from_lab_id_fk":function(e){var l,r;this.price_list_id=((r=(l=this.fromLap)==null?void 0:l.find(p=>p.id===e))==null?void 0:r.price_list_id_fk)??null},selectedPatientFromSearch:function(e){e&&(this.isAddPatientNameShow=!1,this.patientRecord={...e})}}},At={class:"details"},Et={class:"add d-flex"},Nt={key:0,class:"add-patient-form mb-3"},Wt={class:"grid mt-1"},Qt={class:"col-6 pb-0 input-name"},Ot={class:"block text-md mb-2"},Ht={style:{position:"relative"}},qt={class:"ul"},Jt={class:"col-6 pb-0"},Yt={class:"block text-md mb-2"},Kt={class:"col-6 pb-0"},Xt={class:"block text-md mb-2"},Zt={class:"col-6 pb-0"},el={class:"block text-md mb-2"},tl={class:"col-4 pb-0"},ll={class:"block text-md mb-2"},nl={class:"col-4 pb-0"},sl={class:"block text-md mb-2"},ol={class:"col-4 pb-0"},il={class:"block text-md mb-2"},al={class:"col-6 pb-0"},dl={class:"block text-md mb-2"},rl={class:"col-6 pb-0"},ul={class:"block text-md mb-2"},pl={class:"col-6 pb-0"},cl={class:"block text-md mb-2"},ml={class:"col-6 pb-0"},hl={class:"block text-md mb-2"},bl={class:"col-6 pb-0"},fl={class:"block text-md mb-2"},gl={class:"col-6 pb-0"},_l={class:"block text-md mb-2"},yl={class:"col-6 pb-0",style:{display:"flex","align-items":"flex-end"}},vl={class:"col-6 pb-0"},wl={class:"block text-md mb-2"},kl={key:1,class:"grid mt-1"},Vl={class:"col-3 col-md-3 pb-0 input-name"},Rl={class:"block text-md mb-2"},Cl={style:{position:"relative"}},Pl={class:"ul"},$l={class:"col-3 pb-0 col-md-3input-name"},Tl={class:"block text-md mb-2"},Sl={style:{position:"relative"}},Ul={class:"ul"},Il={class:"col-3 pb-0 col-md-3 input-name"},Ml={class:"block text-md mb-2"},Fl={style:{position:"relative"}},Dl={class:"ul"},Ll={class:"col-1 pb-0"},jl={class:"block text-md mb-2"},Bl={class:"col-1 pb-0"},zl={class:"block text-md mb-2"},xl={class:"col-3 pb-0 checkBox"},Gl={class:"block text-md mr-2 ml-2"},Al={class:"col-3 pb-0 checkBox"},El={class:"block text-md mr-2 ml-2"},Nl={class:"details"},Wl={class:"add d-flex"},Ql={class:"dropdowns",style:{width:"100%",display:"flex","justify-content":"space-between"}},Ol={key:0,class:"table"},Hl={key:1,class:"allQuestions"},ql={class:"questions"},Jl={class:"cultures"},Yl={key:0,class:"table"},Kl={key:0,class:"table"},Xl=["onClick"],Zl={key:0,class:"table"},en=["onClick"],tn={class:"details"},ln={class:"add d-flex"},nn={class:"grid mt-1"},sn={class:"col-3 pb-0"},on={class:"block text-md mb-2"},an={class:"col-3 pb-0"},dn={class:"block text-md mb-2"},rn={class:"col-3 pb-0"},un={class:"block text-md mb-2"},pn={class:"col-3 pb-0"},cn={class:"block text-md mb-2"},mn={class:"col-3 pb-0"},hn={class:"block text-md mb-2"},bn={class:"details"},fn={class:"add d-flex"},gn={class:"add_payment"},_n={class:"mt-1"},yn={key:0,class:"table"},vn={class:"details"},wn={class:"add d-flex"},kn={class:""},Vn={class:"grid mt-1"},Rn={class:"col-3 pb-0"},Cn={class:"block text-md mb-2"},Pn={class:"col-3 pb-0"},$n={class:"block text-md mb-2"},Tn={class:"col-3 pb-0"},Sn={class:"block text-md mb-2"},Un={class:"col-3 pb-0"},In={class:"block text-md mb-2"},Mn={class:"col-3 pb-0"},Fn={class:"block text-md mb-2"},Dn={class:"col-4 pb-0"},Ln={class:"block text-md mb-2"},jn={style:{display:"flex"}},Bn={class:"prece"},zn={class:"col-4 pb-0"},xn={class:"block text-md mb-2"},Gn={style:{display:"flex"}},An={class:"prece"},En={class:"col-3 pb-0"},Nn={class:"block text-md mb-2"},Wn={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function Qn(e,l,r,p,i,s){var x;const u=P("Button"),f=P("InputText"),_=P("Listbox"),y=P("InlineMessage"),g=P("Dropdown"),M=P("Message"),F=P("Checkbox"),v=P("tabel"),$=P("TabPanel"),L=P("TabView"),U=P("InputNumber"),B=P("Dialog"),z=P("printInvoiceModal");return d(),c(S,null,[a(B,{visible:e.dialog,"onUpdate:visible":l[65]||(l[65]=o=>e.dialog=o),modal:"",header:(x=e.record)!=null&&x.id?e.t("update"):e.t("add"),style:Te([{width:"90rem"},e.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:T(()=>[t("form",{onSubmit:l[64]||(l[64]=Xe(o=>e.record.id?s.update():s.create(),["prevent"])),class:"border-top-1 border-bluegray-100"},[l[87]||(l[87]=t("br",null,null,-1)),t("div",At,[t("div",Et,[t("h3",null,n(e.t("Patient_details")),1),a(u,{size:"small",class:X(i.showAddPatientForm?"p-button-danger":"p-button-success"),icon:i.showAddPatientForm?"pi pi-times":"pi pi-plus",label:i.showAddPatientForm?e.t("cancel"):e.t("addPatient"),onClick:s.toggleAddPatientForm},null,8,["class","icon","label","onClick"])]),l[67]||(l[67]=t("hr",null,null,-1)),i.showAddPatientForm?(d(),c("div",Nt,[t("div",Wt,[t("div",Qt,[t("label",Ot,n(e.t("name")),1),t("div",Ht,[a(f,{class:"w-full",placeholder:e.t("name"),required:"",type:"text",modelValue:e.patientRecord.name,"onUpdate:modelValue":l[0]||(l[0]=o=>e.patientRecord.name=o),onInput:l[1]||(l[1]=o=>s.searchPatientByname(e.patientRecord.name))},null,8,["placeholder","modelValue"]),A(t("i",{class:X(["pi pi-spin pi-spinner",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,e.hideLoading&&i.isAddPatientNameShow]])]),A(t("div",qt,[e.searchNameTotalCount>0?(d(),N(_,{key:0,listStyle:"max-height:250px",modelValue:i.selectedPatientFromSearch,"onUpdate:modelValue":l[2]||(l[2]=o=>i.selectedPatientFromSearch=o),options:e.searchRecords,optionLabel:"name",class:"w-full md:w-56 list"},null,8,["modelValue","options"])):A((d(),N(y,{key:1,class:"w-full",severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1},512)),[[J,e.hideLoading]])],512),[[J,i.isAddPatientNameShow]])]),t("div",Jt,[t("label",Yt,n(e.t("email")),1),a(f,{class:"w-full",type:"email",modelValue:e.patientRecord.email,"onUpdate:modelValue":l[3]||(l[3]=o=>e.patientRecord.email=o)},null,8,["modelValue"])]),t("div",Kt,[t("label",Xt,n(e.t("phone_number")),1),a(f,{minlength:"11",maxlength:"11",class:"w-full",type:"number",modelValue:e.patientRecord.phone,"onUpdate:modelValue":l[4]||(l[4]=o=>e.patientRecord.phone=o)},null,8,["modelValue"])]),t("div",Zt,[t("label",el,n(e.t("dob")),1),a(f,{class:"w-full",type:"date",modelValue:e.patientRecord.dob,"onUpdate:modelValue":l[5]||(l[5]=o=>e.patientRecord.dob=o)},null,8,["modelValue"])]),t("div",tl,[t("label",ll,n(e.t("age")),1),a(f,{required:"",class:"w-full",type:"number",modelValue:e.patientRecord.age,"onUpdate:modelValue":l[6]||(l[6]=o=>e.patientRecord.age=o)},null,8,["modelValue"])]),t("div",nl,[t("label",sl,n(e.t("age_unit")),1),a(g,{required:"",class:"w-full",modelValue:e.patientRecord.age_unit_id_fk,"onUpdate:modelValue":l[7]||(l[7]=o=>e.patientRecord.age_unit_id_fk=o),options:e.AgeUnits,optionLabel:"label",optionValue:"value",onChange:l[8]||(l[8]=o=>s.ageChange(e.patientRecord.age_unit_id_fk))},null,8,["modelValue","options"]),i.age_unitErrormessage?(d(),N(M,{key:0,severity:"error"},{default:T(()=>[R(n(i.age_unitErrormessage),1)]),_:1})):D("",!0)]),t("div",ol,[t("label",il,n(e.t("gender")),1),a(g,{required:"",class:"w-full",modelValue:e.patientRecord.gender_type_id_fk,"onUpdate:modelValue":l[9]||(l[9]=o=>e.patientRecord.gender_type_id_fk=o),options:e.genders,onChange:l[10]||(l[10]=o=>s.changeTitle(e.patientRecord.gender_type_id_fk)),optionLabel:"label",optionValue:"value"},null,8,["modelValue","options"]),i.genderErrormessage?(d(),N(M,{key:0,severity:"error"},{default:T(()=>[R(n(i.genderErrormessage),1)]),_:1})):D("",!0)]),t("div",al,[t("label",dl,n(e.t("title")),1),a(g,{required:"",class:"w-full",modelValue:e.patientRecord.title_id_fk,"onUpdate:modelValue":l[11]||(l[11]=o=>e.patientRecord.title_id_fk=o),options:e.titles,optionLabel:"label",optionValue:"value",onChange:l[12]||(l[12]=o=>s.titleChange(e.patientRecord.title_id_fk))},null,8,["modelValue","options"]),i.titleErrormessage?(d(),N(M,{key:0,severity:"error"},{default:T(()=>[R(n(i.titleErrormessage),1)]),_:1})):D("",!0)]),t("div",rl,[t("label",ul,n(e.t("nationality")),1),a(g,{filter:"",class:"w-full",modelValue:e.patientRecord.nationality_id_fk,"onUpdate:modelValue":l[13]||(l[13]=o=>e.patientRecord.nationality_id_fk=o),options:e.nationalities,optionLabel:"label",optionValue:"value"},null,8,["modelValue","options"])]),t("div",pl,[t("label",cl,n(e.t("national_id_no")),1),a(f,{class:"w-full",type:"text",modelValue:e.patientRecord.national_id_no,"onUpdate:modelValue":l[14]||(l[14]=o=>e.patientRecord.national_id_no=o)},null,8,["modelValue"])]),t("div",ml,[t("label",hl,n(e.t("address")),1),a(f,{class:"w-full",type:"text",modelValue:e.patientRecord.address,"onUpdate:modelValue":l[15]||(l[15]=o=>e.patientRecord.address=o)},null,8,["modelValue"])]),t("div",bl,[t("label",fl,n(e.t("passport_no")),1),a(f,{class:"w-full",type:"number",modelValue:e.patientRecord.passport_no,"onUpdate:modelValue":l[16]||(l[16]=o=>e.patientRecord.passport_no=o)},null,8,["modelValue"])]),t("div",gl,[t("label",_l,n(e.t("contract")),1),a(g,{class:"w-full",modelValue:e.patientRecord.contract_id_fk,"onUpdate:modelValue":l[17]||(l[17]=o=>e.patientRecord.contract_id_fk=o),options:e.contracts,optionLabel:"label",optionValue:"value"},null,8,["modelValue","options"])]),t("div",yl,[a(u,{size:"small",label:e.t("save"),icon:"pi pi-check",class:"p-button-success save-patient-btn",onClick:s.createNewPatient},null,8,["label","onClick"])]),t("div",vl,[t("label",wl,n(e.t("addfile")),1),a(f,{class:"w-full",type:"file",onChange:l[18]||(l[18]=o=>s.onFileChange(o)),accept:".png ,.jpg ,.jpeg"})])]),l[66]||(l[66]=t("hr",null,null,-1))])):(d(),c("div",kl,[t("div",Vl,[t("label",Rl,n(e.t("name")),1),t("div",Cl,[a(f,{filter:"",class:"w-full",placeholder:e.t("search"),required:"",type:"text",modelValue:e.patient.name,"onUpdate:modelValue":l[19]||(l[19]=o=>e.patient.name=o),onInput:l[20]||(l[20]=o=>{var m;return s.searchitemByname((m=e.patient)==null?void 0:m.name)})},null,8,["placeholder","modelValue"]),A(t("i",{class:X(["pi pi-spin pi-spinner",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,e.hideLoading&&i.isNameShow]]),A(t("i",{class:X(["pi pi-search",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,!e.hideLoading&&!i.isCodeShow]])]),A(t("div",Pl,[e.searchNameTotalCount>0?(d(),N(_,{key:0,listStyle:"max-height:250px",modelValue:e.patient,"onUpdate:modelValue":l[21]||(l[21]=o=>e.patient=o),options:e.searchRecords,optionLabel:"name",class:"w-full md:w-56 list"},null,8,["modelValue","options"])):i.showInlineMessage?(d(),N(y,{key:1,class:"w-full",severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1})):D("",!0)],512),[[J,i.isNameShow]])]),t("div",$l,[t("label",Tl,n(e.t("phone_number")),1),t("div",Sl,[a(f,{filter:"",class:"w-full",placeholder:e.t("search"),type:"text",modelValue:e.patient.phone,"onUpdate:modelValue":l[22]||(l[22]=o=>e.patient.phone=o),onInput:l[23]||(l[23]=o=>s.searchitemByphone(e.patient.phone))},null,8,["placeholder","modelValue"]),A(t("i",{class:X(["pi pi-spin pi-spinner",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,e.hideLoading&&i.isphoneShow]]),A(t("i",{class:X(["pi pi-search",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,!e.hideLoading&&!i.isphoneShow]])]),A(t("div",Ul,[e.searchPhoneTotalCount>0?(d(),N(_,{key:0,listStyle:"max-height:250px",modelValue:e.patient,"onUpdate:modelValue":l[24]||(l[24]=o=>e.patient=o),options:e.searchRecords,optionLabel:"name",class:"w-full md:w-56 list"},null,8,["modelValue","options"])):i.showInlineMessage?(d(),N(y,{key:1,class:"w-full",severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1})):D("",!0)],512),[[J,i.isphoneShow]])]),t("div",Il,[t("label",Ml,n(e.t("code")),1),t("div",Fl,[a(f,{filter:"",class:"w-full",placeholder:e.t("search"),required:"",type:"text",modelValue:e.patient.code,"onUpdate:modelValue":l[25]||(l[25]=o=>e.patient.code=o),onInput:l[26]||(l[26]=o=>s.searchitemBycode(e.patient.code))},null,8,["placeholder","modelValue"]),A(t("i",{class:X(["pi pi-spin pi-spinner",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,e.hideLoading&&i.isCodeShow]]),A(t("i",{class:X(["pi pi-search",e.lang=="ar"?"rtl":"ltr"])},null,2),[[J,!e.hideLoading&&!i.isCodeShow]])]),A(t("div",Dl,[e.searchCodeTotalCount>0?(d(),N(_,{key:0,listStyle:"max-height:250px",modelValue:e.patient,"onUpdate:modelValue":l[27]||(l[27]=o=>e.patient=o),options:e.searchRecords,optionLabel:"name",class:"w-full md:w-56 list"},null,8,["modelValue","options"])):i.showInlineMessage?(d(),N(y,{key:1,class:"w-full",severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1})):D("",!0)],512),[[J,i.isCodeShow]])]),t("div",Ll,[t("label",jl,n(e.t("age")),1),a(f,{class:"w-full",disabled:"",type:"text",modelValue:e.patient.age,"onUpdate:modelValue":l[28]||(l[28]=o=>e.patient.age=o)},null,8,["modelValue"])]),t("div",Bl,[t("label",zl,n(e.t("gender")),1),a(f,{class:"w-full",disabled:"",type:"text",modelValue:e.patient.gender,"onUpdate:modelValue":l[29]||(l[29]=o=>e.patient.gender=o)},null,8,["modelValue"])]),t("div",xl,[a(F,{modelValue:e.record.show_patient_card_id,"onUpdate:modelValue":l[30]||(l[30]=o=>e.record.show_patient_card_id=o),inputId:"ingredient2",binary:""},null,8,["modelValue"]),t("label",Gl,n(e.t("Show_passport_no")),1)]),t("div",Al,[a(F,{modelValue:e.record.show_patient_pic,"onUpdate:modelValue":l[31]||(l[31]=o=>e.record.show_patient_pic=o),inputId:"ingredient2",binary:""},null,8,["modelValue"]),t("label",El,n(e.t("Show_avatar")),1)])]))]),l[88]||(l[88]=t("br",null,null,-1)),t("div",Nl,[t("div",Wl,[t("h3",null,n(e.t("tests")),1)]),l[83]||(l[83]=t("hr",null,null,-1)),a(L,null,{default:T(()=>[a($,{header:e.t("tests")},{default:T(()=>{var o;return[t("div",null,[t("div",Ql,[a(g,{modelValue:i.selectedTest,"onUpdate:modelValue":l[32]||(l[32]=m=>i.selectedTest=m),options:e.tests,filter:!0,filterBy:"name",placeholder:e.t("select"),optionLabel:"name",onChange:l[33]||(l[33]=m=>s.addSelection("test",i.selectedTest)),onFilter:s.onFilter,onScroll:s.onScroll},null,8,["modelValue","options","placeholder","onFilter","onScroll"])]),l[69]||(l[69]=t("br",null,null,-1)),(o=e.selectedTests)!=null&&o.length?(d(),c("table",Ol,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("name")),1),t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("to_lab")),1),t("th",null,n(e.t("Sample_received")),1),t("th",null,[a(u,{label:"show Questions",class:"p-button-success",onClick:l[34]||(l[34]=m=>s.show_Questions())})])])]),t("tbody",null,[(d(!0),c(S,null,I(e.selectedTests,(m,k)=>{var h,w;return d(),c("tr",{key:k},[t("td",null,n(m.name),1),t("td",null,n(s.price_list_id_fk&&((w=(h=m.prices)==null?void 0:h.find(b=>b.price_list_id===s.price_list_id_fk))==null?void 0:w.price_for_customer)||m.price||m.original_price||0),1),t("td",null,[a(g,{modelValue:m.to_lab,"onUpdate:modelValue":b=>m.to_lab=b,options:s.fromLap,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),t("td",null,[a(F,{modelValue:m.is_sample_received,"onUpdate:modelValue":b=>m.is_sample_received=b,inputId:"ingredient3",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),t("td",null,[a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text",onClick:b=>s.removeSelection("test",k)},null,8,["onClick"])])])}),128))])])):D("",!0),l[70]||(l[70]=t("br",null,null,-1)),l[71]||(l[71]=t("hr",null,null,-1)),e.testQuesions.length>0?(d(),c("div",Hl,[t("div",ql,[a(v,{class:"table"},{default:T(()=>[t("thead",null,[t("th",null,n(e.t("question")),1),t("th",null,n(e.t("answer")),1)]),t("tbody",null,[(d(!0),c(S,null,I(e.testQuesions,(m,k)=>(d(),c("tr",{key:k},[t("td",null,[R(n(m.question)+" ",1),a(f,{class:"w-full",type:"text",modelValue:m.id,"onUpdate:modelValue":h=>m.id=h,hidden:"",disabled:""},null,8,["modelValue","onUpdate:modelValue"])]),t("td",null,[m.answer_type=="checkbox"?(d(),N(F,{key:0,modelValue:m.answer,"onUpdate:modelValue":h=>m.answer=h,inputId:"ingredient8",binary:""},null,8,["modelValue","onUpdate:modelValue"])):D("",!0),m.answer_type=="number"?(d(),N(f,{key:1,class:"w-full",required:"",type:"number",modelValue:m.answer,"onUpdate:modelValue":h=>m.answer=h},null,8,["modelValue","onUpdate:modelValue"])):D("",!0),m.answer_type=="date"?(d(),N(f,{key:2,class:"w-full",required:"",type:"date",modelValue:m.answer,"onUpdate:modelValue":h=>m.answer=h},null,8,["modelValue","onUpdate:modelValue"])):D("",!0),m.answer_type=="text"?(d(),N(f,{key:3,class:"w-full",required:"",type:"text",modelValue:m.answer,"onUpdate:modelValue":h=>m.answer=h},null,8,["modelValue","onUpdate:modelValue"])):D("",!0),m.answer_type=="selection"?(d(),N(g,{key:4,modelValue:m.answer,"onUpdate:modelValue":h=>m.answer=h,options:m.answer_type_selection_values,placeholder:e.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])):D("",!0)])]))),128))])]),_:1}),l[68]||(l[68]=t("br",null,null,-1))])])):D("",!0)])]}),_:1},8,["header"]),a($,{header:e.t("cultures")},{default:T(()=>{var o;return[t("div",null,[t("div",Jl,[a(g,{filter:"",modelValue:i.selectedCulture,"onUpdate:modelValue":l[35]||(l[35]=m=>i.selectedCulture=m),options:e.cultures,optionLabel:"name",onChange:l[36]||(l[36]=m=>s.addSelection("culture",i.selectedCulture)),placeholder:e.t("select")},null,8,["modelValue","options","placeholder"])]),l[74]||(l[74]=t("br",null,null,-1)),(o=e.selectedCultures)!=null&&o.length?(d(),c("table",Yl,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("name")),1),t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("to_lab")),1),t("th",null,n(e.t("Sample_received")),1),l[72]||(l[72]=t("th",null,null,-1)),l[73]||(l[73]=t("th",null,null,-1))])]),t("tbody",null,[(d(!0),c(S,null,I(e.selectedCultures,(m,k)=>{var h,w;return d(),c("tr",{key:k},[t("td",null,n(m.name),1),t("td",null,n(s.price_list_id_fk&&((w=(h=m.prices)==null?void 0:h.find(b=>b.price_list_id===s.price_list_id_fk))==null?void 0:w.price_for_customer)||m.price||m.original_price||0),1),t("td",null,[a(g,{modelValue:m.to_lab,"onUpdate:modelValue":b=>m.to_lab=b,options:s.fromLap,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),t("td",null,[a(F,{modelValue:m.is_sample_received,"onUpdate:modelValue":b=>m.is_sample_received=b,inputId:"ingredient3",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),t("td",null,[a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text",onClick:b=>s.removeSelection("culture",k)},null,8,["onClick"])])])}),128))])])):D("",!0)])]}),_:1},8,["header"]),a($,{header:e.t("packages")},{default:T(()=>{var o;return[t("div",null,[t("div",null,[a(g,{filter:"",modelValue:i.selectedPackage,"onUpdate:modelValue":l[37]||(l[37]=m=>i.selectedPackage=m),options:e.packagesList,optionLabel:"name",onChange:l[38]||(l[38]=m=>s.addSelection("package",i.selectedPackage)),placeholder:e.t("select")},null,8,["modelValue","options","placeholder"])]),l[78]||(l[78]=t("br",null,null,-1)),(o=e.selectedPackages)!=null&&o.length?(d(),c("table",Kl,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("name")),1),t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("to_lab")),1),t("th",null,n(e.t("Sample_received")),1),t("th",null,n(e.t("the_tests")),1),l[75]||(l[75]=t("th",null,null,-1))])]),t("tbody",null,[(d(!0),c(S,null,I(e.selectedPackages,(m,k)=>{var h,w;return d(),c(S,{key:k},[t("tr",null,[t("td",null,n(m.name),1),t("td",null,n(s.price_list_id_fk&&((w=(h=m.prices)==null?void 0:h.find(b=>b.price_list_id===s.price_list_id_fk))==null?void 0:w.price_for_customer)||m.price||m.original_price||0),1),t("td",null,[a(g,{modelValue:m.lab,"onUpdate:modelValue":b=>m.lab=b,options:s.fromLap,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),t("td",null,[a(F,{modelValue:m.is_sample_received,"onUpdate:modelValue":b=>m.is_sample_received=b,inputId:"ingredient3",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),t("td",null,[t("i",{class:X(i.showTests[k]?"pi pi-angle-up":"pi pi-angle-down"),style:{color:"slateblue","font-weight":"bold"},onClick:b=>s.toggletests(k)},null,10,Xl)]),t("td",null,[a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text",onClick:b=>s.removeSelection("package",k)},null,8,["onClick"])])]),(d(!0),c(S,null,I(m.tests,b=>A((d(),c("tr",{style:{background:"#3b82f62b"},key:`test-${b.id}`},[t("td",null,n(b.name),1),t("td",null,n(b.price),1),t("td",null,n(e.t("tests")),1),l[76]||(l[76]=t("td",{colspan:"2"},null,-1)),a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text p-button-sm",onClick:W=>s.removeTest(k,b.id)},null,8,["onClick"])])),[[J,i.showTests[k]]])),128)),(d(!0),c(S,null,I(m.cultures,b=>A((d(),c("tr",{style:{background:"#3b82f62b"},key:`test-${b.id}`},[t("td",null,n(b.name),1),t("td",null,n(b.price),1),t("td",null,n(e.t("cultures")),1),l[77]||(l[77]=t("td",{colspan:"2"},null,-1)),a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text p-button-sm",onClick:W=>s.removeCulture(k,b.id)},null,8,["onClick"])])),[[J,i.showTests[k]]])),128))],64)}),128))])])):D("",!0)])]}),_:1},8,["header"]),a($,{header:e.t("test-groups")},{default:T(()=>{var o;return[t("div",null,[t("div",null,[a(g,{filter:"",modelValue:i.selectedtestGroup,"onUpdate:modelValue":l[39]||(l[39]=m=>i.selectedtestGroup=m),options:e.testGroups,optionLabel:"group_name",onChange:l[40]||(l[40]=m=>s.addSelection("testGroup",i.selectedtestGroup)),placeholder:e.t("select")},null,8,["modelValue","options","placeholder"])]),l[82]||(l[82]=t("br",null,null,-1)),(o=e.selectedtestGroups)!=null&&o.length?(d(),c("table",Zl,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("name")),1),t("th",null,n(e.t("Original_Price")),1),t("th",null,n(e.t("to_lab")),1),t("th",null,n(e.t("Sample_received")),1),t("th",null,n(e.t("the_tests")),1),l[79]||(l[79]=t("th",null,null,-1))])]),t("tbody",null,[(d(!0),c(S,null,I(e.selectedtestGroups,(m,k)=>{var h,w;return d(),c(S,{key:k},[t("tr",null,[t("td",null,n(m.group_name),1),t("td",null,n(s.price_list_id_fk&&((w=(h=m.prices)==null?void 0:h.find(b=>b.price_list_id===s.price_list_id_fk))==null?void 0:w.price_for_customer)||m.original_price||0),1),t("td",null,[a(g,{modelValue:m.to_lab,"onUpdate:modelValue":b=>m.to_lab=b,options:s.fromLap,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),t("td",null,[a(F,{modelValue:m.is_sample_received,"onUpdate:modelValue":b=>m.is_sample_received=b,inputId:"ingredient3",binary:""},null,8,["modelValue","onUpdate:modelValue"])]),t("td",null,[t("i",{class:X(i.showTests[k]?"pi pi-angle-up":"pi pi-angle-down"),style:{color:"slateblue","font-weight":"bold"},onClick:b=>s.toggletests(k)},null,10,en)]),t("td",null,[a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text",onClick:b=>s.removeSelection("testGroup",k)},null,8,["onClick"])])]),(d(!0),c(S,null,I(m.tests,b=>A((d(),c("tr",{style:{background:"#3b82f62b"},key:`test-${b.id}`},[t("td",null,n(b.name),1),t("td",null,n(b.price),1),t("td",null,n(e.t("tests")),1),l[80]||(l[80]=t("td",{colspan:"2"},null,-1)),a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text p-button-sm",onClick:W=>s.removeTest(k,b.id)},null,8,["onClick"])])),[[J,i.showTests[k]]])),128)),(d(!0),c(S,null,I(m.culture,b=>A((d(),c("tr",{style:{background:"#3b82f62b"},key:`test-${b.id}`},[t("td",null,n(b.name),1),t("td",null,n(b.price),1),t("td",null,n(e.t("cultures")),1),l[81]||(l[81]=t("td",{colspan:"2"},null,-1)),a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text p-button-sm",onClick:W=>s.removeCulture(k,b.id)},null,8,["onClick"])])),[[J,i.showTests[k]]])),128))],64)}),128))])])):D("",!0)])]}),_:1},8,["header"])]),_:1})]),l[89]||(l[89]=t("br",null,null,-1)),t("div",tn,[t("div",ln,[t("h3",null,n(e.t("inovice_datails")),1)]),l[84]||(l[84]=t("hr",null,null,-1)),t("div",nn,[t("div",sn,[t("label",on,n(e.t("from_lab")),1),a(g,{style:{"text-align":"center"},showClear:"",class:"w-full",modelValue:e.record.from_lab_id_fk,"onUpdate:modelValue":l[41]||(l[41]=o=>e.record.from_lab_id_fk=o),options:s.fromLap,onChange:l[42]||(l[42]=o=>s.price_list(e.record.from_lab_id_fk)),optionValue:"id",optionLabel:"name",placeholder:e.t("select")},null,8,["modelValue","options","placeholder"])]),t("div",an,[t("label",dn,n(e.t("sample_collector")),1),a(g,{class:"w-full",style:{"text-align":"center"},showClear:"",modelValue:e.record.sample_collector_id_fk,"onUpdate:modelValue":l[43]||(l[43]=o=>e.record.sample_collector_id_fk=o),options:e.collectorsList,onChange:l[44]||(l[44]=o=>s.getTotal()),optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","options","placeholder"])]),t("div",rn,[t("label",un,n(e.t("contract")),1),a(g,{class:"w-full",style:{"text-align":"center"},showClear:"",modelValue:e.record.contract_id_fk,"onUpdate:modelValue":l[45]||(l[45]=o=>e.record.contract_id_fk=o),onChange:l[46]||(l[46]=o=>s.cheackMaximunInvoice()),options:e.contractsList,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","options","placeholder"]),s.ErrorMessage?(d(),N(M,{key:0,severity:"warn"},{default:T(()=>[R(n(s.ErrorMessage),1)]),_:1})):D("",!0)]),t("div",pn,[t("label",cn,n(e.t("referrals")),1),a(g,{class:"w-full",style:{"text-align":"center"},showClear:"",modelValue:e.record.referral_id_fk,"onUpdate:modelValue":l[47]||(l[47]=o=>e.record.referral_id_fk=o),onChange:l[48]||(l[48]=o=>s.getTotal()),options:s.theDoctorReferal,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","options","placeholder"])]),t("div",mn,[t("label",hn,n(e.t("Registration_date")),1),a(f,{class:"w-full",type:"date",modelValue:e.record.registration_date,"onUpdate:modelValue":l[49]||(l[49]=o=>e.record.registration_date=o)},null,8,["modelValue"])])])]),l[90]||(l[90]=t("br",null,null,-1)),t("div",bn,[t("div",fn,[t("h3",gn,n(e.t("paymentMethod")),1)]),t("div",_n,[t("div",null,[e.payment_details?(d(),c("table",yn,[t("thead",null,[t("tr",null,[t("th",null,n(e.t("paymentMethod")),1),t("th",null,n(e.t("amount")),1),t("th",null,[a(u,{icon:"pi pi-plus",class:"p-button-rounded p-button-success p-button-text",onClick:l[50]||(l[50]=o=>s.addRow())})])])]),t("tbody",null,[(d(!0),c(S,null,I(e.payment_details,(o,m)=>(d(),c("tr",{key:m},[t("td",null,[a(g,{style:{"text-align":"center",width:"50%"},showClear:"",modelValue:o.payment_method_id_fk,"onUpdate:modelValue":k=>o.payment_method_id_fk=k,options:e.paymentMethods,optionLabel:"name",optionValue:"id",placeholder:e.t("select")},null,8,["modelValue","onUpdate:modelValue","options","placeholder"])]),t("td",null,[a(U,{class:"w-full",type:"number",modelValue:o.amount,"onUpdate:modelValue":k=>o.amount=k,modelModifiers:{number:!0},onInput:k=>s.checkpaid(m)},null,8,["modelValue","onUpdate:modelValue","onInput"])]),t("td",null,[a(u,{icon:"pi pi-trash",class:"p-button-rounded p-button-danger p-button-text",onClick:k=>s.removeRow(m)},null,8,["onClick"])])]))),128))])])):D("",!0)])])]),l[91]||(l[91]=t("br",null,null,-1)),t("div",vn,[t("div",wn,[t("h3",kn,n(e.t("Invoice_summary")),1)]),t("div",Vn,[t("div",Rn,[t("label",Cn,n(e.t("Total")),1),a(U,{class:"w-full",type:"number",modelValue:e.record.total,"onUpdate:modelValue":l[51]||(l[51]=o=>e.record.total=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])]),t("div",Pn,[t("label",$n,n(e.t("Subtotal")),1),a(U,{class:"w-full",type:"number",modelValue:e.record.sub_total,"onUpdate:modelValue":l[52]||(l[52]=o=>e.record.sub_total=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])]),t("div",Tn,[t("label",Sn,n(e.t("Sample_collection_fees")),1),a(U,{class:"w-full",type:"number",modelValue:s.Sample_collection_fees,"onUpdate:modelValue":l[53]||(l[53]=o=>s.Sample_collection_fees=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])]),t("div",Un,[t("label",In,n(e.t("paid")),1),a(U,{class:"w-full",type:"number",modelValue:s.the_paid,"onUpdate:modelValue":l[54]||(l[54]=o=>s.the_paid=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])]),t("div",Mn,[t("label",Fn,n(e.t("Due")),1),a(U,{class:"w-full",type:"number",modelValue:s.due,"onUpdate:modelValue":l[55]||(l[55]=o=>s.due=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])]),t("div",Dn,[t("label",Ln,n(e.t("it_discount")),1),t("div",jn,[t("div",Bn,[a(U,{type:"number",modelValue:e.discountPercentage,"onUpdate:modelValue":l[56]||(l[56]=o=>e.discountPercentage=o),modelModifiers:{number:!0},onInput:l[57]||(l[57]=o=>s.updateDiscountFromPercentage())},null,8,["modelValue"]),l[85]||(l[85]=t("span",{class:"span"},"%",-1))]),a(U,{type:"number",modelValue:e.discountValue,"onUpdate:modelValue":l[58]||(l[58]=o=>e.discountValue=o),modelModifiers:{number:!0},onInput:l[59]||(l[59]=o=>s.updateDiscountFromValue())},null,8,["modelValue"])])]),t("div",zn,[t("label",xn,n(e.t("Contract_payment")),1),t("div",Gn,[t("div",An,[a(U,{type:"number",modelValue:s.Contract_discount_percentage,"onUpdate:modelValue":l[60]||(l[60]=o=>s.Contract_discount_percentage=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"]),l[86]||(l[86]=t("span",{class:"span"},"%",-1))]),a(U,{type:"number",modelValue:s.payment_percent,"onUpdate:modelValue":l[61]||(l[61]=o=>s.payment_percent=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])])]),t("div",En,[t("label",Nn,n(e.t("Referral_commission")),1),a(U,{type:"number",modelValue:s.commission,"onUpdate:modelValue":l[62]||(l[62]=o=>s.commission=o),modelModifiers:{number:!0},disabled:""},null,8,["modelValue"])])])]),t("div",Wn,[a(u,{size:"small",label:e.t("close"),severity:"danger",onClick:l[63]||(l[63]=o=>s.close())},null,8,["label"]),a(u,{size:"small",type:"submit",label:e.record.id?e.t("save"):e.t("add"),severity:"success"},null,8,["label"])])],32)]),_:1},8,["visible","header","style"]),a(z)],64)}const On=te(Gt,[["render",Qn],["__scopeId","data-v-fa59fcf5"]]);const Hn={computed:{...E(K,["patientdialog","patient"])},methods:{close(){this.patient=[],this.patientdialog=!1}},watch:{}},qn={key:0,class:"table table-bordered table-striped m-0"},Jn={nowrap:"nowrap"},Yn={nowrap:"nowrap"},Kn={nowrap:"nowrap"},Xn={nowrap:"nowrap"},Zn={nowrap:"nowrap"},es={nowrap:"nowrap"},ts={nowrap:"nowrap"},ls={nowrap:"nowrap"},ns={nowrap:"nowrap"},ss={nowrap:"nowrap"},os={nowrap:"nowrap"},is={nowrap:"nowrap",class:"direction-ltr"},as={nowrap:"nowrap"},ds={nowrap:"nowrap"},rs={key:1,class:"card flex justify-content-center mb-5"},us={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function ps(e,l,r,p,i,s){var y;const u=P("InlineMessage"),f=P("Button"),_=P("Dialog");return d(),N(_,{visible:e.patientdialog,"onUpdate:visible":l[1]||(l[1]=g=>e.patientdialog=g),modal:"",header:(y=e.patient)!=null&&y.name?e.patient.name:"",style:Te([{width:"50rem"},e.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:T(()=>{var g,M,F,v,$,L,U,B;return[t("div",null,[e.patient?(d(),c("table",qn,[t("tbody",null,[t("tr",null,[t("th",Jn,n(e.t("name")),1),t("td",Yn,n((g=e.patient)==null?void 0:g.name),1)]),t("tr",null,[t("th",Kn,n(e.t("national_id_no")),1),t("td",Xn,n((M=e.patient)==null?void 0:M.national_id_no),1)]),t("tr",null,[t("th",Zn,n(e.t("gender")),1),t("td",es,n((F=e.patient)==null?void 0:F.gender),1)]),t("tr",null,[t("th",ts,n(e.t("dob")),1),t("td",ls,n((v=e.patient)==null?void 0:v.dob),1)]),t("tr",null,[t("th",ns,n(e.t("age")),1),t("td",ss,n((($=e.patient)==null?void 0:$.age_unit)+" "+((L=e.patient)==null?void 0:L.age)),1)]),t("tr",null,[t("th",os,n(e.t("phone_number")),1),t("td",is,n((U=e.patient)==null?void 0:U.phone),1)]),t("tr",null,[t("th",as,n(e.t("address")),1),t("td",ds,n((B=e.patient)==null?void 0:B.address),1)])])])):(d(),c("div",rs,[a(u,{severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1})]))]),t("div",us,[a(f,{size:"small",label:e.t("close"),severity:"danger",onClick:l[0]||(l[0]=z=>s.close())},null,8,["label"])])]}),_:1},8,["visible","header","style"])}const cs=te(Hn,[["render",ps],["__scopeId","data-v-33899995"]]);const ms={computed:{...E(K,["Patient_due","Patient_dueDialog"])},methods:{close(){this.Patient_due=[],this.Patient_dueDialog=!1}},watch:{}},hs={key:0,class:"table table-bordered table-striped m-0"},bs={nowrap:"nowrap"},fs={nowrap:"nowrap"},gs={nowrap:"nowrap"},_s={nowrap:"nowrap"},ys={nowrap:"nowrap"},vs={nowrap:"nowrap"},ws={nowrap:"nowrap"},ks={nowrap:"nowrap"},Vs={key:1,class:"card flex justify-content-center mb-5"},Rs={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function Cs(e,l,r,p,i,s){const u=P("InlineMessage"),f=P("Button"),_=P("Dialog");return d(),N(_,{visible:e.Patient_dueDialog,"onUpdate:visible":l[1]||(l[1]=y=>e.Patient_dueDialog=y),modal:"",header:e.t("Patient_due"),style:Te([{width:"50rem"},e.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:T(()=>[t("div",null,[e.Patient_due?(d(),c("table",hs,[t("tbody",null,[t("tr",null,[t("th",bs,n(e.t("Subtotal")),1),t("td",fs,n(e.Patient_due.sub_total??0),1)]),t("tr",null,[t("th",gs,n(e.t("discount")),1),t("td",_s,n(e.Patient_due.discount??0),1)]),t("tr",null,[t("th",ys,n(e.t("Total")),1),t("td",vs,n(e.Patient_due.total??0),1)]),t("tr",null,[t("th",ws,n(e.t("Due")),1),t("td",ks,n(e.Patient_due.total-e.Patient_due.paid),1)])])])):(d(),c("div",Vs,[a(u,{severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1})]))]),t("div",Rs,[a(f,{size:"small",label:e.t("close"),severity:"danger",onClick:l[0]||(l[0]=y=>s.close())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const Ps=te(ms,[["render",Cs],["__scopeId","data-v-e902ddc2"]]);const $s={computed:{...E(K,["printRecord","WhatsUpDialog","Pdfurl"])},components:{printInvoice:He},methods:{...H(K,["pdf"]),...H($e,["whatsapp"]),async sendMsg(){var l,r,p;const e=document.getElementById("printInvoice").outerHTML;await this.pdf(e,(l=this.printRecord)==null?void 0:l.id),this.whatsapp((p=(r=this.printRecord)==null?void 0:r.patient)==null?void 0:p.phone,this.Pdfurl.path).then(i=>{this.alertSuccess(this.t("alertSuccess")),this.close()})},close(){this.WhatsUpDialog=!1}},watch:{}},Ts={class:"flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3"};function Ss(e,l,r,p,i,s){const u=P("printInvoice"),f=P("Button"),_=P("Dialog");return d(),N(_,{visible:e.WhatsUpDialog,"onUpdate:visible":l[2]||(l[2]=y=>e.WhatsUpDialog=y),modal:"",header:e.t("sendMessage"),style:Te([{width:"70rem"},e.lang=="en"?"direction:ltr ":"direction: rtl"])},{default:T(()=>[t("div",null,[a(u)]),t("div",Ts,[a(f,{size:"small",label:e.t("close"),severity:"danger",onClick:l[0]||(l[0]=y=>s.close())},null,8,["label"]),a(f,{size:"small",label:e.t("sendMessage"),severity:"success",onClick:l[1]||(l[1]=y=>s.sendMsg())},null,8,["label"])])]),_:1},8,["visible","header","style"])}const Us=te($s,[["render",Ss],["__scopeId","data-v-49ad28fd"]]);const Is={components:{BarcodeComponent:Se},computed:{...E(K,["printRecord"]),printAlone(){var e,l;return(l=(e=this.printRecord)==null?void 0:e.test_groups_all)==null?void 0:l.map(r=>{var p;return{name:r==null?void 0:r.name,category:r==null?void 0:r.category,testlength:(p=r==null?void 0:r.tests_print_alone)==null?void 0:p.length,tests_print_alone:r==null?void 0:r.tests_print_alone}})},NotprintAlone(){var e,l;return(l=(e=this.printRecord)==null?void 0:e.test_groups_all)==null?void 0:l.map(r=>{var p;return{name:r==null?void 0:r.name,category:r==null?void 0:r.category,testlength:(p=r==null?void 0:r.tests_not_print_alone)==null?void 0:p.length,tests_not_print_alone:r==null?void 0:r.tests_not_print_alone}})},cultures(){var e,l;return(l=(e=this.printRecord)==null?void 0:e.test_groups_all)==null?void 0:l.map(r=>{var p;return{name:r==null?void 0:r.name,category:r==null?void 0:r.category,culturesLength:(p=r==null?void 0:r.cultures)==null?void 0:p.length,cultures:r==null?void 0:r.cultures}})}},methods:{}},Ms={class:"report-container",id:"job"},Fs={class:"printPage"},Ds={class:"head"},Ls={class:"header"},js={class:"header-item"},Bs={style:{display:"flex","align-items":"center"}},zs={style:{"text-align":"center"}},xs={class:"header-item"},Gs={class:"header-item"},As={class:"header-item"},Es={class:"header"},Ns={class:"header-item"},Ws={style:{display:"flex","align-items":"center"}},Qs={style:{"text-align":"center"}},Os={class:"header-item"},Hs={class:"header-item"},qs={class:"header-item"},Js={class:"header"},Ys={class:"header-item"},Ks={class:"header-item"},Xs={class:"header-item"},Zs={class:"header-item"},eo={class:"tests-section"},to={key:0,class:"caption"},lo={key:1,class:"caption"},no={class:"tests-table"},so={class:"printPage"},oo={class:"head"},io={class:"header"},ao={class:"header-item"},ro={style:{display:"flex","align-items":"center"}},uo={style:{"text-align":"center"}},po={class:"header-item"},co={class:"header-item"},mo={class:"header-item"},ho={class:"header"},bo={class:"header-item"},fo={style:{display:"flex","align-items":"center"}},go={style:{"text-align":"center"}},_o={class:"header-item"},yo={class:"header-item"},vo={class:"header-item"},wo={class:"header"},ko={class:"header-item"},Vo={class:"header-item"},Ro={class:"header-item"},Co={class:"header-item"},Po={class:"tests-section"},$o={key:0,class:"caption"},To={key:1,class:"caption"},So={class:"tests-table"},Uo={class:"head"},Io={class:"header"},Mo={class:"header-item"},Fo={style:{display:"flex","align-items":"center"}},Do={style:{display:"flex","flex-direction":"column","align-items":"center"}},Lo={style:{"text-align":"center"}},jo={class:"header-item"},Bo={class:"header-item"},zo={class:"header-item"},xo={class:"header"},Go={class:"header-item"},Ao={style:{display:"flex","align-items":"center"}},Eo={style:{display:"flex","flex-direction":"column","align-items":"center"}},No={style:{"text-align":"center"}},Wo={class:"header-item"},Qo={class:"header-item"},Oo={class:"header-item"},Ho={class:"header"},qo={class:"header-item"},Jo={class:"header-item"},Yo={class:"header-item"},Ko={class:"header-item"},Xo={class:"test-details"},Zo={class:"test-table"};function ei(e,l,r,p,i,s){var f,_,y,g,M,F,v,$,L,U,B,z,x,o,m,k,h,w,b,W,V,q,O,Y,oe,Z,le;const u=P("BarcodeComponent");return d(),c("div",Ms,[((f=s.NotprintAlone)==null?void 0:f.length)>0?(d(),c(S,{key:0},[l[28]||(l[28]=t("hr",null,null,-1)),t("div",Fs,[t("div",Ds,[t("div",Ls,[l[4]||(l[4]=t("br",null,null,-1)),l[5]||(l[5]=t("br",null,null,-1)),l[6]||(l[6]=t("br",null,null,-1)),l[7]||(l[7]=t("br",null,null,-1)),l[8]||(l[8]=t("br",null,null,-1)),l[9]||(l[9]=t("br",null,null,-1)),t("div",js,[t("div",Bs,[l[0]||(l[0]=t("span",null,"Barcode:",-1)),t("div",zs,[a(u,{value:(_=e.printRecord)==null?void 0:_.barcode},null,8,["value"]),t("span",null,n((y=e.printRecord)==null?void 0:y.barcode),1)])])]),t("div",xs,[l[1]||(l[1]=R(" Age: ")),t("strong",null,n(((M=(g=e.printRecord)==null?void 0:g.patient)==null?void 0:M.age)+((v=(F=e.printRecord)==null?void 0:F.patient)==null?void 0:v.age_unit)),1)]),t("div",Gs,[l[2]||(l[2]=R(" Referred By: ")),t("strong",null,n((L=($=e.printRecord)==null?void 0:$.referral)==null?void 0:L.name),1)]),t("div",As,[l[3]||(l[3]=R(" Total: ")),t("strong",null,n((U=e.printRecord)==null?void 0:U.total),1)])]),t("div",Es,[t("div",Ns,[t("div",Ws,[l[10]||(l[10]=t("span",null,"Patient code:",-1)),t("div",Qs,[a(u,{value:(z=(B=e.printRecord)==null?void 0:B.patient)==null?void 0:z.code},null,8,["value"]),t("span",null,n((o=(x=e.printRecord)==null?void 0:x.patient)==null?void 0:o.code),1)])])]),t("div",Os,[l[11]||(l[11]=R(" Sex: ")),t("strong",null,n((k=(m=e.printRecord)==null?void 0:m.patient)==null?void 0:k.gender),1)]),t("div",Hs,[l[12]||(l[12]=R(" Registration date: ")),t("strong",null,n(e.dateTimeFormat((h=e.printRecord)==null?void 0:h.registration_date)),1)]),t("div",qs,[l[13]||(l[13]=R(" Paid: ")),t("strong",null,n((w=e.printRecord)==null?void 0:w.paid),1)])]),t("div",Js,[t("div",Ys,[l[14]||(l[14]=R(" Patient name: ")),t("strong",null,n((W=(b=e.printRecord)==null?void 0:b.patient)==null?void 0:W.name),1)]),t("div",Ks,[l[15]||(l[15]=R(" Phone: ")),t("strong",null,n((q=(V=e.printRecord)==null?void 0:V.patient)==null?void 0:q.phone),1)]),t("div",Xs,[l[16]||(l[16]=R(" Result date: ")),t("strong",null,n(e.dateTimeFormat((O=e.printRecord)==null?void 0:O.result_date)),1)]),t("div",Zs,[l[17]||(l[17]=R(" Due: ")),t("strong",null,n(((Y=e.printRecord)==null?void 0:Y.total)-((oe=e.printRecord)==null?void 0:oe.paid)),1)])])]),(d(!0),c(S,null,I(s.NotprintAlone,(j,ie)=>{var Q,ne;return d(),c("section",{class:"test-details",key:ie},[t("div",eo,[j.name?(d(),c("div",to,n(j.name),1)):D("",!0),l[22]||(l[22]=t("br",null,null,-1)),j.category?(d(),c("div",lo,n(j.category),1)):D("",!0),t("table",no,[l[21]||(l[21]=t("thead",null,[t("tr",null,[t("th",null,"Test name"),t("th",null,"Unit"),t("th",null,"Sample type"),t("th",null,"Result"),t("th",null,"Signature")])],-1)),t("tbody",null,[(d(!0),c(S,null,I(j==null?void 0:j.tests_not_print_alone,(C,ee)=>(d(),c("tr",{key:ee},[t("td",null,n(C==null?void 0:C.report_name),1),t("td",null,n(C==null?void 0:C.unit),1),t("td",null,n(C==null?void 0:C.sample_name),1),t("td",null,n(C==null?void 0:C.result),1),l[18]||(l[18]=t("td",null,null,-1))]))),128)),(d(!0),c(S,null,I((Q=e.printRecord)==null?void 0:Q.cultures,(C,ee)=>(d(),c("tr",{key:ee},[t("td",null,n(C==null?void 0:C.name),1),t("td",null,n(C==null?void 0:C.unit),1),t("td",null,n(C==null?void 0:C.sample_name),1),t("td",null,n(C==null?void 0:C.result),1),l[19]||(l[19]=t("td",null,null,-1))]))),128)),(d(!0),c(S,null,I((ne=e.printRecord)==null?void 0:ne.packages,(C,ee)=>(d(),c("tr",{key:ee},[t("td",null,n(C==null?void 0:C.name),1),t("td",null,n(C==null?void 0:C.unit),1),t("td",null,n(C==null?void 0:C.sample_name),1),t("td",null,n(C==null?void 0:C.result),1),l[20]||(l[20]=t("td",null,null,-1))]))),128))])])])])}),128)),l[23]||(l[23]=t("div",{class:"footer"},[t("div",null,"Receptionist"),t("div",null,"Sample receiver"),t("div",null,"Sample responsible")],-1)),l[24]||(l[24]=t("br",null,null,-1)),l[25]||(l[25]=t("br",null,null,-1)),l[26]||(l[26]=t("br",null,null,-1)),l[27]||(l[27]=t("br",null,null,-1))])],64)):D("",!0),((Z=s.printAlone)==null?void 0:Z.length)>0?(d(!0),c(S,{key:1},I(s.printAlone,(j,ie)=>(d(),c("div",{key:ie},[(d(!0),c(S,null,I(j==null?void 0:j.tests_print_alone,(Q,ne)=>{var C,ee,ae,de,re,ue,pe,ce,me,he,be,fe,ge,_e,ye,ve,we,ke,Ve,Re,Ce,Pe,De,Le,je,Be;return d(),c("div",{key:"separate-"+ne,class:"print-page page-break"},[t("div",so,[t("div",oo,[t("div",io,[l[33]||(l[33]=t("br",null,null,-1)),l[34]||(l[34]=t("br",null,null,-1)),l[35]||(l[35]=t("br",null,null,-1)),l[36]||(l[36]=t("br",null,null,-1)),l[37]||(l[37]=t("br",null,null,-1)),l[38]||(l[38]=t("br",null,null,-1)),t("div",ao,[t("div",ro,[l[29]||(l[29]=t("span",null,"Barcode:",-1)),t("div",uo,[a(u,{value:(C=e.printRecord)==null?void 0:C.barcode},null,8,["value"]),t("span",null,n((ee=e.printRecord)==null?void 0:ee.barcode),1)])])]),t("div",po,[l[30]||(l[30]=R(" Age: ")),t("strong",null,n(((de=(ae=e.printRecord)==null?void 0:ae.patient)==null?void 0:de.age)+((ue=(re=e.printRecord)==null?void 0:re.patient)==null?void 0:ue.age_unit)),1)]),t("div",co,[l[31]||(l[31]=R(" Referred By: ")),t("strong",null,n((ce=(pe=e.printRecord)==null?void 0:pe.referral)==null?void 0:ce.name),1)]),t("div",mo,[l[32]||(l[32]=R(" Total: ")),t("strong",null,n((me=e.printRecord)==null?void 0:me.total),1)])]),t("div",ho,[t("div",bo,[t("div",fo,[l[39]||(l[39]=t("span",null,"Patient code:",-1)),t("div",go,[a(u,{value:(be=(he=e.printRecord)==null?void 0:he.patient)==null?void 0:be.code},null,8,["value"]),t("span",null,n((ge=(fe=e.printRecord)==null?void 0:fe.patient)==null?void 0:ge.code),1)])])]),t("div",_o,[l[40]||(l[40]=R(" Sex: ")),t("strong",null,n((ye=(_e=e.printRecord)==null?void 0:_e.patient)==null?void 0:ye.gender),1)]),t("div",yo,[l[41]||(l[41]=R(" Registration date: ")),t("strong",null,n(e.dateTimeFormat((ve=e.printRecord)==null?void 0:ve.registration_date)),1)]),t("div",vo,[l[42]||(l[42]=R(" Paid: ")),t("strong",null,n((we=e.printRecord)==null?void 0:we.paid),1)])]),t("div",wo,[t("div",ko,[l[43]||(l[43]=R(" Patient name: ")),t("strong",null,n((Ve=(ke=e.printRecord)==null?void 0:ke.patient)==null?void 0:Ve.name),1)]),t("div",Vo,[l[44]||(l[44]=R(" Phone: ")),t("strong",null,n((Ce=(Re=e.printRecord)==null?void 0:Re.patient)==null?void 0:Ce.phone),1)]),t("div",Ro,[l[45]||(l[45]=R(" Result date: ")),t("strong",null,n(e.dateTimeFormat((Pe=e.printRecord)==null?void 0:Pe.result_date)),1)]),t("div",Co,[l[46]||(l[46]=R(" Due: ")),t("strong",null,n(((De=e.printRecord)==null?void 0:De.total)-((Le=e.printRecord)==null?void 0:Le.paid)),1)])])]),t("div",Po,[j.name?(d(),c("div",$o,n(j.name),1)):D("",!0),l[51]||(l[51]=t("br",null,null,-1)),j.category?(d(),c("div",To,n(j.category),1)):D("",!0),t("table",So,[l[50]||(l[50]=t("thead",null,[t("tr",null,[t("th",null,"Test name"),t("th",null,"Unit"),t("th",null,"Sample type"),t("th",null,"Result"),t("th",null,"Signature")])],-1)),t("tbody",null,[t("tr",null,[t("td",null,n(Q==null?void 0:Q.report_name),1),t("td",null,n(Q==null?void 0:Q.unit),1),t("td",null,n(Q==null?void 0:Q.sample_name),1),t("td",null,n(Q==null?void 0:Q.result),1),l[47]||(l[47]=t("td",null,null,-1))]),(d(!0),c(S,null,I((je=e.printRecord)==null?void 0:je.cultures,(G,Ue)=>(d(),c("tr",{key:Ue},[t("td",null,n(G==null?void 0:G.name),1),t("td",null,n(G==null?void 0:G.unit),1),t("td",null,n(G==null?void 0:G.sample_name),1),t("td",null,n(G==null?void 0:G.result),1),l[48]||(l[48]=t("td",null,null,-1))]))),128)),(d(!0),c(S,null,I((Be=e.printRecord)==null?void 0:Be.packages,(G,Ue)=>(d(),c("tr",{key:Ue},[t("td",null,n(G==null?void 0:G.name),1),t("td",null,n(G==null?void 0:G.unit),1),t("td",null,n(G==null?void 0:G.sample_name),1),t("td",null,n(G==null?void 0:G.result),1),l[49]||(l[49]=t("td",null,null,-1))]))),128))])])]),l[52]||(l[52]=t("div",{class:"footer"},[t("div",null,"Receptionist"),t("div",null,"Sample receiver"),t("div",null,"Sample responsible")],-1)),l[53]||(l[53]=t("br",null,null,-1)),l[54]||(l[54]=t("br",null,null,-1)),l[55]||(l[55]=t("br",null,null,-1)),l[56]||(l[56]=t("br",null,null,-1)),l[57]||(l[57]=t("hr",null,null,-1))])])}),128))]))),128)):D("",!0),(d(!0),c(S,null,I((le=e.printRecord)==null?void 0:le.cultures,(j,ie)=>{var Q,ne,C,ee,ae,de,re,ue,pe,ce,me,he,be,fe,ge,_e,ye,ve,we,ke,Ve,Re,Ce,Pe;return d(),c("div",{key:"separate-"+ie,class:"print-page page-break"},[t("div",Uo,[t("div",Io,[t("div",Mo,[t("div",Fo,[l[58]||(l[58]=t("span",null,"Barcode:",-1)),t("span",Do,[t("div",Lo,[a(u,{value:(Q=e.printRecord)==null?void 0:Q.barcode},null,8,["value"]),t("span",null,n((ne=e.printRecord)==null?void 0:ne.barcode),1)])])])]),t("div",jo,[l[59]||(l[59]=R(" Age: ")),t("strong",null,n(((ee=(C=e.printRecord)==null?void 0:C.patient)==null?void 0:ee.age)+((de=(ae=e.printRecord)==null?void 0:ae.patient)==null?void 0:de.age_unit)),1)]),t("div",Bo,[l[60]||(l[60]=R(" Referred By: ")),t("strong",null,n((ue=(re=e.printRecord)==null?void 0:re.referral)==null?void 0:ue.name),1)]),t("div",zo,[l[61]||(l[61]=R(" Total: ")),t("strong",null,n((pe=e.printRecord)==null?void 0:pe.total),1)])]),t("div",xo,[t("div",Go,[t("div",Ao,[l[62]||(l[62]=t("span",null,"Patient code:",-1)),t("span",Eo,[t("div",No,[a(u,{value:(me=(ce=e.printRecord)==null?void 0:ce.patient)==null?void 0:me.code},null,8,["value"]),t("span",null,n((be=(he=e.printRecord)==null?void 0:he.patient)==null?void 0:be.code),1)])])])]),t("div",Wo,[l[63]||(l[63]=R(" Sex: ")),t("strong",null,n((ge=(fe=e.printRecord)==null?void 0:fe.patient)==null?void 0:ge.gender),1)]),t("div",Qo,[l[64]||(l[64]=R(" Registration date: ")),t("strong",null,n(e.dateTimeFormat((_e=e.printRecord)==null?void 0:_e.registration_date)),1)]),t("div",Oo,[l[65]||(l[65]=R(" Paid: ")),t("strong",null,n((ye=e.printRecord)==null?void 0:ye.paid),1)])]),t("div",Ho,[t("div",qo,[l[66]||(l[66]=R(" Patient name: ")),t("strong",null,n((we=(ve=e.printRecord)==null?void 0:ve.patient)==null?void 0:we.name),1)]),t("div",Jo,[l[67]||(l[67]=R(" Phone: ")),t("strong",null,n((Ve=(ke=e.printRecord)==null?void 0:ke.patient)==null?void 0:Ve.phone),1)]),t("div",Yo,[l[68]||(l[68]=R(" Result date: ")),t("strong",null,n(e.dateTimeFormat((Re=e.printRecord)==null?void 0:Re.result_date)),1)]),t("div",Ko,[l[69]||(l[69]=R(" Due: ")),t("strong",null,n(((Ce=e.printRecord)==null?void 0:Ce.total)-((Pe=e.printRecord)==null?void 0:Pe.paid)),1)])])]),t("section",Xo,[t("table",Zo,[l[71]||(l[71]=t("thead",null,[t("tr",null,[t("th",null,"Test name"),t("th",null,"Unit"),t("th",null,"Sample type"),t("th",null,"Result"),t("th",null,"Signature")])],-1)),t("tbody",null,[t("tr",null,[t("td",null,n(j==null?void 0:j.report_name),1),t("td",null,n(j==null?void 0:j.unit),1),t("td",null,n(j==null?void 0:j.sample_name),1),t("td",null,n(j==null?void 0:j.result),1),l[70]||(l[70]=t("td",null,null,-1))])])])]),l[72]||(l[72]=t("div",{class:"footer"},[t("div",{class:"role"},"Receptionist"),t("div",{class:"role"},"Sample receiver"),t("div",{class:"role"},"Sample responsible")],-1))])}),128))])}const ti=te(Is,[["render",ei],["__scopeId","data-v-cf9f6030"]]);const li={components:{QrcodeVue:nt},computed:{...E(K,["printRecord"])},methods:{getPatientReportLink(e){return`${window.location.origin}/medical-reports/${e}`},generateBarcodeImage(e){if(!e)return"";const l=document.createElement("canvas");document.body.appendChild(l),st(l,e,{format:"CODE128",displayValue:!1,width:1,height:10});const r=l.toDataURL("image/png");return document.body.removeChild(l),r}}},ni={class:"container",id:"thermalRecord"},si={class:"elements"},oi={class:"patient-details"},ii={class:"parcod"},ai={style:{display:"flex","align-items":"center"}},di={style:{display:"flex","flex-direction":"column","align-items":"center"}},ri=["src"],ui={style:{display:"flex","flex-direction":"column","align-items":"center"}},pi=["src"],ci={class:"test-details"},mi={class:"test-table"},hi={class:"summary",style:{"margin-top":"5px",display:"flex","justify-content":"space-between","flex-direction":"column"}},bi={class:"summaryItm"},fi={class:"summaryItm"},gi={class:"summaryItm"},_i={class:"footer"};function yi(e,l,r,p,i,s){var f,_,y,g,M,F,v,$,L,U,B,z,x,o,m,k,h,w,b,W,V,q,O,Y,oe;const u=P("QrcodeVue");return d(),c("div",ni,[l[18]||(l[18]=t("br",null,null,-1)),l[19]||(l[19]=t("br",null,null,-1)),l[20]||(l[20]=t("br",null,null,-1)),l[21]||(l[21]=t("br",null,null,-1)),l[22]||(l[22]=t("br",null,null,-1)),l[23]||(l[23]=t("br",null,null,-1)),t("div",si,[t("section",oi,[t("div",ii,[t("div",ai,[l[0]||(l[0]=t("span",null,[t("strong",null,"Barcode:")],-1)),t("span",di,[t("img",{src:s.generateBarcodeImage((f=e.printRecord)==null?void 0:f.barcode),alt:"Barcode"},null,8,ri),t("span",null,n((_=e.printRecord)==null?void 0:_.barcode),1)])])]),t("p",null,[l[1]||(l[1]=t("strong",null,"Patient ID:",-1)),t("span",ui,[t("img",{src:s.generateBarcodeImage((g=(y=e.printRecord)==null?void 0:y.patient)==null?void 0:g.code),alt:"Barcode"},null,8,pi),t("span",null,n((F=(M=e.printRecord)==null?void 0:M.patient)==null?void 0:F.code),1)])]),t("p",null,[l[2]||(l[2]=t("strong",null,"patient name :",-1)),t("strong",null,n(($=(v=e.printRecord)==null?void 0:v.patient)==null?void 0:$.name),1)]),t("p",null,[l[3]||(l[3]=t("strong",null,"age / Sex :",-1)),t("strong",null,n(((U=(L=e.printRecord)==null?void 0:L.patient)==null?void 0:U.age)+((z=(B=e.printRecord)==null?void 0:B.patient)==null?void 0:z.age_unit))+" / "+n((o=(x=e.printRecord)==null?void 0:x.patient)==null?void 0:o.gender),1)]),t("p",null,[l[4]||(l[4]=t("strong",null,"Request Date :",-1)),t("strong",null,n(e.dateTimeFormat((m=e.printRecord)==null?void 0:m.registration_date)),1)]),t("p",null,[l[5]||(l[5]=t("strong",null,"Result date:",-1)),t("strong",null,n(e.dateTimeFormat((k=e.printRecord)==null?void 0:k.result_date)),1)])]),l[14]||(l[14]=t("hr",null,null,-1)),t("section",ci,[t("table",mi,[l[9]||(l[9]=t("thead",null,[t("tr",null,[t("th",null,"Test"),t("th",null,"Price"),t("th",null,"test type")])],-1)),t("tbody",null,[(d(!0),c(S,null,I((h=e.printRecord)==null?void 0:h.tests,(Z,le)=>(d(),c("tr",{key:le},[t("td",null,n(Z.report_name),1),t("td",null,n(Z.price),1),l[6]||(l[6]=t("td",null,"tests",-1))]))),128)),(d(!0),c(S,null,I((w=e.printRecord)==null?void 0:w.cultures,(Z,le)=>(d(),c("tr",{key:le},[t("td",null,n(Z.name),1),t("td",null,n(Z.price),1),l[7]||(l[7]=t("td",null,"cultures",-1))]))),128)),(d(!0),c(S,null,I((b=e.printRecord)==null?void 0:b.packages,(Z,le)=>(d(),c("tr",{key:le},[t("td",null,n(Z.name),1),t("td",null,n(Z.price),1),l[8]||(l[8]=t("td",null,"packages",-1))]))),128))])])]),t("section",hi,[t("div",bi,[l[10]||(l[10]=t("strong",null,"Total:",-1)),R(" "+n((W=e.printRecord)==null?void 0:W.total),1)]),t("div",fi,[l[11]||(l[11]=t("strong",null,"paid:",-1)),R(" "+n((V=e.printRecord)==null?void 0:V.paid),1)]),t("div",gi,[l[12]||(l[12]=t("strong",null,"Due:",-1)),R(" "+n(((q=e.printRecord)==null?void 0:q.total)-((O=e.printRecord)==null?void 0:O.paid)),1)])]),t("section",_i,[l[13]||(l[13]=t("p",null,[t("strong",null,"امسح الباركود و احصل على النتائج مباشرة")],-1)),a(u,{value:s.getPatientReportLink((oe=(Y=e.printRecord)==null?void 0:Y.patient)==null?void 0:oe.id),size:90,level:"H","render-as":"svg",class:"qr-code"},null,8,["value"])]),l[15]||(l[15]=t("br",null,null,-1)),l[16]||(l[16]=t("br",null,null,-1)),l[17]||(l[17]=t("div",null,"مختبر التعاون الطبي التخصصي يتمنى لكم الصحة و العافية",-1))]),l[24]||(l[24]=t("br",null,null,-1)),l[25]||(l[25]=t("br",null,null,-1)),l[26]||(l[26]=t("br",null,null,-1)),l[27]||(l[27]=t("br",null,null,-1))])}const vi=te(li,[["render",yi],["__scopeId","data-v-32f54c62"]]);const wi={data(){return{Filters:{from_lab:"",contract_id_fk:"",created_by:"",barcode:"",patient_name:"",lab:""},globalFilter:"",menuRefs:{}}},components:{whatsUp:Us,invoicesModal:On,patientModal:cs,job_orderModal:ti,DueModal:Ps,thermalReciptModal:vi,printInvoice:He,parcodeModal:qe,BarcodeComponent:Se,Menu:Ze},mounted(){this.Getinvoices()},computed:{...E(Fe,["contracts"]),...E(K,["invoices","pagination","Pdfurl","record","dialog","patientdialog","patient","Patient_due","Patient_dueDialog","discountPercentage","discountValue","testQuesions","tests_ids","payment_details","selectedContract","selectedTests","selectedPackages","selectedtestGroups","selectedCultures","selectedreferal","patient","selectedCollector","printRecord","WhatsUpDialog"]),...E($e,{thepatientDialog:"dialog",patientRecord:"record",responseData:"responseData"}),...E(lt,["havePermission"]),filteredRecords(){return this.invoices.filter(e=>{var p,i,s,u,f,_,y,g,M,F,v,$,L,U,B,z,x;const l=this.globalFilter?((p=e.barcode)==null?void 0:p.toLowerCase().includes(this.globalFilter.toLowerCase()))||((i=e.from_lab)==null?void 0:i.toLowerCase().includes(this.globalFilter.toLowerCase()))||((u=(s=e.contract)==null?void 0:s.name)==null?void 0:u.toLowerCase().includes(this.globalFilter.toLowerCase()))||((_=(f=e.created_by)==null?void 0:f.name)==null?void 0:_.toLowerCase().includes(this.globalFilter.toLowerCase()))||((g=(y=e.patient)==null?void 0:y.name)==null?void 0:g.toLowerCase().includes(this.globalFilter.toLowerCase()))||((F=(M=e.patient)==null?void 0:M.lab)==null?void 0:F.toLowerCase().includes(this.globalFilter.toLowerCase())):!0,r=(!this.Filters.created_by||(($=(v=e.created_by)==null?void 0:v.name)==null?void 0:$.toLowerCase().includes(this.Filters.created_by.toLowerCase())))&&(!this.Filters.lab||(recordlab==null?void 0:recordlab.toLowerCase().includes(this.Filters.lab.toLowerCase())))&&(!this.Filters.contract_id_fk||((L=e.contract)==null?void 0:L.id)===this.Filters.contract_id_fk)&&(!this.Filters.from_lab||((U=e.from_lab)==null?void 0:U.toLowerCase().includes(this.Filters.from_lab.toLowerCase())))&&(!this.Filters.barcode||((B=e.barcode)==null?void 0:B.toLowerCase().includes(this.Filters.barcode.toLowerCase())))&&(!this.Filters.patient_name||((x=(z=e.patient)==null?void 0:z.name)==null?void 0:x.toLowerCase().includes(this.Filters.patient_name.toLowerCase())));return l&&r})}},methods:{downloadPDF(){this.$refs.DownloadComp.generatePdf()},onPageChange(e){this.pagination.current_page=e.page+1,this.Getinvoices()},...H(K,["Getinvoices","patient_medical_records","Removeinvoices","pdf","getsamples"]),showPatient(e){this.patient=e,this.patientdialog=!0},showPatient_due(e){this.Patient_due=e,this.Patient_dueDialog=!0},deleteRecord(e){et(this.t("AlertWithConfirm")).then(l=>{l.value&&(this.record.id=e.id,this.Removeinvoices())})},setMenuRef(e,l){e&&(this.menuRefs[l]=e)},toggleMenu(e,l){const r=this.menuRefs[l];r&&r.toggle(e)},getMenuItems(e){const l=[];return this.havePermission("invoices job order")&&l.push({label:this.t("job_order"),icon:"pi pi-book",command:()=>this.openJobTemplateAsPDF(e)}),this.havePermission("invoices thermal reciept print")&&l.push({label:this.t("thermal_recipt"),icon:"pi pi-receipt",command:()=>this.openthermalRecord(e)}),this.havePermission("invoices print")&&l.push({label:this.t("printInvoice"),icon:"pi pi-print",command:()=>this.openprintINvoiceTemplate(e)}),this.havePermission("invoices send whatsapp")&&l.push({label:this.t("send"),icon:"pi pi-whatsapp",command:()=>this.sendWhatsUp(e)}),l.length>0&&l.push({separator:!0}),this.havePermission("invoices delete")&&l.push({label:this.t("delete"),icon:"pi pi-trash",class:"text-danger",command:()=>this.deleteRecord(e)}),l},clearFilters(){this.Filters={from_lab:"",contract_id_fk:"",created_by:"",barcode:"",patient_name:"",lab:""},this.globalFilter=""},exportToExcel(){const e=Me.json_to_sheet(this.filteredRecords,{header:["index","from_lab","contract_id_fk","created_by","barcode","lab"]}),l=Me.book_new();Me.book_append_sheet(l,e,"Sheet1"),ot(l,"export.xlsx")},editRecord(e){var l,r,p,i,s,u,f,_,y,g,M,F,v,$,L,U,B,z,x,o,m,k,h,w;this.payment_details=[{amount:null,contract_id_fk:null,payment_method_id_fk:null}],Object.assign(this.record,e.data),this.payment_details=(l=e.data)==null?void 0:l.paidDetails,this.selectedContract=(r=e.data)==null?void 0:r.contract,this.selectedreferal=(p=e.data)==null?void 0:p.referral,this.record.referral_id_fk=(s=(i=e.data)==null?void 0:i.referral)==null?void 0:s.id,this.selectedCollector=(u=e.data)==null?void 0:u.sample_collector,this.record.sub_total=(f=e.data)==null?void 0:f.sub_total,this.record.total=(_=e.data)==null?void 0:_.total,this.record.contract_id_fk=(g=(y=e.data)==null?void 0:y.contract)==null?void 0:g.id,this.record.sample_collector_id_fk=(F=(M=e.data)==null?void 0:M.sample_collector)==null?void 0:F.id,this.record.registration_date=this.dateFormat((v=e.data)==null?void 0:v.registration_date),this.record.result_date=this.dateFormat(($=e.data)==null?void 0:$.result_date),this.selectedTests=(L=e.data)==null?void 0:L.tests,this.selectedPackages=(U=e.data)==null?void 0:U.packages,this.selectedCultures=(B=e.data)==null?void 0:B.cultures,this.selectedtestGroups=(z=e.data)==null?void 0:z.test_groups,this.responseData=(x=e.data)==null?void 0:x.patient,this.discountPercentage=((o=e.data)==null?void 0:o.discount_type_id_fk)==2?(m=e.data)==null?void 0:m.discount:null,this.discountValue=((k=e.data)==null?void 0:k.discount_type_id_fk)==3?(h=e.data)==null?void 0:h.discount:null,this.testQuesions=(w=this.selectedTests)==null?void 0:w.map(b=>{var W;return{test_name:b.name,test_id_fk:b.test_id_fk,questions:(W=b.questions)==null?void 0:W.map(V=>({question:V.question.question,answer_type:V.question.answer_type,answer_type_id_fk:V.question.answer_type_id_fk,answer_type_selection_values:V.question.answer_type_selection_values,answer:V.answer}))}}),this.dialog=!0},addRecord(){this.selectedContract=[],this.selectedreferal=[],this.selectedTests=[],this.selectedPackages=[],this.selectedCultures=[],this.selectedtestGroups=[],this.testQuesions=[],this.payment_details=[{amount:null,contract_id_fk:null,payment_method_id_fk:null}],this.patient=[],this.discountPercentage=0,this.discountValue=0,this.clearObjectValues(this.record),this.record.registration_date=this.dateFormat(new Date().toISOString()),this.dialog=!0},openJobTemplateAsPDF(e){this.printRecord=e,setTimeout(function(){const l=document.getElementById("job").innerHTML,r=`
             @page { size: portrait; margin: 0 !important; }
             @media print {
                 body, .page {
                     margin: 0px !important;
                     box-shadow: 0;
                     -webkit-print-color-adjust: exact;
                     color: #000;

             } body {font-family: "Tajawal",text-transform: capitalize;  sans-serif;;font-size: 14px;margin: 0;padding: 0; color: #000;}
                                       @page { size: A4;  margin: 20mm;}
                                      h1 { font-size: 24px; }
                                      p { font-size: 14px; }
                                      .report-container {
         font-family: Arial, sans-serif;
         width: 100%;
         margin: 0 auto;
     }
     .head{
         display: flex;
         padding:10px;
         justify-content: space-between;}
     .header, .details {
            display: flex;
         padding: 5px 0;
       margin-bottom: 5px;
         flex-direction: column;
         justify-content: space-between;
     }

     .header-item {
         text-align: left;    margin-bottom: 15px;
     }

     .barcode-image {
         width: 100px;
         height: auto;
         display: block;
     }

     .details div {
         margin: 5px;
         font-weight: bold;
     }

     .tests-section {
         margin-top: 10px;
     }

     .tests-table {
         width: 100%;
         border-collapse: collapse;
     }

     .tests-table th, .tests-table td {
         border: 1px solid #0000001c;
         padding: 8px;
         text-align: center;
     }

     .tests-table th {
         background-color: #f0f0f0;
         font-weight: bold;
     }
  .page-break {
          page-break-before: always;
     }   .caption {
          width: 100%;
          font-weight: bold;
          border: 1px solid black;
          padding: 5px;
          text-align: center;
          color: black;
                    }
     .footer {
         display: flex;
         justify-content: space-around;
         margin-top: 20px;
         font-weight: bold;
     }

                              `,p=document.createElement("iframe");p.style.position="absolute",p.style.width="0px",p.style.height="0px",p.style.border="none",document.body.appendChild(p);const i=p.contentWindow.document;i.open(),i.write(`
             <html>
                 <head>
                     <title>Print Job</title>
                     <style>${r}</style>
                 </head>
                 <body>${l}</body>
             </html>
         `),i.close(),p.contentWindow.focus(),p.contentWindow.print(),p.contentWindow.onafterprint=()=>{document.body.removeChild(p)}},50)},openthermalRecord(e){this.printRecord=e,setTimeout(function(){const l=` @media print {   body {   width: auto;
                                height: auto; margin: auto  ;    text-align:center;   -webkit-print-color-adjust: exact;       color: #000;    }
                                 } ;
                         .elements{width: 80px;
    height: 400px;
    margin: 0px 10px 0 10px;
    font-size: 8px;} .elements img{
    width:100%}

                                      body {
                                             font-family: "Tajawal", sans-serif;;
                                                   font-size: xx-small;text-transform: capitalize;
                                             margin: 0;
                                             padding: 0;
                                             background-color: #f8f8f8;
                                             display: flex;
                                             justify-content: center;
                                        }
                                        header {
                                             text-align: center;

                                        }
                                        .logo {
                                             max-width: 40px;
                                             margin-bottom: 1px;
                                        }
              
                                        .divider {
                                             border: 1px solid #000;
                                             margin:3px 0;
                                        }
                                        .patient-details p { margin: 5px 0;      margin-bottom: 10px;  display: flex; align-items: center; justify-content: space-between;
                                        }
                                    .patient-details .parcod {
                                             margin: 5px 0;
                                              display: flex;
                                                 justify-content: space-between;
                                        }

                                        .barcode {

                                             font-weight: bold;
                                             text-align: center;
                                             margin-bottom: 10px;
                                        }

                                        .test-table {
                                             width: 100%;
                                             border-collapse: collapse;

                                        }

                                        .test-table th,
                                        .test-table td {
                                             border: 1px solid #e5e7eb;
                                             padding: 3px;
                                             text-align: center;font-size: 6px;
                                        }

                                       .summaryItm{display: flex; justify-content: space-between;     border-bottom: 1px solid;}
                                        .footer {
                                             text-align: center;
                                             margin-top: 2px;
                                        }

                                        .qrcode {

                                        }
                         .test-details{page-break-inside: avoid; text-align:center;}
                                        /* Responsiveness */
                                        @media screen and (max-width: 768px) {
                                             .container {
                                                  width: 100%;
                                                  padding: 10px;
                                             }

                                             .logo {
                                                  max-width: 100px;
                                             }

                                             .barcode {
                                                  font-size: 16px;
                                             }

                                             .test-table th,
                                             .test-table td {
                                                  padding: 5px;
                                             }

                                             .qrcode {
                                                  max-width: 80px;
                                             }
                                        }
                    @media print {
                            body {
                                margin: 0;
                                padding: 0;
                                width: auto;
                                height: auto;
                            }
                          }
                    //                      `,r=document.getElementById("thermalRecord").innerHTML,p=window.open("","_blank");p.document.write(`<html><head><title>Receipt</title><style>${l}
#thermalRecord {
  width: 80mm; 
  height:400mm;
  /* Set the receipt width to 80mm (or 58mm for smaller paper) */
  padding: 10mm; /* Add padding for better readability */
  font-family: "Courier New", Courier, monospace; /* Use a monospaced font */
  font-size: 12px; /* Adjust font size for clarity */
  line-height: 1.5;
}.qrcode{   width:65px  !important;} .elements{width: 80px;
    height: 400px;
    margin: 0px 10px 0 10px;
    font-size: 8px;} .elements img{
    width:100%}
  @media print {  body {
                                margin: 0;
                                padding: 0;
                                width: auto;
                                height: auto;
                            } 
                          @page {

                      size: 80 400;    /* using inches */

                      margin: 0mm;
                    }}
</style>
</head><body>${r}</body></html>`),p.document.close(),p.focus(),p.print(),p.close()},50)},openprintINvoiceTemplate(e){this.printRecord=e,setTimeout(function(){const l=document.getElementById("printInvoice").innerHTML;var r=`@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } }


                                    body {font-family: "Tajawal",text-transform: capitalize;  sans-serif;;font-size: 14px;margin: 0;padding: 0; color: #000;}
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
     .caption {
          width: 100%;
          font-weight: bold;
          border: 1px solid black;
          padding: 5px;
          text-align: center;
          color: black;
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
      .test-table {
                                  width: 100%;
                                  border-collapse: collapse;
                                  margin: 20px 0;
                             }

                             .test-table th,
                             .test-table td {
                                    border: 1px solid #e5e7eb;
                                  padding: 10px;
                                  text-align: center;
                             }

                             .test-table th {
                                  background-color: #f0f0f0;
                             }
      .summary   {    padding: 16px; }
       .summary td{ text-align:right;} .summary th{ text-align:left;}
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
     .border{border: 1px solid black;      min-height: 25px;  margin: 0px 7px; padding: 5px;width: 150px;text-align: center;}
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
     } `;const p=document.createElement("iframe");p.style.position="absolute",p.style.width="0px",p.style.height="0px",p.style.border="none",document.body.appendChild(p);const i=p.contentWindow.document;i.open(),i.write(`
             <html>
                 <head>
                     <title>printInvoice</title>
                     <style>${r}</style>
                 </head>
                 <body>${l}</body>
             </html>
         `),i.close(),p.contentWindow.focus(),p.contentWindow.print(),p.contentWindow.onafterprint=()=>{document.body.removeChild(p)}},50)},printParcode(e){this.printRecord=e,this.getsamples(e.id).then(l=>{setTimeout(function(){const r=document.getElementById("parcode").innerHTML;var p=`
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
                                   //   line-height: 0;
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

                                                                    `;const i=document.createElement("iframe");i.style.position="absolute",document.body.appendChild(i);const s=i.contentWindow.document;s.open(),s.write(`
                                      <html>
                                          <head>
                                              <title>Print Job</title>
                                              <style>${p}</style>
                                          </head>
                                          <body>${r}</body>
                                      </html>
                                  `),document.innerHTML=r,s.close(),i.contentWindow.focus(),i.contentWindow.print(),i.contentWindow.onafterprint=()=>{document.body.removeChild(i)}},50)})},async sendWhatsUp(e){var _,y,g,M;this.printRecord=e;const l=this.User.name,r=(y=(_=this.printRecord)==null?void 0:_.patient)==null?void 0:y.name,p=`${window.location.origin}/invoice/${e.id}`,i=`اهلا بكم في مختبر ${l}  عزيزي  ${r} يمكنك الحصول على الفاتورة من خلال الضغط على الرابط ادناه 

${p}`,s=encodeURIComponent(i);let u=(M=(g=this.printRecord)==null?void 0:g.patient)==null?void 0:M.phone;u&&u.startsWith("0")&&(u=u.substring(1));const f=`https://wa.me/+964${u}?text=${s}`;window.open(f,"_blank")},isSameDate(e,l){const r=this.formatDate(e),p=this.formatDate(l);return r===p},formatDate(e){const l=new Date(e),r=l.getFullYear(),p=String(l.getMonth()+1).padStart(2,"0"),i=String(l.getDate()).padStart(2,"0");return`${r}-${p}-${i}`}},watch:{Filters:{deep:!0,handler(){this.pagination.current_page=1,this.Getinvoices(this.Filters)}}}},ki={class:"card filterTable pb-0"},Vi={class:"flex justify-content-between mb-2"},Ri={key:0,class:"card flex justify-content-center mb-5"},Ci={key:1},Pi={class:"globalSearch"},$i=["placeholder"],Ti={class:"btns"},Si={class:"noData p-d-flex p-ai-center p-jc-center",style:{height:"100px"}},Ui=["placeholder"],Ii=["placeholder"],Mi={class:"boldText"},Fi=["placeholder"],Di={class:"boldText"},Li={class:"boldText"},ji=["placeholder"],Bi={class:"boldText"},zi=["placeholder"],xi={key:0},Gi=["onClick"];function Ai(e,l,r,p,i,s){var k;const u=P("Button"),f=P("InlineMessage"),_=P("Column"),y=P("Dropdown"),g=P("BarcodeComponent"),M=P("Menu"),F=P("DataTable"),v=P("invoicesModal"),$=P("patient-modal"),L=P("due-modal"),U=P("job_orderModal"),B=P("thermalReciptModal"),z=P("printInvoice"),x=P("whatsUp"),o=P("parcodeModal"),m=tt("tooltip");return d(),c("div",ki,[t("div",Vi,[t("h5",null,n(e.t("invoices")),1),e.havePermission("invoices create")?(d(),N(u,{key:0,size:"small",class:"p-button-success",label:e.t("add"),onClick:s.addRecord},null,8,["label","onClick"])):D("",!0)]),((k=e.pagination.total)==null?void 0:k.toLocaleString())==0?(d(),c("div",Ri,[a(f,{severity:"info"},{default:T(()=>[R(n(e.t("noData")),1)]),_:1})])):(d(),c("div",Ci,[l[7]||(l[7]=t("br",null,null,-1)),t("div",Pi,[A(t("input",{"onUpdate:modelValue":l[0]||(l[0]=h=>i.Filters.patient_name=h),placeholder:e.t("search")+"...",class:"search p-inputtext p-component mb-2"},null,8,$i),[[se,i.Filters.patient_name]]),t("div",Ti,[a(u,{label:e.t("Clear Filters"),icon:"pi pi-filter-slash",onClick:s.clearFilters,class:"p-button-secondary mb-2"},null,8,["label","onClick"]),a(u,{label:e.t("Export to Excel"),icon:"pi pi-file-excel",onClick:s.exportToExcel,class:"p-button-success mb-2"},null,8,["label","onClick"])])]),l[8]||(l[8]=t("br",null,null,-1)),a(F,{size:"small",value:s.filteredRecords,scrollable:"",scrollHeight:"600px",responsiveLayout:"scroll",paginator:!0,lazy:!0,rows:e.pagination.per_page,totalRecords:e.pagination.total,first:(e.pagination.current_page-1)*e.pagination.per_page,onPage:s.onPageChange},{empty:T(()=>[t("div",Si,n(e.t("noData")),1)]),default:T(()=>[a(_,{class:"text-center",header:"#",field:"index"}),a(_,{class:"text-center",field:"patient.name",style:{"min-width":"250px"}},{header:T(()=>[t("p",null,n(e.t("Pationt_name")),1),A(t("input",{"onUpdate:modelValue":l[1]||(l[1]=h=>i.Filters.patient_name=h),placeholder:e.t("search")+"...",class:"p-inputtext p-component"},null,8,Ui),[[se,i.Filters.patient_name]])]),body:T(h=>{var w,b,W,V;return[(b=(w=h.data)==null?void 0:w.patient)!=null&&b.name?(d(),N(u,{key:0,style:{background:"green","min-width":"200px"},label:(V=(W=h.data)==null?void 0:W.patient)==null?void 0:V.name,class:"p-eye-button mx-1"},null,8,["label"])):D("",!0)]}),_:1}),a(_,{class:"text-center",field:"lab",style:{"min-width":"100px"}},{header:T(()=>[t("p",null,n(e.t("lab/bruanch")),1),A(t("input",{"onUpdate:modelValue":l[2]||(l[2]=h=>i.Filters.lab=h),placeholder:e.t("search")+"...",class:"p-inputtext p-component"},null,8,Ii),[[se,i.Filters.lab]])]),body:T(h=>{var w;return[t("p",Mi,n((w=h.data)==null?void 0:w.lab),1)]}),_:1}),a(_,{class:"text-center",field:"created_by.name",style:{"min-width":"100px"}},{header:T(()=>[t("p",null,n(e.t("Created_By")),1),A(t("input",{"onUpdate:modelValue":l[3]||(l[3]=h=>i.Filters.created_by=h),placeholder:e.t("search")+"...",class:"p-inputtext p-component"},null,8,Fi),[[se,i.Filters.created_by]])]),body:T(h=>{var w,b;return[t("p",Di,n((b=(w=h.data)==null?void 0:w.created_by)==null?void 0:b.name),1)]}),_:1}),e.contracts?(d(),N(_,{key:0,class:"text-center",field:"contract.name",style:{"min-width":"100px"}},{header:T(()=>[t("p",null,n(e.t("contract")),1),a(y,{modelValue:i.Filters.contract_id_fk,"onUpdate:modelValue":l[4]||(l[4]=h=>i.Filters.contract_id_fk=h),options:e.contracts,optionLabel:"label",optionValue:"value",placeholder:e.t("search")+"..."},null,8,["modelValue","options","placeholder"])]),body:T(h=>{var w,b;return[t("p",Li,n((b=(w=h.data)==null?void 0:w.contract)==null?void 0:b.name),1)]}),_:1})):D("",!0),a(_,{class:"text-center",field:"from_lab",style:{"min-width":"100px"}},{header:T(()=>[t("p",null,n(e.t("from_lab")),1),A(t("input",{"onUpdate:modelValue":l[5]||(l[5]=h=>i.Filters.from_lab=h),placeholder:e.t("search")+"...",class:"p-inputtext p-component"},null,8,ji),[[se,i.Filters.from_lab]])]),body:T(h=>{var w;return[t("p",Bi,n((w=h.data)==null?void 0:w.from_lab),1)]}),_:1}),a(_,{class:"text-center",field:"barcode",style:{"min-width":"150px"}},{header:T(()=>[t("p",null,n(e.t("Barcode")),1),A(t("input",{"onUpdate:modelValue":l[6]||(l[6]=h=>i.Filters.barcode=h),placeholder:e.t("search")+"...",class:"p-inputtext p-component"},null,8,zi),[[se,i.Filters.barcode]])]),body:T(h=>{var w,b,W,V;return[e.canAccess(["sample_collector"])?(d(),c("div",xi,[t("span",null,n((w=h.data)==null?void 0:w.barcode),1),t("div",null,[a(g,{value:(b=h.data)==null?void 0:b.barcode},null,8,["value"])])])):D("",!0),e.cannotAccess(["sample_collector"])?(d(),c("button",{key:1,class:"Parcode-button mx-1",onClick:q=>s.printParcode(h.data)},[t("span",null,n((W=h.data)==null?void 0:W.barcode),1),t("div",null,[a(g,{value:(V=h.data)==null?void 0:V.barcode},null,8,["value"])])],8,Gi)):D("",!0)]}),_:1}),a(_,{class:"text-center",field:"sent_to_patient",style:{"min-width":"80px"}},{header:T(()=>[t("p",null,n(e.t("theStatus")),1)]),body:T(h=>[t("i",{class:X(["pi pi-circle-fill",h.data.sent_to_patient==!0?"sucess":"danger"])},null,2)]),_:1}),a(_,{class:"text-center",field:"all_actions",style:{"min-width":"250px"}},{header:T(()=>[t("p",null,n(e.t("actions")),1)]),body:T(h=>[A(a(u,{icon:"pi pi-eye",class:"p-button-rounded p-button-text p-button-info",onClick:w=>{var b;return s.showPatient((b=h.data)==null?void 0:b.patient)}},null,8,["onClick"]),[[m,e.t("Patient_details"),void 0,{top:!0}]]),A(a(u,{icon:"pi pi-receipt",class:"p-button-rounded p-button-text p-button-help",onClick:w=>s.showPatient_due(h.data)},null,8,["onClick"]),[[m,e.t("Patient_due"),void 0,{top:!0}]]),e.havePermission("invoices edit")?A((d(),N(u,{key:0,icon:"pi pi-pencil",class:"p-button-rounded p-button-text p-button-warning",onClick:w=>s.editRecord({data:h.data})},null,8,["onClick"])),[[m,e.t("edit"),void 0,{top:!0}]]):D("",!0),a(u,{icon:"pi pi-ellipsis-v",class:"p-button-rounded p-button-text",onClick:w=>s.toggleMenu(w,h.data.id),"aria-haspopup":"true","aria-controls":"menu_"+h.data.id},null,8,["onClick","aria-controls"]),a(M,{ref:w=>s.setMenuRef(w,h.data.id),id:"menu_"+h.data.id,model:s.getMenuItems(h.data),popup:!0},null,8,["id","model"])]),_:1})]),_:1},8,["value","rows","totalRecords","first","onPage"])])),a(v),a($),a(L),a(U),a(B),a(z),a(x),a(o)])}const sa=te(wi,[["render",Ai],["__scopeId","data-v-19775e16"]]);export{sa as default};
