import test from 'node:test';
import assert from 'node:assert/strict';
import { reportMargins, reportPageCss, reportSlices } from '../src/utils/medicalReportPages.js';

test('asymmetric saved margins and zero remain unchanged; invalid values fall back', () => {
  assert.deepEqual(reportMargins({ top: '38', bottom: 27, left: 0, right: '12' }), { top: 38, right: 12, bottom: 27, left: 0 });
  assert.deepEqual(reportMargins({ top: 'bad', right: -1 }), { top: 20, right: 15, bottom: 20, left: 15 });
  assert.throws(() => reportMargins({ top: 160, bottom: 150 }));
});

test('native printing reserves margins on every sheet instead of the document body', () => {
  const css = reportPageCss({ top: 38, right: 12, bottom: 27, left: 0 });
  assert.match(css, /@page[^}]*margin: 38mm 12mm 27mm 0mm/);
  assert.match(css, /top: -38mm/);
  assert.match(css, /table-header-group/);
  assert.doesNotMatch(css, /box-decoration-break/);
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

test('a row larger than the page cannot hang pagination or discard results', () => {
  assert.deepEqual(reportSlices(1600, 700, [[0, 1600]]), [[0, 700], [700, 1400], [1400, 1600]]);
  assert.throws(() => reportSlices(100, 0));
});
