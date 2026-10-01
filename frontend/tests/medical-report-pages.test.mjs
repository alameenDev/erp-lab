import test from 'node:test';
import assert from 'node:assert/strict';
import { reportMargins, reportGeometry, reportSheetCss, reportSlices } from '../src/utils/medicalReportPages.js';

test('asymmetric saved margins and zero remain unchanged; invalid values fall back', () => {
  assert.deepEqual(reportMargins({ top: '38', bottom: 27, left: 0, right: '12' }), { top: 38, right: 12, bottom: 27, left: 0 });
  assert.deepEqual(reportMargins({ top: 'bad', right: -1 }), { top: 20, right: 15, bottom: 20, left: 15 });
  assert.throws(() => reportMargins({ top: 160, bottom: 150 }));
});

test('margins move content while the A4 sheet geometry stays fixed', () => {
  const a = reportGeometry({ top: 45, right: 7, bottom: 25, left: 5 });
  const b = reportGeometry({ top: 62, right: 25, bottom: 38, left: 22 });
  assert.deepEqual(a.paper, b.paper);
  assert.ok(b.content.x > a.content.x && b.content.y > a.content.y);
  assert.ok(b.content.width < a.content.width && b.content.height < a.content.height);
  for (const g of [a, b]) {
    assert.ok(g.content.x + g.content.width <= g.paper.width);
    assert.ok(g.content.y + g.content.height <= g.paper.height);
  }
  assert.match(reportSheetCss, /@page[^}]*margin: 0/);
  assert.doesNotMatch(reportSheetCss, /position:fixed|top: -|left: -/);
});

test('continuation pages cover all pixels exactly once and stay within the printable height', () => {
  const slices = reportSlices(2537, 700, [[680, 730], [1360, 1440], [1980, 2050]]);
  assert.equal(slices[0][0], 0);
  assert.equal(slices.at(-1)[1], 2537);
  slices.forEach(([start, end], i) => {
    assert.ok(end > start && end - start <= 700);
    if (i) assert.equal(start, slices[i - 1][1]);
    for (const [top, bottom] of [[680, 730], [1360, 1440], [1980, 2050]]) {
      assert.ok(!(top < end && end < bottom));
    }
  });
});

test('an exact page fit does not generate a trailing empty sheet', () => {
  assert.deepEqual(reportSlices(1400, 700), [[0, 700], [700, 1400]]);
});

test('explicit page breaks and print-alone groups remain on new pages', () => {
  assert.deepEqual(reportSlices(1500, 700, [], [0, 320, 900]), [[0, 320], [320, 900], [900, 1500]]);
});

test('a long isolated group cannot share its final sheet with the next results', () => {
  // Before: 0–200, isolated group: 200–1800, after: 1800–1950.
  assert.deepEqual(reportSlices(1950, 700, [], [200, 1800]),
    [[0, 200], [200, 900], [900, 1600], [1600, 1800], [1800, 1950]]);
});

test('adjacent isolated groups and first/last boundaries do not add empty sheets', () => {
  assert.deepEqual(reportSlices(900, 700, [], [0, 300, 300, 600, 900]),
    [[0, 300], [300, 600], [600, 900]]);
});

test('a row larger than the page cannot hang pagination or discard results', () => {
  assert.deepEqual(reportSlices(1600, 700, [[0, 1600]]), [[0, 700], [700, 1400], [1400, 1600]]);
  assert.throws(() => reportSlices(100, 0));
});

test('shared rounded row borders do not collapse every sheet to a single row', () => {
  const rows = Array.from({ length: 80 }, (_, i) => [i * 61, (i + 1) * 61 + 1]);
  const slices = reportSlices(4880, 1350, rows);
  assert.equal(slices.length, 4);
  assert.ok(slices.slice(0, -1).every(([a, b]) => b - a >= 1200));
  assert.equal(slices.at(-1)[1], 4880);
});

test('a fitting section moves its heading and all results to the next sheet', () => {
  assert.deepEqual(reportSlices(1050, 700, [[650, 680], [700, 790], [790, 880], [880, 970], [650, 970]]),
    [[0, 650], [650, 1050]]);
});

test('oversized sections retain their heading stack and first complete result', () => {
  const rows = Array.from({ length: 12 }, (_, i) => [750 + i * 100, 850 + i * 100]);
  const slices = reportSlices(1950, 700, [[640, 1950], [640, 850], ...rows]);
  assert.deepEqual(slices, [[0, 640], [640, 1250], [1250, 1950]]);
});

test('merged groups and package ranges remain whole while explicit isolation wins', () => {
  assert.deepEqual(reportSlices(1100, 700, [[550, 980]]), [[0, 550], [550, 1100]]);
  const slices = reportSlices(1100, 700, [[550, 980]], [800]);
  assert.ok(slices.some(([, end]) => end === 800));
  assert.equal(slices.at(-1)[1], 1100);
});

test('unpainted bottom spacing cannot pull a fitting final section onto an extra sheet', () => {
  assert.deepEqual(reportSlices(950, 700, [[750, 1450]]), [[0, 700], [700, 950]]);
});
