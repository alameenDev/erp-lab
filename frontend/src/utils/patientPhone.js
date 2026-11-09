// The patient API stores +964 plus ten digits; the form displays those ten
// digits beside a fixed country prefix. Preserve invalid input for validation
// instead of truncating it or silently removing letters.
export function localPatientPhone(raw) {
  const value = String(raw ?? '')
    .replace(/[٠-٩]/g, digit => String(digit.charCodeAt(0) - 0x0660))
    .replace(/[۰-۹]/g, digit => String(digit.charCodeAt(0) - 0x06f0))
    .replace(/[\s()\-\u200e\u200f\u061c]/g, '');
  let local = value;
  if (local.startsWith('+964')) local = local.slice(4);
  else if (local.startsWith('00964')) local = local.slice(5);
  else if (local.startsWith('964') && local.length > 10) local = local.slice(3);
  const hasCountryCode = local !== value;
  local = local.replace(/^0(?=[1-9])/, '');
  if (hasCountryCode && !/^[0-9]{10}$/.test(local)) return value;
  return local || value;
}

export function validPatientPhone(raw) {
  const local = localPatientPhone(raw);
  return local === '' || /^[0-9]{10}$/.test(local);
}

export function formatPatientPhone(raw) {
  const local = localPatientPhone(raw);
  return local ? `+964${local}` : '';
}
