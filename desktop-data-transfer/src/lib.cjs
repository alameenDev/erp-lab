'use strict';

const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const crypto = require('node:crypto');
const zlib = require('node:zlib');
const { Transform, Writable } = require('node:stream');
const { pipeline } = require('node:stream/promises');
const { spawn } = require('node:child_process');

class MigrationError extends Error {
  constructor(code, message) { super(message); this.name = 'MigrationError'; this.code = code; }
}
const fail = (code, message) => { throw new MigrationError(code, message); };
const isRecord = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const identifier = value => typeof value === 'string' && /^[A-Za-z_][A-Za-z0-9_]{0,63}$/.test(value);
const integer = value => Number.isSafeInteger(value) && value >= 0;

function keyBytes(value) {
  if (typeof value !== 'string' || !/^base64:[A-Za-z0-9+/]{43}=$/.test(value)) {
    fail('INVALID_SOURCE_KEY', 'مفتاح التطبيق المصدر يجب أن يكون مفتاح Laravel صالحاً بطول 32 بايت.');
  }
  const bytes = Buffer.from(value.slice(7), 'base64');
  if (bytes.length !== 32 || bytes.toString('base64') !== value.slice(7)) {
    fail('INVALID_SOURCE_KEY', 'مفتاح التطبيق المصدر غير صالح.');
  }
  return bytes;
}

function normalizeManifest(input) {
  if (!isRecord(input)) fail('INVALID_MANIFEST', 'ملف وصف البيانات غير صالح.');
  const sql = isRecord(input.sql) ? input.sql : input;
  if (typeof sql.sha256 !== 'string' || !/^[a-fA-F0-9]{64}$/.test(sql.sha256)
      || !integer(sql.uncompressedBytes) || sql.uncompressedBytes < 1) {
    fail('INVALID_MANIFEST', 'ملف الوصف لا يحتوي بصمة SQL وحجمه الصحيحين.');
  }
  const raw = isRecord(input.counts) ? input.counts : input.tables;
  if (!isRecord(raw) || Object.keys(raw).length === 0 || Object.keys(raw).length > 1000) {
    fail('INVALID_MANIFEST', 'ملف الوصف لا يحتوي قائمة الجداول وأعداد السجلات.');
  }
  const tables = Object.create(null);
  for (const [name, item] of Object.entries(raw)) {
    if (!identifier(name)) fail('INVALID_MANIFEST', 'اسم جدول غير صالح في ملف الوصف.');
    const rows = isRecord(item) ? (item.rows ?? item.count) : item;
    const detail = isRecord(input.tables?.[name]) ? input.tables[name] : {};
    let columns = isRecord(item) ? item.columns : undefined;
    columns ??= detail.columns ?? input.columns?.[name];
    if (!integer(rows)) fail('INVALID_MANIFEST', 'عدد سجلات غير صالح في ملف الوصف.');
    if (columns !== undefined && (!Array.isArray(columns) || columns.length === 0
        || !columns.every(identifier) || new Set(columns).size !== columns.length)) {
      fail('INVALID_MANIFEST', 'قائمة أعمدة غير صالحة في ملف الوصف.');
    }
    tables[name] = { rows, ...(columns ? { columns: [...columns] } : {}) };
  }
  if (!tables.users || !tables.migrations) {
    fail('INCOMPLETE_MANIFEST', 'التصدير يجب أن يتضمن جدول الحسابات وسجل تحديثات قاعدة البيانات.');
  }
  if (input.tableNames !== undefined) {
    if (!Array.isArray(input.tableNames)
        || [...input.tableNames].sort().join('\0') !== Object.keys(tables).sort().join('\0')) {
      fail('INVALID_MANIFEST', 'قائمة الجداول لا تطابق أعداد السجلات في ملف الوصف.');
    }
  }
  const encryptedColumns = input.encryptedColumns ?? [];
  if (!Array.isArray(encryptedColumns)) fail('INVALID_MANIFEST', 'وصف الأعمدة المشفرة غير صالح.');
  const seen = new Set();
  for (const item of encryptedColumns) {
    if (!isRecord(item) || !identifier(item.table) || !identifier(item.column)
        || !tables[item.table] || !integer(item.nonNullCount)
        || item.nonNullCount > tables[item.table].rows
        || seen.has(item.table + '.' + item.column)) {
      fail('INVALID_MANIFEST', 'وصف الأعمدة المشفرة غير صالح.');
    }
    if (tables[item.table].columns && !tables[item.table].columns.includes(item.column)) {
      fail('INVALID_MANIFEST', 'العمود المشفر غير موجود ضمن أعمدة الجدول.');
    }
    seen.add(item.table + '.' + item.column);
  }
  const rowCount = Object.values(tables).reduce((n, item) => n + item.rows, 0);
  if (!integer(rowCount)) fail('INVALID_MANIFEST', 'مجموع السجلات يتجاوز الحد المدعوم.');
  const warnings = input.warnings ?? [];
  if (!Array.isArray(warnings) || !warnings.every(value => typeof value === 'string' && /^[A-Z][A-Z0-9_]{0,79}$/.test(value))) {
    fail('INVALID_MANIFEST', 'رموز التنبيهات في ملف الوصف غير صالحة.');
  }
  return {
    sql: { sha256: sql.sha256.toLowerCase(), uncompressedBytes: sql.uncompressedBytes },
    tables, encryptedColumns: encryptedColumns.map(({ table, column, nonNullCount }) => ({ table, column, nonNullCount })),
    tableCount: Object.keys(tables).length, rowCount,
    source: isRecord(input.source) ? input.source : {}, warnings: [...new Set(warnings)],
  };
}

