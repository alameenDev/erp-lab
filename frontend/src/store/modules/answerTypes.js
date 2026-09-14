import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const useAnswerTypesStore = defineStore("answerTypes", {
     state: () => ({
          answerTypes: [],
     }),
     actions: {
          /**
           * Fetches answer types from the server and updates the `answerTypes` property.
           *
           * This method sends an HTTP GET request to the "/answer-types" endpoint to retrieve
           * a list of answer types. The response data is then mapped to an array of objects
           * with `label` and `value` properties, which is assigned to the `answerTypes` property.
           *
           */
          async GetanswerTypes() {
               try {
                    const { data } = await $http.get("/answer-types");

                    this.answerTypes = data.map((record) => ({ label: record.answer, value: record.id }));
               } catch (error) {
                    this.answerTypes = [];
               }
          },
     },
});
