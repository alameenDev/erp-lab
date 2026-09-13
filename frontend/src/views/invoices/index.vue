<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("invoices") }}</h5>
               <Button v-if="havePermission('invoices create')" size="small" class="p-button-success" :label="t('add')"
                    @click="addRecord"></Button>
          </div>
          <div class="card flex justify-content-center mb-5" v-if="pagination.total?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <br />
               <div class="globalSearch">
                    <input v-model="Filters.patient_name" :placeholder="t('search') + '...'" class="search p-inputtext p-component mb-2" />
                    <div class="btns">
                         <Button :label="t('Clear Filters')" icon="pi pi-filter-slash" @click="clearFilters"
                              class="p-button-secondary mb-2" />
                         <Button :label="t('Export to Excel')" icon="pi pi-file-excel" @click="exportToExcel"
                              class="p-button-success mb-2" />
                    </div>
               </div>
               <br />

               <DataTable size="small" :value="filteredRecords" scrollable scrollHeight="600px" responsiveLayout="scroll" :paginator="true"
                    :lazy="true" :rows="pagination.per_page" :totalRecords="pagination.total"
                    :first="(pagination.current_page - 1) * pagination.per_page" @page="onPageChange">
                    <template #empty>
                         <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                              {{ t("noData") }}
                         </div>
                    </template>
                    <Column class="text-center" header="#" field="index" />
                    <Column class="text-center" field="patient.name" style="min-width: 250px">
                         <template #header>
                              <p>{{ t("Pationt_name") }}</p>

                              <input v-model="Filters.patient_name" :placeholder="t('search') + '...'" class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <Button style="background: green; min-width: 200px" v-if="slotProps.data?.patient?.name"
                                   :label="slotProps.data?.patient?.name" class="p-eye-button mx-1"></Button>
                         </template>
                    </Column>
                    <!-- <Column class="text-center" field="registration_date" style="min-width: 150px">
                         <template #header>
                              <p>{{ t("Registration_date") }}</p>
                              <Calendar
                                   v-model="Filters.registration_date"
                                   :placeholder="t('search') + '...'"
                                   showIcon
                                   dateFormat="yy-mm-dd" />
                         </template>
                         <template #body="slotProps">
                              {{ dateTimeFormat(slotProps.data.registration_date) }}
                         </template>
                    </Column> -->
                    <Column class="text-center" field="lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input v-model="Filters.lab" :placeholder="t('search') + '...'" class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data?.lab }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="created_by.name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Created_By") }}</p>
                              <input v-model="Filters.created_by" :placeholder="t('search') + '...'" class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data?.created_by?.name }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="contract.name" v-if="contracts" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("contract") }}</p>
                              <Dropdown v-model="Filters.contract_id_fk" :options="contracts" optionLabel="label" optionValue="value"
                                   :placeholder="t('search') + '...'" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data?.contract?.name }}</p>
                         </template>
                    </Column>

                    <Column class="text-center" field="from_lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("from_lab") }}</p>
                              <input v-model="Filters.from_lab" :placeholder="t('search') + '...'" class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data?.from_lab }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="barcode" style="min-width: 150px">
                         <template #header>
                              <p>{{ t("Barcode") }}</p>
                              <input v-model="Filters.barcode" :placeholder="t('search') + '...'" class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <div v-if="canAccess(['sample_collector'])">
                                   <span>{{ slotProps.data?.barcode }}</span>
                                   <div>
                                        <BarcodeComponent :value="slotProps.data?.barcode" />
                                   </div>
                              </div>
                              <button class="Parcode-button mx-1" @click="printParcode(slotProps.data)"
                                   v-if="cannotAccess(['sample_collector'])">
                                   <span>{{ slotProps.data?.barcode }}</span>
                                   <div>
                                        <BarcodeComponent :value="slotProps.data?.barcode" />
                                   </div>
                              </button>
                         </template>
                    </Column>
                    
                    <!-- Status Column -->
                    <Column class="text-center" field="sent_to_patient" style="min-width: 80px">
                         <template #header>
                              <p>{{ t("theStatus") }}</p>
                         </template>
                         <template #body="slotProps">
                              <i class="pi pi-circle-fill"
                                   :class="slotProps.data.sent_to_patient == true ? 'sucess' : 'danger'"></i>
                         </template>
                    </Column>

                    <!-- Single Actions Column with Dropdown Menu -->
                    <Column
                         class="text-center"
                         field="all_actions"
                         style="min-width: 250px">
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <!-- Patient Details Button -->
                              <Button
                                   icon="pi pi-eye"
                                   class="p-button-rounded p-button-text p-button-info"
                                   @click="showPatient(slotProps.data?.patient)"
                                   v-tooltip.top="t('Patient_details')" />

                              <!-- Patient Due Button -->
                              <Button
                                   icon="pi pi-receipt"
                                   class="p-button-rounded p-button-text p-button-help"
                                   @click="showPatient_due(slotProps.data)"
                                   v-tooltip.top="t('Patient_due')" />

                              <!-- Edit Button -->
                              <Button
                                   v-if="havePermission('invoices edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded p-button-text p-button-warning"
                                   @click="editRecord({ data: slotProps.data })"
                                   v-tooltip.top="t('edit')" />

                              <!-- Menu Button -->
                              <Button
                                   icon="pi pi-ellipsis-v"
                                   class="p-button-rounded p-button-text"
                                   @click="toggleMenu($event, slotProps.data.id)"
                                   aria-haspopup="true"
                                   :aria-controls="'menu_' + slotProps.data.id" />
                              <Menu
                                   :ref="el => setMenuRef(el, slotProps.data.id)"
                                   :id="'menu_' + slotProps.data.id"
                                   :model="getMenuItems(slotProps.data)"
                                   :popup="true" />
                         </template>
                    </Column>
               </DataTable>
          </div>
          <invoicesModal></invoicesModal>
          <patient-modal></patient-modal>
          <due-modal></due-modal>
          <job_orderModal></job_orderModal>
          <thermalReciptModal></thermalReciptModal>
          <printInvoice></printInvoice>
          <whatsUp></whatsUp>
          <parcodeModal></parcodeModal>
     </div>
