<template>
  <div class="min-h-screen" :style="lang === 'en' ? 'direction:ltr' : 'direction: rtl'">

    <!-- Auto-save status pill -->
    <div
      v-if="autoSaveReady"
      class="fixed bottom-4 end-4 z-50 flex items-center gap-2 px-3 py-2 rounded-full text-xs font-medium shadow-lg transition-all"
      :class="{
        'bg-slate-800 text-slate-200': autoSaveState === 'saving',
        'bg-green-600 text-white': autoSaveState === 'saved',
        'bg-red-600 text-white': autoSaveState === 'error',
      }"
    >
      <svg v-if="autoSaveState === 'saving'" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
      <svg v-else-if="autoSaveState === 'saved'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
      <svg v-else-if="autoSaveState === 'error'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" /></svg>
      <span>
        {{ autoSaveState === 'saving' ? (t('saving') || 'جاري الحفظ...') : autoSaveState === 'saved' ? (t('saved') || 'تم الحفظ') : (t('save_failed') || 'فشل الحفظ') }}
      </span>
    </div>

    <!-- ==================== TOP BAR ==================== -->
    <div class="relative bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 sticky top-0 z-30 shadow-lg shadow-slate-900/20">
      <!-- Decorative background (clipped, won't affect dropdowns) -->
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-20 -end-20 w-72 h-72 bg-primary-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-16 -start-16 w-56 h-56 bg-primary-600/15 rounded-full blur-2xl"></div>
      </div>

      <div class="relative flex items-center justify-between gap-3 px-4 lg:px-6 py-3">
        <!-- Title cluster -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            :aria-label="t('back') || 'Back'"
            :title="t('back') || 'Back'"
            class="w-10 h-10 flex items-center justify-center rounded-xl text-white/80 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-white/20 shrink-0"
          >
            <svg class="w-5 h-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </button>
          <div class="min-w-0">
            <p class="text-[10px] sm:text-xs text-primary-300/80 font-semibold uppercase tracking-widest">{{ t("medical_reports") }}</p>
            <h1 class="text-base sm:text-lg lg:text-xl font-bold text-white truncate">{{ t("update_result") }}</h1>
          </div>
        </div>

        <!-- Action cluster -->
        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap justify-end">
          <!-- Print -->
          <div class="relative">
            <button
              type="button"
              @click.stop="togglePrintMenu"
              :title="t('print') || 'Print'"
              :aria-label="t('print') || 'Print'"
              class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-2 text-xs sm:text-sm font-medium text-white/90 hover:text-white bg-white/5 hover:bg-white/15 border border-white/10 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-white/20"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              <span class="hidden sm:inline">{{ t("print") || "Print" }}</span>
              <svg class="w-3 h-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div v-if="printMenuOpen" class="absolute end-0 z-[60] mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
              <button @click="openPrintAction(false)" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                {{ t("without_background") || "Without Background" }}
              </button>
              <button @click="openPrintAction(true)" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                {{ t("with_background") || "With Background" }}
              </button>
            </div>
          </div>
          <!-- Download PDF -->
          <div class="relative">
            <button
              type="button"
              @click.stop="toggleDownloadMenu"
              :title="t('download_pdf') || 'Download PDF'"
              :aria-label="t('download_pdf') || 'Download PDF'"
              :disabled="downloadInProgress"
              class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-2 text-xs sm:text-sm font-medium text-white bg-gradient-to-br from-blue-500/80 to-blue-600/80 hover:from-blue-500 hover:to-blue-600 border border-blue-400/30 rounded-lg shadow-sm shadow-blue-500/30 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-4 focus:ring-blue-400/40"
            >
              <svg v-if="!downloadInProgress" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span class="hidden sm:inline">{{ t("download_pdf") || "PDF" }}</span>
              <svg class="w-3 h-3 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div v-if="downloadMenuOpen" class="absolute end-0 z-[60] mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
              <button @click="openDownloadAction(false)" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                {{ t("without_background") || "Without Background" }}
              </button>
              <button @click="openDownloadAction(true)" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                {{ t("with_background") || "With Background" }}
              </button>
            </div>
          </div>
          <!-- WhatsApp -->
          <div class="relative">
            <button
              type="button"
              @click.stop="toggleWhatsappMenu"
              :title="t('whatsapp') || 'WhatsApp'"
              :aria-label="t('whatsapp') || 'WhatsApp'"
              class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-2 text-xs sm:text-sm font-medium text-white bg-gradient-to-br from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 rounded-lg transition-colors cursor-pointer shadow-sm shadow-green-500/30 focus:outline-none focus:ring-4 focus:ring-green-300/40"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
              </svg>
              <span class="hidden sm:inline">{{ t("whatsapp") || "WhatsApp" }}</span>
              <svg class="w-3 h-3 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <div v-if="whatsappMenuOpen" class="absolute end-0 z-[60] mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
              <button @click="openWhatsAppAction(false)" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654z"/></svg>
                {{ t("without_background") || "Without Background" }}
              </button>
              <button @click="openWhatsAppAction(true)" class="w-full px-4 py-2.5 text-start text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                {{ t("with_background") || "With Background" }}
              </button>
            </div>
          </div>

          <!-- Divider -->
          <span class="hidden lg:block w-px h-7 bg-white/15 mx-1" aria-hidden="true"></span>

          <!-- Cancel -->
          <button
            type="button"
            @click="goBack"
            class="hidden sm:inline-flex px-3 py-2 text-xs sm:text-sm font-medium text-white/80 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-white/20"
          >
            {{ t("cancel") }}
          </button>
          <!-- Save (primary) -->
          <button
            type="button"
            @click="update"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs sm:text-sm font-semibold text-white bg-gradient-to-br from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 rounded-lg shadow-sm shadow-primary-500/40 transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-primary-400/40"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ t("save") }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex items-center justify-center min-h-[60vh]"
    >
      <div class="text-center">
        <svg
          class="animate-spin h-10 w-10 text-primary-600 mx-auto mb-4"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-slate-500 text-sm">{{ t("loading") }}...</p>
      </div>
    </div>

    <!-- Main Content -->
    <template v-else>
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        <!-- ==================== PATIENT SEARCH + ADD ==================== -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 flex flex-col sm:flex-row gap-2">
          <div class="relative flex-1" @click.stop>
            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
              </svg>
            </div>
            <input
              type="text"
              v-model="patientSearch"
              @input="onPatientSearchInput"
              @focus="onPatientSearchFocus"
              @blur="setTimeout(() => (patientSearchOpen = false), 200)"
              :placeholder="t('search_pationt') || t('search')"
              class="w-full ps-9 pe-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
            />
            <div
              v-if="patientSearchOpen && (patientSearchResults.length || patientSearchLoading)"
              class="absolute z-30 top-full mt-1 start-0 end-0 bg-white border border-slate-200 rounded-lg shadow-lg max-h-80 overflow-y-auto"
            >
              <div v-if="patientSearchLoading" class="px-4 py-3 text-xs text-slate-400">{{ t("loading") || "..." }}</div>
              <button
                v-for="p in patientSearchResults"
                :key="p.id"
                type="button"
                @click="selectPatientFromSearch(p)"
                class="w-full text-start px-4 py-2.5 hover:bg-primary-50 transition-colors border-b border-slate-50 last:border-b-0 flex items-center justify-between gap-3"
              >
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-slate-800 truncate">{{ p.name }}</p>
                  <p class="text-xs text-slate-500 truncate" dir="rtl">
                    <span v-if="p.code">#{{ p.code }}</span>
                    <span v-if="p.phone" class="ms-2">{{ p.phone }}</span>
                  </p>
                </div>
                <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </button>
              <div v-if="!patientSearchLoading && !patientSearchResults.length" class="px-4 py-3 text-xs text-slate-400">{{ t("no_records_found") || "—" }}</div>
            </div>
          </div>
          <button
            type="button"
            @click="openAddPatient"
            class="px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 shadow-sm shadow-emerald-500/20 inline-flex items-center justify-center gap-2 transition-all focus:outline-none focus:ring-4 focus:ring-emerald-300/40"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{{ t("add_patient") }}</span>
          </button>
          <button
            type="button"
            v-if="updateResultRecord?.patient?.id"
            @click="addInvoiceForPatient"
            :title="t('add_invoice_for_patient') || 'فاتورة جديدة لنفس المريض'"
            class="px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-gradient-to-br from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 shadow-sm shadow-primary-500/20 inline-flex items-center justify-center gap-2 transition-all focus:outline-none focus:ring-4 focus:ring-primary-300/40"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>{{ t("add_invoice_for_patient") || "فاتورة جديدة" }}</span>
          </button>
        </div>

        <!-- ==================== PATIENT INFO (full width) ==================== -->
        <div class="grid grid-cols-1 gap-4">

          <!-- Patient Info Banner — full width -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-5">
              <div class="flex items-start gap-4">
                <!-- Avatar -->
                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-xl shrink-0 shadow-md" aria-hidden="true">
                  {{ updateResultRecord?.patient?.name?.charAt(0)?.toUpperCase() || '?' }}
                </div>
                <!-- Info Grid + status badge -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="min-w-0">
                      <h2 class="text-base font-bold text-slate-900 truncate">{{ updateResultRecord?.patient?.name || '---' }}</h2>
                      <p v-if="updateResultRecord?.patient?.code || updateResultRecord?.patient?.title" class="text-xs text-slate-500">
                        <span v-if="updateResultRecord?.patient?.code">{{ updateResultRecord.patient.code }}</span>
                        <span v-if="updateResultRecord?.patient?.code && updateResultRecord?.patient?.title" class="mx-1 text-slate-300">•</span>
                        <span v-if="updateResultRecord?.patient?.title">{{ updateResultRecord.patient.title }}</span>
                      </p>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                      <!-- Status pill -->
                      <span
                        v-if="updateResultRecord?.is_done !== undefined"
                        :class="[
                          'inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-full whitespace-nowrap ring-1 ring-inset',
                          updateResultRecord.is_done
                            ? 'bg-green-50 text-green-700 ring-green-200'
                            : 'bg-amber-50 text-amber-700 ring-amber-200'
                        ]"
                      >
                        <span class="relative flex h-2 w-2 shrink-0">
                          <span :class="['absolute inline-flex h-full w-full rounded-full opacity-60 animate-ping', updateResultRecord.is_done ? 'bg-green-400' : 'bg-amber-400']"></span>
                          <span :class="['relative inline-flex rounded-full h-2 w-2', updateResultRecord.is_done ? 'bg-green-500' : 'bg-amber-500']"></span>
                        </span>
                        {{ updateResultRecord.is_done ? t("done") : t("pendening") }}
                      </span>

                      <!-- Edit -->
                      <button
                        v-if="updateResultRecord?.patient?.id"
                        type="button"
                        @click="openPatientEdit"
                        :aria-label="t('edit_patient_info') || 'تعديل معلومات المريض'"
                        :title="t('edit_patient_info') || 'تعديل معلومات المريض'"
                        class="inline-flex items-center gap-2 px-3 h-9 rounded-lg text-xs font-semibold text-white bg-gradient-to-br from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 shadow-sm shadow-amber-500/20 transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-amber-300/40 shrink-0 whitespace-nowrap"
                      >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>{{ t("edit_patient_info") || "تعديل معلومات المريض" }}</span>
                      </button>

                      <!-- Medical history CTA -->
                      <button
                        v-if="updateResultRecord?.patient?.id"
                        type="button"
                        @click="openPatientMedicalHistory"
                        class="group inline-flex items-center gap-2 px-3.5 h-9 rounded-lg text-xs font-semibold text-white bg-gradient-to-br from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 shadow-sm shadow-primary-500/20 transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-primary-300/40 whitespace-nowrap shrink-0"
                      >
                        <svg class="w-4 h-4 opacity-90 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ t("view_medical_history") || "عرض التاريخ الطبي" }}</span>
                      </button>
                      <!-- Patient lab history (tests + cultures results across visits) -->
                      <button
                        v-if="updateResultRecord?.patient?.id"
                        type="button"
                        @click="openPatientHistory"
                        class="inline-flex items-center gap-2 px-3.5 h-9 rounded-lg text-xs font-semibold text-white bg-gradient-to-br from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 shadow-sm shadow-indigo-500/20 transition-colors cursor-pointer focus:outline-none focus:ring-4 focus:ring-indigo-300/40 whitespace-nowrap shrink-0"
                      >
                        <svg class="w-4 h-4 opacity-90 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>{{ t("pationtHistory") || "سجل المريض" }}</span>
                      </button>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2.5">
                    <div v-if="updateResultRecord?.patient?.age || updateResultRecord?.patient?.gender">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("age") }} / {{ t("gender") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate">
                        <template v-if="updateResultRecord?.patient?.age">{{ updateResultRecord.patient.age }}{{ updateResultRecord.patient.age_unit }}</template>
                        <template v-if="updateResultRecord?.patient?.age && updateResultRecord?.patient?.gender"> / </template>
                        <template v-if="updateResultRecord?.patient?.gender">{{ updateResultRecord.patient.gender }}</template>
                      </p>
                    </div>
                    <div v-if="updateResultRecord?.patient?.phone">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("phone") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate" dir="rtl">{{ updateResultRecord.patient.phone }}</p>
                    </div>
                    <div v-if="updateResultRecord?.patient?.email">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("email") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate" dir="rtl">{{ updateResultRecord.patient.email }}</p>
                    </div>
                    <div v-if="updateResultRecord?.patient?.dob">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("dob") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ updateResultRecord.patient.dob }}</p>
                    </div>
                    <div v-if="updateResultRecord?.patient?.national_id_no">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("national_id_no") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate font-mono">{{ updateResultRecord.patient.national_id_no }}</p>
                    </div>
                    <div v-if="updateResultRecord?.patient?.address">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("address") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ updateResultRecord.patient.address }}</p>
                    </div>
                    <div v-if="updateResultRecord?.barcode">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("barcode") }}</p>
                      <p class="text-sm font-semibold text-slate-800 font-mono truncate">{{ updateResultRecord.barcode }}</p>
                    </div>
                    <div v-if="updateResultRecord?.registration_date">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("registration_date") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ dateFormat(updateResultRecord.registration_date) }}</p>
                    </div>
                    <div v-if="updateResultRecord?.referral?.name">
                      <p class="text-xs text-slate-400 font-medium mb-0.5">{{ t("doctor") }}</p>
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ updateResultRecord.referral.name }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
        <!-- End: Patient row -->

        <!-- ==================== TEST TABS (full width col-12, only when activeSection === 'tests') ==================== -->
        <div v-if="activeSection === 'tests' && updateResultRecord.tests?.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm px-3 py-2">
          <button
            type="button"
            @click="openInvoiceEdit"
            class="w-full mb-2 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-gradient-to-br from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 shadow-sm shadow-primary-500/20 inline-flex items-center justify-center gap-2 transition-all focus:outline-none focus:ring-4 focus:ring-primary-300/40"
            :title="t('add_test')"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{{ t("add_test") }}</span>
          </button>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="allTabTests.length"
              type="button"
              @click="activeTestTab = 'all'"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2',
                activeTestTab === 'all'
                  ? 'bg-primary-100 text-primary-800 shadow-sm ring-1 ring-primary-200'
                  : 'text-slate-600 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <span>{{ t("all") || "All" }} ({{ allTabTests.length }})</span>
              <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <button
              type="button"
              v-for="(item, index) in updateResultRecord.tests"
              :key="'tt-' + index"
              @click="activeTestTab = index"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2',
                activeTestTab === index
                  ? 'bg-primary-50 text-primary-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <span>{{ item.name }}<span v-if="item.shortcut" class="text-[10px] text-slate-400 font-mono ms-1">({{ item.shortcut }})</span></span>
              <span v-if="item.is_done" class="w-2 h-2 bg-green-500 rounded-full shrink-0"></span>
            </button>
          </div>
        </div>

        <!-- ==================== TEST GROUPS TABS (full width col-12, only when activeSection === 'test_groups') ==================== -->
        <div v-if="activeSection === 'test_groups' && updateResultRecord.test_groups?.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm px-3 py-2">
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              v-for="(item, index) in updateResultRecord.test_groups"
              :key="'tgt-' + index"
              @click="activeTestGroupTab = index"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                activeTestGroupTab === index
                  ? 'bg-amber-50 text-amber-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              {{ item.group_name }}
            </button>
          </div>
        </div>

        <!-- ==================== SECTION NAV + CONTENT (3/9 split) ==================== -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

          <!-- Section Navigation Tabs — col-2 sidebar -->
          <div class="lg:col-span-2 lg:order-1 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="flex overflow-x-auto lg:flex-col lg:overflow-visible px-2 py-2 gap-1">
            <button
              v-if="updateResultRecord.tests?.length > 0"
              type="button"
              @click="activeSection = 'tests'"
              :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all lg:w-full lg:justify-start',
                activeSection === 'tests'
                  ? 'bg-primary-50 text-primary-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
              </svg>
              {{ t("tests") }}
              <span class="px-1.5 py-0.5 text-xs rounded-md bg-primary-100 text-primary-600">{{ updateResultRecord.tests?.length || 0 }}</span>
            </button>
            <button
              v-if="updateResultRecord.packages?.length > 0"
              type="button"
              @click="activeSection = 'packages'"
              :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all lg:w-full lg:justify-start',
                activeSection === 'packages'
                  ? 'bg-purple-50 text-purple-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
              {{ t("packages") }}
              <span class="px-1.5 py-0.5 text-xs rounded-md bg-purple-100 text-purple-600">{{ updateResultRecord.packages?.length || 0 }}</span>
            </button>
            <button
              v-if="updateResultRecord.test_groups?.length > 0"
              type="button"
              @click="activeSection = 'test_groups'"
              :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all lg:w-full lg:justify-start',
                activeSection === 'test_groups'
                  ? 'bg-amber-50 text-amber-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
              {{ t("test-groups") }}
              <span class="px-1.5 py-0.5 text-xs rounded-md bg-amber-100 text-amber-600">{{ updateResultRecord.test_groups?.length || 0 }}</span>
            </button>
            <button
              v-if="updateResultRecord.cultures?.length > 0"
              type="button"
              @click="activeSection = 'cultures'"
              :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all lg:w-full lg:justify-start',
                activeSection === 'cultures'
                  ? 'bg-rose-50 text-rose-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
              </svg>
              {{ t("cultures") }}
              <span class="px-1.5 py-0.5 text-xs rounded-md bg-rose-100 text-rose-600">{{ updateResultRecord.cultures?.length || 0 }}</span>
            </button>
            <button
              type="button"
              @click="activeSection = 'extras'"
              :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all lg:w-full lg:justify-start',
                activeSection === 'extras'
                  ? 'bg-slate-100 text-slate-700 shadow-sm'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              ]"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
              {{ t("attachments") }} & {{ t("comments") || "تعليقات" }}
            </button>
            </div>
          </div>

          <!-- ==================== CONTENT (col-9) ==================== -->
          <div class="lg:col-span-10 lg:order-2 space-y-4">

        <!-- ==================== TESTS SECTION (tabs moved out to full width above) ==================== -->
        <div v-if="activeSection === 'tests' && updateResultRecord.tests?.length > 0" class="space-y-4">
          <!-- All-tests Table View -->
          <div v-if="activeTestTab === 'all'">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                      <th class="px-3 py-2.5 text-start text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("test") || "Test" }}</th>
                      <th class="px-3 py-2.5 text-start text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("Result") }}</th>
                      <th class="px-3 py-2.5 text-start text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("Unit") }}</th>
                      <th class="px-3 py-2.5 text-start text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("tests-reference-ranges") }}</th>
                      <th class="px-3 py-2.5 text-start text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("status") || "Status" }}</th>
                      <th class="px-3 py-2.5 text-start text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("Test_Group_Comment") }}</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("done") }}</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ t("actions") || "Actions" }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="(item, index) in allTabTests" :key="'all-' + index" class="hover:bg-slate-50/50">
                      <td class="px-3 py-2.5 align-top">
                        <p class="text-sm font-medium text-slate-800">{{ item.name }}</p>
                        <p v-if="item.shortcut" class="text-[10px] text-slate-400 font-mono">{{ item.shortcut }}</p>
                      </td>
                      <td class="px-3 py-2.5 align-top">
                        <input
                          v-if="Number(item.result_type_id_fk) === 1 || Number(item.result_type_id_fk) === 2"
                          type="text" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]*"
                          v-model="item.result"
                          @input="autoDetectStatus(item)"
                          @change="autoDetectStatus(item)"
                          @keydown.enter.prevent="confirmAndNext(item, index)"
                          :data-test-row="index"
                          class="w-32 px-2 py-1.5 text-sm border border-slate-200 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                        <input
                          v-else-if="Number(item.result_type_id_fk) === 3 || !item.result_type_id_fk"
                          type="text"
                          v-model="item.result"
                          @input="autoDetectStatus(item)"
                          @change="autoDetectStatus(item)"
                          @keydown.enter.prevent="confirmAndNext(item, index)"
                          :data-test-row="index"
                          class="w-32 px-2 py-1.5 text-sm border border-slate-200 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                        <select
                          v-else-if="Number(item.result_type_id_fk) === 4"
                          v-model="item.result"
                          @keydown.enter.prevent="confirmAndNext(item, index)"
                          :data-test-row="index"
                          class="w-32 px-2 py-1.5 text-sm border border-slate-200 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                        >
                          <option value="">{{ t("select") }}</option>
                          <option v-for="opt in item.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                      </td>
                      <td class="px-3 py-2.5 align-top text-xs text-slate-600">{{ item.unit ?? "—" }}</td>
                      <td class="px-3 py-2.5 align-top text-xs text-blue-600">
                        <div v-if="displayRanges(item).length" class="space-y-0.5">
                          <div v-for="range in displayRanges(item)" :key="range.test_reference_range_id || range.id" style="white-space: pre-line;">
                            <span v-if="range.notes">{{ range.notes }}</span>
                            <span v-else>{{ range.from }} - {{ range.to }}</span>
                          </div>
                        </div>
                        <span v-else class="text-slate-400">—</span>
                      </td>
                      <td class="px-3 py-2.5 align-top">
                        <select
                          v-model="item.result_status_id_fk"
                          class="w-28 px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                        >
                          <option value="">{{ t("select") }}</option>
                          <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                      </td>
                      <td class="px-3 py-2.5 align-top">
                        <input
                          type="text"
                          v-model="item.comment"
                          :placeholder="t('enter_comment')"
                          class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                      </td>
                      <td class="px-3 py-2.5 align-top text-center">
                        <label class="inline-flex cursor-pointer">
                          <input type="checkbox" v-model="item.is_done" class="sr-only peer" />
                          <div class="relative w-10 h-6 bg-slate-200 rounded-full peer-checked:bg-primary-600 transition-colors">
                            <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                          </div>
                        </label>
                      </td>
                      <td class="px-3 py-2.5 align-top text-center">
                        <button
                          type="button"
                          @click="openTestEdit(item)"
                          :title="t('edit_test') || 'Edit test'"
                          :aria-label="t('edit_test') || 'Edit test'"
                          class="inline-flex items-center justify-center w-8 h-8 rounded-md text-amber-600 hover:text-white hover:bg-amber-500 border border-amber-200 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-300"
                        >
                          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Test Content -->
          <div v-else v-for="(item, index) in updateResultRecord.tests" :key="index" v-show="activeTestTab === index">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <!-- Done + Last Result toggles (always visible) -->
              <div v-if="item.sub_tests?.length > 0 && selectedTemplate" class="px-6 pt-5 pb-2 flex flex-wrap gap-6">
                <label class="flex items-center gap-3 cursor-pointer">
                  <div class="relative">
                    <input type="checkbox" v-model="item.is_done" class="sr-only peer" />
                    <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-primary-600 peer-focus:ring-2 peer-focus:ring-primary-300 transition-colors"></div>
                    <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                  </div>
                  <span class="text-sm font-medium text-slate-700">{{ t("done") }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <div class="relative">
                    <input type="checkbox" v-model="item.last_result" class="sr-only peer" />
                    <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-primary-600 peer-focus:ring-2 peer-focus:ring-primary-300 transition-colors"></div>
                    <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                  </div>
                  <span class="text-sm font-medium text-slate-700">{{ t("last_result") }}</span>
                </label>
              </div>
              <!-- Dynamic Template -->
              <div v-if="item.sub_tests?.length > 0 && selectedTemplate" class="p-6" style="direction: ltr;">
                <!-- Template already sanitized in loadTemplate() with input/select allowlist -->
                <div v-html="selectedTemplate" class="dynamic-template"></div>
              </div>

              <!-- Regular Form -->
              <div v-else class="p-6 space-y-5">
                <!-- Row 1: Toggles -->
                <div class="flex flex-wrap gap-6">
                  <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                      <input type="checkbox" v-model="item.is_done" class="sr-only peer" />
                      <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-primary-600 peer-focus:ring-2 peer-focus:ring-primary-300 transition-colors"></div>
                      <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                    </div>
                    <span class="text-sm font-medium text-slate-700">{{ t("done") }}</span>
                  </label>
                  <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                      <input type="checkbox" v-model="item.last_result" class="sr-only peer" />
                      <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-primary-600 peer-focus:ring-2 peer-focus:ring-primary-300 transition-colors"></div>
                      <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                    </div>
                    <span class="text-sm font-medium text-slate-700">{{ t("last_result") }}</span>
                  </label>
                </div>

                <!-- Row 2: Result Type + Result -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("status") || t("Result_Type") }}</label>
                    <select
                      v-model="item.result_status_id_fk"
                      class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white transition-all"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="status in resultStatus" :key="status.value" :value="status.value">
                        {{ status.label }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Result") }}</label>
                    <input
                      v-if="item.result_type_id_fk === 1 || item.result_type_id_fk === 2"
                      type="text"
                      inputmode="decimal"
                      pattern="-?[0-9]*\.?[0-9]*"
                      v-model="item.result"
                      @input="autoDetectStatus(item)"
                      @keydown.enter="item.is_done = true"
                      class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
                    />
                    <input
                      v-else-if="item.result_type_id_fk === 3 || !item.result_type_id_fk"
                      type="text"
                      v-model="item.result"
                      @input="autoDetectStatus(item)"
                      @keydown.enter="item.is_done = true"
                      class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
                    />
                    <select
                      v-else-if="item.result_type_id_fk === 4"
                      v-model="item.result"
                      class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white transition-all"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="opt in item.selection_type_options" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                  </div>
                </div>

                <!-- Reference Ranges -->
                <div v-if="item.test_reference_ranges?.length" class="flex items-start gap-3 p-3.5 bg-blue-50/70 border border-blue-100 rounded-lg">
                  <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-xs font-semibold text-blue-700 mb-1">{{ t("tests-reference-ranges") }}</p>
                    <div class="space-y-0.5">
                      <span v-for="range in item.test_reference_ranges" :key="range.test_reference_range_id || range.id" class="block text-sm text-blue-600">
                        <div v-if="range.notes">
                          <p v-for="(line, idx) in range.notes.split('\n')" :key="idx">{{ line }}</p>
                        </div>
                        <p v-else>
                          {{ range.from }} - {{ range.to }}
                          <span v-if="range.gender && range.gender !== 'Both'" class="text-blue-400 text-xs">({{ range.gender }})</span>
                          <span v-if="range.age_from != null" class="text-blue-400 text-xs">({{ range.age_from }}-{{ range.age_to }} {{ range.age_unit }})</span>
                        </p>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Comment -->
                <div>
                  <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Test_Group_Comment") }}</label>
                  <input
                    type="text"
                    v-model="item.comment"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
                    :placeholder="t('enter_comment')"
                  />
                </div>

                <!-- Unit & Price chips -->
                <div class="flex items-center gap-3">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-600 rounded-md text-xs font-medium">
                    {{ t("Unit") }}: {{ item.unit ?? "---" }}
                  </span>
                  <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-md text-xs font-medium">
                    {{ t("Original_Price") }}: {{ item.price ?? 0 }}
                  </span>
                </div>
              </div>

              <!-- Result Comments -->
              <div class="px-6 pb-6 pt-2 border-t border-slate-100 space-y-2">
                <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Result_Comments") }}</label>
                <div v-if="updateResultRecord.result_comments_tests?.length > 0" class="flex flex-wrap gap-1.5">
                  <button
                    v-for="comment in updateResultRecord.result_comments_tests"
                    :key="comment"
                    type="button"
                    @click="updateResultRecord.tests_comment = updateResultRecord.tests_comment ? updateResultRecord.tests_comment + ', ' + comment : comment"
                    class="px-2.5 py-1 text-xs bg-primary-50 text-primary-700 border border-primary-200 rounded-md hover:bg-primary-100 transition-colors"
                  >
                    {{ comment }}
                  </button>
                </div>
                <textarea
                  v-model="updateResultRecord.tests_comment"
                  rows="3"
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none resize-none transition-all"
                  :placeholder="t('enter_comment')"
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== PACKAGES SECTION ==================== -->
        <div v-if="activeSection === 'packages' && updateResultRecord.packages?.length > 0" class="space-y-4">
          <!-- Package Tabs -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                v-for="(item, index) in updateResultRecord.packages"
                :key="index"
                @click="activePackageTab = index"
                :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                  activePackageTab === index
                    ? 'bg-purple-50 text-purple-700 shadow-sm'
                    : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
                ]"
              >
                {{ item.name }}
              </button>
            </div>
          </div>

          <div v-for="(pkg, pIndex) in updateResultRecord.packages" :key="pIndex" v-show="activePackageTab === pIndex">
            <!-- Custom (is_special_test) tests inside a package render their own HTML
                 template, exactly like they do standalone and inside a test group. -->
            <template v-for="(test, tIndex) in pkg.tests" :key="'pkg-tmpl-' + tIndex">
              <div v-if="isCustomTemplateTest(test)" class="bg-white rounded-xl border border-slate-200 shadow-sm mb-4">
                <div class="px-6 py-3 border-b border-slate-100 bg-gradient-to-r from-rose-50 to-pink-50 flex items-center justify-between">
                  <span class="text-sm font-semibold text-slate-700">
                    {{ test.name }}
                    <span v-if="test.shortcut" class="text-xs text-slate-400 font-mono ms-1">({{ test.shortcut }})</span>
                  </span>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="test.is_done" class="w-4 h-4 text-primary-600 rounded" />
                    <span class="text-xs text-slate-600">{{ t("done") }}</span>
                  </label>
                </div>
                <div class="p-6" style="direction: ltr;">
                  <div v-html="getGroupTemplateHtml(test, pIndex, tIndex, 'packages')" class="dynamic-template"></div>
                </div>
              </div>
            </template>

            <div v-if="packageVisibleTests(pkg).length || pkg.cultures?.length" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <div class="divide-y divide-slate-100">
                <!-- Test rows. Tests coming from a whole test group attached to the
                     package carry test_group_name — a caption row is emitted the first
                     time each group appears so the tech sees which group a test is from. -->
                <template
                  v-for="(test, tIndex) in packageVisibleTests(pkg).filter(t => !isPkgGroupFormulaTarget(pkg, t, pIndex))"
                  :key="'test-' + tIndex"
                >
                <div
                  v-if="test.test_group_name && test.test_group_name !== pkg.tests.filter(t => !isFormulaTarget(pkg, t))[tIndex - 1]?.test_group_name"
                  class="flex items-center gap-2 px-4 py-2 bg-amber-50/70 border-y border-amber-100"
                >
                  <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                  <span class="text-xs font-semibold text-amber-700">{{ test.test_group_name }}</span>
                </div>
                <div
                  data-pkg-test-row
                  class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 hover:bg-slate-50/50 transition-colors"
                >
                  <div class="flex items-center gap-3 flex-1 min-w-0">
                    <span
                      class="w-8 h-8 rounded-md text-xs font-bold flex items-center justify-center shrink-0"
                      :class="test.test_group_name ? 'bg-amber-50 text-amber-600' : 'bg-purple-50 text-purple-600'"
                    >
                      {{ tIndex + 1 }}
                    </span>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-slate-800 truncate">
                        {{ test.name }}
                        <span v-if="test.shortcut" class="text-xs text-slate-400 font-mono ms-1">({{ test.shortcut }})</span>
                      </p>
                      <p class="text-xs text-slate-400">{{ t("price") }}: {{ test.price ?? 0 }}</p>
                    </div>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs shrink-0 sm:min-w-[140px] sm:max-w-[220px]">
                    <span class="text-slate-500">{{ t("Unit") }}: <span class="text-slate-700">{{ test.unit || "—" }}</span></span>
                    <div class="text-blue-600 space-y-0.5">
                      <template v-if="displayRanges(test).length">
                        <div v-for="range in displayRanges(test)" :key="range.test_reference_range_id || range.id" style="white-space: pre-line;">
                          <template v-if="range.notes">{{ range.notes }}</template>
                          <template v-else>{{ range.from }} - {{ range.to }}</template>
                        </div>
                      </template>
                      <span v-else class="text-slate-400">—</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <input
                      v-if="Number(test.result_type_id_fk) === 1 || Number(test.result_type_id_fk) === 2"
                      type="text"
                      inputmode="decimal"
                      pattern="-?[0-9]*\.?[0-9]*"
                      v-model="test.result"
                      @input="autoDetectStatus(test); evalPkgFormulas(pkg, pIndex)"
                      @change="autoDetectStatus(test)"
                      @keydown.enter.prevent="confirmAndNextPackage(test, $event)"
                      data-pkg-row
                      class="w-28 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                      :placeholder="t('Result')"
                    />
                    <select
                      v-else-if="Number(test.result_type_id_fk) === 4"
                      v-model="test.result"
                      @change="evalPkgFormulas(pkg, pIndex)"
                      @keydown.enter.prevent="confirmAndNextPackage(test, $event)"
                      data-pkg-row
                      class="w-36 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="opt in (test.selction_type_options || test.selection_type_options || [])" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <input
                      v-else
                      type="text"
                      v-model="test.result"
                      @input="autoDetectStatus(test); evalPkgFormulas(pkg, pIndex)"
                      @change="autoDetectStatus(test)"
                      @keydown.enter.prevent="confirmAndNextPackage(test, $event)"
                      data-pkg-row
                      class="w-28 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                      :placeholder="t('Result')"
                    />
                    <select
                      v-if="Number(test.result_type_id_fk) !== 3"
                      v-model="test.result_status_id_fk"
                      class="w-36 px-2.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <label class="flex items-center gap-1.5 px-2.5 py-2 bg-slate-50 rounded-lg cursor-pointer">
                      <input type="checkbox" v-model="test.is_done" class="w-3.5 h-3.5 text-primary-600 rounded border-slate-300" />
                      <span class="text-xs text-slate-500">{{ t("done") }}</span>
                    </label>
                  </div>
                </div>
                </template>
                <!-- Culture rows -->
                <div
                  v-for="(culture, cIndex) in pkg.cultures"
                  :key="'culture-' + cIndex"
                  class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 bg-purple-50/30 hover:bg-purple-50/50 transition-colors"
                >
                  <div class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="w-8 h-8 rounded-md bg-pink-50 text-pink-600 text-xs font-bold flex items-center justify-center shrink-0">
                      C{{ cIndex + 1 }}
                    </span>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-slate-800 truncate">{{ culture.name }}</p>
                      <p class="text-xs text-slate-400">{{ t("price") }}: {{ culture.price ?? 0 }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <input
                      type="text"
                      v-model="culture.result"
                      @keydown.enter="culture.is_done = true"
                      class="w-28 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                      :placeholder="t('Result')"
                    />
                    <label class="flex items-center gap-1.5 px-2.5 py-2 bg-slate-50 rounded-lg cursor-pointer">
                      <input type="checkbox" v-model="culture.is_done" class="w-3.5 h-3.5 text-primary-600 rounded border-slate-300" />
                      <span class="text-xs text-slate-500">{{ t("done") }}</span>
                    </label>
                  </div>
                </div>
              </div>
              <!-- Formulas belonging to test groups attached to this package -->
              <div
                v-for="(sec, sIdx) in (pkgGroupSections[pIndex] || [])"
                :key="'pgs-' + sIdx"
                class="p-4 bg-amber-50/40 border-t border-amber-100 space-y-2"
              >
                <div class="text-xs font-bold text-amber-700">{{ sec.group_name }}</div>
                <div v-for="(f, fi) in sec.formula" :key="'pgf-' + fi" class="flex items-center gap-3">
                  <span class="text-sm font-semibold text-emerald-700 min-w-[80px]">{{ f.name || t("formula") }}:</span>
                  <span class="text-xs font-mono text-emerald-600 flex-1">{{ (f.tokens || []).join(" ") }}</span>
                  <span class="text-sm text-slate-500">=</span>
                  <span class="px-3 py-1.5 bg-white border border-emerald-200 rounded-lg text-sm font-mono font-bold text-emerald-700 min-w-[80px] text-center">
                    {{ sec.formula_results?.[f.name || "result"] ?? "—" }}
                  </span>
                </div>
              </div>
              <div v-if="Array.isArray(pkg.formula) && pkg.formula.length" class="p-4 bg-emerald-50/50 border-t border-emerald-100 space-y-2">
                <div v-for="(f, fi) in pkg.formula" :key="'pkg-f-' + fi" class="flex items-center gap-3">
                  <span class="text-sm font-semibold text-emerald-700 min-w-[80px]">{{ f.name || t("formula") }}:</span>
                  <span class="text-xs font-mono text-emerald-600 flex-1">{{ (f.tokens || []).join(" ") }}</span>
                  <span class="text-sm text-slate-500">=</span>
                  <span class="px-3 py-1.5 bg-white border border-emerald-200 rounded-lg text-sm font-mono font-bold text-emerald-700 min-w-[80px] text-center">
                    {{ pkg.formula_results?.[f.name || "result"] ?? "—" }}
                  </span>
                </div>
              </div>

              <!-- Package Result Comments -->
              <div class="px-4 py-4 border-t border-slate-100 space-y-2">
                <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Result_Comments") }}</label>
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
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none resize-none transition-all"
                  :placeholder="t('enter_comment')"
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== TEST GROUPS SECTION (tabs moved out to full width above) ==================== -->
        <div v-if="activeSection === 'test_groups' && updateResultRecord.test_groups?.length > 0" class="space-y-4">
          <div v-for="(group, gIndex) in updateResultRecord.test_groups" :key="gIndex" v-show="activeTestGroupTab === gIndex">
            <!-- Template tests inside test group -->
            <template v-for="(test, tIndex) in group.tests" :key="'tg-tmpl-' + tIndex">
              <div v-if="test.sub_tests?.length > 0 && test.content?.html" class="bg-white rounded-xl border border-slate-200 shadow-sm mb-4">
                <div class="px-6 py-3 border-b border-slate-100 bg-gradient-to-r from-rose-50 to-pink-50 flex items-center justify-between">
                  <span class="text-sm font-semibold text-slate-700">{{ test.name }}<span v-if="test.shortcut" class="text-xs text-slate-400 font-mono ms-1">({{ test.shortcut }})</span></span>
                  <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" v-model="test.is_done" class="w-4 h-4 text-primary-600 rounded" />
                      <span class="text-xs text-slate-600">{{ t("done") }}</span>
                    </label>
                  </div>
                </div>
                <div class="p-6" style="direction: ltr;">
                  <div v-html="getGroupTemplateHtml(test, gIndex, tIndex)" class="dynamic-template"></div>
                </div>
              </div>
            </template>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <div class="divide-y divide-slate-100">
                <div
                  v-for="(test, tIndex) in groupVisibleTests(group)"
                  :key="tIndex"
                  data-tg-test-row
                  class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 hover:bg-slate-50/50 transition-colors"
                >
                  <div class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="w-8 h-8 rounded-md bg-amber-50 text-amber-600 text-xs font-bold flex items-center justify-center shrink-0">
                      {{ tIndex + 1 }}
                    </span>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-slate-800 truncate">
                        {{ test.name }}
                        <span v-if="test.shortcut" class="text-xs text-slate-400 font-mono ms-1">({{ test.shortcut }})</span>
                      </p>
                      <p class="text-xs text-slate-400">{{ t("price") }}: {{ test.price ?? 0 }}</p>
                    </div>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs shrink-0 sm:min-w-[140px] sm:max-w-[220px]">
                    <span class="text-slate-500">{{ t("Unit") }}: <span class="text-slate-700">{{ test.unit || "—" }}</span></span>
                    <div class="text-blue-600 space-y-0.5">
                      <template v-if="displayRanges(test).length">
                        <div v-for="range in displayRanges(test)" :key="range.test_reference_range_id || range.id" style="white-space: pre-line;">
                          <template v-if="range.notes">{{ range.notes }}</template>
                          <template v-else>{{ range.from }} - {{ range.to }}</template>
                        </div>
                      </template>
                      <span v-else class="text-slate-400">{{ t("tests-reference-ranges") }}: —</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <input
                      v-if="Number(test.result_type_id_fk) === 1 || Number(test.result_type_id_fk) === 2"
                      type="text"
                      inputmode="decimal"
                      pattern="-?[0-9]*\.?[0-9]*"
                      v-model="test.result"
                      @input="autoDetectStatus(test); evalFormula(group)"
                      @change="autoDetectStatus(test)"
                      @keydown.enter.prevent="confirmAndNextGroup(test, $event)"
                      :data-tg-row="gIndex + '-' + tIndex"
                      class="w-28 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                      :placeholder="t('Result')"
                    />
                    <select
                      v-else-if="Number(test.result_type_id_fk) === 4"
                      v-model="test.result"
                      @change="evalFormula(group)"
                      @keydown.enter.prevent="confirmAndNextGroup(test, $event)"
                      :data-tg-row="gIndex + '-' + tIndex"
                      class="w-36 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="opt in (test.selction_type_options || test.selection_type_options || [])" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <input
                      v-else
                      type="text"
                      v-model="test.result"
                      @input="autoDetectStatus(test); evalFormula(group)"
                      @change="autoDetectStatus(test)"
                      @keydown.enter.prevent="confirmAndNextGroup(test, $event)"
                      :data-tg-row="gIndex + '-' + tIndex"
                      class="w-28 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                      :placeholder="t('Result')"
                    />
                    <select
                      v-if="Number(test.result_type_id_fk) !== 3"
                      v-model="test.result_status_id_fk"
                      class="w-36 px-2.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <label class="flex items-center gap-1.5 px-2.5 py-2 bg-slate-50 rounded-lg cursor-pointer">
                      <input type="checkbox" v-model="test.is_done" class="w-3.5 h-3.5 text-primary-600 rounded border-slate-300" />
                      <span class="text-xs text-slate-500">{{ t("done") }}</span>
                    </label>
                  </div>
                </div>
                <div v-if="Array.isArray(group.formula) && group.formula.length" class="p-4 bg-emerald-50/50 border-t border-emerald-100 space-y-2">
                  <div v-for="(f, fi) in group.formula" :key="'g-f-' + fi" class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-emerald-700 min-w-[80px]">{{ f.name || t("formula") }}:</span>
                    <span class="text-xs font-mono text-emerald-600 flex-1">{{ (f.tokens || []).join(" ") }}</span>
                    <span class="text-sm text-slate-500">=</span>
                    <span class="px-3 py-1.5 bg-white border border-emerald-200 rounded-lg text-sm font-mono font-bold text-emerald-700 min-w-[80px] text-center">
                      {{ group.formula_results?.[f.name || "result"] ?? "—" }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== CULTURES SECTION ==================== -->
        <div v-if="activeSection === 'cultures' && updateResultRecord.cultures?.length > 0" class="space-y-4">
          <!-- Culture Tabs -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-3 py-2">
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                v-for="(item, index) in updateResultRecord.cultures"
                :key="index"
                @click="activeCultureTab = index"
                :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2',
                  activeCultureTab === index
                    ? 'bg-rose-50 text-rose-700 shadow-sm'
                    : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
                ]"
              >
                <span>{{ item.name }}<span v-if="item.shortcut" class="text-[10px] text-slate-400 font-mono ms-1">({{ item.shortcut }})</span></span>
                <span v-if="item.is_done" class="w-2 h-2 bg-green-500 rounded-full"></span>
              </button>
            </div>
          </div>

          <div v-for="(culture, cIndex) in updateResultRecord.cultures" :key="cIndex" v-show="activeCultureTab === cIndex">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <div class="p-6 space-y-5">
                <!-- Toggles -->
                <div class="flex flex-wrap gap-6">
                  <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                      <input type="checkbox" v-model="culture.is_done" class="sr-only peer" />
                      <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-rose-600 peer-focus:ring-2 peer-focus:ring-rose-300 transition-colors"></div>
                      <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                    </div>
                    <span class="text-sm font-medium text-slate-700">{{ t("done") }}</span>
                  </label>
                  <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                      <input type="checkbox" v-model="culture.last_result" class="sr-only peer" />
                      <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-rose-600 peer-focus:ring-2 peer-focus:ring-rose-300 transition-colors"></div>
                      <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4"></div>
                    </div>
                    <span class="text-sm font-medium text-slate-700">{{ t("last_result") }}</span>
                  </label>
                </div>

                <!-- Result Type + Result -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Result_Type") }}</label>
                    <select
                      v-model="culture.result_status_id_fk"
                      class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none bg-white transition-all"
                    >
                      <option value="">{{ t("select") }}</option>
                      <option v-for="status in resultStatus" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Result") }}</label>
                    <input
                      type="text"
                      v-model="culture.result"
                      @keydown.enter="culture.is_done = true"
                      class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition-all"
                    />
                  </div>
                </div>

                <!-- Comment -->
                <div>
                  <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Test_Group_Comment") }}</label>
                  <input
                    type="text"
                    v-model="culture.comment"
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition-all"
                    :placeholder="t('enter_comment')"
                  />
                </div>

                <!-- Attributes -->
                <div v-if="culture.attribute?.length" class="border border-slate-200 rounded-lg overflow-hidden">
                  <button
                    type="button"
                    @click="toggleAttributes(cIndex)"
                    class="w-full flex items-center justify-between p-3.5 bg-slate-50 hover:bg-slate-100 transition-colors"
                  >
                    <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                      <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                      </svg>
                      {{ t("Show_Attributes") }}
                      <span class="text-xs text-slate-400">({{ culture.attribute.length }})</span>
                    </span>
                    <svg
                      :class="['w-4 h-4 text-slate-400 transition-transform', showAttributes[cIndex] ? 'rotate-180' : '']"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                  <div v-show="showAttributes[cIndex]" class="divide-y divide-slate-100">
                    <div
                      v-for="attribute in culture.attribute"
                      :key="attribute.id"
                      class="flex items-center gap-3 p-3.5"
                    >
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-700">{{ attribute.name }}</p>
                        <p class="text-xs text-slate-400">{{ t("Order") }}: {{ attribute.order }}</p>
                      </div>
                      <input
                        type="text"
                        v-model="attribute.result"
                        class="w-36 px-2.5 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none"
                        :placeholder="t('Result')"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Culture Result Comments -->
              <div class="px-6 pb-6 pt-2 border-t border-slate-100 space-y-2">
                <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ t("Result_Comments") }}</label>
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
                  class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none resize-none transition-all"
                  :placeholder="t('enter_comment')"
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== EXTRAS SECTION (Attachments + Comment) ==================== -->
        <div v-if="activeSection === 'extras'" class="space-y-6">

          <!-- ===== Saved Files ===== -->
          <div v-if="existingAttachments.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center">
                  <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-sm font-bold text-slate-800">{{ t("saved") || "Saved" }} {{ t("attachments") }}</h3>
                  <p class="text-xs text-slate-400">{{ existingAttachments.length }} {{ t("files") || "files" }}</p>
                </div>
              </div>
            </div>
            <div class="divide-y divide-slate-100">
              <div
                v-for="(item, index) in existingAttachments"
                :key="'existing-' + index"
                class="group flex items-center gap-4 px-6 py-3.5 hover:bg-slate-50/60 transition-colors"
              >
                <!-- File type icon -->
                <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" :class="getFileColor(item.file || item.name).bg">
                  <span class="text-[10px] font-black uppercase tracking-wider" :class="getFileColor(item.file || item.name).text">
                    {{ getFileExtension(item.file || item.name) }}
                  </span>
                </div>

                <!-- File info -->
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-slate-800 truncate">{{ item.name }}</p>
                  <p class="text-xs text-emerald-500 flex items-center gap-1 mt-0.5">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                    {{ t("saved") || "Saved" }}
                  </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity shrink-0">
                  <!-- Open in new tab -->
                  <a
                    :href="item.file"
                    target="_blank"
                    class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-all"
                    :title="t('view') || 'Open'"
                  >
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                  </a>
                  <!-- Download -->
                  <a
                    :href="item.file"
                    :download="item.name"
                    class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                    :title="t('download') || 'Download'"
                  >
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                  </a>
                  <!-- Delete -->
                  <button
                    type="button"
                    @click="removeExistingAttachment(index)"
                    class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                  >
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- ===== Upload New Files ===== -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-primary-100 flex items-center justify-center">
                  <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-sm font-bold text-slate-800">{{ t("upload") || "Upload" }} {{ t("attachments") }}</h3>
                  <p class="text-xs text-slate-400">{{ attachments.filter(a => a.file).length }} {{ t("selected") || "selected" }}</p>
                </div>
              </div>
              <button
                type="button"
                @click="addAttachment"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-lg transition-colors"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ t("add") || "Add" }}
              </button>
            </div>

            <div class="p-5 space-y-3">
              <div
                v-for="(item, index) in attachments"
                :key="'new-' + index"
                class="rounded-xl border-2 border-dashed transition-all overflow-hidden"
                :class="item.file ? 'border-primary-300 bg-primary-50/30' : 'border-slate-200 hover:border-primary-300 bg-slate-50/30'"
              >
                <!-- Drop zone / Upload trigger (when no file) -->
                <div v-if="!item.file" class="p-4">
                  <div class="flex items-center gap-4">
                    <input
                      v-model="item.name"
                      type="text"
                      class="flex-1 min-w-0 px-3 py-2.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white"
                      :placeholder="t('name')"
                    />
                    <input
                      type="file"
                      @change="(e) => onFileChange(e, index)"
                      class="hidden"
                      :id="'file-' + index"
                    />
                    <label
                      :for="'file-' + index"
                      class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-lg cursor-pointer transition-colors shrink-0"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                      </svg>
                      {{ t("choose_file") || "Choose File" }}
                    </label>
                    <button
                      v-if="attachments.length > 1"
                      type="button"
                      @click="removeAttachment(index)"
                      class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all shrink-0"
                    >
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Selected file preview -->
                <div v-else class="flex items-center gap-4 px-4 py-3">
                  <!-- File type badge -->
                  <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" :class="getFileColor(item.file.name).bg">
                    <span class="text-[10px] font-black uppercase tracking-wider" :class="getFileColor(item.file.name).text">
                      {{ getFileExtension(item.file.name) }}
                    </span>
                  </div>

                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <input
                      v-model="item.name"
                      type="text"
                      class="w-full text-sm font-semibold text-slate-800 bg-transparent border-0 border-b border-transparent hover:border-slate-200 focus:border-primary-500 outline-none pb-0.5 transition-colors"
                      :placeholder="t('name')"
                    />
                    <div class="flex items-center gap-2 mt-1">
                      <span class="text-xs text-slate-400 truncate">{{ item.file.name }}</span>
                      <span class="text-xs text-slate-300">|</span>
                      <span class="text-xs text-slate-400">{{ formatFileSize(item.file.size) }}</span>
                    </div>
                  </div>

                  <!-- Actions -->
                  <div class="flex items-center gap-1 shrink-0">
                    <input
                      type="file"
                      @change="(e) => onFileChange(e, index)"
                      class="hidden"
                      :id="'file-' + index"
                    />
                    <label
                      :for="'file-' + index"
                      class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg cursor-pointer transition-all"
                      :title="t('change') || 'Change'"
                    >
                      <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                      </svg>
                    </label>
                    <button
                      type="button"
                      @click="removeAttachment(index)"
                      class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                    >
                      <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Empty state (when no uploads and no existing) -->
              <div v-if="attachments.length === 0 && existingAttachments.length === 0" class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
                  <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                  </svg>
                </div>
                <p class="text-sm font-medium text-slate-400">{{ t("no_attachments") }}</p>
                <p class="text-xs text-slate-300 mt-1">{{ t("add") || "Add" }} {{ t("attachments") }}</p>
              </div>
            </div>
          </div>

          <!-- ===== General Comment ===== -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-100">
              <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-slate-800">{{ t("resultComment") }}</h3>
            </div>
            <div class="p-5">
              <textarea
                v-model="updateResultRecord.notes"
                rows="5"
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none resize-none transition-all bg-slate-50/50 focus:bg-white"
                :placeholder="t('enter_general_comment')"
              ></textarea>
            </div>
          </div>
        </div>

          </div>
          <!-- End: Content (col-9) -->
        </div>
        <!-- End: Section nav + Content grid -->

      </div>
    </template>

    <!-- Print/Download/WhatsApp modal + hidden print-result template -->
    <printSelectModal
      v-model="printSelectVisible"
      :print-mode="printSelectMode"
      @print="handlePrintSelection"
    />
    <printResult ref="printResultRef" />
    <pationtHistoryModal />
  </div>
