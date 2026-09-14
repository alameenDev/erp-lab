<template>
     <div class="space-y-6 p-4 lg:p-6 max-w-[1600px] mx-auto" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
          <!-- ============== HEADER BANNER ============== -->
          <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 shadow-2xl">
               <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary-500/20 via-transparent to-transparent"></div>
               <div class="relative p-6 lg:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                         <div class="flex items-center gap-4">
                              <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
                                   <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                   </svg>
                              </div>
                              <div>
                                   <h1 class="text-2xl lg:text-3xl font-bold text-white">
                                        {{ t("accounting_reports") }}
                                   </h1>
                                   <p class="text-sm text-slate-300 mt-1">
                                        {{ t("accounting_reports_subtitle") || "Generate PDF accounting reports for any date range" }}
                                   </p>
                              </div>
                         </div>
                         <button
                              v-if="store.report && store.hasData"
                              type="button"
                              class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all flex items-center gap-2 shadow-lg shadow-primary-500/25 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                              :disabled="store.isGeneratingPdf"
                              @click="generatePdf"
                         >
                              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                              </svg>
                              <span v-if="store.isGeneratingPdf">{{ t("generating_pdf") || "Generating PDF..." }}</span>
                              <span v-else>{{ t("generate_pdf") || "Generate PDF" }}</span>
                         </button>
                    </div>
               </div>
          </div>

          <!-- ============== FILTER CARD ============== -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
               <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                         <div class="flex items-center gap-2">
                              <div class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center">
                                   <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                   </svg>
                              </div>
                              <h2 class="text-base font-semibold text-slate-800">
                                   {{ t("filters") || "Filters" }}
                              </h2>
                         </div>
                    </div>

                    <!-- Date presets -->
                    <div class="flex flex-wrap gap-2 mb-4">
                         <button
                              v-for="p in presets"
                              :key="p.id"
                              type="button"
                              class="px-3 py-1.5 rounded-xl text-sm font-medium transition-all cursor-pointer"
                              :class="activePreset === p.id ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800 bg-slate-50'"
                              @click="applyPreset(p.id)"
                         >
                              {{ t(p.labelKey) || p.fallback }}
                         </button>
                    </div>

                    <!-- Date range inputs (only shown for Custom preset) -->
                    <div v-if="activePreset === 'custom'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                         <div>
                              <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                   {{ t("date_from") || "From" }} <span class="text-danger-500">*</span>
                              </label>
                              <input
                                   type="date"
                                   v-model="store.from"
                                   @change="onDateChange"
                                   @click="$event.target.showPicker?.()"
                                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer"
                              />
                         </div>
                         <div>
                              <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                   {{ t("date_to") || "To" }} <span class="text-danger-500">*</span>
                              </label>
                              <input
                                   type="date"
                                   v-model="store.to"
                                   @change="onDateChange"
                                   @click="$event.target.showPicker?.()"
                                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer"
                              />
                         </div>
                    </div>

                    <!-- Active range display + loading indicator -->
                    <div class="flex items-center justify-between text-sm">
                         <div class="text-slate-500">
                              <span class="font-medium text-slate-700">{{ t("date_range") || "Date range" }}:</span>
                              <span class="ms-2">{{ store.from }} → {{ store.to }}</span>
                         </div>
                         <div v-if="store.isLoading" class="flex items-center gap-2 text-primary-600">
                              <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                   <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                                   <path stroke-linecap="round" d="M22 12a10 10 0 01-10 10"></path>
                              </svg>
                              <span class="text-xs font-medium">{{ t("loading") || "Loading..." }}</span>
                         </div>
                    </div>

                    <!-- Dimension filters -->
                    <div class="mt-5 pt-5 border-t border-slate-200">
                         <div class="flex items-center justify-between mb-3">
                              <p class="text-sm font-medium text-slate-700">
                                   {{ t("more_filters") || "More filters" }}
                              </p>
                              <button
                                   v-if="hasActiveDimensionFilter"
                                   type="button"
                                   class="text-xs text-primary-600 hover:text-primary-700 font-medium cursor-pointer"
                                   @click="clearDimensionFilters"
                              >
                                   {{ t("clear_filters") || "Clear filters" }}
                              </button>
                         </div>
                         <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                              <!-- Branch -->
                              <select
                                   v-model="store.branch_id"
                                   @change="onDimensionChange"
                                   class="px-3 py-2 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer text-sm"
                              >
                                   <option :value="null">{{ t("all_branches") || "All branches" }}</option>
                                   <option v-for="b in branchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                              </select>

                              <!-- Sample Collector -->
                              <select
                                   v-model="store.sample_collector_id"
                                   @change="onDimensionChange"
                                   class="px-3 py-2 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer text-sm"
                              >
                                   <option :value="null">{{ t("all_sample_collectors") || "All sample collectors" }}</option>
                                   <option v-for="s in sampleCollectorOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                              </select>

                              <!-- Referral -->
                              <select
                                   v-model="store.referral_id"
                                   @change="onDimensionChange"
                                   class="px-3 py-2 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer text-sm"
                              >
                                   <option :value="null">{{ t("all_referrals") || "All referrals" }}</option>
                                   <option v-for="r in referralOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
                              </select>

                              <!-- Contract -->
                              <select
                                   v-model="store.contract_id"
                                   @change="onDimensionChange"
                                   class="px-3 py-2 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition cursor-pointer text-sm"
                              >
                                   <option :value="null">{{ t("all_contracts") || "All contracts" }}</option>
                                   <option v-for="c in contractOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                              </select>

                              <!-- Patient (search by name/code) -->
                              <div class="relative">
                                   <input
                                        type="text"
                                        v-model="patientSearchQuery"
                                        @input="onPatientSearch"
                                        :placeholder="patientLabel"
                                        class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition text-sm"
                                   />
                                   <button
                                        v-if="store.patient_id"
                                        type="button"
                                        class="absolute end-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-danger-600 cursor-pointer"
                                        @click="clearPatient"
                                   >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                   </button>
                                   <!-- Search dropdown -->
                                   <div
                                        v-if="patientSearchResults.length && patientSearchOpen"
                                        class="absolute top-full mt-1 left-0 right-0 bg-white border border-slate-200 rounded-xl shadow-lg z-20 max-h-56 overflow-y-auto"
                                   >
                                        <button
                                             v-for="p in patientSearchResults"
                                             :key="p.id"
                                             type="button"
                                             class="w-full text-start px-3 py-2 hover:bg-slate-50 text-sm border-b border-slate-100 last:border-0 cursor-pointer"
                                             @click="selectPatient(p)"
                                        >
                                             <div class="font-medium text-slate-800">{{ p.name }}</div>
                                             <div class="text-xs text-slate-500">{{ p.code }}</div>
                                        </button>
                                   </div>
                              </div>
                         </div>
                    </div>

                    <!-- Section toggles -->
                    <div class="mt-5 pt-5 border-t border-slate-200">
                         <p class="text-sm font-medium text-slate-700 mb-2">
                              {{ t("sections_to_include") || "Sections to include in PDF" }}
                         </p>
                         <div class="flex flex-wrap gap-2">
                              <label
                                   v-for="s in store.allSections"
                                   :key="s"
                                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-sm cursor-pointer transition-all"
                                   :class="store.enabledSections.includes(s) ? 'bg-primary-50 border-primary-300 text-primary-700' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50'"
                              >
                                   <input
                                        type="checkbox"
                                        :checked="store.enabledSections.includes(s)"
                                        @change="store.toggleSection(s)"
                                        class="w-4 h-4 accent-primary-600 cursor-pointer"
                                   />
                                   <span>{{ t(`section_${s}`) || s }}</span>
                              </label>
                         </div>
                    </div>
               </div>
          </div>

          <!-- ============== STATS GRID ============== -->
          <div v-if="store.report" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
               <!-- Invoices -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                         <div>
                              <p class="text-sm font-medium text-slate-500 mb-1">{{ t("invoices") || "Invoices" }}</p>
                              <p class="text-2xl font-bold text-slate-800">{{ store.report.totals?.invoice_count || 0 }}</p>
                         </div>
                         <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
                              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                              </svg>
                         </div>
                    </div>
               </div>

               <!-- Patients -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                         <div>
                              <p class="text-sm font-medium text-slate-500 mb-1">{{ t("patient_count") || "Patients" }}</p>
                              <p class="text-2xl font-bold text-slate-800">{{ store.report.totals?.patient_count || 0 }}</p>
                         </div>
                         <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center">
                              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                         </div>
                    </div>
               </div>

               <!-- Subtotal -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                         <div>
                              <p class="text-sm font-medium text-slate-500 mb-1">{{ t("subtotal") || "Subtotal" }}</p>
                              <p class="text-2xl font-bold text-slate-800">{{ formatNum(store.report.totals?.sub_total || 0) }}</p>
                         </div>
                         <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
                              <svg class="w-6 h-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                         </div>
                    </div>
               </div>

               <!-- Discount -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                         <div>
                              <p class="text-sm font-medium text-slate-500 mb-1">{{ t("discount") || "Discount" }}</p>
                              <p class="text-2xl font-bold text-warning-600">{{ formatNum(store.report.totals?.discount || 0) }}</p>
                         </div>
                         <div class="w-12 h-12 rounded-xl bg-warning-50 flex items-center justify-center">
                              <svg class="w-6 h-6 text-warning-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                              </svg>
                         </div>
                    </div>
               </div>

               <!-- Paid -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                         <div>
                              <p class="text-sm font-medium text-slate-500 mb-1">{{ t("paid") || "Paid" }}</p>
                              <p class="text-2xl font-bold text-success-600">{{ formatNum(store.report.totals?.paid || 0) }}</p>
                         </div>
                         <div class="w-12 h-12 rounded-xl bg-success-50 flex items-center justify-center">
                              <svg class="w-6 h-6 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                              </svg>
                         </div>
                    </div>
               </div>

               <!-- Due -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                         <div>
                              <p class="text-sm font-medium text-slate-500 mb-1">{{ t("due") || "Due" }}</p>
                              <p class="text-2xl font-bold text-danger-600">{{ formatNum(store.report.totals?.due || 0) }}</p>
                         </div>
                         <div class="w-12 h-12 rounded-xl bg-danger-50 flex items-center justify-center">
                              <svg class="w-6 h-6 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                         </div>
                    </div>
               </div>
          </div>

          <!-- ============== EMPTY STATE ============== -->
          <div
               v-if="store.report && !store.hasData"
               class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-12 text-center"
          >
               <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
                    <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
               </div>
               <p class="text-slate-700 font-semibold mb-1">
                    {{ t("no_data_for_range") || "No invoices found in this date range" }}
               </p>
               <p class="text-sm text-slate-500 mb-4">
                    {{ t("try_wider_range") || "Try a wider date range, e.g. last 30 days or this month" }}
               </p>
               <div class="flex justify-center gap-2">
                    <button
                         type="button"
                         class="px-4 py-2 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition cursor-pointer"
                         @click="applyPreset('last_30_days')"
                    >
                         {{ t("preset_last_30_days") || "Last 30 days" }}
                    </button>
                    <button
                         type="button"
                         class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition cursor-pointer"
                         @click="applyPreset('this_month')"
                    >
                         {{ t("preset_this_month") || "This month" }}
                    </button>
               </div>
          </div>

          <!-- ============== SECTION TABS + PREVIEW ============== -->
          <div
               v-if="store.report && store.hasData"
               id="accounting-report-content"
               ref="reportContentRef"
               class="space-y-6"
          >
               <!-- Tab strip (visible only on screen, NOT in PDF) -->
               <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden no-print">
                    <div class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white p-3">
                         <div class="flex flex-wrap gap-2">
                              <button
                                   v-for="s in availableTabs"
                                   :key="s"
                                   @click="activeTab = s"
                                   :class="[
                                        'px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer',
                                        activeTab === s
                                             ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/25'
                                             : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800',
                                   ]"
                              >
                                   {{ t(`section_${s}`) || s }}
                                   <span class="text-xs opacity-75">({{ store.report[s]?.length || 0 }})</span>
                              </button>
                         </div>
                    </div>
               </div>

               <!-- Report Header (PDF only) -->
               <div class="report-section bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 only-print">
                    <div class="flex flex-wrap justify-between items-center gap-3 text-sm">
                         <h2 class="text-xl font-bold text-slate-800">{{ t("accounting_report") || "Accounting Report" }}</h2>
                         <div class="text-slate-600 leading-snug text-end">
                              <div><strong>{{ t("date") || "Date" }}:</strong> {{ store.report.meta?.generated_at }}</div>
                              <div><strong>{{ t("date_range") || "Date range" }}:</strong> {{ store.report.meta?.from }} → {{ store.report.meta?.to }}</div>
                         </div>
                    </div>
               </div>

               <!-- Active section table -->
               <SectionTable
                    v-if="activeTab === 'invoices' && store.report.invoices?.length"
                    :title="t('invoices') || 'Invoices'"
                    icon="invoice"
               >
                    <template #headers>
                         <th class="ar-th">#</th>
                         <th class="ar-th">{{ t("branch") || "Branch" }}</th>
                         <th class="ar-th">{{ t("created_by") || "Created by" }}</th>
                         <th class="ar-th">{{ t("date") || "Date" }}</th>
                         <th class="ar-th">{{ t("patient_name") || "Patient" }}</th>
                         <th class="ar-th">{{ t("referral") || "Referral" }}</th>
                         <th class="ar-th">{{ t("contract") || "Contract" }}</th>
                         <th class="ar-th">{{ t("tests") || "Tests" }}</th>
                         <th class="ar-th text-end">{{ t("subtotal") || "Subtotal" }}</th>
                         <th class="ar-th text-end">{{ t("discount") || "Discount" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                         <th class="ar-th text-end">{{ t("paid") || "Paid" }}</th>
                         <th class="ar-th text-end">{{ t("due") || "Due" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="inv in store.report.invoices" :key="inv.id" class="ar-row">
                              <td class="ar-td font-medium">{{ inv.id }}</td>
                              <td class="ar-td">{{ inv.branch }}</td>
                              <td class="ar-td">{{ inv.created_by }}</td>
                              <td class="ar-td whitespace-nowrap">{{ inv.date }}</td>
                              <td class="ar-td font-medium">{{ inv.patient_name }}</td>
                              <td class="ar-td">{{ inv.referral_name }}</td>
                              <td class="ar-td">{{ inv.contract_name }}</td>
                              <td class="ar-td p-0">
                                   <table v-if="inv.items?.length" class="w-full text-xs">
                                        <tr v-for="(item, idx) in inv.items" :key="idx" class="border-b last:border-0 border-slate-100">
                                             <td class="px-2 py-1">{{ item.name }}</td>
                                             <td class="px-2 py-1 text-end whitespace-nowrap text-slate-600">{{ formatNum(item.price) }}</td>
                                        </tr>
                                   </table>
                              </td>
                              <td class="ar-td text-end">{{ formatNum(inv.sub_total) }}</td>
                              <td class="ar-td text-end text-warning-600">{{ formatNum(inv.discount) }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(inv.total) }}</td>
                              <td class="ar-td text-end text-success-600">{{ formatNum(inv.paid) }}</td>
                              <td class="ar-td text-end" :class="inv.due > 0 ? 'text-danger-600 font-semibold' : 'text-slate-400'">{{ formatNum(inv.due) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'tests' && store.report.tests?.length"
                    :title="t('tests') || 'Tests'"
                    icon="beaker"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("count") || "Count" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.tests" :key="i" class="ar-row">
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end">{{ row.count }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(row.total) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'cultures' && store.report.cultures?.length"
                    :title="t('cultures') || 'Cultures'"
                    icon="beaker"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("count") || "Count" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.cultures" :key="i" class="ar-row">
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end">{{ row.count }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(row.total) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'packages' && store.report.packages?.length"
                    :title="t('packages') || 'Packages'"
                    icon="cube"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("count") || "Count" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.packages" :key="i" class="ar-row">
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end">{{ row.count }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(row.total) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'test_groups' && store.report.test_groups?.length"
                    :title="t('test_groups') || 'Test Groups'"
                    icon="folder"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("count") || "Count" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.test_groups" :key="i" class="ar-row">
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end">{{ row.count }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(row.total) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'referrals' && store.report.referrals?.length"
                    :title="t('referrals') || 'Referrals'"
                    icon="users"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("count") || "Count" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                         <th class="ar-th text-end">{{ t("commission") || "Commission" }}</th>
                         <th class="ar-th text-end">{{ t("profit") || "Profit" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.referrals" :key="i" class="ar-row">
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end">{{ row.count }}</td>
                              <td class="ar-td text-end">{{ formatNum(row.total) }}</td>
                              <td class="ar-td text-end text-warning-600">{{ formatNum(row.commission) }}</td>
                              <td class="ar-td text-end font-semibold text-success-700">{{ formatNum(row.profit) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'contracts' && store.report.contracts?.length"
                    :title="t('contracts') || 'Contracts'"
                    icon="document"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("count") || "Count" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.contracts" :key="i" class="ar-row">
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end">{{ row.count }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(row.total) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <SectionTable
                    v-if="activeTab === 'patients' && store.report.patients?.length"
                    :title="t('patients') || 'Patients'"
                    icon="user"
               >
                    <template #headers>
                         <th class="ar-th">{{ t("code") || "Code" }}</th>
                         <th class="ar-th">{{ t("name") || "Name" }}</th>
                         <th class="ar-th text-end">{{ t("total") || "Total" }}</th>
                         <th class="ar-th text-end">{{ t("patient_payment") || "Patient payment" }}</th>
                         <th class="ar-th text-end">{{ t("paid") || "Paid" }}</th>
                         <th class="ar-th text-end">{{ t("due") || "Due" }}</th>
                    </template>
                    <template #rows>
                         <tr v-for="(row, i) in store.report.patients" :key="i" class="ar-row">
                              <td class="ar-td font-medium text-slate-500">{{ row.code }}</td>
                              <td class="ar-td font-medium">{{ row.name }}</td>
                              <td class="ar-td text-end font-semibold text-slate-800">{{ formatNum(row.total) }}</td>
                              <td class="ar-td text-end">{{ formatNum(row.patient_payment) }}</td>
                              <td class="ar-td text-end text-success-600">{{ formatNum(row.paid) }}</td>
                              <td class="ar-td text-end" :class="row.due > 0 ? 'text-danger-600 font-semibold' : 'text-slate-400'">{{ formatNum(row.due) }}</td>
                         </tr>
                    </template>
               </SectionTable>

               <!-- Hidden mirror of ALL enabled sections, used only for PDF capture -->
               <div class="pdf-only" aria-hidden="true">
                    <template v-for="s in store.enabledSections" :key="s">
                         <SectionTable v-if="store.report[s]?.length" :title="t(`section_${s}`) || s">
                              <template #headers>
                                   <PdfHeaders :section="s" />
                              </template>
                              <template #rows>
                                   <PdfRows :section="s" :rows="store.report[s]" />
                              </template>
                         </SectionTable>
                    </template>

                    <!-- Summary (always last page) -->
                    <div class="accounting-section">
                         <div class="accounting-section-title">{{ t("summary") || "Summary" }}</div>
                         <table class="summary-table">
                              <tbody>
                                   <tr>
                                        <td class="sum-label">{{ t("lab") || "Lab" }}</td>
                                        <td class="sum-value">{{ store.report.meta?.lab_name }}</td>
                                        <td class="sum-label">{{ t("generated_at") || "Generated at" }}</td>
                                        <td class="sum-value">{{ store.report.meta?.generated_at }}</td>
                                   </tr>
                                   <tr>
                                        <td class="sum-label">{{ t("date_range") || "Date range" }}</td>
                                        <td class="sum-value" colspan="3">
                                             {{ store.report.meta?.from }} → {{ store.report.meta?.to }}
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                         <table class="summary-table">
                              <tbody>
                                   <tr>
                                        <td class="sum-label">{{ t("invoices") || "Invoices" }}</td>
                                        <td class="sum-value sum-num">{{ formatNum(store.report.totals?.invoice_count || 0) }}</td>
                                        <td class="sum-label">{{ t("patient_count") || "Patients" }}</td>
                                        <td class="sum-value sum-num">{{ formatNum(store.report.totals?.patient_count || 0) }}</td>
                                   </tr>
                                   <tr>
                                        <td class="sum-label">{{ t("subtotal") || "Subtotal" }}</td>
                                        <td class="sum-value sum-num">{{ formatNum(store.report.totals?.sub_total || 0) }}</td>
                                        <td class="sum-label">{{ t("discount") || "Discount" }}</td>
                                        <td class="sum-value sum-num sum-warning">{{ formatNum(store.report.totals?.discount || 0) }}</td>
                                   </tr>
                                   <tr>
                                        <td class="sum-label">{{ t("total") || "Total" }}</td>
                                        <td class="sum-value sum-num sum-bold">{{ formatNum(store.report.totals?.total || 0) }}</td>
                                        <td class="sum-label">{{ t("paid") || "Paid" }}</td>
                                        <td class="sum-value sum-num sum-success">{{ formatNum(store.report.totals?.paid || 0) }}</td>
                                   </tr>
                                   <tr>
                                        <td class="sum-label">{{ t("due") || "Due" }}</td>
                                        <td class="sum-value sum-num" :class="(store.report.totals?.due || 0) > 0 ? 'sum-danger sum-bold' : ''">
                                             {{ formatNum(store.report.totals?.due || 0) }}
                                        </td>
                                        <td class="sum-label" colspan="2"></td>
                                   </tr>
                              </tbody>
                         </table>
                    </div>
               </div>
          </div>
     </div>
</template>

<script setup>
     import { ref, computed, onMounted, h } from "vue";
     import { useAccountingReportsStore } from "@/store/modules/accountingReports";
     import { useUsersStore } from "@/store/modules/users";
     import { uselabsStore } from "@/store/modules/labs";
     import { useReferralsStore } from "@/store/modules/referrals";
     import { useContractsStore } from "@/store/modules/contract";
     import { useToast } from "@/composables/useToast";
     import { t } from "@/utils/helper";
     import { $http } from "@/plugins/axios";
     import html2canvas from "html2canvas-pro";
     import jsPDF from "jspdf";

     const store = useAccountingReportsStore();
     const usersStore = useUsersStore();
     const labsStore = uselabsStore();
     const referralsStore = useReferralsStore();
     const contractsStore = useContractsStore();
     const toast = useToast();
     const reportContentRef = ref(null);
     const lang = computed(() => localStorage.getItem("locale") || "ar");
     const activePreset = ref("all");
     const activeTab = ref("invoices");

     // Dimension dropdown options (lazy-loaded from existing stores)
     const branchOptions = computed(() => usersStore.branches || []);
     const sampleCollectorOptions = computed(() => labsStore.collectorsList || []);
     const referralOptions = computed(() => referralsStore.records || []);
     const contractOptions = computed(() => contractsStore.records || []);

     const hasActiveDimensionFilter = computed(() => {
          return !!(store.branch_id || store.sample_collector_id || store.referral_id || store.contract_id || store.patient_id);
     });

     // Patient autocomplete
     const patientSearchQuery = ref("");
     const patientSearchResults = ref([]);
     const patientSearchOpen = ref(false);
     const patientLabel = computed(() => t("search_patient") || "Search patient by name or code");
     let patientSearchTimer = null;

     const onPatientSearch = () => {
          patientSearchOpen.value = true;
          if (patientSearchTimer) clearTimeout(patientSearchTimer);
          const q = patientSearchQuery.value.trim();
          if (q.length < 2) {
               patientSearchResults.value = [];
               return;
          }
          patientSearchTimer = setTimeout(async () => {
               try {
                    const { data } = await $http.get("/patients/search-name", { params: { name: q } });
                    patientSearchResults.value = (data?.data || data || []).slice(0, 8);
               } catch {
                    patientSearchResults.value = [];
               }
          }, 300);
     };

     const selectPatient = (p) => {
          store.patient_id = p.id;
          patientSearchQuery.value = `${p.name}${p.code ? " — " + p.code : ""}`;
          patientSearchOpen.value = false;
          patientSearchResults.value = [];
          loadReport(false);
     };

     const clearPatient = () => {
          store.patient_id = null;
          patientSearchQuery.value = "";
          patientSearchResults.value = [];
          loadReport(false);
     };

     const onDimensionChange = () => {
          loadReport(false);
     };

     const clearDimensionFilters = () => {
          store.clearDimensionFilters();
          patientSearchQuery.value = "";
          patientSearchResults.value = [];
          loadReport(false);
     };

     const presets = [
          { id: "all", labelKey: "preset_all", fallback: "All data" },
          { id: "today", labelKey: "preset_today", fallback: "Today" },
          { id: "yesterday", labelKey: "preset_yesterday", fallback: "Yesterday" },
          { id: "this_week", labelKey: "preset_this_week", fallback: "This week" },
          { id: "this_month", labelKey: "preset_this_month", fallback: "This month" },
          { id: "last_30_days", labelKey: "preset_last_30_days", fallback: "Last 30 days" },
          { id: "last_month", labelKey: "preset_last_month", fallback: "Last month" },
          { id: "custom", labelKey: "preset_custom", fallback: "Custom" },
     ];

     const availableTabs = computed(() => {
          if (!store.report) return [];
          return store.enabledSections.filter((s) => store.report[s]?.length > 0);
     });

     const formatNum = (n) => {
          const v = Number(n) || 0;
          return v.toLocaleString("en-US");
     };

     const applyPreset = (preset) => {
          activePreset.value = preset;
          if (preset !== "custom") {
               store.setPreset(preset);
               loadReport(false); // auto-reload silently
          }
     };

     // Debounced auto-reload when user changes a custom date input
     let dateChangeTimer = null;
     const onDateChange = () => {
          activePreset.value = "custom";
          if (dateChangeTimer) clearTimeout(dateChangeTimer);
          dateChangeTimer = setTimeout(() => {
               // Only reload if both dates are set and from <= to
               if (store.from && store.to && store.from <= store.to) {
                    loadReport(false);
               }
          }, 350);
     };

     // Silent on initial mount; toast only when user explicitly triggers
     const loadReport = async (showToast = false) => {
          try {
               await store.GetReport();
               // Pick first available tab so the preview isn't blank
               if (availableTabs.value.length && !availableTabs.value.includes(activeTab.value)) {
                    activeTab.value = availableTabs.value[0];
               }
               if (showToast) toast.success(t("report_loaded") || "Report loaded");
          } catch (err) {
               toast.error(store.error || t("report_load_failed") || "Failed to load report");
          }
     };

     const generatePdf = async () => {
          if (store.isGeneratingPdf || !reportContentRef.value) return;
          store.isGeneratingPdf = true;

          let iframe = null;
          try {
               // Build off-screen iframe to isolate from app dark-mode/branding CSS
               // Take only the .pdf-only block so the active-tab UI doesn't leak in
               const pdfBlock = reportContentRef.value.querySelector(".pdf-only");
               if (!pdfBlock) {
                    toast.error(t("pdf_generation_failed") || "PDF generation failed");
                    return;
               }
               const sourceContent = pdfBlock.innerHTML;

               iframe = document.createElement("iframe");
               iframe.style.cssText = "position: fixed; top: 0; left: -10000px; width: 297mm; height: auto; border: 0; visibility: hidden;";
               document.body.appendChild(iframe);

               const css = `
                    body { font-family: 'Tajawal', system-ui, sans-serif; padding: 8mm; margin: 0; background: #fff; color: #0f172a; }
                    /* Hide all icon chips in PDF — they're decorative for screen */
                    .accounting-section-title svg, .accounting-section-title > div:first-child { display: none !important; }
                    svg { max-width: 16px; max-height: 16px; }
                    .accounting-section { margin-bottom: 14px; page-break-after: auto; }
                    .accounting-section-title { background: #f8fafc; padding: 8px 12px; font-weight: 700; text-align: start; border: 1px solid #e2e8f0; font-size: 14px; color: #0f172a; display: block; }
                    .accounting-section-title h3 { margin: 0; font-size: 14px; font-weight: 700; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #e2e8f0; padding: 6px 8px; font-size: 12px; vertical-align: middle; color: #1e293b; }
                    th { background: #f1f5f9; font-weight: 600; text-align: start; }
                    .ar-th { background: #f1f5f9; font-weight: 600; padding: 6px 8px; border: 1px solid #e2e8f0; text-align: start; font-size: 12px; }
                    .ar-td { padding: 6px 8px; border: 1px solid #e2e8f0; font-size: 12px; }
                    .text-end { text-align: end; }
                    .ar-row { page-break-inside: avoid; }
                    .text-success-600, .text-success-700 { color: #16a34a; }
                    .text-warning-600 { color: #d97706; }
                    .text-danger-600 { color: #dc2626; }
                    /* Summary table */
                    .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
                    .summary-table td { padding: 8px 12px; border: 1px solid #e2e8f0; font-size: 12px; }
                    .sum-label { background: #f8fafc; color: #475569; font-weight: 600; width: 22%; }
                    .sum-value { color: #1e293b; }
                    .sum-num { font-variant-numeric: tabular-nums; text-align: end; }
                    .sum-bold { font-weight: 700; font-size: 13px; }
                    .sum-success { color: #16a34a; }
                    .sum-warning { color: #d97706; }
                    .sum-danger { color: #dc2626; }
                    .text-slate-400 { color: #94a3b8; }
                    .text-slate-500 { color: #64748b; }
                    .text-slate-600 { color: #475569; }
                    .text-slate-800 { color: #1e293b; }
                    .font-semibold { font-weight: 600; }
                    .font-medium { font-weight: 500; }
                    .whitespace-nowrap { white-space: nowrap; }
                    img { max-width: 100%; }
               `;

               const doc = iframe.contentDocument;
               doc.open();
               doc.write(`<!DOCTYPE html><html lang="${lang.value}" dir="${lang.value === 'ar' ? 'rtl' : 'ltr'}">
                    <head><meta charset="utf-8"><title>${t("accounting_report") || "Accounting Report"}</title><style>${css}</style></head>
                    <body>${sourceContent}</body></html>`);
               doc.close();

               await new Promise((r) => setTimeout(r, 500));

               const sections = Array.from(doc.querySelectorAll(".accounting-section"));
               const pdf = new jsPDF({ unit: "mm", format: "a4", orientation: "landscape" });
               const pageW = pdf.internal.pageSize.getWidth();
               const pageH = pdf.internal.pageSize.getHeight();
               const margin = 8;

               if (sections.length === 0) {
                    const canvas = await html2canvas(doc.body, { scale: 2, backgroundColor: "#fff", useCORS: true, logging: false });
                    const imgData = canvas.toDataURL("image/png");
                    const w = pageW - margin * 2;
                    const h = (canvas.height * w) / canvas.width;
                    pdf.addImage(imgData, "PNG", margin, margin, w, Math.min(h, pageH - margin * 2));
               } else {
                    for (let i = 0; i < sections.length; i++) {
                         const el = sections[i];
                         const canvas = await html2canvas(el, { scale: 2, backgroundColor: "#fff", useCORS: true, logging: false });
                         const imgData = canvas.toDataURL("image/png");
                         const w = pageW - margin * 2;
                         const h = (canvas.height * w) / canvas.width;
                         if (i > 0) pdf.addPage();
                         if (h <= pageH - margin * 2) {
                              pdf.addImage(imgData, "PNG", margin, margin, w, h);
                         } else {
                              const pageContentH = pageH - margin * 2;
                              const sliceCanvasH = canvas.width * (pageContentH / w);
                              const totalSlices = Math.ceil(canvas.height / sliceCanvasH);
                              for (let s = 0; s < totalSlices; s++) {
                                   const sliceCanvas = document.createElement("canvas");
                                   sliceCanvas.width = canvas.width;
                                   sliceCanvas.height = Math.min(sliceCanvasH, canvas.height - s * sliceCanvasH);
                                   const ctx = sliceCanvas.getContext("2d");
                                   ctx.fillStyle = "#fff";
                                   ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
                                   ctx.drawImage(canvas, 0, -s * sliceCanvasH);
                                   const sliceData = sliceCanvas.toDataURL("image/png");
                                   const sliceH = (sliceCanvas.height * w) / sliceCanvas.width;
                                   if (s > 0) pdf.addPage();
                                   pdf.addImage(sliceData, "PNG", margin, margin, w, sliceH);
                              }
                         }
                    }
               }

               const filename = `accounting-report-${store.from}-to-${store.to}.pdf`;
               pdf.save(filename);
               toast.success(t("pdf_generated") || "PDF generated");
          } catch (err) {
               console.error("[accounting-pdf]", err);
               toast.error(t("pdf_generation_failed") || "PDF generation failed");
          } finally {
               store.isGeneratingPdf = false;
               if (iframe) iframe.remove();
          }
     };

     // ── PDF-only headers/rows (functional components) ──────────────────────────
     const PdfHeaders = (props) => {
          const labelTotal = t("total") || "Total";
          const labelCount = t("count") || "Count";
          const labelName = t("name") || "Name";
          const cells = (cols) => cols.map((c) => h("th", { class: "ar-th", style: c.style || "" }, c.text));

          if (props.section === "invoices") {
               return cells([
                    { text: "#" },
                    { text: t("branch") || "Branch" },
                    { text: t("created_by") || "Created by" },
                    { text: t("date") || "Date" },
                    { text: t("patient_name") || "Patient" },
                    { text: t("referral") || "Referral" },
                    { text: t("contract") || "Contract" },
                    { text: t("tests") || "Tests" },
                    { text: t("subtotal") || "Subtotal", style: "text-align: end" },
                    { text: t("discount") || "Discount", style: "text-align: end" },
                    { text: labelTotal, style: "text-align: end" },
                    { text: t("paid") || "Paid", style: "text-align: end" },
                    { text: t("due") || "Due", style: "text-align: end" },
               ]);
          }
          if (props.section === "referrals") {
               return cells([
                    { text: labelName },
                    { text: labelCount, style: "text-align: end" },
                    { text: labelTotal, style: "text-align: end" },
                    { text: t("commission") || "Commission", style: "text-align: end" },
                    { text: t("profit") || "Profit", style: "text-align: end" },
               ]);
          }
          if (props.section === "patients") {
               return cells([
                    { text: t("code") || "Code" },
                    { text: labelName },
                    { text: labelTotal, style: "text-align: end" },
                    { text: t("patient_payment") || "Patient payment", style: "text-align: end" },
                    { text: t("paid") || "Paid", style: "text-align: end" },
                    { text: t("due") || "Due", style: "text-align: end" },
               ]);
          }
          // tests / cultures / packages / test_groups / contracts (3-col)
          return cells([
               { text: labelName },
               { text: labelCount, style: "text-align: end" },
               { text: labelTotal, style: "text-align: end" },
          ]);
     };

     const PdfRows = (props) => {
          return props.rows.map((row, i) => {
               if (props.section === "invoices") {
                    const itemsTable = row.items?.length
                         ? h(
                                 "table",
                                 { style: "width: 100%; border-collapse: collapse; font-size: 11px" },
                                 row.items.map((it) =>
                                      h("tr", null, [
                                           h("td", { style: "padding: 3px 6px; border-bottom: 1px solid #f1f5f9" }, it.name),
                                           h("td", { style: "padding: 3px 6px; border-bottom: 1px solid #f1f5f9; text-align: end; white-space: nowrap" }, formatNum(it.price)),
                                      ])
                                 )
                            )
                         : "";
                    return h("tr", { key: i, class: "ar-row" }, [
                         h("td", { class: "ar-td", style: "font-weight: 500" }, row.id),
                         h("td", { class: "ar-td" }, row.branch),
                         h("td", { class: "ar-td" }, row.created_by),
                         h("td", { class: "ar-td", style: "white-space: nowrap" }, row.date),
                         h("td", { class: "ar-td", style: "font-weight: 500" }, row.patient_name),
                         h("td", { class: "ar-td" }, row.referral_name),
                         h("td", { class: "ar-td" }, row.contract_name),
                         h("td", { class: "ar-td", style: "padding: 0" }, [itemsTable]),
                         h("td", { class: "ar-td", style: "text-align: end" }, formatNum(row.sub_total)),
                         h("td", { class: "ar-td", style: "text-align: end; color: #d97706" }, formatNum(row.discount)),
                         h("td", { class: "ar-td", style: "text-align: end; font-weight: 600" }, formatNum(row.total)),
                         h("td", { class: "ar-td", style: "text-align: end; color: #16a34a" }, formatNum(row.paid)),
                         h(
                              "td",
                              { class: "ar-td", style: `text-align: end; ${row.due > 0 ? "color: #dc2626; font-weight: 600" : "color: #94a3b8"}` },
                              formatNum(row.due)
                         ),
                    ]);
               }
               if (props.section === "referrals") {
                    return h("tr", { key: i, class: "ar-row" }, [
                         h("td", { class: "ar-td", style: "font-weight: 500" }, row.name),
                         h("td", { class: "ar-td", style: "text-align: end" }, row.count),
                         h("td", { class: "ar-td", style: "text-align: end" }, formatNum(row.total)),
                         h("td", { class: "ar-td", style: "text-align: end; color: #d97706" }, formatNum(row.commission)),
                         h("td", { class: "ar-td", style: "text-align: end; font-weight: 600; color: #15803d" }, formatNum(row.profit)),
                    ]);
               }
               if (props.section === "patients") {
                    return h("tr", { key: i, class: "ar-row" }, [
                         h("td", { class: "ar-td", style: "color: #64748b; font-weight: 500" }, row.code),
                         h("td", { class: "ar-td", style: "font-weight: 500" }, row.name),
                         h("td", { class: "ar-td", style: "text-align: end; font-weight: 600" }, formatNum(row.total)),
                         h("td", { class: "ar-td", style: "text-align: end" }, formatNum(row.patient_payment)),
                         h("td", { class: "ar-td", style: "text-align: end; color: #16a34a" }, formatNum(row.paid)),
                         h(
                              "td",
                              { class: "ar-td", style: `text-align: end; ${row.due > 0 ? "color: #dc2626; font-weight: 600" : "color: #94a3b8"}` },
                              formatNum(row.due)
                         ),
                    ]);
               }
               // 3-col rows
               return h("tr", { key: i, class: "ar-row" }, [
                    h("td", { class: "ar-td", style: "font-weight: 500" }, row.name),
                    h("td", { class: "ar-td", style: "text-align: end" }, row.count),
                    h("td", { class: "ar-td", style: "text-align: end; font-weight: 600" }, formatNum(row.total)),
               ]);
          });
     };

     // ── Section table component (shared by tab preview + PDF mirror) ───────────
     const iconPaths = {
          invoice: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
          beaker: "M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z",
          cube: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4",
          folder: "M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z",
          users: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
          user: "M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z",
          document: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
     };

     const SectionTable = (props, { slots }) => {
          const iconD = iconPaths[props.icon] || iconPaths.document;
          return h(
               "div",
               {
                    class:
                         "accounting-section bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden",
               },
               [
                    h(
                         "div",
                         {
                              class:
                                   "accounting-section-title border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white px-5 py-3 flex items-center gap-2",
                         },
                         [
                              h(
                                   "div",
                                   {
                                        class:
                                             "w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center",
                                   },
                                   [
                                        h(
                                             "svg",
                                             {
                                                  class: "w-4 h-4 text-primary-600",
                                                  fill: "none",
                                                  viewBox: "0 0 24 24",
                                                  stroke: "currentColor",
                                                  "stroke-width": "2",
                                             },
                                             [h("path", { "stroke-linecap": "round", "stroke-linejoin": "round", d: iconD })]
                                        ),
                                   ]
                              ),
                              h("h3", { class: "text-base font-semibold text-slate-800" }, props.title),
                         ]
                    ),
                    h("div", { class: "overflow-x-auto" }, [
                         h("table", { class: "w-full text-sm" }, [
                              h("thead", null, [h("tr", { class: "bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200" }, slots.headers?.())]),
                              h("tbody", { class: "divide-y divide-slate-100" }, slots.rows?.()),
                         ]),
                    ]),
               ]
          );
     };

     onMounted(async () => {
          // Load filter dropdown options in parallel — fire and forget; auto-load
          // doesn't depend on these completing
          Promise.allSettled([
               usersStore.GetRecords?.(),
               labsStore.collectors?.(),
               referralsStore.GetRecords?.(),
               contractsStore.GetRecords?.(),
          ]).catch(() => {});

          // Auto-load today's report — silent (no toast)
          await loadReport(false);
     });
</script>

<style scoped>
     .ar-th {
          background: #f8fafc;
          font-weight: 600;
          padding: 10px 12px;
          border-bottom: 1px solid #e2e8f0;
          text-align: start;
          font-size: 13px;
          color: #475569;
     }
     .ar-td {
          padding: 10px 12px;
          font-size: 13px;
          color: #1e293b;
     }
     .ar-row:hover {
          background: #f8fafc;
     }
     .text-end {
          text-align: end;
     }
     .pdf-only {
          position: fixed;
          top: 0;
          left: -10000px;
          width: 280mm;
          visibility: hidden;
          pointer-events: none;
     }
     .only-print {
          display: none;
     }
     :global(.dark-mode) .ar-th {
          background: rgb(30 41 59);
          color: rgb(203 213 225);
          border-color: rgb(51 65 85);
     }
     :global(.dark-mode) .ar-td {
          color: rgb(226 232 240);
     }
     :global(.dark-mode) .ar-row:hover {
          background: rgb(30 41 59 / 0.5);
     }
</style>
