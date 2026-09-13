<template>
     <Dialog
          v-model:visible="resdialog"
          modal
          :header="t('cultures')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <ul v-if="cultures.length?.toLocaleString() > 0">
                    <li v-for="cultur in cultures" :key="cultur.id">{{ cultur.name }}</li>
               </ul>
               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { usePackagesStore } from "@/store/modules/packages";

     export default {
          computed: {
               ...mapWritableState(usePackagesStore, ["resdialog", "cultures"]),
          },

          methods: {
               close() {
                    this.cultures = [];
                    this.resdialog = false;
               },
          },
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
