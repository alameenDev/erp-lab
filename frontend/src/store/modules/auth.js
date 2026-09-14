  import { defineStore } from "pinia";
  import { $http } from "@/plugins/axios";
  import router from "@/router";
  import { useLabSettingsStore } from "@/store/modules/labSettings";
  import { applyBranding } from "@/utils/branding";

  export const useAuthStore = defineStore("auth", {
    state: () => ({
      rolePermissions: JSON.parse(localStorage.getItem("rolePermissions") || "[]"),
    }),

    actions: {
      async login({ email, password }) {
        try {
          const { data } = await $http.post(`/user/login`, {
            email,
            password,
          });

          if (!data.token) {
            throw new Error("No token received");
          }

          // Save to localStorage
          localStorage.setItem("token", data.token);
          localStorage.setItem("user", JSON.stringify(data.user));

          // Handle permissions from login response
          // Collect all permissions: role-based + direct user permissions
          let allPermissions = [];

          if (data.user?.roles?.length) {
            allPermissions = data.user.roles.flatMap((role) =>
              (role.permissions || []).map((p) => p.name)
            );
          }

          if (data.user?.permissions?.length) {
            allPermissions.push(...data.user.permissions.map((p) => p.name));
          }

          if (allPermissions.length > 0) {
            this.rolePermissions = [...new Set(allPermissions)];
            localStorage.setItem("rolePermissions", JSON.stringify(this.rolePermissions));
          } else if (data.permissions?.length) {
            this.setPermissions(data.permissions);
          } else {
            // Permissions not in login response - fetch them separately
            try {
              const permResponse = await $http.get(`/permissions?role_id=${data.user.role_id}`);
              if (permResponse.data?.permissions) {
                this.setPermissions(permResponse.data.permissions);
              }
            } catch (permError) {
              console.error("Error fetching permissions:", permError);
            }
          }

          // Fetch lab branding + print settings on first login.
          // App.vue's mounted hook only runs once when the SPA boots; if the
          // user lands on /login first, isUserLoggedIn() is false there so
          // the settings fetch is skipped. Without this, the user has to
          // refresh the page after login to see their print margins, logo,
          // and primary/secondary colors take effect.
          try {
            const labSettingsStore = useLabSettingsStore();
            await labSettingsStore.GetSettings();
            applyBranding(labSettingsStore.settings);
          } catch (brandingErr) {
            console.error("Error loading lab branding on login:", brandingErr);
          }

          // Navigate based on role using Vue Router
          let targetRoute = "/dashboard";
          if (data.user.role_id == 1) {
            targetRoute = "/super-admin";
          } else if (data.user.role_id == 4) {
            targetRoute = "/patients";
          } else if (data.user.role_id == 6) {
            targetRoute = "/invoices";
          }

          // Use router.push for SPA navigation
          await router.push(targetRoute);

        } catch (error) {
          console.error("Login error:", error);
          throw error;
        }
      },

      setPermissions(rawPermissions) {
        // Handle different permission formats
        let all = [];

        if (Array.isArray(rawPermissions) && rawPermissions.length > 0) {
          // Check if it's grouped permissions format
          if (rawPermissions[0]?.permissions) {
            all = rawPermissions.flatMap(group =>
              group.permissions.map(p => p.full_name || p.name)
            );
          } else if (typeof rawPermissions[0] === 'string') {
            // Already flat array of permission strings
            all = rawPermissions;
          } else if (rawPermissions[0]?.name) {
            // Array of permission objects
            all = rawPermissions.map(p => p.name || p.full_name);
          }
        }

        this.rolePermissions = all;
        localStorage.setItem("rolePermissions", JSON.stringify(all));
      },

      // Reload permissions from localStorage (useful after page refresh)
      loadPermissions() {
        const stored = localStorage.getItem("rolePermissions");
        if (stored) {
          this.rolePermissions = JSON.parse(stored);
        }
      },

      havePermission(permission) {
        if (!permission) return false;

        return this.rolePermissions.includes(permission);
      },

      // Clear all auth data
      logout() {
        this.rolePermissions = [];
        localStorage.removeItem("token");
        localStorage.removeItem("user");
        localStorage.removeItem("rolePermissions");
        router.push("/");
      }
    },
  });
