'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const path = require('node:path');
const php = process.env.DL_PHP_BIN;

function transform(json) {
  const processResult = spawnSync(php, ['-n', path.join(__dirname, '../src/postprocess.php')], {
    input: JSON.stringify({json, origins: ['https://example.test']}),
    env: {...process.env, DL_MEDIA_TRANSFORM_TEST: '1'}, encoding: 'utf8',
  });
  assert.equal(processResult.status, 0);
  return JSON.parse(processResult.stdout).value;
}

test('local media conversion preserves mixed JSON content and changes only exact source URLs', {skip: !php}, () => {
  const result = transform(JSON.stringify({
    attachments: [{file: 'https://example.test/api/report-attachments/17/fixture?download=1', storage_path: 'report-attachments/17/fixture.bin'}],
    logo: 'https://example.test/storage/logos/fixture.png',
    clinicalText: 'A note containing https://example.test/storage/logos/fixture.png must remain verbatim.',
    external: 'https://unrelated.test/storage/file.png',
    falseOrigin: 'https://example.test.evil.invalid/storage/file.png',
    userInfo: 'https://user:password@example.test/storage/file.png',
    generalPage: 'https://example.test/patient-portal',
    emptyObject: {}, nullValue: null, numericText: '2.70', booleanValue: false,
  }));
  assert.equal(result.attachments[0].file, 'http://127.0.0.1:17892/api/report-attachments/17/fixture?download=1');
  assert.equal(result.attachments[0].storage_path, 'report-attachments/17/fixture.bin');
  assert.equal(result.logo, 'http://127.0.0.1:17892/storage/logos/fixture.png');
  assert.equal(result.clinicalText, 'A note containing https://example.test/storage/logos/fixture.png must remain verbatim.');
  assert.equal(result.external, 'https://unrelated.test/storage/file.png');
  assert.equal(result.falseOrigin, 'https://example.test.evil.invalid/storage/file.png');
  assert.equal(result.userInfo, 'https://user:password@example.test/storage/file.png');
  assert.equal(result.generalPage, 'https://example.test/patient-portal');
  assert.deepEqual(result.emptyObject, {});
  assert.equal(result.nullValue, null);
  assert.equal(result.numericText, '2.70');
  assert.equal(result.booleanValue, false);
});

test('a numeric-keyed JSON object remains an object, and Unicode survives relinking', {skip: !php}, () => {
  const result = transform('{"0":"اختبار","1":{"file":"https://example.test/storage/a.png"}}');
  assert.equal(Array.isArray(result), false);
  assert.equal(result['0'], 'اختبار');
  assert.equal(result['1'].file, 'http://127.0.0.1:17892/storage/a.png');
});
