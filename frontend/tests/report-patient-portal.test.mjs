import test from 'node:test';
import assert from 'node:assert/strict';
import { reportPatientPortalUrl } from '../src/utils/reportPatientPortal.js';
import { portalReportUrl } from '../src/utils/medicalReportOutput.js';

const token = 'a'.repeat(48);
const base = 'https://example.test';
const portal = `${base}/portal/${token}`;
const record = { id: 14, patient: { id: 7 } };
const defaults = { record, appBaseUrl: base, authenticated: false };

test('staff generates the linked patient portal, never the invoice result URL', async () => {
  const url = await reportPatientPortalUrl({ ...defaults, authenticated: true, http: {
    post: async (path, body) => {
      assert.equal(path, '/portal/generate');
      assert.deepEqual(body, { patient_id: 7 });
      return { data: { url: portal } };
    },
  } });
  assert.equal(url, portal);
});

test('legacy public reports and partial selections cannot generate portal access', async () => {
  const http = { post: () => assert.fail('must not mint a portal token'), get: () => assert.fail('must not fetch portal') };
  assert.equal(await reportPatientPortalUrl({ ...defaults, http }), '');
  assert.equal(await reportPatientPortalUrl({ ...defaults, http, authenticated: true, record: { ...record, suppress_report_qr: true } }), '');
});

test('portal-originated report carries and verifies the existing patient credential', async () => {
  const link = new URL(portalReportUrl(`${base}/result/14`, 14, { report: '14', form: '0' }, token));
  assert.equal(link.search, '?form=0');
  assert.equal(link.hash, '#portal=' + token);
  const url = await reportPatientPortalUrl({ ...defaults, route: { hash: link.hash }, http: {
    get: async path => { assert.equal(path, '/portal/' + token); return { data: { reports: [{ id: '14' }] } }; },
    post: () => assert.fail('public access must not create a new token'),
  } });
  assert.equal(url, portal);
});

test('tokens never propagate to an external, mismatched or referral report URL', () => {
  for (const value of ['https://other.test/result/14', `${base}/result/15`, `${base}/referral-report/14?signature=secret`]) {
    assert.equal(portalReportUrl(value, 14, {}, token), value);
  }
});

test('unverified OTP, another patient and expired portal access cannot produce a QR', async () => {
  for (const data of [{ requires_otp: true }, { reports: [{ id: 15 }] }]) {
    await assert.rejects(reportPatientPortalUrl({ ...defaults, route: { hash: '#portal=' + token }, http: {
      get: async () => ({ data }),
    } }), /تعذر التحقق/);
  }
  await assert.rejects(reportPatientPortalUrl({ ...defaults, route: { hash: '#portal=' + token }, http: {
    get: async () => { throw new Error('expired'); },
  } }), /expired/);
});

test('invalid or failed generation does not fall back to a misleading result QR', async () => {
  for (const url of [undefined, `${base}/result/14`, 'javascript:alert(1)', `${base}/portal/invalid`]) {
    await assert.rejects(reportPatientPortalUrl({ ...defaults, authenticated: true, http: {
      post: async () => ({ data: { url } }),
    } }), /تعذر تجهيز/);
  }
  await assert.rejects(reportPatientPortalUrl({ ...defaults, authenticated: true, http: {
    post: async () => { throw new Error('forbidden'); },
  } }), /forbidden/);
});
