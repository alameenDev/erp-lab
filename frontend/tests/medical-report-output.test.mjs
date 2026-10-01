import test from 'node:test';
import assert from 'node:assert/strict';
import { reportFormChoice, reportFormFromRoute, reportUrlWithForm, portalReportUrl, assertPdfBlob } from '../src/utils/medicalReportOutput.js';

test('report links default to the lab form and respect an explicit without-form choice', () => {
  assert.equal(reportFormChoice(undefined), true);
  for (const value of ['0', 0, false]) assert.equal(reportFormChoice(value), false);
  for (const value of ['1', 1, true]) assert.equal(reportFormChoice(value), true);
  assert.equal(reportFormFromRoute({ query: {}, hash: '#form=0' }), '0');
  assert.equal(reportFormFromRoute({ query: { form: '1' }, hash: '' }), '1');
});

test('QR links carry form mode without losing other report parameters', () => {
  const url = new URL(reportUrlWithForm('https://lab.test/result/14?source=qr', false));
  assert.equal(url.searchParams.get('form'), '0');
  assert.equal(url.searchParams.get('source'), 'qr');
  assert.equal(new URL(reportUrlWithForm(url.href, true)).searchParams.get('form'), '1');
});

test('referral link presentation never changes the signed query', () => {
  const original = 'https://lab.test/referral-report/14?referral=3&expires=999999&signature=a%2Bb';
  const updated = new URL(reportUrlWithForm(original, false));
  assert.equal(updated.search, new URL(original).search);
  assert.equal(updated.hash, '#form=0');
});

test('patient portal forwards the chosen form only to the shared invoice', () => {
  const url = 'https://lab.test/result/14';
  assert.equal(portalReportUrl(url, 14, { report: '14', form: '0' }), url + '?form=0');
  assert.equal(portalReportUrl(url, 14, { report: '15', form: '0' }), url);
  assert.equal(portalReportUrl(url, 14, {}), url);
});

test('saved PDFs retain their original bytes; raw HTML cannot be saved as a PDF', async () => {
  const pdf = new Blob(['%PDF-1.4\nexample'], { type: 'application/pdf' });
  assert.equal(await assertPdfBlob(pdf), pdf);
  await assert.rejects(assertPdfBlob('<table>results</table>'));
  await assert.rejects(assertPdfBlob(new Blob(['<html>results</html>'], { type: 'application/pdf' })));
});
