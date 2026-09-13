import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const useinvoicesStore = defineStore("invoices", {
     state: () => ({
          invoices: [],
          pationtHistoryList: [],
          printInvoiceDialog: false,
          printRecord: [],
          selectedItems: [],
          isAllSelected: false,
          attachments: [],
          AttachDialog: false,
          Pdfurl: "",
          updateResultModalDialog: false,
          pationtHistoryDialog: false,
          patient: [],
          Patient_due: [],
          WhatsUpDialog: false,
          tests_ids: [],
          selectedContract: [],
          selectedCollector: [],
          selectedreferal: [],
          Patient_dueDialog: false,
          print_work_sheetDialog: false,
          selectedTests: [],
          selectedPackages: [],
          selectedtestGroups: [],
          searchPhoneTotalCount: "",
          selectedCultures: [],
          dialog: false,
          patientdialog: false,
          totalCount: "",
          searchNameTotalCount: "",
          searchCodeTotalCount: "",
          searchRecords: [],
          cultursComment: [],
          testsComment: [],
          discountPercentage: 0,
          patient: [],
          samplesTests: [],
          discountValue: 0,
          pagination: {
               pageNumber: 1,
               total: null,
               per_page: 25,
               current_page: 1,
               last_page: null,
               from: null,
               to: null,
          },
          payment_details: [
               {
                    amount: null, // $request->paid,
                    contract_id_fk: null, //
                    payment_method_id_fk: null, // $request->payment_method_id_fk
               },
          ],
          record: {
               patient_id_fk: null, // "required|integer",
               referral_id_fk: null, // "nullable|integer",
               registration_date: null, // "nullable|date",
               result_date: null, // "nullable|date",
               sub_total: null, // "nullable|integer",
               total: null, // "nullable|integer",
               notes: null, // "nullable|string",
               from_lab_id_fk: null, // "nullable|integer",
               contract_id_fk: null, // "nullable|integer",
               sample_collector_id_fk: null, // "nullable|integer",
               discount_type_id_fk: null, // "nullable|integer",
               discount: null, // "nullable|integer",
               show_result_date: false, // "nullable|boolean",
               show_patient_card_id: false, // "nullable|boolean",
               show_patient_pic: false, // "nullable|boolean",
               payment_method_id_fk: null, // "required|integer",

               tests: [
                    {
                         test_id_fk: null, // required
                         to_lab_id_fk: null, //nullable,
                         price: null,
                         is_sample_received: false, //bool if null will defualt to false
                    },
               ],
               cultures: [
                    {
                         culture_id_fk: null, // required
                         to_lab_id_fk: null, //nullable,
                         price: null,
                         is_sample_received: true, //bool if null will defualt to false
                    },
               ],
               packages: [
                    {
                         package_id_fk: null, // required
                         to_lab_id_fk: null, //nullable,
                         price: null,
                         is_sample_received: true, //bool if null will defualt to false
                    },
               ],
               payment_details: [
                    {
                         amount: null, // $request->paid,
                         contract_id_fk: null, //
                         payment_method_id_fk: null, // $request->payment_method_id_fk
                    },
               ],
          },
          updateResultRecord: {
               comments: "",
               tests: [],
               cultures: [],
               id: 1, // invoice required
               attachments: [{ name: "", file: "" }],
               tests_comment: [],
               cultures_comment: [],
          },
     }),
     getters: {
          invoice: (state) => () =>
               state.invoices.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          getTodayDate() {
               const today = new Date();
               const year = today.getFullYear();
               const month = String(today.getMonth() + 1).padStart(2, "0"); // Add leading zero for months
               const day = String(today.getDate()).padStart(2, "0"); // Add leading zero for days
               return `${year}-${month}-${day}`; // Format: YYYY-MM-DD
          },
          async patientHistory(id) {
               const { data } = await $http.get("/invoices/patient-history/" + id);

               this.pationtHistoryList = data;
          },
          async getsamples(id) {
               const { data } = await $http.get("/invoices/get-samples/" + id);

               this.samplesTests = data;
          },
          async sign(id) {
               const { data } = await $http.post(`/invoices/sign`, { id: id });
               this.Getinvoices();
          },
          async searchByname(name) {
               if (name) {
                    const { data } = await $http.post(`/invoices/search-name`, { name: name });

                    this.searchRecords = data;

                    this.searchNameTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async searchByPhone(phone) {
               if (phone) {
                    const { data } = await $http.post(`/invoices/search-phone`, { phone_number: phone });

                    this.searchRecords = data;

                    this.searchPhoneTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async pdf(invoiceContent, id) {
               const blob = new Blob([invoiceContent], { type: "application/pdf" });

               const formData = new FormData();
               formData.append("result_doc", blob, "invoice.pdf");
               formData.append("id", id);
               const { data } = await $http.post(`/invoices/pdf`, formData);
               this.Pdfurl = data;
          },
          async searchBycode(code) {
               if (code) {
                    const { data } = await $http.post(`/invoices/search-code`, { code: code });

                    this.searchRecords = data;

                    this.searchCodeTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async questions() {
               const { data } = await $http.post(`/tests/questions`, { tests_ids: this.tests_ids });
               this.testQuesions = data;
          },
          async Getinvoices(filters) {
               const { data } = await $http.get("/invoices", {
                    params: {
                         page: this.pagination.current_page,
                         ...filters
                    },
               });

               if (data) {
                    this.pagination.total = data?.pagination?.total;
                    this.pagination.per_page = data?.pagination?.per_page;
                    // this.pagination.current_page = data.pagination.current_page;
                    this.pagination.last_page = data?.pagination?.last_page;
                    this.pagination.from = data?.pagination?.from;
                    this.pagination.to = data?.pagination?.to;
                    this.invoices = data?.data?.map((item, index) => ({
                         ...item,
                         index: this.pagination.from + index,
                    }));
               }
          },
          async GetinvoicesById(id) {
               const { data } = await $http.get("/invoices/?id=" + id);

               this.printRecord = data;
          },
          async Addinvoices() {
               const { data } = await $http.post(`/invoices/create`, checkObjectParams(this.record));
               this.printRecord = data;
               this.Getinvoices();
          },
          async Updateinvoices() {
               await $http.put(`/invoices/update`, checkObjectParams(this.record));
               this.Getinvoices();
          },

          async changeInvoiceStatus(id) {
               await $http.post(`invoices/send`, { id: id });
               this.Getinvoices();
          },
          async patient_medical_records(id) {
               const { data } = await $http.get("/invoices/patient-medical-records/" + id);
               this.invoices = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.totalCount = this.invoices.length;
          },
          async updateResult() {
               const formData = new FormData();

               // Step 1: Handle attachments with file data
               this.updateResultRecord.attachments.forEach((attachment, index) => {
                    if (attachment.file instanceof File) {
                         formData.append(`attachments[${index}][name]`, attachment.name);
                         formData.append(`attachments[${index}][file]`, attachment.file);
                    }
               });

               // Step 2: Loop through remaining properties in updateResultRecord
               for (let key in this.updateResultRecord) {
                    const value = this.updateResultRecord[key];

                    if (key === "attachments") {
                         // Skip attachments as they've already been processed
                         continue;
                    } else if (typeof value === "object" || (Array.isArray(value) && value !== null)) {
                         formData.append(key, JSON.stringify(value));
                    } else {
                         formData.append(key, value);
                    }
               }

               // Send the FormData to your API
               await $http.post(`/invoices/update-result`, formData, {
                    headers: { "Content-Type": "multipart/form-data" },
               });

               this.Getinvoices();
          },
          async Removeinvoices() {
               await $http.delete(`/invoices/delete`, { data: { id: this.record.id } });
               this.Getinvoices();
          },
     },
});
