<script setup>
import { ref, computed, onMounted } from "vue";
import { t, alertSuccess, alertError } from "@/utils/helper";
import { $http } from "@/plugins/axios";

const lang = computed(() => localStorage.getItem("locale") || "ar");

const User = computed(() => {
  try {
    return JSON.parse(localStorage.getItem("user"));
  } catch {
    return null;
  }
});

const ImageURL = computed(() => import.meta.env.VITE_ImageURL || "");

const userInitials = computed(() => {
  if (!User.value?.name) return "U";
  return User.value.name
    .split(" ")
    .map((n) => n[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
});

const userRole = computed(() => {
  const roles = {
    1: { name: "Admin", nameAr: "مدير النظام", color: "red" },
    2: { name: "Lab", nameAr: "المختبر", color: "blue" },
    3: { name: "Patient", nameAr: "مريض", color: "green" },
    4: { name: "Branch Lab", nameAr: "فرع المختبر", color: "purple" },
    5: { name: "Doctor", nameAr: "طبيب", color: "teal" },
    6: { name: "Sample Collector", nameAr: "جامع العينات", color: "orange" },
    7: { name: "User", nameAr: "مستخدم", color: "slate" }
  };
  return roles[User.value?.role_id] || { name: "User", nameAr: "مستخدم", color: "slate" };
});

// Password change form
const showPasswordModal = ref(false);
const passwordForm = ref({
  current_password: "",
  new_password: "",
  confirm_password: ""
});
const passwordLoading = ref(false);
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const changePassword = async () => {
  if (passwordForm.value.new_password !== passwordForm.value.confirm_password) {
    alertError(t("passwords_not_match"));
    return;
  }

  if (passwordForm.value.new_password.length < 6) {
    alertError(t("password_min_length"));
    return;
  }

  passwordLoading.value = true;
  try {
    await $http.post("/user/change-password", {
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password,
      new_password_confirmation: passwordForm.value.confirm_password
    });
    alertSuccess(t("password_changed_success"));
    closePasswordModal();
  } catch (error) {
    alertError(error.response?.data?.message || t("password_change_failed"));
  } finally {
    passwordLoading.value = false;
  }
};

const closePasswordModal = () => {
  showPasswordModal.value = false;
  passwordForm.value = {
    current_password: "",
    new_password: "",
    confirm_password: ""
  };
  showCurrentPassword.value = false;
  showNewPassword.value = false;
  showConfirmPassword.value = false;
};

// Profile stats
const stats = computed(() => {
  return {
    role: lang.value === "ar" ? userRole.value.nameAr : userRole.value.name,
    email: User.value?.email || "---",
    phone: User.value?.phone || "---",
    lab: User.value?.lab?.name || "---"
  };
});
</script>

<template>
  <div class="min-h-screen bg-slate-50" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-slate-800 via-slate-900 to-slate-800 relative overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
          <defs>
            <pattern id="profile-grid" width="10" height="10" patternUnits="userSpaceOnUse">
              <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="0.5"/>
            </pattern>
          </defs>
          <rect width="100" height="100" fill="url(#profile-grid)" />
        </svg>
      </div>
      <div class="absolute -top-24 -end-24 w-96 h-96 bg-primary-500/20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-24 -start-24 w-96 h-96 bg-teal-500/20 rounded-full blur-3xl"></div>

      <div class="relative px-6 py-12">
        <div class="max-w-4xl mx-auto">
          <div class="flex flex-col items-center text-center">
            <!-- Avatar -->
            <div class="relative mb-6">
              <div class="w-32 h-32 rounded-2xl overflow-hidden bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center ring-4 ring-white/20 shadow-2xl shadow-primary-500/30">
                <img
                  v-if="User?.image"
                  :src="ImageURL + User.image"
                  :alt="User?.name"
                  class="w-full h-full object-cover"
                />
                <span v-else class="text-4xl font-bold text-white">{{ userInitials }}</span>
              </div>
              <div
                class="absolute -bottom-2 -end-2 w-10 h-10 rounded-xl flex items-center justify-center shadow-lg"
                :class="{
                  'bg-red-500': userRole.color === 'red',
                  'bg-blue-500': userRole.color === 'blue',
                  'bg-green-500': userRole.color === 'green',
                  'bg-purple-500': userRole.color === 'purple',
                  'bg-teal-500': userRole.color === 'teal',
                  'bg-orange-500': userRole.color === 'orange',
                  'bg-slate-500': userRole.color === 'slate'
                }"
              >
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
            </div>

            <!-- Name & Role -->
            <h1 class="text-3xl font-bold text-white mb-2">{{ User?.name }}</h1>
            <div class="flex items-center gap-2 mb-4">
              <span
                class="px-4 py-1.5 text-sm font-medium rounded-full"
                :class="{
                  'bg-red-500/20 text-red-200': userRole.color === 'red',
                  'bg-blue-500/20 text-blue-200': userRole.color === 'blue',
                  'bg-green-500/20 text-green-200': userRole.color === 'green',
                  'bg-purple-500/20 text-purple-200': userRole.color === 'purple',
                  'bg-teal-500/20 text-teal-200': userRole.color === 'teal',
                  'bg-orange-500/20 text-orange-200': userRole.color === 'orange',
                  'bg-slate-500/20 text-slate-200': userRole.color === 'slate'
                }"
              >
                {{ lang === 'ar' ? userRole.nameAr : userRole.name }}
              </span>
            </div>
            <p class="text-slate-400">{{ User?.email }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-4xl mx-auto px-6 -mt-8 relative z-10 pb-12">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
              <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <p class="text-sm text-slate-500">{{ t("role") }}</p>
              <p class="text-lg font-bold text-slate-800">{{ stats.role }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/25">
              <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-sm text-slate-500">{{ t("email") }}</p>
              <p class="text-sm font-bold text-slate-800 truncate">{{ stats.email }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-violet-600 flex items-center justify-center shadow-lg shadow-violet-500/25">
              <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </div>
            <div>
              <p class="text-sm text-slate-500">{{ t("phone_number") }}</p>
              <p class="text-lg font-bold text-slate-800">{{ stats.phone }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/25">
              <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div>
              <p class="text-sm text-slate-500">{{ t("lab/bruanch") }}</p>
              <p class="text-lg font-bold text-slate-800">{{ stats.lab }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Profile Details Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/25">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <h3 class="font-semibold text-slate-800">{{ t("profile_information") }}</h3>
                <p class="text-sm text-slate-500">{{ t("profile_information_desc") }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Full Name -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">{{ t("name") }}</label>
              <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                {{ User?.name || "---" }}
              </div>
            </div>

            <!-- Email -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">{{ t("email") }}</label>
              <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                {{ User?.email || "---" }}
              </div>
            </div>

            <!-- Phone -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">{{ t("phone_number") }}</label>
              <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                {{ User?.phone || "---" }}
              </div>
            </div>

            <!-- Address -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">{{ t("address") }}</label>
              <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                {{ User?.address || "---" }}
              </div>
            </div>

            <!-- Role -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">{{ t("role") }}</label>
              <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                {{ lang === 'ar' ? userRole.nameAr : userRole.name }}
              </div>
            </div>

            <!-- Lab -->
            <div class="space-y-2 md:col-span-2">
              <label class="text-sm font-medium text-slate-700">{{ t("lab/bruanch") }}</label>
              <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700">
                {{ User?.lab?.name || "---" }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Security Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-red-50 to-orange-50">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-orange-600 flex items-center justify-center shadow-lg shadow-red-500/25">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <div>
                <h3 class="font-semibold text-slate-800">{{ t("security") }}</h3>
                <p class="text-sm text-slate-500">{{ t("security_desc") }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="p-6">
          <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center">
                <svg class="w-6 h-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
              </div>
              <div>
                <p class="font-medium text-slate-800">{{ t("password") }}</p>
                <p class="text-sm text-slate-500">{{ t("change_password_desc") }}</p>
              </div>
            </div>
            <button
              @click="showPasswordModal = true"
              class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              {{ t("change_password") }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Change Password Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showPasswordModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="lang === 'ar' ? 'rtl' : 'ltr'">
          <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closePasswordModal"></div>
          <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-6 py-5">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-lg shadow-primary-500/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-xl font-bold text-white">{{ t("change_password") }}</h2>
                    <p class="text-slate-400 text-sm">{{ t("change_password_modal_desc") }}</p>
                  </div>
                </div>
                <button @click="closePasswordModal" class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-all">
                  <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Content -->
            <form @submit.prevent="changePassword">
              <div class="p-6 space-y-4">
                <!-- Current Password -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    {{ t("current_password") }} <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input
                      v-model="passwordForm.current_password"
                      :type="showCurrentPassword ? 'text' : 'password'"
                      required
                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all pe-12"
                      :placeholder="t('enter_current_password')"
                    />
                    <button
                      type="button"
                      @click="showCurrentPassword = !showCurrentPassword"
                      class="absolute end-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                    >
                      <svg v-if="showCurrentPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                      <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- New Password -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    {{ t("new_password") }} <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input
                      v-model="passwordForm.new_password"
                      :type="showNewPassword ? 'text' : 'password'"
                      required
                      minlength="6"
                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all pe-12"
                      :placeholder="t('enter_new_password')"
                    />
                    <button
                      type="button"
                      @click="showNewPassword = !showNewPassword"
                      class="absolute end-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                    >
                      <svg v-if="showNewPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                      <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Confirm Password -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    {{ t("confirm_password") }} <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input
                      v-model="passwordForm.confirm_password"
                      :type="showConfirmPassword ? 'text' : 'password'"
                      required
                      minlength="6"
                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 focus:bg-white outline-none transition-all pe-12"
                      :placeholder="t('confirm_new_password')"
                    />
                    <button
                      type="button"
                      @click="showConfirmPassword = !showConfirmPassword"
                      class="absolute end-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                    >
                      <svg v-if="showConfirmPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                      <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Password Requirements -->
                <div class="bg-blue-50 rounded-xl border border-blue-100 p-4">
                  <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                      <p class="text-sm font-medium text-blue-800">{{ t("password_requirements") }}</p>
                      <p class="text-sm text-blue-600 mt-1">{{ t("password_requirements_desc") }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Footer -->
              <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
                <button
                  type="button"
                  @click="closePasswordModal"
                  class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl transition-all flex items-center gap-2"
                >
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  {{ t("cancel") }}
                </button>
                <button
                  type="submit"
                  :disabled="passwordLoading"
                  class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-medium rounded-xl transition-all shadow-lg shadow-primary-500/25 flex items-center gap-2 disabled:opacity-50"
                >
                  <svg v-if="passwordLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                  {{ t("save") }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
  transform: scale(0.95);
}
</style>
