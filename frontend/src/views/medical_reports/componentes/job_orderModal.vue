<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { dateTimeFormat } from "@/utils/helper";
import BarcodeComponent from "@/components/BarcodeComponent.vue";

const invoicesStore = useinvoicesStore();
const { printRecord } = storeToRefs(invoicesStore);

const printAlone = computed(() => {
  return Array.isArray(printRecord.value?.test_groups_all)
    ? printRecord.value.test_groups_all.map((group) => ({
        name: group?.name,
        category: group?.category,
        testlength: group?.tests_print_alone?.length,
        tests_print_alone: group?.tests_print_alone,
      }))
    : [];
});

const NotprintAlone = computed(() => {
  return Array.isArray(printRecord.value?.test_groups_all)
    ? printRecord.value.test_groups_all.map((group) => ({
        name: group?.name,
        category: group?.category,
        testlength: group?.tests_not_print_alone?.length || 0,
        tests_not_print_alone: group?.tests_not_print_alone || [],
      }))
    : [];
});

const cultures = computed(() => {
  return printRecord.value?.test_groups_all?.map((group) => ({
    name: group?.name,
    category: group?.category,
    culturesLength: group?.cultures?.length,
    cultures: group?.cultures,
  }));
});
</script>

<template>
  <div class="hidden" id="job">
    <br />
    <template v-if="NotprintAlone?.length > 0">
      <div class="printPage">
        <div class="head">
          <div class="header-block">
            <div class="barcode-row">
              <div class="barcode-box">
                <strong>Barcode:</strong>
                <div class="barcode">
                  <BarcodeComponent :value="printRecord?.barcode" />
                  <span>{{ printRecord?.barcode }}</span>
                </div>
              </div>
              <div class="barcode-box">
                <strong>Patient Code:</strong>
                <div class="barcode">
                  <BarcodeComponent :value="printRecord?.patient?.code" />
                  <span>{{ printRecord?.patient?.code }}</span>
                </div>
              </div>
            </div>
            <hr class="barcode-divider" />

            <div class="header-row">
              <div>
                <strong>Patient Name:</strong> {{ printRecord?.patient?.name }}
              </div>
              <div>
                <strong>Phone:</strong> {{ printRecord?.patient?.phone }}
              </div>
              <div>
                <strong>Gender , Age:</strong> {{ printRecord?.patient?.gender }} , {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }}
              </div>
            </div>

            <div class="header-row">
              <div>
                <strong>Registration Date:</strong> {{ dateTimeFormat(printRecord?.registration_date) }}
              </div>
              <div>
                <strong>Result Date:</strong> {{ dateTimeFormat(printRecord?.result_date) }}
              </div>
              <div>
                <strong>Referred By:</strong> {{ printRecord?.referral?.name || printRecord?.from_lab || printRecord?.fromLab?.name || "-" }}
              </div>
            </div>

            <div class="header-row">
              <div>
                <strong>Total:</strong> {{ printRecord?.total }}
              </div>
              <div>
                <strong>Paid:</strong> {{ printRecord?.paid }}
              </div>
              <div>
                <strong>Due:</strong> {{ printRecord?.total - printRecord?.paid }}
              </div>
            </div>
          </div>
        </div>

        <section class="test-details" v-for="(test_group, index) in NotprintAlone" :key="index">
          <div class="tests-section">
            <div v-if="test_group.name" class="caption">{{ test_group.name }}</div>
            <br />
            <div v-if="test_group.category" class="caption">{{ test_group.category }}</div>
            <table class="tests-table">
              <thead>
                <tr>
                  <th>Test name</th>
                  <th>Unit</th>
                  <th>Sample type</th>
                  <th>Result</th>
                  <th>Signature</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in test_group?.tests_not_print_alone" :key="index">
                  <td>{{ item?.report_name }}</td>
                  <td>{{ item?.unit }}</td>
                  <td>{{ item?.sample_name }}</td>
                  <td>{{ item?.result }}</td>
                  <td></td>
                </tr>
                <tr v-for="(item, index) in printRecord?.cultures" :key="index">
                  <td>{{ item?.name }}</td>
                  <td>{{ item?.unit }}</td>
                  <td>{{ item?.sample_name }}</td>
                  <td>{{ item?.result }}</td>
                  <td></td>
                </tr>
                <tr v-for="(item, index) in printRecord?.packages" :key="index">
                  <td>{{ item?.name }}</td>
                  <td>{{ item?.unit }}</td>
                  <td>{{ item?.sample_name }}</td>
                  <td>{{ item?.result }}</td>
                  <td></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <div class="footer">
          <div>Receptionist</div>
          <div>Sample receiver</div>
          <div>Sample responsible</div>
        </div>
      </div>
    </template>
  </div>
</template>
