<template>
  <Dropdown
    v-model="selectedTest"
    :options="tests"
    :filter="true"
    :filterBy="'name'" 
    :placeholder="t('select')"
    optionLabel="name"
    @change="addSelection('test', selectedTest)"
    @filter="onFilter"
    @scroll="onScroll"
  />
</template>

<script>
import { ref } from 'vue'
import { $http } from "@/plugins/axios";

export default {
  data() {
    return {
      selectedTest: null,
      tests: [],
      searchTimeout: null,
    }
  },
  methods: {
    async fetchTests(query) {
      try {
        const { data } = await $http.get('/tests', {
          params: {
            search: query
          }
        })
        this.tests = data.data // أو حسب شكل الداتا بالاستجابة
      } catch (error) {
        console.error('Error fetching tests:', error)
      }
    },
    onFilter(event) {
      const query = event.value

      // عمل ديبونس: انتظار بعد الكتابة شوية قبل ما نرسل ريكويست
      clearTimeout(this.searchTimeout)

      this.searchTimeout = setTimeout(() => {
        this.fetchTests(query)
      }, 300) // تأخير 300ms
    },
    addSelection(type, value) {
      console.log(type, value)
      // هنا تحط الكود حسب شغلك
    },
    onScroll(event) {
      console.log('Scrolled!', event)
      // تقدر تضيف لود مور مثلا هنا
    }
  }
}
</script>
