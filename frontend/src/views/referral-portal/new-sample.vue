<script setup>
import { ref, computed, onMounted, nextTick, watch } from "vue";
import { $http } from "@/plugins/axios";
import { useRouter } from "vue-router";
import { usePrint } from "@/composables/usePrint";
import BarcodeComponent from "@/components/BarcodeComponent.vue";

const router = useRouter();
const { printWithCustomContent } = usePrint();
const requestId = ref(crypto.randomUUID());
const previousPatients = ref([]);
const selectedPatient = ref(null);
const findPatients = async () => {
  error.value = "";
  try {
    const { data } = await $http.get('/referral-portal/patients', { params: { lab_id: selectedLabId.value, name: patientName.value } });
    previousPatients.value = data;
    if (!data.length) error.value = 'لا يوجد مريض مطابق ضمن عيناتك السابقة؛ يمكنك تسجيل مريض جديد.';
  } catch (e) { error.value = e?.response?.data?.message || 'تعذر البحث'; }
};
const choosePatient = (p) => { selectedPatient.value = p; patientName.value = p.name; patientDob.value = p.dob || ''; previousPatients.value = []; };

const connections = ref([]);
const selectedLabId = ref(null);
const tests = ref([]);
const testsLoading = ref(false);

const patientName = ref("");
const patientPhone = ref("");
const patientDob = ref("");
const selectedTestIds = ref([]);

const saving = ref(false);
const error = ref("");
const createdSample = ref(null); // { id, barcode, patient_name }

const total = computed(() =>
  tests.value.filter((t) => selectedTestIds.value.includes(t.test_id)).reduce((sum, t) => sum + Number(t.price || 0), 0)
);

const loadConnections = async () => {
  const { data } = await $http.get("/referral-portal/connections");
  connections.value = data || [];
  if (connections.value.length === 1) {
    selectedLabId.value = connections.value[0].lab_id;
  }
};

const loadTests = async (labId) => {
  if (!labId) return;
  testsLoading.value = true;
  selectedTestIds.value = [];
  try {
    const { data } = await $http.get(`/referral-portal/price-list/${labId}`);
    tests.value = data.tests || [];
  } finally {
    testsLoading.value = false;
  }
};

const toggleTest = (testId) => {
  const i = selectedTestIds.value.indexOf(testId);
  if (i >= 0) selectedTestIds.value.splice(i, 1);
  else selectedTestIds.value.push(testId);
};

const submit = async () => {
  if (!selectedLabId.value || !patientName.value || !selectedTestIds.value.length) return;
  saving.value = true;
  error.value = "";
  try {
    const { data } = await $http.post("/referral-portal/invoices", {
      request_id: requestId.value,
      patient_id: selectedPatient.value?.id,
      lab_id: selectedLabId.value,
      patient_name: patientName.value,
      patient_phone: patientPhone.value || undefined,
      patient_dob: patientDob.value || undefined,
      test_ids: selectedTestIds.value,
    });
    createdSample.value = data;
  } catch (e) {
    error.value = e?.response?.data?.message || "تعذر إنشاء العينة";
  } finally {
    saving.value = false;
  }
};

const printBarcode = async () => {
  await nextTick();
  const el = document.getElementById("tube-label");
  if (!el) return;
  await printWithCustomContent(el.innerHTML, `
    @page{size:60mm 30mm;margin:2mm}
    body{font-family:Arial,sans-serif;text-align:center;margin:0}
    svg{max-width:100%}.name{font-size:11px;font-weight:bold}
  `, 'Barcode');
};

const startAnother = () => {
  createdSample.value = null;
  requestId.value = crypto.randomUUID();
  selectedPatient.value = null;
  previousPatients.value = [];
  patientName.value = "";
  patientPhone.value = "";
  patientDob.value = "";
  selectedTestIds.value = [];
};

onMounted(loadConnections);
watch(selectedLabId, (id) => {
  selectedPatient.value = null; previousPatients.value = [];
  if (id) loadTests(id);
});
</script>

