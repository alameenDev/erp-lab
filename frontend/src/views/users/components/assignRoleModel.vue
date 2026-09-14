<script setup>
import { computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useRolesstore } from "@/store/modules/roles";
import { useUsersStore } from "@/store/modules/users";
import { t } from "@/utils/helper";
import { useToast } from "@/composables/useToast";

const toast = useToast();
const rolesStore = useRolesstore();
const usersStore = useUsersStore();

const { record, assignDialog } = storeToRefs(usersStore);
const { GetUser } = usersStore;
const { role, GetRoles, AssignRoleToUser } = rolesStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

onMounted(() => {
  GetRoles();
});

const submit = async () => {
  try {
    await AssignRoleToUser({
      user_id: record.value.id,
      role_id: record.value.role_id,
    });
    toast.success(t("alertSuccess"));
    assignDialog.value = false;
    clearRecord();
    GetUser();
  } catch (error) {
    console.error(error);
  }
};

const close = () => {
  assignDialog.value = false;
  clearRecord();
};

const clearRecord = () => {
  Object.keys(record.value).forEach((key) => {
    record.value[key] = null;
  });
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="assignDialog"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="close"></div>

        <!-- Modal -->
        <div
          class="relative bg-white rounded-xl shadow-xl w-full max-w-md"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">
              {{ t("assign_role_user") }}
            </h3>
            <button
              @click="close"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <form @submit.prevent="submit" class="p-6">
            <!-- User Info -->
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-500">{{ t("name") }}</p>
              <p class="font-medium text-gray-800">{{ record?.name }}</p>
            </div>

            <!-- Role Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ t("role_permissions") }} <span class="text-red-500">*</span>
              </label>
              <select
                v-model="record.role_id"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="" disabled>{{ t("select") }}</option>
                <option v-for="r in role()" :key="r.value" :value="r.value">
                  {{ r.label }}
                </option>
              </select>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
              <button
                type="button"
                @click="close"
                class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
              >
                {{ t("close") }}
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
              >
                {{ t("save") }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

