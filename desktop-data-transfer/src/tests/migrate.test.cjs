'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const zlib = require('node:zlib');
const net = require('node:net');
const { EventEmitter } = require('node:events');
const { Readable } = require('node:stream');
const {
  MigrationError, normalizeConfig, normalizeManifest, createEphemeralVault,
  verifySqlSource, runProcess, copyStorageTree, publishExclusive, existingPathNoLinks,
} = require('../lib.cjs');
const { createStage, removeOwnedStage, assertCompatibleSchema, publicError, checkedHookResult, stopOwnedRuntime } = require('../migrate.cjs');
const { writeBackup, prepareRestore } = require('../vendor/backup.cjs');

async function temporary(t) {
  const dir = await fsp.mkdtemp(path.join(os.tmpdir(), 'dl-migration-test-'));
  t.after(() => fsp.rm(dir, { recursive: true, force: true }));
  return dir;
}
const payload = Buffer.from('CREATE TABLE fixture (id INT);\nINSERT INTO fixture VALUES (1);\nCOMMIT;\n');
const expected = { uncompressedBytes: payload.length, sha256: crypto.createHash('sha256').update(payload).digest('hex') };
const manifest = () => ({ sql: expected, tables: {
  migrations: { rows: 1, columns: ['id', 'migration', 'batch'] },
  users: { rows: 2, columns: ['id', 'role_id'] },
}, encryptedColumns: [] });

test('manifest normalizes counts and rejects incorrect sizes, columns and warning contents', () => {
  assert.equal(normalizeManifest(manifest()).rowCount, 3);
  const alternate = { sql: expected, counts: { migrations: 1, users: 2 }, warnings: ['OWNER_PORTAL_NOT_AVAILABLE_IN_V1'] };
  assert.deepEqual(normalizeManifest(alternate).warnings, ['OWNER_PORTAL_NOT_AVAILABLE_IN_V1']);
  assert.throws(() => normalizeManifest({ ...manifest(), sql: { ...expected, uncompressedBytes: -1 } }), { code: 'INVALID_MANIFEST' });
  assert.throws(() => normalizeManifest({ ...manifest(), warnings: ['must not print record data'] }), { code: 'INVALID_MANIFEST' });
  const bad = manifest(); bad.tables.users.columns = ['id', 'id'];
  assert.throws(() => normalizeManifest(bad), { code: 'INVALID_MANIFEST' });
  const encrypted = manifest(); encrypted.encryptedColumns = [{ table: 'users', column: 'no_such_column', nonNullCount: 1 }];
  assert.throws(() => normalizeManifest(encrypted), { code: 'INVALID_MANIFEST' });
});

test('config accepts one local source and rejects path traversal, weak keys and reserved output', () => {
  const config = {
    installationDir: path.resolve('application'), sqlPath: path.resolve('source.sql.br'),
    manifestPath: path.resolve('manifest.json'), outputPath: path.resolve('result.dlbackup'),
    password: 'only-a-test-passphrase', sourceAppKey: 'base64:' + crypto.randomBytes(32).toString('base64'),
  };
  assert.equal(normalizeConfig(config).sqlPath, config.sqlPath);
  assert.throws(() => normalizeConfig({ ...config, password: 'short' }), { code: 'INVALID_PASSWORD' });
  assert.throws(() => normalizeConfig({ ...config, sourceAppKey: 'base64:invalid' }), { code: 'INVALID_SOURCE_KEY' });
  assert.throws(() => normalizeConfig({ ...config, outputPath: path.resolve('CON.dlbackup') }), { code: 'INVALID_OUTPUT' });
  assert.throws(() => normalizeConfig({ ...config, sqlPath: path.parse(config.sqlPath).root + 'a' + path.sep + '..' + path.sep + 'source.sql.br' }), { code: 'INVALID_PATH' });
  assert.throws(() => normalizeConfig({ ...config, sqlGzPath: path.resolve('different.gz') }), { code: 'INVALID_CONFIG' });
});

