import { test } from 'node:test';
import assert from 'node:assert/strict';
import { canAccessPage, landingPage, pagePermissions, permissionNames } from '../src/utils/accessControl.js';

const staff = { id: 10, role_id: 50 };
test('each selectable page is a landing candidate for its own view permission', () => {
  for (const [path, names] of Object.entries(pagePermissions)) {
    for (const name of names) {
      assert.equal(canAccessPage(path, staff, [name]), true, path);
      assert.notEqual(landingPage(staff, [name]), '/access-denied', path);
    }
  }
});
test('restricted and empty roles never fall back to a forbidden dashboard', () => {
  assert.equal(landingPage(staff, ['patients view']), '/patients');
  assert.equal(landingPage(staff, ['invoices view']), '/invoices');
  assert.equal(landingPage(staff, ['packages view']), '/packages');
  assert.equal(landingPage(staff, []), '/access-denied');
  assert.equal(canAccessPage('/dashboard', staff, ['patients view']), false);
});
test('account type, portal isolation and unknown routes fail closed', () => {
  assert.equal(landingPage({ role_id: '1' }, []), '/super-admin');
  assert.equal(canAccessPage('/super-admin', staff, ['super admin dashboard']), false);
  const doctor = { ...staff, is_referral_partner: true, is_doctor_portal: true };
  assert.equal(landingPage(doctor, ['patients view']), '/doctor-portal');
  assert.equal(canAccessPage('/patients', doctor, ['patients view']), false);
  assert.equal(canAccessPage('/doctor-portal/patients/123', doctor), true);
  assert.equal(canAccessPage('/new-unregistered-feature', staff, []), false);
  assert.equal(canAccessPage('/access-denied', staff, []), true);
});
test('permission normalization retains direct/full names and clears empty payloads', () => {
  assert.deepEqual(permissionNames([{ permissions: [{ name: 'view', full_name: 'patients view' }] }, { name: 'samples view' }, 'patients view']), ['patients view', 'samples view']);
  assert.deepEqual(permissionNames([]), []);
  assert.deepEqual(permissionNames(null), []);
});
test('create and edit access are independent of viewing and unknown account roles', () => {
  assert.equal(canAccessPage('/invoices/create', staff, ['invoices view']), false);
  assert.equal(canAccessPage('/invoices/create', staff, ['invoices create']), true);
  assert.equal(canAccessPage('/invoices/4/edit', staff, ['invoices view']), false);
  assert.equal(canAccessPage('/invoices/4/edit', staff, ['invoices edit', 'invoices view']), true);
});
