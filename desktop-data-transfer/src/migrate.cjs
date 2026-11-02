'use strict';

const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');
const crypto = require('node:crypto');
const { Runtime } = require('./vendor/runtime.cjs');
const { paths } = require('./vendor/constants.cjs');
const { writeBackup, prepareRestore, DB_VERSION } = require('./vendor/backup.cjs');
const { postprocessImported } = require('./postprocess.cjs');
const {
  MigrationError, fail, normalizeManifest, normalizeConfig, within, existingPathNoLinks,
  createEphemeralVault, decompressor, hashMeter, verifySqlSource, runProcess,
  copyStorageTree, publishExclusive, isRecord,
} = require('./lib.cjs');

const VALIDATOR = path.join(__dirname, 'validate_import.php');
const MAX_MANIFEST_BYTES = 8 * 1024 * 1024;
const MESSAGES = {
  SOURCE_KEY_REQUIRED: 'تحتوي النسخة على إعدادات مشفرة؛ أدخل APP_KEY الأصلي محلياً لإكمال النقل.',
  ENCRYPTED_DATA_INVALID: 'لم ينجح التحقق من الإعدادات المشفرة؛ تأكد من APP_KEY الأصلي. لم تعتمد البيانات.',
  ENCRYPTED_COLUMN_UNDECLARED: 'يوجد حقل مشفر غير موصوف في حزمة النقل؛ يجب مراجعة ملف الوصف.',
  ADMIN_MISSING: 'لا يوجد حساب مدير صالح في البيانات المستوردة.',
  NO_USABLE_ADMIN: 'حسابات المدير الموجودة غير مفعلة أو غير مؤكدة البريد؛ لم تغير الأداة حالتها.',
  TABLE_SET_MISMATCH: 'جداول البيانات المستوردة لا تطابق وصف النسخة.',
  COLUMN_SET_MISMATCH: 'أعمدة البيانات المستوردة لا تطابق وصف النسخة.',
  ROW_COUNT_MISMATCH: 'عدد السجلات المستوردة لا يطابق النسخة المعتمدة.',
  ORPHAN_REFERENCE: 'توجد علاقات غير مكتملة بين السجلات؛ لم تعتمد النسخة.',
};

function publicError(error) {
  return error instanceof MigrationError
    ? { code: error.code, message: error.message }
    : { code: 'MIGRATION_FAILED', message: 'تعذر إكمال تحويل البيانات؛ لم تستبدل الأداة بيانات برنامجك الحالي.' };
}

async function readManifest(file) {
  const checked = await existingPathNoLinks(file, 'file');
  if (checked.stat.size > MAX_MANIFEST_BYTES) fail('INVALID_MANIFEST', 'ملف وصف البيانات أكبر من الحد المسموح.');
  let raw;
  try { raw = JSON.parse(await fsp.readFile(checked.path, 'utf8')); }
  catch { fail('INVALID_MANIFEST', 'تعذر قراءة ملف وصف البيانات.'); }
  return { raw, normalized: normalizeManifest(raw), path: checked.path };
}

async function validatePaths(config) {
  const installation = await existingPathNoLinks(config.installationDir, 'directory');
  const source = await existingPathNoLinks(config.sqlPath, 'file');
  const description = await existingPathNoLinks(config.manifestPath, 'file');
  const outputParent = await existingPathNoLinks(path.dirname(config.outputPath), 'directory');
  const outputPath = path.join(outputParent.path, path.basename(config.outputPath));
  if (within(installation.path, outputPath) || outputPath === source.path || outputPath === description.path) {
    fail('PATH_OVERLAP', 'اختر مكان إخراج منفصلاً عن البرنامج وملفات المصدر.');
  }
  if (process.env.APPDATA && within(path.join(process.env.APPDATA, 'DigitalLabDesktopV1'), outputPath)) {
    fail('LIVE_DATA_PATH', 'لا يمكن حفظ ملف النقل داخل مجلد بيانات البرنامج الحالي.');
  }
  try { await fsp.lstat(outputPath); fail('OUTPUT_EXISTS', 'يوجد ملف إخراج بهذا الاسم؛ اختر اسماً جديداً.'); }
  catch (error) { if (error.code !== 'ENOENT') throw error; }
  let storage;
  if (config.storageAppPath) {
    storage = await existingPathNoLinks(config.storageAppPath, 'directory');
    if (within(storage.path, outputPath)) fail('PATH_OVERLAP', 'اختر مكان إخراج خارج مجلد المرفقات المصدر.');
  }
  return { ...config, installationDir: installation.path, sqlPath: source.path,
    manifestPath: description.path, outputPath, storageAppPath: storage?.path };
}

