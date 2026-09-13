<template>
     <section class="sign" v-if="printRecord?.signed_by?.image">
          <img :src="printRecord?.signed_by?.image" width="100" height="100" />
     </section>
     <section class="patien-hestory" v-if="printRecord?.tests_last_results.length > 0">
          <h3>patient History</h3>
          <table class="test-table">
               <thead>
                    <tr>
                         <th>test</th>
                         <th>Result</th>
                         <th>date</th>
                    </tr>
               </thead>
               <tbody>
                    <tr v-for="(item, index) in printRecord?.tests_last_results" :key="index">
                         <td>
                              {{ item.name }}
                         </td>
                         <td>
                              {{ item.result }}
                         </td>
                         <td>
                              {{ item.result_date }}
                         </td>
                    </tr>
               </tbody>
          </table>
     </section>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";
     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord"]),
          },

          methods: {
               ...mapActions(useinvoicesStore, ["GetinvoicesById"]),
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
