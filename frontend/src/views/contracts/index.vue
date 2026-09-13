<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("contract_Management") }}</h5>

               <Button
                    v-if="havePermission('contracts create')"
                    size="small"
                    class="p-button-success"
                    :label="t('add')"
                    @click="addRecord"></Button>
          </div>

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
                    <Column class="text-center" field="name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("name") }}</p>
                              <input
                                   v-model="Filters.name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="email" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("email") }}</p>
                              <input
                                   v-model="Filters.email"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="phone_number" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("phone_number") }}</p>
                              <input
                                   v-model="Filters.phone_number"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="address" style="min-width: 200px">
                         <template #header>
                              <p>{{ t("address") }}</p>
                              <input
                                   v-model="Filters.address"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="discount_percentage" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("discount_percentage") }}</p>
                              <input
                                   v-model="Filters.discount_percentage"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.discount_percentage.toLocaleString() }}
                         </template>
                    </Column>
                    <Column class="text-center" field="payment_percent" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("payment_percent") }}</p>
                              <input
                                   v-model="Filters.payment_percent"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.payment_percent.toLocaleString() }}
                         </template>
                    </Column>

                    <Column class="text-center" field="maximum_payment_per_invoice" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("maximum_payment_per_invoice") }}</p>
                              <input
                                   v-model="Filters.maximum_payment_per_invoice"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.maximum_payment_per_invoice.toLocaleString() }}
                         </template>
                    </Column>
                    <Column class="text-center" field="credit_limit" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("credit_limit") }}</p>
                              <input
                                   v-model="Filters.credit_limit"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.credit_limit.toLocaleString() }}
                         </template>
                    </Column>
                    <Column class="text-center" field="price_limit" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("price_limit") }}</p>
                              <input
                                   v-model="Filters.price_limit"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.price_limit.toLocaleString() }}
                         </template>
                    </Column>
                    <Column class="text-center" field="actions" style="min-width: 150px" >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('contracts edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('contracts delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
               <!-- <Paginator dir="rtl" :rows="25" :totalRecords="totalCount" v-model:first="filter.pageNumber"></Paginator> -->
          </div>
          <contructModal></contructModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import contructModal from "./components/contract-modal.vue";
     import { useContractsStore } from "@/store/modules/contract";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { ReverralUserRole } from "@/enums";
       import { useAuthStore } from '@/store/modules/auth'
     import * as XLSX from "xlsx";
     export default {
          data() {
               return {
                    Filters: {
                         name: "",
                         email: "",
                         address: "",
                         phone_number: null,
                         payment_percent: null,
                         maximum_payment_per_invoice: null,
                         credit_limit: null,
                         price_limit: null,
                         discount_percentage: null,
                    },
                    globalFilter: "", // Global filter input
               };
          },
          components: {
               contructModal,
          },

          mounted() {
               this.GetRecords();
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapWritableState(useContractsStore, ["records", "totalCount", "record", "dialog"]),

               filteredRecords() {
                    return this.records.filter((record) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? record.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.email?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.address?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.phone_number?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.payment_percent
                                     ?.toLocaleString()
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                record.maximum_payment_per_invoice
                                     ?.toLocaleString()
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                record.credit_limit
                                     ?.toLocaleString()
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                record.price_limit
                                     ?.toLocaleString()
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                record.discount_percentage
                                     ?.toLocaleString()
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
                              (!this.Filters.payment_percent ||
                                   record.payment_percent == this.Filters.payment_percent) &&
                              (!this.Filters.maximum_payment_per_invoice ||
                                   record.maximum_payment_per_invoice == this.Filters.maximum_payment_per_invoice) &&
                              (!this.Filters.credit_limit || record.credit_limit == this.Filters.credit_limit) &&
                              (!this.Filters.price_limit || record.price_limit == this.Filters.price_limit) &&
                              (!this.Filters.discount_percentage ||
                                   record.discount_percentage == this.Filters.discount_percentage);

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
          },
          methods: {
               ...mapActions(useContractsStore, ["GetRecords", , "RemoveContract"]),
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemoveContract();
                         }
                    });
               },

               clearFilters() {
                    this.Filters = {
                         name: "",
                         email: "",
                         address: "",
                         phone_number: null,
                         payment_percent: null,
                         maximum_payment_per_invoice: null,
                         credit_limit: null,
                         price_limit: null,
                         discount_percentage: null,
                    };
                    this.globalFilter = ""; // Clear global filter
               },
               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                         header: [
                              "index",
                              "name",
                              "email",
                              "phone_number",
                              "address",
                              "payment_percent",
                              "maximum_payment_per_invoice",
                              "credit_limit",
                              "price_limit",
                              "discount_percentage",
                         ],
                    });

                    // Create a new workbook and append the worksheet
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                    // Write the workbook to a file
                    XLSX.writeFile(wb, "export.xlsx");
               },
               editRecord(record) {
                    Object.assign(this.record, record);
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
