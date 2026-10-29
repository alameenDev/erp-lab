const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const net = require('node:net');
const crypto = require('node:crypto');
const {spawn} = require('node:child_process');
const {run} = require('./process.cjs');
const {ORIGIN} = require('./constants.cjs');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function freePort() {
  const server = net.createServer();
  await new Promise((resolve, reject) => {server.once('error', reject);server.listen(0, '127.0.0.1', resolve);});
  const port = server.address().port;
  await new Promise(resolve => server.close(resolve));return port;
}
class Runtime {
  constructor(paths, vault, license = null) {this.license=license;this.p = paths;this.vault = vault;this.db = null;this.stopping = false;}
  async init() {
    for (const dir of ['environment', 'cache', 'tmp', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']) await fsp.mkdir(path.join(this.p.data, dir), {recursive: true});
    const file = path.join(this.p.data, 'secrets.dat');
    if (fs.existsSync(file)) this.secrets = JSON.parse(this.vault.decrypt(await fsp.readFile(file)));
    else {
      if (fs.existsSync(this.p.database)) throw new Error('بيانات موجودة بلا مفاتيحها. استعد نسخة احتياطية كاملة؛ لم تتغير البيانات.');
      this.secrets = {appKey: 'base64:' + crypto.randomBytes(32).toString('base64'), rootPassword: crypto.randomBytes(32).toString('hex'), dbPassword: crypto.randomBytes(32).toString('hex')};
      await fsp.writeFile(file, this.vault.encrypt(JSON.stringify(this.secrets)), {mode: 0o600, flag: 'wx'});
    }
    this.dbPort = await freePort();
    const staging = this.p.database + '.initializing';
    if (!fs.existsSync(path.join(this.p.database, 'mysql'))) {
      if (fs.existsSync(this.p.database)) throw new Error('قاعدة البيانات تحتاج استعادة. لم تتم تهيئتها أو مسحها.');
      // Only an unfinished, never-used initialization directory may be replaced.
      await fsp.rm(staging, {recursive: true, force: true});
      await run(path.join(this.p.maria, 'mariadb-install-db.exe'), [`--datadir=${staging}`, `--password=${this.secrets.rootPassword}`, `--port=${this.dbPort}`, '--silent'], {cwd: this.p.maria, timeout: 180000});
      await fsp.rename(staging, this.p.database);
    }
    await this.startDatabase();
    await this.sql(`CREATE DATABASE IF NOT EXISTS digital_lab_desktop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'digital_lab'@'127.0.0.1' IDENTIFIED BY '${this.secrets.dbPassword}'; GRANT ALL ON digital_lab_desktop.* TO 'digital_lab'@'127.0.0.1';`);
    this.env = this.environment();
    await this.console(['migrate', '--force', '--no-interaction'], {timeout: 180000});
    this.status = JSON.parse(await this.php('status.php'));
    if(this.license && this.status.installed)await this.php('license-subscription.php');
    return this.status;
  }
  environment() {
    // Explicit allowlist prevents an inherited hosted DB_URL/mail/cloud config.
    const env = Object.fromEntries(Object.entries(process.env).filter(([k]) => /^(SYSTEMROOT|WINDIR|COMSPEC|TEMP|TMP|PATH|PATHEXT|USERPROFILE|LOCALAPPDATA|APPDATA|PROCESSOR_ARCHITECTURE)$/i.test(k)));
    return {...env, DESKTOP_MODE: 'true', DL_BACKEND: this.p.backend, DL_DATA: this.p.data,
      DL_LICENSE_TYPE: this.license?.type || '', DL_LICENSE_EXPIRY: this.license?.expiresAt ? new Date(this.license.expiresAt).toISOString() : '',
      APP_NAME: 'Digital Lab Desktop', APP_ENV: 'production', APP_DEBUG: 'false', APP_KEY: this.secrets.appKey,
      APP_TIMEZONE: 'Asia/Baghdad', APP_URL: ORIGIN, FRONTEND_URL: ORIGIN, CORS_ALLOWED_ORIGINS: ORIGIN,
      APP_LOCALE: 'ar', APP_FALLBACK_LOCALE: 'en', DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1',
      DB_PORT: String(this.dbPort), DB_DATABASE: 'digital_lab_desktop', DB_USERNAME: 'digital_lab', DB_PASSWORD: this.secrets.dbPassword,
      DB_CHARSET: 'utf8mb4', DB_COLLATION: 'utf8mb4_unicode_ci', SESSION_DRIVER: 'file', CACHE_STORE: 'file',
      QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', LOG_CHANNEL: 'daily', LOG_LEVEL: 'error',
      FILESYSTEM_DISK: 'local', SANCTUM_TOKEN_EXPIRATION: '480', SESSION_SECURE_COOKIE: 'false',
      APP_CONFIG_CACHE: path.join(this.p.cache, 'config.php'), APP_SERVICES_CACHE: path.join(this.p.cache, 'services.php'),
      APP_PACKAGES_CACHE: path.join(this.p.cache, 'packages.php'), APP_ROUTES_CACHE: path.join(this.p.cache, 'routes.php'),
      APP_EVENTS_CACHE: path.join(this.p.cache, 'events.php'), VIEW_COMPILED_PATH: path.join(this.p.storage, 'framework/views')};
  }
  phpArgs() {
    // PHP parses -d values as INI syntax: quote Windows paths containing ~,
    // spaces or parentheses, including the runner's RUNNER~1 short home path.
    const quoted = value => '"' + value.replaceAll('\\', '/') + '"';
    return ['-c', path.join(this.p.bundle, 'php/php.ini'), '-d', `extension_dir=${quoted(path.join(this.p.bundle, 'php/ext'))}`, '-d', `sys_temp_dir=${quoted(path.join(this.p.data, 'tmp'))}`, '-d', `upload_tmp_dir=${quoted(path.join(this.p.data, 'tmp'))}`];
  }
  php(script, options = {}) {return run(this.p.php, [...this.phpArgs(), path.join(this.p.bundle, 'desktop-php', script)], {env: this.env, cwd: this.p.backend, ...options});}
  console(args, options = {}) {return run(this.p.php, [...this.phpArgs(), path.join(this.p.bundle, 'desktop-php/console.php'), ...args], {env: this.env, cwd: this.p.backend, ...options});}
  async install(input) {
    if (this.status.installed) throw new Error('تم إعداد هذه النسخة سابقاً.');
    try {const result = JSON.parse(await this.php('install.php', {input: JSON.stringify(input)}));this.status = {installed: true};return result;}
    catch (e) {
      if (e.detail?.startsWith('{')) {try {throw new Error(Object.values(JSON.parse(e.detail).errors).flat().join('\n'));} catch (parsed) {if (!(parsed instanceof SyntaxError)) throw parsed;}}
      const error = new Error('تعذر إنشاء الحسابات. راجع الحقول وحاول مرة ثانية؛ لا تستخدم بريداً واحداً للحسابين.');
      error.detail = e.detail;throw error;
    }
  }
  async startDatabase() {
    const ini = `[mysqld]\nbasedir="${path.dirname(this.p.maria).replaceAll('\\', '/')}"\ndatadir="${this.p.database.replaceAll('\\', '/')}"\nbind-address=127.0.0.1\nport=${this.dbPort}\ncharacter-set-server=utf8mb4\ncollation-server=utf8mb4_unicode_ci\nmax-allowed-packet=64M\ninnodb-buffer-pool-size=128M\ninnodb-flush-log-at-trx-commit=1\nlog-error="${path.join(this.p.data, 'storage/logs/database.log').replaceAll('\\', '/')}"\n`;
    const config = path.join(this.p.data, 'database.ini');await fsp.writeFile(config, ini);
    this.db = spawn(process.execPath, [path.join(__dirname, 'database-watchdog.cjs'), path.join(this.p.maria, 'mariadbd.exe'), config, path.join(this.p.maria, 'mariadb-admin.exe'), String(this.dbPort), String(process.pid)], {cwd: this.p.maria, env: {...process.env, ELECTRON_RUN_AS_NODE: '1', MYSQL_PWD: this.secrets.rootPassword}, windowsHide: true, stdio: ['ignore', 'ignore', 'ignore', 'ipc']});
    let startupError;this.db.on('error', e => {startupError = e;});
    const deadline = Date.now() + 60000;
    while (Date.now() < deadline) {
      if (startupError || this.db.exitCode !== null) throw new Error('تعذر تشغيل قاعدة البيانات المحلية. راجع سجل التشخيص.');
      try {await this.sql('SELECT 1');return;} catch {await delay(350);}
    }
    throw new Error('انتهت مهلة تشغيل قاعدة البيانات المحلية.');
  }
  sql(input) {return run(path.join(this.p.maria, 'mariadb.exe'), ['--no-defaults', '--protocol=tcp', '--host=127.0.0.1', `--port=${this.dbPort}`, '--user=root', '--default-character-set=utf8mb4', '--batch', '--skip-column-names'], {input, env: {...process.env, MYSQL_PWD: this.secrets.rootPassword}, timeout: 15000});}
  async stopDatabase() {
    if (!this.db || this.db.exitCode !== null) {this.db = null;return;}
    const child = this.db;
    await run(path.join(this.p.maria, 'mariadb-admin.exe'), ['--no-defaults', '--protocol=tcp', '--host=127.0.0.1', `--port=${this.dbPort}`, '--user=root', 'shutdown'], {env: {...process.env, MYSQL_PWD: this.secrets.rootPassword}, timeout: 45000});
    const deadline = Date.now() + 30000;
    while (child.exitCode === null && Date.now() < deadline) await delay(100);
    if (child.exitCode === null) throw new Error('قاعدة البيانات لم تغلق بعد. لم يبدأ النسخ أو الاستبدال.');
    this.db = null;
  }
}
module.exports = {Runtime, freePort};
