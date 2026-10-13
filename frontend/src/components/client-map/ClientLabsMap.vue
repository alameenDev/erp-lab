<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { clientMapApi, googleMapsUrl, loadLeaflet, safeClientAsset } from '@/utils/clientMap';

const locations = ref([]);
const selected = ref(null);
const mapElement = ref(null);
const mapError = ref(false);
const loading = ref(false);
const disposed = ref(false);
let map;
const markers = new Map();

const lang = computed(() => localStorage.getItem('locale') || 'ar');
const text = (ar, en) => lang.value === 'en' ? en : ar;
const safeLogo = (location) => safeClientAsset(location?.logo_url);
const mapsLink = (location) => googleMapsUrl(location?.latitude, location?.longitude);

function escapeHtml(value = '') {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  }[char]));
}

function markerHtml(location) {
  const logo = safeLogo(location);
  return `<div class="client-map-marker" aria-hidden="true">
    <div class="client-map-marker-disc">${logo ? `<img src="${escapeHtml(logo)}" alt="">` : '<span>DL</span>'}</div>
    <div class="client-map-marker-tail"></div>
  </div>`;
}

function popupNode(location) {
  const root = document.createElement('div');
  root.className = 'client-map-popup';

  const header = document.createElement('div');
  header.className = 'client-map-popup-header';

  const logo = document.createElement('img');
  logo.className = 'client-map-popup-logo';
  logo.alt = '';
  logo.src = safeLogo(location) || '/lad_logo.png';
  header.appendChild(logo);

  const copy = document.createElement('div');
  const name = document.createElement('strong');
  name.textContent = location.name;
  const address = document.createElement('span');
  address.textContent = location.address;
  copy.append(name, address);
  header.appendChild(copy);
  root.appendChild(header);

  const link = document.createElement('a');
  link.href = mapsLink(location);
  link.target = '_blank';
  link.rel = 'noopener noreferrer';
  link.textContent = text('فتح في Google Maps', 'Open in Google Maps');
  root.appendChild(link);

  return root;
}

async function initializeMap() {
  if (!locations.value.length || !mapElement.value || disposed.value) return;

  try {
    const L = await loadLeaflet();
    if (disposed.value || !mapElement.value) return;

    map?.remove?.();
    markers.clear();
    map = L.map(mapElement.value, {
      scrollWheelZoom: false,
      zoomControl: true,
      attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const bounds = L.latLngBounds([]);
    locations.value.forEach((location) => {
      const lat = Number(location.latitude), lng = Number(location.longitude);
      const icon = L.divIcon({
        className: 'client-map-div-icon',
        html: markerHtml(location),
        iconSize: [62, 74],
        iconAnchor: [31, 68],
        popupAnchor: [0, -62],
      });

      const marker = L.marker([lat, lng], {
        icon,
        title: location.name,
        keyboard: true,
      }).addTo(map);

      marker.bindPopup(popupNode(location), { maxWidth: 310, closeButton: true });
      marker.on('click', () => { selected.value = location; });
      markers.set(location.id, marker);
      bounds.extend([lat, lng]);
    });

    if (locations.value.length === 1) {
      map.setView([locations.value[0].latitude, locations.value[0].longitude], 14);
    } else if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [48, 48], maxZoom: 13 });
    }

    setTimeout(() => map?.invalidateSize?.(), 80);
    mapError.value = false;
  } catch (error) {
    console.warn('Client map unavailable:', error);
    mapError.value = true;
  }
}

function focusLocation(location) {
  selected.value = location;
  const marker = markers.get(location.id);
  if (map && marker) {
    map.setView([location.latitude, location.longitude], Math.max(map.getZoom?.() || 12, 14), { animate: true });
    marker.openPopup?.();
  }
}

async function loadLocations() {
  loading.value = true;
  try {
    const { data } = await clientMapApi.get('/public/client-labs');
    if (disposed.value) return;
    locations.value = Array.isArray(data?.data) ? data.data : [];
    selected.value = locations.value[0] || null;
    if (locations.value.length) {
      await nextTick();
      await initializeMap();
    }
  } catch (error) {
    console.warn('Unable to load client laboratories:', error);
  } finally {
    loading.value = false;
  }
}

onMounted(loadLocations);
onBeforeUnmount(() => {
  disposed.value = true;
  map?.remove?.();
  map = null;
  markers.clear();
});
</script>

