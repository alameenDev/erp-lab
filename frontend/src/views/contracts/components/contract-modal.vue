<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-6 pb-0 input-name">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <div style="position: relative">
                              <InputText filter class="w-full" required type="text" v-model="record.name" />
                         </div>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("email") }}</label>
                         <InputText class="w-full" required type="email" v-model="record.email" />
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
                         <InputText
                              class="w-full"
                              required
                              type="number"
                              v-model="record.phone_number"
                              maxlength="11"
                              minlength="11" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("address") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.address" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("discount_percentage") }}</label>
                         <InputNumber
                              required
                              class="w-full"
                              v-model="record.discount_percentage"
                              inputId="integeronly"
                              fluid />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("payment_percent") }}</label>
                         <InputNumber
                              required
                              class="w-full"
                              v-model="record.payment_percent"
                              inputId="integeronly"
                              fluid />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("maximum_payment_per_invoice") }}</label>
                         <InputNumber
                              required
                              class="w-full"
                              v-model="record.maximum_payment_per_invoice"
                              inputId="integeronly"
                              fluid />
                    </div>
                    <div class="col-6 pb-0">
                         <label required class="block text-md mb-2">{{ t("credit_limit") }}</label>
                         <InputNumber class="w-full" v-model="record.credit_limit" inputId="integeronly" fluid />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("price_limit") }}</label>
                         <InputNumber
                              required
                              class="w-full"
                              v-model="record.price_limit"
                              inputId="integeronly"
                              fluid />
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
     import { useContractsStore } from "@/store/modules/contract";

     export default {
          data() {},

          computed: {
               ...mapWritableState(useContractsStore, ["record", "dialog"]),
          },
          mounted() {},
          methods: {
               ...mapActions(useContractsStore, ["AddContract", "UpdateContract"]),

               create() {
                    /**
                     * Ensures that the `discount_percentage`, `payment_percent`, `credit_limit`, `price_limit`,
                     * and `maximum_payment_per_invoice` fields of the `record` object are set to 0 if they are
                     * currently undefined or null. Then, it calls the `AddContract` method and, upon successful
                     * completion, displays a success alert and closes the dialog.
                     */
                    this.record.discount_percentage = this.record.discount_percentage
                         ? this.record.discount_percentage
                         : 0;
                    this.record.payment_percent = this.record.payment_percent ? this.record.payment_percent : 0;
                    this.record.credit_limit = this.record.credit_limit ? this.record.credit_limit : 0;
                    this.record.price_limit = this.record.price_limit ? this.record.price_limit : 0;
                    this.record.maximum_payment_per_invoice = this.record.maximum_payment_per_invoice
                         ? this.record.maximum_payment_per_invoice
                         : 0;
                    this.AddContract().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));

                         this.dialog = false;
                    });
               },
               update() {
                    this.record.discount_percentage = this.record.discount_percentage
                         ? this.record.discount_percentage
                         : 0;
                    this.record.payment_percent = this.record.payment_percent ? this.record.payment_percent : 0;
                    this.record.credit_limit = this.record.credit_limit ? this.record.credit_limit : 0;
                    this.record.price_limit = this.record.price_limit ? this.record.price_limit : 0;
                    this.record.maximum_payment_per_invoice = this.record.maximum_payment_per_invoice
                         ? this.record.maximum_payment_per_invoice
                         : 0;
                    this.UpdateContract().then(() => {
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
<style>
     .error {
          color: #d71d1d;
     }
     .border-error {
          border: 1px solid red;
     }
     .input-name {
          position: relative;
     }
     .ul {
          position: absolute;
          z-index: 2;
          left: 17px;
          right: 17px;

          background: white;
     }
     .rtl {
          left: 8px;
     }
     .ltr {
          right: 8px;
     }
     .pi-spin {
          font-size: 1.5rem;
          position: absolute;
          top: 10px;
          color: #004e54bd;
     }
     .hint {
          margin: 0;
          color: rgb(170 54 54 / 97%);
          font-size: medium;
          font-weight: 500;
     }
</style>
