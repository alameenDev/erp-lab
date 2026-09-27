import test from 'node:test';
import assert from 'node:assert/strict';
import { referralWorkspace, scopeReferralRequest } from '../src/utils/referralWorkspace.js';

test('scopes only approved invoice and settings calls to referral workspace', () => {
 referralWorkspace.destinationLabId = 12;
 const invoice = scopeReferralRequest({ url:'/invoices/create',method:'post',data:{total:1}}, '/referral-portal/new');
 assert.equal(invoice.url, '/referral-portal/workspace/invoices/create');
 assert.equal(invoice.params.destination_lab_id,12);
 assert.deepEqual(invoice.data,{total:1});
 const report = scopeReferralRequest({url:'/invoices/5',method:'get'},'/referral-portal/reports/5');
 assert.equal(report.params.document,'report');
 const unsafe = scopeReferralRequest({url:'/invoices/update-result',method:'post'},'/referral-portal/reports/5');
 assert.equal(unsafe.url,'/invoices/update-result');
 const staff = scopeReferralRequest({url:'/lab-settings',method:'get'},'/invoices/create');
 assert.equal(staff.url,'/lab-settings');
});
