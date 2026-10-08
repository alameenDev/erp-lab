<template>
     <main class="accounting" dir="rtl" :aria-busy="store.loading">
          <header class="ar-header">
               <div>
                    <div class="ar-eyebrow">إدارة المختبر / الحسابات</div>
                    <h1>التقارير المالية</h1>
                    <p>صورة واضحة للفواتير، الإحالات والتحصيل وحركة الفحوصات.</p>
               </div>
               <div class="ar-actions">
                    <button type="button" class="ar-btn" :disabled="!report || exporting" @click="exportReport('csv')">
                         <i class="pi pi-download" aria-hidden="true"></i>
                         تصدير Excel
                    </button>
                    <button
                         type="button"
                         class="ar-btn ar-primary"
                         :disabled="!report || exporting"
                         @click="exportReport('print')">
                         <i class="pi pi-print" aria-hidden="true"></i>
                         طباعة / حفظ PDF
                    </button>
               </div>
          </header>

          <form class="ar-panel ar-filters" @submit.prevent="apply">
               <div class="ar-filter-heading">
                    <strong>الفترة والفلاتر</strong>
                    <div class="ar-presets">
                         <button v-for="p in presets" :key="p.key" type="button" class="ar-chip" @click="preset(p.key)">
                              {{ p.label }}
                         </button>
                    </div>
               </div>
               <div class="ar-filter-grid">
                    <label>
                         من تاريخ
                         <input v-model="draft.from" type="date" required aria-label="من تاريخ" />
                    </label>
                    <label>
                         إلى تاريخ
                         <input v-model="draft.to" type="date" :min="draft.from" required aria-label="إلى تاريخ" />
                    </label>
                    <label class="ar-search">
                         البحث
                         <input
                              v-model.trim="draft.search"
                              type="search"
                              placeholder="اسم المريض، الكود أو رقم الفاتورة"
                              maxlength="100"
                              aria-label="البحث" />
                    </label>
                    <label>
                         حالة التسديد
                         <select v-model="draft.status">
                              <option value="">جميع الحالات</option>
                              <option value="paid">مسدد</option>
                              <option value="partial">مسدد جزئياً</option>
                              <option value="unpaid">غير مسدد</option>
                              <option value="credit">رصيد زائد</option>
                         </select>
                    </label>
               </div>
               <details class="ar-more-filters">
                    <summary>
                         فلاتر الإحالات والموظفين والعقود
                         <span v-if="dimensionCount">({{ dimensionCount }})</span>
                    </summary>
                    <div class="ar-filter-grid">
                         <label>
                              نوع الإحالة
                              <select v-model="draft.referral_type">
                                   <option value="">الكل</option>
                                   <option value="lab">مختبر</option>
                                   <option value="doctor">طبيب</option>
                              </select>
                         </label>
                         <label v-for="field in dimensions" :key="field.key">
                              {{ field.label }}
                              <select v-model="draft[field.key]">
                                   <option value="">الكل</option>
                                   <option
                                        v-for="item in store.options[field.options] || []"
                                        :key="item.id"
                                        :value="item.id">
                                        {{ item.name }}
                                   </option>
                              </select>
                         </label>
                    </div>
               </details>
               <div class="ar-filter-footer">
                    <span class="ar-muted">المبالغ بالدينار العراقي · الفترة حسب تاريخ إنشاء الفاتورة</span>
                    <div class="ar-actions">
                         <button type="button" class="ar-btn" @click="reset">إعادة ضبط</button>
                         <button class="ar-btn ar-primary" type="submit" :disabled="store.loading">
                              {{ store.loading ? "جارٍ التحديث…" : "تطبيق الفلاتر" }}
                         </button>
                    </div>
               </div>
          </form>
          <p v-if="optionsError" class="ar-alert" role="alert">
               {{ optionsError }}
               <button class="ar-table-link" @click="loadOptions">إعادة تحميل الفلاتر</button>
          </p>
          <div v-if="store.error || exportError" class="ar-alert" role="alert">
               {{ store.error || exportError }}
               <button v-if="store.error" type="button" class="ar-table-link" @click="apply">إعادة المحاولة</button>
          </div>
          <div v-if="store.loading" class="ar-panel ar-loading" role="status">
               <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
               جارٍ احتساب التقارير وجلب التفاصيل…
          </div>
          <template v-if="report">
               <div class="ar-context">
                    <strong>{{ report.meta.lab_name }}</strong>
                    <span>{{ store.filters.from }} — {{ store.filters.to }}</span>
                    <span>آخر تحديث: {{ generatedAt }}</span>
                    <span v-if="appliedLabel" class="ar-applied">{{ appliedLabel }}</span>
               </div>
               <section class="ar-kpis" aria-label="مؤشرات الفترة">
                    <article v-for="card in cards" :key="card.key" class="ar-kpi" :class="card.tone">
                         <span>{{ card.label }}</span>
                         <strong :data-metric="card.key">{{ money(summary[card.key]) }}</strong>
                         <small>{{ card.note }}</small>
                    </article>
               </section>
               <nav class="ar-tabs" aria-label="أقسام التقارير">
                    <button
                         v-for="section in sections"
                         :key="section.key"
                         type="button"
                         :class="{ active: tab === section.key }"
                         :aria-current="tab === section.key ? 'page' : undefined"
                         @click="tab = section.key">
                         {{ section.label }}
                    </button>
               </nav>

               <section v-if="tab === 'overview'" class="ar-dashboard" aria-label="لوحة حركة المختبر">
                    <article class="ar-panel ar-trend">
                         <div class="ar-panel-title">
                              <div>
                                   <h2>حركة الفواتير والتحصيل</h2>
                                   <p>
                                        {{ report.meta.trend_interval === "month" ? "مقارنة شهرية" : "مقارنة يومية" }} ·
                                        صافي الفواتير مقابل الدفعات المسجلة في الفترة
                                   </p>
                              </div>
                         </div>
                         <div v-if="report.trend.length" class="ar-chart">
                              <Line
                                   :data="chartData"
                                   :options="chartOptions"
                                   aria-label="رسم حركة الفواتير والتحصيل"
                                   role="img" />
                         </div>
                         <div v-else class="ar-empty">لا توجد حركة في هذه الفترة.</div>
                         <details class="ar-chart-data">
                              <summary>عرض أرقام المخطط</summary>
                              <ReportTable
                                   :rows="report.trend"
                                   :columns="trendColumns"
                                   caption="أرقام حركة الفواتير والتحصيل" />
                         </details>
                    </article>
                    <article class="ar-panel">
                         <div class="ar-panel-title">
                              <div>
                                   <h2>حالة الفواتير</h2>
                                   <p>
                                        {{ money(summary.invoice_count) }} فاتورة ·
                                        {{ money(summary.patient_count) }} مريض
                                   </p>
                              </div>
                         </div>
                         <button
                              v-for="state in statuses"
                              :key="state.key"
                              class="ar-status-row"
                              type="button"
                              @click="filterInvoices('status', state.key)">
                              <span>
                                   <i :style="{ background: state.color }"></i>
                                   {{ state.label }}
                              </span>
                              <strong>{{ money(report.payment_status[state.key]) }}</strong>
                              <span class="ar-status-track">
                                   <span
                                        :style="{
                                             width:
                                                  (report.payment_status[state.key] / (summary.invoice_count || 1)) *
                                                       100 +
                                                  '%',
                                             background: state.color,
                                        }"></span>
                              </span>
                         </button>
                         <div class="ar-mini-total">
                              <span>عدد البنود المفوترة</span>
                              <strong>{{ money(summary.billed_items) }}</strong>
                         </div>
                         <div class="ar-mini-total">
                              <span>رصيد زائد على الفواتير</span>
                              <strong>{{ money(summary.credit) }} د.ع</strong>
                         </div>
                    </article>
                    <article class="ar-panel">
                         <div class="ar-panel-title">
                              <div>
                                   <h2>عمر المبالغ المتبقية</h2>
                                   <p>الرصيد الحالي لفواتير الفترة حسب عمر الفاتورة</p>
                              </div>
                         </div>
                         <div v-for="(value, index) in report.aging" :key="index" class="ar-aging">
                              <div>
                                   <span>{{ ["حتى 30 يوم", "31–60 يوم", "61–90 يوم", "أكثر من 90 يوم"][index] }}</span>
                                   <strong>
                                        {{ money(value) }}
                                        <small>د.ع</small>
                                   </strong>
                              </div>
                              <div class="ar-aging-track">
                                   <span
                                        :style="{
                                             width: (value / (Math.max(...report.aging) || 1)) * 100 + '%',
                                        }"></span>
                              </div>
                         </div>
                    </article>
                    <article class="ar-panel">
                         <div class="ar-panel-title">
                              <div>
                                   <h2>تفصيل صافي الفواتير</h2>
                                   <p>مراجعة الخصومات والتكلفة والعمولات</p>
                              </div>
                         </div>
                         <div
                              v-for="line in reconciliation"
                              :key="line.key"
                              class="ar-reconcile"
                              :class="{ strong: line.key === 'total' }">
                              <span>{{ line.label }}</span>
                              <strong>{{ money(summary[line.key]) }}</strong>
                         </div>
                    </article>
                    <article class="ar-panel">
                         <div class="ar-panel-title">
                              <div>
                                   <h2>طرق التحصيل</h2>
                                   <p>يشمل تسديد الفواتير الأقدم خلال الفترة</p>
                              </div>
                         </div>
                         <ReportTable
                              :rows="report.payment_methods"
                              :columns="methodColumns"
                              caption="التحصيل حسب طريقة الدفع" />
                    </article>
               </section>

               <section v-else class="ar-panel ar-report-section" :aria-label="sectionTitle">
                    <div class="ar-panel-title">
                         <div>
                              <h2>{{ sectionTitle }}</h2>
                              <p>{{ sectionNote }}</p>
                         </div>
                         <div class="ar-actions">
                              <button
                                   v-if="tab === 'lab_referrals' || tab === 'doctor_referrals'"
                                   type="button"
                                   class="ar-btn"
                                   @click="filterInvoices('referral_type', tab === 'lab_referrals' ? 'lab' : 'doctor')">
                                   كشف الإحالات التفصيلي
                              </button>
                              <span class="ar-count">{{ money(tableCount) }} سجل</span>
                         </div>
                    </div>
                    <div v-if="store.tableLoading" class="ar-table-progress" role="status">جارٍ تحميل الصفحة…</div>
                    <ReportTable
                         :rows="tableRows"
                         :columns="tableColumns"
                         :caption="sectionTitle"
                         :action="tableAction"
                         @select="selectRow" />
                    <div v-if="pagination" class="ar-pagination">
                         <span>
                              الصفحة {{ pagination.current_page }} من {{ pagination.last_page }} ·
                              {{ money(pagination.total) }} سجل
                         </span>
                         <div class="ar-actions">
                              <button
                                   class="ar-btn"
                                   :disabled="pagination.current_page <= 1 || store.tableLoading"
                                   @click="store.page(tab, pagination.current_page - 1)">
                                   السابق
                              </button>
                              <button
                                   class="ar-btn"
                                   :disabled="pagination.current_page >= pagination.last_page || store.tableLoading"
                                   @click="store.page(tab, pagination.current_page + 1)">
                                   التالي
                              </button>
                         </div>
                    </div>
                    <p class="ar-export-note">الطباعة والتصدير يشملان جميع سجلات هذا القسم المطابقة للفلاتر.</p>
               </section>
               <aside class="ar-basis ar-panel">
                    <h2>
                         <i class="pi pi-info-circle" aria-hidden="true"></i>
                         كيف تُقرأ هذه الأرقام؟
                    </h2>
                    <p>
                         المدفوع والمتبقي هما الرصيد الحالي لفواتير الفترة، أما التحصيل فهو الدفعات حسب تاريخ تسجيلها.
                         الفواتير المحذوفة مستبعدة. رصيد الإحالة هو رصيد فواتيرها ولا يثبت تسوية عمولة الطبيب أو مستحقات
                         المختبر الخارجي.
                    </p>
                    <p>
                         الربح المعروض تقديري: صافي الفاتورة ناقص تكلفة الفحوص المسجلة أو الحالية وعمولة الإحالة
                         الحالية. لا يشمل المصروفات التشغيلية. لا تُحتسب نتيجة نهائية عند نقص بيانات التكلفة أو العمولة.
                    </p>
                    <p v-if="summary.incomplete_profit_invoices" class="ar-coverage">
                         {{ money(summary.incomplete_profit_invoices) }} فاتورة ببيانات تكلفة أو عمولة غير مكتملة ·
                         الجزء المعروف من التكلفة: {{ money(summary.known_cost) }} د.ع.
                    </p>
                    <p v-if="summary.estimated_membership_invoices">
                         {{ money(summary.estimated_membership_invoices) }} فاتورة استُكملت عضوية كروباتها أو باقاتها من
                         التعريف الحالي؛ قد تختلف أعدادها عن التكوين التاريخي.
                    </p>
                    <p v-if="summary.unallocated_revenue">
                         إيراد فواتير بدون بنود محفوظة: {{ money(summary.unallocated_revenue) }} د.ع، لا يدخل في توزيع
                         ربح البنود.
                    </p>
                    <p>
                         عدد الفحوص يشمل التحاليل والزرع داخل الكروبات والباقات، ولا يعد حقول النتيجة فحوصات مستقلة.
                         يُوزّع خصم الفاتورة والعمولة على البنود بنسبة أسعارها لتجنب تكرار الإيراد.
                    </p>
               </aside>
          </template>
          <dialog ref="dialog" class="ar-dialog" @close="detail = null" @click="backdropClose">
               <div class="ar-dialog-header">
                    <div>
                         <span class="ar-eyebrow">كشف تفصيلي</span>
                         <h2>فاتورة #{{ detailId }}</h2>
                    </div>
                    <button type="button" class="ar-btn" aria-label="إغلاق تفاصيل الفاتورة" @click="dialog.close()">
                         إغلاق ×
                    </button>
               </div>
               <div v-if="detailLoading" class="ar-loading" role="status">جارٍ تحميل كشف الفاتورة…</div>
               <p v-else-if="detailError" class="ar-alert" role="alert">{{ detailError }}</p>
               <template v-else-if="detail">
                    <div class="ar-detail-info">
                         <div v-for="field in detailFields" :key="field.key">
                              <small>{{ field.label }}</small>
                              <strong>{{ detail[field.key] || "—" }}</strong>
                         </div>
                    </div>
                    <p class="ar-muted ar-detail-source">
                         مصدر اسم المدخل:
                         {{
                              detail.creator_source === "audit"
                                   ? "سجل الإنشاء الموثق"
                                   : detail.creator_source === "portal_account"
                                     ? "حساب بوابة الإحالة؛ سجل الإنشاء غير متوفر"
                                     : "حساب الفاتورة؛ قد لا يحدد الموظف الفعلي للسجلات القديمة"
                         }}.
                    </p>
                    <div class="ar-detail-totals">
                         <div v-for="k in detailTotals" :key="k.key">
                              <span>{{ k.label }}</span>
                              <strong>
                                   {{ money(detail[k.key]) }}
                                   <small v-if="detail[k.key] !== null">د.ع</small>
                              </strong>
                         </div>
                    </div>
                    <h3>بنود الفاتورة وتوزيع الإيراد</h3>
                    <ReportTable :rows="detail.items" :columns="detailItemColumns" caption="بنود الفاتورة" />
                    <details
                         v-for="item in detail.items.filter((i) => i.kind === 'group' || i.kind === 'package')"
                         :key="item.id"
                         class="ar-item-contents">
                         <summary>{{ item.name }} · {{ item.analysis_count }} فحص</summary>
                         <div class="ar-leaves">
                              <span v-for="leaf in item.analyses" :key="leaf.key">
                                   {{ leaf.name }}
                                   <small dir="ltr">{{ leaf.shortcut }}</small>
                              </span>
                         </div>
                    </details>
                    <h3>الدفعات المسجلة</h3>
                    <ReportTable :rows="detail.payments" :columns="paymentDetailColumns" caption="دفعات الفاتورة" />
                    <h3>التغييرات المالية المسجلة</h3>
                    <p v-if="!detail.activity.length" class="ar-muted">لا توجد تغييرات مالية مؤرشفة لهذه الفاتورة.</p>
                    <article v-for="event in detail.activity" :key="event.id" class="ar-audit">
                         <div>
                              <strong>{{ event.description }}</strong>
                              <span>{{ event.actor }} · {{ event.date }}</span>
                         </div>
                         <ul>
                              <li v-for="(change, index) in event.changes" :key="index">
                                   {{ change.label || change.field }}:
                                   <del>{{ change.before ?? "—" }}</del>
                                   ←
                                   <strong>{{ change.after ?? "—" }}</strong>
                              </li>
                         </ul>
                    </article>
                    <p class="ar-export-note">الأرباح والتكاليف والعمولات تقديرية حسب أساس الحساب الموضح في التقرير.</p>
               </template>
          </dialog>
     </main>
