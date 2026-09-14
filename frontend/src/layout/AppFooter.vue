<script setup>
import { computed } from "vue";
import { t } from "@/utils/helper";

const currentYear = new Date().getFullYear();
const lang = computed(() => localStorage.getItem("locale") || "ar");
const isRTL = computed(() => lang.value === "ar");
</script>

<template>
  <footer class="border-t border-slate-200/80 dark-mode:border-slate-700/80 bg-white/50 dark-mode:bg-slate-900/50 backdrop-blur-sm">
    <div class="px-4 lg:px-6 py-4">
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <!-- Left side - Branding -->
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
            <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
          </div>
          <span class="text-sm text-slate-600 dark-mode:text-slate-400">
            © {{ currentYear }} <span class="font-semibold text-slate-700 dark-mode:text-slate-300">{{ t("appName") || "Digital Lab" }}</span>. {{ t("all_rights_reserved") }}
          </span>
        </div>

        <!-- Right side - Links & Badge -->
        <div class="flex items-center gap-4">
          <!-- Quick Links -->
          <div class="hidden sm:flex items-center gap-3 text-sm">
            <a href="#" class="text-slate-500 hover:text-primary-600 transition-colors">{{ t("privacy") || "Privacy" }}</a>
            <span class="text-slate-300">|</span>
            <a href="#" class="text-slate-500 hover:text-primary-600 transition-colors">{{ t("terms") || "Terms" }}</a>
            <span class="text-slate-300">|</span>
            <a href="#" class="text-slate-500 hover:text-primary-600 transition-colors">{{ t("support") || "Support" }}</a>
          </div>

          <!-- HIPAA Badge -->
          <div class="flex items-center gap-1.5 px-2.5 py-1 bg-primary-50 dark-mode:bg-primary-950/30 rounded-lg border border-primary-100 dark-mode:border-primary-800">
            <svg class="w-4 h-4 text-primary-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span class="text-xs font-medium text-primary-700">{{ t("hipaa_compliant") || "HIPAA Compliant" }}</span>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>
