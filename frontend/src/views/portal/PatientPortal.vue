<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { $http } from "@/plugins/axios";

const route = useRoute();
const token = route.params.token;

const loading = ref(true);
const loadError = ref("");

const requiresOtp = ref(false);
const otpCode = ref("");
const otpSending = ref(false);
const otpVerifying = ref(false);
const otpMessage = ref("");
const otpSent = ref(false);

const patient = ref(null);
const reports = ref([]);
const loyalty = ref(null);

const reportFilter = ref('all');
const filteredReports = computed(() => reports.value.filter(r => reportFilter.value === 'all' || r.status === reportFilter.value));
const money = value => new Intl.NumberFormat('ar-IQ').format(Number(value) || 0);
const activeTab = ref("reports"); // reports | loyalty

const redeemingKey = ref(null);
const redeemMessage = ref("");
const redeemError = ref("");

const load = async () => {
  loading.value = true;
  loadError.value = "";
  try {
    const { data } = await $http.get(`/portal/${token}`);
    if (data.requires_otp) {
      requiresOtp.value = true;
      patient.value = { name: data.patient_name };
    } else {
      requiresOtp.value = false;
      patient.value = data.patient;
      reports.value = data.reports || [];
      loyalty.value = data.loyalty;
    }
  } catch (e) {
    loadError.value = e?.response?.data?.message || "تعذر فتح الرابط، تأكد أنه صحيح أو غير منتهي الصلاحية";
  } finally {
    loading.value = false;
  }
};

const requestOtp = async () => {
  otpSending.value = true;
  otpMessage.value = "";
  try {
    const { data } = await $http.post(`/portal/${token}/otp/request`);
    otpMessage.value = data?.message || "تم إرسال رمز التحقق عبر واتساب";
    otpSent.value = true;
  } catch (e) {
    otpMessage.value = e?.response?.data?.message || "تعذر إرسال رمز التحقق";
  } finally {
    otpSending.value = false;
  }
};

const verifyOtp = async () => {
  if (!otpCode.value) return;
  otpVerifying.value = true;
  otpMessage.value = "";
  try {
    await $http.post(`/portal/${token}/otp/verify`, { code: otpCode.value });
    await load();
  } catch (e) {
    otpMessage.value = e?.response?.data?.message || "رمز التحقق غير صحيح";
  } finally {
    otpVerifying.value = false;
  }
};

const redeem = async (item) => {
  redeemingKey.value = item.key;
  redeemMessage.value = "";
  redeemError.value = "";
  try {
    const { data } = await $http.post(`/portal/${token}/redeem`, { catalog_key: item.key });
    redeemMessage.value = data?.message || "تم الاستبدال بنجاح";
    if (loyalty.value) loyalty.value.balance = data?.balance ?? loyalty.value.balance;
  } catch (e) {
    redeemError.value = e?.response?.data?.message || "تعذر إتمام الاستبدال";
  } finally {
    redeemingKey.value = null;
  }
};

const tierProgressPct = computed(() => {
  if (!loyalty.value?.next_tier) return 100;
  const cur = loyalty.value.tier?.min_yearly_points || 0;
  const next = loyalty.value.next_tier.min_yearly_points || 1;
  const have = loyalty.value.yearly_points || 0;
  const span = Math.max(1, next - cur);
  return Math.max(0, Math.min(100, Math.round(((have - cur) / span) * 100)));
});

const pointsToNextTier = computed(() => {
  if (!loyalty.value?.next_tier) return 0;
  return Math.max(0, (loyalty.value.next_tier.min_yearly_points || 0) - (loyalty.value.yearly_points || 0));
});

