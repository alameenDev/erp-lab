import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const useAntibioticsStore = defineStore("Antibiotics", {
     state: () => ({
          Antibiotics: [],
          dialog: false,

          totalCount: "",

          record: {
               id: "",
               common_name: "",
               short_name: "",
               scientific_name: "",
          },
     }),
     getters: {
          AntibioticsLists: (state) => () =>
               state.Antibiotics.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          async GetAntibiotics() {
               try {
                    const { data } = await $http.get("/antibiotics");

                    this.Antibiotics = data.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));
                    this.totalCount = this.Antibiotics.length;
               } catch (error) {
                    this.Antibiotics = [];
               }
          },
          async AddAntibiotics() {
               try {
                    await $http.post(`/antibiotics/create`, checkObjectParams(this.record));
                    this.GetAntibiotics();
               } catch (error) {
                    throw error;
               }
          },
          async UpdateAntibiotics() {
               try {
                    await $http.put(`/antibiotics/update`, checkObjectParams(this.record));
                    this.GetAntibiotics();
               } catch (error) {
                    throw error;
               }
          },
          async RemoveAntibiotics() {
               try {
                    await $http.delete(`/antibiotics/delete`, { data: { id: this.record.id } });
                    this.GetAntibiotics();
               } catch (error) {
                    throw error;
               }
          },
          async ImportAntibiotics(file) {
               try {
                    const locale = localStorage.getItem("locale") || "ar";
                    const formData = new FormData();
                    formData.append("file", file);
                    const { data } = await $http.post(`/antibiotics/import?locale=${locale}`, formData, {
                         headers: { "Content-Type": "multipart/form-data" },
                    });
                    this.GetAntibiotics();
                    return data;
               } catch (error) {
                    throw error;
               }
          },
     },
});
