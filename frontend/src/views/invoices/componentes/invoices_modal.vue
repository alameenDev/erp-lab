<template>
  <!-- Page Mode: Modern SaaS Layout -->
  <div v-if="isPageMode" class="invoice-page-form bg-slate-50/60 min-h-screen" :style="lang == 'en' ? 'direction:ltr' : 'direction: rtl'">
    <form @submit.prevent="isEditMode ? update() : create()">

      <!-- Single-column layout — right sidebar flows below main content -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        <!-- Main sections -->
        <div class="space-y-6">

          <!-- ==================== PATIENT SELECTION CARD ==================== -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-indigo-50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-slate-800">{{ t("Patient_details") }}</h3>
                    <p class="text-sm text-slate-500">{{ t("search_pationt") }}</p>
                  </div>
                </div>
                <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                  {{ t("step") }} 1
                </span>
              </div>
            </div>
            <div class="p-6">
              <!-- Selected Patient Badge (when existing patient picked) -->
              <div v-if="responseData?.id" class="mb-5 flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shrink-0">
                  {{ responseData.name?.charAt(0)?.toUpperCase() || '?' }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-slate-800 truncate">{{ responseData.name }}</p>
                  <p class="text-xs text-slate-500">
                    <span v-if="responseData.phone">{{ responseData.phone }}</span>
                    <span v-if="responseData.code" class="ms-2">• {{ responseData.code }}</span>
                  </p>
                </div>
                <button type="button" @click="clearPatientSelection()" :aria-label="t('clear_patient') || 'إلغاء اختيار المريض'" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-red-200 min-w-[36px] min-h-[36px]">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Inline Personal Info (hidden when patient already linked: edit mode OR preselected via responseData) -->
              <div v-if="!isEditMode && !responseData?.id" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- Name (with search dropdown) -->
                <div class="md:col-span-2 lg:col-span-3">
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("name") }} <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </span>
                    <input
                      type="text"
                      v-model="patientStore.record.name"
                      @input="searchPatientByName(patientStore.record.name)"
                      @blur="onPatientNameBlur"
                      :placeholder="t('enter_patient_name') || 'أدخل اسم المريض...'"
                      required
                      class="w-full ps-12 pe-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]"
                    />
                    <!-- Search Dropdown -->
                    <div v-show="isPatientNameDropdownShow && patientStore.searchRecords?.length > 0" class="absolute z-30 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                      <div
                        v-for="p in patientStore.searchRecords"
                        :key="p.id"
                        @mousedown.prevent="selectExistingPatient(p)"
                        class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-primary-50 border-b border-slate-100 last:border-0 transition-colors"
                      >
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-xs shrink-0">
                          {{ p.name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-slate-800 truncate">{{ p.name }}</p>
                          <p class="text-xs text-slate-500">
                            <span v-if="p.phone">{{ p.phone }}</span>
                            <span v-if="p.age" class="ms-2">• {{ p.age }} {{ p.gender || '' }}</span>
                            <span v-if="p.code" class="ms-2">• {{ p.code }}</span>
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Title -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("title") }} <span class="text-red-500">*</span></label>
                  <select v-model="patientStore.record.title_id_fk" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px] appearance-none cursor-pointer">
                    <option :value="null">{{ t("select") }}...</option>
                    <option v-for="title in titlesStore.titles" :key="title.value" :value="title.value">{{ title.label }}</option>
                  </select>
                </div>

                <!-- Gender -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("gender") }} <span class="text-red-500">*</span></label>
                  <select v-model="patientStore.record.gender_type_id_fk" @change="changePatientTitle(patientStore.record.gender_type_id_fk)" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px] appearance-none cursor-pointer">
                    <option :value="null">{{ t("select") }}...</option>
                    <option v-for="g in patientGenders" :key="g.value" :value="g.value">{{ g.label }}</option>
                  </select>
                </div>

                <!-- Age -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("age") }} <span class="text-red-500">*</span></label>
                  <input type="number" v-model="patientStore.record.age" min="0" required :placeholder="t('enter_age') || 'أدخل العمر'" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" />
                </div>

                <!-- Age Unit -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("age_unit") }} <span class="text-red-500">*</span></label>
                  <select v-model="patientStore.record.age_unit_id_fk" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px] appearance-none cursor-pointer">
                    <option :value="null">{{ t("select") }}...</option>
                    <option v-for="u in patientStore.AgeUnits" :key="u.value" :value="u.value">{{ u.label }}</option>
                  </select>
                </div>

                <!-- DOB -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("dob") }}</label>
                  <input type="date" v-model="patientStore.record.dob" @click="$event.target.showPicker()" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" />
                </div>

                <!-- Nationality -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("nationality") }}</label>
                  <select v-model="patientStore.record.nationality_id_fk" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px] appearance-none cursor-pointer">
                    <option :value="null">{{ t("select") }}...</option>
                    <option v-for="n in nationalitiesStore.nationalities" :key="n.value" :value="n.value">{{ n.label }}</option>
                  </select>
                </div>

                <!-- Phone -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("phone_number") }}</label>
                  <div class="relative">
                    <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-600 font-semibold text-sm">+964</span>
                    <input type="tel" :value="patientStore.record.phone" @input="onPatientPhoneInput" placeholder="7XXXXXXXXX" maxlength="10" class="w-full ps-16 pe-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" dir="ltr" />
                  </div>
                </div>

                <!-- Email -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("email") }}</label>
                  <input type="email" v-model="patientStore.record.email" :placeholder="t('enter_email') || 'البريد الإلكتروني'" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" dir="ltr" />
                </div>

                <!-- Address -->
                <div class="md:col-span-2 lg:col-span-3">
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("address") }}</label>
                  <input type="text" v-model="patientStore.record.address" :placeholder="t('enter_address') || 'العنوان الكامل'" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" />
                </div>

                <!-- National ID -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("national_id_no") }}</label>
                  <input type="text" v-model="patientStore.record.national_id_no" :placeholder="t('enter_national_id') || 'رقم الهوية'" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" />
                </div>

                <!-- Passport -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("passport_no") }}</label>
                  <input type="text" v-model="patientStore.record.passport_no" :placeholder="t('enter_passport') || 'رقم الجواز'" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-4 focus:ring-primary-500/15 focus:border-primary-500 focus:bg-white outline-none transition-all min-h-[44px]" />
                </div>

                <!-- File -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("addfile") || "صورة المريض" }}</label>
                  <label class="flex items-center justify-center gap-3 w-full px-4 py-3 bg-slate-50 border-2 border-dashed border-slate-200 rounded-xl text-slate-500 hover:border-primary-400 hover:bg-primary-50 hover:text-primary-600 cursor-pointer transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-sm font-medium truncate">{{ patientStore.selectedFile?.name || t('choose_file') || 'اختر ملف...' }}</span>
                    <input type="file" @change="onPatientFileChange" accept=".png,.jpg,.jpeg" class="hidden" />
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- 3-col row: Tests / Cultures / Packages -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- ==================== TESTS & GROUPS SECTION ==================== -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 to-teal-50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-teal-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                  </div>
                  <h3 class="text-base font-semibold text-slate-800">{{ t("tests") }}</h3>
                </div>
                <span v-if="selectedTests?.length || selectedtestGroups?.length" class="px-2.5 py-1 bg-primary-100 text-primary-700 text-xs font-semibold rounded-full">
                  {{ (selectedTests?.length || 0) + (selectedtestGroups?.length || 0) }}
                </span>
              </div>
            </div>
            <div class="p-5 space-y-4">
              <AutoComplete
                v-model="selectedTest"
                :suggestions="tests"
                optionLabel="name"
                :minLength="0"
                @complete="onTestSearch"
                @item-select="onTestSelect"
                :placeholder="t('search_and_select_test') || 'Search and select test...'"
                class="w-full custom-dropdown"
                :delay="300"
                dropdown
                completeOnFocus
              >
                <template #option="slotProps">
                  <div class="flex items-center gap-2">
                    <span v-if="slotProps.option.type === 'group'" class="px-1.5 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded">{{ t("group") || "Group" }}</span>
                    <span>{{ slotProps.option.name }}</span>
                    <span v-if="slotProps.option.shortcut" class="text-xs text-slate-400">({{ slotProps.option.shortcut }})</span>
                  </div>
                </template>
              </AutoComplete>

              <!-- Selected Tests List -->
              <div v-if="selectedTests?.length" class="space-y-2">
                <div
                  v-for="(item, index) in selectedTests"
                  :key="index"
                  class="p-3 bg-slate-50 border border-slate-200 rounded-xl hover:border-primary-200 transition-all group space-y-2"
                >
                  <!-- Top row: # badge + name + price + delete -->
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center text-primary-600 font-semibold text-xs shrink-0">
                      {{ index + 1 }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="font-medium text-slate-800 text-sm truncate">{{ item.name }}</p>
                      <p class="text-xs text-slate-500">{{ t("price") }}: {{ resolveItemPrice(item) }}</p>
                    </div>
                    <button type="button" @click="removeSelection('test', index)" :aria-label="t('remove') || 'حذف'" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors shrink-0 cursor-pointer focus:outline-none focus:ring-4 focus:ring-red-200 min-w-[36px] min-h-[36px]">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  <!-- Bottom row: to_lab dropdown + sample received -->
                  <div class="flex flex-wrap items-center gap-2 ps-11">
                    <Dropdown v-model="item.to_lab" :options="fromLap" optionLabel="name" optionValue="user_id" :placeholder="t('to_lab')" class="flex-1 min-w-0 text-sm" />
                    <label class="flex items-center gap-1.5 px-2 py-1.5 bg-white border border-slate-200 rounded-lg cursor-pointer">
                      <Checkbox v-model="item.is_sample_received" binary />
                      <span class="text-xs text-slate-600 whitespace-nowrap">{{ t("Sample_received") }}</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Selected Test Groups -->
              <div v-if="selectedtestGroups?.length" class="space-y-2">
                <div v-for="(item, index) in selectedtestGroups" :key="'group-' + index" class="border border-blue-200 rounded-xl overflow-hidden">
                  <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-white group">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 font-semibold text-xs shrink-0">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="font-medium text-slate-800 text-sm truncate">
                        <span class="px-1.5 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded me-1.5">{{ t("group") }}</span>
                        {{ item.group_name || item.name }}
                      </p>
                      <p class="text-xs text-slate-500">
                        {{ t("price") }}: {{ resolveGroupPrice(item).toLocaleString() }}
                        • {{ item.tests?.length || 0 }} {{ t("tests") }}
                      </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <button type="button" @click="toggletests(index)" :aria-label="t('toggle_tests') || 'إظهار/إخفاء'" :aria-expanded="showTests[index] ? 'true' : 'false'" class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-blue-200 min-w-[36px] min-h-[36px]">
                        <svg :class="['w-4 h-4 transition-transform', showTests[index] ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                      <button type="button" @click="removeSelection('testGroup', index)" :aria-label="t('remove') || 'حذف'" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-red-200 min-w-[36px] min-h-[36px]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div v-show="showTests[index]" class="p-3 bg-slate-50 border-t border-blue-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                      <div v-for="test in item.tests" :key="`test-${test.id}`" class="flex items-center justify-between p-2 bg-white rounded-lg text-sm">
                        <span class="text-slate-700">{{ test.name }}</span>
                        <span class="text-slate-400 text-xs">{{ test.for_customer_price ?? test.price }}</span>
                      </div>
                      <div v-for="culture in item.culture" :key="`culture-${culture.id}`" class="flex items-center justify-between p-2 bg-purple-50 rounded-lg text-sm">
                        <span class="text-purple-700">{{ culture.name }}</span>
                        <span class="text-purple-400 text-xs">{{ culture.for_customer_price ?? culture.price }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Result Comments Section -->
              <div v-if="localResultComments.length > 0" class="p-4 bg-teal-50 border border-teal-200 rounded-xl">
                <h4 class="text-sm font-semibold text-teal-800 mb-3 flex items-center gap-2">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                  {{ t("Result_Comments") || "Result Comments" }}
                </h4>
                <div class="space-y-3">
                  <div v-for="(item, idx) in localResultComments" :key="idx">
                    <p class="text-xs font-medium text-teal-700 mb-1.5">{{ item.test_name }}</p>
                    <div class="flex flex-wrap gap-2">
                      <span
                        v-for="(comment, cIdx) in item.comments"
                        :key="cIdx"
                        class="px-3 py-1.5 bg-white text-sm text-slate-700 rounded-lg border border-teal-200"
                      >
                        {{ comment }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== CULTURES SECTION ==================== -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-purple-50 to-fuchsia-50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <h3 class="text-base font-semibold text-slate-800">{{ t("cultures") }}</h3>
                </div>
                <span v-if="selectedCultures?.length" class="px-2.5 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">{{ selectedCultures.length }}</span>
              </div>
            </div>
            <div class="p-5 space-y-4">
              <AutoComplete
                v-model="selectedCulture"
                :suggestions="filteredCultures"
                optionLabel="name"
                :minLength="0"
                @complete="onCultureSearch"
                @item-select="onCultureSelect"
                :placeholder="t('search_and_select_culture') || 'Search and select culture...'"
                class="w-full custom-dropdown"
                dropdown
                completeOnFocus
              />

              <div v-if="selectedCultures?.length" class="space-y-2">
                <div
                  v-for="(item, index) in selectedCultures"
                  :key="index"
                  class="flex items-center gap-3 p-3 bg-purple-50/50 border border-purple-200 rounded-xl hover:border-purple-300 transition-all group"
                >
                  <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600 font-semibold text-xs shrink-0">
                    {{ index + 1 }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-800 text-sm truncate">{{ item.name }}</p>
                    <p class="text-xs text-slate-500">
                      {{ t("price") }}: {{ resolveItemPrice(item) }}
                    </p>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <Dropdown v-model="item.to_lab" :options="fromLap" optionLabel="name" optionValue="user_id" :placeholder="t('to_lab')" class="w-28 text-sm" />
                    <div class="flex items-center gap-1.5 px-2 py-1.5 bg-white border border-slate-200 rounded-lg">
                      <Checkbox v-model="item.is_sample_received" binary />
                      <span class="text-xs text-slate-600">{{ t("Sample_received") }}</span>
                    </div>
                    <button type="button" @click="removeSelection('culture', index)" :aria-label="t('remove') || 'حذف'" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-red-200 min-w-[36px] min-h-[36px]">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== PACKAGES SECTION ==================== -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                  </div>
                  <h3 class="text-base font-semibold text-slate-800">{{ t("packages") }}</h3>
                </div>
                <span v-if="selectedPackages?.length" class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">{{ selectedPackages.length }}</span>
              </div>
            </div>
            <div class="p-5 space-y-4">
              <AutoComplete
                v-model="selectedPackage"
                :suggestions="filteredPackages"
                optionLabel="name"
                :minLength="0"
                @complete="onPackageSearch"
                @item-select="onPackageSelect"
                :placeholder="t('search_and_select_package') || 'Search and select package...'"
                class="w-full custom-dropdown"
                dropdown
                completeOnFocus
              />

              <div v-if="selectedPackages?.length" class="space-y-2">
                <div v-for="(item, index) in selectedPackages" :key="index" class="border border-amber-200 rounded-xl overflow-hidden">
                  <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-amber-50 to-white group">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600 font-semibold text-xs shrink-0">
                      {{ index + 1 }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="font-medium text-slate-800 text-sm truncate">{{ item.name }}</p>
                      <p class="text-xs text-slate-500">
                        {{ t("price") }}: {{ resolveItemPrice(item) }}
                        • {{ item.tests?.length || 0 }} {{ t("tests") }} • {{ item.cultures?.length || 0 }} {{ t("cultures") }}
                      </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <button type="button" @click="toggletests(index)" :aria-label="t('toggle_tests') || 'إظهار/إخفاء'" :aria-expanded="showTests[index] ? 'true' : 'false'" class="p-2 text-amber-600 hover:bg-amber-100 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-amber-200 min-w-[36px] min-h-[36px]">
                        <svg :class="['w-4 h-4 transition-transform', showTests[index] ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                      <button type="button" @click="removeSelection('package', index)" :aria-label="t('remove') || 'حذف'" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-red-200 min-w-[36px] min-h-[36px]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div v-show="showTests[index]" class="p-3 bg-slate-50 border-t border-amber-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                      <div v-for="test in item.tests" :key="`test-${test.id}`" class="flex items-center justify-between p-2 bg-white rounded-lg text-sm">
                        <span class="text-slate-700">{{ test.name }}</span>
                        <span class="text-slate-400 text-xs">{{ test.for_customer_price ?? test.price }}</span>
                      </div>
                      <div v-for="culture in item.cultures" :key="`culture-${culture.id}`" class="flex items-center justify-between p-2 bg-purple-50 rounded-lg text-sm">
                        <span class="text-purple-700">{{ culture.name }}</span>
                        <span class="text-purple-400 text-xs">{{ culture.for_customer_price ?? culture.price }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          </div>
          <!-- End 3-col row -->

        </div>

        <!-- Bottom block (was sticky right sidebar) — Invoice Details / Payment / Summary stacked at end of page -->
        <div class="w-full">
          <div class="space-y-6">

            <!-- ==================== INVOICE DETAILS CARD (full width) ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
              <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <h3 class="font-semibold text-slate-800">{{ t("inovice_datails") }}</h3>
                </div>
              </div>
              <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("from_lab") }}</label>
                  <Dropdown showClear class="w-full" v-model="record.from_lab_id_fk" :options="fromLap" @change="price_list(record.from_lab_id_fk); autofillPhoneFromReferral()" optionValue="user_id" optionLabel="name" :placeholder="t('select')" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("sample_collector") }}</label>
                  <Dropdown class="w-full" showClear v-model="record.sample_collector_id_fk" :options="collectorsList" @change="getTotal()" optionLabel="name" optionValue="id" :placeholder="t('select')" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("contract") }}</label>
                  <Dropdown class="w-full" showClear v-model="record.contract_id_fk" @change="cheackMaximunInvoice()" :options="contractsList" optionLabel="name" optionValue="id" :placeholder="t('select')" />
                  <Message severity="warn" v-if="ErrorMessage" class="mt-2 text-sm">{{ ErrorMessage }}</Message>
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("referrals") }}</label>
                  <Dropdown class="w-full" showClear v-model="record.referral_id_fk" @change="applyReferralPriceList(); getTotal(); autofillPhoneFromReferral()" :options="theDoctorReferal" optionLabel="name" optionValue="user_id" :placeholder="t('select')" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ t("Registration_date") }}</label>
                  <InputText class="w-full" type="date" v-model="record.registration_date" @click="$event.target.showPicker()" />
                </div>
              </div>
            </div>

            <!-- ==================== NOTES CARD (full width) ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden h-full flex flex-col">
              <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-gray-50 shrink-0">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-500 to-gray-600 flex items-center justify-center shadow-lg shadow-slate-500/25">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </div>
                  <h3 class="text-base font-semibold text-slate-800">{{ t("notes") }}</h3>
                </div>
              </div>
              <div class="p-5 flex-1 flex">
                <textarea
                  v-model="record.notes"
                  :placeholder="t('enter_notes')"
                  ref="notesTextarea"
                  class="w-full h-full min-h-[180px] px-4 py-3 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none resize-none bg-slate-50 focus:bg-white transition-colors"
                ></textarea>
              </div>
            </div>
            <!-- End full-width Invoice Details + Notes -->

            <!-- ==================== PAYMENT + INVOICE SUMMARY (single card) ==================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
              <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-primary-50 via-teal-50 to-emerald-50">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-primary-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <h3 class="font-semibold text-slate-800">{{ t("Invoice_summary") }}</h3>
                </div>
              </div>

              <div class="p-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                  <!-- LEFT: Payment methods -->
                  <div class="space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                      <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                          <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                          </svg>
                        </span>
                        <span class="text-sm font-semibold text-slate-700">{{ t("paymentMethod") }}</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <button type="button" @click="markFullyPaid()" :aria-label="t('mark_fully_paid') || 'تم دفع المبلغ بالكامل'" :title="t('mark_fully_paid') || 'تم دفع المبلغ بالكامل'" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-white bg-gradient-to-br from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-green-300 shadow-sm shadow-green-500/20 min-h-[36px]">
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          </svg>
                          <span>{{ t("mark_fully_paid") || "تم دفع المبلغ بالكامل" }}</span>
                        </button>
                        <button type="button" @click="addRow()" :aria-label="t('add_payment_method') || 'إضافة طريقة دفع'" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-green-700 bg-white border border-green-200 hover:bg-green-50 hover:border-green-300 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-green-200 min-h-[36px]">
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                          </svg>
                          <span>{{ t("add") }}</span>
                        </button>
                      </div>
                    </div>

                    <div v-for="(payment, index) in payment_details" :key="index" class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl">
                      <div class="flex-1 min-w-0">
                        <Dropdown showClear v-model="payment.payment_method_id_fk" :options="paymentMethods" optionLabel="name" optionValue="id" :placeholder="t('method') || 'Method'" class="w-full text-sm" />
                      </div>
                      <div class="w-24 shrink-0">
                        <InputNumber v-model.number="payment.amount" @input="checkpaid(index)" :placeholder="t('amount')" class="w-full text-sm" />
                      </div>
                      <button type="button" @click="removeRow(index)" :aria-label="t('remove') || 'حذف'" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors shrink-0 cursor-pointer focus:outline-none focus:ring-4 focus:ring-red-200 min-w-[36px] min-h-[36px]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>

                    <p v-if="!payment_details || !payment_details.length" class="text-xs text-slate-400 text-center py-4 border border-dashed border-slate-200 rounded-xl">
                      {{ t("no_payment_methods") || "لا توجد طرق دفع — اضغط اضافة" }}
                    </p>
                  </div>

                  <!-- RIGHT: Summary detail lines + discount -->
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between py-1.5">
                      <span class="text-sm text-slate-600">{{ t("items_count") || "عدد العناصر" }}</span>
                      <span class="text-sm font-semibold text-slate-800 tabular-nums">{{ totalItemsCount }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5">
                      <span class="text-sm text-slate-600">{{ t("Subtotal") }}</span>
                      <span class="text-sm font-medium text-slate-800 tabular-nums">{{ record.sub_total || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5">
                      <span class="text-sm text-slate-600">{{ t("Sample_collection_fees") }}</span>
                      <span class="text-sm font-medium text-slate-800 tabular-nums">{{ Sample_collection_fees || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5">
                      <span class="text-sm text-slate-600">{{ t("Contract_payment") }} ({{ Contract_discount_percentage }}%)</span>
                      <span class="text-sm font-medium text-slate-800 tabular-nums">{{ payment_percent || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5">
                      <span class="text-sm text-slate-600">{{ t("Referral_commission") }}</span>
                      <span class="text-sm font-medium text-slate-800 tabular-nums">{{ commission || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5" v-if="discountValue">
                      <span class="text-sm text-slate-600">{{ t("it_discount") }}</span>
                      <span class="text-sm font-medium text-rose-600 tabular-nums">- {{ discountValue || 0 }}</span>
                    </div>

                    <!-- Promo code -->
                    <div class="pt-3 mt-1 border-t border-slate-100">
                      <label class="block text-sm font-medium text-slate-700 mb-2">بروموكود</label>
                      <div v-if="!promoCodeApplied" class="flex items-center gap-2">
                        <input
                          v-model="promoCodeInput"
                          type="text"
                          placeholder="أدخل الكود"
                          class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm"
                          @keyup.enter="previewPromoCode"
                        />
                        <button
                          type="button"
                          @click="previewPromoCode"
                          :disabled="promoCodeApplying || !promoCodeInput"
                          class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-bold px-4 py-2 rounded-lg"
                        >
                          {{ promoCodeApplying ? "..." : "تطبيق" }}
                        </button>
                      </div>
                      <div v-else class="flex items-center justify-between bg-emerald-50 rounded-lg px-3 py-2">
                        <span class="text-sm font-bold text-emerald-700">{{ promoCodeApplied }}</span>
                        <button type="button" @click="clearPromoCode" class="text-xs text-red-500 font-bold">إزالة</button>
                      </div>
                      <div v-if="promoCodeMessage" class="text-xs text-emerald-600 mt-1">{{ promoCodeMessage }}</div>
                      <div v-if="promoCodeError" class="text-xs text-red-500 mt-1">{{ promoCodeError }}</div>
                    </div>

                    <!-- Discount inputs -->
                    <div class="pt-3 mt-1 border-t border-slate-100">
                      <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("it_discount") }}</label>
                      <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                          <InputNumber v-model.number="discountPercentage" @input="updateDiscountFromPercentage()" class="w-full text-sm" />
                          <span class="absolute end-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">%</span>
                        </div>
                        <span class="text-slate-400">=</span>
                        <div class="flex-1">
                          <InputNumber v-model.number="discountValue" @input="updateDiscountFromValue()" class="w-full text-sm" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Totals (full width KPI tiles) -->
                <div class="mt-6 pt-5 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div class="flex flex-col gap-1 p-4 bg-gradient-to-br from-primary-50 to-teal-50 rounded-xl border border-primary-100">
                    <span class="text-xs font-medium text-primary-700">{{ t("Total") }}</span>
                    <span class="text-2xl font-bold text-primary-700 tabular-nums">{{ record.total || 0 }}</span>
                  </div>
                  <div class="flex flex-col gap-1 p-4 bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-100">
                    <span class="text-xs font-medium text-green-700">{{ t("paid") }}</span>
                    <span class="text-2xl font-bold text-green-600 tabular-nums">{{ the_paid || 0 }}</span>
                    <span class="text-[11px] text-green-600/70">{{ paidPercent }}%</span>
                  </div>
                  <div class="flex flex-col gap-1 p-4 bg-gradient-to-br from-red-50 to-rose-50 rounded-xl border border-red-100">
                    <span class="text-xs font-medium text-red-700">{{ t("Due") }}</span>
                    <span class="text-2xl font-bold text-red-600 tabular-nums">{{ due || 0 }}</span>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-5 flex flex-col sm:flex-row gap-3">
                  <button
                    type="submit"
                    class="flex-1 px-6 py-3.5 text-sm font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2 min-h-[48px] cursor-pointer focus:outline-none focus:ring-4 focus:ring-primary-500/30"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ record.id ? t("save") : t("create_invoice") || t("add") }}
                  </button>
                  <button
                    type="button"
                    @click="close()"
                    class="sm:w-40 px-6 py-3 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors min-h-[44px] cursor-pointer focus:outline-none focus:ring-4 focus:ring-slate-200"
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
  </div>

  <!-- Dialog Mode: PrimeVue Dialog wrapper -->
  <Dialog
    v-else
    :visible="dialog"
    modal
    :header="record?.id ? t('update') : t('add')"
    style="width: 90rem"
    :style="lang == 'en' ? 'direction:ltr' : 'direction: rtl'"
    @update:visible="dialog = $event"
  >
    <form @submit.prevent="isEditMode ? update() : create()" class="space-y-6">
      <p class="text-slate-500 text-center py-8">{{ t("dialog_mode_content") || "Please use the full page form for creating invoices" }}</p>
      <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200">
        <button type="button" @click="close()" class="px-6 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
          {{ t("close") }}
        </button>
      </div>
    </form>
  </Dialog>

  <printInvoiceModal></printInvoiceModal>
</template>

<script>
import { mapActions, mapWritableState, mapGetters } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { uselabsStore } from "@/store/modules/labs";
import { LoaderStore } from "@/store/modules/loader";
import { useContractsStore } from "@/store/modules/contract";
import { useReferralsStore } from "@/store/modules/referrals";
import { useculturesStore } from "@/store/modules/cultures";
import { usetestsStore } from "@/store/modules/tests";
import { usePackagesStore } from "@/store/modules/packages";
import { usepaymentMethodstore } from "@/store/modules/payment-methods";
import { useresultStatusStore } from "@/store/modules/result-status";
import { usePatientsStore } from "@/store/modules/patients";
import { showAlertWithConfirm } from "@/utils/helper";
import printInvoiceModal from "./printInvoice_modal.vue";
import { $http } from "@/plugins/axios";
import Mixins from "@/utils/mixins";
import Dialog from "primevue/dialog";
import { useTitlesStore } from "@/store/modules/titles";
import { useNationalitiesStore } from "@/store/modules/nationalities";

export default {
  mixins: [Mixins],
  props: {
    isPageMode: {
      type: Boolean,
      default: false
    }
  },
  emits: ['saved', 'cancelled'],
  data() {
    return {
      errorMessage: "",
      selectedTest: null,
      selectedCulture: null,
      selectedPackage: null,
      filteredCultures: [],
      filteredPackages: [],
      perceInput: false,
      showTests: [],
      showCultures: [],
      valueInput: false,
      promoCodeInput: "",
      promoCodeApplying: false,
      promoCodeMessage: "",
      promoCodeError: "",
      promoCodeApplied: null, // the code string currently reflected in discount fields, sent on save
      paid: 0,
      index: 0,
      Contract_payment: 0,
      paymentPercent: 0,
      price_list_id: null,
      selectedPatient: null,
      patientSearchLoading: false,
      isPatientNameDropdownShow: false,
      patientNameBlurTimer: null,
      localQuestions: [],
      localResultComments: [],
      questionsLoading: false,
    };
  },
  components: {
    printInvoiceModal,
    Dialog,
  },
  computed: {
    ...mapWritableState(LoaderStore, ["hideLoading"]),
    ...mapWritableState(useresultStatusStore, ["resultStatus"]),
    ...mapWritableState(useinvoicesStore, [
      "record",
      "dialog",
      "discountPercentage",
      "discountValue",
      "testQuesions",
      "tests_ids",
      "payment_details",
      "selectedContract",
      "selectedTests",
      "selectedPackages",
      "selectedtestGroups",
      "selectedCultures",
      "selectedreferal",
      "selectedCollector",
      "printInvoiceDialog",
      "printRecord",
      "searchRecords",
    ]),
    ...mapWritableState(usetestsStore, ["tests"]),
    ...mapGetters(uselabsStore, ["lab"]),
    ...mapWritableState(uselabsStore, ["collectorsList"]),
    ...mapWritableState(useReferralsStore, ["records"]),
    ...mapWritableState(useContractsStore, ["contracts", "contractsList"]),
    ...mapWritableState(useculturesStore, ["cultures"]),
    ...mapWritableState(usePackagesStore, ["packagesList"]),
    ...mapWritableState(usepaymentMethodstore, ["paymentMethods"]),
    ...mapWritableState(usePatientsStore, ["responseData"]),
    patientStore() {
      return usePatientsStore();
    },
    isEditMode() {
      return !!(this.$route?.params?.id) || !!this.record?.id;
    },
    titlesStore() {
      return useTitlesStore();
    },
    nationalitiesStore() {
      return useNationalitiesStore();
    },
    patientGenders() {
      return (this.patientStore.genders || []).filter((g) => g.label !== "Both");
    },
    Contract_discount_percentage() {
      return this.contractsList?.find((c) => c.id === this.record.contract_id_fk)?.discount_percentage ?? 0;
    },
    items() {
      return this.tests;
    },
    fromLap() {
      return this.records.filter((item) => item.role === "Lab" || item.role === "Branch Lab");
    },
    theDoctorReferal() {
      return this.records.filter((item) => item.role === "Doctor");
    },
    payment_percent() {
      return this.contractsList?.find((c) => c.id === this.record.contract_id_fk)?.payment_percent ?? 0;
    },
    commission() {
      return this.records?.find((c) => c.user_id === this.record.referral_id_fk)?.commission ?? 0;
    },
    maximumInvoiceAmount() {
      return this.contractsList?.find((c) => c.id === this.record.contract_id_fk)?.maximum_payment_per_invoice ?? 0;
    },
    price_list_id_fk() {
      return this.price_list_id;
    },
    Sample_collection_fees() {
      return this.collectorsList?.find((c) => c.id === this.record.sample_collector_id_fk)?.commission ?? 0;
    },
    discountedTotal() {
      const t = this.baseTotal + this.payment_percent + this.commission + this.Sample_collection_fees - this.discountValue;
      return t > 0 ? t : 0;
    },
    baseTotal() {
      const getPrice = (item) => this.resolveItemPrice(item);

      const selectedTests = this.selectedTests?.map((item) => ({ ...item, thePrice: getPrice(item) }));
      const selectedCultures = this.selectedCultures?.map((item) => ({ ...item, thePrice: getPrice(item) }));
      const selectedPackages = this.selectedPackages?.map((item) => ({ ...item, thePrice: getPrice(item) }));
      const selectedtestGroups = this.selectedtestGroups?.map((item) => {
        // Sum children prices live so the displayed group price matches the
        // visible child rows (avoids drift if group's stored price is stale).
        return { ...item, thePrice: this.resolveGroupPrice(item) };
      });

      const testsTotal = selectedTests?.reduce((acc, test) => acc + test.thePrice, 0) || 0;
      const culturesTotal = selectedCultures?.reduce((acc, culture) => acc + culture.thePrice, 0) || 0;
      const packagesTotal = selectedPackages?.reduce((acc, p) => acc + p.thePrice, 0) || 0;
      const groupTotal = selectedtestGroups?.reduce((acc, p) => acc + p.thePrice, 0) || 0;

      return testsTotal + culturesTotal + packagesTotal + groupTotal;
    },
    due() {
      const total = Number(this.record.total) || 0;
      const paid = Number(this.the_paid) || 0;
      const d = total - paid;
      return d > 0 ? d : 0;
    },
    the_paid() {
      return this.payment_details.reduce((acc, payment) => acc + (Number(payment.amount) || 0), 0);
    },
    totalItemsCount() {
      return (
        (this.selectedTests?.length || 0) +
        (this.selectedCultures?.length || 0) +
        (this.selectedPackages?.length || 0) +
        (this.selectedtestGroups?.length || 0)
      );
    },
    paidPercent() {
      const total = Number(this.record.total) || 0;
      if (total <= 0) return 0;
      return Math.round(((Number(this.the_paid) || 0) / total) * 100);
    },
    ErrorMessage() {
      return this.errorMessage;
    },
  },
  created() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");
    this.record.registration_date = `${year}-${month}-${day}`;
  },
  mounted() {
    this.Getlabs();
    this.collectors();
    this.GetContracts();
    this.GetReferrals();
    this.fetchTests('');
    this.Getcultures();
    this.Getpackages();
    this.GetpaymentMethods();
    this.GetresultStatus();
    this.patientStore.GetGenders();
    this.patientStore.GetAgeUnits();
    this.titlesStore.GetTitles();
    this.nationalitiesStore.GetNationalities();
    this.$nextTick(() => this.autoGrowNotes());
  },
  methods: {
    ...mapActions(usepaymentMethodstore, ["GetpaymentMethods"]),
    ...mapActions(useresultStatusStore, ["GetresultStatus"]),
    ...mapActions(usetestsStore, ["GetTests"]),
    ...mapActions(useinvoicesStore, ["Addinvoices", "Updateinvoices", "questions", "searchByname"]),
    ...mapActions(uselabsStore, ["Getlabs", "collectors"]),
    ...mapActions(useReferralsStore, { GetReferrals: "GetRecords" }),
    ...mapActions(useContractsStore, { GetContracts: "GetRecords" }),
    ...mapActions(usePackagesStore, ["Getpackages"]),
    ...mapActions(useculturesStore, ["Getcultures"]),

    cheackMaximunInvoice() {
      this.getTotal();
      if (this.record.total > this.maximumInvoiceAmount) {
        this.errorMessage = this.t("MUximunInvoiceError");
        this.record.contract_id_fk = null;
      } else {
        this.errorMessage = "";
      }
    },
    searchPatientByName(v) {
      if (v && v.trim().length > 0) {
        this.patientStore.searchByname(v);
        this.isPatientNameDropdownShow = true;
      } else {
        this.isPatientNameDropdownShow = false;
        this.patientStore.searchRecords = [];
      }
    },
    onPatientNameBlur() {
      clearTimeout(this.patientNameBlurTimer);
      this.patientNameBlurTimer = setTimeout(() => {
        this.isPatientNameDropdownShow = false;
      }, 200);
    },
    selectExistingPatient(p) {
      this.isPatientNameDropdownShow = false;
      this.responseData = p;
      // strip +964 prefix for display
      const ph = this.normalizeLocalPhone(p.phone);
      Object.assign(this.patientStore.record, p, { phone: ph });
    },
    clearPatientSelection() {
      this.responseData = null;
      this.patientStore.searchRecords = [];
      this.patientStore.selectedFile = "";
      // clear form
      Object.keys(this.patientStore.record).forEach((key) => {
        if (typeof this.patientStore.record[key] === "string") this.patientStore.record[key] = "";
        else if (typeof this.patientStore.record[key] === "number") this.patientStore.record[key] = 0;
        else this.patientStore.record[key] = null;
      });
      this.patientStore.record.id = "";
    },
    changePatientTitle(value) {
      if (value == 1) this.patientStore.record.title_id_fk = 1;
      else if (value == 2) this.patientStore.record.title_id_fk = 2;
    },
    // Single source of truth for turning any stored/typed form of an Iraqi number
    // (+964…, 00964…, 964…, 07…) into the local 10-digit form the input expects.
    normalizeLocalPhone(raw) {
      let digits = String(raw ?? "").replace(/[^0-9]/g, "");
      if (digits.startsWith("00964")) digits = digits.slice(5);
      else if (digits.startsWith("964")) digits = digits.slice(3);
      if (digits.startsWith("0")) digits = digits.slice(1);
      return digits;
    },
    onPatientPhoneInput(e) {
      this.patientStore.record.phone = this.normalizeLocalPhone(e.target.value);
    },
    // Auto-fill the patient phone from the chosen "from lab" (falling back to the
    // chosen referral) — both come from the referrals list, which carries the
    // linked user's phone_number. Deliberately NON-destructive: it only fills when
    // no phone has been entered and no existing patient is selected, so a real
    // patient's own number is never replaced by the lab's.
    autofillPhoneFromReferral() {
      if (this.responseData?.id) return;
      if (this.patientStore.record.phone) return;

      const lab = this.fromLap?.find((c) => c.user_id === this.record.from_lab_id_fk);
      const ref = this.records?.find((c) => c.user_id === this.record.referral_id_fk);
      const phone = this.normalizeLocalPhone(lab?.phone_number || ref?.phone_number || "");
      if (phone) this.patientStore.record.phone = phone;
    },
    onPatientFileChange(e) {
      this.patientStore.selectedFile = e.target.files[0];
    },
    async ensurePatientExists() {
      // Existing patient already selected
      if (this.responseData?.id) return true;
      const pr = this.patientStore.record;
      if (!pr.name?.trim()) {
        this.alertError(this.t("errorMessage") || "اسم المريض مطلوب");
        return false;
      }
      // Defaults required by backend
      if (!pr.title_id_fk) {
        pr.title_id_fk = pr.gender_type_id_fk == 2 ? 2 : 1;
      }
      if (!pr.age_unit_id_fk) pr.age_unit_id_fk = 1;
      if (!pr.gender_type_id_fk) {
        this.alertError(this.t("errorMessage") || "الجنس مطلوب");
        return false;
      }
      if (pr.age === "" || pr.age === null || pr.age === undefined) {
        this.alertError(this.t("errorMessage") || "العمر مطلوب");
        return false;
      }
      pr.gender_id_fk = pr.gender_type_id_fk;
      pr.phone_number = pr.phone ? `+964${pr.phone}` : "";
      try {
        await this.patientStore.AddPatient();
        return !!this.responseData?.id;
      } catch (e) {
        this.alertError(e?.response?.data?.message || this.t("error") || "فشل إنشاء المريض");
        return false;
      }
    },
    async fetchTests(query) {
      try {
        const { data } = await $http.get('/tests', { params: { search: query, include_groups: 1 } });
        this.tests = Array.isArray(data) ? data : data.data || [];
      } catch (error) {
        console.error('Error fetching tests:', error);
      }
    },
    onFilter(event) {
      const query = event.value;
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.fetchTests(query);
      }, 300);
    },
    onTestSearch(event) {
      const query = event.query || '';
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.fetchTests(query);
      }, 300);
    },
    onTestSelect(event) {
      if (event.value) {
        this.addSelection(event.value.type === 'group' ? 'testGroup' : 'test', event.value);
        this.$nextTick(() => { this.selectedTest = null; });
      }
    },
    onCultureSearch(event) {
      const query = (event.query || '').toLowerCase();
      this.filteredCultures = !query
        ? [...this.cultures]
        : this.cultures.filter(c => c.name?.toLowerCase().includes(query));
    },
    onCultureSelect(event) {
      if (event.value) {
        this.addSelection('culture', event.value);
        this.$nextTick(() => { this.selectedCulture = null; });
      }
    },
    onPackageSearch(event) {
      const query = (event.query || '').toLowerCase();
      this.filteredPackages = !query
        ? [...this.packagesList]
        : this.packagesList.filter(p => p.name?.toLowerCase().includes(query) || p.shortcut?.toLowerCase().includes(query));
    },
    onPackageSelect(event) {
      if (event.value) {
        this.addSelection('package', event.value);
        this.$nextTick(() => { this.selectedPackage = null; });
      }
    },
    toggletests(index) {
      this.showTests[index] = !this.showTests[index];
    },
    toggleCultures(index) {
      this.showCultures[index] = !this.showCultures[index];
    },
    removeTest(packageIndex, testId) {
      const packageItem = this.selectedPackages[packageIndex];
      packageItem.tests = packageItem.tests.filter((test) => test.id !== testId);
    },
    removeCulture(packageIndex, cultureId) {
      const packageItem = this.selectedPackages[packageIndex];
      packageItem.cultures = packageItem.cultures.filter((culture) => culture.id !== cultureId);
    },
    checkpaid(index) {
      this.index = index;
    },
    price_list(v) {
      const lab = this.fromLap?.find((c) => c.user_id === v);
      // Prefer the partner-lab's own price-list (from labs.price_list_id_fk),
      // fall back to the referral relationship's price_list_id_fk.
      this.price_list_id = lab?.lab_price_list_id_fk ?? lab?.price_list_id_fk ?? null;
      if (!this.price_list_id) this.applyReferralPriceList();
      this.applyFromLabDiscount();
    },
    // Recompute the active price-list when the referral changes. Precedence:
    //   1. From-lab partner's price-list (B2B contract)
    //   2. Referral's price-list (doctor referral)
    //   3. None — falls back to retail (for_customer_price)
    applyReferralPriceList() {
      const ref = this.records?.find((c) => c.user_id === this.record.referral_id_fk);
      const lab = this.fromLap?.find((c) => c.user_id === this.record.from_lab_id_fk);
      const fromLabPL = lab?.lab_price_list_id_fk ?? lab?.price_list_id_fk;
      this.price_list_id = fromLabPL ?? ref?.price_list_id_fk ?? null;
      this.applyFromLabDiscount();
    },
    // Auto-apply a percent discount to the invoice based on the picked from_lab
    // or referral. Looks at three places, in order:
    //   1. labs.discount_percentage on the partner-lab (set in /labs)
    //   2. price_lists.discount on the lab's price-list (set in /priceList)
    //   3. price_lists.discount on the referral's price-list (e.g. doctor referral)
    // Acts like the user typed the % in the discount field.
    applyFromLabDiscount() {
      const lab = this.fromLap?.find((c) => c.user_id === this.record.from_lab_id_fk);
      const ref = this.records?.find((c) => c.user_id === this.record.referral_id_fk);
      const pct = Number(
        lab?.lab_discount_percentage
          ?? lab?.lab_price_list_discount
          ?? lab?.price_list_discount
          ?? ref?.lab_discount_percentage
          ?? ref?.lab_price_list_discount
          ?? ref?.price_list_discount
          ?? 0
      ) || 0;
      this._updatingDiscount = true;
      this.discountPercentage = pct;
      this.discountValue = (this.baseTotal * pct) / 100;
      this.perceInput = pct > 0;
      this.valueInput = false;
      this.recalcTotal();
      this.$nextTick(() => { this._updatingDiscount = false; });
    },
    addRecord() {
      this.clearObjectValues(this.patientRecord);
      this.thepatientDialog = true;
    },
    updateDiscountFromPercentage() {
      this.perceInput = true;
      this.valueInput = false;
      this._updatingDiscount = true;
      this.discountValue = (this.baseTotal * this.discountPercentage) / 100;
      this.recalcTotal();
      this.$nextTick(() => { this._updatingDiscount = false; });
    },
    updateDiscountFromValue() {
      this.valueInput = true;
      this.perceInput = false;
      this._updatingDiscount = true;
      this.discountPercentage = this.baseTotal > 0 ? (this.discountValue / this.baseTotal) * 100 : 0;
      this.recalcTotal();
      this.$nextTick(() => { this._updatingDiscount = false; });
    },
    async previewPromoCode() {
      if (!this.promoCodeInput) return;
      this.promoCodeApplying = true;
      this.promoCodeMessage = "";
      this.promoCodeError = "";
      try {
        const { data } = await $http.post("/promo-codes/preview", {
          code: this.promoCodeInput,
          invoice_amount: Math.round(Number(this.baseTotal) || 0),
          patient_id_fk: this.record.patient_id_fk || null,
        });
        this._updatingDiscount = true;
        if (data.discount_type === "percentage") {
          this.discountPercentage = Number(data.discount_value);
          this.discountValue = (this.baseTotal * this.discountPercentage) / 100;
          this.perceInput = true;
          this.valueInput = false;
        } else {
          this.discountValue = Number(data.discount_amount);
          this.discountPercentage = this.baseTotal > 0 ? (this.discountValue / this.baseTotal) * 100 : 0;
          this.valueInput = true;
          this.perceInput = false;
        }
        this.recalcTotal();
        this.$nextTick(() => { this._updatingDiscount = false; });
        this.promoCodeApplied = this.promoCodeInput.trim();
        this.promoCodeMessage = `تم تطبيق الخصم (-${data.discount_amount})`;
      } catch (e) {
        this.promoCodeApplied = null;
        this.promoCodeError = e?.response?.data?.message || "تعذر تطبيق البروموكود";
      } finally {
        this.promoCodeApplying = false;
      }
    },
    clearPromoCode() {
      this.promoCodeInput = "";
      this.promoCodeApplied = null;
      this.promoCodeMessage = "";
      this.promoCodeError = "";
      this.discountPercentage = 0;
      this.discountValue = 0;
      this.perceInput = false;
      this.valueInput = false;
      this.recalcTotal();
    },
    // Persists the previewed promo code against a saved invoice (records
    // the redemption + re-syncs the authoritative discount/total from the
    // backend). Called right after the invoice itself is created/updated.
    async commitPromoCode(invoiceId) {
      if (!invoiceId) return;
      if (this.promoCodeApplied) {
        try {
          await $http.post(`/invoices/${invoiceId}/apply-promo-code`, { code: this.promoCodeApplied });
        } catch (e) {
          this.alertSuccess(e?.response?.data?.message || "تعذر تسجيل استخدام البروموكود");
        }
      } else if (this.record.promo_code_id_fk) {
        // promo was cleared while editing an invoice that already had one
        try {
          await $http.delete(`/invoices/${invoiceId}/promo-code`);
        } catch (e) {
          // ignore
        }
      }
    },
    recalcTotal() {
      const total = this.baseTotal + this.payment_percent + this.commission + this.Sample_collection_fees - this.discountValue;
      this.record.total = total > 0 ? Math.round(total) : 0;
    },
    getTotal() {
      this.recalcTotal();
    },
    async show_Questions() {
      const ids = this.selectedTests.map((test) => test.id ?? test.test_id_fk);
      if (ids.length > 0) {
        this.questionsLoading = true;
        try {
          const response = await $http.post(`/tests/questions`, { tests_ids: ids });
          this.localQuestions = response.data?.questions || [];
          this.localResultComments = response.data?.result_comments || [];
          this.testQuesions = this.localQuestions;
          this.tests_ids = ids;
        } catch (error) {
          this.localQuestions = [];
          this.localResultComments = [];
        } finally {
          this.questionsLoading = false;
        }
      }
    },
    // Single source of truth for "what does this item cost on this invoice?"
    // Precedence:
    //  1. The selected price-list override (if any) — for B2B / contract invoices
    //  2. for_customer_price — the lab's customer-facing retail price
    //  3. price (B2B / wholesale) — last fallback
    //  4. original_price (legacy field)
    resolveItemPrice(item) {
      if (!item) return 0;
      if (this.price_list_id_fk && Array.isArray(item.prices)) {
        const pl = item.prices.find((p) => p.price_list_id === this.price_list_id_fk);
        const v = pl?.price_for_customer;
        if (v != null && v !== "") return Number(v) || 0;
      }
      if (item.for_customer_price != null && item.for_customer_price !== "" && Number(item.for_customer_price) > 0) {
        return Number(item.for_customer_price) || 0;
      }
      return Number(item.price ?? item.original_price ?? 0) || 0;
    },
    /**
     * Test groups have their OWN price, which may be discounted vs the sum of
     * the children (e.g. group of 4×10000 sold for 36000). The group's own
     * price is the source of truth for what to charge — use it when set, and
     * only fall back to summing children when the group carries no own price.
     */
    resolveGroupPrice(group) {
      if (!group) return 0;
      // Price-list override for the group
      if (this.price_list_id_fk && Array.isArray(group.prices)) {
        const pl = group.prices.find((p) => p.price_list_id === this.price_list_id_fk);
        const v = pl?.price_for_customer;
        if (v != null && v !== "") return Number(v) || 0;
      }
      // Group's own customer/list/original price
      const own =
        (group.for_customer_price != null && group.for_customer_price !== "" && Number(group.for_customer_price) > 0 && Number(group.for_customer_price)) ||
        (group.price != null && group.price !== "" && Number(group.price) > 0 && Number(group.price)) ||
        (group.original_price != null && group.original_price !== "" && Number(group.original_price) > 0 && Number(group.original_price));
      if (own) return own;
      // No own price → sum children
      const tests = Array.isArray(group.tests) ? group.tests : [];
      const cultures = Array.isArray(group.culture) ? group.culture : Array.isArray(group.cultures) ? group.cultures : [];
      const sum = (arr) => arr.reduce((acc, c) => acc + this.resolveItemPrice(c), 0);
      return sum(tests) + sum(cultures);
    },
    addSelection(type, item) {
      if (item) {
        const selectedItem = {
          ...item,
          thePrice: this.resolveItemPrice(item),
        };
        if (type === "test") {
          // If item is a test group (merged from backend), route to selectedtestGroups
          if (item.type === "group") {
            const exists = this.selectedtestGroups.find((g) => g.id === item.id);
            if (!exists) this.selectedtestGroups.push(selectedItem);
          } else {
            const exists = this.selectedTests.find((test) => test.id === item.id);
            if (!exists) {
              this.selectedTests.push(selectedItem);
              this.show_Questions();
            }
          }
        } else if (type === "package") {
          const exists = this.selectedPackages.find((pkg) => pkg.id === item.id);
          if (!exists) this.selectedPackages.push(selectedItem);
        } else if (type === "culture") {
          const exists = this.selectedCultures.find((pkg) => pkg.id === item.id);
          if (!exists) this.selectedCultures.push(selectedItem);
        } else if (type === "testGroup") {
          const exists = this.selectedtestGroups.find((pkg) => pkg.id === item.id);
          if (!exists) this.selectedtestGroups.push(selectedItem);
        }
      }
    },
    getComponentType(type) {
      switch (type) {
        case "number": return "input";
        case "text": return "input";
        case "checkbox": return "checkbox";
        case "selections": return "Dropdown";
        default: return "input";
      }
    },
    handleQuestionChange(item) {
      if (item.selectedQuestion) {
        item.selectedQuestion.answer = null;
      }
    },
    removeSelection(type, index) {
      if (type === "test") {
        this.selectedTests.splice(index, 1);
        if (this.selectedTests.length > 0) {
          this.show_Questions();
        } else {
          this.localQuestions = [];
          this.localResultComments = [];
        }
      }
      else if (type === "package") this.selectedPackages.splice(index, 1);
      else if (type === "culture") this.selectedCultures.splice(index, 1);
      else if (type === "testGroup") this.selectedtestGroups.splice(index, 1);
    },
    async create() {
      // Auto-create inline patient if not picked from search
      const ok = await this.ensurePatientExists();
      if (!ok) return;
      // Set patient_id_fk from selected/newly-created patient
      if (this.responseData?.id) {
        this.record.patient_id_fk = this.responseData.id;
      }

      this.record.show_patient_card_id = this.record?.show_patient_card_id ? true : false;
      this.record.show_result_date = this.record?.show_result_date ? true : false;
      this.record.show_patient_pic = this.record?.show_patient_pic ? true : false;
      this.record.payment_details = this.payment_details;
      if (this.perceInput) {
        this.record.discount = Math.round(Number(this.discountPercentage) || 0);
        this.record.discount_type_id_fk = 2;
      } else if (this.valueInput) {
        this.record.discount = Math.round(Number(this.discountValue) || 0);
        this.record.discount_type_id_fk = 3;
      } else {
        this.record.discount = null;
        this.record.discount_type_id_fk = null;
      }
      this.record.sub_total = Math.round(Number(this.record.sub_total) || 0);
      this.record.total = Math.round(Number(this.record.total) || 0);
      const getPrice = (item) => this.resolveItemPrice(item);

      this.record.tests = this.selectedTests?.map((test) => ({
        test_id_fk: test.id,
        price: getPrice(test),
        result: test.result ?? null,
        comment: test.comment ?? null,
        result_status_id_fk: test.result_status_id_fk ?? null,
        to_lab_id_fk: null,
        is_sample_received: test.is_sample_received ?? false,
        questions: this.localQuestions
          ?.filter((v) => v.test_id_fk === test.id)
          .map((q) => ({
            question: {
              id: q.id,
              question: q.question,
              answer_type: q.answer_type,
              answer_type_id_fk: q.answer_type_id_fk,
              answer_type_selection_values: q.answer_type_selection_values,
            },
            answer: q.answer_type == "checkbox" ? (q.answer ? true : false) : q.answer,
          })),
      }));

      this.record.cultures = this.selectedCultures.map((culture) => ({
        culture_id_fk: culture.id,
        price: getPrice(culture),
        result: culture.result ?? null,
        comment: culture.comment ?? null,
        result_status_id_fk: culture.result_status_id_fk ?? null,
        to_lab_id_fk: culture.lab ?? null,
        is_sample_received: culture.is_sample_received ?? false,
        questions: null,
      }));

      this.record.packages = this.selectedPackages.map((pkg) => ({
        package_id_fk: pkg.id,
        price: getPrice(pkg),
        result: pkg.result ?? null,
        package_tests: pkg.tests ?? [],
        package_cultures: pkg.cultures ?? [],
        comment: pkg.comment ?? null,
        result_status_id_fk: pkg.result_status_id_fk ?? null,
        to_lab_id_fk: pkg.to_lab ?? null,
        is_sample_received: pkg.is_sample_received ?? false,
        questions: null,
      }));

      this.record.test_groups = this.selectedtestGroups.map((group) => ({
        test_group_id_fk: group.id,
        price: this.resolveGroupPrice(group),
        result: group.result ?? null,
        test_group_tests: group.tests ?? [],
        test_group_cultures: group.culture ?? [],
        comment: group.comment ?? null,
        result_status_id_fk: group.result_status_id_fk ?? null,
        to_lab_id_fk: group.to_lab ?? null,
        is_sample_received: group.is_sample_received ?? false,
        questions: null,
      }));

      this.Addinvoices()
        .then(async (createdInvoice) => {
          await this.commitPromoCode(createdInvoice?.id);
          this.alertSuccess(this.t("invoice_created_successfully") || this.t("alertSuccess"));
          this.clearObjectValues(this.record);
          // Clear patient form + selection so next invoice starts fresh
          this.clearObjectValues(this.patientStore.record);
          this.responseData = null;
          this.selectedTests = [];
          this.selectedCultures = [];
          this.selectedPackages = [];
          this.selectedtestGroups = [];
          this.payment_details = [{ amount: null, contract_id_fk: null, payment_method_id_fk: null }];
          this.discountPercentage = 0;
          this.discountValue = 0;
          this.promoCodeInput = "";
          this.promoCodeApplied = null;
          this.promoCodeMessage = "";
          this.promoCodeError = "";
          if (this.isPageMode) {
            this.printInvoiceDialog = true;
          } else {
            this.dialog = false;
          }
        })
        .catch((error) => {
          console.error("Create invoice error:", error);
          this.alertError(error?.response?.data?.message || this.t("error_creating_invoice") || "Failed to create invoice");
        });
    },
    // Auto-grow Notes textarea — reset height then match scrollHeight.
    // Also called once on mount + when invoice loads in edit mode so saved
    // notes render at the full height instead of stuck at rows=3.
    autoGrowNotes(e) {
      const el = e?.target || this.$refs.notesTextarea;
      if (!el) return;
      el.style.height = "auto";
      el.style.height = el.scrollHeight + "px";
    },
    addRow() {
      this.payment_details.push({ amount: null, contract_id_fk: null, payment_method_id_fk: null });
    },
    removeRow(index) {
      this.payment_details.splice(index, 1);
    },
    markFullyPaid() {
      const total = Number(this.discountedTotal) || 0;
      if (total <= 0) return;
      if (!this.payment_details?.length) {
        this.payment_details = [{ amount: total, contract_id_fk: null, payment_method_id_fk: null }];
        return;
      }
      const others = this.payment_details
        .slice(0, -1)
        .reduce((s, r) => s + (Number(r.amount) || 0), 0);
      const remaining = total - others;
      const last = this.payment_details[this.payment_details.length - 1];
      last.amount = remaining > 0 ? remaining : total;
    },
    update() {
      // Ensure patient_id_fk is set from responseData if not already set
      if (!this.record.patient_id_fk && this.responseData?.id) {
        this.record.patient_id_fk = this.responseData.id;
      }
      this.record.show_patient_card_id = this.record?.show_patient_card_id ? true : false;
      this.record.show_result_date = this.record?.show_result_date ? true : false;
      this.record.show_patient_pic = this.record?.show_patient_pic ? true : false;
      this.record.payment_details = this.payment_details;
      if (this.perceInput) {
        this.record.discount = Math.round(Number(this.discountPercentage) || 0);
        this.record.discount_type_id_fk = 2;
      } else if (this.valueInput) {
        this.record.discount = Math.round(Number(this.discountValue) || 0);
        this.record.discount_type_id_fk = 3;
      } else {
        this.record.discount = null;
        this.record.discount_type_id_fk = null;
      }
      this.record.sub_total = Math.round(Number(this.record.sub_total) || 0);
      this.record.total = Math.round(Number(this.record.total) || 0);
      this.record.tests = this.selectedTests?.map((test) => ({
        test_id_fk: test.id ?? test.test_id_fk,
        result: test.result ?? null,
        comment: test.comment ?? null,
        result_status_id_fk: test.result_status_id_fk ?? null,
        to_lab_id_fk: null,
        price: this.resolveItemPrice(test),
        is_sample_received: test.is_sample_received ?? false,
        questions: this.localQuestions
          ?.filter((v) => v.test_id_fk === test.id)
          .map((q) => ({
            question: {
              id: q.id,
              question: q.question,
              answer_type: q.answer_type,
              answer_type_id_fk: q.answer_type_id_fk,
              answer_type_selection_values: q.answer_type_selection_values,
            },
            answer: q.answer_type == "checkbox" ? (q.answer ? true : false) : q.answer,
          })),
      }));
      this.record.cultures = this.selectedCultures.map((test) => ({
        culture_id_fk: test.id ?? test.culture_id_fk,
        result: test.result ?? null,
        comment: test.comment ?? null,
        result_status_id_fk: test.result_status_id_fk ?? null,
        to_lab_id_fk: test.lab ?? null,
        price: this.resolveItemPrice(test),
        is_sample_received: test.is_sample_received ?? false,
        questions: null,
      }));
      this.record.packages = this.selectedPackages.map((test) => ({
        package_id_fk: test.id ?? test.package_id_fk,
        result: test.result ?? null,
        package_tests: test.tests ?? test.package_tests ?? [],
        package_cultures: test.cultures ?? test.package_cultures ?? [],
        comment: test.comment ?? null,
        result_status_id_fk: test.result_status_id_fk ?? null,
        to_lab_id_fk: test.to_lab ?? test.to_lab_id_fk ?? null,
        price: this.resolveItemPrice(test),
        is_sample_received: test.is_sample_received ?? false,
        questions: null,
      }));
      this.record.test_groups = this.selectedtestGroups.map((test) => ({
        test_group_id_fk: test.id ?? test.test_group_id_fk,
        result: test.result ?? null,
        test_group_tests: test.tests ?? test.test_group_tests ?? [],
        test_group_cultures: test.cultures ?? test.culture ?? test.test_group_cultures ?? [],
        comment: test.comment ?? null,
        result_status_id_fk: test.result_status_id_fk ?? null,
        to_lab_id_fk: test.to_lab ?? test.to_lab_id_fk ?? null,
        price: this.resolveGroupPrice(test),
        is_sample_received: test.is_sample_received ?? false,
        questions: null,
      }));

      const _updatingInvoiceId = this.record.id;
      this.Updateinvoices()
        .then(async () => {
          await this.commitPromoCode(_updatingInvoiceId);
          this.alertSuccess(this.t("invoice_updated_successfully") || this.t("alertSuccess"));
          this.clearObjectValues(this.record);
          this.promoCodeInput = "";
          this.promoCodeApplied = null;
          this.promoCodeMessage = "";
          this.promoCodeError = "";
          if (this.isPageMode) {
            this.$emit('saved');
          } else {
            this.dialog = false;
          }
        })
        .catch((error) => {
          console.error("Update invoice error:", error);
          this.alertError(error?.response?.data?.message || this.t("error_updating_invoice") || "Failed to update invoice");
        });
    },
    close() {
      this.selectedContract = [];
      this.selectedreferal = [];
      this.selectedTests = [];
      this.selectedPackages = [];
      this.selectedCultures = [];
      this.selectedPatient = null;
      this.searchRecords = [];
      this.payment_details = [{ amount: null, contract_id_fk: null, payment_method_id_fk: null }];
      this.discountPercentage = 0;
      this.discountValue = 0;
      this.errorMessage = "";
      this.clearObjectValues(this.record);
      if (this.isPageMode) {
        this.$emit('cancelled');
      } else {
        this.dialog = false;
      }
    },
    onScroll() {
      // All tests are loaded at once, no need for infinite scroll
    },
  },
  watch: {
    "record.notes": function () {
      this.$nextTick(() => this.autoGrowNotes());
    },
    // Pre-fill the promo code box when editing an invoice that already has one applied.
    "record.promo_code": {
      immediate: true,
      handler(v) {
        if (v) {
          this.promoCodeInput = v;
          this.promoCodeApplied = v;
        }
      },
    },
    // Init discount mode flags when invoice loads in edit mode (form.vue sets record.discount_type_id_fk)
    // Also computes the paired field (% ↔ amount) since v-model values arrive separately.
    "record.discount_type_id_fk": {
      immediate: true,
      handler(v) {
        const t = Number(v);
        if (t === 2) {
          this.perceInput = true; this.valueInput = false;
          // Compute amount from percentage once baseTotal is known
          this.$nextTick(() => {
            if (this.baseTotal > 0 && this.discountPercentage > 0) {
              this._updatingDiscount = true;
              this.discountValue = Math.round((this.baseTotal * this.discountPercentage) / 100);
              this.$nextTick(() => { this._updatingDiscount = false; });
            }
          });
        } else if (t === 3) {
          this.valueInput = true; this.perceInput = false;
          this.$nextTick(() => {
            if (this.baseTotal > 0 && this.discountValue > 0) {
              this._updatingDiscount = true;
              this.discountPercentage = Math.round((this.discountValue / this.baseTotal) * 100);
              this.$nextTick(() => { this._updatingDiscount = false; });
            }
          });
        }
      },
    },
    baseTotal: function () {
      this.record.sub_total = Math.round(this.baseTotal || 0);
      if (this.perceInput && this.discountPercentage > 0) {
        this._updatingDiscount = true;
        this.discountValue = Math.round((this.baseTotal * this.discountPercentage) / 100);
        this.$nextTick(() => { this._updatingDiscount = false; });
      } else if (this.valueInput && this.discountValue > 0 && this.baseTotal > 0) {
        this._updatingDiscount = true;
        this.discountPercentage = Math.round((this.discountValue / this.baseTotal) * 100);
        this.$nextTick(() => { this._updatingDiscount = false; });
      }
      this.recalcTotal();
    },
    discountPercentage: function () {
      if (this._updatingDiscount) return;
      if (this.discountPercentage !== null && this.discountPercentage >= 0) {
        this._updatingDiscount = true;
        this.discountValue = (this.baseTotal * this.discountPercentage) / 100;
        this.recalcTotal();
        this.$nextTick(() => { this._updatingDiscount = false; });
      } else {
        this.discountValue = 0;
        this.recalcTotal();
      }
    },
    discountValue: function () {
      if (this._updatingDiscount) return;
      if (this.discountValue !== null && this.discountValue >= 0) {
        this._updatingDiscount = true;
        this.discountPercentage = this.baseTotal > 0 ? (this.discountValue / this.baseTotal) * 100 : 0;
        this.recalcTotal();
        this.$nextTick(() => { this._updatingDiscount = false; });
      } else {
        this.discountPercentage = 0;
        this.recalcTotal();
      }
    },
    "record.from_lab_id_fk": function (v) {
      const lab = this.fromLap?.find((c) => c.id === v);
      this.price_list_id = lab?.lab_price_list_id_fk ?? lab?.price_list_id_fk ?? null;
      if (!this.price_list_id) this.applyReferralPriceList();
      this.applyFromLabDiscount();
    },
    // Re-resolve when referral changes (covers edit-mode load + records arriving async)
    "record.referral_id_fk": function () {
      this.applyReferralPriceList();
    },
  },
};
</script>

