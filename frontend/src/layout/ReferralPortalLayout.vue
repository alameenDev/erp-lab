<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import { useLabSettingsStore } from "@/store/modules/labSettings";
import { useinvoicesStore } from "@/store/modules/invoices";
import { usePatientsStore } from "@/store/modules/patients";
import { referralWorkspace } from "@/utils/referralWorkspace";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/store/modules/auth";

const ready = ref(false), error = ref('');
const settings = useLabSettingsStore();
const reset = () => { useinvoicesStore().$reset(); usePatientsStore().$reset(); settings.$reset(); referralWorkspace.destinationLabId = null; };
reset();
const load = async () => { error.value = ''; try { await settings.GetSettings(); ready.value = true; } catch(e) { error.value = e.response?.data?.message || 'تعذر تحميل إعدادات جهة الإحالة'; } };
onMounted(load);
onBeforeUnmount(reset);
const router = useRouter();
const authStore = useAuthStore();

const logout = () => {
  authStore.logout();
};
</script>

<template>
  <div class="min-h-screen bg-gray-50" dir="rtl">
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-10">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="font-bold text-teal-700">بوابة الإحالة</div>
        <div class="flex items-center gap-1 text-sm">
          <router-link
            to="/referral-portal"
            class="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50"
            active-class="bg-teal-50 text-teal-700 font-bold"
            exact
          >
            الفواتير والتقارير
          </router-link>
          <router-link
            to="/referral-portal/new"
            class="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50"
            active-class="bg-teal-50 text-teal-700 font-bold"
          >
            + فاتورة جديدة
          </router-link>
          <router-link
            to="/referral-portal/profile"
            class="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50"
            active-class="bg-teal-50 text-teal-700 font-bold"
          >
            إعدادات الطباعة والفورمة
          </router-link>
          <button @click="logout" class="px-3 py-2 rounded-lg text-red-500 hover:bg-red-50">خروج</button>
        </div>
      </div>
    </nav>
    <router-view v-if="ready" />
    <div v-else class="p-6 text-center"><p>{{ error || "جاري تحميل البوابة…" }}</p><button v-if="error" @click="load">إعادة المحاولة</button></div>
  </div>
</template>