function normalizeConfig(input) {
  if (!isRecord(input)) fail('INVALID_CONFIG', 'إعدادات أداة النقل غير صالحة.');
  const sqlPath = input.sqlPath ?? input.sqlGzPath;
  if (input.sqlPath && input.sqlGzPath && input.sqlPath !== input.sqlGzPath) {
    fail('INVALID_CONFIG', 'حدد ملف SQL مضغوطاً واحداً فقط.');
  }
  for (const value of [input.installationDir, sqlPath, input.manifestPath, input.outputPath]) {
    if (typeof value !== 'string' || !value || !path.isAbsolute(value) || value.includes('\0')
        || value.split(/[\\/]/).includes('..') || /^(?:\\\\|\/\/)/.test(value)) {
      fail('INVALID_PATH', 'يجب استخدام مسارات محلية كاملة وصحيحة.');
    }
  }
  if (input.storageAppPath !== undefined && input.storageAppPath !== null && input.storageAppPath !== '') {
    const value = input.storageAppPath;
    if (typeof value !== 'string' || !path.isAbsolute(value) || value.includes('\0')
        || value.split(/[\\/]/).includes('..') || /^(?:\\\\|\/\/)/.test(value)) {
      fail('INVALID_PATH', 'مسار مجلد المرفقات غير صالح.');
    }
  }
  if (typeof input.password !== 'string' || input.password.length < 12 || input.password.length > 256) {
    fail('INVALID_PASSWORD', 'كلمة سر النسخة الاحتياطية يجب أن تكون بين 12 و256 حرفاً.');
  }
  const sourceAppKey = input.sourceAppKey || undefined;
  if (sourceAppKey) keyBytes(sourceAppKey).fill(0);
  if (path.extname(input.outputPath).toLowerCase() !== '.dlbackup') {
    fail('INVALID_OUTPUT', 'ملف الإخراج يجب أن يكون بصيغة .dlbackup.');
  }
  const outputName = path.basename(input.outputPath);
  if (/[\\/:\x00-\x1f]/.test(outputName) || /[. ]$/.test(outputName)
      || /^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i.test(outputName)) {
    fail('INVALID_OUTPUT', 'اسم ملف الإخراج غير صالح على Windows.');
  }
  return {
    installationDir: path.resolve(input.installationDir), sqlPath: path.resolve(sqlPath),
    manifestPath: path.resolve(input.manifestPath), outputPath: path.resolve(input.outputPath),
    storageAppPath: input.storageAppPath ? path.resolve(input.storageAppPath) : undefined,
    password: input.password, sourceAppKey,
  };
}

function within(parent, target) {
  const normalize = value => process.platform === 'win32' ? path.resolve(value).toLowerCase() : path.resolve(value);
  const relative = path.relative(normalize(parent), normalize(target));
  return relative === '' || (!relative.startsWith('..' + path.sep) && relative !== '..' && !path.isAbsolute(relative));
}

