<template>
     <Dialog v-model:visible="assignDialog" modal :header="t('assign_role_user')" style="width: 50rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="submit()" class="border-top-1 border-bluegray-100">
               <div class="grid mt-1">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("role_permissions") }}</label>
                         <Dropdown required class="w-full" v-model="record.role_id" :options="role()" optionLabel="label"
                              optionValue="value" />
                    </div>
               </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
                    <Button size="small" type="submit" :label="t('save')"
                         severity="success"></Button>
               </div>
          </form>
     </Dialog>
</template>

<script>
import { mapActions, mapWritableState, mapGetters } from "pinia";
import { useRolesstore } from "@/store/modules/roles";
import { useUsersStore } from "@/store/modules/users";

export default {
     data() {
          return {};
     },
     computed: {
          ...mapWritableState(useUsersStore, [
               "record",
               "assignDialog",
          ]),
          ...mapGetters(useRolesstore, ["role"]),
     },
     mounted() {
          this.GetRoles();
     },
     methods: {
          ...mapActions(useUsersStore, ["AddUser", "ResendCode", "GetUser"]),
          ...mapActions(useRolesstore, ["GetRoles", "AssignRoleToUser"]),
          submit() {
               this.AssignRoleToUser({
                    user_id: this.record.id,
                    role_id: this.record.role_id,
               }).then(() => {
                    this.assignDialog = false;
                    this.alertSuccess(this.t("alertSuccess"));
                    this.clearObjectValues(this.record);
                    this.GetUser()
               });
          },
          close() {
               this.assignDialog = false;
               this.clearObjectValues(this.record);
          },
     },
};
</script>
