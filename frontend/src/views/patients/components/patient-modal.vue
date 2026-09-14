<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usePatientsStore } from "@/store/modules/patients";
import { useContractsStore } from "@/store/modules/contract";
import { useNationalitiesStore } from "@/store/modules/nationalities";
import { useTitlesStore } from "@/store/modules/titles";
import { useinvoicesStore } from "@/store/modules/invoices";
import { LoaderStore } from "@/store/modules/loader";
import { t } from "@/utils/helper";
import { useToast } from "@/composables/useToast";

const patientsStore = usePatientsStore();
const contractsStore = useContractsStore();
const nationalitiesStore = useNationalitiesStore();
const titlesStore = useTitlesStore();
const invoicesStore = useinvoicesStore();
const loaderStore = LoaderStore();
const toast = useToast();

const { record, dialog, AgeUnits, genders, searchRecords, searchNameTotalCount, responseData, selectedFile } = storeToRefs(patientsStore);
const patientGenders = computed(() => genders.value.filter((g) => g.label !== "Both"));
const { nationalities } = storeToRefs(nationalitiesStore);
const { titles } = storeToRefs(titlesStore);
const { records: contractRecords } = storeToRefs(contractsStore);
const { patient } = storeToRefs(invoicesStore);
const { hideLoading } = storeToRefs(loaderStore);

// Computed contracts list for dropdowns
const contracts = computed(() =>
  contractRecords.value?.map(c => ({ label: c.name, value: c.id })) || []
);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const isNameShow = ref(false);
const isSubmitting = ref(false);
const errors = ref({
  name: "",
  age: "",
  age_unit: "",
  gender: "",
  title: "",
});

// Search patients by name
const searchitemByname = (v) => {
  if (v) {
    patientsStore.searchByname(v);
    isNameShow.value = true;
  } else {
    isNameShow.value = false;
  }
};

// Select existing patient from search — use directly without creating
const selectPatient = (p) => {
  isNameShow.value = false;
  // Set this patient as the selected patient in the invoice
  patient.value = p;
  responseData.value = p;
  dialog.value = false;
  toast.success(t("patient_selected") || "Patient selected");
};

// Handle gender change
const changeTitle = (value) => {
  if (value !== "" && value != null) {
    errors.value.gender = "";
  }
  if (value == 1) {
    record.value.title_id_fk = 1;
  } else {
    record.value.title_id_fk = 2;
  }
};

// Handle file change
const onFileChange = (event) => {
  selectedFile.value = event.target.files[0];
};

// Validate and create
const create = async () => {
  if (!validate()) return;

  isSubmitting.value = true;
  try {
    record.value.phone_number = formatPhone(record.value.phone);
    record.value.gender_id_fk = record.value.gender_type_id_fk;

    await patientsStore.AddPatient();
    toast.success(t("alertSuccess"));
    patient.value = responseData.value;
    close();
  } finally {
    isSubmitting.value = false;
  }
};

// Validate and update
const update = async () => {
  if (!validate()) return;

  isSubmitting.value = true;
  try {
    record.value.phone_number = formatPhone(record.value.phone);
    record.value.gender_id_fk = record.value.gender_type_id_fk;

    await patientsStore.UpdatePatient();
    patient.value = responseData.value;
    toast.success(t("alertSuccess"));
    close();
  } finally {
    isSubmitting.value = false;
  }
};

// Format phone: ensure +964 prefix and 10 digits after
const formatPhone = (val) => {
  if (!val) return "";
  let digits = val.replace(/[^0-9]/g, "");
  if (digits.startsWith("964")) digits = digits.slice(3);
  if (digits.startsWith("00964")) digits = digits.slice(5);
  if (digits.startsWith("0")) digits = digits.slice(1);
  return digits ? `+964${digits}` : "";
};

const onPhoneInput = (e) => {
  let digits = e.target.value.replace(/[^0-9]/g, "");
  if (digits.startsWith("964")) digits = digits.slice(3);
  if (digits.startsWith("0")) digits = digits.slice(1);
  record.value.phone = digits;
};

// Validation
const validate = () => {
  let isValid = true;
  errors.value = { name: "", age: "", age_unit: "", gender: "", title: "", phone: "" };

  if (!record.value.name?.trim()) {
    errors.value.name = t("errorMessage");
    isValid = false;
  }
  if (!record.value.age && record.value.age !== 0) {
    errors.value.age = t("errorMessage");
    isValid = false;
  }
  if (!record.value.age_unit_id_fk) {
    errors.value.age_unit = t("errorMessage");
    isValid = false;
  }
  if (!record.value.gender_type_id_fk) {
    errors.value.gender = t("errorMessage");
    isValid = false;
  }
  if (!record.value.title_id_fk) {
    errors.value.title = t("errorMessage");
    isValid = false;
  }
  if (record.value.phone && !/^[0-9]{10}$/.test(record.value.phone)) {
    errors.value.phone = t("invalid_phone") || "Phone must be 10 digits (e.g. 7703814484)";
    isValid = false;
  }

  return isValid;
};

