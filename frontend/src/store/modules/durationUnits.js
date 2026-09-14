import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const useDurationUnitsStore = defineStore("durationUnits", {
     state: () => ({
          durationUnitsList: [],
     }),
     actions: {
          async GetdurationUnits() {
               try {
                    const { data } = await $http.get("/duration-units");

                    this.durationUnitsList = data.map((record) => ({ label: record.unit, value: record.id }));
               } catch (error) {
                    this.durationUnitsList = [];
               }
          },
     },
});
