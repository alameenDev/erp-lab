import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const useTemplatesStore = defineStore("template", {
     state: () => ({
          templates: [],
          dialog: false,
          loading: false,
          totalCount: "",
          record: {
               id: "",
               name: "",
               content: {}, // "required|string",
               test_or_culture_id: "", // "required|string",
               type: "test" , // "required|boolean",
          },
     }),
     getters: {
          TemplatesLists: (state) => () =>
               state.templates.map((record) => ({
                    label: record.name,
                    value: { id: record.id },
               })),
          testGroupTemplates: (state) => () =>
               state.templates.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          async GetTemplates() {
               this.loading = true;
               try {
                    const { data } = await $http.get("/templates");
                    const items = Array.isArray(data) ? data : [];
                    this.templates = items.map((item, index) => ({
                         ...item,
                         index: index + 1,
                    }));
                    this.totalCount = this.templates.length;
               } catch (error) {
                    this.templates = [];
               } finally {
                    this.loading = false;
               }
          },

          
          async AddTemplates() {
               await $http.post(`/templates/create`, checkObjectParams(this.record));
               this.GetTemplates();
          },
          async UpdateTemplates() {
               await $http.put(`/templates/update`, checkObjectParams(this.record));

               this.GetTemplates();
          },
          async RemoveTemplates() {
               await $http.delete(`/templates/delete`, { data: { id: this.record.id } });
               this.GetTemplates();
          },
     },
});
