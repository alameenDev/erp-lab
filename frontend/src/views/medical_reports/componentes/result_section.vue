<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { dateTimeFormat } from "@/utils/helper";
import QrcodeVue from "qrcode.vue";

const invoicesStore = useinvoicesStore();
const { printRecord } = storeToRefs(invoicesStore);

const patientId = computed(() => printRecord.value?.id);

const appBaseUrl = import.meta.env.VITE_APP_URL || window.location.origin;

const getPatientReportLink = () => {
     return `${appBaseUrl}/result/${patientId.value}`;
};
</script>

<template>
     <div class="rs-header" dir="ltr" style="direction: ltr !important; text-align: left !important; border-bottom: 2px solid #333; padding: 10px 16px; font-family: Arial, sans-serif; display: flex; align-items: flex-start; gap: 16px;">
          <!-- Left: Patient Info -->
          <div class="rs-info" style="flex: 1; font-size: 14px; font-weight: 700; line-height: 1.7;">
               <div><span style="color: #444;">Patient Name</span> : {{ printRecord?.patient?.name }}</div>
               <div><span style="color: #444;">Age / Sex</span> : {{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</div>
               <div><span style="color: #444;">Referred By</span> : {{ printRecord?.referral?.name || printRecord?.from_lab || printRecord?.fromLab?.name || "-" }}</div>
          </div>

          <!-- Center: Dates -->
          <div class="rs-dates" style="font-size: 14px; font-weight: 700; line-height: 1.7; padding-left: 16px; border-left: 1.5px solid #ccc;">
               <div><span style="color: #444;">Registered On</span> : {{ dateTimeFormat(printRecord?.registration_date) }}</div>
               <div><span style="color: #444;">Printed On</span> : {{ dateTimeFormat(new Date().toISOString()) }}</div>
               <div><span style="color: #444;">Reg. No.</span> : {{ printRecord?.patient?.code }}</div>
          </div>

          <!-- Right: QR -->
          <div class="rs-qr" style="padding-left: 16px; border-left: 1.5px solid #ccc; display: flex; align-items: center;">
               <QrcodeVue :value="getPatientReportLink()" :size="60" level="H" render-as="svg" />
          </div>
     </div>
</template>

<style scoped>
/* Keep the patient header as ONE row at every width (it wraps to 2 rows on
   mobile in the on-screen/WhatsApp view). Print iframe uses the inline styles
   at A4 width and is unaffected by these scoped rules. */
.rs-header {
     flex-wrap: nowrap !important;
}
/* Text columns may shrink below their content so the QR never drops to a new row. */
.rs-info,
.rs-dates {
     min-width: 0;
}
/* On narrow screens shrink type, spacing and QR so all three columns still fit. */
@media screen and (max-width: 640px) {
     .rs-header {
          gap: 8px !important;
          padding: 8px 10px !important;
     }
     .rs-info,
     .rs-dates {
          font-size: 11px !important;
          line-height: 1.45 !important;
     }
     .rs-dates,
     .rs-qr {
          padding-left: 8px !important;
     }
     .rs-qr :deep(svg) {
          width: 44px !important;
          height: 44px !important;
     }
}
</style>
