<script setup>
import { computed, onMounted, onUnmounted } from "vue";
import AppTopbar from "./AppTopbar.vue";
import AppSidebar from "./AppSidebar.vue";
import AppFooter from "./AppFooter.vue";
import { useLayout } from "@/layout/composables/layout";

const { isSidebarActive, closeSidebar, isSidebarCollapsed } = useLayout();

const lang = computed(() => localStorage.getItem("locale") || "ar");
const isRTL = computed(() => lang.value === "ar");

// Close sidebar when clicking outside on mobile
const handleOutsideClick = (event) => {
  const sidebar = document.querySelector("aside");
  const menuButton = document.querySelector(".menu-toggle");

  if (
    sidebar &&
    !sidebar.contains(event.target) &&
    menuButton &&
    !menuButton.contains(event.target) &&
    isSidebarActive.value
  ) {
    closeSidebar();
  }
};

onMounted(() => {
  document.addEventListener("click", handleOutsideClick);
});

onUnmounted(() => {
  document.removeEventListener("click", handleOutsideClick);
});

// Dynamic content margin based on sidebar state
const contentMargin = computed(() => isSidebarCollapsed?.value ? "5rem" : "16rem");
</script>

<template>
  <div
    class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-50 to-slate-100 dark-mode:from-slate-950 dark-mode:via-slate-950 dark-mode:to-slate-900 flex flex-col transition-colors duration-300"
    :dir="lang === 'ar' ? 'rtl' : 'ltr'"
  >
    <!-- Background Pattern -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
      <!-- Subtle grid pattern -->
      <div class="absolute inset-0 bg-[linear-gradient(to_right,#f1f5f910_1px,transparent_1px),linear-gradient(to_bottom,#f1f5f910_1px,transparent_1px)] bg-[size:4rem_4rem]"></div>
      <!-- Gradient orbs -->
      <div class="absolute -top-40 -end-40 w-80 h-80 bg-primary-200/20 rounded-full blur-3xl"></div>
      <div class="absolute top-1/2 -start-20 w-60 h-60 bg-primary-300/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Sidebar -->
    <AppSidebar />

    <!-- Sidebar Overlay (mobile) -->
    <Transition name="fade">
      <div
        v-if="isSidebarActive"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-30 lg:hidden"
        @click="closeSidebar"
      ></div>
    </Transition>

    <!-- Topbar -->
    <AppTopbar />

    <!-- Main Content -->
    <main
      class="flex-1 pt-16 transition-all duration-300 relative z-10"
      :style="{
        [isRTL ? 'marginRight' : 'marginLeft']: contentMargin,
        width: `calc(100% - ${contentMargin})`
      }"
      :class="['lg:block']"
    >
      <div class="p-4 lg:p-6 min-h-[calc(100vh-8rem)]">
        <!-- Page content with smooth transitions -->
        <router-view v-slot="{ Component }">
          <Transition name="page" mode="out-in">
            <component :is="Component" />
          </Transition>
        </router-view>
      </div>

      <!-- Footer -->
      <AppFooter />
    </main>
  </div>
</template>

<style>
/* Fade transition for overlay */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Page transition */
.page-enter-active,
.page-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.page-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.page-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Mobile responsiveness */
@media (max-width: 1023px) {
  main {
    margin-left: 0 !important;
    margin-right: 0 !important;
    width: 100% !important;
  }
}
</style>
