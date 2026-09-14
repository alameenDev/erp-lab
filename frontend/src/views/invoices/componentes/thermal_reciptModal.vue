<script setup>
import { ref, computed, watch } from "vue";
import { storeToRefs } from "pinia";
import JsBarcode from "jsbarcode";
import QRCode from "qrcode";
import { useinvoicesStore } from "@/store/modules/invoices";
import { dateTimeFormat } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const { printRecord } = storeToRefs(invoicesStore);

const qrDataUrl = ref("");

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;

const getResultLink = (invoiceId) => {
  return `${appBaseUrl}/result/${invoiceId}`;
};

// Generate QR code as data URL whenever printRecord changes
watch(
  () => printRecord.value?.id,
  async (id) => {
    if (id) {
      try {
        qrDataUrl.value = await QRCode.toDataURL(getResultLink(id), {
          width: 90,
          margin: 1,
          errorCorrectionLevel: "H",
        });
      } catch (err) {
        console.error("QR generation failed:", err);
        qrDataUrl.value = "";
      }
    }
  },
  { immediate: true },
);

const generateBarcodeImage = (value) => {
  if (!value) return "";
  const canvas = document.createElement("canvas");
  JsBarcode(canvas, value, {
    format: "CODE128",
    displayValue: false,
    width: 1.5,
    height: 25,
    margin: 0,
  });
  return canvas.toDataURL("image/png");
};

const due = computed(() => (printRecord.value?.total || 0) - (printRecord.value?.paid || 0));

const hasTestGroups = computed(() => printRecord.value?.test_groups?.length > 0);
const hasPackages = computed(() => printRecord.value?.packages?.length > 0);
const hasIndividualTests = computed(() => printRecord.value?.tests?.length > 0 || printRecord.value?.cultures?.length > 0);
</script>

