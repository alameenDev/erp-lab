<template>
     <Dialog
          v-model:visible="resdialog"
          modal
          :header="t('Result_Comments')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div>
               <ul v-if="result_comments.length?.toLocaleString() > 0">
                    <li v-for="comment in result_comments" :key="comment">
                         {{ comment }}
                    </li>
               </ul>

               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
     </Dialog>
</template>
<script>
     import { mapWritableState } from "pinia";
     import { useculturesStore } from "@/store/modules/cultures";

     export default {
          computed: {
               ...mapWritableState(useculturesStore, ["resdialog", "result_comments"]),
          },

          methods: {
               close() {
                    this.result_comments = [];
                    this.resdialog = false;
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
