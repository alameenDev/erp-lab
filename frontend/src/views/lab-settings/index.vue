<script setup>
import { ref, computed, onMounted } from "vue";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { storeToRefs } from "pinia";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import { applyBranding } from "@/utils/branding";
import DocumentPreview from "./DocumentPreview.vue";
import LoyaltySettings from "./LoyaltySettings.vue";

import { documentDefaults, documentConfig } from "@/utils/labDocuments";
const documents = ref(JSON.parse(JSON.stringify(documentDefaults)));
const communication = ref({invoice:"",result:""});
const toast = useToast();
const store = useLabSettingsStore();
const { settings, isLoading } = storeToRefs(store);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const activeTab = ref("branding");
const printSection = ref("layout");
const previewKind = ref("report");
const sections = computed(() => lang.value === "en"
 ? [{id:"layout",label:"Paper & visibility"},{id:"barcode",label:"Barcode labels"},{id:"header",label:"Patient header"},{id:"table",label:"Result tables"},{id:"background",label:"Report background"}]
 : [{id:"layout",label:"الورق وإظهار الحقول"},{id:"barcode",label:"ملصقات الباركود"},{id:"header",label:"رأس التقرير"},{id:"table",label:"جداول النتائج"},{id:"background",label:"خلفية التقرير"}]);
const previewCell = computed(() => ({
 fontSize: (settings.value.print_table_config?.body_font_size ?? 13) + "px",
 color: settings.value.print_table_config?.body_color || "#1e293b",
 backgroundColor: settings.value.print_table_config?.body_bg_color || "#ffffff",
 padding: (settings.value.print_table_config?.cell_padding ?? 6) + "px",
 border: "1px solid " + (settings.value.print_table_config?.border_color || "#e2e8f0"),
}));
const previewHeading = computed(() => ({
 ...previewCell.value,
 fontSize: (settings.value.print_table_config?.header_font_size ?? 13) + "px",
 color: settings.value.print_table_config?.header_color || "#0f172a",
 backgroundColor: settings.value.print_table_config?.header_bg_color || "#f1f5f9",
 fontWeight: settings.value.print_table_config?.header_font_weight || "bold",
}));

const logoInput = ref(null);
const bgInput = ref(null);
const logoPreview = ref(null);
const bgPreview = ref(null);
const logoFile = ref(null);
const bgFile = ref(null);
const saving = ref(false);
const resetting = ref(false);
const imageUrl = import.meta.env.VITE_ImageURL || "";

const fonts = [
     { label: "Tajawal", value: "Tajawal" },
     { label: "Cairo", value: "Cairo" },
     { label: "Amiri", value: "Amiri" },
     { label: "Inter", value: "Inter" },
];

onMounted(async () => {
     await store.GetSettings();
     const s = settings.value;
     documents.value = {invoice:documentConfig(s,'invoice'),thermal:documentConfig(s,'thermal')};
     communication.value = {invoice:s.whatsapp_invoice_message || '',result:s.whatsapp_result_message || ''};
     if (s.logo) logoPreview.value = s.logo.startsWith("http") ? s.logo : imageUrl + "/" + s.logo;
     if (s.report_background) bgPreview.value = s.report_background.startsWith("http") ? s.report_background : imageUrl + "/" + s.report_background;
     if (!s.print_margins) settings.value.print_margins = { top: 20, bottom: 20, left: 15, right: 15 };
     if (!s.barcode_config) settings.value.barcode_config = { label_width: 3, label_height: 1.5, name_size: 9, info_size: 7, number_size: 6, barcode_height: 40, sample_size: 8, tests_size: 7 };
     if (!s.patient_header_config) settings.value.patient_header_config = { name_size: 20, info_size: 13, barcode_height: 35, qr_size: 90, line_height: 1.7 };
     if (!s.print_table_config) settings.value.print_table_config = {
          header_font_size: 13, header_font_family: "inherit", header_color: "#0f172a",
          header_bg_color: "#f1f5f9", header_font_weight: "bold",
          body_font_size: 13, body_font_family: "inherit", body_color: "#1e293b",
          body_bg_color: "#ffffff", border_color: "#e2e8f0",
          cell_padding: 6, section_spacing: 12,
     };
});

const onLogoChange = (e) => {
     const file = e.target.files[0];
     if (!file) return;
     logoFile.value = file;
     const reader = new FileReader();
     reader.onload = (ev) => { logoPreview.value = ev.target.result; };
     reader.readAsDataURL(file);
};

