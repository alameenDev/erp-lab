import JsBarcode from 'jsbarcode';

export const labelFields = ['barcode', 'number', 'patient', 'age', 'gender', 'date', 'sample', 'tests', 'laboratory', 'groups', 'custom'];
export const fieldNames = {
  barcode: ['رمز الباركود', 'Barcode symbol'], number: ['رقم العينة', 'Sample number'],
  patient: ['اسم المريض', 'Patient name'], age: ['العمر', 'Age'], gender: ['الجنس', 'Gender'],
  date: ['التاريخ والوقت', 'Date & time'], sample: ['نوع العينة', 'Sample type'],
  tests: ['التحاليل', 'Tests'], laboratory: ['اسم المختبر', 'Laboratory'],
  groups: ['المجموعات والباقات', 'Groups & packages'], custom: ['نص إضافي', 'Custom text'],
};
const n = (value, fallback, min, max) => value !== '' && value != null && Number.isFinite(Number(value)) ? Math.min(max, Math.max(min, Number(value))) : fallback;
const bool = (value, fallback) => value == null ? fallback : ![false, 0, '0', 'false'].includes(value);
export const roundMm = value => Math.round(value * 100) / 100;

export function defaultLabel(width = 76.2, height = 38.1, old = {}, showTests = true) {
  const w = width - 4;
  const item = (x, y, width, h, font, visible = true, align = 'center', bold = false) => ({ x, y, width, height: h, font, visible, align, bold, rotation: 0 });
  const barH = n(old.barcode_height, 40, 20, 100) * 25.4 / 96;
  const compact = height < 30;
  const y = compact ? 4 : 6;
  const bh = Math.min(barH, height * 0.29);
  const patientY = y + bh + 0.8;
  const patientH = compact ? 4 : 5;
  const infoY = patientY + patientH + 0.3;
  return {
    version: 2, width_mm: width, height_mm: height, dpi: 203, format: 'CODE128',
    quiet_modules: 10, offset_x: 0, offset_y: 0, copies: 1, custom_text: '',
    elements: {
      barcode: item(2, y, w, bh, 8),
      number: item(2, 0.7, w * 0.5, 3.3, n(old.number_size, 8, 4, 24), true, 'left', true),
      sample: item(2 + w * 0.5, 0.7, w * 0.5, 3.3, n(old.sample_size, 8, 4, 24), true, 'right', true),
      patient: item(2, patientY, w, patientH, n(old.name_size, compact ? 8 : 10, 4, 24), true, 'center', true),
      age: item(2, infoY, w * 0.24, 3.2, n(old.info_size, compact ? 5.5 : 7, 4, 24), true, 'left'),
      gender: item(2 + w * 0.24, infoY, w * 0.24, 3.2, n(old.info_size, compact ? 5.5 : 7, 4, 24)),
      date: item(2 + w * 0.48, infoY, w * 0.52, 3.2, n(old.info_size, compact ? 5.5 : 7, 4, 24), true, 'right'),
      tests: item(2, infoY + 3.8, w, Math.max(3, height - infoY - 4.5), n(old.tests_size, 7, 4, 24), showTests),
      laboratory: item(2, 1, w, 4, 9, false, 'center', true),
      groups: item(2, height - 5, w, 4, 7, false),
      custom: item(2, height - 5, w, 4, 8, false),
    },
  };
}

export function labelConfig(input, showTests = true) {
  const c = input && typeof input === 'object' ? input : {};
  const modern = Number(c.version) === 2;
  const width = roundMm(modern ? n(c.width_mm, 76.2, 25, 150) : n(c.label_width, 3, 1, 10) * 25.4);
  const height = roundMm(modern ? n(c.height_mm, 38.1, 15, 150) : n(c.label_height, 1.5, 0.5, 10) * 25.4);
  const base = defaultLabel(width, height, c, showTests);
  if (!modern) return base;
  return {
    ...base, dpi: [203, 300, 600].includes(Number(c.dpi)) ? Number(c.dpi) : 203,
    format: ['CODE128', 'CODE39'].includes(c.format) ? c.format : 'CODE128',
    quiet_modules: Math.round(n(c.quiet_modules, 10, 10, 30)),
    offset_x: n(c.offset_x, 0, -10, 10), offset_y: n(c.offset_y, 0, -10, 10),
    copies: Math.round(n(c.copies, 1, 1, 20)), custom_text: String(c.custom_text || '').slice(0, 120),
    elements: Object.fromEntries(labelFields.map(key => {
      const e = c.elements?.[key] || {}, d = base.elements[key];
      return [key, { x: n(e.x, d.x, 0, 150), y: n(e.y, d.y, 0, 150),
        width: n(e.width, d.width, 1, 150), height: n(e.height, d.height, 1, 150),
        font: n(e.font, d.font, 4, 32), align: ['left', 'center', 'right'].includes(e.align) ? e.align : d.align,
        bold: bool(e.bold, d.bold), visible: key === 'barcode' || bool(e.visible, d.visible),
        rotation: [0, 90, 180, 270].includes(Number(e.rotation)) ? Number(e.rotation) : 0 }];
    })),
  };
}

