<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { dateTimeFormat, t } from "@/utils/helper";
import BarcodeComponent from "@/components/BarcodeComponent.vue";

const invoicesStore = useinvoicesStore();
const { printRecord } = storeToRefs(invoicesStore);

const printAlone = computed(() => {
  return printRecord.value?.test_groups_all?.map((group) => ({
    name: group?.name,
    category: group?.category,
    testlength: group?.tests_print_alone?.length,
    tests_print_alone: group?.tests_print_alone,
  }));
});

const NotprintAlone = computed(() => {
  return printRecord.value?.test_groups_all?.map((group) => ({
    name: group?.name,
    category: group?.category,
    testlength: group?.tests_not_print_alone?.length,
    tests_not_print_alone: group?.tests_not_print_alone,
  }));
});

const allTests = computed(() => {
  const tests = [];
  // Add regular tests
  if (printRecord.value?.tests) {
    tests.push(...printRecord.value.tests.map(t => ({ ...t, type: 'test' })));
  }
  // Add cultures
  if (printRecord.value?.cultures) {
    tests.push(...printRecord.value.cultures.map(c => ({ ...c, type: 'culture' })));
  }
  // Add packages
  if (printRecord.value?.packages) {
    tests.push(...printRecord.value.packages.map(p => ({ ...p, type: 'package' })));
  }
  return tests;
});
</script>

<template>
  <div class="hidden" id="job">
    <!-- Job Order Print Template -->
    <div class="job-order-container">
      <!-- Header Section -->
      <div class="job-header">
        <table class="header-table">
          <tbody>
            <tr>
              <td class="header-cell">
                <div class="barcode-section">
                  <span class="label">{{ t('Barcode') || 'Barcode' }}:</span>
                  <div class="barcode-wrapper">
                    <BarcodeComponent :value="printRecord?.barcode" />
                    <span class="barcode-text">{{ printRecord?.barcode }}</span>
                  </div>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('age') || 'Age' }}:</span>
                  <strong>{{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }}</strong>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('referral') || 'Referred By' }}:</span>
                  <strong>{{ printRecord?.referral?.name || '-' }}</strong>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('total') || 'Total' }}:</span>
                  <strong>{{ printRecord?.total }}</strong>
                </div>
              </td>
            </tr>
            <tr>
              <td class="header-cell">
                <div class="barcode-section">
                  <span class="label">{{ t('patient_code') || 'Patient Code' }}:</span>
                  <div class="barcode-wrapper">
                    <BarcodeComponent :value="printRecord?.patient?.code" />
                    <span class="barcode-text">{{ printRecord?.patient?.code }}</span>
                  </div>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('sex') || 'Sex' }}:</span>
                  <strong>{{ printRecord?.patient?.gender }}</strong>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('registration_date') || 'Reg. Date' }}:</span>
                  <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('paid') || 'Paid' }}:</span>
                  <strong>{{ printRecord?.paid }}</strong>
                </div>
              </td>
            </tr>
            <tr>
              <td class="header-cell" colspan="2">
                <div class="info-row">
                  <span class="label">{{ t('Pationt_name') || 'Patient Name' }}:</span>
                  <strong class="patient-name">{{ printRecord?.patient?.name }}</strong>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('result_date') || 'Result Date' }}:</span>
                  <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
                </div>
              </td>
              <td class="header-cell">
                <div class="info-row">
                  <span class="label">{{ t('Due') || 'Due' }}:</span>
                  <strong class="due-amount">{{ (printRecord?.total || 0) - (printRecord?.paid || 0) }}</strong>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tests Table -->
      <div class="tests-section">
        <table class="tests-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t('test_name') || 'Test Name' }}</th>
              <th>{{ t('unit') || 'Unit' }}</th>
              <th>{{ t('sample') || 'Sample Type' }}</th>
              <th>{{ t('result') || 'Result' }}</th>
              <th>{{ t('signature') || 'Signature' }}</th>
            </tr>
          </thead>
          <tbody>
            <!-- Tests from test_groups_all -->
            <template v-for="(test_group, gIndex) in NotprintAlone" :key="'g-' + gIndex">
              <tr v-if="test_group.name || test_group.category" class="group-header">
                <td colspan="6">
                  <strong>{{ test_group.name || test_group.category }}</strong>
                </td>
              </tr>
              <tr v-for="(item, idx) in test_group?.tests_not_print_alone" :key="'t-' + gIndex + '-' + idx">
                <td>{{ idx + 1 }}</td>
                <td>{{ item?.report_name || item?.name }}</td>
                <td>{{ item?.unit || '-' }}</td>
                <td>{{ item?.sample_name || '-' }}</td>
                <td class="result-cell"></td>
                <td class="signature-cell"></td>
              </tr>
            </template>

            <!-- Direct tests -->
            <tr v-for="(item, idx) in printRecord?.tests" :key="'test-' + idx">
              <td>{{ idx + 1 }}</td>
              <td>{{ item?.report_name || item?.name }}</td>
              <td>{{ item?.unit || '-' }}</td>
              <td>{{ item?.sample_name || '-' }}</td>
              <td class="result-cell"></td>
              <td class="signature-cell"></td>
            </tr>

            <!-- Cultures -->
            <tr v-for="(item, idx) in printRecord?.cultures" :key="'culture-' + idx">
              <td>{{ (printRecord?.tests?.length || 0) + idx + 1 }}</td>
              <td>{{ item?.name }}</td>
              <td>{{ item?.unit || '-' }}</td>
              <td>{{ item?.sample_name || '-' }}</td>
              <td class="result-cell"></td>
              <td class="signature-cell"></td>
            </tr>

            <!-- Packages -->
            <tr v-for="(item, idx) in printRecord?.packages" :key="'package-' + idx">
              <td>{{ (printRecord?.tests?.length || 0) + (printRecord?.cultures?.length || 0) + idx + 1 }}</td>
              <td>{{ item?.name }}</td>
              <td>{{ item?.unit || '-' }}</td>
              <td>{{ item?.sample_name || '-' }}</td>
              <td class="result-cell"></td>
              <td class="signature-cell"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer Signatures -->
      <div class="signatures-section">
        <div class="signature-box">
          <div class="signature-line"></div>
          <span>{{ t('receptionist') || 'Receptionist' }}</span>
        </div>
        <div class="signature-box">
          <div class="signature-line"></div>
          <span>{{ t('sample_receiver') || 'Sample Receiver' }}</span>
        </div>
        <div class="signature-box">
          <div class="signature-line"></div>
          <span>{{ t('sample_responsible') || 'Sample Responsible' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Print styles are handled inline in the print function */
</style>
