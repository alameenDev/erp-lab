// One calculator for the editor and every report output. Formula definitions
// win over saved target values, which may belong to an earlier calculation.
export const formulaKey = value => String(value ?? '').trim();
export const formulaApproved = value => value === true || value === 1 || value === '1' || value === 'true';
export const formulaDefinitions = parent => [
  ...(Array.isArray(parent?.calculation_formulas) ? parent.calculation_formulas : Array.isArray(parent?.formula) ? parent.formula : []),
  ...(!parent?.calculation_formulas && Array.isArray(parent?.test_groups) ? parent.test_groups.flatMap(g => Array.isArray(g.formula) ? g.formula : []) : []),
];
export const formulaTestMatches = (test, key) => [test?.shortcut, test?.name].some(v => formulaKey(v) && formulaKey(v) === formulaKey(key));
export const isFormulaTarget = (parent, test) => (Array.isArray(parent?.formula) ? parent.formula : []).some(f => formulaTestMatches(test, f?.name));
export const findFormulaTest = (parent, key) => (parent?.tests || []).find(t => formulaTestMatches(t, key));
const numeric = value => {
  if (typeof value !== 'number' && typeof value !== 'string') return null;
  const text = String(value).trim();
  if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/.test(text)) return null;
  const n = Number(text); return Number.isFinite(n) ? n : null;
};
const invalid = reason => ({ value: null, complete: false, reason });

function arithmetic(tokens) {
  let at = 0;
  const fail = () => { throw new Error('invalid'); };
  const primary = () => {
    const token = tokens[at++];
    if (typeof token === 'number') return token;
    if (token === '+' || token === '-') return (token === '-' ? -1 : 1) * primary();
    if (token === '(') { const n = sum(); if (tokens[at++] !== ')') fail(); return n; }
    return fail();
  };
  const product = () => {
    let n = primary();
    while (tokens[at] === '*' || tokens[at] === '/') {
      const op = tokens[at++], rhs = primary();
      if (op === '/' && rhs === 0) throw new Error('division_by_zero');
      n = op === '*' ? n * rhs : n / rhs;
    }
    return n;
  };
  const sum = () => {
    let n = product();
    while (tokens[at] === '+' || tokens[at] === '-') { const op = tokens[at++], rhs = product(); n = op === '+' ? n + rhs : n - rhs; }
    return n;
  };
  const result = sum();
  if (at !== tokens.length || !Number.isFinite(result * 100)) fail();
  return Math.round(result * 100) / 100;
}

export function evaluateReportFormulas(parent) {
  const definitions = formulaDefinitions(parent), tests = Array.isArray(parent?.tests) ? parent.tests : [];
  const results = Object.create(null), byName = new Map(), visiting = new Set();
  for (const f of definitions) {
    const key = formulaKey(f?.name);
    if (!key) continue;
    if (byName.has(key)) { if (JSON.stringify(byName.get(key)?.tokens) !== JSON.stringify(f.tokens)) byName.set(key, null); }
    else byName.set(key, f);
  }
  const resolve = key => {
    if (Object.hasOwn(results, key)) return results[key];
    if (visiting.has(key) || visiting.size >= 128) return invalid('cycle');
    const f = byName.get(key);
    if (!f || !Array.isArray(f.tokens) || !f.tokens.length || f.tokens.length > 512) return invalid('invalid');
    visiting.add(key);
    let complete = true, digits = '', tokens = [], failure = null;
    const flush = () => {
      if (!digits) return;
      const n = numeric(digits); if (n === null) failure = 'invalid'; else tokens.push(n);
      digits = '';
    };
    for (const raw of f.tokens) {
      const token = formulaKey(raw);
      if (/^[\d.]+$/.test(token)) { digits += token; continue; }
      flush(); if (failure) break;
      if (/^[+\-*/()]$/.test(token)) { tokens.push(token); continue; }
      let operand;
      if (byName.has(token)) operand = resolve(token);
      else {
        const matches = tests.filter(t => formulaTestMatches(t, token));
        const n = matches.length === 1 ? numeric(matches[0].result) : null;
        operand = n === null ? invalid('missing') : {value:n, complete:formulaApproved(matches[0].is_done)};
      }
      if (operand.value === null) { failure = operand.reason || 'missing'; break; }
      complete = complete && operand.complete;
      tokens.push(operand.value);
    }
    flush();
    let result;
    try { result = failure ? invalid(failure) : {value:arithmetic(tokens), complete, reason:complete ? null : 'unapproved'}; }
    catch (error) { result = invalid(error.message === 'division_by_zero' ? error.message : 'invalid'); }
    visiting.delete(key); results[key] = result; return result;
  };
  for (const key of byName.keys()) results[key] = resolve(key);
  return results;
}
export const reportFormulaValue = (parent, formula) => evaluateReportFormulas(parent)[formulaKey(formula?.name)]?.value ?? '';
export const formulaResultHint = (parent, formula) => {
  const state = evaluateReportFormulas(parent)[formulaKey(formula?.name)];
  return ({ missing:'بانتظار إدخال نتائج التحاليل المرتبطة', unapproved:'بانتظار اعتماد التحاليل المرتبطة',
    cycle:'توجد حلقة في ارتباط المعادلات', division_by_zero:'تعذر الحساب: القسمة على صفر', invalid:'راجع إعداد المعادلة' })[state?.reason] || '';
};
