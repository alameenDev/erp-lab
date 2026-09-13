import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useNationalitiesStore = defineStore("nationality", {
     state: () => ({
          nationalities: [],
          dialog: false,
          record: {
               id: "",
               name: "",
               email: "",
               password: "",
               role_id: "",
          },
     }),
     actions: {
          async GetNationalities() {
               const { data } = await $http.get("/nationalities");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.nationalities = data.map((record) => ({ label: record.country_name, value: record.id }));
          },

          async AddNationality() {
               await $http.post(`/nationalities/create`, checkObjectParams(this.record));
               this.GetNationalities();
          },
          async UpdateNationality() {
               const { password, ...rest } = this.record;
               let submitData = rest;
               if (password) {
                    submitData = { ...submitData, password };
               }
               const { data } = await $http.post(`/nationalities/${this.record.id}`, checkObjectParams(submitData));

               this.GetNationalities();
          },
          async RemoveNationality() {
               await $http.delete(`/nationalities/delete/${this.record.id}`);
               this.GetNationalities();
          },
     },
});
