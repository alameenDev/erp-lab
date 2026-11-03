export const collectorWorkspace = { sourceLabId: null };
export function scopeCollectorRequest(request, path) {
 if (!path.startsWith('/collector-portal')) return request;
 const url = '/' + String(request.url || '').replace(/^\//, '');
 const method = (request.method || 'get').toLowerCase();
 const allowed = (method === 'get' && /^\/(labs|collectors|contracts|referrals|templates|tests|cultures|packages|genders|age-units|titles|nationalities|payment-methods|result-status)$/.test(url))
  || (method === 'post' && ['/patients/search-name','/tests/questions','/invoices/create'].includes(url));
 if (!allowed) return request;
 request.url = '/collector-portal/workspace' + url;
 request.params = { ...request.params, source_lab_id: collectorWorkspace.sourceLabId };
 return request;
}
