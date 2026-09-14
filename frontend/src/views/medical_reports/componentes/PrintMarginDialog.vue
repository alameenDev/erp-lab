<script setup>
import { ref, computed, onMounted } from "vue";
import { $http } from "@/plugins/axios";

const visible = defineModel("visible");
const props = defineProps({
  background: { type: String, default: "" },
});
const emit = defineEmits(["preview", "background-change"]);

const margins = ref({
  top: 20,
  bottom: 20,
  left: 15,
  right: 15,
});

const showCategories = ref(true);
const showTestsOnBarcode = ref(true);
const showPatientName = ref(true);

const bgPreview = ref(props.background || "");
const bgInput = ref(null);
const uploading = ref(false);
const lang = computed(() => localStorage.getItem("locale") || "ar");

onMounted(async () => {
  // Try to load from server first, fallback to localStorage
  try {
    const user = JSON.parse(localStorage.getItem("user") || "{}");
    if (user?.id) {
      const { data } = await $http.get(`/lab-margins/${user.id}`);
      if (data?.margins) {
        margins.value = data.margins;
        localStorage.setItem("bgPrintMargins", JSON.stringify(data.margins));
      } else {
        const saved = localStorage.getItem("bgPrintMargins");
        if (saved) margins.value = JSON.parse(saved);
      }
    } else {
      const saved = localStorage.getItem("bgPrintMargins");
      if (saved) margins.value = JSON.parse(saved);
    }
  } catch {
    const saved = localStorage.getItem("bgPrintMargins");
    if (saved) margins.value = JSON.parse(saved);
  }
  bgPreview.value = localStorage.getItem("reportBackground") || "";
  const savedShowCat = localStorage.getItem("printShowCategories");
  if (savedShowCat !== null) showCategories.value = savedShowCat === "true";
  const savedShowTests = localStorage.getItem("printShowTestsOnBarcode");
  if (savedShowTests !== null) showTestsOnBarcode.value = savedShowTests === "true";
  const savedShowName = localStorage.getItem("printShowPatientName");
  if (savedShowName !== null) showPatientName.value = savedShowName === "true";
});

async function saveMargins() {
  localStorage.setItem("bgPrintMargins", JSON.stringify(margins.value));
  localStorage.setItem("printShowCategories", showCategories.value.toString());
  localStorage.setItem("printShowTestsOnBarcode", showTestsOnBarcode.value.toString());
  localStorage.setItem("printShowPatientName", showPatientName.value.toString());
  try {
    await $http.post("/lab-margins", margins.value);
  } catch (err) {
    console.error("Failed to save margins to server:", err);
  }
  visible.value = false;
}

async function uploadBackground(e) {
  const file = e.target.files[0];
  if (!file) return;

  // Preview locally
  const reader = new FileReader();
  reader.onload = (ev) => {
    bgPreview.value = ev.target.result;
    localStorage.setItem("reportBackground", ev.target.result);
    emit("background-change", ev.target.result);
  };
  reader.readAsDataURL(file);

  // Upload to server
  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append("background", file);
    await $http.post("/lab-background", formData, { headers: { "Content-Type": "multipart/form-data" } });
  } catch (err) {
    console.error("Failed to upload background to server:", err);
  } finally {
    uploading.value = false;
  }

  e.target.value = "";
}

function removeBackground() {
  bgPreview.value = "";
  localStorage.removeItem("reportBackground");
  emit("background-change", "");
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="visible = false"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">ضبط هوامش المحتوى</h2>
            <button
              @click="visible = false"
              class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 space-y-6">
            <!-- Margins -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">الهامش العلوي (mm)</label>
                <input
                  type="number"
                  v-model.number="margins.top"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">الهامش السفلي (mm)</label>
                <input
                  type="number"
                  v-model.number="margins.bottom"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">الهامش الأيسر (mm)</label>
                <input
                  type="number"
                  v-model.number="margins.left"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">الهامش الأيمن (mm)</label>
                <input
                  type="number"
                  v-model.number="margins.right"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
            </div>

            <!-- Print Options -->
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
              <input
                type="checkbox"
                id="showCategories"
                v-model="showCategories"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <label for="showCategories" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                إظهار الأقسام (Categories) في الطباعة والواتساب
              </label>
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
              <input
                type="checkbox"
                id="showTestsOnBarcode"
                v-model="showTestsOnBarcode"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <label for="showTestsOnBarcode" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                إظهار التحاليل في طباعة الباركود
              </label>
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
              <input
                type="checkbox"
                id="showPatientName"
                v-model="showPatientName"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <label for="showPatientName" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                إظهار اسم التحليل في الطباعة
              </label>
            </div>

            <!-- Background Upload -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">خلفية التقرير</label>
              <div v-if="bgPreview" class="relative group mb-3">
                <img :src="bgPreview" alt="Report background" class="w-full h-40 object-contain border border-gray-200 rounded-lg bg-gray-50" />
                <button
                  @click="removeBackground"
                  class="absolute top-2 end-2 w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-md"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <button
                @click="bgInput?.click()"
                :disabled="uploading"
                class="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-600 hover:border-primary-400 hover:text-primary-600 hover:bg-primary-50/50 transition-all disabled:opacity-50"
              >
                <svg v-if="!uploading" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                {{ uploading ? 'جاري الرفع...' : bgPreview ? 'تغيير الخلفية' : 'رفع صورة الخلفية' }}
              </button>
              <input ref="bgInput" type="file" accept="image/*" class="hidden" @change="uploadBackground" />
              <p class="text-xs text-gray-400 mt-1.5">صورة A4 بالحجم الكامل تُستخدم كخلفية عند الطباعة</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-2 p-4 border-t border-gray-200">
            <button
              @click="saveMargins"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              حفظ
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
