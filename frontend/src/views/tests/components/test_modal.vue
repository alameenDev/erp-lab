<template>
     <Dialog v-model:visible="dialog" modal :header="record?.id ? t('update') : t('add')" style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="flex flex-wrap gap-4 col-12 my-2">
                         <h5>
                              قم بتحديد نوع التحليل:
                         </h5>
                         <RadioButtonGroup v-model="record.is_special_test" required class="w-full flex gap-2">
                              <div class="flex items-center gap-2">
                                   <RadioButton v-model="record.is_special_test" required inputId="ingredient1"
                                        name="pizza1" :value="false" />
                                   <label for="ingredient1">تحليل عادي</label>
                              </div>
                              <div class="flex items-center gap-2">
                                   <RadioButton v-model="record.is_special_test" required inputId="ingredient2"
                                        name="pizza2" :value="true" />
                                   <label for="ingredient2">تحليل مخصص</label>
                              </div>
                         </RadioButtonGroup>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.name" maxlength="255" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("interface_code") }}</label>
                         <InputText class="w-full" type="text" v-model="record.interface_code" maxlength="255" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Shortcut") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.shortcut" maxlength="255" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Report_Name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.report_name" maxlength="255" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Order") }}</label>
                         <InputNumber required class="w-full" v-model="record.order" :min="1" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Unit") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.unit" maxlength="255" />
                    </div>
                    <!-- test_duration -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Test_Duration") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.test_duration" min="0" />
                    </div>

                    <!-- duration_unit_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Duration_Unit") }}</label>
                         <Dropdown required class="w-full" v-model="record.duration_unit_id_fk"
                              :options="durationUnitsList" optionLabel="label" optionValue="value" />
                    </div>
                    <!-- category_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Category") }}</label>
                         <Dropdown required class="w-full" v-model="record.category_id_fk" :options="categories()"
                              optionLabel="label" optionValue="value" />
                    </div>
                    <div class="col-3 pb-0" style="align-self: center">
                         <div class="mt-3">
                              <Checkbox v-model="record.is_print_alone" inputId="ingredient1" binary />
                              <label for="ingredient1" class="mr-2 ml-2">{{ t("Print_Alone") }}</label>
                         </div>
                    </div>
                    <div class="col-3 pb-0" style="align-self: center">
                         <div class="mt-3">
                              <Checkbox v-model="record.is_contain_status" inputId="ingredient2" binary />
                              <label for="ingredient2" class="mr-2 ml-2">{{ t("Status") }}</label>
                         </div>
                    </div>

                    <!-- Price List -->
                    <!-- <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("price_list") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.price_list_id_fk"
                              :options="price_list()"
                              optionLabel="label"
                              optionValue="value" />
                    </div> -->
                    <!-- for_customer_price -->
                    <div class="col-6 pb-0" v-if="!record?.id">
                         <label class="block text-md mb-2">{{ t("Customer_Price") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.for_customer_price" min="0" />
                    </div>

                    <div class="col-6 pb-0" v-if="!record?.id">
                         <label class="block text-md mb-2">{{ t("Original_Price") }}</label>
                         <InputNumber required class="w-full" v-model="record.price" :min="0" />
                    </div>

                    <!-- Questions -->
                    <div class="col-6 pb-0" style="align-self: center">
                         <label class="block text-md mb-2">{{ t("test-questions") }}</label>
                         <MultiSelect v-model="record.question_ids_fk" :options="Questions()" optionLabel="label"
                              optionValue="value" filter class="w-full" />
                    </div>
                    <!-- sample_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Sample") }}</label>
                         <Dropdown required class="w-full" v-model="record.sample_id_fk" :options="samples()"
                              optionLabel="label" optionValue="value" />
                    </div>
                    <!-- Result_Type -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Result_Type") }}</label>
                         <Dropdown required class="w-full" v-model="record.result_type_id_fk" :options="resultTypes"
                              optionLabel="label" optionValue="value" />
                    </div>
                    <!-- result_comments -->
                    <div class="col-12 pb-0">
                         <div class="options pb-0">
                              <div class="add">
                                   <label class="block text-md mb-2">{{ t("Result_Comments") }}</label>
                              </div>

                              <div v-for="(comment, index) in record.result_comments" :key="index"
                                   class="flex gap-2 mb-2">
                                   <InputText class="w-full" type="text" v-model="record.result_comments[index]"
                                        maxlength="255" />
                                   <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-text"
                                        @click="removeComment(record?.result_comments, index)" />
                              </div>
                              <br />
                              <br />
                              <div class="add-ptn">
                                   <Button icon="pi pi-plus" class="p-button-text"
                                        @click="addComment(record?.result_comments)" raised />
                              </div>
                         </div>
                    </div>

                    <div class="col-12 pb-0" v-show="record.result_type_id_fk == 4">
                         <div class="options">
                              <div class="add">
                                   <label class="block text-md mb-2">{{ t("Selection_Type_Options") }}</label>
                              </div>

                              <div v-for="(comment, index) in record.selection_type_options" :key="index"
                                   class="flex gap-2 mb-2">
                                   <InputText class="w-full" type="text" v-model="record.selection_type_options[index]"
                                        maxlength="255" />
                                   <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-text"
                                        @click="removeOption(record.selection_type_options, index)" />
                              </div>
                              <br />
                              <br />
                              <div class="add-ptn">
                                   <Button icon="pi pi-plus" class="p-button-text"
                                        @click="addOption(record.selection_type_options)" raised />
                              </div>
                         </div>
                    </div>
               </div>
               <hr />
               <!-- Test Reference Range -->

               <div class="tests-reference">
                    <h5 class="m-4">{{ t("tests-reference-ranges") }}</h5>
                    <div v-for="(ref, index) in record.test_reference_ranges" :key="index"
                         class="border gap-2 p-3 mb-3 relative">
                         <div class="grid mt-1">
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("gender") }}</label>
                                   <Dropdown required class="w-full" v-model="ref.gender_id_fk" :options="genders"
                                        optionLabel="label" optionValue="value" />
                              </div>

                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("age_unit") }}</label>
                                   <Dropdown required class="w-full" v-model="ref.age_unit_id_fk" :options="AgeUnits"
                                        optionLabel="label" optionValue="value" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("age") }}</label>
                                   <div class="ages">
                                        <InputText required class="w-full ml-1" v-model="ref.age_from" :min="0"
                                             :placeholder="t('From')" />
                                        <InputText required class="w-full" v-model="ref.age_to" :min="0"
                                             :placeholder="t('To')" />
                                   </div>
                              </div>
                              <div class="col-12">
                                   <label class="block text-md mb-2" v-if="
                                        record?.result_type_id_fk == '' ||
                                        record?.result_type_id_fk == 1 ||
                                        record?.result_type_id_fk == 2
                                   ">
                                        {{ t("normal") }}
                                   </label>
                                   <div class="options pb-0"
                                        v-if="record?.result_type_id_fk == 3 || record?.result_type_id_fk == 4">
                                        <div class="add">
                                             <label class="block text-md mb-2">{{ t("test_reference_options") }}</label>
                                        </div>

                                        <div v-for="(r, index) in ref.test_reference_options" :key="index"
                                             class="flex gap-2 mb-2">
                                             <InputText class="w-full" type="text"
                                                  v-model="ref.test_reference_options[index]" maxlength="255" />
                                             <Button icon="pi pi-trash"
                                                  class="p-button-rounded p-button-danger p-button-text"
                                                  @click="removeRefOption(index, ref.test_reference_options)" />
                                        </div>
                                        <br />
                                        <br />
                                        <div class="add-ptn">
                                             <Button icon="pi pi-plus" class="p-button-text"
                                                  @click="addRefOption(ref.test_reference_options)" raised />
                                        </div>
                                   </div>
                                   <div class="grid mt-1" v-if="
                                        record?.result_type_id_fk == '' ||
                                        record?.result_type_id_fk == 1 ||
                                        record?.result_type_id_fk == 2
                                   ">
                                        <div class="col-6 pb-0">
                                             <InputText required class="w-full" v-model="ref.from"
                                                  :placeholder="t('From')" />
                                        </div>

                                        <div class="col-6 pb-0">
                                             <InputText required class="w-full" v-model="ref.to"
                                                  :placeholder="t('To')" />
                                        </div>
                                   </div>
                              </div>
                              <div class="col-12 pb-0">
                                   <label class="block text-md mb-2">{{ t("Test_Group_Comment") }}</label>
                                   <Textarea v-model="ref.notes" rows="5" cols="30" class="w-full whitespace-pre-line" />
                              </div>
                              <!-- Remove Button -->
                              <Button icon="pi pi-trash" class="remove p-button-text absolute top-0 left-0"
                                   @click="removeRecord(record.test_reference_ranges, index)" />
                         </div>

                    </div>
                    <br />
                    <br />
                    <!-- Add Button -->
                    <div class="add-ptn">
                         <Button icon="pi pi-plus" class="p-button-text"
                              @click="addRecord(record.test_reference_ranges)" raised />
                    </div>

               </div>
               <!-- new code -->
               <div class="col-12 pb-0" v-if="record.is_special_test">
                    <div class="options pb-0">
                         <div class="add">
                              <label class="block text-md mb-2">التحاليل الفرعية</label>
                         </div>

                         <div v-for="(sup, index) in record.sub_tests" :key="index" class=" gap-2 mb-2">
                              <label class="block text-md mb-2">الاسم</label>
                      
                              <InputText class="w-full" type="text" v-model="sup.name" maxlength="255" />
                              <small class="text-xs text-gray-500" v-text="`{{sub_test.${sup.name}.value}}`"></small>
                              <div class="flex items-center">
                                   <!-- <Button 
                                   icon="pi pi-copy" 
                                   size="small"
                                   class="p-button-text p-button-info"
                                   label="نسخ الاسم"
                                   @click="copySubTestVariable(sup.name, 'name')"
                                   /> -->
                                   <Button 
                                   icon="pi pi-copy" 
                                   size="small"
                                   class="p-button-text p-button-info"
                                   label="نسخ القيمة"
                                   @click="copySubTestVariable(sup.name, 'value')"
                                   />
                         </div>

                              <label class="block text-md ">نوع النتيجة</label>
                              <Dropdown required class="w-full" v-model="sup.type" :options="resultTypes"
                                   optionLabel="label" optionValue="value" />
                              <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-text"
                                   @click="removeSupTest(index)" />
                              <br />
                              <!-- Show only when type === 4 -->
                              <div v-if="sup.type === 4" class="w-full">
                                   <label class="block text-md mb-2">خيارات النتائج</label>

                                   <!-- Render inputs for each option -->
                                   <div v-for="(opt, optIndex) in sup.sup_test_reference_options" :key="optIndex"
                                        class="flex items-center gap-2 mb-2">
                                        <InputText v-model="sup.sup_test_reference_options[optIndex]" class="w-full"
                                             placeholder="اكتب الخيار" />
                                        <Button icon="pi pi-trash"
                                             class="p-button-rounded p-button-danger p-button-text"
                                             @click="removeReferenceOption(index, optIndex)" />
                                   </div>

                                   <!-- Add button to add new input -->
                                   <Button icon="pi pi-plus" class="p-button-sm p-button-outlined mt-2"
                                        @click="addReferenceOption(index)" label="إضافة خيار" />
                              </div>
                         </div>
                         <br />
                         <br />
                         <div class="add-ptn">
                              <Button icon="pi pi-plus" class="p-button-text" @click="addSupTest()" raised />
                         </div>

                    </div>

               </div>
               <h5 v-if="record.is_special_test">
               انشئ شكل القالب هنا :
               </h5>
               <editor-content v-model="record.content" :editor="editor" style="direction: ltr !important;" class="editor-container"  v-if="record.is_special_test"/>
               <div class="controls my-4" v-if="record.is_special_test">
                    <label for=""> اعمدة الجدول</label>
                    <input type="number" v-model="rows" min="1" placeholder="عدد الصفوف" />
                    <label for=""> صفوف الجدول</label>
                    <input type="number" v-model="cols" min="1" placeholder="عدد الأعمدة" />
                    <Button @click="addTable">➕ إضافة جدول</Button>
                    <Button @click="undo">↩️ تراجع</Button>
                    <Button @click="redo">↪️ إعادة</Button>
              </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" type="submit"  :label="record.id ? t('save') : t('add')" severity="success" />
                    <Button size="small" :label="t('close')" severity="danger" @click="close()" />
               </div>
          </form>
     </Dialog>
