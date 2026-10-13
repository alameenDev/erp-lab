<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import Dialog from 'primevue/dialog';
import { clientMapApi, clientMapError, googleMapsUrl, newClientMapRequestId, safeClientAsset } from '@/utils/clientMap';

const managerOpen = ref(false);
const editorOpen = ref(false);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const list = ref(null);
const deleteId = ref(null);
const previewUrl = ref(null);

const lang = computed(() => localStorage.getItem('locale') || 'ar');
const text = (ar, en) => lang.value === 'en' ? en : ar;

const freshForm = () => ({
  id: null,
  version: null,
  request_id: newClientMapRequestId(),
  name: '',
  address: '',
  latitude: '',
  longitude: '',
  enabled: true,
  sort_order: 0,
  logo: null,
  existing_logo: null,
});

const form = ref(freshForm());

function cleanupPreview() {
  if (previewUrl.value?.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = null;
}

function openCreate() {
  cleanupPreview();
  form.value = freshForm();
  error.value = '';
  success.value = '';
  editorOpen.value = true;
}

function openEdit(item) {
  cleanupPreview();
  form.value = {
    id: item.id,
    version: item.version,
    request_id: null,
    name: item.name,
    address: item.address,
    latitude: item.latitude,
    longitude: item.longitude,
    enabled: Boolean(item.enabled),
    sort_order: Number(item.sort_order || 0),
    logo: null,
    existing_logo: safeClientAsset(item.logo_url),
  };
  previewUrl.value = form.value.existing_logo;
  error.value = '';
  success.value = '';
  editorOpen.value = true;
}

function onLogo(event) {
  cleanupPreview();
  const file = event.target.files?.[0] || null;
  if (!file) {
    form.value.logo = null;
    previewUrl.value = form.value.existing_logo;
    return;
  }

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
    event.target.value = '';
    form.value.logo = null;
    previewUrl.value = form.value.existing_logo;
    error.value = text('استخدم صورة JPG أو PNG أو WebP بحجم لا يتجاوز 5MB.', 'Use a JPG, PNG or WebP image up to 5MB.');
    return;
  }

  form.value.logo = file;
  previewUrl.value = URL.createObjectURL(file);
  error.value = '';
}

const coordinatesValid = computed(() => {
  const lat = Number(form.value.latitude), lng = Number(form.value.longitude);
  return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
});

const coordinateLink = computed(() => coordinatesValid.value ? googleMapsUrl(form.value.latitude, form.value.longitude) : null);

async function load(page = 1) {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await clientMapApi.get('/super-admin/client-labs', { params: { page } });
    list.value = data;
  } catch (e) {
    error.value = clientMapError(e);
  } finally {
    loading.value = false;
  }
}

async function openManager() {
  managerOpen.value = true;
  success.value = '';
  await load();
}

async function save() {
  if (saving.value) return;
  error.value = '';
  success.value = '';

  if (!form.value.name.trim() || !form.value.address.trim()) {
    error.value = text('أدخل اسم المختبر وعنوانه.', 'Enter the laboratory name and address.');
    return;
  }
  if (!coordinatesValid.value) {
    error.value = text('تأكد من خط العرض والطول.', 'Check the latitude and longitude.');
    return;
  }
  if (!form.value.id && !form.value.logo) {
    error.value = text('ارفع شعار المختبر.', 'Upload the laboratory logo.');
    return;
  }

  saving.value = true;
  const data = new FormData();
  data.append('name', form.value.name.trim());
  data.append('address', form.value.address.trim());
  data.append('latitude', String(Number(form.value.latitude)));
  data.append('longitude', String(Number(form.value.longitude)));
  data.append('enabled', form.value.enabled ? '1' : '0');
  data.append('sort_order', String(Number(form.value.sort_order || 0)));
  if (form.value.logo) data.append('logo', form.value.logo, form.value.logo.name);
  if (form.value.id) data.append('version', String(form.value.version));
  else data.append('request_id', form.value.request_id);

  try {
    const url = form.value.id ? `/super-admin/client-labs/${form.value.id}` : '/super-admin/client-labs';
    await clientMapApi.post(url, data);
    success.value = text('تم حفظ المختبر على خريطة العملاء.', 'Laboratory map details saved.');
    editorOpen.value = false;
    cleanupPreview();
    await load(list.value?.current_page || 1);
  } catch (e) {
    error.value = clientMapError(e);
  } finally {
    saving.value = false;
  }
}