async function validateInstallation(installationDir, signal) {
  const bundle = (await existingPathNoLinks(path.join(installationDir, 'resources', 'bundle'), 'directory')).path;
  const required = ['php/php.exe', 'php/php-cgi.exe', 'php/php.ini', 'mariadb/bin/mariadbd.exe',
    'mariadb/bin/mariadb.exe', 'mariadb/bin/mariadb-admin.exe', 'mariadb/bin/mariadb-install-db.exe',
    'backend/vendor/autoload.php', 'desktop-php/bootstrap.php', 'desktop-php/status.php',
    'desktop-php/console.php', 'build.json'];
  for (const file of required) await existingPathNoLinks(path.join(bundle, file), 'file');
  let build;
  try { build = JSON.parse(await fsp.readFile(path.join(bundle, 'build.json'), 'utf8')); }
  catch { fail('INCOMPATIBLE_DESKTOP', 'تعذر التحقق من إصدار نسخة Desktop المثبتة.'); }
  if (build.version !== '1.0.0' || build.mariadb !== DB_VERSION || build.origin !== 'http://127.0.0.1:17892') {
    fail('INCOMPATIBLE_DESKTOP', 'هذه الأداة مخصصة لنسخة Desktop V1 وقاعدة MariaDB المطابقة لها.');
  }
  const version = await runProcess(path.join(bundle, 'mariadb/bin/mariadbd.exe'), ['--no-defaults', '--version'],
    { signal, timeout: 15000, outputLimit: 8192, errorCode: 'DATABASE_VERSION_CHECK_FAILED' });
  if (!new RegExp('\\b' + DB_VERSION.replaceAll('.', '\\.') + '(?:[-\\s]|$)').test(version)
      || !version.toLowerCase().includes('mariadb')) {
    fail('DATABASE_VERSION_MISMATCH', 'إصدار محرك قاعدة البيانات لا يطابق صيغة النسخة الاحتياطية.');
  }
  return { bundle, build };
}

function assertCompatibleSchema(required, actual) {
  if (!isRecord(required) || !isRecord(actual)) fail('INVALID_SCHEMA', 'تعذر التحقق من مخطط قاعدة البيانات.');
  for (const [table, columns] of Object.entries(required)) {
    if (!Array.isArray(actual[table]) || !columns.every(column => actual[table].includes(column))) {
      fail('DESKTOP_SCHEMA_MISMATCH', 'نسخة البيانات لا تحتوي الجداول والأعمدة التي تحتاجها نسخة Desktop الحالية.');
    }
  }
}

