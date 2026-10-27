import { reactive, watch, onMounted, onBeforeUnmount } from 'vue';
import { $http } from '@/plugins/axios';
import { portalDevice, vapidBytes, waitForPortalWorker, portalPushError } from '@/utils/patientPortalApp';
import { bindOwnerWorker } from '@/utils/ownerPortalApp';

export function useOwnerPortalApp(ownerId) {
  const state = reactive({ device:portalDevice(), ready:false, preparing:false, busy:false, error:'', message:'', config:null,
    subscribed:false, invoice_enabled:true, daily_enabled:true, unread:0, installAvailable:false, permission:window.Notification?.permission || 'default' });
  let registration, subscription, installEvent, revision = 0, timer, disposed = false;
  const current = version => !disposed && version === revision;
  const errorText = e => e.response?.data?.message || portalPushError(e);
  async function refreshUnread() {
    const version = revision;
    if (!ownerId.value || document.hidden) return;
    try { const { data } = await $http.get('/owner-portal/notifications'); if (current(version)) state.unread = data.unread; } catch { /* Inbox has its own retry UI. */ }
  }
  async function prepare() {
    const version = ++revision;
    state.ready = false; state.preparing = true; state.error = ''; state.subscribed = false;
    state.device = portalDevice(); state.permission = window.Notification?.permission || 'default';
    if (!ownerId.value) { state.preparing = false; return; }
    try {
      const [config, reg] = await Promise.all([
        $http.get('/owner-portal/notifications/config'),
        navigator.serviceWorker && window.isSecureContext ? navigator.serviceWorker.register('/owner-portal/sw.js', { scope:'/owner-portal/', updateViaCache:'none' }).then(waitForPortalWorker) : Promise.resolve(null),
      ]);
      if (!current(version)) return;
      state.config = config.data; registration = reg;
      subscription = state.device.push && reg ? await reg.pushManager.getSubscription() : null;
      if (!current(version)) return;
      if (subscription) {
        const { data } = await $http.post('/owner-portal/notifications/push/status', { endpoint:subscription.endpoint });
        if (!current(version)) return;
        Object.assign(state, data);
      }
      await bindOwnerWorker(state.subscribed ? ownerId.value : null, reg);
      if (current(version)) state.ready = Boolean(reg && config.data.enabled && state.device.push);
    } catch(e) { if (current(version)) state.error = errorText(e); }
    finally { if (current(version)) { state.preparing = false; refreshUnread(); } }
  }
  async function enable() {
    if (!state.ready || state.busy) return;
    const version = revision;
    state.busy = true; state.error = ''; state.message = '';
    try {
      // Safari requires subscribe() directly in the click gesture, before any awaited work.
      const pending = subscription ? Promise.resolve(subscription) : registration.pushManager.subscribe({ userVisibleOnly:true, applicationServerKey:vapidBytes(state.config.public_key) });
      const sub = await pending;
      if (!current(version)) return;
      subscription = sub;
      const { data } = await $http.post('/owner-portal/notifications/push/subscribe', { subscription:sub.toJSON(), invoice_enabled:state.invoice_enabled, daily_enabled:state.daily_enabled });
      if (!current(version)) return;
      await bindOwnerWorker(ownerId.value, registration);
      if (!current(version)) return;
      Object.assign(state, data); state.permission = Notification.permission; state.message = 'تم حفظ إشعارات هذا الجهاز.';
    } catch(e) { if (current(version)) state.error = errorText(e); }
    finally { if (current(version)) state.busy = false; }
  }
  async function disable() {
    if (state.busy) return;
    state.busy = true; state.error = ''; state.message = '';
    try {
      if (subscription) await $http.post('/owner-portal/notifications/push/unsubscribe', { endpoint:subscription.endpoint });
      await bindOwnerWorker(null, registration);
      if (subscription) await subscription.unsubscribe();
      subscription = null; state.subscribed = false; state.message = 'تم إيقاف إشعارات هذا الجهاز. يبقى السجل داخل البوابة.';
    } catch(e) { state.error = errorText(e); } finally { state.busy = false; }
  }
  async function test() {
    if (!subscription || !state.subscribed || state.busy) return;
    state.busy = true; state.error = ''; state.message = '';
    try { const { data } = await $http.post('/owner-portal/notifications/push/test', { endpoint:subscription.endpoint }); state.message = data.message; await refreshUnread(); }
    catch(e) { state.error = errorText(e); } finally { state.busy = false; }
  }
  function beforeInstall(event) { event.preventDefault(); installEvent = event; state.installAvailable = true; }
  function installed() { installEvent = null; state.installAvailable = false; state.device = portalDevice(); }
  async function install() { if (!installEvent) return; const prompt = installEvent; installEvent = null; state.installAvailable = false; await prompt.prompt(); await prompt.userChoice; state.device = portalDevice(); }
  function visible() { if (!document.hidden) { state.device = portalDevice(); refreshUnread(); } }
  watch(ownerId, prepare);
  onMounted(() => { window.addEventListener('beforeinstallprompt', beforeInstall); window.addEventListener('appinstalled', installed); document.addEventListener('visibilitychange', visible); timer = setInterval(refreshUnread, 60000); if (ownerId.value) prepare(); });
  onBeforeUnmount(() => { disposed = true; revision++; clearInterval(timer); window.removeEventListener('beforeinstallprompt', beforeInstall); window.removeEventListener('appinstalled', installed); document.removeEventListener('visibilitychange', visible); });
  return Object.assign(state, { prepare, enable, disable, test, install, refreshUnread, clearBinding:() => bindOwnerWorker(null, registration) });
}
