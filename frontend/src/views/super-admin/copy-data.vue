<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useSuperAdminStore } from "@/store/modules/superAdmin";
import { useLabDataCopyStore } from "@/store/modules/labDataCopy";
import { t, showAlertWithConfirm } from "@/utils/helper";
import { useToast } from "@/composables/useToast";

const router = useRouter();
const superAdmin = useSuperAdminStore();
const copyStore = useLabDataCopyStore();
const toast = useToast();

const { labs } = storeToRefs(superAdmin);
const {
     fromLabId, toLabId, activeType, selection,
     items, itemsLoading, itemsPagination, itemsSearch,
     previewLoading, previewSummary, copying,
} = storeToRefs(copyStore);

const dataTypes = [
     { key: "test", label: t("tests") || "Tests", icon: "🧪" },
     { key: "test_group", label: t("test-groups") || "Test Groups", icon: "📋" },
     { key: "package", label: t("packages") || "Packages", icon: "📦" },
     { key: "culture", label: t("cultures") || "Cultures", icon: "🦠" },
     { key: "category", label: t("categories") || "Categories", icon: "🏷️" },
     { key: "sample", label: t("samples") || "Samples", icon: "💉" },
     { key: "question", label: t("test-questions") || "Questions", icon: "❓" },
     { key: "antibiotic", label: t("Antibiotics") || "Antibiotics", icon: "💊" },
];

const labLabel = (id) => {
     const l = labs.value?.find((x) => x.id === id);
     return l ? l.name : "—";
};

const onSourceChange = () => {
     copyStore.resetSelection();
     copyStore.fetchItems(1);
};

const onTypeChange = (key) => {
     copyStore.activeType = key;
     copyStore.itemsSearch = "";
     copyStore.fetchItems(1);
};

let searchTimer;
const onSearch = () => {
     clearTimeout(searchTimer);
     searchTimer = setTimeout(() => copyStore.fetchItems(1), 300);
};

const visibleItems = computed(() => copyStore.items[activeType.value] || []);
const selectedTotal = computed(() => copyStore.totalSelected());

const allVisibleSelected = computed(() => {
     const visible = visibleItems.value;
     if (!visible.length) return false;
     const sel = new Set(selection.value[activeType.value] || []);
     return visible.every((i) => sel.has(i.id));
});

const toggleAllVisible = () => {
     if (allVisibleSelected.value) copyStore.clearVisible();
     else copyStore.selectAllVisible();
};

const onPageChange = (p) => {
     if (p < 1 || p > itemsPagination.value.last_page) return;
     copyStore.fetchItems(p);
};

const canPreview = computed(() => fromLabId.value && toLabId.value && fromLabId.value !== toLabId.value && selectedTotal.value > 0);

const runPreview = async () => {
     if (!canPreview.value) return;
     try {
          await copyStore.preview();
     } catch (e) {
          toast.error(e?.response?.data?.message || "Preview failed", { duration: 4000 });
     }
};

const runCopy = async () => {
     if (!previewSummary.value) {
          toast.warning(t("run_preview_first") || "Run preview first to see what will be copied", { duration: 3000 });
          return;
     }
     const result = await showAlertWithConfirm(
          (t("copy_lab_data_confirm") || "Copy this data from") +
               ` "${labLabel(fromLabId.value)}" → "${labLabel(toLabId.value)}"?`
     );
     if (!result.value) return;
     try {
          const data = await copyStore.copy();
          toast.success(t("copy_completed") || "Copy completed", { duration: 4000 });
          // Show stats below
          copyStore.copyResult = data;
          copyStore.previewSummary = null;
          copyStore.resetSelection();
     } catch (e) {
          toast.error(e?.response?.data?.message || "Copy failed", { duration: 5000 });
     }
};

const friendlyType = (key) => dataTypes.find((d) => d.key === key)?.label || key;

onMounted(async () => {
     // Load labs list (admin only sees labs of role_id=2)
     await superAdmin.GetLabs({ per_page: 200 });
});

// When user picks a different type tab, fetch items if source is set
watch(activeType, () => {
     if (fromLabId.value) copyStore.fetchItems(1);
});
</script>