async function runPhp(runtime, scriptFile, payload, signal) {
  const checked = await existingPathNoLinks(scriptFile, 'file');
  if (!within(__dirname, checked.path) || path.extname(checked.path).toLowerCase() !== '.php') {
    fail('INVALID_HELPER', 'ملف التحقق غير معتمد.');
  }
  const output = await runProcess(runtime.p.php, [...runtime.phpArgs(), checked.path], {
    env: { ...runtime.env, DL_MIGRATION_BUNDLE: runtime.p.bundle }, cwd: runtime.p.backend,
    input: JSON.stringify(payload), signal, timeout: 30 * 60 * 1000,
    outputLimit: 4 * 1024 * 1024, acceptedExitCodes: [0, 2], errorCode: 'PHP_VALIDATION_FAILED',
  });
  let result;
  try { result = JSON.parse(output); }
  catch { fail('PHP_VALIDATION_FAILED', 'تعذر قراءة نتيجة التحقق المحلي.'); }
  if (!isRecord(result) || result.ok !== true) {
    const code = typeof result?.code === 'string' && /^[A-Z][A-Z0-9_]{0,79}$/.test(result.code)
      ? result.code : 'PHP_VALIDATION_FAILED';
    fail(code, MESSAGES[code] ?? 'فشل التحقق المحلي من البيانات؛ لم تعتمد النسخة.');
  }
  return result;
}

async function importSql(runtime, sourcePath, expected, onProgress, signal) {
  const meter = hashMeter(expected, onProgress);
  const sqlFile = fs.createReadStream(sourcePath);
  const unpack = decompressor(sourcePath);
  try {
    await runProcess(path.join(runtime.p.maria, 'mariadb.exe'), [
      '--no-defaults', '--protocol=tcp', '--host=127.0.0.1', `--port=${runtime.dbPort}`,
      '--user=digital_lab', '--database=digital_lab_desktop', '--default-character-set=utf8mb4',
      '--binary-mode=1', '--local-infile=0', '--skip-reconnect', '--batch', '--silent',
    ], {
      env: { ...process.env, MYSQL_PWD: runtime.secrets.dbPassword },
      cwd: runtime.p.maria, inputStreams: [sqlFile, unpack, meter.stream],
      timeout: 2 * 60 * 60 * 1000, outputLimit: 8192, signal, errorCode: 'SQL_IMPORT_FAILED',
    });
    return meter.finish();
  } finally { sqlFile.destroy(); unpack.destroy(); meter.stream.destroy(); }
}

async function portIsOpen(port) {
  if (!Number.isInteger(port) || port < 1 || port > 65535) return false;
  return new Promise(resolve => {
    const socket = net.createConnection({ host: '127.0.0.1', port });
    let finished = false;
    const done = result => { if (finished) return; finished = true; socket.destroy(); resolve(result); };
    socket.setTimeout(1500, () => done(true));
    socket.once('connect', () => done(true));
    socket.once('error', error => done(error.code !== 'ECONNREFUSED'));
  });
}

async function stopOwnedRuntime(runtime) {
  if (!runtime) return true;
  const child = runtime.db;
  if (!child) return !await portIsOpen(runtime.dbPort);
  try { await runtime.stopDatabase(); }
  catch {
    // Disconnecting asks the original watchdog to stop only its own MariaDB.
    // Killing the watchdog directly could leave a Windows server orphaned.
    if (child.connected) { try { child.disconnect(); } catch {} }
    if (child.pid && child.exitCode === null && child.signalCode === null) {
      await new Promise(resolve => {
        const timer = setTimeout(resolve, 55000);
        child.once('close', () => { clearTimeout(timer); resolve(); });
      });
    }
  }
  if (child.pid && child.exitCode === null && child.signalCode === null) return false;
  if (await portIsOpen(runtime.dbPort)) return false;
  runtime.db = null;
  return true;
}

async function createStage(tempRoot) {
  const root = (await existingPathNoLinks(tempRoot, 'directory')).path;
  const stage = await fsp.mkdtemp(path.join(root, 'DigitalLab-Migration-'));
  await fsp.chmod(stage, 0o700);
  const token = crypto.randomBytes(32).toString('hex');
  await fsp.writeFile(path.join(stage, '.migration-owner'), token, { flag: 'wx', mode: 0o600 });
  return { path: stage, token, root };
}