async function existingPathNoLinks(value, kind) {
  const absolute = path.resolve(value), parsed = path.parse(absolute);
  let current = parsed.root;
  for (const part of absolute.slice(parsed.root.length).split(path.sep).filter(Boolean)) {
    current = path.join(current, part);
    const stat = await fsp.lstat(current);
    if (stat.isSymbolicLink()) fail('LINK_NOT_ALLOWED', 'لا تدعم أداة النقل الروابط الرمزية أو وصلات المجلدات.');
  }
  const stat = await fsp.lstat(absolute);
  if ((kind === 'file' && !stat.isFile()) || (kind === 'directory' && !stat.isDirectory())) {
    fail('INVALID_PATH_TYPE', 'نوع الملف أو المجلد المحدد غير صحيح.');
  }
  return { path: await fsp.realpath(absolute), stat };
}

function createEphemeralVault() {
  const key = crypto.randomBytes(32);
  let destroyed = false;
  return {
    encrypt(value) {
      if (destroyed) fail('VAULT_CLOSED', 'أغلقت مفاتيح المرحلة المؤقتة.');
      const iv = crypto.randomBytes(12), cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
      const encrypted = Buffer.concat([cipher.update(String(value), 'utf8'), cipher.final()]);
      return Buffer.concat([Buffer.from('DLMV01'), iv, cipher.getAuthTag(), encrypted]);
    },
    decrypt(value) {
      if (destroyed) fail('VAULT_CLOSED', 'أغلقت مفاتيح المرحلة المؤقتة.');
      if (!Buffer.isBuffer(value) || value.length < 34 || value.subarray(0, 6).toString() !== 'DLMV01') {
        fail('INVALID_VAULT', 'ملف مفاتيح المرحلة غير صالح.');
      }
      try {
        const cipher = crypto.createDecipheriv('aes-256-gcm', key, value.subarray(6, 18));
        cipher.setAuthTag(value.subarray(18, 34));
        return Buffer.concat([cipher.update(value.subarray(34)), cipher.final()]).toString('utf8');
      } catch { fail('INVALID_VAULT', 'تعذر التحقق من مفاتيح المرحلة.'); }
    },
    destroy() { key.fill(0); destroyed = true; },
  };
}

function decompressor(file) {
  const extension = path.extname(file).toLowerCase();
  if (extension === '.gz') return zlib.createGunzip();
  if (extension === '.br') return zlib.createBrotliDecompress();
  if (extension === '.sql') return new Transform({ transform(chunk, _encoding, done) { done(null, chunk); } });
  fail('UNSUPPORTED_SQL_FORMAT', 'يدعم ملف SQL أو SQL المضغوط بصيغة gz أو br فقط.');
}

function hashMeter(expected, onProgress = () => {}) {
  const hash = crypto.createHash('sha256');
  let bytes = 0, last = 0;
  const stream = new Transform({
    transform(chunk, _encoding, done) {
      bytes += chunk.length;
      if (bytes > expected.uncompressedBytes) return done(new MigrationError('SOURCE_SIZE_MISMATCH', 'حجم SQL المفكوك لا يطابق النسخة المعتمدة.'));
      hash.update(chunk);
      if (Date.now() - last >= 500) { last = Date.now(); onProgress(bytes, expected.uncompressedBytes); }
      done(null, chunk);
    },
  });
  return {
    stream,
    finish() {
      const digest = hash.digest('hex');
      if (bytes !== expected.uncompressedBytes || digest !== expected.sha256) {
        fail('SOURCE_HASH_MISMATCH', 'ملف SQL لا يطابق البصمة المعتمدة؛ لم تعتمد بيانات النقل.');
      }
      onProgress(bytes, expected.uncompressedBytes);
      return { bytes, sha256: digest };
    },
  };
}

async function verifySqlSource(file, expected, onProgress, signal) {
  const meter = hashMeter(expected, onProgress);
  try {
    await pipeline(fs.createReadStream(file), decompressor(file), meter.stream,
      new Writable({ write(_chunk, _encoding, done) { done(); } }), { signal });
    return meter.finish();
  } catch (error) {
    if (error instanceof MigrationError) throw error;
    if (signal?.aborted) fail('CANCELLED', 'أُلغيت عملية النقل.');
    fail('SOURCE_READ_FAILED', 'تعذر فك ملف SQL أو قراءته؛ تحقق من اكتمال الملف.');
  }
}