</template>

<script setup>
import { messageTemplate } from '@/utils/labDocuments';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useTemplatesStore } from "@/store/modules/template";
import { useinvoicesStore } from "@/store/modules/invoices";
import { useresultStatusStore } from "@/store/modules/result-status";
import { usePatientsStore } from "@/store/modules/patients";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { t, sanitizeHtml, showAlertWithConfirm } from "@/utils/helper";
import { $http } from "@/plugins/axios";
import { useToast } from "@/composables/useToast";
import { usePrint } from "@/composables/usePrint";
import printResult from "./componentes/print_Result.vue";
import printSelectModal from "./componentes/printSelectModal.vue";
import pationtHistoryModal from "./componentes/pationtHistory_modal.vue";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const templatesStore = useTemplatesStore();
const invoicesStore = useinvoicesStore();
const resultStatusStore = useresultStatusStore();
const patientsStore = usePatientsStore();
const labSettingsStore = useLabSettingsStore();

const { templates } = storeToRefs(templatesStore);
const { resultStatus } = storeToRefs(resultStatusStore);
const { updateResultRecord, testsComment, cultursComment, printRecord } = storeToRefs(invoicesStore);

const { printWithIframe, printStyles } = usePrint();
const reportBackground = computed(() => labSettingsStore.settings.report_background || "");

