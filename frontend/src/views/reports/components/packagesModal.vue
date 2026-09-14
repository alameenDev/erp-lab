<script setup>
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { useReportsStore } from "@/store/modules/reports";
import { t } from "@/utils/helper";

const reportsStore = useReportsStore();
const { packdialog, packages } = storeToRefs(reportsStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");

// Client-side pagination
const currentPage = ref(1);
const rowsPerPage = ref(25);

const paginatedPackages = computed(() => {
  const start = (currentPage.value - 1) * rowsPerPage.value;
  const end = start + rowsPerPage.value;
  return packages.value?.slice(start, end) || [];
});

const totalPages = computed(() => {
  return Math.ceil((packages.value?.length || 0) / rowsPerPage.value);
});

const close = () => {
  packages.value = [];
  packdialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="packdialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("packages") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
            <div v-if="packages?.length" class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">{{ t("name") }}</th>
                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">{{ t("count") }}</th>
                    <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">{{ t("Total") }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="pkg in paginatedPackages" :key="pkg.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-center text-gray-700">{{ pkg.name }}</td>
                    <td class="px-4 py-3 text-center text-gray-700">{{ pkg.count }}</td>
                    <td class="px-4 py-3 text-center text-gray-700">{{ pkg.total }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Pagination -->
              <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 mt-4">
                <button
                  v-for="page in totalPages"
                  :key="page"
                  @click="currentPage = page"
                  :class="[
                    'px-3 py-1 rounded text-sm',
                    currentPage === page
                      ? 'bg-blue-600 text-white'
                      : 'border border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
            </div>
            <div v-else class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
              <svg class="w-12 h-12 text-blue-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-blue-700 font-medium">{{ t("noData") }}</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
