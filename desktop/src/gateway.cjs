const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const {spawn} = require('node:child_process');
const {PORT, ORIGIN} = require('./constants.cjs');
const MIME = {'.html':'text/html; charset=utf-8','.js':'text/javascript','.mjs':'text/javascript','.css':'text/css','.json':'application/json','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.gif':'image/gif','.webp':'image/webp','.svg':'image/svg+xml','.ico':'image/x-icon','.woff2':'font/woff2','.woff':'font/woff','.ttf':'font/ttf','.pdf':'application/pdf','.xlsx':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','.csv':'text/csv','.txt':'text/plain'};
function validPath(raw) {
  try {const value = decodeURIComponent(raw.split('?')[0]);return !value.includes('\\') && !/[\x00-\x1f]/.test(value) && !value.split('/').some(p => p === '..' || p.startsWith('.')) && !/%(?:2e|2f|5c|00)/i.test(value);} catch {return false;}
}
function createGateway(runtime, secret, port = PORT, licenseCheck = () => {}) {
  const origin = `http://127.0.0.1:${port}`;
  const children = new Set();let paused = false, slots = 0;const waiting = [];
  const admit = async () => {if (slots < 6) {slots++;return;}if(waiting.length>=64)throw new Error('BUSY');await new Promise(resolve=>waiting.push(resolve));};
  const release = () => {if(waiting.length)waiting.shift()();else slots--;};
  const fail = (res, status, message, maintenance = false) => {if (!res.headersSent) res.writeHead(status, {'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store'});res.end(JSON.stringify({message, ...(maintenance ? {desktop_maintenance:true} : {})}));};
  const server = http.createServer(async (req, res) => {
    if (req.headers.host !== `127.0.0.1:${port}` || !validPath(req.url)) return fail(res, 403, 'Forbidden');
    const url = new URL(req.url, origin);
    const bridge = req.method === 'POST' && /^\/api\/device\/bridge\/(heartbeat|results)$/.test(url.pathname);
    const value = req.headers['x-digital-lab-desktop'];
    const authorized = typeof value === 'string' && Buffer.byteLength(value) === Buffer.byteLength(secret) && crypto.timingSafeEqual(Buffer.from(value), Buffer.from(secret));
    if (req.headers.origin && req.headers.origin !== origin) return fail(res, 403, 'Origin denied');
    if (!authorized && !bridge) return fail(res, 403, 'Open Digital Lab Desktop');
    try {licenseCheck();} catch(e) {return fail(res, 402, e.message);}
    if (paused) return fail(res, 503, 'جارٍ حفظ نسخة احتياطية. حاول بعد قليل.', true);
    if (url.pathname.startsWith('/api/') || url.pathname === '/up') {
      try {await admit();} catch {return fail(res,503,'الخدمة مشغولة. حاول بعد قليل.');}
      if(paused){release();return fail(res,503,'الخدمة متوقفة مؤقتاً للنسخ.',true);}
      const chunks = [];let size = 0;
      try {for await (const b of req) {size += b.length;if (size > 128*1024*1024) {release();return fail(res, 413, 'الملف كبير جداً.');}chunks.push(b);}} catch {release();return;}
      if (paused) {release();return fail(res, 503, 'الخدمة متوقفة مؤقتاً للنسخ.',true);}
      const headers = {};
      for (const [k,v] of Object.entries(req.headers)) if (['accept','authorization','cookie','user-agent','origin','referer','x-requested-with','x-device-token','x-csrf-token','x-audit-action-id','x-audit-label','x-audit-page'].includes(k)) headers['HTTP_'+k.toUpperCase().replaceAll('-','_')] = String(v);
      const env = {...runtime.env, ...headers, REDIRECT_STATUS:'200', GATEWAY_INTERFACE:'CGI/1.1', SERVER_PROTOCOL:'HTTP/1.1', SERVER_NAME:'127.0.0.1', SERVER_PORT:String(port), HTTP_HOST:`127.0.0.1:${port}`, REMOTE_ADDR:'127.0.0.1', REQUEST_METHOD:req.method, REQUEST_URI:req.url, QUERY_STRING:url.search.slice(1), SCRIPT_NAME:'/index.php', SCRIPT_FILENAME:path.join(runtime.p.bundle, 'desktop-php/http.php'), DOCUMENT_ROOT:path.join(runtime.p.backend, 'public'), CONTENT_TYPE:req.headers['content-type'] || '', CONTENT_LENGTH:String(size)};
      const child = spawn(runtime.p.cgi, runtime.phpArgs(), {cwd:runtime.p.backend,env,windowsHide:true,stdio:['pipe','pipe','ignore']});children.add(child);
      let head = Buffer.alloc(0), sent = false;
      const timer = setTimeout(() => {child.kill();fail(res, 504, 'انتهت مهلة الطلب. تحقق من حفظ العملية قبل تكرارها.');}, 120000);
      child.stdin.on('error',()=>{});
      child.on('error',()=>fail(res, 500, 'تعذر تشغيل الخدمة المحلية.'));
      child.stdout.on('data', b => {
        if (sent) {res.write(b);return;}
        head = Buffer.concat([head,b]);let index = head.indexOf('\r\n\r\n'), skip = 4;
        if (index < 0) {index = head.indexOf('\n\n');skip = 2;}
        if (index < 0) {if (head.length > 65536) {child.kill();fail(res,502,'Invalid response');}return;}
        const out = {'Cache-Control':'no-store','X-Content-Type-Options':'nosniff'};let status = 200;
        for (const line of head.subarray(0,index).toString().split(/\r?\n/)) {
          const at = line.indexOf(':');if (at < 1) continue;const key = line.slice(0,at).toLowerCase(), value = line.slice(at+1).trim();
          if (key === 'status') status = Number.parseInt(value,10);
          else if (!['connection','transfer-encoding','content-length','server','x-powered-by','strict-transport-security'].includes(key)) {if (key === 'set-cookie') (out[key] ||= []).push(value);else out[key] = value;}
        }
        if (out.location && status === 200) status = 302;
        res.writeHead(status,out);sent = true;res.write(head.subarray(index+skip));head = null;
      });
      child.on('close',()=>{clearTimeout(timer);children.delete(child);release();sent ? res.end() : fail(res,500,'تعذر تجهيز الاستجابة المحلية.');});
      res.on('close',()=>{if (!res.writableEnded) child.kill();});
      child.stdin.end(Buffer.concat(chunks));return;
    }
    if (!['GET','HEAD'].includes(req.method)) return fail(res,405,'Method not allowed');
    try {
      const pathname = decodeURIComponent(url.pathname);
      let root = runtime.p.frontend, relative = pathname;
      if (pathname.startsWith('/storage/')) {root = path.join(runtime.p.storage,'app/public');relative = pathname.slice(9);}
      let file = path.resolve(root, '.' + (relative.startsWith('/') ? relative : '/'+relative));
      if (!file.startsWith(path.resolve(root)+path.sep)) file = path.join(root,'index.html');
      if (!fs.existsSync(file) && root === runtime.p.frontend) {
        const publicFile = path.resolve(runtime.p.backend,'public','.'+pathname);
        if (publicFile.startsWith(path.resolve(runtime.p.backend,'public')+path.sep) && fs.existsSync(publicFile)) {root = path.join(runtime.p.backend,'public');file = publicFile;}
        else if (!path.extname(pathname)) file = path.join(root,'index.html');
      }
      const ext = path.extname(file).toLowerCase();
      if (!MIME[ext] || !fs.statSync(file).isFile() || !fs.realpathSync(file).startsWith(fs.realpathSync(root)+path.sep)) return fail(res,404,'Not found');
      res.writeHead(200, {'Content-Type':MIME[ext],'Content-Length':fs.statSync(file).size,'Cache-Control':'no-store','X-Content-Type-Options':'nosniff', ...(ext === '.svg' ? {'Content-Security-Policy':"default-src 'none'; style-src 'unsafe-inline'; sandbox"} : {})});
      if (req.method === 'HEAD') res.end();else fs.createReadStream(file).pipe(res);
    } catch {fail(res,404,'Not found');}
  });
  server.requestTimeout = 150000;server.headersTimeout = 15000;
  return {server, async pause() {paused = true;const until = Date.now()+125000;while(slots && Date.now()<until) await new Promise(r=>setTimeout(r,100));if(slots) throw new Error('عمليات قيد التنفيذ؛ حاول النسخ بعد اكتمالها.');}, resume(){paused=false;}, async close(){paused=true;await new Promise(r=>server.close(r));}};
}
module.exports = {createGateway, validPath};
