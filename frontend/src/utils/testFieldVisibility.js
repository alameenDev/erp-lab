const enabled = value => ![false, 0, '0', 'false'].includes(value);
export const showsUnit = test => enabled(test?.show_unit);
export const showsReferenceRange = test => enabled(test?.show_reference_range);

// Groups/packages retain their column positions. Independent tables can use
// the freed space when none of their printed rows needs an optional column.
export function resultFieldColumns(items = [], preserve = false) {
  return {
    show_unit: preserve || items.some(showsUnit),
    show_reference_range: preserve || items.some(showsReferenceRange),
  };
}

// Custom templates own their geometry. Clear identified field cells instead
// of deleting columns, changing spans or touching result placeholders/inputs.
// Only explicit field markers or exact table headings identify a field.
export function visibleTemplateFields(html, test) {
  if ((showsUnit(test) && showsReferenceRange(test)) || !html || typeof DOMParser === 'undefined') return html;
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const hidden = field => field === 'unit' ? !showsUnit(test) : field === 'reference' && !showsReferenceRange(test);
  const fieldName = text => {
    const value = text.toLowerCase().replace(/[\s_():\-]/g, '');
    if (['unit', 'units', 'theunit', 'الوحدة', 'الوحده', 'يونت'].includes(value)) return 'unit';
    if (['reference', 'range', 'ranges', 'referencerange', 'referenceranges', 'normal', 'normalrange', 'normalranges', 'المعدلالطبيعي', 'المدىالطبيعي', 'النورمل'].includes(value)) return 'reference';
    return null;
  };
  for (const table of doc.querySelectorAll('table')) {
    const occupied = [], cells = [];
    for (const [r, row] of [...table.rows].entries()) {
      occupied[r] ||= []; let c = 0;
      for (const cell of row.cells) {
        while (occupied[r][c]) c++;
        const cols = Array.from({ length: cell.colSpan || 1 }, (_, i) => c + i);
        for (let y = r; y < r + (cell.rowSpan || 1); y++) {
          occupied[y] ||= [];
          for (const x of cols) occupied[y][x] = true;
        }
        cells.push({ cell, cols, heading: cell.tagName === 'TH' || row.parentElement.tagName === 'THEAD' || r === 0 });
        c += cols.length;
      }
    }
    const hiddenCols = new Set(cells.filter(({ cell, heading }) => heading && hidden(fieldName(cell.textContent.trim())))
      .flatMap(({ cols }) => cols));
    for (const { cell, cols } of cells) {
      if (cols.every(c => hiddenCols.has(c))) cell.replaceChildren();
    }
  }
  for (const node of doc.querySelectorAll('[data-result-field]')) {
    if (hidden(node.dataset.resultField)) node.replaceChildren();
  }
  return doc.body.innerHTML;
}
