<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("test-groups") }}</h5>
               <Button
                    v-if="havePermission('test groups create')"
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
                    :value="filteredTestGroups"
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

                    <Column class="text-center" field="group_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Group_Name") }}</p>
                              <input
                                   v-model="filters.group_name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="category_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Category") }}</p>
                              <input
                                   v-model="filters.category_name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="lab_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input
                                   v-model="filters.lab_name"
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

                    <Column class="text-center" field="for_customer_price" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Customer_Price") }}</p>
                              <input
                                   v-model="filters.for_customer_price"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.for_customer_price?.toLocaleString() }}
                         </template>
                    </Column>

                    <Column class="text-center" field="original_price" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Original_Price") }}</p>
                              <input
                                   v-model="filters.original_price"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.original_price?.toLocaleString() }}
                         </template>
                    </Column>
                    <Column class="text-center" field="duration_unit" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Duration_Unit") }}</p>
                              <Dropdown
                                   v-model="filters.duration_unit"
                                   :options="durationUnitsList"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>

                    <Column class="text-center" field="test_duration" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Test_Duration") }}</p>
                              <input
                                   v-model="filters.test_duration"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>

                    <Column class="text-center" field="precautions" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Precautions") }}</p>
                              <input
                                   v-model="filters.precautions"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>

                    <Column class="text-center" field="result_comments" style="min-width: 10px">
                         <template #header>
                              <p>{{ t("Result_Comments") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   icon="pi pi-eye"
                                   class="p-eye-button mx-1"
                                   @click="showResult(slotProps.data.result_comments)"></Button>
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

                    <Column class="text-center" field="tests" style="min-width: 10px">
                         <template #header>
                              <p>{{ t("cultures") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   icon="pi pi-eye"
                                   class="p-eye-button mx-1"
                                   @click="showculture(slotProps.data?.culture)"></Button>
                         </template>
                    </Column>

                    <Column class="text-center" field="actions" style="min-width: 200px" >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                              v-if="havePermission('test groups edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('test groups delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>

          <testGroupModal></testGroupModal>
          <ResultCommentsModal></ResultCommentsModal>
          <TestsModal></TestsModal>
          <culturModal></culturModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState, mapGetters } from "pinia";
     import TestGroupModal from "./components/testGroup-modal.vue";
     import ResultCommentsModal from "./components/result_comments_modal.vue";
     import TestsModal from "./components/tests_modal.vue";
     import culturModal from "./components/cultures_modal.vue";
     import { usetestGroupsStore } from "@/store/modules/testGroups";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { usetestsStore } from "@/store/modules/tests";
     import { useculturesStore } from "@/store/modules/cultures";
     import { useDurationUnitsStore } from "@/store/modules/durationUnits";
     import * as XLSX from "xlsx";
       import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    globalFilter: "",
                    filters: {
                         category_name: "",
                         group_name: "",
                         shortcut: "",
                         for_customer_price: "",
                         original_price: "",
                         test_duration: "",
                         duration_unit: "",
                         precautions: "",
                         lab_name: "",
                    },
               };
          },
          mounted() {
               this.GettestGroups();
          },
          components: {
               TestGroupModal,
               ResultCommentsModal,
               TestsModal,
               culturModal,
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapGetters(usetestsStore, ["TestLists"]),
               ...mapGetters(useculturesStore, ["cultureGroupTestes"]),
               ...mapWritableState(useDurationUnitsStore, ["durationUnitsList"]),
               ...mapWritableState(usetestGroupsStore, [
                    "testGroups",
                    "record",
                    "dialog",
                    "cultures",
                    "culturedialog",
                    "resdialog",
                    "totalCount",
                    "testdialog",
                    "tests",
                    "result_comments",
               ]),
               filteredTestGroups() {
                    return this.testGroups.filter((test) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? test.category_name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.group_name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.lab_name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.shortcut?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.for_customer_price?.toString().includes(this.globalFilter.toString()) ||
                                test.original_price?.toString().includes(this.globalFilter.toString()) ||
                                test.test_duration?.toString().includes(this.globalFilter.toString()) ||
                                test.duration_unit?.toString().includes(this.globalFilter.toString()) ||
                                test.precautions?.toLowerCase().includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnfilters =
                              (!this.filters.category_name ||
                                   test.category_name
                                        .toLowerCase()
                                        .includes(this.filters.category_name.toLowerCase())) &&
                              (!this.filters.lab_name ||
                                   test.lab_name.toLowerCase().includes(this.filters.lab_name.toLowerCase())) &&
                              (!this.filters.group_name ||
                                   test.group_name.toLowerCase().includes(this.filters.group_name.toLowerCase())) &&
                              (!this.filters.shortcut ||
                                   test.shortcut.toLowerCase().includes(this.filters.shortcut.toLowerCase())) &&
                              (!this.filters.for_customer_price ||
                                   test.for_customer_price
                                        .toString()
                                        .includes(this.filters.for_customer_price.toString())) &&
                              (!this.filters.original_price ||
                                   test.original_price.toString().includes(this.filters.original_price.toString())) &&
                              (!this.filters.test_duration ||
                                   test.test_duration.toString().includes(this.filters.test_duration.toString())) &&
                              (!this.filters.duration_unit ||
                                   test.duration_unit.toString().includes(this.filters.duration_unit.toString())) &&
                              (!this.filters.precautions ||
                                   test.precautions.toLowerCase().includes(this.filters.precautions.toLowerCase()));

                         return matchesGlobalFilter && matchesColumnfilters;
                    });
               },
          },
          methods: {
               ...mapActions(usetestGroupsStore, ["RemovetestGroups", "GettestGroups"]),
               convertToArray(param) {
                    if (!Array.isArray(param)) {
                         // If the parameter is not an array, convert it to an array
                         return param ? [param] : this.convertStringToArray(param); // Return an array with the parameter or an empty array if it's falsy
                    }
                    return param; // If it is already an array, return it as is
               },
               convertStringToArray(str) {
                    // Replace single quotes with double quotes
                    const formattedStr = str.replace(/'/g, '"');

                    try {
                         // Parse the string to convert it to an array
                         return JSON.parse(formattedStr);
                    } catch (e) {
                         console.error("Invalid string format:", e);
                         return []; // Return an empty array if parsing fails
                    }
               },
               editRecord(record) {
                    Object.assign(this.record, record);
                    this.record.original_price = record.original_price;

                    this.record.result_comments = this.convertToArray(record.result_comments);

                    this.record.is_print_alone = record.is_print_alone == 1 ? true : false;

                    this.record.culture_ids = record.culture.map((item) => item.id);

                    this.record.test_ids = record.tests.map((item) => item.id);

                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.record.result_comments = [""];
                    this.dialog = true;
               },
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemovetestGroups();
                         }
                    });
               },
               clearFilters() {
                    this.globalFilter = "";
                    this.filters = {
                         category_name: "",
                         group_name: "",
                         shortcut: "",
                         for_customer_price: "",
                         original_price: "",
                         test_duration: "",
                         duration_unit: "",
                         precautions: "",
                         lab_name: "",
                    };
               },
               showResult(result) {
                    this.result_comments = result;
                    this.resdialog = true;
               },
               showtests(tests) {
                    this.tests = tests;
                    this.testdialog = true;
               },
               showculture(cultures) {
                    this.cultures = cultures;
                    this.culturedialog = true;
               },
               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredTestGroups, {
                         header: [
                              "index",
                              "category_name",
                              "group_name",
                              "shortcut",
                              "for_customer_price",
                              "original_price",
                              "test_duration",
                              "duration_unit",
                              "precautions",
                              "is_print_alone",
                              "lab_name",
                         ],
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
