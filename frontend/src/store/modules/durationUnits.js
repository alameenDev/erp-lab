import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const useDurationUnitsStore = defineStore("durationUnits", {
     state: () => ({
          durationUnits: [],
     }),
     actions: {
          async GetdurationUnits() {
               const { data } = await $http.get("/duration-units");

               this.durationUnitsList = data.map((record) => ({ label: record.unit, value: record.id }));
          },
     },
});
