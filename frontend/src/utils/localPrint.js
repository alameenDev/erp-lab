// Local printer choices belong to this browser/device, never to lab settings
// on the server. An empty printer always means the existing browser dialog.
export const PRINT_TYPES = [
  ['barcode', 'الباركود'], ['report', 'التقارير الطبية'], ['invoice', 'الفواتير'],
  ['thermal', 'الوصولات الحرارية'], ['job', 'أوامر العمل'], ['worksheet', 'ورقة العمل'],
  ['history', 'سجل نتائج المريض'], ['register', 'سجل العمل اليومي'],
];
export const PRINT_STORAGE_KEY = 'digital-lab-print-v1';
const BASE = 'http://127.0.0.1:17891/v1';
const active = new Set();
export function readPrintSettings() {
  try {
    const value = JSON.parse(globalThis.localStorage?.getItem(PRINT_STORAGE_KEY) || '{}');
    return {enabled: value.enabled === true, token: globalThis.window?.digitalLabDesktop ? 'd'.repeat(64) : /^[a-f0-9]{64}$/.test(value.token || '') ? value.token : '',
      routes: Object.fromEntries(PRINT_TYPES.map(([kind]) => [kind, {
        printer: typeof value.routes?.[kind]?.printer === 'string' ? value.routes[kind].printer : '',
        copies: Math.max(1, Math.min(20, Math.trunc(Number(value.routes?.[kind]?.copies) || 1))),
      }]))};
  } catch {return {enabled: false, token: '', routes: {}};}
}
export function savePrintSettings(value) {
  localStorage.setItem(PRINT_STORAGE_KEY, JSON.stringify(value));
  window.dispatchEvent(new Event('digital-lab-print-settings'));
}
export async function helperRequest(path, {method = 'GET', body, token = readPrintSettings().token, timeout = 7000} = {}) {
  if (globalThis.window?.digitalLabDesktop) return window.digitalLabDesktop.printRequest(path, {method, body});
  const response = await fetch(BASE + path, {method, mode: 'cors', credentials: 'omit', cache: 'no-store',
    headers: {...(token ? {Authorization: `Bearer ${token}`} : {}), ...(body ? {'Content-Type': 'application/json'} : {})},
    ...(body ? {body: JSON.stringify(body)} : {}), signal: AbortSignal.timeout(timeout)});
  const data = await response.json();
  if (!response.ok) throw Object.assign(new Error(data.error || 'تعذر الاتصال بمساعد الطباعة.'), {status: response.status});
  return data;
}
export async function pairPrintHelper(code) {
  const data = await helperRequest('/pair', {method: 'POST', body: {code}, token: '', timeout: 90000});
  if (!/^[a-f0-9]{64}$/.test(data.token || '')) throw new Error('استجابة ربط غير صالحة.');
  savePrintSettings({...readPrintSettings(), token: data.token, enabled: false});
}
export async function unpairPrintHelper() {
  await helperRequest('/pair', {method: 'DELETE'});
  savePrintSettings({...readPrintSettings(), token: '', enabled: false});
}
export const printRouteEnabled = kind => {const s = readPrintSettings(); return !!(s.enabled && s.token && s.routes[kind]?.printer);};

