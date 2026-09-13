<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("cultures") }}</h5>
               <Button
                    v-if="havePermission('cultures create')"
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
                    :value="filteredcultures"
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
                    <Column class="text-center" field="precautions" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("precautions") }}</p>
                              <input
                                   v-model="filters.precautions"
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
                    <Column class="text-center" field="test_group_name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("Group_Name") }}</p>
                              <input
                                   v-model="filters.test_group_name"
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
                    <Column class="text-center" field="sample">
                         <template #header>
                              <p>{{ t("Sample") }}</p>
                              <Dropdown
                                   v-model="filters.sample"
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
                                   v-model="filters.prices"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="attribute " style="min-width: 100px">
                         <template #header>
                              <p>{{ t("attributes") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   icon="pi pi-eye"
                                   class="p-eye-button mx-1"
                                   @click="showAttr(slotProps.data.attribute)"></Button>
                         </template>
                    </Column>
                    <!-- <Column class="text-center" field="attribute" style="min-width: 10px">
                         <template #header>
                              <p>{{ t("Selection_Type_Options") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   icon="pi pi-eye"
                                   class="p-eye-button mx-1"
                                   @click="showselection(slotProps.data.attribute.selection_type_options)"></Button>
                         </template>
                    </Column> -->
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
                    <Column class="text-center" field="actions" style="min-width: 140px" >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('cultures edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('cultures delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>

          <cultureModal></cultureModal>
          <ResultCommentsModal></ResultCommentsModal>
          <atrributesModal></atrributesModal>
     </div>
</template>

<script>
     import { mapActions, mapGetters, mapWritableState } from "pinia";
     import { useculturesStore } from "@/store/modules/cultures";
     import cultureModal from "./components/culture_modal.vue";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { useDurationUnitsStore } from "@/store/modules/durationUnits";
     import { usesamplesStore } from "@/store/modules/samples";
     import ResultCommentsModal from "./components/result_comments_modal.vue";
     import atrributesModal from "./components/atrributesmodal.vue";
     import { useResultTypesStore } from "@/store/modules/resultTypes";
     import { usetestGroupsStore } from "@/store/modules/testGroups";
       import { useAuthStore } from '@/store/modules/auth'

     import * as XLSX from "xlsx";
     export default {
          data() {
               return {
                    globalFilter: "",
                    id: 6,

                    filters: {
                         name: "",
                         test_group_name: "",
                         precautions: "",
                         prices: "",
                         attribute: "",
                         report_name: "",
                         test_duration: "",
                         duration_unit: "",
                         sample: "",
                         category: "",
                         lab: "",
                    },
               };
          },
          mounted() {
               this.Getcultures();
          },
          components: {
               cultureModal,
               ResultCommentsModal,
               atrributesModal,
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapGetters(usetestGroupsStore, ["Groups"]),
               ...mapWritableState(useResultTypesStore, ["resultTypes"]),
               ...mapGetters(usesamplesStore, ["samples"]),
               ...mapWritableState(useDurationUnitsStore, ["durationUnitsList"]),
               ...mapWritableState(useculturesStore, [
                    "cultures",
                    "resdialog",
                    "attrdialog",
                    "record",
                    "dialog",
                    "totalCount",
                    "result_comments",
                    "attributesList",
                    "attributes",
               ]),
               filteredcultures() {
                    return this.cultures.filter((culture) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? culture.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                culture.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                culture.test_group_name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                culture.category?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                culture.precautions?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                culture.prices?.toString().includes(this.globalFilter.toString()) ||
                                culture.attribute?.toString().includes(this.globalFilter.toString()) ||
                                culture.test_duration?.toString().includes(this.globalFilter.toString()) ||
                                (this.samples()
                                     ? this.samples().find((v) => v.label === culture.sample)?.label ?? ""
                                     : ""
                                )
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase()) ||
                                (this.durationUnitsList
                                     ? this.durationUnitsList.find((v) => v.label === culture.duration_unit)?.label ??
                                       ""
                                     : ""
                                )
                                     .toLowerCase()
                                     .includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnfilters =
                              (!this.filters.name ||
                                   culture.name.toLowerCase().includes(this.filters.name.toLowerCase())) &&
                              (!this.filters.lab ||
                                   culture.lab.toLowerCase().includes(this.filters.lab.toLowerCase())) &&
                              (!this.filters.category ||
                                   culture.category?.toLowerCase().includes(this.filters.category.toLowerCase())) &&
                              (!this.filters.test_group_name ||
                                   culture.test_group_name
                                        .toLowerCase()
                                        .includes(this.filters.test_group_name.toLowerCase())) &&
                              (!this.filters.attribute ||
                                   culture.attribute?.attribute_name
                                        .toLowerCase()
                                        .includes(this.filters.attribute.toLowerCase())) &&
                              (!this.filters.prices ||
                                   culture.prices[0]?.price?.toString().includes(this.filters.prices.toString())) &&
                              (!this.filters.precautions ||
                                   culture.precautions
                                        .toLowerCase()
                                        .includes(this.filters?.precautions.toLowerCase())) &&
                              (!this.filters?.test_duration ||
                                   culture?.test_duration
                                        ?.toString()
                                        .includes(this.filters?.test_duration?.toString())) &&
                              (!this.filters.duration_unit || culture.duration_unit == this.filters.duration_unit) &&
                              (!this.filters.sample || culture.sample == this.filters.sample);

                         return matchesGlobalFilter && matchesColumnfilters;
                    });
               },
          },
          methods: {
               ...mapActions(usesamplesStore, ["Getsamples"]),
               ...mapActions(useculturesStore, ["Removecultures", "Getcultures"]),
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
                    // this.record.selection_type_options = this.convertToArray(record.attribute.selection_type_options);
                    this.record.result_comments = record.result_comments;
                    // this.record.order = record.attribute.order;
                    // this.record.attribute_name = record.attribute.attribute_name;
                    this.record.sample_id_fk = this.samples()?.find((q) => q.label == record?.sample)?.value;
                    this.record.duration_unit_id_fk = this.durationUnitsList?.find(
                         (q) => q.label == record.duration_unit
                    ).value;
                    // this.record.result_type_id_fk = this.resultTypes.find(
                    //      (q) => q.label == record.attribute.result_type
                    // ).value;
                    this.record.attribute_id_fk = record.attribute.id;
                    this.record.test_group_id_fk = this.Groups()?.find(
                         (q) => q.label == record?.test_group_name
                    )?.value;
                    this.record.price = record.price;
                    record.attribute.selection_type_options = record.attribute.selection_type_options
                         ? record.attribute.selection_type_options
                         : [""];
                    this.attributes = record.attribute;
                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.record.selection_type_options = [""];
                    this.record.result_comments = [""];
                    this.attributes = [
                         {
                              attribute_name: null, // "required|string|max:255",
                              order: null, // "required|integer",
                              result_type_id_fk: null, // "required|integer|max:255",
                              selection_type_options: [""], // "required|array|max:255",
                         },
                    ];
                    this.dialog = true;
               },
               showResult(result) {
                    this.result_comments = result;
                    this.resdialog = true;
               },
               showAttr(attr) {
                    this.attributesList = attr;
                    this.attrdialog = true;
               },
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.Removecultures();
                         }
                    });
               },
               clearfilters() {
                    this.globalFilter = "";
                    this.filters = {
                         name: "",
                         test_group_name: "",
                         precautions: "",
                         prices: "",
                         attribute: "",
                         report_name: "",
                         test_duration: "",
                         duration_unit: "",
                         sample: "",
                         lab: "",
                    };
               },

               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredcultures, {
                         header: [
                              "index",
                              "name",
                              "test_group_name",
                              "attribute",
                              "prices",
                              "precautions",
                              "test_duration",
                              "duration_unit",
                              "sample",
                              "category",
                              "lab",
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
