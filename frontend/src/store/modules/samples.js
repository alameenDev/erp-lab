import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const usesamplesStore = defineStore("samples", {
     state: () => ({
          samplesList: [],
          totalCount: "",
          dialog: false,
          record: {
               id: "",
               name: "",
          },
     }),
     getters: {
          samples: (state) => () =>
               state.samplesList.map((record) => ({ label: record.sample_name, value: record.id })),
     },
     actions: {
          async Getsamples() {
               const { data } = await $http.get("/samples");

               this.samplesList = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.totalCount = this.samplesList.length;
          },
          async Addsample() {
               await $http.post(`/samples/create`, checkObjectParams(this.record));
               this.Getsamples();
          },
          async Updatesample() {
               await $http.put(`/samples/update`, checkObjectParams(this.record));

               this.Getsamples();
          },
          async Removesample() {
               await $http.delete(`/samples/delete`, { data: { id: this.record.id } });
               this.Getsamples();
          },
     },
});
