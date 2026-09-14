<script setup>
import { ref, computed, watch } from "vue";

const props = defineProps({
  data: { type: Array, default: () => [] },
  columns: { type: Array, default: () => [] },
  paginator: { type: Boolean, default: true },
  rows: { type: Number, default: 10 },
  searchable: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: "Search..." },
  searchFields: { type: Array, default: () => [] },
  selectable: { type: Boolean, default: false },
  emptyMessage: { type: String, default: "No records found" },
});

const emit = defineEmits(["row-select", "sort"]);

const searchQuery = ref("");
const sortField = ref("");
const sortOrder = ref(1);
const currentPage = ref(1);
const selectedRows = ref([]);

const getNestedValue = (obj, path) => {
  return path.split(".").reduce((acc, part) => acc && acc[part], obj);
};

const filteredData = computed(() => {
  let result = [...props.data];

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    const fields = props.searchFields.length > 0
      ? props.searchFields
      : props.columns.map(c => c.field);

    result = result.filter(row =>
      fields.some(field => {
        const value = getNestedValue(row, field);
        return value && String(value).toLowerCase().includes(query);
      })
    );
  }

  // Sorting
  if (sortField.value) {
    result.sort((a, b) => {
      const aVal = getNestedValue(a, sortField.value);
      const bVal = getNestedValue(b, sortField.value);

      if (aVal < bVal) return -1 * sortOrder.value;
      if (aVal > bVal) return 1 * sortOrder.value;
      return 0;
    });
  }

  return result;
});

const totalPages = computed(() => Math.ceil(filteredData.value.length / props.rows) || 1);
const startIndex = computed(() => (currentPage.value - 1) * props.rows);
const endIndex = computed(() => startIndex.value + props.rows);

const paginatedData = computed(() => {
  if (!props.paginator) return filteredData.value;
  return filteredData.value.slice(startIndex.value, endIndex.value);
});

const toggleSort = (field) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 1 ? -1 : 1;
  } else {
    sortField.value = field;
    sortOrder.value = 1;
  }
  emit("sort", { field: sortField.value, order: sortOrder.value });
};

const toggleSelect = (row) => {
  const index = selectedRows.value.indexOf(row);
  if (index > -1) {
    selectedRows.value.splice(index, 1);
  } else {
    selectedRows.value.push(row);
  }
  emit("row-select", { row, selected: selectedRows.value });
};

watch(searchQuery, () => {
  currentPage.value = 1;
});

watch(() => props.data, () => {
  currentPage.value = 1;
});
</script>

<template>
  <div class="overflow-hidden bg-white rounded-xl border border-slate-200 shadow-sm">
    <!-- Search/Filter Bar -->
    <div v-if="searchable || $slots.toolbar" class="p-4 border-b border-slate-100">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div v-if="searchable" class="relative flex-1 max-w-md">
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full ps-10 pe-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 placeholder:text-slate-400"
          />
          <svg class="absolute start-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <slot name="toolbar"></slot>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th
              v-for="col in columns"
              :key="col.field"
              :class="[
                'px-4 py-3 text-start text-xs font-semibold text-slate-600 uppercase tracking-wider',
                col.sortable ? 'cursor-pointer hover:bg-slate-100 select-none transition-colors' : '',
                col.headerClass || '',
              ]"
              :style="col.width ? { width: col.width } : {}"
              @click="col.sortable && toggleSort(col.field)"
            >
              <div class="flex items-center gap-2">
                {{ col.header }}
                <template v-if="col.sortable">
                  <svg v-if="sortField === col.field" class="h-4 w-4 text-primary-500" :class="sortOrder === 1 ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                  </svg>
                  <svg v-else class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                  </svg>
                </template>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-slate-100">
          <tr
            v-for="(row, index) in paginatedData"
            :key="row.id || index"
            :class="[
              'hover:bg-slate-50 transition-colors',
              selectable ? 'cursor-pointer' : '',
              selectedRows.includes(row) ? 'bg-primary-50' : '',
            ]"
            @click="selectable && toggleSelect(row)"
          >
            <td
              v-for="col in columns"
              :key="col.field"
              :class="['px-4 py-3 text-sm text-slate-700', col.bodyClass || '']"
            >
              <slot :name="col.field" :data="row" :index="index">
                {{ getNestedValue(row, col.field) }}
              </slot>
            </td>
          </tr>
          <tr v-if="paginatedData.length === 0">
            <td :colspan="columns.length" class="px-4 py-12 text-center">
              <div class="flex flex-col items-center gap-2">
                <svg class="w-12 h-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm text-slate-500">{{ emptyMessage }}</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="paginator && filteredData.length > 0" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
      <div class="text-sm text-slate-500">
        Showing <span class="font-medium text-slate-700">{{ startIndex + 1 }}</span> to <span class="font-medium text-slate-700">{{ Math.min(endIndex, filteredData.length) }}</span> of <span class="font-medium text-slate-700">{{ filteredData.length }}</span> entries
      </div>
      <div class="flex items-center gap-1">
        <button
          :disabled="currentPage === 1"
          class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-100 transition-colors"
          @click="currentPage--"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <span class="px-3 py-1.5 text-sm font-medium text-slate-700">
          {{ currentPage }} / {{ totalPages }}
        </span>
        <button
          :disabled="currentPage === totalPages"
          class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-100 transition-colors"
          @click="currentPage++"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>
