<template>
  <Dialog
    v-model:visible="dialog"
    modal
    :header="record?.id ? t('update') : t('add')"
    style="width: 85rem"
    :style="lang == 'en' ? 'direction:ltr' : 'direction: rtl'"
    :contentStyle="{ padding: 0 }"
  >
    <form @submit.prevent="record.id ? update() : create()">
      <div class="p-6 space-y-6 bg-slate-50/50">
        <!-- Grid Layout -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

          <!-- Left Column - Main Content (8 cols) -->
          <div class="xl:col-span-8 space-y-6">

            <!-- ==================== BASIC INFO CARD ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
              <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-500 to-teal-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                      </svg>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-slate-800">{{ t("test_basic_info") || "Basic Information" }}</h3>
                      <p class="text-sm text-slate-500">{{ t("test_basic_info_desc") || "Enter test name and details" }}</p>
                    </div>
                  </div>
                  <span class="px-3 py-1 bg-primary-100 text-primary-700 text-xs font-semibold rounded-full">
                    {{ t("step") || "Step" }} 1
                  </span>
                </div>
              </div>

              <div class="p-6">
                <!-- Test Type Selection -->
                <div class="mb-6 p-4 bg-slate-50 rounded-xl">
                  <label class="block text-sm font-medium text-slate-700 mb-3">{{ t("test_type") || "Test Type" }}</label>
                  <div class="flex gap-6">
                    <div class="flex items-center gap-2">
                      <RadioButton v-model="record.is_special_test" inputId="normalTest" name="testType" :value="false" />
                      <label for="normalTest" class="text-sm text-slate-700 cursor-pointer">{{ t("normal_test") || "Normal Test" }}</label>
                    </div>
                    <div class="flex items-center gap-2">
                      <RadioButton v-model="record.is_special_test" inputId="specialTest" name="testType" :value="true" />
                      <label for="specialTest" class="text-sm text-slate-700 cursor-pointer">{{ t("special_test") || "Special Test" }}</label>
                    </div>
                  </div>
                </div>

                <!-- Form Fields Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("name") }} <span class="text-red-500">*</span></label>
                    <InputText required class="w-full" type="text" v-model="record.name" maxlength="255" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("interface_code") }}</label>
                    <InputText class="w-full" type="text" v-model="record.interface_code" maxlength="255" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Shortcut") }} <span class="text-red-500">*</span></label>
                    <InputText required class="w-full" type="text" v-model="record.shortcut" maxlength="255" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Report_Name") }} <span class="text-red-500">*</span></label>
                    <InputText required class="w-full" type="text" v-model="record.report_name" maxlength="255" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Order") }} <span class="text-red-500">*</span></label>
                    <InputNumber required class="w-full" v-model="record.order" :min="1" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Unit") }}</label>
                    <InputText class="w-full" type="text" v-model="record.unit" maxlength="255" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Test_Duration") }} <span class="text-red-500">*</span></label>
                    <InputText required class="w-full" type="number" v-model="record.test_duration" min="0" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Duration_Unit") }} <span class="text-red-500">*</span></label>
                    <Dropdown required class="w-full" v-model="record.duration_unit_id_fk" :options="durationUnitsList" optionLabel="label" optionValue="value" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Category") }} <span class="text-red-500">*</span></label>
                    <Dropdown required class="w-full" v-model="record.category_id_fk" :options="categories()" optionLabel="label" optionValue="value" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Sample") }} <span class="text-red-500">*</span></label>
                    <Dropdown required class="w-full" v-model="record.sample_id_fk" :options="samples()" optionLabel="label" optionValue="value" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Result_Type") }} <span class="text-red-500">*</span></label>
                    <Dropdown required class="w-full" v-model="record.result_type_id_fk" :options="resultTypes" optionLabel="label" optionValue="value" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("test-questions") }}</label>
                    <MultiSelect v-model="record.question_ids_fk" :options="Questions()" optionLabel="label" optionValue="value" filter class="w-full" />
                  </div>
                </div>

                <!-- Checkboxes -->
                <div class="flex items-center gap-6 mt-4 pt-4 border-t border-slate-100">
                  <div class="flex items-center gap-2">
                    <Checkbox v-model="record.is_print_alone" inputId="printAlone" binary />
                    <label for="printAlone" class="text-sm text-slate-700 cursor-pointer">{{ t("Print_Alone") }}</label>
                  </div>
                  <div class="flex items-center gap-2">
                    <Checkbox v-model="record.is_contain_status" inputId="containStatus" binary />
                    <label for="containStatus" class="text-sm text-slate-700 cursor-pointer">{{ t("Status") }}</label>
                  </div>
                </div>
              </div>
            </div>

            <!-- ==================== RESULT COMMENTS CARD ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
              <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                      </svg>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-slate-800">{{ t("Result_Comments") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("result_comments_desc") || "Add predefined comments for results" }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="addComment()"
                    class="p-2 text-amber-600 hover:text-amber-700 hover:bg-amber-100 rounded-lg transition-colors"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="p-6">
                <div v-if="record.result_comments?.length" class="space-y-2">
                  <div
                    v-for="(comment, index) in record.result_comments"
                    :key="index"
                    class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl group"
                  >
                    <span class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 text-sm font-medium shrink-0">
                      {{ index + 1 }}
                    </span>
                    <InputText class="flex-1" type="text" v-model="record.result_comments[index]" maxlength="255" />
                    <button
                      type="button"
                      @click="removeComment(record?.result_comments, index)"
                      class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
                <div v-else class="py-8 text-center">
                  <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                  </div>
                  <p class="text-slate-500 text-sm">{{ t("no_comments") || "No comments added yet" }}</p>
                </div>
              </div>
            </div>

            <!-- ==================== SELECTION OPTIONS CARD (Conditional) ==================== -->
            <div v-show="record.result_type_id_fk == 4" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
              <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-purple-50 to-indigo-50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                      </svg>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-slate-800">{{ t("Selection_Type_Options") }}</h3>
                      <p class="text-sm text-slate-500">{{ t("selection_options_desc") || "Define selection options for this test" }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="addOption(record.selection_type_options)"
                    class="p-2 text-purple-600 hover:text-purple-700 hover:bg-purple-100 rounded-lg transition-colors"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="p-6">
                <div class="space-y-2">
                  <div
                    v-for="(opt, index) in record.selection_type_options"
                    :key="index"
                    class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl group"
                  >
                    <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-700 text-sm font-medium shrink-0">
                      {{ index + 1 }}
                    </span>
                    <InputText class="flex-1" type="text" v-model="record.selection_type_options[index]" maxlength="255" />
                    <button
                      type="button"
                      @click="removeOption(record.selection_type_options, index)"
                      class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- ==================== SPECIAL TEST TEMPLATE (Conditional) ==================== -->
            <div v-if="record.is_special_test" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
              <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-rose-50 to-pink-50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center shadow-lg shadow-rose-500/25">
                      <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </div>
                    <div>
                      <h3 class="text-lg font-semibold text-slate-800">{{ t("sub_tests") || "Sub Tests" }}</h3>
                      <p class="text-sm text-slate-500">{{ t("sub_tests_desc") || "Add sub-tests for special test" }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="addSupTest()"
                    class="p-2 text-rose-600 hover:text-rose-700 hover:bg-rose-100 rounded-lg transition-colors"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="p-6 space-y-4">
                <div
                  v-for="(sup, index) in record.sub_tests"
                  :key="index"
                  class="p-4 bg-slate-50 rounded-xl border border-slate-200"
                >
                  <div class="flex items-start justify-between mb-3">
                    <span class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center text-rose-700 text-sm font-semibold">
                      {{ index + 1 }}
                    </span>
                    <button
                      type="button"
                      @click="removeSupTest(index)"
                      class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("name") }}</label>
                      <InputText class="w-full" type="text" v-model="sup.name" maxlength="255" />
                      <small class="text-xs text-slate-500 mt-1 block" v-text="`{{sub_test.${sup.name}.value}}`"></small>
                      <Button
                        type="button"
                        icon="pi pi-copy"
                        size="small"
                        class="p-button-text p-button-info mt-1"
                        :label="t('copy_value') || 'Copy Value'"
                        @click="copySubTestVariable(sup.name, 'value')"
                      />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Result_Type") }}</label>
                      <Dropdown required class="w-full" v-model="sup.type" :options="resultTypes" optionLabel="label" optionValue="value" />
                    </div>
                  </div>

                  <!-- Selection Options for type 4 -->
                  <div v-if="sup.type === 4" class="mt-4 pt-4 border-t border-slate-200">
                    <div class="flex items-center justify-between mb-3">
                      <label class="text-sm font-medium text-slate-700">{{ t("result_options") || "Result Options" }}</label>
                      <Button
                        type="button"
                        icon="pi pi-plus"
                        size="small"
                        class="p-button-outlined"
                        @click="addReferenceOption(index)"
                      />
                    </div>
                    <div class="space-y-2">
                      <div
                        v-for="(opt, optIndex) in sup.sup_test_reference_options"
                        :key="optIndex"
                        class="flex items-center gap-2"
                      >
                        <InputText v-model="sup.sup_test_reference_options[optIndex]" class="flex-1" :placeholder="t('option') || 'Option'" />
                        <!-- Per-option colour. Stored in sup.option_colors keyed by the
                             option text, so existing string-only options keep working and
                             no migration is needed (sub_tests is free-form jsonb). The
                             colour is reused for the result value in update-result,
                             invoice details and every print/PDF/WhatsApp output. -->
                        <label
                          class="flex items-center gap-1.5 px-2 py-1.5 bg-white border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50"
                          :title="t('color') || 'اللون'"
                        >
                          <input
                            type="color"
                            :value="optionColor(sup, opt) || '#1e293b'"
                            @input="setOptionColor(sup, opt, $event.target.value)"
                            class="w-6 h-6 p-0 border-0 bg-transparent cursor-pointer"
                          />
                          <button
                            v-if="optionColor(sup, opt)"
                            type="button"
                            @click.prevent="setOptionColor(sup, opt, null)"
                            class="text-[10px] text-slate-400 hover:text-red-500"
                            :title="t('reset') || 'إزالة اللون'"
                          >&#10005;</button>
                        </label>
                        <!-- "Default" checkbox: when checked, this option's
                             value is included in sup.default_value (comma-
                             separated). Update-result.vue uses default_value
                             when no value has been entered yet. -->
                        <label class="flex items-center gap-1.5 px-2.5 py-2 bg-white border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50">
                          <input
                            type="checkbox"
                            :checked="isDefaultOption(sup, opt)"
                            @change="toggleDefaultOption(sup, opt)"
                            class="w-4 h-4 text-primary-600 rounded border-slate-300 focus:ring-primary-500 cursor-pointer"
                          />
                          <span class="text-xs text-slate-600">{{ t("default") || "افتراضي" }}</span>
                        </label>
                        <button
                          type="button"
                          @click="removeReferenceOption(index, optIndex)"
                          class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        >
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ============== SMART TEMPLATE EDITOR ============== -->
                <div class="mt-6" :class="isFullscreen ? 'fixed inset-0 z-[1000] bg-slate-50 p-4 flex flex-col' : ''">
                  <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-slate-700">
                      {{ t("template_editor") || "Template Editor" }}
                      <span v-if="record?.sub_tests?.length" class="ms-2 text-xs text-slate-400">· type <kbd class="px-1 py-0.5 bg-slate-200 rounded text-[10px] font-mono">{{ '{{' }}</kbd> for variables · <kbd class="px-1 py-0.5 bg-slate-200 rounded text-[10px] font-mono">/</kbd> for commands</span>
                    </label>
                    <div class="flex items-center gap-2">
                      <!-- Lint badge -->
                      <span v-if="lintReport.total > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                        :class="lintReport.broken.length ? 'bg-warning-50 text-warning-700 border border-warning-200' : 'bg-success-50 text-success-700 border border-success-200'"
                        :title="lintReport.broken.length ? ('Broken: ' + lintReport.broken.join(', ')) : 'All variables valid'">
                        <svg v-if="lintReport.broken.length" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                        {{ lintReport.resolved }}/{{ lintReport.total }}
                      </span>
                      <button type="button" @click="showHelp = !showHelp" class="p-1.5 rounded hover:bg-slate-100 text-slate-500" title="Keyboard shortcuts">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </button>
                      <button type="button" @click="toggleFullscreen" class="p-1.5 rounded hover:bg-slate-100 text-slate-500" :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'">
                        <svg v-if="!isFullscreen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 9V5m0 0H5m4 4L4 4m11 5h4m0 0V5m0 4l5-5M9 15v4m0 0H5m4 0l-5 5m11-5h4m0 0v4m0-4l5 5"/></svg>
                      </button>
                    </div>
                  </div>

                  <!-- Toolbar -->
                  <div class="flex flex-wrap items-center gap-1 mb-3 p-2 bg-slate-100 rounded-lg border border-slate-200 relative">
                    <!-- Text Formatting -->
                    <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('bold') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Bold (⌘B)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('italic') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Italic (⌘I)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('underline') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Underline (⌘U)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('strike') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Strikethrough">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7.24 8.75c-.26-.48-.39-1.03-.39-1.67 0-.61.13-1.16.4-1.67.26-.5.63-.93 1.11-1.29.48-.35 1.05-.63 1.7-.83.66-.19 1.39-.29 2.18-.29.81 0 1.54.11 2.21.34.66.22 1.23.54 1.69.94.47.4.83.88 1.08 1.43.25.55.38 1.15.38 1.81h-3.01c0-.31-.05-.59-.15-.85-.09-.27-.24-.49-.44-.68-.2-.19-.45-.33-.75-.44-.3-.1-.66-.16-1.06-.16-.39 0-.74.04-1.03.13-.29.09-.53.21-.72.36-.19.16-.34.34-.44.55-.1.21-.15.43-.15.66 0 .48.25.88.74 1.21.38.25.77.48 1.41.7H7.39c-.05-.08-.11-.17-.15-.25zM21 12v-2H3v2h9.62c.18.07.4.14.55.2.37.17.66.34.87.51.21.17.35.36.43.57.07.2.11.43.11.69 0 .23-.05.45-.14.66-.09.2-.23.38-.42.53-.19.15-.42.26-.71.35-.29.08-.63.13-1.01.13-.43 0-.83-.04-1.18-.13s-.66-.23-.91-.42c-.25-.19-.45-.44-.59-.75-.14-.31-.25-.76-.25-1.21H6.4c0 .55.08 1.13.24 1.58.16.45.37.85.65 1.21.28.35.6.66.98.92.37.26.78.48 1.22.65.44.17.9.3 1.38.39.48.08.96.13 1.44.13.8 0 1.53-.09 2.18-.28s1.21-.45 1.67-.79c.46-.34.82-.77 1.07-1.27s.38-1.07.38-1.71c0-.6-.1-1.14-.31-1.61-.05-.11-.11-.23-.17-.33H21z"/></svg>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Sub/Superscript -->
                    <button type="button" @click="editor.chain().focus().toggleSubscript().run()" :class="['p-1.5 rounded cursor-pointer text-xs', editor?.isActive('subscript') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Subscript (H₂O)">
                      X<sub>2</sub>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleSuperscript().run()" :class="['p-1.5 rounded cursor-pointer text-xs', editor?.isActive('superscript') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Superscript (cm²)">
                      X<sup>2</sup>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Font Size -->
                    <select v-model="fontSize" @change="editor.chain().focus().setMark('textStyle', { fontSize: fontSize + 'px' }).run()" class="px-1.5 py-1 text-xs border border-slate-300 rounded bg-white cursor-pointer" title="Font Size">
                      <option v-for="s in [8,10,12,14,16,18,20,24,28,32,36,48]" :key="s" :value="s">{{ s }}px</option>
                    </select>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Text Color -->
                    <label class="relative p-1.5 rounded hover:bg-slate-200 cursor-pointer" title="Text Color">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11 2L5.5 16h2.25l1.12-3h6.25l1.12 3h2.25L13 2h-2zm-1.38 9L12 4.67 14.38 11H9.62z"/><rect x="3" y="18" width="18" height="3" :fill="textColor"/></svg>
                      <input type="color" v-model="textColor" @input="editor.chain().focus().setColor(textColor).run()" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                    </label>
                    <label class="relative p-1.5 rounded hover:bg-slate-200 cursor-pointer" title="Highlight">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.75 7L14 3.25l-10 10V17h3.75l10-10zm2.96-2.96a.996.996 0 000-1.41L18.37.29a.996.996 0 00-1.41 0L15 2.25 18.75 6l1.96-1.96z"/><rect x="2" y="19" width="20" height="3" :fill="highlightColor"/></svg>
                      <input type="color" v-model="highlightColor" @input="editor.chain().focus().toggleHighlight({ color: highlightColor }).run()" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                    </label>
                    <button type="button" @click="editor.chain().focus().unsetColor().unsetHighlight().run()" class="p-1.5 rounded hover:bg-slate-200 text-slate-500 cursor-pointer" title="Clear Color">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l16 16M12 2v20"/></svg>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Alignment -->
                    <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive({ textAlign: 'left' }) ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Align Left">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive({ textAlign: 'center' }) ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Align Center">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7 15v2h10v-2H7zm-4 6h18v-2H3v2zm0-8h18v-2H3v2zm4-6v2h10V7H7zM3 3v2h18V3H3z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive({ textAlign: 'right' }) ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Align Right">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 21h18v-2H3v2zm6-4h12v-2H9v2zm-6-4h18v-2H3v2zm6-4h12V7H9v2zM3 3v2h18V3H3z"/></svg>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Heading -->
                    <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="['p-1.5 rounded text-xs font-bold cursor-pointer', editor?.isActive('heading', { level: 2 }) ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Heading 2">H2</button>
                    <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="['p-1.5 rounded text-xs font-bold cursor-pointer', editor?.isActive('heading', { level: 3 }) ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Heading 3">H3</button>

                    <!-- Lists -->
                    <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('bulletList') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Bullet list">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('orderedList') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Numbered list">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="['p-1.5 rounded cursor-pointer', editor?.isActive('blockquote') ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Quote">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>
                    </button>
                    <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" class="p-1.5 rounded hover:bg-slate-200 cursor-pointer" title="Horizontal rule">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 11h18v2H3z"/></svg>
                    </button>
                    <button type="button" @click="applySnippet('page_break')" class="p-1.5 rounded hover:bg-slate-200 cursor-pointer" :title="t('page_break') || 'Page break (print)'">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M4 6h5M4 12h16M4 18h5M15 18h5M15 6h5M14 3l3 3-3 3M14 21l3-3-3-3"/>
                      </svg>
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Table (basic insert) -->
                    <input type="number" v-model="rows" min="1" class="w-10 px-1.5 py-1 border border-slate-300 rounded text-xs" title="Rows" />
                    <span class="text-xs text-slate-400">×</span>
                    <input type="number" v-model="cols" min="1" class="w-10 px-1.5 py-1 border border-slate-300 rounded text-xs" title="Columns" />
                    <button type="button" @click="addTable" class="px-2 py-1 rounded hover:bg-slate-200 text-xs font-semibold cursor-pointer" title="Insert blank table">
                      {{ t("add_table") || "Table" }}
                    </button>

                    <!-- The killer button — one-click full report table -->
                    <button
                      v-if="record?.sub_tests?.length"
                      type="button"
                      @click="buildReportTable"
                      class="px-2.5 py-1 rounded bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold cursor-pointer inline-flex items-center gap-1"
                      :title="t('auto_build_report') || 'Build full report table from all sub-tests'"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                      {{ t("report_table") || "Report Table" }}
                    </button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Symbols popover -->
                    <div class="relative">
                      <button type="button" @click.stop="showSymbols = !showSymbols; showSnippets = false" :class="['p-1.5 rounded cursor-pointer', showSymbols ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Medical symbols">
                        Ω
                      </button>
                      <div v-if="showSymbols" class="absolute z-20 top-full mt-1 start-0 bg-white border border-slate-200 rounded-xl shadow-xl p-2 grid grid-cols-6 gap-1 w-[240px]">
                        <button v-for="s in ['±','≤','≥','×','÷','°','µ','Ω','π','α','β','γ','δ','λ','σ','∞','²','³','½','¼','→','←','↑','↓','™','©','®','№','§','¶','•','·']"
                          :key="s" type="button" @click="insertSymbol(s)"
                          class="w-8 h-8 rounded hover:bg-primary-50 text-slate-700 text-sm cursor-pointer">{{ s }}</button>
                      </div>
                    </div>

                    <!-- Snippets popover -->
                    <div class="relative">
                      <button type="button" @click.stop="showSnippets = !showSnippets; showSymbols = false" :class="['p-1.5 rounded cursor-pointer text-xs font-semibold', showSnippets ? 'bg-primary-100 text-primary-700' : 'hover:bg-slate-200']" title="Snippets">
                        {{ t("snippets") || "Snippets" }}
                      </button>
                      <div v-if="showSnippets" class="absolute z-20 top-full mt-1 start-0 bg-white border border-slate-200 rounded-xl shadow-xl py-1 min-w-[200px]">
                        <button type="button" @click="applySnippet('normal')" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">{{ t("normal") || "Normal" }}</button>
                        <button type="button" @click="applySnippet('abnormal')" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">{{ t("abnormal") || "Abnormal" }}</button>
                        <button type="button" @click="applySnippet('see_attached')" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">{{ t("see_attached") || "See attached" }}</button>
                        <button type="button" @click="applySnippet('ref_range')" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">{{ t("reference_range_table") || "Reference range table" }}</button>
                        <button type="button" @click="applySnippet('signature')" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">{{ t("signature_block") || "Signature block" }}</button>
                        <button type="button" @click="applySnippet('page_break')" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer inline-flex items-center gap-2">
                          <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 8h16M4 12h16M4 16h16"/></svg>
                          {{ t("page_break") || "Page break" }}
                        </button>
                        <hr class="my-1 border-slate-100" />
                        <button type="button" @click="normalizeVariables" class="w-full text-start px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer text-warning-700">
                          {{ t("normalize_variables") || "Normalize variables" }}
                        </button>
                      </div>
                    </div>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>

                    <!-- Undo/Redo -->
                    <button type="button" @click="undo" class="p-1.5 rounded hover:bg-slate-200 cursor-pointer" title="Undo (⌘Z)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
                    </button>
                    <button type="button" @click="redo" class="p-1.5 rounded hover:bg-slate-200 cursor-pointer" title="Redo (⌘⇧Z)">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>
                    </button>
                  </div>

                  <!-- Floating table toolbar (shows only when cursor is in a table) -->
                  <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                  >
                    <div v-if="inTable" class="flex flex-wrap items-center gap-1 mb-2 p-1.5 bg-primary-50 border border-primary-200 rounded-lg text-xs">
                      <span class="text-[10px] font-bold text-primary-700 uppercase tracking-wider px-1">Table</span>
                      <button type="button" @click="tblAddRowAbove" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Row above">↑ Row</button>
                      <button type="button" @click="tblAddRowBelow" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Row below">↓ Row</button>
                      <button type="button" @click="tblAddColBefore" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Col before">← Col</button>
                      <button type="button" @click="tblAddColAfter" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Col after">Col →</button>
                      <span class="w-px h-4 bg-primary-300 mx-1"></span>
                      <button type="button" @click="tblDeleteRow" class="px-2 py-1 rounded hover:bg-danger-100 text-danger-700 cursor-pointer" title="Delete row">🗑 Row</button>
                      <button type="button" @click="tblDeleteCol" class="px-2 py-1 rounded hover:bg-danger-100 text-danger-700 cursor-pointer" title="Delete col">🗑 Col</button>
                      <span class="w-px h-4 bg-primary-300 mx-1"></span>
                      <button type="button" @click="tblMerge" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Merge cells">⎘ Merge</button>
                      <button type="button" @click="tblSplit" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Split cells">⎙ Split</button>
                      <button type="button" @click="tblToggleHeader" class="px-2 py-1 rounded hover:bg-white text-slate-700 cursor-pointer" title="Toggle header row">Header</button>
                      <span class="w-px h-4 bg-primary-300 mx-1"></span>
                      <label class="flex items-center gap-1 px-2 text-slate-600">Padding
                        <select @change="tblSetPadding($event.target.value)" class="px-1 py-0.5 border border-slate-300 rounded bg-white cursor-pointer">
                          <option value="">default</option>
                          <option value="2px">compact (2)</option>
                          <option value="4px 6px">normal (4/6)</option>
                          <option value="6px 8px">relaxed</option>
                          <option value="10px">loose</option>
                        </select>
                      </label>
                      <span class="flex-1"></span>
                      <button type="button" @click="tblDelete" class="px-2 py-1 rounded hover:bg-danger-100 text-danger-700 font-semibold cursor-pointer" title="Delete whole table">× Table</button>
                    </div>
                  </Transition>

                  <!-- Shortcuts help drop-in -->
                  <div v-if="showHelp" class="mb-3 p-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-600">
                    <p class="font-bold text-slate-800 mb-2">Keyboard & editor shortcuts</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1.5">
                      <div><kbd class="font-mono bg-slate-100 px-1 rounded">{{ '{{' }}</kbd> insert sub-test variable</div>
                      <div><kbd class="font-mono bg-slate-100 px-1 rounded">/</kbd> slash command menu</div>
                      <div><kbd class="font-mono bg-slate-100 px-1 rounded">⌘B / ⌘I / ⌘U</kbd> bold / italic / underline</div>
                      <div><kbd class="font-mono bg-slate-100 px-1 rounded">⌘Z / ⌘⇧Z</kbd> undo / redo</div>
                      <div><kbd class="font-mono bg-slate-100 px-1 rounded">Tab / ⇧Tab</kbd> next / previous cell in table</div>
                      <div><kbd class="font-mono bg-slate-100 px-1 rounded">Enter</kbd> in table = new paragraph inside cell</div>
                    </div>
                  </div>

                  <editor-content v-model="record.content" :editor="editor" style="direction: ltr !important;" class="flex-1 min-h-[200px] p-4 bg-white border border-slate-200 rounded-xl overflow-auto" />
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column - Sticky Sidebar (4 cols) -->
          <div class="xl:col-span-4">
            <div class="xl:sticky xl:top-6 space-y-6">

              <!-- ==================== PRICING CARD ==================== -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-green-50 to-emerald-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                      <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <h3 class="font-semibold text-slate-800">{{ t("pricing") || "Pricing" }}</h3>
                  </div>
                </div>
                <div class="p-5 space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Customer_Price") }} <span class="text-red-500">*</span></label>
                    <InputText required class="w-full" type="number" v-model="record.for_customer_price" min="0" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Original_Price") }} <span class="text-red-500">*</span></label>
                    <InputNumber required class="w-full" v-model="record.price" :min="0" />
                  </div>
                </div>
              </div>

              <!-- ==================== REFERENCE RANGES CARD ==================== -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                      </div>
                      <h3 class="font-semibold text-slate-800">{{ t("tests-reference-ranges") }}</h3>
                    </div>
                    <button
                      type="button"
                      @click="addRecord(record.test_reference_ranges)"
                      class="p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-100 rounded-lg transition-colors"
                    >
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                      </svg>
                    </button>
                  </div>
                </div>
                <div class="p-5 space-y-4 max-h-[500px] overflow-y-auto">
                  <div
                    v-for="(ref, index) in record.test_reference_ranges"
                    :key="index"
                    class="p-4 bg-slate-50 rounded-xl border border-slate-200 relative"
                  >
                    <button
                      type="button"
                      @click="removeRecord(record.test_reference_ranges, index)"
                      class="absolute top-2 end-2 p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>

                    <div class="space-y-3">
                      <div class="grid grid-cols-2 gap-3">
                        <div>
                          <label class="block text-xs font-medium text-slate-600 mb-1">{{ t("gender") }} <span class="text-red-500">*</span></label>
                          <Dropdown required class="w-full text-sm" v-model="ref.gender_id_fk" :options="genders" optionLabel="label" optionValue="value" />
                        </div>
                        <div>
                          <label class="block text-xs font-medium text-slate-600 mb-1">{{ t("age_unit") }} <span class="text-red-500">*</span></label>
                          <Dropdown required class="w-full text-sm" v-model="ref.age_unit_id_fk" :options="AgeUnits" optionLabel="label" optionValue="value" />
                        </div>
                      </div>

                      <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">{{ t("age") }} <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                          <InputText required class="flex-1 text-sm" v-model="ref.age_from" :min="0" :placeholder="t('From')" />
                          <InputText required class="flex-1 text-sm" v-model="ref.age_to" :min="0" :placeholder="t('To')" />
                        </div>
                      </div>

                      <!-- Normal Range -->
                      <div v-if="record?.result_type_id_fk == '' || record?.result_type_id_fk == 1 || record?.result_type_id_fk == 2">
                        <label class="block text-xs font-medium text-slate-600 mb-1">{{ t("normal") }} <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                          <InputText required class="flex-1 text-sm" v-model="ref.from" :placeholder="t('From')" />
                          <InputText required class="flex-1 text-sm" v-model="ref.to" :placeholder="t('To')" />
                        </div>
                      </div>

                      <!-- Reference Options -->
                      <div v-if="record?.result_type_id_fk == 3 || record?.result_type_id_fk == 4">
                        <div class="flex items-center justify-between mb-2">
                          <label class="text-xs font-medium text-slate-600">{{ t("test_reference_options") }}</label>
                          <button type="button" @click="addRefOption(ref.test_reference_options)" class="p-1 text-blue-600 hover:bg-blue-50 rounded">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                          </button>
                        </div>
                        <div class="space-y-2">
                          <div v-for="(r, rIndex) in ref.test_reference_options" :key="rIndex" class="flex items-center gap-2">
                            <InputText class="flex-1 text-sm" type="text" v-model="ref.test_reference_options[rIndex]" maxlength="255" />
                            <button type="button" @click="removeRefOption(rIndex, ref.test_reference_options)" class="p-1 text-red-400 hover:text-red-600">
                              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        </div>
                      </div>

                      <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">{{ t("Test_Group_Comment") }}</label>
                        <Textarea v-model="ref.notes" rows="2" class="w-full text-sm whitespace-pre-line" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ==================== ACTION BUTTONS ==================== -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
                <div class="space-y-3">
                  <button
                    type="submit"
                    class="w-full px-6 py-3 text-sm font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ record.id ? t("save") : t("add") }}
                  </button>
                  <button
                    type="button"
                    @click="close()"
                    class="w-full px-6 py-3 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
                  >
                    {{ t("cancel") }}
                  </button>
                </div>
              </div>

            </div>
          </div>
        </div>
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
import Underline from "@tiptap/extension-underline";
import Image from "@tiptap/extension-image";
import TextStyle from "@tiptap/extension-text-style";
import Subscript from "@tiptap/extension-subscript";
import Superscript from "@tiptap/extension-superscript";
import { VariableSuggestion } from "./editor/variableSuggestion";
import { SlashCommands } from "./editor/slashCommands";
import { buildRender } from "./editor/suggestionRender";
import { PageBreak } from "./editor/pageBreakNode";
import { slugifyKey, buildVar, lintTemplate } from "@/composables/useSubTestVar";

