<template>
     <Dialog
          v-model:visible="testdialog"
          modal
          :header="t('tests')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <DataTable
                    size="small"
                    :value="tests"
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

                    <Column class="text-center" field="name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("name") }}</p>
                         </template>
                    </Column>

                    <Column class="text-center" field="price" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Original_Price") }}</p>
                         </template>
                    </Column>
               </DataTable>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { useReportsStore } from "@/store/modules/reports";

     export default {
          computed: {
               ...mapWritableState(useReportsStore, ["testdialog", "tests"]),
          },

          methods: {
               close() {
                    this.tests = [];
                    this.testdialog = false;
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
