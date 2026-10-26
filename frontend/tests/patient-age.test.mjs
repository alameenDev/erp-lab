import test from 'node:test';
import assert from 'node:assert/strict';
import { ageFromBirthDate, birthDateFromAge, birthDateFromParts, calendarDate, isEstimatedBirthDate } from '../src/utils/patientAge.js';

test('age uses completed calendar years around birthdays', () => {
  assert.deepEqual(ageFromBirthDate('2000-10-26', '2026-10-26'), { age: 26, unit: 'Years' });
  assert.deepEqual(ageFromBirthDate('2000-10-27', '2026-10-26'), { age: 25, unit: 'Years' });
});
test('infants use completed months or days, including zero', () => {
  assert.deepEqual(ageFromBirthDate('2026-06-15', '2026-10-26'), { age: 4, unit: 'Months' });
  assert.deepEqual(ageFromBirthDate('2026-10-20', '2026-10-26'), { age: 6, unit: 'Days' });
  assert.deepEqual(ageFromBirthDate('2026-10-26', '2026-10-26'), { age: 0, unit: 'Days' });
});
test('leap birthdays and month ends use clamped calendar anniversaries', () => {
  assert.deepEqual(ageFromBirthDate('2024-02-29', '2025-02-28'), { age: 1, unit: 'Years' });
  assert.deepEqual(ageFromBirthDate('2026-01-31', '2026-02-28'), { age: 1, unit: 'Months' });
  assert.equal(birthDateFromAge(1, 'Months', '2026-03-31'), '2026-02-28');
  assert.equal(birthDateFromAge(1, 'Years', '2024-02-29'), '2023-02-28');
});
test('reverse entry retains years, months and days without rounding', () => {
  assert.equal(birthDateFromAge(30, 'Years', '2026-10-26'), '1996-10-26');
  assert.equal(birthDateFromAge(14, 'Months', '2026-10-26'), '2025-08-26');
  assert.equal(birthDateFromAge(6, 'Days', '2026-10-26'), '2026-10-20');
  assert.equal(birthDateFromAge(0, 'Days', '2026-10-26'), '2026-10-26');
});
test('invalid dates and ages never silently roll to another month', () => {
  for (const dob of ['2026-02-29', '2026-04-31', '2026-13-01', '0000-01-01']) assert.equal(calendarDate(dob), null);
  assert.equal(ageFromBirthDate('2026-10-27', '2026-10-26'), null);
  for (const age of ['', null, -1, 2.5, 'no', 50000]) assert.equal(birthDateFromAge(age, 'Years', '2026-10-26'), null);
  assert.equal(birthDateFromParts({ day: '31', month: '4', year: '2026' }), null);
  assert.equal(birthDateFromParts({ day: '29', month: '2', year: '2024' }), '2024-02-29');
});
test('API timestamps retain their calendar day and precision flags normalize', () => {
  assert.deepEqual(ageFromBirthDate('2026-10-25T00:00:00.000000Z', '2026-10-26'), { age: 1, unit: 'Days' });
  for (const value of [true, 1, '1']) assert.equal(isEstimatedBirthDate(value), true);
  for (const value of [false, 0, '0', undefined]) assert.equal(isEstimatedBirthDate(value), false);
});