// Close modal
const close = () => {
  dialog.value = false;
  errors.value = { name: "", age: "", age_unit: "", gender: "", title: "" };
  // Clear record
  Object.keys(record.value).forEach((key) => {
    if (typeof record.value[key] === "string") record.value[key] = "";
    else if (typeof record.value[key] === "number") record.value[key] = 0;
    else if (Array.isArray(record.value[key])) record.value[key] = [];
    else record.value[key] = null;
  });
};

// Handle submit
const handleSubmit = () => {
  if (record.value.id) {
    update();
  } else {
    create();
  }
};

// Watch for patient selection
watch(patient, (v) => {
  if (v) {
    isNameShow.value = false;
    Object.assign(record.value, v);
    // Strip +964 prefix for display in input
    let ph = record.value.phone || "";
    if (ph.startsWith("+964")) ph = ph.slice(4);
    else if (ph.startsWith("964")) ph = ph.slice(3);
    else if (ph.startsWith("00964")) ph = ph.slice(5);
    else if (ph.startsWith("0")) ph = ph.slice(1);
    record.value.phone = ph.replace(/[^0-9]/g, "");
  }
});

onMounted(() => {
  patientsStore.GetAgeUnits();
  patientsStore.GetGenders();
  titlesStore.GetTitles();
  contractsStore.GetRecords();
  nationalitiesStore.GetNationalities();
});
</script>