<template>
  <section
    v-if="locations.length"
    id="client-labs"
    class="client-map-section py-16 sm:py-20 lg:py-28 bg-white relative overflow-hidden"
    :dir="lang === 'en' ? 'ltr' : 'rtl'"
    data-testid="client-map-section"
  >
    <div class="absolute top-0 start-0 w-96 h-96 bg-primary-50/70 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 end-0 w-80 h-80 bg-cyan-50/70 rounded-full blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-3xl mx-auto mb-8 sm:mb-12">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-primary-50 border border-primary-200 rounded-full mb-4">
          <svg class="w-4 h-4 text-primary-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" />
            <circle cx="12" cy="10" r="2.5" />
          </svg>
          <span class="text-xs sm:text-sm font-bold text-primary-700 uppercase tracking-wide">
            {{ text('عملاؤنا', 'Our Clients') }}
          </span>
        </div>
        <h2 class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-900 mb-3">
          {{ text('مختبرات تستخدم منظومة المختبر الرقمي', 'Laboratories Powered by Digital Lab') }}
        </h2>
        <p class="text-sm sm:text-base text-slate-500 leading-relaxed">
          {{ text('استكشف مواقع المختبرات التي تعتمد على منصتنا لإدارة أعمالها وتقديم خدماتها للمرضى.', 'Explore laboratories using our platform to manage operations and serve their patients.') }}
        </p>
      </div>

      <div class="client-map-layout">
        <div class="client-map-shell">
          <div ref="mapElement" class="client-map-canvas" data-testid="client-map-canvas"></div>
          <div v-if="mapError" class="client-map-warning" role="status">
            {{ text('تعذر تحميل الخريطة التفاعلية حالياً، ويمكنك استعراض المختبرات من القائمة.', 'The interactive map is temporarily unavailable. You can still browse laboratories below.') }}
          </div>
        </div>

        <aside v-if="selected" class="client-selected-card" data-testid="client-map-selected">
          <div class="client-selected-logo">
            <img :src="safeLogo(selected) || '/lad_logo.png'" :alt="selected.name" />
          </div>
          <div class="min-w-0">
            <p class="client-selected-kicker">{{ text('مختبر عميل', 'Client Laboratory') }}</p>
            <h3>{{ selected.name }}</h3>
            <p class="client-selected-address">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" />
                <circle cx="12" cy="10" r="2.5" />
              </svg>
              <span>{{ selected.address }}</span>
            </p>
            <a
              v-if="mapsLink(selected)"
              :href="mapsLink(selected)"
              target="_blank"
              rel="noopener noreferrer"
              class="client-map-link"
              data-testid="client-map-google-link"
            >
              {{ text('عرض الموقع على Google Maps', 'View on Google Maps') }}
              <span aria-hidden="true">↗</span>
            </a>
          </div>
        </aside>
      </div>

      <div class="client-map-labs" :aria-label="text('قائمة المختبرات', 'Laboratory list')">
        <button
          v-for="location in locations"
          :key="location.id"
          type="button"
          class="client-map-lab-chip"
          :class="{ active: selected?.id === location.id }"
          @click="focusLocation(location)"
          :data-testid="`client-map-location-${location.id}`"
        >
          <img :src="safeLogo(location) || '/lad_logo.png'" :alt="location.name" />
          <span>
            <strong>{{ location.name }}</strong>
            <small>{{ location.address }}</small>
          </span>
        </button>
      </div>
    </div>
  </section>
</template>

