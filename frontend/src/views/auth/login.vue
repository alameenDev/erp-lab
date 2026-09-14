<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { storeToRefs } from "pinia";
import { useAuthStore } from "@/store/modules/auth";
import { useUsersStore } from "@/store/modules/users";
import { t } from "@/utils/helper";
import AuthCodeModal from "../users/components/authCodeModal.vue";

const authStore = useAuthStore();
const usersStore = useUsersStore();

const email = ref("");
const password = ref("");
const showPassword = ref(false);
const isLoading = ref(false);
const errorMessage = ref("");
const subscriptionExpired = ref(false);
const salesPhone = ref("");
const rememberMe = ref(false);
const isPageLoaded = ref(false);

const { codeDialog, record } = storeToRefs(usersStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const isRTL = computed(() => lang.value === "ar");

// Language picker (3 langs)
const languages = [
  { code: "ar", label: "العربية", short: "AR", flag: "🇮🇶" },
  { code: "en", label: "English", short: "EN", flag: "🇬🇧" },
  { code: "ku", label: "کوردی", short: "KU", flag: "🇮🇶" },
];
const langMenuOpen = ref(false);
const currentLang = computed(() => languages.find((l) => l.code === lang.value) || languages[0]);
const setLanguage = (code) => {
  if (code === lang.value) { langMenuOpen.value = false; return; }
  localStorage.setItem("locale", code);
  window.location.reload();
};
const onClickOutsideLang = (e) => {
  if (!e.target.closest("[data-lang-menu]")) langMenuOpen.value = false;
};

onMounted(() => {
  setTimeout(() => {
    isPageLoaded.value = true;
  }, 100);
  document.addEventListener("click", onClickOutsideLang);
});
onUnmounted(() => document.removeEventListener("click", onClickOutsideLang));

const submit = async (e) => {
  e.preventDefault();
  errorMessage.value = "";
  isLoading.value = true;
  try {
    await authStore.login({ email: email.value, password: password.value });
  } catch (error) {
    const resData = error.response?.data;
    if (resData?.subscription_expired) {
      subscriptionExpired.value = true;
      salesPhone.value = resData.sales_phone || "07838334835";
      errorMessage.value = resData.message;
    } else {
      subscriptionExpired.value = false;
      errorMessage.value = resData?.message || error.message || t("login_error") || "Login failed. Please check your credentials.";
    }
  } finally {
    isLoading.value = false;
  }
};

const forgetPassword = async () => {
  if (!email.value) {
    errorMessage.value = t("please_enter_email") || "Please enter your email";
    return;
  }
  errorMessage.value = "";
  record.value.email = email.value;
  isLoading.value = true;
  try {
    await usersStore.forget_password();
    codeDialog.value = true;
  } catch (error) {
    errorMessage.value = error.response?.data?.message || error.message || t("forget_password_error") || "Failed to send reset email.";
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div
    class="min-h-screen flex relative overflow-hidden"
    :dir="isRTL ? 'rtl' : 'ltr'"
    :class="{ 'opacity-0': !isPageLoaded, 'opacity-100': isPageLoaded }"
    style="transition: opacity 0.5s ease-out"
  >
    <!-- Background — soft slate -->
    <div class="absolute inset-0 bg-slate-50">
      <!-- Lab grid pattern -->
      <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(rgba(15,23,42,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,0.6) 1px, transparent 1px); background-size: 40px 40px;"></div>
      <!-- Brand glows -->
      <div class="absolute -top-32 -start-32 w-96 h-96 bg-primary-100/60 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-32 -end-32 w-96 h-96 bg-primary-200/40 rounded-full blur-3xl"></div>
    </div>

    <!-- Top bar: back + language -->
    <div class="absolute top-4 sm:top-6 inset-x-4 sm:inset-x-6 z-50 flex items-center justify-between">
      <router-link
        to="/"
        class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/80 backdrop-blur-xl border border-slate-200 rounded-xl text-slate-600 hover:bg-white hover:text-primary-600 hover:border-primary-300 shadow-sm hover:shadow-md transition-all"
      >
        <svg class="w-4 h-4 sm:w-5 sm:h-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span class="text-xs sm:text-sm font-semibold hidden sm:inline">{{ t("back_to_home") || "Back to Home" }}</span>
      </router-link>

      <!-- 3-language dropdown -->
      <div class="relative" data-lang-menu>
        <button
          @click.stop="langMenuOpen = !langMenuOpen"
          class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/80 backdrop-blur-xl border border-slate-200 rounded-xl text-slate-700 hover:bg-white hover:border-primary-300 shadow-sm hover:shadow-md transition-all"
          :title="currentLang.label"
        >
          <span class="text-base">{{ currentLang.flag }}</span>
          <span class="text-xs sm:text-sm font-bold">{{ currentLang.short }}</span>
          <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': langMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <div
            v-if="langMenuOpen"
            class="absolute end-0 mt-2 w-44 bg-white border border-slate-200 rounded-2xl shadow-2xl shadow-slate-300/50 overflow-hidden py-1.5"
          >
            <button
              v-for="l in languages"
              :key="l.code"
              @click.stop="setLanguage(l.code)"
              class="w-full px-4 py-2.5 flex items-center gap-3 text-sm transition-colors"
              :class="l.code === lang ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
            >
              <span class="text-base">{{ l.flag }}</span>
              <span class="flex-1 text-start font-semibold">{{ l.label }}</span>
              <svg v-if="l.code === lang" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 flex w-full">
      <!-- Left Panel — Brand Hero -->
      <div class="hidden lg:flex lg:w-1/2 xl:w-[55%] bg-gradient-to-br from-slate-950 via-slate-950 to-primary-950 relative overflow-hidden">
        <!-- Lab grid pattern -->
        <div class="absolute inset-0 opacity-[0.06]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 40px 40px;"></div>
        <!-- Brand glow blobs -->
        <div class="absolute -top-32 -start-32 w-[28rem] h-[28rem] bg-primary-500/15 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -end-32 w-[28rem] h-[28rem] bg-primary-600/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 start-1/2 -translate-x-1/2 -translate-y-1/2 w-[36rem] h-[36rem] bg-primary-700/5 rounded-full blur-3xl"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col justify-between w-full p-10 xl:p-14">
          <!-- Logo + Brand -->
          <div class="flex items-center gap-3">
            <div class="relative w-12 h-12 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-2xl shadow-primary-500/40 ring-1 ring-white/20 overflow-hidden">
              <img src="/lad_logo.png" alt="Lab Logo" class="w-9 h-9 object-contain relative z-10" />
              <div class="absolute inset-0 bg-gradient-to-tr from-transparent to-white/20"></div>
            </div>
            <div class="leading-tight">
              <h1 class="text-xl font-bold text-white tracking-tight">{{ t("appName") || "Digital Lab" }}</h1>
              <p class="text-[11px] text-slate-400 font-medium">{{ t("login_brand_subtitle") || "Laboratory Management" }}</p>
            </div>
          </div>

          <!-- Hero -->
          <div class="flex-1 flex flex-col justify-center py-8">
            <!-- Live badge -->
            <div class="inline-flex w-fit items-center gap-2.5 px-3 py-1.5 bg-white/[0.04] border border-white/10 rounded-full mb-7 backdrop-blur-sm">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-500 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-success-500"></span>
              </span>
              <span class="text-xs font-semibold text-slate-300 tracking-wide">{{ t("trusted_by_labs") || "Trusted by 500+ Laboratories" }}</span>
            </div>

            <h2 class="text-4xl xl:text-5xl font-bold text-white leading-[1.1] tracking-tight mb-5">
              {{ t("login_hero_title") || "Welcome back to your" }}<br>
              <span class="bg-gradient-to-r from-primary-300 via-primary-200 to-primary-400 bg-clip-text text-transparent">{{ t("login_hero_highlight") || "Lab Workspace" }}</span>
            </h2>
            <p class="text-base text-slate-400 max-w-md leading-relaxed mb-9">
              {{ t("login_hero_description") || "Streamline operations, enhance accuracy, and deliver results faster with the most comprehensive LIS." }}
            </p>

            <!-- Mini feature highlights -->
            <div class="grid grid-cols-2 gap-3 max-w-md">
              <div class="bg-white/[0.03] border border-white/10 rounded-xl p-3.5 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-1">
                  <div class="w-7 h-7 rounded-lg bg-primary-500/20 border border-primary-500/40 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-primary-400" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v2h1v14a4 4 0 0 0 4 4 4 4 0 0 0 4-4V4h1V2H7m4 14c-.6 0-1-.4-1-1s.4-1 1-1 1 .4 1 1-.4 1-1 1m1-5h-4V4h4v3z"/></svg>
                  </div>
                  <h3 class="text-white font-bold text-xs">{{ t("feature_tests") || "500+ Tests" }}</h3>
                </div>
                <p class="text-[10px] text-slate-500 ms-9">{{ t("feature_tests_desc") || "All test types" }}</p>
              </div>
              <div class="bg-white/[0.03] border border-white/10 rounded-xl p-3.5 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-1">
                  <div class="w-7 h-7 rounded-lg bg-primary-500/20 border border-primary-500/40 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-primary-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4a4 4 0 0 1 4 4 4 4 0 0 1-4 4 4 4 0 0 1-4-4 4 4 0 0 1 4-4m0 10c4.42 0 8 1.79 8 4v2H4v-2c0-2.21 3.58-4 8-4z"/></svg>
                  </div>
                  <h3 class="text-white font-bold text-xs">{{ t("feature_patients") || "Patient Records" }}</h3>
                </div>
                <p class="text-[10px] text-slate-500 ms-9">{{ t("feature_patients_desc") || "Full history" }}</p>
              </div>
              <div class="bg-white/[0.03] border border-white/10 rounded-xl p-3.5 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-1">
                  <div class="w-7 h-7 rounded-lg bg-primary-500/20 border border-primary-500/40 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-primary-400" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m0 16H5V5h14v14m-7-2h2V7h-4v2h2"/></svg>
                  </div>
                  <h3 class="text-white font-bold text-xs">{{ t("feature_reports") || "Smart Reports" }}</h3>
                </div>
                <p class="text-[10px] text-slate-500 ms-9">{{ t("feature_reports_desc") || "Auto results" }}</p>
              </div>
              <div class="bg-white/[0.03] border border-white/10 rounded-xl p-3.5 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-1">
                  <div class="w-7 h-7 rounded-lg bg-primary-500/20 border border-primary-500/40 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-primary-400" viewBox="0 0 24 24" fill="currentColor"><path d="M2 6h2v12H2V6m3 0h1v12H5V6m2 0h3v12H7V6m4 0h1v12h-1V6m3 0h2v12h-2V6m3 0h3v12h-3V6m4 0h1v12h-1V6"/></svg>
                  </div>
                  <h3 class="text-white font-bold text-xs">{{ t("feature_barcode") || "Barcode & QR" }}</h3>
                </div>
                <p class="text-[10px] text-slate-500 ms-9">{{ t("feature_barcode_desc") || "Sample tracking" }}</p>
              </div>
            </div>
          </div>

          <!-- Stats with dividers -->
          <div class="grid grid-cols-3 gap-4 pt-6 border-t border-white/10">
            <div>
              <div class="text-2xl xl:text-3xl font-bold text-white tracking-tight">10K<span class="text-primary-400">+</span></div>
              <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-0.5">{{ t("stat_tests") || "Tests/Month" }}</div>
            </div>
            <div class="border-s border-white/10 ps-4">
              <div class="text-2xl xl:text-3xl font-bold text-white tracking-tight">5K<span class="text-primary-400">+</span></div>
              <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-0.5">{{ t("stat_patients") || "Patients" }}</div>
            </div>
            <div class="border-s border-white/10 ps-4">
              <div class="text-2xl xl:text-3xl font-bold bg-gradient-to-r from-primary-300 to-primary-400 bg-clip-text text-transparent tracking-tight">99.9%</div>
              <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-0.5">{{ t("stat_uptime") || "Uptime" }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel - Login Form -->
      <div class="flex-1 lg:w-1/2 xl:w-[45%] flex items-center justify-center p-4 sm:p-8 lg:p-12 pt-20 sm:pt-24 lg:pt-12">
        <div class="w-full max-w-md">
          <!-- Login Card -->
          <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-2xl shadow-slate-300/40">
            <!-- Header -->
            <div class="text-center mb-7 sm:mb-8">
              <div class="relative w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center mb-4 shadow-2xl shadow-primary-500/40 ring-1 ring-white/20 overflow-hidden">
                <img src="/lad_logo.png" alt="Lab Logo" class="w-11 h-11 object-contain relative z-10" />
                <div class="absolute inset-0 bg-gradient-to-tr from-transparent to-white/20"></div>
              </div>
              <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-1.5 tracking-tight">
                {{ t("welcome_back") || "Welcome back" }}
              </h2>
              <p class="text-sm text-slate-500">
                {{ t("login_subtitle") || "Sign in to access your laboratory" }}
              </p>
            </div>

            <!-- Subscription Expired Message -->
            <Transition
              enter-active-class="transition duration-300 ease-out"
              enter-from-class="opacity-0 -translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-200 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-2"
            >
              <div v-if="subscriptionExpired" class="mb-6 p-4 sm:p-5 bg-warning-50 border border-warning-200 rounded-2xl">
                <div class="flex items-start gap-3">
                  <div class="w-10 h-10 bg-warning-100 border border-warning-200 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-warning-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-warning-800 mb-2">{{ errorMessage }}</p>
                    <a
                      :href="'tel:' + salesPhone"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-warning-100 hover:bg-warning-200 text-warning-800 rounded-lg text-xs font-bold transition-colors"
                      dir="ltr"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                      </svg>
                      {{ salesPhone }}
                    </a>
                  </div>
                  <button @click="subscriptionExpired = false; errorMessage = ''" class="text-warning-500 hover:text-warning-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </Transition>

            <!-- Error Message -->
            <Transition
              enter-active-class="transition duration-300 ease-out"
              enter-from-class="opacity-0 -translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-200 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-2"
            >
              <div v-if="errorMessage && !subscriptionExpired" class="mb-6 flex items-start gap-3 p-4 bg-danger-50 border border-danger-200 rounded-2xl">
                <div class="w-8 h-8 rounded-lg bg-danger-100 border border-danger-200 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <span class="text-sm font-medium text-danger-700 flex-1 mt-1">{{ errorMessage }}</span>
                <button @click="errorMessage = ''" class="text-danger-500 hover:text-danger-700 transition-colors mt-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </Transition>

            <!-- Form -->
            <form @submit="submit" class="space-y-4 sm:space-y-5">
              <!-- Email -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                  {{ t("email") || "Email Address" }}
                </label>
                <div class="relative group">
                  <div class="absolute inset-y-0 start-0 flex items-center ps-4 pointer-events-none text-slate-400 group-focus-within:text-primary-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <input
                    type="email"
                    v-model="email"
                    required
                    autocomplete="email"
                    class="w-full h-12 sm:h-13 ps-12 pe-4 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all"
                    :placeholder="t('enter_email') || 'name@laboratory.com'"
                  />
                </div>
              </div>

              <!-- Password -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                  {{ t("password") || "Password" }}
                </label>
                <div class="relative group">
                  <div class="absolute inset-y-0 start-0 flex items-center ps-4 pointer-events-none text-slate-400 group-focus-within:text-primary-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                  <input
                    :type="showPassword ? 'text' : 'password'"
                    v-model="password"
                    required
                    :dir="isRTL ? 'rtl' : 'ltr'"
                    autocomplete="current-password"
                    class="w-full h-12 sm:h-13 ps-12 pe-12 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all"
                    :placeholder="t('enter_password') || '••••••••'"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute inset-y-0 end-0 flex items-center pe-4 text-slate-400 hover:text-primary-500 transition-colors"
                  >
                    <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Remember Me & Forgot Password -->
              <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2.5 cursor-pointer group">
                  <div class="relative">
                    <input type="checkbox" v-model="rememberMe" class="peer sr-only" />
                    <div class="w-5 h-5 border-2 border-slate-300 rounded-md peer-checked:bg-primary-500 peer-checked:border-primary-500 group-hover:border-primary-400 transition-all"></div>
                    <svg class="absolute top-0.5 start-0.5 w-4 h-4 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <span class="text-xs sm:text-sm font-medium text-slate-600 group-hover:text-slate-900 transition-colors">
                    {{ t("remember_me") || "Remember me" }}
                  </span>
                </label>
                <button
                  type="button"
                  @click="forgetPassword"
                  class="text-xs sm:text-sm font-bold text-primary-600 hover:text-primary-700 transition-colors"
                >
                  {{ t("forget_password") || "Forgot password?" }}
                </button>
              </div>

              <!-- Submit Button -->
              <button
                type="submit"
                :disabled="isLoading"
                class="relative w-full h-13 sm:h-14 flex items-center justify-center gap-2 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl shadow-2xl shadow-primary-500/40 hover:shadow-primary-500/60 hover:-translate-y-0.5 active:scale-[0.98] focus:outline-none focus:ring-4 focus:ring-primary-500/30 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 transition-all overflow-hidden group text-sm sm:text-base"
              >
                <div class="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                <span class="relative">{{ isLoading ? (t("signing_in") || "Signing in...") : (t("login") || "Sign in") }}</span>
                <svg v-if="!isLoading" class="relative w-4 h-4 sm:w-5 sm:h-5 rtl:rotate-180 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
                <svg v-else class="relative animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
              <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
              <div class="relative flex justify-center"><span class="px-4 bg-white text-xs text-slate-400 font-semibold uppercase tracking-wider">{{ t('or') || 'or' }}</span></div>
            </div>

            <!-- Register Link -->
            <p class="text-center text-xs sm:text-sm text-slate-500">
              {{ t("dont_have_account") || "Don't have an account?" }}
              <router-link to="/register" class="text-primary-600 font-bold hover:text-primary-700 hover:underline">
                {{ t("register_lab") || "Register your lab" }}
              </router-link>
            </p>
          </div>

          <!-- Footer -->
          <div class="text-center mt-6 sm:mt-8 space-y-1.5">
            <p class="text-xs text-slate-400">
              © {{ new Date().getFullYear() }} {{ t("appName") || "Digital Lab" }}. {{ t("all_rights_reserved") || "All rights reserved." }}
            </p>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-success-50 border border-success-100 rounded-full">
              <svg class="w-3 h-3 text-success-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
              </svg>
              <span class="text-[10px] font-bold text-success-700 uppercase tracking-wider">{{ t("hipaa_compliant") || "HIPAA Compliant" }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Auth Code Modal -->
    <AuthCodeModal :isReset="true" />

    <!-- Loading Overlay -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-4">
          <div class="relative">
            <div class="w-16 h-16 border-4 border-primary-100 rounded-full"></div>
            <div class="absolute top-0 left-0 w-16 h-16 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
              <svg class="w-6 h-6 text-primary-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
              </svg>
            </div>
          </div>
          <span class="text-slate-600 font-medium">{{ t("loading") || "Loading..." }}</span>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
@keyframes float {
  0%, 100% { transform: translateY(0px) rotate(0deg); }
  50% { transform: translateY(-20px) rotate(5deg); }
}

@keyframes float-delayed {
  0%, 100% { transform: translateY(0px) rotate(0deg); }
  50% { transform: translateY(-15px) rotate(-5deg); }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}

.animate-float-delayed {
  animation: float-delayed 8s ease-in-out infinite;
  animation-delay: 1s;
}
</style>
