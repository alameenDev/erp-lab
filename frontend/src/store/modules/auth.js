import { bindCollectorWorker } from '@/utils/collectorPortalApp';
import { bindOwnerWorker } from '@/utils/ownerPortalApp';
import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import router from "@/router";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { applyBranding } from "@/utils/branding";
import { landingPage, permissionNames, readStoredJson } from "@/utils/accessControl";

let accessRequest = null;
let accessToken = null;
export const useAuthStore = defineStore("auth", {
  state: () => ({ rolePermissions: permissionNames(readStoredJson('rolePermissions', [])) }),
  actions: {
    async login({ email, password }) {
      const { data } = await $http.post('/user/login', { email, password });
      if (!data.token || !data.user) throw new Error('No login session received');
      // Replace permissions even when the new account has no grants.
      localStorage.setItem('token', data.token);
      this.applyAccess(data);
      if (!data.user.is_referral_partner && !data.user.owner_portal_only && !data.user.collector_portal_only) {
        try {
          const settings = useLabSettingsStore();
          await settings.GetSettings();
          applyBranding(settings.settings);
        } catch (error) { console.error('Error loading lab branding:', error); }
      }
      const redirect = router.currentRoute.value.query.redirect;
      const ownerTarget = data.user.owner_portal_only && typeof redirect === 'string' && /^\/owner-portal\/(notifications|app)(\?|$)/.test(redirect);
      await router.push(ownerTarget ? redirect : landingPage(data.user, this.rolePermissions));
    },
    applyAccess(data) {
      const previous = readStoredJson('user');
      if (!data.user?.owner_portal_only || (previous?.owner_portal_only && previous.id !== data.user.id)) bindOwnerWorker(null).catch(() => {});
      if (!data.user?.collector_portal_only || (previous?.collector_portal_only && previous.id !== data.user.id)) bindCollectorWorker(null).catch(() => {});
      localStorage.setItem('user', JSON.stringify(data.user));
      const effective = data.permissions ?? [
        ...(data.user?.roles || []).flatMap(role => role.permissions || []),
        ...(data.user?.permissions || []),
      ];
      this.setPermissions(data.user?.is_referral_partner || data.user?.owner_portal_only || data.user?.collector_portal_only ? [] : effective);
    },
    async refreshAccess() {
      const token = localStorage.getItem('token');
      if (!token) return null;
      if (accessRequest && accessToken === token) return accessRequest;
      accessToken = token;
      const pending = $http.get('/user/access').then(({ data }) => {
        if (localStorage.getItem('token') === token) this.applyAccess(data);
        return data;
      });
      accessRequest = pending;
      try { return await pending; }
      finally { if (accessRequest === pending) accessRequest = null; }
    },
    setPermissions(raw) {
      this.rolePermissions = permissionNames(raw);
      localStorage.setItem('rolePermissions', JSON.stringify(this.rolePermissions));
    },
    loadPermissions() { this.rolePermissions = permissionNames(readStoredJson('rolePermissions', [])); },
    havePermission(permission) { return Boolean(permission) && this.rolePermissions.includes(permission); },
    clearSession() {
      this.rolePermissions = [];
      for (const key of ['token', 'user', 'rolePermissions']) localStorage.removeItem(key);
    },
    async logout() {
      const owner = readStoredJson('user')?.owner_portal_only;
      const collector = readStoredJson('user')?.collector_portal_only;
      if (collector) { await bindCollectorWorker(null).catch(() => {}); try { await $http.post('/user/logout'); } catch {} }
      if (owner) {
        await bindOwnerWorker(null).catch(() => {});
        try { await $http.post('/user/logout'); } catch {}
      }
      this.clearSession(); return router.push(collector ? '/collector-portal/login' : owner ? '/owner-portal/login' : '/');
    },
  },
});