// All-tab table excludes special/custom tests ("تحليل مخصص", is_special_test) — they
// render a custom HTML table, not a single result input, so the row view is meaningless.
const allTabTests = computed(() =>
     (updateResultRecord.value?.tests || []).filter((t) => !t.is_special_test)
);

// Print/Download/WhatsApp state — mirrors medical_reports/index.vue
const printResultRef = ref(null);
const printSelectVisible = ref(false);
const printSelectMode = ref("normal");
const printSelectItem = ref(null);
const downloadInProgress = ref(false);
const printMenuOpen = ref(false);
const downloadMenuOpen = ref(false);
const whatsappMenuOpen = ref(false);
const closeAllMenus = () => {
  printMenuOpen.value = false;
  downloadMenuOpen.value = false;
  whatsappMenuOpen.value = false;
};
const togglePrintMenu = () => { const v = !printMenuOpen.value; closeAllMenus(); printMenuOpen.value = v; };
const toggleDownloadMenu = () => { const v = !downloadMenuOpen.value; closeAllMenus(); downloadMenuOpen.value = v; };
const toggleWhatsappMenu = () => { const v = !whatsappMenuOpen.value; closeAllMenus(); whatsappMenuOpen.value = v; };
const User = computed(() => {
  try { return JSON.parse(localStorage.getItem("user")); } catch { return null; }
});

