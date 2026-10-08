<template>
     <div class="ar-table-wrap" tabindex="0" :aria-label="caption">
          <table class="ar-table">
               <caption class="ar-sr-only">{{ caption }}</caption>
               <thead>
                    <tr>
                         <th v-for="col in columns" :key="col.key" scope="col">{{ col.label }}</th>
                    </tr>
               </thead>
               <tbody>
                    <tr v-for="(row, index) in rows" :key="`${row.id ?? row.name}-${index}`">
                         <td
                              v-for="(col, colIndex) in columns"
                              :key="col.key"
                              :class="{ 'ar-number': col.money || col.number }">
                              <button
                                   v-if="action && colIndex === 0"
                                   class="ar-table-link"
                                   type="button"
                                   @click="$emit('select', row)"
                                   :aria-label="`${action}: ${row[col.key] ?? ''}`">
                                   {{ display(col, row) }}
                                   <span aria-hidden="true">↗</span>
                              </button>
                              <span
                                   v-else-if="col.key === 'payment_status'"
                                   class="ar-status"
                                   :data-status="row.payment_status">
                                   {{ display(col, row) }}
                              </span>
                              <template v-else>{{ display(col, row) }}</template>
                              <small v-if="col.key === 'name' && row.shortcut" class="ar-shortcut" dir="ltr">
                                   {{ row.shortcut }}
                              </small>
                              <small v-if="col.key === 'created_by' && row.creator_source !== 'audit'" class="ar-muted">
                                   {{
                                        row.creator_source === "portal_account"
                                             ? "حساب بوابة الإحالة"
                                             : "حسب حساب الفاتورة"
                                   }}
                              </small>
                         </td>
                    </tr>
                    <tr v-if="!rows.length">
                         <td :colspan="columns.length" class="ar-empty">لا توجد سجلات تطابق هذه الفلاتر.</td>
                    </tr>
               </tbody>
          </table>
     </div>
</template>
<script setup>
     const props = defineProps({
          columns: { type: Array, required: true },
          rows: { type: Array, default: () => [] },
          caption: { type: String, required: true },
          action: { type: String, default: "" },
     });
     defineEmits(["select"]);
     const states = { paid: "مسدد", partial: "مسدد جزئياً", unpaid: "غير مسدد", credit: "رصيد زائد" };
     const kinds = { test: "تحليل", culture: "زرع", group: "كروب", package: "باقة" };
     const number = new Intl.NumberFormat("en-US");
     function display(col, row) {
          const value = row[col.key];
          if (value === null || value === undefined) return col.money ? "غير مكتمل" : "—";
          if (col.key === "payment_status") return states[value] || value;
          if (col.key === "kind") return kinds[value] || value;
          return col.money || col.number ? number.format(value) : value;
     }
</script>
