<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.name" maxlength="255" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("precautions") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.precautions" maxlength="255" />
                    </div>
                    <!-- Group_Name -->
                    <!-- <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Group_Name") }}</label>
                         <Dropdown
                              v-model="record.test_group_id_fk"
                              :options="Groups()"
                              optionLabel="label"
                              optionValue="value"
                              filter
                              class="w-full" />
                    </div> -->
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
                    <!-- for_customer_price -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Customer_Price") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.price_for_customer" min="0" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Original_Price") }}</label>
                         <InputNumber required class="w-full" v-model="record.price" :min="0" />
                    </div>
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
                    <!-- attribute -->

                    <div class="col-12 pb-0">
                         <div class="options pb-0">
                              <h5 class="m-2">{{ t("attributes") }}</h5>
                              <div
                                   class="padding grid mt-1 mb-5"
                                   v-for="(attr, index) in attributes"
                                   :key="index"
                                   style="position: relative">
                                   <div class="col-6 pb-0">
                                        <label class="block text-md mb-2">{{ t("attribute_name") }}</label>
                                        <InputText
                                             required
                                             class="w-full"
                                             type="text"
                                             v-model="attr.attribute_name"
                                             maxlength="255" />
                                   </div>
                                   <div class="col-6 pb-0">
                                        <label class="block text-md mb-2">{{ t("Order") }}</label>
                                        <InputNumber required class="w-full" v-model="attr.order" :min="1" />
                                   </div>
                                   <div class="col-6 pb-0">
                                        <label class="block text-md mb-2">{{ t("Result_Type") }}</label>
                                        <Dropdown
                                             required
                                             class="w-full"
                                             v-model="attr.result_type_id_fk"
                                             :options="resultTypes"
                                             optionLabel="label"
                                             optionValue="value" />
                                   </div>
                                   <div class="col-12 pb-0" v-show="attr.result_type_id_fk == 4">
                                        <div class="options">
                                             <div class="add">
                                                  <label class="block text-md mb-2">
                                                       {{ t("Selection_Type_Options") }}
                                                  </label>
                                             </div>

                                             <div
                                                  v-for="(comment, i) in attr.selection_type_options"
                                                  :key="i"
                                                  class="flex gap-2 mb-2">
                                                  <InputText
                                                       class="w-full"
                                                       type="text"
                                                       v-model="attr.selection_type_options[i]"
                                                       maxlength="255" />
                                                  <Button
                                                       icon="pi pi-trash"
                                                       class="p-button-rounded p-button-danger p-button-text"
                                                       @click="removeOption(attr.selection_type_options, i)" />
                                             </div>
                                             <br />
                                             <br />
                                             <div class="add-ptn">
                                                  <Button
                                                       icon="pi pi-plus"
                                                       class="p-button-text"
                                                       @click="addOption(attr.selection_type_options)"
                                                       raised />
                                             </div>
                                        </div>
                                   </div>
                                   <Button
                                        icon="pi pi-trash"
                                        class="remove p-button-text absolute top-0 left-0"
                                        @click="removeOption(attributes, index)" />
                                   <br />
                              </div>
                              <br />
                              <br />
                              <div class="add-ptn">
                                   <Button icon="pi pi-plus" class="p-button-text" @click="addAttrOption()" raised />
                              </div>
                         </div>
                    </div>
               </div>

               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" type="submit" :label="record.id ? t('save') : t('add')" severity="success" />
                    <Button size="small" :label="t('close')" severity="danger" @click="close()" />
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapGetters, mapWritableState } from "pinia";
     import { useculturesStore } from "@/store/modules/cultures";
     import { usetestGroupsStore } from "@/store/modules/testGroups";
     import { useResultTypesStore } from "@/store/modules/resultTypes";
     import { usetestsQuestionsStore } from "@/store/modules/tests-questions";
     import { usePatientsStore } from "@/store/modules/patients";
     import { priceListStore } from "@/store/modules/priceList";
     import { useDurationUnitsStore } from "@/store/modules/durationUnits";
     import { usesamplesStore } from "@/store/modules/samples";
     import { useCategoriesStore } from "@/store/modules/categories";

     export default {
          computed: {
               ...mapWritableState(useculturesStore, ["record", "dialog", "attributes"]),
               ...mapGetters(usetestGroupsStore, ["Groups"]),
               ...mapGetters(usetestsQuestionsStore, ["Questions"]),

               ...mapWritableState(useResultTypesStore, ["resultTypes"]),
               ...mapWritableState(usePatientsStore, ["genders", "AgeUnits"]),
               ...mapGetters(priceListStore, ["price_list"]),
               ...mapWritableState(useDurationUnitsStore, ["durationUnitsList"]),
               ...mapGetters(usesamplesStore, ["samples"]),
               ...mapGetters(useCategoriesStore, ["categories"]),
          },
          mounted() {
               this.GettestGroups();
               this.GetresultTypes();
               this.GetGenders();
               this.GetAgeUnits();
               this.GetTestsQuestions();
               this.GetpriceList();
               this.GetdurationUnits();
               this.Getsamples();
               this.Getcategories();
          },
          methods: {
               ...mapActions(useDurationUnitsStore, ["GetdurationUnits"]),
               ...mapActions(usetestGroupsStore, ["GettestGroups"]),
               ...mapActions(useResultTypesStore, ["GetresultTypes"]),
               ...mapActions(usePatientsStore, ["GetGenders", "GetAgeUnits"]),
               ...mapActions(usetestsQuestionsStore, ["GetTestsQuestions"]),
               ...mapActions(priceListStore, ["GetpriceList"]),
               ...mapActions(useculturesStore, ["Addcultures", "Updatecultures"]),
               ...mapActions(usesamplesStore, ["Getsamples"]),
               ...mapActions(useCategoriesStore, ["Getcategories"]),
               create() {
                    this.Addcultures().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.record.result_comments = [""];
                         this.record.selection_type_options = [""];
                         this.attributes = [
                              {
                                   attribute_name: null, // "required|string|max:255",
                                   order: null, // "required|integer",
                                   result_type_id_fk: null, // "required|integer|max:255",
                                   selection_type_options: [""], // "required|array|max:255",
                              },
                         ];
                         this.dialog = false;
                    });
               },
               addOption(array) {
                    array.push("");
               },
               removeOption(array, index) {
                    array?.splice(index, 1);
               },
               addAttrOption() {
                    this.attributes?.push({
                         attribute_name: null,
                         order: null,
                         result_type_id_fk: null,
                         selection_type_options: [""],
                    });
               },

               addComment(array) {
                    array?.push("");
               },
               removeComment(array, index) {
                    array.splice(index, 1);
               },
               update() {
                    this.Updatecultures().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);

                         this.attributes = [
                              {
                                   attribute_name: null, // "required|string|max:255",
                                   order: null, // "required|integer",
                                   result_type_id_fk: null, // "required|integer|max:255",
                                   selection_type_options: [""], // "required|array|max:255",
                              },
                         ];
                         this.dialog = false;
                    });
               },
               close() {
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
                    this.dialog = false;
               },
          },
          watch: {},
     };
</script>
<style scoped lang="scss">
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
          padding: 20px;
          position: relative;
          border-radius: 19px;
          border: 1px solid #f3f4f6;
     }
     .add {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 10px;
          padding: 0 6px;
     }
     .padding {
          position: relative;
          background: aliceblue;
          padding: 20px;
     }
     label {
          font-weight: 500;
     }
     .ages {
          display: flex;
          justify-content: space-between;
     }
     .relative {
          position: relative;
     }
     .absolute {
          position: absolute;
     }
     .top-0 {
          top: 0;
     }
     .right-0 {
          right: 0;
     }

     .tests-reference {
          position: relative;
          background: #fafafa;
          border-radius: 19px;
          padding: 10px;
          .remove {
               background: #8a0a0adb;
               color: white;
               padding: 2px 6px;
               width: fit-content;
          }
          .border {
               background: #f0f8ffa1;
               border: 1px solid #0000001a;
               margin: 8px;
               border-radius: 10px;
          }
     }
     label {
          font-weight: 500;
     }
     .remove {
          background: #8a0a0adb;
          color: white;
          padding: 2px 6px;
          width: fit-content;
     }
     .absolute {
          position: absolute;
     }
</style>
