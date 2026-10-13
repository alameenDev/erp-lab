export function resultHistoryRows(record, item, type = 'test') {
  const id = item?.[`${type}_id_fk`] || item?.id;
  if (!id) return [];
  const rows = record?.result_history?.[`${type}_${id}`];
  return Array.isArray(rows) ? rows : [];
}

export function previousResultsCount(settings) {
  const count = Number(settings?.previous_results_count ?? 1);
  return Number.isInteger(count) && count >= 1 && count <= 10 ? count : 1;
}

// Output-only selection. Keep the complete history available to editors/charts.
// Count prior examinations, retaining every component of a compound test (CBC,
// for example) from each selected invoice instead of truncating its field rows.
export function reportHistoryRows(record, item, type = 'test', settings = {}, kind = 'all') {
  if (settings?.show_last_result !== true) return [];
  const rows = resultHistoryRows(record, item, type)
    .filter(row => row.value !== null && row.value !== undefined && String(row.value).trim() !== ''
      && !(record?.id != null && row.invoice_id != null && String(row.invoice_id) === String(record.id)))
    .slice().sort((a, b) => String(b.date || '').localeCompare(String(a.date || ''))
      || Number(b.invoice_id || 0) - Number(a.invoice_id || 0));
  const visits = new Set(), seen = new Set(), selected = [];
  const limit = previousResultsCount(settings);
  for (const row of rows) {
    const visit = row.invoice_id != null ? `invoice:${row.invoice_id}` : `date:${row.date || ''}`;
    if (!visits.has(visit) && visits.size >= limit) continue;
    visits.add(visit);
    const key = JSON.stringify([visit, row.field, row.value, row.unit]);
    if (seen.has(key)) continue;
    seen.add(key);
    // Compatibility with history responses cached before component metadata.
    const component = row.is_component ?? Boolean(row.field && row.field !== item?.name);
    if (kind === 'scalar' && component || kind === 'components' && !component) continue;
    selected.push(row);
  }
  return selected;
}

// Preserve the recorded calendar date without browser time-zone conversion.
export function historyDisplayDate(raw) {
  const match = String(raw || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  return match ? `${match[3]}/${match[2]}/${match[1]}` : String(raw || '—');
}
