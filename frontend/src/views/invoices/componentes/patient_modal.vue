<template>
     <Dialog
          v-model:visible="patientdialog"
          modal
          :header="patient?.name ? patient.name : ''"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <table v-if="patient" class="table table-bordered table-striped m-0">
                    <tbody>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("name") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ patient?.name }}
                              </td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("national_id_no") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ patient?.national_id_no }}
                              </td>
                         </tr>
                         <!-- <tr>
                          <th  nowrap="nowrap">
                                {{ t('passport_no') }}
                            </th>
                            <td nowrap="nowrap">
                               {{ patient.national_id_no }}
                            </td>
                            
                        </tr> -->
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("gender") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ patient?.gender }}
                              </td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("dob") }}
                              </th>
                              <td nowrap="nowrap">
                                   {{ patient?.dob }}
                              </td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">
                                   {{ t("age") }}
                              </th>
                              <td nowrap="nowrap">{{ patient?.age_unit + " " + patient?.age }}</td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">{{ t("phone_number") }}</th>
                              <td nowrap="nowrap" class="direction-ltr">{{ patient?.phone }}</td>
                         </tr>
                         <tr>
                              <th nowrap="nowrap">{{ t("address") }}</th>
                              <td nowrap="nowrap">{{ patient?.address }}</td>
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
               ...mapWritableState(useinvoicesStore, ["patientdialog", "patient"]),
          },

          methods: {
               close() {
                    this.patient = [];
                    this.patientdialog = false;
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
