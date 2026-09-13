<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-2">
                    <!-- name -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.name" maxlength="255" />
                    </div>
                    <!-- shortcut -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("Shortcut") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.shortcut" maxlength="255" />
                    </div>

                    <!-- tests_id_fk -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("tests") }}</label>
                         <MultiSelect
                              v-model="record.tests"
                              :options="TestLists()"
                              optionLabel="label"
                              optionValue="value"  @scroll="onScroll"
                              filter
                              class="w-full" />
                    </div>

                    <!-- cultures -->
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("cultures") }}</label>
                         <MultiSelect
                              v-model="record.cultures"
                              :options="cultureLists()"
                              optionLabel="label"
                              optionValue="value"
                              filter
                              class="w-full" />
                    </div>
                    <div class="col-12">
                         <div class="grid">
                              <!-- is_constant_price -->

                              <div class="col-6 pb-0" style="align-self: center">
                                   <div class="mt-3">
                                        <Checkbox v-model="record.is_constant_price" inputId="ingredient2" binary />
                                        <label for="ingredient2" class="ml-2 mr-2">{{ t("is_constant_price") }}</label>
                                   </div>
                              </div>
                              <!--price -->
                              <div class="col-6 pb-0" v-if="record.is_constant_price">
                                   <label class="block text-md mb-2">{{ t("Original_Price") }}</label>
                                   <InputNumber required class="w-full" type="number" v-model="record.price" min="0" />
                              </div>
                         </div>
                    </div>
               </div>

               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button
                         size="small"
                         type="submit"
                         :label="record.id ? t('save') : t('add')"
                         severity="success"></Button>
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapGetters, mapWritableState } from "pinia";
     import { usePackagesStore } from "@/store/modules/packages";
     import { usetestsStore } from "@/store/modules/tests";
     import { useculturesStore } from "@/store/modules/cultures";

     export default {
          computed: {
               ...mapWritableState(usePackagesStore, ["record", "dialog"]),
               ...mapGetters(usetestsStore, ["TestLists",'pagination']),
               ...mapGetters(useculturesStore, ["cultureLists"]),
          },
          mounted() {
               this.GetTests();
               this.Getcultures();
          },
          methods: {
               ...mapActions(usePackagesStore, ["Addpackage", "Updatepackage"]),
               ...mapActions(usetestsStore, ["GetTests"]),
               ...mapActions(useculturesStore, ["Getcultures"]),
   onScroll(event) {
                    const bottom = event.target.scrollHeight - event.target.scrollTop === event.target.clientHeight;
                    if (bottom && this.pagination.current_page < this.pagination.last_page) {
                         this.pagination.current_page++;
                         this.GetTests();
                    }
               },
               create() {
                    this.record.is_test = this.record.is_test == true ? true : false;
                    this.record.is_constant_price = this.record.is_constant_price == true ? true : false;
                    this.Addpackage().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.dialog = false;
                    });
               },

               update() {
                    this.record.is_test = this.record.is_test == true ? true : false;
                    this.record.is_constant_price = this.record.is_constant_price == true ? true : false;
                    this.Updatepackage().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.dialog = false;
                    });
               },
               close() {
                    this.dialog = false;
                    this.clearObjectValues(this.record);
               },
          },
          watch: {},
     };
</script>
<style lang="scss">
     .add {
          background: #f9fafb;
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 10px;
          padding: 0 6px;
     }
     label {
          font-weight: 500;
     }
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
</style>