const lang = computed(() => localStorage.getItem("locale") || "ar");
const isLoading = ref(true);

// ===== Auto-save (debounced, silent) =====
const autoSaveReady = ref(false); // gate: don't save during initial load
const autoSaveState = ref("idle"); // idle | saving | saved | error
let autoSaveTimer = null;
let autoSaveInFlight = false;
let autoSavePending = false;
let autoSaveDirty = false; // unsaved change exists

// Mirror attachment state (existing URLs + newly-picked Files) onto the record
// so auto-save persists adds AND removals. Returns true if new Files are queued.
const syncAttachmentsToRecord = () => {
  const newFiles = attachments.value
    .filter((item) => item.file instanceof File)
    .map((item) => ({ name: item.name || null, file: item.file }));
  updateResultRecord.value.attachments = [...existingAttachments.value, ...newFiles];
  return newFiles.length > 0;
};

const runAutoSave = async () => {
  if (autoSaveInFlight) { autoSavePending = true; return; }
  autoSaveInFlight = true;
  autoSaveDirty = false;
  autoSaveState.value = "saving";
  try {
    const hadNewFiles = syncAttachmentsToRecord();
    await invoicesStore.autoSaveResult();
    // After uploading new files, refetch so they become "existing" URLs —
    // otherwise the same File re-uploads on every later save (duplicates).
    if (hadNewFiles) {
      const id = route.params.id;
      const data = await invoicesStore.GetinvoicesById(id);
      if (data?.attachments) existingAttachments.value = data.attachments.map((a) => ({ ...a }));
      attachments.value = [{ name: "", file: null }];
    }
    autoSaveState.value = "saved";
  } catch {
    autoSaveState.value = "error";
    autoSaveDirty = true; // retry on next change / unmount
  } finally {
    autoSaveInFlight = false;
    if (autoSavePending) {
      autoSavePending = false;
      scheduleAutoSave();
    }
  }
};

