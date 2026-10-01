// Semantic keys keep widths attached to the right column in both templates.
export const reportColumnDefaults = Object.freeze({ test: 28, result: 14, unit: 11, reference: 23, last_result: 10, status: 14 });
export const customReportColumns = (config) => [true, 1, '1'].includes(config?.custom_column_widths);

export function reportColumnKeys(settings = {}) {
  const modern = settings.report_template === 'modern';
  return ['test', 'result', ...(modern && settings.show_status !== false ? ['status'] : []), 'unit', 'reference',
    ...(settings.show_last_result === true ? ['last_result'] : []), ...(!modern && settings.show_status !== false ? ['status'] : [])];
}

// Allocate integer percentages, at least 5% per visible column, totalling 100.
// Largest remainders avoid drift when settings are saved/reloaded repeatedly.
function distribute(keys, weights, total) {
  const spare = total - keys.length * 5;
  let values = keys.map(key => Math.max(0, weights[key] - 5));
  if (!values.some(Boolean)) values = keys.map(() => 1);
  const sum = values.reduce((a, b) => a + b, 0);
  const raw = values.map(value => 5 + spare * value / sum);
  const rounded = raw.map(Math.floor);
  const order = raw.map((value, i) => ({ i, fraction: value - rounded[i] })).sort((a, b) => b.fraction - a.fraction);
  const remaining = total - rounded.reduce((a, b) => a + b, 0);
  for (let i = 0; i < remaining; i++) rounded[order[i].i]++;
  return Object.fromEntries(keys.map((key, i) => [key, rounded[i]]));
}

export function reportColumnWidths(settings = {}) {
  const saved = settings.print_table_config?.column_widths || {};
  const weights = Object.fromEntries(Object.entries(reportColumnDefaults).map(([key, fallback]) => {
    const value = Number(saved[key]);
    return [key, Number.isFinite(value) && value >= 5 && value <= 85 ? value : fallback];
  }));
  // Stable allocation regardless of the visual order of Flag/Status.
  const keys = Object.keys(reportColumnDefaults).filter(key => reportColumnKeys(settings).includes(key));
  return distribute(keys, weights, 100);
}

export function resizeReportColumn(settings, key, input) {
  const current = reportColumnWidths(settings);
  if (!(key in current) || input === '' || !Number.isFinite(Number(input))) return current;
  const others = Object.keys(current).filter(name => name !== key);
  const width = Math.max(5, Math.min(100 - others.length * 5, Math.round(Number(input))));
  return { ...distribute(others, current, 100 - width), [key]: width };
}

// This CSS accompanies the canonical DOM into preview, PDF, print and WhatsApp.
// A colgroup is the sole source of width; long values wrap without clipping.
export const reportColumnCss = `
  :is(#Result, .report-template-preview) table.report-results-table.report-column-widths {
    table-layout: fixed !important; width: 100% !important;
  }
  :is(#Result, .report-template-preview) table.report-results-table.report-column-widths > thead > tr > th,
  :is(#Result, .report-template-preview) table.report-results-table.report-column-widths > tbody > tr > td {
    width: auto !important; min-width: 0 !important; white-space: normal !important;
    overflow-wrap: anywhere !important; word-break: normal !important;
  }
  :is(#Result, .report-template-preview) table.report-column-widths .mr-flag {
    max-width: 100%; flex-wrap: wrap; box-sizing: border-box;
  }
`;
