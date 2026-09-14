<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { t, formatNum } from "@/utils/helper";
import { $http } from "@/plugins/axios";

const emit = defineEmits(["saved"]);

const store = useSuperAdminStore();
const { subscriptionDialog, subscriptionRecord, plans } = storeToRefs(store);

const labOptions = ref([]);
const saving = ref(false);
const billingCycle = ref("monthly");

const isEdit = computed(() => !!subscriptionRecord.value.id);

const activePlans = computed(() => plans.value.filter((p) => !p.is_custom));

const loadLabs = async () => {
     try {
          const { data } = await $http.get("/super-admin/labs", { params: { per_page: 999 } });
          labOptions.value = (data.data || []).map((l) => ({ id: l.id, name: l.name, email: l.email }));
     } catch (e) {
          console.error(e);
     }
};

const onPlanSelect = (planId) => {
     const plan = plans.value.find((p) => p.id === Number(planId));
     if (!plan) return;
     subscriptionRecord.value.plan_id_fk = plan.id;
     subscriptionRecord.value.plan_name = plan.name_ar || plan.name;
     subscriptionRecord.value.max_users = plan.max_users;
     subscriptionRecord.value.max_invoices_per_month = plan.max_invoices_per_month;
     subscriptionRecord.value.currency = plan.currency || "IQD";
     subscriptionRecord.value.price =
          billingCycle.value === "yearly" ? plan.yearly_price : plan.monthly_price;
};

watch(billingCycle, (cycle) => {
     const plan = plans.value.find((p) => p.id === subscriptionRecord.value.plan_id_fk);
     if (plan) {
          subscriptionRecord.value.price = cycle === "yearly" ? plan.yearly_price : plan.monthly_price;
     }
});

const save = async () => {
     saving.value = true;
     try {
          if (isEdit.value) {
               await store.UpdateSubscription(subscriptionRecord.value);
          } else {
               await store.AddSubscription(subscriptionRecord.value);
          }
          subscriptionDialog.value = false;
          emit("saved");
     } catch (e) {
          console.error(e);
     } finally {
          saving.value = false;
     }
};

onMounted(() => {
     loadLabs();
     if (plans.value.length === 0) {
          store.GetPlans();
     }
});
</script>

