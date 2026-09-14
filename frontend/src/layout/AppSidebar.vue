<script setup>
import { ref, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useLayout } from "@/layout/composables/layout";
import { t, sanitizeSvg } from "@/utils/helper";
import { useAuthStore } from "@/store/modules/auth";
import { useLabSettingsStore } from "@/store/modules/labSettings";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const labSettingsStore = useLabSettingsStore();
const { rolePermissions } = storeToRefs(authStore);
const { settings: labSettings } = storeToRefs(labSettingsStore);
const { isSidebarActive, closeSidebar, isSidebarCollapsed, toggleSidebarCollapse } = useLayout();
const imageUrl = import.meta.env.VITE_ImageURL || "";

const sidebarStyle = computed(() => {
  const p = labSettings.value?.primary_color || "#0d9488";
  const s = labSettings.value?.secondary_color || "#14b8a6";
  // Darken the colors for sidebar background
  return {
    background: `linear-gradient(to bottom, ${darkenHex(p, 0.75)}, ${darkenHex(p, 0.82)}, ${darkenHex(s, 0.85)})`,
  };
});

function darkenHex(hex, amount) {
  const r = Math.round(parseInt(hex.slice(1, 3), 16) * (1 - amount));
  const g = Math.round(parseInt(hex.slice(3, 5), 16) * (1 - amount));
  const b = Math.round(parseInt(hex.slice(5, 7), 16) * (1 - amount));
  return `rgb(${r}, ${g}, ${b})`;
}

const lang = computed(() => localStorage.getItem("locale") || "ar");
const isRTL = computed(() => lang.value === "ar");
const userRoleId = computed(() => {
  try { return JSON.parse(localStorage.getItem("user"))?.role_id; } catch { return null; }
});

// Force re-render key when permissions change
const permissionsKey = ref(0);

// Track expanded menu items
const expandedItems = ref({});

// Menu configuration with groups - Medical themed icons
const menuGroups = [
  {
    groupLabel: "super_admin",
    items: [
      {
        label: "super_admin_panel",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>`,
        permission: "super admin dashboard",
        items: [
          { label: "system_overview", to: "/super-admin", permission: "super admin dashboard" },
          { label: "lab_management", to: "/super-admin/labs", permission: "super admin labs" },
          { label: "subscriptions", to: "/super-admin/subscriptions", permission: "subscriptions view" },
          { label: "system_activity_log", to: "/super-admin/activity-log", permission: "super admin activity log" },
          { label: "patients", to: "/super-admin/patients", permission: "super admin dashboard" },
          { label: "copy_lab_data", to: "/super-admin/copy-data", permission: "super admin dashboard" },
        ],
      },
    ],
  },
  {
    groupLabel: "main",
    items: [
      {
        label: "dashboard",
        to: "/dashboard",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>`,
        permission: "reports view",
      },
    ],
  },
  {
    groupLabel: "operations",
    items: [
      {
        label: "invoices",
        to: "/invoices",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>`,
        permission: "invoices view",
      },
      {
        label: "medical_reports",
        to: "/medical_reports",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>`,
        permission: "invoices view",
      },
      {
        label: "reports",
        to: "/reports",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>`,
        permission: "reports view",
      },
      {
        label: "accounting_reports",
        to: "/accounting-reports",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>`,
        permission: "accounting reports view",
      },
      {
        label: "inventory",
        to: "/inventory",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7L12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`,
        permission: "inventory view",
      },
      {
        label: "devices",
        to: "/devices",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
        </svg>`,
        permission: "devices view",
      },
    ],
  },
  {
    groupLabel: "management",
    items: [
      {
        label: "patients",
        to: "/patients",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>`,
        permission: "patients view",
      },
      {
        label: "priceList",
        to: "/priceList",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>`,
        permission: "price list view",
      },
      {
        label: "referrals",
        to: "/referrals",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
        </svg>`,
        permission: "referrals view",
      },
      {
        label: "contracts",
        to: "/contracts",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>`,
        permission: "contracts view",
      },
    ],
  },
  {
    groupLabel: "laboratory",
    items: [
      {
        label: "the_tests",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
        </svg>`,
        permission: "tests view",
        items: [
          { label: "tests", to: "/tests", permission: "tests view" },
          { label: "test-groups", to: "/test-groups", permission: "test groups view" },
          { label: "categories", to: "/categories", permission: "categories view" },
          { label: "samples", to: "/samples", permission: "samples view" },
          { label: "packages", to: "/packages", permission: "packages view" },
          { label: "cultures", to: "/cultures", permission: "cultures view" },
          { label: "test-questions", to: "/test-questions", permission: "test questions view" },
          { label: "Antibiotics", to: "/Antibiotics", permission: "antibiotics view" },
        ],
      },
    ],
  },
  {
    groupLabel: "administration",
    items: [
      {
        label: "users",
        to: "/users",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>`,
        permission: "users view",
      },
      {
        label: "roles",
        to: "/roles",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>`,
        permission: "roles view",
      },
      {
        label: "activityLogger",
        to: "/activityLogger",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>`,
        permission: "activities view",
      },
    ],
  },
  {
    groupLabel: "settings",
    items: [
      {
        label: "payments",
        to: "/paymentMethods",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>`,
        permission: "payments view",
      },
      {
        label: "lab_settings",
        to: "/lab-settings",
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>`,
      },
    ],
  },
];