const FontSize = TextStyle.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      fontSize: {
        default: null,
        parseHTML: (element) => element.style.fontSize?.replace(/['"]+/g, ""),
        renderHTML: (attributes) => {
          if (!attributes.fontSize) return {};
          return { style: `font-size: ${attributes.fontSize}` };
        },
      },
    };
  },
});

// Allow per-cell padding via data-cell-padding attribute. Used by the cell
// padding control in the bubble menu and respected at print-time.
const cellPaddingAttr = {
     cellPadding: {
          default: null,
          parseHTML: (el) => el.getAttribute("data-cell-padding"),
          renderHTML: (attrs) => {
               if (!attrs.cellPadding) return {};
               return {
                    "data-cell-padding": attrs.cellPadding,
                    style: `padding: ${attrs.cellPadding}`,
               };
          },
     },
};
const PadCell = TableCell.extend({
     addAttributes() { return { ...this.parent?.(), ...cellPaddingAttr }; },
});
const PadHeader = TableHeader.extend({
     addAttributes() { return { ...this.parent?.(), ...cellPaddingAttr }; },
});

import Placeholder from "@tiptap/extension-placeholder";
import { Color } from "@tiptap/extension-color";
import TextAlign from "@tiptap/extension-text-align";
import Highlight from "@tiptap/extension-highlight";

