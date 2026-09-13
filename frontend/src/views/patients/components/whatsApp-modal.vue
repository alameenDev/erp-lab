<template>
     <Dialog
          v-model:visible="whatsDialog"
          modal
          :header="t('sendMessage')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          >
          <form @submit.prevent="send()" class="border-top-1 border-bluegray-100">
               <div class="mt-5">
                    <div class="pb-0 text-center">
                         <Textarea v-model="MsgRecord.message" rows="5" cols="50" :placeholder="t('writeMessage')" />
                    </div>
               </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
                    <Button size="small" type="submit" :label="t('send')" severity="success"></Button>
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { usePatientsStore } from "@/store/modules/patients";

     export default {
          computed: {
               ...mapWritableState(usePatientsStore, ["whatsDialog", "MsgRecord"]),
          },

          methods: {
               ...mapActions(usePatientsStore, ["whatsapp"]),

               send() {
                    this.whatsapp().then(() => {
                         this.whatsDialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.MsgRecord);
                    });
               },
               close() {
                    this.whatsDialog = false;
                    this.clearObjectValues(this.MsgRecord);
               },
          },
     };
</script>