</template>

<script>
import { mapActions, mapWritableState } from "pinia";
import invoicesModal from "./componentes/invoices_modal.vue";
import patientModal from "./componentes/patient_modal.vue";
import DueModal from "./componentes/due_modal.vue";
import whatsUp from "./componentes/whatsUp_moadal.vue";
import { useinvoicesStore } from "@/store/modules/invoices";
import { showAlertWithConfirm } from "@/utils/helper";
import { useContractsStore } from "@/store/modules/contracts";
import job_orderModal from "./componentes/job_orderModal.vue";
import printInvoice from "./componentes/print_invoice.vue";
import thermalReciptModal from "./componentes/thermal_reciptModal.vue";
import parcodeModal from "./componentes/parcodeModal.vue";
import { format } from "date-fns";
import * as XLSX from "xlsx";
import BarcodeComponent from "../../components/BarcodeComponent.vue";
import { usePatientsStore } from "@/store/modules/patients";
import { useAuthStore } from '@/store/modules/auth'
import Menu from 'primevue/menu';

export default {
     data() {
          return {
               Filters: {
                    from_lab: "",
                    contract_id_fk: "",
                    created_by: "",
                    barcode: "",
                    patient_name: "",
                    lab: "",
               },
               globalFilter: "", // Global filter input
               menuRefs: {}, // Store menu references
          };
     },
     components: {
          whatsUp,
          invoicesModal,
          patientModal,
          job_orderModal,
          DueModal,
          thermalReciptModal,
          printInvoice,
          parcodeModal,
          BarcodeComponent,
          Menu,
     },

     mounted() {
          this.Getinvoices();
     },
     computed: {
          ...mapWritableState(useContractsStore, ["contracts"]),
          ...mapWritableState(useinvoicesStore, [
               "invoices",
               "pagination",
               "Pdfurl",
               "record",
               "dialog",
               "patientdialog",
               "patient",
               "Patient_due",
               "Patient_dueDialog",
               "discountPercentage",
               "discountValue",
               "testQuesions",
               "tests_ids",
               "payment_details",
               "selectedContract",
               "selectedTests",
               "selectedPackages",
               "selectedtestGroups",
               "selectedCultures",
               "selectedreferal",
               "patient",
               "selectedCollector",
               "printRecord",
               "WhatsUpDialog",
          ]),
          ...mapWritableState(usePatientsStore, {
               thepatientDialog: "dialog",
               patientRecord: "record",
               responseData: "responseData",
          }),
          ...mapWritableState(useAuthStore, ["havePermission"]),
          filteredRecords() {
               return this.invoices.filter((record) => {
                    // Apply global filter
                    const matchesGlobalFilter = this.globalFilter
                         ? record.barcode?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.from_lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.contract?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.created_by?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.patient?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.patient?.lab?.toLowerCase().includes(this.globalFilter.toLowerCase())
                         : true;

                    // Apply column-specific filters
                    const matchesColumnFilters =
                         (!this.Filters.created_by ||
                              record.created_by?.name
                                   ?.toLowerCase()
                                   .includes(this.Filters.created_by.toLowerCase())) &&
                         (!this.Filters.lab ||
                              recordlab?.toLowerCase().includes(this.Filters.lab.toLowerCase())) &&
                         (!this.Filters.contract_id_fk || record.contract?.id === this.Filters.contract_id_fk) &&
                         (!this.Filters.from_lab ||
                              record.from_lab?.toLowerCase().includes(this.Filters.from_lab.toLowerCase())) &&
                         (!this.Filters.barcode ||
                              record.barcode?.toLowerCase().includes(this.Filters.barcode.toLowerCase())) &&
                         (!this.Filters.patient_name ||
                              record.patient?.name
                                   ?.toLowerCase()
                                   .includes(this.Filters.patient_name.toLowerCase()));

                    // Return true if it matches both the global filter and the column filters
                    return matchesGlobalFilter && matchesColumnFilters;
               });
          },
     },
     methods: {
          downloadPDF() {
               this.$refs.DownloadComp.generatePdf();
          },
          onPageChange(event) {
               this.pagination.current_page = event.page + 1;
               this.Getinvoices();
          },
          ...mapActions(useinvoicesStore, [
               "Getinvoices",
               "patient_medical_records",
               "Removeinvoices",
               "pdf",
               "getsamples",
          ]),

          showPatient(patient) {
               this.patient = patient;
               this.patientdialog = true;
          },
          showPatient_due(Patient_due) {
               this.Patient_due = Patient_due;
               this.Patient_dueDialog = true;
          },

          deleteRecord(record) {
               showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                    if (res.value) {
                         this.record.id = record.id;
                         this.Removeinvoices();
                    }
               });
          },

          setMenuRef(el, id) {
               if (el) {
                    this.menuRefs[id] = el;
               }
          },

          toggleMenu(event, menuId) {
               const menu = this.menuRefs[menuId];
               if (menu) {
                    menu.toggle(event);
               }
          },

          getMenuItems(data) {
               const items = [];

               // Job Order
               if (this.havePermission('invoices job order')) {
                    items.push({
                         label: this.t('job_order'),
                         icon: 'pi pi-book',
                         command: () => this.openJobTemplateAsPDF(data)
                    });
               }

               // Thermal Receipt
               if (this.havePermission('invoices thermal reciept print')) {
                    items.push({
                         label: this.t('thermal_recipt'),
                         icon: 'pi pi-receipt',
                         command: () => this.openthermalRecord(data)
                    });
               }

               // Print Invoice
               if (this.havePermission('invoices print')) {
                    items.push({
                         label: this.t('printInvoice'),
                         icon: 'pi pi-print',
                         command: () => this.openprintINvoiceTemplate(data)
                    });
               }

               // WhatsApp Send
               if (this.havePermission('invoices send whatsapp')) {
                    items.push({
                         label: this.t('send'),
                         icon: 'pi pi-whatsapp',
                         command: () => this.sendWhatsUp(data)
                    });
               }

               // Separator
               if (items.length > 0) {
                    items.push({ separator: true });
               }

               // Delete
               if (this.havePermission('invoices delete')) {
                    items.push({
                         label: this.t('delete'),
                         icon: 'pi pi-trash',
                         class: 'text-danger',
                         command: () => this.deleteRecord(data)
                    });
               }

               return items;
          },

          clearFilters() {
               this.Filters = {
                    from_lab: "",
                    contract_id_fk: "",
                    created_by: "",
                    barcode: "",
                    patient_name: "",
                    lab: "",
               };
               this.globalFilter = ""; // Clear global filter
          },

          exportToExcel() {
               // Create a worksheet from the filtered records
               const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                    header: ["index", "from_lab", "contract_id_fk", "created_by", "barcode", "lab"],
               });

               // Create a new workbook and append the worksheet
               const wb = XLSX.utils.book_new();
               XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

               // Write the workbook to a file
               XLSX.writeFile(wb, "export.xlsx");
          },
          editRecord(record) {
               this.payment_details = [
                    {
                         amount: null, // $request->paid,
                         contract_id_fk: null, //
                         payment_method_id_fk: null, // $request->payment_method_id_fk
                    },
               ];

               Object.assign(this.record, record.data);
               this.payment_details = record.data?.paidDetails;
               this.selectedContract = record.data?.contract;
               this.selectedreferal = record.data?.referral;
               this.record.referral_id_fk = record.data?.referral?.id;
               this.selectedCollector = record.data?.sample_collector;
               this.record.sub_total = record.data?.sub_total;
               this.record.total = record.data?.total;
               this.record.contract_id_fk = record.data?.contract?.id;
               this.record.sample_collector_id_fk = record.data?.sample_collector?.id;
               this.record.registration_date = this.dateFormat(record.data?.registration_date);
               this.record.result_date = this.dateFormat(record.data?.result_date);
               this.selectedTests = record.data?.tests;
               this.selectedPackages = record.data?.packages;
               this.selectedCultures = record.data?.cultures;
               this.selectedtestGroups = record.data?.test_groups;

               this.responseData = record.data?.patient;
               this.discountPercentage = record.data?.discount_type_id_fk == 2 ? record.data?.discount : null;
               this.discountValue = record.data?.discount_type_id_fk == 3 ? record.data?.discount : null;
               // Populate testQuestions based on the selected tests and their existing questions

               this.testQuesions = this.selectedTests?.map((test) => {
                    return {
                         test_name: test.name,
                         test_id_fk: test.test_id_fk,
                         questions: test.questions?.map((q) => ({
                              question: q.question.question,
                              answer_type: q.question.answer_type,
                              answer_type_id_fk: q.question.answer_type_id_fk,
                              answer_type_selection_values: q.question.answer_type_selection_values,
                              answer: q.answer,
                         })),
                    };
               });

               this.dialog = true;
          },
          addRecord() {
               this.selectedContract = [];
               this.selectedreferal = [];
               this.selectedTests = [];
               this.selectedPackages = [];
               this.selectedCultures = [];
               this.selectedtestGroups = [];
               this.testQuesions = [];
               this.payment_details = [
                    {
                         amount: null, // $request->paid,
                         contract_id_fk: null, //
                         payment_method_id_fk: null, // $request->payment_method_id_fk
                    },
               ];
               this.patient = [];
               this.discountPercentage = 0;
               this.discountValue = 0;
               this.clearObjectValues(this.record);
               this.record.registration_date = this.dateFormat(new Date().toISOString());

               this.dialog = true;
          },
          openJobTemplateAsPDF(data) {
               this.printRecord = data;
               setTimeout(function () {
                    // Get HTML to print from element
                    const jobContent = document.getElementById("job").innerHTML;

                    // CSS for styling print view
                    const css = `
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
          openthermalRecord(data) {
               this.printRecord = data;

               setTimeout(function () {
                    // Get HTML to print from element

                    const css = ` @media print {   body {   width: auto;
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
                    //                      `;
                    const receipt = document.getElementById("thermalRecord").innerHTML;
                    const printWindow = window.open("", "_blank");
                    printWindow.document.write(
                         `<html><head><title>Receipt</title><style>${css}
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
</head><body>${receipt}</body></html>`
                    );
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
               }, 50);

               // Wait for the content to load, then trigger the print dialog
          },
          openprintINvoiceTemplate(data) {
               this.printRecord = data;
               setTimeout(function () {
                    // Get HTML to print from element
                    const jobContent = document.getElementById("printInvoice").innerHTML;

                    var css = `@page { size: portrait;  margin: 0 !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    } }


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
          printParcode(data) {
               this.printRecord = data;
               this.getsamples(data.id).then((res) => {
                    setTimeout(function () {

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
          async sendWhatsUp(record) {
               this.printRecord = record;

               const labName = this.User.name; // Replace with the dynamic lab name
               const patientName = this.printRecord?.patient?.name; // Replace with the dynamic patient name

               // const encodedRecord = encodeURIComponent(JSON.stringify(record));
               const url = `${window.location.origin}/invoice/${record.id}`;
               const message = `اهلا بكم في مختبر ${labName}  عزيزي  ${patientName} يمكنك الحصول على الفاتورة من خلال الضغط على الرابط ادناه \n\n${url}`;
               const encodedMessage = encodeURIComponent(message);
               let phoneNumber = this.printRecord?.patient?.phone; // Replace with the dynamic phone number if needed
               // Remove leading 0 from phone number (e.g., 077... becomes 77...)
               if (phoneNumber && phoneNumber.startsWith('0')) {
                    phoneNumber = phoneNumber.substring(1);
               }

               const whatsAppUrl = `https://wa.me/+964${phoneNumber}?text=${encodedMessage}`;

               window.open(whatsAppUrl, "_blank");
          },

          isSameDate(date1, date2) {
               // Convert both dates to the same format (e.g., 'yyyy-mm-dd') for comparison
               const formattedDate1 = this.formatDate(date1);
               const formattedDate2 = this.formatDate(date2);
               return formattedDate1 === formattedDate2;
          },
          formatDate(date) {
               // Assuming date is a string in the format 'yyyy-mm-dd' or a Date object
               const d = new Date(date);
               const year = d.getFullYear();
               const month = String(d.getMonth() + 1).padStart(2, "0");
               const day = String(d.getDate()).padStart(2, "0");
               return `${year}-${month}-${day}`;
          },
     },
     watch: {
          Filters: {
               deep: true,
               handler() {
                    this.pagination.current_page = 1;
                    this.Getinvoices(this.Filters);
               }
          }
     },
};
</script>
<style scoped>
.sucess {
     color: green;
}

.danger {
     color: red;
}

.Parcode-button {
     background: none;
     border: none;
     cursor: pointer;
}

/* Actions Menu Button Styles */
:deep(.p-button-text) {
     color: var(--primary-color);
     transition: all 0.3s ease;
}

:deep(.p-button-text:hover) {
     background-color: var(--primary-color);
     color: white;
}

:deep(.p-menu .p-menuitem-link) {
     transition: all 0.2s ease;
}

:deep(.p-menu .p-menuitem-link:hover) {
     background-color: var(--primary-50);
}

:deep(.text-danger) {
     color: #dc3545 !important;
}

:deep(.text-success) {
     color: #28a745 !important;
}

/* Add gap between icon and text in menu item link */
:deep(.p-menu .p-menuitem-link) {
     gap: 0.75rem !important;
}

/* Add gap between menu items */
:deep(.p-menu .p-menuitem) {
     margin-bottom: 0.5rem !important;
}

:deep(.p-menu .p-menuitem:last-child) {
     margin-bottom: 0 !important;
}
</style>
