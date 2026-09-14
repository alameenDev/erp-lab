import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useTitlesStore = defineStore("Title", {
     state: () => ({
          titles: [],
          dialog: false,
          record: {
               id: "",
               title: "",
          },
     }),
     actions: {
          async GetTitles() {
               try {
                    const { data } = await $http.get("/titles");
                    this.titles = (data || []).map((record) => ({ label: record.title, value: record.id }));
               } catch (error) {
                    this.titles = [];
               }
          },

          async AddTitle() {
               await $http.post(`/titles/create`, checkObjectParams(this.record));
               this.GetTitles();
          },

          async RemoveTitle() {
               await $http.delete(`/titles/delete/${this.record.id}`);
               this.GetTitles();
          },
     },
});
