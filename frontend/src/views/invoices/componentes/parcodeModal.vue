<script setup>
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import BarcodeComponent from "@/components/BarcodeComponent.vue";
import { dateTimeFormat } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const labSettingsStore = useLabSettingsStore();
const { printRecord } = storeToRefs(invoicesStore);
const showTests = computed(() => labSettingsStore.settings.show_tests_on_barcode !== false);

// Group all tests/cultures by sample_name, including inner tests from packages and test_groups
const sampleGroups = computed(() => {
     const groups = {};

     const addToGroup = (sampleName, testName, groupName) => {
          const key = sampleName || "Other";
          if (!groups[key]) groups[key] = { sampleName: key, tests: [], groupNames: [] };
          if (testName) groups[key].tests.push(testName);
          if (groupName && !groups[key].groupNames.includes(groupName)) {
               groups[key].groupNames.push(groupName);
          }
     };

     // Individual tests
     printRecord.value?.tests?.forEach((t) => {
          addToGroup(t.sample_name, t.shortcut || t.name);
     });

     // Individual cultures
     printRecord.value?.cultures?.forEach((c) => {
          addToGroup(c.sample_name, c.name);
     });

     // Package inner tests and cultures
     printRecord.value?.packages?.forEach((p) => {
          p.tests?.forEach((t) => addToGroup(t.sample_name, t.shortcut || t.name, p.name));
          p.cultures?.forEach((c) => addToGroup(c.sample_name || c.sample, c.name, p.name));
     });

     // Test group inner tests and cultures (test_groups has .tests/.cultures, test_groups_all has different keys)
     printRecord.value?.test_groups?.forEach((g) => {
          const gName = g.group_name || g.name;
          g.tests?.forEach((t) => addToGroup(t.sample_name, t.shortcut || t.name, gName));
          g.cultures?.forEach((c) => addToGroup(c.sample_name || c.sample, c.name, gName));
     });

     return Object.values(groups);
});
</script>

<template>
     <div class="hidden" id="parcode">
          <template v-if="sampleGroups.length">
               <div v-for="(group, idx) in sampleGroups" :key="idx" class="lbl">
                    <div class="top-row">
                         <span class="num">{{ printRecord?.barcode }}</span>
                         <span class="sample">{{ group.sampleName }}</span>
                    </div>
                    <div class="bc">
                         <BarcodeComponent :value="printRecord?.barcode" />
                    </div>
                    <div class="pname">{{ printRecord?.patient?.name }}</div>
                    <div class="info-row">
                         <span>{{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</span>
                         <span>{{ dateTimeFormat(printRecord?.created_at) }}</span>
                    </div>
                    <div v-if="showTests && group.tests.length" class="tests-row">{{ group.tests.join(', ') }}</div>
               </div>
          </template>
          <div v-else-if="printRecord?.barcode" class="lbl">
               <div class="top-row">
                    <span class="num">{{ printRecord?.barcode }}</span>
               </div>
               <div class="bc">
                    <BarcodeComponent :value="printRecord?.barcode" />
               </div>
               <div class="pname">{{ printRecord?.patient?.name }}</div>
               <div class="info-row">
                    <span>{{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</span>
                    <span>{{ dateTimeFormat(printRecord?.created_at) }}</span>
               </div>
          </div>
     </div>
</template>