const scheduleAutoSave = () => {
  if (!autoSaveReady.value) return;
  autoSaveDirty = true;
  clearTimeout(autoSaveTimer);
  autoSaveState.value = "saving";
  autoSaveTimer = setTimeout(runAutoSave, 1200);
};

const openPatientEdit = () => {
  const pid = updateResultRecord.value?.patient?.id;
  if (!pid) return;
  router.push({ path: "/patients", query: { edit: pid } });
};

const openPatientMedicalHistory = () => {
  const pid = updateResultRecord.value?.patient?.id;
  if (!pid) return;
  router.push({ path: "/medical_reports", query: { patient_id: pid } });
};

const openPatientHistory = async () => {
  const pid = updateResultRecord.value?.patient?.id;
  if (!pid) return;
  try {
    await invoicesStore.patientHistory(pid);
    invoicesStore.pationtHistoryDialog = true;
  } catch (e) {
    toast.error(t("error") || "Error");
  }
};

const openInvoiceEdit = () => {
  const iid = updateResultRecord.value?.id || route.params.id;
  if (!iid) return;
  router.push({ name: "invoices-edit", params: { id: iid }, query: { return: "update-result" } });
};

const patientSearch = ref("");
const patientSearchResults = ref([]);
const patientSearchOpen = ref(false);
const patientSearchLoading = ref(false);
let patientSearchTimer = null;

const fetchPatients = async (q) => {
  patientSearchLoading.value = true;
  try {
    const { data } = await $http.post("/patients/search-name", { name: q || " " });
    patientSearchResults.value = Array.isArray(data) ? data : [];
    patientSearchOpen.value = true;
  } catch (e) {
    patientSearchResults.value = [];
  } finally {
    patientSearchLoading.value = false;
  }
};

const onPatientSearchInput = () => {
  clearTimeout(patientSearchTimer);
  const q = patientSearch.value?.trim();
  patientSearchTimer = setTimeout(() => fetchPatients(q), 300);
};

const onPatientSearchFocus = () => {
  if (patientSearchResults.value.length) {
    patientSearchOpen.value = true;
  } else {
    fetchPatients("");
  }
};

const selectPatientFromSearch = async (p) => {
  if (!p?.id) return;
  patientSearchOpen.value = false;
  const confirm = await showAlertWithConfirm(
    t("switch_patient") || "Switch patient?",
    `${t("AlertWithConfirm") || ""} ${p.name || ""}`.trim(),
    t("ok"),
    t("cancel")
  );
  if (!confirm?.value) return;
  patientSearch.value = "";
  try {
    await invoicesStore.patient_medical_records(p.id);
    const list = invoicesStore.invoices || [];
    const latest = list[0];
    if (!latest?.id) {
      toast.warning(t("no_records_found") || "No reports found");
      return;
    }
    if (String(latest.id) === String(route.params.id)) {
      window.location.reload();
      return;
    }
    window.location.href = `/medical_reports/update-result/${latest.id}`;
  } catch (e) {
    toast.error(t("error_occurred") || "Error");
  }
};

const openAddPatient = () => {
  router.push({ path: "/patients", query: { create: 1 } });
};

const openTestEdit = (item) => {
  const tid = item?.test_id_fk || item?.id;
  if (!tid) return;
  const invoiceId = updateResultRecord.value?.id || route.params.id;
  router.push({ path: "/tests", query: { edit: tid, return: "update-result", return_id: invoiceId } });
};

const addInvoiceForPatient = () => {
  const p = updateResultRecord.value?.patient;
  if (!p?.id) return;
  // Seed patientsStore.responseData so /invoices/create sees the existing patient as picked
  patientsStore.responseData = {
    id: p.id,
    name: p.name,
    code: p.code,
    phone: p.phone,
    email: p.email,
    address: p.address,
    gender: p.gender,
    gender_type_id_fk: p.gender_type_id_fk,
    title: p.title,
    title_id_fk: p.title_id_fk,
    age: p.age,
    age_unit_id_fk: p.age_unit_id_fk,
    dob: p.dob,
    national_id_no: p.national_id_no,
    passport_no: p.passport_no,
    image: p.image,
  };
  router.push({ path: "/invoices/create", query: { patient_id: p.id, return: "update-result", return_id: updateResultRecord.value?.id || route.params.id } });
};

// Local state
const existingAttachments = ref([]);
const attachments = ref([{ name: "", file: null }]);
const showAttributes = ref([]);
const selectedTemplate = ref(null);
const activeTestTab = ref("all");
const activePackageTab = ref(0);
const activeTestGroupTab = ref(0);
const activeCultureTab = ref(0);
const activeSection = ref('tests');

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

// Ranges to SHOW next to a result input: patient-matched (age/gender) first,
// all ranges only when nothing matches. Mirrors autoDetectStatus's fallback —
// without this, tests with many ranges (e.g. per-age "Critical high" rows)
// dump every range into the row and blow up the layout.
const displayRanges = (t) => {
  const filtered = getFilteredRanges(t?.test_reference_ranges);
  return filtered?.length ? filtered : (t?.test_reference_ranges || []);
};

// Auto-detect result status based on result value vs reference ranges
// Status IDs: 1=high, 2=normal, 3=abnormal, 4=low, 5=ask doctor
const isFormulaTarget = (parent, test) => {
  const formulas = Array.isArray(parent?.formula) ? parent.formula : [];
  if (!formulas.length) return false;
  const key = test.shortcut || test.name;
  return formulas.some(f => f?.name === key);
};

// Fixed-point evaluator: repeats passes until no new formula resolves.
// Lets formula 2 reference formula 1's `name` (e.g. A=B-C, D=A-C → D resolves
// once A is computed). Tokens resolve against child test results first, then
// against already-computed formula_results. Max passes capped at formula count
// to short-circuit cycles (e.g. A=B+1, B=A+1).
const evalFormula = (parent) => {
  const formulas = Array.isArray(parent?.formula) ? parent.formula : [];
  if (!formulas.length) return;
  const tests = parent.tests || [];
  // Reset on every call — child test values may have changed; stale results
  // would block recomputation in the "already resolved" guard below.
  parent.formula_results = {};
  const results = parent.formula_results;

  const tryEval = (f) => {
    if (!f?.tokens?.length) return false;
    let expr = "";
    for (const tok of f.tokens) {
      if (/^[+\-*/()]$/.test(tok)) { expr += tok; continue; }
      if (/^[\d.]+$/.test(tok)) { expr += tok; continue; }
      const test = tests.find(t => (t.shortcut || t.name) === tok);
      let val = test ? parseFloat(test.result) : NaN;
      if (isNaN(val)) {
        const prior = results[tok];
        val = prior != null ? parseFloat(prior) : NaN;
      }
      if (isNaN(val)) return false;
      expr += `(${val})`;
    }
    if (!/^[\d+\-*/().\s]+$/.test(expr)) return false;
    try {
      const r = Function(`"use strict"; return (${expr});`)();
      if (typeof r === "number" && !isNaN(r)) {
        results[f.name || "result"] = Math.round(r * 100) / 100;
        return true;
      }
    } catch (_) { /* ignore */ }
    return false;
  };

  for (let pass = 0; pass < formulas.length; pass++) {
    let progress = false;
    for (const f of formulas) {
      const key = f?.name || "result";
      if (results[key] != null) continue; // already resolved
      if (tryEval(f)) progress = true;
    }
    if (!progress) break;
  }
};

const confirmAndNext = async (item, index) => {
  if (item) item.is_done = true;
  await nextTick();
  const total = allTabTests.value.length || 0;
  const next = index + 1 < total ? index + 1 : 0;
  const sel = `[data-test-row="${next}"]`;
  const el = document.querySelector(sel);
  if (el) {
    el.focus();
    if (typeof el.select === "function") el.select();
  }
};

// Visible (row-rendered) tests inside a group — excludes custom HTML-template
// tests and formula targets, matching the v-for in the group rows table.
const groupVisibleTests = (group) =>
  (group?.tests || []).filter((t) => !(t.sub_tests?.length > 0 && t.content?.html) && !isFormulaTarget(group, t));

// Same split for packages: custom (is_special_test) tests render their own HTML
// template, everything else renders as a normal result row.
// Colour configured per option in /tests (sub_test.option_colors). Applies to the
// selected option; a multi-select whose picks disagree gets no colour, so we never
// imply a single colour for mixed values.
const subOptionColor = (sub, rawValue) => {
  const map = sub?.option_colors;
  if (!map || !rawValue) return null;
  const picks = String(rawValue).split(", ").filter(Boolean);
  const colors = [...new Set(picks.map((v) => map[v]).filter(Boolean))];
  return colors.length === 1 ? colors[0] : null;
};

const isCustomTemplateTest = (t) => t?.sub_tests?.length > 0 && !!t?.content?.html;

