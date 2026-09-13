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
               <div class="col-3 pb-0">
                    <label class="block text-md mb-3">{{ t("report_type") }}</label>

                    <Dropdown
                         class="w-full listpadding"
                         v-model="record.report_type"
                         :options="reports_Type"
                         optionLabel="label"
                         optionValue="value" />
               </div>

               <div class="col-3 pb-0" v-if="Role != 4">
                    <label class="block text-md mb-3">{{ t("branch") }}</label>

                    <select v-model="record.lab_id" class="w-full listpadding-b">
                         <option :value="branchData.id">{{ branchData.name }}</option>
                         <option v-for="(branch, index) in branches" :key="index" :value="branch.id">
                              {{ branch.name }}
                         </option>
                    </select>
               </div>
               <div class="col-3 pb-0" v-if="record.report_type == 'from_lab' || record.report_type == 'to_lab'">
                    <label class="block text-md mb-3">{{ t("lab") }}</label>

                    <Dropdown
                         class="w-full listpadding"
                         v-model="record.id"
                         :options="labs"
                         optionLabel="name"
                         optionValue="id" />
               </div>
               <div class="col-3 pb-0" v-if="record.report_type == 'referrals'">
                    <label class="block text-md mb-3">{{ t("referrals") }}</label>

                    <Dropdown
                         class="w-full listpadding"
                         v-model="record.id"
                         :options="records"
                         optionLabel="name"
                         optionValue="id" />
               </div>
               <div class="col-3 pb-0" v-if="record.report_type == 'sample_collectors'">
                    <label class="block text-md mb-3">{{ t("sample_collector") }}</label>

                    <Dropdown
                         class="w-full listpadding"
                         v-model="record.id"
                         :options="collectorsList"
                         optionLabel="name"
                         optionValue="id" />
               </div>
               <div class="col-3 pb-0" v-if="record.report_type == 'contracts'">
                    <label class="block text-md mb-3">{{ t("contracts") }}</label>

                    <Dropdown
                         class="w-full listpadding"
                         v-model="record.id"
                         :options="contractsList"
                         optionLabel="name"
                         optionValue="id" />
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
                    <div class="card-header">
                         <h5 class="card-title">{{ t("Summary") }}</h5>
                    </div>
                    <br />
                    <div class="card-body">
                         <div class="grid">
                              <div
                                   v-if="reports.subtotal >= 0"
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-info-box text-info">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_subtotal">
                                                  {{ reports.subtotal.toLocaleString() }}
                                             </h4>
                                             <span>{{ t("Subtotal") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   v-if="reports.discount >= 0"
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-navy-box text-navy">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_discount">
                                                  {{ reports.discount.toLocaleString() }}
                                             </h4>
                                             <span>{{ t("discount") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   v-if="reports.total_amount >= 0"
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-info-box text-info">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_total">
                                                  {{ reports.total_amount.toLocaleString() }}
                                             </h4>
                                             <span>{{ t("total_amount") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   v-if="reports.paid_amount >= 0"
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-secondary-box text-secondary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_paid">
                                                  {{ reports.paid_amount.toLocaleString() }}
                                             </h4>
                                             <span>{{ t("paid_amount") }}</span>
                                        </div>
                                   </div>
                              </div>
                              <div
                                   v-if="reports.due >= 0"
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-primary-box text-primary">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_due">{{ reports.due.toLocaleString() }}</h4>
                                             <span>{{ t("Due") }}</span>
                                        </div>
                                   </div>
                              </div>

                              <div
                                   v-if="reports.total_invoices >= 0"
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
                                             <h4 class="m-0" id="summary_expenses">{{ reports.total_invoices }}</h4>
                                             <span>{{ t("total_invoices") }}</span>
                                        </div>
                                   </div>
                              </div>

                              <div
                                   v-if="reports.profit >= 0"
                                   class="col-2 col-lg-2 col-md-3 col-sm-12 col-xs-12 mt-2 mb-4 custom-success-box text-success">
                                   <div class="grid">
                                        <div class="col-4 col-lg-3 col-md-5 col-sm-2 col-xs-4">
                                             <span class="icon">
                                                  <span class="text-center">
                                                       <i class="pi pi-money-bill" style="color: slateblue"></i>
                                                  </span>
                                             </span>
                                        </div>
                                        <div class="col-8 col-lg-9 col-md-7 col-sm-10 col-xs-8">
                                             <h4 class="m-0" id="summary_profit">
                                                  {{ reports.profit.toLocaleString() }}
                                             </h4>
                                             <span>{{ t("profit") }}</span>
                                        </div>
                                   </div>
                              </div>
                         </div>
                    </div>
               </div>

               <br />
               <div class="reports" v-if="reports">
                    <TabView>
                         <TabPanel v-if="reports.tests" :header="t('tests')">
                              <div>
                                   <!-- Global Filter -->
                                   <div class="globalSearch">
                                        <input
                                             v-model="testglobalFilter"
                                             :placeholder="t('search') + '...'"
                                             class="search p-inputtext p-component mb-2" />
                                        <div class="btns">
                                             <Button
                                                  :label="t('Clear Filters')"
                                                  icon="pi pi-filter-slash"
                                                  @click="cleartestFilters"
                                                  class="p-button-secondary mb-2" />
                                        </div>
                                   </div>
                                   <br />
                                   <!-- DataTable -->
                                   <DataTable
                                        size="small"
                                        :value="filteredtests"
                                        scrollable
                                        scrollHeight="500px"
                                        responsiveLayout="scroll"
                                        paginator
                                        :rows="25">
                                        <template #empty>
                                             <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                                                  {{ t("noData") }}
                                             </div>
                                        </template>

                                        <!-- Name Column -->
                                        <Column class="text-center" field="name">
                                             <template #header>
                                                  <p>{{ t("name") }}</p>
                                                  <input
                                                       v-model="testFilters.name"
                                                       :placeholder="t('search') + '...'"
                                                       class="p-inputtext p-component" />
                                             </template>
                                        </Column>

                                        <!-- Count Column -->
                                        <Column class="text-center" field="count" style="min-width: 100px">
                                             <template #header>
                                                  <p>{{ t("count") }}</p>
                                                  <input
                                                       v-model="testFilters.count"
                                                       :placeholder="t('search') + '...'"
                                                       class="p-inputtext p-component" />
                                             </template>
                                        </Column>

                                        <!-- Total Column -->
                                        <Column class="text-center" field="total" style="min-width: 100px">
                                             <template #header>
                                                  <p>{{ t("Total") }}</p>
                                                  <input
                                                       v-model="testFilters.total"
                                                       :placeholder="t('search') + '...'"
                                                       class="p-inputtext p-component" />
                                             </template>
                                        </Column>
                                   </DataTable>
                                   <br />
                                   <hr />

                                   <br />
                                   <hr />
                              </div>
                         </TabPanel>
                         <TabPanel v-if="reports.cultures" :header="t('cultures')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="cultureglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearcultureFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredcultures"
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
                                             <input
                                                  v-model="cultureFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="cultureFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="total" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Total") }}</p>
                                             <input
                                                  v-model="cultureFilters.total"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.packages" :header="t('packages')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="packagesglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearpackagesFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredpackages"
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
                                             <input
                                                  v-model="packagesFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="packagesFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="total" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Total") }}</p>
                                             <input
                                                  v-model="packagesFilters.total"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.contracts" :header="t('contracts')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="contractsglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearcontractsFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredcontracts"
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
                                             <input
                                                  v-model="contractsFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="contractsFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="discount_percentage" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("discount_percentage") }}</p>
                                             <input
                                                  v-model="contractsFilters.discount_percentage"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.referals" :header="t('referals')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="referalsglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearreferalsFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredreferals"
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
                                             <input
                                                  v-model="referalsFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="referalsFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="discount_percentage" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("discount_percentage") }}</p>
                                             <input
                                                  v-model="referalsFilters.discount_percentage"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.patients" :header="t('patients')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="patientsglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearpatientsFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredpatients"
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
                                             <input
                                                  v-model="patientsFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="patientsFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="total_invoices" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("total_invoices") }}</p>
                                             <input
                                                  v-model="patientsFilters.total_invoices"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="total_due" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Due") }}</p>
                                             <input
                                                  v-model="patientsFilters.total_due"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="total_paid" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("paid") }}</p>
                                             <input
                                                  v-model="patientsFilters.total_paid"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.users" :header="t('users')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="usersglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearusersFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredusers"
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
                                             <input
                                                  v-model="usersFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="usersFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="total_invoices" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("total_invoices") }}</p>
                                             <input
                                                  v-model="usersFilters.total_invoices"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.payment_methods" :header="t('paymentMethods')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="payment_methodsglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearpayment_methodsFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredpayment_methods"
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
                                             <input
                                                  v-model="payment_methodsFilters.name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="count" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("count") }}</p>
                                             <input
                                                  v-model="payment_methodsFilters.count"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="total" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Total") }}</p>
                                             <input
                                                  v-model="payment_methodsFilters.total"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                         <TabPanel v-if="reports.invoices" :header="t('invoices')">
                              <!-- Global Filter -->
                              <div class="globalSearch">
                                   <input
                                        v-model="invoicesglobalFilter"
                                        :placeholder="t('search') + '...'"
                                        class="search p-inputtext p-component mb-2" />
                                   <div class="btns">
                                        <Button
                                             :label="t('Clear Filters')"
                                             icon="pi pi-filter-slash"
                                             @click="clearinvoicesFilters"
                                             class="p-button-secondary mb-2" />
                                   </div>
                              </div>
                              <br />
                              <DataTable
                                   size="small"
                                   :value="filteredinvoices"
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

                                   <Column class="text-center" field="patient_name" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("patient_name") }}</p>
                                             <input
                                                  v-model="invoicesFilters.patient_name"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>

                                   <Column class="text-center" field="due" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Due") }}</p>
                                             <input
                                                  v-model="invoicesFilters.due"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="subtotal" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Subtotal") }}</p>
                                             <input
                                                  v-model="invoicesFilters.subtotal"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="total" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("Total") }}</p>
                                             <input
                                                  v-model="invoicesFilters.total"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="discount" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("discount_percentage") }}</p>
                                             <input
                                                  v-model="invoicesFilters.discount"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="contract.name" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("contract") }}</p>
                                             <input
                                                  v-model="invoicesFilters.contract"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column
                                        class="text-center"
                                        field="contract.discount_percentage"
                                        style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("contract_commission") }}</p>
                                             <input
                                                  v-model="invoicesFilters.discount_percentage"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="referal.name" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("referal") }}</p>
                                             <input
                                                  v-model="invoicesFilters.referal"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="referal.commission" style="min-width: 100px">
                                        <template #header>
                                             <p>{{ t("referal_commission") }}</p>
                                             <input
                                                  v-model="invoicesFilters.commission"
                                                  :placeholder="t('search') + '...'"
                                                  class="p-inputtext p-component" />
                                        </template>
                                   </Column>
                                   <Column class="text-center" field="tests" style="min-width: 10px">
                                        <template #header>
                                             <p>{{ t("tests") }}</p>
                                        </template>
                                        <template #body="slotProps">
                                             <Button
                                                  icon="pi pi-eye"
                                                  class="p-eye-button mx-1"
                                                  @click="showtests(slotProps.data.tests)"></Button>
                                        </template>
                                   </Column>
                              </DataTable>
                         </TabPanel>
                    </TabView>
               </div>

          </div>
     </div>
     <TestsModal></TestsModal>
     <culturModal></culturModal>
     <packagesModal></packagesModal>
     <contractsModal></contractsModal>
     <referalsModal></referalsModal>
