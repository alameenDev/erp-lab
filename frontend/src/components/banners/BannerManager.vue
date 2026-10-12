<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import Dialog from 'primevue/dialog';
import BannerCard from './BannerCard.vue';
import { bannersApi, bannerLocalDate, bannerUtcDate, validBannerFile } from '@/utils/labBanners';
import { announcementError, currentAnnouncementUser, newAnnouncementRequestId } from '@/utils/labAnnouncements';

const props = defineProps({ visible: Boolean });
const emit = defineEmits(['update:visible']);
const text = (ar, en) => localStorage.getItem('locale') === 'en' ? en : ar;
const result = ref(null), labs = ref([]), loading = ref(false), saving = ref(false), error = ref(''), success = ref('');
const editing = ref(false), original = ref(null), query = ref(''), mobilePreview = ref(false), deleteTarget = ref(null);
const fileInputKey = ref(0), desktopFile = ref(null), mobileFile = ref(null), desktopPreview = ref(''), phonePreview = ref('');
const form = reactive({});
let requestId = '', disposed = false;
const abort = new AbortController();
const token = localStorage.getItem('token'), userId = currentAnnouncementUser()?.id;
const session = () => !disposed && token === localStorage.getItem('token') && userId === currentAnnouncementUser()?.id;
const filteredLabs = computed(() => labs.value.filter((lab) => lab.name.toLowerCase().includes(query.value.trim().toLowerCase())));
const preview = computed(() => ({ title: form.title || text('عنوان البنر', 'Banner title'), description: form.description,
  image_url: mobilePreview.value && !form.remove_mobile_image && (phonePreview.value || original.value?.mobile_image_url)
    ? (phonePreview.value || original.value.mobile_image_url) : (desktopPreview.value || original.value?.image_url),
  mobile_image_url: form.remove_mobile_image ? null : (phonePreview.value || original.value?.mobile_image_url),
  button_label: form.button_label, button_url: form.button_url }));
