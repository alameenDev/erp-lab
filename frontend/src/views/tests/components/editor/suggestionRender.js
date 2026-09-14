// Connects @tiptap/suggestion's imperative API to our Vue SuggestionPopup component.
// Returns a factory that each suggestion extension can pass as `render`.

import { createApp, h, ref } from "vue";
import SuggestionPopup from "./SuggestionPopup.vue";

export const buildRender = () => () => {
     let container = null;
     let mountedApp = null;
     let popupInstance = null;
     let itemsRef = ref([]);
     let onSelectRef = ref(() => {});

     const updatePosition = (clientRect) => {
          if (!container || !clientRect) return;
          const rect = clientRect();
          if (!rect) {
               container.style.display = "none";
               return;
          }
          const pad = 4;
          const vw = window.innerWidth;
          const vh = window.innerHeight;
          // Position below the caret; flip above if there's no room
          const popupH = container.offsetHeight || 220;
          let top = rect.bottom + pad;
          if (top + popupH > vh - 8) top = Math.max(8, rect.top - popupH - pad);
          let left = rect.left;
          const popupW = container.offsetWidth || 280;
          if (left + popupW > vw - 8) left = Math.max(8, vw - popupW - 8);
          container.style.display = "";
          container.style.top = `${top}px`;
          container.style.left = `${left}px`;
     };

     return {
          onStart: (props) => {
               container = document.createElement("div");
               container.style.cssText =
                    "position: fixed; z-index: 9999; pointer-events: auto;";
               document.body.appendChild(container);

               itemsRef.value = props.items || [];
               onSelectRef.value = (item) => props.command(item);

               mountedApp = createApp({
                    setup() {
                         return () =>
                              h(SuggestionPopup, {
                                   ref: (r) => (popupInstance = r),
                                   items: itemsRef.value,
                                   onSelect: (item) => onSelectRef.value(item),
                              });
                    },
               });
               mountedApp.mount(container);
               updatePosition(props.clientRect);
          },
          onUpdate: (props) => {
               itemsRef.value = props.items || [];
               onSelectRef.value = (item) => props.command(item);
               popupInstance?.reset?.();
               requestAnimationFrame(() => updatePosition(props.clientRect));
          },
          onKeyDown: (props) => {
               if (props.event.key === "Escape") {
                    return true;
               }
               if (props.event.key === "ArrowUp") {
                    popupInstance?.up?.();
                    return true;
               }
               if (props.event.key === "ArrowDown") {
                    popupInstance?.down?.();
                    return true;
               }
               if (props.event.key === "Enter" || props.event.key === "Tab") {
                    popupInstance?.enter?.();
                    return true;
               }
               return false;
          },
          onExit: () => {
               try {
                    mountedApp?.unmount();
               } catch {}
               if (container?.parentNode) container.parentNode.removeChild(container);
               container = null;
               mountedApp = null;
               popupInstance = null;
          },
     };
};
