import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const usetestsStore = defineStore("tests", {
     state: () => ({
          tests: [],
          dialog: false,
          totalCount: "",
          loading: false,
          record: {
               id: "",
               name: "",
               category_id_fk: "", // "required|string",
               interface_code: "", // "required|string",
               selection_type_options: [""], // "json|string",
               shortcut: "", // "required|string",
               report_name: "", // "required|string",
               order: "", // "required|integer",
               result_type_id_fk: 2, // "required|integer",
               sample_id_fk: 1, //required|integer",
               test_duration: 5, //required|integer",
               duration_unit_id_fk: 1, //required|integer",
               unit: "", // "required|string",
               is_contain_status: 0, // "required|integer",
               is_print_alone: 0, // "required|integer",
               //price list
               for_customer_price: "",
               price: "", // "required|integer",
               //questions
               question_ids_fk: [], // requred|array of questions_ids
               result_comments: [""],
               test_reference_ranges: [
                    {
                         gender_id_fk: "", //"required|integer",
                         age_from: "", //"required|integer",
                         age_to: "", //"required|integer",
                         age_unit_id_fk: "", //"required|integer",
                         from: 0, //"required|numeric",
                         to: 0, //"required|numeric",
                         test_reference_options: [""],
                         notes: "",
                    },
               ],
               is_special_test: false,
               content: {}, // "required|string",
               sup_tests: [
                    {
                         name: "",
                         type: 1,
                         value: "",
                         sup_test_reference_options: [""],
                    }
               ]
          },
     }),
     getters: {
          TestLists: (state) => () =>
               state.tests.map((record) => ({
                    label: record.name,
                    value: { id: record.id, price: record.price },
                    shortcut: record.shortcut || "",
                    is_special: !!(record.sub_tests?.length && record.content?.html),
               })),
          testGroupTestes: (state) => () =>
               state.tests.map((record) => ({
                    label: record.name,
                    value: record.id,
                    price: record.for_customer_price || record.price || 0,
                    shortcut: record.shortcut || "",
                    is_special: !!(record.sub_tests?.length && record.content?.html),
               })),
     },
     actions: {
          async GetTests() {
               this.loading = true;
               try {
                    const { data } = await $http.get("/tests");
                    const items = Array.isArray(data) ? data : [];
                    this.tests = items.map((item, index) => ({
                         ...item,
                         index: index + 1,
                    }));
                    this.totalCount = this.tests.length;
               } catch (error) {
                    this.tests = [];
               } finally {
                    this.loading = false;
               }
          },
          async Addtests() {
               const payload = JSON.parse(JSON.stringify(this.record));
               const params = checkObjectParams(payload);
               await $http.post(`/tests/create`, params);
               this.GetTests();
          },
          async Updatetests() {
               const payload = JSON.parse(JSON.stringify(this.record));
               const params = checkObjectParams(payload);
               await $http.put(`/tests/update`, params);
               this.GetTests();
          },
          async Removetests() {
               await $http.delete(`/tests/delete`, { data: { id: this.record.id } });
               this.GetTests();
          },
          async ImportTests(file) {
               const locale = localStorage.getItem("locale") || "ar";
               const formData = new FormData();
               formData.append("file", file);
               const { data } = await $http.post(`/tests/import?locale=${locale}`, formData, {
                    headers: { "Content-Type": "multipart/form-data" },
               });
               this.GetTests();
               return data;
          },
     },
});