function revokePreviews() {
  for (const value of [desktopPreview.value, phonePreview.value]) if (value) URL.revokeObjectURL(value);
  desktopPreview.value = ''; phonePreview.value = '';
}
function edit(banner = null) {
  if (saving.value) return;
  revokePreviews(); desktopFile.value = null; mobileFile.value = null; fileInputKey.value++;
  original.value = banner; requestId = newAnnouncementRequestId(); query.value = ''; mobilePreview.value = false; error.value = ''; success.value = '';
  Object.assign(form, { title: banner?.title || '', description: banner?.description || '', button_label: banner?.button_label || '', button_url: banner?.button_url || '',
    audience: banner?.audience || 'all', lab_ids: banner?.targets?.map((lab) => Number(lab.id)) || [], enabled: banner?.enabled ?? true,
    sort_order: banner?.sort_order ?? 0, starts_at: bannerLocalDate(banner?.starts_at), ends_at: bannerLocalDate(banner?.ends_at), remove_mobile_image: false });
  editing.value = true; deleteTarget.value = null;
}
function chooseImage(event, mobile = false) {
  const file = event.target.files?.[0];
  const currentFile = mobile ? mobileFile : desktopFile, currentPreview = mobile ? phonePreview : desktopPreview;
  if (!file) return;
  if (!validBannerFile(file)) {
    error.value = text('اختر صورة JPG أو PNG أو WebP لا تتجاوز 5 ميغابايت.', 'Choose a JPG, PNG or WebP image up to 5 MB.');
    event.target.value = ''; return;
  }
  if (currentPreview.value) URL.revokeObjectURL(currentPreview.value);
  currentFile.value = file; currentPreview.value = URL.createObjectURL(file); error.value = '';
  if (mobile) form.remove_mobile_image = false;
}
function removeMobile() {
  mobileFile.value = null;
  if (phonePreview.value) URL.revokeObjectURL(phonePreview.value);
  phonePreview.value = ''; form.remove_mobile_image = true; fileInputKey.value++;
}
async function load(page = 1) {
  if (!session()) return;
  loading.value = true;
  try {
    const [items, options] = await Promise.all([
      bannersApi.get('/lab-banners', { params: { page }, signal: abort.signal }),
      bannersApi.get('/lab-banners/labs', { signal: abort.signal }),
    ]);
    if (session()) { result.value = items.data; labs.value = options.data; }
  } catch (e) { if (session()) error.value = announcementError(e); }
  finally { if (!disposed) loading.value = false; }
}
async function save() {
  if (saving.value || !session()) return;
  error.value = ''; success.value = '';
  if (!original.value && !desktopFile.value) { error.value = text('ارفع صورة البنر أولاً.', 'Upload the banner image first.'); return; }
  if (form.audience === 'selected' && !form.lab_ids.length) { error.value = text('اختر مختبراً واحداً على الأقل.', 'Select at least one laboratory.'); return; }
  saving.value = true;
  try {
    const body = new FormData();
    for (const key of ['title', 'description', 'button_label', 'button_url', 'audience', 'sort_order']) body.append(key, String(form[key] ?? ''));
    for (const key of ['starts_at', 'ends_at']) body.append(key, bannerUtcDate(form[key]));
    body.append('enabled', form.enabled ? '1' : '0');
    body.append('remove_mobile_image', form.remove_mobile_image ? '1' : '0');
    if (form.audience === 'selected') form.lab_ids.forEach((id) => body.append('lab_ids[]', String(id)));
    if (desktopFile.value) body.append('image', desktopFile.value);
    if (mobileFile.value) body.append('mobile_image', mobileFile.value);
    body.append(original.value ? 'version' : 'request_id', String(original.value ? original.value.version : requestId));
    await bannersApi.post(original.value ? `/lab-banners/${original.value.id}` : '/lab-banners', body, { signal: abort.signal });
    if (!session()) return;
    editing.value = false; revokePreviews();
    success.value = text('تم حفظ البنر. سيظهر للمختبرات المحددة ضمن فترة العرض عند فتح الرئيسية أو تحديثها التلقائي.', 'Banner saved. Eligible laboratories will see it during its display period on opening or automatically refreshing their homepage.');
    await load(1);
  } catch (e) { if (session()) error.value = announcementError(e); }
  finally { if (!disposed) saving.value = false; }
}
async function remove() {
  if (!deleteTarget.value || saving.value || !session()) return;
  saving.value = true; error.value = '';
  try {
    await bannersApi.delete(`/lab-banners/${deleteTarget.value.id}`, { data: { version: deleteTarget.value.version }, signal: abort.signal });
    if (session()) { deleteTarget.value = null; success.value = text('تم حذف البنر من العرض.', 'Banner removed from display.'); await load(1); }
  } catch (e) { if (session()) error.value = announcementError(e); }
  finally { if (!disposed) saving.value = false; }
}
function status(banner) {
  if (!banner.enabled) return text('متوقف', 'Disabled');
  if (banner.ends_at && new Date(banner.ends_at).getTime() <= Date.now()) return text('منتهي', 'Expired');
  if (banner.starts_at && new Date(banner.starts_at).getTime() > Date.now()) return text('مجدول', 'Scheduled');
  return text('فعّال', 'Active');
}
function back() { if (!saving.value) { editing.value = false; error.value = ''; revokePreviews(); load(result.value?.current_page || 1); } }
onMounted(() => load());
onBeforeUnmount(() => { disposed = true; abort.abort(); revokePreviews(); });
</script>