async function removeOwnedStage(stage) {
  if (!stage) return true;
  try {
    const checked = await existingPathNoLinks(stage.path, 'directory');
    if (!within(stage.root, checked.path) || path.dirname(checked.path) !== stage.root
        || !path.basename(checked.path).startsWith('DigitalLab-Migration-')) return false;
    const marker = await existingPathNoLinks(path.join(checked.path, '.migration-owner'), 'file');
    if ((await fsp.readFile(marker.path, 'utf8')) !== stage.token) return false;
    await fsp.rm(checked.path, { recursive: true, force: false });
    return true;
  } catch { return false; }
}

function checkedHookResult(result, manifest) {
  if (!isRecord(result)) fail('POSTPROCESS_FAILED', 'لم يكتمل إعداد البيانات المحلية.');
  const warnings = result.warnings ?? [];
  if (!Array.isArray(warnings) || !warnings.every(code => typeof code === 'string' && /^[A-Z][A-Z0-9_]{0,79}$/.test(code))) {
    fail('POSTPROCESS_FAILED', 'نتيجة إعداد البيانات المحلية غير صالحة.');
  }
  const changed = normalizeManifest({ sql: manifest.sql, tables: result.expectedTables ?? manifest.tables,
    encryptedColumns: result.expectedEncryptedColumns ?? manifest.encryptedColumns, warnings });
  const changes = result.changes ?? [];
  if (!Array.isArray(changes) || !changes.every(item => isRecord(item)
      && typeof item.code === 'string' && /^[A-Z][A-Z0-9_]{0,79}$/.test(item.code)
      && Number.isSafeInteger(item.rows) && item.rows >= 0)) {
    fail('POSTPROCESS_FAILED', 'ملخص إعداد البيانات المحلية غير صالح.');
  }
  return { manifest: changed, warnings, changes: changes.map(({ code, rows }) => ({ code, rows })) };
}

