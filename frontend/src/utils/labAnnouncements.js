import axios from 'axios';

// Independent client: background checks must never activate the global page loader
// or redirect a user who is entering results when a request temporarily fails.
export const announcementsApi = axios.create({
  baseURL: import.meta.env.VITE_BASE_URL || '/api',
  withCredentials: true,
  timeout: 15000,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});
announcementsApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export function currentAnnouncementUser() {
  try {
    if (!localStorage.getItem('token')) return null;
    return JSON.parse(localStorage.getItem('user') || 'null');
  } catch { return null; }
}

export function safeAnnouncementUrl(value) {
  if (!value) return null;
  try {
    const url = new URL(value);
    return ['https:', 'http:'].includes(url.protocol) ? url.href : null;
  } catch { return null; }
}

export function announcementError(error) {
  const errors = error?.response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(' ');
  return error?.response?.data?.message || 'تعذر إكمال الطلب. تحقق من الاتصال وحاول مجدداً.';
}

export function newAnnouncementRequestId() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function announcementDate(value) {
  if (!value) return '—';
  // This feature persists timestamps in UTC; query-builder dates do not carry a suffix.
  const normalized = /(?:Z|[+-]\d{2}:?\d{2})$/.test(value) ? value : `${value.replace(' ', 'T')}Z`;
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString(localStorage.getItem('locale') === 'en' ? 'en-GB' : 'ar-IQ');
}