<template>
  <Dialog :visible="props.visible" @update:visible="(open) => { if (!saving) emit('update:visible', open); }" modal :closable="!saving" :closeOnEscape="!saving" :dismissableMask="false"
    :header="text('إدارة البنرات الإعلانية', 'Advertising banner management')" :style="{ width: '72rem', maxWidth: '96vw' }" :pt="{ mask: { class: 'lab-banner-manager-mask' } }">
    <div class="banner-manager" :dir="text('rtl', 'ltr')">
      <p v-if="error" role="alert" class="banner-error" data-testid="banner-error">{{ error }}</p>
      <p v-if="success" role="status" class="banner-success">{{ success }}</p>
      <template v-if="!editing">
        <div class="banner-toolbar"><p>{{ text('تظهر البنرات في رئيسية المختبر فقط، أسفل إشعارات الإدارة.', 'Banners appear only on the laboratory homepage, below administration notifications.') }}</p><button type="button" class="banner-btn primary" :disabled="loading || saving" @click="edit()" data-testid="banner-add">{{ text('إضافة بنر جديد', 'Add new banner') }}</button></div>
        <p v-if="loading" role="status">{{ text('جارٍ التحميل…', 'Loading…') }}</p>
        <p v-if="result && !result.data.length" class="banner-empty">{{ text('لا توجد بنرات بعد. أضف أول بنر للمختبرات.', 'No banners yet. Add the first laboratory banner.') }}</p>
        <article v-for="banner in result?.data || []" :key="banner.id" class="banner-list-item" :data-testid="`banner-item-${banner.id}`">
          <div><strong>{{ banner.title }}</strong><small>{{ status(banner) }} · {{ text('الترتيب', 'Order') }}: {{ banner.sort_order }}</small><small>{{ banner.audience === 'all' ? text('جميع المختبرات', 'All laboratories') : banner.targets.map((lab) => lab.name).join('، ') }}</small></div>
          <div class="banner-actions"><button type="button" class="banner-btn" :disabled="saving" @click="edit(banner)" :data-testid="`banner-edit-${banner.id}`">{{ text('تعديل / معاينة', 'Edit / preview') }}</button><button type="button" class="banner-btn danger" :disabled="saving" @click="deleteTarget = banner" :data-testid="`banner-delete-${banner.id}`">{{ text('حذف', 'Delete') }}</button></div>
        </article>
        <div v-if="deleteTarget" class="banner-confirm" role="alert"><p>{{ text('تأكيد حذف البنر من العرض:', 'Confirm removing this banner:') }} <strong>{{ deleteTarget.title }}</strong></p><div class="banner-actions"><button class="banner-btn danger" :disabled="saving" @click="remove" data-testid="banner-confirm-delete">{{ text('تأكيد الحذف', 'Confirm delete') }}</button><button class="banner-btn" :disabled="saving" @click="deleteTarget = null">{{ text('إلغاء', 'Cancel') }}</button></div></div>
        <nav v-if="result && result.last_page > 1" class="banner-actions"><button class="banner-btn" :disabled="loading || result.current_page <= 1" @click="load(result.current_page - 1)">{{ text('السابق', 'Previous') }}</button><span>{{ result.current_page }} / {{ result.last_page }}</span><button class="banner-btn" :disabled="loading || result.current_page >= result.last_page" @click="load(result.current_page + 1)">{{ text('التالي', 'Next') }}</button></nav>
      </template>
      <form v-else @submit.prevent="save" class="banner-form" data-testid="banner-form">
        <fieldset :disabled="saving" class="banner-fields">
          <h3>{{ original ? text('تعديل البنر', 'Edit banner') : text('إضافة بنر جديد', 'Add new banner') }}</h3>
          <label>{{ text('عنوان البنر', 'Banner title') }}<input v-model="form.title" required maxlength="200" data-testid="banner-title" /></label>
          <label>{{ text('التفاصيل', 'Details') }}<textarea v-model="form.description" rows="3" maxlength="2000" data-testid="banner-description"></textarea></label>
          <div class="banner-grid">
            <label>{{ text('صورة البنر الرئيسية', 'Main banner image') }}<input :key="`desktop-${fileInputKey}`" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseImage($event)" data-testid="banner-upload" /><small>{{ text('مقترح: 1600 × 400 بكسل. حتى 5 ميغابايت؛ JPG، PNG، WebP.', 'Suggested: 1600 × 400 px. Up to 5 MB; JPG, PNG, WebP.') }}</small></label>
            <label>{{ text('صورة للموبايل — اختيارية', 'Mobile image — optional') }}<input :key="`mobile-${fileInputKey}`" type="file" accept="image/jpeg,image/png,image/webp" @change="chooseImage($event, true)" data-testid="banner-mobile-upload" /><small>{{ text('مقترح: 800 × 600 بكسل. بدونها تُستخدم الصورة الرئيسية.', 'Suggested: 800 × 600 px. Otherwise the main image is used.') }}</small><button v-if="(phonePreview || original?.mobile_image_url) && !form.remove_mobile_image" type="button" class="banner-btn" @click="removeMobile">{{ text('إزالة صورة الموبايل', 'Remove mobile image') }}</button></label>
          </div>
          <div class="banner-grid"><label>{{ text('نص الزر — اختياري', 'Button label — optional') }}<input v-model="form.button_label" maxlength="80" :required="Boolean(form.button_url.trim())" :placeholder="text('اعرف أكثر', 'Learn more')" data-testid="banner-button-label" /></label><label>{{ text('رابط الزر', 'Button URL') }}<input v-model="form.button_url" type="url" maxlength="2048" :required="Boolean(form.button_label.trim())" placeholder="https://" dir="ltr" data-testid="banner-button-url" /></label></div>
          <fieldset class="banner-audience"><legend>{{ text('المختبرات المستهدفة', 'Target laboratories') }}</legend><label class="banner-inline"><input type="radio" v-model="form.audience" value="all" data-testid="banner-audience-all" />{{ text('جميع المختبرات الحالية والمستقبلية', 'All current and future laboratories') }}</label><label class="banner-inline"><input type="radio" v-model="form.audience" value="selected" data-testid="banner-audience-selected" />{{ text('مختبر معين أو عدة مختبرات', 'One or multiple selected laboratories') }}</label><template v-if="form.audience === 'selected'"><input v-model="query" :aria-label="text('بحث عن مختبر', 'Search laboratories')" :placeholder="text('بحث باسم المختبر…', 'Search by lab name…')" /><div class="banner-labs"><label v-for="lab in filteredLabs" :key="lab.id" class="banner-inline"><input type="checkbox" v-model="form.lab_ids" :value="Number(lab.id)" :data-testid="`banner-lab-${lab.id}`" />{{ lab.name }}</label></div><small>{{ text('المحدد', 'Selected') }}: {{ form.lab_ids.length }}</small></template></fieldset>
          <div class="banner-grid"><label>{{ text('بداية العرض — اختيارية', 'Display start — optional') }}<input v-model="form.starts_at" type="datetime-local" /></label><label>{{ text('نهاية العرض — اختيارية', 'Display end — optional') }}<input v-model="form.ends_at" type="datetime-local" :min="form.starts_at || undefined" /></label></div>
          <small>{{ text('المواعيد بتوقيت جهازك. تركها فارغة يعني العرض دون تقييد بتاريخ.', 'Times use your device timezone. Empty dates mean no date restriction.') }}</small>
          <div class="banner-grid"><label>{{ text('ترتيب العرض — الرقم الأصغر أولاً', 'Display order — lower numbers first') }}<input v-model.number="form.sort_order" type="number" min="0" max="99999" required /></label><label class="banner-inline"><input v-model="form.enabled" type="checkbox" data-testid="banner-enabled" />{{ text('تفعيل البنر ضمن فترة العرض', 'Enable banner during its display period') }}</label></div>
          <div class="banner-review"><strong>{{ text('معاينة قبل الحفظ', 'Preview before saving') }}</strong><div class="banner-actions"><button type="button" class="banner-btn" :aria-pressed="!mobilePreview" @click="mobilePreview = false">{{ text('حاسبة', 'Desktop') }}</button><button type="button" class="banner-btn" :aria-pressed="mobilePreview" @click="mobilePreview = true" data-testid="banner-preview-mobile">{{ text('موبايل', 'Mobile') }}</button></div><div :class="['banner-preview', { phone: mobilePreview }]"><BannerCard :banner="preview" preview /></div></div>
          <p class="banner-publication-note">{{ form.enabled ? text('عند الحفظ، سيُعرض البنر للجمهور المحدد خلال المواعيد المحددة.', 'Saving publishes this banner to the selected audience during its display period.') : text('سيُحفظ البنر متوقفاً ولن يظهر للمختبرات حتى تفعيله.', 'The banner will be saved disabled and will not appear until enabled.') }}</p>
          <div class="banner-actions"><button type="submit" class="banner-btn primary" data-testid="banner-save">{{ saving ? text('جارٍ الحفظ…', 'Saving…') : text('حفظ البنر', 'Save banner') }}</button><button type="button" class="banner-btn" @click="back">{{ text('رجوع للقائمة', 'Back to list') }}</button></div>
        </fieldset>
      </form>
    </div>
  </Dialog>
