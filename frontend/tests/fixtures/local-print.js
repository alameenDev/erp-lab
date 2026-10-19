import {createApp, h} from 'vue';
import Settings from '../../src/views/lab-settings/LocalPrinterSettings.vue';
import BarcodeLabel from '../../src/components/barcode/BarcodeLabel.vue';
import {defaultLabel, barcodePrintCss} from '../../src/utils/barcodeLabels.js';
import {usePrint} from '../../src/composables/usePrint.js';
import {savePrintSettings, readPrintSettings, routePrintDocument, snapshotPrintDocument} from '../../src/utils/localPrint.js';
import {printMedicalReportPages} from '../../src/utils/medicalReportPages.js';
import '../../src/assets/main.css';
const pixel = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lRkAAAAASUVORK5CYII=';
const originalCreate = document.createElement.bind(document);
window.nativePrints = 0;
document.createElement = function(tag, ...args) {
  const node = originalCreate(tag, ...args);
  if (tag === 'iframe') {
    const append = document.body.appendChild.bind(document.body);
    document.body.appendChild = function(child) {
      const result = append(child);
      if (child.tagName === 'IFRAME') child.contentWindow.print = () => {window.nativePrints++;child.contentWindow.onafterprint?.();};
      return result;
    };
  }
  return node;
};
createApp({setup() {
  const print = usePrint();
  const config = defaultLabel(50,30,{},true,true);
  window.qa = {savePrintSettings, readPrintSettings, snapshotPrintDocument,
    async send(kind, css='@page{size:A4;margin:12mm}') {
      return print.printWithIframe(kind === 'barcode' ? 'qa-label' : 'qa-document', kind === 'barcode' ? barcodePrintCss(config) : css, 'QA '+kind,0,null,{throwOnError:true,printKind:kind});
    },
    async report() {
      const w=window.open('about:blank','_blank');w.print=()=>{window.nativePrints++;};
      return printMedicalReportPages([pixel,pixel],w);
    },
    async missingAsset() {
      const doc=document.implementation.createHTMLDocument('QA');doc.body.innerHTML='<img src="/missing-asset.png">';
      return routePrintDocument(doc,'invoice');
    }
  };
  return () => h('main',{},[
    h(Settings),h('div',{style:'position:absolute;left:-10000px;top:0'},[
      h('div',{id:'qa-document'},[h('h1','مستند تجريبي'),h('img',{src:pixel}),h('table',[h('tbody',[h('tr',[h('td','Test'),h('td','12.5')])])])]),
      h('div',{id:'qa-label'},[h(BarcodeLabel,{config,data:{barcode:'1234567890',number:'1234567890',register_number:'#12',patient:'مريض تجريبي',age:'30 سنة',gender:'ذكر',date:'19/10/2026',sample:'EDTA',tests:'CBC'}})])
    ])
  ]);
}}).mount('#app');
