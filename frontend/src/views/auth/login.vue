<template>
     <div
          class="surface-ground flex align-items-center justify-content-center min-h-screen min-w-screen overflow-hidden">
          <div class="flex flex-column align-items-center justify-content-center">
               <div
                    style="
                         border-radius: 56px;
                         padding: 0.3rem;
                         background: linear-gradient(180deg, #004e54 10%, rgba(237, 23, 94, 0) 30%);
                    ">
                    <form
                         @submit="submit"
                         class="w-full surface-card pb-4 pt-3 px-5 sm:px-8"
                         style="border-radius: 53px">
                         <div class="text-center mb-4">
                              <img src="/login.jpg" alt="Image" height="200" class="mb-3" />
                         </div>

                         <div>
                              <label class="block text-900 text-lg font-medium mb-2">{{ t("email") }}</label>
                              <InputText
                                   required
                                   type="email"
                                   class="w-full md:w-30rem mb-3"
                                   style="padding: 1rem"
                                   v-model="email" />

                              <label class="block text-900 font-medium text-lg mb-2">{{ t("password") }}</label>
                              <Password
                                   dir="ltr"
                                   :feedback="false"
                                   required
                                   v-model="password"
                                   :toggleMask="true"
                                   class="w-full mb-3"
                                   inputClass="w-full"
                                   :inputStyle="{ padding: '1rem' }"></Password>

                              <Button
                                   style="background-color: #004e54; border-color: #004e54"
                                   type="submit"
                                   :label="t('login')"
                                   class="w-full text-white-700 p-2 mt-3 text-lg"></Button>
                              <p
                                   class="w-full mt-3 text-center text-lg"
                                   style="color: #004e54; cursor: pointer"
                                   @click="forgetPassword()">
                                   {{ t("forget_password") }}
                              </p>
                         </div>
                    </form>
               </div>
          </div>

          <AuthCodeModal isReset="true"></AuthCodeModal>
          <loading v-if="isLoading" />
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useAuthStore } from "@/store/modules/auth";
     import { useUsersStore } from "@/store/modules/users";
     import loading from "@/components/loading.vue";
     import AuthCodeModal from "../users/components/authCodeModal.vue";
     export default {
          components: {
               loading,
          },
          data() {
               return {
                    email: "",
                    password: "",
                    isLoading: false,
               };
          },
          components: {
               AuthCodeModal,
          },
          computed: {
               ...mapWritableState(useUsersStore, ["records", "record", "codeDialog"]),
          },

          methods: {
               ...mapActions(useAuthStore, ["login"]),
               ...mapActions(useUsersStore, ["forget_password"]),
               async submit(e)  {
                    e.preventDefault();
                    this.isLoading = true;
                    await this.login({ email: this.email, password: this.password });
                    this.isLoading = false;
               },
               forgetPassword() {
                    this.record.email = this.email;
                    this.forget_password().then(() => {
                         this.codeDialog = true;
                         this.isLoading = false;
                    });
               },
          },
     };
</script>

<style scoped>
     img {
          border-radius: 26%;
     }
     .pi-eye {
          transform: scale(1.6);
          margin-right: 1rem;
     }

     .pi-eye-slash {
          transform: scale(1.6);
          margin-right: 1rem;
     }
</style>
