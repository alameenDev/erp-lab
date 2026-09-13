<template>
  <Dialog v-model:visible="dialog" modal :header="record?.id ? t('update') : t('add')" style="width: 50rem"
    :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
    <form @submit.prevent="create()" class="border-top-1 border-bluegray-100">
      <div class="grid my-4">
        <div class="col-12 pb-0">
          <label class="block text-md mb-2">اسم القالب</label>
          <InputText required class="w-full" type="text" v-model="record.name" maxlength="255" />
        </div>
        <!-- <div class="col-6 pb-0">
          <label class="block text-md mb-2">{{ t("tests") }}</label>
          <Dropdown required class="w-full" v-model="record.test_or_culture_id" :options="testGroupTestes()"
            optionLabel="label" optionValue="value" />
        </div> -->
        <div class="col-4 pb-0">
          <label class="block text-md mb-2">اسم التحليل</label>
          <InputText class="w-full" type="text" v-model="record.interface_code" maxlength="255" />
        </div>
        <div class="col-4 pb-0">
          <label class="block text-md mb-2">{{ t("Shortcut") }}</label>
          <InputText required class="w-full" type="text" v-model="record.shortcut" maxlength="255" />
        </div>
        <div class="col-4 pb-0">
          <label class="block text-md mb-2">من عمر</label>
          <InputNumber required class="w-full" type="text" v-model="record.from" maxlength="255" />
        </div>
        <div class="col-4 pb-0">
          <label class="block text-md mb-2">الى عمر</label>
          <InputNumber required class="w-full" type="text" v-model="record.to" maxlength="255" />
        </div>
        <div class="col-4 pb-0">
          <label class="block text-md mb-2">وحدة العمر</label>
               <Dropdown
                    required
                    class="w-full"
                    v-model="selectedCity"
                    :options="cities"
                    optionLabel="name"
                    optionValue="code" />
        </div>
        <div class="col-6 pb-0">
          <label class="block text-md mb-2"> sup test</label>
          <InputText required class="w-full" type="text"  maxlength="255" />
        </div>
        <div class="col-6 pb-0">
          <label class="block text-md mb-2">type of result</label>
               <Dropdown
                    required
                    class="w-full"
                    v-model="selectedCity"
                    :options="cities2"
                    optionLabel="name"
                    optionValue="code" />
        </div>
      </div>
      <h5>
        انشئ شكل القالب هنا :
      </h5>
      <editor-content v-model="record.content" :editor="editor" class="editor-container" />
      <div>
        <Button sized="md" type="submit" class="my-4">حفظ القالب</Button>
      </div>
    </form>
    <div class="controls my-4">
      <label for=""> اعمدة الجدول</label>
      <input type="number" v-model="rows" min="1" placeholder="عدد الصفوف" />
      <label for=""> صفوف الجدول</label>
      <input type="number" v-model="cols" min="1" placeholder="عدد الأعمدة" />
      <Button @click="addTable">➕ إضافة جدول</Button>
      <!-- <button @click="addTextBox">📝 إضافة مربع نص</button> -->
      <!-- <button @click="addHeading">🔠 إضافة عنوان</button> -->
      <Button @click="undo">↩️ تراجع</Button>
      <Button @click="redo">↪️ إعادة</Button>
      <!-- <button @click="addImage">🖼️ إضافة صورة</button> -->
    </div>
  </Dialog>
</template>

<script>
import { mapActions, mapGetters, mapWritableState } from "pinia";
import { useTemplatesStore } from "@/store/modules/template";
import { usetestsStore } from "@/store/modules/tests";
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
      selectedCity: null,
            cities: [
                { name: 'Year', code: 'NY' },
                { name: 'Month', code: 'RM' },
                { name: 'Day', code: 'LDN' },
            ],
            cities2: [
                { name: 'test', code: 'NY' },
                { name: 'select', code: 'RM' },
                { name: 'number', code: 'LDN' },
            ],

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
        content: "", // يمكن تخزين القالب هنا إذا لزم الأمر
      }),
      dialogVisible: false, // التحكم في إظهار أو إخفاء الـ Dialog
      selectedTemplate: { name: "", content: { html: "" } }, // القالب المحدد للعرض
      rows: 2, // عدد الصفوف الافتراضي
      cols: 3, // عدد الأعمدة الافتراضي
    }
  },

  computed: {
    ...mapWritableState(useTemplatesStore, ["templates", "record", "dialog"]),
    ...mapWritableState(usetestsStore, ["testGroupTestes"]),
  },

  methods: {
    ...mapGetters(useTemplatesStore, ["filteredtests"]),
    ...mapActions(useTemplatesStore, ["AddTemplates", "UpdateTemplates"]),
    ...mapActions(usetestsStore, ["GetTests"]),

    create() {
      // this.record.content = this.editor.getHTML();
      this.record.type = 'test'
      this.record.content = {
        html: this.editor.getHTML(), // جلب محتوى المحرر بصيغة HTML
        text: this.editor.getText(), // جلب محتوى المحرر كنص عادي (اختياري)
      };
      this.AddTemplates().then(() => {
        this.dialog = false;
        this.alertSuccess(this.t("alertSuccess"));
        this.clearObjectValues(this.record);
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
    update() {
      this.UpdateTemplates().then(() => {
        this.dialog = false;
        this.alertSuccess(this.t("alertSuccess"));
        this.clearObjectValues(this.record);
      });
    },
    close() {
      this.dialog = false;
      this.clearObjectValues(this.record);
    },
  },
  beforeUnmount() {
    this.editor.destroy();
  },
  mounted() {
    this.GetTests()
  },
};
</script>
<style>
.editor-container {
  border: 1px solid #ddd;
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
</style>
