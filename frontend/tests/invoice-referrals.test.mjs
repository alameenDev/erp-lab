import { test } from 'node:test';
import assert from 'node:assert/strict';
import { referralContains, sortInvoiceReferrals } from '../src/utils/invoiceReferralOptions.js';

test('most used first, with stable name ties and no mutation of management options', () => {
  const options = Object.freeze([
    { user_id: 31, name: 'Z', invoice_usage_count: 0 },
    { user_id: 42, name: 'Popular', invoice_usage_count: '18' },
    { user_id: 51, name: 'A', invoice_usage_count: 0 },
    { user_id: 65, name: 'New' },
  ].map(Object.freeze));
  assert.deepEqual(sortInvoiceReferrals(options).map(o => o.user_id), [42, 51, 65, 31]);
  assert.deepEqual(options.map(o => o.user_id), [31, 42, 51, 65]);
  assert.deepEqual(sortInvoiceReferrals([]), []);
});

test('Arabic spelling, diacritics and case are searchable without changing names', () => {
  for (const [name, search] of [['د. أَحمـد علي', 'احمد'], ['مختبر إزمير', 'ازمير'], ['د. مُصطفى', 'مصطفي'], ['City Lab', 'CITY'], ['اسم   الطبيب', 'اسم الطبيب']]) {
    assert.ok(referralContains(name, search), `${name} matches ${search}`);
  }
  assert.ok(referralContains('Any name', '  '));
  assert.equal(referralContains(null, 'name'), false);
  assert.equal(referralContains('مختبر العاصمة', 'دكتور علي'), false);
});
