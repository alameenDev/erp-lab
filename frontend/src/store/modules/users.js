import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useUsersStore = defineStore("user", {
     state: () => ({
          totalCount: 0,
          UserRolestotalCount: 0,
          records: [],
          dialog: false,
          assignDialog: false,
          sample_collectors: [],
          signature: "",
          UserRoles: [],
          codeDialog: false,
          selectedFile: "",
          otpCode: "",
          branches: [],

          record: {
               id: "",
               name: "",
               email: "",
               password: "",
               role_id: "",
               phone: "",
               address: "",
               discount_percentage: 0,
               sample_collector_id: "",
               price_list_id: "",
          },
     }),
     actions: {
          async GetRecords() {
               const { data } = await $http.get("/user/show");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.records = data.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.sample_collectors = this.records.filter((item) => item.role_id == "Sample Collector");
               this.branches = this.records.filter((item) => item.role == "Branch Lab");

               this.totalCount = this.records.length;
          },
          async GetUserRoles() {
               const { data } = await $http.get("/roles");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.UserRoles = data.roles.map((record) => ({ label: record.name, value: record.id }));

               this.UserRolestotalCount = this.UserRoles.length;
          },
          async forget_password() {
               await $http.post(`/user/forget_password`, {
                    email: this.record.email,
               });
          },
          async reset_password(password) {
               await $http.post(`/user/reset_password`, {
                    email: this.record.email,
                    code: this.otpCode,
                    password: password,
               });
          },

          async ResendCode() {
               await $http.post(`/user/resend_code`, {
                    email: this.record.email,
               });
          },
          async VerifyCode() {
               await $http.post(`/user/verify_code`, {
                    email: this.record.email,
                    code: this.otpCode,
               });
          },
          async AddUser() {
               const formData = new FormData();
               formData.append("image", this.selectedFile);
               formData.append("signature", this.signature);
               for (let key in this.record) {
                    formData.append(key, this.record[key]);
               }
               await $http.post(`/user/create`, formData);
               this.GetRecords();
          },
          async UpdateUser() {
               const { password, ...rest } = this.record;
               let submitData = rest;
               if (password) {
                    submitData = { ...submitData, password };
               }
               await $http.post(`/user/update`, checkObjectParams(submitData));

               this.GetRecords();
          },
          async RemoveUser() {
               await $http.delete(`/user/delete`, { data: { id: this.record.id } });
               this.GetRecords();
          },
     },
});
