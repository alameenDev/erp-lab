import axios from 'axios';

export const clientMapApi = axios.create({
  baseURL: import.meta.env.VITE_BASE_URL || '/api',
  withCredentials: true,
  timeout: 20000,
  headers: { Accept: 'application/json' },
});

clientMapApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export function clientMapError(error) {
  const errors = error?.response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(' ');
  return error?.response?.data?.message || 'تعذر إكمال الطلب. تحقق من الاتصال وحاول مجدداً.';
}

export function newClientMapRequestId() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function safeClientAsset(value) {
  if (!value) return null;
  if (typeof value === 'string' && /^\/(?!\/)/.test(value)) return value;
  try {
    const url = new URL(value);
    return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : null;
  } catch {
    return null;
  }
}

export function googleMapsUrl(latitude, longitude) {
  const lat = Number(latitude), lng = Number(longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
  return `https://www.google.com/maps?q=${encodeURIComponent(lat)},${encodeURIComponent(lng)}`;
}

const leafletBase = globalThis.window?.digitalLabDesktop ? '/desktop-vendor/leaflet' : 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist';
let leafletPromise;
export function loadLeaflet() {
  if (globalThis.L?.map) return Promise.resolve(globalThis.L);
  if (leafletPromise) return leafletPromise;

  leafletPromise = new Promise((resolve, reject) => {
    const cssId = 'digital-lab-leaflet-css';
    if (!document.getElementById(cssId)) {
      const link = document.createElement('link');
      link.id = cssId;
      link.rel = 'stylesheet';
      link.href = leafletBase + '/leaflet.css';
      document.head.appendChild(link);
    }

    const existing = document.getElementById('digital-lab-leaflet-js');
    if (existing) {
      existing.addEventListener('load', () => globalThis.L?.map ? resolve(globalThis.L) : reject(new Error('Leaflet unavailable')), { once: true });
      existing.addEventListener('error', () => reject(new Error('Leaflet failed to load')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.id = 'digital-lab-leaflet-js';
    script.src = leafletBase + '/leaflet.js';
    script.async = true;
    script.crossOrigin = 'anonymous';
    script.onload = () => globalThis.L?.map ? resolve(globalThis.L) : reject(new Error('Leaflet unavailable'));
    script.onerror = () => reject(new Error('Leaflet failed to load'));
    document.head.appendChild(script);
  }).catch((error) => {
    leafletPromise = null;
    throw error;
  });

  return leafletPromise;
}