// Test groups attached to a package, rebuilt as real objects so evalFormula()
// (which writes parent.formula_results) has somewhere persistent to write.
// Held in a ref rather than a computed: a computed would hand back a fresh
// object each render and every result it wrote would be discarded.
// Indexed to match updateResultRecord.packages.
const pkgGroupSections = ref([]);

const buildPkgGroupSections = () => {
  pkgGroupSections.value = (updateResultRecord.value?.packages || []).map((pkg) => {
    const byGroup = new Map();
    (pkg.tests || []).forEach((t) => {
      const g = t.test_group_name;
      if (!g) return;
      if (isFormulaTarget(pkg, t)) return; // computed by the package itself
      const key = t.test_group_id_fk ?? `name:${g}`;
      if (!byGroup.has(key)) byGroup.set(key, { key, group_name: g });
    });
    return [...byGroup.values()]
      .map((entry) => {
        const meta =
          (pkg.test_groups || []).find((g) => (g.test_group_id_fk ?? `name:${g.group_name || g.name}`) === entry.key) ||
          (pkg.test_groups || []).find((g) => (g.group_name || g.name) === entry.group_name);
        return {
          group_name: entry.group_name,
          formula: Array.isArray(meta?.formula) ? meta.formula : [],
          // Whole package, so a formula operand that is also a package member
          // (deduped out of the tagged set) still resolves.
          tests: pkg.tests || [],
          formula_results: {},
        };
      })
      .filter((sec) => sec.formula.length);
  });
  pkgGroupSections.value.forEach((secs) => secs.forEach((sec) => evalFormula(sec)));
};

// A test computed by its own group's formula is not entered by hand — same rule
// standalone groups already follow via groupVisibleTests.
const isPkgGroupFormulaTarget = (pkg, t, pIndex) =>
  (pkgGroupSections.value[pIndex] || []).some((sec) => isFormulaTarget(sec, t));

const evalPkgFormulas = (pkg, pIndex) => {
  evalFormula(pkg);
  (pkgGroupSections.value[pIndex] || []).forEach((sec) => evalFormula(sec));
};

const packageVisibleTests = (pkg) =>
  (pkg?.tests || []).filter((t) => !isCustomTemplateTest(t) && !isFormulaTarget(pkg, t));
const packageTemplateTests = (pkg) => (pkg?.tests || []).filter((t) => isCustomTemplateTest(t));

// Enter on a group row: mark done + focus the NEXT test's result control.
// DOM-relative: walk from the current row container to the next row (wraps to
// first), then focus that row's result input/select — never the status select.
// Enter inside a PACKAGE row: mark done + focus the next row's result control.
// DOM-relative like the test-group version, so it is unaffected by how the rows
// are filtered (group captions, formula targets, custom tests) and wraps around.
const confirmAndNextPackage = async (test, ev) => {
  if (test) test.is_done = true;
  await nextTick();
  const row = ev?.target?.closest("[data-pkg-test-row]");
  if (!row) return;
  let next = row.nextElementSibling;
  while (next && !next.hasAttribute("data-pkg-test-row")) next = next.nextElementSibling;
  if (!next) {
    const rows = row.parentElement?.querySelectorAll("[data-pkg-test-row]");
    next = rows && rows.length ? rows[0] : null;
  }
  const ctrl = next?.querySelector("[data-pkg-row]");
  if (ctrl) {
    ctrl.focus();
    if (typeof ctrl.select === "function") ctrl.select();
  }
};

const confirmAndNextGroup = async (test, ev) => {
  if (test) test.is_done = true;
  await nextTick();
  const row = ev?.target?.closest("[data-tg-test-row]");
  if (!row) return;
  let next = row.nextElementSibling;
  while (next && !next.hasAttribute("data-tg-test-row")) next = next.nextElementSibling;
  if (!next) {
    const rows = row.parentElement?.querySelectorAll("[data-tg-test-row]");
    next = rows && rows.length ? rows[0] : null;
  }
  const ctrl = next?.querySelector("[data-tg-row]");
  if (ctrl) {
    ctrl.focus();
    if (typeof ctrl.select === "function") ctrl.select();
  }
};

const autoDetectStatus = (item) => {
  if (!item) return;
  const raw = item.result;
  if (raw == null || String(raw).trim() === "") return;
  const val = parseFloat(String(raw).trim());
  if (isNaN(val)) return;

  let ranges = getFilteredRanges(item.test_reference_ranges);
  // Fallback to all ranges if no patient-specific match
  if (!ranges?.length) ranges = item.test_reference_ranges || [];
  if (!ranges.length) return;

  // Find the first range with numeric from/to (handle string numbers too)
  const range = ranges.find(r => {
    const f = r?.from, tt = r?.to;
    return f != null && tt != null && f !== "" && tt !== "" && !isNaN(parseFloat(f)) && !isNaN(parseFloat(tt));
  });
  if (!range) return;

  const from = parseFloat(range.from);
  const to = parseFloat(range.to);

  if (val < from) {
    item.result_status_id_fk = 4; // low
  } else if (val > to) {
    item.result_status_id_fk = 1; // high
  } else {
    item.result_status_id_fk = 2; // normal
  }
};

const addAttachment = () => {
  attachments.value.push({ name: "", file: null });
};

const removeAttachment = (index) => {
  attachments.value.splice(index, 1);
};

const removeExistingAttachment = (index) => {
  existingAttachments.value.splice(index, 1);
};

const toggleAttributes = (index) => {
  showAttributes.value[index] = !showAttributes.value[index];
};

const onFileChange = (event, index) => {
  const file = event.target.files?.[0];
  if (file) {
    attachments.value[index].file = file;
    if (!attachments.value[index].name) {
      attachments.value[index].name = file.name.replace(/\.[^/.]+$/, "");
    }
  }
};

const getFileExtension = (filename) => {
  if (!filename) return "FILE";
  const ext = String(filename).split(".").pop()?.toLowerCase();
  return ext && ext.length <= 5 ? ext : "FILE";
};

const getFileColor = (filename) => {
  const ext = getFileExtension(filename);
  const map = {
    pdf: { bg: "bg-red-100", text: "text-red-600" },
    doc: { bg: "bg-blue-100", text: "text-blue-600" },
    docx: { bg: "bg-blue-100", text: "text-blue-600" },
    xls: { bg: "bg-emerald-100", text: "text-emerald-600" },
    xlsx: { bg: "bg-emerald-100", text: "text-emerald-600" },
    csv: { bg: "bg-emerald-100", text: "text-emerald-600" },
    png: { bg: "bg-purple-100", text: "text-purple-600" },
    jpg: { bg: "bg-purple-100", text: "text-purple-600" },
    jpeg: { bg: "bg-purple-100", text: "text-purple-600" },
    gif: { bg: "bg-purple-100", text: "text-purple-600" },
    svg: { bg: "bg-pink-100", text: "text-pink-600" },
    zip: { bg: "bg-amber-100", text: "text-amber-600" },
    rar: { bg: "bg-amber-100", text: "text-amber-600" },
    txt: { bg: "bg-slate-100", text: "text-slate-600" },
  };
  return map[ext] || { bg: "bg-slate-100", text: "text-slate-500" };
};

const formatFileSize = (bytes) => {
  if (!bytes) return "";
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / 1048576).toFixed(1) + " MB";
};

// Renders a custom (is_special_test) template inside a CONTAINER — either a
// test group or a package. `kind` selects which updateResultRecord array the
// entered sub_test values are written back to, so packages reuse this verbatim.
// Rendered custom-template markup, cached per container+test and NOT rebuilt
// while the page is open. v-html replaces the whole block whenever this string
// changes, which destroys the very input the tech is typing into — autosave
// flips a reactive flag on every keystroke, so every keystroke forced a
// re-render and the string embedded the just-typed value. The fields inside are
// bound imperatively and write straight back to the model, so frozen markup
// stays correct; a reload rebuilds it from stored values.
const templateHtmlCache = new Map();
// The section wrappers use v-if, so leaving a section and coming back rebuilds
// these nodes. Drop the cache then, so the markup is regenerated from the model
// and shows everything entered since the first render. Tab switches use v-show
// and keep their DOM, so they must NOT invalidate — the imperative state
// (multi-select display text, bound listeners) lives in those nodes.
watch(activeSection, () => templateHtmlCache.clear());

const getGroupTemplateHtml = (testData, gIdx, tIdx, kind = "test_groups") => {
  if (!testData?.content?.html) return "";
  const cacheKey = `${kind}:${gIdx}:${tIdx}`;
  let cached = templateHtmlCache.get(cacheKey);
  if (cached === undefined) {
  let html = testData.content.html;
  if (Array.isArray(testData.sub_tests)) {
    testData.sub_tests.forEach((sub, index) => {
      if (!sub || sub.name == null) return;
      const escapedName = String(sub.name).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      const regex = new RegExp(`\\{\\{sub_test\\.${escapedName}\\.value\\}\\}`, "g");
      let rawValue = sub.value;
      if (!rawValue || rawValue === "null" || rawValue === "---") rawValue = "";
      let replacement;

      // Normalize options + coerce type (jsonb may return "4" string / opts as JSON string)
      let opts = sub.sup_test_reference_options;
      if (typeof opts === "string") {
        try { opts = JSON.parse(opts); } catch { opts = []; }
      }
      const subType = Number(sub.type);

      if (subType === 4 && Array.isArray(opts) && opts.length > 0) {
        // Keep only values that still exist as options (drop stale/removed ones like "Normal")
        const selectedValues = rawValue ? rawValue.split(", ").filter((v) => opts.includes(v)) : [];
        const displayText = selectedValues.length > 0 ? selectedValues.join(", ") : "اختر...";
        const displayColor = subOptionColor(sub, selectedValues.join(", ")) || (selectedValues.length > 0 ? "#1e293b" : "#94a3b8");
        replacement = `<div class="ms-wrap-group" data-kind="${kind}" data-gidx="${gIdx}" data-tidx="${tIdx}" data-sidx="${index}" style="display:inline-block;position:relative;min-width:140px;vertical-align:middle;">
            <div class="ms-trigger" style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;background:#fff;font-size:13px;color:${displayColor};min-height:34px;">
              <span class="ms-display">${displayText}</span>
              <svg style="flex-shrink:0;width:12px;height:12px;opacity:.4" viewBox="0 0 14 14" fill="currentColor"><path d="M7 10.4c-.1 0-.2 0-.3-.06a.8.8 0 01-.26-.17L1.14 4.85a.54.54 0 01.1-.53.55.55 0 01.49-.15c.19.01.37.09.5.24L7 8.47l4.75-4.72a.55.55 0 01.78.02.55.55 0 01.02.78L7.58 10.13a.8.8 0 01-.58.27z"/></svg>
            </div>
            <div class="ms-menu" style="display:none;position:absolute;top:100%;left:0;min-width:100%;z-index:50;background:#fff;border:1px solid #d1d5db;border-radius:8px;margin-top:4px;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:200px;overflow-y:auto;">
              ${opts.map(option => {
                const isChecked = selectedValues.includes(option);
                return `<label class="ms-option" style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;${isChecked ? 'background:#f0fdfa;' : ''}">
                  <input type="checkbox" value="${option}" ${isChecked ? "checked" : ""} style="width:15px;height:15px;accent-color:#0d9488;flex-shrink:0;" />${option}</label>`;
              }).join("")}
            </div>
          </div>`;
      } else {
        const txtColor = subOptionColor(sub, rawValue);
        replacement = `<input type="text" class="editable-field-group px-3 py-2 border border-gray-300 rounded-lg text-sm" ${txtColor ? `style="color:${txtColor};font-weight:700;"` : ""} value="${rawValue}" data-kind="${kind}" data-gidx="${gIdx}" data-tidx="${tIdx}" data-sidx="${index}" style="width:auto;min-width:80px;display:inline-block;" />`;
      }
      html = html.replace(regex, replacement);
    });
  }
    cached = sanitizeHtml(html, {
      ALLOWED_TAGS: ["p","br","span","div","strong","b","i","em","u","h1","h2","h3","h4","h5","h6","ul","ol","li","table","tr","td","th","thead","tbody","tfoot","colgroup","col","img","input","label","blockquote","pre","code","mark","sub","sup","hr","svg","path"],
      ALLOWED_ATTR: ["class","style","src","alt","href","target","width","height","colspan","rowspan","border","cellpadding","cellspacing","data-colwidth","data-cell-type","align","valign","dir","type","value","data-kind","data-gidx","data-tidx","data-sidx","data-bound","placeholder","checked","viewBox","fill","d","stroke","stroke-width"],
      ALLOW_DATA_ATTR: true
    });
    templateHtmlCache.set(cacheKey, cached);
  }

  // Binder always runs: the section wrapper uses v-if, so switching sections
  // recreates these nodes even though the markup string is unchanged. The
  // data-bound guard keeps it from double-binding.
  nextTick(() => {
    // Text inputs
    document.querySelectorAll(".editable-field-group").forEach((input) => {
      if (input.dataset.bound) return;
      input.dataset.bound = "1";
      const eventType = input.tagName === "SELECT" ? "change" : "input";
      input.addEventListener(eventType, (e) => {
        const gi = parseInt(e.target.dataset.gidx);
        const ti = parseInt(e.target.dataset.tidx);
        const si = parseInt(e.target.dataset.sidx);
        const container = updateResultRecord.value[e.target.dataset.kind || "test_groups"]?.[gi];
        if (container?.tests?.[ti]?.sub_tests?.[si]) {
          container.tests[ti].sub_tests[si].value = e.target.value;
        }
      });
    });
    // Multi-select dropdowns
    document.querySelectorAll(".ms-wrap-group").forEach((wrap) => {
      if (wrap.dataset.bound) return;
      wrap.dataset.bound = "1";
      const trigger = wrap.querySelector(".ms-trigger");
      const menu = wrap.querySelector(".ms-menu");
      const display = wrap.querySelector(".ms-display");
      trigger.addEventListener("click", (e) => {
        e.stopPropagation();
        document.querySelectorAll(".ms-menu").forEach((m) => { if (m !== menu) m.style.display = "none"; });
        menu.style.display = menu.style.display === "none" ? "block" : "none";
      });
      menu.addEventListener("click", (e) => e.stopPropagation());
      menu.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
        cb.addEventListener("change", () => {
          const gi = parseInt(wrap.dataset.gidx);
          const ti = parseInt(wrap.dataset.tidx);
          const si = parseInt(wrap.dataset.sidx);
          const container = updateResultRecord.value[wrap.dataset.kind || "test_groups"]?.[gi];
          if (!container?.tests?.[ti]?.sub_tests?.[si]) return;
          const checked = Array.from(menu.querySelectorAll('input[type="checkbox"]:checked')).map((c) => c.value);
          container.tests[ti].sub_tests[si].value = checked.join(", ");
          display.textContent = checked.length > 0 ? checked.join(", ") : "اختر...";
          display.style.color = subOptionColor(container.tests[ti].sub_tests[si], checked.join(", ")) || (checked.length > 0 ? "#1e293b" : "#94a3b8");
        });
      });
    });
    document.addEventListener("click", () => document.querySelectorAll(".ms-menu").forEach((m) => { m.style.display = "none"; }));
  });
  return cached;
};

