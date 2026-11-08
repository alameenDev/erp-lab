<script setup>
import { computed } from 'vue';
import { commentText } from '@/utils/resultComments';
const props = defineProps({ value: { default: '' }, label: { type: String, default: '' } });
const text = computed(() => commentText(props.value));
</script>

<template>
  <div v-if="text" class="report-comment" data-report-keep-together style="max-width:100%; text-align:start; white-space:normal; overflow-wrap:anywhere; text-transform:none; padding:4px 0;">
    <p class="report-comment-text" dir="auto" style="margin:0; line-height:1.55; white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word;"><strong>Comment<span v-if="label"> — {{ label }}</span>: </strong>{{ text }}</p>
  </div>
</template>