<template>
  <UiModal
    v-model="dialog"
    :closable="true"
    size="xl"
    :noPadding="true"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-500 to-teal-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-semibold text-slate-800">
            {{ record?.id ? t("update") + " " + t("patient") : t("addPatient") }}
          </h3>
          <p class="text-sm text-slate-500">{{ t("fill_patient_details") || "Fill in the patient information below" }}</p>
        </div>
      </div>
    </template>

    <form @submit.prevent="handleSubmit" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
      <div class="p-6 space-y-6">

        <!-- ==================== PERSONAL INFORMATION CARD ==================== -->
        <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <div>
                  <h4 class="font-semibold text-slate-800">{{ t("Personal_information") || "Personal Information" }}</h4>
                  <p class="text-sm text-slate-500">{{ t("basic_patient_details") || "Basic patient details" }}</p>
                </div>
              </div>
              <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                {{ t("required") || "Required" }}
              </span>
            </div>
          </div>

          <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <!-- Name -->
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  {{ t("name") }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </span>
                  <input
                    type="text"
                    v-model="record.name"
                    @input="searchitemByname(record.name)"
                    @blur="setTimeout(() => isNameShow = false, 200)"
                    :placeholder="t('enter_patient_name') || 'Enter patient name...'"
                    required
                    class="w-full ps-12 pe-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                    :class="{ 'border-red-500 bg-red-50': errors.name }"
                  />
                  <svg v-show="hideLoading" class="absolute top-3.5 w-5 h-5 text-primary-500 animate-spin" :class="lang === 'ar' ? 'left-3' : 'right-3'" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <!-- Search Results Dropdown -->
                  <div v-show="isNameShow && searchRecords?.length > 0" class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    <div
                      v-for="p in searchRecords"
                      :key="p.id"
                      @mousedown.prevent="selectPatient(p)"
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
                <p v-if="errors.name" class="mt-1.5 text-sm text-red-500 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ errors.name }}
                </p>
              </div>

              <!-- Title -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  {{ t("title") }} <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="record.title_id_fk"
                  required
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer"
                  :class="{ 'border-red-500 bg-red-50': errors.title }"
                >
                  <option :value="null">{{ t("select") }}...</option>
                  <option v-for="title in titles" :key="title.value" :value="title.value">
                    {{ title.label }}
                  </option>
                </select>
                <p v-if="errors.title" class="mt-1.5 text-sm text-red-500 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ errors.title }}
                </p>
              </div>

              <!-- Gender -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  {{ t("gender") }} <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="record.gender_type_id_fk"
                  @change="changeTitle(record.gender_type_id_fk)"
                  required
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer"
                  :class="{ 'border-red-500 bg-red-50': errors.gender }"
                >
                  <option :value="null">{{ t("select") }}...</option>
                  <option v-for="gender in patientGenders" :key="gender.value" :value="gender.value">
                    {{ gender.label }}
                  </option>
                </select>
                <p v-if="errors.gender" class="mt-1.5 text-sm text-red-500 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ errors.gender }}
                </p>
              </div>

              <!-- Age -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  {{ t("age") }} <span class="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  v-model="record.age"
                  min="0"
                  required
                  :placeholder="t('enter_age') || 'Enter age'"
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                  :class="{ 'border-red-500 bg-red-50': errors.age }"
                />
                <p v-if="errors.age" class="mt-1.5 text-sm text-red-500 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ errors.age }}
                </p>
              </div>

              <!-- Age Unit -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  {{ t("age_unit") }} <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="record.age_unit_id_fk"
                  required
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer"
                  :class="{ 'border-red-500 bg-red-50': errors.age_unit }"
                >
                  <option :value="null">{{ t("select") }}...</option>
                  <option v-for="unit in AgeUnits" :key="unit.value" :value="unit.value">
                    {{ unit.label }}
                  </option>
                </select>
                <p v-if="errors.age_unit" class="mt-1.5 text-sm text-red-500 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ errors.age_unit }}
                </p>
              </div>

              <!-- DOB -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("dob") }}</label>
                <input
                  type="date"
                  v-model="record.dob"
                  @click="$event.target.showPicker()"
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                />
              </div>

              <!-- Nationality -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("nationality") }}</label>
                <select
                  v-model="record.nationality_id_fk"
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer"
                >
                  <option :value="null">{{ t("select") }}...</option>
                  <option v-for="nat in nationalities" :key="nat.value" :value="nat.value">
                    {{ nat.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== CONTACT INFORMATION CARD ==================== -->
        <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-green-50 to-emerald-50">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/25">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-slate-800">{{ t("contact_information") || "Contact Information" }}</h4>
                <p class="text-sm text-slate-500">{{ t("phone_email_address") || "Phone, email and address" }}</p>
              </div>
            </div>
          </div>

          <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <!-- Phone -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("phone_number") }}</label>
                <div class="relative">
                  <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-600 font-semibold text-sm">+964</span>
                  <input
                    type="tel"
                    :value="record.phone"
                    @input="onPhoneInput"
                    placeholder="7XXXXXXXXX"
                    maxlength="10"
                    class="w-full ps-16 pe-4 py-3 bg-slate-50 border rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                    :class="errors.phone ? 'border-red-400' : 'border-slate-200'"
                    dir="ltr"
                  />
                </div>
                <p v-if="errors.phone" class="text-red-500 text-xs mt-1">{{ errors.phone }}</p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("email") }}</label>
                <div class="relative">
                  <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </span>
                  <input
                    type="email"
                    v-model="record.email"
                    :placeholder="t('enter_email') || 'Enter email address'"
                    class="w-full ps-12 pe-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                    dir="ltr"
                  />
                </div>
              </div>

              <!-- Address -->
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("address") }}</label>
                <div class="relative">
                  <span class="absolute top-3.5 start-4 text-slate-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </span>
                  <input
                    type="text"
                    v-model="record.address"
                    :placeholder="t('enter_address') || 'Enter full address'"
                    class="w-full ps-12 pe-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== IDENTIFICATION & CONTRACT CARD ==================== -->
        <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-slate-800">{{ t("identification_contract") || "Identification & Contract" }}</h4>
                <p class="text-sm text-slate-500">{{ t("id_passport_contract") || "ID, passport and contract details" }}</p>
              </div>
            </div>
          </div>

          <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <!-- National ID -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("national_id_no") }}</label>
                <input
                  type="text"
                  v-model="record.national_id_no"
                  :placeholder="t('enter_national_id') || 'Enter national ID'"
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                />
              </div>

              <!-- Passport -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("passport_no") }}</label>
                <input
                  type="text"
                  v-model="record.passport_no"
                  :placeholder="t('enter_passport') || 'Enter passport number'"
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all"
                />
              </div>

              <!-- Contract -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("contract") }}</label>
                <select
                  v-model="record.contract_id_fk"
                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all appearance-none cursor-pointer"
                >
                  <option :value="null">{{ t("select") }}...</option>
                  <option v-for="contract in contracts" :key="contract.value" :value="contract.value">
                    {{ contract.label }}
                  </option>
                </select>
              </div>

              <!-- File Upload -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">{{ t("addfile") || "Profile Photo" }}</label>
                <label class="flex items-center justify-center gap-3 w-full px-4 py-3 bg-slate-50 border-2 border-dashed border-slate-200 rounded-xl text-slate-500 hover:border-primary-400 hover:bg-primary-50 hover:text-primary-600 cursor-pointer transition-all group">
                  <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span class="text-sm font-medium">{{ selectedFile?.name || t('choose_file') || 'Choose file...' }}</span>
                  <input
                    type="file"
                    @change="onFileChange"
                    accept=".png,.jpg,.jpeg"
                    class="hidden"
                  />
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ==================== FOOTER ACTIONS ==================== -->
      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-gradient-to-r from-slate-50 to-white">
        <button
          type="button"
          @click="close"
          :disabled="isSubmitting"
          class="px-6 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ t("cancel") }}
        </button>
        <button
          type="submit"
          :disabled="isSubmitting"
          class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 rounded-xl transition-all shadow-lg shadow-primary-500/25 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          {{ record.id ? t("save") : t("add") }}
        </button>
      </div>
    </form>
  </UiModal>
</template>
