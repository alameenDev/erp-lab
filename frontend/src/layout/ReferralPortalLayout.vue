<script setup>
import { useRouter } from "vue-router";
import { useAuthStore } from "@/store/modules/auth";

const router = useRouter();
const authStore = useAuthStore();

const logout = () => {
  authStore.logout();
};
</script>

<template>
  <div class="min-h-screen bg-gray-50" dir="rtl">
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-10">
      <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="font-bold text-teal-700">بوابة الإحالة</div>
        <div class="flex items-center gap-1 text-sm">
          <router-link
            to="/referral-portal"
            class="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50"
            active-class="bg-teal-50 text-teal-700 font-bold"
            exact
          >
            العينات
          </router-link>
          <router-link
            to="/referral-portal/new"
            class="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50"
            active-class="bg-teal-50 text-teal-700 font-bold"
          >
            + عينة جديدة
          </router-link>
          <router-link
            to="/referral-portal/profile"
            class="px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50"
            active-class="bg-teal-50 text-teal-700 font-bold"
          >
            الهوية
          </router-link>
          <button @click="logout" class="px-3 py-2 rounded-lg text-red-500 hover:bg-red-50">خروج</button>
        </div>
      </div>
    </nav>
    <router-view />
  </div>
</template>
