import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const useResultTypesStore = defineStore("resultTypes", {
     state: () => ({
          resultTypes: [],
     }),
     actions: {
          async GetresultTypes() {
               try {
                    const { data } = await $http.get("/result-types");

                    this.resultTypes = data.map((record) => ({ label: record.result_type_name, value: record.id }));
               } catch (error) {
                    this.resultTypes = [];
               }
          },
     },
});
