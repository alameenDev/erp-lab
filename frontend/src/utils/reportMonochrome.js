// Print palette, not a blanket grayscale filter: text must remain legible.
// Keep this shared by the settings preview and the canonical report output.
const hexColor = value => {
  const raw = String(value || '').trim().replace(/^#/, '');
  if (!/^(?:[\da-f]{3}|[\da-f]{4}|[\da-f]{6}|[\da-f]{8})$/i.test(raw)) return null;
  const full = raw.length <= 4 ? [...raw].map(c => c + c).join('') : raw;
  const alpha = full.length === 8 ? parseInt(full.slice(6), 16) / 255 : 1;
  return [0, 2, 4].map(i => Math.round(parseInt(full.slice(i, i + 2), 16) * alpha + 255 * (1 - alpha)));
};
const gray = rgb => Math.round(rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722);
const hex = value => '#' + Math.round(value).toString(16).padStart(2, '0').repeat(3);
const luminance = value => { const n = value / 255; return n <= .04045 ? n / 12.92 : ((n + .055) / 1.055) ** 2.4; };
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + .05) / (Math.min(luminance(a), luminance(b)) + .05);
const isDefault = (value, original) => !value || JSON.stringify(hexColor(value)) === JSON.stringify(hexColor(original));
const background = (value, original, fallback) => isDefault(value, original) ? fallback : gray(hexColor(value) || hexColor(hex(fallback)));
const text = (value, original, bg) => {
  const candidate = isDefault(value, original) ? 0 : gray(hexColor(value) || [0, 0, 0]);
  // Preserve a custom dark/light gray only when it is sufficiently distinct.
  return contrast(candidate, bg) >= 7 ? candidate : (contrast(0, bg) >= contrast(255, bg) ? 0 : 255);
};

export function reportMonochromePalette(config = {}) {
  config = config || {};
  const head = background(config.header_bg_color, '#f1f5f9', 255);
  const body = background(config.body_bg_color, '#ffffff', 255);
  const section = background(config.header_bg_color, '#f1f5f9', 232);
  const border = isDefault(config.border_color, '#e2e8f0') ? 184
    : Math.min(192, gray(hexColor(config.border_color) || [184, 184, 184]));
  return {
    header: hex(head), body: hex(body), section: hex(section), border: hex(border),
    headerText: hex(text(config.header_color, '#0f172a', head)),
    bodyText: hex(text(config.body_color, '#1e293b', body)),
    sectionText: hex(text(config.header_color, '#0f172a', section)),
    sectionBorder: isDefault(config.border_color, '#e2e8f0') ? '#404040' : hex(border),
  };
}

export function getReportMonochromeCss(settings = {}) {
  if (![true, 1, '1'].includes(settings.print_black_white)) return '';
  const p = reportMonochromePalette(settings.print_table_config);
  // This explicit mode scope outranks the older, unscoped B&W reset still
  // appended by legacy callers. Never change the colored report or admin UI.
  const r = ':is(#Result, .report-template-preview).report-monochrome';
  const table = `${r} table:not(.print-wrapper):not(.info-table)`;
  return `
    ${r}, ${r} * {
      color: #000 !important; background-color: transparent !important;
      background-image: none !important; border-color: ${p.border} !important;
      text-shadow: none !important; box-shadow: none !important;
      -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
    }
    ${table} > thead > tr > th { color: ${p.headerText} !important; background-color: ${p.header} !important; border-color: ${p.border} !important; }
    ${table} > thead > tr > th * { color: ${p.headerText} !important; }
    ${table} > tbody > tr > td { color: ${p.bodyText} !important; background-color: ${p.body} !important; border-color: ${p.border} !important; }
    ${table} > tbody > tr > td * { color: ${p.bodyText} !important; }
    ${table}.report-results-table > tbody > tr > td { font-weight: 400 !important; }
    ${r} .report-results-table > tbody > tr:nth-child(even) > td { background-image: none !important; }
    ${r} .section-header, ${r} .caption, ${r} .bg-gray-300 {
      color: ${p.sectionText} !important; background-color: ${p.section} !important;
      border-color: ${p.sectionBorder} !important; font-weight: 700 !important;
    }
    ${r} .section-header *, ${r} .caption *, ${r} .bg-gray-300 * { color: ${p.sectionText} !important; }
    ${r} .mr-patient-title, ${r} .mr-detail-icon { background-color: #e8e8e8 !important; color: #000 !important; }
    ${r} .mr-title-icon, ${r} .mr-section-icon { background-color: #404040 !important; color: #fff !important; }
    ${table} > tbody > tr > td .mr-flag { background-color: #ededed !important; color: #000 !important; border-color: #808080 !important; }
    ${table} > tbody > tr > td .mr-flag * { color: #000 !important; }
    ${r} img, ${r} svg { filter: grayscale(1) !important; }
  `;
}

// CSS image filters are not sufficient for a canvas export or a separately
// composed letterhead. Convert the FINAL sheet without thresholding images,
// changing alpha, or washing out already-black text and chosen gray fills.
export function grayscaleReportPixels(data) {
  for (let i = 0; i < data.length; i += 4) {
    const value = Math.round(data[i] * .2126 + data[i + 1] * .7152 + data[i + 2] * .0722);
    data[i] = data[i + 1] = data[i + 2] = value;
  }
  return data;
}

export function grayscaleReportCanvas(canvas) {
  const context = canvas.getContext('2d');
  const image = context.getImageData(0, 0, canvas.width, canvas.height);
  grayscaleReportPixels(image.data);
  context.putImageData(image, 0, 0);
}