async function remove(item) {
  if (deleteId.value !== item.id) {
    deleteId.value = item.id;
    return;
  }

  loading.value = true;
  error.value = '';
  try {
    await clientMapApi.delete(`/super-admin/client-labs/${item.id}`, { data: { version: item.version } });
    deleteId.value = null;
    success.value = text('تم حذف المختبر من الخريطة.', 'Laboratory removed from the map.');
    await load(list.value?.current_page || 1);
  } catch (e) {
    error.value = clientMapError(e);
  } finally {
    loading.value = false;
  }
}

onBeforeUnmount(cleanupPreview);
</script>

<template>
  <section class="client-map-admin-card" :dir="lang === 'en' ? 'ltr' : 'rtl'">
    <div class="client-map-admin-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" />
        <circle cx="12" cy="10" r="2.5" />
      </svg>
    </div>
    <div class="min-w-0 flex-1">
      <h3>{{ text('خريطة مختبرات العملاء', 'Client Laboratory Map') }}</h3>
      <p>{{ text('أضف اسم المختبر والعنوان والإحداثيات والشعار ليظهر على الخريطة العامة للموقع.', 'Add laboratory names, addresses, coordinates and logos for the public website map.') }}</p>
    </div>
    <button type="button" class="client-map-admin-primary" @click="openManager" data-testid="client-map-manager-open">
      {{ text('إدارة الخريطة', 'Manage Map') }}
    </button>

    <Dialog
      v-model:visible="managerOpen"
      modal
      :header="text('إدارة مختبرات العملاء على الخريطة', 'Manage Client Laboratories on Map')"
      :style="{ width: '70rem', maxWidth: '96vw' }"
    >
      <div class="client-map-manager" :dir="lang === 'en' ? 'ltr' : 'rtl'">
        <div class="client-map-manager-toolbar">
          <div>
            <strong>{{ text('المختبرات الظاهرة في الموقع', 'Laboratories shown on the website') }}</strong>
            <small>{{ text('فقط المختبرات المفعلة تظهر للعامة.', 'Only enabled laboratories are public.') }}</small>
          </div>
          <button type="button" class="client-map-admin-primary" @click="openCreate" data-testid="client-map-add">
            + {{ text('إضافة مختبر', 'Add Laboratory') }}
          </button>
        </div>

        <p v-if="error" class="client-map-admin-error" role="alert" data-testid="client-map-error">{{ error }}</p>
        <p v-if="success" class="client-map-admin-success" role="status">{{ success }}</p>
        <p v-if="loading" class="client-map-admin-loading">{{ text('جارٍ التحميل…', 'Loading…') }}</p>
        <p v-if="list && !list.data.length && !loading" class="client-map-admin-empty">{{ text('لم تتم إضافة مختبرات بعد.', 'No laboratories have been added yet.') }}</p>

        <div class="client-map-admin-list">
          <article v-for="item in list?.data || []" :key="item.id" class="client-map-admin-item" :data-testid="`client-map-item-${item.id}`">
            <img :src="safeClientAsset(item.logo_url) || '/lad_logo.png'" :alt="item.name" />
            <div class="min-w-0">
              <div class="client-map-admin-title-row">
                <strong>{{ item.name }}</strong>
                <span :class="item.enabled ? 'client-map-status-on' : 'client-map-status-off'">
                  {{ item.enabled ? text('ظاهر', 'Visible') : text('مخفي', 'Hidden') }}
                </span>
              </div>
              <p>{{ item.address }}</p>
              <small>{{ Number(item.latitude).toFixed(6) }}, {{ Number(item.longitude).toFixed(6) }}</small>
            </div>
            <div class="client-map-admin-actions">
              <a :href="googleMapsUrl(item.latitude, item.longitude)" target="_blank" rel="noopener noreferrer">
                {{ text('الخريطة', 'Map') }}
              </a>
              <button type="button" @click="openEdit(item)" :data-testid="`client-map-edit-${item.id}`">{{ text('تعديل', 'Edit') }}</button>
              <button type="button" class="danger" @click="remove(item)" :data-testid="`client-map-delete-${item.id}`">
                {{ deleteId === item.id ? text('تأكيد الحذف', 'Confirm Delete') : text('حذف', 'Delete') }}
              </button>
            </div>
          </article>
        </div>

        <div v-if="list && list.last_page > 1" class="client-map-admin-pagination">
          <button type="button" :disabled="loading || list.current_page <= 1" @click="load(list.current_page - 1)">{{ text('السابق', 'Previous') }}</button>
          <span>{{ list.current_page }} / {{ list.last_page }}</span>
          <button type="button" :disabled="loading || list.current_page >= list.last_page" @click="load(list.current_page + 1)">{{ text('التالي', 'Next') }}</button>
        </div>
      </div>
    </Dialog>

    <Dialog
      v-model:visible="editorOpen"
      modal
      :header="form.id ? text('تعديل مختبر', 'Edit Laboratory') : text('إضافة مختبر إلى الخريطة', 'Add Laboratory to Map')"
      :style="{ width: '44rem', maxWidth: '96vw' }"
      @hide="cleanupPreview"
    >
      <form class="client-map-editor" :dir="lang === 'en' ? 'ltr' : 'rtl'" @submit.prevent="save">
        <p v-if="error" class="client-map-admin-error" role="alert">{{ error }}</p>

        <div class="client-map-form-grid">
          <label class="client-map-form-wide">
            <span>{{ text('اسم المختبر', 'Laboratory Name') }}</span>
            <input v-model="form.name" maxlength="180" required data-testid="client-map-name" />
          </label>

          <label class="client-map-form-wide">
            <span>{{ text('عنوان المختبر', 'Laboratory Address') }}</span>
            <textarea v-model="form.address" maxlength="500" rows="2" required data-testid="client-map-address"></textarea>
          </label>

          <label>
            <span>{{ text('Latitude - خط العرض', 'Latitude') }}</span>
            <input v-model="form.latitude" type="number" min="-90" max="90" step="0.0000001" required data-testid="client-map-latitude" />
          </label>

          <label>
            <span>{{ text('Longitude - خط الطول', 'Longitude') }}</span>
            <input v-model="form.longitude" type="number" min="-180" max="180" step="0.0000001" required data-testid="client-map-longitude" />
          </label>

          <div class="client-map-coordinate-help client-map-form-wide">
            <span>{{ text('يمكنك نسخ الإحداثيات مباشرة من Google Maps، مثال: 33.312805, 44.361488', 'Copy coordinates directly from Google Maps, e.g. 33.312805, 44.361488') }}</span>
            <a v-if="coordinateLink" :href="coordinateLink" target="_blank" rel="noopener noreferrer">{{ text('معاينة الموقع', 'Preview Location') }}</a>
          </div>

          <label class="client-map-form-wide">
            <span>{{ form.id ? text('استبدال شعار المختبر (اختياري)', 'Replace Laboratory Logo (Optional)') : text('شعار المختبر', 'Laboratory Logo') }}</span>
            <input type="file" accept="image/png,image/jpeg,image/webp" @change="onLogo" :required="!form.id" data-testid="client-map-logo" />
            <small>{{ text('JPG / PNG / WebP — الحد الأقصى 5MB', 'JPG / PNG / WebP — max 5MB') }}</small>
          </label>

          <div v-if="previewUrl" class="client-map-logo-preview client-map-form-wide">
            <img :src="previewUrl" :alt="form.name || text('معاينة الشعار', 'Logo preview')" />
          </div>

          <label>
            <span>{{ text('ترتيب الظهور', 'Display Order') }}</span>
            <input v-model.number="form.sort_order" type="number" min="0" max="99999" />
          </label>

          <label class="client-map-switch">
            <input v-model="form.enabled" type="checkbox" />
            <span>{{ text('إظهار المختبر في الموقع العام', 'Show laboratory on public website') }}</span>
          </label>
        </div>

        <div class="client-map-editor-actions">
          <button type="button" class="client-map-admin-secondary" :disabled="saving" @click="editorOpen = false">{{ text('إلغاء', 'Cancel') }}</button>
          <button type="submit" class="client-map-admin-primary" :disabled="saving" data-testid="client-map-save">
            {{ saving ? text('جارٍ الحفظ…', 'Saving…') : text('حفظ المختبر', 'Save Laboratory') }}
          </button>
        </div>
      </form>
    </Dialog>
  </section>