</template>
<script>
     import { mapActions, mapWritableState, mapGetters } from "pinia";
     import { useReportsStore } from "@/store/modules/reports";
     import TestsModal from "./components/tests_Modal.vue";
     import culturModal from "./components/cultures_Modal.vue";
     import contractsModal from "./components/contractsModal.vue";
     import packagesModal from "./components/packagesModal.vue";
     import referalsModal from "./components/referals.vue";
     import { uselabsStore } from "@/store/modules/labs";
     import { useReferralsStore } from "@/store/modules/referrals";
     import { useContractsStore } from "@/store/modules/contracts";
     import { useUsersStore } from "@/store/modules/users";
     export default {
          data() {
               return {
                    branchData: {},
                    testglobalFilter: "",
                    payment_methodsglobalFilter: "",
                    patientsglobalFilter: "",
                    cultureglobalFilter: "",
                    packagesglobalFilter: "",
                    contractsglobalFilter: "",
                    referalsglobalFilter: "",
                    invoicesglobalFilter: "",
                    usersglobalFilter: "", // Holds global filter value
                    testFilters: {
                         name: "",
                         count: "",
                         total: "",
                    },
                    cultureFilters: {
                         name: "",
                         count: "",
                         total: "",
                    },
                    packagesFilters: {
                         name: "",
                         count: "",
                         total: "",
                    },
                    contractsFilters: {
                         name: "",
                         count: "",
                         discount_percentage: "",
                    },
                    referalsFilters: {
                         name: "",
                         count: "",
                         discount_percentage: "",
                    },
                    invoicesFilters: {
                         patient_name: "",
                         due: "",
                         subtotal: "",
                         total: "",
                         discount: "",
                         contract: "",
                         discount_percentage: "",
                         referal: "",
                         commission: "",
                    },
                    patientsFilters: {
                         name: "",
                         count: "",
                         total_invoices: "",
                         total_due: "",
                         total_paid: "",
                    },
                    usersFilters: {
                         name: "",
                         count: "",
                         total_invoices: "",
                    },
                    payment_methodsFilters: {
                         name: "",
                         count: "",
                         total: "",
                    },
               };
          },
          components: {
               TestsModal,
               culturModal,
               packagesModal,
               contractsModal,
               referalsModal,
          },
          mounted() {
               this.record.start_date = new Date().toISOString().split("T")[0];
               this.record.end_date = new Date().toISOString().split("T")[0];
               this.record.lab_id = this.User.id;
               this.branchData = { id: this.User.id, name: this.User.name };
               this.Getreports();
               this.Getlabs();
               this.GetRecords();
               this.collectors();
               this.GetContracts();
               this.Getbranches();
          },

          computed: {
               ...mapWritableState(useUsersStore, ["branches"]),
               ...mapWritableState(useContractsStore, ["contractsList"]),
               ...mapWritableState(uselabsStore, ["collectorsList"]),
               ...mapWritableState(useReferralsStore, ["records"]),
               ...mapWritableState(uselabsStore, ["labs"]),

               ...mapWritableState(useReportsStore, [
                    "record",
                    "reports",
                    "totalCount",
                    "testdialog",
                    "culturedialog",
                    "tests",
                    "cultures",
                    "packdialog",
                    "packages",
                    "contracts",
                    "contractsdialog",
                    "referals",
                    "referalsdialog",
               ]),
               filteredtests() {
                    // Filter logic combining global and column filters
                    return this.reports?.tests?.filter((record) => {
                         const globalMatch = this.testglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.testglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.testFilters).every((key) => {
                              if (!this.testFilters[key]) return true;
                              return String(record[key]).toLowerCase().includes(this.testFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredcultures() {
                    // Filter logic combining global and column filters
                    return this.reports?.cultures?.filter((record) => {
                         const globalMatch = this.cultureglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.cultureglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.cultureFilters).every((key) => {
                              if (!this.cultureFilters[key]) return true;
                              return String(record[key]).toLowerCase().includes(this.cultureFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredpackages() {
                    // Filter logic combining global and column filters
                    return this.reports?.packages?.filter((record) => {
                         const globalMatch = this.packagesglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.packagesglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.packagesFilters).every((key) => {
                              if (!this.packagesFilters[key]) return true;
                              return String(record[key])
                                   .toLowerCase()
                                   .includes(this.packagesFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredcontracts() {
                    // Filter logic combining global and column filters
                    return this.reports?.contracts?.filter((record) => {
                         const globalMatch = this.contractsglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.contractsglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.contractsFilters).every((key) => {
                              if (!this.contractsFilters[key]) return true;
                              return String(record[key])
                                   .toLowerCase()
                                   .includes(this.contractsFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredreferals() {
                    // Filter logic combining global and column filters
                    return this.reports?.referals?.filter((record) => {
                         const globalMatch = this.referalsglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.referalsglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.referalsFilters).every((key) => {
                              if (!this.referalsFilters[key]) return true;
                              return String(record[key])
                                   .toLowerCase()
                                   .includes(this.referalsFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredpatients() {
                    // Filter logic combining global and column filters
                    return this.reports?.patients?.filter((record) => {
                         const globalMatch = this.patientsglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.patientsglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.patientsFilters).every((key) => {
                              if (!this.patientsFilters[key]) return true;
                              return String(record[key])
                                   .toLowerCase()
                                   .includes(this.patientsFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredinvoices() {
                    // Filter logic combining global and column filters
                    return this.reports?.invoices?.filter((record) => {
                         const matchesGlobalFilter = this.invoicesglobalFilter
                              ? record.patient_name?.toLowerCase().includes(this.invoicesglobalFilter.toLowerCase()) ||
                                record.due?.toString().includes(this.invoicesglobalFilter.toString()) ||
                                record.subtotal?.toString().includes(this.invoicesglobalFilter.toString()) ||
                                record.total?.toString().includes(this.invoicesglobalFilter.toString()) ||
                                record.discount?.toString().includes(this.invoicesglobalFilter.toString()) ||
                                record.contract.name?.toLowerCase().includes(this.invoicesglobalFilter.toLowerCase()) ||
                                record.contract.discount_percentage
                                     ?.toString()
                                     .includes(this.invoicesglobalFilter.toString()) ||
                                record.referal.name?.includes(this.invoicesglobalFilter.toLowerCase()) ||
                                record.referal.commission?.includes(this.invoicesglobalFilter.toLowerCase())
                              : true;

                         const matchesColumnFilters =
                              (!this.invoicesFilters.patient_name ||
                                   record.patient_name
                                        ?.toLowerCase()
                                        .includes(this.invoicesFilters.name.toLowerCase())) &&
                              (!this.invoicesFilters.due ||
                                   record.due?.toString().includes(this.invoicesFilters.due.toString())) &&
                              (!this.invoicesFilters.subtotal ||
                                   record.subtotal?.toString().includes(this.invoicesFilters.subtotal.toString())) &&
                              (!this.invoicesFilters.total ||
                                   record.total?.toString().includes(this.invoicesFilters.total.toString())) &&
                              (!this.invoicesFilters.discount ||
                                   record.discount?.toString().includes(this.invoicesFilters.discount.toString())) &&
                              (!this.invoicesFilters.contract ||
                                   record.contract?.name
                                        ?.toString()
                                        .includes(this.invoicesFilters.contract.toString())) &&
                              (!this.invoicesFilters.discount_percentage ||
                                   record.contract?.discount_percentage
                                        ?.toString()
                                        .includes(this.invoicesFilters.discount_percentage.toString())) &&
                              (!this.invoicesFilters.referal ||
                                   record.referal?.name
                                        ?.toLowerCase()
                                        .includes(this.invoicesFilters.referal.toLowerCase())) &&
                              (!this.invoicesFilters.commission ||
                                   record.referal?.commission
                                        .toString()
                                        .includes(this.invoicesFilters.commission.toString()));

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
               filteredpayment_methods() {
                    // Filter logic combining global and column filters
                    return this.reports?.payment_methods?.filter((record) => {
                         const globalMatch = this.payment_methodsglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value)
                                          .toLowerCase()
                                          .includes(this.payment_methodsglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.payment_methodsFilters).every((key) => {
                              if (!this.payment_methodsFilters[key]) return true;
                              return String(record[key])
                                   .toLowerCase()
                                   .includes(this.payment_methodsFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               filteredusers() {
                    // Filter logic combining global and column filters
                    return this.reports?.users?.filter((record) => {
                         const globalMatch = this.usersglobalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.usersglobalFilter.toLowerCase())
                                )
                              : true;

                         const columnMatch = Object.keys(this.usersFilters).every((key) => {
                              if (!this.usersFilters[key]) return true;
                              return String(record[key]).toLowerCase().includes(this.usersFilters[key].toLowerCase());
                         });

                         return globalMatch && columnMatch;
                    });
               },
               reports_Type() {
                    if (this.lang == "en") {
                         return [
                              {
                                   value: "accounting",
                                   label: "accounting",
                              },

                              {
                                   value: "sample_collectors",
                                   label: "sample_collectors",
                              },
                              {
                                   value: "referrals",
                                   label: "referrals",
                              },
                              {
                                   value: "from_lab",
                                   label: "from_lab",
                              },
                              {
                                   value: "to_lab",
                                   label: "to_lab",
                              },
                              {
                                   value: "contracts",
                                   label: "contracts",
                              },
                         ];
                    } else {
                         return [
                              {
                                   value: "accounting",
                                   label: "الحسابات",
                              },

                              {
                                   value: "sample_collectors",
                                   label: "جامع العينات",
                              },
                              {
                                   value: "referrals",
                                   label: "الاحالات",
                              },
                              {
                                   value: "from_lab",
                                   label: "من مختبر",
                              },
                              {
                                   value: "to_lab",
                                   label: "الى مختبر",
                              },
                              {
                                   value: "contracts",
                                   label: "جهات العقد",
                              },
                         ];
                    }
               },
          },
          methods: {
               ...mapActions(useUsersStore, { Getbranches: "GetRecords" }),
               ...mapActions(useContractsStore, ["GetContracts"]),
               ...mapActions(uselabsStore, ["Getlabs", "collectors"]),
               ...mapActions(useReportsStore, ["Getreports"]),
               ...mapActions(useReferralsStore, ["GetRecords"]),

               showtests(tests) {
                    this.tests = tests;
                    this.testdialog = true;
               },
               showculture(cultures) {
                    this.cultures = cultures;
                    this.culturedialog = true;
               },
               showPackges(packages) {
                    this.packages = packages;
                    this.packdialog = true;
               },
               showcontracts(contracts) {
                    this.contracts = contracts;
                    this.contractsdialog = true;
               },
               showreferals(referals) {
                    this.referals = referals;
                    this.referalsdialog = true;
               },
               cleartestFilters() {
                    this.testglobalFilter = "";
                    this.testFilters = {
                         name: "",
                         count: "",
                         total: "",
                    };
               },
               clearcultureFilters() {
                    this.cultureglobalFilter = "";
                    this.cultureFilters = {
                         name: "",
                         count: "",
                         total: "",
                    };
               },
               clearpackagesFilters() {
                    this.packagesglobalFilter = "";
                    this.packagesFilters = {
                         name: "",
                         count: "",
                         total: "",
                    };
               },
               clearcontractsFilters() {
                    this.contractsglobalFilter = "";
                    this.contractsFilters = {
                         name: "",
                         count: "",
                         discount_percentage: "",
                    };
               },
               clearreferalsFilters() {
                    this.referalsglobalFilter = "";
                    this.referalsFilters = {
                         name: "",
                         count: "",
                         discount_percentage: "",
                    };
               },
               clearinvoicesFilters() {
                    this.invoicesglobalFilter = "";
                    this.invoicesFilters = {
                         patient_name: "",
                         due: "",
                         subtotal: "",
                         total: "",
                         discount: "",
                         contract: "",
                         discount_percentage: "",
                         referal: "",
                         commission: "",
                    };
               },
               clearpatientsFilters() {
                    this.patientsglobalFilter = "";
                    this.patientsFilters = {
                         name: "",
                         count: "",
                         total_invoices: "",
                    };
               },
               clearusersFilters() {
                    this.usersglobalFilter = "";
                    this.usersFilters = {
                         name: "",
                         count: "",
                         total_invoices: "",
                    };
               },
               clearpayment_methodsFilters() {
                    this.payment_methodsglobalFilter = "";
                    this.payment_methodsFilters = {
                         name: "",
                         count: "",
                         total: "",
                    };
               },
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
          padding: 8px !important;
          border-radius: 5px;
     }
     .listpadding-b {
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
