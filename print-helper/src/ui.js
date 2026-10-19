const api = window.digitalLabPrint, $ = id => document.getElementById(id);
const run = fn => async () => {try {$('error').textContent = ''; await fn();} catch {$('error').textContent = 'تعذر تنفيذ الطلب. أعد فتح المساعد وحاول مجدداً.';}};
async function refresh() {
  const status = await api.status(); $('version').textContent = 'v' + status.version;
  $('startup').checked = status.startup;
  $('code').textContent = status.code && status.code.expires > Date.now() ? status.code.value : '—';
  $('clients').replaceChildren();
  for (const client of status.clients) {
    const row = document.createElement('div'), label = document.createElement('span'), button = document.createElement('button');
    label.textContent = client.origin; label.dir = 'ltr'; button.textContent = 'إلغاء الربط';
    button.onclick = run(async () => {await api.revoke(client.origin); await refresh();});
    row.append(label, button); $('clients').append(row);
  }
  if (!status.clients.length) $('clients').textContent = 'لا توجد مواقع مرتبطة بعد.';
}
$('new-code').onclick = run(async () => {await api.code(); await refresh();});
$('startup').onchange = run(async () => {await api.startup($('startup').checked); await refresh();});
$('refresh').onclick = run(async () => {
  const printers = await api.printers(); $('printers').replaceChildren();
  for (const printer of printers) {const li = document.createElement('li');li.textContent=printer.displayName;$('printers').append(li);}
  if (!printers.length) $('printers').textContent='لا توجد طابعات. أضف تعريف الطابعة في Windows أولاً.';
});
window.addEventListener('focus', run(refresh));
setInterval(run(refresh), 15000); run(refresh)();
