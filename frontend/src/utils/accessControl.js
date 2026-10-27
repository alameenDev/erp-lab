export function readStoredJson(key, fallback = null) {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
}

export function permissionNames(raw = []) {
  if (!Array.isArray(raw)) return [];
  return [...new Set(raw.flatMap(item => {
    if (typeof item === 'string') return [item];
    if (Array.isArray(item?.permissions)) return permissionNames(item.permissions);
    return item?.full_name || item?.name ? [item.full_name || item.name] : [];
  }))];
}

export const pagePermissions = {
  '/dashboard': ['main dashboard view', 'reports view'],
  '/patients': ['patients view'],
  '/patients/work-register': ['patients view'],
  '/invoices': ['invoices view'],
  '/medical_reports': ['invoices view'],
  '/users': ['users view'], '/roles': ['roles view'],
  '/activityLogger': ['activities view'],
  '/tests': ['tests view'], '/test-groups': ['test groups view'],
  '/categories': ['categories view'], '/samples': ['samples view'],
  '/packages': ['packages view'], '/cultures': ['cultures view'],
  '/test-questions': ['test questions view'], '/Antibiotics': ['antibiotics view'],
  '/priceList': ['price list view'], '/paymentMethods': ['payments view'],
  '/referrals': ['referrals view'], '/contracts': ['contracts view'],
  '/reports': ['reports view'], '/accounting-reports': ['accounting reports view'],
  '/inventory': ['inventory view'], '/devices': ['devices view'],
  '/doctors': ['doctors view'], '/promo-codes': ['promo codes view'],
  '/lab-settings': ['lab settings edit'],
};

export function canAccessPage(path, user, permissions = []) {
  if (!user) return false;
  if (path === '/logout') return true;
  if (user.owner_portal_only) return path === '/owner-portal' || path.startsWith('/owner-portal/');
  if (path === '/owner-portal' || path.startsWith('/owner-portal/')) return false;
  if (user.is_referral_partner) {
    const home = user.is_doctor_portal ? '/doctor-portal' : '/referral-portal';
    return path === home || path.startsWith(home + '/');
  }
  if (path.startsWith('/referral-portal') || path.startsWith('/doctor-portal')) return false;
  if (path.startsWith('/super-admin')) return Number(user.role_id) === 1;
  if (['/access-denied', '/app/profile', '/printer-settings'].includes(path)) return true;
  if (path === '/invoices/create') return permissions.includes('invoices create');
  if (/^\/invoices\/[^/]+\/edit$/.test(path)) return permissions.includes('invoices edit') && permissions.includes('invoices view');
  if (path.startsWith('/medical_reports/update-result/')) return permissions.includes('invoices view');
  if (['/lab-settings', '/doctors', '/promo-codes'].includes(path) && [1, 2].includes(Number(user.role_id))) return true;
  const required = pagePermissions[path];
  return required ? required.some(permission => permissions.includes(permission)) : false;
}

export function landingPage(user, permissions = []) {
  if (user?.owner_portal_only) return '/owner-portal/';
  if (user?.is_referral_partner) return user.is_doctor_portal ? '/doctor-portal' : '/referral-portal';
  if (Number(user?.role_id) === 1) return '/super-admin';
  const preferred = Number(user?.role_id) === 4 ? '/patients' : Number(user?.role_id) === 6 ? '/invoices' : '/dashboard';
  return [preferred, ...Object.keys(pagePermissions), '/invoices/create']
    .find(path => canAccessPage(path, user, permissions)) || '/access-denied';
}
