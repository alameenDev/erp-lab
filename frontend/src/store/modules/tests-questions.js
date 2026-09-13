import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const usetestsQuestionsStore = defineStore("tests-questions", {
     state: () => ({
          testsQuestions: [],
          dialog: false,
          totalCount: "",
          record: {
               id: "",
               answer_type_selection_values: [""], //"required|array",
               question: "", //"required|string",
               answer_type_id_fk: "", //"required|integer"
          },
     }),
     getters: {
          Questions: (state) => () =>
               state.testsQuestions.map((record) => ({ label: record.question, value: record.id })),
     },
     actions: {
          async GetTestsQuestions() {
               const { data } = await $http.get("/tests-questions");

               this.testsQuestions = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.totalCount = this.testsQuestions.length;
          },
          async AddTestsQuestions() {
               await $http.post(`/tests-questions/create`, checkObjectParams(this.record));
               this.GetTestsQuestions();
          },
          async UpdateTestsQuestions() {
               await $http.put(`/tests-questions/update`, checkObjectParams(this.record));

               this.GetTestsQuestions();
          },
          async RemoveTestsQuestions() {
               await $http.delete(`/tests-questions/delete`, { data: { id: this.record.id } });
               this.GetTestsQuestions();
          },
     },
});
