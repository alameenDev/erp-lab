<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useLayout } from "@/layout/composables/layout";
import { useRouter } from "vue-router";
import { t } from "@/utils/helper";
import { $http } from "@/plugins/axios";

const router = useRouter();
const { toggleSidebar, isSidebarCollapsed, toggleDarkMode, isDarkMode } = useLayout();

const currentLocale = ref(localStorage.getItem("locale") || "ar");
const showProfileMenu = ref(false);
const showLangMenu = ref(false);

// Search functionality
const searchQuery = ref("");
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchResults = ref(false);
let searchTimeout = null;

const performSearch = async () => {
  if (!searchQuery.value || searchQuery.value.trim() === "") {
    searchResults.value = [];
    showSearchResults.value = false;
    return;
  }

  isSearching.value = true;
  try {
    const { data } = await $http.post("/invoices/search-name", { name: searchQuery.value.toLowerCase() });
    searchResults.value = data || [];
    showSearchResults.value = searchResults.value.length > 0;
  } catch (error) {
    searchResults.value = [];
    showSearchResults.value = false;
  } finally {
    isSearching.value = false;
  }
};

// Debounced search - works with any input
watch(searchQuery, (newVal) => {
  if (searchTimeout) clearTimeout(searchTimeout);
  if (!newVal || newVal.trim() === "") {
    searchResults.value = [];
    showSearchResults.value = false;
    return;
  }
  searchTimeout = setTimeout(performSearch, 200);
});

const selectPatient = (patient) => {
  showSearchResults.value = false;
  searchQuery.value = "";
  searchResults.value = [];
  router.push({ path: "/invoices", query: { patient_id: patient.id } });
};

const closeSearch = () => {
  showSearchResults.value = false;
};

const isRTL = computed(() => currentLocale.value === "ar");

const languages = [
  { value: "en", label: "English", flag: "🇺🇸" },
  { value: "ar", label: "العربية", flag: "🇮🇶" },
];

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
    1: "Admin",
    2: "Lab",
    3: "Patient",
    4: "Branch Lab",
    5: "Doctor",
    6: "Sample Collector",
    7: "User"
  };
  return roles[User.value?.role_id] || "User";
});

const changeLanguage = (lang) => {
  localStorage.setItem("locale", lang);
  showLangMenu.value = false;
  window.location.reload();
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  localStorage.removeItem("rolePermissions");
  router.push("/");
};

const closeMenus = () => {
  showProfileMenu.value = false;
  showLangMenu.value = false;
};

// Close dropdowns when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.dropdown-container')) {
    closeMenus();
  }
};

// Current time display
const currentTime = ref("");
const updateTime = () => {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString(currentLocale.value === "ar" ? "ar-u-nu-latn" : "en-US", {
    hour: "2-digit",
    minute: "2-digit"
  });
};

// Sidebar width for content offset
const sidebarWidth = computed(() => isSidebarCollapsed?.value ? "5rem" : "16rem");

// Track if desktop view
const isDesktop = ref(typeof window !== 'undefined' ? window.innerWidth >= 1024 : true);

const updateIsDesktop = () => {
  isDesktop.value = window.innerWidth >= 1024;
};

let timer = null;

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('resize', updateIsDesktop);
  updateTime();
  timer = setInterval(updateTime, 60000);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('resize', updateIsDesktop);
  if (timer) clearInterval(timer);
});

// Header positioning style
const headerStyle = computed(() => {
  if (!isDesktop.value) {
    return { width: '100%', left: '0', right: '0' };
  }
  return {
    width: `calc(100% - ${sidebarWidth.value})`,
    [isRTL.value ? 'right' : 'left']: sidebarWidth.value
  };
});
</script>

