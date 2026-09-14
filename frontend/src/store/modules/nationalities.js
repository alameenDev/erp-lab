import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useNationalitiesStore = defineStore("nationality", {
     state: () => ({
          nationalities: [],
          dialog: false,
          record: {
               id: "",
               country_name: "",
          },
     }),
     actions: {
          async GetNationalities() {
               try {
                    const { data } = await $http.get("/nationalities");
                    this.nationalities = (data || []).map((record) => ({ label: record.country_name, value: record.id }));
               } catch (error) {
                    this.nationalities = [];
               }
          },

          async AddNationality() {
               await $http.post(`/nationalities/create`, checkObjectParams(this.record));
               this.GetNationalities();
          },
          async UpdateNationality() {
               await $http.put(`/nationalities/${this.record.id}`, checkObjectParams(this.record));
               this.GetNationalities();
          },
          async RemoveNationality() {
               await $http.delete(`/nationalities/delete/${this.record.id}`);
               this.GetNationalities();
          },
     },
});
