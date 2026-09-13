import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const usetestsReferenceStore = defineStore("tests-reference", {
     state: () => ({
          testsReference: [],
          dialog: false,
          record: {
               id: "",
               test_id_fk: "", //required|integer,
               gender_id_fk: "", //required|integer,
               age: "", //required|integer,
               age_unit_id_fk: "", //required|integer,
               from: "", //required|numeric,
               to: "", //required|numeric,
               notes: "", //nullable|string,
          },
     }),
     getters: {
          References: (state) => () => state.testsReference.map((record) => ({ label: record.name, value: record.id })),
     },
     actions: {
          async GetTestsReference() {
               const { data } = await $http.get("/tests-reference-ranges");

               this.testsReference = data;
          },
          async AddTestsReference() {
               await $http.post(`/tests-reference-ranges/create`, checkObjectParams(this.record));
               this.GetTestsReference();
          },
          async UpdateTestsReference() {
               await $http.post(`/tests-reference-ranges/update`, checkObjectParams(this.record));

               this.GetTestsReference();
          },
          async RemoveTestsReference() {
               await $http.delete(`/tests-reference-ranges/delete`, { data: { id: this.record.id } });
               this.GetTestsReference();
          },
     },
});
