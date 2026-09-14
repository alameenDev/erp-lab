<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { storeToRefs } from "pinia";
import { useTemplatesStore } from "@/store/modules/template";
import { usetestsStore } from "@/store/modules/tests";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";
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

const templatesStore = useTemplatesStore();
const testsStore = usetestsStore();

const { templates, record, dialog } = storeToRefs(templatesStore);
const { AddTemplates, UpdateTemplates } = templatesStore;
const { GetTests } = testsStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

// Age unit dropdown
const selectedAgeUnit = ref(null);
const ageUnitDropdownOpen = ref(false);
const ageUnits = [
  { name: "Year", code: "year" },
  { name: "Month", code: "month" },
  { name: "Day", code: "day" },
];

// Result type dropdown
const selectedResultType = ref(null);
const resultTypeDropdownOpen = ref(false);
const resultTypes = [
  { name: "test", code: "test" },
  { name: "select", code: "select" },
  { name: "number", code: "number" },
];

// Table controls
const rows = ref(2);
const cols = ref(3);

// TipTap Editor
const editor = ref(
  new Editor({
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
        placeholder: lang.value === "ar" ? "ابدأ بكتابة التقرير الطبي هنا..." : "Start typing the medical report here...",
      }),
    ],
    content: "",
  })
);

const create = () => {
  record.value.type = "test";
  record.value.content = {
    html: editor.value.getHTML(),
    text: editor.value.getText(),
  };
  AddTemplates().then(() => {
    dialog.value = false;
    alertSuccess(t("alertSuccess"));
    clearObjectValues(record.value);
  });
};

const addTable = () => {
  if (rows.value < 1 || cols.value < 1) {
    alert(lang.value === "ar" ? "يجب إدخال عدد الصفوف والأعمدة بشكل صحيح." : "Please enter valid rows and columns.");
    return;
  }
  editor.value.chain().focus().insertTable({ rows: rows.value, cols: cols.value, withHeaderRow: true }).run();
};

const undo = () => {
  editor.value.chain().focus().undo().run();
};

const redo = () => {
  editor.value.chain().focus().redo().run();
};

const close = () => {
  dialog.value = false;
  clearObjectValues(record.value);
};

onMounted(() => {
  GetTests();
});

onBeforeUnmount(() => {
  editor.value.destroy();
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">
              {{ record?.id ? t("update") : t("add") }}
            </h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
            <form @submit.prevent="create">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <!-- Template Name -->
                <div class="col-span-1 md:col-span-2 lg:col-span-3">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'اسم القالب' : 'Template Name' }} <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    v-model="record.name"
                    required
                    maxlength="255"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Test Name -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'اسم التحليل' : 'Test Name' }}
                  </label>
                  <input
                    type="text"
                    v-model="record.interface_code"
                    maxlength="255"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Shortcut -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("Shortcut") }} <span class="text-red-500">*</span></label>
                  <input
                    type="text"
                    v-model="record.shortcut"
                    required
                    maxlength="255"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- From Age -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'من عمر' : 'From Age' }} <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    v-model="record.from"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- To Age -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'الى عمر' : 'To Age' }} <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    v-model="record.to"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Age Unit Dropdown -->
                <div class="relative">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'وحدة العمر' : 'Age Unit' }}
                  </label>
                  <button
                    type="button"
                    @click="ageUnitDropdownOpen = !ageUnitDropdownOpen"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-left flex items-center justify-between focus:ring-2 focus:ring-blue-500"
                  >
                    <span>{{ selectedAgeUnit?.name || (lang === 'ar' ? 'اختر' : 'Select') }}</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                  <div
                    v-if="ageUnitDropdownOpen"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg"
                  >
                    <div
                      v-for="unit in ageUnits"
                      :key="unit.code"
                      @click="selectedAgeUnit = unit; ageUnitDropdownOpen = false"
                      class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                    >
                      {{ unit.name }}
                    </div>
                  </div>
                </div>

                <!-- Sub Test -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'التحليل الفرعي' : 'Sub Test' }}
                  </label>
                  <input
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Result Type Dropdown -->
                <div class="relative">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ lang === 'ar' ? 'نوع النتيجة' : 'Result Type' }}
                  </label>
                  <button
                    type="button"
                    @click="resultTypeDropdownOpen = !resultTypeDropdownOpen"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-left flex items-center justify-between focus:ring-2 focus:ring-blue-500"
                  >
                    <span>{{ selectedResultType?.name || (lang === 'ar' ? 'اختر' : 'Select') }}</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                  <div
                    v-if="resultTypeDropdownOpen"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg"
                  >
                    <div
                      v-for="resultType in resultTypes"
                      :key="resultType.code"
                      @click="selectedResultType = resultType; resultTypeDropdownOpen = false"
                      class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                    >
                      {{ resultType.name }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Editor Section -->
              <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                  {{ lang === 'ar' ? 'انشئ شكل القالب هنا:' : 'Create template format here:' }}
                </h3>
                <editor-content :editor="editor" class="border border-gray-300 rounded-lg p-4 min-h-[300px] bg-white" />
              </div>

              <!-- Editor Controls -->
              <div class="flex flex-wrap items-center gap-3 mb-4 p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center gap-2">
                  <label class="text-sm text-gray-600">{{ lang === 'ar' ? 'اعمدة الجدول' : 'Table Columns' }}</label>
                  <input
                    type="number"
                    v-model="rows"
                    min="1"
                    class="w-16 px-2 py-1 border border-gray-300 rounded text-center"
                  />
                </div>
                <div class="flex items-center gap-2">
                  <label class="text-sm text-gray-600">{{ lang === 'ar' ? 'صفوف الجدول' : 'Table Rows' }}</label>
                  <input
                    type="number"
                    v-model="cols"
                    min="1"
                    class="w-16 px-2 py-1 border border-gray-300 rounded text-center"
                  />
                </div>
                <button
                  type="button"
                  @click="addTable"
                  class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                >
                  {{ lang === 'ar' ? 'إضافة جدول' : 'Add Table' }}
                </button>
                <button
                  type="button"
                  @click="undo"
                  class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors"
                >
                  {{ lang === 'ar' ? 'تراجع' : 'Undo' }}
                </button>
                <button
                  type="button"
                  @click="redo"
                  class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors"
                >
                  {{ lang === 'ar' ? 'إعادة' : 'Redo' }}
                </button>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end">
                <button
                  type="submit"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                  {{ lang === 'ar' ? 'حفظ القالب' : 'Save Template' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
