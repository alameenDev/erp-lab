const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const hash = value => crypto.createHash('sha256').update(value).digest('hex');

class State {
  constructor(file, now = Date.now) {
    this.file = file; this.now = now; this.code = null; this.attempts = 0;
    this.data = {clients: [], jobs: {}};
    if (fs.existsSync(file)) this.data = JSON.parse(fs.readFileSync(file, 'utf8'));
    // Never repeat a potentially spooled job after a restart. No document data
    // or patient names are stored in the ledger, only opaque IDs and outcomes.
    for (const job of Object.values(this.data.jobs)) {
      if (job.status === 'submitting') {job.status = 'unknown'; job.error = 'انقطع المساعد أثناء الإرسال. تحقق من قائمة الطباعة قبل إعادة المحاولة.';}
      else if (['queued', 'rendering'].includes(job.status)) {job.status = 'failed'; job.error = 'توقف المساعد قبل إرسال المستند للطابعة.';}
    }
    this.save();
  }
  save() {
    fs.mkdirSync(path.dirname(this.file), {recursive: true});
    fs.writeFileSync(this.file + '.tmp', JSON.stringify(this.data), {mode: 0o600});
    fs.renameSync(this.file + '.tmp', this.file);
  }
  newCode() {
    this.code = {value: crypto.randomInt(10000000, 100000000).toString(), expires: this.now() + 300000};
    this.attempts = 0; return this.code;
  }
  consumeCode(value) {
    if (!this.code || this.code.expires < this.now() || ++this.attempts > 5 || this.code.value !== value) return false;
    this.code = null; return true;
  }
  pair(origin) {
    const token = crypto.randomBytes(32).toString('hex');
    this.data.clients = this.data.clients.filter(c => c.origin !== origin);
    this.data.clients.push({origin, hash: hash(token), created: this.now()});
    this.save(); return token;
  }
  authorized(origin, token) {
    if (typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)) return false;
    const client = this.data.clients.find(c => c.origin === origin);
    return !!client && crypto.timingSafeEqual(Buffer.from(client.hash, 'hex'), Buffer.from(hash(token), 'hex'));
  }
  revoke(origin) {this.data.clients = this.data.clients.filter(c => c.origin !== origin); this.save();}
  job(origin, id) {const job = this.data.jobs[id]; return job?.origin === origin ? job : null;}
  createJob(origin, input) {
    const fingerprint = hash(JSON.stringify(input));
    const existing = this.data.jobs[input.id];
    if (existing) {
      if (existing.origin !== origin || existing.fingerprint !== fingerprint) throw new Error('JOB_CONFLICT');
      return {job: existing, fresh: false};
    }
    for (const [id, job] of Object.entries(this.data.jobs)) {
      if (this.now() - job.created > 7 * 86400000 && ['submitted', 'failed'].includes(job.status)) delete this.data.jobs[id];
    }
    if (Object.keys(this.data.jobs).length >= 10000) throw new Error('LEDGER_FULL');
    const job = {id: input.id, origin, fingerprint, created: this.now(), status: 'queued'};
    this.data.jobs[input.id] = job; this.save(); return {job, fresh: true};
  }
  update(job, status, error) {job.status = status; if (error) job.error = error; this.save();}
}
module.exports = {State};
