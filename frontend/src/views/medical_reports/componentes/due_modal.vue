<template>
     <Dialog
          v-model:visible="Patient_dueDialog"
          modal
          :header="t('the_tests')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <div style="text-align: center" v-for="test_group in record.test_groups" :key="test_group">
                    <div>
                         <p style="font-weight: bold" :class="test_group.is_done ? 'done' : 'pendening'">
                              {{ test_group.name }}
                         </p>
                         <table v-if="record" class="table table-bordered table-striped m-0">
                              <tbody>
                                   <tr v-for="test in test_group.tests" :key="test">
                                        <td nowrap="nowrap" :class="test_group.is_done ? 'done' : 'pendening'">
                                             {{ test.name }}
                                        </td>
                                   </tr>

                                   <tr v-for="culture in test_group.cultures" :key="culture">
                                        <td nowrap="nowrap" :class="test_group.is_done ? 'done' : 'pendening'">
                                             {{ culture.name }}
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
               </div>
               <br />
               <table v-if="record" class="table table-bordered table-striped m-0">
                    <tbody>
                         <tr v-for="test in record.tests" :key="test">
                              <td nowrap="nowrap" :class="test.is_done ? 'done' : 'pendening'">
                                   {{ test.name }}
                              </td>
                         </tr>

                         <tr v-for="culture in record.cultures" :key="culture">
                              <td nowrap="nowrap" :class="culture.is_done ? 'done' : 'pendening'">
                                   {{ culture.name }}
                              </td>
                         </tr>
                         <tr v-for="packag in record.packages" :key="packag">
                              <td nowrap="nowrap" :class="packag.is_done ? 'done' : 'pendening'">
                                   {{ packag.name }}
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
               ...mapWritableState(useinvoicesStore, ["record", "Patient_dueDialog"]),
          },

          methods: {
               close() {
                    this.record = [];
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
          border: 1px solid rgb(0 0 0 / 20%);
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