const statusLabel = (status) => (status === "ready" ? "جاهز" : "قيد الإجراء");
const statusClass = (status) => (status === "ready" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700");

const formatDate = (d) => {
  if (!d) return "";
  try {
    return new Date(d).toLocaleDateString("ar-IQ", { year: "numeric", month: "short", day: "numeric" });
  } catch (e) {
    return d;
  }
};

onMounted(load);
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-6 px-4" dir="rtl">
    <div class="max-w-2xl mx-auto">
      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-24 text-gray-500">
        <div class="w-10 h-10 border-4 border-teal-600 border-t-transparent rounded-full animate-spin mb-4"></div>
        جاري تحميل بياناتك...
      </div>

      <!-- Invalid / expired link -->
      <div v-else-if="loadError" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
        <div class="text-4xl mb-3">⚠️</div>
        <div class="text-lg font-bold text-gray-800 mb-1">تعذّر فتح الرابط</div>
        <div class="text-gray-500">{{ loadError }}</div>
      </div>

      <!-- OTP verification -->
      <div v-else-if="requiresOtp" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <div class="text-center mb-6">
          <div class="text-4xl mb-3">🔒</div>
          <div class="text-lg font-bold text-gray-800">مرحباً {{ patient?.name }}</div>
          <div class="text-gray-500 text-sm mt-1">لحماية بياناتك الطبية، يرجى التحقق من رقم هاتفك</div>
        </div>

        <button
          v-if="!otpSent"
          @click="requestOtp"
          :disabled="otpSending"
          class="w-full bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white font-bold py-3 rounded-xl transition"
        >
          {{ otpSending ? "جاري الإرسال..." : "إرسال رمز التحقق عبر واتساب" }}
        </button>

        <div v-else class="space-y-3">
          <input
            v-model="otpCode"
            type="text"
            inputmode="numeric"
            maxlength="6"
            placeholder="أدخل الرمز المكوّن من 6 أرقام"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-center text-lg tracking-widest focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
          <button
            @click="verifyOtp"
            :disabled="otpVerifying || otpCode.length < 4"
            class="w-full bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white font-bold py-3 rounded-xl transition"
          >
            {{ otpVerifying ? "جاري التحقق..." : "تأكيد الرمز" }}
          </button>
          <button @click="requestOtp" :disabled="otpSending" class="w-full text-teal-700 text-sm py-2">
            إعادة إرسال الرمز
          </button>
        </div>

        <div v-if="otpMessage" class="text-center text-sm mt-4 text-gray-600">{{ otpMessage }}</div>
      </div>

      <!-- Dashboard -->
      <div v-else class="space-y-4">
        <!-- Patient card -->
        <div class="bg-gradient-to-br from-teal-600 to-teal-800 rounded-2xl shadow-sm p-6 text-white">
          <div class="text-sm opacity-80 mb-1">مرحباً بك</div>
          <div class="text-2xl font-bold mb-3">{{ patient?.name }}</div>
          <div class="flex items-center gap-4 text-sm opacity-90">
            <span v-if="patient?.code">رقم الملف: {{ patient.code }}</span>
          </div>
          <div v-if="loyalty" class="mt-4 pt-4 border-t border-white/20 flex items-center justify-between">
            <div>
              <div class="text-xs opacity-80">رصيد النقاط</div>
              <div class="text-xl font-bold">{{ loyalty.balance }} نقطة</div>
            </div>
            <div class="bg-white/15 rounded-full px-4 py-1.5 text-sm font-semibold">
              {{ loyalty.tier?.label_ar }}
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 bg-white rounded-xl p-1 shadow-sm border border-gray-100">
          <button
            @click="activeTab = 'reports'"
            :class="[
              'flex-1 py-2.5 rounded-lg text-sm font-bold transition',
              activeTab === 'reports' ? 'bg-teal-600 text-white' : 'text-gray-500',
            ]"
          >
            الفواتير والتحاليل
          </button>
          <button
            v-if="loyalty"
            @click="activeTab = 'loyalty'"
            :class="[
              'flex-1 py-2.5 rounded-lg text-sm font-bold transition',
              activeTab === 'loyalty' ? 'bg-teal-600 text-white' : 'text-gray-500',
            ]"
          >
            برنامج الولاء
          </button>
        </div>

        <!-- Reports tab -->
        <div v-if="activeTab === 'reports'" class="space-y-3">
          <div v-if="!reports.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center text-gray-400">
            لا توجد فحوصات مسجلة حتى الآن
          </div>
          <div class="flex flex-wrap gap-2" aria-label="تصفية الفواتير">
            <button v-for="filter in [{key:'all',label:'الكل'}, {key:'pending',label:'قيد الإجراء'}, {key:'ready',label:'جاهزة'}]" :key="filter.key" @click="reportFilter = filter.key" :aria-pressed="reportFilter === filter.key" :class="['px-4 py-2 rounded-full text-sm border', reportFilter === filter.key ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-600 border-slate-200']">{{ filter.label }} ({{ reports.filter(r => filter.key === 'all' || r.status === filter.key).length }})</button>
          </div>
          <p v-if="reports.length && !filteredReports.length" class="text-sm text-slate-500 p-4">لا توجد فواتير ضمن هذه الحالة.</p>
          <article v-for="r in filteredReports" :key="r.id" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <header class="flex items-center justify-between gap-3 p-4 border-b border-slate-100"><div><h2 class="font-bold text-gray-800">فاتورة #{{ r.id }}</h2><p class="text-xs text-gray-500 mt-1">{{ formatDate(r.date) }} · {{ r.barcode }}</p></div><span :class="['text-xs font-bold px-3 py-1 rounded-full', statusClass(r.status)]">{{ statusLabel(r.status) }}</span></header>
            <div class="grid grid-cols-3 gap-2 p-4 text-sm bg-slate-50"><div><p class="text-xs text-slate-500">الإجمالي</p><p class="font-bold mt-1">{{ money(r.total) }} د.ع</p></div><div><p class="text-xs text-slate-500">المدفوع</p><p class="font-bold text-teal-700 mt-1">{{ money(r.paid) }} د.ع</p></div><div><p class="text-xs text-slate-500">المتبقي</p><p class="font-bold text-amber-800 mt-1">{{ money(r.due) }} د.ع</p></div></div>
            <p v-if="r.loyalty_discount" class="px-4 py-2 text-xs text-teal-700">خصم نقاط: {{ money(r.loyalty_discount) }} د.ع مقابل {{ r.loyalty_points_spent }} نقطة</p>
            <details class="p-4"><summary class="cursor-pointer font-semibold text-sm text-slate-700">تفاصيل الفحوصات ({{ r.tests?.length || 0 }})</summary><div v-for="test in r.tests" :key="test.id" class="flex justify-between gap-3 border-b border-slate-100 py-3 text-sm"><div><p class="font-semibold">{{ test.name }}</p><p class="text-xs text-slate-500 mt-1">{{ test.kind }} · {{ test.sample_received ? 'تم استلام العينة' : 'بانتظار استلام العينة' }}</p></div><span :class="['text-xs self-start px-2 py-1 rounded-full whitespace-nowrap', statusClass(test.status)]">{{ test.status === 'ready' ? 'مكتمل' : 'قيد الإجراء' }}</span></div></details>
            <footer class="px-4 pb-4"><p v-if="r.result_date" class="text-xs text-slate-500 mb-3">موعد النتائج: {{ formatDate(r.result_date) }}</p><a v-if="r.status === 'ready' && r.view_url" :href="r.view_url" target="_blank" rel="noopener" class="block text-center rounded-xl bg-teal-700 text-white py-3 text-sm font-bold">عرض التقرير والنتائج</a><p v-else class="rounded-xl bg-amber-50 text-amber-900 p-3 text-xs">الفحوصات قيد الإجراء؛ سيظهر رابط التقرير عند اكتمال الفاتورة.</p></footer>
          </article>
        </div>

        <!-- Loyalty tab -->
        <div v-else-if="activeTab === 'loyalty' && loyalty" class="space-y-4">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold text-gray-700">{{ loyalty.tier?.label_ar }}</span>
              <span v-if="loyalty.next_tier" class="text-xs text-gray-400">
                {{ pointsToNextTier }} نقطة للوصول إلى {{ loyalty.next_tier.label_ar }}
              </span>
              <span v-else class="text-xs text-gray-400">أعلى مستوى</span>
            </div>
            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-teal-600 rounded-full transition-all" :style="{ width: tierProgressPct + '%' }"></div>
            </div>
            <div class="text-xs text-gray-400 mt-2">نقاط هذا العام: {{ loyalty.yearly_points }}</div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="font-bold text-gray-700 mb-3">استبدل نقاطك</div>
            <div v-if="redeemMessage" class="bg-emerald-50 text-emerald-700 text-sm rounded-xl p-3 mb-3">
              {{ redeemMessage }}
            </div>
            <div v-if="redeemError" class="bg-red-50 text-red-600 text-sm rounded-xl p-3 mb-3">
              {{ redeemError }}
            </div>
            <div class="space-y-2">
              <div
                v-for="item in loyalty.redemption_catalog"
                :key="item.key"
                class="flex items-center justify-between border border-gray-100 rounded-xl p-3"
              >
                <div>
                  <div class="text-sm font-semibold text-gray-700">{{ item.label_ar }}</div>
                  <div class="text-xs text-gray-400">{{ item.points }} نقطة</div>
                </div>
                <button
                  @click="redeem(item)"
                  :disabled="redeemingKey === item.key || loyalty.balance < item.points"
                  class="text-sm font-bold px-4 py-2 rounded-lg bg-teal-600 text-white disabled:bg-gray-200 disabled:text-gray-400 transition"
                >
                  {{ redeemingKey === item.key ? "..." : "استبدال" }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
