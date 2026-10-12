<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from "vue";
import { $http } from "@/plugins/axios";
import { t } from "@/utils/helper";
import UiButton from "@/components/ui/Button.vue";

const props = defineProps({ subscriptionId: { type: Number, required: true } });
const emit = defineEmits(["close", "saved"]);
const dialog = ref(null);
const preview = ref(null);
const months = ref(1);
const loading = ref(false);
const saving = ref(false);
const error = ref("");
const needsRefresh = ref(false);
const abort = new AbortController();
const option = computed(() => preview.value?.options.find((item) => item.months === Number(months.value)));
const close = () => { if (!saving.value) emit("close"); };
const message = (e) => e.response?.data?.message || t("renewal_request_failed");

async function load() {
    loading.value = true;
    error.value = "";
    preview.value = null;
    try {
        const { data } = await $http.get(`/subscriptions/${props.subscriptionId}/renewal`, { signal: abort.signal });
        preview.value = data;
        needsRefresh.value = false;
    } catch (e) {
        if (!abort.signal.aborted) error.value = message(e);
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (saving.value || loading.value || needsRefresh.value || !option.value) return;
    saving.value = true;
    error.value = "";
    try {
        const { data } = await $http.post(`/subscriptions/${props.subscriptionId}/renew`, {
            months: Number(months.value), version: preview.value.version,
        });
        emit("saved", data.subscription);
    } catch (e) {
        error.value = message(e);
        // A lost response may already have renewed the subscription. Always
        // reload its state before allowing a second, explicit confirmation.
        needsRefresh.value = true;
    } finally {
        saving.value = false;
    }
}

onMounted(() => { dialog.value.showModal(); load(); });
onBeforeUnmount(() => { abort.abort(); dialog.value?.close(); });
</script>

<template>
    <dialog ref="dialog" class="renewal-dialog" aria-labelledby="renewal-title" @cancel.prevent="close">
        <form @submit.prevent="save">
            <header class="renewal-header">
                <h2 id="renewal-title" class="text-lg font-bold text-slate-900">{{ t("renew_subscription") }}</h2>
                <button type="button" :disabled="saving" :aria-label="t('close')" class="text-2xl text-slate-500 px-2 rounded-lg focus:ring-2" @click="close">×</button>
            </header>
            <div class="p-5 sm:p-6 space-y-5" :aria-busy="loading || saving">
                <p v-if="loading" role="status" class="text-slate-500 py-8 text-center">{{ t("loading") }}</p>
                <div v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 space-y-3">
                    <p>{{ error }}</p>
                    <UiButton variant="outline" size="sm" :disabled="loading || saving" @click="load">{{ t("renewal_refresh") }}</UiButton>
                </div>
                <template v-if="preview">
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-1">
                        <p class="font-bold text-slate-900 break-words">{{ preview.subscription.lab?.name }}</p>
                        <p class="text-sm text-slate-600 break-words">{{ t("plan_name") }}: {{ preview.subscription.plan_name }}</p>
                        <p class="text-sm text-slate-600">{{ t("current_expiry") }}: <bdi>{{ preview.subscription.end_date?.split('T')[0] }}</bdi></p>
                    </div>
                    <div>
                        <label for="renewal-months" class="block text-sm font-semibold text-slate-700 mb-2">{{ t("renewal_duration") }}</label>
                        <select id="renewal-months" v-model.number="months" :disabled="saving || needsRefresh" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-blue-500">
                            <option v-for="item in preview.options" :key="item.months" :value="item.months">{{ t(`renewal_months_${item.months}`) }}</option>
                        </select>
                    </div>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div><dt class="text-sm text-emerald-800">{{ t("renewal_start") }}</dt><dd class="font-bold text-emerald-950 mt-1" data-testid="renewal-start"><bdi>{{ preview.renewal_start_date }}</bdi></dd></div>
                        <div><dt class="text-sm text-emerald-800">{{ t("new_expiry") }}</dt><dd class="font-bold text-emerald-950 mt-1" data-testid="renewal-end"><bdi>{{ option?.end_date }}</bdi></dd></div>
                    </dl>
                    <p class="text-sm text-slate-600 leading-6">{{ t(preview.continues_current_term ? "renewal_keeps_days" : "renewal_starts_today") }} {{ t("renewal_keeps_plan") }}</p>
                </template>
            </div>
            <footer class="renewal-footer">
                <UiButton variant="outline" :disabled="saving" @click="close">{{ t("cancel") }}</UiButton>
                <UiButton type="submit" :loading="saving" :disabled="loading || needsRefresh || !option">{{ t("confirm_renewal") }}</UiButton>
            </footer>
        </form>
    </dialog>
</template>

<style scoped>
.renewal-dialog { width: min(560px, calc(100vw - 24px)); max-height: calc(100dvh - 32px); margin: auto; padding: 0; border: 0; border-radius: 16px; overflow-y: auto; box-shadow: 0 24px 80px #0f172a40; }
.renewal-dialog::backdrop { background: #0f172a80; backdrop-filter: blur(3px); }
.renewal-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
.renewal-footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; padding: 16px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
</style>
