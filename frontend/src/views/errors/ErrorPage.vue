<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import { t } from "@/utils/helper";

const props = defineProps({
  code: { type: [String, Number], required: true },
  title: { type: String, default: "" },
  message: { type: String, default: "" },
  icon: { type: String, default: "" },
  showHomeButton: { type: Boolean, default: true },
  showBackButton: { type: Boolean, default: true },
});

const router = useRouter();

const errorConfig = computed(() => {
  const configs = {
    400: {
      title: t("error400Title") || "Bad Request",
      message: t("error400Message") || "The request could not be understood by the server.",
      icon: "warning",
      color: "warning",
    },
    401: {
      title: t("error401Title") || "Unauthorized",
      message: t("error401Message") || "You need to login to access this page.",
      icon: "lock",
      color: "danger",
    },
    403: {
      title: t("error403Title") || "Access Denied",
      message: t("error403Message") || "You don't have permission to access this resource.",
      icon: "shield",
      color: "danger",
    },
    404: {
      title: t("error404Title") || "Page Not Found",
      message: t("error404Message") || "The page you're looking for doesn't exist or has been moved.",
      icon: "search",
      color: "primary",
    },
    500: {
      title: t("error500Title") || "Server Error",
      message: t("error500Message") || "Something went wrong on our end. Please try again later.",
      icon: "server",
      color: "danger",
    },
    503: {
      title: t("error503Title") || "Service Unavailable",
      message: t("error503Message") || "The service is temporarily unavailable. Please try again later.",
      icon: "clock",
      color: "warning",
    },
  };

  return configs[props.code] || configs[404];
});

const displayTitle = computed(() => props.title || errorConfig.value.title);
const displayMessage = computed(() => props.message || errorConfig.value.message);
const iconType = computed(() => props.icon || errorConfig.value.icon);
const colorClass = computed(() => errorConfig.value.color);

const goBack = () => {
  router.back();
};

const goHome = () => {
  router.push("/dashboard");
};

const goLogin = () => {
  router.push("/");
};
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="max-w-lg w-full text-center">
      <!-- Icon -->
      <div
        class="mx-auto w-24 h-24 rounded-full flex items-center justify-center mb-6"
        :class="`bg-${colorClass}-100`"
      >
        <!-- Warning Icon -->
        <svg v-if="iconType === 'warning'" class="w-12 h-12" :class="`text-${colorClass}-500`" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>

        <!-- Lock Icon -->
        <svg v-else-if="iconType === 'lock'" class="w-12 h-12" :class="`text-${colorClass}-500`" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>

        <!-- Shield Icon -->
        <svg v-else-if="iconType === 'shield'" class="w-12 h-12" :class="`text-${colorClass}-500`" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01" />
        </svg>

        <!-- Search Icon -->
        <svg v-else-if="iconType === 'search'" class="w-12 h-12" :class="`text-${colorClass}-500`" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>

        <!-- Server Icon -->
        <svg v-else-if="iconType === 'server'" class="w-12 h-12" :class="`text-${colorClass}-500`" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
        </svg>

        <!-- Clock Icon -->
        <svg v-else-if="iconType === 'clock'" class="w-12 h-12" :class="`text-${colorClass}-500`" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <!-- Error Code -->
      <h1 class="text-7xl font-bold text-slate-900 mb-2">{{ code }}</h1>

      <!-- Title -->
      <h2 class="text-2xl font-semibold text-slate-800 mb-3">{{ displayTitle }}</h2>

      <!-- Message -->
      <p class="text-slate-500 mb-8 max-w-md mx-auto">{{ displayMessage }}</p>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-center gap-3">
        <button
          v-if="showBackButton"
          @click="goBack"
          class="inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 px-4 py-2 text-sm border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 focus:ring-slate-300"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          {{ t("goBack") || "Go Back" }}
        </button>

        <button
          v-if="showHomeButton"
          @click="goHome"
          class="inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 px-4 py-2 text-sm bg-primary-500 text-white hover:bg-primary-600 focus:ring-primary-500"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          {{ t("goHome") || "Go Home" }}
        </button>

        <button
          v-if="code === 401"
          @click="goLogin"
          class="inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 px-4 py-2 text-sm bg-primary-500 text-white hover:bg-primary-600 focus:ring-primary-500"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
          </svg>
          {{ t("login") || "Login" }}
        </button>
      </div>

      <!-- Logo -->
      <div class="mt-12 flex items-center justify-center gap-2 text-slate-400">
        <div class="w-8 h-8 rounded-lg bg-primary-500 flex items-center justify-center overflow-hidden">
          <img src="/lad_logo.png" alt="Lab Logo" class="w-6 h-6 object-contain" />
        </div>
        <span class="text-sm font-medium">{{ t("appName") || "Medical Lab" }}</span>
      </div>
    </div>
  </div>
</template>
