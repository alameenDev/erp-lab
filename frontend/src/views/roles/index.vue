<template>
  <div class="card filterTable pb-0">
       <div class="flex justify-content-between mb-2">
            <h5>{{ t("role_permissions") }}</h5>
            <Button
                 v-if="havePermission('roles create')"
                 size="small"
                 class="p-button-success"
                 :label="t('add')"
                 @click="addRecord"></Button>
       </div>

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
                           <!-- <input
                                v-model="Filters.name"
                                :placeholder="t('search') + '...'"
                                class="p-inputtext p-component" /> -->
                      </template>
                 </Column>
                 <!-- <Column class="text-center" field="lab">
                      <template #header>
                           <p>{{ t("lab/bruanch") }}</p>
                           <input
                                v-model="Filters.lab"
                                :placeholder="t('search') + '...'"
                                class="p-inputtext p-component" />
                      </template>
                 </Column> -->
                 <Column class="text-center" field="actions"  >
                      <template #header>
                           <p>{{ t("actions") }}</p>
                      </template>
                      <template #body="slotProps">
                           <Button
                                v-if="havePermission('roles edit')"
                                icon="pi pi-pencil"
                                class="p-button-rounded mx-1"
                                @click="editRecord(slotProps.data)"></Button>
                           <Button
                                v-if="havePermission('roles delete')"
                                icon="pi pi-trash"
                                class="p-button-rounded mx-1 p-button-danger"
                                @click="deleteRecord(slotProps.data)"></Button>
                      </template>
                 </Column>
            </DataTable>
       </div>
       <roleModal></roleModal>
  </div>
</template>

<script>
  import { mapActions, mapWritableState } from "pinia";
  import roleModal from "./components/role_model.vue";
  import { useRolesstore } from "@/store/modules/roles";
  import { showAlertWithConfirm } from "@/utils/helper";
  import { format } from "date-fns";
  import * as XLSX from "xlsx";
    import { useAuthStore } from '@/store/modules/auth'
  export default {
       data() {
            return {
                 Filters: {
                      role: "",
                      lab: "",
                 },
                 globalFilter: "", // Global filter input
            };
       },
       components: {
            roleModal,
       },

       mounted() {
            this.GetRoles();
       },
       computed: {
            ...mapWritableState(useAuthStore, ["havePermission"]),
            ...mapWritableState(useRolesstore, ["roles", "totalCount", "record", "dialog"]),
            filteredRecords() {
                 return this.roles.filter((record) => {
                      const matchesGlobalFilter = this.globalFilter
                           ? record.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                             record.lab?.toLowerCase().includes(this.globalFilter.toLowerCase())
                           : true;

                      const matchesColumnFilters =
                           !this.Filters.name ||
                           (record.name.toLowerCase().includes(this.Filters.name.toLowerCase()) &&
                                this.Filters.lab) ||
                           record.lab.toLowerCase().includes(this.Filters.lab.toLowerCase());

                      return matchesGlobalFilter && matchesColumnFilters;
                 });
            },
       },
       methods: {
            ...mapActions(useRolesstore, ["GetRoles", "RemoveRoles"]),
            deleteRecord(record) {
                 showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                      if (res.value) {
                           this.record.id = record.id;
                           this.RemoveRoles();
                      }
                 });
            },

            clearFilters() {
                 this.Filters = {
                      name: "",
                      lab: "",
                 };
                 this.globalFilter = ""; // Clear global filter
            },
            exportToExcel() {
                 // Create a worksheet from the filtered records
                 const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                      header: ["index", "name", "lab"],
                 });

                 // Create a new workbook and append the worksheet
                 const wb = XLSX.utils.book_new();
                 XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                 // Write the workbook to a file
                 XLSX.writeFile(wb, "export.xlsx");
            },
            editRecord(record) {
                 Object.assign(this.record, record);
                 this.record = record
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
