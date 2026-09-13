<script setup>
     import { ref, onBeforeMount, watch , computed} from "vue";
     import { useRoute } from "vue-router";
     import { useLayout } from "@/layout/composables/layout";
     
     import { t } from "@/utils/helper";
     import { useAuthStore } from '@/store/modules/auth'
     const authStore = useAuthStore();
     const route = useRoute();

     const { layoutConfig, layoutState, setActiveMenuItem, onMenuToggle } = useLayout();

     const props = defineProps({
          item: {
               type: Object,
               default: () => ({}),
          },
          index: {
               type: Number,
               default: 0,
          },
          root: {
               type: Boolean,
               default: true,
          },
          parentItemKey: {
               type: String,
               default: null,
          },
     });

     const isActiveMenu = ref(false);
     const itemKey = ref(null);

     onBeforeMount(() => {
          itemKey.value = props.parentItemKey ? props.parentItemKey + "-" + props.index : String(props.index);

          const activeItem = layoutState.activeMenuItem;

          isActiveMenu.value =
               activeItem === itemKey.value || activeItem ? activeItem.startsWith(itemKey.value + "-") : false;
     });

     watch(
          () => layoutConfig.activeMenuItem.value,
          (newVal) => {
               isActiveMenu.value = newVal === itemKey.value || newVal.startsWith(itemKey.value + "-");
          }
     );
     const itemClick = (event, item) => {
          if (item.disabled) {
               event.preventDefault();
               return;
          }

          const { overlayMenuActive, staticMenuMobileActive } = layoutState;

          if ((item.to || item.url) && (staticMenuMobileActive.value || overlayMenuActive.value)) {
               onMenuToggle();
          }

          if (item.command) {
               item.command({ originalEvent: event, item: item });
          }

          const foundItemKey = item.items ? (isActiveMenu.value ? props.parentItemKey : itemKey) : itemKey.value;

          setActiveMenuItem(foundItemKey);
     };

     const checkActiveRoute = (item) => {
          return route.path === item.to;
     };

     // ✅ الدالة التي تتحقق من صلاحية العنصر
const isItemVisible = computed(() => {
  if (!props.item.permission) return true; // إذا ما محدد صلاحية، نعرضه دائمًا
  return authStore.havePermission(props.item.permission);
});
</script>

<template>
     <li :class="{ 'layout-root-menuitem': root, 'active-menuitem': isActiveMenu }">
          
          
          <div v-if="root && item.visible !== false" class="layout-menuitem-root-text">{{ t(item.label) }}</div>
          <!-- v-if="(!item.to || item.items) && item.visible !== false"
          v-show="item.roles?.includes(Role)" -->
          <a
           
               v-if="(!item.to || item.items) && item.visible !== false && isItemVisible"
               :href="item.url"
               @click="itemClick($event, item, index)"
               :class="item.class"
               :target="item.target"
               tabindex="0">
               <span class="layout-menuitem-text text-lg">
                    {{ t(item.label) }}
               </span>
               <img
                    src="/src/assets/arrow-down-sign-to-navigate.png"
                    class="pi pi-fw pi-angle-down layout-submenu-toggler"
                    v-if="item.items"
                    style="text-transform: lowercase; width: 6%" />
               <!-- <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items" style="margin: 0"></i> -->
          </a>
          <!-- <router-link
               v-if="item.to && !item.items && item.visible !== false"
               v-show="item.roles?.includes(Role)"
               @click="itemClick($event, item, index)"
               :class="[item.class, { 'active-route': checkActiveRoute(item) }]"
               tabindex="0"
               :to="item.to"> -->
               <router-link
                         v-if="item.to && !item.items && item.visible !== false && isItemVisible"
                         @click="itemClick($event, item, index)"
                         :class="[item.class, { 'active-route': checkActiveRoute(item) }]"
                         tabindex="0"
                         :to="item.to"
                         >
               <span class="layout-menuitem-text text-lg">{{ t(item.label) }}</span>
               <img
                    src="/src/assets/arrow-down-sign-to-navigate.png"
                    class="pi pi-fw pi-angle-down layout-submenu-toggler"
                    v-if="item.items"
                    style="text-transform: lowercase; width: 6%" />

               <!-- <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items" style="margin: 0"></i> -->
          </router-link>
          <Transition v-if="item.items && item.visible !== false && isItemVisible" name="layout-submenu">
               <ul v-show="root ? true : isActiveMenu" class="layout-submenu">
                    <app-menu-item
                         v-for="(child, i) in item.items"
                         :key="child"
                         :index="i"
                         :item="child"
                         :parentItemKey="itemKey"
                         :root="false"></app-menu-item>
               </ul>
          </Transition>
     </li>
</template>

<style lang="scss" scoped>
     .layout-menu ul a {
          justify-content: space-between;
          transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
     }
     
     .layout-menu ul ul li a {
          margin-right: 1rem;
          position: relative;
     }

     /* Smooth animation for menu items */
     li {
          animation: fadeInUp 0.3s ease-out;
     }

     @keyframes fadeInUp {
          from {
               opacity: 0;
               transform: translateY(10px);
          }
          to {
               opacity: 1;
               transform: translateY(0);
          }
     }
</style>
