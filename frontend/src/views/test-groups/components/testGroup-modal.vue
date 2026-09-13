<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-2">
                    <!-- category_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Category") }}</label>

                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.category_id_fk"
                              :options="categories()"
                              optionLabel="label"
                              optionValue="value" />
                    </div>

                    <!-- for_customer_price -->
                    <div class="col-6 pb-0" v-if="!record?.id">
                         <label class="block text-md mb-2">{{ t("Customer_Price") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.for_customer_price" min="0" />
                    </div>

                    <!-- group_name -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Group_Name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.group_name" maxlength="255" />
                    </div>

                    <!-- shortcut -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Shortcut") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.shortcut" maxlength="255" />
                    </div>
                    <!-- tests -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("tests") }}</label>
                         <MultiSelect
                              v-model="record.test_ids"
                              :options="testGroupTestes()"
                              optionLabel="label"
                              optionValue="value"
                              filter
@scroll="onScroll"
                              class="w-full" />
                    </div>
                    <!-- culture_ids -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("cultures") }}</label>
                         <MultiSelect
                              v-model="record.culture_ids"
                              :options="cultureGroupTestes()"
                              optionLabel="label"
                              optionValue="value"
                              filter
                              class="w-full" />
                    </div>
                    <!-- sample_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Sample") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.sample_id_fk"
                              :options="samples()"
                              optionLabel="label"
                              optionValue="value" />
                    </div>

                    <!-- original_price -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Original_Price") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.original_price" min="0" />
                    </div>

                    <!-- test_duration -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Test_Duration") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.test_duration" min="0" />
                    </div>

                    <!-- duration_unit_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Duration_Unit") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.duration_unit_id_fk"
                              :options="durationUnitsList"
                              optionLabel="label"
                              optionValue="value" />
                    </div>

                    <!-- precautions -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Precautions") }}</label>
                         <InputText class="w-full" type="text" v-model="record.precautions" />
                    </div>

                    <!-- is_print_alone -->

                    <div class="col-6 pb-0" style="align-self: center">
                         <div class="mt-3">
                              <Checkbox v-model="record.is_print_alone" inputId="ingredient1" binary />
                              <label for="ingredient1" class="ml-2">{{ t("Print_Alone") }}</label>
                         </div>
                    </div>

                    <!-- result_comments -->
                    <div class="col-12 pb-0">
                         <div class="options pb-0">
                              <div class="add">
                                   <label class="block text-md mb-2">{{ t("Result_Comments") }}</label>
                              </div>

                              <div
                                   v-for="(comment, index) in record.result_comments"
                                   :key="index"
                                   class="flex gap-2 mb-2">
                                   <InputText
                                        class="w-full"
                                        type="text"
                                        v-model="record.result_comments[index]"
                                        maxlength="255" />
                                   <Button
                                        icon="pi pi-trash"
                                        class="p-button-rounded p-button-danger p-button-text"
                                        @click="removeComment(record.result_comments, index)" />
                              </div>
                              <br />
                              <br />
                              <div class="add-ptn">
                                   <Button
                                        icon="pi pi-plus"
                                        class="p-button-text"
                                        @click="addComment(record.result_comments)"
                                        raised />
                              </div>
                         </div>
                    </div>

                    <!-- test_group_comment -->
                    <div class="col-12 pb-0">
                         <label class="block text-md mb-2">{{ t("Test_Group_Comment") }}</label>
                         <InputText class="w-full" type="text" v-model="record.test_group_comment" maxlength="255" />
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
     import { mapActions, mapGetters, mapWritableState } from "pinia";
     import { usetestGroupsStore } from "@/store/modules/testGroups";
     import { useCategoriesStore } from "@/store/modules/categories";
     import { usesamplesStore } from "@/store/modules/samples";
     import { useDurationUnitsStore } from "@/store/modules/durationUnits";
     import { usetestsStore } from "@/store/modules/tests";
     import { useculturesStore } from "@/store/modules/cultures";

     export default {
          computed: {
               ...mapWritableState(usetestGroupsStore, ["record", "dialog", "testGroups"]),
               ...mapWritableState(useCategoriesStore, ["categories"]),
               ...mapGetters(usesamplesStore, ["samples"]),
               ...mapWritableState(useDurationUnitsStore, ["durationUnitsList"]),
               ...mapGetters(usetestsStore, ["testGroupTestes",'pagination']),
               ...mapGetters(useculturesStore, ["cultureGroupTestes"]),
          },
          mounted() {
               this.Getcategories();
               this.Getsamples();
               this.GetdurationUnits();
               this.GetTests();
               this.Getcultures();
          },
          methods: {
               ...mapActions(usetestsStore, ["GetTests"]),
               ...mapActions(useculturesStore, ["Getcultures"]),
               ...mapActions(usetestGroupsStore, ["AddtestGroups", "UpdatetestGroups"]),
               ...mapActions(useCategoriesStore, ["Getcategories"]),
               ...mapActions(usesamplesStore, ["Getsamples"]),
               ...mapActions(useDurationUnitsStore, ["GetdurationUnits"]),
               create() {
                    // this.record.result_comments = this.result_comments;
                    this.record.is_print_alone = this.record.is_print_alone == true ? 1 : 0;
                    this.AddtestGroups().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         // this.record.result_comments = [""];
                         this.dialog = false;
                    });
               },   onScroll(event) {
                    const bottom = event.target.scrollHeight - event.target.scrollTop === event.target.clientHeight;
                    if (bottom && this.pagination.current_page < this.pagination.last_page) {
                         this.pagination.current_page++;
                         this.GetTests();
                    }
               },
               addComment(array) {
                    array.push("");
               },
               removeComment(array, index) {
                    array.splice(index, 1);
               },
               update() {
                    this.record.is_print_alone = this.record.is_print_alone == true ? 1 : 0;
                    this.UpdatetestGroups().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         // this.record.result_comments = [""];
                         this.dialog = false;
                    });
               },
               close() {
                    this.result_comments = [""];
                    this.dialog = false;
                    this.clearObjectValues(this.record);
               },
          },
          watch: {},
     };
</script>
<style lang="scss">
     .add {
          background: #f9fafb;
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 10px;
          padding: 0 6px;
     }
     label {
          font-weight: 500;
     }
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
