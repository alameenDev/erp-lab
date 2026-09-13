<template>
     <Dialog
          v-model:visible="WhatsUpDialog"
          modal
          :header="t('sendMessage')"
          style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <printResult></printResult>
          </div>
          <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               <Button size="small" :label="t('sendMessage')" severity="success" @click="sendMsg()"></Button>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState, mapActions } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import { usePatientsStore } from "@/store/modules/patients";
     import printResult from "./print_Result.vue";
     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord", "WhatsUpDialog", "Pdfurl"]),
          },
          components: {
               printResult,
          },
          methods: {
               ...mapActions(useinvoicesStore, ["pdf"]),
               ...mapActions(usePatientsStore, ["whatsapp"]),

               async sendMsg() {
                    const invoiceContent = document.getElementById("Result").outerHTML;
                    await this.pdf(invoiceContent, this.printRecord?.id);
                    this.whatsapp(this.printRecord?.patient?.phone, this.Pdfurl.path).then((res) => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.close();
                    });
               },
               close() {
                    this.WhatsUpDialog = false;
               },
          },
          watch: {},
     };
</script>
<style scoped>
     #Result {
          display: block;
     }
     table,
     th,
     td {
          border: 1px solid #d1d5db;
          padding: 5px;
     }
     .table {
          width: 100%;
          text-align: center;
          padding: 20px;
          border: 1px solid;
          border-radius: 13px;
     }
</style>
