import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useContractsStore = defineStore("contracts", {
     state: () => ({
          contracts: [],
          contractsList: [],
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
          async GetContracts() {
               const { data } = await $http.get("/contracts");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.contracts = data.map((record) => ({ label: record.name, value: record.id }));
               this.contractsList = data;
          },

          async AddContract() {
               await $http.post(`/contracts/create`, checkObjectParams(this.record));
               this.Getcontracts();
          },
          async UpdateContract() {
               const { password, ...rest } = this.record;
               let submitData = rest;
               if (password) {
                    submitData = { ...submitData, password };
               }
               const { data } = await $http.post(`/contracts/${this.record.id}`, checkObjectParams(submitData));

               this.Getcontracts();
          },
          async RemoveContract() {
               await $http.delete(`/contracts/delete/${this.record.id}`);
               this.Getcontracts();
          },
     },
});
