<script setup>
import { computed, watch, onBeforeUnmount } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: "" },
  size: { type: String, default: "md" },
  closable: { type: Boolean, default: true },
  closeOnBackdrop: { type: Boolean, default: true },
  noPadding: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "close"]);

const sizeClasses = computed(() => ({
  sm: "max-w-sm",
  md: "max-w-lg",
  lg: "max-w-2xl",
  xl: "max-w-4xl",
  "2xl": "max-w-5xl",
  full: "max-w-6xl",
})[props.size]);

const close = () => {
  emit("update:modelValue", false);
  emit("close");
};

const handleBackdropClick = (e) => {
  if (e.target === e.currentTarget && props.closeOnBackdrop) close();
};

watch(() => props.modelValue, (val) => {
  document.body.style.overflow = val ? "hidden" : "";
});

onBeforeUnmount(() => {
  document.body.style.overflow = "";
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click="handleBackdropClick"
      >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" />

        <!-- Modal Container - Centers the modal -->
        <div class="flex min-h-full items-center justify-center p-4">
          <!-- Modal -->
          <div
            :class="[
              'relative w-full bg-white rounded-2xl shadow-2xl transform transition-all',
              sizeClasses,
            ]"
            @click.stop
          >
            <!-- Header -->
            <div v-if="title || $slots.header" class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
              <h3 class="text-lg font-semibold text-slate-900">
                <slot name="header">{{ title }}</slot>
              </h3>
              <button
                v-if="closable"
                type="button"
                class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                @click="close"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div :class="[noPadding ? '' : 'px-6 py-4', 'max-h-[calc(100vh-180px)] overflow-y-auto']">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
              <slot name="footer" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