const removeLogo = async () => {
     try { await store.RemoveLogo(); logoPreview.value = null; logoFile.value = null; toast.success(t("alertSuccess")); } catch { toast.error(t("error")); }
};

const onBgChange = (e) => {
     const file = e.target.files[0];
     if (!file) return;
     bgFile.value = file;
     const reader = new FileReader();
     reader.onload = (ev) => { bgPreview.value = ev.target.result; };
     reader.readAsDataURL(file);
};

const removeBg = async () => {
     // If the user just picked a new file but hasn't saved yet, just discard it locally
     if (bgFile.value) {
          bgFile.value = null;
          // Restore the previously-saved preview if the server has one
          if (settings.value.report_background) {
               bgPreview.value = settings.value.report_background.startsWith("http")
                    ? settings.value.report_background
                    : imageUrl + "/" + settings.value.report_background;
          } else {
               bgPreview.value = null;
          }
          if (bgInput.value) bgInput.value.value = "";
          return;
     }
     // Otherwise persist the removal on the backend
     try {
          await store.RemoveBackground();
          bgPreview.value = null;
          bgFile.value = null;
          if (bgInput.value) bgInput.value.value = "";
          toast.success(t("alertSuccess"));
     } catch {
          toast.error(t("error"));
     }
};

const save = async () => {
     saving.value = true;
     try {
          const formData = new FormData();
          for (const [kind, config] of Object.entries(documents.value)) {
               for (const [key, value] of Object.entries(config)) {
                    formData.append(`document_config[${kind}][${key}]`, typeof value === "boolean" ? (value ? "1" : "0") : value);
               }
          }
          formData.append("whatsapp_invoice_message", communication.value.invoice);
          formData.append("whatsapp_result_message", communication.value.result);
          if (logoFile.value) formData.append("logo", logoFile.value);
          if (bgFile.value) formData.append("report_background", bgFile.value);
          formData.append("primary_color", settings.value.primary_color || "#0d9488");
          formData.append("secondary_color", settings.value.secondary_color || "#14b8a6");
          formData.append("font_family", settings.value.font_family || "Tajawal");
          formData.append("lab_display_name", settings.value.lab_display_name || "");
          formData.append("tagline", settings.value.tagline || "");
          formData.append("print_margins[top]", settings.value.print_margins?.top ?? 20);
          formData.append("print_margins[bottom]", settings.value.print_margins?.bottom ?? 20);
          formData.append("print_margins[left]", settings.value.print_margins?.left ?? 15);
          formData.append("print_margins[right]", settings.value.print_margins?.right ?? 15);
          formData.append("show_categories", settings.value.show_categories ? "1" : "0");
          formData.append("show_tests_on_barcode", settings.value.show_tests_on_barcode ? "1" : "0");
          formData.append("show_test_names", settings.value.show_test_names ? "1" : "0");
          formData.append("show_status", settings.value.show_status ? "1" : "0");
          formData.append("show_last_result", settings.value.show_last_result ? "1" : "0");
          formData.append("print_black_white", settings.value.print_black_white ? "1" : "0");
          const bc = settings.value.barcode_config || {};
          formData.append("barcode_config[label_width]", bc.label_width ?? 3);
          formData.append("barcode_config[label_height]", bc.label_height ?? 1.5);
          formData.append("barcode_config[name_size]", bc.name_size ?? 9);
          formData.append("barcode_config[info_size]", bc.info_size ?? 7);
          formData.append("barcode_config[number_size]", bc.number_size ?? 6);
          formData.append("barcode_config[barcode_height]", bc.barcode_height ?? 40);
          formData.append("barcode_config[sample_size]", bc.sample_size ?? 8);
          formData.append("barcode_config[tests_size]", bc.tests_size ?? 7);

          const ph = settings.value.patient_header_config || {};
          formData.append("patient_header_config[name_size]", ph.name_size ?? 20);
          formData.append("patient_header_config[info_size]", ph.info_size ?? 13);
          formData.append("patient_header_config[barcode_height]", ph.barcode_height ?? 35);
          formData.append("patient_header_config[qr_size]", ph.qr_size ?? 90);
          formData.append("patient_header_config[line_height]", ph.line_height ?? 1.7);

          const pt = settings.value.print_table_config || {};
          formData.append("print_table_config[header_font_size]", pt.header_font_size ?? 13);
          formData.append("print_table_config[header_font_family]", pt.header_font_family || "inherit");
          formData.append("print_table_config[header_color]", pt.header_color || "#0f172a");
          formData.append("print_table_config[header_bg_color]", pt.header_bg_color || "#f1f5f9");
          formData.append("print_table_config[header_font_weight]", pt.header_font_weight || "bold");
          formData.append("print_table_config[body_font_size]", pt.body_font_size ?? 13);
          formData.append("print_table_config[body_font_family]", pt.body_font_family || "inherit");
          formData.append("print_table_config[body_color]", pt.body_color || "#1e293b");
          formData.append("print_table_config[body_bg_color]", pt.body_bg_color || "#ffffff");
          formData.append("print_table_config[border_color]", pt.border_color || "#e2e8f0");
          formData.append("print_table_config[cell_padding]", pt.cell_padding ?? 6);
          formData.append("print_table_config[section_spacing]", pt.section_spacing ?? 12);

          await store.UpdateSettings(formData);
          logoFile.value = null;
          bgFile.value = null;
          if (bgInput.value) bgInput.value.value = "";
          if (logoInput.value) logoInput.value.value = "";

          toast.success(t("alertSuccess"));
     } catch (e) {
          // Surface the actual backend message (e.g. "file too large", "wrong format")
          const apiErrors = e?.response?.data?.errors;
          let msg = e?.response?.data?.message || t("error");
          if (apiErrors && typeof apiErrors === "object") {
               const first = Object.values(apiErrors)[0];
               if (Array.isArray(first) && first.length) msg = first[0];
          }
          toast.error(msg, { duration: 5000 });
     } finally { saving.value = false; }
};

