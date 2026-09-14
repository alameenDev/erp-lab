import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

/**
 * Invoices Store - Manages invoice operations, printing, and patient data
 */
export const useinvoicesStore = defineStore("invoices", {
  state: () => ({
    // Core data
    invoices: [],
    printRecord: [],
    selectedItems: [],
    samplesTests: [],
    attachments: [],
    searchRecords: [],

    // Patient related
    patient: [],
    Patient_due: [],
    pationtHistoryList: [],

    // Selections
    selectedTests: [],
    selectedPackages: [],
    selectedtestGroups: [],
    selectedCultures: [],
    selectedContract: [],
    selectedCollector: [],
    selectedreferal: [],
    tests_ids: [],

    // Comments and questions
    cultursComment: [],
    testsComment: [],
    testQuesions: [],

    // Discount
    discountPercentage: 0,
    discountValue: 0,

    // Dialogs
    dialog: false,
    patientdialog: false,
    printInvoiceDialog: false,
    AttachDialog: false,
    updateResultModalDialog: false,
    pationtHistoryDialog: false,
    WhatsUpDialog: false,
    Patient_dueDialog: false,
    print_work_sheetDialog: false,

    // Pagination & stats (server-side)
    pagination: {
      current_page: 1,
      per_page: 25,
      total: 0,
      last_page: 1,
      from: null,
      to: null,
    },
    stats: {
      total: 0,
      completed_sent: 0,
      completed_done: 0,
      pending_sent: 0,
      pending_done: 0,
      signed: 0,
      sent: 0,
      total_amount: 0,
      paid_amount: 0,
      due_amount: 0,
    },
    lastParams: {},

    // Other
    isAllSelected: false,
    Pdfurl: "",
    totalCount: "",
    searchNameTotalCount: "",
    searchCodeTotalCount: "",
    searchPhoneTotalCount: "",

    // Payment details template
    payment_details: [{
      amount: null,
      contract_id_fk: null,
      payment_method_id_fk: null,
    }],

    // Invoice record form
    record: {
      patient_id_fk: null,
      referral_id_fk: null,
      registration_date: null,
      result_date: null,
      sub_total: null,
      total: null,
      notes: null,
      from_lab_id_fk: null,
      contract_id_fk: null,
      sample_collector_id_fk: null,
      discount_type_id_fk: null,
      discount: null,
      show_result_date: false,
      show_patient_card_id: false,
      show_patient_pic: false,
      payment_method_id_fk: null,
      tests: [],
      cultures: [],
      packages: [],
      payment_details: [{
        amount: null,
        contract_id_fk: null,
        payment_method_id_fk: null,
      }],
    },

    // Update result form
    updateResultRecord: {
      notes: "",
      tests: [],
      cultures: [],
      id: null,
      attachments: [{ name: "", file: "" }],
      tests_comment: "",
      cultures_comment: "",
      package_comment: "",
    },
  }),

  getters: {
    // Transform invoices to label/value format for dropdowns
    invoiceOptions: (state) => state.invoices.map((record) => ({
      label: record.name,
      value: record.id,
    })),
  },

  actions: {
    // Utility: Get today's date in YYYY-MM-DD format
    getTodayDate() {
      return new Date().toISOString().split("T")[0];
    },

    // Reset record to default values
    resetRecord() {
      this.record = {
        patient_id_fk: null,
        referral_id_fk: null,
        registration_date: null,
        result_date: null,
        sub_total: null,
        total: null,
        notes: null,
        from_lab_id_fk: null,
        contract_id_fk: null,
        sample_collector_id_fk: null,
        discount_type_id_fk: null,
        discount: null,
        show_result_date: false,
        show_patient_card_id: false,
        show_patient_pic: false,
        payment_method_id_fk: null,
        tests: [],
        cultures: [],
        packages: [],
        payment_details: [{
          amount: null,
          contract_id_fk: null,
          payment_method_id_fk: null,
        }],
      };
      this.selectedTests = [];
      this.selectedPackages = [];
      this.selectedtestGroups = [];
      this.selectedCultures = [];
      this.selectedContract = [];
      this.selectedCollector = [];
      this.selectedreferal = [];
    },

    // CRUD Operations
    async Getinvoices(params) {
      try {
        // If no params passed, re-use last params (for post-mutation refresh)
        if (params) {
          this.lastParams = params;
        } else {
          params = this.lastParams;
        }
        const { data } = await $http.get("/invoices", { params });
        const items = Array.isArray(data.data) ? data.data : [];
        const from = data.pagination?.from || 1;
        this.invoices = items.map((item, index) => ({
          ...item,
          index: from + index,
        }));
        this.pagination = data.pagination || this.pagination;
        this.stats = data.stats || this.stats;
        this.totalCount = data.pagination?.total || this.invoices.length;
      } catch (error) {
        console.error("Error fetching invoices:", error);
        throw error;
      }
    },

    async GetinvoicesById(id) {
      try {
        const { data } = await $http.get(`/invoices/${id}`);
        this.printRecord = data;
        return data;
      } catch (error) {
        console.error("Error fetching invoice:", error);
        throw error;
      }
    },

    async Addinvoices() {
      try {
        const { data } = await $http.post(`/invoices/create`, checkObjectParams(this.record));
        this.printRecord = data;
        return data;
      } catch (error) {
        console.error("Error creating invoice:", error);
        throw error;
      }
    },

    async Updateinvoices() {
      try {
        await $http.put(`/invoices/update`, checkObjectParams(this.record));
      } catch (error) {
        console.error("Error updating invoice:", error);
        throw error;
      }
    },

    async Removeinvoices() {
      try {
        await $http.delete(`/invoices/delete`, { data: { id: this.record.id } });
        await this.Getinvoices();
      } catch (error) {
        console.error("Error deleting invoice:", error);
        throw error;
      }
    },

    async addPayment(invoiceId, amount, paymentMethodId) {
      try {
        const { data } = await $http.post("/invoices/add-payment", {
          invoice_id: invoiceId,
          amount,
          payment_method_id_fk: paymentMethodId,
        });
        return data;
      } catch (error) {
        console.error("Error adding payment:", error);
        throw error;
      }
    },

    // Status and signing
    async changeInvoiceStatus(id, withBackground = true) {
      try {
        await $http.post(`invoices/send`, { id, with_background: withBackground });
        await this.Getinvoices();
      } catch (error) {
        console.error("Error changing invoice status:", error);
        throw error;
      }
    },

    async sign(id) {
      try {
        await $http.post(`/invoices/sign`, { id });
        await this.Getinvoices();
      } catch (error) {
        console.error("Error signing invoice:", error);
        throw error;
      }
    },

    // Search operations
    async searchByname(name) {
      if (!name) {
        this.searchRecords = [];
        this.searchNameTotalCount = 0;
        return;
      }
      try {
        const { data } = await $http.post(`/invoices/search-name`, { name });
        this.searchRecords = Array.isArray(data) ? data : [];
        this.searchNameTotalCount = this.searchRecords.length;
      } catch (error) {
        console.error("Error searching by name:", error);
        this.searchRecords = [];
        this.searchNameTotalCount = 0;
      }
    },

    async searchByPhone(phone) {
      if (!phone) {
        this.searchRecords = [];
        this.searchPhoneTotalCount = 0;
        return;
      }
      try {
        const { data } = await $http.post(`/invoices/search-phone`, { phone_number: phone });
        this.searchRecords = data || [];
        this.searchPhoneTotalCount = this.searchRecords.length;
      } catch (error) {
        console.error("Error searching by phone:", error);
        this.searchRecords = [];
        this.searchPhoneTotalCount = 0;
      }
    },

    async searchBycode(code) {
      if (!code) {
        this.searchRecords = [];
        this.searchCodeTotalCount = 0;
        return;
      }
      try {
        const { data } = await $http.post(`/invoices/search-code`, { code });
        this.searchRecords = data || [];
        this.searchCodeTotalCount = this.searchRecords.length;
      } catch (error) {
        console.error("Error searching by code:", error);
        this.searchRecords = [];
        this.searchCodeTotalCount = 0;
      }
    },

    // Patient and samples
    async patientHistory(id) {
      try {
        const { data } = await $http.get(`/invoices/patient-history/${id}`);
        this.pationtHistoryList = data;
      } catch (error) {
        console.error("Error fetching patient history:", error);
        throw error;
      }
    },

    async patient_medical_records(id) {
      try {
        const { data } = await $http.get(`/invoices/patient-medical-records/${id}`);
        this.invoices = data.map((item, index) => ({ ...item, index: index + 1 }));
        this.totalCount = this.invoices.length;
      } catch (error) {
        console.error("Error fetching medical records:", error);
        throw error;
      }
    },

    async getsamples(id) {
      try {
        const { data } = await $http.get(`/invoices/get-samples/${id}`);
        this.samplesTests = data;
      } catch (error) {
        console.error("Error fetching samples:", error);
        throw error;
      }
    },

    // Questions
    async questions() {
      try {
        const { data } = await $http.post(`/tests/questions`, { tests_ids: this.tests_ids });
        this.testQuesions = data;
      } catch (error) {
        console.error("Error fetching questions:", error);
        throw error;
      }
    },

    // PDF generation
    async pdf(invoiceContent, id) {
      try {
        const blob = new Blob([invoiceContent], { type: "application/pdf" });
        const formData = new FormData();
        formData.append("result_doc", blob, "invoice.pdf");
        formData.append("id", id);
        const { data } = await $http.post(`/invoices/pdf`, formData);
        this.Pdfurl = data;
        return data;
      } catch (error) {
        console.error("Error generating PDF:", error);
        throw error;
      }
    },

    // Update result with attachments
    async updateResult() {
      try {
        const formData = new FormData();
        const rec = this.updateResultRecord;

        // Handle attachments: existing (URL strings) + new (File objects)
        const existingAttachments = [];
        let fileIndex = 0;
        if (rec.attachments) {
          rec.attachments.forEach((attachment) => {
            if (attachment.file instanceof File) {
              formData.append(`attachments[${fileIndex}][name]`, attachment.name || "");
              formData.append(`attachments[${fileIndex}][file]`, attachment.file);
              fileIndex++;
            } else if (typeof attachment.file === "string" && attachment.file) {
              existingAttachments.push(attachment);
            }
          });
        }
        if (existingAttachments.length > 0) {
          formData.append("existing_attachments", JSON.stringify(existingAttachments));
        }

        // Send only the fields the backend needs
        formData.append("id", rec.id);
        if (rec.tests?.length) formData.append("tests", JSON.stringify(rec.tests));
        if (rec.cultures?.length) formData.append("cultures", JSON.stringify(rec.cultures));
        if (rec.packages?.length) formData.append("packages", JSON.stringify(rec.packages));
        if (rec.test_groups?.length) formData.append("test_groups", JSON.stringify(rec.test_groups));
        formData.append("tests_comment", rec.tests_comment || "");
        formData.append("cultures_comment", rec.cultures_comment || "");
        formData.append("package_comment", rec.package_comment || "");
        formData.append("notes", rec.notes || "");

        await $http.post(`/invoices/update-result`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        await this.Getinvoices();
      } catch (error) {
        console.error("Error updating result:", error);
        throw error;
      }
    },

    // Silent auto-save: persists result fields (no new file uploads, no list
    // refresh, no global loader). New attachments still require the Save button.
    // URL carries ?silent=1 so axios suppresses the loader (see silentPatterns).
    async autoSaveResult() {
      const rec = this.updateResultRecord;
      if (!rec?.id) return;
      const formData = new FormData();
      formData.append("id", rec.id);
      if (rec.tests?.length) formData.append("tests", JSON.stringify(rec.tests));
      if (rec.cultures?.length) formData.append("cultures", JSON.stringify(rec.cultures));
      if (rec.packages?.length) formData.append("packages", JSON.stringify(rec.packages));
      if (rec.test_groups?.length) formData.append("test_groups", JSON.stringify(rec.test_groups));
      formData.append("tests_comment", rec.tests_comment || "");
      formData.append("cultures_comment", rec.cultures_comment || "");
      formData.append("package_comment", rec.package_comment || "");
      formData.append("notes", rec.notes || "");
      // Preserve already-saved attachments so the backend doesn't wipe them.
      const existing = (rec.attachments || []).filter((a) => typeof a.file === "string" && a.file);
      if (existing.length) formData.append("existing_attachments", JSON.stringify(existing));

      await $http.post(`/invoices/update-result?silent=1`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    },
  },
});
