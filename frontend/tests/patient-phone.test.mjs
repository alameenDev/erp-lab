import test from 'node:test';
import assert from 'node:assert/strict';
import { localPatientPhone, formatPatientPhone, validPatientPhone } from '../src/utils/patientPhone.js';

test('stored, typed and pasted Iraqi formats share a local display and API value', () => {
  for (const raw of ['7701234567', 7701234567, '07701234567', '+9647701234567', '9647701234567',
    '009647701234567', '+964 (770) 123-4567', '٠٧٧٠١٢٣٤٥٦٧', '۰۷۷۰۱۲۳۴۵۶۷', '\u200e+964 7701234567\u200f']) {
    assert.equal(localPatientPhone(raw), '7701234567', String(raw));
    assert.equal(formatPatientPhone(raw), '+9647701234567');
    assert.equal(validPatientPhone(raw), true);
    assert.equal(localPatientPhone(localPatientPhone(raw)), '7701234567');
  }
});

test('phone remains optional', () => {
  for (const raw of [null, undefined, '', '  ']) {
    assert.equal(localPatientPhone(raw), '');
    assert.equal(formatPatientPhone(raw), '');
    assert.equal(validPatientPhone(raw), true);
  }
});

test('invalid numbers are not truncated or silently accepted as empty', () => {
  for (const raw of ['770123', '77012345678', '+971501234567', '+964', '00964', '964', '0', '770abc1234567', '+964+7701234567', '+964+9647701234567', '9649647701234567']) {
    assert.equal(validPatientPhone(raw), false, raw);
    assert.notEqual(localPatientPhone(raw), '', raw);
    assert.equal(validPatientPhone(localPatientPhone(raw)), false, 'normalizing twice cannot validate malformed input: ' + raw);
  }
  assert.equal(localPatientPhone('77012345678'), '77012345678');
});
