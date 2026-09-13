import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const usepaymentMethodstore = defineStore("paymentMethods", {
     state: () => ({
          paymentMethods: [],
          dialog: false,
          totalCount: "",
          record: {
               id: "",
               name: "", // "required|string|max:2,55",
          },
     }),
     getters: {
          payment_Methods: (state) => () =>
               state.paymentMethods.map((record) => ({ label: record.name, value: record.id })),
     },
     actions: {
          async GetpaymentMethods() {
               const { data } = await $http.get("/payment-methods");
               this.paymentMethods = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.paymentMethods.length;
          },

          async AddpaymentMethod() {
               await $http.post(`/payment-methods/create`, this.record);
               this.GetpaymentMethods();
          },
          async UpdatepaymentMethod() {
               await $http.put(`/payment-methods/update`, checkObjectParams(this.record));

               this.GetpaymentMethods();
          },

          async RemovepaymentMethod() {
               await $http.delete(`/payment-methods/delete`, { data: { id: this.record.id } });
               this.GetpaymentMethods();
          },
     },
});
