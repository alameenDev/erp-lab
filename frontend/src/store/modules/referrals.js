import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useReferralsStore = defineStore("referral", {
     state: () => ({
          totalCount: 0,
          searchTotalCount: 0,
          records: [],
          dialog: false,
          searchRecords: [],
          record: {
               id: "",
               name: "",
               email: "",
               role_id: "",
               phone_number: "",
               address: "",
               price_list_id_fk: null,
               commission: 0,
          },
          filter: {
               name: "",
          },
     }),
     getters: {
          referrals: (state) => () =>
               state.records.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          async GetRecords() {
               const { data } = await $http.get("/referrals");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;

               this.records = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.records.length;
          },
          async search(name) {
               if (name) {
                    const { data } = await $http.get("/referrals/search", { params: { name: name } });

                    this.searchRecords = data;

                    this.searchTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async AddReferral() {
               await $http.post(`/referrals/create`, this.record);
               this.GetRecords();
          },
          async UpdateReferral() {
               await $http.put(`/referrals/update`, this.record);
               this.GetRecords();
          },
          async RemoveReferral() {
               await $http.delete(`/referrals/delete`, {
                    data: { id: this.record.id },
               });
               this.GetRecords();
          },
     },
});
