import { ref, computed, onMounted, onUnmounted } from 'vue';
import { portalDevice, portalPreferenceKey, vapidBytes, portalPushError } from '@/utils/patientPortalApp';

export function usePatientPortalApp(token) {
  const device = ref(portalDevice()), permission = ref(window.Notification?.permission || 'unsupported');
  const config = ref(null), subscribed = ref(false), results = ref(true), offers = ref(false);
  const busy = ref(false), error = ref(''), message = ref(''), welcome = ref(false), installEvent = ref(null);
  const inbox = ref([]), unread = ref(0), initialized = ref(false);
  let registration = null, preferenceKey = '', alive = true, oldAppleTitle, appleTitle;
  const base = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
  const path = `${base}/portal/${encodeURIComponent(token)}`;
  const canSubscribe = computed(() => Boolean(config.value?.public_key && device.value.push &&
    (!device.value.ios || device.value.installed) && permission.value !== 'denied' && !device.value.embedded));
  const statusText = computed(() => subscribed.value && permission.value === 'granted' ? 'مفعّلة على هذا الجهاز' :
    permission.value === 'denied' ? 'محظورة من المتصفح' : device.value.ios && !device.value.installed ? 'متاحة بعد إضافة البوابة' : 'غير مفعّلة');

  async function api(suffix, data) {
    const response = await fetch(path + suffix, { method: data === undefined ? 'GET' : 'POST',
      headers: { Accept: 'application/json', ...(data === undefined ? {} : { 'Content-Type': 'application/json' }) },
      cache: 'no-store', credentials: 'same-origin', ...(data === undefined ? {} : { body: JSON.stringify(data) }) });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || 'تعذر الاتصال بخدمة البوابة. حاول مجدداً.');
    return body;
  }

  function dismiss() {
    welcome.value = false;
    if (preferenceKey) try { localStorage.setItem(preferenceKey, 'dismissed'); } catch { /* private browsing */ }
  }

  async function refresh() {
    device.value = portalDevice();
    permission.value = window.Notification?.permission || 'unsupported';
    if (!registration || !device.value.push) return;
    const browserSubscription = await registration.pushManager.getSubscription();
    if (!browserSubscription) { subscribed.value = false; return; }
    const data = await api('/push/status', { endpoint: browserSubscription.endpoint });
    if (!alive) return;
    subscribed.value = data.subscribed;
    results.value = data.results_enabled;
    offers.value = data.offers_enabled;
  }

  async function loadInbox() {
    const data = await api('/notifications');
    if (alive) { inbox.value = data.items || []; unread.value = data.unread || 0; }
  }

  async function markRead() {
    try {
      const ids = inbox.value.filter(item => !item.read_at).map(item => item.id);
      if (ids.length) await api('/notifications/read', { ids });
      await loadInbox();
    } catch (e) { error.value = portalPushError(e); }
  }

  // This handler must invoke requestPermission synchronously from the click;
  // waiting for network or a service worker first loses Safari user activation.
  async function enable() {
    if (busy.value || !canSubscribe.value) return;
    busy.value = true; error.value = ''; message.value = '';
    try {
      const granted = window.Notification.permission === 'granted' ? 'granted' : await window.Notification.requestPermission();
      permission.value = granted;
      if (granted !== 'granted') {
        error.value = granted === 'denied' ? 'تم رفض السماح. يمكنك تغييره من إعدادات الموقع في المتصفح.' : 'لم يتم تفعيل الإشعارات. يمكنك المحاولة لاحقاً.';
        return;
      }
      if (!registration) throw new Error('جاري تجهيز البوابة على جهازك. انتظر لحظة ثم حاول مجدداً.');
      const subscription = await registration.pushManager.getSubscription() || await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: vapidBytes(config.value.public_key) });
      await api('/push/subscribe', { subscription: subscription.toJSON(), results_enabled: results.value, offers_enabled: offers.value });
      subscribed.value = true; dismiss(); message.value = 'تم تفعيل إشعاراتك على هذا الجهاز.';
    } catch (e) { error.value = portalPushError(e); }
    finally { busy.value = false; }
  }

  async function save() {
    if (busy.value) return;
    busy.value = true; error.value = ''; message.value = '';
    try {
      const subscription = await registration?.pushManager.getSubscription();
      if (!subscription || !subscribed.value) throw new Error('فعّل إشعارات هذا الجهاز أولاً.');
      await api('/push/subscribe', { subscription: subscription.toJSON(), results_enabled: results.value, offers_enabled: offers.value });
      message.value = 'تم حفظ تفضيلات إشعاراتك.';
    } catch (e) { error.value = portalPushError(e); }
    finally { busy.value = false; }
  }

  async function disable() {
    if (busy.value) return;
    busy.value = true; error.value = ''; message.value = '';
    try {
      const subscription = await registration?.pushManager.getSubscription();
      if (subscription) await api('/push/unsubscribe', { endpoint: subscription.endpoint });
      subscribed.value = false;
      message.value = 'تم إيقاف إشعاراتك على هذا الجهاز. تبقى تحديثاتك داخل البوابة.';
    } catch (e) { error.value = portalPushError(e); }
    finally { busy.value = false; }
  }

  async function testNotification() {
    if (busy.value) return;
    busy.value = true; error.value = ''; message.value = '';
    try {
      const subscription = await registration?.pushManager.getSubscription();
      if (!subscription) throw new Error('فعّل الإشعارات أولاً.');
      await api('/push/test', { endpoint: subscription.endpoint });
      message.value = 'أُضيف الإشعار التجريبي للإرسال. قد يستغرق وصوله دقيقة أو أكثر حسب الاتصال وإعدادات جهازك.';
      await loadInbox();
    } catch (e) { error.value = portalPushError(e); }
    finally { busy.value = false; }
  }

  async function install() {
    if (!installEvent.value || busy.value) return false;
    busy.value = true; error.value = ''; message.value = '';
    try {
      const prompt = installEvent.value; installEvent.value = null;
      const result = await prompt.prompt();
      message.value = result.outcome === 'accepted' ? 'تم قبول طلب الإضافة. افتح البوابة من أيقونتها على جهازك.' : 'يمكنك إضافة البوابة لاحقاً من الإعدادات.';
      return true;
    } catch (e) { error.value = portalPushError(e); return false; }
    finally { busy.value = false; }
  }

  function captureInstall(event) { event.preventDefault(); installEvent.value = event; }
  function installed() { device.value = { ...portalDevice(), installed: true }; installEvent.value = null; dismiss(); }
  function visibility() { if (document.visibilityState === 'visible') { void refresh().catch(() => {}); void loadInbox().catch(() => {}); } }

  onMounted(async () => {
    window.addEventListener('beforeinstallprompt', captureInstall);
    window.addEventListener('appinstalled', installed);
    document.addEventListener('visibilitychange', visibility);
    appleTitle = document.querySelector('meta[name="apple-mobile-web-app-title"]');
    oldAppleTitle = appleTitle?.content;
    if (!appleTitle) { appleTitle = document.createElement('meta'); appleTitle.name = 'apple-mobile-web-app-title'; document.head.appendChild(appleTitle); }
    appleTitle.content = 'بوابة المريض';
    if (navigator.serviceWorker && window.isSecureContext) {
      navigator.serviceWorker.register('/portal/sw.js', { scope: '/portal/', updateViaCache: 'none' })
        .then(() => navigator.serviceWorker.ready).then(value => { if (alive) { registration = value; void refresh().catch(() => {}); } }).catch(() => {});
    }
    try {
      preferenceKey = await portalPreferenceKey(token);
      const data = await api('/app-config');
      if (!alive) return;
      config.value = data;
      await refresh();
      let dismissed = false;
      try { dismissed = localStorage.getItem(preferenceKey) === 'dismissed'; } catch { /* private browsing */ }
      welcome.value = !dismissed && !(device.value.installed && subscribed.value) && permission.value !== 'denied';
      await loadInbox();
    } catch (e) { if (alive) error.value = portalPushError(e); }
    finally { initialized.value = true; }
  });

  onUnmounted(() => {
    alive = false;
    window.removeEventListener('beforeinstallprompt', captureInstall);
    window.removeEventListener('appinstalled', installed);
    document.removeEventListener('visibilitychange', visibility);
    if (appleTitle?.content === 'بوابة المريض') {
      if (oldAppleTitle !== undefined) appleTitle.content = oldAppleTitle; else appleTitle.remove();
    }
  });
  return { device, permission, config, subscribed, results, offers, busy, error, message, welcome, installEvent,
    inbox, unread, initialized, canSubscribe, statusText, dismiss, enable, save, disable, testNotification, install, loadInbox, markRead };
}