const resetBranding = async () => {
     const msg = activeTab.value === "branding"
          ? (t("reset_branding_confirm") || "هل أنت متأكد من إعادة تعيين العلامة التجارية؟")
          : (t("reset_print_confirm") || "هل أنت متأكد من إعادة تعيين إعدادات الطباعة؟");
     const { value } = await showAlertWithConfirm(msg);
     if (!value) return;
     resetting.value = true;
     try {
          await store.ResetSettings(activeTab.value);
          documents.value = {invoice:documentConfig(settings.value,'invoice'),thermal:documentConfig(settings.value,'thermal')};
          if (activeTab.value === "branding") {
               logoPreview.value = null;
               logoFile.value = null;
          } else {
               bgPreview.value = null;
               bgFile.value = null;
          }
          toast.success(t("alertSuccess"));
     } catch { toast.error(t("error")); } finally { resetting.value = false; }
};
</script>

<template>
     <div class="max-w-7xl mx-auto space-y-6">
          <!-- Header -->
          <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
               <div>
                    <h1 class="text-2xl font-bold text-slate-800">{{ t("lab_settings") }}</h1>
                    <p class="text-sm text-slate-500 mt-1">{{ t("lab_settings_desc") }}</p>
               </div>
               <button @click="resetBranding" :disabled="resetting" class="px-4 py-2 text-sm font-medium bg-red-50 text-red-600 rounded-xl hover:bg-red-100 border border-red-200 transition-colors flex items-center gap-2 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    {{ t("reset_branding") }}
               </button>
          </div>

          <!-- Tabs -->
          <div class="flex gap-2">
               <button @click="activeTab = 'branding'" :class="['px-5 py-2.5 text-sm font-medium rounded-xl transition-colors', activeTab === 'branding' ? 'bg-primary-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50']">
                    {{ t("branding") || "العلامة التجارية" }}
               </button>
               <button @click="activeTab = 'print'" :class="['px-5 py-2.5 text-sm font-medium rounded-xl transition-colors', activeTab === 'print' ? 'bg-primary-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50']">
                    {{ t("print_settings") || "إعدادات الطباعة" }}
               </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
               <!-- Main Content (3 cols) -->
               <div class="lg:col-span-3 space-y-6">

                    <!-- ==================== BRANDING TAB ==================== -->
                    <template v-if="activeTab === 'branding'">
                         <!-- Logo -->
                         <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-4">{{ t("logo") }}</h3>
                              <div class="flex items-start gap-4">
                                   <div class="w-24 h-24 rounded-xl border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden bg-slate-50 shrink-0">
                                        <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="w-full h-full object-contain" />
                                        <svg v-else class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                   </div>
                                   <div class="flex-1 space-y-2">
                                        <button @click="logoInput?.click()" class="px-4 py-2 text-sm font-medium bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 transition-colors">{{ logoPreview ? t("change_logo") : t("upload_logo") }}</button>
                                        <button v-if="logoPreview" @click="removeLogo" class="px-4 py-2 text-sm font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors ms-2">{{ t("delete") }}</button>
                                        <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
                                        <p class="text-xs text-slate-400">PNG, JPG, WebP — {{ t("max") }} 5MB</p>
                                   </div>
                              </div>
                         </div>

                         <!-- Colors -->
                         <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-4">{{ t("colors") }}</h3>
                              <div class="grid grid-cols-2 gap-4">
                                   <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("primary_color") }}</label>
                                        <div class="flex items-center gap-3">
                                             <input type="color" v-model="settings.primary_color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer" />
                                             <input type="text" v-model="settings.primary_color" class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg font-mono" maxlength="9" />
                                        </div>
                                   </div>
                                   <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("secondary_color") }}</label>
                                        <div class="flex items-center gap-3">
                                             <input type="color" v-model="settings.secondary_color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer" />
                                             <input type="text" v-model="settings.secondary_color" class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg font-mono" maxlength="9" />
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <!-- Font + Lab Info -->
                         <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-4">{{ t("lab_info") }}</h3>
                              <div class="space-y-4">
                                   <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("font") }}</label>
                                        <select v-model="settings.font_family" class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm">
                                             <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
                                        </select>
                                   </div>
                                   <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("lab_display_name") }}</label>
                                        <input type="text" v-model="settings.lab_display_name" class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm" />
                                   </div>
                                   <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("tagline") }}</label>
                                        <input type="text" v-model="settings.tagline" class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm" />
                                   </div>
                              </div>
                         </div>
                    </template>

                    <!-- ==================== PRINT TAB ==================== -->
                    <template v-if="activeTab === 'print'">
