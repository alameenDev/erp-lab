// Interface events describe clicks only. Confirmed database changes come from server audit.
let recent = null;
let installed = false;
let queue = [];
let timer;
const excluded = path => /^\/(login|register|portal|result|invoice|doctor-portal|referral-portal)(\/|$)/.test(path);

export function activityRequestHeaders(method, url) {
  if (!recent || Date.now() - recent.at > 15000 || !['post', 'put', 'patch', 'delete'].includes(String(method).toLowerCase()) || /activity\/|\/log\/|search|heartbeat/.test(url || '')) return {};
  return {
    'X-Audit-Action-Id': recent.action_id,
    'X-Audit-Label': encodeURIComponent(recent.label),
    'X-Audit-Page': recent.page,
  };
}

export function buttonActivity(target, path = window.location.pathname) {
  if (excluded(path)) return null;
  const button = target?.closest?.('button, [role="button"], input[type="submit"], input[type="button"]');
  if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true' || button.closest('[data-audit-ignore]')) return null;
  const label = (button.dataset.auditLabel || button.getAttribute('aria-label') || button.getAttribute('title') || button.textContent || button.value || 'زر بدون تسمية').replace(/\s+/g, ' ').trim().slice(0, 160);
  const container = button.closest('[data-audit-invoice-id]');
  const routeInvoice = path.match(/^\/medical_reports\/update-result\/(\d+)/)?.[1];
  const invoiceId = Number(container?.dataset.auditInvoiceId || routeInvoice || 0);
  return { action_id: crypto.randomUUID(), label, page: path.slice(0, 200), ...(invoiceId > 0 ? { invoice_id: invoiceId } : {}) };
}

export function installActivityAudit() {
  if (installed) return;
  installed = true;
  const flush = async () => {
    clearTimeout(timer);
    if (!queue.length) return;
    const batch = queue.splice(0, 20);
    const token = batch[0].token;
    const actions = batch.filter(item => item.token === token).map(({ token: _, ...action }) => action);
    try {
      const base = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
      await fetch(base + '/activity/actions', {
        method: 'POST', credentials: 'include', keepalive: true,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({ actions }),
      });
    } catch { /* UI telemetry must not prevent the requested clinical action. */ }
    if (queue.length) timer = setTimeout(flush, 300);
  };
  document.addEventListener('click', event => {
    const token = localStorage.getItem('token');
    if (!token) return;
    const action = buttonActivity(event.target);
    if (!action) return;
    recent = { ...action, at: Date.now() };
    queue.push({ ...action, token });
    if (queue.length >= 20) void flush();
    else { clearTimeout(timer); timer = setTimeout(flush, 300); }
  }, true);
  window.addEventListener('pagehide', flush);
}