for (const extension of ['gz', 'br', 'sql']) {
  test(`SQL source verification streams ${extension} and checks the decompressed bytes`, async t => {
    const dir = await temporary(t), file = path.join(dir, 'source.sql.' + extension);
    await fsp.writeFile(file, extension === 'gz' ? zlib.gzipSync(payload)
      : extension === 'br' ? zlib.brotliCompressSync(payload) : payload);
    const progress = [];
    const result = await verifySqlSource(file, expected, (bytes, total) => progress.push([bytes, total]));
    assert.equal(result.sha256, expected.sha256);
    assert.deepEqual(progress.at(-1), [payload.length, payload.length]);
    await assert.rejects(() => verifySqlSource(file, { ...expected, sha256: '0'.repeat(64) }), { code: 'SOURCE_HASH_MISMATCH' });
    await assert.rejects(() => verifySqlSource(file, { ...expected, uncompressedBytes: payload.length - 1 }), { code: 'SOURCE_SIZE_MISMATCH' });
  });
}

test('truncated compressed SQL is refused before any database work', async t => {
  const dir = await temporary(t), file = path.join(dir, 'truncated.gz');
  await fsp.writeFile(file, zlib.gzipSync(payload).subarray(0, 16));
  await assert.rejects(() => verifySqlSource(file, expected), { code: 'SOURCE_READ_FAILED' });
});

test('ephemeral vault is authenticated, non-deterministic and unusable after destruction', () => {
  const vault = createEphemeralVault(), other = createEphemeralVault();
  const plaintext = 'fixture-only-secret';
  const a = vault.encrypt(plaintext), b = vault.encrypt(plaintext);
  assert.notDeepEqual(a, b);
  assert.equal(a.includes(Buffer.from(plaintext)), false);
  assert.equal(vault.decrypt(a), plaintext);
  const damaged = Buffer.from(a); damaged[damaged.length - 1] ^= 1;
  assert.throws(() => vault.decrypt(damaged), { code: 'INVALID_VAULT' });
  assert.throws(() => other.decrypt(a), { code: 'INVALID_VAULT' });
  vault.destroy(); other.destroy();
  assert.throws(() => vault.decrypt(a), { code: 'VAULT_CLOSED' });
});

test('process runner streams stdin and never includes raw child diagnostics in errors', { timeout: 5000 }, async () => {
  const bytes = crypto.randomBytes(350000);
  const sum = await runProcess(process.execPath, ['-e', 'let n=0;process.stdin.on("data",b=>n+=b.length);process.stdin.on("end",()=>process.stdout.write(String(n)));'],
    { inputStreams: [Readable.from([bytes])], timeout: 2000 });
  assert.equal(Number(sum), bytes.length);
  await assert.rejects(() => runProcess(process.execPath, ['-e', 'process.stdout.write("FIXTURE_PRIVATE_ROW");process.stderr.write("FIXTURE_PRIVATE_KEY");process.exit(1);'],
    { timeout: 2000, errorCode: 'SQL_IMPORT_FAILED' }), error => {
    assert.equal(error.code, 'SQL_IMPORT_FAILED');
    assert.equal(String(error).includes('FIXTURE_PRIVATE'), false);
    return true;
  });
});

test('early child failure terminates a streaming input and does not hang', { timeout: 5000 }, async () => {
  async function* chunks() {
    for (let n = 0; n < 1000; n++) yield Buffer.alloc(65536);
  }
  await assert.rejects(() => runProcess(process.execPath, ['-e', 'process.exit(4)'],
    { inputStreams: [Readable.from(chunks())], timeout: 2000, errorCode: 'SQL_IMPORT_FAILED' }), { code: 'SQL_IMPORT_FAILED' });
});

test('process cancellation is explicit and bounded', { timeout: 5000 }, async () => {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 80);
  try {
    await assert.rejects(() => runProcess(process.execPath, ['-e', 'setTimeout(()=>{},10000)'],
      { signal: controller.signal, timeout: 2000 }), { code: 'CANCELLED' });
  } finally { clearTimeout(timer); }
});

test('output publication completes, preserves all bytes and never overwrites an existing file', { timeout: 5000 }, async t => {
  const dir = await temporary(t), source = path.join(dir, 'candidate'), destination = path.join(dir, 'result.dlbackup');
  const bytes = crypto.randomBytes(1024 * 1024 + 73);
  await fsp.writeFile(source, bytes);
  await publishExclusive(source, destination);
  assert.deepEqual(await fsp.readFile(destination), bytes);
  await fsp.writeFile(source, 'different');
  await assert.rejects(() => publishExclusive(source, destination), { code: 'OUTPUT_EXISTS' });
  assert.deepEqual(await fsp.readFile(destination), bytes);
});

