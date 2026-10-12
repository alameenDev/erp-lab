import axios from 'axios';

// Multipart uploads and background refreshes must not use the application's
// global loader/error redirects, or force application/json onto FormData.
export const bannersApi = axios.create({
  baseURL: import.meta.env.VITE_BASE_URL || '/api',
  withCredentials: true,
  timeout: 30000,
  headers: { Accept: 'application/json' },
});
bannersApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export function safeBannerLink(value) {
  if (!value) return null;
  try {
    const url = new URL(value);
    return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : null;
  } catch { return null; }
}

export function safeBannerImage(value, preview = false) {
  if (preview && typeof value === 'string' && value.startsWith('blob:')) return value;
  if (typeof value === 'string' && /^\/(?!\/)/.test(value)) return value;
  return safeBannerLink(value);
}

export function bannerLocalDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}

export function bannerUtcDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) throw new Error('Invalid date');
  return date.toISOString();
}

export function validBannerFile(file) {
  return file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 5 * 1024 * 1024;
}
