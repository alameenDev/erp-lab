import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const usePackagesStore = defineStore("packages", {
     state: () => ({
          packagesList: [],
          dialog: false,
          tests: [],
          cultures: [],
          totalCount: "",
          resdialog: false,
          testdialog: false,
          record: {
               id: "",
               name: "", // "required|string|max:2,55",
               shortcut: "", // "required|string|max:2,55",
               price: "", // "nullable if is_constant_price is true|integer",
               is_constant_price: false, // "required|boolean",
               tests: null, // "required|array",
               cultures: null, // "required|array",
               test_groups: null, // whole test groups included in the package
               formula: [],
          },
     }),
     getters: {
          packages: (state) => () => state.packagesList.map((record) => ({ label: record.name, value: record.id })),
     },
     actions: {
          async Getpackages() {
               try {
                    const { data } = await $http.get("/packages");
                    this.packagesList = data.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));

                    this.totalCount = this.packagesList.length;
               } catch (error) {
                    this.packagesList = [];
               }
          },

          async Addpackage() {
               try {
                    this.record.cultures = this.record.cultures ? this.record.cultures : [];
                    this.record.tests = this.record.tests ? this.record.tests : [];
                    this.record.test_groups = this.record.test_groups ? this.record.test_groups : [];

                    await $http.post(`/packages/create`, this.record);
                    this.Getpackages();
               } catch (error) {
                    throw error;
               }
          },
          async Updatepackage() {
               try {
                    this.record.cultures = this.record.cultures ? this.record.cultures : [];
                    this.record.tests = this.record.tests ? this.record.tests : [];
                    this.record.test_groups = this.record.test_groups ? this.record.test_groups : [];
                    await $http.put(`/packages/update`, checkObjectParams(this.record));

                    this.Getpackages();
               } catch (error) {
                    throw error;
               }
          },

          async Removepackage() {
               try {
                    await $http.delete(`/packages/delete`, { data: { id: this.record.id } });
                    this.Getpackages();
               } catch (error) {
                    throw error;
               }
          },
          async ImportPackages(file) {
               try {
                    const locale = localStorage.getItem("locale") || "ar";
                    const formData = new FormData();
                    formData.append("file", file);
                    const { data } = await $http.post(`/packages/import?locale=${locale}`, formData, {
                         headers: { "Content-Type": "multipart/form-data" },
                    });
                    this.Getpackages();
                    return data;
               } catch (error) {
                    throw error;
               }
          },
     },
});
