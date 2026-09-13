<template>
     <div class="sections">
          <div class="patient-info" style="display: flex; align-items: center; justify-content: space-around">
               <div class="qrcode">
                    <QrcodeVue :value="getPatientReportLink()" :size="90" level="H" render-as="svg" class="qr-code" />
               </div>
               <div class="section">
                    <table>
                         <tr>
                              <th style="font-size: 1px">
                                   <span>
                                        <strong>Barcode</strong>
                                   </span>
                              </th>
                              <td>
                                   <div style="text-align: center">
                                        <BarcodeComponent :value="printRecord?.barcode" />
                                        <span>{{ printRecord?.barcode }}</span>
                                   </div>
                              </td>
                         </tr>
                         <tr>
                              <th>
                                   <div>
                                        <strong>age /Sex</strong>
                                   </div>
                              </th>
                              <td>
                                   <div class="border">
                                        <strong>
                                             {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }}
                                             /
                                             {{ printRecord?.patient?.gender }}
                                        </strong>
                                   </div>
                              </td>
                         </tr>
                         <tr>
                              <th>
                                   <div>
                                        <strong>DOB</strong>
                                   </div>
                              </th>
                              <td>
                                   <div class="border">
                                        <strong>{{ printRecord?.patient?.dob }}</strong>
                                   </div>
                              </td>
                         </tr>
                         <tr>
                              <th>
                                   <div>
                                        <strong>referred by</strong>
                                   </div>
                              </th>
                              <td>
                                   <div class="border">
                                        <strong>{{ printRecord?.referral?.name || '-' }}</strong>
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
                                             <strong>{{ printRecord?.patient?.name }}</strong>
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
                                             <strong>
                                                  {{ dateFormat(printRecord?.registration_date) }}
                                             </strong>
                                        </div>
                                   </td>
                              </tr>
                              <tr>
                                   <th>
                                        <div>
                                             <strong>Card ID</strong>
                                        </div>
                                   </th>
                                   <td>
                                        <div class="border">
                                             <strong>{{ printRecord?.patient?.code }}</strong>
                                        </div>
                                   </td>
                              </tr>
                              <tr>
                                   <th>
                                        <div>
                                             <strong>printed at</strong>
                                        </div>
                                   </th>

                                   <td>
                                        <div class="border">
                                             <strong>{{ dateFormat(today) }}</strong>
                                        </div>
                                   </td>
                              </tr>
                         </table>
                    </div>
               </div>
          </div>
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import QrcodeVue from "qrcode.vue";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import BarcodeComponent from "../../../components/BarcodeComponent.vue";

     export default {
          data() {
               return {
                    today: "",
                    pdfUrl: null,
                    documentHeight: 0,
                    patientId: null,
               };
          },

          components: {
               BarcodeComponent,
               QrcodeVue,
          },
          created() {
               // Set today's date in YYYY-MM-DD format when the component is created
               const today = new Date();
               const year = today.getFullYear();
               const month = String(today.getMonth() + 1).padStart(2, "0"); // Add leading zero for months
               const day = String(today.getDate()).padStart(2, "0"); // Add leading zero for days
               this.today = `${year}-${month}-${day}`;
          },

          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord"]),
          },

          methods: {
               ...mapActions(useinvoicesStore, ["GetinvoicesById"]),

               getPatientReportLink() {
                    const url = `${window.location.origin}/result/${this.patientId}`;
                    return url;
               },
          },
     };
</script>
<style scoped>
     #Result {
          display: none;
     }
     body {
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
          margin: 0 auto;
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
     .section table tr {
          margin-bottom: 1px;
     }
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
          @media print {
               .print-page {
                    page-break-after: always;
               }

               .page-break {
                    page-break-before: always;
               }
          }
     }
     .page-break {
          page-break-before: always;
     }
     body {
          margin: 0;
          padding: 0;
     }
</style>
