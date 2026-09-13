<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <InputText class="w-full" required type="text" v-model="record.name" />
                    </div>
               </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
                    <Button
                         size="small"
                         type="submit"
                         :label="record.id ? t('save') : t('add')"
                         severity="success"></Button>
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useCategoriesStore } from "@/store/modules/categories";

     export default {
          computed: {
               ...mapWritableState(useCategoriesStore, ["record", "dialog"]),
          },

          methods: {
               ...mapActions(useCategoriesStore, ["Addcategory", "Updatecategory"]),

               create() {
                    this.Addcategory().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.dialog = false;
                    });
               },
               update() {
                    this.Updatecategory().then(() => {
                         this.dialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                    });
               },
               close() {
                    this.dialog = false;
                    this.clearObjectValues(this.record);
               },
          },
     };
</script>
