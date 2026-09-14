import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import VueQrcode from "@chenfengyuan/vue-qrcode";

// Tailwind CSS
import "@/assets/main.css";

// PrimeVue
import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import "primeicons/primeicons.css";
// Removed primeflex - using Tailwind CSS instead

// PrimeVue Components (only importing used components)
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Dropdown from "primevue/dropdown";
import Checkbox from "primevue/checkbox";
import Listbox from "primevue/listbox";
import InlineMessage from "primevue/inlinemessage";
import Message from "primevue/message";
import TabView from "primevue/tabview";
import TabPanel from "primevue/tabpanel";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Calendar from "primevue/calendar";
import Textarea from "primevue/textarea";
import RadioButton from "primevue/radiobutton";
import MultiSelect from "primevue/multiselect";
import Toast from "primevue/toast";
import ToastService from "primevue/toastservice";
import ConfirmDialog from "primevue/confirmdialog";
import ConfirmationService from "primevue/confirmationservice";
import Tooltip from "primevue/tooltip";
import Badge from "primevue/badge";
import Tag from "primevue/tag";
import ProgressBar from "primevue/progressbar";
import Card from "primevue/card";
import Avatar from "primevue/avatar";
import Menu from "primevue/menu";
import Sidebar from "primevue/sidebar";
import AutoComplete from "primevue/autocomplete";

// UI Components
import {
  UiButton,
  UiInput,
  UiSelect,
  UiCheckbox,
  UiTextarea,
  UiModal,
  UiCard,
  UiDataTable,
  UiLoading,
  UiToast,
} from "@/components/ui";

import { registerPlugins } from "@/plugins";
import Mixins from "@/utils/mixins.js";
import { vueErrorHandler, vueWarnHandler, setupGlobalErrorHandlers } from "@/utils/logger.js";

const app = createApp(App);

// Setup global error handlers (window.onerror, unhandledrejection)
setupGlobalErrorHandlers();

// Vue error handlers
app.config.errorHandler = vueErrorHandler;
app.config.warnHandler = vueWarnHandler;

// Use PrimeVue with Aura theme
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      prefix: "p",
      darkModeSelector: ".dark-mode",
      cssLayer: false,
    },
  },
  ripple: true,
});
app.use(ToastService);
app.use(ConfirmationService);

// Register PrimeVue directives
app.directive("tooltip", Tooltip);

// Register PrimeVue Components globally (only used components)
app.component("Dialog", Dialog);
app.component("Button", Button);
app.component("InputText", InputText);
app.component("InputNumber", InputNumber);
app.component("Dropdown", Dropdown);
app.component("Checkbox", Checkbox);
app.component("Listbox", Listbox);
app.component("InlineMessage", InlineMessage);
app.component("Message", Message);
app.component("TabView", TabView);
app.component("TabPanel", TabPanel);
app.component("DataTable", DataTable);
app.component("Column", Column);
app.component("Calendar", Calendar);
app.component("Textarea", Textarea);
app.component("RadioButton", RadioButton);
app.component("MultiSelect", MultiSelect);
app.component("Toast", Toast);
app.component("ConfirmDialog", ConfirmDialog);
app.component("Badge", Badge);
app.component("Tag", Tag);
app.component("ProgressBar", ProgressBar);
app.component("Card", Card);
app.component("Avatar", Avatar);
app.component("Menu", Menu);
app.component("Sidebar", Sidebar);
app.component("AutoComplete", AutoComplete);

app.use(router);

// Register QR Code component
app.component("VueQrcode", VueQrcode);

// Register UI Components globally
app.component("UiButton", UiButton);
app.component("UiInput", UiInput);
app.component("UiSelect", UiSelect);
app.component("UiCheckbox", UiCheckbox);
app.component("UiTextarea", UiTextarea);
app.component("UiModal", UiModal);
app.component("UiCard", UiCard);
app.component("UiDataTable", UiDataTable);
app.component("UiLoading", UiLoading);
app.component("UiToast", UiToast);

registerPlugins(app);

app.mixin(Mixins).mount("#app");
