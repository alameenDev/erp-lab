<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("test-questions") }}</h5>
               <Button
                     v-if="havePermission('test questions create')"
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
                    <Column class="text-center" field="lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input
                                   v-model="Filters.lab"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="question">
                         <template #header>
                              <p>{{ t("question") }}</p>
                              <input
                                   v-model="Filters.question"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="answer_type">
                         <template #header>
                              <p>{{ t("answer_type") }}</p>
                              <input
                                   v-model="Filters.answer_type"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="actions"  >
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                              v-if="havePermission('test questions edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('test questions delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
               </DataTable>
          </div>
          <QuestionModal></QuestionModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import QuestionModal from "./componentes/question-modal.vue";
     import { usetestsQuestionsStore } from "@/store/modules/tests-questions";
     import { useAnswerTypesStore } from "@/store/modules/answerTypes";
     import { showAlertWithConfirm } from "@/utils/helper";
     import * as XLSX from "xlsx";
       import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    Filters: {
                         question: "",
                         answer_type: "",
                         lab: "",
                    },
                    globalFilter: "", // Global filter input
               };
          },
          components: {
               QuestionModal,
          },

          mounted() {
               this.GetTestsQuestions();
               this.GetanswerTypes();
          },
          computed: {
               ...mapWritableState(useAuthStore, ["havePermission"]),
               ...mapWritableState(usetestsQuestionsStore, ["testsQuestions", "totalCount", "record", "dialog"]),
               ...mapWritableState(useAnswerTypesStore, ["answerTypes"]),
               filteredRecords() {
                    return this.testsQuestions.filter((record) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? record.question?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.answer_type?.toLowerCase().includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnFilters =
                              (!this.Filters.question ||
                                   record.question.toLowerCase().includes(this.Filters.question.toLowerCase())) &&
                              (!this.Filters.lab ||
                                   record.lab.toLowerCase().includes(this.Filters.lab.toLowerCase())) &&
                              (!this.Filters.answer_type ||
                                   record.answer_type.toLowerCase().includes(this.Filters.answer_type.toLowerCase()));

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
          },
          methods: {
               ...mapActions(usetestsQuestionsStore, ["GetTestsQuestions", "RemoveTestsQuestions"]),
               ...mapActions(useAnswerTypesStore, ["GetanswerTypes"]),
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemoveTestsQuestions();
                         }
                    });
               },

               clearFilters() {
                    this.Filters = {
                         question: "",
                         answer_type: "",
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
                    this.record.answer_type_id_fk = record.answer_type_id;
                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.record.answer_type_selection_values = [""];
                    this.dialog = true;
               },
          },
     };
</script>
<style scoped></style>