// Check if user has permission for a menu item
const hasPermission = (permission) => {
  if (!permission) return true;
  return rolePermissions.value.includes(permission);
};

// Watch for permission changes and force re-render
watch(
  rolePermissions,
  () => {
    permissionsKey.value++;
  },
  { deep: true, immediate: true }
);

// Check if route is active
const isActive = (to) => {
  return route.path === to || route.path.startsWith(to + "/");
};

// Check if any child route is active
const hasActiveChild = (items) => {
  return items?.some((item) => isActive(item.to));
};

// Toggle submenu
const toggleSubmenu = (label) => {
  expandedItems.value[label] = !expandedItems.value[label];
};

// Check if submenu is expanded
const isExpanded = (label) => {
  return expandedItems.value[label];
};

// Filter visible groups (only show groups that have at least one visible item)
// Admin (role_id=1) only sees the super_admin group
const visibleGroups = computed(() => {
  // Include permissionsKey in dependency tracking to force re-computation
  const _key = permissionsKey.value;
  const isAdmin = userRoleId.value == 1;
  return menuGroups
    .filter((group) => isAdmin ? group.groupLabel === "super_admin" : group.groupLabel !== "super_admin")
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => hasPermission(item.permission)),
    }))
    .filter((group) => group.items.length > 0);
});

// Handle navigation
const navigateTo = (to) => {
  router.push(to);
  if (window.innerWidth < 1024) {
    closeSidebar();
  }
};

// Auto-expand active parent menu on mount
watch(
  () => route.path,
  () => {
    menuGroups.forEach((group) => {
      group.items.forEach((item) => {
        if (item.items && hasActiveChild(item.items)) {
          expandedItems.value[item.label] = true;
        }
      });
    });
  },
  { immediate: true }
);

// Sidebar position classes based on RTL/LTR
const sidebarClasses = computed(() => {
  const collapsed = isSidebarCollapsed?.value;
  const width = collapsed ? "w-20" : "w-64";
  const base = `fixed top-0 bottom-0 ${width} z-40 overflow-hidden transition-all duration-300 flex flex-col`;

  if (isRTL.value) {
    return [
      base,
      "right-0",
      isSidebarActive.value ? "translate-x-0" : "translate-x-full lg:translate-x-0"
    ];
  } else {
    return [
      base,
      "left-0",
      isSidebarActive.value ? "translate-x-0" : "-translate-x-full lg:translate-x-0"
    ];
  }
});
</script>

