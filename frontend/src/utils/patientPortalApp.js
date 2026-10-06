export function portalDevice(env = window) {
  const nav = env.navigator;
  const ios = /iPad|iPhone|iPod/.test(nav.userAgent) || (nav.platform === 'MacIntel' && nav.maxTouchPoints > 1);
  const installed = Boolean(env.matchMedia?.('(display-mode: standalone)').matches || nav.standalone);
  return { ios, installed, push: Boolean(env.isSecureContext && nav.serviceWorker && env.PushManager && env.Notification),
    embedded: /FBAN|FBAV|Instagram|; wv\)/i.test(nav.userAgent) };
}

export function vapidBytes(key) {
  const value = key.replace(/-/g, '+').replace(/_/g, '/');
  return Uint8Array.from(atob(value + '='.repeat((4 - value.length % 4) % 4)), char => char.charCodeAt(0));
}

export async function portalPreferenceKey(token) {
  // Do not duplicate the patient's bearer credential in storage keys.
  const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token));
  return 'portal-app:' + Array.from(new Uint8Array(hash), n => n.toString(16).padStart(2, '0')).join('');
}

export function waitForPortalWorker(registration, timeoutMs = 15000) {
  return new Promise((resolve, reject) => {
    const watched = new Set();
    let timer;
    function finish(error) {
      clearTimeout(timer);
      registration.removeEventListener('updatefound', check);
      watched.forEach(worker => worker.removeEventListener('statechange', check));
      if (error) reject(error); else resolve(registration);
    }
    function check() {
      if (registration.active?.state === 'activated') { finish(); return; }
      for (const worker of [registration.installing, registration.waiting, registration.active]) {
        if (worker && !watched.has(worker)) { watched.add(worker); worker.addEventListener('statechange', check); }
      }
    }
    registration.addEventListener('updatefound', check);
    timer = setTimeout(() => finish(new Error('تعذر تجهيز خدمة إشعارات البوابة. تحقق من الاتصال واضغط إعادة المحاولة.')), timeoutMs);
    check();
  });
}

export function portalPushError(error) {
  if (error?.name === 'NotAllowedError') return 'لم يتم السماح بالإشعارات. يمكنك تفعيلها من إعدادات الموقع في المتصفح.';
  if (error?.name === 'NotSupportedError') return 'هذا المتصفح لا يدعم الإشعارات. افتح البوابة في Safari أو Chrome واتبع إرشادات التثبيت.';
  if (error?.name === 'AbortError') return 'لم يكتمل الاتصال بخدمة الإشعارات. حاول مرة أخرى.';
  return error?.message || 'تعذر إتمام العملية. حاول مرة أخرى.';
}