</template>

<style>
.banner-manager{min-width:0;color:#17253b}.banner-toolbar,.banner-actions{display:flex;flex-wrap:wrap;align-items:center;gap:.65rem}.banner-toolbar{justify-content:space-between;margin-bottom:1rem}.banner-toolbar p{flex:1 1 20rem;color:#64748b;line-height:1.7}
.banner-btn{padding:.65rem 1rem;border:1px solid #cbd5e1;border-radius:.6rem;background:#fff;color:#334155;font-weight:650;cursor:pointer;white-space:normal}.banner-btn.primary{background:#087f9c;border-color:#087f9c;color:#fff}.banner-btn.danger{color:#b91c1c;border-color:#fecaca}.banner-btn:disabled{opacity:.5;cursor:not-allowed}.banner-btn:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}
.banner-fields{display:grid;gap:1rem;min-width:0;border:0;padding:0}.banner-fields h3{font-size:1.15rem;font-weight:700}.banner-fields>label,.banner-grid>label{display:grid;gap:.4rem;font-weight:600;min-width:0}.banner-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.banner-fields input:not([type=checkbox]):not([type=radio]),.banner-fields textarea{width:100%;max-width:100%;min-width:0;box-sizing:border-box;display:block;border:1px solid #cbd5e1;border-radius:.6rem;padding:.7rem;background:#fff;color:#17253b;font-weight:400}.banner-fields input[type=file]{font-size:.85rem;overflow:hidden}.banner-fields small{color:#64748b;font-size:.8rem;font-weight:400;line-height:1.7}
.banner-audience{display:grid;gap:.8rem;padding:1rem;border:1px solid #dce6ef;border-radius:.75rem;min-width:0}.banner-audience legend{font-weight:650}.banner-inline,.banner-grid>.banner-inline{display:flex;align-items:center;gap:.6rem}.banner-inline input{flex-shrink:0;width:1rem;height:1rem}.banner-labs{max-height:12rem;overflow:auto;display:grid;gap:.7rem;padding:.5rem}
.banner-review{background:#f7f9fc;border:1px solid #e2e8f0;border-radius:.9rem;padding:1rem;min-width:0}.banner-review>.banner-actions{margin-block:.7rem}.banner-preview{width:100%;min-width:0;margin-inline:auto}.banner-preview.phone{max-width:360px}.banner-preview.phone .lab-banner-content{display:grid;grid-template-columns:minmax(0,1fr)}.banner-preview.phone .lab-banner-cta{width:100%}.banner-preview.phone .lab-banner-copy{min-width:0}
.banner-list-item{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem;margin-bottom:.8rem}.banner-list-item>div:first-child{flex:1 1 15rem;min-width:0;overflow-wrap:anywhere}.banner-list-item small{display:block;color:#64748b;line-height:1.8;margin-top:.3rem}
.banner-error,.banner-success,.banner-confirm,.banner-publication-note{padding:.85rem;border-radius:.65rem;line-height:1.8;margin-bottom:.8rem}.banner-error{color:#991b1b;background:#fef2f2}.banner-success{color:#166534;background:#f0fdf4}.banner-confirm{background:#fff7ed;color:#9a3412}.banner-publication-note{background:#ecfeff;color:#155e75}.banner-empty{text-align:center;padding:2.5rem 1rem;color:#64748b}
@media(max-width:640px){.banner-grid{grid-template-columns:minmax(0,1fr)}.banner-toolbar .banner-btn{width:100%}.banner-review{padding:.65rem}}
@media print{.lab-banner-manager-mask{display:none!important}}
</style>