test('storage copy preserves nested files and refuses a symbolic link outside the source', { timeout: 5000 }, async t => {
  const dir = await temporary(t), source = path.join(dir, 'source'), target = path.join(dir, 'target');
  await fsp.mkdir(path.join(source, 'private', 'fixture'), { recursive: true });
  await fsp.mkdir(path.join(source, 'public'), { recursive: true });
  await fsp.writeFile(path.join(source, 'private', 'fixture', 'attachment.bin'), 'synthetic attachment');
  await fsp.writeFile(path.join(source, 'public', 'logo.svg'), '<svg/>');
  const result = await copyStorageTree(source, target);
  assert.equal(result.files, 2);
  assert.equal(await fsp.readFile(path.join(target, 'private', 'fixture', 'attachment.bin'), 'utf8'), 'synthetic attachment');
  const outside = path.join(dir, 'outside'); await fsp.writeFile(outside, 'not inside the source tree');
  try { await fsp.symlink(outside, path.join(source, 'link')); }
  catch (error) { if (['EPERM', 'EACCES'].includes(error.code)) { t.diagnostic('Platform does not permit test symlinks.'); return; } throw error; }
  await assert.rejects(() => copyStorageTree(source, path.join(dir, 'target2')), { code: 'LINK_NOT_ALLOWED' });
  await assert.rejects(() => existingPathNoLinks(path.join(source, 'link'), 'file'), { code: 'LINK_NOT_ALLOWED' });
});

test('storage copy rejects Windows filename collisions and reserved names', { skip: process.platform === 'win32' }, async t => {
  const dir = await temporary(t), source = path.join(dir, 'source');
  await fsp.mkdir(source);
  await fsp.writeFile(path.join(source, 'A.txt'), 'one');
  await fsp.writeFile(path.join(source, 'a.txt'), 'two');
  await assert.rejects(() => copyStorageTree(source, path.join(dir, 'target')), { code: 'STORAGE_NAME_COLLISION' });
  await fsp.unlink(path.join(source, 'a.txt'));
  await fsp.writeFile(path.join(source, 'NUL.txt'), 'invalid name');
  await assert.rejects(() => copyStorageTree(source, path.join(dir, 'target2')), { code: 'INVALID_STORAGE_ENTRY' });
});

test('cleanup deletes only its own marked stage and refuses a wrong ownership token', async t => {
  const dir = await temporary(t), stage = await createStage(dir);
  await fsp.writeFile(path.join(stage.path, 'fixture'), 'local test data');
  assert.equal(await removeOwnedStage({ ...stage, token: 'wrong' }), false);
  assert.equal(await fsp.readFile(path.join(stage.path, 'fixture'), 'utf8'), 'local test data');
  assert.equal(await removeOwnedStage(stage), true);
  await assert.rejects(fsp.stat(stage.path), { code: 'ENOENT' });
});

test('cleanup refuses an open database port even when the watchdog handle has already exited', { timeout: 5000 }, async t => {
  const server = net.createServer(socket => socket.destroy());
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  t.after(() => new Promise(resolve => server.close(resolve)));
  const runtime = { dbPort: server.address().port,
    db: { pid: 1000000000, exitCode: 0, signalCode: null },
    async stopDatabase() { this.db = null; } };
  assert.equal(await stopOwnedRuntime(runtime), false);
  // A second cleanup attempt must not interpret a cleared handle as proof that
  // the server stopped; the open port still prevents stage deletion.
  assert.equal(await stopOwnedRuntime(runtime), false);
});

test('failed graceful shutdown requests the owning watchdog to stop and waits for its close', { timeout: 5000 }, async () => {
  const reserved = net.createServer();
  await new Promise(resolve => reserved.listen(0, '127.0.0.1', resolve));
  const port = reserved.address().port;
  await new Promise(resolve => reserved.close(resolve));
  const child = new EventEmitter();
  Object.assign(child, { pid: 1000000000, exitCode: null, signalCode: null, connected: true });
  let requested = false;
  child.disconnect = () => {
    requested = true; child.connected = false;
    queueMicrotask(() => { child.exitCode = 0; child.emit('close', 0); });
  };
  const runtime = { db: child, dbPort: port, async stopDatabase() { throw new Error('synthetic shutdown failure'); } };
  assert.equal(await stopOwnedRuntime(runtime), true);
  assert.equal(requested, true);
  assert.equal(runtime.db, null);
});

