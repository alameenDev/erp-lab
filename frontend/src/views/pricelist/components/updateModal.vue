<script setup>
import { ref, computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { priceListStore } from "@/store/modules/priceList";
import { usePackagesStore } from "@/store/modules/packages";
import { useculturesStore } from "@/store/modules/cultures";
import { usetestsStore } from "@/store/modules/tests";
import { usetestGroupsStore } from "@/store/modules/testGroups";
import { t, alertSuccess, clearObjectValues } from "@/utils/helper";

const priceStore = priceListStore();
const packagesStore = usePackagesStore();
const culturesStore = useculturesStore();
const testsStore = usetestsStore();
const testGroupsStore = usetestGroupsStore();

const { record, updatedialog, UpdateList, isDiscount } = storeToRefs(priceStore);
const { UpdatepriceList } = priceStore;
const { Getpackages } = packagesStore;
const { Getcultures } = culturesStore;
const { GetTests } = testsStore;
const { GettestGroups } = testGroupsStore;

const lang = computed(() => localStorage.getItem("locale") || "ar");

const testsSearchQuery = ref("");
const cultureSearchQuery = ref("");
const packageSearchQuery = ref("");
const activeTab = ref("tests");

const test_currentPage = ref(1);
const packages_currentPage = ref(1);
const cultures_currentPage = ref(1);
const rowsPerPage = 10;

// Stable ref arrays — built once when dialog opens, not re-created by computed
const testItems = ref([]);
const cultureItems = ref([]);
const packageItems = ref([]);

// Load all system items + merge saved prices when dialog opens
watch(updatedialog, async (val) => {
  if (val) {
    await Promise.all([GetTests(), GettestGroups(), Getcultures(), Getpackages()]);

    // Build lookup maps from saved price list items
    const savedTestMap = {};
    for (const item of UpdateList.value?.price_list_items?.tests || []) {
      savedTestMap[item.test_id] = item;
    }
    const savedGroupMap = {};
    for (const item of UpdateList.value?.price_list_items?.test_groups || []) {
      savedGroupMap[item.test_group_id] = item;
    }
    const savedCultureMap = {};
    for (const item of UpdateList.value?.price_list_items?.cultures || []) {
      savedCultureMap[item.culture_id] = item;
    }
    const savedPackageMap = {};
    for (const item of UpdateList.value?.price_list_items?.packages || []) {
      savedPackageMap[item.package_id] = item;
    }

    // Merge all system items with saved prices
    // "Current price" = the lab's customer-facing price (with B2B/original
    // as fallback). Same precedence as everywhere else in the app.
    testItems.value = [
      ...(testsStore.tests || []).map((t) => {
        const saved = savedTestMap[t.id];
        return {
          id: t.id,
          name: t.name,
          shortcut: t.shortcut,
          current_price: t.for_customer_price ?? t.price,
          price_for_customer: saved ? saved.price_for_customer : null,
          price_list_rel_id: saved ? saved.price_list_rel_id : null,
          is_group: false,
        };
      }),
      ...(testGroupsStore.testGroups || []).map((g) => {
        const saved = savedGroupMap[g.id];
        return {
          id: g.id,
          name: g.group_name,
          shortcut: g.shortcut,
          current_price: g.for_customer_price ?? g.original_price,
          price_for_customer: saved ? saved.price_for_customer : null,
          price_list_rel_id: saved ? saved.price_list_rel_id : null,
          is_group: true,
        };
      }),
    ];

    cultureItems.value = (culturesStore.cultures || []).map((c) => {
      const saved = savedCultureMap[c.id];
      return {
        id: c.id,
        name: c.name,
        shortcut: c.shortcut,
        current_price: c.price_for_customer ?? c.for_customer_price ?? c.price,
        price_for_customer: saved ? saved.price_for_customer : null,
        price_list_rel_id: saved ? saved.price_list_rel_id : null,
      };
    });

    packageItems.value = (packagesStore.packagesList || []).map((p) => {
      const saved = savedPackageMap[p.id];
      return {
        id: p.id,
        name: p.name,
        shortcut: p.shortcut,
        current_price: p.for_customer_price ?? p.price,
        price_for_customer: saved ? saved.price_for_customer : null,
        price_list_rel_id: saved ? saved.price_list_rel_id : null,
      };
    });
  }
});

// Filter — name OR shortcut, case/whitespace insensitive
const matchItem = (item, q) => {
  const needle = (q || "").toLowerCase().trim();
  if (!needle) return true;
  return (
    (item.name || "").toLowerCase().includes(needle) ||
    (item.shortcut || "").toLowerCase().includes(needle)
  );
};
const filteredTests = computed(() => testItems.value.filter((t) => matchItem(t, testsSearchQuery.value)));
const filteredCultures = computed(() => cultureItems.value.filter((c) => matchItem(c, cultureSearchQuery.value)));
const filteredPackages = computed(() => packageItems.value.filter((p) => matchItem(p, packageSearchQuery.value)));

const tests_totalPages = computed(() => Math.ceil(filteredTests.value.length / rowsPerPage));
const cultures_totalPages = computed(() => Math.ceil(filteredCultures.value.length / rowsPerPage));
const packages_totalPages = computed(() => Math.ceil(filteredPackages.value.length / rowsPerPage));

const paginatedTests = computed(() => {
  const start = (test_currentPage.value - 1) * rowsPerPage;
  return filteredTests.value.slice(start, start + rowsPerPage);
});

const paginatedCultures = computed(() => {
  const start = (cultures_currentPage.value - 1) * rowsPerPage;
  return filteredCultures.value.slice(start, start + rowsPerPage);
});

const paginatedPackages = computed(() => {
  const start = (packages_currentPage.value - 1) * rowsPerPage;
  return filteredPackages.value.slice(start, start + rowsPerPage);
});

// Reset page to 1 on search
watch(testsSearchQuery, () => { test_currentPage.value = 1; });
watch(cultureSearchQuery, () => { cultures_currentPage.value = 1; });
watch(packageSearchQuery, () => { packages_currentPage.value = 1; });

const test_prevPage = () => {
  if (test_currentPage.value > 1) test_currentPage.value--;
};

const test_nextPage = () => {
  if (test_currentPage.value < tests_totalPages.value) test_currentPage.value++;
};

const packages_prevPage = () => {
  if (packages_currentPage.value > 1) packages_currentPage.value--;
};

const packages_nextPage = () => {
  if (packages_currentPage.value < packages_totalPages.value) packages_currentPage.value++;
};

const cultures_prevPage = () => {
  if (cultures_currentPage.value > 1) cultures_currentPage.value--;
};

const cultures_nextPage = () => {
  if (cultures_currentPage.value < cultures_totalPages.value) cultures_currentPage.value++;
};

const hasPrice = (val) => val != null && val !== "" && !isNaN(val);

const update = () => {
  record.value.tests = testItems.value.filter((t) => !t.is_group && hasPrice(t.price_for_customer)).map((test) => ({
    price_list_rel_id: test.price_list_rel_id,
    id: test.id,
    price: test.price_for_customer,
  }));
  record.value.groups = testItems.value.filter((t) => t.is_group && hasPrice(t.price_for_customer)).map((group) => ({
    price_list_rel_id: group.price_list_rel_id,
    id: group.id,
    price: group.price_for_customer,
  }));
  record.value.cultures = cultureItems.value.filter((c) => hasPrice(c.price_for_customer)).map((culture) => ({
    price_list_rel_id: culture.price_list_rel_id,
    id: culture.id,
    price: culture.price_for_customer,
  }));
  record.value.packages = packageItems.value.filter((p) => hasPrice(p.price_for_customer)).map((pkg) => ({
    price_list_rel_id: pkg.price_list_rel_id,
    id: pkg.id,
    price: pkg.price_for_customer,
  }));

  UpdatepriceList().then(() => {
    alertSuccess(t("alertSuccess"));
    clearObjectValues(record.value);
    updatedialog.value = false;
  });
};

const close = () => {
  clearObjectValues(record.value);
  updatedialog.value = false;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="updatedialog" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close"></div>
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col"
          :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        >
          <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">{{ t("update") }}</h2>
            <button @click="close" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="update" class="flex-1 overflow-y-auto p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("name") }} <span class="text-red-500">*</span></label>
                <input
                  v-model="record.name"
                  type="text"
                  required
                  maxlength="255"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div v-if="!isDiscount">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t("discount") }}</label>
                <input
                  v-model="record.discount"
                  type="number"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="flex items-center">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="isDiscount"
                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                  <span class="text-sm font-medium text-gray-700">{{ t("is_constatnt_price") }}</span>
                </label>
              </div>
            </div>

            <div v-if="isDiscount" class="mt-6">
              <div class="border-b border-gray-200">
                <nav class="flex gap-4">
                  <button
                    type="button"
                    @click="activeTab = 'tests'"
                    :class="[
                      'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                      activeTab === 'tests'
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                  >
                    {{ t("tests") }}
                  </button>
                  <button
                    type="button"
                    @click="activeTab = 'cultures'"
                    :class="[
                      'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                      activeTab === 'cultures'
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                  >
                    {{ t("cultures") }}
                  </button>
                  <button
                    type="button"
                    @click="activeTab = 'packages'"
                    :class="[
                      'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                      activeTab === 'packages'
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                  >
                    {{ t("packages") }}
                  </button>
                </nav>
              </div>

              <!-- Tests Tab -->
              <div v-if="activeTab === 'tests'" class="mt-4">
                <input
                  v-model="testsSearchQuery"
                  type="text"
                  :placeholder="t('search')"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                <div v-if="filteredTests.length > 0" class="mt-4 overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-700 text-white">
                      <tr>
                        <th class="px-4 py-3 text-start">{{ t("name") }}</th>
                        <th class="px-4 py-3 text-start">{{ t("current_price") }}</th>
                        <th class="px-4 py-3 text-start">{{ t("new_price") }}</th>
                      </tr>
                    </thead>
                    <tbody class="bg-gray-50 divide-y divide-gray-200">
                      <tr v-for="test in paginatedTests" :key="test.id + (test.is_group ? '_g' : '_t')" class="hover:bg-gray-100">
                        <td class="px-4 py-3 text-gray-800">
                          <span v-if="test.is_group" class="inline-block px-1.5 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded me-1.5">{{ t("group") }}</span>
                          {{ test.name }}
                          <span v-if="test.shortcut" class="ms-1.5 text-xs text-gray-400 font-mono">({{ test.shortcut }})</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ test.current_price ?? '-' }}</td>
                        <td class="px-4 py-3">
                          <input
                            v-model.number="test.price_for_customer"
                            type="number"
                            :placeholder="t('new_price')"
                            class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="flex justify-center items-center gap-2 mt-4">
                    <button type="button" @click="test_prevPage" :disabled="test_currentPage === 1" class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-100">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <span class="text-sm text-gray-600">{{ test_currentPage }}</span>
                    <button type="button" @click="test_nextPage" :disabled="test_currentPage >= tests_totalPages" class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-100">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                  </div>
                </div>
                <div v-else class="text-center py-8 text-red-500">{{ t("noData") }}</div>
              </div>

              <!-- Cultures Tab -->
              <div v-if="activeTab === 'cultures'" class="mt-4">
                <input
                  v-model="cultureSearchQuery"
                  type="text"
                  :placeholder="t('search')"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                <div v-if="filteredCultures.length > 0" class="mt-4 overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-700 text-white">
                      <tr>
                        <th class="px-4 py-3 text-start">{{ t("name") }}</th>
                        <th class="px-4 py-3 text-start">{{ t("current_price") }}</th>
                        <th class="px-4 py-3 text-start">{{ t("new_price") }}</th>
                      </tr>
                    </thead>
                    <tbody class="bg-gray-50 divide-y divide-gray-200">
                      <tr v-for="culture in paginatedCultures" :key="culture.id" class="hover:bg-gray-100">
                        <td class="px-4 py-3 text-gray-800">
                          {{ culture.name }}
                          <span v-if="culture.shortcut" class="ms-1.5 text-xs text-gray-400 font-mono">({{ culture.shortcut }})</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ culture.current_price ?? '-' }}</td>
                        <td class="px-4 py-3">
                          <input
                            v-model.number="culture.price_for_customer"
                            type="number"
                            :placeholder="t('new_price')"
                            class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="flex justify-center items-center gap-2 mt-4">
                    <button type="button" @click="cultures_prevPage" :disabled="cultures_currentPage === 1" class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-100">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <span class="text-sm text-gray-600">{{ cultures_currentPage }}</span>
                    <button type="button" @click="cultures_nextPage" :disabled="cultures_currentPage >= cultures_totalPages" class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-100">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                  </div>
                </div>
                <div v-else class="text-center py-8 text-red-500">{{ t("noData") }}</div>
              </div>

              <!-- Packages Tab -->
              <div v-if="activeTab === 'packages'" class="mt-4">
                <input
                  v-model="packageSearchQuery"
                  type="text"
                  :placeholder="t('search')"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                <div v-if="filteredPackages.length > 0" class="mt-4 overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-700 text-white">
                      <tr>
                        <th class="px-4 py-3 text-start">{{ t("name") }}</th>
                        <th class="px-4 py-3 text-start">{{ t("current_price") }}</th>
                        <th class="px-4 py-3 text-start">{{ t("new_price") }}</th>
                      </tr>
                    </thead>
                    <tbody class="bg-gray-50 divide-y divide-gray-200">
                      <tr v-for="pkg in paginatedPackages" :key="pkg.id" class="hover:bg-gray-100">
                        <td class="px-4 py-3 text-gray-800">
                          {{ pkg.name }}
                          <span v-if="pkg.shortcut" class="ms-1.5 text-xs text-gray-400 font-mono">({{ pkg.shortcut }})</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ pkg.current_price ?? '-' }}</td>
                        <td class="px-4 py-3">
                          <input
                            v-model.number="pkg.price_for_customer"
                            type="number"
                            :placeholder="t('new_price')"
                            class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="flex justify-center items-center gap-2 mt-4">
                    <button type="button" @click="packages_prevPage" :disabled="packages_currentPage === 1" class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-100">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <span class="text-sm text-gray-600">{{ packages_currentPage }}</span>
                    <button type="button" @click="packages_nextPage" :disabled="packages_currentPage >= packages_totalPages" class="px-3 py-1 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-100">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                  </div>
                </div>
                <div v-else class="text-center py-8 text-red-500">{{ t("noData") }}</div>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
              <button
                type="button"
                @click="close"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
              >
                {{ t("close") }}
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
              >
                {{ t("save") }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
