const http = require('node:http');
const {validateJob} = require('./validation.cjs');
const PORT = 17891;
const MAX_BYTES = 128 * 1024 * 1024;
const publicJob = job => ({id: job.id, status: job.status, ...(job.error ? {error: job.error} : {})});
function validOrigin(origin, development = false) {
  try {
    const u = new URL(origin);
    return u.origin === origin && !u.username && !u.password && (u.protocol === 'https:' ||
      development && u.protocol === 'http:' && ['localhost', '127.0.0.1'].includes(u.hostname));
  } catch {return false;}
}
function readJson(req) {
  return new Promise((resolve, reject) => {
    if (req.headers['content-type'] !== 'application/json') return reject(Object.assign(new Error('JSON_REQUIRED'), {status: 415}));
    let size = 0; const chunks = [];
    req.on('data', chunk => {
      size += chunk.length;
      if (size > MAX_BYTES) {reject(Object.assign(new Error('DOCUMENT_TOO_LARGE'), {status: 413})); req.destroy();}
      else chunks.push(chunk);
    });
    req.on('end', () => {try {resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));} catch {reject(Object.assign(new Error('INVALID_JSON'), {status: 400}));}});
    req.on('error', reject);
  });
}
function createServer({state, approvePair, listPrinters, print, development = false, port = PORT}) {
  let pending = 0, chain = Promise.resolve(), pairing = false;
  const server = http.createServer(async (req, res) => {
    const send = (status, value) => {if (!res.destroyed) {res.writeHead(status, {'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff'}); res.end(JSON.stringify(value));}};
    const origin = req.headers.origin;
    if (req.headers.host !== `127.0.0.1:${server.address()?.port || port}` || !validOrigin(origin, development)) return send(403, {error: 'ORIGIN_DENIED'});
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
    if (req.method === 'OPTIONS') {
      res.setHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE');
      res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
      res.setHeader('Access-Control-Allow-Private-Network', 'true');
      res.setHeader('Access-Control-Max-Age', '600');
      return send(204, {});
    }
    try {
      if (req.method === 'GET' && req.url === '/v1/health') return send(200, {name: 'Digital Lab Print', protocol: 1, version: '1.0.0'});
      if (req.method === 'POST' && req.url === '/v1/pair') {
        const body = await readJson(req);
        if (pairing || !state.consumeCode(body.code)) return send(403, {error: 'رمز الربط غير صحيح أو منتهي. أنشئ رمزاً جديداً من المساعد.'});
        pairing = true;
        try {
          if (!await approvePair(origin)) return send(403, {error: 'تم رفض ربط هذا الموقع.'});
          return send(200, {token: state.pair(origin)});
        } finally {pairing = false;}
      }
      const token = (req.headers.authorization || '').replace(/^Bearer /, '');
      if (!state.authorized(origin, token)) return send(401, {error: 'أعد ربط هذه الحاسبة من إعدادات الطابعات.'});
      if (req.method === 'DELETE' && req.url === '/v1/pair') {state.revoke(origin); return send(200, {ok: true});}
      if (req.method === 'GET' && req.url === '/v1/printers') {
        const printers = await listPrinters();
        return send(200, {printers: printers.map(p => ({name: p.name, displayName: p.displayName || p.name, isDefault: !!p.isDefault}))});
      }
      const match = /^\/v1\/jobs\/([a-f0-9-]{36})$/.exec(req.url);
      if (req.method === 'GET' && match) {
        const job = state.job(origin, match[1]);
        return send(job ? 200 : 404, job ? publicJob(job) : {error: 'JOB_NOT_FOUND'});
      }
      if (req.method === 'POST' && req.url === '/v1/jobs') {
        const input = validateJob(await readJson(req));
        if (pending >= 5) return send(429, {error: 'قائمة الطباعة ممتلئة. انتظر اكتمال الطلبات الحالية.'});
        const {job, fresh} = state.createJob(origin, input);
        if (fresh) {
          pending++;
          chain = chain.then(async () => {
            try {
              if (!state.authorized(origin, token)) throw new Error('تم إلغاء ربط الموقع قبل الطباعة.');
              state.update(job, 'rendering');
              await print(input, () => state.update(job, 'submitting'));
              state.update(job, 'submitted');
            } catch (error) {
              state.update(job, job.status === 'submitting' ? 'unknown' : 'failed', error.safeMessage || 'تعذرت الطباعة. تحقق من الطابعة وقائمة الطباعة في Windows قبل إعادة المحاولة.');
            } finally {pending--;}
          }).catch(() => {});
        }
        return send(202, publicJob(job));
      }
      return send(404, {error: 'NOT_FOUND'});
    } catch (error) {
      send(error.status || (error.message === 'JOB_CONFLICT' ? 409 : 400), {error: error.safeMessage || error.message || 'PRINT_HELPER_ERROR'});
    }
  });
  server.requestTimeout = 120000; server.headersTimeout = 15000;
  return server;
}
module.exports = {createServer, validOrigin, PORT, MAX_BYTES};
