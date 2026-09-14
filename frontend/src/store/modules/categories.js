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
               try {
                    const { data } = await $http.get("/categories");

                    this.Testcategories = data.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));
                    this.totalCount = this.Testcategories.length;
               } catch (error) {
                    this.Testcategories = [];
               }
          },

          async Addcategory() {
               try {
                    await $http.post(`/categories/create`, checkObjectParams(this.record));
                    this.Getcategories();
               } catch (error) {
                    throw error;
               }
          },
          async Updatecategory() {
               try {
                    await $http.put(`/categories/update`, checkObjectParams(this.record));

                    this.Getcategories();
               } catch (error) {
                    throw error;
               }
          },
          async Removecategory() {
               try {
                    await $http.delete(`/categories/delete`, { data: { id: this.record.id } });
                    this.Getcategories();
               } catch (error) {
                    throw error;
               }
          },
          async ImportCategories(file) {
               try {
                    const locale = localStorage.getItem("locale") || "ar";
                    const formData = new FormData();
                    formData.append("file", file);
                    const { data } = await $http.post(`/categories/import?locale=${locale}`, formData, {
                         headers: { "Content-Type": "multipart/form-data" },
                    });
                    this.Getcategories();
                    return data;
               } catch (error) {
                    throw error;
               }
          },
     },
});
