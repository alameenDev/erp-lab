<template>
     <Dialog
          v-model:visible="culturedialog"
          modal
          :header="t('cultures')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <ul v-if="cultures?.length?.toLocaleString() > 0">
                    <li v-for="culture in cultures" :key="culture.id">{{ culture.name }}</li>
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
               ...mapWritableState(usetestGroupsStore, ["culturedialog", "cultures"]),
          },

          methods: {
               close() {
                    this.cultures = [];
                    this.culturedialog = false;
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
