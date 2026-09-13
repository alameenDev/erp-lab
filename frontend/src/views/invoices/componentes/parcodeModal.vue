<template>
     <div class="container" id="parcode">
          <!-- class="index > 0 ? 'page-break' : ''" -->
          <div
               class="label-container"
               style="font-size: 2.5px"
               v-for="(s, index) in samplesTests"
               :key="index"
               :class="index > 0 ? 'page-break' : ''">
               <p class="details">
                    <span>{{ s?.sample_name }}</span>
               </p>
               <div class="barcode">
                    <div>
                         <span>{{ printRecord?.barcode }}</span>
                    </div>
                    <div>
                         <BarcodeComponent :value="printRecord?.barcode" />
                    </div>
               </div>
               <div class="text-center" style="line-height: 0">
                    <p>{{ printRecord?.patient?.name }}</p>
                    <p>
                         {{ printRecord?.patient?.gender }} /
                         {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }} &nbsp;&nbsp;&nbsp;
                         {{ dateTimeFormat(printRecord?.registration_date) }}
                    </p>
               </div>
               <div class="test-list">
                    <span v-for="t in s.test" :key="t">{{ t.name }}-</span>
                    <span v-for="t in s.culture" :key="t">{{ t.name }}-</span>
               </div>
          </div>
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";

     import { useinvoicesStore } from "@/store/modules/invoices";
     import BarcodeComponent from "../../../components/BarcodeComponent.vue";

     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord", "samplesTests"]),
          },
          components: { BarcodeComponent },
          methods: {},
     };
</script>
<style scoped>
     #parcode {
          display: none;
     }
</style>
