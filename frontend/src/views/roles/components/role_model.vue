<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { storeToRefs } from "pinia";
import { useRolesstore } from "@/store/modules/roles";
import { t } from "@/utils/helper";
import { useToast } from "@/composables/useToast";

const toast = useToast();
const rolesStore = useRolesstore();
const { record, dialog, permissions, controllers } = storeToRefs(rolesStore);
const { AddRoles, UpdateRoles, GetPermissions } = rolesStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");
const isLoading = ref(false);
const selectedPermissions = ref([]);
const expandedControllers = ref({});

onMounted(() => {
  GetPermissions();
});

// Watch dialog to initialize permissions when editing
watch(dialog, (newValue) => {
  if (newValue) {
    if (record.value.id) {
      // permissions may be array of IDs (from index) or array of objects (from API)
      const perms = record.value.permissions || [];
      selectedPermissions.value = perms.map((p) => typeof p === "object" ? p.id : p);
      record.value.role = record.value.name || record.value.role;
    } else {
      selectedPermissions.value = [];
    }
    // Expand all controllers by default
    if (controllers.value) {
      controllers.value.forEach((c) => {
        expandedControllers.value[c.name] = true;
      });
    }
  }
});

const toggleController = (name) => {
  expandedControllers.value[name] = !expandedControllers.value[name];
};

const toggleSelectAll = () => {
  const allPermissionIds = controllers.value.flatMap((controller) =>
    controller.permissions.map((action) => action.id)
  );
  if (selectedPermissions.value.length === allPermissionIds.length) {
    selectedPermissions.value = [];
  } else {
    selectedPermissions.value = [...allPermissionIds];
  }
};

const isPermissionSelected = (id) => {
  return selectedPermissions.value.includes(id);
};

const togglePermission = (id) => {
  const index = selectedPermissions.value.indexOf(id);
  if (index !== -1) {
    selectedPermissions.value.splice(index, 1);
  } else {
    selectedPermissions.value.push(id);
  }
};

const submit = async () => {
  try {
    isLoading.value = true;
    await AddRoles({
      role: record.value.role,
      permissions: selectedPermissions.value,
    });
    toast.success(t("alertSuccess"));
    close();
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const update = async () => {
  try {
    isLoading.value = true;
    await UpdateRoles({
      role: record.value.role,
      role_id: record.value.id,
      permissions: selectedPermissions.value,
    });
    toast.success(t("alertSuccess"));
    close();
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const close = () => {
  dialog.value = false;
  Object.keys(record.value).forEach((key) => {
    record.value[key] = null;
  });
  selectedPermissions.value = [];
};

const allSelected = computed(() => {
  if (!controllers.value) return false;
  const allPermissionIds = controllers.value.flatMap((controller) =>
    controller.permissions.map((action) => action.id)
  );
  return selectedPermissions.value.length === allPermissionIds.length;
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="dialog"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="close"></div>

        <!-- Modal -->
        <div
          class="relative bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">
              {{ record?.id ? t("update") : t("add") }} {{ t("role_permissions") }}
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
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <!-- Role Name Input -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("name") }} <span class="text-red-500">*</span></label>
              <input
                v-model="record.role"
                type="text"
                required
                class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :placeholder="t('enter_role_name')"
              />
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
              <h4 class="text-lg font-semibold text-gray-700">{{ t("role_permissions") }}</h4>
              <div class="flex gap-3">
                <button
                  type="button"
                  @click="toggleSelectAll"
                  class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path v-if="allSelected" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  {{ allSelected ? t("deselect_all") : t("select_all") }}
                </button>
                <button
                  type="button"
                  @click="record.id ? update() : submit()"
                  :disabled="isLoading"
                  class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                >
                  <svg v-if="isLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  {{ t("save") }}
                </button>
              </div>
            </div>

            <!-- Permissions Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div
                v-for="controller in controllers"
                :key="controller.name"
                class="border border-gray-200 rounded-lg overflow-hidden"
              >
                <!-- Accordion Header -->
                <button
                  type="button"
                  @click="toggleController(controller.name)"
                  class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
                >
                  <span class="font-medium text-gray-700">{{ controller.name }}</span>
                  <svg
                    class="w-5 h-5 text-gray-500 transition-transform duration-200"
                    :class="{ 'rotate-180': expandedControllers[controller.name] }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <!-- Accordion Content -->
                <Transition name="accordion">
                  <div v-if="expandedControllers[controller.name]" class="px-4 py-3 space-y-2">
                    <label
                      v-for="permission in controller.permissions"
                      :key="permission.id"
                      class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        :checked="isPermissionSelected(permission.id)"
                        @change="togglePermission(permission.id)"
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                      />
                      <span class="text-sm text-gray-600">{{ permission.full_name }}</span>
                    </label>
                  </div>
                </Transition>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button
              type="button"
              @click="close"
              class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
            >
              {{ t("close") }}
            </button>
            <button
              type="button"
              @click="record.id ? update() : submit()"
              :disabled="isLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-50"
            >
              {{ record.id ? t("save") : t("add") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

