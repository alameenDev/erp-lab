<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("medical_reports") }}</h5>
          </div>

          <div class="card flex justify-content-center mb-5" v-if="pagination.total?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <br />
               <br />
               <div class="flex justify-content-end mb-2">
                    <Button label="هوامش الطباعة" icon="pi pi-print" @click="marginDialogVisible = true" />
               </div>
               <div class="globalSearch">
                    <input v-model="Filters.patient_name" :placeholder="t('search') + '...'"
                         class="search p-inputtext p-component mb-2" />
                    <div class="btns">
                         <Button :label="t('Clear Filters')" icon="pi pi-filter-slash" @click="clearFilters"
                              class="p-button-secondary mb-2" />
                         <Button v-if="havePermission('invoices export')" :label="t('Export to Excel')"
                              icon="pi pi-file-excel" @click="exportToExcel" class="p-button-success mb-2" />
                    </div>
                    
               </div>
               
               <br />
               <br />
               <div></div>
               <DataTable size="small" :value="filteredRecords" scrollable scrollHeight="600px"
                    responsiveLayout="scroll" :paginator="true" :lazy="true" :rows="pagination.per_page"
                    :totalRecords="pagination.total" :first="(pagination.current_page - 1) * pagination.per_page"
                    @page="onPageChange">
                    <template #empty>
                         <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                              {{ t("noData") }}
                         </div>
                    </template>
                    <Column class="text-center" header="#" field="index" />
                    <Column class="text-center" field="patient.name" style="min-width: 250px">
                         <template #header>
                              <p>{{ t("Pationt_name") }}</p>
                              <input v-model="Filters.patient_name" :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <Button style="background: green; min-width: 200px" v-if="slotProps.data.patient?.name"
                                   :label="slotProps.data.patient?.name" class="p-eye-button mx-1"></Button>
                         </template>
                    </Column>
                    <Column class="text-center" field="registration_date" style="min-width: 150px">
                         <template #header>
                              <p>{{ t("Registration_date") }}</p>
                              <Calendar v-model="Filters.registration_date" :placeholder="t('search') + '...'" showIcon
                                   dateFormat="yy-mm-dd" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ dateTimeFormat(slotProps.data.registration_date) }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input v-model="Filters.lab" :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data?.lab }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="created_by.name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Created_By") }}</p>
                              <input v-model="Filters.created_by" :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data.created_by?.name }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="contract.name" v-if="contracts" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("contract") }}</p>
                              <Dropdown v-model="Filters.contract_id_fk" :options="contracts" optionLabel="label"
                                   optionValue="value" :placeholder="t('search') + '...'" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data.contract?.name }}</p>
                         </template>
                    </Column>

                    <Column class="text-center" field="from_lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("from_lab") }}</p>
                              <input v-model="Filters.from_lab" :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data?.from_lab }}</p>
                         </template>
                    </Column>
                    <Column class="text-center" field="barcode" style="min-width: 150px" v-if="!patientId">
                         <template #header>
                              <p>{{ t("Barcode") }}</p>
                              <input v-model="Filters.barcode" :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <button class="Parcode-button mx-1" @click="printParcode(slotProps.data)">
                                   <span>{{ slotProps.data?.barcode }}</span>
                                   <div>
                                        <BarcodeComponent :value="slotProps.data?.barcode" />
                                   </div>
                              </button>
                         </template>
                    </Column>
                    <Column class="text-center" field="signed_by.name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("sign_by") }}</p>
                              <input v-model="Filters.signed_by" :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              <p class="boldText">{{ slotProps.data.signed_by?.name }}</p>
                         </template>
                    </Column>

                    <!-- Tests Status Column -->
                    <Column class="text-center" field="tests" style="min-width: 120px">
                         <template #header>
                              <p>{{ t("the_tests") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button :label="slotProps.data.is_done ? t('done') : t('pendening')"
                                   class="p-eye-button mx-1" :class="slotProps.data.is_done ? 'done' : 'pendening'"
                                   @click="showPatient_due(slotProps.data)"></Button>
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
                         style="min-width: 280px">
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <!-- Update Result Button -->
                              <Button
                                   v-if="havePermission('invoices edit') && !patientId"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded p-button-text"
                                   @click="updateResult(slotProps.data)"
                                   v-tooltip.top="t('updateResult')" />

                              <!-- Print Result Button -->
                              <Button
                                   icon="pi pi-print"
                                   class="p-button-rounded p-button-text"
                                   @click="openprintResultTemplate(slotProps.data)"
                                   v-tooltip.top="t('Result')" />

                              <!-- Print PDF Button (للواتساب) -->
                              <Button
                                   v-if="!patientId && havePermission('invoices send whatsapp')"
                                   icon="pi pi-file-pdf"
                                   class="p-button-rounded p-button-text p-button-help"
                                   @click="printPDFForWhatsApp(slotProps.data)"
                                   v-tooltip.top="t('Print PDF')" />

                              <!-- WhatsApp Button -->
                              <Button
                                   v-if="!patientId && havePermission('invoices send whatsapp')"
                                   icon="pi pi-whatsapp"
                                   class="p-button-rounded p-button-text p-button-success"
                                   @click="sendWhatsAppMessage(slotProps.data)"
                                   v-tooltip.top="t('send_whatsapp')" />

                              <!-- Patient History Button -->
                              <Button
                                   icon="pi pi-calendar"
                                   class="p-button-rounded p-button-text"
                                   @click="PationtHistory(slotProps.data)"
                                   v-tooltip.top="t('pationtHistory')" />

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
          <pationtHistoryModal></pationtHistoryModal>
          <PrintMarginDialog v-model:visible="marginDialogVisible" @preview="generatePDF" />
          <patient-modal></patient-modal>
          <due-modal></due-modal>
          <job_orderModal></job_orderModal>
          <printResult></printResult>
          <whatsUp></whatsUp>
          <parcodModal></parcodModal>
          <workSheetModal></workSheetModal>
          <updateResultModal></updateResultModal>
          <attachment></attachment>
          
          <!-- Hidden file inputs for header and footer images -->
          <input ref="headerImageInput" type="file" accept="image/*" style="display: none" @change="uploadHeaderImage" />
          <input ref="footerImageInput" type="file" accept="image/*" style="display: none" @change="uploadFooterImage" />
     </div>
</template>

<script>
import { mapActions, mapWritableState } from "pinia";
import pationtHistoryModal from "./componentes/pationtHistory_modal.vue";
import patientModal from "./componentes/patient_modal.vue";
import DueModal from "./componentes/due_modal.vue";
import parcodModal from "./componentes/parcodeModal.vue";
import whatsUp from "./componentes/whatsUp_moadal.vue";
import PrintMarginDialog from "./componentes/PrintMarginDialog.vue";
import attachment from "./componentes/attachment.vue";
import { useinvoicesStore } from "@/store/modules/invoices";
import { showAlertWithConfirm } from "@/utils/helper";
import { useContractsStore } from "@/store/modules/contracts";
import { useAuthStore } from '@/store/modules/auth'
import job_orderModal from "./componentes/job_orderModal.vue";
import printResult from "./componentes/print_Result.vue";
import workSheetModal from "./componentes/workSheet_modal.vue";
import updateResultModal from "./componentes/updateResult.vue";
import BarcodeComponent from "../../components/BarcodeComponent.vue";
import { format } from "date-fns";
import * as XLSX from "xlsx";
import html2pdf from "html2pdf.js";
import Menu from 'primevue/menu';
export default {
     data() {
          return {
               marginDialogVisible: false,
               Filters: {
                    registration_date: "",
                    from_lab: "",
                    contract_id_fk: "",
                    created_by: "",
                    barcode: "",
                    signed_by: "",
                    patient_name: "",
                    lab: "",
               },
               globalFilter: "", // Global filter input
               menuRefs: {}, // Store menu references
               currentRecordId: null, // ID السجل الحالي لتحميل الصور
               patientImages: JSON.parse(localStorage.getItem('patientImages') || '{}'), // صور كل مريض
          };
     },
     components: {
          whatsUp,
          PrintMarginDialog,
          pationtHistoryModal,
          patientModal,
          job_orderModal,
          DueModal,
          printResult,
          parcodModal,
          workSheetModal,
          updateResultModal,
          BarcodeComponent,
          attachment,
          Menu,
     },

     mounted() {
          this.pagination.current_page = 1;
          this.patientId = this.$route.params.patientId;
          if (this.patientId) {
               this.patient_medical_records(this.patientId);
               localStorage.removeItem("token");
               localStorage.removeItem("user");
          } else {
               this.Getinvoices();
          }
     },
     computed: {
          ...mapWritableState(useContractsStore, ["contracts"]),
          ...mapWritableState(useAuthStore, ["havePermission"]),
          ...mapWritableState(useinvoicesStore, [
               "invoices",
               "attachments",
               "AttachDialog",
               "Pdfurl",
               "pagination",
               "record",
               "pationtHistoryDialog",
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
               "selectedCultures",
               "selectedreferal",
               "patient",
               "selectedCollector",
               "printRecord",
               "WhatsUpDialog",
               "parcodeDialog",
               "print_work_sheetDialog",
               "selectedItems",
               "isAllSelected",
               "updateResultModalDialog",
               "updateResultRecord",
          ]),

          filteredRecords() {
               return this.invoices.filter((record) => {
                    // Apply global filter
                    const matchesGlobalFilter = this.globalFilter
                         ? record.barcode?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.from_lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.contract?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.created_by?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.signed_by?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.patient?.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                         record.patient?.lab?.toLowerCase().includes(this.globalFilter.toLowerCase())
                         : true;

                    // Apply column-specific filters
                    const matchesColumnFilters =
                         (!this.Filters.registration_date ||
                              this.isSameDate(record.registration_date, this.Filters.registration_date)) &&
                         (!this.Filters.lab || this.isSameDate(record.lab, this.Filters.lab)) &&
                         (!this.Filters.created_by ||
                              record.created_by?.name
                                   ?.toLowerCase()
                                   .includes(this.Filters.created_by.toLowerCase())) &&
                         (!this.Filters.signed_by ||
                              record.signed_by?.name
                                   ?.toLowerCase()
                                   .includes(this.Filters.signed_by.toLowerCase())) &&
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
          ...mapActions(useinvoicesStore, [
               "Getinvoices",
               "patient_medical_records",
               "patientHistory",
               "sign",
               "pdf",
               "getsamples",
               "changeInvoiceStatus",
          ]),
          onPageChange(event) {
               this.pagination.current_page = event.page + 1;
               this.Getinvoices();
          },
          PDF(data) {
               this.printRecord = data;
               setTimeout(function () {
                    const element = document.getElementById("Result").innerHTML;
                    const options = {
                         margin: 1,
                         filename: `invoice-${this.invoiceNumber}.pdf`,
                         image: { type: "jpeg", quality: 0.98 },
                         html2canvas: { scale: 2 },
                         jsPDF: { unit: "in", format: "letter", orientation: "portrait" },
                    };

                    html2pdf().from(element).set(options).save();
               }, 50);
          },
          showAttach(attachments) {
               this.attachments = attachments;
               this.AttachDialog = true;
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

               // Patient Details
               items.push({
                    label: this.t('Patient_details'),
                    icon: 'pi pi-eye',
                    command: () => this.showPatient(data?.patient)
               });

               // Tests Status
               items.push({
                    label: data.is_done ? this.t('done') : this.t('pendening'),
                    icon: data.is_done ? 'pi pi-check' : 'pi pi-clock',
                    command: () => this.showPatient_due(data),
                    class: data.is_done ? 'text-success' : 'text-warning'
               });

               // Job Order
               if (!this.patientId) {
                    items.push({
                         label: this.t('job_order'),
                         icon: 'pi pi-book',
                         command: () => this.openJobTemplateAsPDF(data)
                    });
               }

               // Separator
               if (items.length > 0) {
                    items.push({ separator: true });
               }

               // Signature
               if (!this.patientId) {
                    items.push({
                         label: this.t('Signature'),
                         icon: data.is_signed ? 'pi pi-wave-pulse' : 'pi pi-exclamation-circle',
                         command: () => this.signInvoice(data.id),
                         class: data.is_signed ? 'text-success' : ''
                    });
               }

               // Attachments
               items.push({
                    label: this.t('attachments'),
                    icon: 'pi pi-file',
                    command: () => this.showAttach(data?.attachments)
               });

               // Print Work Sheet
               if (!this.patientId) {
                    items.push({
                         label: this.t('print_work_sheet'),
                         icon: 'pi pi-print',
                         command: () => this.print_work_sheet(data)
                    });
               }

               // Separator قبل خيارات الصور
               items.push({ separator: true });

               // Upload Header Image
               items.push({
                    label: 'صورة الهيدر',
                    icon: 'pi pi-image',
                    command: () => this.openHeaderImageUpload(data.id)
               });

               // Upload Footer Image
               items.push({
                    label: 'صورة الفوتر',
                    icon: 'pi pi-image',
                    command: () => this.openFooterImageUpload(data.id)
               });

               return items;
          },
          showPatient(patient) {
               this.patient = patient;
               this.patientdialog = true;
          },
          signInvoice(id) {
               this.sign(id).then((res) => {
                    this.alertSuccess(this.t("alertSuccess"));
               });
          },
          updateResult(data) {
               // this.clearObjectValues(this.updateResultRecord);
               this.updateResultRecord = data;
               // this.updateResultRecord.cultures_comment = [];
               this.updateResultModalDialog = true;
          },
          showPatient_due(data) {
               this.record = data;
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

          clearFilters() {
               this.Filters = {
                    registration_date: "",
                    from_lab: "",
                    contract_id_fk: "",
                    created_by: "",
                    barcode: "",
                    signed_by: "",
                    lab: "",
               };
               this.globalFilter = ""; // Clear global filter
          },
          exportToExcel() {
               // Create a worksheet from the filtered records
               const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                    header: [
                         "index",
                         "registration_date",
                         "from_lab",
                         "contract_id_fk",
                         "created_by",
                         "barcode",
                         "signed_by",
                         "lab",
                    ],
               });

               // Create a new workbook and append the worksheet
               const wb = XLSX.utils.book_new();
               XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

               // Write the workbook to a file
               XLSX.writeFile(wb, "export.xlsx");
          },
          PationtHistory(record) {
               this.patientHistory(record.patient.id).then((res) => {
                    this.pationtHistoryDialog = true;
               });
          },
          addRecord() {
               this.selectedContract = [];
               this.selectedreferal = [];
               this.selectedTests = [];
               this.selectedPackages = [];
               this.selectedCultures = [];
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
               this.dialog = true;
          },
          openJobTemplateAsPDF(data) {
               this.printRecord = data;

               setTimeout(function () {
                    // Get HTML to print from the element
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

          openprintResultTemplate(data) {
               this.printRecord = [];

               this.printRecord = data;
               Object.assign(this.printRecord, data);
               setTimeout(function () {
                    // Get HTML to print from element
                    const jobContent = document.getElementById("Result").innerHTML;
                    // Get dynamic margins from localStorage
                    const storedMargins = JSON.parse(localStorage.getItem("printMargins") || "{}");

                    const top = storedMargins.top ?? 20;
                    const bottom = storedMargins.bottom ?? 20;
                    const left = storedMargins.left ?? 15;
                    const right = storedMargins.right ?? 15;

                    var css = `@page { size: portrait;   margin: ${top}mm ${right}mm ${bottom}mm ${left}mm !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    };

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
                             }`;

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
                                              <style>${css}  .page-break {
               page-break-before: always;
          }  </style>
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
          parcodeDialog(data) {
               this.record = data;
               this.parcodeDialog = true;
          },
          print_work_sheet(data) {
               this.printRecord = data;
               this.print_work_sheetDialog = true;
               this.selectedItems = [];
               this.isAllSelected = false;
          },
          async sendWhatsUp(record) {

               Object.assign(this.printRecord, record);
               const labName = this.User.name;
               const patientName = this.printRecord?.patient?.name;
               const url = `${window.location.origin}/result/${record.id}`;
               const message = `اهلا بكم في مختبر ${labName}  عزيزي  ${patientName} يمكنك الحصول على النتيجة من خلال الضغط على الرابط ادناه ${url}`;
               const encodedMessage = encodeURIComponent(message);
               const phoneNumber = this.printRecord?.patient?.phone;
               const whatsAppUrl = `https://wa.me/+964${phoneNumber}?text=${message}`;

               await window.open(whatsAppUrl, "_blank");
               this.changeInvoiceStatus(record.id);
          },

          // دالة لطباعة PDF فقط (للواتساب) - تفتح نافذة الطباعة مع الهيدر والفوتر
          printPDFForWhatsApp(record) {
               this.printRecord = [];
               this.printRecord = record;
               Object.assign(this.printRecord, record);
               
               const self = this;
               
               setTimeout(function () {
                    console.log("🔍 Current record ID:", record.id);
                    console.log("📦 All patient images:", self.patientImages);
                    
                    // Get HTML to print from element
                    let jobContent = document.getElementById("Result").innerHTML;
                    
                    // الحصول على صور المريض الحالي
                    const patientImages = self.patientImages[record.id] || {};
                    
                    console.log("🖼️ Patient images for this record:", patientImages);
                    console.log("📸 Header image exists:", !!patientImages.header);
                    console.log("📸 Footer image exists:", !!patientImages.footer);
                    
                    // إضافة الهيدر إذا كان موجوداً
                    if (patientImages.header) {
                         console.log("✅ Adding header image...");
                         const headerHtml = `<div style="text-align: center; margin-bottom: 20px; page-break-inside: avoid;">
                              <img src="${patientImages.header}" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" />
                         </div>`;
                         jobContent = headerHtml + jobContent;
                    } else {
                         console.log("⚠️ No header image found for this patient");
                    }
                    
                    // إضافة الفوتر إذا كان موجوداً
                    if (patientImages.footer) {
                         console.log("✅ Adding footer image...");
                         const footerHtml = `<div style="position: fixed; bottom: 0; left: 0; right: 0; text-align: center; page-break-inside: avoid;">
                              <img src="${patientImages.footer}" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" />
                         </div>`;
                         jobContent = jobContent + footerHtml;
                    } else {
                         console.log("⚠️ No footer image found for this patient");
                    }
                    
                    // Get dynamic margins from localStorage
                    const storedMargins = JSON.parse(localStorage.getItem("printMargins") || "{}");

                    const top = storedMargins.top ?? 20;
                    const bottom = storedMargins.bottom ?? 20;
                    const left = storedMargins.left ?? 15;
                    const right = storedMargins.right ?? 15;

                    var css = `@page { size: portrait;   margin: ${top}mm ${right}mm ${bottom}mm ${left}mm !important;}  @media print {    body,  .page {   margin: 0px !important;   box-shadow: 0;    -webkit-print-color-adjust: exact;       color: #000;    };

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
                             }`;

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
                                              <style>${css}  .page-break {
               page-break-before: always;
          }  </style>
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

          // فتح نافذة تحميل صورة الهيدر
          openHeaderImageUpload(recordId) {
               this.currentRecordId = recordId;
               this.$refs.headerImageInput.click();
          },

          // فتح نافذة تحميل صورة الفوتر
          openFooterImageUpload(recordId) {
               this.currentRecordId = recordId;
               this.$refs.footerImageInput.click();
          },

          // دالة لتحميل صورة الهيدر
          uploadHeaderImage(event) {
               const file = event.target.files[0];
               if (file && this.currentRecordId) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                         // إنشاء كائن للمريض إذا لم يكن موجوداً
                         if (!this.patientImages[this.currentRecordId]) {
                              this.patientImages[this.currentRecordId] = {};
                         }
                         
                         // حفظ صورة الهيدر
                         this.patientImages[this.currentRecordId].header = e.target.result;
                         
                         // حفظ في localStorage
                         localStorage.setItem('patientImages', JSON.stringify(this.patientImages));
                         
                         this.$toast.add({
                              severity: 'success',
                              summary: 'نجح',
                              detail: 'تم تحميل صورة الهيدر بنجاح',
                              life: 3000
                         });
                         
                         // إعادة تعيين input
                         event.target.value = '';
                    };
                    reader.readAsDataURL(file);
               }
          },

          // دالة لتحميل صورة الفوتر
          uploadFooterImage(event) {
               const file = event.target.files[0];
               if (file && this.currentRecordId) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                         // إنشاء كائن للمريض إذا لم يكن موجوداً
                         if (!this.patientImages[this.currentRecordId]) {
                              this.patientImages[this.currentRecordId] = {};
                         }
                         
                         // حفظ صورة الفوتر
                         this.patientImages[this.currentRecordId].footer = e.target.result;
                         
                         // حفظ في localStorage
                         localStorage.setItem('patientImages', JSON.stringify(this.patientImages));
                         
                         this.$toast.add({
                              severity: 'success',
                              summary: 'نجح',
                              detail: 'تم تحميل صورة الفوتر بنجاح',
                              life: 3000
                         });
                         
                         // إعادة تعيين input
                         event.target.value = '';
                    };
                    reader.readAsDataURL(file);
               }
          },

          // دالة لفتح WhatsApp فقط مع رسالة
          async sendWhatsAppMessage(record) {
               try {
                    console.log("📱 Opening WhatsApp...");
                    
                    const labName = this.User?.name || "المختبر";
                    const patientName = record?.patient?.name || "المريض";
                    const message = `اهلا بكم في مختبر ${labName}\nعزيزي ${patientName}\nإليك نتائج الفحوصات الطبية`;
                    const encodedMessage = encodeURIComponent(message);

                    let phoneNumber = record?.patient?.phone;
                    if (phoneNumber && phoneNumber.startsWith('0')) {
                         phoneNumber = phoneNumber.substring(1);
                    }

                    const whatsAppUrl = `https://wa.me/+964${phoneNumber}?text=${encodedMessage}`;
                    window.open(whatsAppUrl, "_blank");

                    this.changeInvoiceStatus(record.id);
                    console.log("✅ WhatsApp opened successfully!");
               } catch (error) {
                    console.error("❌ Error in sendWhatsAppMessage:", error);
                    alert("حدث خطأ أثناء فتح WhatsApp. يرجى المحاولة مرة أخرى.");
               }
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
          downloadPDF(data) {
               this.printRecord = data;
               // Get HTML content from the ref
               const content = document.getElementById("Result").innerHTML;

               // Format HTML content as a Blob
               const blob = new Blob([content], { type: "application/pdf" });

               // Create an object URL for the Blob
               const url = URL.createObjectURL(blob);

               // Create a temporary anchor element to trigger download
               const a = document.createElement("a");
               a.href = url;
               a.download = "document.pdf";

               // Append anchor to body, trigger click, then remove it
               document.body.appendChild(a);
               a.click();
               document.body.removeChild(a);

               // Revoke object URL to free memory
               URL.revokeObjectURL(url);
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

.pendening {
     border: none;
     background: transparent;
     color: goldenrod;
     font-weight: bold;
}

.done {
     border: none;
     background: transparent;
     color: green;
     font-weight: bold;
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

:deep(.text-warning) {
     color: #ffc107 !important;
}
</style>
