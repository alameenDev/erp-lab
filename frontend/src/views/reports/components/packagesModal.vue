<template>
     <Dialog
          v-model:visible="packdialog"
          modal
          :header="t('packages')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <DataTable
               size="small"
               :value="packages"
               scrollable
               scrollHeight="450px"
               responsiveLayout="scroll"
               paginator
               :rows="25">
               <template #empty>
                    <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                         {{ t("noData") }}
                    </div>
               </template>

               <Column class="text-center" field="namr" style="min-width: 100px">
                    <template #header>
                         <p>{{ t("name") }}</p>
                    </template>
               </Column>

               <Column class="text-center" field="count" style="min-width: 100px">
                    <template #header>
                         <p>{{ t("count") }}</p>
                    </template>
               </Column>

               <Column class="text-center" field="total" style="min-width: 100px">
                    <template #header>
                         <p>{{ t("Total") }}</p>
                    </template>
               </Column>
          </DataTable>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { useReportsStore } from "@/store/modules/reports";

     export default {
          computed: {
               ...mapWritableState(useReportsStore, ["packdialog", "packages"]),
          },

          methods: {
               close() {
                    this.packages = [];
                    this.packdialog = false;
               },
          },
          watch: {},
     };
</script>
<style scoped>
     ul {
          list-style: circle;
          background: rgba(0, 0, 0, 0.02);
     }
     li {
          background: #b9d1c742;
          padding: 6px;
          border-radius: 4px;
          margin: 3px;
     }
</style>
