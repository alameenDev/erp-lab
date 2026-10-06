<script setup>
import { computed } from "vue";
import QrcodeVue from "qrcode.vue";
import { dateTimeFormat } from "@/utils/helper";

const props = defineProps({
  record: { type: Object, default: () => ({}) },
  reportUrl: { type: String, default: "" },
  showQr: { type: Boolean, default: true },
  qrLabel: { type: String, default: "patient portal" },
});
const printedOn = dateTimeFormat(new Date().toISOString());
const details = computed(() => [
  { icon: 'user', label: 'Patient Name', value: props.record?.patient?.name ?? '—', name: true },
  { icon: 'users', label: 'Age / Sex', value: [props.record?.patient?.age, props.record?.patient?.age_unit].filter(v => v != null && v !== '').join(' ') + ' / ' + (props.record?.patient?.gender || '—') },
  { icon: 'user-plus', label: 'Referred By', value: props.record?.referral?.name || props.record?.from_lab || props.record?.fromLab?.name || '—' },
]);
const registration = computed(() => [
  { icon: 'id-card', label: 'Reg. No.', value: props.record?.patient?.code ?? '—' },
  { icon: 'calendar', label: 'Registered On', value: props.record?.registration_date ? dateTimeFormat(props.record.registration_date) : '—' },
  { icon: 'clock', label: 'Printed On', value: printedOn },
]);
</script>

<template>
  <div class="mr-patient-card" dir="ltr">
    <div class="mr-patient-title">
      <i class="pi pi-user mr-title-icon" aria-hidden="true"></i>
      <strong>Patient Information</strong>
      <span>PATIENT DETAILS</span>
    </div>
    <div class="mr-patient-body">
      <div v-for="(column, index) in [details, registration]" :key="index" class="mr-patient-column">
        <div v-for="item in column" :key="item.label" class="mr-detail">
          <i :class="['pi', 'pi-' + item.icon, 'mr-detail-icon']" aria-hidden="true"></i>
          <div class="mr-detail-text">
            <span class="mr-detail-label">{{ item.label }}</span>
            <strong :class="{ 'mr-patient-name': item.name }" dir="auto">{{ item.value }}</strong>
          </div>
        </div>
      </div>
      <div v-if="showQr && reportUrl" class="mr-qr">
        <QrcodeVue :value="reportUrl" :size="90" :margin="4" level="M" render-as="svg" />
        <span>Scan to view<br />{{ qrLabel }}</span>
      </div>
    </div>
  </div>
</template>
