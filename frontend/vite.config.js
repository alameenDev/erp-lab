import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  build: {
    minify: true,
    chunkSizeWarningLimit: 2000,
    rollupOptions: {
      output: {
        manualChunks(id) {
          // Vue core libraries
          if (id.includes("node_modules/vue") ||
              id.includes("node_modules/@vue") ||
              id.includes("node_modules/vue-router") ||
              id.includes("node_modules/pinia")) {
            return "vue-vendor";
          }

          // Excel library
          if (id.includes("node_modules/xlsx")) {
            return "xlsx";
          }

          // PDF generation libraries
          if (id.includes("node_modules/html2pdf") ||
              id.includes("node_modules/jspdf") ||
              id.includes("node_modules/html2canvas")) {
            return "pdf-libs";
          }

          // Chart.js
          if (id.includes("node_modules/chart.js")) {
            return "chart";
          }

          // QR Code libraries
          if (id.includes("node_modules/qrcode") ||
              id.includes("node_modules/@chenfengyuan/vue-qrcode")) {
            return "qrcode";
          }

          // Common utilities
          if (id.includes("node_modules/axios") ||
              id.includes("node_modules/date-fns") ||
              id.includes("node_modules/sweetalert2")) {
            return "utils";
          }
        },
      },
    },
  },
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  server: {
    proxy: {
      "/api": {
        target: "http://127.0.0.1:8000",
        changeOrigin: true,
        secure: false,
      },
    },
  },
});
