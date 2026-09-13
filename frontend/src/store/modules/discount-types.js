import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const usediscountTypesStore = defineStore("discount-types", {
     state: () => ({
          discountTypes: [],
     }),
     actions: {
          async GetdiscountTypes() {
               const { data } = await $http.get("/discount-types");

               this.discountTypes = data.map((record) => ({ label: record.answer, value: record.id }));
          },
     },
});