export function barcodeGeometry(value, config) {
  const c = labelConfig(config);
  const text = String(value ?? '');
  if (!text || text.length > 255 || (c.format === 'CODE39' && !/^[0-9A-Z .\-$/+%]+$/.test(text))) return { error: 'invalid' };
  try {
    const encoded = {};
    JsBarcode(encoded, text, { format: c.format, displayValue: false, width: 1, margin: 0 });
    const bits = encoded.encodings.map(part => part.data).join('');
    if (!bits || !/^[01]+$/.test(bits)) return { error: 'invalid' };
    const modules = bits.length + 2 * c.quiet_modules;
    const dots = Math.floor((c.elements.barcode.width * c.dpi / 25.4 + 1e-7) / modules);
    const minDots = Math.max(2, Math.ceil(0.19 * c.dpi / 25.4));
    if (dots < minDots) return { error: 'narrow', minimum: Math.ceil(modules * minDots * 25.4 / c.dpi * 100) / 100 };
    const bars = [];
    for (const match of bits.matchAll(/1+/g)) bars.push({ x: c.quiet_modules + match.index, width: match[0].length });
    return { bars, modules, dots, moduleMm: dots * 25.4 / c.dpi,
      width: modules * dots * 25.4 / c.dpi, height: c.elements.barcode.height };
  } catch { return { error: 'invalid' }; }
}

export function elementBox(e, c) {
  return { x: e.x + c.offset_x, y: e.y + c.offset_y,
    width: e.rotation % 180 ? e.height : e.width, height: e.rotation % 180 ? e.width : e.height };
}
export function elementStyle(e, c) {
  const transforms = { 0: '', 90: `translateX(${e.height}mm) rotate(90deg)`, 180: `translate(${e.width}mm, ${e.height}mm) rotate(180deg)`, 270: `translateY(${e.width}mm) rotate(270deg)` };
  return { position: 'absolute', left: `${e.x + c.offset_x}mm`, top: `${e.y + c.offset_y}mm`,
    width: `${e.width}mm`, height: `${e.height}mm`, fontSize: `${e.font}pt`,
    fontWeight: e.bold ? '700' : '400', textAlign: e.align, lineHeight: '1.12',
    transform: transforms[e.rotation], transformOrigin: 'top left', overflow: 'hidden',
    whiteSpace: 'pre-wrap', overflowWrap: 'anywhere', color: '#000', boxSizing: 'border-box' };
}
export function labelIssues(config, data) {
  const c = labelConfig(config), issues = [], geo = barcodeGeometry(data.barcode, c);
  if (geo.error) issues.push({ code: geo.error, key: 'barcode', minimum: geo.minimum });
  const boxes = [];
  for (const key of labelFields) {
    const e = c.elements[key];
    if (!e.visible || (key !== 'barcode' && !data[key])) continue;
    const box = elementBox(e, c);
    if (box.x < -0.01 || box.y < -0.01 || box.x + box.width > c.width_mm + 0.01 || box.y + box.height > c.height_mm + 0.01) issues.push({ code: 'outside', key });
    // Text needs at least one full line; actual wrapping is measured by the renderer.
    if (key !== 'barcode' && e.height < e.font * 25.4 / 72 * 1.12) issues.push({ code: 'text', key });
    boxes.push({ ...box, key });
  }
  for (let i = 0; i < boxes.length; i++) for (let j = i + 1; j < boxes.length; j++) {
    const a = boxes[i], b = boxes[j];
    if (Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x) > 0.1 && Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y) > 0.1) issues.push({ code: 'overlap', key: a.key, other: b.key });
  }
  return issues;
}

export function sampleGroups(record = {}) {
  const groups = new Map();
  const add = (t, name) => {
    const sampleName = t.sample_name || t.sample || 'Other';
    if (!groups.has(sampleName)) groups.set(sampleName, { sampleName, tests: [], groupNames: [] });
    const g = groups.get(sampleName), test = t.shortcut || t.name;
    if (test && !g.tests.includes(test)) g.tests.push(test);
    if (name && !g.groupNames.includes(name)) g.groupNames.push(name);
  };
  [...(record.tests || []), ...(record.cultures || [])].forEach(t => add(t));
  [...(record.packages || []), ...(record.test_groups || [])].forEach(g => [...(g.tests || []), ...(g.cultures || [])].forEach(t => add(t, g.group_name || g.name)));
  return groups.size ? [...groups.values()] : [{ sampleName: '', tests: [], groupNames: [] }];
}
export function labelData(record, group, laboratory, date) {
  const p = record.patient || {};
  return { barcode: String(record.barcode || ''), number: String(record.barcode || ''), patient: p.name || '',
    age: [p.age ?? '', p.age_unit || ''].filter(v => v !== '').join(' '), gender: p.gender || '', date: date || '',
    sample: group.sampleName, tests: group.tests.join(', '), groups: group.groupNames.join(', '), laboratory: laboratory || '' };
}
export function barcodePrintCss(config) {
  const c = labelConfig(config);
  return `@page { size:${c.width_mm}mm ${c.height_mm}mm; margin:0; }
    * { box-sizing:border-box; } html,body { margin:0; padding:0; width:${c.width_mm}mm; }
    body { font-family:Arial,sans-serif; background:#fff; color:#000; }
    .barcode-label { break-inside:avoid; page-break-inside:avoid; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .barcode-label + .barcode-label { break-before:page; page-break-before:always; }
    .barcode-editor-only { display:none !important; }
    svg { max-width:none; }`;
}
