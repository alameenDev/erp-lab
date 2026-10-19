const KINDS = ['barcode', 'report', 'invoice', 'thermal', 'job', 'worksheet', 'history', 'register'];
function validateJob(input) {
  if (!input || !/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(input.id || '')) throw new Error('INVALID_JOB_ID');
  if (!KINDS.includes(input.kind)) throw new Error('INVALID_DOCUMENT_TYPE');
  if (typeof input.printer !== 'string' || !input.printer.trim() || input.printer.length > 256 || /[\x00-\x1f]/.test(input.printer)) throw new Error('INVALID_PRINTER');
  if (!Number.isInteger(input.copies) || input.copies < 1 || input.copies > 20) throw new Error('INVALID_COPIES');
  if (typeof input.html !== 'string' || !input.html.trim() || Buffer.byteLength(input.html) > 127 * 1024 * 1024) throw new Error('INVALID_DOCUMENT');
  const p = input.paper;
  if (!p || !Number.isFinite(p.width) || p.width < 20 || p.width > 420 ||
    !(input.kind === 'thermal' && p.height === null) && (!Number.isFinite(p.height) || p.height < 10 || p.height > 2000)) throw new Error('INVALID_PAPER');
  return {id: input.id, kind: input.kind, printer: input.printer, copies: input.copies, html: input.html, paper: {width: p.width, height: p.height}};
}
module.exports = {validateJob, KINDS};
