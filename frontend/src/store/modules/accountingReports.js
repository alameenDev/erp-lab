import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
const date = (d) =>
     `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
export const defaultAccountingFilters = () => {
     const now = new Date();
     return {
          from: date(new Date(now.getFullYear(), now.getMonth(), 1)),
          to: date(now),
          search: "",
          status: "",
          referral_type: "",
          branch_id: "",
          owner_id: "",
          created_by: "",
          lab_referral_id: "",
          doctor_id: "",
          contract_id: "",
          patient_id: "",
          sample_collector_id: "",
     };
};
export const useAccountingReportsStore = defineStore("accountingReports", {
     state: () => ({
          filters: defaultAccountingFilters(),
          report: null,
          options: {},
          invoices: null,
          payments: null,
          loading: false,
          tableLoading: false,
          error: "",
          serial: 0,
          tableSerial: 0,
     }),
     actions: {
          async loadOptions() {
               const { data } = await $http.get("/accounting-reports/options");
               this.options = data;
          },
          async apply(filters) {
               const serial = ++this.serial;
               ++this.tableSerial;
               this.loading = true;
               this.tableLoading = false;
               this.error = "";
               this.report = null;
               this.invoices = null;
               this.payments = null;
               this.filters = { ...filters };
               try {
                    const params = { ...filters };
                    const [overview, invoices, payments] = await Promise.all([
                         $http.get("/accounting-reports", { params, timeout: 120000 }),
                         $http.get("/accounting-reports/invoices", { params }),
                         $http.get("/accounting-reports/payments", { params }),
                    ]);
                    if (serial !== this.serial) return;
                    this.report = overview.data;
                    this.invoices = invoices.data;
                    this.payments = payments.data;
               } catch (error) {
                    if (serial === this.serial)
                         this.error = error?.response?.data?.message || "تعذر تحميل التقارير. حاول مرة أخرى.";
               } finally {
                    if (serial === this.serial) this.loading = false;
               }
          },
          async page(section, page) {
               const serial = ++this.tableSerial,
                    reportSerial = this.serial;
               this.tableLoading = true;
               this.error = "";
               try {
                    const { data } = await $http.get(`/accounting-reports/${section}`, {
                         params: { ...this.filters, page },
                    });
                    if (serial === this.tableSerial && reportSerial === this.serial) this[section] = data;
               } catch (error) {
                    if (serial === this.tableSerial && reportSerial === this.serial)
                         this.error = error?.response?.data?.message || "تعذر تحميل هذه الصفحة.";
               } finally {
                    if (serial === this.tableSerial) this.tableLoading = false;
               }
          },
          async detail(id) {
               const { data } = await $http.get(`/accounting-reports/invoices/${id}`);
               return data;
          },
          async export(section, format) {
               return (
                    await $http.get("/accounting-reports/export", {
                         params: { ...this.filters, section, format },
                         responseType: "blob",
                         timeout: 180000,
                    })
               ).data;
          },
          cancel() {
               ++this.serial;
               ++this.tableSerial;
          },
     },
});
