import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";

export const useLabDataCopyStore = defineStore("labDataCopy", {
     state: () => ({
          // Wizard state
          fromLabId: null,
          toLabId: null,
          activeType: "test", // category | sample | question | antibiotic | culture | test | test_group | package
          selection: {
               category: [],
               sample: [],
               question: [],
               antibiotic: [],
               culture: [],
               test: [],
               test_group: [],
               package: [],
          },

          // Per-type item lists (separate so switching tabs preserves state)
          items: {},
          itemsLoading: false,
          itemsPagination: {
               current_page: 1,
               last_page: 1,
               per_page: 50,
               total: 0,
          },
          itemsSearch: "",

          previewLoading: false,
          previewSummary: null,
          copying: false,
          copyResult: null,
     }),

     actions: {
          resetSelection() {
               this.selection = {
                    category: [], sample: [], question: [], antibiotic: [],
                    culture: [], test: [], test_group: [], package: [],
               };
               this.items = {};
               this.previewSummary = null;
               this.copyResult = null;
          },

          async fetchItems(page = 1) {
               if (!this.fromLabId || !this.activeType) return;
               this.itemsLoading = true;
               try {
                    const { data } = await $http.get(
                         `/super-admin/lab-data/${this.fromLabId}/${this.activeType}`,
                         { params: { search: this.itemsSearch || undefined, per_page: 50, page } }
                    );
                    this.items[this.activeType] = data.data || [];
                    this.itemsPagination = data.pagination || this.itemsPagination;
               } finally {
                    this.itemsLoading = false;
               }
          },

          toggleId(id) {
               const list = this.selection[this.activeType] || [];
               const idx = list.indexOf(id);
               if (idx === -1) list.push(id);
               else list.splice(idx, 1);
               this.selection[this.activeType] = [...list];
          },

          isSelected(id) {
               return (this.selection[this.activeType] || []).includes(id);
          },

          selectAllVisible() {
               const ids = (this.items[this.activeType] || []).map((i) => i.id);
               const set = new Set([...(this.selection[this.activeType] || []), ...ids]);
               this.selection[this.activeType] = [...set];
          },

          clearVisible() {
               const visible = new Set((this.items[this.activeType] || []).map((i) => i.id));
               this.selection[this.activeType] = (this.selection[this.activeType] || []).filter(
                    (id) => !visible.has(id)
               );
          },

          totalSelected() {
               return Object.values(this.selection).reduce((s, arr) => s + arr.length, 0);
          },

          buildPayload() {
               const selection = {};
               for (const k of Object.keys(this.selection)) {
                    if (this.selection[k]?.length) selection[k] = this.selection[k];
               }
               return {
                    from_lab_id: this.fromLabId,
                    to_lab_id: this.toLabId,
                    selection,
               };
          },

          async preview() {
               this.previewLoading = true;
               try {
                    const { data } = await $http.post("/super-admin/lab-data/preview", this.buildPayload());
                    this.previewSummary = data.summary || {};
                    return data.summary;
               } finally {
                    this.previewLoading = false;
               }
          },

          async copy() {
               this.copying = true;
               try {
                    const { data } = await $http.post("/super-admin/lab-data/copy", this.buildPayload());
                    this.copyResult = data;
                    return data;
               } finally {
                    this.copying = false;
               }
          },
     },
});
