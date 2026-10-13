<script setup>
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";

const invoicesStore = useinvoicesStore();
const { printRecord } = storeToRefs(invoicesStore);
</script>

<template>
  <section class="w-full flex justify-center my-2" v-if="printRecord?.signed_by?.image">
    <img :src="printRecord?.signed_by?.image" width="100" height="100" />
  </section>
</template>
