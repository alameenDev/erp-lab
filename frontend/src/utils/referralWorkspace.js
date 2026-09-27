// Explicit request allowlist: the server independently enforces referral ownership.
export const referralWorkspace = { destinationLabId: null };
export const isReferralWorkspace = path => path.startsWith('/referral-portal');
export function scopeReferralRequest(request, path) {
 if (!isReferralWorkspace(path)) return request;
 const url = '/' + String(request.url || '').replace(/^\//, '');
 const method = (request.method || 'get').toLowerCase();
 const allowed = (method === 'get' && /^\/(labs|collectors|contracts|referrals|templates|tests|cultures|packages|genders|age-units|titles|nationalities|payment-methods|result-status|lab-settings|invoices\/\d+)$/.test(url))
  || (method === 'post' && ['/patients/search-name','/tests/questions','/invoices/create','/lab-settings','/lab-settings/reset'].includes(url))
  || (method === 'delete' && ['/lab-settings/logo','/lab-settings/background'].includes(url));
 if (!allowed) return request;
 request.url = '/referral-portal/workspace' + url;
 request.params = { ...request.params, destination_lab_id: referralWorkspace.destinationLabId };
 if (/^\/referral-portal\/reports\//.test(path) && /^\/invoices\/\d+$/.test(url)) request.params.document = 'report';
 return request;
}