<style scoped>
/* Custom Dropdown Styling */
:deep(.custom-dropdown) {
  border-radius: 0.75rem;
}

:deep(.custom-dropdown .p-dropdown-label) {
  padding: 0.75rem 1rem;
}

/* Force PrimeVue components to respect container width */
:deep(.p-dropdown),
:deep(.p-inputnumber),
:deep(.p-inputtext) {
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

/* PrimeVue input min-height for healthcare touch-target a11y (WCAG 44px) */
:deep(.invoice-page-form .p-dropdown),
:deep(.invoice-page-form .p-inputnumber-input),
:deep(.invoice-page-form .p-inputtext),
:deep(.invoice-page-form .p-autocomplete-input) {
  min-height: 44px;
}

/* Stronger focus rings on PrimeVue components */
:deep(.invoice-page-form .p-dropdown:not(.p-disabled).p-focus),
:deep(.invoice-page-form .p-inputnumber-input:focus),
:deep(.invoice-page-form .p-inputtext:focus),
:deep(.invoice-page-form .p-autocomplete-input:focus) {
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
  border-color: rgb(13, 148, 136);
}

/* Reduced-motion: respect user preference */
@media (prefers-reduced-motion: reduce) {
  .invoice-page-form *,
  .invoice-page-form *::before,
  .invoice-page-form *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
  }
}
</style>
