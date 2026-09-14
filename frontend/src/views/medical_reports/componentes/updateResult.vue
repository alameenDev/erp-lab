<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import { storeToRefs } from "pinia";
import { useTemplatesStore } from "@/store/modules/template";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useresultStatusStore } from "@/store/modules/result-status";
import { t, sanitizeHtml } from "@/utils/helper";

const templatesStore = useTemplatesStore();
const invoicesStore = useinvoicesStore();
const resultStatusStore = useresultStatusStore();

const { templates } = storeToRefs(templatesStore);
const { resultStatus } = storeToRefs(resultStatusStore);
const {
  updateResultRecord,
  updateResultModalDialog,
  testsComment,
  cultursComment,
} = storeToRefs(invoicesStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

// Local state
const attachments = ref([{ name: "", file: null }]);
const showAttributes = ref([]);
const selectedTemplate = ref(null);
const activeTestTab = ref(0);
const activePackageTab = ref(0);
const activeTestGroupTab = ref(0);
const activeCultureTab = ref(0);

// Methods
const getFilteredRanges = (referenceRanges) => {
  const patientDetails = updateResultRecord.value?.patient;
  if (!patientDetails || !referenceRanges) return [];

  const convertToDays = (age, unit) => {
    switch (unit) {
      case "Days":
        return age;
      case "Months":
        return age * 30;
      case "Years":
        return age * 365;
      default:
        return age;
    }
  };

  const patientAgeInDays = convertToDays(patientDetails.age, patientDetails.age_unit);

  return referenceRanges.filter((range) => {
    const rangeGender = range.gender?.toLowerCase();
    const isGenderMatch = !rangeGender || rangeGender === "both" || rangeGender === patientDetails.gender?.toLowerCase();
    if (range.age_from == null && range.age_to == null) return isGenderMatch;
    const rangeAgeFromInDays = convertToDays(range.age_from ?? 0, range.age_unit);
    const rangeAgeToInDays = convertToDays(range.age_to ?? 999, range.age_unit);
    const isAgeMatch = patientAgeInDays >= rangeAgeFromInDays && patientAgeInDays <= rangeAgeToInDays;
    return isGenderMatch && isAgeMatch;
  });
};

const autoDetectStatus = (item) => {
  const val = parseFloat(item.result);
  if (isNaN(val)) return;
  let ranges = getFilteredRanges(item.test_reference_ranges);
  if (!ranges?.length) ranges = item.test_reference_ranges || [];
  if (!ranges.length) return;
  const range = ranges.find(r => r.from != null && r.to != null && !isNaN(parseFloat(r.from)) && !isNaN(parseFloat(r.to)));
  if (!range) return;
  const from = parseFloat(range.from);
  const to = parseFloat(range.to);
  if (val < from) item.result_status_id_fk = 4;
  else if (val > to) item.result_status_id_fk = 1;
  else item.result_status_id_fk = 2;
};

const addAttachment = () => {
  attachments.value.push({ name: "", file: null });
};

const removeAttachment = (index) => {
  attachments.value.splice(index, 1);
};

const toggleAttributes = (index) => {
  showAttributes.value[index] = !showAttributes.value[index];
};

const onFileChange = (event, index) => {
  const file = event.target.files?.[0];
  if (file) {
    attachments.value[index].file = file;
  }
};

const loadTemplate = async () => {
  try {
    const testData = updateResultRecord.value.tests[0];
    if (!testData?.content?.html) {
      selectedTemplate.value = null;
      return;
    }

    let templateHtml = testData.content.html;

    if (Array.isArray(testData.sub_tests)) {
      testData.sub_tests.forEach((sub, index) => {
        const regex = new RegExp(`{{sub_test\\.${sub.name}\\.value}}`, "g");
        let replacement = "";

        if (sub.type === 4 && Array.isArray(sub.sup_test_reference_options) && sub.sup_test_reference_options.length > 0) {
          replacement = `
            <select class="editable-field px-2 py-1 border border-gray-300 rounded text-sm" data-key="sub_tests[${index}].value" data-test-id="${testData.test_id_fk}">
              ${sub.sup_test_reference_options.map(option => `<option value="${option}" ${option === sub.value ? "selected" : ""}>${option}</option>`).join("")}
            </select>
          `;
        } else {
          replacement = `<input type="text" class="editable-field px-2 py-1 border border-gray-300 rounded text-sm" value="${sub.value || ""}" data-key="sub_tests[${index}].value" data-test-id="${testData.test_id_fk}" />`;
        }

        templateHtml = templateHtml.replace(regex, replacement);
      });
    }

    // SECURITY: Sanitize template HTML to prevent XSS while allowing form elements
    const sanitizedHtml = sanitizeHtml(templateHtml, {
      ALLOWED_TAGS: [
        "p", "br", "span", "div", "strong", "b", "i", "em", "u",
        "h1", "h2", "h3", "h4", "h5", "h6",
        "ul", "ol", "li", "table", "tr", "td", "th", "thead", "tbody",
        "img", "input", "select", "option", "label", "blockquote", "pre", "code"
      ],
      ALLOWED_ATTR: [
        "class", "style", "src", "alt", "href", "target",
        "width", "height", "colspan", "rowspan",
        "type", "value", "data-key", "data-test-id", "selected", "placeholder"
      ]
    });
    selectedTemplate.value = `<div id="custom-table-container">${sanitizedHtml}</div>`;

    nextTick(() => {
      document.querySelectorAll(".editable-field").forEach((input) => {
        input.addEventListener("input", (e) => {
          const key = e.target.dataset.key;
          const value = e.target.value;
          const match = key.match(/sub_tests\[(\d+)\]\.(.+)/);

          if (match) {
            const idx = parseInt(match[1]);
            const field = match[2];
            const mainTest = updateResultRecord.value.tests[0];
            if (mainTest && mainTest.sub_tests[idx]) {
              mainTest.sub_tests[idx][field] = value;
            }
          }
        });
      });
    });
  } catch (error) {
    console.error("Error loading template:", error);
  }
};

const update = () => {
  updateResultRecord.value.attachments = attachments.value.map((item) => ({
    name: item.name || null,
    file: item.file || null,
  }));

  invoicesStore.updateResult().then(() => {
    testsComment.value = [];
    cultursComment.value = [];
    updateResultModalDialog.value = false;
  });
};

const close = () => {
  updateResultModalDialog.value = false;
};

// Watchers
watch(updateResultModalDialog, (val) => {
  if (val) {
    nextTick(() => {
      if (updateResultRecord.value.tests?.length > 0) {
        loadTemplate();
      }
    });
  }
});

// Lifecycle
onMounted(() => {
  templatesStore.GetTemplates();
  resultStatusStore.GetresultStatus();
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="updateResultModalDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-7xl max-h-[90vh] overflow-hidden flex flex-col"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("updateResult") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- File Upload Section -->
            <div class="border border-gray-200 rounded-lg p-4">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">رفع الملفات</h3>
                <button
                  @click="addAttachment"
                  class="p-2 bg-green-500 text-white rounded-full hover:bg-primary-600 transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                </button>
              </div>
              <table class="w-full">
                <thead class="bg-gray-700 text-white">
                  <tr>
                    <th class="px-4 py-2 text-center">{{ t("name") }}</th>
                    <th class="px-4 py-2 text-center">{{ t("file") }}</th>
                    <th class="px-4 py-2 text-center">{{ t("delete") }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in attachments" :key="index" class="border-b border-gray-200">
                    <td class="p-2">
                      <input
                        v-model="item.name"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#004e54] focus:border-transparent"
                      />
                    </td>
                    <td class="p-2">
                      <input
                        type="file"
                        @change="(e) => onFileChange(e, index)"
                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#004e54] file:text-white hover:file:bg-[#006970]"
                      />
                    </td>
                    <td class="p-2 text-center">
                      <button
                        @click="removeAttachment(index)"
                        class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-full transition-colors"
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

            <!-- Tests Section -->
            <div class="border-2 border-green-200 rounded-xl p-6">
              <h2 class="text-lg font-bold text-gray-800 mb-4">{{ t("tests") }}</h2>

              <div v-if="updateResultRecord.tests?.length > 0">
                <!-- Tabs -->
                <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-2">
                  <button
                    v-for="(item, index) in updateResultRecord.tests"
                    :key="index"
                    @click="activeTestTab = index"
                    :class="[
                      'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                      activeTestTab === index
                        ? 'bg-[#004e54] text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                  >
                    {{ item.name }}
                  </button>
                </div>

                <!-- Tab Content -->
                <div v-for="(item, index) in updateResultRecord.tests" :key="index" v-show="activeTestTab === index">
                  <!-- Dynamic Template -->
                  <div v-if="item.sub_tests?.length > 0 && selectedTemplate" class="mb-4" style="direction: ltr;">
                    <div v-html="sanitizeHtml(selectedTemplate)" class="dynamic-template"></div>
                  </div>

                  <!-- Regular Table -->
                  <div v-else class="overflow-x-auto">
                    <table class="w-full border-collapse">
                      <thead class="bg-gray-700 text-white">
                        <tr>
                          <th class="px-3 py-2 text-center text-sm">{{ t("done") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("tests-reference-ranges") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Unit") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result_Type") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("last_result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Test_Group_Comment") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Original_Price") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("name") }}</th>
                        </tr>
                      </thead>
                      <tbody class="bg-gray-50">
                        <tr class="border-b border-gray-200">
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="item.is_done" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                          <td class="p-2 text-center text-sm">
                            <span v-for="range in getFilteredRanges(item.test_reference_ranges)" :key="range.id">
                              <div v-if="range.notes">
                                <p v-for="(line, idx) in range.notes.split('\n')" :key="idx">{{ line }}</p>
                              </div>
                              <p v-else>{{ range.from }}-{{ range.to }}</p>
                              <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
                            </span>
                          </td>
                          <td class="p-2 text-center text-sm">{{ item.unit ?? "---" }}</td>
                          <td class="p-2">
                            <select
                              v-model="item.result_status_id_fk"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                              <option value="">{{ t("select") }}</option>
                              <option v-for="status in resultStatus" :key="status.value" :value="status.value">
                                {{ status.label }}
                              </option>
                            </select>
                          </td>
                          <td class="p-2">
                            <input
                              v-if="item.result_type_id_fk === 1 || item.result_type_id_fk === 2"
                              type="number"
                              v-model="item.result"
                              @input="autoDetectStatus(item)"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <input
                              v-else-if="item.result_type_id_fk === 3 || !item.result_type_id_fk"
                              type="text"
                              v-model="item.result"
                              @input="autoDetectStatus(item)"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <select
                              v-else-if="item.result_type_id_fk === 4"
                              v-model="item.result"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                              <option value="">{{ t("select") }}</option>
                              <option v-for="opt in item.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="item.last_result" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                          <td class="p-2">
                            <input
                              type="text"
                              v-model="item.comment"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                          </td>
                          <td class="p-2 text-center text-sm">{{ item.price ?? 0 }}</td>
                          <td class="p-2 text-center text-sm font-medium">{{ item.name ?? "---" }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Result Comments -->
                  <div class="mt-4 space-y-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("Result_Comments") }}</label>
                    <div v-if="updateResultRecord.result_comments_tests?.length > 0" class="flex flex-wrap gap-1.5">
                      <button
                        v-for="comment in updateResultRecord.result_comments_tests"
                        :key="comment"
                        type="button"
                        @click="updateResultRecord.tests_comment = updateResultRecord.tests_comment ? updateResultRecord.tests_comment + ', ' + comment : comment"
                        class="px-2.5 py-1 text-xs bg-blue-50 text-blue-700 border border-blue-200 rounded-md hover:bg-blue-100 transition-colors"
                      >
                        {{ comment }}
                      </button>
                    </div>
                    <textarea
                      v-model="updateResultRecord.tests_comment"
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#004e54] resize-none"
                      :placeholder="t('enter_comment')"
                    ></textarea>
                  </div>
                </div>
              </div>
              <div v-else class="flex justify-center p-4">
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ t("noData") }}</div>
              </div>
            </div>

            <!-- Packages Section -->
            <div class="border-2 border-green-200 rounded-xl p-6">
              <h2 class="text-lg font-bold text-[#004e54] mb-4">{{ t("packages") }}</h2>

              <div v-if="updateResultRecord.packages?.length > 0">
                <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-2">
                  <button
                    v-for="(item, index) in updateResultRecord.packages"
                    :key="index"
                    @click="activePackageTab = index"
                    :class="[
                      'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                      activePackageTab === index ? 'bg-[#004e54] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                  >
                    {{ item.name }}
                  </button>
                </div>

                <div v-for="(pkg, pIndex) in updateResultRecord.packages" :key="pIndex" v-show="activePackageTab === pIndex">
                  <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                      <thead class="bg-gray-700 text-white">
                        <tr>
                          <th class="px-3 py-2 text-center text-sm">{{ t("name") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Original_Price") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Test_Group_Comment") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("last_result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result_Type") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("done") }}</th>
                        </tr>
                      </thead>
                      <tbody class="bg-gray-50">
                        <tr v-for="(test, tIndex) in pkg.tests" :key="'test-' + tIndex" class="border-b border-gray-200">
                          <td class="p-2 text-center text-sm">{{ test.name }}</td>
                          <td class="p-2 text-center text-sm">{{ test.price ?? 0 }}</td>
                          <td class="p-2">
                            <input type="text" v-model="test.comment" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2">
                            <input
                              v-if="test.result_type_id_fk === 1 || test.result_type_id_fk === 2"
                              type="number"
                              v-model="test.result"
                              @input="autoDetectStatus(test)"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <input
                              v-else-if="test.result_type_id_fk === 3"
                              type="text"
                              v-model="test.result"
                              @input="autoDetectStatus(test)"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <select
                              v-else-if="test.result_type_id_fk === 4"
                              v-model="test.result"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                              <option v-for="opt in test.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <input v-else type="text" v-model="test.result" @input="autoDetectStatus(test)" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2">
                            <select v-model="test.result_status_id_fk" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                              <option value="">{{ t("select") }}</option>
                              <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="test.is_done" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                        </tr>
                        <tr v-for="(culture, cIndex) in pkg.cultures" :key="'culture-' + cIndex" class="border-b border-gray-200">
                          <td class="p-2 text-center text-sm">{{ culture.name }}</td>
                          <td class="p-2 text-center text-sm">{{ culture.price ?? 0 }}</td>
                          <td class="p-2">
                            <input type="text" v-model="culture.comment" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="culture.last_result" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                          <td class="p-2">
                            <input type="text" v-model="culture.result" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2">
                            <select v-model="culture.result_status_id_fk" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                              <option value="">{{ t("select") }}</option>
                              <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="culture.is_done" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="mt-4 space-y-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("Result_Comments") }}</label>
                    <div v-if="updateResultRecord.result_package_comments?.length > 0" class="flex flex-wrap gap-1.5">
                      <button
                        v-for="comment in updateResultRecord.result_package_comments"
                        :key="comment"
                        type="button"
                        @click="updateResultRecord.package_comment = updateResultRecord.package_comment ? updateResultRecord.package_comment + ', ' + comment : comment"
                        class="px-2.5 py-1 text-xs bg-purple-50 text-purple-700 border border-purple-200 rounded-md hover:bg-purple-100 transition-colors"
                      >
                        {{ comment }}
                      </button>
                    </div>
                    <textarea
                      v-model="updateResultRecord.package_comment"
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#004e54] resize-none"
                      :placeholder="t('enter_comment')"
                    ></textarea>
                  </div>
                </div>
              </div>
              <div v-else class="flex justify-center p-4">
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ t("noData") }}</div>
              </div>
            </div>

            <!-- Test Groups Section -->
            <div class="border-2 border-green-200 rounded-xl p-6">
              <h2 class="text-lg font-bold text-[#004e54] mb-4">{{ t("test-groups") }}</h2>

              <div v-if="updateResultRecord.test_groups?.length > 0">
                <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-2">
                  <button
                    v-for="(item, index) in updateResultRecord.test_groups"
                    :key="index"
                    @click="activeTestGroupTab = index"
                    :class="[
                      'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                      activeTestGroupTab === index ? 'bg-[#004e54] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                  >
                    {{ item.group_name }}
                  </button>
                </div>

                <div v-for="(group, gIndex) in updateResultRecord.test_groups" :key="gIndex" v-show="activeTestGroupTab === gIndex">
                  <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                      <thead class="bg-gray-700 text-white">
                        <tr>
                          <th class="px-3 py-2 text-center text-sm">{{ t("name") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Original_Price") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Test_Group_Comment") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("last_result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result_Type") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("done") }}</th>
                        </tr>
                      </thead>
                      <tbody class="bg-gray-50">
                        <tr v-for="(test, tIndex) in group.tests" :key="tIndex" class="border-b border-gray-200">
                          <td class="p-2 text-center text-sm">{{ test.name }}</td>
                          <td class="p-2 text-center text-sm">{{ test.price ?? 0 }}</td>
                          <td class="p-2">
                            <input type="text" v-model="test.comment" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="test.last_result" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                          <td class="p-2">
                            <input
                              v-if="test.result_type_id_fk === 1 || test.result_type_id_fk === 2"
                              type="number"
                              v-model="test.result"
                              @input="autoDetectStatus(test)"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <input
                              v-else-if="test.result_type_id_fk === 3"
                              type="text"
                              v-model="test.result"
                              @input="autoDetectStatus(test)"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <select
                              v-else-if="test.result_type_id_fk === 4"
                              v-model="test.result"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                              <option v-for="opt in test.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <input v-else type="text" v-model="test.result" @input="autoDetectStatus(test)" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2">
                            <select v-model="test.result_status_id_fk" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                              <option value="">{{ t("select") }}</option>
                              <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="test.is_done" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div v-else class="flex justify-center p-4">
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ t("noData") }}</div>
              </div>
            </div>

            <!-- Cultures Section -->
            <div class="border-2 border-green-200 rounded-xl p-6">
              <h2 class="text-lg font-bold text-gray-800 mb-4">{{ t("cultures") }}</h2>

              <div v-if="updateResultRecord.cultures?.length > 0">
                <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-2">
                  <button
                    v-for="(item, index) in updateResultRecord.cultures"
                    :key="index"
                    @click="activeCultureTab = index"
                    :class="[
                      'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                      activeCultureTab === index ? 'bg-[#004e54] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                  >
                    {{ item.name }}
                  </button>
                </div>

                <div v-for="(culture, cIndex) in updateResultRecord.cultures" :key="cIndex" v-show="activeCultureTab === cIndex">
                  <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                      <thead class="bg-gray-700 text-white">
                        <tr>
                          <th class="px-3 py-2 text-center text-sm">{{ t("name") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Original_Price") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Test_Group_Comment") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("last_result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Result_Type") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("done") }}</th>
                          <th class="px-3 py-2 text-center text-sm">{{ t("Show_Attributes") }}</th>
                        </tr>
                      </thead>
                      <tbody class="bg-gray-50">
                        <tr class="border-b border-gray-200">
                          <td class="p-2 text-center text-sm">{{ culture.name }}</td>
                          <td class="p-2 text-center text-sm">{{ culture.price ?? 0 }}</td>
                          <td class="p-2">
                            <input type="text" v-model="culture.comment" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="culture.last_result" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                          <td class="p-2">
                            <input
                              v-if="culture.result_type_id_fk === 1 || culture.result_type_id_fk === 2"
                              type="number"
                              v-model="culture.result"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <input
                              v-else-if="culture.result_type_id_fk === 3"
                              type="text"
                              v-model="culture.result"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                            <select
                              v-else-if="culture.result_type_id_fk === 4"
                              v-model="culture.result"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            >
                              <option v-for="opt in culture.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <input v-else type="text" v-model="culture.result" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                          </td>
                          <td class="p-2">
                            <select v-model="culture.result_status_id_fk" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                              <option value="">{{ t("select") }}</option>
                              <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                          </td>
                          <td class="p-2 text-center">
                            <input type="checkbox" v-model="culture.is_done" class="w-4 h-4 text-[#004e54] rounded" />
                          </td>
                          <td class="p-2 text-center">
                            <button
                              @click="toggleAttributes(cIndex)"
                              class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-full transition-colors"
                            >
                              <svg
                                class="w-5 h-5 transition-transform"
                                :class="{ 'rotate-180': showAttributes[cIndex] }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                              >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                              </svg>
                            </button>
                          </td>
                        </tr>
                        <!-- Attributes Row -->
                        <tr v-if="showAttributes[cIndex]">
                          <td colspan="8" class="p-4">
                            <table class="w-full border-collapse">
                              <thead class="bg-green-700 text-white">
                                <tr>
                                  <th class="px-3 py-2 text-center text-sm">{{ t("name") }}</th>
                                  <th class="px-3 py-2 text-center text-sm">{{ t("Order") }}</th>
                                  <th class="px-3 py-2 text-center text-sm">{{ t("Result") }}</th>
                                </tr>
                              </thead>
                              <tbody class="bg-white">
                                <tr v-for="attribute in culture.attribute" :key="attribute.id" class="border-b border-gray-200">
                                  <td class="p-2 text-center text-sm">{{ attribute.name }}</td>
                                  <td class="p-2 text-center text-sm">{{ attribute.order }}</td>
                                  <td class="p-2">
                                    <input
                                      v-if="attribute.result_type_id_fk === 1"
                                      type="number"
                                      v-model="attribute.result"
                                      class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                    />
                                    <input
                                      v-else-if="attribute.result_type_id_fk === 3"
                                      type="text"
                                      v-model="attribute.result"
                                      class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                    />
                                    <select
                                      v-else-if="attribute.result_type_id_fk === 4"
                                      v-model="attribute.result"
                                      class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                    >
                                      <option v-for="opt in attribute.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>
                                    <input v-else type="text" v-model="attribute.result" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" />
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="mt-4 space-y-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("Result_Comments") }}</label>
                    <div v-if="updateResultRecord.result_comments_cultures?.length > 0" class="flex flex-wrap gap-1.5">
                      <button
                        v-for="comment in updateResultRecord.result_comments_cultures"
                        :key="comment"
                        type="button"
                        @click="updateResultRecord.cultures_comment = updateResultRecord.cultures_comment ? updateResultRecord.cultures_comment + ', ' + comment : comment"
                        class="px-2.5 py-1 text-xs bg-rose-50 text-rose-700 border border-rose-200 rounded-md hover:bg-rose-100 transition-colors"
                      >
                        {{ comment }}
                      </button>
                    </div>
                    <textarea
                      v-model="updateResultRecord.cultures_comment"
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#004e54] resize-none"
                      :placeholder="t('enter_comment')"
                    ></textarea>
                  </div>
                </div>
              </div>
              <div v-else class="flex justify-center p-4">
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ t("noData") }}</div>
              </div>
            </div>

            <!-- Result Comment -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("resultComment") }}</label>
              <input
                type="text"
                v-model="updateResultRecord.comments"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#004e54] focus:border-transparent"
              />
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50">
            <button
              @click="close"
              class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              {{ t("close") }}
            </button>
            <button
              @click="update"
              class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
            >
              {{ t("update") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
