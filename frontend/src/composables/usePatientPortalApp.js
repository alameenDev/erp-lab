import { ref, computed, onMounted, onUnmounted } from 'vue';
import { portalDevice, portalPreferenceKey, vapidBytes, portalPushError, waitForPortalWorker } from '@/utils/patientPortalApp';

export function usePatientPortalApp(token) {
  const device = ref(portalDevice()), permission = ref(window.Notification?.permission || 'unsupported');
  // New patients approve both clearly-described categories with the native
  // consent. Existing server preferences and explicit opt-outs are preserved.
  const config = ref(null), subscribed = ref(false), results = ref(true), offers = ref(true);
  const busy = ref(false), preparing = ref(true), workerReady = ref(false), error = ref(''), message = ref('');
  const installEvent = ref(null), inbox = ref([]), unread = ref(0), initialized = ref(false);
  let registration = null, browserSubscription = null, preferenceKey = '', alive = true, refreshing = null, oldAppleTitle, appleTitle;
  const base = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
  const path = base + '/portal/' + encodeURIComponent(token);
  const canSubscribe = computed(() => Boolean(initialized.value && workerReady.value && config.value?.public_key && device.value.push &&
    (!device.value.ios || device.value.installed) && permission.value !== 'denied' && !device.value.embedded));
  const active = computed(() => subscribed.value && permission.value === 'granted');
  const statusText = computed(() => active.value ? 'مفعّلة على هذا الجهاز' :
    permission.value === 'denied' ? 'محظورة من الجهاز' : device.value.ios && !device.value.installed ? 'تحتاج الإضافة للشاشة الرئيسية' :
      preparing.value ? 'جاري تجهيز الإشعارات…' : 'بانتظار السماح من هذا الجهاز');

  async function api(suffix, data) {
    const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(path + suffix, { method: data === undefined ? 'GET' : 'POST',
        headers: { Accept: 'application/json', ...(data === undefined ? {} : { 'Content-Type': 'application/json' }) },
        cache: 'no-store', credentials: 'same-origin', signal: controller.signal,
        ...(data === undefined ? {} : { body: JSON.stringify(data) }) });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.message || 'تعذر الاتصال بخدمة البوابة. حاول مجدداً.');
      return body;
    } finally { clearTimeout(timer); }
  }

  function rememberedChoice() {
    try {
      const value = JSON.parse(localStorage.getItem(preferenceKey));
      return value && ['enabled', 'disabled'].includes(value.state) ? value : null;
    } catch { return null; }
  }
  function remember(state) {
    if (preferenceKey) try {
      localStorage.setItem(preferenceKey, JSON.stringify({ state, results_enabled: results.value, offers_enabled: offers.value }));
    } catch { /* Browsers with restricted storage still allow explicit opt-in. */ }
  }
  async function persistSubscription(subscription) {
    await api('/push/subscribe', { subscription: subscription.toJSON(), results_enabled: results.value, offers_enabled: offers.value });
    if (!alive) return;
    subscribed.value = true;
    remember('enabled');
  }

  async function refreshSubscription() {
    device.value = portalDevice();
    permission.value = window.Notification?.permission || 'unsupported';
    if (!registration || !device.value.push) return;
    browserSubscription = await registration.pushManager.getSubscription();
    if (!alive) return;
    if (!browserSubscription) { subscribed.value = false; return; }
    const data = await api('/push/status', { endpoint: browserSubscription.endpoint });
    if (!alive) return;
    const choice = rememberedChoice();
    if (choice?.state === 'disabled') {
      // Finish a previously failed opt-out, without removing a family member's endpoint.
      if (data.subscribed) await api('/push/unsubscribe', { endpoint: browserSubscription.endpoint });
      subscribed.value = false;
      return;
    }
    subscribed.value = Boolean(data.subscribed);
    if (data.subscribed) {
      results.value = data.results_enabled; offers.value = data.offers_enabled;
      remember('enabled');
    } else if (choice?.state === 'enabled' && permission.value === 'granted') {
      // Resume saving THIS portal after an interrupted request. Origin-wide
      // browser permission alone must not silently subscribe another patient.
      results.value = choice.results_enabled !== false;
      offers.value = choice.offers_enabled === true;
      await persistSubscription(browserSubscription);
      if (alive) message.value = 'تم تفعيل إشعاراتك على هذا الجهاز.';
    }
  }
  async function refresh() {
    if (busy.value || preparing.value) return;
    if (!refreshing) refreshing = refreshSubscription().finally(() => { refreshing = null; });
    return refreshing;
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
    } catch (e) { if (alive) error.value = portalPushError(e); }
  }

  // All preparation is finished BEFORE showing an enabled button. Calling
  // subscribe directly from the click preserves Safari's required user gesture
  // and lets the browser show its own single permission prompt.
  async function enable() {
    if (busy.value || !canSubscribe.value) return;
    busy.value = true; error.value = ''; message.value = '';
    remember('enabled');
    try {
      const pending = registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: vapidBytes(config.value.public_key) });
      browserSubscription = await pending;
      if (!alive) return;
      permission.value = window.Notification.permission;
      await persistSubscription(browserSubscription);
      if (alive) message.value = 'تم تفعيل إشعاراتك على هذا الجهاز.';
    } catch (e) {
      if (alive) {
        permission.value = window.Notification?.permission || 'unsupported';
        error.value = permission.value === 'granted'
          ? 'سماح الجهاز موجود، لكن لم يكتمل ربط الإشعارات ببوابتك. اضغط «السماح بالإشعارات» للمحاولة مجدداً. ' + portalPushError(e)
          : portalPushError(e);
      }
    } finally { busy.value = false; }
  }

  async function save() {
    if (busy.value) return;
    busy.value = true; error.value = ''; message.value = '';
    try {
      if (!browserSubscription || !subscribed.value) throw new Error('اسمح بالإشعارات على هذا الجهاز أولاً.');
      await persistSubscription(browserSubscription);
      if (alive) message.value = 'تم حفظ تفضيلات إشعاراتك.';
    } catch (e) { if (alive) error.value = portalPushError(e); }
    finally { busy.value = false; }
  }
  async function disable() {
    if (busy.value) return;
    busy.value = true; error.value = ''; message.value = '';
    remember('disabled');
    try {
      if (browserSubscription) await api('/push/unsubscribe', { endpoint: browserSubscription.endpoint });
      if (alive) {
        subscribed.value = false;
        message.value = 'تم إيقاف إشعاراتك على هذا الجهاز. تبقى تحديثاتك داخل البوابة.';
      }
    } catch (e) { if (alive) error.value = portalPushError(e); }
    finally { busy.value = false; }
  }
  async function testNotification() {
    if (busy.value) return;
    busy.value = true; error.value = ''; message.value = '';
    try {
      if (!browserSubscription || !subscribed.value) throw new Error('اسمح بالإشعارات أولاً.');
      await api('/push/test', { endpoint: browserSubscription.endpoint });
      if (!alive) return;
      message.value = 'أُضيف الإشعار التجريبي للإرسال. قد يستغرق وصوله دقيقة أو أكثر حسب الاتصال وإعدادات جهازك.';
      await loadInbox();
    } catch (e) { if (alive) error.value = portalPushError(e); }
    finally { busy.value = false; }
  }
  async function install() {
    if (!installEvent.value || busy.value) return false;
    busy.value = true; error.value = ''; message.value = '';
    try {
      const prompt = installEvent.value; installEvent.value = null;
      const result = await prompt.prompt();
      if (alive) message.value = result.outcome === 'accepted' ? 'تم قبول طلب الإضافة. افتح البوابة من أيقونتها على جهازك.' : 'يمكنك إضافة البوابة لاحقاً من الإعدادات.';
      return true;
    } catch (e) { if (alive) error.value = portalPushError(e); return false; }
    finally { busy.value = false; }
  }
  function captureInstall(event) { event.preventDefault(); installEvent.value = event; }
  function installed() { device.value = { ...portalDevice(), installed: true }; installEvent.value = null; }
  function visibility() {
    if (document.visibilityState === 'visible' && initialized.value && !preparing.value && !busy.value) {
      void refresh().catch(e => { if (alive) error.value = portalPushError(e); });
      void loadInbox().catch(() => {});
    }
  }

  async function prepare() {
    if (busy.value) return;
    preparing.value = true; workerReady.value = false; error.value = '';
    try {
      preferenceKey = await portalPreferenceKey(token);
      const [data, worker] = await Promise.all([
        api('/app-config'),
        navigator.serviceWorker && window.isSecureContext
          ? navigator.serviceWorker.register('/portal/sw.js', { scope: '/portal/', updateViaCache: 'none' }).then(waitForPortalWorker)
          : Promise.resolve(null),
      ]);
      if (!alive) return;
      config.value = data; registration = worker;
      await refreshSubscription();
      if (alive) workerReady.value = Boolean(worker);
    } catch (e) { if (alive) error.value = portalPushError(e); }
    finally { if (alive) { initialized.value = true; preparing.value = false; } }
    if (alive) void loadInbox().catch(() => {});
  }

  onMounted(() => {
    window.addEventListener('beforeinstallprompt', captureInstall);
    window.addEventListener('appinstalled', installed);
    document.addEventListener('visibilitychange', visibility);
    appleTitle = document.querySelector('meta[name="apple-mobile-web-app-title"]');
    oldAppleTitle = appleTitle?.content;
    if (!appleTitle) { appleTitle = document.createElement('meta'); appleTitle.name = 'apple-mobile-web-app-title'; document.head.appendChild(appleTitle); }
    appleTitle.content = 'بوابة المريض';
    void prepare();
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
  return { device, permission, config, subscribed, results, offers, busy, preparing, workerReady, error, message, installEvent,
    inbox, unread, initialized, canSubscribe, active, statusText, enable, save, disable, testNotification, install, loadInbox, markRead, prepare };
}
