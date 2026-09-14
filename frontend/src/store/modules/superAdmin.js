import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const useSuperAdminStore = defineStore("superAdmin", {
     state: () => ({
          kpis: {
               total_labs: 0,
               total_users: 0,
               total_invoices: 0,
               total_revenue: 0,
               total_paid: 0,
               total_patients: 0,
               active_subscriptions: 0,
               changes: {},
          },
          labComparison: [],
          revenueTrend: [],
          labs: [],
          labsPagination: { current_page: 1, total: 0, per_page: 25, last_page: 1 },
          labDetail: null,
          activities: [],
          activitiesPagination: { current_page: 1, total: 0, per_page: 50, last_page: 1 },
          subscriptions: [],
          subscriptionsPagination: { current_page: 1, total: 0, per_page: 25, last_page: 1 },
          subscriptionDialog: false,
          subscriptionRecord: {
               id: null,
               lab_id_fk: null,
               plan_id_fk: null,
               plan_name: "",
               start_date: "",
               end_date: "",
               status: "trial",
               max_users: null,
               max_invoices_per_month: null,
               price: 0,
               currency: "IQD",
               notes: "",
          },
          plans: [],
          patients: [],
          patientsPagination: { current_page: 1, total: 0, per_page: 25, last_page: 1 },
          patientDetail: null,
          patientMedicalReports: [],
          reportDetail: null,
     }),

     actions: {
          async GetKpis() {
               try {
                    const { data } = await $http.get("/super-admin/kpis");
                    this.kpis = data;
               } catch (e) {
                    console.error("GetKpis error:", e);
               }
          },

          async GetLabComparison(params = {}) {
               try {
                    const { data } = await $http.get("/super-admin/lab-comparison", { params });
                    this.labComparison = data;
               } catch (e) {
                    console.error("GetLabComparison error:", e);
               }
          },

          async GetRevenueTrend(params = {}) {
               try {
                    const { data } = await $http.get("/super-admin/revenue-trend", { params });
                    this.revenueTrend = data;
               } catch (e) {
                    console.error("GetRevenueTrend error:", e);
               }
          },

          async GetLabs(params = {}) {
               try {
                    const { data } = await $http.get("/super-admin/labs", { params });
                    this.labs = data.data || [];
                    this.labsPagination = data.pagination;
               } catch (e) {
                    console.error("GetLabs error:", e);
               }
          },

          async GetLabDetail(labId) {
               try {
                    const { data } = await $http.get(`/super-admin/lab-detail/${labId}`);
                    this.labDetail = data;
               } catch (e) {
                    console.error("GetLabDetail error:", e);
               }
          },

          async GetActivities(params = {}) {
               try {
                    const { data } = await $http.get("/super-admin/activity-log", { params });
                    this.activities = data.data || [];
                    this.activitiesPagination = data.pagination;
               } catch (e) {
                    console.error("GetActivities error:", e);
               }
          },

          async GetPatients(params = {}) {
               try {
                    const { data } = await $http.get("/super-admin/patients", { params });
                    this.patients = data.data || [];
                    this.patientsPagination = data.pagination;
               } catch (e) {
                    console.error("GetPatients error:", e);
               }
          },

          async GetPatientDetail(patientId) {
               try {
                    const { data } = await $http.get(`/super-admin/patient-detail/${patientId}`);
                    this.patientDetail = data;
               } catch (e) {
                    console.error("GetPatientDetail error:", e);
               }
          },

          async GetPatientMedicalReports(patientId) {
               try {
                    this.patientMedicalReports = [];
                    const { data } = await $http.get(`/invoices/patient-medical-records/${patientId}`);
                    this.patientMedicalReports = Array.isArray(data) ? data : [];
               } catch (e) {
                    console.error("GetPatientMedicalReports error:", e);
               }
          },

          async GetReportDetail(invoiceId) {
               try {
                    this.reportDetail = null;
                    const { data } = await $http.get(`/invoices/${invoiceId}`);
                    this.reportDetail = data;
               } catch (e) {
                    console.error("GetReportDetail error:", e);
               }
          },

          async GetPlans() {
               try {
                    const { data } = await $http.get("/plans");
                    this.plans = data || [];
               } catch (e) {
                    console.error("GetPlans error:", e);
               }
          },

          async GetSubscriptions(params = {}) {
               try {
                    const { data } = await $http.get("/subscriptions", { params });
                    this.subscriptions = data.data || [];
                    this.subscriptionsPagination = data.pagination;
               } catch (e) {
                    console.error("GetSubscriptions error:", e);
               }
          },

          async AddSubscription(record) {
               const { data } = await $http.post("/subscriptions/create", record);
               return data;
          },

          async UpdateSubscription(record) {
               const { data } = await $http.put("/subscriptions/update", record);
               return data;
          },

          async RemoveSubscription(id) {
               const { data } = await $http.delete("/subscriptions/delete", { data: { id } });
               return data;
          },

          resetSubscriptionRecord() {
               this.subscriptionRecord = {
                    id: null,
                    lab_id_fk: null,
                    plan_id_fk: null,
                    plan_name: "",
                    start_date: "",
                    end_date: "",
                    status: "trial",
                    max_users: null,
                    max_invoices_per_month: null,
                    price: 0,
                    currency: "IQD",
                    notes: "",
               };
          },
     },
});
