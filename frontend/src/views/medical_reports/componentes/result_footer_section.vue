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
  <section v-if="printRecord?.tests_last_results?.length > 0">
    <h3>patient History</h3>
    <table class="w-full my-5" style="border-collapse: collapse;">
      <thead>
        <tr>
          <th class="border border-black/15 p-2.5 text-center">test</th>
          <th class="border border-black/15 p-2.5 text-center">Result</th>
          <th class="border border-black/15 p-2.5 text-center">date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, index) in printRecord?.tests_last_results" :key="index">
          <td class="border border-black/15 p-2.5 text-center">{{ item.name }}</td>
          <td class="border border-black/15 p-2.5 text-center">{{ item.result }}</td>
          <td class="border border-black/15 p-2.5 text-center">{{ item.result_date }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