</template>
<script setup>
     import { computed, onMounted, onBeforeUnmount, reactive, ref } from "vue";
     import { Line } from "vue-chartjs";
     import {
          Chart as ChartJS,
          CategoryScale,
          LinearScale,
          PointElement,
          LineElement,
          Tooltip,
          Legend,
          Filler,
     } from "chart.js";
     import { useAccountingReportsStore, defaultAccountingFilters } from "@/store/modules/accountingReports";
     import ReportTable from "./components/ReportTable.vue";
     ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler);
     const store = useAccountingReportsStore();
     const draft = reactive({ ...store.filters });
     const tab = ref("overview");
     const optionsError = ref("");
     const exporting = ref(false);
     const exportError = ref("");
     const report = computed(() => store.report);
     const summary = computed(() => report.value?.summary || {});
     const formatter = new Intl.NumberFormat("en-US");
     const money = (value) => (value === null || value === undefined ? "غير مكتمل" : formatter.format(value));
     const generatedAt = computed(() => report.value?.meta.generated_at?.replace("T", " ").slice(0, 16));
     const presets = [
          { key: "today", label: "اليوم" },
          { key: "week", label: "آخر 7 أيام" },
          { key: "month", label: "هذا الشهر" },
          { key: "last_month", label: "الشهر السابق" },
          { key: "year", label: "هذه السنة" },
     ];
     const dimensions = [
          { key: "owner_id", label: "المختبر", options: "owner_labs" },
          { key: "lab_referral_id", label: "المختبر المحيل", options: "labs" },
          { key: "doctor_id", label: "الطبيب المحيل", options: "doctors" },
          { key: "created_by", label: "مدخل العملية", options: "operators" },
          { key: "branch_id", label: "حساب / فرع الفاتورة", options: "branches" },
          { key: "contract_id", label: "العقد", options: "contracts" },
          { key: "sample_collector_id", label: "جامع العينة", options: "collectors" },
     ];
     const dimensionCount = computed(
          () => dimensions.filter((f) => draft[f.key]).length + (draft.referral_type ? 1 : 0)
     );
     const statuses = [
          { key: "paid", label: "مسدد", color: "#137a69" },
          { key: "partial", label: "مسدد جزئياً", color: "#ce8724" },
          { key: "unpaid", label: "غير مسدد", color: "#c74e5e" },
          { key: "credit", label: "رصيد زائد", color: "#6671ca" },
     ];
     const appliedLabel = computed(() => {
          const parts = dimensions
               .filter((f) => store.filters[f.key])
               .map(
                    (f) =>
                         `${f.label}: ${(store.options[f.options] || []).find((x) => String(x.id) === String(store.filters[f.key]))?.name || store.filters[f.key]}`
               );
          if (store.filters.search) parts.push(`البحث: ${store.filters.search}`);
          if (store.filters.status) parts.push(statuses.find((s) => s.key === store.filters.status)?.label);
          if (store.filters.referral_type)
               parts.push(store.filters.referral_type === "lab" ? "إحالات المختبرات" : "إحالات الأطباء");
          if (store.filters.patient_id) parts.push(`المريض #${store.filters.patient_id}`);
          return parts.join(" · ");
     });
     const cards = [
          { key: "total", label: "صافي الفواتير", note: "بعد الخصومات · د.ع", tone: "ar-kpi-teal" },
          {
               key: "collections_in_period",
               label: "التحصيل خلال الفترة",
               note: "حسب تاريخ الدفعة · د.ع",
               tone: "ar-kpi-blue",
          },
          { key: "balance", label: "المتبقي على فواتير الفترة", note: "الرصيد الحالي · د.ع", tone: "ar-kpi-amber" },
          { key: "profit_estimate", label: "الربح التقديري", note: "قبل المصروفات التشغيلية · د.ع", tone: "" },
          { key: "invoice_count", label: "عدد الفواتير", note: "الفواتير غير المحذوفة" },
          { key: "analysis_count", label: "عدد الفحوصات", note: "يشمل الكروبات والباقات" },
          { key: "paid", label: "المدفوع على فواتير الفترة", note: "جميع دفعات هذه الفواتير · د.ع" },
          { key: "cost_estimate", label: "التكلفة التقديرية", note: "من تكلفة الفحوص المسجلة · د.ع" },
     ];
     const sections = [
          { key: "overview", label: "لوحة المتابعة" },
          { key: "invoices", label: "كشف الفواتير" },
          { key: "lab_referrals", label: "إحالات المختبرات" },
          { key: "doctor_referrals", label: "إحالات الأطباء" },
          { key: "items", label: "الإيراد والأرباح" },
          { key: "analyses", label: "عدد الفحوصات" },
          { key: "payments", label: "حركة التحصيل" },
          { key: "operators", label: "حركة الموظفين" },
          { key: "contracts", label: "العقود" },
          { key: "patients", label: "حسابات المرضى" },
          { key: "outbound", label: "التحويل للخارج" },
     ];
     const col = (key, label, type) => ({ key, label, money: type === "money", number: type === "number" });
     const accountColumns = [
          col("name", "الاسم"),
          col("lab_name", "المختبر"),
          col("invoice_count", "الفواتير", "number"),
          col("total", "صافي الفواتير", "money"),
          col("paid", "المدفوع", "money"),
          col("balance", "المتبقي", "money"),
          col("credit", "الرصيد الزائد", "money"),
     ];
     const columns = {
          invoices: [
               col("id", "الفاتورة"),
               col("patient_name", "المريض"),
               col("referral_lab", "المختبر المحيل"),
               col("doctor", "الطبيب"),
               col("total", "الصافي", "money"),
               col("paid", "المدفوع", "money"),
               col("balance", "المتبقي", "money"),
               col("credit", "رصيد زائد", "money"),
               col("date", "التاريخ"),
               col("created_by", "مدخل العملية"),
               col("payment_status", "الحالة"),
          ],
          lab_referrals: accountColumns,
          doctor_referrals: [
               ...accountColumns,
               col("commission_estimate", "عمولة تقديرية", "money"),
               col("profit_estimate", "ربح تقديري", "money"),
          ],
          operators: accountColumns,
          contracts: accountColumns,
          patients: accountColumns,
          items: [
               col("name", "البند"),
               col("kind", "النوع"),
               col("count", "الطلبات", "number"),
               col("price", "قبل الخصم", "money"),
               col("net_allocated", "الإيراد الموزع", "money"),
               col("cost_estimate", "تكلفة تقديرية", "money"),
               col("commission_allocated", "عمولة تقديرية", "money"),
               col("profit_estimate", "ربح تقديري", "money"),
          ],
          analyses: [
               col("name", "الفحص"),
               col("kind", "النوع"),
               col("count", "إجمالي العدد", "number"),
               col("direct", "مباشر", "number"),
               col("in_groups", "داخل الكروبات", "number"),
               col("in_packages", "داخل الباقات", "number"),
          ],
          payments: [
               col("invoice_id", "الفاتورة"),
               col("patient_name", "المريض"),
               col("referral_lab", "المختبر المحيل"),
               col("doctor", "الطبيب"),
               col("amount", "المبلغ", "money"),
               col("method", "طريقة الدفع"),
               col("date", "تاريخ الدفعة"),
               col("payment_account", "حساب التسديد"),
               col("created_by", "مدخل الفاتورة"),
          ],
          outbound: [
               col("name", "المختبر المستلم"),
               col("lab_name", "مختبر الفاتورة"),
               col("count", "البنود المحولة", "number"),
               col("net_allocated", "الإيراد الموزع", "money"),
               col("cost_estimate", "تكلفة تقديرية", "money"),
          ],
     };
     const trendColumns = [
          col("period", "الفترة"),
          col("count", "الفواتير", "number"),
          col("total", "صافي الفواتير", "money"),
          col("collections", "التحصيل", "money"),
     ];
     const methodColumns = [
          col("name", "طريقة الدفع"),
          col("count", "عدد الدفعات", "number"),
          col("amount", "المبلغ", "money"),
     ];
     const reconciliation = [
          { key: "sub_total", label: "الإجمالي قبل الخصم" },
          { key: "discount_amount", label: "الخصومات" },
          { key: "loyalty_discount", label: "خصومات الولاء" },
          { key: "adjustment", label: "فرق التسوية المسجل" },
          { key: "total", label: "صافي الفواتير" },
          { key: "cost_estimate", label: "تكلفة الفحوص التقديرية" },
          { key: "commission_estimate", label: "عمولات الإحالات التقديرية" },
          { key: "profit_estimate", label: "الربح التقديري" },
     ];
     const sectionTitle = computed(() => sections.find((s) => s.key === tab.value)?.label);
     const sectionNote = computed(
          () =>
               ({
                    invoices:
                         "المريض، الإحالة، المدفوع والمتبقي، تاريخ الفاتورة ومدخل العملية. اضغط رقم الفاتورة للتفاصيل.",
                    lab_referrals: "اضغط اسم المختبر لفتح كشف مرضاه وفواتيره وتسديداته.",
                    doctor_referrals:
                         "اضغط اسم الطبيب لعرض المرضى والفواتير. العمولة تقديرية حسب النسبة الحالية وليست مستحقات مسددة.",
                    items: "الإيراد موزع بعد خصومات الفاتورة. لا تُجمع إيرادات مكونات الباقة فوق إيرادها.",
                    analyses:
                         "أعداد التحاليل والزرع المباشرة وداخل الكروبات والباقات، مع احتساب كل فحص مرة داخل البند.",
                    payments:
                         "دفعات سجلت خلال الفترة، بما فيها تسديد فواتير قديمة. حساب التسديد قد يختلف عن الموظف المنفذ.",
                    operators: "المدخل من سجل الإنشاء، أو حساب الفاتورة للسجلات القديمة. اضغط الاسم لعرض فواتيره.",
                    contracts: "اضغط العقد لعرض الفواتير المرتبطة به.",
                    patients: "اضغط اسم المريض لعرض كشف فواتيره.",
                    outbound: "بنود أُرسلت إلى مختبر خارجي. التكاليف تقديرية؛ لا يوجد هنا إثبات دفع للمختبر المستلم.",
               })[tab.value]
     );
     const pagination = computed(() => (["invoices", "payments"].includes(tab.value) ? store[tab.value] : null));
     const tableRows = computed(() => pagination.value?.data || report.value?.[tab.value] || []);
     const tableColumns = computed(() => columns[tab.value] || []);
     const tableCount = computed(() => pagination.value?.total ?? tableRows.value.length);
     const tableAction = computed(() =>
          ["invoices", "payments"].includes(tab.value)
               ? "عرض الفاتورة"
               : ["lab_referrals", "doctor_referrals", "operators", "contracts", "patients"].includes(tab.value)
                 ? "عرض الفواتير"
                 : ""
     );
     const chartData = computed(() => ({
          labels: report.value?.trend.map((x) => x.period) || [],
          datasets: [
               {
                    label: "صافي الفواتير",
                    data: report.value?.trend.map((x) => x.total) || [],
                    borderColor: "#168b7b",
                    backgroundColor: "rgba(22,139,123,.07)",
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2,
               },
               {
                    label: "التحصيل",
                    data: report.value?.trend.map((x) => x.collections) || [],
                    borderColor: "#5179cf",
                    backgroundColor: "transparent",
                    tension: 0.25,
                    pointRadius: 2,
               },
          ],
     }));
     const chartOptions = {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: {
               legend: {
                    position: "bottom",
                    rtl: true,
                    labels: { usePointStyle: true, padding: 24, font: { family: "Arial" } },
               },
               tooltip: { rtl: true, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.raw)} د.ع` } },
          },
          scales: {
               x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
               y: {
                    beginAtZero: true,
                    ticks: { callback: (value) => new Intl.NumberFormat("en", { notation: "compact" }).format(value) },
                    grid: { color: "#edf1f5" },
               },
          },
     };
     function preset(key) {
          const now = new Date(),
               start = new Date(now),
               end = new Date(now);
          if (key === "week") start.setDate(start.getDate() - 6);
          if (key === "month") start.setDate(1);
          if (key === "year") {
               start.setMonth(0);
               start.setDate(1);
          }
          if (key === "last_month") {
               start.setDate(1);
               start.setMonth(start.getMonth() - 1);
               end.setDate(0);
          }
          const fmt = (d) =>
               `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
          draft.from = fmt(start);
          draft.to = fmt(end);
     }
     async function loadOptions() {
          optionsError.value = "";
          try {
               await store.loadOptions();
          } catch {
               optionsError.value = "تعذر تحميل أسماء الفلاتر.";
          }
     }
     function apply() {
          exportError.value = "";
          return store.apply({ ...draft });
     }
     function reset() {
          Object.assign(draft, defaultAccountingFilters());
          tab.value = "overview";
          apply();
     }
     function filterInvoices(key, value, ownerId = null) {
          Object.assign(draft, store.filters);
          draft[key] = value;
          if (ownerId) draft.owner_id = ownerId;
          tab.value = "invoices";
          apply();
     }
     function selectRow(row) {
          if (tab.value === "invoices") return openDetail(row.id);
          if (tab.value === "payments") return openDetail(row.invoice_id);
          const key = {
               lab_referrals: "lab_referral_id",
               doctor_referrals: "doctor_id",
               operators: "created_by",
               contracts: "contract_id",
               patients: "patient_id",
          }[tab.value];
          if (key) filterInvoices(key, row.id, row.lab_id);
     }
     async function exportReport(format) {
          const section = tab.value;
          let popup = null;
          if (format === "print") {
               popup = window.open("about:blank", "_blank");
               if (!popup) {
                    exportError.value = "اسمح بفتح نافذة الطباعة من المتصفح ثم أعد المحاولة.";
                    return;
               }
               popup.document.open();
               popup.document.write(
                    '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><body>جارٍ تجهيز التقرير للطباعة…</body></html>'
               );
               popup.document.close();
               popup.opener = null;
          }
          exporting.value = true;
          exportError.value = "";
          try {
               const blob = await store.export(section, format);
               if (popup) {
                    // All dynamic values in this server-rendered document are HTML-escaped.
                    // Writing the prepared document also avoids popup/Blob navigation races.
                    const html = await blob.text();
                    if (popup.closed) return;
                    popup.document.open();
                    popup.document.write(html);
                    popup.document.close();
               } else {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement("a");
                    link.href = url;
                    link.download = `accounting-${section}-${store.filters.from}.csv`;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 60000);
               }
          } catch {
               popup?.close();
               exportError.value = "تعذر تجهيز التقرير. أعد المحاولة أو اختر فترة أقصر.";
          } finally {
               exporting.value = false;
          }
     }
     const dialog = ref(null),
          detail = ref(null),
          detailId = ref(null),
          detailLoading = ref(false),
          detailError = ref("");
     let detailSerial = 0;
     const detailFields = [
          { key: "patient_name", label: "المريض" },
          { key: "patient_code", label: "كود المريض" },
          { key: "date", label: "تاريخ الإنشاء" },
          { key: "registration_date", label: "تاريخ التسجيل" },
          { key: "referral_lab", label: "المختبر المحيل" },
          { key: "doctor", label: "الطبيب المحيل" },
          { key: "created_by", label: "مدخل العملية" },
          { key: "branch_name", label: "حساب الفاتورة" },
          { key: "contract", label: "العقد" },
          { key: "collector", label: "جامع العينة" },
     ];
     const detailTotals = [
          { key: "sub_total", label: "قبل الخصم" },
          { key: "discount_amount", label: "الخصم" },
          { key: "loyalty_discount", label: "خصم الولاء" },
          { key: "adjustment", label: "فرق التسوية" },
          { key: "total", label: "الصافي" },
          { key: "paid", label: "المدفوع" },
          { key: "balance", label: "المتبقي" },
          { key: "credit", label: "رصيد زائد" },
          { key: "commission_estimate", label: "عمولة تقديرية" },
          { key: "profit_estimate", label: "ربح تقديري" },
     ];
     const detailItemColumns = [
          col("name", "البند"),
          col("kind", "النوع"),
          col("price", "السعر", "money"),
          col("net_allocated", "الإيراد الموزع", "money"),
          col("cost_estimate", "تكلفة تقديرية", "money"),
          col("profit_estimate", "ربح تقديري", "money"),
          col("to_lab", "مختبر التنفيذ"),
     ];
     const paymentDetailColumns = [
          col("id", "رقم الدفعة"),
          col("date", "التاريخ"),
          col("method", "طريقة الدفع"),
          col("amount", "المبلغ", "money"),
     ];
     async function openDetail(id) {
          const serial = ++detailSerial;
          detailId.value = id;
          detail.value = null;
          detailError.value = "";
          detailLoading.value = true;
          dialog.value.showModal();
          try {
               const row = await store.detail(id);
               if (serial === detailSerial) detail.value = row;
          } catch {
               if (serial === detailSerial) detailError.value = "تعذر تحميل الفاتورة أو لا توجد صلاحية لعرضها.";
          } finally {
               if (serial === detailSerial) detailLoading.value = false;
          }
     }
     function backdropClose(event) {
          if (event.target === dialog.value) {
               const b = dialog.value.getBoundingClientRect();
               if (
                    event.clientX < b.left ||
                    event.clientX > b.right ||
                    event.clientY < b.top ||
                    event.clientY > b.bottom
               )
                    dialog.value.close();
          }
     }
     onMounted(() => {
          loadOptions();
          apply();
     });
     onBeforeUnmount(() => {
          store.cancel();
          ++detailSerial;
     });
