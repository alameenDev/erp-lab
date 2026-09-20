import test from 'node:test';
import assert from 'node:assert/strict';
import { effectScope } from 'vue';
import { usePrint } from '../src/composables/usePrint.js';

function fixture(mode = 'afterprint') {
  const attached = new Set();
  let prints = 0;
  globalThis.document = {
    getElementById: () => ({outerHTML: '<div>barcode</div>'}),
    body: {appendChild: frame => attached.add(frame)},
    createElement() {
      const frame = {style: {}, setAttribute() {}, remove() {attached.delete(frame);}};
      frame.contentWindow = {
        document: {images: [], fonts: {ready: Promise.resolve()}, open() {}, write() {}, close() {}},
        focus() {},
        print() {
          prints++;
          assert.match(frame.style.cssText, /width: 0/);
          assert.match(frame.style.cssText, /left: -10000px/);
          assert.equal(typeof this.onafterprint, 'function', 'cleanup must be installed before print');
          if (mode === 'afterprint') this.onafterprint();
          if (mode === 'throw') throw new Error('Print cancelled by browser');
        }
      };
      return frame;
    }
  };
  const scope = effectScope();
  const api = scope.run(() => usePrint());
  return {attached, scope, api, prints: () => prints};
}

test('both print paths clean up even when afterprint fires before print returns', async () => {
  const f = fixture();
  await f.api.printWithCustomContent('<svg/>', '', 'Barcode', 0);
  await f.api.printWithIframe('parcode', '', 'Barcode', 0);
  assert.equal(f.prints(), 2);
  assert.equal(f.attached.size, 0);
  f.scope.stop();
});
test('navigation cleans orphan frames when browser does not emit afterprint', async () => {
  const f = fixture('no-event');
  const printing = f.api.printWithCustomContent('<svg/>', '', 'Barcode', 0);
  await new Promise(resolve => setTimeout(resolve, 10));
  assert.equal(f.attached.size, 1);
  f.scope.stop();
  await printing;
  assert.equal(f.attached.size, 0);
});
test('navigation cancels queued prints before frames are created', async () => {
  const f = fixture();
  const printing = f.api.printWithCustomContent('<svg/>', '', 'Barcode', 50);
  f.scope.stop();
  await printing;
  assert.equal(f.prints(), 0);
  assert.equal(f.attached.size, 0);
});
test('failed print removes the hidden frame', async () => {
  const f = fixture('throw');
  await f.api.printWithCustomContent('<svg/>', '', 'Barcode', 0);
  assert.equal(f.attached.size, 0);
  f.scope.stop();
});
