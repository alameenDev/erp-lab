import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";
export const useculturesStore = defineStore("cultures", {
     state: () => ({
          cultures: [],
          dialog: false,
          attrdialog: false,
          resdialog: false,
          totalCount: "",
          result_comments: [],
          attributesList: [],
          attributes: [
               {
                    attribute_name: null, // "required|string|max:255",
                    order: null, // "required|integer",
                    result_type_id_fk: null, // "required|integer|max:255",
                    selection_type_options: [""], // "required|array|max:255",
               },
          ],
          record: {
               id: "",
               attribute_id_fk: "",
               category_id_fk: "",
               name: "", // "required|string|max:255",
               precautions: "", // "string|max:255",
               result_comments: [""], // "array|max:255",
               price: "", // "required|integer",
               test_group_id_fk: "", // "nullable|integer|exists:test_groups,id",
               sample_id_fk: "", // "nullable|integer|exists:samples,id",
               test_duration: "", // "required|integer",
               duration_unit_id_fk: "",
               price_for_customer: "",
          },
          selection_type_options: [],
     }),
     getters: {
          cultureLists: (state) => () =>
               state.cultures.map((record) => ({
                    label: record.name,
                    value: { id: record.id, price: record.price },
               })),
          cultureGroupTestes: (state) => () =>
               state.cultures.map((record) => ({
                    label: record.name,
                    value: record.id,
                    price: record.for_customer_price || record.price || 0,
               })),
     },
     actions: {
          async Getcultures() {
               try {
                    const { data } = await $http.get("/cultures");

                    this.cultures = data.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));
                    this.totalCount = this.cultures.length;
               } catch (error) {
                    this.cultures = [];
               }
          },
          async Addcultures() {
               try {
                    this.record.result_comments = this.record.result_comments
                         ? this.record.result_comments
                         : this.result_comments;

                    this.record.attributes = this.attributes;
                    await $http.post(`/cultures/create`, checkObjectParams(this.record));
                    this.Getcultures();
               } catch (error) {
                    throw error;
               }
          },
          async Updatecultures() {
               try {
                    this.record.result_comments = this.record.result_comments
                         ? this.record.result_comments
                         : this.result_comments;

                    this.record.attributes = this.attributes;
                    await $http.put(`/cultures/update`, checkObjectParams(this.record));

                    this.Getcultures();
               } catch (error) {
                    throw error;
               }
          },
          async Removecultures() {
               try {
                    await $http.delete(`/cultures/delete`, { data: { id: this.record.id } });
                    this.Getcultures();
               } catch (error) {
                    throw error;
               }
          },
          async ImportCultures(file) {
               try {
                    const locale = localStorage.getItem("locale") || "ar";
                    const formData = new FormData();
                    formData.append("file", file);
                    const { data } = await $http.post(`/cultures/import?locale=${locale}`, formData, {
                         headers: { "Content-Type": "multipart/form-data" },
                    });
                    this.Getcultures();
                    return data;
               } catch (error) {
                    throw error;
               }
          },
     },
});