</script>
<style>
     .accounting {
          --ar-ink: #203548;
          --ar-muted: #657789;
          --ar-line: #e2e9ef;
          --ar-teal: #147d70;
          direction: rtl;
          color: var(--ar-ink);
          background: #f4f7fa;
          padding: 28px;
          min-width: 0;
          font-family: inherit;
          font-size: 14px;
          line-height: 1.55;
     }
     .accounting * {
          box-sizing: border-box;
     }
     .accounting h1,
     .accounting h2,
     .accounting h3,
     .accounting p {
          margin: 0;
     }
     .accounting button,
     .accounting input,
     .accounting select {
          font: inherit;
     }
     .accounting button {
          cursor: pointer;
     }
     .accounting button:disabled {
          opacity: 0.5;
          cursor: wait;
     }
     .accounting button:focus-visible,
     .accounting summary:focus-visible,
     .accounting .ar-table-wrap:focus-visible {
          outline: 3px solid #579ec7;
          outline-offset: 3px;
     }
     .ar-header,
     .ar-filter-heading,
     .ar-filter-footer,
     .ar-context,
     .ar-panel-title,
     .ar-pagination,
     .ar-dialog-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 16px;
     }
     .ar-header {
          margin-bottom: 24px;
          align-items: flex-start;
     }
     .ar-eyebrow {
          font-size: 12px;
          color: var(--ar-teal);
          font-weight: 700;
          letter-spacing: 0.2px;
          margin-bottom: 6px;
     }
     .accounting h1 {
          font-size: 29px;
          line-height: 1.35;
          font-weight: 750;
     }
     .ar-header p {
          color: var(--ar-muted);
          margin-top: 8px;
     }
     .ar-actions {
          display: flex;
          align-items: center;
          gap: 8px;
          flex-wrap: wrap;
     }
     .ar-btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          border: 1px solid #d2dce5;
          background: #fff;
          color: #294255;
          padding: 10px 15px;
          border-radius: 9px;
          font-weight: 600;
          white-space: nowrap;
          min-height: 42px;
     }
     .ar-btn:hover {
          background: #f3f7f9;
     }
     .ar-primary {
          background: var(--ar-teal);
          color: #fff;
          border-color: var(--ar-teal);
     }
     .ar-primary:hover {
          background: #10665c;
     }
     .ar-panel {
          background: #fff;
          border: 1px solid var(--ar-line);
          border-radius: 14px;
          box-shadow: 0 3px 12px #26364a04;
          min-width: 0;
          overflow: hidden;
     }
     .ar-filters {
          padding: 20px;
     }
     .ar-filter-heading {
          margin-bottom: 18px;
          flex-wrap: wrap;
     }
     .ar-presets {
          display: flex;
          gap: 6px;
          flex-wrap: wrap;
     }
     .ar-chip {
          padding: 5px 12px;
          border: 1px solid #e1e7ed;
          border-radius: 7px;
          background: #f8fafc;
          color: #4a6376;
          font-size: 12px;
     }
     .ar-chip:hover {
          border-color: #8fc5bb;
          color: #116b5f;
     }
     .ar-filter-grid {
          display: grid;
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 14px;
     }
     .ar-filter-grid label {
          display: flex;
          flex-direction: column;
          gap: 6px;
          font-size: 12px;
          font-weight: 650;
          color: #506476;
     }
     .ar-filter-grid input,
     .ar-filter-grid select {
          width: 100%;
          min-width: 0;
          min-height: 42px;
          background: #fff;
          border: 1px solid #d5dfe8;
          border-radius: 8px;
          padding: 9px 11px;
          color: var(--ar-ink);
          font-size: 13px;
     }
     .ar-filter-grid input:focus,
     .ar-filter-grid select:focus {
          outline: 2px solid #a0cfc7;
          outline-offset: 1px;
     }
     .ar-more-filters {
          margin-top: 15px;
     }
     .ar-more-filters summary {
          cursor: pointer;
          font-size: 12px;
          color: #287b70;
          padding: 7px 0;
     }
     .ar-more-filters[open] .ar-filter-grid {
          margin-top: 12px;
     }
     .ar-filter-footer {
          border-top: 1px solid #eef2f5;
          margin-top: 18px;
          padding-top: 14px;
          flex-wrap: wrap;
     }
     .ar-muted {
          color: var(--ar-muted);
          font-size: 12px;
     }
     .ar-alert {
          background: #fff3e9;
          border: 1px solid #edcdb2;
          color: #804321;
          padding: 14px 18px;
          border-radius: 10px;
          margin: 15px 0;
     }
     .ar-loading {
          padding: 60px 20px;
          text-align: center;
          color: #587284;
          margin-top: 16px;
     }
     .ar-loading i {
          margin-inline-end: 8px;
     }
     .ar-context {
          justify-content: flex-start;
          flex-wrap: wrap;
          color: var(--ar-muted);
          font-size: 12px;
          margin: 23px 0 15px;
          gap: 8px 20px;
     }
     .ar-context strong {
          color: #2a4857;
     }
     .ar-applied {
          width: 100%;
          color: var(--ar-teal);
     }
     .ar-kpis {
          display: grid;
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 14px;
     }
     .ar-kpi {
          background: #fff;
          border: 1px solid var(--ar-line);
          border-top: 3px solid #9caebe;
          border-radius: 12px;
          padding: 18px 20px;
          display: flex;
          flex-direction: column;
          gap: 8px;
          min-width: 0;
     }
     .ar-kpi > span {
          color: #597082;
          font-size: 12px;
          font-weight: 600;
     }
     .ar-kpi strong {
          font-size: 27px;
          letter-spacing: -0.6px;
          font-variant-numeric: tabular-nums;
          overflow-wrap: anywhere;
     }
     .ar-kpi small {
          font-size: 11px;
          color: #7a8b98;
     }
     .ar-kpi-teal {
          border-top-color: #168b7b;
     }
     .ar-kpi-teal strong {
          color: #137767;
     }
     .ar-kpi-blue {
          border-top-color: #5179cf;
     }
     .ar-kpi-amber {
          border-top-color: #d39842;
     }
     .ar-tabs {
          display: flex;
          overflow-x: auto;
          gap: 3px;
          border-bottom: 1px solid #dbe3eb;
          margin: 25px 0 20px;
          padding-bottom: 0;
          scrollbar-width: thin;
     }
     .ar-tabs button {
          white-space: nowrap;
          padding: 13px 17px;
          border: 0;
          border-bottom: 3px solid transparent;
          background: transparent;
          color: #6b7d8e;
          font-size: 13px;
     }
     .ar-tabs button.active {
          color: #137a6a;
          border-bottom-color: #168b7b;
          font-weight: 750;
          background: #eaf4f1;
          border-radius: 8px 8px 0 0;
     }
     .ar-dashboard {
          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 18px;
     }
     .ar-trend {
          grid-column: 1/-1;
     }
     .ar-panel-title {
          padding: 20px 22px;
          border-bottom: 1px solid #edf1f5;
          align-items: flex-start;
     }
     .ar-panel-title h2,
     .ar-basis h2 {
          font-size: 16px;
          font-weight: 700;
     }
     .ar-panel-title p {
          color: var(--ar-muted);
          font-size: 12px;
          margin-top: 5px;
          line-height: 1.8;
     }
     .ar-chart {
          height: 310px;
          padding: 18px 22px;
     }
     .ar-chart-data {
          margin: 0 22px 18px;
          font-size: 12px;
          color: #517180;
     }
     .ar-chart-data summary {
          cursor: pointer;
          padding: 10px 0;
     }
     .ar-status-row {
          display: grid;
          width: calc(100% - 44px);
          grid-template-columns: 1fr auto;
          gap: 9px;
          border: 0;
          background: transparent;
          text-align: right;
          margin: 18px 22px;
          padding: 0;
          color: var(--ar-ink);
     }
     .ar-status-row > span:first-child {
          display: flex;
          align-items: center;
          gap: 8px;
     }
     .ar-status-row i {
          width: 8px;
          height: 8px;
          border-radius: 50%;
     }
     .ar-status-track,
     .ar-aging-track {
          grid-column: 1/-1;
          background: #f1f4f7;
          height: 5px;
          display: block;
          border-radius: 5px;
          overflow: hidden;
     }
     .ar-status-track > span,
     .ar-aging-track > span {
          height: 100%;
          display: block;
          border-radius: 5px;
     }
     .ar-mini-total,
     .ar-reconcile {
          display: flex;
          justify-content: space-between;
          gap: 15px;
          padding: 11px 22px;
          border-top: 1px solid #f1f4f7;
          color: #607588;
          font-size: 12px;
     }
     .ar-mini-total strong,
     .ar-reconcile strong {
          color: #29485b;
          font-variant-numeric: tabular-nums;
     }
     .ar-reconcile.strong {
          background: #f0f8f5;
          color: #15705f;
          font-weight: 700;
     }
     .ar-aging {
          padding: 18px 22px;
     }
     .ar-aging > div:first-child {
          display: flex;
          justify-content: space-between;
          gap: 10px;
          margin-bottom: 10px;
          color: #597082;
          font-size: 12px;
     }
     .ar-aging strong {
          color: #a56b21;
          font-variant-numeric: tabular-nums;
     }
     .ar-aging-track > span {
          background: #d6a158;
     }
     .ar-table-wrap {
          width: 100%;
          overflow-x: auto;
          overscroll-behavior-inline: contain;
     }
     .ar-table {
          width: 100%;
          border-collapse: collapse;
          font-size: 12px;
          color: #314b5d;
          text-align: right;
     }
     .ar-table th {
          background: #f7f9fb;
          font-size: 11px;
          color: #688092;
          font-weight: 700;
          white-space: nowrap;
          padding: 14px 16px;
          border-bottom: 1px solid #e0e8ef;
     }
     .ar-table td {
          padding: 14px 16px;
          border-bottom: 1px solid #edf2f6;
          vertical-align: middle;
          min-width: 105px;
          max-width: 260px;
          overflow-wrap: anywhere;
     }
     .ar-table tbody tr:hover {
          background: #fafcfd;
     }
     .ar-table .ar-number {
          white-space: nowrap;
          font-variant-numeric: tabular-nums;
          direction: ltr;
          text-align: right;
     }
     .ar-table-link {
          padding: 0;
          border: 0;
          background: none;
          color: #137a6a;
          text-align: inherit;
          font-weight: 650;
     }
     .ar-table-link:hover {
          text-decoration: underline;
     }
     .ar-table-link span {
          font-size: 11px;
          margin-inline-start: 5px;
          opacity: 0.6;
     }
     .ar-shortcut {
          display: block;
          font-size: 10px;
          color: #8b99a5;
          margin-top: 4px;
          text-align: right;
     }
     .ar-table td > .ar-muted {
          display: block;
          line-height: 1.4;
          font-size: 10px;
          margin-top: 4px;
     }
     .ar-status {
          display: inline-block;
          padding: 4px 9px;
          border-radius: 6px;
          white-space: nowrap;
          font-size: 10px;
          font-weight: 600;
          background: #edf4f2;
          color: #147d66;
     }
     .ar-status[data-status="unpaid"] {
          background: #fbecee;
          color: #b74350;
     }
     .ar-status[data-status="partial"] {
          background: #fcf3e6;
          color: #a66e25;
     }
     .ar-status[data-status="credit"] {
          background: #f0edfa;
          color: #6c59ac;
     }
     .ar-empty {
          text-align: center !important;
          padding: 50px 20px !important;
          color: #8898a6 !important;
     }
     .ar-pagination {
          padding: 17px 22px;
          font-size: 12px;
          color: #758a9b;
     }
     .ar-count {
          white-space: nowrap;
          border: 1px solid #dbe7ee;
          background: #f6f9fb;
          border-radius: 20px;
          padding: 4px 12px;
          color: #688093;
          font-size: 11px;
     }
     .ar-export-note {
          padding: 14px 22px;
          color: #7b8e9d;
          font-size: 11px;
          border-top: 1px solid #edf2f6;
     }
     .ar-table-progress {
          padding: 9px 22px;
          background: #ecf8f4;
          color: #147d66;
          font-size: 12px;
     }
     .ar-basis {
          margin-top: 20px;
          padding: 20px 22px;
          background: #fafdfe;
          border-color: #dce8ed;
     }
     .ar-basis h2 {
          color: #506f80;
          font-size: 13px;
          margin-bottom: 9px;
     }
     .ar-basis h2 i {
          margin-inline-end: 5px;
     }
     .ar-basis p {
          font-size: 11px;
          color: #708593;
          line-height: 1.9;
          margin-top: 6px;
     }
     .ar-basis .ar-coverage {
          color: #9a6726;
          font-weight: 650;
     }
     .ar-dialog {
          width: min(1160px, calc(100vw - 40px));
          max-height: 90vh;
          margin: auto;
          border: 1px solid #dde5ec;
          border-radius: 16px;
          padding: 0;
          color: var(--ar-ink);
          box-shadow: 0 30px 100px #13263f3d;
          background: #fff;
     }
     .ar-dialog::backdrop {
          background: #16304288;
          backdrop-filter: blur(2px);
     }
     .ar-dialog-header {
          padding: 20px 24px;
          position: sticky;
          top: 0;
          background: #fff;
          border-bottom: 1px solid #e3e9ef;
          z-index: 1;
     }
     .ar-dialog-header h2 {
          font-size: 22px;
     }
     .ar-detail-info {
          display: grid;
          grid-template-columns: repeat(5, minmax(0, 1fr));
          gap: 20px;
          padding: 22px 24px;
     }
     .ar-detail-info small {
          display: block;
          color: #8192a0;
          font-size: 11px;
          margin-bottom: 6px;
     }
     .ar-detail-info strong {
          font-size: 12px;
          overflow-wrap: anywhere;
     }
     .ar-detail-source {
          padding: 0 24px 20px;
     }
     .ar-detail-totals {
          display: grid;
          grid-template-columns: repeat(5, minmax(0, 1fr));
          gap: 1px;
          background: #dfe9eb;
          margin: 0 24px;
          border: 1px solid #dfe9eb;
          border-radius: 10px;
          overflow: hidden;
     }
     .ar-detail-totals > div {
          padding: 13px;
          background: #f5faf9;
          display: flex;
          flex-direction: column;
          gap: 5px;
     }
     .ar-detail-totals span {
          font-size: 11px;
          color: #648479;
     }
     .ar-detail-totals strong {
          font-size: 16px;
          font-variant-numeric: tabular-nums;
          overflow-wrap: anywhere;
     }
     .ar-detail-totals small {
          font-size: 9px;
     }
     .ar-dialog h3 {
          padding: 24px 24px 12px;
          font-size: 14px;
          font-weight: 700;
     }
     .ar-dialog > p.ar-muted {
          padding: 0 24px 20px;
     }
     .ar-item-contents {
          margin: 12px 24px;
          font-size: 12px;
     }
     .ar-item-contents summary {
          cursor: pointer;
          padding: 10px;
          background: #f6f9fb;
          border-radius: 7px;
          color: #4c7184;
     }
     .ar-leaves {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          padding: 12px 0;
     }
     .ar-leaves > span {
          padding: 5px 10px;
          background: #f2f6f7;
          border-radius: 5px;
     }
     .ar-leaves small {
          color: #8092a1;
          margin-inline-start: 6px;
     }
     .ar-audit {
          margin: 0 24px 12px;
          padding: 12px 16px;
          border: 1px solid #e4ebf0;
          border-radius: 9px;
          font-size: 12px;
     }
     .ar-audit > div {
          display: flex;
          justify-content: space-between;
          gap: 12px;
          flex-wrap: wrap;
     }
     .ar-audit span {
          color: #7c8f9d;
          font-size: 11px;
     }
     .ar-audit ul {
          padding-right: 18px;
          line-height: 1.8;
          margin: 8px 0 0;
     }
     .ar-audit del {
          color: #aa6c6c;
     }
     .ar-sr-only {
          position: absolute;
          width: 1px;
          height: 1px;
          padding: 0;
          margin: -1px;
          overflow: hidden;
          clip: rect(0, 0, 0, 0);
          white-space: nowrap;
          border: 0;
     }
     @media (min-width: 1500px) {
          .ar-dashboard {
               grid-template-columns: repeat(3, minmax(0, 1fr));
          }
          .ar-trend {
               grid-column: span 2;
               grid-row: span 1;
          }
     }
     @media (max-width: 1000px) {
          .accounting {
               padding: 20px;
          }
          .ar-header {
               flex-direction: column;
          }
          .ar-filter-grid {
               grid-template-columns: repeat(2, minmax(0, 1fr));
          }
          .ar-kpi {
               padding: 14px;
          }
          .ar-kpi strong {
               font-size: 23px;
          }
          .ar-detail-info,
          .ar-detail-totals {
               grid-template-columns: repeat(3, minmax(0, 1fr));
          }
     }
     @media (max-width: 650px) {
          .accounting {
               padding: 12px;
          }
          .accounting h1 {
               font-size: 24px;
          }
          .ar-header {
               gap: 15px;
               margin-bottom: 16px;
          }
          .ar-header p {
               font-size: 12px;
          }
          .ar-header > .ar-actions {
               width: 100%;
          }
          .ar-header .ar-btn {
               flex: 1;
               font-size: 12px;
               padding: 9px;
          }
          .ar-filters {
               padding: 14px;
          }
          .ar-filter-grid {
               gap: 12px 10px;
          }
          .ar-filter-grid .ar-search {
               grid-column: 1/-1;
          }
          .ar-filter-footer > .ar-muted {
               font-size: 10px;
          }
          .ar-filter-footer .ar-actions {
               margin-inline-start: auto;
          }
          .ar-presets {
               gap: 5px;
          }
          .ar-chip {
               font-size: 10px;
               padding: 5px 8px;
          }
          .ar-kpis {
               grid-template-columns: repeat(2, minmax(0, 1fr));
               gap: 9px;
          }
          .ar-kpi {
               padding: 13px 11px;
               gap: 6px;
          }
          .ar-kpi strong {
               font-size: 23px;
          }
          .ar-kpi > span {
               font-size: 10px;
          }
          .ar-kpi small {
               font-size: 9px;
          }
          .ar-context {
               font-size: 10px;
               gap: 6px 12px;
          }
          .ar-tabs {
               margin: 18px 0 14px;
          }
          .ar-tabs button {
               font-size: 12px;
               padding: 12px;
          }
          .ar-dashboard {
               grid-template-columns: minmax(0, 1fr);
               gap: 14px;
          }
          .ar-trend {
               grid-column: auto;
          }
          .ar-panel-title {
               padding: 16px;
          }
          .ar-panel-title h2 {
               font-size: 14px;
          }
          .ar-panel-title p {
               font-size: 11px;
          }
          .ar-chart {
               height: 270px;
               padding: 12px;
          }
          .ar-pagination {
               padding: 14px;
               font-size: 10px;
               flex-wrap: wrap;
          }
          .ar-pagination .ar-btn {
               padding: 7px 11px;
               font-size: 11px;
          }
          .ar-basis {
               padding: 16px;
          }
          .ar-dialog {
               width: calc(100vw - 16px);
               max-height: 94vh;
               border-radius: 12px;
          }
          .ar-dialog-header {
               padding: 15px;
          }
          .ar-dialog-header h2 {
               font-size: 19px;
          }
          .ar-detail-info {
               grid-template-columns: repeat(2, minmax(0, 1fr));
               padding: 16px;
               gap: 16px;
          }
          .ar-detail-totals {
               grid-template-columns: repeat(2, minmax(0, 1fr));
               margin: 0 16px;
          }
          .ar-audit {
               margin: 0 16px 10px;
          }
          .ar-table td,
          .ar-table th {
               padding: 12px;
          }
     }
</style>
