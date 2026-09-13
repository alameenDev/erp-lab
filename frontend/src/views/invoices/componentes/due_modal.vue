<template>
     <Dialog
          v-model:visible="Patient_dueDialog"
          modal
          :header="t('Patient_due')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <table v-if="Patient_due" class="table table-bordered table-striped m-0">
                    <tbody>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("Subtotal") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ Patient_due.sub_total ?? 0 }}
                              </td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("discount") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ Patient_due.discount ?? 0 }}
                              </td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("Total") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ Patient_due.total ?? 0 }}
                              </td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("Due") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ Patient_due.total - Patient_due.paid ?? 0 }}
                              </td>
                         </tr>
                    </tbody>
               </table>

               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
          <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";

     export default {
          computed: {
               ...mapWritableState(useinvoicesStore, ["Patient_due", "Patient_dueDialog"]),
          },

          methods: {
               close() {
                    this.Patient_due = [];
                    this.Patient_dueDialog = false;
               },
          },
          watch: {},
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
</style>
