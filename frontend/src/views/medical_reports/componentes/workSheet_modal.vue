<template>
     <Dialog
          v-model:visible="print_work_sheetDialog"
          modal
          :header="t('print_work_sheet')"
          style="width: 80rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <table class="table table-bordered">
                    <thead>
                         <tr>
                              <th style="width: 87%; text-align: -webkit-auto">{{ t("the_tests") }}</th>
                              <th>{{ t("done") }}</th>
                              <th>
                                   <Button
                                        size="small"
                                        :icon="isAllSelected ? 'pi pi-check-circle' : 'pi pi-circle'"
                                        severity="success"
                                        @click="checkAll()"></Button>
                              </th>
                         </tr>
                    </thead>
                    <tbody>
                         <tr
                              v-for="(item, index) in printRecord.tests"
                              :key="index"
                              :class="{ 'odd-row': index % 2 === 0, 'even-row': index % 2 !== 0 }">
                              <td
                                   :class="{ 'text-danger': !item.is_done, 'text-success': item.is_done }"
                                   style="width: 87%; text-align: -webkit-auto">
                                   {{ item.name }}
                              </td>
                              <td>
                                   <i class="pi pi-check-circle text-success" v-if="item.is_done"></i>

                                   <i class="pi pi-power-off text-danger" v-if="!item.is_done"></i>
                              </td>
                              <td>
                                   <input type="checkbox" :value="item" v-model="selectedItems" />
                              </td>
                         </tr>
                         <tr
                              v-for="(item, index) in printRecord.cultures"
                              :key="index"
                              :class="{ 'even-row': index % 2 === 0, 'odd-row': index % 2 !== 0 }">
                              <td
                                   :class="{ 'text-danger': !item.is_done, 'text-success': item.is_done }"
                                   style="width: 87%; text-align: -webkit-auto">
                                   {{ item.name }}
                              </td>
                              <td>
                                   <i class="pi pi-check-circle text-success" v-if="item.is_done"></i>

                                   <i class="pi pi-power-off text-danger" v-if="!item.is_done"></i>
                              </td>
                              <td>
                                   <input type="checkbox" :value="item" v-model="selectedItems" />
                              </td>
                         </tr>
                         <tr
                              v-for="(item, index) in printRecord.packages"
                              :key="index"
                              :class="{ 'odd-row': index % 2 === 0, 'even-row': index % 2 !== 0 }">
                              <td
                                   :class="{ 'text-danger': !item.is_done, 'text-success': item.is_done }"
                                   style="width: 87%; text-align: -webkit-auto">
                                   {{ item.name }}
                              </td>
                              <td>
                                   <i class="pi pi-check-circle text-success" v-if="item.is_done"></i>

                                   <i class="pi pi-power-off text-danger" v-if="!item.is_done"></i>
                              </td>
                              <td>
                                   <input type="checkbox" :value="item" v-model="selectedItems" />
                              </td>
                         </tr>
                    </tbody>
               </table>
          </div>
          <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               <Button
                    size="small"
                    :label="t('print')"
                    severity="success"
                    @click="print()"
                    :disabled="selectedItems.length == 0"></Button>
          </div>
     </Dialog>

     <div class="report-container" id="worksheet">
          <br />
          <br />
          <br />
          <br />
          <br />
          <br />
          <!-- Header Section -->
          <div class="header-section">
               <div class="details">
                    <table>
                         <tr>
                              <th>
                                   <span>
                                        <strong>Barcode</strong>
                                   </span>
                              </th>
                              <td>
                                   <span>
                                        <div>
                                             <span>
                                                  <img
                                                       :src="generateBarcodeImage(printRecord?.barcode)"
                                                       alt="Barcode" />
                                             </span>
                                        </div>
                                   </span>
                              </td>
                         </tr>
                         <tr>
                              <th>
                                   <div>
                                        <strong>age/ Sex</strong>
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
                    </table>
               </div>

               <div class="details">
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
                    </table>
               </div>
          </div>

          <!-- Test Sections -->
          <div class="test-section" v-for="test in selectedItems" :key="test">
               <h3>{{ test.name }}</h3>
               <table class="table">
                    <thead>
                         <tr>
                              <th>name</th>
                              <th>Result</th>
                              <th>Unit</th>
                              <th>tests reference ranges</th>
                              <th>the Status</th>
                         </tr>
                    </thead>
                    <tbody>
                         <tr>
                              <td>{{ test.name }}</td>
                              <td></td>
                              <td></td>
                              <td></td>
                              <td></td>
                         </tr>
                         <tr class="commit">
                              <td>Comment:</td>

                              <td></td>
                              <td></td>
                              <td></td>
                              <td></td>
                         </tr>
                    </tbody>
               </table>
          </div>
          <br />
          <br />
          <br />
          <br />
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import JsBarcode from "jsbarcode";
     import { useinvoicesStore } from "@/store/modules/invoices";

     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, [
                    "printRecord",
                    "print_work_sheetDialog",
                    "selectedItems",
                    "isAllSelected",
               ]),
          },

          methods: {
               generateBarcodeImage(value) {
                    if (!value) return "";

                    const canvas = document.createElement("canvas");
                    JsBarcode(canvas, value, {
                         format: "CODE128",
                         displayValue: true,
                         width: 1,
                         height: 15,
                    });
                    return canvas.toDataURL("image/png");
               },
               checkAll() {
                    if (this.isAllSelected) {
                         // Deselect all items
                         this.selectedItems = [];
                    } else {
                         // Select all items
                         this.selectedItems = [
                              ...this.printRecord?.tests,
                              ...this.printRecord?.cultures,
                              ...this.printRecord?.packages,
                         ];
                    }
                    this.isAllSelected = !this.isAllSelected;
               },

               print() {
                    setTimeout(function () {
                         // Get HTML to print from element
                         const jobContent = document.getElementById("worksheet").innerHTML;
                         const head = document.head?.innerHTML || document.getElementsByTagName("head")[0]?.innerHTML;
                         // Get all stylesheets HTML

                         var css = `@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } } 
 
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
    }     `;
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
                     <title>Print Job</title>
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
               },
               close() {
                    this.print_work_sheetDialog = false;
                    this.selectedItems = [];
                    this.isAllSelected = false;
               },
          },
          watch: {
               selectedItems() {
                    // If all items are selected, update the isAllSelected flag
                    this.isAllSelected =
                         this.selectedItems.length ===
                         this.printRecord?.tests.length +
                              this.printRecord?.cultures.length +
                              this.printRecord?.packages.length;
               },
          },
     };
</script>
<style scoped>
     #worksheet {
          display: none;
     }
     .table {
          width: 100%;
          border: 1px solid #0000005c;
          padding: 7px;
          text-align: center;
          background: rgb(132 134 138 / 3%);
          font-size: large;
     }
     thead {
          background: #004e54;
          color: white;
     }
     .table th,
     .table td {
          text-align: center;
          padding: 11px;
     }

     .text-success {
          color: green;
     }

     .text-danger {
          color: red;
     }
     .odd-row {
          background-color: #e5e7eb; /* light gray for odd rows */
     }

     .even-row {
          background-color: #ffffff; /* white for even rows */
     }
</style>
