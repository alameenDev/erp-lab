import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const usetestsStore = defineStore("tests", {
     state: () => ({
          tests: [],
          dialog: false,
          totalCount: "",
          loading: false,
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
               })),
          testGroupTestes: (state) => () =>
               state.tests.map((record) => ({
                    label: record.name,
                    value: record.id,
               })),
     },
     actions: {
          async GetTests(filters) {
               this.loading= true
               const { data } = await $http.get("/tests", {
                    params: {
                         page: this.pagination.current_page,
                        ...filters
                    },
               });
               if(data) {
                    this.pagination.total = data?.pagination?.total;
                    this.pagination.per_page = data?.pagination?.per_page;
                    this.pagination.current_page = data?.pagination?.current_page;
                    this.pagination.last_page = data?.pagination?.last_page;
                    this.pagination.from = data?.pagination?.from;
                    this.pagination.to = data?.pagination?.to;
                    this.tests = data?.data?.map((item, index) => ({
                         ...item,
                         index: this.pagination.from + index,
                    }));
               }              this.loading= false
          },
          async Addtests() {
               await $http.post(`/tests/create`, checkObjectParams(this.record));
               this.GetTests();
          },
          async Updatetests() {
               await $http.put(`/tests/update`, checkObjectParams(this.record));

               this.GetTests();
          },
          async Removetests() {
               await $http.delete(`/tests/delete`, { data: { id: this.record.id } });
               this.GetTests();
          },
     },
});