<template>
  <aside :class="sidebarClasses" :style="sidebarStyle">
    <!-- Logo Section -->
    <div
      class="h-16 flex items-center border-b border-white/10 transition-all duration-300"
      :class="isSidebarCollapsed ? 'justify-center px-2' : 'justify-between px-4'"
    >
      <router-link
        to="/"
        class="flex items-center overflow-hidden"
        :class="isSidebarCollapsed ? 'justify-center' : 'gap-3'"
      >
        <!-- Lab Logo -->
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg overflow-hidden" :style="{ background: `linear-gradient(135deg, ${labSettings.primary_color || '#0d9488'}, ${labSettings.secondary_color || '#14b8a6'})` }">
          <img v-if="labSettings.logo" :src="labSettings.logo?.startsWith('http') ? labSettings.logo : imageUrl + '/' + labSettings.logo" alt="Logo" class="w-8 h-8 object-contain" />
          <img v-else src="/lad_logo.png" alt="Lab Logo" class="w-8 h-8 object-contain" />
        </div>
        <div
          v-show="!isSidebarCollapsed"
          class="flex flex-col overflow-hidden transition-opacity duration-300"
          :class="isSidebarCollapsed ? 'opacity-0 w-0' : 'opacity-100'"
        >
          <span class="text-base font-bold text-white whitespace-nowrap">{{ labSettings.lab_display_name || t("appName") }}</span>
          <span class="text-xs text-slate-400 whitespace-nowrap">{{ labSettings.tagline || t("login_brand_subtitle") }}</span>
        </div>
      </router-link>

      <!-- Collapse Toggle (Desktop) -->
      <button
        v-if="toggleSidebarCollapse && !isSidebarCollapsed"
        @click="toggleSidebarCollapse"
        class="hidden lg:flex w-8 h-8 items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors flex-shrink-0"
      >
        <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-180': isRTL }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>
    </div>

    <!-- Expand button when collapsed -->
    <button
      v-if="toggleSidebarCollapse && isSidebarCollapsed"
      @click="toggleSidebarCollapse"
      class="hidden lg:flex w-full h-10 items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition-colors border-b border-white/10"
    >
      <svg class="w-5 h-5 transition-transform rotate-180" :class="{ 'rotate-0': isRTL }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
      </svg>
    </button>

    <!-- Navigation -->
    <nav :key="permissionsKey" class="flex-1 overflow-y-auto overflow-x-hidden py-4 scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">
      <!-- Menu Groups -->
      <template v-for="(group, groupIndex) in visibleGroups" :key="group.groupLabel">
        <!-- Group Label -->
        <div class="px-4 pt-4 pb-2" :class="{ 'pt-2': groupIndex === 0 }">
          <transition name="fade">
            <span v-if="!isSidebarCollapsed" class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">
              {{ t(group.groupLabel) }}
            </span>
          </transition>
          <div v-if="isSidebarCollapsed" class="h-px bg-white/10 mx-2"></div>
        </div>

        <!-- Group Items -->
        <ul class="space-y-1 px-3">
          <template v-for="item in group.items" :key="item.label">
            <!-- Item with submenu -->
            <li v-if="item.items">
              <button
                class="flex items-center w-full py-2.5 px-3 rounded-xl transition-all duration-200 group"
                :class="[
                  isExpanded(item.label) || hasActiveChild(item.items)
                    ? 'bg-white/15 text-white'
                    : 'text-slate-400 hover:bg-white/10 hover:text-slate-200',
                  isSidebarCollapsed ? 'justify-center' : 'justify-between'
                ]"
                @click="toggleSubmenu(item.label)"
                :title="isSidebarCollapsed ? t(item.label) : ''"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-9 h-9 flex items-center justify-center rounded-lg transition-all duration-200 flex-shrink-0"
                    :class="hasActiveChild(item.items) ? 'bg-primary-500/20 text-primary-400' : 'text-slate-400 group-hover:text-slate-200'"
                  >
                    <span class="w-5 h-5" v-html="sanitizeSvg(item.icon)"></span>
                  </div>
                  <transition name="fade">
                    <span v-if="!isSidebarCollapsed" class="font-medium text-sm whitespace-nowrap">{{ t(item.label) }}</span>
                  </transition>
                </div>
                <transition name="fade">
                  <svg
                    v-if="!isSidebarCollapsed"
                    class="w-4 h-4 text-slate-500 transition-transform duration-200 flex-shrink-0"
                    :class="{ 'rotate-180': isExpanded(item.label) }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </transition>
              </button>

              <!-- Submenu -->
              <Transition name="slide">
                <ul v-if="isExpanded(item.label) && !isSidebarCollapsed" class="mt-1 space-y-0.5 ps-4 ms-4 border-s border-white/10">
                  <template v-for="subItem in item.items" :key="subItem.label">
                    <li v-if="hasPermission(subItem.permission)">
                      <button
                        class="w-full flex items-center gap-3 py-2 px-3 text-sm rounded-lg transition-all duration-200 text-start"
                        :class="[
                          isActive(subItem.to)
                            ? 'bg-primary-500/10 text-primary-400 font-medium'
                            : 'text-slate-400 hover:bg-white/10 hover:text-slate-200',
                        ]"
                        @click="navigateTo(subItem.to)"
                      >
                        <span
                          class="w-1.5 h-1.5 rounded-full flex-shrink-0 transition-colors"
                          :class="isActive(subItem.to) ? 'bg-primary-400' : 'bg-slate-600'"
                        ></span>
                        <span class="whitespace-nowrap">{{ t(subItem.label) }}</span>
                      </button>
                    </li>
                  </template>
                </ul>
              </Transition>
            </li>

            <!-- Item without submenu -->
            <li v-else>
              <button
                class="flex items-center gap-3 w-full py-2.5 px-3 rounded-xl transition-all duration-200 group"
                :class="[
                  isActive(item.to)
                    ? 'bg-primary-500/10 text-primary-400'
                    : 'text-slate-400 hover:bg-white/10 hover:text-slate-200',
                  isSidebarCollapsed ? 'justify-center' : ''
                ]"
                @click="navigateTo(item.to)"
                :title="isSidebarCollapsed ? t(item.label) : ''"
              >
                <div
                  class="w-9 h-9 flex items-center justify-center rounded-lg transition-all duration-200 flex-shrink-0"
                  :class="isActive(item.to) ? 'bg-primary-500/20 text-primary-400' : 'text-slate-400 group-hover:text-slate-200'"
                >
                  <span class="w-5 h-5" v-html="sanitizeSvg(item.icon)"></span>
                </div>
                <transition name="fade">
                  <span v-if="!isSidebarCollapsed" class="font-medium text-sm whitespace-nowrap">{{ t(item.label) }}</span>
                </transition>
                <!-- Active indicator -->
                <transition name="fade">
                  <span
                    v-if="isActive(item.to) && !isSidebarCollapsed"
                    class="ms-auto w-1.5 h-1.5 rounded-full bg-primary-400 flex-shrink-0"
                  ></span>
                </transition>
              </button>
            </li>
          </template>
        </ul>
      </template>
    </nav>

    <!-- Bottom Section - Medical Badge -->
    <div class="p-4 border-t border-white/10">
      <transition name="fade">
        <div v-if="!isSidebarCollapsed" class="bg-gradient-to-r from-primary-500/10 to-primary-600/10 rounded-xl p-3 border border-primary-500/20">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
              <!-- Medical Cross -->
              <svg class="w-5 h-5 text-primary-400" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-primary-400">{{ t("hipaa_compliant") || "HIPAA Compliant" }}</p>
              <p class="text-[10px] text-slate-500 truncate">{{ t("secure_data") || "Secure Data Protection" }}</p>
            </div>
          </div>
        </div>
      </transition>
      <div v-if="isSidebarCollapsed" class="flex justify-center">
        <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center" :title="t('hipaa_compliant') || 'HIPAA Compliant'">
          <svg class="w-5 h-5 text-primary-400" viewBox="0 0 24 24" fill="currentColor">
            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
          </svg>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgb(51 65 85 / 0.5);
  border-radius: 2px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: rgb(51 65 85 / 0.7);
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Slide transition for submenus */
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
  overflow: hidden;
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-10px);
}
.slide-enter-to,
.slide-leave-from {
  opacity: 1;
  max-height: 500px;
  transform: translateY(0);
}
</style>
