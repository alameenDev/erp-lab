<template>
     <Dialog
          v-model:visible="testdialog"
          modal
          :header="t('tests')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <ul v-if="tests.length?.toLocaleString() > 0">
                    <li v-for="test in tests" :key="test.id">{{ test.name }} - {{ test.shortcut }}</li>
               </ul>
               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { usetestGroupsStore } from "@/store/modules/testGroups";

     export default {
          computed: {
               ...mapWritableState(usetestGroupsStore, ["testdialog", "tests"]),
          },

          methods: {
               close() {
                    this.tests = [];
                    this.testdialog = false;
               },
          },
          watch: {},
     };
</script>
<style scoped>
     ul {
          list-style: circle;
          background: rgba(0, 0, 0, 0.02);
     }
     li {
          background: #b9d1c742;
          padding: 6px;
          border-radius: 4px;
          margin: 3px;
     }
</style>
