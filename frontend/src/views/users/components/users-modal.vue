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
                         <InputText class="w-full" v-model="record.name" required type="text" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("email") }}</label>
                         <InputText class="w-full" v-model="record.email" required type="email" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("password") }}</label>

                         <Password
                              :required="!record.id"
                              class="w-full"
                              v-model="record.password"
                              :toggleMask="true"
                              inputClass="w-full"></Password>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("phone_number") }}</label>
                         <InputText class="w-full" required type="text" v-model="record.phone" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("address") }}</label>
                         <InputText class="w-full" type="text" v-model="record.address" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Userimage") }}</label>
                         <InputText
                              class="w-full"
                              type="file"
                              @change="onFileChange('image', $event)"
                              accept=".png ,.jpg ,.jpeg" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Signature") }}</label>
                         <InputText
                              class="w-full"
                              type="file"
                              @change="onFileChange('Signature', $event)"
                              accept=".png ,.jpg ,.jpeg" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("userRole") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.role_id"
                              :options="UserRoles"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-6 pb-0" v-if="record.role_id == 4">
                         <label class="block text-md mb-2">{{ t("sample_collector") }}</label>
                         <Dropdown
                              class="w-full"
                              v-model="record.sample_collector_id"
                              :options="sample_collectors"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-6 pb-0" v-if="record.role_id == 4">
                         <label class="block text-md mb-2">{{ t("price_list") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.price_list_id"
                              :options="price_list()"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-6 pb-0" v-if="record.role_id == 4 || record.role_id == 6">
                         <label class="block text-md mb-2">{{ t("commission") }}</label>
                         <InputText class="w-full" type="text" v-model="record.discount_percentage" />
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
     import { mapActions, mapWritableState, mapGetters } from "pinia";
     import { useUsersStore } from "@/store/modules/users";
     import { priceListStore } from "@/store/modules/priceList";

     export default {
          data() {
               return {};
          },
          computed: {
               ...mapWritableState(useUsersStore, [
                    "selectedFile",
                    "signature",
                    "sample_collectors",
                    "record",
                    "dialog",
                    "UserRoles",
                    "codeDialog",
               ]),
               ...mapGetters(priceListStore, ["price_list"]),
          },
          mounted() {
               this.GetpriceList();
          },
          methods: {
               ...mapActions(useUsersStore, ["AddUser", "UpdateUser", "ResendCode"]),
               ...mapActions(priceListStore, ["GetpriceList"]),

               create() {
                    this.AddUser().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         // this.ResendCode().then(() => {
                         //      this.codeDialog = true;
                         // });
                         this.dialog = false;
                    });
               },
               update() {
                    this.UpdateUser().then(() => {
                         this.dialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                    });
               },
               close() {
                    this.dialog = false;
                    this.clearObjectValues(this.record);
               },
               onFileChange(type, event) {
                    if (type == "Signature") {
                         this.signature = event.target.files[0];
                    } else {
                         this.selectedFile = event.target.files[0];
                    }
               },
          },
     };
</script>
<style>
     .error {
          color: #d71d1d;
     }
     .border-error {
          border: 1px solid red;
     }
</style>