</template>

<style scoped>
.client-map-admin-card{display:flex;align-items:center;gap:1rem;background:linear-gradient(135deg,#fff,#f6fbfd);border:1px solid #dbe7ef;border-radius:1rem;padding:1.1rem 1.25rem;box-shadow:0 4px 16px rgba(15,23,42,.04)}
.client-map-admin-icon{width:3rem;height:3rem;display:flex;align-items:center;justify-content:center;border-radius:.9rem;background:#ecfeff;color:#0e7490;flex:0 0 auto}.client-map-admin-icon svg{width:1.5rem;height:1.5rem}
.client-map-admin-card h3{font-size:1rem;font-weight:800;color:#172033;margin:0}.client-map-admin-card p{font-size:.82rem;color:#64748b;margin:.25rem 0 0;line-height:1.6}
.client-map-admin-primary,.client-map-admin-secondary,.client-map-admin-actions button,.client-map-admin-actions a,.client-map-admin-pagination button{border-radius:.7rem;padding:.65rem 1rem;font-weight:700;font-size:.82rem;cursor:pointer;border:1px solid #cbd5e1;text-decoration:none}
.client-map-admin-primary{background:#0e7490;color:#fff;border-color:#0e7490}.client-map-admin-secondary{background:#fff;color:#334155}
.client-map-manager{display:grid;gap:1rem}.client-map-manager-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.client-map-manager-toolbar strong{display:block;color:#172033}.client-map-manager-toolbar small{display:block;color:#64748b;margin-top:.25rem}
.client-map-admin-error,.client-map-admin-success{padding:.75rem .9rem;border-radius:.7rem;font-size:.82rem}.client-map-admin-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.client-map-admin-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}.client-map-admin-loading,.client-map-admin-empty{color:#64748b;font-size:.85rem;padding:1rem;text-align:center}
.client-map-admin-list{display:grid;gap:.7rem}.client-map-admin-item{display:grid;grid-template-columns:3.5rem minmax(0,1fr) auto;gap:.85rem;align-items:center;border:1px solid #e2e8f0;border-radius:.9rem;padding:.8rem;background:#fff}.client-map-admin-item>img{width:3.5rem;height:3.5rem;object-fit:contain;border:1px solid #e2e8f0;border-radius:.8rem;padding:.25rem}.client-map-admin-title-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.client-map-admin-item strong{color:#1e293b;font-size:.9rem}.client-map-admin-item p{color:#64748b;font-size:.78rem;margin:.2rem 0}.client-map-admin-item small{color:#94a3b8;font-size:.72rem}
.client-map-status-on,.client-map-status-off{font-size:.68rem;padding:.2rem .45rem;border-radius:999px;font-weight:800}.client-map-status-on{background:#ecfdf5;color:#047857}.client-map-status-off{background:#f1f5f9;color:#64748b}
.client-map-admin-actions{display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end}.client-map-admin-actions button,.client-map-admin-actions a{background:#fff;color:#334155;padding:.45rem .65rem}.client-map-admin-actions .danger{color:#b91c1c;border-color:#fecaca;background:#fff7f7}
.client-map-admin-pagination{display:flex;justify-content:center;align-items:center;gap:.8rem}.client-map-admin-pagination button{background:#fff;color:#334155}.client-map-admin-pagination button:disabled,.client-map-admin-primary:disabled,.client-map-admin-secondary:disabled{opacity:.5;cursor:not-allowed}
.client-map-editor{display:grid;gap:1rem}.client-map-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.client-map-form-grid label{display:grid;gap:.4rem;font-weight:700;color:#334155;font-size:.82rem}.client-map-form-grid input:not([type=checkbox]),.client-map-form-grid textarea{width:100%;border:1px solid #cbd5e1;border-radius:.65rem;padding:.7rem .8rem;background:#fff;color:#0f172a;font-size:1rem}.client-map-form-wide{grid-column:1/-1}.client-map-form-grid small{color:#94a3b8;font-weight:500}.client-map-coordinate-help{display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;padding:.75rem;border-radius:.7rem;background:#f8fafc;border:1px dashed #cbd5e1;color:#64748b;font-size:.78rem}.client-map-coordinate-help a{color:#0e7490;font-weight:800}
.client-map-logo-preview{display:flex;align-items:center;justify-content:center;padding:.8rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.8rem}.client-map-logo-preview img{width:6rem;height:6rem;object-fit:contain;background:#fff;border-radius:1rem;padding:.4rem;border:1px solid #e2e8f0}
.client-map-switch{display:flex!important;align-items:center;gap:.65rem;align-self:end;min-height:2.8rem}.client-map-switch input{width:1.1rem;height:1.1rem}.client-map-editor-actions{display:flex;justify-content:flex-end;gap:.6rem;flex-wrap:wrap}
.client-map-admin-primary:focus-visible,.client-map-admin-secondary:focus-visible,.client-map-admin-actions button:focus-visible,.client-map-admin-actions a:focus-visible,.client-map-admin-pagination button:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}
@media(max-width:760px){.client-map-admin-card{align-items:flex-start;flex-wrap:wrap}.client-map-admin-card>.client-map-admin-primary{width:100%}.client-map-admin-item{grid-template-columns:3rem minmax(0,1fr)}.client-map-admin-item>img{width:3rem;height:3rem}.client-map-admin-actions{grid-column:1/-1;justify-content:flex-start}.client-map-form-grid{grid-template-columns:1fr}.client-map-form-wide{grid-column:auto}.client-map-switch{align-self:auto}}
</style>
