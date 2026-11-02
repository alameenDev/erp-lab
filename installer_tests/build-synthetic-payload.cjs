'use strict';

// Assemble a code-only installer fixture. The real data/, README and reports
// in the source directory are deliberately never opened by this helper.
const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const crypto = require('node:crypto');
const zlib = require('node:zlib');

const files = [
  'Start-Transfer.cmd', 'Transfer-Wizard.ps1', 'package.json',
  'src/migrate.cjs', 'src/lib.cjs', 'src/postprocess.cjs',
  'src/postprocess.php', 'src/validate_import.php',
  'src/vendor/runtime.cjs', 'src/vendor/constants.cjs',
  'src/vendor/process.cjs', 'src/vendor/backup.cjs',
  'src/vendor/database-watchdog.cjs',
];
const packages = ['yauzl', 'yazl', 'buffer-crc32', 'pend'];

async function main() {
  const [sourceArg, destinationArg] = process.argv.slice(2);
  if (!sourceArg || !destinationArg || process.argv.length !== 4) {
    throw new Error('Usage: node build-synthetic-payload.cjs SOURCE_CODE_DIRECTORY NEW_PAYLOAD_DIRECTORY');
  }
  const source = await fsp.realpath(sourceArg);
  const destination = path.resolve(destinationArg);
  if (fs.existsSync(destination)) throw new Error('Destination must not already exist');
  await fsp.mkdir(destination, { recursive: true });
  for (const relative of files) {
    const input = path.join(source, relative);
    const stat = await fsp.lstat(input);
    if (!stat.isFile() || stat.isSymbolicLink()) throw new Error('Unexpected source code entry: ' + relative);
    const output = path.join(destination, relative);
    await fsp.mkdir(path.dirname(output), { recursive: true });
    await fsp.copyFile(input, output, fs.constants.COPYFILE_EXCL);
  }
  const moduleRoot = [path.join(source, 'src', 'node_modules'), path.join(source, 'node_modules')]
    .find(candidate => packages.every(name => fs.existsSync(path.join(candidate, name, 'package.json'))));
  if (!moduleRoot) throw new Error('Install the declared helper dependencies first');
  for (const name of packages) {
    await fsp.cp(path.join(moduleRoot, name), path.join(destination, 'src', 'node_modules', name), {
      recursive: true, errorOnExist: true, force: false, dereference: false,
    });
  }

  const sql = Buffer.from('-- Generated synthetic installer fixture; never clinical data.\n'
    + 'CREATE TABLE installer_fixture (id INT PRIMARY KEY, label VARCHAR(64));\n'
    + "INSERT INTO installer_fixture VALUES (1, 'synthetic-only');\n", 'utf8');
  const manifest = {
    format: 1,
    app: 'Digital Lab installer validation fixture',
    source: { synthetic: true, origin: 'https://fixture.example.invalid' },
    sql: {
      file: 'online.sql.br', compression: 'br',
      sha256: crypto.createHash('sha256').update(sql).digest('hex'),
      uncompressedBytes: sql.length,
    },
    tables: { installer_fixture: { rows: 1, columns: ['id', 'label'] } },
    encryptedColumns: [],
    summary: { tables: 1, records: 1, filesIncluded: false, encryptedValues: 0 },
    warnings: [],
  };
  await fsp.mkdir(path.join(destination, 'data'));
  await fsp.writeFile(path.join(destination, 'data', 'online.sql.br'), zlib.brotliCompressSync(sql), { flag: 'wx' });
  await fsp.writeFile(path.join(destination, 'data', 'manifest.json'), JSON.stringify(manifest, null, 2), { flag: 'wx' });
  await fsp.writeFile(path.join(destination, 'README_AR.txt'), '\ufeffحزمة صناعية لاختبار المثبّت فقط. لا تحتوي بيانات مرضى أو مختبرات فعلية.\r\n', { flag: 'wx' });
  await fsp.writeFile(path.join(destination, 'SYNTHETIC_INSTALLER_FIXTURE.txt'),
    'This package validates installer file placement only. It is not a clinical database migration fixture.\r\n', { flag: 'wx' });

  const hashes = {};
  async function visit(directory, prefix = '') {
    for (const entry of await fsp.readdir(directory, { withFileTypes: true })) {
      const relative = prefix ? prefix + '/' + entry.name : entry.name;
      const full = path.join(directory, entry.name);
      if (entry.isSymbolicLink()) throw new Error('Fixture may not contain links');
      if (entry.isDirectory()) await visit(full, relative);
      else if (entry.isFile()) hashes[relative] = crypto.createHash('sha256').update(await fsp.readFile(full)).digest('hex');
    }
  }
  await visit(destination);
  const hashFile = destination + '.hashes.json';
  await fsp.writeFile(hashFile, JSON.stringify({ syntheticDataOnly: true, files: hashes }, null, 2), { flag: 'wx' });
  process.stdout.write(JSON.stringify({ syntheticDataOnly: true, directory: destination, hashes: hashFile, fileCount: Object.keys(hashes).length }) + '\n');
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
