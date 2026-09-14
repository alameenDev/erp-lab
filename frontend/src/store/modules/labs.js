import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const uselabsStore = defineStore("labs", {
     state: () => ({
          labs: [],
          dialog: false,
          collectorsList: [],
          totalCount: "",
          record: {},
     }),
     getters: {
          lab: (state) => () =>
               state.labs.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
          collector: (state) => () =>
               state.collectorsList.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          async Getlabs() {
               const { data } = await $http.get("/labs");

               this.labs = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.totalCount = this.labs.length;
          },
          async collectors() {
               try {
                    const { data } = await $http.get("/collectors");
                    this.collectorsList = (data || []).map((item, index) => ({
                         ...item,
                         index: index + 1,
                    }));
               } catch (error) {
                    this.collectorsList = [];
               }
          },
          async Addlabs() {
               await $http.post(`/labs/create`, checkObjectParams(this.record));
               this.Getlabs();
          },
          async Updatelabs() {
               await $http.put(`/labs/update`, checkObjectParams(this.record));

               this.Getlabs();
          },
          async Removelabs() {
               await $http.delete(`/labs/delete`, { data: { id: this.record.id } });
               this.Getlabs();
          },
     },
});
