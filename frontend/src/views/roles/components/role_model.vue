<template>
     <Dialog v-model:visible="dialog" modal :header="record?.id ? t('update') : t('add')" style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <!-- <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
            <div class="grid mt-1">
                 <div class="col-8 pb-0">
                      <label class="block text-md mb-2">{{ t("name") }}</label>
                      <InputText class="w-full" required type="text" v-model="record.role" />
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
       </form> -->

          <Card>
               <template #title>
                    <div class="col-8 my-2 w-full">
                         <label class="block text-sm mb-2">{{ t("name") }}</label>
                         <InputText class="w-full" required type="text" v-model="record.role" />
                    </div>
                    <div class="!w-full flex justify-around items-center ">
                         <div>
                              <h2>
                                   <span class="text-lg font-semibold text-gray-700">
                                        {{ t('role_permissions') }}
                                   </span>
                              </h2>
                         </div>
                         <div class="flex flex-col gap-2">
                              <Button :loading="isLoading" label="Save" icon="pi pi-save"
                                   class="bg-primary text-white rounded-none"
                                   @click="record.id ? update() : submit()" />
                              <Button label="Select All" icon="pi pi-check-square" outlined class="rounded-none"
                                   @click="toggleSelectAll" />
                         </div>
                    </div>
               </template>

               <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-2">
                         <div v-for="controller in controllers" :key="controller" class="w-full">
                              <Accordion :activeIndex="0">
                                   <AccordionTab :header="controller.name">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-gray-600">
                                             <div v-for="permission in controller.permissions" :key="permission.id"
                                                  class="flex items-center gap-2 text-gray-700">
                                                  <Checkbox v-model="selectedPermissions"
                                                       :inputId="'perm_' + permission.id" :value="permission.id" />
                                                  <label :for="'perm_' + permission.id">{{ permission.full_name
                                                  }}</label>
                                             </div>
                                        </div>
                                   </AccordionTab>
                              </Accordion>
                         </div>
                    </div>
               </template>
          </Card>
     </Dialog>
</template>

<script>
import { mapActions, mapWritableState } from "pinia";
import { useRolesstore } from "@/store/modules/roles";
export default {
     data() {
          return {
               isLoading: false,
               selectedPermissions: [],
               selctedItemID: [],
          }
     },
     computed: {
          ...mapWritableState(useRolesstore, ["record", "dialog", "permissions", "controllers"]),
     },

     methods: {
          ...mapActions(useRolesstore, ["AddRoles", "UpdateRoles", "GetPermissions"]),
          changeSelection(id) {
               const index = this.selectedPermissions.indexOf(id)
               if (index !== -1)
                    this.selectedPermissions.splice(index, 1)
               else
                    this.selectedPermissions.push(id)
          },

          toggleSelectAll() {
               const allPermissionIds = this.controllers.flatMap(controller =>
                    controller.permissions.map(action => action.id)
               )
               if (this.selectedPermissions.length === allPermissionIds.length) {
                    this.selectedPermissions = []
               } else {
                    this.selectedPermissions = allPermissionIds
               }
          },
          async submit() {
               try {
                    this.isLoading = true
                    this.AddRoles({
                         role: this.record.role,
                         permissions: this.selectedPermissions,
                    })
                    this.clearObjectValues(this.record);
                    this.dialog = false;
               } catch (error) {
                    console.error(error)
               } finally {
                    this.isLoading = false
               }
          },
          // create() {
          //      this.AddRoles().then(() => {
          //           this.alertSuccess(this.t("alertSuccess"));
          //           this.clearObjectValues(this.record);
          //           this.dialog = false;
          //      });
          // },

          update() {

               this.UpdateRoles({
                    role: this.record.role,
                    role_id: this.record.id,
                    permissions: this.selectedPermissions,
               }).then(() => {
                    this.dialog = false;
                    this.alertSuccess(this.t("alertSuccess"));
                    this.clearObjectValues(this.record);
               });
          },
          close() {
               this.dialog = false;
               this.clearObjectValues(this.record);
               this.selectedPermissions = []
               this.selctedItemID = []

          },

     },
     watch: {
          dialog: {
               handler(newValue) {
                    if (this.record.id) {
                         this.selectedPermissions = this.record?.permissions?.map(permission => permission.id)
                         this.selctedItemID = this.record?.permissions?.map(permission => permission.id)
                         this.record.role = this.record?.name
                    }
                    else {
                         this.selectedPermissions = []
                         this.selctedItemID = []
                    }
               },
               immediate: true,
          },
     },
     mounted() {
          this.GetPermissions();
     },
};
</script>
