import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useReportsStore = defineStore("reports", {
     state: () => ({
          reports: [],
          testdialog: false,
          culturedialog: false,
          tests: [],
          cultures: [],
          totalCount: "",
          packdialog: false,
          contracts: [],
          contractsdialog: false,
          referals: [],
          referalsdialog: false,
          packages: [],
          record: {
               start_date: "", // required
               end_date: "", // required
               report_type: "accounting",
               id: null,
               lab_id: null,
          },
     }),
     actions: {
          async Getreports() {
               const { data } = await $http.get("/reports", { params: { ...this.record } });
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;

               this.reports = data;

               this.totalCount = this.reports.length;
          },
     },
});