<template>
  <div class="max-w-2xl mx-auto p-4" dir="rtl">
    <h1 class="text-lg font-bold text-gray-800 mb-4">عينة جديدة</h1>

    <!-- Success / barcode print step -->
    <div v-if="createdSample" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-center space-y-4">
      <div class="text-emerald-600 text-lg font-bold">تم إنشاء العينة ✅</div>
      <div id="tube-label" class="inline-block p-3 border border-dashed border-gray-300 rounded-lg">
        <BarcodeComponent :value="createdSample.barcode" :width="1.4" :height="35" />
        <div class="name">{{ createdSample.patient_name }}</div>
      </div>
      <div class="flex gap-2 justify-center">
        <button @click="printBarcode" class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold px-5 py-2.5 rounded-xl">
          🖨 طباعة ملصق الأنبوب
        </button>
        <button @click="startAnother" class="bg-gray-100 text-gray-600 text-sm font-bold px-5 py-2.5 rounded-xl">
          عينة أخرى
        </button>
      </div>
      <button @click="router.push('/referral-portal')" class="text-xs text-teal-700 underline">الرجوع لقائمة العينات</button>
    </div>

    <!-- Creation form -->
    <div v-else class="space-y-4">
      <div v-if="connections.length > 1" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <label class="text-xs text-gray-500 block mb-1">أرسل إلى</label>
        <select v-model="selectedLabId" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
          <option :value="null" disabled>اختر المختبر</option>
          <option v-for="c in connections" :key="c.lab_id" :value="c.lab_id">{{ c.lab_name }}</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-3">
        <div>
          <label class="text-xs text-gray-500 block mb-1">اسم المريض</label>
          <input :readonly="!!selectedPatient" v-model="patientName" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-500 block mb-1">رقم الهاتف (اختياري)</label>
            <input v-model="patientPhone" type="text" dir="ltr" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">تاريخ الميلاد (اختياري)</label>
            <input :readonly="!!selectedPatient" v-model="patientDob" type="date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
      </div>

      <div class="bg-teal-50 rounded-xl p-4 text-sm space-y-2">
        <p>رقم الهاتف لا يربط المرضى تلقائياً. اختر المريض السابق بعد التأكد من الاسم وتاريخ الميلاد، أو سجّل مريضاً جديداً.</p>
        <button v-if="!selectedPatient" type="button" @click="findPatients" :disabled="!selectedLabId || patientName.length < 3" class="text-teal-800 underline disabled:opacity-40">بحث بالاسم ضمن مرضاي السابقين</button>
        <div v-if="selectedPatient">المريض المحدد: {{ selectedPatient.name }} — {{ selectedPatient.code }} — {{ selectedPatient.dob || 'الميلاد غير مسجل' }}
          <button type="button" @click="selectedPatient = null" class="underline mx-2">إلغاء الاختيار وتسجيل جديد</button>
        </div>
        <button v-for="p in previousPatients" :key="p.id" type="button" @click="choosePatient(p)" class="block w-full text-right border p-2 rounded">{{ p.name }} — {{ p.code }} — {{ p.dob || 'الميلاد غير مسجل' }}</button>
      </div>
      <div v-if="selectedLabId" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="text-sm font-bold text-gray-700 mb-2">اختر الفحوصات</div>
        <div v-if="testsLoading" class="text-center text-gray-400 py-4">جاري التحميل...</div>
        <div v-else-if="!tests.length" class="text-center text-gray-400 py-4 text-sm">لا توجد فحوصات متاحة بهذه القائمة</div>
        <div v-else class="space-y-1 max-h-72 overflow-y-auto">
          <label
            v-for="t in tests"
            :key="t.test_id"
            class="flex items-center justify-between px-2 py-2 rounded-lg hover:bg-gray-50 cursor-pointer"
          >
            <span class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" :checked="selectedTestIds.includes(t.test_id)" @change="toggleTest(t.test_id)" />
              {{ t.name }}
            </span>
            <span class="text-sm font-bold text-gray-600">{{ t.price }}</span>
          </label>
        </div>
        <div v-if="selectedTestIds.length" class="flex justify-between border-t border-gray-100 mt-3 pt-3 text-sm font-bold">
          <span>الإجمالي</span>
          <span>{{ total }}</span>
        </div>
      </div>

      <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>
      <button
        @click="submit"
        :disabled="saving || !selectedLabId || !patientName || !selectedTestIds.length"
        class="w-full bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold py-3 rounded-xl"
      >
        {{ saving ? "جاري الإنشاء..." : "إنشاء العينة" }}
      </button>
    </div>
  </div>
</template>
