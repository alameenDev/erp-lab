<template>
     <Dialog
          v-model:visible="pationtHistoryDialog"
          modal
          :header="t('pationtHistory')"
          style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div class="invoices" v-if="pationtHistoryList">
               <div class="invoice">
                    <TabView>
                         <TabPanel :header="t('tests')">
                              <table class="table" v-if="pationtHistoryList.tests.length > 0">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                             <th>{{ t("theDate") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="test in pationtHistoryList.tests" :key="test">
                                             <td class="name">{{ test.name }}</td>
                                             <td>{{ test.result ?? "---" }}</td>
                                             <td>{{ test.status ?? "---" }}</td>
                                             <td>{{ dateTimeFormat(test.updated_at) }}</td>
                                        </tr>
                                   </tbody>
                              </table>
                              <div class="card flex justify-content-center mb-5" v-else>
                                   <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                              </div>
                         </TabPanel>
                         <TabPanel :header="t('cultures')">
                              <table class="table" v-if="pationtHistoryList.cultures.length > 0">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                             <th>{{ t("theDate") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="cultur in pationtHistoryList.cultures" :key="cultur">
                                             <td class="name">{{ cultur.name }}</td>
                                             <td>{{ cultur.result }}</td>
                                             <td>{{ cultur.status ?? "---" }}</td>
                                             <td>{{ dateTimeFormat(cultur.updated_at) }}</td>
                                        </tr>
                                   </tbody>
                              </table>
                              <div class="card flex justify-content-center mb-5" v-else>
                                   <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                              </div>
                         </TabPanel>
                         <!-- <TabPanel :header="t('packages')">
                              <table class="table" v-if="pationtHistoryList.packages.length > 0">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="packag in pationtHistoryList.packages" :key="packag">
                                             <td class="name">{{ packag.name }}</td>
                                             <td>{{ packag.result }}</td>
                                             <td>
                                                  {{
                                                       resultStatus.find((v) => v.id === packag.status_id_fk)?.status ??
                                                       "---"
                                                  }}
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <div class="card flex justify-content-center mb-5" v-else>
                                   <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                              </div>
                         </TabPanel> -->
                    </TabView>
                    <br />
               </div>
          </div>
          <div class="invoices" id="history" v-if="pationtHistoryList">
               <div class="invoice">
                    <div>
                         <h2>{{ t("tests") }}</h2>
                         <table class="table" v-if="pationtHistoryList.tests.length > 0">
                              <thead>
                                   <tr>
                                        <th>{{ t("name") }}</th>
                                        <th>{{ t("Result") }}</th>
                                        <th>{{ t("Result_Type") }}</th>
                                        <th>{{ t("theDate") }}</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="test in pationtHistoryList.tests" :key="test">
                                        <td class="name">{{ test.name }}</td>
                                        <td>{{ test.result ?? "---" }}</td>
                                        <td>{{ test.status ?? "---" }}</td>
                                        <td>{{ dateTimeFormat(test.updated_at) }}</td>
                                   </tr>
                              </tbody>
                         </table>
                         <div class="card flex justify-content-center mb-5" v-else>
                              <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                         </div>
                    </div>
                    <div>
                         <h2>{{ t("cultures") }}</h2>

                         <table class="table" v-if="pationtHistoryList.cultures.length > 0">
                              <thead>
                                   <tr>
                                        <th>{{ t("name") }}</th>
                                        <th>{{ t("Result") }}</th>
                                        <th>{{ t("Result_Type") }}</th>
                                        <th>{{ t("theDate") }}</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="cultur in pationtHistoryList.cultures" :key="cultur">
                                        <td class="name">{{ cultur.name }}</td>
                                        <td>{{ cultur.result }}</td>
                                        <td>{{ cultur.status ?? "---" }}</td>
                                        <td>{{ dateTimeFormat(cultur.updated_at) }}</td>
                                   </tr>
                              </tbody>
                         </table>
                         <div class="card flex justify-content-center mb-5" v-else>
                              <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                         </div>
                    </div>

                    <!-- <TabPanel :header="t('packages')">
                              <table class="table" v-if="pationtHistoryList.packages.length > 0">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="packag in pationtHistoryList.packages" :key="packag">
                                             <td class="name">{{ packag.name }}</td>
                                             <td>{{ packag.result }}</td>
                                             <td>
                                                  {{
                                                       resultStatus.find((v) => v.id === packag.status_id_fk)?.status ??
                                                       "---"
                                                  }}
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <div class="card flex justify-content-center mb-5" v-else>
                                   <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                              </div>
                         </TabPanel> -->

                    <br />
               </div>
          </div>
          <div class="card flex justify-content-center mb-5" v-else>
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>
          <div class="flex justify-content-center gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               <Button size="small" :label="t('print')" severity="primary" @click="print()"></Button>
          </div>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import { useresultStatusStore } from "@/store/modules/result-status";
     export default {
          data() {
               return {};
          },
          computed: {
               ...mapWritableState(useresultStatusStore, ["resultStatus"]),
               ...mapWritableState(useinvoicesStore, ["pationtHistoryList", "pationtHistoryDialog"]),
          },

          methods: {
               close() {
                    this.pationtHistoryDialog = false;
               },
               print() {
                    const printContents = document.getElementById("history").innerHTML;
                    const originalContents = document.body.innerHTML;
                    document.body.innerHTML = printContents;
                    window.print();
                    document.body.innerHTML = originalContents;
                    window.location.reload();
               },
          },
     };
</script>
<style scoped>
     .invoice {
          padding: 48px;
          border: 1px solid #0000004d;
          border-radius: 16px;
          margin-bottom: 18px;
     }
     /* Table Styling */
     tr {
          height: 55px;
     }
     td {
          border: 1px solid #0000002b;
     }
     thead {
          background: #374151;
          color: white;
          height: 35px;
     }
     .table {
          width: 100%;
          text-align: center;
          background: #f9fafb;
          display: table;
     }
     .name {
          color: #1f64c4;
          font-weight: bold;
     }
     #history {
          display: none;
     }
</style>