<style>
.client-map-layout{display:grid;grid-template-columns:minmax(0,1fr) 19rem;gap:1rem;align-items:stretch}
.client-map-shell{position:relative;min-width:0;border:1px solid #dbe7ef;border-radius:1.25rem;overflow:hidden;background:#eef5f8;box-shadow:0 18px 50px rgba(15,23,42,.08)}
.client-map-canvas{width:100%;height:31rem;min-height:22rem;z-index:1}
.client-map-warning{position:absolute;z-index:500;inset-inline:1rem;bottom:1rem;padding:.75rem 1rem;border-radius:.8rem;background:rgba(255,255,255,.96);color:#92400e;border:1px solid #fde68a;font-size:.85rem;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.client-selected-card{display:flex;flex-direction:column;justify-content:center;gap:1rem;border:1px solid #dbe7ef;border-radius:1.25rem;background:linear-gradient(145deg,#fff,#f8fbfd);padding:1.25rem;box-shadow:0 18px 50px rgba(15,23,42,.06);min-width:0}
.client-selected-logo{width:5rem;height:5rem;border-radius:1.2rem;background:#fff;border:1px solid #e2e8f0;padding:.5rem;box-shadow:0 8px 20px rgba(15,23,42,.08)}
.client-selected-logo img{width:100%;height:100%;object-fit:contain;border-radius:.8rem}
.client-selected-kicker{font-size:.72rem;font-weight:800;color:#0891b2;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .3rem}
.client-selected-card h3{font-size:1.25rem;line-height:1.4;font-weight:800;color:#0f172a;overflow-wrap:anywhere;margin:0}
.client-selected-address{display:flex;align-items:flex-start;gap:.5rem;color:#64748b;line-height:1.8;font-size:.9rem;margin-top:.65rem}
.client-selected-address svg{width:1rem;height:1rem;flex:0 0 auto;margin-top:.35rem;color:#0891b2}
.client-map-link{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;margin-top:1rem;padding:.7rem .9rem;border-radius:.75rem;background:#0e7490;color:#fff;font-weight:700;font-size:.85rem;text-decoration:none}
.client-map-link:focus-visible,.client-map-lab-chip:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}
.client-map-labs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-top:1rem}
.client-map-lab-chip{display:flex;align-items:center;gap:.7rem;min-width:0;text-align:start;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.75rem;cursor:pointer;transition:border-color .2s,box-shadow .2s,transform .2s}
.client-map-lab-chip.active{border-color:#22a4c3;box-shadow:0 8px 24px rgba(8,145,178,.12)}
.client-map-lab-chip img{width:2.8rem;height:2.8rem;object-fit:contain;border-radius:.75rem;background:#fff;border:1px solid #edf2f7;padding:.25rem;flex:0 0 auto}
.client-map-lab-chip span{min-width:0;display:grid;gap:.2rem}.client-map-lab-chip strong{font-size:.82rem;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.client-map-lab-chip small{font-size:.7rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.client-map-div-icon{background:transparent!important;border:0!important}
.client-map-marker{position:relative;width:62px;height:74px;filter:drop-shadow(0 7px 8px rgba(15,23,42,.25))}
.client-map-marker-disc{position:absolute;inset:0 auto auto 5px;width:52px;height:52px;border-radius:999px;background:#fff;border:3px solid #0891b2;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4px;color:#0e7490;font-weight:900;font-size:.8rem}
.client-map-marker-disc img{width:100%;height:100%;object-fit:contain;border-radius:999px;background:#fff}
.client-map-marker-tail{position:absolute;left:23px;top:46px;width:16px;height:16px;background:#0891b2;transform:rotate(45deg);border-radius:0 0 4px 0;z-index:-1}
.client-map-popup{min-width:220px;padding:.15rem}.client-map-popup-header{display:flex;align-items:center;gap:.7rem}.client-map-popup-logo{width:3rem;height:3rem;object-fit:contain;border-radius:.7rem;border:1px solid #e2e8f0;padding:.2rem}.client-map-popup-header>div{display:grid;gap:.2rem}.client-map-popup-header strong{font-size:.92rem;color:#0f172a}.client-map-popup-header span{font-size:.75rem;color:#64748b;line-height:1.5}.client-map-popup>a{display:inline-block;margin-top:.7rem;color:#0e7490;font-weight:700;text-decoration:none;font-size:.78rem}
@media(hover:hover) and (pointer:fine){.client-map-lab-chip:hover{transform:translateY(-2px);border-color:#67c8dc;box-shadow:0 8px 24px rgba(15,23,42,.08)}}
@media(max-width:1023px){.client-map-layout{grid-template-columns:1fr}.client-selected-card{flex-direction:row;align-items:center;justify-content:flex-start}.client-map-labs{grid-template-columns:repeat(2,minmax(0,1fr))}.client-map-canvas{height:26rem}}
@media(max-width:640px){.client-map-canvas{height:22rem;min-height:20rem}.client-selected-card{align-items:flex-start}.client-selected-logo{width:4rem;height:4rem;flex:0 0 auto}.client-map-labs{grid-template-columns:1fr}.client-map-lab-chip{min-height:4.2rem}.client-map-link{width:100%}}
@media print{.client-map-section{display:none!important}}
</style>
