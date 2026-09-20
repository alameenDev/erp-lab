<script setup>
import { computed, ref, watch, nextTick } from "vue";
import { sharePatientPortal } from "@/utils/sharePatientPortal";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { usePrint } from "@/composables/usePrint";
import { documentCss } from "@/utils/labDocuments";
import { t, dateTimeFormat } from "@/utils/helper";
import JsBarcode from "jsbarcode";
import QrcodeVue from "qrcode.vue";
import parcodModal from "./parcodeModal.vue";
import thermalReciptModal from "./thermal_reciptModal.vue";
import BarcodeComponent from "@/components/BarcodeComponent.vue";

const invoicesStore = useinvoicesStore();
const labSettingsStore = useLabSettingsStore();
const { printRecord, printInvoiceDialog } = storeToRefs(invoicesStore);
const { printStyles, printWithCustomContent } = usePrint();

const dialog = ref(null);
let previousFocus = null;
watch(printInvoiceDialog, async (open) => {
  if (open) { previousFocus = document.activeElement; await nextTick(); dialog.value?.focus(); }
  else previousFocus?.focus?.();
});
const trapFocus = (event) => {
  const nodes = [...dialog.value.querySelectorAll('button:not(:disabled), [href], [tabindex="0"]')];
  if (!nodes.length) return;
  const first = nodes[0], last = nodes[nodes.length - 1];
  if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) { event.preventDefault(); last.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
};
const sending = ref(false);
const shareError = ref("");
const shareNotice = ref("");
const money = (value) => new Intl.NumberFormat('ar-IQ').format(Number(value) || 0);
const invoiceItems = computed(() => [
  ...(printRecord.value?.tests || []).map(x => ({...x, label:x.report_name || x.name, kind:'تحليل'})),
  ...(printRecord.value?.cultures || []).map(x => ({...x, label:x.name, kind:'زرع'})),
  ...(printRecord.value?.test_groups || []).map(x => ({...x, label:x.group_name || x.name, kind:'مجموعة', price:x.price ?? [...(x.tests || []), ...(x.cultures || [])].reduce((sum, test) => sum + (Number(test.price) || 0), 0), children:[...(x.tests || []), ...(x.cultures || [])]})),
  ...(printRecord.value?.packages || []).map(x => ({...x, label:x.name, kind:'باقة', children:[...(x.tests || []), ...(x.cultures || [])]})),
]);
watch(printInvoiceDialog, () => { shareError.value = ''; shareNotice.value = ''; });
const sendWelcome = async () => {
  if (sending.value) return;
  sending.value = true; shareError.value = ''; shareNotice.value = '';
  try {
    await sharePatientPortal(printRecord.value, labSettingsStore.settings, '', 'invoice');
    shareNotice.value = 'تم فتح الرسالة في واتساب؛ اضغط إرسال هناك لإيصالها للمريض.';
  } catch (e) { shareError.value = e.message; }
  finally { sending.value = false; }
};

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;
const getPatientReportLink = () => `${appBaseUrl}/result/${printRecord.value?.id}`;

// Same technique as print_invoice.vue: a raster <img> (not an inline <svg>)
// so it matches the existing ".hdr-bc img { max-width:130px; height:28px }"
// print CSS instead of needing separate sizing rules.
const generateBarcodeImage = (value) => {
  if (!value) return "";
  const canvas = document.createElement("canvas");
  JsBarcode(canvas, value, {
    format: "CODE128",
    displayValue: false,
    width: 1.2,
    height: 25,
    margin: 0,
  });
  return canvas.toDataURL("image/png");
};

const close = () => {
  printInvoiceDialog.value = false;
};

const due = computed(() => (printRecord.value?.total || 0) - (printRecord.value?.paid || 0));

const printParcode = async (data) => {
  printRecord.value = data;
  await nextTick();
  const content = document.getElementById("parcode")?.innerHTML;
  if (!content) return;
  await printWithCustomContent(
    content,
    printStyles.getBarcodeCss(labSettingsStore.settings.barcode_config),
    "Print Barcode"
  );
};