test('schema compatibility permits extra source columns and rejects missing Desktop columns', () => {
  assertCompatibleSchema({ users: ['id', 'name'] }, { users: ['id', 'name', 'new_feature'], extra: ['id'] });
  assert.throws(() => assertCompatibleSchema({ users: ['id', 'name'] }, { users: ['id'] }), { code: 'DESKTOP_SCHEMA_MISMATCH' });
  assert.throws(() => assertCompatibleSchema({ users: ['id'] }, {}), { code: 'DESKTOP_SCHEMA_MISMATCH' });
});

test('public results suppress unexpected exceptions and constrain hook metadata', () => {
  const sanitized = publicError(Object.assign(new Error('FIXTURE_PRIVATE_DATA'), { detail: 'FIXTURE_SQL' }));
  assert.equal(JSON.stringify(sanitized).includes('FIXTURE'), false);
  assert.equal(publicError(new MigrationError('SOURCE_KEY_REQUIRED', 'local message')).code, 'SOURCE_KEY_REQUIRED');
  const normalized = normalizeManifest(manifest());
  const result = checkedHookResult({ warnings: ['ATTACHMENTS_NOT_PROVIDED'], changes: [{ code: 'MEDIA_URLS_REWRITTEN', rows: 2, ignoredPrivateValue: 'must not escape' }] }, normalized);
  assert.deepEqual(result.changes, [{ code: 'MEDIA_URLS_REWRITTEN', rows: 2 }]);
  assert.throws(() => checkedHookResult({ warnings: ['raw person detail'] }, normalized), { code: 'POSTPROCESS_FAILED' });
});

test('original backup code round-trips encrypted data and rejects wrong passwords and tampering', { timeout: 15000 }, async t => {
  const dir = await temporary(t), data = path.join(dir, 'data');
  await fsp.mkdir(path.join(data, 'database', 'mysql'), { recursive: true });
  await fsp.mkdir(path.join(data, 'storage', 'app', 'public'), { recursive: true });
  await fsp.writeFile(path.join(data, 'database', 'mysql', 'fixture'), 'synthetic database marker');
  await fsp.writeFile(path.join(data, 'storage', 'app', 'public', 'fixture.bin'), payload);
  const secrets = { appKey: 'base64:' + crypto.randomBytes(32).toString('base64'),
    rootPassword: crypto.randomBytes(32).toString('hex'), dbPassword: crypto.randomBytes(32).toString('hex') };
  const password = 'fixture-backup-password';
  const archive = path.join(dir, 'candidate.dlbackup');
  await writeBackup({ data, secrets, destination: archive, password });
  const archiveBytes = await fsp.readFile(archive);
  assert.equal(archiveBytes.includes(Buffer.from(secrets.appKey)), false);
  const vault = createEphemeralVault(); t.after(() => vault.destroy());
  const restored = path.join(dir, 'restored');
  await prepareRestore({ source: archive, password, stage: restored, vault });
  assert.deepEqual(JSON.parse(vault.decrypt(await fsp.readFile(path.join(restored, 'secrets.dat')))), secrets);
  assert.deepEqual(await fsp.readFile(path.join(restored, 'storage', 'app', 'public', 'fixture.bin')), payload);
  const wrongStage = path.join(dir, 'wrong');
  await assert.rejects(() => prepareRestore({ source: archive, password: 'wrong-fixture-password', stage: wrongStage, vault }));
  await assert.rejects(fsp.stat(wrongStage), { code: 'ENOENT' });
  const tampered = path.join(dir, 'tampered.dlbackup');
  const bad = Buffer.from(archiveBytes); bad[bad.length - 1] ^= 1;
  await fsp.writeFile(tampered, bad);
  const tamperedStage = path.join(dir, 'tampered-stage');
  await assert.rejects(() => prepareRestore({ source: tampered, password, stage: tamperedStage, vault }));
  await assert.rejects(fsp.stat(tamperedStage), { code: 'ENOENT' });
  assert.deepEqual(await fsp.readFile(path.join(data, 'storage', 'app', 'public', 'fixture.bin')), payload);
});
