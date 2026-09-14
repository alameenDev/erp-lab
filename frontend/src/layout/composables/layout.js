import { reactive, computed, ref } from "vue";

const layoutConfig = reactive({
  menuMode: "overlay",
  sidebarCollapsed: false,
  activeMenuItem: null,
});

const layoutState = reactive({
  sidebarOpen: false,
  profileMenuOpen: false,
});

// Separate ref for sidebar collapsed state (persisted)
const sidebarCollapsed = ref(localStorage.getItem("sidebarCollapsed") === "true");

// Dark mode state (persisted)
const darkMode = ref(localStorage.getItem("darkMode") === "true");

// Apply dark mode class on init
if (darkMode.value) {
  document.documentElement.classList.add("dark-mode");
}

export function useLayout() {
  const toggleSidebar = () => {
    layoutState.sidebarOpen = !layoutState.sidebarOpen;
  };

  const closeSidebar = () => {
    layoutState.sidebarOpen = false;
  };

  const toggleSidebarCollapse = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem("sidebarCollapsed", sidebarCollapsed.value);
  };

  const toggleDarkMode = () => {
    darkMode.value = !darkMode.value;
    localStorage.setItem("darkMode", darkMode.value);
    document.documentElement.classList.toggle("dark-mode", darkMode.value);
  };

  const toggleProfileMenu = () => {
    layoutState.profileMenuOpen = !layoutState.profileMenuOpen;
  };

  const closeProfileMenu = () => {
    layoutState.profileMenuOpen = false;
  };

  const setActiveMenuItem = (key) => {
    layoutConfig.activeMenuItem = key;
  };

  const isSidebarActive = computed(() => layoutState.sidebarOpen);
  const isSidebarCollapsed = computed(() => sidebarCollapsed.value);
  const isDarkMode = computed(() => darkMode.value);

  return {
    layoutConfig,
    layoutState,
    toggleSidebar,
    closeSidebar,
    toggleSidebarCollapse,
    toggleDarkMode,
    toggleProfileMenu,
    closeProfileMenu,
    setActiveMenuItem,
    isSidebarActive,
    isSidebarCollapsed,
    isDarkMode,
  };
}
