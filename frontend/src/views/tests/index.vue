<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("tests") }}</h5>
               <Button
                    v-if="havePermission('tests create')"
                    size="small"
                    class="p-button-success"
                    :label="t('add')"
                    @click="addRecord"></Button>
          </div>
          <div class="card flex justify-content-center mb-5" v-if="pagination.total?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <div class="globalSearch">
                    <input
                         v-model="filters.name"
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
                    :value="filteredtests"
                    scrollable
                    scrollHeight="600px"
                    responsiveLayout="scroll"
                    :paginator="true"
                    :lazy="true"
                     :loading="loading"
                    :rows="pagination.per_page"
                    :totalRecords="pagination.total"
                    :first="(pagination.current_page - 1) * pagination.per_page"
                    @page="onPageChange"
                    >
                    <template #empty>
                         <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                              {{ t("noData") }}
                         </div>
                    </template>
                    <template #loading>
                    <div class="p-datatable-loading-overlay p-d-flex p-ai-center p-jc-center">
                         <i class="pi pi-spin pi-spinner text-white" style="font-size: 2rem"></i>
                         <span class="mx-2 my-6 text-white">جاري تحميل البيانات...</span>
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
                    <Column class="text-center" field="category" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Category") }}</p>
                              <input
                                   v-model="filters.category"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="report_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Report_Name") }}</p>
                              <input
                                   v-model="filters.report_name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
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
                    <Column class="text-center" field="duration_unit">
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
                    <Column class="text-center" field="sample_name">
                         <template #header>
                              <p>{{ t("Sample") }}</p>
                              <Dropdown
                                   v-model="filters.sample_name"
                                   :options="samples()"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>
                    <Column class="text-center" field="price" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Original_Price") }}</p>
                              <input
                                   v-model="filters.price"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                         <template #body="slotProps">
                              {{ slotProps.data.price?.toLocaleString() }}
                         </template>
                    </Column>

                    <Column class="text-center" field="interface_code" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("interface_code") }}</p>
                              <input
                                   v-model="filters.interface_code"
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
                                   v-if="havePermission('tests edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('tests delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>

          <TestsModal></TestsModal>
     </div>
</template>

<script>
     import { mapActions, mapGetters, mapWritableState } from "pinia";
     import { usetestsStore } from "@/store/modules/tests";
     import TestsModal from "./components/test_modal.vue";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { useDurationUnitsStore } from "@/store/modules/durationUnits";
     import { usesamplesStore } from "@/store/modules/samples";
       import { useAuthStore } from '@/store/modules/auth'

     import * as XLSX from "xlsx";
     export default {
          data() {
               return {
                    globalFilter: "",
                    filters: {
                         name: "",
                         lab: "",
                         category: "",
                         shortcut: "",
                         price: "",
                         interface_code: "",
                         report_name: "",
                         test_duration: "",
                         duration_unit: "",
                         sample_name: "",
                    },
               };
          },
          mounted() {
               this.GetTests();
          },
          components: {
               TestsModal,
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapGetters(usesamplesStore, ["samples"]),
               ...mapWritableState(useDurationUnitsStore, ["durationUnitsList"]),
               ...mapWritableState(usetestsStore, ["tests", "record", "dialog", "pagination" ,"loading"]),
               filteredtests() {
                    return this.tests?.filter((test) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? test.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.category?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.shortcut?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                test.price?.toString().includes(this.globalFilter.toString()) ||
                                test.interface_code?.toString().includes(this.globalFilter.toString()) ||
                                test.report_name?.toString().includes(this.globalFilter.toString()) ||
                                test.test_duration?.toString().includes(this.globalFilter.toString()) ||
                                (this.samples && Array.isArray(this.samples)
                                     ? this.samples?.find((v) => v.label === test.sample_name)?.label ?? ""
                                     : ""
                                )
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                (this.durationUnitsList
                                     ? this.durationUnitsList?.find((v) => v.label === test?.duration_unit)?.label ?? ""
                                     : ""
                                )
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnfilters =
                              (!this.filters.name ||
                                   test.name.toLowerCase().includes(this.filters.name.toLowerCase())) &&
                              (!this.filters.lab || test.lab.toLowerCase().includes(this.filters.lab.toLowerCase())) &&
                              (!this.filters.category ||
                                   test.category?.toLowerCase().includes(this.filters.category.toLowerCase())) &&
                              (!this.filters.shortcut ||
                                   test.shortcut?.toLowerCase().includes(this.filters.shortcut.toLowerCase())) &&
                              (!this.filters?.price ||
                                   test.price?.toString().includes(this.filters.price?.toString())) &&
                              (!this.filters.interface_code ||
                                   test.interface_code.toString().includes(this.filters?.interface_code.toString())) &&
                              (!this.filters?.report_name ||
                                   test.report_name?.toLowerCase().includes(this.filters?.report_name.toLowerCase())) &&
                              (!this.filters?.test_duration ||
                                   test?.test_duration?.toString().includes(this.filters?.test_duration?.toString())) &&
                              (!this.filters?.duration_unit || test?.duration_unit == this.filters?.duration_unit) &&
                              (!this.filters?.sample_name || test?.sample_name == this.filters?.sample_name);

                         return matchesGlobalFilter && matchesColumnfilters;
                    });
               },
          },
          methods: {
               ...mapActions(usetestsStore, ["Removetests", "GetTests"]),
               onPageChange(event) {
                    this.pagination.current_page = event.page + 1;

                    // PrimeVue pages start from 0
                    this.GetTests();
               },
               editRecord(record) {
                    Object.assign(this.record, record);
                    this.record.question_ids_fk = record.questions.map((q) => q.id);
                    this.record.is_contain_status = record.is_contain_status == 1 ? true : false;
                    this.record.is_print_alone = record.is_print_alone == 1 ? true : false;
                    this.record.selection_type_options = record.selction_type_options;
                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.record.selection_type_options = [""];
                    this.record.result_comments = [""];
                    this.record.test_reference_ranges = [
                         {
                              gender_id_fk: "",
                              age_from: "",
                              age_to: "",
                              age_unit_id_fk: "",
                              from: 0,
                              to: 0,
                              test_reference_options: [""],
                              notes: "",
                         },
                    ];
                    this.dialog = true;
               },
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.Removetests();
                         }
                    });
               },
               clearfilters() {
                    this.globalFilter = "";
                    this.filters = {
                         name: "",
                         category: "",
                         shortcut: "",
                         price: "",
                         interface_code: "",
                         report_name: "",
                         test_duration: "",
                         duration_unit: "",
                         sample_name: "",
                         lab: "",
                    };
               },

               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredtests, {
                         header: [
                              "index",
                              "lab",
                              "name",
                              "category",
                              "shortcut",
                              "price",
                              "interface_code",
                              "report_name",
                              "test_duration",
                              "duration_unit",
                              "sample_name",
                         ],
                    });

                    // Create a new workbook and append the worksheet
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                    // Write the workbook to a file
                    XLSX.writeFile(wb, "export.xlsx");
               },
          },
          watch: {
               filters: {
                    deep: true,
                    handler() {
                         this.pagination.current_page = 1; 
                         this.GetTests(this.filters); 
                    }
               }
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
