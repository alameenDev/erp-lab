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
               try {
                    const { data } = await $http.get("/user/show");
                    // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
                    this.records = data.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));
                    this.sample_collectors = this.records.filter((item) => item.role_id == 6);
                    this.branches = this.records.filter((item) => item.role_id == 4);

                    this.totalCount = this.records.length;
               } catch (error) {
                    this.records = [];
               }
          },
          async GetUserRoles() {
               try {
                    const { data } = await $http.get("/roles");
                    // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
                    this.UserRoles = data.roles.map((record) => ({ label: record.name, value: record.id }));

                    this.UserRolestotalCount = this.UserRoles.length;
               } catch (error) {
                    this.UserRoles = [];
               }
          },
          async forget_password() {
               try {
                    await $http.post(`/user/forget_password`, {
                         email: this.record.email,
                    });
               } catch (error) {
                    throw error;
               }
          },
          async reset_password(password) {
               try {
                    await $http.post(`/user/reset_password`, {
                         email: this.record.email,
                         code: this.otpCode,
                         password: password,
                    });
               } catch (error) {
                    throw error;
               }
          },

          async ResendCode() {
               try {
                    await $http.post(`/user/resend_code`, {
                         email: this.record.email,
                    });
               } catch (error) {
                    throw error;
               }
          },
          async VerifyCode() {
               try {
                    await $http.post(`/user/verify_code`, {
                         email: this.record.email,
                         code: this.otpCode,
                    });
               } catch (error) {
                    throw error;
               }
          },
          async AddUser() {
               try {
                    const formData = new FormData();
                    // Only append image if it's a valid file (not empty string)
                    if (this.selectedFile && this.selectedFile instanceof File) {
                         formData.append("image", this.selectedFile);
                    }
                    // Only append signature if it's a valid file (backend expects 'signiture')
                    if (this.signature && this.signature instanceof File) {
                         formData.append("signiture", this.signature);
                    }
                    for (let key in this.record) {
                         // Only append non-empty values
                         if (this.record[key] !== "" && this.record[key] !== null && this.record[key] !== undefined) {
                              formData.append(key, this.record[key]);
                         }
                    }
                    await $http.post(`/user/create`, formData);
                    this.GetRecords();
               } catch (error) {
                    throw error;
               }
          },
          async UpdateUser() {
               try {
                    const { password, ...rest } = this.record;
                    let submitData = rest;
                    if (password) {
                         submitData = { ...submitData, password };
                    }
                    await $http.post(`/user/update`, checkObjectParams(submitData));

                    this.GetRecords();
               } catch (error) {
                    throw error;
               }
          },
          async RemoveUser() {
               try {
                    await $http.delete(`/user/delete`, { data: { id: this.record.id } });
                    this.GetRecords();
               } catch (error) {
                    throw error;
               }
          },
     },
});
