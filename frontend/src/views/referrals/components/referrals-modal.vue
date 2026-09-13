<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form
               @submit.prevent="record.id ? update() : create()"
               class="border-top-1 border-bluegray-100"
               @click="isShow = false">
               <div class="grid mt-1">
                    <div class="col-6 pb-0 input-name">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <div style="position: relative">
                              <InputText
                                   filter
                                   class="w-full"
                                   required
                                   type="text"
                                   v-model="record.name"
                                   @input="searchitem(record.name)" />
                              <i
                                   class="pi pi-spin pi-spinner"
                                   v-show="hideLoading"
                                   :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                         </div>

                         <div class="ul" v-show="isShow">
                              <!-- <p class="hint">{{ t("aleadyThere") }}</p> -->

                              <Listbox
                                   listStyle="max-height:250px"
                                   v-model="selectedRecord"
                                   :options="searchRecords"
                                   optionLabel="name"
                                   class="w-full md:w-56 list" />
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
                         <label class="block text-md mb-2">{{ t("commission") }}</label>
                         <InputNumber class="w-full" v-model="record.commission" inputId="integeronly" fluid />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("address") }}</label>
                         <InputText class="w-full" type="text" v-model="record.address" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("userRole") }}</label>

                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.role_id"
                              :options="UserRoles.asList()"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-6 pb-0" v-if="record.role_id == 2">
                         <label class="block text-md mb-2">{{ t("priceList") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.price_list_id_fk"
                              :options="price_list()"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
               </div>
               <!-- <div class="updat grid mt-1" v-show="record.id">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("commission") }}</label>
                         <InputNumber class="w-full" v-model="record.commission" inputId="integeronly" fluid />
                    </div>
               </div> -->
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
     import { useReferralsStore } from "@/store/modules/referrals";
     import { ReverralUserRole } from "@/enums";
     import { LoaderStore } from "@/store/modules/loader";
     import { priceListStore } from "@/store/modules/priceList";

     export default {
          data() {
               return { isShow: false, selectedRecord: "" };
          },

          computed: {
               UserRoles() {
                    return ReverralUserRole;
               },
               ...mapWritableState(LoaderStore, ["hideLoading"]),
               ...mapGetters(priceListStore, ["price_list"]),
               ...mapWritableState(useReferralsStore, [
                    "record",
                    "dialog",
                    "searchRecords",
                    "searchTotalCount",
                    "filter",
               ]),
          },
          mounted() {
               this.GetpriceList();
          },
          methods: {
               ...mapActions(useReferralsStore, ["AddUser", "UpdateReferral", "AddReferral", "search"]),
               ...mapActions(priceListStore, ["GetpriceList"]),
               create() {
                    this.record.commission = this.record.commission ? this.record.commission : 0;
                    this.AddReferral().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));

                         this.dialog = false;
                    });
               },
               update() {
                    this.UpdateReferral().then(() => {
                         this.dialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                    });
               },
               close() {
                    this.dialog = false;
                    this.clearObjectValues(this.record);
               },
               searchitem(v) {
                    if (v) {
                         this.search(v);
                         this.isShow = true;
                    } else {
                         this.isShow = false;
                         this.clearObjectValues(this.record);
                    }
               },
          },
          watch: {
               selectedRecord: function (v) {
                    if (v) {
                         this.isShow = false;
                         Object.assign(this.record, v);
                         this.record.id = null;
                         this.record.role_id =
                              this.UserRoles?.asList().find((x) => x.label.toLowerCase() == v.role.toLowerCase())
                                   ?.value ?? "";
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
