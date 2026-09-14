<script setup>
defineProps({
  loading: { type: Boolean, default: true },
  size: { type: String, default: "md" },
  message: { type: String, default: "" },
  overlay: { type: Boolean, default: false },
});
</script>

<template>
  <Transition name="fade">
    <div
      v-if="loading"
      :class="[
        'flex items-center justify-center',
        overlay ? 'fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm' : '',
      ]"
    >
      <div class="flex flex-col items-center gap-4">
        <!-- Spinner -->
        <div class="relative w-20 h-20">
          <svg class="w-full h-full animate-spin-slow" viewBox="0 0 50 50">
            <circle
              class="stroke-primary-500 animate-dash"
              cx="25"
              cy="25"
              r="20"
              fill="none"
              stroke-width="4"
              stroke-linecap="round"
            ></circle>
          </svg>
          <!-- Center Icon -->
          <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 animate-pulse">
            <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
          </div>
        </div>

        <!-- Message -->
        <p v-if="message" class="text-base font-medium text-slate-500 animate-pulse">{{ message }}</p>

        <!-- Progress Bar -->
        <div class="w-48 h-1 rounded-sm overflow-hidden bg-slate-200">
          <div class="h-full w-2/5 rounded-sm bg-gradient-to-r from-primary-500 to-primary-600 animate-loading-progress"></div>
        </div>
      </div>
    </div>
  </Transition>
</template>