<template>
  <div class="hidden" id="thermalRecord">
    <div class="thermal-receipt">

      <!-- ===== Header ===== -->
      <div class="header">
        <div class="title">INVOICE</div>
      </div>

      <!-- ===== Barcode Row ===== -->
      <div class="bc-row">
        <div class="bc-col">
          <img :src="generateBarcodeImage(printRecord?.barcode)" alt="" class="bc-img" />
          <span class="bc-txt">{{ printRecord?.barcode }}</span>
        </div>
        <div class="bc-col">
          <img :src="generateBarcodeImage(printRecord?.patient?.code)" alt="" class="bc-img" />
          <span class="bc-txt">{{ printRecord?.patient?.code }}</span>
        </div>
      </div>

      <div class="sep"></div>

      <!-- ===== Patient Info ===== -->
      <div class="info">
        <div class="row">
          <span>Patient</span>
          <strong>{{ printRecord?.patient?.name }}</strong>
        </div>
        <div class="row">
          <span>Age / Sex</span>
          <strong>{{ printRecord?.patient?.age }}{{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</strong>
        </div>
        <div v-if="printRecord?.patient?.phone" class="row">
          <span>Phone</span>
          <strong>{{ printRecord?.patient?.phone }}</strong>
        </div>
        <div v-if="printRecord?.referral?.name" class="row">
          <span>Referral</span>
          <strong>{{ printRecord?.referral?.name }}</strong>
        </div>
        <div v-if="printRecord?.contract?.name" class="row">
          <span>Contract</span>
          <strong>{{ printRecord?.contract?.name }}</strong>
        </div>
        <div v-if="printRecord?.sample_collector?.name" class="row">
          <span>Collector</span>
          <strong>{{ printRecord?.sample_collector?.name }}</strong>
        </div>
        <div class="row">
          <span>Reg. Date</span>
          <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
        </div>
        <div class="row">
          <span>Result Date</span>
          <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
        </div>
      </div>

      <div class="sep"></div>

      <!-- ===== Test Groups ===== -->
      <template v-if="hasTestGroups">
        <div v-for="(group, gi) in printRecord.test_groups" :key="'g-' + gi">
          <div class="grp-title">{{ group.group_name }}</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num-col">#</th>
                <th class="name-col">Test</th>
                <th class="sample-col">Sample</th>
                <th class="price-col">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in group.tests" :key="'gt-' + gi + '-' + idx">
                <td class="num-col">{{ idx + 1 }}</td>
                <td class="name-col">{{ item.report_name || item.name }}</td>
                <td class="sample-col">{{ item.sample_name || '-' }}</td>
                <td class="price-col">{{ item.price }}</td>
              </tr>
              <tr v-for="(item, idx) in group.cultures" :key="'gc-' + gi + '-' + idx">
                <td class="num-col">{{ (group.tests?.length || 0) + idx + 1 }}</td>
                <td class="name-col">{{ item.name }}</td>
                <td class="sample-col">{{ item.sample_name || '-' }}</td>
                <td class="price-col">{{ item.price }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="sep"></div>
      </template>

      <!-- ===== Packages ===== -->
      <template v-if="hasPackages">
        <div v-for="(pkg, pi) in printRecord.packages" :key="'pkg-' + pi">
          <div class="grp-title">{{ pkg.name }} (Pkg)</div>
          <table class="tbl">
            <thead>
              <tr>
                <th class="num-col">#</th>
                <th class="name-col">Test</th>
                <th class="sample-col">Sample</th>
                <th class="price-col">Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in pkg.tests" :key="'pt-' + pi + '-' + idx">
                <td class="num-col">{{ idx + 1 }}</td>
                <td class="name-col">{{ item.report_name || item.name }}</td>
                <td class="sample-col">{{ item.sample_name || '-' }}</td>
                <td class="price-col">{{ idx === 0 ? pkg.price : '' }}</td>
              </tr>
              <tr v-for="(item, idx) in pkg.cultures" :key="'pc-' + pi + '-' + idx">
                <td class="num-col">{{ (pkg.tests?.length || 0) + idx + 1 }}</td>
                <td class="name-col">{{ item.name }}</td>
                <td class="sample-col">{{ item.sample_name || '-' }}</td>
                <td class="price-col">{{ !pkg.tests?.length && idx === 0 ? pkg.price : '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="sep"></div>
      </template>

      <!-- ===== Individual Tests & Cultures ===== -->
      <template v-if="hasIndividualTests">
        <div class="grp-title">Individual Tests</div>
        <table class="tbl">
          <thead>
            <tr>
              <th class="num-col">#</th>
              <th class="name-col">Test</th>
              <th class="sample-col">Sample</th>
              <th class="price-col">Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in printRecord?.tests" :key="'t-' + index">
              <td class="num-col">{{ index + 1 }}</td>
              <td class="name-col">{{ item.report_name || item.name }}</td>
              <td class="sample-col">{{ item.sample_name || '-' }}</td>
              <td class="price-col">{{ item.price }}</td>
            </tr>
            <tr v-for="(item, index) in printRecord?.cultures" :key="'c-' + index">
              <td class="num-col">{{ (printRecord?.tests?.length || 0) + index + 1 }}</td>
              <td class="name-col">{{ item.name }}</td>
              <td class="sample-col">{{ item.sample_name || '-' }}</td>
              <td class="price-col">{{ item.price }}</td>
            </tr>
          </tbody>
        </table>
        <div class="sep"></div>
      </template>

      <!-- ===== Notes ===== -->
      <div v-if="printRecord?.notes" class="notes-section">
        <strong>Notes:</strong> {{ printRecord.notes }}
      </div>

      <!-- ===== Financial Summary ===== -->
      <div class="summary">
        <div class="sum-row">
          <span>Subtotal</span>
          <span>{{ printRecord?.sub_total }}</span>
        </div>
        <div v-if="printRecord?.discount" class="sum-row">
          <span>Discount</span>
          <span>- {{ printRecord?.discount }}</span>
        </div>
        <div class="sum-row total">
          <span>Total</span>
          <span>{{ printRecord?.total }}</span>
        </div>
        <div class="sum-row">
          <span>Paid</span>
          <span>{{ printRecord?.paid }}</span>
        </div>
        <div class="sum-row due">
          <span>Due</span>
          <span>{{ due }}</span>
        </div>
      </div>

      <div class="sep"></div>

      <!-- ===== QR Code ===== -->
      <div class="qr">
        <img v-if="qrDataUrl" :src="qrDataUrl" class="qr-img" alt="QR Code" />
        <p class="qr-hint">Scan to view results</p>
      </div>

      <!-- ===== Footer ===== -->
      <div class="footer">
        <p>Thank you for choosing our lab</p>
        <p>We wish you good health</p>
      </div>

    </div>
  </div>
</template>
