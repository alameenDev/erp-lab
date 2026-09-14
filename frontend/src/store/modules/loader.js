import { defineStore } from "pinia";

export const LoaderStore = defineStore("loader", {
     state: () => ({
          requests: [],
          requestofhide: [],
          showLoader: false,
          loaderTimer: null,
     }),
     getters: {
          IsLoading() {
               return this.showLoader && this.requests.length > 0;
          },
          hideLoading() {
               return this.requestofhide.length > 0;
          },
     },
     actions: {
          async PushRequest(url) {
               this.requests.push(url);
               // Only show loader after 500ms delay — fast requests never show it
               if (!this.loaderTimer && this.requests.length > 0) {
                    this.loaderTimer = setTimeout(() => {
                         if (this.requests.length > 0) {
                              this.showLoader = true;
                         }
                    }, 500);
               }
          },
          async PopRequest(url) {
               const index = this.requests.indexOf(url);
               if (index !== -1) {
                    this.requests.splice(index, 1);
               }
               if (this.requests.length === 0) {
                    this.showLoader = false;
                    if (this.loaderTimer) {
                         clearTimeout(this.loaderTimer);
                         this.loaderTimer = null;
                    }
               }
          },
          // after hide
          async PushHideRequest(url) {
               this.requestofhide.push(url);
          },
          async PopHideRequest(url) {
               const index = this.requestofhide.indexOf(url);
               if (index !== -1) {
                    this.requestofhide.splice(index, 1);
               }
          },
     },
});