<template>
     <UiModal v-model="subscriptionDialog" :title="isEdit ? t('edit_subscription') : t('add_subscription')" size="md">
          <form @submit.prevent="save" class="space-y-4">
               <!-- Lab -->
               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                         {{ t("select_lab") }} <span class="text-red-500">*</span>
                    </label>
                    <select
                         v-model="subscriptionRecord.lab_id_fk"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         required
                    >
                         <option :value="null" disabled>{{ t("select_lab") }}</option>
                         <option v-for="lab in labOptions" :key="lab.id" :value="lab.id">
                              {{ lab.name }} ({{ lab.email }})
                         </option>
                    </select>
               </div>

               <!-- Plan Selector -->
               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                         {{ t("select_plan") }} <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                         <button
                              v-for="plan in activePlans"
                              :key="plan.id"
                              type="button"
                              @click="onPlanSelect(plan.id)"
                              class="relative rounded-xl border-2 px-3 py-3 text-center transition-all duration-200 cursor-pointer"
                              :class="
                                   subscriptionRecord.plan_id_fk === plan.id
                                        ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200'
                                        : 'border-gray-200 hover:border-gray-300 bg-white'
                              "
                         >
                              <span
                                   v-if="plan.is_popular"
                                   class="absolute -top-2.5 start-1/2 -translate-x-1/2 bg-amber-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"
                              >
                                   {{ t("most_popular") }}
                              </span>
                              <div class="font-semibold text-sm text-gray-900">{{ plan.name_ar }}</div>
                              <div class="text-xs text-gray-500 mt-1">{{ plan.name }}</div>
                              <div class="text-sm font-bold text-blue-600 mt-1">
                                   {{ formatNum(plan.monthly_price) }}
                                   <span class="text-[10px] font-normal text-gray-400">IQD/{{ t("month") }}</span>
                              </div>
                              <div class="text-xs text-gray-400 mt-0.5">
                                   {{ plan.max_users ? plan.max_users + " " + t("users") : t("unlimited") }}
                              </div>
                         </button>
                    </div>
               </div>

               <!-- Billing Cycle -->
               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("billing_cycle") }}</label>
                    <div class="flex gap-2">
                         <button
                              type="button"
                              @click="billingCycle = 'monthly'"
                              class="flex-1 py-2 rounded-xl text-sm font-medium transition-colors"
                              :class="
                                   billingCycle === 'monthly'
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                              "
                         >
                              {{ t("monthly") }}
                         </button>
                         <button
                              type="button"
                              @click="billingCycle = 'yearly'"
                              class="flex-1 py-2 rounded-xl text-sm font-medium transition-colors"
                              :class="
                                   billingCycle === 'yearly'
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                              "
                         >
                              {{ t("yearly") }}
                         </button>
                    </div>
               </div>

               <!-- Plan Name (auto-filled, editable) -->
               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                         {{ t("plan_name") }} <span class="text-red-500">*</span>
                    </label>
                    <input
                         v-model="subscriptionRecord.plan_name"
                         type="text"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         required
                    />
               </div>

               <!-- Status -->
               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                         {{ t("status") }} <span class="text-red-500">*</span>
                    </label>
                    <select
                         v-model="subscriptionRecord.status"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         required
                    >
                         <option value="trial">{{ t("subscription_trial") }}</option>
                         <option value="active">{{ t("subscription_active") }}</option>
                         <option value="expired">{{ t("subscription_expired") }}</option>
                         <option value="suspended">{{ t("subscription_suspended") }}</option>
                    </select>
               </div>

               <!-- Dates -->
               <div class="grid grid-cols-2 gap-4">
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">
                              {{ t("start_date") }} <span class="text-red-500">*</span>
                         </label>
                         <input
                              v-model="subscriptionRecord.start_date"
                              type="date"
                              class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                              @click="$event.target.showPicker()"
                              required
                         />
                    </div>
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">
                              {{ t("end_date") }} <span class="text-red-500">*</span>
                         </label>
                         <input
                              v-model="subscriptionRecord.end_date"
                              type="date"
                              class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                              @click="$event.target.showPicker()"
                              required
                         />
                    </div>
               </div>

               <!-- Max Users / Max Invoices -->
               <div class="grid grid-cols-2 gap-4">
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("max_users") }}</label>
                         <input
                              v-model.number="subscriptionRecord.max_users"
                              type="number"
                              min="1"
                              class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         />
                    </div>
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("max_invoices_per_month") }}</label>
                         <input
                              v-model.number="subscriptionRecord.max_invoices_per_month"
                              type="number"
                              min="1"
                              class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         />
                    </div>
               </div>

               <!-- Price / Currency -->
               <div class="grid grid-cols-2 gap-4">
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("price") }}</label>
                         <input
                              v-model.number="subscriptionRecord.price"
                              type="number"
                              min="0"
                              class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         />
                    </div>
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("currency") }}</label>
                         <input
                              v-model="subscriptionRecord.currency"
                              type="text"
                              class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                         />
                    </div>
               </div>

               <!-- Notes -->
               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t("notes") }}</label>
                    <textarea
                         v-model="subscriptionRecord.notes"
                         rows="3"
                         class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm"
                    ></textarea>
               </div>

               <!-- Actions -->
               <div class="flex justify-end gap-3 pt-2">
                    <UiButton type="button" @click="subscriptionDialog = false" class="bg-gray-200 text-gray-700">
                         {{ t("cancel") }}
                    </UiButton>
                    <UiButton type="submit" :disabled="saving">
                         {{ saving ? t("loading") : t("save") }}
                    </UiButton>
               </div>
          </form>
     </UiModal>
</template>
