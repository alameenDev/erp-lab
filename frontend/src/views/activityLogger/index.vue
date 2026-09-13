<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("activityLogger") }}</h5>
          </div>
          <!-- <users-filter></users-filter> -->

          <div class="card flex justify-content-center mb-5" v-if="totalCount?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <div>
                    <!-- Global Search Section -->
                    <div class="globalSearch">
                         <input
                              v-model="globalFilter"
                              :placeholder="t('search') + '...'"
                              class="search p-inputtext p-component mb-2" />
                         <div class="btns">
                              <Button
                                   :label="t('Clear Filters')"
                                   icon="pi pi-filter-slash"
                                   @click="clearFilters"
                                   class="p-button-secondary mb-2" />
                              <Button
                                   :label="t('Export to Excel')"
                                   icon="pi pi-file-excel"
                                   @click="exportToExcel"
                                   class="p-button-success mb-2" />
                         </div>
                    </div>

                    <br />
                    <br />

                    <!-- Data Table -->
                    <DataTable
                         size="small"
                         :value="filteredrecords"
                         scrollable
                         scrollHeight="500px"
                         responsiveLayout="scroll"
                         paginator
                         :rows="25">
                         <!-- Empty State -->
                         <template #empty>
                              <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                                   {{ t("noData") }}
                              </div>
                         </template>

                         <!-- Columns -->
                         <Column class="text-center" header="#" field="index" />
                         <Column class="text-center" field="causer_id">
                              <template #header>
                                   <p>{{ t("causer_id") }}</p>
                                   <input
                                        v-model="Filters.causer_id"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                         <Column class="text-center" field="causer_name">
                              <template #header>
                                   <p>{{ t("causer_name") }}</p>
                                   <input
                                        v-model="Filters.causer_name"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                         <Column class="text-center" field="causer_role">
                              <template #header>
                                   <p>{{ t("userRole") }}</p>
                                   <input
                                        v-model="Filters.causer_role"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                         <Column class="text-center" field="log_name">
                              <template #header>
                                   <p>{{ t("log_name") }}</p>
                                   <input
                                        v-model="Filters.log_name"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                         <Column class="text-center" field="description">
                              <template #header>
                                   <p>{{ t("description") }}</p>
                                   <input
                                        v-model="Filters.description"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                         <Column class="text-center" field="created_at">
                              <template #header>
                                   <p>{{ t("activityDate") }}</p>
                                   <Calendar
                                        v-model="Filters.activityDate"
                                        :placeholder="t('search') + '...'"
                                        showIcon
                                        dateFormat="yy-mm-dd" />
                              </template>
                              <template #body="slotProps">
                                   {{ dateTimeFormat(slotProps.data.created_at) }}
                              </template>
                         </Column>
                         <Column class="text-center" field="subject_id">
                              <template #header>
                                   <p>معرف المعدل عليه</p>
                                   <input
                                        v-model="Filters.subject_id"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                         <Column class="text-center" field="subject_type">
                              <template #header>
                                   <p>الجزء المعدل عليه</p>
                                   <input
                                        v-model="Filters.subject_type"
                                        :placeholder="t('search') + '...'"
                                        class="p-inputtext p-component" />
                              </template>
                         </Column>
                    </DataTable>
               </div>
          </div>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { ActivityStore } from "@/store/modules/activityLogger";
     import { format } from "date-fns";
     import * as XLSX from "xlsx";
     export default {
          data() {
               return {
                    globalFilter: "",
                    Filters: {
                         log_name: "",
                         description: "",
                         activityDate: "",
                         causer_name: "",
                         causer_id: "",
                         causer_role: "",
                         subject_id: "",
                         subject_type: "",
                    },
               };
          },
          mounted() {
               this.GetRecords();
          },
          computed: {
               ...mapWritableState(ActivityStore, ["records", "totalCount"]),
               filteredrecords() {
                    return this.records.filter((record) => {
                         // Global filter
                         const matchesGlobalFilter = this.globalFilter
                              ? Object.values(record).some((value) =>
                                     String(value).toLowerCase().includes(this.globalFilter.toLowerCase())
                                )
                              : true;

                         // Column-specific filters
                         const matchesColumnFilters =
                              (!this.Filters.log_name ||
                                   record.log_name?.toLowerCase().includes(this.Filters.log_name.toLowerCase())) &&
                              (!this.Filters.subject_type ||
                                   record.subject_type
                                        ?.toLowerCase()
                                        .includes(this.Filters.subject_type.toLowerCase())) &&
                              (!this.Filters.causer_id ||
                                   record.causer_id?.toString().includes(this.Filters.causer_id.toString())) &&
                              (!this.Filters.subject_id ||
                                   record.subject_id?.toString().includes(this.Filters.subject_id.toString())) &&
                              (!this.Filters.causer_role ||
                                   record.causer_role?.toString().includes(this.Filters.causer_role.toString())) &&
                              (!this.Filters.description ||
                                   record.description
                                        ?.toLowerCase()
                                        .includes(this.Filters.description.toLowerCase())) &&
                              (!this.Filters.causer_name ||
                                   record.causer_name
                                        ?.toLowerCase()
                                        .includes(this.Filters.causer_name.toLowerCase())) &&
                              (!this.Filters.activityDate ||
                                   this.dateTimeFormat(record.created_at)
                                        .toLowerCase()
                                        .includes(String(this.Filters.activityDate).toLowerCase()));

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
          },
          methods: {
               ...mapActions(ActivityStore, ["GetRecords"]),
               dateTimeFormat(date) {
                    // Format the date if needed
                    return new Date(date).toLocaleString();
               },
               clearFilters() {
                    this.globalFilter = "";
                    this.Filters = {
                         log_name: "",
                         description: "",
                         activityDate: "",
                         subject_id: "",
                         subject_type: "",
                    };
               },
               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                         header: ["index", "log_name", "description", "activityDate", "subject_type", "subject_id"],
                    });

                    // Create a new workbook and append the worksheet
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                    // Write the workbook to a file
                    XLSX.writeFile(wb, "export.xlsx");
               },
          },
     };
</script>
<style scoped></style>