export default {
  components: { EditorContent },
  data() {
    const self = this;
    const editor = new Editor({
      extensions: [
        StarterKit.configure({
          heading: { levels: [1, 2, 3] },
        }),
        Table.configure({ resizable: true }),
        TableRow,
        PadCell,
        PadHeader,
        PageBreak,
        Underline,
        Image,
        Subscript,
        Superscript,
        FontSize,
        Color,
        Highlight.configure({ multicolor: true }),
        TextAlign.configure({ types: ["heading", "paragraph"] }),
        Placeholder.configure({
          placeholder: "Type / for commands, {{ to insert a sub-test variable, or just start writing…",
        }),
        VariableSuggestion.configure({
          getSubTests: () => self.record?.sub_tests || [],
          render: buildRender(),
        }),
        SlashCommands.configure({
          getItems: (query) => self.getSlashItems(query),
          render: buildRender(),
        }),
      ],
      content: "",
      onUpdate: () => {
        self.refreshCursorState();
      },
      onSelectionUpdate: () => {
        self.refreshCursorState();
      },
    });
    return {
      editor,
      dialogVisible: false,
      selectedTemplate: { name: "", content: { html: "" } },
      rows: 1,
      cols: 3,
      textColor: "#000000",
      highlightColor: "#ffff00",
      fontSize: "16",
      // New smart-editor state
      isFullscreen: false,
      inTable: false,
      showSymbols: false,
      showSnippets: false,
      showHelp: false,
      lintReport: { resolved: 0, broken: [], total: 0 },
    };
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
  watch: {
    dialog(visible) {
      if (visible && this.record?.id && this.record.content?.html) {
        // Migrate templates saved with an earlier build where TipTap stripped
        // the <div class="page-break"> and left only <p>Page break</p> text.
        const migrated = this.record.content.html.replace(
          /<p[^>]*>\s*Page\s*break\s*<\/p>/gi,
          '<div data-type="page-break"></div>'
        );
        this.editor.commands.setContent(migrated);
      } else if (visible && !this.record?.id) {
        this.editor.commands.setContent("");
      }
      if (visible) {
        this.$nextTick(() => {
          this.runLint();
          this.refreshCursorState();
        });
      } else {
        this.isFullscreen = false;
        this.showSymbols = false;
        this.showSnippets = false;
        this.showHelp = false;
      }
    },
    // Re-lint whenever sub_tests change (keys move, lint updates).
    "record.sub_tests": {
      deep: true,
      handler() {
        this.runLint();
      },
    },
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
    this.record.is_special_test = false;
    // One-time cleanup: drop any leftover template drafts from the removed
    // auto-save feature so they don't accumulate in localStorage.
    try {
      Object.keys(localStorage).forEach((k) => {
        if (k.startsWith("digitalab:test-tmpl-draft:")) localStorage.removeItem(k);
      });
    } catch {}
    // Close popovers on outside click / Escape
    this._clickAway = (e) => {
      if (!this.$el?.contains(e.target)) {
        this.showSymbols = false;
        this.showSnippets = false;
      }
    };
    this._escKey = (e) => {
      if (e.key === "Escape") {
        if (this.isFullscreen) this.isFullscreen = false;
        this.showSymbols = false;
        this.showSnippets = false;
        this.showHelp = false;
      }
    };
    document.addEventListener("click", this._clickAway);
    document.addEventListener("keydown", this._escKey);
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
        html: this.editor.getHTML(),
        text: this.editor.getText(),
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
        this.record.result_comments = [""];
        this.dialog = false;
      });
    },

    addTable() {
      if (this.rows < 1 || this.cols < 1) {
        alert("Please enter valid rows and columns.");
        return;
      }
      this.editor.chain().focus().insertTable({ rows: this.rows, cols: this.cols, withHeaderRow: true }).run();
    },

    // ===== Auto-build report table (Stage 1a) =====
    // Builds a 4-column report table pre-populated with every defined sub-test.
    buildReportTable() {
      const subs = (this.record?.sub_tests || []).filter((s) => s?.name);
      if (!subs.length) {
        this.alertWarning && this.alertWarning(this.t("no_sub_tests_defined") || "Add at least one sub-test first.");
        return;
      }
      const headerCell = (txt) => `<th><p><strong>${txt}</strong></p></th>`;
      const cell = (txt) => `<td><p>${txt}</p></td>`;
      const rows = subs
        .map((s) => `<tr>${cell(s.name)}${cell(buildVar(s.name, "value"))}${cell("—")}${cell("—")}</tr>`)
        .join("");
      const html =
        `<table><tbody>` +
        `<tr>${headerCell(this.t("test") || "Test")}${headerCell(this.t("Result") || "Result")}${headerCell(this.t("Unit") || "Unit")}${headerCell(this.t("normal") || "Reference")}</tr>` +
        rows +
        `</tbody></table><p></p>`;
      this.editor.chain().focus().insertContent(html).run();
    },

    // Insert a single row of "name | {{var}} | unit | range" at the cursor.
    insertSubTestLine(sub) {
      if (!sub?.name) return;
      const html = `<p><strong>${sub.name}:</strong> ${buildVar(sub.name, "value")}</p>`;
      this.editor.chain().focus().insertContent(html).run();
    },

    // ===== Slash menu items (Stage 1c) =====
    getSlashItems(query) {
      const q = (query || "").toLowerCase();
      const subs = (this.record?.sub_tests || []).filter((s) => s?.name);
      const items = [
        {
          label: this.t("insert_report_table") || "Report table",
          description: "4-col table with all sub-tests",
          icon: "🧬",
          action: () => this.buildReportTable(),
        },
        {
          label: this.t("insert_2col_table") || "Table (2×3)",
          description: "Simple 2-column table",
          icon: "▦",
          action: ({ editor }) => editor.chain().focus().insertTable({ rows: 3, cols: 2, withHeaderRow: true }).run(),
        },
        ...subs.map((s) => ({
          label: `Insert row · ${s.name}`,
          description: buildVar(s.name, "value"),
          icon: "≡",
          action: () => this.insertSubTestLine(s),
        })),
        {
          label: this.t("heading_2") || "Heading 2",
          icon: "H₂",
          shortcut: "##",
          action: ({ editor }) => editor.chain().focus().toggleHeading({ level: 2 }).run(),
        },
        {
          label: this.t("heading_3") || "Heading 3",
          icon: "H₃",
          shortcut: "###",
          action: ({ editor }) => editor.chain().focus().toggleHeading({ level: 3 }).run(),
        },
        {
          label: this.t("bullet_list") || "Bullet list",
          icon: "•",
          action: ({ editor }) => editor.chain().focus().toggleBulletList().run(),
        },
        {
          label: this.t("numbered_list") || "Numbered list",
          icon: "1.",
          action: ({ editor }) => editor.chain().focus().toggleOrderedList().run(),
        },
        {
          label: this.t("quote") || "Quote",
          icon: "❝",
          action: ({ editor }) => editor.chain().focus().toggleBlockquote().run(),
        },
        {
          label: this.t("divider") || "Divider",
          icon: "—",
          action: ({ editor }) => editor.chain().focus().setHorizontalRule().run(),
        },
        {
          label: this.t("page_break") || "Page break",
          description: "Start what follows on a new printed page",
          icon: "↴",
          action: () => this.applySnippet("page_break"),
        },
      ];
      if (!q) return items.slice(0, 12);
      return items.filter((i) => i.label.toLowerCase().includes(q) || (i.description || "").toLowerCase().includes(q)).slice(0, 12);
    },

    // ===== Floating table toolbar (Stage 1d) =====
    refreshCursorState() {
      this.inTable = !!this.editor?.isActive("table");
    },
    tblAddRowAbove() { this.editor.chain().focus().addRowBefore().run(); },
    tblAddRowBelow() { this.editor.chain().focus().addRowAfter().run(); },
    tblAddColBefore() { this.editor.chain().focus().addColumnBefore().run(); },
    tblAddColAfter() { this.editor.chain().focus().addColumnAfter().run(); },
    tblDeleteRow() { this.editor.chain().focus().deleteRow().run(); },
    tblDeleteCol() { this.editor.chain().focus().deleteColumn().run(); },
    tblMerge() { this.editor.chain().focus().mergeCells().run(); },
    tblSplit() { this.editor.chain().focus().splitCell().run(); },
    tblToggleHeader() { this.editor.chain().focus().toggleHeaderRow().run(); },
    tblDelete() { this.editor.chain().focus().deleteTable().run(); },
    tblSetPadding(value) {
      const padding = value || null;
      this.editor
        .chain()
        .focus()
        .updateAttributes("tableCell", { cellPadding: padding })
        .updateAttributes("tableHeader", { cellPadding: padding })
        .run();
    },

    // ===== Medical symbols quick-insert (Stage 2) =====
    insertSymbol(sym) {
      this.editor.chain().focus().insertContent(sym).run();
      this.showSymbols = false;
    },

    // ===== Snippets library (Stage 2) =====
    applySnippet(key) {
      // Page break is a real TipTap node, not raw HTML — otherwise TipTap's
      // schema strips the <div> and the print CSS has nothing to match.
      if (key === "page_break") {
        this.editor.chain().focus().insertPageBreak().run();
        this.showSnippets = false;
        return;
      }
      const snippets = {
        normal: `<p><strong>${this.t("normal") || "Normal"}.</strong></p>`,
        abnormal: `<p><strong>${this.t("abnormal") || "Abnormal."}</strong></p>`,
        see_attached: `<p><em>${this.t("see_attached") || "See attached report."}</em></p>`,
        ref_range: `<table><tbody><tr><th><p><strong>Age</strong></p></th><th><p><strong>Range</strong></p></th></tr><tr><td><p>Adult</p></td><td><p></p></td></tr><tr><td><p>Child</p></td><td><p></p></td></tr></tbody></table><p></p>`,
        signature: `<p>&nbsp;</p><p style="text-align: right"><strong>_______________________</strong><br/>${this.t("signature") || "Signature"}</p>`,
      };
      const html = snippets[key];
      if (html) this.editor.chain().focus().insertContent(html).run();
      this.showSnippets = false;
    },

    // ===== Fullscreen (Stage 2) =====
    toggleFullscreen() {
      this.isFullscreen = !this.isFullscreen;
    },

    // ===== Template linter (Stage 3) =====
    runLint() {
      const html = this.editor?.getHTML() || "";
      this.lintReport = lintTemplate(html, this.record?.sub_tests || []);
    },

    // ===== Normalize legacy variables (Stage 1e opt-in migration) =====
    // Rewrites {{sub_test.Pus Cells.value}} -> {{sub_test.pus_cells.value}}
    // so spaces in names never break print. Only touches the editor content.
    normalizeVariables() {
      let html = this.editor.getHTML();
      const subs = this.record?.sub_tests || [];
      let changed = 0;
      subs.forEach((s) => {
        if (!s?.name) return;
        const key = slugifyKey(s.name);
        if (key === s.name) return;
        const escaped = s.name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        ["value", "name", "comment"].forEach((f) => {
          const re = new RegExp(`{{\\s*sub_test\\.${escaped}\\.${f}\\s*}}`, "g");
          const before = html;
          html = html.replace(re, `{{sub_test.${key}.${f}}}`);
          if (before !== html) changed++;
        });
      });
      if (changed > 0) {
        this.editor.commands.setContent(html);
        this.alertSuccess && this.alertSuccess(`${changed} ${this.t("variables_normalized") || "variable(s) normalized"}`);
      } else {
        this.alertInfo && this.alertInfo(this.t("nothing_to_normalize") || "Nothing to normalize.");
      }
      this.runLint();
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
      this.record.content = {
        html: this.editor.getHTML(),
        text: this.editor.getText(),
      };

      this.Updatetests().then(() => {
        this.alertSuccess(this.t("alertSuccess"));
        this.clearObjectValues(this.record);
        this.record.selection_type_options = [""];
        this.record.result_comments = [""];
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

    addComment() {
      if (!Array.isArray(this.record.result_comments)) {
        this.record.result_comments = [];
      }
      this.record.result_comments.push("");
    },

    addSupTest() {
      if (!Array.isArray(this.record.sub_tests)) {
        try {
          this.record.sub_tests = JSON.parse(this.record.sub_tests);
          if (!Array.isArray(this.record.sub_tests)) {
            this.record.sub_tests = [];
          }
        } catch (e) {
          this.record.sub_tests = [];
        }
      }
      this.record.sub_tests.push({
        name: "",
        type: 1,
        value: null,
        sup_test_reference_options: [],
        option_colors: {},
        default_value: "",
      });
    },

    removeSupTest(index) {
      this.record.sub_tests.splice(index, 1);
    },

    // ---- Per-option colours (sup.option_colors: { "<option text>": "#rrggbb" }) ----
    optionColor(sup, opt) {
      return (sup?.option_colors && sup.option_colors[opt]) || null;
    },
    setOptionColor(sup, opt, color) {
      if (!sup.option_colors) sup.option_colors = {};
      if (color) sup.option_colors[opt] = color;
      else delete sup.option_colors[opt];
    },
    addReferenceOption(testIndex) {
      this.record.sub_tests[testIndex].sup_test_reference_options.push("");
    },

    // Default-option helpers — sub_test.default_value is a comma-separated
    // list of option values that should be pre-selected on update-result
    // when no value has been entered yet. Multi-select (type 4) supports
    // multiple defaults.
    _splitDefaults(sup) {
      return (sup?.default_value || "")
        .toString()
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);
    },
    isDefaultOption(sup, optionValue) {
      if (!optionValue) return false;
      return this._splitDefaults(sup).includes(optionValue);
    },
    toggleDefaultOption(sup, optionValue) {
      if (!optionValue) return;
      const current = this._splitDefaults(sup);
      const idx = current.indexOf(optionValue);
      if (idx === -1) current.push(optionValue);
      else current.splice(idx, 1);
      sup.default_value = current.join(", ");
    },

    copySubTestVariable(name, field = "value") {
      const variable = `{{sub_test.${name}.${field}}}`;
      const textarea = document.createElement("textarea");
      textarea.value = variable;
      textarea.style.position = "fixed";
      textarea.style.opacity = "0";
      document.body.appendChild(textarea);
      textarea.select();
      try {
        const successful = document.execCommand("copy");
        if (successful) {
          this.alertSuccess(this.t("copied") || "Copied!");
        }
      } catch (err) {
        console.error("Copy failed", err);
      }
      document.body.removeChild(textarea);
    },

    removeReferenceOption(testIndex, optIndex) {
      const sup = this.record.sub_tests[testIndex];
      const removed = sup.sup_test_reference_options[optIndex];
      sup.sup_test_reference_options.splice(optIndex, 1);
      // Drop the deleted option from default_value if it was marked default
      if (removed && sup.default_value) {
        sup.default_value = this._splitDefaults(sup)
          .filter((v) => v !== removed)
          .join(", ");
      }
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
      this.record.result_comments = [""];
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
    if (this._clickAway) document.removeEventListener("click", this._clickAway);
    if (this._escKey) document.removeEventListener("keydown", this._escKey);
    this.editor.destroy();
  },
};
</script>

<style scoped>
/* Force PrimeVue components to respect container width */
:deep(.p-dropdown),
:deep(.p-inputnumber),
:deep(.p-inputtext),
:deep(.p-multiselect) {
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
}

:deep(.p-inputnumber-input) {
  width: 100% !important;
  min-width: 0 !important;
}

:deep(.p-dropdown-label) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Sticky sidebar fix */
@media (min-width: 1280px) {
  .xl\:sticky {
    position: sticky;
  }
}

/* Editor styling */
:deep(.ProseMirror) {
  outline: none;
  min-height: 150px;
  font-size: 14px;
  line-height: 1.55;
}

:deep(.ProseMirror table) {
  border-collapse: collapse;
  width: 100%;
  margin: 0.5em 0;
}

:deep(.ProseMirror th),
:deep(.ProseMirror td) {
  border: 1px solid #e2e8f0;
  padding: 8px;
  vertical-align: top;
  position: relative;
}

/* Respect inline data-cell-padding from the per-cell control */
:deep(.ProseMirror td[data-cell-padding]),
:deep(.ProseMirror th[data-cell-padding]) {
  padding: attr(data-cell-padding);
}

:deep(.ProseMirror th) {
  background-color: #f8fafc;
  font-weight: 600;
}

/* Highlight the currently selected cell — helps users find the cursor */
:deep(.ProseMirror .selectedCell) {
  background-color: rgba(20, 184, 166, 0.1);
  box-shadow: inset 0 0 0 2px #14b8a6;
}

/* Lists */
:deep(.ProseMirror ul),
:deep(.ProseMirror ol) {
  padding-inline-start: 1.25em;
  margin: 0.5em 0;
}
:deep(.ProseMirror ul) { list-style: disc; }
:deep(.ProseMirror ol) { list-style: decimal; }
:deep(.ProseMirror li) { margin: 0.15em 0; }
:deep(.ProseMirror li p) { margin: 0; }

/* Blockquote */
:deep(.ProseMirror blockquote) {
  border-inline-start: 3px solid #14b8a6;
  padding-inline-start: 0.85em;
  color: #475569;
  font-style: italic;
  margin: 0.5em 0;
}

/* Horizontal rule */
:deep(.ProseMirror hr) {
  border: none;
  border-top: 2px dashed #e2e8f0;
  margin: 1em 0;
}

/* Page break marker — visible in the editor as a clear label, respected at print */
:deep(.ProseMirror .page-break) {
  display: block;
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  color: #0f766e;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 6px 0;
  margin: 14px 0;
  border-top: 2px dashed #14b8a6;
  border-bottom: 2px dashed #14b8a6;
  background: repeating-linear-gradient(135deg, rgba(20,184,166,0.05) 0 6px, transparent 6px 12px);
  user-select: none;
}

/* Placeholder (empty editor hint) */
:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  color: #94a3b8;
  pointer-events: none;
  height: 0;
}

/* Code blocks + inline code for medical formulas */
:deep(.ProseMirror code) {
  background: #f1f5f9;
  border-radius: 4px;
  padding: 0.1em 0.35em;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.9em;
}

/* Highlight unresolved {{ ... }} visually inside editor — optional hint */
:deep(.ProseMirror) :where(*) {
  /* no-op base rule to keep specificity manageable */
}

/* Fullscreen wrapper shadow for visual depth */
.fixed.inset-0 {
  box-shadow: 0 0 0 1px rgba(0,0,0,0.04);
}
</style>
