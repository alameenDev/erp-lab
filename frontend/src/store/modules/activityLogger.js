import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const ActivityStore = defineStore("activity", {
     state: () => ({
          records: [],
          totalCount: "",
     }),
     actions: {
          /**
           * Asynchronously fetches activity records from the server and processes them.
           *
           * This function sends a GET request to the `/activity/show` endpoint to retrieve
           * activity records. The retrieved data is then mapped to include an `index` property
           * which starts from 1. The processed records are stored in the `records` property
           * and the total count of records is stored in the `totalCount` property.
           *
           */
          async GetRecords() {
               const { data } = await $http.get(`/activity/show`);
               this.records = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.totalCount = this.records.length;
          },
     },
});
