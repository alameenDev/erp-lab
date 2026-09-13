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
                              <InputText
                                   filter
                                   class="w-full"
                                   :placeholder="t('search')"
                                   required
                                   type="text"
                                   v-model="record.name"
                                   @input="searchitemByname(record.name)" />
                              <i
                                   class="pi pi-spin pi-spinner"
                                   v-show="hideLoading && isNameShow"
                                   :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                         </div>

                         <div class="ul" v-show="isNameShow">
                              <Listbox
                                   v-if="searchNameTotalCount > 0"
                                   listStyle="max-height:250px"
                                   v-model="patient"
                                   :options="searchRecords"
                                   optionLabel="name"
                                   class="w-full md:w-56 list" />

                              <InlineMessage v-else class="w-full" severity="info" v-show="hideLoading">
                                   {{ t("noData") }}
                              </InlineMessage>
                         </div>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("email") }}</label>
                         <InputText class="w-full" type="email" v-model="record.email" />
                    </div>

                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("phone_number") }}</label>
                         <InputText minlength="11" maxlength="11" class="w-full" type="number" v-model="record.phone" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("dob") }}</label>
                         <InputText class="w-full" type="date" v-model="record.dob" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("age") }}</label>
                         <InputText required class="w-full" type="number" v-model="record.age" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("age_unit") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.age_unit_id_fk"
                              :options="AgeUnits"
                              optionLabel="label"
                              optionValue="value"
                              @change="ageChange(record.age_unit_id_fk)" />
                         <Message severity="error" v-if="age_unitErrormessage">{{ age_unitErrormessage }}</Message>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("gender") }}</label>
                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.gender_type_id_fk"
                              :options="genders"
                              @change="changeTitle(record.gender_type_id_fk)"
                              optionLabel="label"
                              optionValue="value" />
                         <Message severity="error" v-if="genderErrormessage">{{ genderErrormessage }}</Message>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("title") }}</label>

                         <Dropdown
                              required
                              class="w-full"
                              v-model="record.title_id_fk"
                              :options="titles"
                              optionLabel="label"
                              optionValue="value"
                              @change="titleChange(record.title_id_fk)" />
                         <Message severity="error" v-if="titleErrormessage">
                              {{ ErrormetitleErrormessagessage }}
                         </Message>
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("nationality") }}</label>
                         <Dropdown
                              filter
                              class="w-full"
                              v-model="record.nationality_id_fk"
                              :options="nationalities"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("national_id_no") }}</label>
                         <InputText class="w-full" type="text" v-model="record.national_id_no" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("address") }}</label>
                         <InputText class="w-full" type="text" v-model="record.address" />
                    </div>
                    <!-- <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("code") }}</label>
                         <InputText class="w-full" required type="number" v-model="record.code" />
                    </div> -->
                    <!-- <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("lab_card") }}</label>
                         <InputText class="w-full" required type="number" v-model="record.lab_card" />
                    </div> -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("passport_no") }}</label>
                         <InputText class="w-full" type="number" v-model="record.passport_no" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("contract") }}</label>
                         <Dropdown
                              class="w-full"
                              v-model="record.contract_id_fk"
                              :options="contracts"
                              optionLabel="label"
                              optionValue="value" />
                    </div>
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("addfile") }}</label>
                         <InputText
                              class="w-full"
                              type="file"
                              @change="onFileChange($event)"
                              accept=".png ,.jpg ,.jpeg" />
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
     import { usePatientsStore } from "@/store/modules/patients";
     import { useContractsStore } from "@/store/modules/contracts";
     import { useNationalitiesStore } from "@/store/modules/nationalities";
     import { useTitlesStore } from "@/store/modules/titles";
     import { LoaderStore } from "@/store/modules/loader";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import { TimeScale } from "chart.js";
     export default {
          data() {
               return { isNameShow: false, age_unitErrormessage: "", genderErrormessage: "", titleErrormessage: "" };
          },
          computed: {
               ...mapWritableState(usePatientsStore, [
                    "record",
                    "responseData",
                    "selectedFile",
                    "dialog",
                    "AgeUnits",
                    "genders",
                    "searchRecords",
                    "searchNameTotalCount",
               ]),
               ...mapWritableState(useinvoicesStore, ["patient"]),
               ...mapWritableState(LoaderStore, ["hideLoading"]),
               ...mapWritableState(useNationalitiesStore, ["nationalities"]),
               ...mapWritableState(useTitlesStore, ["titles"]),
               ...mapWritableState(useContractsStore, ["contracts"]),
          },
          mounted() {
               this.GetAgeUnits();
               this.GetGenders();
               this.GetTitles();
               this.GetContracts();
               this.GetNationalities();
          },
          methods: {
               ...mapActions(usePatientsStore, [
                    "AddPatient",
                    "UpdatePatient",
                    "RemovePatient",
                    "GetAgeUnits",
                    "GetGenders",
                    "whatsapp",
                    "searchByname",
               ]),

               ...mapActions(useNationalitiesStore, ["GetNationalities"]),
               ...mapActions(useTitlesStore, ["GetTitles"]),
               ...mapActions(useContractsStore, ["GetContracts"]),
               onFileChange(event) {
                    this.selectedFile = event.target.files[0];
               },
               searchitemByname(v) {
                    if (v) {
                         this.searchByname(v);
                         this.isNameShow = true;
                    } else {
                         this.isNameShow = false;
                         // this.clearObjectValues(this.record);
                    }
               },
               changeTitle(value) {
                    if (value !== "" && value != null) {
                         this.genderErrormessage = "";
                    }
                    if (value == 1) {
                         this.record.title_id_fk = 1;
                    } else {
                         this.record.title_id_fk = 2;
                    }
               },
               create() {
                    if (this.record.age_unit_id_fk && this.record.gender_type_id_fk && this.record.title_id_fk) {
                         this.record.phone_number = this.record.phone;
                         this.record.gender_id_fk = this.record.gender_type_id_fk;
                         this.AddPatient().then(() => {
                              this.alertSuccess(this.t("alertSuccess"));
                              this.patient = this.responseData;

                              this.dialog = false;
                         });
                         this.age_unitErrormessage = "";
                         this.genderErrormessage = "";
                         this.titleErrormessage = "";
                    } else if (this.record.age_unit_id_fk == "" || this.record.age_unit_id_fk == null) {
                         this.age_unitErrormessage = this.t("errorMessage");
                    } else if (this.record.gender_type_id_fk == "" || this.record.gender_type_id_fk == null) {
                         this.genderErrormessage = this.t("errorMessage");
                    } else if (this.record.title_id_fk == "" || this.record.title_id_fk == null) {
                         this.titleErrormessage = this.t("errorMessage");
                    }
               },
               ageChange(v) {
                    if (v !== "" && v != null) {
                         this.age_unitErrormessage = "";
                    }
               },
               titleChange(v) {
                    if (v !== "" && v != null) {
                         this.titleErrormessage = "";
                    }
               },
               update() {
                    if (this.record.age_unit_id_fk && this.record.gender_type_id_fk && this.record.title_id_fk) {
                         this.record.phone_number = this.record.phone;
                         this.record.gender_id_fk = this.record.gender_type_id_fk;
                         this.UpdatePatient().then(() => {
                              this.patient = this.responseData;

                              this.dialog = false;
                              this.alertSuccess(this.t("alertSuccess"));
                         });
                         this.age_unitErrormessage = "";
                         this.genderErrormessage = "";
                         this.titleErrormessage = "";
                    } else if (this.record.age_unit_id_fk == "" || this.record.age_unit_id_fk == null) {
                         this.age_unitErrormessage = this.t("errorMessage");
                    } else if (this.record.gender_type_id_fk == "" || this.record.gender_type_id_fk == null) {
                         this.genderErrormessage = this.t("errorMessage");
                    } else if (this.record.title_id_fk == "" || this.record.title_id_fk == null) {
                         this.titleErrormessage = this.t("errorMessage");
                    }
               },
               close() {
                    this.dialog = false;
                    this.clearObjectValues(this.record);
                    this.Errormessage = "";
               },
          },
          watch: {
               patient: function (v) {
                    if (v) {
                         this.isNameShow = false;
                         this.record = v;
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
     .pi-spin {
          font-size: 1.5rem;
          position: absolute;
          top: 10px;
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
</style>
