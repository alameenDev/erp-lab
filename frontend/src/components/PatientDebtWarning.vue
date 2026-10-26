<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useAuthStore } from '@/store/modules/auth';
import { readStoredJson } from '@/utils/accessControl';

const props = defineProps({ invoice: Object, active: Boolean });
const emit = defineEmits(['checking']);
const auth = useAuthStore();
const balance = ref(null), loading = ref(false), error = ref(false);
const permitted = computed(() => {
  const user = readStoredJson('user', {});
  if (user?.is_referral_partner || [3, 5].includes(Number(user?.role_id))) return false;
  return Number(user?.role_id) === 1 || ['invoices view', 'invoices create', 'invoices edit', 'invoices print',
    'medical reports results view', 'medical reports update', 'invoices send whatsapp'].some(p => auth.havePermission(p));
});
const enabled = computed(() => props.active && permitted.value && !props.invoice?.referral_document && !!props.invoice?.id);
const money = value => new Intl.NumberFormat('ar-IQ').format(Number(value) || 0);
let controller, revision = 0;
const checking = value => { loading.value = value; emit('checking', value); };

async function refresh() {
  const current = ++revision;
  controller?.abort();
  balance.value = null; error.value = false; checking(false);
  const token = localStorage.getItem('token');
  if (!enabled.value || !token) return;
  controller = new AbortController();
  const request = controller;
  const timeout = setTimeout(() => request.abort(), 8000);
  checking(true);
  try {
    // An optional balance check must not invoke global loader/login/error redirects.
    const base = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
    const response = await fetch(`${base}/invoices/${props.invoice.id}/patient-balance`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      credentials: 'include', cache: 'no-store', signal: request.signal,
    });
    if (!response.ok) throw new Error('Balance unavailable');
    const data = await response.json();
    if (String(data.invoice_id) !== String(props.invoice.id) || !Number.isFinite(Number(data.outstanding_amount))) throw new Error('Invalid balance');
    if (current === revision && token === localStorage.getItem('token')) balance.value = data;
  } catch {
    if (current === revision) error.value = true;
  } finally {
    clearTimeout(timeout);
    if (current === revision) checking(false);
  }
}
watch([() => props.active, () => props.invoice?.id, () => props.invoice?.referral_document, permitted], refresh, { immediate: true });
onBeforeUnmount(() => { revision++; controller?.abort(); });
</script>

<template>
  <div v-if="enabled && loading" role="status" class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600" data-patient-debt-loading>جاري التحقق من المبالغ المتبقية على المريض...</div>
  <div v-else-if="enabled && error" role="alert" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" data-patient-debt-error>
    تعذر التحقق من مديونية المريض. راجع الحساب قبل الطباعة.
    <button type="button" class="ms-2 font-bold underline" @click="refresh">إعادة المحاولة</button>
  </div>
  <section v-else-if="enabled && balance?.outstanding_amount > 0" role="alert" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950" data-patient-debt-warning>
    <h3 class="font-bold">تنبيه: توجد فواتير غير مسددة لهذا المريض</h3>
    <p class="mt-2 text-lg font-bold">المبلغ المطلوب دفعه: <span data-patient-debt-total>{{ money(balance.outstanding_amount) }} د.ع</span></p>
    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
      <span>هذه الفاتورة: {{ money(balance.current_invoice_amount) }} د.ع</span>
      <span>فواتير أخرى: {{ money(balance.other_invoices_amount) }} د.ع</span>
      <span>عدد الفواتير غير المسددة: {{ balance.unpaid_invoice_count }}</span>
    </div>
  </section>
</template>
