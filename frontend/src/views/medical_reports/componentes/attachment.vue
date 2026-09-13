<template>
     <Dialog
          v-model:visible="AttachDialog"
          modal
          :header="t('attachments')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <table v-if="attachments.length > 0" class="table table-bordered table-striped m-0">
                    <thead>
                         <th>{{ t("name") }}</th>
                         <th>{{ t("file") }}</th>
                    </thead>
                    <tbody>
                         <tr v-for="attach in attachments" :key="attach">
                              <td>
                                   {{ attach.name }}
                              </td>
                              <td>
                                   <Button
                                        style="width: 44%"
                                        icon="pi pi-link"
                                        as="a"
                                        :label="t('show_result_date')"
                                        :href="attach.file"
                                        target="_blank"
                                        rel="noopener"
                                        @click="href(attach.file)" />
                              </td>
                         </tr>
                    </tbody>
               </table>

               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
          <div class="flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";

     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, ["attachments", "AttachDialog"]),
          },
          methods: {
               close() {
                    this.attachments = [];
                    this.AttachDialog = false;
               },
               href(url) {
                    window.open(url, "_blank");
               },
          },
     };
</script>
<style scoped>
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
     .pendening {
          border: none;
          color: goldenrod;
     }
     .done {
          border: none;
          color: green;
     }
</style>