const loadTemplate = async () => {
  try {
    const testIdx = activeTestTab.value;
    const testData = updateResultRecord.value.tests[testIdx];
    const rawHtml = testData?.content?.html || "";
    if (!rawHtml.replace(/<[^>]+>/g, "").trim()) {
      selectedTemplate.value = null;
      return;
    }

    let templateHtml = rawHtml;

    if (Array.isArray(testData.sub_tests)) {
      testData.sub_tests.forEach((sub, index) => {
        // Skip malformed sub-tests with no name (would crash the whole template)
        if (!sub || sub.name == null) return;
        // Escape dots and spaces in field names for regex
        const escapedName = String(sub.name).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        const regex = new RegExp(`\\{\\{sub_test\\.${escapedName}\\.value\\}\\}`, "g");
        let replacement = "";

        // Normalize options: may arrive as array OR JSON string from DB
        let opts = sub.sup_test_reference_options;
        if (typeof opts === "string") {
          try { opts = JSON.parse(opts); } catch { opts = []; }
        }
        // Coerce type — backend/jsonb may return "4" string
        const subType = Number(sub.type);

        if (subType === 4 && Array.isArray(opts) && opts.length > 0) {
          let rawValue = sub.value;
          if (!rawValue || rawValue === "null" || rawValue === "---") rawValue = "";
          // Keep only values that still exist as options (drop stale/removed ones like "Normal")
          const selectedValues = rawValue ? rawValue.split(", ").filter((v) => opts.includes(v)) : [];
          const displayText = selectedValues.length > 0 ? selectedValues.join(", ") : "اختر...";
          const displayColor = subOptionColor(sub, selectedValues.join(", ")) || (selectedValues.length > 0 ? "#1e293b" : "#94a3b8");
          replacement = `<div class="ms-wrap" data-key="sub_tests[${index}].value" data-test-idx="${testIdx}" style="display:inline-block;position:relative;min-width:140px;vertical-align:middle;">
              <div class="ms-trigger" style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;background:#fff;font-size:13px;color:${displayColor};min-height:34px;">
                <span class="ms-display">${displayText}</span>
                <svg style="flex-shrink:0;width:12px;height:12px;opacity:.4" viewBox="0 0 14 14" fill="currentColor"><path d="M7 10.4c-.1 0-.2 0-.3-.06a.8.8 0 01-.26-.17L1.14 4.85a.54.54 0 01.1-.53.55.55 0 01.49-.15c.19.01.37.09.5.24L7 8.47l4.75-4.72a.55.55 0 01.78.02.55.55 0 01.02.78L7.58 10.13a.8.8 0 01-.58.27z"/></svg>
              </div>
              <div class="ms-menu" style="display:none;position:absolute;top:100%;left:0;min-width:100%;z-index:50;background:#fff;border:1px solid #d1d5db;border-radius:8px;margin-top:4px;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:200px;overflow-y:auto;">
                ${opts.map(option => {
                  const isChecked = selectedValues.includes(option);
                  return `<label class="ms-option" style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;${isChecked ? 'background:#f0fdfa;' : ''}">
                    <input type="checkbox" value="${option}" ${isChecked ? "checked" : ""} style="width:15px;height:15px;accent-color:#0d9488;flex-shrink:0;" />${option}</label>`;
                }).join("")}
              </div>
            </div>`;
        } else {
          const sColor = subOptionColor(sub, sub.value);
          replacement = `<input type="text" class="editable-field px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" ${sColor ? `style="color:${sColor};font-weight:700;"` : ""} value="${sub.value || ""}" data-key="sub_tests[${index}].value" data-test-idx="${testIdx}" style="width: auto; min-width: 80px; display: inline-block;" />`;
        }

        templateHtml = templateHtml.replace(regex, replacement);
      });
    }

    // Sanitize while allowing form elements
    const sanitizedHtml = sanitizeHtml(templateHtml, {
      ALLOWED_TAGS: [
        "p", "br", "span", "div", "strong", "b", "i", "em", "u",
        "h1", "h2", "h3", "h4", "h5", "h6",
        "ul", "ol", "li", "table", "tr", "td", "th", "thead", "tbody", "tfoot", "colgroup", "col", "caption",
        "img", "input", "select", "option", "label", "blockquote", "pre", "code", "mark", "sub", "sup", "hr", "svg", "path"
      ],
      ALLOWED_ATTR: [
        "class", "style", "src", "alt", "href", "target",
        "width", "height", "colspan", "rowspan", "border", "cellpadding", "cellspacing",
        "data-colwidth", "data-cell-type", "align", "valign", "dir",
        "type", "value", "data-key", "data-test-idx", "selected", "placeholder", "multiple", "checked",
        "viewBox", "fill", "d", "stroke", "stroke-width"
      ],
      ALLOW_DATA_ATTR: true
    });
    selectedTemplate.value = `<div id="custom-table-container">${sanitizedHtml}</div>`;

    nextTick(() => {
      // Text/number/select fields — guard against re-binding on repeated loadTemplate() calls
      document.querySelectorAll(".editable-field").forEach((input) => {
        if (input.dataset.bound) return;
        input.dataset.bound = "1";
        const eventType = input.tagName === "SELECT" ? "change" : "input";
        input.addEventListener(eventType, (e) => {
          const key = e.target.dataset.key;
          const idx = parseInt(e.target.dataset.testIdx || "0");
          const value = e.target.value;
          const match = key.match(/sub_tests\[(\d+)\]\.(.+)/);

          if (match) {
            const subIdx = parseInt(match[1]);
            const field = match[2];
            const mainTest = updateResultRecord.value.tests[idx];
            if (mainTest && mainTest.sub_tests[subIdx]) {
              mainTest.sub_tests[subIdx][field] = value;
            }
          }
        });
      });

      // Multi-select dropdowns with checkboxes — guard so toggle doesn't bind twice
      document.querySelectorAll(".ms-wrap").forEach((wrap) => {
        if (wrap.dataset.bound) return;
        wrap.dataset.bound = "1";
        const trigger = wrap.querySelector(".ms-trigger");
        const menu = wrap.querySelector(".ms-menu");
        const display = wrap.querySelector(".ms-display");

        trigger.addEventListener("click", (e) => {
          e.stopPropagation();
          // Close other open menus
          document.querySelectorAll(".ms-menu").forEach((m) => { if (m !== menu) m.style.display = "none"; });
          // Use computed style — inline style may be empty after Vue v-html re-render
          const isHidden = menu.style.display === "none" || getComputedStyle(menu).display === "none";
          menu.style.display = isHidden ? "block" : "none";
        });

        menu.addEventListener("click", (e) => e.stopPropagation());

        menu.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
          cb.addEventListener("change", () => {
            const key = wrap.dataset.key;
            const idx = parseInt(wrap.dataset.testIdx || "0");
            const match = key.match(/sub_tests\[(\d+)\]\.(.+)/);
            if (!match) return;

            const subIdx = parseInt(match[1]);
            const field = match[2];
            const mainTest = updateResultRecord.value.tests[idx];
            if (!mainTest || !mainTest.sub_tests[subIdx]) return;

            const checked = Array.from(menu.querySelectorAll('input[type="checkbox"]:checked')).map((c) => c.value);
            mainTest.sub_tests[subIdx][field] = checked.join(", ");

            // Update display text
            display.textContent = checked.length > 0 ? checked.join(", ") : "اختر...";
            display.style.color = subOptionColor(mainTest.sub_tests[subIdx], checked.join(", ")) || (checked.length > 0 ? "#1e293b" : "#94a3b8");
          });
        });
      });

      // Close all dropdowns on outside click
      const closeMenus = () => document.querySelectorAll(".ms-menu").forEach((m) => { m.style.display = "none"; });
      document.removeEventListener("click", closeMenus);
      document.addEventListener("click", closeMenus);
    });
  } catch (error) {
    console.error("Error loading template:", error);
  }
};

const update = async () => {
  try {
    // Combine existing attachments (URLs) + new attachments (File objects)
    updateResultRecord.value.attachments = [
      ...existingAttachments.value,
      ...attachments.value
        .filter((item) => item.file instanceof File)
        .map((item) => ({ name: item.name || null, file: item.file })),
    ];

    await invoicesStore.updateResult();
    autoSaveDirty = false; // manual save covers pending changes
    clearTimeout(autoSaveTimer);
    testsComment.value = [];
    cultursComment.value = [];
    toast.success(t("alertSuccess"));
    router.push("/medical_reports");
  } catch (error) {
    const msg = error?.response?.data?.error || error?.response?.data?.message || t("alertError");
    toast.error(msg);
  }
};

// Walk every sub_test in the loaded invoice and copy `default_value` into
// `value` when the lab tech hasn't entered a result yet. The default is
// configured per-option in /tests under "افتراضي" checkboxes for type-4
// (selection) sub-tests. Only fills empty values — never overwrites the
// tech's own input.
const applySubTestDefaults = (record) => {
  const fillForTest = (t) => {
    if (!t || !Array.isArray(t.sub_tests)) return;
    for (const sub of t.sub_tests) {
      const empty = sub.value == null || sub.value === "" || sub.value === "null" || sub.value === "---";
      if (empty && sub.default_value) {
        sub.value = sub.default_value;
      }
    }
  };
  (record?.tests || []).forEach(fillForTest);
  (record?.packages || []).forEach((p) => (p.tests || []).forEach(fillForTest));
  (record?.test_groups || []).forEach((g) => (g.tests || []).forEach(fillForTest));
};

const goBack = () => {
  router.push("/medical_reports");
};

// Lifecycle
const handleDocClick = () => {
  if (printMenuOpen.value || downloadMenuOpen.value || whatsappMenuOpen.value) {
    closeAllMenus();
  }
  if (patientSearchOpen.value) {
    patientSearchOpen.value = false;
  }
};
onUnmounted(() => document.removeEventListener("click", handleDocClick));

onMounted(async () => {
  document.addEventListener("click", handleDocClick);
  const invoiceId = route.params.id;

  await Promise.all([
    templatesStore.GetTemplates(),
    resultStatusStore.GetresultStatus()
  ]);

  // Always fetch full invoice data from API (list data is lightweight and lacks tests/cultures/packages)
  if (invoiceId) {
    try {
      const data = await invoicesStore.GetinvoicesById(invoiceId);
      if (data) {
        updateResultRecord.value = data;
        updateResultRecord.value.package_comment = data.package_comments || "";
        updateResultRecord.value.tests_comment = Array.isArray(data.tests_comment) ? data.tests_comment.join(", ") : (data.tests_comment || "");
        updateResultRecord.value.cultures_comment = Array.isArray(data.cultures_comment) ? data.cultures_comment.join(", ") : (data.cultures_comment || "");
        // Pre-fill sub_test values with their default_value when no value
        // has been entered yet — set in the test definition under
        // /tests as "افتراضي" checkboxes per option.
        applySubTestDefaults(updateResultRecord.value);
      }
    } catch {
      router.push("/medical_reports");
      return;
    }
  }

  // Still no data — redirect back
  if (!updateResultRecord.value?.id) {
    router.push("/medical_reports");
    return;
  }

  // Load existing attachments from invoice data
  if (updateResultRecord.value.attachments?.length > 0) {
    existingAttachments.value = updateResultRecord.value.attachments.map((a) => ({ ...a }));
  }

  // Auto-select first available section
  if (updateResultRecord.value.tests?.length > 0) {
    activeSection.value = 'tests';
  } else if (updateResultRecord.value.packages?.length > 0) {
    activeSection.value = 'packages';
  } else if (updateResultRecord.value.test_groups?.length > 0) {
    activeSection.value = 'test_groups';
  } else if (updateResultRecord.value.cultures?.length > 0) {
    activeSection.value = 'cultures';
  } else {
    activeSection.value = 'extras';
  }

  // Rebuild the per-package group-formula sections and evaluate them once, so
  // the boxes show computed values on load rather than only after an edit.
  buildPkgGroupSections();

  // Open on all-tab when it has rows; else first test tab.
  activeTestTab.value = allTabTests.value.length ? "all" : 0;

  isLoading.value = false;

  nextTick(() => {
    if (updateResultRecord.value.tests?.length > 0) {
      loadTemplate();
    }
    // Enable auto-save only after load + defaults are applied, so the initial
    // hydration doesn't trigger a save.
    autoSaveReady.value = true;
  });
});

