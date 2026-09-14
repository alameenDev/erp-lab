import axios from "axios";

const logAxios = axios.create({
  baseURL: import.meta.env.VITE_BASE_URL,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  timeout: 5000,
});

// Add auth token if available
logAxios.interceptors.request.use((request) => {
  const token = localStorage.getItem("token");
  if (token) {
    request.headers.Authorization = `Bearer ${token}`;
  }
  return request;
});

/**
 * Send log to backend API
 * @param {string} level - Log level: error, warning, info, debug
 * @param {object} data - Log data
 */
const sendLog = async (level, data) => {
  try {
    await logAxios.post(`/log/${level}`, data);
  } catch (e) {
    // Silently fail - don't cause more errors when logging
    console.warn(`Failed to send ${level} log to server:`, e.message);
  }
};

/**
 * Frontend Logger - sends logs to backend API
 */
export const logger = {
  error: (message, extra = {}) => {
    console.error(message, extra);
    sendLog("error", {
      message: typeof message === "string" ? message : String(message),
      url: window.location.href,
      ...extra,
    });
  },

  warning: (message, extra = {}) => {
    console.warn(message, extra);
    sendLog("warning", {
      message: typeof message === "string" ? message : String(message),
      url: window.location.href,
      ...extra,
    });
  },

  info: (message, extra = {}) => {
    console.info(message, extra);
    sendLog("info", {
      message: typeof message === "string" ? message : String(message),
      url: window.location.href,
      ...extra,
    });
  },

  debug: (message, extra = {}) => {
    console.debug(message, extra);
    sendLog("debug", {
      message: typeof message === "string" ? message : String(message),
      url: window.location.href,
      ...extra,
    });
  },
};

/**
 * Vue Error Handler - catches Vue component errors
 * Usage: app.config.errorHandler = vueErrorHandler
 */
export const vueErrorHandler = (err, instance, info) => {
  const componentName = instance?.$options?.name || instance?.$.type?.name || "Unknown";

  logger.error(err.message || "Vue Error", {
    stack: err.stack,
    component: componentName,
    info: info,
    source: "vue",
  });
};

/**
 * Vue Warning Handler - catches Vue warnings
 * Usage: app.config.warnHandler = vueWarnHandler
 */
export const vueWarnHandler = (msg, instance, trace) => {
  const componentName = instance?.$options?.name || instance?.$.type?.name || "Unknown";

  logger.warning(msg, {
    component: componentName,
    data: { trace },
    source: "vue",
  });
};

/**
 * Setup global window error handlers
 * Call this function once on app initialization
 */
export const setupGlobalErrorHandlers = () => {
  // Catch uncaught errors
  window.onerror = (message, source, line, column, error) => {
    logger.error(message, {
      stack: error?.stack,
      source: source,
      line: line,
      column: column,
    });
    return false; // Let the error propagate
  };

  // Catch unhandled promise rejections
  window.onunhandledrejection = (event) => {
    const error = event.reason;
    logger.error(error?.message || "Unhandled Promise Rejection", {
      stack: error?.stack,
      source: "unhandledrejection",
    });
  };
};

export default logger;
