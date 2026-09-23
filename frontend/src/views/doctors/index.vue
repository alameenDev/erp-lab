<script setup>
import { ref, onMounted } from "vue";
import { $http } from "@/plugins/axios";

const doctors = ref([]);
const bookings = ref([]);
const loading = ref(true);
const activeTab = ref("doctors"); // doctors | bookings

const showForm = ref(false);
const editingId = ref(null);
const form = ref(defaultForm());
const saving = ref(false);
const error = ref("");
const photoFile = ref(null);
const photoPreview = ref(null);

function defaultForm() {
  return {
    name: "",
    specialty: "",
    description: "",
    phone: "",
    whatsapp: "",
    links: { website: "", facebook: "", instagram: "", twitter: "", tiktok: "" },
    bookable: true,
    is_active: true,
  };
}

const loadDoctors = async () => {
  loading.value = true;
  try {
    const { data } = await $http.get("/doctors");
    doctors.value = data || [];
  } finally {
    loading.value = false;
  }
};

const loadBookings = async () => {
  loading.value = true;
  try {
    const { data } = await $http.get("/doctor-bookings");
    bookings.value = data?.data || data || [];
  } finally {
    loading.value = false;
  }
};

const switchTab = (tab) => {
  activeTab.value = tab;
  if (tab === "doctors") loadDoctors();
  else loadBookings();
};

const openCreate = () => {
  editingId.value = null;
  form.value = defaultForm();
  photoFile.value = null;
  photoPreview.value = null;
  error.value = "";
  showForm.value = true;
};

const openEdit = (doc) => {
  editingId.value = doc.id;
  form.value = {
    name: doc.name,
    specialty: doc.specialty || "",
    description: doc.description || "",
    phone: doc.phone || "",
    whatsapp: doc.whatsapp || "",
    links: { website: "", facebook: "", instagram: "", twitter: "", tiktok: "", ...(doc.links || {}) },
    bookable: !!doc.bookable,
    is_active: !!doc.is_active,
  };
  photoFile.value = null;
  photoPreview.value = doc.photo || null;
  error.value = "";
  showForm.value = true;
};

const onPhotoChange = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  photoFile.value = file;
  photoPreview.value = URL.createObjectURL(file);
};

const buildFormData = () => {
  const fd = new FormData();
  fd.append("name", form.value.name);
  fd.append("specialty", form.value.specialty || "");
  fd.append("description", form.value.description || "");
  fd.append("phone", form.value.phone || "");
  fd.append("whatsapp", form.value.whatsapp || "");
  Object.entries(form.value.links || {}).forEach(([k, v]) => {
    if (v) fd.append(`links[${k}]`, v);
  });
  fd.append("bookable", form.value.bookable ? "1" : "0");
  fd.append("is_active", form.value.is_active ? "1" : "0");
  if (photoFile.value) fd.append("photo", photoFile.value);
  return fd;
};

const save = async () => {
  if (!form.value.name) return;
  saving.value = true;
  error.value = "";
  try {
    const fd = buildFormData();
    if (editingId.value) {
      fd.append("_method", "PUT");
      await $http.post(`/doctors/${editingId.value}`, fd, { headers: { "Content-Type": "multipart/form-data" } });
    } else {
      await $http.post("/doctors", fd, { headers: { "Content-Type": "multipart/form-data" } });
    }
    showForm.value = false;
    await loadDoctors();
  } catch (e) {
    error.value = e?.response?.data?.message || "تعذر حفظ بيانات الطبيب";
  } finally {
    saving.value = false;
  }
};

const remove = async (doc) => {
  if (!confirm(`حذف الطبيب ${doc.name}؟`)) return;
  await $http.delete(`/doctors/${doc.id}`);
  doctors.value = doctors.value.filter((d) => d.id !== doc.id);
};

const setBookingStatus = async (booking, status) => {
  try {
    await $http.put(`/doctor-bookings/${booking.id}`, { status });
    booking.status = status;
  } catch (e) {
    // ignore
  }
};

const statusLabel = (s) => ({ pending: "بانتظار التأكيد", confirmed: "مؤكد", cancelled: "ملغي" }[s] || s);
const statusClass = (s) =>
  ({
    pending: "bg-amber-100 text-amber-700",
    confirmed: "bg-emerald-100 text-emerald-700",
    cancelled: "bg-gray-100 text-gray-500",
  }[s] || "bg-gray-100 text-gray-500");

onMounted(loadDoctors);
</script>

