'use strict';
const path = require('node:path');

async function postprocessImported({manifest, sourceManifest, runPhp, signal}) {
  if (signal?.aborted) {
    const { fail } = require('./lib.cjs');
    fail('CANCELLED', 'أُلغيت عملية النقل.');
  }
  const warnings = [];
  for (const code of sourceManifest.warnings || []) {
    if (typeof code === 'string' && /^[A-Z][A-Z0-9_]{0,79}$/.test(code)) warnings.push(code);
  }
  const changes = [];
  if (sourceManifest.urlRewrite) {
    const result = await runPhp(path.join(__dirname, 'postprocess.php'), {urlRewrite: sourceManifest.urlRewrite});
    if (!result || result.ok !== true || !Number.isSafeInteger(result.changedValues) || result.changedValues < 0) {
      const error = new Error('Unable to relink local media. The source and installed application were not changed.');
      error.code = 'MEDIA_RELINK_FAILED';
      throw error;
    }
    changes.push({code: 'MEDIA_URLS_RELINKED', rows: result.changedValues});
  }
  return {expectedTables: manifest.tables, expectedEncryptedColumns: manifest.encryptedColumns, warnings, changes};
}

module.exports = { postprocessImported };
