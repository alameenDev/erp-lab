<template>
     <div class="report-container" id="job">
          <template v-if="NotprintAlone?.length > 0">
               <hr />
               <div class="printPage">
                    <div class="head">
                         <div class="header">
                              <br />
                              <br />
                              <br />
                              <br />
                              <br />
                              <br />
                              <div class="header-item">
                                   <div style="display: flex; align-items: center">
                                        <span>Barcode:</span>

                                        <div style="text-align: center">
                                             <BarcodeComponent :value="printRecord?.barcode" />
                                             <span>{{ printRecord?.barcode }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div class="header-item">
                                   Age:
                                   <strong>
                                        {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }}
                                   </strong>
                              </div>
                              <div class="header-item">
                                   Referred By:
                                   <strong>{{ printRecord?.referral?.name }}</strong>
                              </div>
                              <div class="header-item">
                                   Total:
                                   <strong>{{ printRecord?.total }}</strong>
                              </div>
                         </div>

                         <div class="header">
                              <div class="header-item">
                                   <div style="display: flex; align-items: center">
                                        <span>Patient code:</span>

                                        <div style="text-align: center">
                                             <BarcodeComponent :value="printRecord?.patient?.code" />
                                             <span>{{ printRecord?.patient?.code }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div class="header-item">
                                   Sex:
                                   <strong>{{ printRecord?.patient?.gender }}</strong>
                              </div>
                              <div class="header-item">
                                   Registration date:
                                   <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                              </div>
                              <div class="header-item">
                                   Paid:
                                   <strong>{{ printRecord?.paid }}</strong>
                              </div>
                         </div>
                         <div class="header">
                              <div class="header-item">
                                   Patient name:

                                   <strong>
                                        {{ printRecord?.patient?.name }}
                                   </strong>
                              </div>
                              <div class="header-item">
                                   Phone:

                                   <strong>{{ printRecord?.patient?.phone }}</strong>
                              </div>
                              <div class="header-item">
                                   Result date:
                                   <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
                              </div>
                              <div class="header-item">
                                   Due:
                                   <strong>{{ printRecord?.total - printRecord?.paid }}</strong>
                              </div>
                         </div>
                    </div>
                    <section class="test-details" v-for="(test_group, index) in NotprintAlone" :key="index">
                         <!-- Tests Table Section -->
                         <div class="tests-section">
                              <div v-if="test_group.name" class="caption">
                                   {{ test_group.name }}
                              </div>
                              <br />
                              <div v-if="test_group.category" class="caption">
                                   {{ test_group.category }}
                              </div>
                              <table class="tests-table">
                                   <thead>
                                        <tr>
                                             <th>Test name</th>
                                             <th>Unit</th>
                                             <th>Sample type</th>
                                             <th>Result</th>
                                             <th>Signature</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(item, index) in test_group?.tests_not_print_alone" :key="index">
                                             <td>{{ item?.report_name }}</td>
                                             <td>{{ item?.unit }}</td>
                                             <td>{{ item?.sample_name }}</td>
                                             <td>{{ item?.result }}</td>
                                             <td></td>
                                        </tr>
                                        <tr v-for="(item, index) in printRecord?.cultures" :key="index">
                                             <td>{{ item?.name }}</td>
                                             <td>{{ item?.unit }}</td>
                                             <td>{{ item?.sample_name }}</td>
                                             <td>{{ item?.result }}</td>
                                             <td></td>
                                        </tr>
                                        <tr v-for="(item, index) in printRecord?.packages" :key="index">
                                             <td>{{ item?.name }}</td>
                                             <td>{{ item?.unit }}</td>
                                             <td>{{ item?.sample_name }}</td>
                                             <td>{{ item?.result }}</td>
                                             <td></td>
                                        </tr>
                                   </tbody>
                              </table>
                         </div>

                         <!-- Footer Section -->
                    </section>
                    <div class="footer">
                         <div>Receptionist</div>
                         <div>Sample receiver</div>
                         <div>Sample responsible</div>
                    </div>
                    <br />
                    <br />
                    <br />
                    <br />
               </div>
          </template>

          <template v-if="printAlone?.length > 0">
               <div v-for="(test_group, index) in printAlone" :key="index">
                    <div
                         v-for="(item, index) in test_group?.tests_print_alone"
                         :key="'separate-' + index"
                         class="print-page page-break">
                         <div class="printPage">
                              <div class="head">
                                   <div class="header">
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <div class="header-item">
                                             <div style="display: flex; align-items: center">
                                                  <span>Barcode:</span>
                                                  <div style="text-align: center">
                                                       <BarcodeComponent :value="printRecord?.barcode" />
                                                       <span>{{ printRecord?.barcode }}</span>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="header-item">
                                             Age:
                                             <strong>
                                                  {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }}
                                             </strong>
                                        </div>
                                        <div class="header-item">
                                             Referred By:
                                             <strong>{{ printRecord?.referral?.name }}</strong>
                                        </div>
                                        <div class="header-item">
                                             Total:
                                             <strong>{{ printRecord?.total }}</strong>
                                        </div>
                                   </div>

                                   <div class="header">
                                        <div class="header-item">
                                             <div style="display: flex; align-items: center">
                                                  <span>Patient code:</span>
                                                  <div style="text-align: center">
                                                       <BarcodeComponent :value="printRecord?.patient?.code" />
                                                       <span>{{ printRecord?.patient?.code }}</span>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="header-item">
                                             Sex:
                                             <strong>{{ printRecord?.patient?.gender }}</strong>
                                        </div>
                                        <div class="header-item">
                                             Registration date:
                                             <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                                        </div>
                                        <div class="header-item">
                                             Paid:
                                             <strong>{{ printRecord?.paid }}</strong>
                                        </div>
                                   </div>
                                   <div class="header">
                                        <div class="header-item">
                                             Patient name:

                                             <strong>
                                                  {{ printRecord?.patient?.name }}
                                             </strong>
                                        </div>
                                        <div class="header-item">
                                             Phone:

                                             <strong>{{ printRecord?.patient?.phone }}</strong>
                                        </div>
                                        <div class="header-item">
                                             Result date:
                                             <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
                                        </div>
                                        <div class="header-item">
                                             Due:
                                             <strong>{{ printRecord?.total - printRecord?.paid }}</strong>
                                        </div>
                                   </div>
                              </div>
                              <!-- Tests Table Section -->
                              <div class="tests-section">
                                   <div v-if="test_group.name" class="caption">
                                        {{ test_group.name }}
                                   </div>
                                   <br />
                                   <div v-if="test_group.category" class="caption">
                                        {{ test_group.category }}
                                   </div>
                                   <table class="tests-table">
                                        <thead>
                                             <tr>
                                                  <th>Test name</th>
                                                  <th>Unit</th>
                                                  <th>Sample type</th>
                                                  <th>Result</th>
                                                  <th>Signature</th>
                                             </tr>
                                        </thead>
                                        <tbody>
                                             <tr>
                                                  <td>{{ item?.report_name }}</td>
                                                  <td>{{ item?.unit }}</td>
                                                  <td>{{ item?.sample_name }}</td>
                                                  <td>{{ item?.result }}</td>
                                                  <td></td>
                                             </tr>
                                             <tr v-for="(item, index) in printRecord?.cultures" :key="index">
                                                  <td>{{ item?.name }}</td>
                                                  <td>{{ item?.unit }}</td>
                                                  <td>{{ item?.sample_name }}</td>
                                                  <td>{{ item?.result }}</td>
                                                  <td></td>
                                             </tr>
                                             <tr v-for="(item, index) in printRecord?.packages" :key="index">
                                                  <td>{{ item?.name }}</td>
                                                  <td>{{ item?.unit }}</td>
                                                  <td>{{ item?.sample_name }}</td>
                                                  <td>{{ item?.result }}</td>
                                                  <td></td>
                                             </tr>
                                        </tbody>
                                   </table>
                              </div>

                              <!-- Footer Section -->
                              <div class="footer">
                                   <div>Receptionist</div>
                                   <div>Sample receiver</div>
                                   <div>Sample responsible</div>
                              </div>
                              <br />
                              <br />
                              <br />
                              <br />
                              <hr />
                         </div>
                    </div>
               </div>
          </template>
          <!-- seperated cultures -->
          <div v-for="(item, index) in printRecord?.cultures" :key="'separate-' + index" class="print-page page-break">
               <div class="head">
                    <div class="header">
                         <div class="header-item">
                              <div style="display: flex; align-items: center">
                                   <span>Barcode:</span>
                                   <span style="display: flex; flex-direction: column; align-items: center">
                                        <div style="text-align: center">
                                             <BarcodeComponent :value="printRecord?.barcode" />
                                             <span>{{ printRecord?.barcode }}</span>
                                        </div>
                                   </span>
                              </div>
                         </div>
                         <div class="header-item">
                              Age:
                              <strong>{{ printRecord?.patient?.age + printRecord?.patient?.age_unit }}</strong>
                         </div>
                         <div class="header-item">
                              Referred By:
                              <strong>{{ printRecord?.referral?.name }}</strong>
                         </div>
                         <div class="header-item">
                              Total:
                              <strong>{{ printRecord?.total }}</strong>
                         </div>
                    </div>

                    <div class="header">
                         <div class="header-item">
                              <div style="display: flex; align-items: center">
                                   <span>Patient code:</span>
                                   <span style="display: flex; flex-direction: column; align-items: center">
                                        <div style="text-align: center">
                                             <BarcodeComponent :value="printRecord?.patient?.code" />
                                             <span>{{ printRecord?.patient?.code }}</span>
                                        </div>
                                   </span>
                              </div>
                         </div>
                         <div class="header-item">
                              Sex:
                              <strong>{{ printRecord?.patient?.gender }}</strong>
                         </div>
                         <div class="header-item">
                              Registration date:
                              <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                         </div>
                         <div class="header-item">
                              Paid:
                              <strong>{{ printRecord?.paid }}</strong>
                         </div>
                    </div>
                    <div class="header">
                         <div class="header-item">
                              Patient name:

                              <strong>{{ printRecord?.patient?.name }}</strong>
                         </div>
                         <div class="header-item">
                              Phone:

                              <strong>{{ printRecord?.patient?.phone }}</strong>
                         </div>
                         <div class="header-item">
                              Result date:
                              <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
                         </div>
                         <div class="header-item">
                              Due:
                              <strong>{{ printRecord?.total - printRecord?.paid }}</strong>
                         </div>
                    </div>
               </div>
               <section class="test-details">
                    <table class="test-table">
                         <thead>
                              <tr>
                                   <th>Test name</th>
                                   <th>Unit</th>
                                   <th>Sample type</th>
                                   <th>Result</th>
                                   <th>Signature</th>
                              </tr>
                         </thead>
                         <tbody>
                              <tr>
                                   <td>{{ item?.report_name }}</td>
                                   <td>{{ item?.unit }}</td>
                                   <td>{{ item?.sample_name }}</td>
                                   <td>{{ item?.result }}</td>
                                   <td></td>
                              </tr>
                         </tbody>
                    </table>
               </section>

               <div class="footer">
                    <div class="role">Receptionist</div>
                    <div class="role">Sample receiver</div>
                    <div class="role">Sample responsible</div>
               </div>
          </div>
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import BarcodeComponent from "../../../components/BarcodeComponent.vue";

     import { useinvoicesStore } from "@/store/modules/invoices";
     export default {
          components: { BarcodeComponent },
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord"]),

               printAlone() {
                    return this.printRecord?.test_groups_all?.map((group) => ({
                         name: group?.name,
                         category: group?.category,
                         testlength: group?.tests_print_alone?.length,
                         tests_print_alone: group?.tests_print_alone,

                         // Keeping other group properties like 'cultures' if necessary
                    }));
               },
               NotprintAlone() {
                    return this.printRecord?.test_groups_all?.map((group) => ({
                         name: group?.name,
                         category: group?.category,
                         testlength: group?.tests_not_print_alone?.length,
                         tests_not_print_alone: group?.tests_not_print_alone,
                    }));
               },
               cultures() {
                    return this.printRecord?.test_groups_all?.map((group) => ({
                         name: group?.name,
                         category: group?.category,
                         culturesLength: group?.cultures?.length,
                         cultures: group?.cultures, // Keeping other group properties like 'cultures' if necessary
                    }));
               },
          },

          methods: {},
     };
</script>
<style scoped>
     #job {
          display: none;
     }
</style>
