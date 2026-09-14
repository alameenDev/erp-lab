import axios from "axios";
import { LoaderStore } from "@/store/modules/loader";
import router from "@/router";

// Axios configuration with CORS support
let config = {
  baseURL: import.meta.env.VITE_BASE_URL || "/api",
  withCredentials: true,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  timeout: 30000,
};

const _axios = axios.create(config);

// URLs that should NOT show the global loader (search, filter, polling, heartbeat)
const shouldHideLoader = (url) => {
  if (!url) return false;
  const silentPatterns = [
    "/search",
    "/invoices/search-name",
    "/invoices/search-code",
    "/invoices/search-phone",
    "/referrals/search",
    "/patients/search-name",
    "update-result?silent",
    "/device/heartbeat",
    "/device-results",
    "/devices",
    "/tests?",
    "/result-status",
    "/genders",
    "/age-units",
    "/nationalities",
    "/titles",
    "/answer-types",
    "/duration-units",
    "/result-types",
    "/collectors",
    "/permissions",
    "/roles",
    "/log/",
  ];
  return silentPatterns.some((p) => url.includes(p));
};

// Request interceptor
_axios.interceptors.request.use(
  (request) => {
    const token = localStorage.getItem("token");
    if (token) {
      request.headers.Authorization = `Bearer ${token}`;
    }

    // Loader handling — hide for search/filter/polling requests
    try {
      const loaderStore = LoaderStore();
      if (!shouldHideLoader(request.url)) {
        loaderStore.PushRequest(request.url);
      } else {
        loaderStore.PushHideRequest(request.url);
      }
    } catch (e) {
      console.warn("Loader store error:", e);
    }
    return request;
  },
  (error) => {
    console.error(error);
    return Promise.reject(error);
  }
);

// Response interceptor
_axios.interceptors.response.use(
  (response) => {
    const loaderStore = LoaderStore();
    if (!shouldHideLoader(response.config.url)) {
      loaderStore.PopRequest(response.config.url);
    } else {
      loaderStore.PopHideRequest(response.config.url);
    }
    return response;
  },
  (error) => {
    try {
      const loaderStore = LoaderStore();
      if (error.config?.url) {
        if (!shouldHideLoader(error.config.url)) {
          loaderStore.PopRequest(error.config.url);
        } else {
          loaderStore.PopHideRequest(error.config.url);
        }
      }
    } catch (e) {
      console.warn("Loader store error:", e);
    }

    const status = error.response?.status;
    const isLoginRequest = error.config?.url?.includes("/user/login");

    // Handle different error codes
    switch (status) {
      case 400:
        showToast(error.response.data?.message || "Bad request", "error");
        break;

      case 401:
        if (!isLoginRequest) {
          localStorage.removeItem("token");
          localStorage.removeItem("user");
          localStorage.removeItem("rolePermissions");
          // If user is on a public page (welcome/login/register/result/invoice), don't redirect away.
          // Token was stale — silent removal is enough; public pages don't need auth.
          const path = window.location.pathname || "";
          const publicPrefixes = ["/", "/login", "/register", "/result/", "/invoice/", "/medical-reports/", "/error/"];
          const isPublic = publicPrefixes.some((p) => p === "/" ? path === "/" : path.startsWith(p));
          if (!isPublic) {
            // Send to /login (not /error/401) so user can re-auth and continue
            router.push("/login");
          }
        }
        break;

      case 403:
        // Don't redirect for login or public endpoints — let the component handle it
        if (!error.config?.url?.includes("/invoices/public/") && !error.config?.url?.includes("/user/login")) {
          showToast(error.response.data?.message || "Access denied", "error");
          router.push("/error/403");
        }
        break;

      case 404:
        // Only redirect for page not found, not for API resources
        if (!error.config?.url) {
          router.push("/error/404");
        } else {
          showToast(error.response.data?.message || "Resource not found", "error");
        }
        break;

      case 500:
        showToast(error.response.data?.message || "Server error. Please try again later.", "error");
        break;

      case 503:
        router.push("/error/503");
        break;

      default:
        if (error.response?.data?.message) {
          showToast(error.response.data.message, "error");
        } else if (error.message === "Network Error") {
          showToast("Network error. Please check your connection.", "error");
        }
    }

    return Promise.reject(error);
  }
);

// Toast notification function using Tailwind classes
function showToast(message, type = "error") {
  const isRtl = document.documentElement.dir === "rtl" || document.body.dir === "rtl";

  const baseClasses = "fixed top-5 z-[9999] px-4 py-3 rounded-lg shadow-lg max-w-[400px] flex items-center gap-2.5 transition-transform duration-300 ease-out";
  const positionClasses = isRtl ? "left-5 -translate-x-[120%]" : "right-5 translate-x-[120%]";
  const typeClasses = type === "error"
    ? "bg-red-50 border border-red-200 text-red-800"
    : "bg-green-50 border border-green-200 text-green-800";

  const toast = document.createElement("div");
  toast.className = `${baseClasses} ${positionClasses} ${typeClasses}`;

  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("class", "w-5 h-5 shrink-0");
  svg.setAttribute("fill", "none");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("stroke", "currentColor");
  const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
  path.setAttribute("stroke-linecap", "round");
  path.setAttribute("stroke-linejoin", "round");
  path.setAttribute("stroke-width", "2");
  path.setAttribute(
    "d",
    type === "error"
      ? "M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
      : "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
  );
  svg.appendChild(path);

  const span = document.createElement("span");
  span.className = "text-sm leading-snug";
  span.textContent = message;

  toast.appendChild(svg);
  toast.appendChild(span);
  document.body.appendChild(toast);

  // Trigger animation
  requestAnimationFrame(() => {
    toast.classList.remove("translate-x-[120%]", "-translate-x-[120%]");
    toast.classList.add("translate-x-0");
  });

  setTimeout(() => {
    toast.classList.remove("translate-x-0");
    toast.classList.add(isRtl ? "-translate-x-[120%]" : "translate-x-[120%]");
    setTimeout(() => {
      if (toast.parentNode) {
        document.body.removeChild(toast);
      }
    }, 300);
  }, 4000);
}

export const $http = _axios;
