<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useinvoicesStore } from "@/store/modules/invoices";
import { usepaymentMethodstore } from "@/store/modules/payment-methods";
import { t, dateTimeFormat } from "@/utils/helper";
import { useToast } from "@/composables/useToast";
import { usePrint } from "@/composables/usePrint";

const toast = useToast();
const { printWithIframe, printStyles } = usePrint();
const justPaid = ref(false);
const invoicesStore = useinvoicesStore();
const pmStore = usepaymentMethodstore();
const { Patient_due, Patient_dueDialog } = storeToRefs(invoicesStore);
const { paymentMethods } = storeToRefs(pmStore);

const lang = computed(() => localStorage.getItem("locale") || "ar");
const inv = computed(() => Patient_due.value || {});
const paid = computed(() => inv.value.paid || 0);
const total = computed(() => inv.value.total || 0);
const due = computed(() => total.value - paid.value);
const payments = computed(() => inv.value.payment_details || []);
const paidPercent = computed(() => total.value <= 0 ? 0 : Math.min(Math.round((paid.value / total.value) * 100), 100));

const status = computed(() => {
     if (inv.value.payment_status) return inv.value.payment_status;
     if (total.value <= 0) return "unpaid";
     if (paid.value >= total.value) return "paid";
     if (paid.value > 0) return "partial";
     return "unpaid";
});

