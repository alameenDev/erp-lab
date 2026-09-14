<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useTemplatesStore } from "@/store/modules/template";
import { t, showAlertWithConfirm, clearObjectValues, sanitizeHtml } from "@/utils/helper";
import FormatModal from "./components/format_modal.vue";

const templatesStore = useTemplatesStore();
const { templates, record, dialog, loading } = storeToRefs(templatesStore);
const { GetTemplates, RemoveTemplates } = templatesStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

// Dialog for viewing template
const dialogVisible = ref(false);
const selectedTemplate = ref({ name: "", content: { html: "" } });

// SECURITY: Sanitized template content to prevent XSS
const sanitizedTemplateHtml = computed(() => {
  return sanitizeHtml(selectedTemplate.value?.content?.html || "");
});

// Pagination
const currentPage = ref(1);
const rowsPerPage = ref(10);

const paginatedTemplates = computed(() => {
  const start = (currentPage.value - 1) * rowsPerPage.value;
  const end = start + rowsPerPage.value;
  return templates.value?.slice(start, end) || [];
});

const totalPages = computed(() => {
  return Math.ceil((templates.value?.length || 0) / rowsPerPage.value);
});

const onPageChange = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
};

const showTemplate = (template) => {
  selectedTemplate.value = template;
  dialogVisible.value = true;
};

const closeViewDialog = () => {
  dialogVisible.value = false;
};

const addRecord = () => {
  clearObjectValues(record.value);
  dialog.value = true;
};

const deleteRecord = (recordData) => {
  showAlertWithConfirm(t("AlertWithConfirm")).then((res) => {
    if (res.value) {
      record.value.id = recordData.id;
      RemoveTemplates();
    }
  });
};

onMounted(() => {
  GetTemplates();
});
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-slate-800">{{ t("samples") }}</h2>
      <button
        @click="addRecord"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ t("add") }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span class="mx-2 text-slate-600">{{ lang === 'ar' ? 'جاري تحميل البيانات...' : 'Loading data...' }}</span>
    </div>

    <!-- Data Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200">
            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">{{ t("name") }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">{{ t("type") }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">{{ t("test") }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">{{ t("content") }}</th>
            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">{{ t("actions") }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-if="!templates?.length">
            <td colspan="5" class="px-4 py-12 text-center text-slate-500">
              {{ t("noData") }}
            </td>
          </tr>
          <tr v-for="template in paginatedTemplates" :key="template.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-center text-slate-700">{{ template.name }}</td>
            <td class="px-4 py-3 text-center text-slate-700">{{ template.type }}</td>
            <td class="px-4 py-3 text-center text-slate-700">{{ template.test_or_culture_name }}</td>
            <td class="px-4 py-3 text-center">
              <button
                @click="showTemplate(template)"
                class="p-2 text-blue-600 hover:bg-blue-50 rounded-full transition-colors"
                :title="t('view')"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <button
                @click="deleteRecord(template)"
                class="p-2 text-red-600 hover:bg-red-50 rounded-full transition-colors"
                :title="t('delete')"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="templates?.length" class="flex items-center justify-between mt-4 pt-4 border-t border-slate-200">
      <div class="text-sm text-slate-600">
        {{ t("showing") }} {{ (currentPage - 1) * rowsPerPage + 1 }} - {{ Math.min(currentPage * rowsPerPage, templates.length) }} {{ t("of") }} {{ templates.length }}
      </div>
      <div class="flex gap-1">
        <button
          @click="onPageChange(currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1 rounded border border-slate-300 text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
        >
          {{ lang === 'ar' ? 'السابق' : 'Previous' }}
        </button>
        <button
          v-for="page in totalPages"
          :key="page"
          @click="onPageChange(page)"
          :class="[
            'px-3 py-1 rounded text-sm',
            currentPage === page
              ? 'bg-blue-600 text-white'
              : 'border border-slate-300 hover:bg-slate-50'
          ]"
        >
          {{ page }}
        </button>
        <button
          @click="onPageChange(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="px-3 py-1 rounded border border-slate-300 text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
        >
          {{ lang === 'ar' ? 'التالي' : 'Next' }}
        </button>
      </div>
    </div>

    <!-- View Template Dialog -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="dialogVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/50" @click="closeViewDialog"></div>
          <div
            class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden"
            :dir="lang === 'ar' ? 'rtl' : 'ltr'"
          >
            <div class="flex items-center justify-between p-4 border-b border-slate-200">
              <h2 class="text-xl font-semibold text-slate-800">{{ lang === 'ar' ? 'عرض القالب' : 'View Template' }}</h2>
              <button @click="closeViewDialog" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
              <!-- SECURITY: Using sanitized HTML to prevent XSS -->
              <div
                v-html="sanitizedTemplateHtml"
                class="border border-slate-200 rounded-lg p-4 bg-slate-50 min-h-[200px]"
              ></div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Format Modal -->
    <FormatModal />
  </div>
</template>