<nav aria-label="Print settings sections" class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-3">
<button v-for="section in sections" :key="section.id" @click="printSection = section.id" :aria-pressed="printSection === section.id" :class="['rounded-xl px-3 py-2 text-sm transition-colors', printSection === section.id ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100']">{{ section.label }}</button>
</nav>
                         <!-- Margins -->
                         <div v-show="printSection === 'layout'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-4">{{ t("print_margins") || "هوامش الطباعة" }}</h3>
                              <div class="grid grid-cols-2 gap-4">
                                   <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ t("margin_top") || "الهامش العلوي" }} (mm)</label>
                                        <input type="number" v-model.number="settings.print_margins.top" min="0" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                   </div>
                                   <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ t("margin_bottom") || "الهامش السفلي" }} (mm)</label>
                                        <input type="number" v-model.number="settings.print_margins.bottom" min="0" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                   </div>
                                   <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ t("margin_left") || "الهامش الأيسر" }} (mm)</label>
                                        <input type="number" v-model.number="settings.print_margins.left" min="0" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                   </div>
                                   <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ t("margin_right") || "الهامش الأيمن" }} (mm)</label>
                                        <input type="number" v-model.number="settings.print_margins.right" min="0" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                   </div>
                              </div>
                         </div>

                         <!-- Print Options -->
                         <div v-show="printSection === 'layout'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-4">{{ t("print_options") || "خيارات الطباعة" }}</h3>
                              <div class="space-y-3">
                                   <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                                        <input type="checkbox" id="showCat" v-model="settings.show_categories" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500" />
                                        <label for="showCat" class="text-sm font-medium text-slate-700 cursor-pointer select-none">{{ t("show_categories_print") || "إظهار الأقسام في الطباعة والواتساب" }}</label>
                                   </div>
                                   <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                                        <input type="checkbox" id="showBarTests" v-model="settings.show_tests_on_barcode" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500" />
                                        <label for="showBarTests" class="text-sm font-medium text-slate-700 cursor-pointer select-none">{{ t("show_tests_barcode") || "إظهار التحاليل في طباعة الباركود" }}</label>
                                   </div>
                                   <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                                        <input type="checkbox" id="showTestName" v-model="settings.show_test_names" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500" />
                                        <label for="showTestName" class="text-sm font-medium text-slate-700 cursor-pointer select-none">{{ t("show_test_names_print") || "إظهار اسم التحليل في الطباعة" }}</label>
                                   </div>
                                   <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                                        <input type="checkbox" id="showStatus" v-model="settings.show_status" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500" />
                                        <label for="showStatus" class="text-sm font-medium text-slate-700 cursor-pointer select-none">{{ t("show_status_print") || "إظهار الحالة في الطباعة" }}</label>
                                   </div>
                                   <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                                        <input type="checkbox" id="showLastResult" v-model="settings.show_last_result" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500" />
                                        <label for="showLastResult" class="text-sm font-medium text-slate-700 cursor-pointer select-none">{{ t("show_last_result_print") || "إظهار النتيجة السابقة في الطباعة" }}</label>
                                   </div>
                                   <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                                        <input type="checkbox" id="printBlackWhite" v-model="settings.print_black_white" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500" />
                                        <label for="printBlackWhite" class="text-sm font-medium text-slate-700 cursor-pointer select-none">{{ t("print_black_white") || "الطباعة بالأبيض والأسود (بدون ألوان)" }}</label>
                                   </div>
                              </div>
                         </div>

                         <!-- Barcode Label Config -->
                         <div v-show="printSection === 'barcode'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-1">{{ t("barcode_config") || "إعدادات ملصق الباركود (الستيكر الفيزيائي)" }}</h3>
                              <p class="text-xs text-slate-500 mb-4">{{ t("barcode_config_hint") || "أحجام الستيكر اللاصق الذي يطبع على طابعة الباركود (3×1.5 إنش). مختلف عن رأس صفحة النتيجة." }}</p>
                              <div class="space-y-4">
                                   <!-- Label Size -->
                                   <div>
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ t("label_size") || "حجم الملصق (بالإنش)" }}</p>
                                        <div class="grid grid-cols-2 gap-3">
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("width") || "العرض" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.label_width" min="1" max="10" step="0.25" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("height") || "الارتفاع" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.label_height" min="0.5" max="10" step="0.25" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                        </div>
                                   </div>
                                   <!-- Font Sizes -->
                                   <div>
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ t("font_sizes") || "أحجام الخط (pt)" }}</p>
                                        <div class="grid grid-cols-3 gap-3">
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("patient_name") || "اسم المريض" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.name_size" min="4" max="24" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("info_line") || "معلومات المريض" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.info_size" min="4" max="24" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("invoice_number") || "رقم الفاتورة" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.number_size" min="4" max="24" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("sample_name") || "اسم العينة" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.sample_size" min="4" max="24" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("tests") || "التحاليل" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.tests_size" min="4" max="24" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("barcode_height") || "ارتفاع الباركود (px)" }}</label>
                                                  <input type="number" v-model.number="settings.barcode_config.barcode_height" min="20" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <!-- Result-page header (Print / PDF / WhatsApp) — DIFFERENT from the barcode-sticker card above -->
                         <div v-show="printSection === 'header'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-1">{{ t("patient_header_config") || "إعدادات رأس صفحة النتيجة" }}</h3>
                              <p class="text-xs text-slate-500 mb-4">{{ t("patient_header_hint") || "يطبق على رأس صفحة النتيجة (الطباعة، PDF، واتساب). مختلف عن ملصق الباركود الفيزيائي أعلاه." }}</p>
                              <div class="space-y-4">
                                   <div>
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ t("phc_section_label") || "أحجام عناصر الرأس (px)" }}</p>
                                        <div class="grid grid-cols-3 gap-3">
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("phc_name_size") || "حجم اسم المريض" }}</label>
                                                  <input type="number" v-model.number="settings.patient_header_config.name_size" min="8" max="48" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("phc_info_size") || "حجم بيانات الرأس" }}</label>
                                                  <input type="number" v-model.number="settings.patient_header_config.info_size" min="8" max="32" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("phc_line_height") || "تباعد الأسطر" }}</label>
                                                  <input type="number" step="0.1" v-model.number="settings.patient_header_config.line_height" min="1" max="3" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("phc_barcode_height") || "ارتفاع باركود الرأس" }}</label>
                                                  <input type="number" v-model.number="settings.patient_header_config.barcode_height" min="15" max="120" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-500 mb-1">{{ t("phc_qr_size") || "حجم رمز QR" }}</label>
                                                  <input type="number" v-model.number="settings.patient_header_config.qr_size" min="30" max="200" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <!-- Print Table (Test|Result|Unit|Reference Range) -->
                         <div v-show="printSection === 'table'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-1">{{ t("print_table_config") || "إعدادات جدول التحاليل في الطباعة / PDF / WhatsApp" }}</h3>
                              <p class="text-xs text-slate-500 mb-4">{{ t("print_table_hint") || "تخصيص خط ولون وحدود وتباعد جدول النتائج (Test | Result | Unit | Reference Range)" }}</p>
                              <div class="space-y-5">
                                   <!-- Header row -->
                                   <div>
                                        <p class="text-sm font-semibold text-slate-700 mb-2">{{ t("table_header") || "رأس الجدول" }}</p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("font_size") || "حجم الخط" }}</label>
                                                  <input type="number" v-model.number="settings.print_table_config.header_font_size" min="6" max="32" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("font_family") || "نوع الخط" }}</label>
                                                  <select v-model="settings.print_table_config.header_font_family" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white">
                                                       <option value="inherit">inherit</option>
                                                       <option value="Tajawal">Tajawal</option>
                                                       <option value="Cairo">Cairo</option>
                                                       <option value="Amiri">Amiri</option>
                                                       <option value="Inter">Inter</option>
                                                       <option value="Arial">Arial</option>
                                                  </select>
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("font_weight") || "سمك الخط" }}</label>
                                                  <select v-model="settings.print_table_config.header_font_weight" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white">
                                                       <option value="normal">normal</option>
                                                       <option value="600">600</option>
                                                       <option value="700">700 (bold)</option>
                                                       <option value="800">800</option>
                                                  </select>
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("text_color") || "لون النص" }}</label>
                                                  <input type="color" v-model="settings.print_table_config.header_color" class="w-full h-10 px-1 py-1 border border-slate-200 rounded-lg" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("bg_color") || "لون الخلفية" }}</label>
                                                  <input type="color" v-model="settings.print_table_config.header_bg_color" class="w-full h-10 px-1 py-1 border border-slate-200 rounded-lg" />
                                             </div>
                                        </div>
                                   </div>

                                   <!-- Body row -->
                                   <div>
                                        <p class="text-sm font-semibold text-slate-700 mb-2">{{ t("table_body") || "محتوى الجدول" }}</p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("font_size") || "حجم الخط" }}</label>
                                                  <input type="number" v-model.number="settings.print_table_config.body_font_size" min="6" max="32" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("font_family") || "نوع الخط" }}</label>
                                                  <select v-model="settings.print_table_config.body_font_family" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white">
                                                       <option value="inherit">inherit</option>
                                                       <option value="Tajawal">Tajawal</option>
                                                       <option value="Cairo">Cairo</option>
                                                       <option value="Amiri">Amiri</option>
                                                       <option value="Inter">Inter</option>
                                                       <option value="Arial">Arial</option>
                                                  </select>
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("text_color") || "لون النص" }}</label>
                                                  <input type="color" v-model="settings.print_table_config.body_color" class="w-full h-10 px-1 py-1 border border-slate-200 rounded-lg" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("bg_color") || "لون الخلفية" }}</label>
                                                  <input type="color" v-model="settings.print_table_config.body_bg_color" class="w-full h-10 px-1 py-1 border border-slate-200 rounded-lg" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("border_color") || "لون الحدود" }}</label>
                                                  <input type="color" v-model="settings.print_table_config.border_color" class="w-full h-10 px-1 py-1 border border-slate-200 rounded-lg" />
                                             </div>
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("cell_padding") || "تباعد داخل الخلية (px)" }}</label>
                                                  <input type="number" v-model.number="settings.print_table_config.cell_padding" min="0" max="40" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                        </div>
                                   </div>

                                   <!-- Spacing between sections -->
                                   <div>
                                        <p class="text-sm font-semibold text-slate-700 mb-2">{{ t("table_spacing") || "التباعد بين الجداول" }}</p>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                             <div>
                                                  <label class="block text-xs text-slate-600 mb-1">{{ t("section_spacing") || "المسافة بين كل تحليل (px)" }}</label>
                                                  <input type="number" v-model.number="settings.print_table_config.section_spacing" min="0" max="80" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                                             </div>
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <!-- Report Background -->
                         <div v-show="printSection === 'background'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                              <h3 class="text-base font-semibold text-slate-800 mb-4">{{ t("report_background") || "خلفية التقرير" }}</h3>
                              <div v-if="bgPreview" class="relative group mb-3">
                                   <img :src="bgPreview" alt="Background" class="w-full h-40 object-contain border border-slate-200 rounded-lg bg-slate-50" />
                                   <button @click="removeBg" class="absolute top-2 end-2 w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-md">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                   </button>
                              </div>
                              <button @click="bgInput?.click()" class="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-slate-300 rounded-lg text-sm text-slate-600 hover:border-primary-400 hover:text-primary-600 hover:bg-primary-50/50 transition-all">
                                   <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                   {{ bgPreview ? t("change_background") || "تغيير الخلفية" : t("upload_background") || "رفع صورة الخلفية" }}
                              </button>
                              <input ref="bgInput" type="file" accept="image/*" class="hidden" @change="onBgChange" />
                              <p class="text-xs text-slate-400 mt-1.5">{{ t("background_hint") || "صورة A4 بالحجم الكامل تُستخدم كخلفية عند الطباعة" }}</p>
                         </div>
                    </template>


                    <section v-if="activeTab === 'print'" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-5">
                         <h3 class="font-bold text-slate-800">{{ lang === 'en' ? 'Invoice & thermal printing' : 'الفاتورة والطباعة الحرارية' }}</h3>
                         <div v-for="kind in ['invoice','thermal']" :key="kind" class="rounded-xl border border-slate-200 p-4 space-y-4">
                              <h4 class="font-semibold">{{ kind === 'invoice' ? (lang === 'en' ? 'Standard invoice' : 'الفاتورة العادية') : (lang === 'en' ? 'Thermal receipt' : 'الفاتورة الحرارية') }}</h4>
                              <div class="grid gap-4 sm:grid-cols-2">
                                   <label v-if="kind === 'invoice'" class="text-sm">Paper / الورق
                                        <select v-model="documents[kind].paper" class="block w-full border rounded-lg p-2"><option>A4</option><option>A5</option></select>
                                   </label>
                                   <label v-if="kind === 'invoice'" class="text-sm">Orientation / الاتجاه
                                        <select v-model="documents[kind].orientation" class="block w-full border rounded-lg p-2"><option value="portrait">عمودي / Portrait</option><option value="landscape">أفقي / Landscape</option></select>
                                   </label>
                                   <label v-if="kind === 'thermal'" class="text-sm">Width / العرض (mm)
                                        <select v-model.number="documents[kind].width" class="block w-full border rounded-lg p-2"><option :value="58">58 mm</option><option :value="80">80 mm</option></select>
                                   </label>
                                   <label class="text-sm">Margin / الهامش (mm)<input v-model.number="documents[kind].margin" type="number" min="0" :max="kind === 'thermal' ? 10 : 30" class="block w-full border rounded-lg p-2"></label>
                                   <label class="text-sm">Font size / حجم الخط (px)<input v-model.number="documents[kind].font_size" type="number" min="8" max="24" class="block w-full border rounded-lg p-2"></label>
                                   <label v-if="kind === 'invoice'" class="text-sm">Text color / لون النص<input v-model="documents[kind].color" type="color" class="block"></label>
                                   <label v-if="kind === 'invoice'" class="text-sm">Title color / لون العنوان<input v-model="documents[kind].accent" type="color" class="block"></label>
                              </div>
                              <div class="flex flex-wrap gap-4 text-sm"><label><input v-model="documents[kind].show_barcode" type="checkbox"> Barcode / باركود</label><label><input v-model="documents[kind].show_qr" type="checkbox"> QR / رابط النتائج</label></div>
                              <label class="block text-sm">Footer / النص أسفل الفاتورة<textarea v-model="documents[kind].footer" maxlength="500" rows="2" class="block w-full border rounded-lg p-2"></textarea></label>
                         </div>
                    </section>
                    <section v-if="activeTab === 'branding'" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-4">
                         <h3 class="font-bold text-slate-800">{{ lang === 'en' ? 'WhatsApp messages' : 'رسائل واتساب' }}</h3>
                         <p class="text-xs text-slate-500">{{ lang === 'en' ? 'Leave blank to keep the existing message. Supported variables:' : 'اترك الحقل فارغاً لاستخدام الرسالة الأصلية. المتغيرات المتاحة:' }}</p>
                         <code v-pre class="block text-xs" dir="ltr">{lab_name} · {patient_name} · {invoice_number} · {link}</code>
                         <label class="block text-sm">Invoice message / رسالة الفاتورة<textarea v-model="communication.invoice" rows="4" maxlength="3000" class="block w-full border rounded-lg p-3"></textarea></label>
                         <label class="block text-sm">Result message / رسالة النتائج<textarea v-model="communication.result" rows="4" maxlength="3000" class="block w-full border rounded-lg p-3"></textarea></label>
                    </section>
                    <LoyaltySettings v-if="activeTab === 'branding'" :config="settings.loyalty_config" @saved="store.GetSettings()" />
                    <!-- Save Button -->
                    <button @click="save" :disabled="saving" class="w-full px-6 py-3 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition-colors font-medium flex items-center justify-center gap-2 disabled:opacity-50">
                         <svg v-if="saving" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                         <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                         {{ t("save") }}
                    </button>
               </div>

               <!-- Live Preview (2 cols) -->
               <div class="lg:col-span-2">
                    <div class="lg:sticky lg:top-6">
                         <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                              <div class="px-5 py-3 border-b border-slate-100">
                                   <h3 class="text-sm font-semibold text-slate-600">{{ t("preview") }}</h3>
                              </div>
                              <div class="p-5">
                                   <div v-if="activeTab === 'branding'" class="border border-slate-200 rounded-xl overflow-hidden" :style="{ fontFamily: (settings.font_family || 'Tajawal') + ', sans-serif' }">
                                        <div class="p-4 text-white text-center" :style="{ background: `linear-gradient(135deg, ${settings.primary_color || '#0d9488'}, ${settings.secondary_color || '#14b8a6'})` }">
                                             <div v-if="logoPreview" class="w-16 h-16 mx-auto mb-2 bg-white/20 rounded-xl flex items-center justify-center overflow-hidden">
                                                  <img :src="logoPreview" alt="Logo" class="w-14 h-14 object-contain" />
                                             </div>
                                             <div v-else class="w-16 h-16 mx-auto mb-2 bg-white/20 rounded-xl flex items-center justify-center">
                                                  <svg class="w-8 h-8 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                             </div>
                                             <h4 class="font-bold text-sm">{{ settings.lab_display_name || t("lab_name_preview") }}</h4>
                                             <p v-if="settings.tagline" class="text-xs opacity-80 mt-0.5">{{ settings.tagline }}</p>
                                        </div>
                                        <div class="p-4 space-y-2">
                                             <div class="h-2 rounded-full w-3/4" :style="{ backgroundColor: (settings.primary_color || '#0d9488') + '20' }"></div>
                                             <div class="h-2 rounded-full w-1/2" :style="{ backgroundColor: (settings.secondary_color || '#14b8a6') + '20' }"></div>
                                             <div class="mt-3 flex gap-2">
                                                  <div class="px-3 py-1.5 rounded-lg text-white text-xs font-medium" :style="{ backgroundColor: settings.primary_color || '#0d9488' }">{{ t("button") }}</div>
                                                  <div class="px-3 py-1.5 rounded-lg text-xs font-medium border" :style="{ borderColor: settings.secondary_color || '#14b8a6', color: settings.secondary_color || '#14b8a6' }">{{ t("button") }}</div>
                                             </div>
                                        </div>
                                   </div>
                                   
