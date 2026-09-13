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
          },
     }),
     getters: {
          packages: (state) => () => state.packagesList.map((record) => ({ label: record.name, value: record.id })),
     },
     actions: {
          async Getpackages() {
               const { data } = await $http.get("/packages");
               this.packagesList = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.packagesList.length;
          },

          async Addpackage() {
               this.record.cultures = this.record.cultures ? this.record.cultures : [];
               this.record.tests = this.record.tests ? this.record.tests : [];

               await $http.post(`/packages/create`, this.record);
               this.Getpackages();
          },
          async Updatepackage() {
               this.record.cultures = this.record.cultures ? this.record.cultures : [];
               this.record.tests = this.record.tests ? this.record.tests : [];
               await $http.put(`/packages/update`, checkObjectParams(this.record));

               this.Getpackages();
          },

          async Removepackage() {
               await $http.delete(`/packages/delete`, { data: { id: this.record.id } });
               this.Getpackages();
          },
     },
});
