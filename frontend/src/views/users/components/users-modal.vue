<script setup>
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useUsersStore } from "@/store/modules/users";
import { priceListStore } from "@/store/modules/priceList";
import { t } from "@/utils/helper";
import { useToast } from "@/composables/useToast";

const toast = useToast();
const usersStore = useUsersStore();
const priceStore = priceListStore();

const { selectedFile, signature, sample_collectors, record, dialog, UserRoles, codeDialog } = storeToRefs(usersStore);
const { AddUser, UpdateUser } = usersStore;
const { GetpriceList, price_list } = priceStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");
const showPassword = ref(false);

onMounted(() => {
  GetpriceList();
});

const create = async () => {
  try {
    await AddUser();
    toast.success(t("alertSuccess"));
    dialog.value = false;
    clearRecord();
  } catch (error) {
    console.error(error);
  }
};

const update = async () => {
  try {
    await UpdateUser();
    toast.success(t("alertSuccess"));
    dialog.value = false;
    clearRecord();
  } catch (error) {
    console.error(error);
  }
};

const close = () => {
  dialog.value = false;
  clearRecord();
};

const clearRecord = () => {
  Object.keys(record.value).forEach((key) => {
    record.value[key] = null;
  });
  // Clear file selections to prevent stale data
  selectedFile.value = "";
  signature.value = "";
};

const onFileChange = (type, event) => {
  if (type === "Signature") {
    signature.value = event.target.files[0];
  } else {
    selectedFile.value = event.target.files[0];
  }
};

const submit = () => {
  if (record.value.id) {
    update();
  } else {
    create();
  }
};
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
          class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">
              {{ record?.id ? t("update") : t("add") }}
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
          <form @submit.prevent="submit" class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("name") }} <span class="text-red-500">*</span></label>
                <input
                  v-model="record.name"
                  type="text"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("email") }} <span class="text-red-500">*</span></label>
                <input
                  v-model="record.email"
                  type="email"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <!-- Password -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("password") }} <span v-if="!record.id" class="text-red-500">*</span></label>
                <div class="relative">
                  <input
                    v-model="record.password"
                    :type="showPassword ? 'text' : 'password'"
                    :required="!record.id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  >
                    <svg v-if="showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Phone -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("phone_number") }}</label>
                <input
                  v-model="record.phone"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <!-- Address -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("address") }}</label>
                <input
                  v-model="record.address"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>

              <!-- User Image -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("Userimage") }}</label>
                <input
                  type="file"
                  @change="onFileChange('image', $event)"
                  accept=".png,.jpg,.jpeg"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
              </div>

              <!-- Signature -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("Signature") }}</label>
                <input
                  type="file"
                  @change="onFileChange('Signature', $event)"
                  accept=".png,.jpg,.jpeg"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
              </div>

              <!-- User Role -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("userRole") }} <span class="text-red-500">*</span></label>
                <select
                  v-model="record.role_id"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="" disabled>{{ t("select") }}</option>
                  <option v-for="role in UserRoles.filter(r => ![2, 3, 4].includes(r.value))" :key="role.value" :value="role.value">
                    {{ role.label }}
                  </option>
                </select>
              </div>

              <!-- Sample Collector (for Branch Lab - role_id 4) -->
              <div v-if="record.role_id == 4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("sample_collector") }}</label>
                <select
                  v-model="record.sample_collector_id"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="" disabled>{{ t("select") }}</option>
                  <option v-for="collector in sample_collectors" :key="collector.value" :value="collector.value">
                    {{ collector.label }}
                  </option>
                </select>
              </div>

              <!-- Price List (for Branch Lab - role_id 4) -->
              <div v-if="record.role_id == 4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("price_list") }} <span class="text-red-500">*</span></label>
                <select
                  v-model="record.price_list_id"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="" disabled>{{ t("select") }}</option>
                  <option v-for="item in price_list()" :key="item.value" :value="item.value">
                    {{ item.label }}
                  </option>
                </select>
              </div>

              <!-- Commission (for Branch Lab or Sample Collector - role_id 4 or 6) -->
              <div v-if="record.role_id == 4 || record.role_id == 6">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("commission") }}</label>
                <input
                  v-model="record.discount_percentage"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
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
                {{ record.id ? t("save") : t("add") }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