<label v-if="activeTab === 'print'" class="block text-sm mb-3">نوع المعاينة
<select v-model="previewKind" class="block w-full border rounded-lg p-2 mt-1"><option value="report">التقرير الطبي</option><option value="invoice">الفاتورة</option><option value="thermal">الفاتورة الحرارية</option></select></label>
<DocumentPreview v-if="activeTab === 'print' && previewKind !== 'report'" :settings="settings" :documents="documents" :kind="previewKind" />
<section v-if="activeTab === 'print' && previewKind === 'report'" class="overflow-auto rounded-xl border border-slate-200 bg-slate-100 p-4">
<p class="mb-3 text-xs text-slate-500">{{ lang === 'en' ? 'Illustrative preview · sample data, not a clinical report' : 'معاينة توضيحية ببيانات تجريبية — ليست تقريراً طبياً' }}</p>
<div class="min-w-[300px] bg-white p-4 shadow-sm" :style="{fontFamily: settings.font_family || 'Tajawal', filter: settings.print_black_white ? 'grayscale(1)' : 'none'}">
<h4 class="mb-3 text-center font-bold" :style="{color: settings.primary_color}">{{ settings.lab_display_name || 'Digital Lab' }}</h4>
<p :style="{fontSize:(settings.patient_header_config?.name_size ?? 20)+'px',lineHeight:settings.patient_header_config?.line_height ?? 1.7}">{{ lang === 'en' ? 'Sample patient' : 'مريض تجريبي' }}</p>
<p class="mb-4 text-slate-500" :style="{fontSize:(settings.patient_header_config?.info_size ?? 13)+'px'}">LAB-0001</p>
<table class="w-full border-collapse" dir="ltr">
<thead><tr><th v-if="settings.show_test_names" :style="previewHeading">Test</th><th :style="previewHeading">Result</th><th :style="previewHeading">Unit</th><th v-if="settings.show_status" :style="previewHeading">Flag</th></tr></thead>
<tbody><tr><td v-if="settings.show_test_names" :style="previewCell">Example test</td><td :style="previewCell">—</td><td :style="previewCell">—</td><td v-if="settings.show_status" :style="previewCell">—</td></tr></tbody>
</table></div></section>
<p class="text-xs text-slate-400 mt-3 text-center">{{ t("preview_desc") }}</p>
                              </div>
                         </div>
                    </div>
               </div>
          </div>
     </div>
</template>
