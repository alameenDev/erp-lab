import { resultHistoryRows, reportHistoryRows, historyDisplayDate } from './resultHistory.js';
import { showsUnit } from './testFieldVisibility.js';

export const isDeviceCbc = item => item?.content?.bridge_cbc === true;
export const cbcHistoryLayout = settings => settings?.print_table_config?.cbc_previous_results_layout === 'separate_page' ? 'separate_page' : 'inline';
export function cbcHistoryEnabled(item, settings = {}) {
  if (!isDeviceCbc(item)) return false;
  return item.last_result == null ? settings.show_last_result === true : [true, 1, '1', 'true'].includes(item.last_result);
}
const fieldKey = value => String(value || '').trim().toUpperCase();
const valid = row => row.value !== null && row.value !== undefined && !['', 'null'].includes(String(row.value).trim());
const newestFirst = (a, b) => String(b.date || '').localeCompare(String(a.date || '')) || Number(b.invoice_id || 0) - Number(a.invoice_id || 0);

// Match the persisted test ID and exact component name, never a test display name,
// field position or guessed analyzer alias. Preserve source units; do not convert.
export function cbcPreviousResult(record, item, field, settings = {}) {
  if (!cbcHistoryEnabled(item, settings)) return null;
  const rows = resultHistoryRows(record, item).filter(row => valid(row)
    && row.is_component !== false && fieldKey(row.field) === fieldKey(field)
    && !(record?.id != null && String(row.invoice_id) === String(record.id))).slice().sort(newestFirst);
  if (!rows.length) return null;
  const latest = rows[0];
  const sameVisit = rows.filter(row => row.invoice_id != null && latest.invoice_id != null
    ? String(row.invoice_id) === String(latest.invoice_id) : row.date === latest.date);
  const distinct = new Set(sameVisit.map(row => JSON.stringify([row.item_id ?? null, String(row.value), row.unit || ''])));
  return distinct.size === 1 ? latest : { ambiguous: true };
}

export function cbcSeparateHistory(record, item, settings = {}) {
  if (!cbcHistoryEnabled(item, settings)) return [];
  const key = `test_${item.test_id_fk || item.id}`;
  const components = resultHistoryRows(record, item).filter(row => valid(row)
    && (row.is_component ?? Boolean(row.field && row.field !== item.name)));
  return reportHistoryRows({ ...record, result_history: { [key]: components } }, item,
    'test', { ...settings, show_last_result: true }, 'components');
}

// Decorate the existing server-generated CBC table without replacing current
// values, flags, ranges, comments or fonts. All history is inserted as text.
export function cbcHistoryHtml(html, record, item, settings = {}) {
  if (!cbcHistoryEnabled(item, settings) || cbcHistoryLayout(settings) !== 'inline') return html;
  const document = new DOMParser().parseFromString(html, 'text/html');
  const table = [...document.querySelectorAll('table')].find(table => {
    const headers = [...(table.tHead?.rows[0]?.cells || [])].map(cell => cell.textContent.trim());
    return headers[0] === 'Test' && headers[1] === 'Result';
  });
  if (!table || table.querySelector('[data-cbc-last-result]')) return html;
  const cellStyle = 'border:1px solid #cbd5e1;padding:4px 6px;text-align:left;vertical-align:middle;overflow-wrap:anywhere;';
  const header = document.createElement('th');
  header.textContent = 'Last Result'; header.scope = 'col'; header.dataset.cbcLastResult = 'true'; header.style.cssText = cellStyle;
  table.tHead.rows[0].cells[1].after(header);
  for (const body of table.tBodies) for (const row of body.rows) {
    if (row.cells.length < 2) continue;
    const previous = cbcPreviousResult(record, item, row.cells[0].textContent, settings);
    const cell = document.createElement('td'); cell.dataset.cbcLastResult = 'true'; cell.style.cssText = cellStyle;
    const value = document.createElement('div'); value.style.fontWeight = '600';
    value.textContent = previous?.ambiguous ? 'Multiple prior results' : previous ? String(previous.value) : '—';
    cell.append(value);
    if (previous && !previous.ambiguous) {
      if (showsUnit(item) && previous.unit) {
        const unit = document.createElement('small'); unit.textContent = previous.unit; unit.style.display = 'block'; cell.append(unit);
      }
      const time = document.createElement('time'); time.textContent = historyDisplayDate(previous.date);
      time.dateTime = previous.date || ''; time.style.cssText = 'display:block;font-size:9px;color:#475569;'; cell.append(time);
      if (previous.date_source === 'registration') {
        const note = document.createElement('small'); note.textContent = 'Registration date'; note.style.display = 'block'; cell.append(note);
      }
    }
    row.cells[1].after(cell);
  }
  return document.body.innerHTML;
}
