<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("priceList") }}</h5>
               <Button
                    v-if="havePermission('price list create')"
                    size="small"
                    class="p-button-success"
                    :label="t('add')"
                    @click="addRecord"></Button>
          </div>

          <div class="card flex justify-content-center mb-5" v-if="totalCount?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <div class="globalSearch">
                    <input
                         v-model="globalFilter"
                         :placeholder="t('search') + '...'"
                         class="search p-inputtext p-component mb-2" />
                    <div class="btns">
                         <Button
                              :label="t('Clear Filters')"
                              icon="pi pi-filter-slash"
                              @click="clearfilters"
                              class="p-button-secondary mb-2" />
                         <Button
                              :label="t('Export to Excel')"
                              icon="pi pi-file-excel"
                              @click="exportToExcel"
                              class="p-button-success mb-2" />
                    </div>
               </div>

               <DataTable
                    size="small"
                    :value="filteredpriceList"
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

                    <Column class="text-center" header="#" field="index" style="min-width: 10px" />
                    <Column class="text-center" field="lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input
                                   v-model="filters.lab"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="price_list_title" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("name") }}</p>
                              <input
                                   v-model="filters.price_list_title"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="discount" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("discount") }}</p>
                              <input
                                   v-model="filters.discount"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.discount ? slotProps.data.discount : "---" }}
                         </template>
                    </Column>
                    <Column class="text-center" field="price_list_type" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("price_list_type") }}</p>
                              <select v-model="filters.price_list_type" class="p-inputtext p-component">
                                   <option :value="t('is_constatnt_price')">
                                        {{ t("is_constatnt_price") }}
                                   </option>
                                   <option :value="t('it_discount')">
                                        {{ t("it_discount") }}
                                   </option>
                              </select>
                         </template>
                         <template #body="slotProps">
                              {{ getPriceListType(slotProps.data) }}
                         </template>
                    </Column>
                    <Column class="text-center" field="actions" style="min-width: 140px" >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('price list edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('price list delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>

          <priceListModal></priceListModal>
          <updateModal></updateModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import updateModal from "./components/updateModal.vue";
     import priceListModal from "./components/priceList_modal.vue";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { priceListStore } from "@/store/modules/priceList";
       import { useAuthStore } from '@/store/modules/auth'
     import * as XLSX from "xlsx";
     export default {
          data() {
               return {
                    globalFilter: "",
                    filters: {
                         price_list_title: "",
                         discount: "",
                         lab: "",
                    },
               };
          },
          mounted() {
               this.GetpriceList();
          },
          components: {
               priceListModal,
               updateModal,
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapWritableState(priceListStore, [
                    "UpdateRecord",
                    "isDiscount",
                    "priceList",
                    "dropdowns",
                    "record",
                    "UpdateList",
                    "dialog",
                    "updatedialog",
                    "totalCount",
               ]),
               filteredpriceList() {
                    return this.priceList.filter((price) => {
                         // Global filter logic
                         const matchesGlobalFilter = this.globalFilter
                              ? price.price_list_title?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                price.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                price.discount?.toString().includes(this.globalFilter.toString()) ||
                                this.getPriceListType(price).toLowerCase().includes(this.globalFilter.toLowerCase())
                              : true;

                         // Column-specific filter logic
                         const matchesPriceListTitle =
                              !this.filters.price_list_title ||
                              price.price_list_title
                                   ?.toLowerCase()
                                   .includes(this.filters.price_list_title?.toLowerCase());

                         const matchesDiscount =
                              !this.filters.discount ||
                              price.discount?.toString().includes(this.filters.discount?.toString()) ||
                              (this.filters.discount === "0" && price.discount === null);
                         const matchesLab =
                              !this.filters.lab || price.lab?.toLowerCase().includes(this.filters.lab?.toLowerCase());

                         const matchesPriceListType =
                              !this.filters.price_list_type ||
                              this.getPriceListType(price).toLowerCase() ===
                                   this.filters.price_list_type?.toLowerCase();

                         return (
                              matchesGlobalFilter &&
                              matchesPriceListTitle &&
                              matchesDiscount &&
                              matchesPriceListType &&
                              matchesLab
                         );
                    });
               },
          },
          methods: {
               ...mapActions(priceListStore, ["RemovepriceList", "GetpriceList", "GetpriceListById"]),
               async editRecord(record) {
                    await this.GetpriceListById(record.id);
                    this.record.id = record.id;
                    this.record.name = record?.price_list_title;
                    this.record.discount = record?.discount;
                    this.isDiscount = record?.discount ? false : true;

                    this.updatedialog = true;
               },
               getPriceListType(price) {
                    return price.discount == null ? this.t("is_constatnt_price") : this.t("it_discount");
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.dialog = true;
               },

               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemovepriceList();
                         }
                    });
               },
               clearfilters() {
                    this.globalFilter = "";
                    this.filters = {
                         price_list_title: "",
                         discount: "",
                         lab: "",
                    };
               },

               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredpriceList, {
                         header: ["index", "price_list_title", "discount", "lab"],
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

<style scoped>
     .p-eye-button {
          background: no-repeat;
          color: #469168e0;
          border: none;
     }
</style>
