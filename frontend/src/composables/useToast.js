import { ref } from "vue";

// Global toast state
const toasts = ref([]);
let toastId = 0;

const removeToast = (id) => {
  const index = toasts.value.findIndex((t) => t.id === id);
  if (index > -1) {
    toasts.value.splice(index, 1);
  }
};

export function useToast() {
  const show = (message, options = {}) => {
    const id = ++toastId;
    const toast = {
      id,
      message,
      type: options.type || "info",
      title: options.title || "",
      duration: options.duration ?? 3000,
    };

    toasts.value.push(toast);

    if (toast.duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, toast.duration);
    }

    return id;
  };

  return {
    toasts,
    show,
    success: (message, options = {}) => show(message, { ...options, type: "success" }),
    error: (message, options = {}) => show(message, { ...options, type: "error" }),
    warning: (message, options = {}) => show(message, { ...options, type: "warning" }),
    info: (message, options = {}) => show(message, { ...options, type: "info" }),
    remove: removeToast,
  };
}

// Export the toasts ref for the Toast component
export { toasts, removeToast };
