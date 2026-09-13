<script setup>
     import { ref, onMounted, onBeforeUnmount } from "vue";
     import { useLayout } from "@/layout/composables/layout";

     const { onMenuToggle, layoutConfig, changeThemeSettings } = useLayout();

     const outsideClickListener = ref(null);
     const topbarMenuActive = ref(false);

     onMounted(() => {
          bindOutsideClickListener();
     });

     onBeforeUnmount(() => {
          unbindOutsideClickListener();
     });
     const currentLocale = localStorage.getItem("locale") ? localStorage.getItem("locale") : "ar";
     const languages = [
          {
               value: "en",
               label: "English",
          },

          {
               value: "ar",
               label: "Arabic",
          },
     ];
     let lang = currentLocale;

     const onChangeTheme = (theme, mode) => {
          const elementId = "theme-css";
          const linkElement = document.getElementById(elementId);
          const cloneLinkElement = linkElement.cloneNode(true);
          const newThemeUrl = linkElement.getAttribute("href").replace(layoutConfig.theme.value, theme);
          cloneLinkElement.setAttribute("id", elementId + "-clone");
          cloneLinkElement.setAttribute("href", newThemeUrl);
          cloneLinkElement.addEventListener("load", () => {
               linkElement.remove();
               cloneLinkElement.setAttribute("id", elementId);
               changeThemeSettings(theme, mode === "dark");
          });
          linkElement.parentNode.insertBefore(cloneLinkElement, linkElement.nextSibling);
     };

     const bindOutsideClickListener = () => {
          if (!outsideClickListener.value) {
               outsideClickListener.value = (event) => {
                    if (isOutsideClicked(event)) {
                         topbarMenuActive.value = false;
                    }
               };
               document.addEventListener("click", outsideClickListener.value);
          }
     };
     const unbindOutsideClickListener = () => {
          if (outsideClickListener.value) {
               document.removeEventListener("click", outsideClickListener);
               outsideClickListener.value = null;
          }
     };
     const isOutsideClicked = (event) => {
          if (!topbarMenuActive.value) return;

          const sidebarEl = document.querySelector(".layout-topbar-menu");
          const topbarEl = document.querySelector(".layout-topbar-menu-button");

          return !(
               sidebarEl.isSameNode(event.target) ||
               sidebarEl.contains(event.target) ||
               topbarEl.isSameNode(event.target) ||
               topbarEl.contains(event.target)
          );
     };
     const changeLanguage = (lang) => {
          localStorage.setItem("locale", lang);
          window.location.reload();
     };
</script>

<template>
     <div class="layout-topbar flex justify-content-between">
          <div class="flex justify-content-between">
               <router-link to="/" class="layout-topbar-logo">
                    <img
                         :src="User?.image ? ImageURL + User?.image : '/src/assets/user.png'"
                         style="border-radius: 50%" />
                    <!-- <img src="/src/assets/img.png" /> -->

                    <span class="text-xl" style="margin: 0 8px">{{ User?.name }}</span>
                    <!-- <i class="pi pi-user"></i> -->
               </router-link>

               <button class="p-link layout-menu-button layout-topbar-button" @click="onMenuToggle()">
                    <i class="pi pi-bars"></i>
               </button>
          </div>
          <div class="gap-2 flex justify-content-center align-items-center">
               <!-- <i class="pi pi-language"></i> -->
               <Dropdown
                    required
                    class="w-full lang_list"
                    v-model="lang"
                    :options="languages"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="currentLocale"
                    @change="changeLanguage(lang)" />

               <div class="flex hidden md:block">
                    <Button
                         v-if="layoutConfig.theme.value === 'lara-light-indigo'"
                         icon="pi pi-moon"
                         @click="onChangeTheme('lara-dark-indigo', 'dark')"
                         text></Button>
                    <Button v-else icon="pi pi-sun" @click="onChangeTheme('lara-light-indigo', 'light')" text></Button>
               </div>
          </div>
     </div>
</template>

<style lang="scss" scoped>
     .lang_list {
          text-transform: capitalize;
     }
</style>
