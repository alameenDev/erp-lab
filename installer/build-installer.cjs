'use strict';
// Cross-platform NSIS build. Input payload may contain confidential records;
// enumerate only explicit files, never print their content or publish them.
const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');

function argumentsMap(args) {
  const result = {};
  for (let i = 0; i < args.length; i += 2) {
    if (!args[i].startsWith('--') || !args[i + 1]) throw new Error('Expected --name value');
    if (Object.hasOwn(result, args[i])) throw new Error('Duplicate argument');
    result[args[i]] = args[i + 1];
  }
  for (const name of ['--desktop-setup', '--payload', '--output', '--makensis', '--icon']) {
    if (!result[name]) throw new Error('Missing ' + name);
  }
  return result;
}

function nsis(value) {
  if (/[\r\n\0]/.test(value)) throw new Error('Invalid NSIS path');
  return value.replaceAll('$', () => '$$').replaceAll('"', '$\\"');
}

async function digest(file) {
  const h = crypto.createHash('sha256');
  for await (const chunk of fs.createReadStream(file)) h.update(chunk);
  return h.digest('hex');
}

async function enumerate(root) {
  const result = [], folded = new Set();
  async function walk(directory, prefix) {
    const children = await fsp.readdir(directory, { withFileTypes: true });
    children.sort((a, b) => a.name.localeCompare(b.name));
    for (const item of children) {
      if (item.isSymbolicLink()) throw new Error('Payload links are not allowed');
      if (/[\x00-\x1f<>:"|?*]/.test(item.name) || /[. ]$/.test(item.name) || /^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i.test(item.name)) throw new Error('Invalid Windows payload name');
      const relative = prefix ? prefix + '/' + item.name : item.name;
      if (folded.has(relative.toLowerCase())) throw new Error('Payload filename collision');
      folded.add(relative.toLowerCase());
      const absolute = path.join(directory, item.name);
      if (item.isDirectory()) await walk(absolute, relative);
      else if (item.isFile()) result.push(relative);
      else throw new Error('Unsupported payload entry');
    }
  }
  await walk(root, '');
  for (const required of ['Transfer-Wizard.ps1', 'Start-Transfer.cmd', 'README_AR.txt', 'src/migrate.cjs', 'data/manifest.json', 'data/online.sql.br']) {
    if (!result.includes(required)) throw new Error('Incomplete transfer payload: ' + required);
  }
  return result;
}

async function main() {
  const input = argumentsMap(process.argv.slice(2));
  const setup = path.resolve(input['--desktop-setup']);
  const payload = path.resolve(input['--payload']);
  const output = path.resolve(input['--output']);
  const icon = path.resolve(input['--icon']);
  const compiler = path.resolve(input['--makensis']);
  for (const filename of [setup, icon, compiler]) {
    const st = await fsp.lstat(filename);
    if (!st.isFile() || st.isSymbolicLink()) throw new Error('Build inputs must be regular files');
  }
  const files = await enumerate(payload);
  const setupHash = await digest(setup);
  const root = await fsp.mkdtemp(path.join(os.tmpdir(), 'DigitalLab-Installer-Build-'));
  await fsp.mkdir(path.dirname(output), { recursive: true });
  try {
    const include = path.join(root, 'payload_files.nsh');
    const lines = ['; Generated explicit managed-file list', '!macro InstallPayload'];
    const directories = new Set(['']);
    let current;
    for (const name of files) {
      const dirname = path.posix.dirname(name) === '.' ? '' : path.posix.dirname(name);
      const windowsDir = dirname.replaceAll('/', '\\');
      if (dirname !== current) {
        lines.push('  SetOutPath "$INSTDIR\\Transfer' + (windowsDir ? '\\' + nsis(windowsDir) : '') + '"');
        current = dirname;
      }
      lines.push('  File "' + nsis(path.join(payload, ...name.split('/'))) + '"');
      let dir = dirname;
      while (dir && dir !== '.') {
        directories.add(dir);
        dir = path.posix.dirname(dir);
      }
    }
    lines.push('!macroend', '', '!macro UninstallPayload');
    for (const name of files) lines.push('  Delete "$INSTDIR\\Transfer\\' + nsis(name.replaceAll('/', '\\')) + '"');
    for (const dir of [...directories].sort((a, b) => b.length - a.length)) {
      lines.push('  RMDir "$INSTDIR\\Transfer' + (dir ? '\\' + nsis(dir.replaceAll('/', '\\')) : '') + '"');
    }
    lines.push('!macroend', '');
    await fsp.writeFile(include, '\uFEFF' + lines.join('\r\n'), 'utf8');
    const prefix = process.platform === 'win32' ? '/' : '-';
    const definitions = {
      DESKTOP_SETUP: setup, DESKTOP_SETUP_SHA256: setupHash,
      TOOL_PAYLOAD: payload, PAYLOAD_INCLUDE: include, OUTPUT_FILE: output,
      ICON_FILE: icon, BOOTSTRAP_SCRIPT: path.join(__dirname, 'Bootstrap.ps1'),
    };
    // Diagnostics are compiled only for a generated synthetic payload.
    const manifest = JSON.parse(await fsp.readFile(path.join(payload, 'data', 'manifest.json'), 'utf8'));
    if (manifest.source?.synthetic === true) definitions.SYNTHETIC_DIAGNOSTICS = '1';
    const args = [prefix + 'V2', ...Object.entries(definitions).map(([key, value]) => prefix + 'D' + key + '=' + value), path.join(__dirname, 'Setup.nsi')];
    const child = spawnSync(compiler, args, { stdio: 'inherit', env: process.env, windowsHide: true });
    if (child.error) throw child.error;
    if (child.status !== 0) throw new Error('NSIS compilation failed');
    const meta = {
      output, bytes: (await fsp.stat(output)).size, sha256: await digest(output),
      desktopSetupSha256: setupHash, payloadFileCount: files.length,
      builtAt: new Date().toISOString(),
    };
    await fsp.writeFile(output + '.build.json', JSON.stringify(meta, null, 2) + '\n', 'utf8');
    console.log(JSON.stringify(meta));
  } finally {
    await fsp.rm(root, { recursive: true, force: true });
  }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
