import { labelConfig, labelData } from './barcodeLabels.js';

// Old invoices can contain JSON strings or keyed child collections.
const children = value => {
  for (let i = 0; i < 2 && typeof value === 'string'; i++) {
    try { value = JSON.parse(value); } catch { return []; }
  }
  return value && typeof value === 'object' ? Object.values(value).filter(x => x && typeof x === 'object') : [];
};
const text = value => typeof value === 'string' ? value.trim() : '';
export const barcodeCopies = value => Math.min(20, Math.max(0, Math.trunc(Number(value) || 0)));

export function barcodeSamples(record = {}) {
  const samples = new Map();
  const add = (test, kind, parents) => {
    const sampleName = text(test.sample_name?.sample_name) || text(test.sample_name) || text(test.sample?.sample_name) || text(test.sample) || 'Other';
    // Snapshot shapes can omit sample IDs. Group by the normalized sample name
    // so direct tests, groups and packages share one copy counter.
    const key = `sample:${sampleName.toLocaleLowerCase()}`;
    if (!samples.has(key)) samples.set(key, { key, sampleName, tests: [], analyses: [], groupNames: [], seen: new Set() });
    const sample = samples.get(key);
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
  return [...samples.values()].map(({ seen, ...sample }) => sample);
}

export function sampleLabelCount(samples, copies) {
  return samples.reduce((total, sample) => total + barcodeCopies(copies[sample.key]), 0);
}

export function invoiceBarcodeLabels(record, settings, samples, copies, date = '') {
  if (!record?.barcode) return [];
  const config = labelConfig(settings.barcode_config, settings.show_tests_on_barcode !== false);
  const selected = copies == null
    ? (samples.length ? samples : [{ sampleName: '', tests: [], groupNames: [] }]).map(group => ({ group, copies: config.copies }))
    : samples.map(group => ({ group, copies: barcodeCopies(copies[group.key]) }));
  return selected.flatMap(({ group, copies }) => Array.from({ length: copies }, () => labelData(record, group, settings.lab_display_name, date)));
}
