<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("question") }}</label>
                         <InputText class="w-full" required type="text" v-model="record.question" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("answer_type") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.answer_type_id_fk"
                              :options="answerTypes"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-12 pb-0" v-if="record.answer_type_id_fk == 5">
                         <div class="options pb-0">
                              <div class="add">
                                   <label class="block text-md mb-2">{{ t("answer_type_selection_values") }}</label>
                              </div>

                              <div
                                   v-for="(Question, index) in record.answer_type_selection_values"
                                   :key="index"
                                   class="flex gap-2 mb-2">
                                   <InputText
                                        class="w-full"
                                        type="text"
                                        v-model="record.answer_type_selection_values[index]"
                                        maxlength="255" />
                                   <Button
                                        icon="pi pi-trash"
                                        class="p-button-rounded p-button-danger p-button-text"
                                        @click="removeQuestion(record.answer_type_selection_values, index)" />
                              </div>
                              <br />
                              <br />
                              <div class="add-ptn">
                                   <Button
                                        icon="pi pi-plus"
                                        class="p-button-text"
                                        @click="addQuestion(record.answer_type_selection_values)"
                                        raised />
                              </div>
                         </div>
                    </div>
               </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
                    <Button
                         size="small"
                         type="submit"
                         :label="record.id ? t('save') : t('add')"
                         severity="success"></Button>
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { usetestsQuestionsStore } from "@/store/modules/tests-questions";
     import { useAnswerTypesStore } from "@/store/modules/answerTypes";
     export default {
          computed: {
               ...mapWritableState(usetestsQuestionsStore, ["record", "dialog"]),
               ...mapWritableState(useAnswerTypesStore, ["answerTypes"]),
          },

          methods: {
               ...mapActions(usetestsQuestionsStore, ["AddTestsQuestions", "UpdateTestsQuestions"]),

               create() {
                    this.AddTestsQuestions().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.record.answer_type_selection_values = [""];
                         this.dialog = false;
                    });
               },
               update() {
                    this.UpdateTestsQuestions().then(() => {
                         this.dialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.record.answer_type_selection_values = [""];
                    });
               },
               close() {
                    this.dialog = false;
                    this.clearObjectValues(this.record);
                    this.record.answer_type_selection_values = [""];
               },
               addQuestion(array) {
                    array.push("");
               },
               removeQuestion(array, index) {
                    array.splice(index, 1);
               },
          },
     };
</script>
<style lang="scss">
     .add-ptn {
          position: absolute;
          bottom: 1px;
          left: 0;
          button {
               border-radius: 50%;
          }
     }
     .options {
          background: #f9fafb;
          padding: 14px;
          position: relative;
          border-radius: 19px;
          border: 1px solid #f3f4f6;
     }
</style>
