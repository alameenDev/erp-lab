import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useTitlesStore = defineStore("Title", {
     state: () => ({
          titles: [],
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
          async GetTitles() {
               const { data } = await $http.get("/titles");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.titles = data.map((record) => ({ label: record.title, value: record.id }));
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
