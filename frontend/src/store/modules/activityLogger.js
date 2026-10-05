import { defineStore } from 'pinia';
import { $http } from '@/plugins/axios';

export const ActivityStore = defineStore('activity', {
  state: () => ({ records: [], totalCount: 0, pagination: { current_page: 1, last_page: 1, total: 0 }, stats: {}, loading: false, error: '', requestNumber: 0 }),
  actions: {
    async GetRecords(params = {}) {
      const request = ++this.requestNumber;
      this.loading = true; this.error = '';
      try {
        const { data } = await $http.get('/activity/show', { params });
        if (request !== this.requestNumber) return;
        this.records = data.data || [];
        this.pagination = data.pagination;
        this.totalCount = data.pagination.total;
        this.stats = data.stats;
      } catch (error) {
        if (request !== this.requestNumber) return;
        this.error = error.response?.data?.message || 'تعذر تحميل سجل النشاط. أعد المحاولة.';
      } finally {
        if (request === this.requestNumber) this.loading = false;
      }
    },
    async detail(id) {
      const { data } = await $http.get(`/activity/show/${id}`);
      return data;
    },
    async exportReport(params) {
      const { data } = await $http.get('/activity/export', { params, responseType: 'blob', timeout: 120000 });
      const url = URL.createObjectURL(data);
      const link = document.createElement('a');
      link.href = url; link.download = `activity-report-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link); link.click(); link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
  },
});