</template>

<script>
import { mapActions, mapGetters, mapWritableState } from "pinia";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { useResultTypesStore } from "@/store/modules/resultTypes";
import { usetestsQuestionsStore } from "@/store/modules/tests-questions";
import { usePatientsStore } from "@/store/modules/patients";
import { usetestsStore } from "@/store/modules/tests";
import { priceListStore } from "@/store/modules/priceList";
import { useDurationUnitsStore } from "@/store/modules/durationUnits";
import { usesamplesStore } from "@/store/modules/samples";
import { useCategoriesStore } from "@/store/modules/categories";
import { Editor, EditorContent } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Table from "@tiptap/extension-table";
import TableRow from "@tiptap/extension-table-row";
import TableCell from "@tiptap/extension-table-cell";
import TableHeader from "@tiptap/extension-table-header";
import Heading from "@tiptap/extension-heading";
import Paragraph from "@tiptap/extension-paragraph";
import Bold from "@tiptap/extension-bold";
import Italic from "@tiptap/extension-italic";
import Underline from "@tiptap/extension-underline";
import Image from "@tiptap/extension-image";
import TextStyle from "@tiptap/extension-text-style";
import Placeholder from "@tiptap/extension-placeholder"; 

export default {
     components: { EditorContent },
     data() {
          return {
               editor: new Editor({
        extensions: [
          StarterKit,
          Table.configure({ resizable: true }),
          TableRow,
          TableCell,
          TableHeader,
          Heading.configure({ levels: [1, 2, 3] }),
          Paragraph,
          Bold,
          Italic,
          Underline,
          Image,
          TextStyle,
          Placeholder.configure({
            placeholder: "✍️ ابدأ بكتابة التقرير الطبي هنا...",
          }),
        ],
        content: "", 
      }),
      dialogVisible: false, 
      selectedTemplate: { name: "", content: { html: "" } }, 
      rows: 1, 
      cols: 3, 
          }
     },
     computed: {
          ...mapGetters(usetestGroupsStore, ["Groups"]),
          ...mapGetters(usetestsQuestionsStore, ["Questions"]),
          ...mapWritableState(usetestsStore, ["record", "dialog", "loading"]),
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
          this.record.is_special_test = false
     },
     methods: {
          ...mapActions(useDurationUnitsStore, ["GetdurationUnits"]),
          ...mapActions(usetestGroupsStore, ["GettestGroups"]),
          ...mapActions(useResultTypesStore, ["GetresultTypes"]),
          ...mapActions(usePatientsStore, ["GetGenders", "GetAgeUnits"]),
          ...mapActions(usetestsQuestionsStore, ["GetTestsQuestions"]),
          ...mapActions(priceListStore, ["GetpriceList"]),
          ...mapActions(usetestsStore, ["Addtests", "Updatetests"]),
          ...mapActions(usesamplesStore, ["Getsamples"]),
          ...mapActions(useCategoriesStore, ["Getcategories"]),
          create() {
               this.record.is_print_alone = this.record.is_print_alone == true ? 1 : 0;
               this.record.is_contain_status = this.record.is_contain_status == true ? 1 : 0;
               this.record.content = {
                    html: this.editor.getHTML(), // جلب محتوى المحرر بصيغة HTML
                    text: this.editor.getText(), // جلب محتوى المحرر كنص عادي (اختياري)
                    };
               this.Addtests().then(() => {
                    this.alertSuccess(this.t("alertSuccess"));
                    this.clearObjectValues(this.record);
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
                    this.record.selection_type_options = [""];
                    this.dialog = false;
               });
          },
          addTable() {
      if (this.rows < 1 || this.cols < 1) {
        alert("يجب إدخال عدد الصفوف والأعمدة بشكل صحيح.");
        return;
      }
      this.editor.chain().focus().insertTable({ rows: this.rows, cols: this.cols, withHeaderRow: true }).run();
    },
    addTextBox() {
      this.editor.chain().focus().insertContent('<div class="custom-text-box">📌 مربع نص مخصص</div>').run();
    },
    addHeading() {
      this.editor.chain().focus().setNode("heading", { level: 2 }).run();
    },
    addImage() {
      const url = prompt("أدخل رابط الصورة:");
      if (url) {
        this.editor.chain().focus().setImage({ src: url }).run();
      }
    },
    undo() {
      this.editor.chain().focus().undo().run();
    },
    redo() {
      this.editor.chain().focus().redo().run();
    },
          addOption(array) {
               array.push("");
          },
          addRefOption(array) {
               array.push("");
          },
          removeRefOption(index, array) {
               array.splice(index, 1);
          },
          removeOption(array, index) {
               array.splice(index, 1);
          },
          update() {
               this.record.is_print_alone = this.record.is_print_alone == true ? 1 : 0;
               this.record.is_contain_status = this.record.is_contain_status == true ? 1 : 0;

               this.Updatetests().then(() => {
                    this.alertSuccess(this.t("alertSuccess"));
                    this.clearObjectValues(this.record);
                    this.record.selection_type_options = [""];
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

                    this.dialog = false;
               });
          },
          addComment(array) {
               array.push("");
          },

          addSupTest() {
               if (!Array.isArray(this.record.sub_tests)) {
                    try {
                         // Try to parse if it's accidentally a JSON string
                         this.record.sub_tests = JSON.parse(this.record.sub_tests);
                         if (!Array.isArray(this.record.sub_tests)) {
                              this.record.sub_tests = [];
                         }
                    } catch (e) {
                         // If it's not valid JSON
                         this.record.sub_tests = [];
                    }
               }

               this.record.sub_tests.push({
                    name: "",
                    type: 1,
                    value:null,
                    sup_test_reference_options: [],
               });
          },
          removeSupTest(index) {
               this.record.sub_tests.splice(index, 1);
          },
          addReferenceOption(testIndex) {
               this.record.sub_tests[testIndex].sup_test_reference_options.push("");
          },
          copySubTestVariable(name, field = 'value') {
    const variable = `{{sub_test.${name}.${field}}}`;

    // أنشئ عنصر textarea مؤقت
    const textarea = document.createElement('textarea');
    textarea.value = variable;
    textarea.style.position = 'fixed';  // لتفادي التمرير
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);

    // حدد النص وانسخه
    textarea.select();
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            this.alertSuccess('تم النسخ');
        } else {
            this.alertSuccess('فشل النسخ');
        }
    } catch (err) {
        this.alertSuccess('حدث خطأ أثناء النسخ');
    }

    // احذف العنصر المؤقت
    document.body.removeChild(textarea);
},
          removeReferenceOption(testIndex, optIndex) {
               this.record.sub_tests[testIndex].sup_test_reference_options.splice(optIndex, 1);
          },
          removeComment(array, index) {
               array.splice(index, 1);
          },
          close() {
               this.clearObjectValues(this.record);
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

               this.record.selection_type_options = [""];
               this.dialog = false;
          },
          addRecord(array) {
               array.push({
                    gender_id_fk: "",
                    age_from: "",
                    age_to: "",
                    age_unit_id_fk: "",
                    from: 0,
                    to: 0,
                    test_reference_options: [""],
                    notes: "",
               });
          },
          removeRecord(array, index) {
               array.splice(index, 1);
          },
     },
     beforeUnmount() {
    this.editor.destroy();
  },
     watch: {},
};
</script>
<style  lang="scss">

