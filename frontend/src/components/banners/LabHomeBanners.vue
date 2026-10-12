<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue';
import BannerCard from './BannerCard.vue';
import { bannersApi } from '@/utils/labBanners';
import { currentAnnouncementUser } from '@/utils/labAnnouncements';

const BannerManager = defineAsyncComponent(() => import('./BannerManager.vue'));
const user = ref(currentAnnouncementUser());
const eligible = computed(() => user.value && !user.value.is_referral_partner && !user.value.referral_portal_only && ![3, 5].includes(Number(user.value.role_id)));
const admin = computed(() => eligible.value && Number(user.value?.role_id) === 1);
const managerOpen = ref(false), banners = ref([]), selected = ref(0), now = ref(Date.now());
const live = computed(() => banners.value.filter((banner) => !banner.ends_at || new Date(banner.ends_at).getTime() > now.value));
const active = computed(() => live.value[selected.value] || live.value[0]);
const text = (ar, en) => localStorage.getItem('locale') === 'en' ? en : ar;
const token = localStorage.getItem('token');
let disposed = false, busy = false, stopped = false, timer, clock;
const controller = new AbortController();
const sameSession = () => !disposed && token === localStorage.getItem('token') && String(user.value?.id) === String(currentAnnouncementUser()?.id);
function stop() { stopped = true; clearTimeout(timer); controller.abort(); banners.value = []; managerOpen.value = false; user.value = null; }
async function refresh() {
  clearTimeout(timer);
  if (disposed || stopped || !eligible.value || admin.value) return;
  if (!sameSession()) { stop(); return; }
  if (busy) return;
  if (document.hidden || !navigator.onLine) { timer = setTimeout(refresh, 60000); return; }
  busy = true;
  try {
    const { data } = await bannersApi.get('/lab-banners/active', { signal: controller.signal });
    if (!sameSession()) { stop(); return; }
    const previous = active.value?.id;
    banners.value = Array.isArray(data.data) ? data.data : [];
    now.value = Date.now();
    selected.value = Math.max(0, live.value.findIndex((banner) => banner.id === previous));
  } catch (error) {
    if ([401, 403].includes(error.response?.status)) stop();
    // Advertising is optional. Fail closed without covering or interrupting laboratory work.
    else banners.value = [];
  } finally {
    busy = false;
    if (!disposed && !stopped) timer = setTimeout(refresh, 60000);
  }
}
function resume() { if (!document.hidden) { now.value = Date.now(); refresh(); } }
function storage(event) { if (['user', 'token', null].includes(event.key) && !sameSession()) stop(); }
function move(offset) { selected.value = (Math.max(0, live.value.indexOf(active.value)) + offset + live.value.length) % live.value.length; }
onMounted(() => {
  refresh();
  clock = setInterval(() => { now.value = Date.now(); if (!sameSession()) stop(); }, 1000);
  window.addEventListener('focus', resume); window.addEventListener('online', resume);
  document.addEventListener('visibilitychange', resume); window.addEventListener('storage', storage);
});
onBeforeUnmount(() => {
  disposed = true; clearTimeout(timer); clearInterval(clock); controller.abort();
  window.removeEventListener('focus', resume); window.removeEventListener('online', resume);
  document.removeEventListener('visibilitychange', resume); window.removeEventListener('storage', storage);
});
</script>

<template>
  <section v-if="admin" class="lab-banner-admin-bar" :dir="text('rtl', 'ltr')">
    <div><strong>{{ text('بنرات الصفحة الرئيسية للمختبرات', 'Laboratory homepage banners') }}</strong><small>{{ text('إدارة الصور والتفاصيل وروابط العروض التي تظهر أسفل الإشعارات.', 'Manage images, details and links displayed below administration notifications.') }}</small></div>
    <button type="button" class="lab-banner-manage" @click="managerOpen = true" data-testid="banner-manager-open"><i class="pi pi-image" aria-hidden="true"></i> {{ text('إدارة البنرات الإعلانية', 'Manage advertising banners') }}</button>
    <BannerManager v-if="managerOpen" v-model:visible="managerOpen" />
  </section>
  <section v-else-if="eligible && active" class="lab-home-banners" :dir="text('rtl', 'ltr')" :aria-label="text('إعلانات الإدارة', 'Administration banners')" data-testid="lab-home-banners">
    <BannerCard :banner="active" :key="active.id" />
    <nav v-if="live.length > 1" class="lab-banner-controls" :aria-label="text('التنقل بين البنرات', 'Banner navigation')">
      <button type="button" @click="move(-1)" data-testid="banner-previous">{{ text('السابق', 'Previous') }}</button>
      <span aria-live="polite">{{ live.indexOf(active) + 1 }} / {{ live.length }}</span>
      <button type="button" @click="move(1)" data-testid="banner-next">{{ text('التالي', 'Next') }}</button>
    </nav>
  </section>
</template>

<style scoped>
.lab-home-banners{margin-bottom:1.25rem;min-width:0;width:100%}.lab-banner-admin-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;background:#fff;border:1px solid #dce6ef;border-radius:.85rem;padding:1rem;margin-bottom:1rem;color:#17253b}
.lab-banner-admin-bar small{display:block;color:#64748b;margin-top:.35rem;line-height:1.7}.lab-banner-manage,.lab-banner-controls button{border:1px solid #cbd5e1;border-radius:.65rem;padding:.65rem 1rem;font-weight:600;background:#fff;color:#155e75;cursor:pointer;white-space:normal}
.lab-banner-manage{background:#087f9c;color:#fff;border-color:#087f9c;display:flex;gap:.6rem;align-items:center}.lab-banner-controls{display:flex;justify-content:center;align-items:center;gap:1rem;margin-top:.6rem;color:#53667e;font-size:.85rem}
.lab-banner-manage:focus-visible,.lab-banner-controls button:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}
@media print{.lab-home-banners,.lab-banner-admin-bar{display:none!important}}
</style>
