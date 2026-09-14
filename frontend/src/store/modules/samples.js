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
               try {
                    const { data } = await $http.get("/samples");

                    this.samplesList = data.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));
                    this.totalCount = this.samplesList.length;
               } catch (error) {
                    this.samplesList = [];
               }
          },
          async Addsample() {
               try {
                    await $http.post(`/samples/create`, checkObjectParams(this.record));
                    this.Getsamples();
               } catch (error) {
                    throw error;
               }
          },
          async Updatesample() {
               try {
                    await $http.put(`/samples/update`, checkObjectParams(this.record));

                    this.Getsamples();
               } catch (error) {
                    throw error;
               }
          },
          async Removesample() {
               try {
                    await $http.delete(`/samples/delete`, { data: { id: this.record.id } });
                    this.Getsamples();
               } catch (error) {
                    throw error;
               }
          },
          async ImportSamples(file) {
               try {
                    const locale = localStorage.getItem("locale") || "ar";
                    const formData = new FormData();
                    formData.append("file", file);
                    const { data } = await $http.post(`/samples/import?locale=${locale}`, formData, {
                         headers: { "Content-Type": "multipart/form-data" },
                    });
                    this.Getsamples();
                    return data;
               } catch (error) {
                    throw error;
               }
          },
     },
});
