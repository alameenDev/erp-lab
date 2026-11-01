import test from 'node:test';
import assert from 'node:assert/strict';
import { attachmentPrintLayout } from '../src/utils/reportAttachments.js';

test('legacy attachments get full page width and a legible maximum height', () => {
  assert.deepEqual(attachmentPrintLayout(), { print_width_percent: 100, print_max_height_mm: 210 });
  assert.deepEqual(attachmentPrintLayout({name:'old PDF'}), { print_width_percent: 100, print_max_height_mm: 210 });
});

test('saved dimensions preserve valid sizes and reject out of range values in the renderer', () => {
  assert.deepEqual(attachmentPrintLayout({print_width_percent:80,print_max_height_mm:240}), {print_width_percent:80,print_max_height_mm:240});
  assert.deepEqual(attachmentPrintLayout({print_width_percent:500,print_max_height_mm:-10}), {print_width_percent:100,print_max_height_mm:210});
  assert.deepEqual(attachmentPrintLayout({print_width_percent:'70',print_max_height_mm:'180'}), {print_width_percent:70,print_max_height_mm:180});
});
