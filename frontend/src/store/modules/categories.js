import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useCategoriesStore = defineStore("categories", {
     state: () => ({
          Testcategories: [],
          totalCount: "",
          dialog: false,
          record: {
               id: "",
               name: "",
          },
     }),
     getters: {
          categories: (state) => () => state.Testcategories.map((record) => ({ label: record.name, value: record.id })),
     },
     actions: {
          async Getcategories() {
               const { data } = await $http.get("/categories");

               this.Testcategories = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.totalCount = this.Testcategories.length;
          },

          async Addcategory() {
               await $http.post(`/categories/create`, checkObjectParams(this.record));
               this.Getcategories();
          },
          async Updatecategory() {
               await $http.put(`/categories/update`, checkObjectParams(this.record));

               this.Getcategories();
          },
          async Removecategory() {
               await $http.delete(`/categories/delete`, { data: { id: this.record.id } });
               this.Getcategories();
          },
     },
});
