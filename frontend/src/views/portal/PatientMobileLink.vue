<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({ token: { type: String, required: true } });
const available = ref(false), hasPhone = ref(false), phoneHint = ref('');
const code = ref(''), expires = ref(0), clock = ref(Date.now()), busy = ref(false);
const error = ref(''), message = ref(''), confirmRevoke = ref(false);
let alive = true;
let timer;
const seconds = computed(() => Math.max(0, Math.ceil((expires.value - clock.value) / 1000)));
const formattedCode = computed(() => code.value.match(/.{1,4}/g)?.join(' ') || '');
const endpoint = () => (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '') +
  '/portal/' + encodeURIComponent(props.token) + '/mobile-link';

async function request(method) {
  const response = await fetch(endpoint(), {
    method, credentials: 'omit', cache: 'no-store', headers: { Accept: 'application/json' },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || 'تعذر إكمال الطلب. حاول مرة أخرى.');
  return data;
}
onMounted(async () => {
  try {
    const data = await request('GET');
    if (!alive) return;
    available.value = data.available === true;
    hasPhone.value = data.has_phone === true;
    phoneHint.value = data.phone_hint || '';
  } catch { /* Disabled labs retain their existing portal without an error. */ }
});
onUnmounted(() => { alive = false; clearInterval(timer); code.value = ''; });

async function generate() {
  if (busy.value) return;
  busy.value = true; error.value = ''; message.value = ''; code.value = '';
  clearInterval(timer);
  try {
    const data = await request('POST');
    if (!alive) return;
    code.value = data.code; expires.value = Date.parse(data.expires_at); clock.value = Date.now();
    timer = setInterval(() => {
      clock.value = Date.now();
      if (seconds.value === 0) { code.value = ''; clearInterval(timer); }
    }, 1000);
  } catch (e) { if (alive) error.value = e.message; }
  finally { if (alive) busy.value = false; }
}
async function copy() {
  try { await navigator.clipboard.writeText(code.value); message.value = 'تم نسخ رمز الربط.'; }
  catch { message.value = 'حدد الرمز وانسخه يدوياً.'; }
}
async function revoke() {
  if (!confirmRevoke.value) { confirmRevoke.value = true; return; }
  busy.value = true; error.value = ''; message.value = '';
  try {
    const data = await request('DELETE');
    if (!alive) return;
    code.value = ''; clearInterval(timer); message.value = data.message;
  } catch (e) { if (alive) error.value = e.message; }
  finally { if (alive) { busy.value = false; confirmRevoke.value = false; } }
}
</script>

<template>
  <section v-if="available" class="mobile-link" aria-labelledby="mobile-link-title">
    <div class="mobile-link-heading">
      <span class="mobile-link-icon" aria-hidden="true">↗</span>
      <div><h2 id="mobile-link-title">ملفك معك في تطبيق المختبر الرقمي</h2>
        <p>اربط هذا الملف للاطلاع على تقاريرك ونقاطك من التطبيق.</p></div>
    </div>
    <p v-if="hasPhone">في التطبيق، أدخل رقمك المسجل المنتهي بـ <b dir="ltr">{{ phoneHint }}</b> ورمز الربط أدناه.</p>
    <p v-else>اطلب من المختبر إضافة رقم هاتف صحيح إلى ملفك حتى تتمكن من ربط التطبيق.</p>
    <div v-if="code && seconds" class="mobile-link-code">
      <small>رمز ربط خاص • يستخدم مرة واحدة</small>
      <output dir="ltr" aria-label="رمز الربط">{{ formattedCode }}</output>
      <span>ينتهي خلال {{ Math.floor(seconds / 60) }}:{{ String(seconds % 60).padStart(2, '0') }}</span>
      <button type="button" @click="copy">نسخ الرمز</button>
      <small>احتفظ بالرمز لنفسك؛ يتيح الوصول إلى هذا الملف.</small>
    </div>
    <button v-else-if="hasPhone" type="button" class="mobile-link-primary" :disabled="busy" @click="generate">
      {{ busy ? 'جاري إنشاء الرمز…' : 'إنشاء رمز ربط التطبيق' }}
    </button>
    <p v-if="error" role="alert" class="mobile-link-error">{{ error }}</p>
    <p v-if="message" role="status">{{ message }}</p>
    <div class="mobile-link-revoke">
      <button type="button" :disabled="busy" @click="revoke">
        {{ confirmRevoke ? 'تأكيد إلغاء ربط أجهزة التطبيق' : 'إلغاء ربط أجهزة التطبيق لهذا الملف' }}
      </button>
      <button v-if="confirmRevoke" type="button" :disabled="busy" @click="confirmRevoke = false">تراجع</button>
    </div>
  </section>
</template>

<style scoped>
.mobile-link{padding:22px;border:1px solid #cae8de;border-radius:20px;background:linear-gradient(130deg,#fff,#effbf5);color:#173f38}
.mobile-link-heading{display:flex;gap:12px;align-items:center;margin-bottom:14px}
.mobile-link-heading h2{margin:0;font-size:16px;font-weight:800;line-height:1.7}
.mobile-link p{font-size:13px;line-height:1.9;margin:7px 0;color:#506e66}
.mobile-link-icon{display:grid;place-items:center;background:#d4f5e8;border-radius:14px;width:46px;height:46px;flex:none;font-size:26px}
.mobile-link button{cursor:pointer;border-radius:12px;padding:10px 15px;font:inherit;font-size:13px}
.mobile-link button:disabled{opacity:.5;cursor:wait}
.mobile-link-primary{background:#087f69;color:#fff;border:0;margin-top:9px;width:100%}
.mobile-link-code{display:flex;flex-direction:column;gap:9px;align-items:center;border:1px dashed #89c7b2;background:white;border-radius:16px;padding:18px;margin:15px 0}
.mobile-link-code output{font-family:monospace;font-size:clamp(22px,6vw,32px);font-weight:800;letter-spacing:1px;user-select:all}
.mobile-link-code small,.mobile-link-code span{font-size:12px;color:#536f65;text-align:center}
.mobile-link-code button{background:#e3f6ed;border:0;color:#075642}
.mobile-link .mobile-link-error{background:#fff0f1;color:#a02036;padding:10px;border-radius:9px}
.mobile-link-revoke{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.mobile-link-revoke button{background:transparent;border:0;text-decoration:underline;color:#61786f;padding:6px 0;font-size:11px}
button:focus-visible{outline:3px solid #1ac1a1;outline-offset:3px}
</style>
