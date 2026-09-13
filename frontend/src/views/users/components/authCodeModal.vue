<template>
     <Dialog
          v-model:visible="codeDialog"
          modal
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <p class="activation_header">
               {{ t("Activation_msg") }}
               <span>{{ record.email }}</span>
               ,{{ t("please") }}
          </p>
          <br />
          <form @submit.prevent="confirm()" class="border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-7" v-show="isReset">
                         <label class="block text-center text-md mb-2">{{ t("NewPassword") }}</label>
                         <Password class="w-full" v-model="password" :toggleMask="true" inputClass="w-full"></Password>
                    </div>
                    <div class="col-7 otp">
                         <label class="block text-md mb-2">{{ t("otpCode") }}</label>
                         <OtpInput required v-model="otpCode" :integerOnly="true" :length="6"></OtpInput>
                    </div>
               </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
                    <Button
                         size="small"
                         type="submit"
                         :label="!isReset ? t('activate') : t('save')"
                         severity="success"
                         :disabled="otpCode == ''"></Button>
               </div>
          </form>
          <br />
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useUsersStore } from "@/store/modules/users";
     import OtpInput from "./otp-input.vue";
     export default {
          data() {
               return {
                    password: "",
               };
          },
          computed: {
               ...mapWritableState(useUsersStore, ["record", "codeDialog", "otpCode"]),
          },
          components: {
               OtpInput,
          },
          props: {
               isReset: Boolean,
          },
          methods: {
               ...mapActions(useUsersStore, ["VerifyCode", "reset_password"]),

               confirm() {
                    if (this.isReset) {
                         this.reset_password(this.password).then(() => {
                              this.alertSuccess(this.t("alertSuccess"));
                              this.clearObjectValues(this.record);
                              this.otpCode = "";
                              this.codeDialog = false;
                         });
                    } else {
                         this.VerifyCode().then(() => {
                              this.alertSuccess(this.t("alertSuccess"));
                              this.clearObjectValues(this.record);
                              this.otpCode = "";
                              this.codeDialog = false;
                         });
                    }
               },

               close() {
                    this.codeDialog = false;
                    this.clearObjectValues(this.record);
                    this.otpCode = "";
               },
          },
     };
</script>
<style scoped>
     .grid {
          justify-content: center;
     }
     label {
          font-weight: bold;
     }
     .otp {
          direction: ltr;
          text-align: center;
     }
     .activation_header {
          text-align: center;
          font-size: large;
          font-weight: 500;
          color: #006224;
     }
     .error {
          color: #d71d1d;
     }
     .border-error {
          border: 1px solid red;
     }
</style>
