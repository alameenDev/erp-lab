import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const useTemplatesStore = defineStore("template", {
     state: () => ({
          templates: [],
          dialog: false,
          loading: false,
          totalCount: "",
          pagination: {
               total: null,
               pageNumber: 1,
               per_page: 25,
               current_page: 1,
               last_page: null,
               from: null,
               to: null,
          },
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
                    value: { id: record.id, price: record.price },
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
               const { data } = await $http.get("/templates", {
                    params: {
                         page: this.pagination.current_page,
                    },
               })
               console.log(data);
               this.pagination.total = data.pagination.total;
               this.pagination.per_page = data.pagination.per_page;
               this.pagination.current_page = data.pagination.current_page;
               this.pagination.last_page = data.pagination.last_page;
               this.pagination.from = data.pagination.from;
               this.pagination.to = data.pagination.to;
               this.templates = data.data.map((item, index) => ({
                    ...item,
                    index: this.pagination.from + index,
               }));
               this.loading = false;
          //     console.log(this.templates);
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