<template>
  <header
    class="fixed top-0 h-16 bg-white/80 dark-mode:bg-slate-900/90 backdrop-blur-xl z-30 border-b border-slate-200/80 dark-mode:border-slate-700/80 transition-all duration-300"
    :style="headerStyle"
  >
    <div class="h-full px-4 lg:px-6 flex items-center justify-between">
      <!-- Start side -->
      <div class="flex items-center gap-4">
        <!-- Menu Toggle Button (Mobile) -->
        <button
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-all duration-200 lg:hidden"
          @click="toggleSidebar"
        >
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <!-- Search Bar (Desktop) -->
        <div class="hidden md:block relative">
          <div class="flex items-center gap-3 bg-primary-50 rounded-xl px-4 py-2.5 min-w-[280px] group focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/30 border border-primary-200 focus-within:border-primary-400 transition-all duration-200">
            <svg v-if="!isSearching" class="w-5 h-5 text-primary-400 group-focus-within:text-primary-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <svg v-else class="w-5 h-5 text-primary-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <input
              type="text"
              v-model="searchQuery"
              :placeholder="t('search_patients') || 'Search patients...'"
              class="bg-transparent border-none outline-none text-sm text-slate-700 placeholder-primary-400 w-full"
              @focus="showSearchResults = searchResults.length > 0"
              @blur="setTimeout(() => closeSearch(), 200)"
            />
            <button v-if="searchQuery" @click="searchQuery = ''; searchResults = []; showSearchResults = false" class="text-primary-400 hover:text-primary-600 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Search Results Dropdown -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
          >
            <div
              v-if="showSearchResults && searchResults.length > 0"
              class="absolute z-50 top-full mt-2 w-full bg-white rounded-xl border border-slate-200 shadow-xl shadow-slate-200/50 overflow-hidden"
            >
              <div class="px-4 py-2 bg-slate-50 border-b border-slate-100">
                <span class="text-xs font-medium text-slate-500">{{ searchResults.length }} {{ t('results_found') || 'results found' }}</span>
              </div>
              <div class="max-h-80 overflow-y-auto">
                <div
                  v-for="patient in searchResults"
                  :key="patient.id"
                  class="px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0 transition-colors"
                  @mousedown.prevent="selectPatient(patient)"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                      <span class="text-sm font-semibold text-primary-600">{{ patient.name?.charAt(0)?.toUpperCase() || 'P' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-slate-800 truncate">{{ patient.name }}</p>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span v-if="patient.code" class="text-xs text-slate-500">
                          #{{ patient.code }}
                        </span>
                        <span v-if="patient.phone" class="text-xs text-slate-400">
                          {{ patient.phone }}
                        </span>
                      </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>

      <!-- End side -->
      <div class="flex items-center gap-2">
        <!-- Dark Mode Toggle -->
        <button
          @click="toggleDarkMode"
          class="p-2 rounded-xl transition-all duration-200"
          :class="isDarkMode
            ? 'text-amber-400 hover:bg-slate-700 bg-slate-800'
            : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'"
          :title="isDarkMode ? 'Light Mode' : 'Dark Mode'"
        >
          <!-- Sun icon (shown in dark mode) -->
          <svg v-if="isDarkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
          </svg>
          <!-- Moon icon (shown in light mode) -->
          <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
          </svg>
        </button>

        <!-- Current Time Badge -->
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg"
          :class="isDarkMode ? 'bg-slate-800' : 'bg-slate-100/80'"
        >
          <svg class="w-4 h-4" :class="isDarkMode ? 'text-slate-400' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-sm font-medium" :class="isDarkMode ? 'text-slate-300' : 'text-slate-600'">{{ currentTime }}</span>
        </div>

        <!-- Language Selector -->
        <div class="relative dropdown-container">
          <button
            class="flex items-center gap-2 h-10 px-3 rounded-xl text-slate-600 hover:bg-slate-100 transition-all duration-200"
            @click.stop="showLangMenu = !showLangMenu; showProfileMenu = false"
          >
            <span class="text-lg">{{ currentLocale === "ar" ? "🇮🇶" : "🇺🇸" }}</span>
            <span class="hidden sm:inline text-sm font-medium">
              {{ currentLocale === "ar" ? "العربية" : "EN" }}
            </span>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': showLangMenu }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Language Dropdown -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
          >
            <div
              v-if="showLangMenu"
              class="absolute z-50 bg-white rounded-xl border border-slate-200 py-1.5 shadow-xl shadow-slate-200/50 mt-2 w-40 overflow-hidden"
              :class="isRTL ? 'left-0' : 'right-0'"
            >
              <button
                v-for="lang in languages"
                :key="lang.value"
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors w-full text-start"
                :class="{ 'bg-primary-50 text-primary-700': currentLocale === lang.value }"
                @click="changeLanguage(lang.value)"
              >
                <span class="text-lg">{{ lang.flag }}</span>
                <span class="font-medium">{{ lang.label }}</span>
                <svg v-if="currentLocale === lang.value" class="w-4 h-4 ms-auto text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </button>
            </div>
          </Transition>
        </div>

        <!-- Divider -->
        <div class="w-px h-8 bg-slate-200 mx-1 hidden sm:block"></div>

        <!-- Profile Menu -->
        <div class="relative dropdown-container">
          <button
            class="flex items-center gap-3 h-10 ps-1.5 pe-3 rounded-xl hover:bg-slate-100 transition-all duration-200"
            @click.stop="showProfileMenu = !showProfileMenu; showLangMenu = false"
          >
            <div class="w-8 h-8 rounded-xl overflow-hidden bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center ring-2 ring-primary-100">
              <img
                v-if="User?.image"
                :src="ImageURL + User.image"
                :alt="User?.name"
                class="w-full h-full object-cover"
              />
              <span v-else class="text-xs font-bold text-white">{{ userInitials }}</span>
            </div>
            <div class="hidden lg:flex flex-col items-start">
              <span class="text-sm font-semibold text-slate-700 max-w-[120px] truncate">
                {{ User?.name }}
              </span>
              <span class="text-xs text-slate-400">{{ userRole }}</span>
            </div>
            <svg class="hidden lg:block w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': showProfileMenu }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Profile Dropdown -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2"
          >
            <div
              v-if="showProfileMenu"
              class="absolute z-50 bg-white rounded-2xl border border-slate-200 shadow-xl shadow-slate-200/50 mt-2 w-64 overflow-hidden"
              :class="isRTL ? 'left-0' : 'right-0'"
            >
              <!-- User info header -->
              <div class="p-4 bg-gradient-to-r from-primary-500 to-primary-600">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-xl overflow-hidden bg-white/20 flex items-center justify-center ring-2 ring-white/30">
                    <img
                      v-if="User?.image"
                      :src="ImageURL + User.image"
                      :alt="User?.name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else class="text-lg font-bold text-white">{{ userInitials }}</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ User?.name }}</p>
                    <p class="text-xs text-primary-100 truncate mt-0.5">{{ User?.email }}</p>
                    <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 text-[10px] font-medium bg-white/20 text-white rounded-full">
                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                      {{ userRole }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="py-2">
                <router-link
                  to="/app/profile"
                  class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                  @click="closeMenus"
                >
                  <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                  <div>
                    <span class="font-medium">{{ t("profile") || "Profile" }}</span>
                    <p class="text-xs text-slate-400">{{ t("manage_account") || "Manage your account" }}</p>
                  </div>
                </router-link>
              </div>

              <div class="p-2 border-t border-slate-100 bg-slate-50/50">
                <button
                  class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-xl transition-all duration-200 w-full text-red-600 hover:bg-red-50"
                  @click="logout"
                >
                  <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                  </div>
                  <span class="font-medium">{{ t("logOut") || "Logout" }}</span>
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </header>
</template>
