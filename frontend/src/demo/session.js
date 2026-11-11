import { $http } from '@/plugins/axios';
import { useAuthStore } from '@/store/modules/auth';
export function examples() { try { return JSON.parse(localStorage.getItem('demo-examples') || '{}'); } catch { return {}; } }
export async function enterDemo(action = 'resume', identity = 'lab') {
  const { data } = await $http.post('/learning-demo/' + action, { identity });
  if (!data.token || !data.user) throw new Error('تعذر تجهيز حساب التدريب.');
  if (action !== 'resume') {
    localStorage.clear(); localStorage.setItem('locale', 'ar');
  }
  if (data.secret) localStorage.setItem('demo-secret', data.secret);
  localStorage.setItem('demo-examples', JSON.stringify(data.examples));
  localStorage.setItem('demo-expiry', data.expires_at);
  localStorage.setItem('demo-identity', data.identity);
  localStorage.setItem('token', data.token);
  useAuthStore().applyAccess(data);
}
