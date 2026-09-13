<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("Antibiotics") }}</h5>
               <Button
                    v-if="havePermission('antibiotics create')"
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
                    :value="filteredAntibiotics"
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

                    <Column class="text-center" field="scientific_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("scientific_name") }}</p>
                              <input
                                   v-model="filters.scientific_name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input
                                   v-model="filters.lab"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="common_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("common_name") }}</p>
                              <input
                                   v-model="filters.common_name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="short_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("short_name") }}</p>
                              <input
                                   v-model="filters.short_name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="actions" style="min-width: 140px" >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('antibiotics edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('antibiotics delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>

          <AntibioticsModal></AntibioticsModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useAntibioticsStore } from "@/store/modules/Antibiotics";
     import AntibioticsModal from "./components/Anti_modal.vue";
     import { showAlertWithConfirm } from "@/utils/helper";
     import * as XLSX from "xlsx";
       import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    globalFilter: "",

                    filters: {
                         scientific_name: "",
                         common_name: "",
                         short_name: "",
                         lab: "",
                    },
               };
          },
          mounted() {
               this.GetAntibiotics();
          },
          components: {
               AntibioticsModal,
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapWritableState(useAntibioticsStore, ["Antibiotics", "record", "dialog", "totalCount"]),
               /**
                * Filters the list of antibiotics based on global and column-specific filters.
                *
                *  The filtered list of antibiotics.
                *
                * The filtering logic inludes:
                * - Global filter: Checks if any of the antibiotic properties (scientific_name, common_name, lab, short_name)
                *   contain the global filter string (case-insensitive).
                * - Column-specific filters: Checks if each antibiotic property matches the corresponding filter value
                *   (case-insensitive). If a column filter is not provided, it is ignored in the filtering process.
                *
                * The function returns only those antibiotics that match both the global filter and all the provided column filters.
                */
               filteredAntibiotics() {
                    return this.Antibiotics.filter((Antibiotics) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? Antibiotics.scientific_name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                Antibiotics.common_name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                Antibiotics.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                Antibiotics.short_name?.toLowerCase().includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnfilters =
                              (!this.filters.scientific_name ||
                                   Antibiotics?.scientific_name
                                        .toLowerCase()
                                        .includes(this.filters?.scientific_name.toLowerCase())) &&
                              (!this.filters.scientific_name ||
                                   Antibiotics?.lab?.toLowerCase().includes(this.filters?.lab?.toLowerCase())) &&
                              (!this.filters?.common_name ||
                                   Antibiotics?.common_name
                                        .toLowerCase()
                                        .includes(this.filters?.common_name.toLowerCase())) &&
                              (!this.filters?.short_name ||
                                   Antibiotics?.short_name
                                        .toLowerCase()
                                        .includes(this.filters?.short_name.toLowerCase()));

                         return matchesGlobalFilter && matchesColumnfilters;
                    });
               },
          },
          methods: {
               ...mapActions(useAntibioticsStore, ["RemoveAntibiotics", "GetAntibiotics"]),
               editRecord(record) {
                    Object.assign(this.record, record);
                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.dialog = true;
               },

               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemoveAntibiotics();
                         }
                    });
               },
               clearfilters() {
                    this.globalFilter = "";
                    this.filters = {
                         scientific_name: "",
                         common_name: "",
                         short_name: "",
                         lab: "",
                    };
               },

               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredAntibiotics, {
                         header: ["index", "scientific_name", "common_name", "short_name", "lab"],
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
