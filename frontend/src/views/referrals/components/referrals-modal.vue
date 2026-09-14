<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useReferralsStore } from "@/store/modules/referrals";
import { priceListStore } from "@/store/modules/priceList";
import { LoaderStore } from "@/store/modules/loader";
import { ReverralUserRole } from "@/enums";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const referralsStore = useReferralsStore();
const priceStore = priceListStore();
const loaderStore = LoaderStore();

const { record, dialog, searchRecords } = storeToRefs(referralsStore);
const { AddReferral, UpdateReferral, search } = referralsStore;
const { price_list } = priceStore;
const { GetpriceList } = priceStore;
const { hideLoading } = storeToRefs(loaderStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const UserRoles = ReverralUserRole;

const isShow = ref(false);
const selectedRecord = ref("");
const showPassword = ref(false);

onMounted(() => {
  GetpriceList();
});

const handleSubmit = () => {
  record.value.commission = record.value.commission || 0;
  if (record.value.id) {
    UpdateReferral().then(() => {
      alertSuccess(t("alertSuccess"));
      clearObjectValues(record.value);
      dialog.value = false;
    });
  } else {
    AddReferral().then(() => {
      alertSuccess(t("alertSuccess"));
      dialog.value = false;
    });
  }
};

const close = () => {
  dialog.value = false;
  clearObjectValues(record.value);
};

const searchitem = (v) => {
  if (v) {
    search(v);
    isShow.value = true;
  } else {
    isShow.value = false;
    clearObjectValues(record.value);
  }
};

const selectRecord = (rec) => {
  isShow.value = false;
  Object.assign(record.value, rec);
  record.value.id = null;
  record.value.role_id = UserRoles?.asList().find((x) => x.label.toLowerCase() === rec.role?.toLowerCase())?.value ?? "";
};

watch(selectedRecord, (v) => {
  if (v) {
    selectRecord(v);
  }
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">
              {{ record?.id ? t("update") : t("add") }}
            </h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <form @submit.prevent="handleSubmit" @click="isShow = false">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Name with Search -->
                <div class="relative">
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("name") }} <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <input
                      v-model="record.name"
                      type="text"
                      required
                      @input="searchitem(record.name)"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <svg v-show="hideLoading" class="absolute top-2.5 w-5 h-5 text-teal-600 animate-spin" :class="lang === 'ar' ? 'left-2' : 'right-2'" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                  </div>
                  <!-- Search Results -->
                  <div v-show="isShow && searchRecords?.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    <div
                      v-for="rec in searchRecords"
                      :key="rec.id"
                      @click="selectRecord(rec)"
                      class="px-4 py-2 cursor-pointer hover:bg-gray-50 border-b border-gray-100 last:border-0"
                    >
                      {{ rec.name }}
                    </div>
                  </div>
                </div>

                <!-- Email -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("email") }}</label>
                  <input
                    v-model="record.email"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Phone Number -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("phone_number") }}</label>
                  <input
                    v-model="record.phone_number"
                    type="tel"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Commission -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("commission") }}</label>
                  <input
                    v-model.number="record.commission"
                    type="number"
                    min="0"
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

                <!-- User Role -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("userRole") }} <span class="text-red-500">*</span></label>
                  <select
                    v-model="record.role_id"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="" disabled>{{ t("select") }}</option>
                    <option v-for="role in UserRoles.asList()" :key="role.value" :value="role.value">
                      {{ role.label }}
                    </option>
                  </select>
                </div>

                <!-- Price List (for Lab role) -->
                <div v-if="record.role_id === 2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("priceList") }} <span class="text-red-500">*</span></label>
                  <select
                    v-model="record.price_list_id_fk"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="" disabled>{{ t("select") }}</option>
                    <option v-for="pl in price_list()" :key="pl.value" :value="pl.value">
                      {{ pl.label }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Footer -->
              <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                <button
                  type="button"
                  @click="close"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
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
      </div>
    </Transition>
  </Teleport>
</template>