.editor-container {
//   border: 1px solid #ddd;
  min-height: 300px;
  padding: 10px;
  background: #fff;
}

.controls {
  margin-top: 10px;
  display: flex;
  gap: 10px;
  align-items: center;
}

.controls input {
  width: 80px;
  padding: 5px;
  border: 1px solid #ccc;
  border-radius: 5px;
  text-align: center;
}

.controls button {
  padding: 8px 12px;
  border: none;
  background: #007bff;
  color: white;
  cursor: pointer;
  border-radius: 5px;
}

.controls button:hover {
  background: #0056b3;
}

/* 🏆 تحسين تصميم الجدول */
table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  /* خلفية الجدول بيضاء */
}

th,
td {
  border: 1px solid #ccc;
  /* حدود رمادية */
  padding: 10px;
  text-align: left;
}

th {
  background: #f8f9fa;
  /* خلفية رأس الجدول */
  font-weight: bold;
}

/* 🎨 تصميم مربع النص */
.custom-text-box {
  border: 1px solid #555;
  padding: 10px;
  margin: 10px 0;
  background: #f1f1f1;
  font-style: italic;
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

.add {
     display: flex;
     justify-content: space-between;
     align-items: center;
     margin-bottom: 10px;
     padding: 0 6px;
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
</style>
