<template>
  <div id="app" :dir="lang === 'en' ? 'ltr' : 'rtl'" :class="lang === 'en' ? '' : 'rtl'">
    <UiLoading v-if="IsLoading" overlay :message="t('loading') + '...'" />
    <UiToast />
    <router-view></router-view>
  </div>
</template>

<script>
import { LoaderStore } from "@/store/modules/loader";
import { useAuthStore } from "@/store/modules/auth";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { mapWritableState } from "pinia";
import { $http } from "@/plugins/axios";
import { applyBranding, loadCachedBranding } from "@/utils/branding";

export default {
  computed: {
    ...mapWritableState(LoaderStore, ["IsLoading"]),
    lang() {
      return localStorage.getItem("locale") || "ar";
    },
  },
  async mounted() {
    const locale = localStorage.getItem("locale") || "ar";
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === "en" ? "ltr" : "rtl";

    const authStore = useAuthStore();
    authStore.loadPermissions();

    if (this.isUserLoggedIn()) {
      const user = JSON.parse(localStorage.getItem("user"));
      try {
        const { data } = await $http.get(`/permissions?role_id=${user.role_id}`);
        authStore.setPermissions(data.permissions);
      } catch (error) {
        console.error("Error fetching permissions:", error);
      }

      // Only apply branding inside the app (not welcome/login/register) AND only when token present
      const isPublicPage = ["/", "/login", "/register"].includes(window.location.pathname);
      const hasToken = !!localStorage.getItem("token");
      if (!isPublicPage && hasToken) {
        loadCachedBranding();
        try {
          const labSettingsStore = useLabSettingsStore();
          await labSettingsStore.GetSettings();
          applyBranding(labSettingsStore.settings);
        } catch (error) {
          console.error("Error loading lab branding:", error);
        }
      }

      // Listen for live updates from settings page
      window.addEventListener("lab-branding-updated", (e) => applyBranding(e.detail));
    }
  },
  methods: {
    isUserLoggedIn() {
      return !!localStorage.getItem("token");
    },
  },
};
</script>

<!-- Styles moved to main.css using Tailwind -->
