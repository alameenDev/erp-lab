<script setup>
import { ref, computed, onMounted } from "vue";
import { $http } from "@/plugins/axios";

const codes = ref([]);
const loading = ref(true);
const activeOnly = ref(false);

// Single-code form
const showSingleForm = ref(false);
const singleForm = ref(defaultForm());
const singleSaving = ref(false);
const singleError = ref("");

// Batch form
const showBatchForm = ref(false);
const batchForm = ref({ ...defaultForm(), count: 10, prefix: "" });
const batchSaving = ref(false);
const batchError = ref("");
const batchResult = ref(null);

function defaultForm() {
  return {
    code: "",
    label: "",
    discount_type: "percentage",
    discount_value: null,
    max_uses: null,
    max_uses_per_patient: 1,
    min_invoice_amount: null,
    expires_at: "",
  };
}

const load = async () => {
  loading.value = true;
  try {
    const { data } = await $http.get("/promo-codes", {
      params: activeOnly.value ? { is_active: 1 } : {},
    });
    codes.value = data?.data || data || [];
  } catch (e) {
    // keep whatever we had
  } finally {
    loading.value = false;
  }
};

const createSingle = async () => {
  singleSaving.value = true;
  singleError.value = "";
  try {
    await $http.post("/promo-codes", { ...singleForm.value, code: singleForm.value.code || null });
    singleForm.value = defaultForm();
    showSingleForm.value = false;
    await load();
  } catch (e) {
    singleError.value = e?.response?.data?.message || "تعذر إنشاء الكود";
  } finally {
    singleSaving.value = false;
  }
};

const createBatch = async () => {
  batchSaving.value = true;
  batchError.value = "";
  batchResult.value = null;
  try {
    const { data } = await $http.post("/promo-codes/generate-batch", {
      count: batchForm.value.count,
      prefix: batchForm.value.prefix || null,
      label: batchForm.value.label || null,
      discount_type: batchForm.value.discount_type,
      discount_value: batchForm.value.discount_value,
      max_uses_per_code: batchForm.value.max_uses || 1,
      max_uses_per_patient: batchForm.value.max_uses_per_patient,
      min_invoice_amount: batchForm.value.min_invoice_amount,
      expires_at: batchForm.value.expires_at || null,
    });
    batchResult.value = data;
    await load();
  } catch (e) {
    batchError.value = e?.response?.data?.message || "تعذر توليد الدفعة";
  } finally {
    batchSaving.value = false;
  }
};

const toggleActive = async (promo) => {
  try {
    await $http.put(`/promo-codes/${promo.id}`, { is_active: !promo.is_active });
    promo.is_active = !promo.is_active;
  } catch (e) {
    // ignore
  }
};

const remove = async (promo) => {
  if (!confirm(`حذف الكود ${promo.code}؟`)) return;
  try {
    await $http.delete(`/promo-codes/${promo.id}`);
    codes.value = codes.value.filter((c) => c.id !== promo.id);
  } catch (e) {
    // ignore
  }
};

const copyBatchCodes = () => {
  if (!batchResult.value?.codes) return;
  const text = batchResult.value.codes.map((c) => c.code).join("\n");
  navigator.clipboard?.writeText(text);
};

const discountLabel = (promo) =>
  promo.discount_type === "percentage" ? `${promo.discount_value}%` : `${promo.discount_value}`;

const usageLabel = (promo) => `${promo.used_count} / ${promo.max_uses ?? "∞"}`;

const isExpired = (promo) => promo.expires_at && new Date(promo.expires_at) < new Date();

onMounted(load);
</script>

