<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("reports") }}</h5>
          </div>

          <div class="grid pb-0" style="align-items: flex-end">
               <div class="col-3 pb-0">
                    <label class="block text-md mb-3">{{ t("from_date") }}</label>
                    <InputText class="w-full padding" required type="date" v-model="record.start_date" />
               </div>
               <div class="col-3 pb-0">
                    <label class="block text-md mb-3">{{ t("to_date") }}</label>
                    <InputText class="w-full padding" required type="date" v-model="record.end_date" />
               </div>
               <div class="col-3 pb-0" v-if="Role != 4">
                    <label class="block text-md mb-3">{{ t("branch") }}</label>

                    <select v-model="record.lab_id" class="w-full listpadding">
                         <option :value="branchData.id">{{ branchData.name }}</option>
                         <option v-for="(branch, index) in branches" :key="index" :value="branch.id">
                              {{ branch.name }}
                         </option>
                    </select>
                    <!-- <Dropdown
                         class="w-full listpadding"
                         v-model="record.lab_id"
                         :options="branches"
                         optionLabel="name"
                         optionValue="id" /> -->
               </div>
               <div class="col-3 pb-0">
                    <Button :label="t('search')" class="p-eye-button mx-1" @click="Getreports"></Button>
               </div>
          </div>
          <br />
          <div class="card flex justify-content-center mb-5" v-if="totalCount?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <br />
               <!-- ------------------ -->

               <div class="card card-primary">
                    <!-- <div class="card-header">
                         <h5 class="card-title">{{ t("Summary") }}</h5>
                    </div> -->
                    <br />
                    <div class="card-body">
                         <div class="grid">
                              <!-- <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-info-box text-info">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-objects-column" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_subtotal">{{ reports.total_samples }}</h4>
                                             <span>{{ t("samples") }}</span>
                                        </div>
                                   </div>
                              </div> -->
                              <!-- <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-navy-box text-navy">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-pause" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_discount">{{ reports.total_pending }}</h4>
                                             <span>{{ t("total_pending") }}</span>
                                        </div>
                                   </div>
                              </div> -->
                              <!-- <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-info-box text-info">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-check" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_total">{{ reports.total_completed }}</h4>
                                             <span>{{ t("total_completed") }}</span>
                                        </div>
                                   </div>
                              </div> -->
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-secondary-box text-secondary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-check" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_paid">{{ reports.tests.completed }}</h4>
                                             <span>{{ t("test_completed") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-primary-box text-primary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-pause" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_due">{{ reports.tests.pending }}</h4>
                                             <span>{{ t("test_pending") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-primary-box text-primary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-objects-column" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_due">{{ reports.tests.total }}</h4>
                                             <span>{{ t("tests") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-secondary-box text-secondary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-check" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_paid">{{ reports.cultures.completed }}</h4>
                                             <span>{{ t("cultures_completed") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-primary-box text-primary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-pause" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_due">{{ reports.cultures.pending }}</h4>
                                             <span>{{ t("cultures_pending") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-primary-box text-primary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-objects-column" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_due">{{ reports.cultures.total }}</h4>
                                             <span>{{ t("cultures") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-danger-box text-danger">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_expenses">{{ reports.invoices.total }}</h4>
                                             <span>{{ t("total_invoices") }}</span>
                                        </div>
                                   </div>
                              </div>

                              <div
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-success-box text-success">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-user-plus" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_profit">{{ reports.patients.total }}</h4>
                                             <span>{{ t("patients") }}</span>
                                        </div>
                                   </div>
                              </div>
                         </div>
                    </div>
               </div>

               <br />
          </div>
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useReportsStore } from "@/store/modules/reports";
     import { useUsersStore } from "@/store/modules/users";
     export default {
          data() {
               return { branchData: {} };
          },
          components: {},
          created() {},
          mounted() {
               this.record.start_date = new Date().toISOString().split("T")[0];
               this.record.end_date = new Date().toISOString().split("T")[0];
               this.record.report_type = "quick_summary";
               this.record.lab_id = this.User.id;
               this.branchData = { id: this.User.id, name: this.User.name };
               this.Getreports();
               this.Getbranches();
          },

          computed: {
               ...mapWritableState(useReportsStore, ["record", "reports", "totalCount"]),
               ...mapWritableState(useUsersStore, ["branches"]),
          },
          methods: {
               ...mapActions(useUsersStore, { Getbranches: "GetRecords" }),
               ...mapActions(useReportsStore, ["Getreports"]),
          },
     };
</script>
<style scoped>
     .data {
          width: 100%;
          text-align: center;
          font-weight: 500;
          border: 1px solid #3b82f65e;
          border-radius: 4px;
          padding: 30px 0;
     }
     .table {
          width: 100%;
     }
     thead {
          height: 42px;
     }
     .padding {
          padding: 11px !important;
          border-radius: 5px;
     }
     .listpadding {
          padding: 15px !important;
          border-radius: 5px;
          border-color: #d1d5db;
     }
     .icon i {
          width: 50px;
          height: 50px;
          padding: 12px 0 0 0;
          font-size: x-large;
     }
     .icon {
          width: 55px;
          height: 55px;
          text-align: center;
     }
     .pi-money-bill :before {
          content: "\f53a";
     }
     .custom-navy-box .icon {
          display: block;
          color: #001f3f;
          background-color: #001f3f2b;
          border-radius: 50%;
     }
     .custom-info-box .icon {
          display: block;
          color: #00cfe8;
          background-color: rgba(0, 207, 232, 0.12);
          border-radius: 50%;
     }
     .custom-secondary-box .icon {
          display: block;
          color: #6c757d;
          background-color: rgba(128, 128, 128, 0.12);
          border-radius: 50%;
     }
     .custom-primary-box .icon {
          display: block;
          color: #3038ce;
          background-color: rgba(115, 103, 240, 0.12);
          border-radius: 50%;
     }
     .custom-commission-box .icon {
          display: block;
          color: #3038ce;
          background-color: rgba(250, 230, 247);
          border-radius: 50%;
     }
     .custom-danger-box .icon {
          display: block;
          color: #ea5455;
          background-color: rgba(234, 84, 85, 0.12);
          border-radius: 50%;
     }
     .custom-warning-box .icon {
          display: block;
          color: #ff9f43;
          background-color: rgba(255, 165, 0, 0.12);
          border-radius: 50%;
     }
     .custom-success-box .icon {
          display: block;
          color: #030377;
          background-color: rgba(40, 199, 111, 0.12);
          border-radius: 50%;
     }
</style>
