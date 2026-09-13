import { defineStore } from "pinia";

export const LoaderStore = defineStore("loader", {
     state: () => ({
          requests: [],
          requestofhide: [],
     }),
     getters: {
          IsLoading() {
               return this.requests.length > 0;
          },
          hideLoading() {
               return this.requestofhide.length > 0;
          },
     },
     actions: {
          async PushRequest(url) {
               this.requests.push(url);
          },
          async PopRequest(url) {
               this.requests.splice(this.requests.indexOf(url), 1);
          },
          // after hide
          async PushHideRequest(url) {
               this.requestofhide.push(url);
          },
          async PopHideRequest(url) {
               this.requestofhide.splice(this.requestofhide.indexOf(url), 1);
          },
     },
});
