<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("packages") }}</h5>
               <Button
                    v-if="havePermission('packages create')"
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
                              @click="clearFilters"
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
                    :value="filteredpackages"
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

                    <Column class="text-center" header="#" field="index" style="min-width: 10px" />

                    <Column class="text-center" field="name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("name") }}</p>
                              <input
                                   v-model="filters.name"
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

                    <Column class="text-center" field="shortcut" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Shortcut") }}</p>
                              <input
                                   v-model="filters.shortcut"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>

                    <Column class="text-center" field="price" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Original_Price") }}</p>
                              <input
                                   v-model="filters.original_price"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data?.price?.toLocaleString() }}
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

                    <Column class="text-center" field="cultures" style="min-width: 10px">
                         <template #header>
                              <p>{{ t("cultures") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   icon="pi pi-eye"
                                   class="p-eye-button mx-1"
                                   @click="showcultures(slotProps.data.cultures)"></Button>
                         </template>
                    </Column>

                    <Column class="text-center" field="actions" style="min-width: 200px">
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('packages edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('packages delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>

          <PackagesModal></PackagesModal>
          <culturesModal></culturesModal>
          <TestsModal></TestsModal>
     </div>
</template>

<script>
     import { mapActions, mapGetters, mapWritableState } from "pinia";
     import PackagesModal from "./components/packages_modal.vue";
     import TestsModal from "./components/testsmodal.vue";
     import culturesModal from "./components/cultures_modal.vue";
     import { usePackagesStore } from "@/store/modules/packages";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { usetestsStore } from "@/store/modules/tests";
     import { useculturesStore } from "@/store/modules/cultures";
     import * as XLSX from "xlsx";
     import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    globalFilter: "",
                    filters: {
                         name: "",
                         shortcut: "",
                         original_price: "",
                         lab: "",
                    },
               };
          },
          mounted() {
               this.Getpackages();
          },
          components: {
               PackagesModal,
               culturesModal,
               TestsModal,
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapGetters(usetestsStore, ["TestLists"]),
               ...mapGetters(useculturesStore, ["cultureLists"]),
               ...mapWritableState(usePackagesStore, [
                    "packagesList",
                    "record",
                    "tests",
                    "cultures",
                    "dialog",
                    "resdialog",
                    "testdialog",
                    "totalCount",
               ]),
               filteredpackages() {
                    return this.packagesList.filter((test) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? test.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.shortcut?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.original_price?.toString().includes(this.globalFilter.toString())
                              : true;

                         const matchesColumnfilters =
                              (!this.filters?.name ||
                                   test?.name.toLowerCase().includes(this.filters?.name.toLowerCase())) &&
                              (!this.filters?.lab ||
                                   test?.lab.toLowerCase().includes(this.filters?.lab.toLowerCase())) &&
                              (!this.filters.shortcut ||
                                   test.shortcut.toLowerCase().includes(this.filters?.shortcut?.toLowerCase())) &&
                              (!this.filters.original_price ||
                                   test.original_price?.toString().includes(this.filters?.original_price.toString()));

                         return matchesGlobalFilter && matchesColumnfilters;
                    });
               },
          },
          methods: {
               ...mapActions(usePackagesStore, ["Removepackage", "Getpackages"]),

               editRecord(record) {
                    Object.assign(this.record, record);
                    this.record.is_constant_price = record.is_constant_price == true ? true : false;

                    const testOptions = this.TestLists().map((test) => test.value); // Get all test values

                    this.record.tests = record.tests.map((test) => {
                         // Find the corresponding test object from the options
                         const matchedTest = testOptions.find((option) => option.id === test.id);
                         return matchedTest ? matchedTest : test; // If found, use matched; otherwise, fallback to the current test
                    });
                    const cultureOptions = this.cultureLists().map((culture) => culture.value); // Get all test values
                    this.record.cultures = record.cultures.map((culture) => {
                         // Find the corresponding test object from the options
                         const matchedCulture = cultureOptions.find((option) => option.id === culture.id);
                         return matchedCulture ? matchedCulture : culture; // If found, use matched; otherwise, fallback to the current test
                    });
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
                              this.Removepackage();
                         }
                    });
               },
               showcultures(result) {
                    this.cultures = result;
                    this.resdialog = true;
               },
               showtests(tests) {
                    this.tests = tests;
                    this.testdialog = true;
               },
               clearFilters() {
                    this.globalFilter = "";
                    this.filters = {
                         name: "",
                         shortcut: "",
                         original_price: "",
                         lab: "",
                    };
               },

               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredpackages, {
                         header: ["index", "name", "shortcut", "original_price", "lab"],
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
