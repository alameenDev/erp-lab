import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const priceListStore = defineStore("priceList", {
     state: () => ({
          totalCount: 0,
          dropdowns: [],
          dialog: false,
          isDiscount: false,
          priceList: [],
          tests: [], // nullable, send when modifying certin items and discount is null
          cultures: [],
          packages: [],
          updatedialog: false,
          record: {
               name: "", //required
               discount: "",
               tests: [], // nullable, send when modifying certin items and discount is null
               cultures: [],
               packages: [],
          },
          UpdateList: [],
     }),
     getters: {
          price_list: (state) => () =>
               state.priceList.map((record) => ({ label: record.price_list_title, value: record.id })),
     },
     actions: {
          async GetpriceList() {
               const { data } = await $http.get("/price_list");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.priceList = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.priceList.length;
          },
          async GetpriceListById(id) {
               const { data } = await $http.get("/price_list/show/" + id);
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.UpdateList = data;
          },
          async AddpriceList() {
               this.record.cultures = this.record.cultures ? this.record.cultures : [];
               this.record.tests = this.record.tests ? this.record.tests : [];
               this.record.packages = this.record.packages ? this.record.packages : [];
               await $http.post(`/price_list/create`, this.record);
               this.GetpriceList();
          },
          async UpdatepriceList() {
               this.record.cultures = this.record.cultures ? this.record.cultures : [];
               this.record.tests = this.record.tests ? this.record.tests : [];
               this.record.packages = this.record.packages ? this.record.packages : [];
               await $http.put(`/price_list/update`, this.record);

               this.GetpriceList();
          },
          async RemovepriceList() {
               await $http.delete(`/price_list/delete`, { data: { id: this.record.id } });
               this.GetpriceList();
          },
     },
});