async function migrate(input, { onEvent = () => {}, signal } = {}) {
  if (process.platform !== 'win32') fail('WINDOWS_REQUIRED', 'يجب تشغيل أداة التحويل على Windows حيث توجد نسخة Digital Lab Desktop.');
  const initial = normalizeConfig(input);
  let config, stage, preparedResult, failure, cleanupComplete = true;
  const runtimes = [];
  const vault = createEphemeralVault();
  let phase = 'preflight', currentProgress = { percent: 0 };
  const emit = event => onEvent(event);
  const progress = (name, detail = {}) => {
    phase = name; currentProgress = { percent: null, ...detail };
    emit({ type: 'progress', phase, ...currentProgress });
  };
  const heartbeat = setInterval(() => emit({ type: 'progress', phase, ...currentProgress }), 5000);
  const abortCheck = () => { if (signal?.aborted) fail('CANCELLED', 'أُلغيت عملية النقل.'); };
  try {
    config = await validatePaths(initial);
    const { raw: sourceManifest, normalized: manifest } = await readManifest(config.manifestPath);
    if (manifest.encryptedColumns.some(item => item.nonNullCount > 0) && !config.sourceAppKey) {
      fail('SOURCE_KEY_REQUIRED', MESSAGES.SOURCE_KEY_REQUIRED);
    }
    progress('verify_source', { percent: 0, bytes: 0, totalBytes: manifest.sql.uncompressedBytes });
    await verifySqlSource(config.sqlPath, manifest.sql, (bytes, totalBytes) =>
      progress('verify_source', { percent: Math.floor(bytes / totalBytes * 100), bytes, totalBytes }), signal);
    abortCheck();
    progress('prepare_runtime');
    const { bundle } = await validateInstallation(config.installationDir, signal);
    const localTemp = process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Temp') : os.tmpdir();
    await fsp.mkdir(localTemp, { recursive: true });
    stage = await createStage(localTemp);
    if (config.storageAppPath && (within(config.storageAppPath, stage.path) || within(stage.path, config.storageAppPath))) {
      fail('PATH_OVERLAP', 'مجلد المرحلة يتداخل مع مجلد المرفقات المصدر.');
    }
    const runtime = new Runtime(paths(bundle, path.join(stage.path, 'data')), vault);
    runtimes.push(runtime);
    await runtime.init();
    abortCheck();
    const baseline = await runPhp(runtime, VALIDATOR, { action: 'schema' }, signal);
    // Fixed SQL only, on the newly created stage's random loopback port.
    // This cannot address the installed application's live database directory.
    if (!within(stage.path, runtime.p.database) || runtime.p.data !== path.join(stage.path, 'data')) {
      fail('INVALID_STAGE', 'مسار قاعدة المرحلة غير صالح.');
    }
    await runtime.sql('DROP DATABASE digital_lab_desktop; CREATE DATABASE digital_lab_desktop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
    progress('import_sql', { percent: 0, bytes: 0, totalBytes: manifest.sql.uncompressedBytes });
    await importSql(runtime, config.sqlPath, manifest.sql, (bytes, totalBytes) =>
      progress('import_sql', { percent: Math.floor(bytes / totalBytes * 100), bytes, totalBytes }), signal);
    abortCheck();
    progress('validate_import');
    const validationPayload = { tables: manifest.tables, encryptedColumns: manifest.encryptedColumns };
    const initialCheck = await runPhp(runtime, VALIDATOR, { action: 'validate', ...validationPayload }, signal);
    assertCompatibleSchema(baseline.schema, initialCheck.schema);
    await runPhp(runtime, VALIDATOR, { action: 'rekey', ...validationPayload, sourceAppKey: config.sourceAppKey }, signal);
    config.sourceAppKey = undefined;
    // Restore runs migrations too; exercise that exact operation before delivery.
    await runtime.console(['migrate', '--force', '--no-interaction'], { timeout: 10 * 60 * 1000 });
    abortCheck();
    let copied = { files: 0, bytes: 0 };
    const warnings = [...manifest.warnings];
    if (config.storageAppPath) {
      progress('copy_storage');
      let lastCopyNotice = 0;
      copied = await copyStorageTree(config.storageAppPath, path.join(runtime.p.storage, 'app'), counts => {
        if (Date.now() - lastCopyNotice > 1000) { lastCopyNotice = Date.now(); progress('copy_storage', { files: counts.files, bytes: counts.bytes }); }
      });
    } else warnings.push('ATTACHMENTS_NOT_PROVIDED');
    abortCheck();
    progress('postprocess');
    const hook = checkedHookResult(await postprocessImported({
      runtime, manifest, sourceManifest, signal, emit, storageProvided: !!config.storageAppPath,
      runPhp: (scriptFile, payload) => runPhp(runtime, scriptFile, payload, signal),
    }), manifest);
    warnings.push(...hook.warnings);
    const finalPayload = { tables: hook.manifest.tables, encryptedColumns: hook.manifest.encryptedColumns };
    const verified = await runPhp(runtime, VALIDATOR, { action: 'verify_crypto', ...finalPayload }, signal);
    const installed = JSON.parse(await runtime.php('status.php'));
    if (!installed.installed || installed.database !== 'digital_lab_desktop') fail('ADMIN_MISSING', MESSAGES.ADMIN_MISSING);
    abortCheck();
    progress('backup');
    if (!await stopOwnedRuntime(runtime)) fail('DATABASE_SHUTDOWN_FAILED', 'لم تؤكد الأداة توقف قاعدة المرحلة؛ لم تبدأ النسخة الاحتياطية.');
    const candidate = path.join(stage.path, 'prepared.dlbackup');
    await writeBackup({ data: runtime.p.data, secrets: runtime.secrets, destination: candidate, password: config.password });
    abortCheck();
    progress('verify_backup');
    const restoredPath = path.join(stage.path, 'restore-check');
    await prepareRestore({ source: candidate, password: config.password, stage: restoredPath, vault });
    const restored = new Runtime(paths(bundle, restoredPath), vault);
    runtimes.push(restored);
    const restoredStatus = await restored.init();
    if (!restoredStatus.installed) fail('RESTORE_VERIFICATION_FAILED', 'لم تنجح تجربة استعادة الحسابات من النسخة الناتجة.');
    await runPhp(restored, VALIDATOR, { action: 'verify_crypto', ...finalPayload }, signal);
    if (!await stopOwnedRuntime(restored)) fail('DATABASE_SHUTDOWN_FAILED', 'لم تؤكد الأداة توقف قاعدة تجربة الاستعادة.');
    abortCheck();
    progress('publish');
    // Only the authenticated, successfully restored candidate reaches the user.
    await existingPathNoLinks(path.dirname(config.outputPath), 'directory');
    await publishExclusive(candidate, config.outputPath);
    preparedResult = {
      type: 'result', ok: true, outputPath: config.outputPath, sourceSha256: manifest.sql.sha256,
      sourceBytes: manifest.sql.uncompressedBytes, tableCount: verified.tableCount, rowCount: verified.rowCount,
      foreignKeyCount: verified.foreignKeyCount, attachmentsCopied: copied.files,
      encryptedValuesRekeyed: initialCheck.encryptedValues, databaseVersion: DB_VERSION,
      archiveVerified: true, restoredDatabaseVerified: true,
      warnings: [...new Set(warnings)], changes: hook.changes,
    };
  } catch (error) { failure = error; }
  finally {
    progress('cleanup');
    let allStopped = true;
    for (const runtime of [...runtimes].reverse()) {
      try { if (!await stopOwnedRuntime(runtime)) allStopped = false; }
      catch { allStopped = false; }
    }
    if (stage) cleanupComplete = allStopped && await removeOwnedStage(stage);
    vault.destroy();
    for (const runtime of runtimes) {
      if (runtime.secrets) runtime.secrets = null;
      if (runtime.env) { delete runtime.env.APP_KEY; delete runtime.env.DB_PASSWORD; }
    }
    initial.password = ''; initial.sourceAppKey = undefined;
    if (config) { config.password = ''; config.sourceAppKey = undefined; }
    clearInterval(heartbeat);
  }
  if (failure) return { type: 'result', ok: false, error: publicError(failure), cleanupComplete };
  if (!cleanupComplete) preparedResult.warnings.push('STAGE_CLEANUP_PENDING');
  return { ...preparedResult, cleanupComplete };
}

async function readStdin() {
  const chunks = []; let length = 0;
  for await (const chunk of process.stdin) {
    length += chunk.length;
    if (length > 64 * 1024) fail('INVALID_CONFIG', 'إعدادات النقل أكبر من الحد المسموح.');
    chunks.push(chunk);
  }
  try { return JSON.parse(Buffer.concat(chunks).toString('utf8')); }
  catch { fail('INVALID_CONFIG', 'تعذر قراءة إعدادات النقل.'); }
  finally { for (const chunk of chunks) chunk.fill(0); }
}

async function main() {
  const aborter = new AbortController();
  const abort = () => aborter.abort();
  process.on('SIGINT', abort); process.on('SIGTERM', abort);
  const output = event => process.stdout.write(JSON.stringify(event) + '\n');
  let result;
  try {
    if (process.argv.slice(2).some(arg => arg !== '--config-stdin')) fail('INVALID_ARGUMENTS', 'مرر إعدادات النقل عبر stdin فقط.');
    const config = await readStdin();
    try { result = await migrate(config, { onEvent: output, signal: aborter.signal }); }
    finally { if (isRecord(config)) { config.password = ''; config.sourceAppKey = undefined; } }
  } catch (error) { result = { type: 'result', ok: false, error: publicError(error), cleanupComplete: true }; }
  finally { process.off('SIGINT', abort); process.off('SIGTERM', abort); }
  output(result);
  process.exitCode = result.ok ? 0 : 1;
}

module.exports = {
  migrate, readManifest, validatePaths, validateInstallation, assertCompatibleSchema,
  runPhp, importSql, stopOwnedRuntime, createStage, removeOwnedStage, publicError, checkedHookResult,
};
if (require.main === module) main();
