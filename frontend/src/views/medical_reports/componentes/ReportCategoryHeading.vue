<script setup>
defineProps({ category: String, modern: Boolean });
</script>
<template>
  <div v-if="category" data-report-category class="section-header w-full font-bold border border-black p-1 text-center text-black bg-gray-300">
    <i v-if="modern" class="pi pi-filter mr-section-icon" aria-hidden="true"></i><span>{{ category }}</span><small v-if="modern" class="mr-section-caption">TEST RESULTS</small>
  </div>
</template>
