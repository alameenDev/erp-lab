import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

const ALL_SECTIONS = [
     "invoices",
     "tests",
     "cultures",
     "packages",
     "test_groups",
     "referrals",
     "contracts",
     "patients",
];

const today = () => new Date().toISOString().slice(0, 10);
const ALL_DATA_FROM = "2020-01-01";

export const useAccountingReportsStore = defineStore("accountingReports", {
     state: () => ({
          // Date filters — default to "All data"
          from: ALL_DATA_FROM,
          to: today(),

          // Dimension filters (null = no filter)
          branch_id: null,
          sample_collector_id: null,
          referral_id: null,
          contract_id: null,
          patient_id: null,

          // Section toggles for the PDF output
          enabledSections: [...ALL_SECTIONS],

          // Fetched data
          report: null,
          isLoading: false,
          isGeneratingPdf: false,
          error: null,
     }),

     getters: {
          allSections: () => ALL_SECTIONS,
          hasData(state) {
               return state.report?.meta?.invoice_count > 0;
          },
     },

     actions: {
          async GetReport() {
               this.isLoading = true;
               this.error = null;
               try {
                    const params = {
                         from: this.from,
                         to: this.to,
                         sections: this.enabledSections.join(","),
                    };
                    if (this.branch_id) params.branch_id = this.branch_id;
                    if (this.sample_collector_id) params.sample_collector_id = this.sample_collector_id;
                    if (this.referral_id) params.referral_id = this.referral_id;
                    if (this.contract_id) params.contract_id = this.contract_id;
                    if (this.patient_id) params.patient_id = this.patient_id;
                    const { data } = await $http.get("/accounting-reports", { params });
                    this.report = data;
                    return data;
               } catch (err) {
                    this.error = err?.response?.data?.message || err.message || "Failed to load report";
                    this.report = null;
                    throw err;
               } finally {
                    this.isLoading = false;
               }
          },

          clearDimensionFilters() {
               this.branch_id = null;
               this.sample_collector_id = null;
               this.referral_id = null;
               this.contract_id = null;
               this.patient_id = null;
          },

          setDateRange(from, to) {
               this.from = from;
               this.to = to;
          },

          setPreset(preset) {
               const t = new Date();
               const fmt = (d) => d.toISOString().slice(0, 10);
               if (preset === "all") {
                    this.from = "2020-01-01";
                    this.to = fmt(t);
               } else if (preset === "today") {
                    this.from = this.to = fmt(t);
               } else if (preset === "yesterday") {
                    const y = new Date(t);
                    y.setDate(y.getDate() - 1);
                    this.from = this.to = fmt(y);
               } else if (preset === "this_week") {
                    const start = new Date(t);
                    start.setDate(t.getDate() - t.getDay());
                    this.from = fmt(start);
                    this.to = fmt(t);
               } else if (preset === "this_month") {
                    this.from = fmt(new Date(t.getFullYear(), t.getMonth(), 1));
                    this.to = fmt(t);
               } else if (preset === "last_30_days") {
                    const start = new Date(t);
                    start.setDate(t.getDate() - 30);
                    this.from = fmt(start);
                    this.to = fmt(t);
               } else if (preset === "last_month") {
                    const first = new Date(t.getFullYear(), t.getMonth() - 1, 1);
                    const last = new Date(t.getFullYear(), t.getMonth(), 0);
                    this.from = fmt(first);
                    this.to = fmt(last);
               }
          },

          toggleSection(section) {
               const idx = this.enabledSections.indexOf(section);
               if (idx >= 0) this.enabledSections.splice(idx, 1);
               else this.enabledSections.push(section);
          },

          resetSections() {
               this.enabledSections = [...ALL_SECTIONS];
          },
     },
});