<template>
     <div class="space-y-6">
          <!-- Header -->
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
               <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark-mode:text-slate-50 tracking-tight">
                         {{ t("copy_lab_data") || "Copy Lab Data" }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark-mode:text-slate-400 mt-0.5">
                         {{ t("copy_lab_data_desc") || "Bootstrap a new lab quickly by copying tests, packages, and reference data from an existing lab. All relations are copied (questions, ranges, sub-tests, pivot tables) and existing duplicates are reused." }}
                    </p>
               </div>
               <button @click="router.push('/super-admin')" class="self-start text-xs font-semibold text-slate-500 hover:text-primary-600 inline-flex items-center gap-1 cursor-pointer">
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 19l-7-7 7-7" /></svg>
                    {{ t("back") || "Back" }}
               </button>
          </div>

          <!-- Step 1: pick source + destination -->
          <div class="bg-white dark-mode:bg-slate-900 rounded-2xl border border-slate-200 dark-mode:border-slate-800 p-5">
               <div class="flex items-center gap-2 mb-4">
                    <span class="w-7 h-7 rounded-full bg-primary-600 text-white text-sm font-bold flex items-center justify-center">1</span>
                    <h2 class="font-bold text-slate-800 dark-mode:text-slate-100">{{ t("select_labs") || "Select source and destination" }}</h2>
               </div>
               <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-3 items-end">
                    <div>
                         <label class="block text-xs font-semibold text-slate-600 dark-mode:text-slate-400 mb-1.5 uppercase tracking-wider">{{ t("from_lab") || "From lab" }}</label>
                         <select v-model="fromLabId" @change="onSourceChange" class="w-full px-4 py-2.5 bg-slate-50 dark-mode:bg-slate-800 border border-slate-200 dark-mode:border-slate-700 rounded-xl text-slate-700 dark-mode:text-slate-200 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 cursor-pointer">
                              <option :value="null" disabled>{{ t("select_lab") || "Choose source lab" }}</option>
                              <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
                         </select>
                    </div>
                    <div class="flex items-center justify-center pb-2 text-slate-400">
                         <svg class="w-6 h-6 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </div>
                    <div>
                         <label class="block text-xs font-semibold text-slate-600 dark-mode:text-slate-400 mb-1.5 uppercase tracking-wider">{{ t("to_lab") || "To lab" }}</label>
                         <select v-model="toLabId" class="w-full px-4 py-2.5 bg-slate-50 dark-mode:bg-slate-800 border border-slate-200 dark-mode:border-slate-700 rounded-xl text-slate-700 dark-mode:text-slate-200 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 cursor-pointer">
                              <option :value="null" disabled>{{ t("select_lab") || "Choose destination lab" }}</option>
                              <option v-for="l in labs" :key="l.id" :value="l.id" :disabled="l.id === fromLabId">{{ l.name }}</option>
                         </select>
                    </div>
               </div>
               <p v-if="fromLabId && toLabId && fromLabId === toLabId" class="text-xs text-danger-600 mt-2">
                    {{ t("source_dest_must_differ") || "Source and destination must be different labs." }}
               </p>
          </div>

          <!-- Step 2: pick data type + items -->
          <div v-if="fromLabId && toLabId && fromLabId !== toLabId" class="bg-white dark-mode:bg-slate-900 rounded-2xl border border-slate-200 dark-mode:border-slate-800 overflow-hidden">
               <div class="px-5 py-4 border-b border-slate-100 dark-mode:border-slate-800 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-primary-600 text-white text-sm font-bold flex items-center justify-center">2</span>
                    <h2 class="font-bold text-slate-800 dark-mode:text-slate-100">{{ t("pick_items") || "Choose what to copy" }}</h2>
                    <span class="ms-auto text-xs text-slate-500 dark-mode:text-slate-400">
                         {{ selectedTotal }} {{ t("selected") || "selected" }}
                    </span>
               </div>

               <!-- Type tabs -->
               <div class="flex flex-wrap gap-1 p-3 bg-slate-50/60 dark-mode:bg-slate-800/40 border-b border-slate-100 dark-mode:border-slate-800">
                    <button
                         v-for="type in dataTypes"
                         :key="type.key"
                         @click="onTypeChange(type.key)"
                         class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer inline-flex items-center gap-1.5"
                         :class="activeType === type.key
                              ? 'bg-primary-600 text-white'
                              : 'bg-white dark-mode:bg-slate-900 text-slate-600 dark-mode:text-slate-300 hover:bg-slate-100 dark-mode:hover:bg-white/5 border border-slate-200 dark-mode:border-slate-700'"
                    >
                         <span class="text-sm">{{ type.icon }}</span>
                         <span>{{ type.label }}</span>
                         <span v-if="selection[type.key]?.length"
                              class="ms-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full"
                              :class="activeType === type.key ? 'bg-white text-primary-700' : 'bg-primary-100 text-primary-700'"
                         >{{ selection[type.key].length }}</span>
                    </button>
               </div>

               <!-- Search + bulk select -->
               <div class="flex flex-wrap items-center gap-2 p-3 border-b border-slate-100 dark-mode:border-slate-800">
                    <div class="relative flex-1 min-w-[200px]">
                         <input
                              v-model="itemsSearch"
                              @input="onSearch"
                              type="text"
                              :placeholder="t('search') + '...'"
                              class="w-full ps-9 pe-3 py-2 bg-slate-50 dark-mode:bg-slate-800 border border-slate-200 dark-mode:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                         />
                         <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <button
                         @click="toggleAllVisible"
                         class="px-3 py-2 text-xs font-semibold rounded-lg cursor-pointer transition-colors"
                         :class="allVisibleSelected
                              ? 'bg-warning-50 text-warning-700 hover:bg-warning-100 border border-warning-200'
                              : 'bg-primary-50 text-primary-700 hover:bg-primary-100 border border-primary-200'"
                    >
                         {{ allVisibleSelected ? (t("clear_visible") || "Clear visible") : (t("select_all_visible") || "Select all visible") }}
                    </button>
                    <span class="text-xs text-slate-500 tabular-nums">
                         {{ itemsPagination.total || 0 }} {{ t("total") || "total" }}
                    </span>
               </div>

               <!-- Items -->
               <div class="max-h-[500px] overflow-y-auto">
                    <div v-if="itemsLoading" class="p-8 text-center text-sm text-slate-500">
                         {{ t("loading") }}…
                    </div>
                    <div v-else-if="!visibleItems.length" class="p-8 text-center text-sm text-slate-400">
                         {{ t("noData") }}
                    </div>
                    <ul v-else class="divide-y divide-slate-100 dark-mode:divide-slate-800">
                         <li v-for="item in visibleItems" :key="item.id">
                              <label class="flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50 dark-mode:hover:bg-white/5 cursor-pointer transition-colors">
                                   <input
                                        type="checkbox"
                                        :checked="copyStore.isSelected(item.id)"
                                        @change="copyStore.toggleId(item.id)"
                                        class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500 cursor-pointer"
                                   />
                                   <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 dark-mode:text-slate-100 truncate">{{ item.label || "—" }}</p>
                                        <p v-if="item.secondary" class="text-xs text-slate-400 dark-mode:text-slate-500 font-mono truncate">{{ item.secondary }}</p>
                                   </div>
                                   <span class="text-[10px] text-slate-300 dark-mode:text-slate-600 font-mono">#{{ item.id }}</span>
                              </label>
                         </li>
                    </ul>
               </div>

               <!-- Pagination -->
               <div v-if="itemsPagination.last_page > 1" class="flex items-center justify-between px-4 py-2 border-t border-slate-100 dark-mode:border-slate-800 bg-slate-50/50 dark-mode:bg-slate-800/30">
                    <p class="text-xs text-slate-500">
                         {{ itemsPagination.from }}–{{ itemsPagination.to }} / {{ itemsPagination.total }}
                    </p>
                    <div class="flex items-center gap-1">
                         <button @click="onPageChange(itemsPagination.current_page - 1)" :disabled="itemsPagination.current_page <= 1" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark-mode:bg-slate-900 border border-slate-200 dark-mode:border-slate-700 text-slate-600 dark-mode:text-slate-300 disabled:opacity-40 cursor-pointer">‹</button>
                         <span class="text-xs font-bold tabular-nums text-slate-600 dark-mode:text-slate-300 px-2">{{ itemsPagination.current_page }} / {{ itemsPagination.last_page }}</span>
                         <button @click="onPageChange(itemsPagination.current_page + 1)" :disabled="itemsPagination.current_page >= itemsPagination.last_page" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark-mode:bg-slate-900 border border-slate-200 dark-mode:border-slate-700 text-slate-600 dark-mode:text-slate-300 disabled:opacity-40 cursor-pointer">›</button>
                    </div>
               </div>
          </div>

          <!-- Step 3: preview + confirm -->
          <div v-if="canPreview" class="bg-white dark-mode:bg-slate-900 rounded-2xl border border-slate-200 dark-mode:border-slate-800 p-5">
               <div class="flex items-center gap-2 mb-4">
                    <span class="w-7 h-7 rounded-full bg-primary-600 text-white text-sm font-bold flex items-center justify-center">3</span>
                    <h2 class="font-bold text-slate-800 dark-mode:text-slate-100">{{ t("preview_and_copy") || "Preview & copy" }}</h2>
               </div>

               <div class="flex flex-wrap gap-3 mb-4">
                    <button @click="runPreview" :disabled="previewLoading" class="px-4 py-2 rounded-xl bg-info-50 hover:bg-info-100 text-info-700 border border-info-200 text-sm font-semibold cursor-pointer disabled:opacity-50 inline-flex items-center gap-2">
                         <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                         {{ previewLoading ? (t("loading") + "…") : (t("preview") || "Preview") }}
                    </button>
                    <button @click="runCopy" :disabled="!previewSummary || copying" class="px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold cursor-pointer disabled:opacity-50 inline-flex items-center gap-2">
                         <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                         {{ copying ? (t("copying") + "…") : (t("copy_now") || "Copy now") }}
                    </button>
               </div>

               <!-- Preview summary -->
               <div v-if="previewSummary" class="rounded-xl bg-slate-50 dark-mode:bg-slate-800 border border-slate-200 dark-mode:border-slate-700 overflow-hidden">
                    <div class="px-4 py-2 bg-slate-100 dark-mode:bg-slate-800/80 border-b border-slate-200 dark-mode:border-slate-700">
                         <p class="text-xs font-bold text-slate-600 dark-mode:text-slate-300 uppercase tracking-wider">{{ t("what_will_happen") || "What will happen" }}</p>
                    </div>
                    <table class="w-full text-sm">
                         <thead>
                              <tr class="text-xs font-semibold text-slate-500 dark-mode:text-slate-400">
                                   <th class="text-start px-4 py-2">{{ t("type") || "Type" }}</th>
                                   <th class="text-end px-4 py-2">{{ t("total") || "Total" }}</th>
                                   <th class="text-end px-4 py-2">{{ t("will_create") || "Will create" }}</th>
                                   <th class="text-end px-4 py-2">{{ t("reuse_existing") || "Reuse existing" }}</th>
                              </tr>
                         </thead>
                         <tbody class="divide-y divide-slate-200 dark-mode:divide-slate-700">
                              <tr v-for="(s, key) in previewSummary" :key="key">
                                   <td class="px-4 py-2 font-semibold text-slate-700 dark-mode:text-slate-200">{{ friendlyType(key) }}</td>
                                   <td class="px-4 py-2 text-end font-bold tabular-nums text-slate-800 dark-mode:text-slate-100">{{ s.count }}</td>
                                   <td class="px-4 py-2 text-end tabular-nums text-success-600">+{{ s.will_create }}</td>
                                   <td class="px-4 py-2 text-end tabular-nums text-slate-500">{{ s.reuse_existing }}</td>
                              </tr>
                         </tbody>
                    </table>
                    <p v-if="Object.keys(previewSummary).length > Object.keys(selection).filter(k => selection[k]?.length).length" class="px-4 py-2 text-[11px] text-slate-500 italic border-t border-slate-200 dark-mode:border-slate-700">
                         {{ t("auto_dependencies_note") || "Auto-included dependencies are listed alongside your direct picks." }}
                    </p>
               </div>
          </div>

          <!-- Last copy result -->
          <div v-if="copyStore.copyResult" class="bg-success-50 dark-mode:bg-success-500/10 rounded-2xl border border-success-200 dark-mode:border-success-500/20 p-5">
               <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M5 13l4 4L19 7" /></svg>
                    <h2 class="font-bold text-success-800 dark-mode:text-success-300">{{ copyStore.copyResult.message }}</h2>
               </div>
               <table class="w-full text-sm">
                    <tbody class="divide-y divide-success-200/40">
                         <tr v-for="(s, key) in copyStore.copyResult.stats" :key="key" v-show="s.copied || s.reused">
                              <td class="py-1.5 font-semibold text-slate-700">{{ friendlyType(key) }}</td>
                              <td class="py-1.5 text-end tabular-nums text-success-700">+{{ s.copied }} {{ t("created") || "created" }}</td>
                              <td class="py-1.5 text-end tabular-nums text-slate-500">{{ s.reused }} {{ t("reused") || "reused" }}</td>
                         </tr>
                    </tbody>
               </table>
          </div>
     </div>
</template>