// Read the last @page media size without rewriting existing margins/styles.
// The receipt's "80mm auto" is retained even on engines that reject that CSS.
export function printPaper(css, kind = 'invoice') {
  let size = '';
  for (const page of css.matchAll(/@page\s*(?:[^{}]*)\{([^}]*)\}/gi)) {
    for (const declaration of page[1].matchAll(/(?:^|;)\s*size\s*:\s*([^;]+)/gi)) size = declaration[1].replace(/!important/gi, '').trim();
  }
  const mm = /^(\d+(?:\.\d+)?)mm\s+(auto|\d+(?:\.\d+)?mm)$/i.exec(size);
  if (mm) return {width: Number(mm[1]), height: mm[2].toLowerCase() === 'auto' ? null : Number.parseFloat(mm[2])};
  if (kind === 'thermal') return {width: 80, height: null};
  const dims = /\bA5\b/i.test(size) ? [148, 210] : /\bA3\b/i.test(size) ? [297, 420] : [210, 297];
  if (/landscape/i.test(size)) dims.reverse();
  return {width: dims[0], height: dims[1]};
}
async function dataUrl(source, base) {
  if (source.startsWith('data:')) return source;
  const url = new URL(source, base);
  if (!['http:', 'https:', 'blob:'].includes(url.protocol)) throw new Error('تعذر تجهيز صورة للمساعد. استخدم الطباعة الاعتيادية.');
  const response = await fetch(url.href, {credentials: url.origin === window.location.origin ? 'same-origin' : 'omit', signal: AbortSignal.timeout(15000)});
  if (!response.ok) throw new Error('تعذر تحميل صورة للمستند.');
  const blob = await response.blob();
  if (!/^(image\/|font\/|application\/(?:octet-stream|font|vnd\.ms-fontobject))/i.test(blob.type)) throw new Error('رابط الصورة أو الخط لا يعيد ملفاً صالحاً. استخدم الطباعة الاعتيادية.');
  return new Promise((resolve, reject) => {const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = reject; reader.readAsDataURL(blob);});
}
export async function snapshotPrintDocument(doc, kind) {
  const clone = doc.documentElement.cloneNode(true);
  const base = /^https?:/.test(doc.baseURI) ? doc.baseURI : window.location.href;
  const cache = new Map();
  const embed = url => {if (!cache.has(url)) cache.set(url, dataUrl(url, base)); return cache.get(url);};
  const css = [...clone.querySelectorAll('style')].map(s => s.textContent).join('\n');
  if (clone.querySelector('link[rel="stylesheet"]') || /@import\b/i.test(css)) throw new Error('هذا المستند يحتاج الطباعة الاعتيادية لتحميل تنسيقه.');
  clone.querySelectorAll('script,iframe,object,embed,base,meta,link').forEach(node => node.remove());
  for (const node of [clone, ...clone.querySelectorAll('*')]) {
    for (const attr of [...node.attributes]) if (/^on/i.test(attr.name) || ['srcdoc', 'action', 'formaction'].includes(attr.name)) node.removeAttribute(attr.name);
  }
  await Promise.all([...clone.querySelectorAll('img')].map(async img => {
    img.removeAttribute('srcset'); img.removeAttribute('loading');
    if (img.getAttribute('src')) img.src = await embed(img.getAttribute('src'));
  }));
  for (const node of clone.querySelectorAll('image')) {
    const href = node.getAttribute('href') || node.getAttribute('xlink:href');
    if (href) {node.setAttribute('href', await embed(href)); node.removeAttribute('xlink:href');}
  }
  const embedCss = async text => {
    let result = text;
    for (const match of text.matchAll(/url\(\s*(['"]?)(.*?)\1\s*\)/gi)) {
      if (!match[2] || match[2].startsWith('#')) continue;
      result = result.replace(match[0], `url("${await embed(match[2])}")`);
    }
    return result;
  };
  for (const style of clone.querySelectorAll('style')) style.textContent = await embedCss(style.textContent);
  for (const node of clone.querySelectorAll('[style]')) node.setAttribute('style', await embedCss(node.getAttribute('style')));
  const html = '<!doctype html>' + clone.outerHTML;
  if (new Blob([html]).size > 120 * 1024 * 1024) throw new Error('المستند كبير للطباعة المباشرة. استخدم الطباعة الاعتيادية.');
  return {html, paper: printPaper(css, kind)};
}
const uncertain = () => new Error('حالة إرسال الطباعة غير مؤكدة. تحقق من قائمة الطباعة في Windows ومن الأوراق قبل إعادة الطباعة؛ لم تُرسل نسخة ثانية تلقائياً.');
async function submit(job, token) {
  let status;
  // Once POST starts, never fall back or retry automatically, even if the
  // response was lost. Query this same opaque job ID to recover its outcome.
  try {status = await helperRequest('/jobs', {method: 'POST', body: job, token, timeout: 120000});}
  catch (error) {
    if (error.status) throw error;
    try {status = await helperRequest('/jobs/' + job.id, {token});} catch {throw uncertain();}
  }
  const deadline = Date.now() + 120000;
  while (['queued', 'rendering', 'submitting'].includes(status.status) && Date.now() < deadline) {
    await new Promise(resolve => setTimeout(resolve, 700));
    try {status = await helperRequest('/jobs/' + job.id, {token});} catch {throw uncertain();}
  }
  if (status.status === 'submitted') return;
  if (status.status === 'failed') throw new Error(status.error || 'تعذر إرسال المستند للطابعة.');
  throw uncertain();
}
/** Return true only when the helper accepted this document into the spooler.
 *  false means the caller should run its original browser print unchanged. */
export async function routePrintDocument(doc, kind, {settings = readPrintSettings(), allowFallback = true, canSend = () => true} = {}) {
  const route = settings.routes[kind];
  if (!settings.enabled || !settings.token || !route?.printer) return false;
  if (active.has(kind)) throw Object.assign(new Error('يوجد طلب طباعة من هذا النوع قيد الإرسال. انتظر اكتماله.'), {localPrintFailure: true});
  active.add(kind);
  try {
    let snapshot;
    try {
      const {printers} = await helperRequest('/printers', {token: settings.token});
      if (!printers?.some(p => p.name === route.printer)) throw new Error('الطابعة المحفوظة غير موجودة على هذه الحاسبة.');
      snapshot = await snapshotPrintDocument(doc, kind);
    } catch (error) {
      const reason = error.message && !/fetch|network|timeout|aborted/i.test(error.message) ? error.message : 'مساعد الطباعة غير متصل أو إذن الاتصال المحلي غير مفعّل.';
      if (!allowFallback) throw new Error(reason);
      if (window.confirm(`${reason}\nلم يُرسل أي طلب للطابعة. هل تريد فتح نافذة الطباعة الاعتيادية؟`)) return false;
      throw new Error('أُلغيت الطباعة قبل الإرسال.');
    }
    if (!canSend()) throw new Error('أُغلقت نافذة المستند قبل الإرسال.');
    await submit({id: crypto.randomUUID(), kind, printer: route.printer, copies: route.copies, ...snapshot}, settings.token);
    return true;
  } catch (error) {error.localPrintFailure = true; throw error;}
  finally {active.delete(kind);}
}
// The few legacy windows outside usePrint keep their existing rendering and
// cleanup; only the final destination is selected here.
export async function printPreparedWindow(win, kind, cleanup = () => {}) {
  try {
    if (await routePrintDocument(win.document, kind)) {cleanup(); return;}
    win.focus(); win.print();
  } catch (error) {cleanup(); window.alert(error.message || 'تعذرت الطباعة.');}
}
