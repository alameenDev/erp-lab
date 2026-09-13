import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useContractsStore = defineStore("Contract", {
     state: () => ({
          totalCount: 0,
          searchTotalCount: 0,
          records: [],
          dialog: false,
          searchRecords: [],
          record: {
               id: "",
               name: "",
               payment_percent: 0, //نسبة الدفع
               maximum_payment_per_invoice: 0, //الحد الأقصى للدفع لكل فاتورة
               credit_limit: 0, //الحد الإئتمانى
               price_limit: 0, //الحد الافصى للسعر
               discount_percentage: 0,
               address: "",
               phone_number: "",
               email: "",
               password: "",
          },
          filter: {
               name: "",
          },
     }),
     getters: {
          Contracts: (state) => () =>
               state.records.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          async GetRecords() {
               const { data } = await $http.get("/contracts");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;

               this.records = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.records.length;
          },
          async search(name) {
               if (name) {
                    const { data } = await $http.get("/Contracts/search", { params: { name: name } });

                    this.searchRecords = data;

                    this.searchTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async AddContract() {
               await $http.post(`/contracts/create`, this.record);
               this.GetRecords();
          },
          async UpdateContract() {
               await $http.put(`/contracts/update`, this.record);
               this.GetRecords();
          },
          async RemoveContract() {
               await $http.delete(`/contracts/delete`, {
                    data: { id: this.record.id },
               });
               this.GetRecords();
          },
     },
});