<template>
  <div class="p-4 md:p-6 max-w-5xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-800">الأطباء</h1>
      <button
        v-if="activeTab === 'doctors'"
        @click="openCreate"
        class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold px-4 py-2 rounded-xl"
      >
        + إضافة طبيب
      </button>
    </div>

    <div class="flex gap-2 bg-white rounded-xl p-1 shadow-sm border border-gray-100 mb-5 w-fit">
      <button
        @click="switchTab('doctors')"
        :class="['px-4 py-2 rounded-lg text-sm font-bold', activeTab === 'doctors' ? 'bg-teal-600 text-white' : 'text-gray-500']"
      >
        قائمة الأطباء
      </button>
      <button
        @click="switchTab('bookings')"
        :class="['px-4 py-2 rounded-lg text-sm font-bold', activeTab === 'bookings' ? 'bg-teal-600 text-white' : 'text-gray-500']"
      >
        طلبات الحجز
      </button>
    </div>

    <!-- Doctor form -->
    <div v-if="showForm" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
      <div class="flex gap-4">
        <div class="shrink-0">
          <label class="block w-24 h-24 rounded-xl bg-gray-100 overflow-hidden cursor-pointer border border-gray-200 flex items-center justify-center text-gray-400 text-xs">
            <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" />
            <span v-else>صورة</span>
            <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
          </label>
        </div>
        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-500 block mb-1">الاسم</label>
            <input v-model="form.name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">الاختصاص</label>
            <input v-model="form.specialty" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="مثلاً: أمراض دم" />
          </div>
          <div class="md:col-span-2">
            <label class="text-xs text-gray-500 block mb-1">نبذة تعريفية</label>
            <textarea v-model="form.description" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"></textarea>
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">رقم الهاتف</label>
            <input v-model="form.phone" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">واتساب</label>
            <input v-model="form.whatsapp" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">الموقع الإلكتروني</label>
            <input v-model="form.links.website" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">فيسبوك</label>
            <input v-model="form.links.facebook" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">انستغرام</label>
            <input v-model="form.links.instagram" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
          </div>
          <div>
            <label class="text-xs text-gray-500 block mb-1">تيك توك</label>
            <input v-model="form.links.tiktok" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
          </div>
          <div class="flex items-center gap-4 md:col-span-2">
            <label class="flex items-center gap-2 text-sm text-gray-600">
              <input v-model="form.bookable" type="checkbox" /> يقبل حجوزات من البوابة
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
              <input v-model="form.is_active" type="checkbox" /> ظاهر بالبوابة
            </label>
          </div>
        </div>
      </div>
      <div v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="showForm = false" class="text-sm font-bold px-4 py-2 rounded-xl text-gray-500">إلغاء</button>
        <button
          @click="save"
          :disabled="saving || !form.name"
          class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-bold px-5 py-2 rounded-xl"
        >
          {{ saving ? "جاري الحفظ..." : "حفظ" }}
        </button>
      </div>
    </div>

    <!-- Doctors list -->
    <div v-if="activeTab === 'doctors'" class="space-y-3">
      <div v-if="loading" class="text-center text-gray-400 py-8">جاري التحميل...</div>
      <div v-else-if="!doctors.length" class="text-center text-gray-400 py-8">لا يوجد أطباء مضافين بعد</div>
      <div
        v-for="doc in doctors"
        :key="doc.id"
        class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-4"
      >
        <img
          :src="doc.photo || 'https://placehold.co/64x64?text=%20'"
          class="w-16 h-16 rounded-xl object-cover bg-gray-100 shrink-0"
        />
        <div class="flex-1 min-w-0">
          <div class="font-bold text-gray-800">{{ doc.name }}</div>
          <div class="text-sm text-gray-500">{{ doc.specialty || "—" }}</div>
        </div>
        <span :class="['text-xs font-bold px-2 py-1 rounded-full', doc.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500']">
          {{ doc.is_active ? "ظاهر" : "مخفي" }}
        </span>
        <button @click="openEdit(doc)" class="text-teal-600 text-sm font-bold">تعديل</button>
        <button @click="remove(doc)" class="text-red-500 text-sm font-bold">حذف</button>
      </div>
    </div>

    <!-- Booking requests -->
    <div v-else class="space-y-3">
      <div v-if="loading" class="text-center text-gray-400 py-8">جاري التحميل...</div>
      <div v-else-if="!bookings.length" class="text-center text-gray-400 py-8">لا توجد طلبات حجز بعد</div>
      <div v-for="b in bookings" :key="b.id" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between mb-2">
          <div>
            <div class="font-bold text-gray-800">{{ b.patient_name || "مريض" }}</div>
            <div class="text-sm text-gray-500">
              حجز عند: {{ b.doctor?.name }} — {{ b.doctor?.specialty }}
            </div>
          </div>
          <span :class="['text-xs font-bold px-2 py-1 rounded-full', statusClass(b.status)]">{{ statusLabel(b.status) }}</span>
        </div>
        <div class="text-sm text-gray-500 space-y-1">
          <div v-if="b.phone" dir="ltr" class="text-right">📞 {{ b.phone }}</div>
          <div v-if="b.preferred_date">🗓 الموعد المفضل: {{ new Date(b.preferred_date).toLocaleString("ar-IQ") }}</div>
          <div v-if="b.notes">📝 {{ b.notes }}</div>
        </div>
        <div v-if="b.status === 'pending'" class="flex gap-2 mt-3">
          <button @click="setBookingStatus(b, 'confirmed')" class="text-xs font-bold bg-emerald-600 text-white px-3 py-1.5 rounded-lg">تأكيد</button>
          <button @click="setBookingStatus(b, 'cancelled')" class="text-xs font-bold bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg">إلغاء</button>
        </div>
      </div>
    </div>
  </div>
</template>