const statusConfig = computed(() => ({
     paid: { label: t("fully_paid") || "مدفوع بالكامل", color: "green", icon: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" },
     partial: { label: t("partially_paid") || "مدفوع جزئياً", color: "amber", icon: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" },
     unpaid: { label: t("unpaid") || "غير مدفوع", color: "red", icon: "M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" },
})[status.value] || { label: "—", color: "slate", icon: "" });

// Payment form
const showPayForm = ref(false);
const payAmount = ref(0);
const payMethodId = ref("");
const paying = ref(false);

const openPayForm = () => {
     payAmount.value = due.value;
     payMethodId.value = "";
     showPayForm.value = true;
     if (!paymentMethods.value?.length) pmStore.GetpaymentMethods();
};

const submitPayment = async () => {
     if (!payAmount.value || !payMethodId.value) return;
     paying.value = true;
     try {
          const data = await invoicesStore.addPayment(inv.value.id, payAmount.value, payMethodId.value);
          Patient_due.value.paid = data.total_paid;
          Patient_due.value.payment_status = data.payment_status;
          if (!Patient_due.value.payment_details) Patient_due.value.payment_details = [];
          Patient_due.value.payment_details.push(data.payment);
          showPayForm.value = false;
          justPaid.value = true;
          toast.success(t("alertSuccess"));
          invoicesStore.Getinvoices();
     } catch (error) {
          toast.error(error.response?.data?.message || t("error"));
     } finally {
          paying.value = false;
     }
};

const printThermal = async () => {
     await printWithIframe("thermalRecord", printStyles.thermalReceipt, "Thermal Receipt", 100, () => invoicesStore.GetinvoicesById(inv.value.id));
};

const printInvoice = async () => {
     await printWithIframe("printInvoice", printStyles.invoice, "Print Invoice", 100, () => invoicesStore.GetinvoicesById(inv.value.id));
};

const close = () => {
     Patient_due.value = [];
     Patient_dueDialog.value = false;
     showPayForm.value = false;
     justPaid.value = false;
};
</script>

<template>
     <UiModal v-model="Patient_dueDialog" :closable="true" size="lg">
          <template #header>
               <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="`bg-${statusConfig.color}-100`">
                         <svg class="w-5 h-5" :class="`text-${statusConfig.color}-600`" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                         </svg>
                    </div>
                    <div>
                         <h3 class="text-lg font-bold text-slate-800">{{ t("payment_details_title") || "تفاصيل الدفع" }}</h3>
                         <p class="text-xs text-slate-400">#{{ inv.barcode || inv.id }}</p>
                    </div>
               </div>
          </template>

          <div class="space-y-0" :dir="lang === 'ar' ? 'rtl' : 'ltr'">

               <!-- ==================== TOP SECTION: STATUS + AMOUNTS ==================== -->
               <div class="grid grid-cols-1 md:grid-cols-2 gap-0 border-b border-slate-100">
                    <!-- Left: Big Circle -->
                    <div class="flex flex-col items-center justify-center py-8 px-6">
                         <div class="relative w-32 h-32 mb-4">
                              <!-- Background circle -->
                              <svg class="w-32 h-32 -rotate-90" viewBox="0 0 120 120">
                                   <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="8" />
                                   <circle cx="60" cy="60" r="52" fill="none" :stroke="status === 'paid' ? '#22c55e' : status === 'partial' ? '#f59e0b' : '#ef4444'" stroke-width="8" stroke-linecap="round" :stroke-dasharray="326.73" :stroke-dashoffset="326.73 - (326.73 * paidPercent / 100)" class="transition-all duration-700" />
                              </svg>
                              <div class="absolute inset-0 flex flex-col items-center justify-center">
                                   <span class="text-2xl font-black text-slate-800">{{ paidPercent }}%</span>
                                   <span class="text-[10px] font-medium" :class="`text-${statusConfig.color}-600`">{{ statusConfig.label }}</span>
                              </div>
                         </div>
                    </div>

                    <!-- Right: Amount Cards -->
                    <div class="flex flex-col justify-center gap-3 py-6 px-6 bg-slate-50/50">
                         <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-slate-100">
                              <span class="text-xs font-medium text-slate-500">{{ t("Total") }}</span>
                              <span class="text-lg font-black text-slate-800">{{ total }}</span>
                         </div>
                         <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-green-100">
                              <span class="text-xs font-medium text-green-600">{{ t("paid") }}</span>
                              <span class="text-lg font-black text-green-600">{{ paid }}</span>
                         </div>
                         <div class="flex items-center justify-between p-3 rounded-xl" :class="due > 0 ? 'bg-red-50 border border-red-100' : 'bg-green-50 border border-green-100'">
                              <span class="text-xs font-medium" :class="due > 0 ? 'text-red-600' : 'text-green-600'">{{ t("Due") }}</span>
                              <span class="text-lg font-black" :class="due > 0 ? 'text-red-600' : 'text-green-600'">{{ due }}</span>
                         </div>
                    </div>
               </div>

               <!-- ==================== BREAKDOWN ==================== -->
               <div v-if="inv.discount" class="px-6 py-3 bg-slate-50/30 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-4 text-xs text-slate-500">
                         <span>{{ t("Subtotal") }}: <b class="text-slate-700">{{ inv.sub_total ?? 0 }}</b></span>
                         <span>{{ t("discount") }}: <b class="text-orange-600">-{{ inv.discount }}</b></span>
                    </div>
               </div>

               <!-- ==================== PAYMENT HISTORY ==================== -->
               <div class="px-6 py-5">
                    <div class="flex items-center justify-between mb-4">
                         <h4 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              {{ t("payment_history") || "سجل الدفعات" }}
                         </h4>
                         <span v-if="payments.length" class="px-2 py-0.5 text-xs font-bold rounded-full bg-slate-100 text-slate-600">{{ payments.length }}</span>
                    </div>

                    <!-- Payments List -->
                    <div v-if="payments.length" class="space-y-2">
                         <div v-for="(p, i) in payments" :key="p.id || i" class="flex items-center gap-4 p-3.5 bg-white rounded-xl border border-slate-100 hover:border-green-200 hover:shadow-sm transition-all">
                              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white text-xs font-bold shadow-sm shrink-0">
                                   {{ i + 1 }}
                              </div>
                              <div class="flex-1 min-w-0">
                                   <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-800">{{ p.payment_method || t("cash") || "نقدي" }}</p>
                                   </div>
                                   <p class="text-xs text-slate-400 mt-0.5">
                                        <svg class="w-3 h-3 inline-block -mt-0.5 me-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        {{ p.paid_at ? dateTimeFormat(p.paid_at) : '-' }}
                                   </p>
                              </div>
                              <div class="text-end shrink-0">
                                   <span class="text-base font-black text-green-600">{{ p.amount }}</span>
                              </div>
                         </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="py-8 text-center">
                         <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-slate-100 flex items-center justify-center">
                              <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                   <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                              </svg>
                         </div>
                         <p class="text-sm font-medium text-slate-400">{{ t("no_payments") || "لا توجد دفعات مسجلة" }}</p>
                         <p class="text-xs text-slate-300 mt-1">{{ t("add_first_payment") || "أضف أول دفعة باستخدام الزر أدناه" }}</p>
                    </div>
               </div>

               <!-- ==================== ADD PAYMENT FORM ==================== -->
               <div v-if="showPayForm && due > 0" class="px-6 pb-5">
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-5 border border-green-200 space-y-4">
                         <div class="flex items-center gap-2">
                              <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                   <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                              </div>
                              <h4 class="text-sm font-bold text-green-800">{{ t("add_payment") || "إضافة دفعة" }}</h4>
                         </div>
                         <div class="grid grid-cols-2 gap-3">
                              <div>
                                   <label class="block text-xs font-semibold text-green-700 mb-1.5">{{ t("amount") || "المبلغ" }}</label>
                                   <input type="number" v-model.number="payAmount" :max="due" min="1" class="w-full px-3.5 py-2.5 text-sm border border-green-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white" />
                              </div>
                              <div>
                                   <label class="block text-xs font-semibold text-green-700 mb-1.5">{{ t("payment_method") || "طريقة الدفع" }}</label>
                                   <select v-model="payMethodId" :dir="lang === 'ar' ? 'rtl' : 'ltr'" class="w-full px-3.5 py-2.5 text-sm border border-green-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                                        <option value="">{{ t("select") }}</option>
                                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                   </select>
                              </div>
                         </div>
                         <div class="flex gap-2">
                              <button @click="submitPayment" :disabled="paying || !payAmount || !payMethodId" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-green-600 rounded-xl hover:bg-green-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2 shadow-sm">
                                   <svg v-if="paying" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                   {{ t("confirm_payment") || "تأكيد الدفع" }}
                              </button>
                              <button @click="showPayForm = false" class="px-4 py-2.5 text-sm font-medium text-green-700 bg-green-100 rounded-xl hover:bg-green-200 transition-colors">
                                   {{ t("cancel") }}
                              </button>
                         </div>
                    </div>
               </div>

               <!-- ==================== PRINT BUTTONS (after payment) ==================== -->
               <div v-if="justPaid || (payments.length > 0 && due <= 0)" class="px-6 pb-2">
                    <div class="grid grid-cols-2 gap-3">
                         <button @click="printThermal" class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-xl hover:bg-purple-50 hover:border-purple-200 transition-all">
                              <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
                                   <svg class="w-4.5 h-4.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                   </svg>
                              </div>
                              <span class="text-sm font-medium text-slate-700">{{ t("thermal_recipt") || "الايصال الحراري" }}</span>
                         </button>
                         <button @click="printInvoice" class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-xl hover:bg-teal-50 hover:border-teal-200 transition-all">
                              <div class="w-9 h-9 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                                   <svg class="w-4.5 h-4.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                   </svg>
                              </div>
                              <span class="text-sm font-medium text-slate-700">{{ t("print_invoice") || "الفاتورة" }}</span>
                         </button>
                    </div>
               </div>

               <!-- ==================== FOOTER ==================== -->
               <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                    <button v-if="due > 0 && !showPayForm" @click="openPayForm" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-green-500 to-green-600 rounded-xl hover:from-green-600 hover:to-green-700 transition-all flex items-center gap-2 shadow-sm shadow-green-500/25">
                         <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                         {{ t("add_payment") || "إضافة دفعة" }}
                    </button>
                    <div v-else-if="due <= 0" class="flex items-center gap-2 text-green-600 text-sm font-semibold">
                         <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                         {{ t("fully_paid") || "مدفوع بالكامل" }}
                    </div>
                    <div v-else></div>
                    <button @click="close" class="px-5 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                         {{ t("close") }}
                    </button>
               </div>
          </div>
     </UiModal>
</template>