// Auto-save: deep-watch the editable result fields. Debounced in scheduleAutoSave.
watch(
  () => [
    updateResultRecord.value?.tests,
    updateResultRecord.value?.cultures,
    updateResultRecord.value?.packages,
    updateResultRecord.value?.test_groups,
    updateResultRecord.value?.tests_comment,
    updateResultRecord.value?.cultures_comment,
    updateResultRecord.value?.package_comment,
    updateResultRecord.value?.notes,
  ],
  () => scheduleAutoSave(),
  { deep: true }
);

// Auto-save attachments (adds + removals). Only fires on a real file pick or
// a removal — typing the name field also schedules (cheap, debounced).
watch(
  [attachments, existingAttachments],
  () => scheduleAutoSave(),
  { deep: true }
);

onUnmounted(() => {
  // Flush any pending unsaved change before leaving so nothing is lost.
  clearTimeout(autoSaveTimer);
  if (autoSaveReady.value && autoSaveDirty && !autoSaveInFlight) {
    syncAttachmentsToRecord();
    invoicesStore.autoSaveResult().catch(() => {});
  }
});

watch(activeTestTab, () => {
  loadTemplate();
});

// When all-tab is empty (only custom HTML-template tests), don't leave 'all' selected.
watch(allTabTests, (list) => {
  if (!list.length && activeTestTab.value === "all") activeTestTab.value = 0;
});

// =============== Print / Download / WhatsApp actions (mirrors medical_reports/index.vue) ===============

const openPrintAction = async (withBg = false) => {
  closeAllMenus();
  const id = updateResultRecord.value?.id || route.params.id;
  if (!id) return;
  invoicesStore.changeInvoiceStatus(id, withBg);
  await invoicesStore.GetinvoicesById(id);
  printSelectItem.value = updateResultRecord.value;
  printSelectMode.value = withBg ? "background" : "normal";
  printSelectVisible.value = true;
};

const openDownloadAction = async (withBg = false) => {
  closeAllMenus();
  const id = updateResultRecord.value?.id || route.params.id;
  if (!id) return;
  await invoicesStore.GetinvoicesById(id);
  printSelectItem.value = updateResultRecord.value;
  printSelectMode.value = withBg ? "download-bg" : "download";
  printSelectVisible.value = true;
};

const openWhatsAppAction = async (withBg = false) => {
  closeAllMenus();
  const id = updateResultRecord.value?.id || route.params.id;
  if (!id) return;
  invoicesStore.changeInvoiceStatus(id, withBg);
  await invoicesStore.GetinvoicesById(id);
  printSelectItem.value = updateResultRecord.value;
  printSelectMode.value = withBg ? "whatsapp-bg" : "whatsapp";
  printSelectVisible.value = true;
};

const sendWhatsApp = (rec, withBg = false) => {
  const labName = labSettingsStore.settings.lab_display_name || User.value?.name || "المختبر";
  const patientName = rec?.patient?.name || "المريض";
  const appUrl = import.meta.env.VITE_APP_URL || window.location.origin;
  const resultLink = `${appUrl}/result/${rec.id}${withBg ? "?form=1" : ""}`;
  const message = `اهلا بكم في مختبر ${labName}\nعزيزي ${patientName}\nإليك نتائج الفحوصات الطبية:\n${resultLink}`;
  let phone = rec?.patient?.phone?.replace(/\s+/g, "");
  if (phone?.startsWith("+")) phone = phone.substring(1);
  if (phone?.startsWith("00")) phone = phone.substring(2);
  if (phone?.startsWith("0")) phone = "964" + phone.substring(1);
  if (!phone?.startsWith("964")) phone = "964" + phone;
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(messageTemplate(labSettingsStore.settings.whatsapp_result_message,{lab_name:labName,patient_name:patientName,invoice_number:rec.id,link:resultLink},message))}`, "_blank");
};

const downloadAsPdf = async (withBg) => {
  if (downloadInProgress.value) return;
  downloadInProgress.value = true;
  printResultRef.value?.beginCapture?.();
  await nextTick();
  await new Promise((r) => setTimeout(r, 300));

  let iframe = null;
  try {
    const sourceEl = document.getElementById("Result");
    if (!sourceEl) { toast.error(t("download_failed") || "Download failed"); return; }
    const content = sourceEl.innerHTML;
    if (!content?.trim()) { toast.error(t("download_failed") || "Download failed"); return; }

    const rawMargins = labSettingsStore.settings.print_margins || {};
    const margins = {
      top: Number(rawMargins.top ?? 20),
      bottom: Number(rawMargins.bottom ?? 20),
      left: Number(rawMargins.left ?? 15),
      right: Number(rawMargins.right ?? 15),
    };
    const bgImage = withBg ? reportBackground.value : null;

    let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(labSettingsStore.settings.patient_header_config) + printStyles.getPrintTableCss(labSettingsStore.settings.print_table_config);
    if (labSettingsStore.settings.print_black_white) css += printStyles.getBlackWhiteCss();
    css += `
      body { padding: 0 !important; margin: 0 !important; background: #fff; }
      .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
      .pw-cell.pw-top { padding-top: 0 !important; }
      .pw-cell.pw-bottom { padding-bottom: 0 !important; }
      .print-bg { position: absolute; top: 0; left: 0; width: 210mm; height: 297mm; z-index: -1; }
      .print-bg img { width: 100%; height: 100%; display: block; }
    `;

    iframe = document.createElement("iframe");
    iframe.style.cssText = "position:fixed;left:-10000px;top:0;width:210mm;height:297mm;border:0;";
    document.body.appendChild(iframe);
    iframe.contentDocument.write(`<!DOCTYPE html><html><head><style>${css}</style></head><body>${bgImage ? `<div class="print-bg"><img src="${bgImage}" /></div>` : ""}${content}</body></html>`);
    iframe.contentDocument.close();
    await new Promise((r) => setTimeout(r, 500));

    const html2canvas = (await import("html2canvas-pro")).default;
    const { jsPDF } = await import("jspdf");
    const pdf = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    const pageW = 210, pageH = 297;
    const wrappers = iframe.contentDocument.querySelectorAll("table.print-wrapper, .print-wrapper");
    const targets = wrappers.length ? Array.from(wrappers) : [iframe.contentDocument.body];

    for (let i = 0; i < targets.length; i++) {
      const canvas = await html2canvas(targets[i], { scale: 2, useCORS: true, backgroundColor: null });
      const imgData = canvas.toDataURL("image/jpeg", 0.95);
      const imgW = pageW - margins.left - margins.right;
      const imgH = (canvas.height * imgW) / canvas.width;
      if (i > 0) pdf.addPage();
      pdf.addImage(imgData, "JPEG", margins.left, margins.top, imgW, imgH);
    }

    const safeName = (printSelectItem.value?.patient?.name || "patient").replace(/[^\p{L}\p{N}\s_-]/gu, "");
    pdf.save(`${safeName}-${printSelectItem.value?.barcode || ""}.pdf`);
  } catch (e) {
    console.error("[download]", e);
    toast.error(t("download_failed") || "Download failed");
  } finally {
    if (iframe?.parentNode) iframe.parentNode.removeChild(iframe);
    printResultRef.value?.endCapture?.();
    downloadInProgress.value = false;
  }
};

const printDirectWithBackground = async () => {
  await nextTick();
  await new Promise((r) => setTimeout(r, 150));
  const content = document.getElementById("Result")?.innerHTML;
  if (!content) return;
  const printWindow = window.open("", "_blank");
  if (!printWindow) return;

  const margins = labSettingsStore.settings.print_margins || { top: 20, bottom: 20, left: 15, right: 15 };
  const bgImage = reportBackground.value;

  let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(labSettingsStore.settings.patient_header_config) + printStyles.getPrintTableCss(labSettingsStore.settings.print_table_config);
    if (labSettingsStore.settings.print_black_white) css += printStyles.getBlackWhiteCss();
  css += `
    body { padding: ${margins.top}mm ${margins.right}mm ${margins.bottom}mm ${margins.left}mm !important; -webkit-box-decoration-break: clone; box-decoration-break: clone; margin: 0 !important; }
    .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
    .pw-cell.pw-top { padding-top: 0 !important; }
    .pw-cell.pw-bottom { padding-bottom: 0 !important; }
    .print-bg { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; z-index: -1; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .print-bg img { width: 100%; height: 100%; display: block; }
  `;

  printWindow.document.write(`<!DOCTYPE html><html><head><title>Print Result</title><style>${css}</style></head><body>${bgImage ? `<div class="print-bg"><img src="${bgImage}" /></div>` : ""}${content}</body></html>`);
  printWindow.document.close();

  const img = new Image();
  img.onload = img.onerror = () => {
    setTimeout(() => { printWindow.focus(); printWindow.print(); }, 200);
  };
  if (bgImage) img.src = bgImage; else img.onload();
};

const handlePrintSelection = async (selection) => {
  const original = {
    tests: [...(printRecord.value.tests || [])],
    cultures: [...(printRecord.value.cultures || [])],
    packages: [...(printRecord.value.packages || [])],
    test_groups: [...(printRecord.value.test_groups || [])],
  };
  printRecord.value.tests = original.tests.filter((_, i) => selection.tests.includes(i));
  printRecord.value.cultures = original.cultures.filter((_, i) => selection.cultures.includes(i));
  printRecord.value.packages = original.packages.filter((_, i) => selection.packages.includes(i));
  printRecord.value.test_groups = original.test_groups.filter((_, i) => selection.testGroups.includes(i));

  await nextTick();
  await new Promise((r) => setTimeout(r, 200));

  if (selection.mode === "whatsapp" || selection.mode === "whatsapp-bg") {
    sendWhatsApp(printSelectItem.value, selection.mode === "whatsapp-bg");
  } else if (selection.mode === "download" || selection.mode === "download-bg") {
    await downloadAsPdf(selection.mode === "download-bg");
  } else if (selection.mode === "normal") {
    const margins = labSettingsStore.settings.print_margins || { top: 20, bottom: 20, left: 15, right: 15 };
    let css = printStyles.getResultCss() + printStyles.getPatientHeaderCss(labSettingsStore.settings.patient_header_config) + printStyles.getPrintTableCss(labSettingsStore.settings.print_table_config);
    if (labSettingsStore.settings.print_black_white) css += printStyles.getBlackWhiteCss();
    css += `
      body { padding: ${margins.top}mm ${margins.right}mm ${margins.bottom}mm ${margins.left}mm !important; -webkit-box-decoration-break: clone; box-decoration-break: clone; margin: 0 !important; }
      .pw-cell { padding-left: 0 !important; padding-right: 0 !important; }
      .pw-cell.pw-top { padding-top: 0 !important; }
      .pw-cell.pw-bottom { padding-bottom: 0 !important; }
    `;
    await printWithIframe("Result", css, "Print Result", 100);
  } else {
    await printDirectWithBackground();
  }

  printRecord.value.tests = original.tests;
  printRecord.value.cultures = original.cultures;
  printRecord.value.packages = original.packages;
  printRecord.value.test_groups = original.test_groups;
};
</script>

<style scoped>
/* Sticky top bar */
.sticky {
  position: sticky;
}

/* === UI-UX Pro Max: Accessible & Ethical (WCAG AAA) === */

/* Touch targets: 44x44 min on interactive elements (mobile + a11y) */
button:not(.sr-only),
[role="button"],
input[type="text"],
input[type="number"],
input[type="email"],
input[type="tel"],
select,
textarea {
  min-height: 44px;
}

/* Override: chip/comment-pill buttons stay compact */
button.text-xs {
  min-height: 0;
}

/* Stronger focus rings — 4px + offset for keyboard nav (Pro Max spec) */
button:focus-visible,
[role="button"]:focus-visible,
a:focus-visible {
  outline: none;
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.25);
  border-radius: 0.5rem;
}

input:focus-visible,
select:focus-visible,
textarea:focus-visible {
  outline: none;
}

/* Cursor on all interactive elements */
button:not(:disabled),
[role="button"]:not([aria-disabled="true"]),
label[for],
.cursor-pointer {
  cursor: pointer;
}

button:disabled,
[aria-disabled="true"] {
  cursor: not-allowed;
}

/* Respect prefers-reduced-motion (Pro Max critical rule) */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
  .animate-ping,
  .animate-spin,
  .animate-pulse {
    animation: none !important;
  }
}

/* Body text 16px floor on mobile (Pro Max readable-font-size rule) */
@media (max-width: 640px) {
  input,
  select,
  textarea {
    font-size: 16px; /* prevents iOS zoom + readable */
  }
}

/* Smooth tab transitions in spec window 150-300ms */
button[type="button"] {
  transition-duration: 200ms;
}
</style>
