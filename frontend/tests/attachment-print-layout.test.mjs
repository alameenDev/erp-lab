import test from 'node:test';
import assert from 'node:assert/strict';
import { attachmentPrintLayout, attachmentContentBounds } from '../src/utils/reportAttachments.js';

test('legacy attachments get full page width and a legible maximum height', () => {
  assert.deepEqual(attachmentPrintLayout(), { print_width_percent: 100, print_max_height_mm: 210, print_auto_crop: true });
  assert.deepEqual(attachmentPrintLayout({name:'old PDF'}), { print_width_percent: 100, print_max_height_mm: 210, print_auto_crop: true });
});

test('saved dimensions preserve valid sizes and reject out of range values in the renderer', () => {
  assert.deepEqual(attachmentPrintLayout({print_width_percent:80,print_max_height_mm:240,print_auto_crop:true}), {print_width_percent:80,print_max_height_mm:240,print_auto_crop:true});
  assert.deepEqual(attachmentPrintLayout({print_width_percent:500,print_max_height_mm:-10}), {print_width_percent:100,print_max_height_mm:210,print_auto_crop:true});
  assert.deepEqual(attachmentPrintLayout({print_width_percent:'70',print_max_height_mm:'180'}), {print_width_percent:70,print_max_height_mm:180,print_auto_crop:true});
});

test('PDF automatic fit can be disabled without losing saved dimensions', () => {
  assert.equal(attachmentPrintLayout({print_auto_crop:false}).print_auto_crop, false);
  assert.equal(attachmentPrintLayout({print_auto_crop:'0'}).print_auto_crop, false);
  assert.equal(attachmentPrintLayout({print_auto_crop:1}).print_auto_crop, true);
});

test('PDF blank margins are trimmed but every dark pixel remains in bounds', () => {
  const width = 600, height = 900;
  const data = new Uint8ClampedArray(width * height * 4).fill(255);
  const mark = (x, y) => {const i=(y*width+x)*4;data[i]=20;data[i+1]=20;data[i+2]=20;};
  // Synthetic small clinical result far below the top of a mostly empty PDF.
  for(let y=410;y<=610;y+=2)for(let x=140;x<=450;x+=2)mark(x,y);
  const box = attachmentContentBounds(data,width,height);
  assert.ok(box && box.y > 100, 'empty PDF header is eliminated');
  assert.ok(box.width < width && box.height < height, 'content can expand on A4');
  assert.ok(box.x <= 140 && box.x + box.width >= 450);
  assert.ok(box.y <= 410 && box.y + box.height >= 610);
  assert.ok(box.width >= 240 && box.height >= 360, 'never zoom beyond 2.5x');
});

test('PDF crop ignores pure white pages and keeps edge content', () => {
  const width=120,height=200,data=new Uint8ClampedArray(width*height*4).fill(255);
  assert.equal(attachmentContentBounds(data,width,height),null);
  for(let y=0;y<200;y+=2)for(let x=0;x<120;x+=2){const i=(y*width+x)*4;data[i]=data[i+1]=data[i+2]=0;}
  assert.equal(attachmentContentBounds(data,width,height),null,'full-page data must not be cropped');
});
