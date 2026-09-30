// PrimeIcons 7 glyphs and font from the app's existing icon library.
const reportIconFont = new URL('../../node_modules/primeicons/fonts/primeicons.woff2', import.meta.url).href;
const primeIconsCss = `@font-face { font-family: primeicons; src: url("${reportIconFont}") format("woff2"); font-weight: normal; font-style: normal; }`;

// This CSS travels with the source DOM into the shared page renderer. Keeping
// it here makes previews, PDF downloads, native print and WhatsApp identical.
export function getReportTemplateCss(settings = {}) {
  if (settings.report_template !== 'modern') return '';
  const header = settings.patient_header_config || {};
  const table = settings.print_table_config || {};
  const s = ':is(#Result, .report-template-preview).report-modern';
  const info = header.info_size || 13;
  const border = table.border_color || '#e2e8f0';
  return `${primeIconsCss}
    ${s} { font-family: Arial, sans-serif; direction: ltr; text-align: left; text-transform: none; color: #172f42; }
    ${s} * { box-sizing: border-box; }
    ${s} .pi-id-card:before { content: '\\e9aa'; }
    ${s} .pi-filter:before { content: '\\e94c'; }
    ${s} .pi-users:before { content: '\\e941'; }
    ${s} .pi-clock:before { content: '\\e940'; }
    ${s} .pi-user-plus:before { content: '\\e93f'; }
    ${s} .pi-user:before { content: '\\e939'; }
    ${s} .pi-calendar:before { content: '\\e927'; }
    ${s} .pi-check:before { content: '\\e909'; }
    ${s} .pi-arrow-down:before { content: '\\e919'; }
    ${s} .pi-arrow-up:before { content: '\\e91c'; }
    ${s} .pi { font-family: primeicons !important; font-weight: normal !important; font-style: normal; line-height: 1; text-transform: none; }
    ${s} .mr-patient-card { border: 1px solid #c9dfe2; border-radius: 10px; overflow: hidden; margin: 0 0 20px; background: #fff; }
    ${s} .mr-patient-title { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #edf7f7; border-bottom: 1px solid #d4e7e8; }
    ${s} .mr-patient-title strong { font-size: 19px !important; font-weight: 700 !important; color: #183b4d; }
    ${s} .mr-patient-title > span { margin-left: auto; font-size: 9px !important; letter-spacing: 1px; color: #657f89; font-weight: 400 !important; }
    ${s} .mr-title-icon, ${s} .mr-section-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 29px; width: 29px; height: 29px; background: #4b999a; border-radius: 6px; color: #fff; font-size: 16px !important; }
    ${s} .mr-patient-body { display: flex; align-items: stretch; gap: 14px; padding: 16px; }
    ${s} .mr-patient-column { flex: 1 1 0; min-width: 0; }
    ${s} .mr-patient-column + .mr-patient-column { border-left: 1px solid #e3ecee; padding-left: 14px; }
    ${s} .mr-detail { display: flex; align-items: flex-start; gap: 9px; margin: 0 0 13px; }
    ${s} .mr-detail:last-child { margin-bottom: 0; }
    ${s} .mr-detail-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 28px; width: 28px; height: 28px; margin-top: 3px; border-radius: 50%; background: #edf7f7; color: #5a8b90; font-size: 14px !important; }
    ${s} .mr-detail-text { min-width: 0; flex: 1; line-height: ${header.line_height || 1.7}; overflow-wrap: anywhere; }
    ${s} .mr-detail-label { display: block; color: #627a87; font-size: ${info}px !important; font-weight: 400 !important; }
    ${s} .mr-detail-text strong { display: block; font-size: ${info}px !important; font-weight: 700 !important; color: #172f42; text-align: left; }
    ${s} .mr-detail-text .mr-patient-name { font-size: ${header.name_size || 20}px !important; }
    ${s} .mr-qr { flex: 0 1 auto; min-width: 0; align-self: center; text-align: center; padding-left: 10px; border-left: 1px solid #e3ecee; }
    ${s} .mr-qr svg { display: block; width: ${header.qr_size || 90}px; height: ${header.qr_size || 90}px; max-width: 100%; }
    ${s} .mr-qr span { display: block; font-size: 10px !important; font-weight: 400 !important; line-height: 1.5; margin-top: 7px; color: #657f89; }
    ${s} .section-header { display: flex; align-items: center; gap: 10px; padding: 10px 13px !important; text-align: left !important; border: 1px solid ${border} !important; border-radius: 8px 8px 0 0; background: #eff7f7 !important; color: #193b4b !important; margin-bottom: 0 !important; font-size: ${table.header_font_size || 13}px !important; }
    ${s} .section-header > span { flex: 1; min-width: 0; overflow-wrap: anywhere; font-weight: 700 !important; }
    ${s} .mr-section-caption { margin-left: auto; font-size: 8px !important; letter-spacing: .8px; font-weight: 400 !important; color: #6b838c; }
    ${s} .report-results-table { width: 100%; table-layout: auto !important; border-collapse: separate !important; border-spacing: 0; border: 1px solid ${border}; border-radius: 0 0 8px 8px; overflow: hidden; margin: 0 0 ${table.section_spacing ?? 12}px !important; }
    ${s} .report-results-table > thead > tr > th,
    ${s} .report-results-table > tbody > tr > td { text-align: left !important; vertical-align: middle; line-height: 1.65; padding: ${table.cell_padding ?? 6}px ${table.cell_padding ?? 6}px !important; border-width: 0 1px 1px 0 !important; overflow-wrap: anywhere; font-weight: 400 !important; }
    ${s} .report-results-table > thead > tr > th { font-weight: ${table.header_font_weight || 'bold'} !important; }
    ${s} .report-results-table > thead > tr > th:first-child { width: 30%; }
    ${s} .report-results-table > tbody > tr > td:first-child { font-weight: 600 !important; }
    ${s} .report-results-table > tbody > tr > td:nth-child(2) { font-weight: 700 !important; }
    ${s} .report-results-table > thead > tr > th:last-child,
    ${s} .report-results-table > tbody > tr > td:last-child { border-right-width: 0 !important; }
    ${s} .report-results-table > tbody > tr:last-child > td { border-bottom-width: 0 !important; }
    ${s} .report-results-table > tbody > tr:nth-child(even) > td { background-image: linear-gradient(rgba(64, 125, 135, .04), rgba(64, 125, 135, .04)) !important; }
    ${s} .report-results-table p, ${s} .report-results-table span { margin: 0; font-weight: inherit !important; }
    ${s} .mr-flag { display: inline-flex; align-items: center; justify-content: center; gap: 5px; border-radius: 5px; padding: 2px 7px; font-size: .9em; line-height: 1.6; font-weight: 600 !important; border: 1px solid transparent; background: #eff3f6; color: #52636f; }
    ${s} .mr-flag .pi { font-size: .85em; flex-shrink: 0; }
    ${s} .mr-flag-high { background: #fce8e8; color: #b73f3f; border-color: #f5caca; }
    ${s} .mr-flag-normal { background: #e4f3eb; color: #277247; border-color: #bfdfce; }
    ${s} .mr-flag-low { background: #fff4d9; color: #846000; border-color: #ecd69a; }
    ${s} .mr-empty-flag { color: #738591; }
    ${s}.report-monochrome, ${s}.report-monochrome * { color: #000 !important; background-color: transparent !important; background-image: none !important; border-color: #000 !important; }
    ${s}.report-monochrome .report-results-table > tbody > tr:nth-child(even) > td { background-image: none !important; }
    ${s}.report-monochrome img { filter: grayscale(1) !important; }
  `;
}
