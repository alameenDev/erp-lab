<script setup>
import { $http } from "@/plugins/axios";
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

const portalAccount = ref(false);
const password = ref('');
const passwordConfirmation = ref('');
const saving = ref(false);
const formError = ref('');
watch(dialog, open => { password.value = ''; passwordConfirmation.value = ''; portalAccount.value = Boolean(open && !record.value.id && Number(record.value.role_id) === 5); formError.value = ''; });
watch(() => record.value.role_id, role => {
  if (!record.value.id && Number(role) === 5) portalAccount.value = true;
});
const isShow = ref(false);
const selectedRecord = ref("");
const showPassword = ref(false);

onMounted(() => {
  GetpriceList();
});

const handleSubmit = async () => {
  saving.value = true; formError.value = '';
  try {
    record.value.commission = record.value.commission || 0;
    if (portalAccount.value && !record.value.id) {
      await $http.post('/referrals/portal-account', { name: record.value.name, email: record.value.email,
        phone_number: record.value.phone_number, address: record.value.address,
        role_id: Number(record.value.role_id), commission: record.value.commission,
        price_list_id_fk: record.value.price_list_id_fk, password: password.value, password_confirmation: passwordConfirmation.value });
      await referralsStore.GetRecords();
    } else if (record.value.id) {
      if (Number(record.value.role_id) === 5 && portalAccount.value) {
        await $http.post(`/referrals/${record.value.id}/doctor-portal`, {
          email: record.value.email, password: password.value, password_confirmation: passwordConfirmation.value,
        });
      }
      await UpdateReferral();
    }
    else await AddReferral();
    alertSuccess(t("alertSuccess")); clearObjectValues(record.value); dialog.value = false;
    password.value = ''; passwordConfirmation.value = '';
  } catch (e) { formError.value = Object.values(e?.response?.data?.errors || {}).flat().join(' / ') || e?.response?.data?.message || 'تعذر الحفظ'; }
  finally { saving.value = false; }
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
              <label v-if="!record.id && Number(record.role_id) !== 5" class="block bg-teal-50 p-3 rounded-lg mb-4 text-sm"><input type="checkbox" v-model="portalAccount" /> إنشاء حساب دخول لبوابة الإحالة فقط (بدون صلاحيات الموظفين)</label>
              <p v-if="!record.id && Number(record.role_id) === 5" class="rounded-lg bg-blue-50 p-3 text-sm text-blue-800 mb-4">سيُنشأ حساب بوابة الطبيب تلقائياً. يستطيع الطبيب عرض نتائج المرضى المحالين باسمه فقط.</p>
              <label v-if="record.id && Number(record.role_id) === 5 && !record.lab_portal_profile" class="block bg-blue-50 p-3 rounded-lg mb-4 text-sm"><input type="checkbox" v-model="portalAccount" /> {{ record.portal_enabled ? 'تغيير بيانات دخول بوابة الطبيب' : 'تفعيل حساب بوابة الطبيب' }}</label>
              <p v-if="portalAccount" class="text-sm mb-3">البريد هو اسم الدخول. حدّد كلمة مرور لا تقل عن 12 حرفاً وشاركها مع الجهة بشكل خاص.<span v-if="Number(record.role_id) === 2"> قائمة الأسعار مطلوبة.</span></p>
              <p v-if="formError" role="alert" class="text-red-700 mb-3">{{ formError }}</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Name with Search -->
                <div class="relative">
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("name") }} <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <input
                      v-model="record.name"
                      type="text"
                      required
                      @input="!portalAccount && searchitem(record.name)"
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
                    :required="portalAccount"
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
                <div v-if="!portalAccount || !record.id">
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
                <div v-if="Number(record.role_id) === 2">
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

              <div v-if="portalAccount" class="grid md:grid-cols-2 gap-4 mt-4">
                <label class="text-sm">كلمة المرور<input v-model="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required class="block border rounded p-2 w-full" /></label>
                <label class="text-sm">تأكيد كلمة المرور<input v-model="passwordConfirmation" type="password" autocomplete="new-password" required class="block border rounded p-2 w-full" /></label>
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
                  type="submit" :disabled="saving"
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