async function runProcess(file, args, { env, cwd, input, inputStreams, timeout = 120000,
  signal, outputLimit = 1024 * 1024, errorCode = 'PROCESS_FAILED', acceptedExitCodes = [0] } = {}) {
  if (signal?.aborted) fail('CANCELLED', 'أُلغيت عملية النقل.');
  let child, timer, timeoutHit = false, aborted = false, stdoutBytes = 0, outputTooLarge = false;
  const stdout = [];
  const close = new Promise((resolve, reject) => {
    try { child = spawn(file, args, { env: env ?? process.env, cwd, shell: false, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] }); }
    catch { reject(new MigrationError(errorCode, 'تعذر تشغيل مكوّن النقل المحلي.')); return; }
    child.stdout.on('data', chunk => {
      stdoutBytes += chunk.length;
      if (stdoutBytes <= outputLimit) stdout.push(chunk);
      else { outputTooLarge = true; child.kill(); }
    });
    // Source SQL, rows, paths and credentials can appear in stderr. Never expose it.
    child.stderr.on('data', () => {});
    child.on('error', () => reject(new MigrationError(errorCode, 'تعذر تشغيل مكوّن النقل المحلي.')));
    child.on('close', (code, terminationSignal) => {
      if (timeoutHit) reject(new MigrationError('PROCESS_TIMEOUT', 'استغرق مكوّن النقل وقتاً أطول من الحد المسموح.'));
      else if (aborted) reject(new MigrationError('CANCELLED', 'أُلغيت عملية النقل.'));
      else if (outputTooLarge) reject(new MigrationError('PROCESS_OUTPUT_LIMIT', 'توقف التحقق لأن حجم نتيجة المكوّن غير متوقع.'));
      else if (!acceptedExitCodes.includes(code) || terminationSignal) reject(new MigrationError(errorCode, 'لم يكتمل مكوّن النقل المحلي بنجاح؛ لم تعتمد البيانات.'));
      else resolve(Buffer.concat(stdout).toString('utf8'));
    });
    timer = setTimeout(() => { timeoutHit = true; child.kill(); }, timeout);
  });
  // Attach a handler immediately so an early spawn failure is not unhandled.
  close.catch(() => {});
  const abort = () => { aborted = true; child?.kill(); };
  signal?.addEventListener('abort', abort, { once: true });
  try {
    if (!child) return await close;
    let feeding;
    if (inputStreams) feeding = pipeline(...inputStreams, child.stdin, ...(signal ? [{ signal }] : []));
    else feeding = new Promise((resolve, reject) => {
      child.stdin.once('error', reject);
      child.stdin.end(input, error => error ? reject(error) : resolve());
    });
    feeding.catch(() => { child?.kill(); });
    const [sent, exited] = await Promise.allSettled([feeding, close]);
    if (exited.status === 'rejected') throw exited.reason;
    if (sent.status === 'rejected') {
      if (sent.reason instanceof MigrationError) throw sent.reason;
      fail(errorCode, 'تعذر تمرير ملف النقل إلى المكوّن المحلي.');
    }
    return exited.value;
  } finally {
    clearTimeout(timer);
    signal?.removeEventListener('abort', abort);
  }
}

