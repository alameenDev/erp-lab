// This scope is independent from patient portals and the staff application.
export async function bindOwnerWorker(ownerId, registration = null) {
  if (!navigator.serviceWorker) return;
  const reg = registration || await navigator.serviceWorker.getRegistration('/owner-portal/');
  if (!reg?.active || new URL(reg.scope).pathname !== '/owner-portal/') return;
  await new Promise((resolve, reject) => {
    const channel = new MessageChannel();
    const timer = setTimeout(() => { channel.port1.close(); reject(new Error('تعذر ربط إشعارات الجهاز. أعد المحاولة.')); }, 5000);
    channel.port1.onmessage = event => { clearTimeout(timer); channel.port1.close(); event.data?.ok ? resolve() : reject(new Error('تعذر حفظ إعدادات الإشعارات.')); };
    reg.active.postMessage({ type:'OWNER_BIND', ownerId:ownerId ? String(ownerId) : null }, [channel.port2]);
  });
}

let restoreHead = null;
export function ownerPortalHead(active, doc = document) {
  if (!active) { restoreHead?.(); restoreHead = null; return; }
  if (restoreHead) return;
  const undo = [];
  const claim = (selector, tag, attrs) => {
    let el = doc.querySelector(selector); const previous = el?.cloneNode(true);
    if (!el) { el = doc.createElement(tag); doc.head.appendChild(el); }
    Object.entries(attrs).forEach(([key,value]) => el.setAttribute(key,value));
    undo.push(() => previous ? el.replaceWith(previous) : el.remove());
  };
  claim('link[rel="manifest"]', 'link', { rel:'manifest', href:'/owner-app/manifest.webmanifest' });
  for (const [name,content] of Object.entries({ 'theme-color':'#102f30', 'apple-mobile-web-app-capable':'yes', 'mobile-web-app-capable':'yes', 'apple-mobile-web-app-title':'مالك Digital Lab' })) claim(`meta[name="${name}"]`, 'meta', { name,content });
  restoreHead = () => undo.reverse().forEach(fn => fn());
}
