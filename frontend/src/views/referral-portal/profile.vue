<script setup>
import { ref, onMounted } from "vue";
import { $http } from "@/plugins/axios";

const form = ref({ display_name: "", phone: "", address: "" });
const logoPreview = ref(null);
const logoFile = ref(null);
const saving = ref(false);
const message = ref("");

const load = async () => {
  const { data } = await $http.get("/referral-portal/profile");
  form.value = { display_name: data.display_name || "", phone: data.phone || "", address: data.address || "" };
  logoPreview.value = data.logo || null;
};

const onLogoChange = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  logoFile.value = file;
  logoPreview.value = URL.createObjectURL(file);
};

const save = async () => {
  saving.value = true;
  message.value = "";
  try {
    const fd = new FormData();
    fd.append("display_name", form.value.display_name || "");
    fd.append("phone", form.value.phone || "");
    fd.append("address", form.value.address || "");
    if (logoFile.value) fd.append("logo", logoFile.value);
    await $http.post("/referral-portal/profile", fd, { headers: { "Content-Type": "multipart/form-data" } });
    message.value = "تم الحفظ";
  } catch (e) {
    message.value = e?.response?.data?.message || "تعذر الحفظ";
  } finally {
    saving.value = false;
  }
};

onMounted(load);
</script>

<template>
  <div class="max-w-lg mx-auto p-4" dir="rtl">
    <h1 class="text-lg font-bold text-gray-800 mb-1">هوية مختبرك</h1>
    <p class="text-sm text-gray-500 mb-4">هذي المعلومات تظهر على النتائج اللي تطبعها لمرضاك من بوابتك.</p>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
      <label class="block w-20 h-20 rounded-xl bg-gray-100 overflow-hidden cursor-pointer border border-gray-200 flex items-center justify-center text-gray-400 text-xs mx-auto">
        <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" />
        <span v-else>الشعار</span>
        <input type="file" accept="image/*" class="hidden" @change="onLogoChange" />
      </label>

      <div>
        <label class="text-xs text-gray-500 block mb-1">اسم المختبر/العيادة</label>
        <input v-model="form.display_name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">رقم الهاتف</label>
        <input v-model="form.phone" dir="ltr" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">العنوان</label>
        <input v-model="form.address" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
      </div>

      <div v-if="message" class="text-sm text-emerald-600">{{ message }}</div>
      <button
        @click="save"
        :disabled="saving"
        class="w-full bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-bold py-3 rounded-xl"
      >
        {{ saving ? "جاري الحفظ..." : "حفظ" }}
      </button>
    </div>
  </div>
</template>
