import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { applyBranding } from "@/utils/branding";

export const useLabSettingsStore = defineStore("labSettings", {
     state: () => ({
          settings: {
               logo: null,
               primary_color: "#0d9488",
               secondary_color: "#14b8a6",
               font_family: "Tajawal",
               lab_display_name: "",
               tagline: "",
          },
          isLoading: false,
     }),
     actions: {
          async GetSettings() {
               try {
                    this.isLoading = true;
                    const { data } = await $http.get("/lab-settings");
                    // Normalize null jsonb configs to defaults so templates that
                    // bind `settings.X.field` directly (no ?. guard) don't crash
                    // on labs that haven't saved that section yet.
                    this.settings = {
                         ...data,
                         print_margins: data?.print_margins || { top: 20, bottom: 20, left: 15, right: 15 },
                         barcode_config: data?.barcode_config || { label_width: 3, label_height: 1.5, name_size: 9, info_size: 7, number_size: 6, barcode_height: 40, sample_size: 8, tests_size: 7 },
                         patient_header_config: data?.patient_header_config || { name_size: 20, info_size: 13, barcode_height: 35, qr_size: 90, line_height: 1.7 },
                    };
               } catch (error) {
                    console.error("Error fetching lab settings:", error);
               } finally {
                    this.isLoading = false;
               }
          },
          async UpdateSettings(formData) {
               try {
                    const { data } = await $http.post("/lab-settings", formData, {
                         headers: { "Content-Type": "multipart/form-data" },
                    });
                    this.settings = data.setting;
                    applyBranding(this.settings);
                    return data;
               } catch (error) {
                    console.error("Error updating lab settings:", error);
                    throw error;
               }
          },
          async ResetSettings(section = "all") {
               try {
                    const { data } = await $http.post("/lab-settings/reset", { section });
                    this.settings = data.setting;

                    // Clear branding cache
                    const darkStyle = document.getElementById("lab-branding-dark");
                    if (darkStyle) darkStyle.remove();
                    localStorage.removeItem("labBranding");

                    // Reset CSS variables to defaults
                    const root = document.documentElement;
                    root.style.removeProperty("--header-from");
                    root.style.removeProperty("--header-via");
                    root.style.removeProperty("--header-to");
                    root.style.removeProperty("--lab-secondary");
                    root.style.removeProperty("--p-primary-color");
                    root.style.removeProperty("--p-primary-contrast-color");
                    root.style.removeProperty("--p-highlight-background");
                    root.style.removeProperty("--p-highlight-color");
                    root.style.removeProperty("--p-focus-ring-color");
                    for (const shade of [50,100,200,300,400,500,600,700,800,900,950]) {
                         root.style.removeProperty(`--color-primary-${shade}`);
                    }
                    root.style.fontFamily = "";
                    document.body.style.fontFamily = "";

                    // Re-apply defaults
                    applyBranding(this.settings);
                    return data;
               } catch (error) {
                    console.error("Error resetting settings:", error);
                    throw error;
               }
          },
          async RemoveLogo() {
               try {
                    await $http.delete("/lab-settings/logo");
                    this.settings.logo = null;
               } catch (error) {
                    console.error("Error removing logo:", error);
                    throw error;
               }
          },
          async RemoveBackground() {
               try {
                    await $http.delete("/lab-settings/background");
                    this.settings.report_background = null;
               } catch (error) {
                    console.error("Error removing background:", error);
                    throw error;
               }
          },
     },
});
