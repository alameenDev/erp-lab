// Trusted isolated preload only. No API is exposed to the printed page, whose
// JavaScript remains disabled. Avoid evaluating code in the document world.
const {ipcRenderer} = require('electron');
ipcRenderer.on('dlprint:prepare', async (_event, paper) => {
  try {
    await document.fonts.ready;
    await Promise.all([...document.images].map(image => image.complete ? Promise.resolve() :
      new Promise(resolve => {image.onload = resolve; image.onerror = resolve;})));
    if ([...document.images].some(image => !image.naturalWidth)) throw new Error('IMAGE_NOT_READY');
    const labels = [...document.querySelectorAll('.barcode-label')];
    const overflow = labels.some(label => label.dataset.labelErrors || [...label.querySelectorAll('[data-label-text]')]
      .some(e => e.scrollHeight > e.clientHeight + 1 || e.scrollWidth > e.clientWidth + 1));
    if (overflow) {
      ipcRenderer.send('dlprint:prepared', {error: 'LABEL_OVERFLOW', fields: [...document.querySelectorAll('[data-label-text]')]
        .map(e => ({field: e.dataset.labelText, width: e.clientWidth, scrollWidth: e.scrollWidth, height: e.clientHeight, scrollHeight: e.scrollHeight}))});
      return;
    }
    let height = paper.height;
    if (height === null) {
      const style = document.createElement('style');
      style.textContent = 'html,body{height:auto!important;min-height:0!important;}';
      document.head.appendChild(style);
      height = Math.max(30, Math.ceil(document.body.getBoundingClientRect().height * 25.4 / 96) + 12);
      if (height > 2000) throw new Error('RECEIPT_TOO_LONG');
    }
    ipcRenderer.send('dlprint:prepared', {height, unsafe: document.body.dataset.unsafe || null});
  } catch (error) {ipcRenderer.send('dlprint:prepared', {error: String(error.message)});}
});
