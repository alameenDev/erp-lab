import { scopedStorage } from './storage';
window.__demoAppUrl = location.origin + '/demo';
window.__demoImageUrl = location.origin + '/demo/storage';
const native = Reflect.get(window, 'local' + 'Storage');
const nativeSession = Reflect.get(window, 'session' + 'Storage');
window.__demoStorage = scopedStorage(native, 'digital-lab-learning-v1:');
window.__demoSessionStorage = scopedStorage(nativeSession, 'digital-lab-learning-v1:');
window.__demoStorage.setItem('locale', 'ar');

// Guard every transport, including legacy fetch/secondary Axios instances.
// Production API URLs are never a fallback. Browser cookies are scoped to /demo.
const secret = () => window.__demoStorage.getItem('demo-secret');
function destination(input) {
  const url = new URL(input, location.href);
  if (['blob:', 'data:'].includes(url.protocol)) return url.href;
  if (url.origin !== location.origin) throw new Error('الاتصال الخارجي معطل في التجربة التعليمية.');
  if (/^\/(api|storage)(\/|$)/.test(url.pathname)) url.pathname = '/demo' + url.pathname;
  if (!url.pathname.startsWith('/demo/')) throw new Error('هذا الاتصال خارج بيئة التدريب.');
  return url.href;
}
const originalFetch = window.fetch.bind(window);
window.fetch = (input, init = {}) => {
  try {
    const source = input instanceof Request ? input : null;
    const url = destination(source?.url || input);
    const headers = new Headers(init.headers || source?.headers);
    if (secret()) headers.set('X-Demo-Session', secret());
    return originalFetch(source ? new Request(url, source) : url, { ...init, headers });
  } catch (error) { return Promise.reject(error); }
};
const open = XMLHttpRequest.prototype.open;
const send = XMLHttpRequest.prototype.send;
XMLHttpRequest.prototype.open = function (method, url, ...rest) { return open.call(this, method, destination(url), ...rest); };
XMLHttpRequest.prototype.send = function (body) { if (secret()) this.setRequestHeader('X-Demo-Session', secret()); return send.call(this, body); };
// Direct WhatsApp hyperlinks/window.open are simulated; print/blob windows remain native.
const nativeOpen = window.open.bind(window);
window.open = (url, ...args) => {
  if (url && !['about:blank', ''].includes(url) && !String(url).startsWith('blob:')) {
    const target = new URL(url, location.href);
    if (target.origin !== location.origin) { window.dispatchEvent(new CustomEvent('demo-notice', { detail: 'محاكاة: لم يُفتح اتصال أو إرسال خارجي.' })); return null; }
    if (!target.pathname.startsWith('/demo/')) target.pathname = '/demo' + target.pathname;
    url = target.href;
  }
  return nativeOpen(url, ...args);
};
document.addEventListener('click', event => {
  const anchor = event.target.closest?.('a[href]');
  if (!anchor) return;
  const url = new URL(anchor.href, location.href);
  if (['blob:', 'data:'].includes(url.protocol)) return;
  if (url.origin !== location.origin || !url.pathname.startsWith('/demo/')) {
    event.preventDefault(); window.dispatchEvent(new CustomEvent('demo-notice', { detail: 'هذا الرابط خارج التجربة التعليمية.' }));
  }
}, true);
await import('../main.js');
