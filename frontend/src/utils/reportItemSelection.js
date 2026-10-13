import { formulaDefinitions, formulaTestMatches, evaluateReportFormulas } from './reportFormulas.js';

export const reportItemName = item => item.report_name || item.group_name || item.name || 'فحص';
const copy = value => JSON.parse(JSON.stringify(value));
const id = test => String(test.test_id_fk ?? test.culture_id_fk ?? test.id ?? test.name);
const groupId = group => String(group.test_group_id_fk ?? group.id ?? group.group_name ?? group.name);
const belongs = (item, group) => item.test_group_id_fk != null
  ? String(item.test_group_id_fk) === groupId(group)
  : Boolean(item.test_group_name) && item.test_group_name === (group.group_name || group.name);

// The invoice API carries package results in flattened tests/cultures. Nested
// catalog membership supplies headings, not a second copy of those results.
function prepareParent(source, packaged = false) {
  const parent = copy(source);
  parent.tests ||= []; parent.cultures ||= [];
  if (packaged) {
    for (const group of parent.test_groups || []) {
      group.test_group_id_fk ??= group.id;
      for (const field of ['tests', 'cultures']) {
        for (const item of group[field] || []) {
          if (!parent[field].some(t => id(t) === id(item))) parent[field].push({ ...item,
            test_group_id_fk: group.test_group_id_fk ?? group.id,
            test_group_name: group.group_name || group.name });
        }
      }
    }
  }
  const values = evaluateReportFormulas(parent);
  const addTargets = (owner, group = null) => {
    for (const f of owner.formula || []) {
      if (!parent.tests.some(t => formulaTestMatches(t, f.name))) parent.tests.push({
        name: f.name, shortcut: f.name, unit: f.unit || '', result: values[f.name]?.value ?? '',
        is_done: values[f.name]?.complete ?? false,
        ...(group ? { test_group_id_fk: group.test_group_id_fk ?? group.id, test_group_name: group.group_name || group.name } : {}),
      });
    }
  };
  addTargets(parent);
  if (packaged) for (const group of parent.test_groups || []) addTargets(group, group);
  return parent;
}
const leaf = (item, key) => ({ item, key, name: reportItemName(item), children: [] });
export const reportNodeKeys = node => node.children.length ? node.children.flatMap(reportNodeKeys) : [node.key];
export function reportSelectionModel(record) {
  const normalized = {
    tests: copy(record?.tests || []), cultures: copy(record?.cultures || []),
    test_groups: (record?.test_groups || []).map(g => prepareParent(g)),
    packages: (record?.packages || []).map(p => prepareParent(p, true)),
  };
  const parentNode = (item, key, packaged) => {
    const children = ['tests', 'cultures'].flatMap(field => item[field].map((t, i) => leaf(t, `${key}/${field}:${i}`)));
    if (packaged) {
      const grouped = new Set();
      const groups = (item.test_groups || []).map((group, i) => {
        const rows = children.filter(n => belongs(n.item, group));
        rows.forEach(n => grouped.add(n.key));
        return { ...leaf(group, `${key}/group:${i}`), children: rows };
      }).filter(n => n.children.length);
      return { ...leaf(item, key), children: [...children.filter(n => !grouped.has(n.key)), ...groups] };
    }
    return { ...leaf(item, key), children };
  };
  const sections = [ ['tests','التحاليل'], ['cultures','الزروع'], ['test_groups','الكروبات'], ['packages','الباقات'] ]
    .map(([key, label]) => ({ key, label, nodes: normalized[key].map((item, i) =>
      ['tests','cultures'].includes(key) ? leaf(item, `${key}:${i}`) : parentNode(item, `${key}:${i}`, key === 'packages')) }));
  return { normalized, sections, keys: sections.flatMap(s => s.nodes.flatMap(reportNodeKeys)) };
}
export function searchReportNodes(nodes, term) {
  const q = term.trim().toLocaleLowerCase();
  if (!q) return nodes;
  return nodes.flatMap(node => {
    if (node.name.toLocaleLowerCase().includes(q)) return [node];
    const children = searchReportNodes(node.children, term);
    // Retain the original children for whole-group toggles and partial state.
    return children.length ? [{ ...node, visibleChildren: children }] : [];
  });
}

export function selectReportItems(record, selection) {
  const model = reportSelectionModel(record);
  // Compatibility for callers which still supply top-level index lists.
  const keys = selection.selectedKeys ?? model.sections.flatMap(s => s.nodes.flatMap((n, i) =>
    (selection[s.key === 'test_groups' ? 'testGroups' : s.key] || []).includes(i) ? reportNodeKeys(n) : []));
  const selected = new Set(keys);
  const filterParent = (parent, key, packaged) => {
    const tests = parent.tests.filter((_, i) => selected.has(`${key}/tests:${i}`));
    const cultures = parent.cultures.filter((_, i) => selected.has(`${key}/cultures:${i}`));
    const formulas = owner => (owner.formula || []).filter(f => tests.some(t => formulaTestMatches(t, f.name)));
    return { ...parent, tests, cultures, formula: formulas(parent),
      // Hidden operands stay available to calculate chosen results, never to render.
      report_calculation_tests: parent.tests, calculation_formulas: formulaDefinitions(parent),
      ...(packaged ? { test_groups: (parent.test_groups || []).flatMap(g => {
        const rows = tests.filter(t => belongs(t, g)), cultureRows = cultures.filter(t => belongs(t, g));
        return rows.length || cultureRows.length ? [{ ...g, tests: rows, cultures: cultureRows, formula: formulas(g) }] : [];
      }) } : {}),
    };
  };
  const result = {};
  for (const section of model.sections) result[section.key] = section.nodes.flatMap((node, i) => {
    if (!reportNodeKeys(node).some(k => selected.has(k))) return [];
    const parent = model.normalized[section.key][i];
    return [['tests','cultures'].includes(section.key) ? parent : filterParent(parent, node.key, section.key === 'packages')];
  });
  result.suppress_report_qr = Boolean(record?.suppress_report_qr) || model.keys.some(k => !selected.has(k));
  return result;
}