async function copyStorageTree(source, destination, onProgress = () => {}) {
  const src = (await existingPathNoLinks(source, 'directory')).path;
  if (within(src, destination) || within(destination, src)) fail('PATH_OVERLAP', 'مجلد المرفقات يتداخل مع مجلد المرحلة.');
  let files = 0, bytes = 0;
  const visited = new Set();
  async function walk(from, to, relative) {
    const actual = await existingPathNoLinks(from, 'directory');
    if (!within(src, actual.path)) fail('STORAGE_ESCAPE', 'مسار مرفق خارج المجلد المحدد.');
    await fsp.mkdir(to, { recursive: true });
    for (const entry of await fsp.readdir(from, { withFileTypes: true })) {
      if (entry.name === '.' || entry.name === '..' || /[\\/:\x00-\x1f]/.test(entry.name)) {
        fail('INVALID_STORAGE_ENTRY', 'اسم مرفق غير صالح للنقل إلى Windows.');
      }
      if (/[. ]$/.test(entry.name) || /^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i.test(entry.name)) {
        fail('INVALID_STORAGE_ENTRY', 'اسم مرفق محجوز أو غير متوافق مع Windows.');
      }
      const rel = path.join(relative, entry.name), folded = rel.toLowerCase();
      if (visited.has(folded)) fail('STORAGE_NAME_COLLISION', 'يوجد تعارض بين أسماء ملفات المرفقات عند النقل إلى Windows.');
      visited.add(folded);
      const current = path.join(from, entry.name), target = path.join(to, entry.name);
      const stat = await fsp.lstat(current);
      if (stat.isSymbolicLink()) fail('LINK_NOT_ALLOWED', 'لا يمكن نسخ مرفق يشير إلى رابط رمزي.');
      if (stat.isDirectory()) await walk(current, target, rel);
      else if (stat.isFile()) {
        const validated = await existingPathNoLinks(current, 'file');
        if (!within(src, validated.path)) fail('STORAGE_ESCAPE', 'مسار مرفق خارج المجلد المحدد.');
        const handle = await fsp.open(current, fs.constants.O_RDONLY | (fs.constants.O_NOFOLLOW ?? 0));
        try {
          const opened = await handle.stat();
          if (!opened.isFile() || opened.dev !== stat.dev || opened.ino !== stat.ino
              || opened.size !== stat.size || opened.mtimeMs !== stat.mtimeMs) {
            fail('STORAGE_CHANGED', 'تغير أحد المرفقات أثناء النسخ؛ أعد المحاولة بنسخة ثابتة.');
          }
          // Both descriptors have one owner. A replaced path cannot redirect
          // reads, and stream destruction cannot accidentally close/reuse a fd.
          const output = await fsp.open(target, 'wx', 0o600);
          try {
            const buffer = Buffer.allocUnsafe(1024 * 1024);
            for (;;) {
              const { bytesRead } = await handle.read(buffer, 0, buffer.length, null);
              if (!bytesRead) break;
              let offset = 0;
              while (offset < bytesRead) {
                const { bytesWritten } = await output.write(buffer, offset, bytesRead - offset, null);
                if (!bytesWritten) throw new Error('Write made no progress.');
                offset += bytesWritten;
              }
            }
          } finally { await output.close(); }
          const after = await fsp.lstat(current);
          if (after.isSymbolicLink() || after.dev !== stat.dev || after.ino !== stat.ino
              || after.size !== stat.size || after.mtimeMs !== stat.mtimeMs) {
            fail('STORAGE_CHANGED', 'تغير أحد المرفقات أثناء النسخ؛ أعد المحاولة بنسخة ثابتة.');
          }
        } finally { await handle.close(); }
        files++; bytes += stat.size;
        onProgress({ files, bytes });
      } else fail('INVALID_STORAGE_ENTRY', 'يوجد نوع ملف غير مدعوم في مجلد المرفقات.');
    }
  }
  await walk(src, destination, '');
  return { files, bytes };
}

async function publishExclusive(source, target) {
  let handle, ownedStat;
  try {
    handle = await fsp.open(target, 'wx', 0o600);
    ownedStat = await handle.stat();
    for await (const chunk of fs.createReadStream(source)) {
      let offset = 0;
      while (offset < chunk.length) {
        const { bytesWritten } = await handle.write(chunk, offset, chunk.length - offset, null);
        if (!bytesWritten) throw new Error('Write made no progress.');
        offset += bytesWritten;
      }
    }
    await handle.sync();
    await handle.close(); handle = null;
  } catch (error) {
    try { await handle?.close(); } catch {}
    if (ownedStat) {
      try {
        const now = await fsp.lstat(target);
        if (!now.isSymbolicLink() && now.dev === ownedStat.dev && now.ino === ownedStat.ino) await fsp.unlink(target);
      } catch {}
    }
    if (error.code === 'EEXIST') fail('OUTPUT_EXISTS', 'يوجد ملف بهذا الاسم؛ اختر اسماً جديداً ولا تستبدل نسخة سابقة.');
    fail('OUTPUT_WRITE_FAILED', 'تعذر حفظ النسخة الناتجة في المكان المحدد.');
  }
}

module.exports = {
  MigrationError, fail, isRecord, identifier, keyBytes, normalizeManifest, normalizeConfig,
  within, existingPathNoLinks, createEphemeralVault, decompressor, hashMeter,
  verifySqlSource, runProcess, copyStorageTree, publishExclusive,
};
