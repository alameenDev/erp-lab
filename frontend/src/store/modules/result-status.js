import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useresultStatusStore = defineStore("result-status", {
     state: () => ({
          resultStatus: [],
          dialog: false,
     }),
     actions: {
          async GetresultStatus() {
               const { data } = await $http.get("/result-status");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.resultStatus = data.map((record) => ({ label: record.status, value: record.id }));
          },
     },
});
