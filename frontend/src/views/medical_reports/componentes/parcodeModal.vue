<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import BarcodeComponent from "@/components/BarcodeComponent.vue";
import { dateTimeFormat } from "@/utils/helper";

const invoicesStore = useinvoicesStore();
const labSettingsStore = useLabSettingsStore();
const { printRecord } = storeToRefs(invoicesStore);
const showTests = computed(() => labSettingsStore.settings.show_tests_on_barcode !== false);

// Group all tests/cultures by sample_name
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

     printRecord.value?.tests?.forEach((t) => {
          addToGroup(t.sample_name, t.shortcut || t.name);
     });
     printRecord.value?.cultures?.forEach((c) => {
          addToGroup(c.sample_name, c.name);
     });
     printRecord.value?.packages?.forEach((p) => {
          p.tests?.forEach((t) => addToGroup(t.sample_name, t.shortcut || t.name, p.name));
          p.cultures?.forEach((c) => addToGroup(c.sample_name || c.sample, c.name, p.name));
     });
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
          <!-- One page per sample -->
          <template v-if="sampleGroups.length">
               <div v-for="(group, idx) in sampleGroups" :key="idx" class="lbl">
                    <!-- Row 1: Invoice number (left) + Sample name (right) -->
                    <div class="top-row">
                         <span class="num">{{ printRecord?.barcode }}</span>
                         <span class="sample">{{ group.sampleName }}</span>
                    </div>
                    <!-- Row 2: Barcode -->
                    <div class="bc">
                         <BarcodeComponent :value="printRecord?.barcode" />
                    </div>
                    <!-- Row 3: Patient name -->
                    <div class="pname">{{ printRecord?.patient?.name }}</div>
                    <!-- Row 4: Age/Gender (left) + Date (right) -->
                    <div class="info-row">
                         <span>{{ printRecord?.patient?.age }} {{ printRecord?.patient?.age_unit }} / {{ printRecord?.patient?.gender }}</span>
                         <span>{{ dateTimeFormat(printRecord?.created_at) }}</span>
                    </div>
                    <!-- Row 5: Tests -->
                    <div v-if="showTests && group.tests.length" class="tests-row">{{ group.tests.join(', ') }}</div>
               </div>
          </template>
          <!-- Fallback -->
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
