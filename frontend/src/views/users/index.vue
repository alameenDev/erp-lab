<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("usersManagment") }}</h5>
               <Button
                     v-if="havePermission('users create')"
                    size="small"
                    class="p-button-success"
                    :label="t('addUser')"
                    @click="addRecord"></Button>
          </div>
          <!-- <users-filter></users-filter> -->

          <div class="card flex justify-content-center mb-5" v-if="totalCount?.toLocaleString() == 0">
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
                    <Column class="text-center" field="email">
                         <template #header>
                              <p>{{ t("email") }}</p>
                              <input
                                   v-model="Filters.email"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="phone">
                         <template #header>
                              <p>{{ t("phone_number") }}</p>
                              <input
                                   v-model="Filters.phone"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.phone ? slotProps.data.phone : "----" }}
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
                    <Column class="text-center" field="role" v-if="UserRoles">
                         <template #header>
                              <p>{{ t("userRole") }}</p>
                              <Dropdown
                                   v-model="Filters.role"
                                   :options="UserRoles"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>
                    <!-- <Column class="text-center filter-calendar" field="created_at">
                         <template #header>
                              <p>{{ t("created_at") }}</p>
                              <Calendar
                                   v-model="Filters.created_at"
                                   :placeholder="t('search') + '...'"
                                   showIcon
                                   dateFormat="yy-mm-dd" />
                         </template>
                         <template #body="slotProps">
                              {{ dateTimeFormat(slotProps.data.created_at) }}
                         </template>
                    </Column> -->
                    <Column class="text-center" field="actions"  >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('users edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('users delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('users edit')"
                                  v-tooltip.top="t('assign_role_user')"
                                   icon="pi pi-user-edit"
                                   class="p-button-rounded mx-1 p-button-info"
                                   @click="assignRole(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
               <!-- <Paginator dir="rtl" :rows="25" :totalRecords="totalCount" v-model:first="filter.pageNumber"></Paginator> -->
          </div>
          <users-modal></users-modal>
          <AuthCodeModal></AuthCodeModal>
          <assign-role-model></assign-role-model>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import UsersModal from "./components/users-modal.vue";
     import assignRoleModel from "./components/assignRoleModel.vue";
     import AuthCodeModal from "./components/authCodeModal.vue";
     import { useUsersStore } from "@/store/modules/users";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { format } from "date-fns";
     import * as XLSX from "xlsx";
       import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    Filters: {
                         name: "",
                         email: "",
                         role: "",
                         created_at: null,
                         address: "",
                         phone_number: null,
                    },
                    globalFilter: "", // Global filter input
               };
          },
          components: {
               UsersModal,
               AuthCodeModal,
               assignRoleModel,
               //UsersFilter,
          },

          mounted() {
               this.GetUserRoles();
               this.GetRecords();
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapWritableState(useUsersStore, ["records", "UserRoles", "totalCount", "record", "dialog","assignDialog"]),
               filteredRecords() {
                    return this.records.filter((record) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? record.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.email?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.address?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.phone_number?.includes(this.globalFilter.toLowerCase()) ||
                                (this.UserRoles ? this.UserRoles.find((v) => v.label === record.role)?.label ?? "" : "")
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnFilters =
                              (!this.Filters.name ||
                                   record.name.toLowerCase().includes(this.Filters.name.toLowerCase())) &&
                              (!this.Filters.email ||
                                   record.email.toLowerCase().includes(this.Filters.email.toLowerCase())) &&
                              (!this.Filters.address ||
                                   record.address.toLowerCase().includes(this.Filters.address.toLowerCase())) &&
                              (!this.Filters.phone_number ||
                                   record.phone_number
                                        .toLowerCase()
                                        .includes(this.Filters.phone_number.toLowerCase())) &&
                              (!this.Filters.role || record.role == this.Filters.role);

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
          },
          methods: {
               ...mapActions(useUsersStore, ["GetRecords", "GetUserRoles", "AddUser", "RemoveUser", "resetFilter"]),
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemoveUser();
                         }
                    });
               },
               dateMatches(recordDate, filterDate) {
                    if (!recordDate || !filterDate) return true;
                    const formattedRecordDate = format(new Date(recordDate), "yyyy-MM-dd");
                    const formattedFilterDate = format(new Date(filterDate), "yyyy-MM-dd");
                    return formattedRecordDate === formattedFilterDate;
               },

               clearFilters() {
                    this.Filters = {
                         name: "",
                         email: "",
                         role: "",
                         created_at: null,
                         address: "",
                         phone_number: null,
                    };
                    this.globalFilter = ""; // Clear global filter
               },
               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                         header: ["index", "name", "email", "phone_number", "address", "role", "created_at"],
                    });

                    // Create a new workbook and append the worksheet
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                    // Write the workbook to a file
                    XLSX.writeFile(wb, "export.xlsx");
               },
               editRecord(record) {
                    Object.assign(this.record, record);
                    this.record.role_id = this.UserRoles
                         ? this.UserRoles.find((v) => v.label === record.role)?.value ?? ""
                         : "";
                    this.dialog = true;
               },
               assignRole(record) {
                    Object.assign(this.record, record);
                
                    this.record= record;
                    // this.record.role_id = this.UserRoles
                    //      ? this.UserRoles.find((v) => v.label === record.role)?.value ?? ""
                    //      : "";
                    this.assignDialog = true;
                    console.log("this.assignDialog", this.assignDialog);
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.dialog = true;
               },
          },
     };
</script>
<style scoped></style>
