<script setup>
import { ref, nextTick } from 'vue';
import Report from '@/views/medical_reports/componentes/print_Result.vue';
import PrintSelectModal from '@/views/medical_reports/componentes/printSelectModal.vue';
import { selectReportItems } from '@/utils/reportItemSelection';
import { executeReportAction } from '@/utils/executeReportAction';
import { useLabSettingsStore } from '@/store/modules/labSettings';
import { useinvoicesStore } from '@/store/modules/invoices';
import { useTemplatesStore } from '@/store/modules/template';
import { useresultStatusStore } from '@/store/modules/result-status';
import { $http } from '@/plugins/axios';
import { selectionSample } from './report-selection-data.mjs';
const invoices = useinvoicesStore(), settings = useLabSettingsStore();
const source = selectionSample(); invoices.printRecord = JSON.parse(JSON.stringify(source));
settings.settings = { report_template: new URLSearchParams(location.search).get('template') || 'classic', show_test_names: !new URLSearchParams(location.search).has('merged'), show_categories: true, show_status: false, show_last_result: true,
  print_margins: { top: 12, bottom: 12, left: 12, right: 12 } };
useTemplatesStore().GetTemplates = async () => {};
useresultStatusStore().resultStatus = [{ value: 2, label: 'Normal' }];
const requests = [];
$http.defaults.adapter = async config => {
  requests.push(config.url);
  if (config.url === '/portal/generate') return { data: { url: 'https://example.test/portal/'+'a'.repeat(48) }, status: 200, config, headers: {} };
  throw new Error('Unexpected request '+config.url);
};
const report = ref(null), open = ref(true), busy = ref(false), status = ref('Ready'), previews = ref([]);
const executions = [], events = [];
const realClick = HTMLAnchorElement.prototype.click;
HTMLAnchorElement.prototype.click = function () { if (this.download) events.push(['download', this.download]); else realClick.call(this); };
async function execute(selection) {
  busy.value = true; status.value = 'Rendering';
  const original = invoices.printRecord;
  try {
    invoices.printRecord = { ...original, ...selectReportItems(original, selection) };
    await nextTick(); report.value.beginCapture(); await nextTick();
    const text = document.getElementById('Result').innerText;
    const output = await report.value.prepareReport({ withBackground: false });
    const record = JSON.parse(JSON.stringify(invoices.printRecord));
    if (selection.action !== 'preview') {
      const frame = document.createElement('iframe'); document.body.appendChild(frame);
      try {
        await executeReportAction({ action: selection.action, output, record, settings: settings.settings,
          printTab: { closed: false, document: frame.contentDocument, focus() {}, print() { events.push(['print']); } },
          whatsappTab: { closed: false, opener: null, location: { set href(url) { events.push(['whatsapp', url]); } } },
          store: { async recordReportAction(id, action) { events.push(['status', id, action]); } },
        });
      } finally { frame.remove(); }
    }
    executions.push({ selection, record, text, output }); previews.value = output.pages;
    status.value = 'PASS '+executions.length;
  } catch (error) { status.value = 'FAIL '+error.message; console.error(error); }
  finally { report.value.endCapture(); invoices.printRecord = original; busy.value = false; }
}
window.selectionFixture = { executions, events, requests, invoices, source, settings };
</script>
<template>
  <main class="p-5"><button id="open" @click="open = true">فتح التقرير</button><p id="status">{{ status }}</p>
    <div id="pages"><img v-for="(page, i) in previews" :key="i" :src="page" :alt="'Report page '+i" style="width: 600px; max-width: 100%" /></div>
    <Report ref="report" /><PrintSelectModal v-model="open" :busy="busy" can-share @execute="execute" />
  </main>
</template>
