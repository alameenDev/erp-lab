<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("referralsManagment") }}</h5>
               <Button
                    v-if="havePermission('referrals create')"
                    size="small"
                    class="p-button-success"
                    :label="t('add')"
                    @click="addRecord"></Button>
          </div>
          <!-- <users-filter></users-filter> -->

          <div class="card flex justify-content-center mb-5" v-if="totalCount.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <br />
               <br />
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
               <DataTable
                    size="small"
                    :value="filteredRecords"
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
                    <Column class="text-center" header="#" field="index" />
                    <Column class="text-center" field="name">
                         <template #header>
                              <p>{{ t("name") }}</p>
                              <input
                                   v-model="Filters.name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="lab">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input
                                   v-model="Filters.lab"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="email">
                         <template #header>
                              <p>{{ t("email") }}</p>
                              <input
                                   v-model="Filters.email"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="phone_number">
                         <template #header>
                              <p>{{ t("phone_number") }}</p>
                              <input
                                   v-model="Filters.phone_number"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="address">
                         <template #header>
                              <p>{{ t("address") }}</p>
                              <input
                                   v-model="Filters.address"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="commission">
                         <template #header>
                              <p>{{ t("commission") }}</p>
                              <input
                                   v-model="Filters.commission"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.commission?.toLocaleString() }}
                         </template>
                    </Column>
                    <Column class="text-center" field="role" v-if="UserRoles">
                         <template #header>
                              <p>{{ t("userRole") }}</p>
                              <Dropdown
                                   v-model="Filters.role"
                                   :options="UserRoles.asList()"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>

                    <Column class="text-center" field="actions"  >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('referrals edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('referrals delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
               <!-- <Paginator dir="rtl" :rows="25" :totalRecords="totalCount" v-model:first="filter.pageNumber"></Paginator> -->
          </div>
          <referralsModal></referralsModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import ReferralsModal from "./components/referrals-modal.vue";
     import { useReferralsStore } from "@/store/modules/referrals";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { ReverralUserRole } from "@/enums";
     import * as XLSX from "xlsx";
       import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    Filters: {
                         name: "",
                         email: "",
                         role: "",
                         address: "",
                         phone_number: null,
                         lab: "",
                         commission: "",
                    },
                    globalFilter: "", // Global filter input
               };
          },
          components: {
               ReferralsModal,
          },

          mounted() {
               this.GetRecords();
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               UserRoles() {
                    return ReverralUserRole;
               },
               ...mapWritableState(useReferralsStore, ["records", "totalCount", "record", "dialog"]),

               filteredRecords() {
                    return this.records.filter((record) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? record.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.email?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.address?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.phone_number?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.commission
                                     ?.toLocaleString()
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                (this.UserRoles
                                     ? this.UserRoles.asList().find((v) => v.label === record.role)?.label ?? ""
                                     : ""
                                )
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnFilters =
                              (!this.Filters.name ||
                                   record.name?.toLowerCase().includes(this.Filters.name?.toLowerCase())) &&
                              (!this.Filters.lab ||
                                   record.lab?.toLowerCase().includes(this.Filters.lab?.toLowerCase())) &&
                              (!this.Filters.email ||
                                   record.email?.toLowerCase().includes(this.Filters.email?.toLowerCase())) &&
                              (!this.Filters.address ||
                                   record.address?.toLowerCase().includes(this.Filters.address?.toLowerCase())) &&
                              (!this.Filters.phone_number ||
                                   record.phone_number
                                        ?.toLowerCase()
                                        .includes(this.Filters.phone_number?.toLowerCase())) &&
                              (!this.Filters.role || record.role?.toLowerCase() == this.Filters.role) &&
                              (!this.Filters.commission || record.commission == this.Filters.commission);

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
          },
          methods: {
               ...mapActions(useReferralsStore, ["GetRecords", "RemoveReferral"]),
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemoveReferral();
                         }
                    });
               },

               clearFilters() {
                    this.Filters = {
                         name: "",
                         email: "",
                         role: "",
                         created_at: null,
                         address: "",
                         phone_number: null,
                         commission: "",
                         lab: "",
                    };
                    this.globalFilter = ""; // Clear global filter
               },
               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                         header: ["index", "name", "email", "phone_number", "address", "commission", "role", "lab"],
                    });

                    // Create a new workbook and append the worksheet
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                    // Write the workbook to a file
                    XLSX.writeFile(wb, "export.xlsx");
               },
               editRecord(record) {
                    Object.assign(this.record, record);

                    this.record.role_id =
                         this.UserRoles?.asList().find((v) => v.label.toLowerCase() === record.role.toLowerCase())
                              ?.value || null;

                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.dialog = true;
               },
          },
     };
</script>
<style scoped></style>
