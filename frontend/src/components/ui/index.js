// UI Components
export { default as UiButton } from "./Button.vue";
export { default as UiInput } from "./Input.vue";
export { default as UiSelect } from "./Select.vue";
export { default as UiCheckbox } from "./Checkbox.vue";
export { default as UiTextarea } from "./Textarea.vue";
export { default as UiModal } from "./Modal.vue";
export { default as UiCard } from "./Card.vue";
export { default as UiDataTable } from "./DataTable.vue";
export { default as UiLoading } from "./Loading.vue";
export { default as UiToast } from "./Toast.vue";
export { useToast } from "@/composables/useToast";

// Plugin to register all UI components globally
export default {
  install(app) {
    app.component("UiButton", () => import("./Button.vue"));
    app.component("UiInput", () => import("./Input.vue"));
    app.component("UiSelect", () => import("./Select.vue"));
    app.component("UiCheckbox", () => import("./Checkbox.vue"));
    app.component("UiTextarea", () => import("./Textarea.vue"));
    app.component("UiModal", () => import("./Modal.vue"));
    app.component("UiCard", () => import("./Card.vue"));
    app.component("UiDataTable", () => import("./DataTable.vue"));
    app.component("UiLoading", () => import("./Loading.vue"));
    app.component("UiToast", () => import("./Toast.vue"));
  },
};
