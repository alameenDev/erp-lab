import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

/**
 * Reports Store - Manages dashboard stats, reports, and analytics
 */
export const useReportsStore = defineStore("reports", {
  state: () => ({
    // Report data
    reports: null,
    totalCount: "",

    // Dashboard summary
    summary: {
      total_invoices: 0,
      total_patients: 0,
      total_revenue: 0,
      total_paid: 0,
      total_remaining: 0,
    },

    // Charts data
    dailyRevenue: [],
    mostRequestedTests: [],
    doctorReferrals: [],
    labToLab: [],

    // Accounting report data
    accountingData: {
      tests: [],
      cultures: [],
      packages: [],
      testGroups: [],
      contracts: [],
      referals: [],
      patients: [],
      paymentMethods: [],
      profit: 0,
      discount: 0,
      subtotal: 0,
      referalCommission: 0,
    },

    // Tests and cultures stats
    testsStats: { total: 0, completed: 0, pending: 0 },
    culturesStats: { total: 0, completed: 0, pending: 0 },

    // Recent invoices for dashboard
    recentInvoices: [],

    // Today's stats
    todayStats: { invoices: 0, patients: 0, revenue: 0, tests: 0 },

    // Comparison data (vs previous period)
    comparison: {
      invoices: { current: 0, previous: 0, change: 0 },
      revenue: { current: 0, previous: 0, change: 0 },
      patients: { current: 0, previous: 0, change: 0 },
    },

    // Dialogs
    testdialog: false,
    culturedialog: false,
    packdialog: false,
    contractsdialog: false,
    referalsdialog: false,

    // Lists for dialogs
    tests: [],
    cultures: [],
    packages: [],
    contracts: [],
    referals: [],

    // Filter record
    record: {
      start_date: "",
      end_date: "",
      report_type: "accounting",
      id: null,
      lab_id: null,
    },
  }),

  actions: {
    // Get reports based on record filters
    async Getreports() {
      try {
        const { data } = await $http.get("/reports", { params: { ...this.record } });
        this.reports = data;
        // Calculate totalCount from total_invoices or sum of arrays
        this.totalCount = data?.total_invoices ||
          (data?.tests?.length || 0) +
          (data?.cultures?.length || 0) +
          (data?.packages?.length || 0) +
          (data?.patients?.length || 0) +
          (data?.invoices?.length || 0);

        // Map quick_summary response to dashboard state
        if (this.record.report_type === "quick_summary" && data) {
          this.summary = {
            total_invoices: data.summary?.total_invoices || 0,
            total_patients: data.summary?.total_patients || 0,
            total_revenue: data.summary?.total_revenue || 0,
            total_paid: data.summary?.total_paid || 0,
            total_remaining: data.summary?.total_remaining || 0,
          };
          this.dailyRevenue = data.daily_revenue || [];
          this.mostRequestedTests = data.most_requested_tests || [];
          this.doctorReferrals = data.doctor_referrals || [];
          this.labToLab = data.lab_to_lab || [];
        }

        // Map accounting report data
        if (this.record.report_type === "accounting" && data) {
          this.accountingData = {
            tests: data.tests || [],
            cultures: data.cultures || [],
            packages: data.packages || [],
            testGroups: data.test_groups || [],
            contracts: data.contracts || [],
            referals: data.referals || [],
            patients: data.patients || [],
            paymentMethods: data.payment_methods || [],
            profit: data.profit || 0,
            discount: data.discount || 0,
            subtotal: data.subtotal || 0,
            referalCommission: data.referal_commission || 0,
          };
          this.summary = {
            total_invoices: data.total_invoices || 0,
            total_patients: data.patients?.length || 0,
            total_revenue: data.total_amount || 0,
            total_paid: data.paid_amount || 0,
            total_remaining: data.due || 0,
          };
        }
      } catch (error) {
        console.error("Error fetching reports:", error);
        throw error;
      }
    },

    // Get dashboard stats from dedicated server endpoint
    async GetDashboardStats() {
      try {
        const { data } = await $http.get("/reports/dashboard-stats");
        this.testsStats = data.tests_stats || { total: 0, completed: 0, pending: 0 };
        this.culturesStats = data.cultures_stats || { total: 0, completed: 0, pending: 0 };
        this.todayStats = data.today_stats || { invoices: 0, patients: 0, revenue: 0, tests: 0 };
        this.recentInvoices = data.recent_invoices || [];
      } catch (error) {
        console.error("Error fetching dashboard stats:", error);
        throw error;
      }
    },

    // Get comparison data between current and previous month
    async GetComparisonData() {
      try {
        const today = new Date();
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const startOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const endOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);

        const baseParams = { report_type: "quick_summary", lab_id: this.record.lab_id };

        const [currentRes, previousRes] = await Promise.all([
          $http.get("/reports", {
            params: { ...baseParams, start_date: startOfMonth.toISOString().split("T")[0], end_date: today.toISOString().split("T")[0] },
          }),
          $http.get("/reports", {
            params: { ...baseParams, start_date: startOfLastMonth.toISOString().split("T")[0], end_date: endOfLastMonth.toISOString().split("T")[0] },
          }),
        ]);

        const current = currentRes.data?.summary || {};
        const previous = previousRes.data?.summary || {};

        const calcChange = (curr, prev) => {
          if (!prev || prev === 0) return curr > 0 ? 100 : 0;
          return Math.round(((curr - prev) / prev) * 100);
        };

        this.comparison = {
          invoices: { current: current.total_invoices || 0, previous: previous.total_invoices || 0, change: calcChange(current.total_invoices, previous.total_invoices) },
          revenue: { current: current.total_revenue || 0, previous: previous.total_revenue || 0, change: calcChange(current.total_revenue, previous.total_revenue) },
          patients: { current: current.total_patients || 0, previous: previous.total_patients || 0, change: calcChange(current.total_patients, previous.total_patients) },
        };
      } catch (error) {
        console.error("Error fetching comparison data:", error);
        throw error;
      }
    },

    // Reset record to defaults
    resetRecord() {
      this.record = {
        start_date: "",
        end_date: "",
        report_type: "accounting",
        id: null,
        lab_id: null,
      };
    },
  },
});
