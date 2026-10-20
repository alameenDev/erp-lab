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
          role: (state) =>
               state.roles.map((record) => ({ label: record.name, value: record.id , permissions: record.permissions })),
     },
     actions: {
          async GetRoles() {
               try {
                    const { data } = await $http.get("/roles");
                    this.roles = data.roles.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));

                    this.totalCount = this.roles.length;
               } catch (error) {
                    this.roles = [];
               }
          },
          async GetPermissions() {
               try {
                    const { data } = await $http.get("/permissions?catalog=1");
                    this.permissions = data.permissions.map((item, index) => ({
                         ...item,
                         index: index + 1, // Adding 1 to start indexing from 1 instead of 0
                    }));
                    this.controllers = data.permissions.map((item) => ({
                         ...item,
                         index: item.controller, // Adding 1 to start indexing from 1 instead of 0
                    }));

                    this.totalCount = this.permissions.length;
               } catch (error) {
                    this.permissions = [];
                    this.controllers = [];
                    throw error;
               }
          },

          async AddRoles(body) {
               try {
                    await $http.post(`/roles/create`, body);
                    await this.GetRoles();
               } catch (error) {
                    throw error;
               }
          },
          async UpdateRoles(body) {
               try {
                    await $http.put(`/roles/update`, body);
                    await this.GetRoles();
               } catch (error) {
                    throw error;
               }
          },
          async AssignRoleToUser(body) {
               try {
                    await $http.post(`/roles/assign-role`, body);
               } catch (error) {
                    throw error;
               }
          },

          async RemoveRoles() {
               try {
                    await $http.delete(`/roles/delete`, { data: { role_id: this.record.id } });
                    await this.GetRoles();
               } catch (error) {
                    throw error;
               }
          },
     },
});
