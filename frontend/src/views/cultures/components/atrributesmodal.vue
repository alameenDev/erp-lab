<template>
     <Dialog
          v-model:visible="attrdialog"
          modal
          :header="t('attributesList')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <DataTable
                    v-if="attributesList.length > 0"
                    size="small"
                    :value="attributesList"
                    scrollable
                    responsiveLayout="scroll"
                    paginator
                    :rows="25">
                    <Column class="text-center" field="attribute_name" :header="t('name')"></Column>
                    <Column class="text-center" field="order" :header="t('Order')"></Column>
                    <Column class="text-center" field="result_type" :header="t('Result_Type')"></Column>
               </DataTable>
               <div
                    v-else
                    class="text-center noData p-d-flex p-ai-center p-jc-center"
                    style="height: 100px; color: red; line-height: 5">
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { useculturesStore } from "@/store/modules/cultures";

     export default {
          computed: {
               ...mapWritableState(useculturesStore, ["attrdialog", "attributesList"]),
          },

          methods: {
               close() {
                    this.attributesList = [];
                    this.attrdialog = false;
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
