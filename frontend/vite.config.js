import { fileURLToPath, URL } from "node:url";
import { defineConfig, transformWithEsbuild } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => ({
  base: mode === "demo" ? "/demo/" : "/",
  define: mode === "demo" ? {
    "import.meta.env.VITE_DEMO_MODE": "true",
    "import.meta.env.VITE_BASE_URL": JSON.stringify("/demo/api"),
    "import.meta.env.VITE_APP_URL": "window.__demoAppUrl",
    "import.meta.env.VITE_IMAGE_URL": "window.__demoImageUrl",
    "import.meta.env.VITE_ImageURL": "window.__demoImageUrl",
    "import.meta.env.VITE_PATIENT_MOBILE_ENABLED": JSON.stringify("false"),
  } : { "import.meta.env.VITE_DEMO_MODE": "false" },
  plugins: [vue(), tailwindcss(), ...(mode === 'demo' ? [{ name: 'learning-demo-storage', enforce: 'post', async transform(code, id) {
    if (!id.includes('/src/') || !/\.(?:js|vue)(?:$|\?vue&type=script)/.test(id)) return;
    return transformWithEsbuild(code, id, { loader:'js', target:'es2022', define: {
      localStorage:'window.__demoStorage', 'window.localStorage':'window.__demoStorage', 'globalThis.localStorage':'window.__demoStorage',
      sessionStorage:'window.__demoSessionStorage', 'window.sessionStorage':'window.__demoSessionStorage', 'globalThis.sessionStorage':'window.__demoSessionStorage',
    } });
  } }, { name: 'learning-demo-html', transformIndexHtml: { order:'pre', handler(html) {
    return html.replace('/src/main.js', '/src/demo/bootstrap.js')
      .replace(/<link[^>]+rel="(?:canonical|manifest)"[^>]*>/g, '')
      .replace(/<title>[\s\S]*?<\/title>/, '<title>تجربة تعليمية | المختبر الرقمي</title>')
      .replace('</head>', '<meta name="robots" content="noindex,nofollow"></head>');
  } } }] : [])],
  build: {
    outDir: mode === "demo" ? "dist-demo" : "dist",
    target: mode === "demo" ? "es2022" : undefined,
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
    host: "0.0.0.0",
    allowedHosts: ["terminal.local"],
    proxy: {
      ...(mode === "demo" ? { "/demo/api": { target:"http://127.0.0.1:8000", changeOrigin:true, rewrite:path => path.replace(/^\/demo/, "") } } : {}),
      "/api": {
        target: "http://127.0.0.1:8000",
        changeOrigin: true,
        secure: false,
      },
    },
  },
}));