const openprintINvoiceTemplate = (data) => {
  printRecord.value = data;
  setTimeout(() => {
    const jobContent = document.getElementById("printInvoice")?.innerHTML;
    if (!jobContent) return;

    // Merge the base invoice CSS with the lab's saved Document Settings
    // (paper size, orientation, margin, font size/family, colors, barcode/QR
    // visibility) — same as print_invoice.vue, so this print button honours
    // the same settings as the PDF/preview flow instead of always using
    // the hardcoded defaults.
    const css = printStyles.invoice + documentCss(labSettingsStore.settings, "invoice");

    const printFrame = document.createElement("iframe");
    printFrame.style.cssText = "position: absolute; width: 0px; height: 0px; border: none;";
    document.body.appendChild(printFrame);

    const frameDoc = printFrame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(`<html><head><title>Print Invoice</title><style>${css}</style></head><body>${jobContent}</body></html>`);
    frameDoc.close();

    printFrame.contentWindow.focus();
    printFrame.contentWindow.print();
    printFrame.contentWindow.onafterprint = () => {
      document.body.removeChild(printFrame);
    };
  }, 50);
};

const openthermalRecord = (data) => {
  printRecord.value = data;
  setTimeout(() => {
    const jobContent = document.getElementById("thermalRecord")?.innerHTML;
    if (!jobContent) return;

    const css = printStyles.thermalReceipt + documentCss(labSettingsStore.settings, "thermal");

    const printFrame = document.createElement("iframe");
    printFrame.style.cssText = "position: absolute; width: 0px; height: 0px; border: none;";
    document.body.appendChild(printFrame);

    const frameDoc = printFrame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(`<html><head><title>Print Thermal Receipt</title><style>${css}</style></head><body>${jobContent}</body></html>`);
    frameDoc.close();

    printFrame.contentWindow.focus();
    printFrame.contentWindow.print();
    printFrame.contentWindow.onafterprint = () => {
      document.body.removeChild(printFrame);
    };
  }, 200);
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="printInvoiceDialog" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-5" dir="rtl">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="close"></div>
        <section ref="dialog" @keydown.tab="trapFocus" role="dialog" aria-modal="true" aria-labelledby="invoice-summary-title" tabindex="-1" @keydown.esc="close" class="relative w-full max-w-5xl max-h-[94vh] flex flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl">
          <header class="flex items-center justify-between gap-4 bg-slate-900 text-white px-6 py-5">
            <div><p class="text-teal-300 text-xs mb-1">الفاتورة مسجلة • #{{ printRecord?.id }}</p><h2 id="invoice-summary-title" class="text-xl font-bold">ملخص الزيارة والطباعة</h2><p class="text-slate-300 text-sm mt-1">راجع التفاصيل، اطبع الوصل وشارك بوابة المريض.</p></div>
            <button type="button" @click="close" aria-label="إغلاق ملخص الفاتورة" class="rounded-xl px-3 py-2 bg-white/10 hover:bg-white/20">✕</button>
          </header>
          <div class="overflow-y-auto p-4 sm:p-6 space-y-5">
            <section class="bg-white border border-slate-200 rounded-2xl p-5 flex flex-col sm:flex-row justify-between gap-4">
              <div><p class="text-xs text-slate-500 mb-1">المريض</p><h3 class="text-xl font-bold text-slate-900">{{ printRecord?.patient?.name }}</h3><div class="flex flex-wrap gap-4 text-sm text-slate-500 mt-2"><span>رقم الملف: {{ printRecord?.patient?.code || '—' }}</span><span>{{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }} · {{ printRecord?.patient?.gender }}</span><span dir="ltr">{{ printRecord?.patient?.phone || 'لا يوجد رقم هاتف' }}</span></div></div>
              <div class="text-sm text-slate-500 space-y-2"><p>تاريخ التسجيل: {{ dateTimeFormat(printRecord?.registration_date) }}</p><p>موعد النتائج: {{ printRecord?.result_date ? dateTimeFormat(printRecord.result_date) : 'غير محدد' }}</p><p v-if="printRecord?.referral?.name">الطبيب المحيل: {{ printRecord.referral.name }}</p></div>
            </section>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">إجمالي الفاتورة</p><p class="text-2xl font-bold mt-2 text-slate-900">{{ money(printRecord?.total) }} <small class="text-xs">د.ع</small></p></div>
              <div class="rounded-xl border border-teal-100 bg-teal-50 p-4"><p class="text-sm text-teal-700">المبلغ المدفوع</p><p class="text-2xl font-bold mt-2 text-teal-800">{{ money(printRecord?.paid) }} <small class="text-xs">د.ع</small></p></div>
              <div class="rounded-xl border border-amber-100 bg-amber-50 p-4"><p class="text-sm text-amber-800">المبلغ المتبقي</p><p class="text-2xl font-bold mt-2 text-amber-900">{{ money(due) }} <small class="text-xs">د.ع</small></p></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
              <section class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl overflow-hidden">
                <h3 class="font-bold text-slate-800 border-b border-slate-100 p-4">الفحوصات المطلوبة <span class="text-xs text-slate-500 font-normal">({{ invoiceItems.length }} بند)</span></h3>
                <div v-for="(item, index) in invoiceItems" :key="index" class="p-4 border-b border-slate-100 last:border-0 flex justify-between gap-3"><div><p class="font-semibold text-slate-800">{{ item.label }}</p><p class="text-xs text-slate-500 mt-1">{{ item.kind }}<span v-if="item.sample_name"> · {{ item.sample_name }}</span></p><p v-if="item.children?.length" class="text-xs text-slate-500 mt-2">{{ item.children.map(x => x.report_name || x.name).join('، ') }}</p></div><span class="text-sm font-semibold text-slate-700 whitespace-nowrap">{{ money(item.price) }} د.ع</span></div>
                <p v-if="!invoiceItems.length" class="p-5 text-sm text-slate-500">لا توجد فحوصات لعرضها.</p>
                <div class="p-4 text-sm text-slate-600 border-t border-slate-100"><p>المجموع قبل الخصم: {{ money(printRecord?.sub_total) }} د.ع</p><p v-if="printRecord?.discount" class="mt-1">الخصم: {{ printRecord.discount }} {{ Number(printRecord.discount_type_id_fk) === 2 ? '%' : 'د.ع' }}</p><div v-if="printRecord?.paidDetails?.length" class="mt-3 space-y-1"><p v-for="(payment, i) in printRecord.paidDetails" :key="i">{{ payment.payment_method || 'دفعة' }}: {{ money(payment.amount) }} د.ع</p></div></div>
                <div v-if="printRecord?.loyalty_discount" class="p-4 bg-teal-50 text-sm text-teal-800">خصم الولاء: {{ money(printRecord.loyalty_discount) }} د.ع مقابل {{ printRecord.loyalty_points_spent }} نقطة</div>
                <p v-if="printRecord?.notes" class="p-4 text-sm bg-amber-50 text-amber-900">ملاحظات: {{ printRecord.notes }}</p>
              </section>
              <aside class="space-y-4">
                <section class="bg-white rounded-2xl border border-slate-200 p-4 space-y-3"><h3 class="font-bold text-slate-800">الطباعة</h3>
                  <button type="button" @click="openthermalRecord(printRecord)" class="w-full rounded-xl bg-slate-900 text-white p-3 font-semibold hover:bg-slate-800">طباعة الوصل الحراري</button>
                  <button type="button" @click="openprintINvoiceTemplate(printRecord)" class="w-full rounded-xl border border-slate-300 p-3 text-slate-700 hover:bg-slate-50">طباعة الفاتورة الورقية</button>
                  <button type="button" @click="printParcode(printRecord)" class="w-full rounded-xl border border-slate-300 p-3 text-slate-700 hover:bg-slate-50">طباعة ملصق الباركود</button>
                  <div v-if="printRecord?.barcode" class="flex flex-col items-center overflow-hidden pt-2" dir="ltr"><BarcodeComponent :value="printRecord.barcode" /><span class="text-xs text-slate-500">{{ printRecord.barcode }}</span></div>
                </section>
                <section class="bg-teal-50 rounded-2xl border border-teal-100 p-4"><h3 class="font-bold text-teal-900">بوابة المريض</h3><p class="text-sm text-teal-800 my-2 leading-6">رسالة ترحيبية ورابط خاص لمتابعة النقاط والفواتير والتحاليل والنتائج.</p>
                  <button type="button" @click="sendWelcome" :disabled="sending || !printRecord?.patient?.phone" class="w-full rounded-xl bg-teal-700 hover:bg-teal-800 text-white p-3 font-semibold disabled:opacity-50">{{ sending ? 'جاري تجهيز الرسالة…' : 'إرسال الترحيب عبر واتساب' }}</button>
                  <p v-if="!printRecord?.patient?.phone" class="text-xs mt-2 text-amber-900">أضف رقم هاتف المريض لتفعيل الإرسال.</p><p v-if="shareError" role="alert" class="text-sm mt-2 text-red-700">{{ shareError }}</p><p v-if="shareNotice" role="status" class="text-sm mt-2 text-teal-900">{{ shareNotice }}</p>
                </section>
              </aside>
            </div>
          </div>
          <footer class="border-t border-slate-200 bg-white px-6 py-3 flex justify-end"><button type="button" @click="close" class="rounded-xl px-6 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50">تم، إغلاق</button></footer>
        </section>
      </div>
    </Transition>
  </Teleport>
  <parcodModal></parcodModal>
  <thermalReciptModal></thermalReciptModal>

  <!-- Hidden printable invoice -->
  <div class="hidden" id="printInvoice">
    <div class="inv">
      <div class="inv-title">INVOICE</div>

      <!-- Barcode + Patient Code Barcode + QR link to results -->
      <!-- Visibility toggled by lab_settings.document_config.invoice.show_barcode/show_qr via documentCss() -->
      <div class="hdr">
        <div class="hdr-bc">
          <img :src="generateBarcodeImage(printRecord?.barcode)" alt="" />
          <span>{{ printRecord?.barcode }}</span>
        </div>
        <div class="hdr-bc">
          <img :src="generateBarcodeImage(printRecord?.patient?.code)" alt="" />
          <span>{{ printRecord?.patient?.code }}</span>
        </div>
        <div class="hdr-qr">
          <QrcodeVue :value="getPatientReportLink()" :size="70" level="H" render-as="svg" />
          <span>Scan for results</span>
        </div>
      </div>

      <!-- Patient Info -->
      <div class="info-grid">
        <div class="info-row">
          <div class="info-cell info-lbl">Patient</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.name }}</div>
          <div class="info-cell info-lbl">Code</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.code }}</div>
        </div>
        <div class="info-row">
          <div class="info-cell info-lbl">Age / Sex</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.age }}{{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</div>
          <div class="info-cell info-lbl">Phone</div>
          <div class="info-cell info-val">{{ printRecord?.patient?.phone || "-" }}</div>
        </div>
        <div class="info-row">
          <div class="info-cell info-lbl">Reg. Date</div>
          <div class="info-cell info-val">{{ dateTimeFormat(printRecord?.registration_date) }}</div>
          <div class="info-cell info-lbl">Result Date</div>
          <div class="info-cell info-val">{{ dateTimeFormat(printRecord?.result_date) }}</div>
        </div>
        <div v-if="printRecord?.referral?.name" class="info-row">
          <div class="info-cell info-lbl">Referral</div>
          <div class="info-cell info-val">{{ printRecord?.referral?.name }}</div>
          <div class="info-cell info-lbl">Contract</div>
          <div class="info-cell info-val">{{ printRecord?.contract?.name || "-" }}</div>
        </div>
      </div>

      <!-- Test Groups -->
      <template v-if="printRecord?.test_groups?.length > 0">
        <div v-for="(group, gi) in printRecord.test_groups" :key="'pg-' + gi">
          <div class="section-title">{{ group.group_name }}</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num">#</th>
                <th class="txt-start">Test</th>
                <th class="sample">Sample</th>
                <th class="price">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in group.tests" :key="'pgt-' + idx">
                <td class="num">{{ idx + 1 }}</td>
                <td class="txt-start">{{ item.report_name || item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ item.price }}</td>
              </tr>
              <tr v-for="(item, idx) in group.cultures" :key="'pgc-' + idx">
                <td class="num">{{ (group.tests?.length || 0) + idx + 1 }}</td>
                <td class="txt-start">{{ item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ item.price }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Packages -->
      <template v-if="printRecord?.packages?.length > 0">
        <div v-for="(pkg, pi) in printRecord.packages" :key="'ppkg-' + pi">
          <div class="section-title">{{ pkg.name }} (Package)</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num">#</th>
                <th class="txt-start">Test</th>
                <th class="sample">Sample</th>
                <th class="price">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in pkg.tests" :key="'ppt-' + idx">
                <td class="num">{{ idx + 1 }}</td>
                <td class="txt-start">{{ item.report_name || item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ idx === 0 ? pkg.price : "" }}</td>
              </tr>
              <tr v-for="(item, idx) in pkg.cultures" :key="'ppc-' + idx">
                <td class="num">{{ (pkg.tests?.length || 0) + idx + 1 }}</td>
                <td class="txt-start">{{ item.name }}</td>
                <td>{{ item.sample_name || "-" }}</td>
                <td class="price">{{ !pkg.tests?.length && idx === 0 ? pkg.price : "" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Individual Tests & Cultures -->
      <template v-if="printRecord?.tests?.length > 0 || printRecord?.cultures?.length > 0">
        <div class="section-title">Individual Tests</div>
        <table class="tbl">
          <thead>
            <tr>
              <th class="num">#</th>
              <th class="txt-start">Test</th>
              <th class="sample">Sample</th>
              <th class="price">Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in printRecord?.tests" :key="'pit-' + index">
              <td class="num">{{ index + 1 }}</td>
              <td class="txt-start">{{ item.report_name || item.name }}</td>
              <td>{{ item.sample_name || "-" }}</td>
              <td class="price">{{ item.price }}</td>
            </tr>
            <tr v-for="(item, index) in printRecord?.cultures" :key="'pic-' + index">
              <td class="num">{{ (printRecord?.tests?.length || 0) + index + 1 }}</td>
              <td class="txt-start">{{ item.name }}</td>
              <td>{{ item.sample_name || "-" }}</td>
              <td class="price">{{ item.price }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Notes -->
      <div v-if="printRecord?.notes" class="notes">
        <strong>Notes:</strong> {{ printRecord.notes }}
      </div>

      <!-- Financial Summary -->
      <div class="summ">
        <table class="summ-tbl">
          <tr>
            <td class="lbl">Subtotal</td>
            <td>IQD {{ printRecord?.sub_total }}</td>
          </tr>
          <tr v-if="printRecord?.discount">
            <td class="lbl">Discount</td>
            <td>IQD {{ printRecord?.discount }}</td>
          </tr>
          <tr v-if="printRecord?.loyalty_discount"><td class="lbl">خصم الولاء</td><td>IQD {{ printRecord.loyalty_discount }}</td></tr>
          <tr class="total">
            <td>Total</td>
            <td>IQD {{ printRecord?.total }}</td>
          </tr>
          <tr class="paid-row">
            <td>Paid</td>
            <td>IQD {{ printRecord?.paid }}</td>
          </tr>
          <tr class="due">
            <td>Due</td>
            <td>IQD {{ due }}</td>
          </tr>
        </table>
      </div>

      <!-- Payment Details -->
      <div v-if="printRecord?.paidDetails?.length > 1" class="pay-details">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Method</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(pd, idx) in printRecord.paidDetails" :key="'ppd-' + idx">
              <td>{{ idx + 1 }}</td>
              <td>{{ pd.payment_method || "-" }}</td>
              <td>IQD {{ pd.amount }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <p>Thank you for choosing our lab</p>
      </div>
    </div>
  </div>
</template>
