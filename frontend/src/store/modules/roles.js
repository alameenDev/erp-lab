import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useRolesstore = defineStore("roles", {
     state: () => ({
          roles: [],
          permissions: [],
          controllers : [],
          dialog: false,
          totalCount: "",
          record: {
               id: "",
               role: "", // "required|string|max:2,55",
               permissions: [],
          },
     }),
     getters: {
          role: (state) => () =>
               state.roles.map((record) => ({ label: record.name, value: record.id , permissions: record.permissions })),
     },
     actions: {
          async GetRoles() {
               const { data } = await $http.get("/roles");
               this.roles = data.roles.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.roles.length;
          },
          async GetPermissions() {
               const { data } = await $http.get("/permissions");
               console.log(data, "data");
               this.permissions = data.permissions.map((item, index) => ({
                    ...item,
                    index: index + 1, // Adding 1 to start indexing from 1 instead of 0
               }));
               this.controllers = data.permissions.map((item) => ({
                    ...item,
                    index: item.controller, // Adding 1 to start indexing from 1 instead of 0
               }));

               this.totalCount = this.roles.length;
          },

          async AddRoles(body) {
               await $http.post(`/roles/create`, body);
               this.GetRoles();
          },
          async UpdateRoles(body) {
               await $http.put(`/roles/update`, checkObjectParams(body));

               this.GetRoles();
          },
          async AssignRoleToUser(body) {
               await $http.post(`/roles/assign-role`,body);

               this.GetRoles();
          },

          async RemoveRoles() {
               await $http.delete(`/roles/delete`, { data: { id: this.record.id } });
               this.GetRoles();
          },
     },
});
