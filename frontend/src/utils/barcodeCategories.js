import { labelConfig, labelData, sampleGroups } from './barcodeLabels.js';

// Old invoices can contain JSON strings or keyed child collections.
const children = value => {
  for (let i = 0; i < 2 && typeof value === 'string'; i++) {
    try { value = JSON.parse(value); } catch { return []; }
  }
  return value && typeof value === 'object' ? Object.values(value).filter(x => x && typeof x === 'object') : [];
};
const text = value => typeof value === 'string' ? value.trim() : '';
export const barcodeCopies = value => Math.min(20, Math.max(0, Math.trunc(Number(value) || 0)));

export function barcodeCategories(record = {}) {
  const categories = new Map();
  const add = (test, kind, parents) => {
    const categoryName = text(test.category?.name) || text(test.category) || text(test.category_name);
    const categoryId = test.category_id_fk ?? test.category?.id;
    const key = categoryId != null ? `id:${categoryId}` : categoryName ? `name:${categoryName}` : 'uncategorized';
    if (!categories.has(key)) categories.set(key, { key, name: categoryName || 'غير مصنف', samples: new Map() });
    const category = categories.get(key);
    const sampleName = text(test.sample_name?.sample_name) || text(test.sample_name) || text(test.sample?.sample_name) || text(test.sample) || 'Other';
    if (!category.samples.has(sampleName)) category.samples.set(sampleName, { sampleName, tests: [], analyses: [], groupNames: [], seen: new Set() });
    const sample = category.samples.get(sampleName);
    const name = text(test.report_name) || text(test.name) || text(test.shortcut) || 'تحليل';
    const shortcut = text(test.shortcut);
    const id = kind === 'culture' ? test.culture_id_fk ?? test.id : test.test_id_fk ?? test.id;
    const identity = `${kind}:${id ?? name}`;
    if (!sample.seen.has(identity)) {
      sample.seen.add(identity);
      sample.analyses.push({ key: identity, name, shortcut });
      const caption = shortcut || name;
      if (!sample.tests.includes(caption)) sample.tests.push(caption);
    }
    for (const parent of parents) if (parent && !sample.groupNames.includes(parent)) sample.groupNames.push(parent);
  };
  const visit = (node, parents = []) => {
    children(node.tests).forEach(test => add(test, 'test', parents));
    children(node.cultures).forEach(test => add(test, 'culture', parents));
    for (const group of [...children(node.test_groups), ...children(node.packages)]) {
      visit(group, [...parents, text(group.group_name) || text(group.name)]);
    }
  };
  visit(record);
  return [...categories.values()].map(category => ({ ...category,
    samples: [...category.samples.values()].map(({ seen, ...sample }) => sample),
  }));
}

export function categoryLabelCount(categories, copies) {
  return categories.reduce((total, category) => total + category.samples.length * barcodeCopies(copies[category.key]), 0);
}

export function invoiceBarcodeLabels(record, settings, categories, copies, date = '') {
  if (!record?.barcode) return [];
  const config = labelConfig(settings.barcode_config, settings.show_tests_on_barcode !== false);
  const selected = copies == null
    ? sampleGroups(record).map(group => ({ group, copies: config.copies }))
    : categories.flatMap(category => category.samples.map(sample => ({
      group: { ...sample, groupNames: [...new Set([category.name, ...sample.groupNames])] }, copies: barcodeCopies(copies[category.key]),
    })));
  return selected.flatMap(({ group, copies }) => Array.from({ length: copies }, () => labelData(record, group, settings.lab_display_name, date)));
}
