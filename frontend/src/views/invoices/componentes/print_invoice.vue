<template>
     <div v-if="pdfUrl" class="pdf-preview">
          <iframe :src="pdfUrl" width="100%" :height="documentHeight" style="border: 1px solid #ccc"></iframe>
     </div>
     <div class="container" id="printInvoice" ref="contentToConvert">
          <br />
          <br />
          <br />
          <br />
          <br />
          <br />
          <section class="patient-info">
               <div class="qrcode">
                    <img :src="printRecord?.pdf_qr_code" alt="QR Code" class="qrcode" />
               </div>
               <div class="section">
                    <table>
                         <tr>
                              <th>
                                   <span>
                                        <strong>Barcode</strong>
                                   </span>
                              </th>
                              <td>
                                   <span style="display: flex; flex-direction: column; align-items: center">
                                        <img :src="generateBarcodeImage(printRecord?.barcode)" alt="Barcode" />
                                        <strong>{{ printRecord?.patient?.code }}</strong>
                                   </span>
                              </td>
                         </tr>
                         <tr>
                              <th>
                                   <div>
                                        <strong>age / Sex</strong>
                                   </div>
                              </th>
                              <td>
                                   <div class="border">
                                        <strong>
                                             {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }} /
                                             {{ printRecord?.patient?.gender }}
                                        </strong>
                                   </div>
                              </td>
                         </tr>
                         <tr>
                              <th>
                                   <div>
                                        <strong>Referred By</strong>
                                   </div>
                              </th>
                              <td>
                                   <div class="border">
                                        <strong>{{ jobOrder?.referral?.name }}</strong>
                                   </div>
                              </td>
                         </tr>
                    </table>
               </div>

               <div class="patient-details">
                    <div class="sectionItem">
                         <table>
                              <tr>
                                   <th>
                                        <div>
                                             <strong>patient name</strong>
                                        </div>
                                   </th>
                                   <td>
                                        <div class="border">
                                             <strong>
                                                  {{ printRecord?.patient?.name }}
                                             </strong>
                                        </div>
                                   </td>
                              </tr>
                              <tr>
                                   <th>
                                        <div>
                                             <strong>Request Date</strong>
                                        </div>
                                   </th>
                                   <td>
                                        <div class="border">
                                             <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                                        </div>
                                   </td>
                              </tr>
                              <tr>
                                   <th>
                                        <div>
                                             <strong>Result date</strong>
                                        </div>
                                   </th>

                                   <td>
                                        <div class="border">
                                             <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
                                        </div>
                                   </td>
                              </tr>
                         </table>
                    </div>
               </div>
          </section>
          <div v-if="printRecord?.test_groups?.length > 0">
               <section class="test-details" v-for="(test_group, index) in printRecord?.test_groups" :key="index">
                    <div class="caption">
                         {{ test_group.group_name }}
                    </div>
                    <table class="test-table">
                         <thead>
                              <tr>
                                   <th>tests</th>
                                   <th>Price</th>
                              </tr>
                         </thead>
                         <tbody>
                              <tr v-for="(item, index) in test_group?.tests" :key="index">
                                   <td>{{ item.report_name }}</td>
                                   <td>{{ item.price }}</td>
                              </tr>
                              <tr v-for="(item, index) in test_group?.cultures" :key="index">
                                   <td>{{ item.name }}</td>
                                   <td>{{ item.price }}</td>
                              </tr>
                         </tbody>
                    </table>
               </section>
          </div>
          <div v-if="printRecord?.packages?.length > 0">
               <section class="test-details" v-for="(pkg, index) in printRecord?.packages" :key="index">
                    <div class="caption">
                         {{ pkg.name }}
                    </div>
                    <table class="test-table">
                         <thead>
                              <tr>
                                   <th>tests</th>
                                   <th>Price</th>
                              </tr>
                         </thead>
                         <tbody>
                              <tr v-for="(item, index) in pkg?.tests" :key="index">
                                   <td>{{ item.report_name }}</td>
                                   <td>{{ item.price }}</td>
                              </tr>
                              <tr v-for="(item, index) in pkg?.cultures" :key="index">
                                   <td>{{ item.name }}</td>
                                   <td>{{ item.price }}</td>
                              </tr>
                         </tbody>
                    </table>
               </section>
          </div>
          <section class="test-details" v-if="printRecord?.tests?.length > 0 || printRecord?.cultures?.length > 0">
               <table class="test-table">
                    <thead>
                         <tr>
                              <th>tests</th>
                              <th>Price</th>
                         </tr>
                    </thead>
                    <tbody>
                         <tr v-for="(item, index) in printRecord?.tests" :key="index">
                              <td>{{ item.report_name }}</td>
                              <td>{{ item.price }}</td>
                         </tr>
                         <tr v-for="(item, index) in printRecord?.cultures" :key="index">
                              <td>{{ item.name }}</td>
                              <td>{{ item.price }}</td>
                         </tr>
                         <tr v-for="(item, index) in printRecord?.packages" :key="index">
                              <td>{{ item.name }}</td>
                              <td>{{ item.price }}</td>
                         </tr>
                    </tbody>
               </table>
          </section>

          <section class="summary">
               <table style="width: 100%">
                    <tr>
                         <th>Subtotal:</th>
                         <td>
                              <strong>{{ printRecord?.sub_total }}</strong>
                         </td>
                    </tr>
                    <tr>
                         <th>discount:</th>
                         <td>
                              <strong>{{ printRecord?.discount }}</strong>
                         </td>
                    </tr>
                    <tr>
                         <th>Total :</th>
                         <td>
                              <strong>{{ printRecord?.total }}</strong>
                         </td>
                    </tr>
                    <tr>
                         <th>paid:</th>
                         <td>
                              <strong>{{ printRecord?.paid }}</strong>
                         </td>
                    </tr>
                    <tr>
                         <th>Due:</th>
                         <td>
                              <strong>{{ printRecord?.total - printRecord?.paid }}</strong>
                         </td>
                    </tr>
               </table>
          </section>
          <br />
          <br />
          <br />
          <br />
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import JsBarcode from "jsbarcode";
     import html2pdf from "html2pdf.js";
     import QrcodeVue from "qrcode.vue";
     import { useinvoicesStore } from "@/store/modules/invoices";

     export default {
          data() {
               return {
                    isfromRoute: false,
                    invoiceId: null,
                    documentHeight: 0,
                    pdfUrl: null,
               };
          },
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord"]),
          },

          mounted() {
               this.invoiceId = this.$route.params.invoiceId;
               if (this.invoiceId) {
                    this.getData();
               }

               this.documentHeight = document.documentElement.scrollHeight;
               if (this.isfromRoute) {
                    this.openprintINvoiceTemplate([]);
               }
          },

          methods: {
               ...mapActions(useinvoicesStore, ["GetinvoicesById"]),
               getData() {
                    this.GetinvoicesById(this.patientId).then(() => {
                         this.generatePDF();
                    });
               },
               getPatientReportLink() {
                    // Construct the full URL to the medical reports page
                    // This could be a relative or absolute URL depending on your routing
                    const url = `${window.location.origin}/result/${this.patientId}`;
                    return url;
                    // Or if using vue-router
                    // return this.$router.resolve({
                    //   name: 'medical-reports',
                    //   params: { patientId: patientId }
                    // }).href
               },
               async generatePDF() {
                    document.getElementById("printInvoice").style.display = "block";
                    const element = this.$refs.contentToConvert;
                    // Wait for the image to load

                    const opt = {
                         margin: 0.5,
                         filename: "medical-report.pdf",
                         image: { type: "jpeg", quality: 0.98 },
                         html2canvas: { scale: 4 },
                         jsPDF: {
                              unit: "in",
                              format: "a4",
                              orientation: "portrait",
                         },
                    };

                    // // Generate and save PDF
                    // html2pdf().set(opt).from(element).save();

                    // Generate PDF as data URL for preview

                    await html2pdf()
                         .set(opt)
                         .from(element)
                         .outputPdf("datauristring")
                         .then((pdfDataUri) => {
                              this.pdfUrl = pdfDataUri;
                         });

                    document.getElementById("printInvoice").style.display = "none";
               },
               generateBarcodeImage(value) {
                    if (!value) return "";

                    const canvas = document.createElement("canvas");
                    JsBarcode(canvas, value, {
                         format: "CODE128",
                         displayValue: false,
                         width: 1,
                         height: 15,
                    });
                    return canvas.toDataURL("image/png");
               },
               openprintINvoiceTemplate(data) {
                    this.printRecord = data;
                    setTimeout(function () {
                         // Get HTML to print from element
                         const jobContent = document.getElementById("printInvoice").innerHTML;
                         const head = document.head?.innerHTML || document.getElementsByTagName("head")[0]?.innerHTML;
                         // Get all stylesheets HTML

                         var css = `@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } }`;

                         const newWindow = window.open("", "_blank");
                         newWindow.document.write(`
                           <html>
                             <head>
                               <title>printINvoice Template</title>
                               <style>
                               ${css}
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
                               ${jobContent}
                             </body>
                           </html>
                         `);
                         // Open the print window
                         const WinPrint = window.open(
                              "",
                              "",
                              "left=10,top=100,width=800,height=900,toolbar=0,scrollbars=0,status=0"
                         );

                         newWindow.document.close(); // Close the document to complete rendering
                         newWindow.onload = () => {
                              newWindow.print();
                              newWindow.onafterprint = () => {
                                   newWindow.close(); // Close the window after printing
                              };
                         };
                         setTimeout(() => {
                              WinPrint?.focus();
                              WinPrint?.print();
                         }, 1000);
                    }, 50);
               },
          },
     };
</script>
<style scoped>
     #printInvoice {
          display: none;
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
     .caption {
          width: 100%;
          font-weight: bold;
          border: 1px solid black;
          padding: 5px;
          text-align: center;
          color: black;
          background: #dddddce0;
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

          .header,
          .patient-info,
          .test-details,
          .pricing-info {
               page-break-inside: avoid;
          }
     }
</style>
