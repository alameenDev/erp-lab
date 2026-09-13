import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const usetestGroupsStore = defineStore("testGroups", {
     state: () => ({
          testGroups: [],
          tests: [],
          cultures: [],
          culturedialog: false,
          dialog: false,
          resdialog: false,
          testdialog: false,
          result_comments: [""],
          test_ids: [""],
          totalCount: "",
          record: {
               id: "",
               category_id_fk: "",
               for_customer_price: "",
               group_name: "",
               shortcut: "",
               sample_id_fk: "",
               original_price: "",
               test_duration: "",
               duration_unit_id_fk: "",
               precautions: "",
               is_print_alone: "0",
               result_comments: [""],
               test_group_comment: "",
               test_ids: [""],
               culture_ids: [""],
          },
     }),
     getters: {
          Groups: (state) => () => state.testGroups.map((record) => ({ label: record.group_name, value: record.id })),
     },
     actions: {
          async GettestGroups() {
               const { data } = await $http.get("/test_groups");

               this.testGroups = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.testGroups.length;
          },

          async AddtestGroups() {
               this.record.test_ids = this.record.test_ids ? this.record.test_ids : this.test_ids;
               await $http.post(`/test_groups/create`, this.record);
               this.GettestGroups();
          },
          async UpdatetestGroups() {
               await $http.put(`/test_groups/update`, this.record);

               this.GettestGroups();
          },
          async RemovetestGroups() {
               await $http.delete(`/test_groups/delete`, { data: { id: this.record.id } });
               this.GettestGroups();
          },
     },
});