<template>
  <div class="p-4 md:p-6 max-w-5xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-800">أكواد الخصم (البروموكود)</h1>
      <div class="flex gap-2">
        <button
          @click="showSingleForm = !showSingleForm"
          class="bg-white border border-gray-200 hover:border-teal-400 text-gray-700 text-sm font-bold px-4 py-2 rounded-xl transition"
        >
          + كود واحد
        </button>
        <button
          @click="showBatchForm = !showBatchForm"
          class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold px-4 py-2 rounded-xl transition"
        >
          + توليد دفعة أكواد
        </button>
      </div>
    </div>

    <!-- Single code form -->
    <div v-if="showSingleForm" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="text-xs text-gray-500 block mb-1">الكود (اختياري - يتولد تلقائياً)</label>
          <input v-model="singleForm.code" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="EX: WELCOME10" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">وصف/تسمية</label>
          <input v-model="singleForm.label" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="مثلاً: عرض العيد" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">نوع الخصم</label>
          <select v-model="singleForm.discount_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="percentage">نسبة مئوية %</option>
            <option value="fixed">مبلغ ثابت</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">قيمة الخصم</label>
          <input v-model.number="singleForm.discount_value" type="number" step="0.01" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">عدد مرات الاستخدام الكلي (فارغ = بلا حد)</label>
          <input v-model.number="singleForm.max_uses" type="number" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">حد الاستخدام لكل مريض</label>
          <input v-model.number="singleForm.max_uses_per_patient" type="number" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">حد أدنى لقيمة الفاتورة (اختياري)</label>
          <input v-model.number="singleForm.min_invoice_amount" type="number" min="0" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">تاريخ الانتهاء (اختياري)</label>
          <input v-model="singleForm.expires_at" type="date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
      </div>
      <div v-if="singleError" class="text-red-600 text-sm mt-3">{{ singleError }}</div>
      <div class="flex justify-end mt-4">
        <button
          @click="createSingle"
          :disabled="singleSaving || !singleForm.discount_value"
          class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-bold px-5 py-2 rounded-xl"
        >
          {{ singleSaving ? "جاري الحفظ..." : "إنشاء الكود" }}
        </button>
      </div>
    </div>

    <!-- Batch generation form -->
    <div v-if="showBatchForm" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
      <div class="text-sm font-bold text-gray-700 mb-3">توليد عدة أكواد دفعة واحدة (مثلاً لحملة إعلانية)</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="text-xs text-gray-500 block mb-1">عدد الأكواد المطلوب توليدها</label>
          <input v-model.number="batchForm.count" type="number" min="1" max="500" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">بادئة الكود (اختياري)</label>
          <input v-model="batchForm.prefix" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="EID" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">وصف الحملة</label>
          <input v-model="batchForm.label" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="حملة العيد" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">نوع الخصم</label>
          <select v-model="batchForm.discount_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="percentage">نسبة مئوية %</option>
            <option value="fixed">مبلغ ثابت</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">قيمة الخصم</label>
          <input v-model.number="batchForm.discount_value" type="number" step="0.01" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">مرات استخدام كل كود (افتراضي 1)</label>
          <input v-model.number="batchForm.max_uses" type="number" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 block mb-1">تاريخ الانتهاء (اختياري)</label>
          <input v-model="batchForm.expires_at" type="date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
      </div>
      <div v-if="batchError" class="text-red-600 text-sm mt-3">{{ batchError }}</div>

      <div v-if="batchResult" class="bg-emerald-50 rounded-xl p-4 mt-4">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-bold text-emerald-700">
            تم توليد {{ batchResult.codes.length }} كود بنجاح ✅
          </div>
          <button @click="copyBatchCodes" class="text-xs font-bold text-teal-700 underline">نسخ كل الأكواد</button>
        </div>
        <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto">
          <span v-for="c in batchResult.codes" :key="c.id" class="bg-white border border-gray-200 rounded-lg px-2 py-1 text-xs font-mono">
            {{ c.code }}
          </span>
        </div>
      </div>

      <div class="flex justify-end mt-4">
        <button
          @click="createBatch"
          :disabled="batchSaving || !batchForm.discount_value || !batchForm.count"
          class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-bold px-5 py-2 rounded-xl"
        >
          {{ batchSaving ? "جاري التوليد..." : "توليد الدفعة" }}
        </button>
      </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between p-4 border-b border-gray-100">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          <input v-model="activeOnly" @change="load" type="checkbox" />
          إظهار المفعّل فقط
        </label>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-400">جاري التحميل...</div>
      <div v-else-if="!codes.length" class="p-8 text-center text-gray-400">لا توجد أكواد بعد</div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-500">
            <tr>
              <th class="text-right px-4 py-2">الكود</th>
              <th class="text-right px-4 py-2">الوصف</th>
              <th class="text-right px-4 py-2">الخصم</th>
              <th class="text-right px-4 py-2">الاستخدام</th>
              <th class="text-right px-4 py-2">الانتهاء</th>
              <th class="text-right px-4 py-2">الحالة</th>
              <th class="text-right px-4 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="promo in codes" :key="promo.id" class="border-t border-gray-50">
              <td class="px-4 py-2 font-mono font-bold">{{ promo.code }}</td>
              <td class="px-4 py-2 text-gray-500">{{ promo.label || "—" }}</td>
              <td class="px-4 py-2">{{ discountLabel(promo) }}</td>
              <td class="px-4 py-2">{{ usageLabel(promo) }}</td>
              <td class="px-4 py-2" :class="isExpired(promo) ? 'text-red-500' : 'text-gray-500'">
                {{ promo.expires_at ? new Date(promo.expires_at).toLocaleDateString("ar-IQ") : "—" }}
              </td>
              <td class="px-4 py-2">
                <button
                  @click="toggleActive(promo)"
                  :class="['text-xs font-bold px-2 py-1 rounded-full', promo.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500']"
                >
                  {{ promo.is_active ? "مفعّل" : "متوقف" }}
                </button>
              </td>
              <td class="px-4 py-2 text-left">
                <button @click="remove(promo)" class="text-red-500 hover:text-red-700 text-xs font-bold">حذف</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
