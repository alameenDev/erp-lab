import axios from "axios";
import { LoaderStore } from "@/store/modules/loader";

// Axios configuration with CORS support
let config = {
     baseURL: import.meta.env.VITE_BASE_URL,
     withCredentials: true, // Enable sending cookies and credentials cross-origin
     headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
     },
     timeout: 30000, // 30 seconds timeout
};

const _axios = axios.create(config);

// Set authorization header if token exists
_axios.defaults.headers.Authorization = localStorage.getItem("token") ? `Bearer ${localStorage.getItem("token")}` : "";
const excludedUrls = ["/referrals/search", "/invoices/search-name", "/invoices/search-code", "/invoices/search-phone"]; // Add URLs that should not trigger the loader
// Request interceptor
_axios.interceptors.request.use(
     (request) => {
          // You can modify request here if needed
          const loaderStore = LoaderStore();
          if (!excludedUrls.includes(request.url)) {
               loaderStore.PushRequest(request.url);
          } else {
               loaderStore.PushHideRequest(request.url);
          }
          return request;
     },
     (error) => {
          // Handle request error here
          console.error(error);
          return Promise.reject(error);
     }
);

// Response interceptor
_axios.interceptors.response.use(
     (response) => {
          const loaderStore = LoaderStore();
          if (!excludedUrls.includes(response.config.url)) {
               loaderStore.PopRequest(response.config.url);
          } else {
               loaderStore.PopHideRequest(response.config.url);
          }
          return response;
     },
     (error) => {
          const loaderStore = LoaderStore();
          loaderStore.PopRequest(error.config.url);

          if (error.response.status !== 200 || error.response.status !== 201 || error.response.status !== 400) {
               alertError(error.response.data.message);
          }
          if (error.response?.status === 400) {
               alertError(error.response.data.message);
          }
          if (error.response?.status === 401) {
               localStorage.removeItem("token");
               localStorage.removeItem("user");
               window.location.href = "/";
          }
          if (error.response?.status === 500) {
               alertError("something wrong try again");
          }

          return Promise.reject(error);
     }
);

function alertError(message) {
     var toast = document.createElement("div");
     toast.className = "error-toast";
     toast.innerHTML = message;
     document.body.appendChild(toast);
     setTimeout(function () {
          document.body.removeChild(toast);
     }, 3000);
}

export const $http = _axios;
