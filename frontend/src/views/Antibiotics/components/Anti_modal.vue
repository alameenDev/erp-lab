<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("scientific_name") }}</label>
                         <InputText
                              required
                              class="w-full"
                              type="text"
                              v-model="record.scientific_name"
                              maxlength="255" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("common_name") }}</label>
                         <InputText
                              required
                              class="w-full"
                              type="common_name"
                              v-model="record.common_name"
                              maxlength="255" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("short_name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.short_name" maxlength="255" />
                    </div>
               </div>

               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()" />
                    <Button size="small" type="submit" :label="record.id ? t('save') : t('add')" severity="success" />
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useAntibioticsStore } from "@/store/modules/Antibiotics";

     export default {
          computed: {
               ...mapWritableState(useAntibioticsStore, ["record", "dialog"]),
          },
          mounted() {},
          methods: {
               ...mapActions(useAntibioticsStore, ["AddAntibiotics", "UpdateAntibiotics"]),

               create() {
                    this.AddAntibiotics().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.dialog = false;
                    });
               },

               update() {
                    this.UpdateAntibiotics().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.dialog = false;
                    });
               },
               close() {
                    this.clearObjectValues(this.record);

                    this.dialog = false;
               },
          },
          watch: {},
     };
</script>
<style scoped lang="scss">
     .add-ptn {
          position: absolute;
          bottom: 1px;
          left: 0;
          button {
               border-radius: 50%;
          }
     }
     .options {
          background: #f9fafb;
          padding: 14px;
          position: relative;
          border-radius: 19px;
          border: 1px solid #f3f4f6;
     }
     .add {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 10px;
          padding: 0 6px;
     }
     label {
          font-weight: 500;
     }
     .ages {
          display: flex;
          justify-content: space-between;
     }
     .relative {
          position: relative;
     }
     .absolute {
          position: absolute;
     }
     .top-0 {
          top: 0;
     }
     .right-0 {
          right: 0;
     }

     .tests-reference {
          position: relative;
          background: #fafafa;
          border-radius: 19px;
          padding: 10px;
          .remove {
               background: #8a0a0adb;
               color: white;
               padding: 2px 6px;
               width: fit-content;
          }
          .border {
               background: #f0f8ffa1;
               border: 1px solid #0000001a;
               margin: 8px;
               border-radius: 10px;
          }
     }
     label {
          font-weight: 500;
     }
</style>
