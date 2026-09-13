<template>
     <Dialog v-model:visible="printInvoiceDialog" modal style="width: 70rem">
          <div>
               <section class="parcode">
                    <span @click="printParcode(printRecord)" style="margin: auto; cursor: pointer">
                         <!-- <span class="ml-2">{{ t("print_parcode") }}</span> -->
                         <div style="display: flex; flex-direction: column; align-items: center">
                              <BarcodeComponent :value="printRecord?.barcode" />
                              {{ printRecord?.barcode }}
                         </div>
                    </span>
                    <span>
                         <Button
                              :label="t('thermal_recipt')"
                              icon="pi pi-receipt"
                              class="p-button-rounded mx-1"
                              @click="openthermalRecord(printRecord)"></Button>
                    </span>
               </section>
               <br />
               <hr />
               <div id="print">
                    <div v-if="printRecord?.test_groups.length > 0">
                         <section
                              class="test-details"
                              v-for="(test_group, index) in printRecord?.test_groups"
                              :key="index">
                              <div class="caption">
                                   {{ test_group.group_name }}
                              </div>
                              <table class="test-table table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("Original_Price") }}</th>
                                             <th>{{ t("the_tests") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(item, index) in test_group?.tests" :key="index">
                                             <td>{{ item.price }}</td>
                                             <td>{{ item.report_name }}</td>
                                        </tr>
                                        <tr v-for="(item, index) in test_group?.cultures" :key="index">
                                             <td>{{ item.price }}</td>
                                             <td>{{ item.name }}</td>
                                        </tr>
                                   </tbody>
                              </table>
                         </section>
                    </div>
                    <br />
                    <div v-if="printRecord?.packages.length > 0">
                         <section class="test-details" v-for="(pkg, index) in printRecord?.packages" :key="index">
                              <div class="caption">
                                   {{ pkg.name }}
                              </div>
                              <table class="test-table table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("Original_Price") }}</th>
                                             <th>{{ t("the_tests") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(item, index) in pkg?.tests" :key="index">
                                             <td>{{ item.price }}</td>
                                             <td>{{ item.report_name }}</td>
                                        </tr>
                                        <tr v-for="(item, index) in pkg?.cultures" :key="index">
                                             <td>{{ item.price }}</td>
                                             <td>{{ item.name }}</td>
                                        </tr>
                                   </tbody>
                              </table>
                         </section>
                    </div>
                    <br />
                    <div class="test-details" v-if="printRecord?.tests.length > 0 || printRecord?.cultures.length > 0">
                         <table class="test-table table">
                              <thead>
                                   <tr>
                                        <th>{{ t("Original_Price") }}</th>
                                        <th>{{ t("the_tests") }}</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="(item, index) in printRecord?.tests" :key="index">
                                        <td>{{ item.price }}</td>
                                        <td>{{ item.report_name }}</td>
                                   </tr>
                                   <tr v-for="(item, index) in printRecord?.cultures" :key="index">
                                        <td>{{ item.price }}</td>
                                        <td>{{ item.name }}</td>
                                   </tr>
                                   <tr v-for="(item, index) in printRecord?.packages" :key="index">
                                        <td>{{ item.price }}</td>
                                        <td>{{ item.name }}</td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
                    <br />
                    <div class="summary" style="margin-top: 5px">
                         <div class="receipt">
                              <div class="row">
                                   <span class="label">Subtotal</span>
                                   <span class="value">IQD {{ printRecord?.sub_total }}</span>
                              </div>
                              <div class="row">
                                   <span class="label">discount percentage</span>
                                   <span class="value">IQD {{ printRecord?.discount }}</span>
                              </div>
                              <div class="row">
                                   <span class="label">Total</span>
                                   <span class="value">IQD {{ printRecord?.total }}</span>
                              </div>
                              <div class="row">
                                   <span class="label">paid</span>
                                   <span class="value">IQD {{ printRecord?.paid }}</span>
                              </div>
                              <!-- <div class="row payment-info">
                                   <span class="value">IQD {{ printRecord?.paid }}</span>
                                   <span class="label">عن طريق كاش On 2024-11-17 19:00</span>
                              </div> -->
                              <div class="row">
                                   <span class="label">Due</span>
                                   <span class="value">IQD {{ printRecord?.total - printRecord?.paid }}</span>
                              </div>
                         </div>
                    </div>
               </div>
          </div>
          <div class="flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               <Button
                    icon="pi pi-print"
                    size="small"
                    severity="success"
                    @click="openprintINvoiceTemplate(printRecord)"></Button>
          </div>
     </Dialog>
     <parcodModal></parcodModal>
</template>
<script>
     import { mapWritableState, mapActions } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import parcodModal from "./parcodeModal.vue";
     import BarcodeComponent from "../../../components/BarcodeComponent.vue";
     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord", "printInvoiceDialog"]),
          },
          components: { parcodModal, BarcodeComponent },
          methods: {
               ...mapActions(useinvoicesStore, ["getsamples"]),

               printParcode(data) {
                    this.printRecord = data;
                    this.getsamples(data.id).then((res) => {
                         setTimeout(function () {
                              //                               const printContent = document.getElementById("parcode").innerHTML;
                              //                               const originalContent = document.body.innerHTML;

                              //                               // Add custom styles dynamically
                              //                               const style = document.createElement("style");
                              //                               style.type = "text/css";
                              //                               var css = `
                              //                             @page { size: 25mm 8mm;  margin: 0 !important;}
                              //                            @media print {
                              //                                body,  .page {   margin: 0px !important;   box-shadow: 0;  text-transform: capitalize;
                              //                               //    -webkit-print-color-adjust: exact;       color: #000;    } } ;
                              //                               //         @page {  size: 25mm 8mm;   margin: 0;      }
                              //                               //             body {margin: 0; padding: 0; font-family: Arial, sans-serif;  color: #000;      }
                              //                               //             .page-break {     display: block;     page-break-before: always; }
                              //                               //    .label-container { width:25mm;  height:8mm;
                              //                               //    text-align: center;
                              //                               //     border: 1px solid #000;

                              //                               //      }
                              //                             @page {
                              //   size: 25mm 8mm;
                              //   margin: 0;
                              // }

                              // @media print {
                              //   body {
                              //     margin: 0;
                              //     padding: 0;
                              //     box-sizing: border-box;
                              //   }

                              //   .container {
                              //     width: 25mm;
                              //     height: 8mm;
                              //     display: flex;
                              //     flex-direction: column;
                              //     align-items: center;
                              //     justify-content: center;
                              //     line-height: 0;

                              //     overflow: hidden; /* Ensure no content overflows */
                              //     page-break-inside: avoid; /* Avoid breaking the container */
                              //   }

                              //   .label-container {
                              //     width: 100%;
                              //     height: 100%;
                              //     font-size: 1px;
                              //     display: flex;
                              //     flex-direction: column;
                              //     align-items: center;
                              //     justify-content: center;
                              //     line-height: 0;

                              //     overflow: hidden; /* Ensure no content overflows */
                              //   }

                              //   .details,
                              //   .barcode,
                              //   .text-center,
                              //   .test-list {
                              //     text-align: center;
                              //     margin: 0;
                              //     padding:0;
                              //     line-height: 0;

                              //   }
                              // svg{
                              // height:5px !important;
                              // }
                              //   .page-break {
                              //     page-break-before: always;
                              //   }
                              // }

                              //                                       `;
                              //                               style.innerHTML = css;
                              //                               document.head.appendChild(style);

                              //                               // Temporarily replace body content with the print section
                              //                               document.body.innerHTML = printContent;

                              //                               // Trigger the print and then restore the original content
                              //                               window.print();

                              // Get HTML to print from element
                              const jobContent = document.getElementById("parcode").innerHTML;
                              // Get all stylesheets HTML

                              var css = `
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

                                                                    `;
                              // Create an iframe for printing

                              const printFrame = document.createElement("iframe");
                              printFrame.style.position = "absolute";
                              // Add iframe to the document
                              document.body.appendChild(printFrame);

                              // Write the content to the iframe
                              const frameDoc = printFrame.contentWindow.document;
                              frameDoc.open();
                              frameDoc.write(`
                                      <html>
                                          <head>
                                              <title>Print Job</title>
                                              <style>${css}</style>
                                          </head>
                                          <body>${jobContent}</body>
                                      </html>
                                  `);
                              document.innerHTML = jobContent;
                              frameDoc.close();

                              // Trigger print dialog from the iframe
                              printFrame.contentWindow.focus();
                              printFrame.contentWindow.print();

                              // Remove the iframe after printing or canceling
                              printFrame.contentWindow.onafterprint = () => {
                                   document.body.removeChild(printFrame);
                              };
                         }, 50);
                    });
               },
               openprintINvoiceTemplate(data) {
                    this.printRecord = data;
                    setTimeout(function () {
                         // Get HTML to print from element
                         const jobContent = document.getElementById("printInvoice").innerHTML;

                         var css = `@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } }


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
     } `; // Create an iframe for printing
                         const printFrame = document.createElement("iframe");
                         printFrame.style.position = "absolute";
                         printFrame.style.width = "0px";
                         printFrame.style.height = "0px";
                         printFrame.style.border = "none";

                         // Add iframe to the document
                         document.body.appendChild(printFrame);

                         // Write the content to the iframe
                         const frameDoc = printFrame.contentWindow.document;
                         frameDoc.open();
                         frameDoc.write(`
             <html>
                 <head>
                     <title>printInvoice</title>
                     <style>${css}</style>
                 </head>
                 <body>${jobContent}</body>
             </html>
         `);
                         frameDoc.close();
                         // Trigger print dialog from the iframe
                         printFrame.contentWindow.focus();
                         printFrame.contentWindow.print();

                         // Remove the iframe after printing or canceling
                         printFrame.contentWindow.onafterprint = () => {
                              document.body.removeChild(printFrame);
                         };
                    }, 50);

                    // Wait for the content to load, then trigger the print dialog
               },
               openthermalRecord(data) {
                    this.printRecord = data;
                    setTimeout(function () {
                         // Get HTML to print from element
                         const jobContent = document.getElementById("thermalRecord").innerHTML;

                         const css = ` @media print {   body,  .page {   width:Statement; margin: auto; !important;   box-shadow: 0; text-align:center;   -webkit-print-color-adjust: exact;       color: #000;    } } ;
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

                     `;
                         // Create an iframe for printing
                         const printFrame = document.createElement("iframe");
                         printFrame.style.position = "absolute";
                         printFrame.style.width = "0px";
                         printFrame.style.height = "0px";
                         printFrame.style.border = "none";

                         // Add iframe to the document
                         document.body.appendChild(printFrame);

                         // Write the content to the iframe
                         const frameDoc = printFrame.contentWindow.document;
                         frameDoc.open();
                         frameDoc.write(`
             <html>
                 <head>
                     <title>Print thermal</title>
                     <style>${css}</style>
                 </head>
                 <body>${jobContent}</body>
             </html>
         `);
                         frameDoc.close();
                         // Trigger print dialog from the iframe
                         printFrame.contentWindow.focus();
                         printFrame.contentWindow.print();

                         // Remove the iframe after printing or canceling
                         printFrame.contentWindow.onafterprint = () => {
                              document.body.removeChild(printFrame);
                         };
                    }, 50);

                    // Wait for the content to load, then trigger the print dialog
               },
               close() {
                    this.printInvoiceDialog = false;
               },
          },
     };
</script>
<style scoped>
     body {
          font-family: Arial, sans-serif;
          direction: rtl;
          background-color: #f9f9f9;
          padding: 20px;
     }

     .receipt {
          background: #fff;
          padding: 20px;
          border: 1px solid #ccc;
          max-width: 400px;
          margin: auto;
          font-size: 16px;
     }

     .row {
          display: flex;
          justify-content: space-between;
          padding: 10px 0;
          border-bottom: 1px solid #000;
     }

     .row:last-child {
          border-bottom: none;
     }

     .label {
          font-weight: bold;
     }

     .value {
          font-weight: normal;
     }

     .payment-info {
          flex-direction: column;
          align-items: flex-end;
          text-align: right;
     }
     tr {
          height: 55px;
     }
     td {
          border: 1px solid #0000002b;
     }
     thead {
          background: #374151;
          color: white;
          height: 35px;
     }
     .table {
          width: 100%;
          text-align: center;
          background: #f9fafb;
          display: table;
     }
     .parcode {
          display: flex;
          justify-content: flex-end;
          font-size: larger;
          font-weight: bold;
          border: 1px solid #00000047;
          padding: 24px;
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
</style>
