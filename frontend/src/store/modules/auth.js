  import { defineStore } from "pinia";
  import { $http } from "@/plugins/axios";

  export const useAuthStore = defineStore("auth", {
    state: () => ({
      rolePermissions: JSON.parse(localStorage.getItem("rolePermissions") || "[]"),
    }),

    actions: {
      async login({ email, password }) {
        const { data } = await $http.post(`/user/login`, {
          email,
          password,
        });

        localStorage.setItem("token", data.token);
        localStorage.setItem("user", JSON.stringify(data.user));
      //  localStorage.setItem("rolePermissions", JSON.stringify(data.user.permissions || []));

      //  // تحديث حالة store
      //  this.rolePermissions = data.user.permissions || [];

        if (data.user.role_id == 4) {
          window.location.href = "/patients";
        } else if (data.user.role_id == 6) {
          window.location.href = "/invoices";
        } else {
          window.location.href = "/dashboard";
        }
      },

      setPermissions(rawPermissions) {
        const all = rawPermissions.flatMap(group =>
          group.permissions.map(p => p.full_name)
        );
    
        this.rolePermissions = all;
        localStorage.setItem("rolePermissions", JSON.stringify(all));
      },

      havePermission(permission) {
        if (!permission) return false;
      
        return this.rolePermissions.includes(permission);
      }
    },
  });
