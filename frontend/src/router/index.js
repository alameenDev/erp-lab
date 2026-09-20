import { createRouter, createWebHistory } from "vue-router";
import AppLayout from "@/layout/AppLayout.vue";

const router = createRouter({
     history: createWebHistory("/"),
     routes: [
          {
               path: "/app",
               component: AppLayout,
               beforeEnter: (to, from, next) => {
                    return next();
               },
               meta: { requiresAuth: true },
               children: [
                    {
                         path: "/super-admin",
                         name: "super-admin-dashboard",
                         component: () => import("@/views/super-admin/dashboard.vue"),
                         meta: { title: "Super Admin | Digital Lab", description: "لوحة المدير العام - Super Admin Dashboard" },
                    },
                    {
                         path: "/super-admin/labs",
                         name: "super-admin-labs",
                         component: () => import("@/views/super-admin/labs.vue"),
                         meta: { title: "Lab Management | Digital Lab", description: "إدارة المختبرات - Lab Management" },
                    },
                    {
                         path: "/super-admin/subscriptions",
                         name: "super-admin-subscriptions",
                         component: () => import("@/views/super-admin/subscriptions.vue"),
                         meta: { title: "Subscriptions | Digital Lab", description: "الاشتراكات - Subscriptions" },
                    },
                    {
                         path: "/super-admin/activity-log",
                         name: "super-admin-activity-log",
                         component: () => import("@/views/super-admin/activity-log.vue"),
                         meta: { title: "System Activity Log | Digital Lab", description: "سجل نشاط النظام - System Activity Log" },
                    },
                    {
                         path: "/super-admin/patients",
                         name: "super-admin-patients",
                         component: () => import("@/views/super-admin/patients.vue"),
                         meta: { title: "Patients | Digital Lab", description: "المرضى - Patients Management" },
                    },
                    {
                         path: "/super-admin/copy-data",
                         name: "super-admin-copy-data",
                         component: () => import("@/views/super-admin/copy-data.vue"),
                         meta: { title: "Copy Lab Data | Digital Lab", description: "نسخ بيانات المختبر - Copy lab data between labs" },
                    },
                    {
                         path: "/patients",
                         name: "patients",
                         component: () => import("@/views/patients/index.vue"),
                         meta: { title: "Patients | Digital Lab", description: "إدارة المرضى - Patient management" },
                    },
                    {
                         path: "/users",
                         name: "users",
                         component: () => import("@/views/users/index.vue"),
                         meta: { title: "Users | Digital Lab", description: "إدارة المستخدمين - User management" },
                    },
                    {
                         path: "/roles",
                         name: "roles",
                         component: () => import("@/views/roles/index.vue"),
                         meta: { title: "Roles | Digital Lab", description: "إدارة الأدوار - Role management" },
                    },
                    {
                         path: "/activityLogger",
                         name: "activityLogger",
                         component: () => import("@/views/activityLogger/index.vue"),
                         meta: { title: "Activity Log | Digital Lab", description: "سجل النشاط - Activity log" },
                    },
                    {
                         path: "/test-groups",
                         name: "test-groups",
                         component: () => import("@/views/test-groups/index.vue"),
                         meta: { title: "Test Groups | Digital Lab", description: "مجموعات الفحوصات - Test groups" },
                    },
                    {
                         path: "/tests",
                         name: "tests",
                         component: () => import("@/views/tests/index.vue"),
                         meta: { title: "Tests | Digital Lab", description: "إدارة الفحوصات - Test management" },
                    },
                    {
                         path: "/samples",
                         name: "samples",
                         component: () => import("@/views/samples/index.vue"),
                         meta: { title: "Samples | Digital Lab", description: "إدارة العينات - Sample management" },
                    },
                    {
                         path: "/test-questions",
                         name: "test-questions",
                         component: () => import("@/views/test-questions/index.vue"),
                         meta: { title: "Test Questions | Digital Lab", description: "أسئلة الفحوصات - Test questions" },
                    },
                    {
                         path: "/categories",
                         name: "categories",
                         component: () => import("@/views/categories/index.vue"),
                         meta: { title: "Categories | Digital Lab", description: "إدارة التصنيفات - Category management" },
                    },
                    {
                         path: "/referrals",
                         name: "referrals",
                         component: () => import("@/views/referrals/index.vue"),
                         meta: { title: "Referrals | Digital Lab", description: "إدارة الإحالات - Referral management" },
                    },
                    {
                         path: "/packages",
                         name: "packages",
                         component: () => import("@/views/packages/index.vue"),
                         meta: { title: "Packages | Digital Lab", description: "إدارة الباقات - Package management" },
                    },
                    {
                         path: "/cultures",
                         name: "cultures",
                         component: () => import("@/views/cultures/index.vue"),
                         meta: { title: "Cultures | Digital Lab", description: "إدارة الزراعات - Culture management" },
                    },
                    {
                         path: "/Antibiotics",
                         name: "Antibiotics",
                         component: () => import("@/views/Antibiotics/index.vue"),
                         meta: { title: "Antibiotics | Digital Lab", description: "إدارة المضادات الحيوية - Antibiotics" },
                    },
                    {
                         path: "/priceList",
                         name: "priceList",
                         component: () => import("@/views/pricelist/index.vue"),
                         meta: { title: "Price List | Digital Lab", description: "قائمة الأسعار - Price list" },
                    },
                    {
                         path: "/paymentMethods",
                         name: "paymentMethods",
                         component: () => import("@/views/paymentMethods/index.vue"),
                         meta: { title: "Payment Methods | Digital Lab", description: "طرق الدفع - Payment methods" },
                    },
                    {
                         path: "/invoices",
                         name: "invoices",
                         component: () => import("@/views/invoices/index.vue"),
                         meta: { title: "Invoices | Digital Lab", description: "إدارة الفواتير - Invoice management" },
                    },
                    {
                         path: "/invoices/create",
                         name: "invoices-create",
                         component: () => import("@/views/invoices/form.vue"),
                         meta: { title: "Create Invoice | Digital Lab", description: "إنشاء فاتورة جديدة" },
                    },
                    {
                         path: "/invoices/:id/edit",
                         name: "invoices-edit",
                         component: () => import("@/views/invoices/form.vue"),
                         meta: { title: "Edit Invoice | Digital Lab", description: "تعديل الفاتورة" },
                    },
                    {
                         path: "/medical_reports",
                         name: "medical_reports",
                         component: () => import("@/views/medical_reports/index.vue"),
                         meta: { title: "Medical Reports | Digital Lab", description: "التقارير الطبية - Medical reports" },
                    },
                    {
                         path: "/medical_reports/update-result/:id",
                         name: "update-result",
                         component: () => import("@/views/medical_reports/update-result.vue"),
                         meta: { title: "Update Result | Digital Lab", description: "تحديث النتيجة" },
                    },
                    {
                         path: "/lab-settings",
                         name: "lab-settings",
                         component: () => import("@/views/lab-settings/index.vue"),
                         meta: { title: "Lab Settings | Digital Lab", description: "إعدادات المختبر" },
                    },
                    {
                         path: "/promo-codes",
                         name: "promo-codes",
                         component: () => import("@/views/promo-codes/index.vue"),
                         meta: { title: "Promo Codes | Digital Lab", description: "إدارة أكواد الخصم - Promo codes management" },
                    },
                    {
                         path: "/inventory",
                         name: "inventory",
                         component: () => import("@/views/inventory/index.vue"),
                         meta: { title: "Inventory | Digital Lab", description: "إدارة مخزون المختبر وتتبع الكِتّات" },
                    },
                    {
                         path: "/devices",
                         name: "devices",
                         component: () => import("@/views/devices/index.vue"),
                         meta: { title: "Devices | Digital Lab", description: "إدارة الأجهزة - Device management" },
                    },
                    {
                         path: "/contracts",
                         name: "contracts",
                         component: () => import("@/views/contracts/index.vue"),
                         meta: { title: "Contracts | Digital Lab", description: "إدارة العقود - Contract management" },
                    },
                    {
                         path: "/reports",
                         name: "reports",
                         component: () => import("@/views/reports/index.vue"),
                         meta: { title: "Reports | Digital Lab", description: "التقارير والإحصائيات - Reports" },
                    },
                    {
                         path: "/accounting-reports",
                         name: "accounting-reports",
                         component: () => import("@/views/accounting-reports/index.vue"),
                         meta: { title: "Accounting Reports | Digital Lab", description: "التقارير المحاسبية - Accounting reports" },
                    },
                    {
                         path: "/dashboard",
                         name: "dashboard",
                         component: () => import("@/views/dashboard/index.vue"),
                         meta: { title: "Dashboard | Digital Lab", description: "لوحة التحكم - Dashboard" },
                    },
                    {
                         path: "profile",
                         name: "profile",
                         component: () => import("@/views/profile/index.vue"),
                         meta: { title: "Profile | Digital Lab", description: "الملف الشخصي - Profile" },
                    },
               ],
          },
          {
               path: "/result/:patientId",
               name: "result",
               component: () => import("@/views/medical_reports/componentes/print_Result.vue"),
               meta: {
                    title: "Lab Results | Digital Lab - نتائج التحاليل - ئەنجامی تاقیکردنەوە",
                    description: "عرض نتائج التحاليل المخبرية - View laboratory test results - بینینی ئەنجامەکانی تاقیکردنەوەی تاقیگە",
               },
          },
          {
               path: "/invoice/:invoiceId",
               name: "invoice",
               component: () => import("@/views/invoices/componentes/print_invoice.vue"),
               meta: {
                    title: "Invoice | Digital Lab - الفاتورة - پسوولە",
                    description: "عرض تفاصيل الفاتورة - View invoice details - بینینی وردەکاریەکانی پسوولە",
               },
          },
          {
               path: "/portal/:token",
               name: "patient-portal",
               component: () => import("@/views/portal/PatientPortal.vue"),
               meta: {
                    title: "بوابة المريض | Digital Lab",
                    description: "نتائجك الطبية ونقاط الولاء - Your lab results and loyalty points",
               },
          },
          {
               path: "/medical-reports/:patientId",
               name: "medical-reports",
               component: () => import("@/views/medical_reports/index.vue"),
               meta: {
                    requiresAuth: false,
                    title: "Medical Reports | Digital Lab - التقارير الطبية - ڕاپۆرتەکانی پزیشکی",
                    description: "عرض التقارير الطبية للمريض - View patient medical reports - بینینی ڕاپۆرتەکانی پزیشکی نەخۆش",
               },
          },
          {
               path: "/",
               name: "welcome",
               component: () => import("@/views/welcome/index.vue"),
               meta: {
                    title: "Digital Lab | المختبر الرقمي | لابی دیجیتاڵ - سیستەمی بەڕێوەبردنی تاقیگە",
                    description:
                         "Digital Lab - نظام إدارة المختبرات الطبية الرقمي. Medical Laboratory Management System. لابی دیجیتاڵ - سیستەمی بەڕێوەبردنی تاقیگەی پزیشکی.",
               },
          },
          {
               path: "/login",
               name: "login",
               component: () => import("@/views/auth/login.vue"),
               meta: {
                    title: "Sign In | Digital Lab - تسجيل الدخول - چوونەژوورەوە",
                    description: "تسجيل الدخول إلى نظام المختبر الرقمي - Sign in to Digital Lab - چوونەژوورەوە بۆ لابی دیجیتاڵ",
               },
          },
          {
               path: "/register",
               name: "register",
               component: () => import("@/views/auth/register.vue"),
               meta: {
                    title: "Register | Digital Lab - إنشاء حساب - تۆمارکردن",
                    description: "إنشاء حساب جديد في نظام المختبر الرقمي - Create a new Digital Lab account - دروستکردنی هەژمارێکی نوێ لە لابی دیجیتاڵ",
               },
          },
          {
               path: "/logout",
               name: "logout",
               beforeEnter: (to, from, next) => {
                    let lang = localStorage.getItem("locale") ? localStorage.getItem("locale") : "ar";
                    localStorage.clear();
                    localStorage.setItem("locale", lang);
                    next("/");
               },
               meta: { requiresAuth: true },
          },
          // Error pages
          {
               path: "/error/400",
               name: "error-400",
               component: () => import("@/views/errors/400.vue"),
               meta: { requiresAuth: false, title: "Bad Request | Digital Lab" },
          },
          {
               path: "/error/401",
               name: "error-401",
               component: () => import("@/views/errors/401.vue"),
               meta: { requiresAuth: false, title: "Unauthorized | Digital Lab" },
          },
          {
               path: "/error/403",
               name: "error-403",
               component: () => import("@/views/errors/403.vue"),
               meta: { requiresAuth: false, title: "Forbidden | Digital Lab" },
          },
          {
               path: "/error/404",
               name: "error-404",
               component: () => import("@/views/errors/404.vue"),
               meta: { requiresAuth: false, title: "Not Found | Digital Lab" },
          },
          {
               path: "/error/500",
               name: "error-500",
               component: () => import("@/views/errors/500.vue"),
               meta: { requiresAuth: false, title: "Server Error | Digital Lab" },
          },
          {
               path: "/error/503",
               name: "error-503",
               component: () => import("@/views/errors/503.vue"),
               meta: { requiresAuth: false, title: "Service Unavailable | Digital Lab" },
          },
          // Catch all - 404
          {
               path: "/:catchAll(.*)",
               name: "notfound",
               component: () => import("@/views/errors/404.vue"),
               meta: { title: "Not Found | Digital Lab" },
          },
     ],
});

// Navigation guard to check if user is authenticated
router.beforeEach((to, from, next) => {
     const isAuthenticated = localStorage.getItem("token");
     const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);

     // Redirect authenticated users away from welcome and login pages
     if (isAuthenticated && (to.name === "welcome" || to.name === "login")) {
          try {
               const user = JSON.parse(localStorage.getItem("user"));
               if (user?.role_id == 1) {
                    next("/super-admin");
               } else {
                    next("/dashboard");
               }
          } catch {
               next("/dashboard");
          }
     } else if (requiresAuth && !isAuthenticated) {
          next("/login");
     } else {
          next();
     }
});

// Update document title and meta tags after each navigation
router.afterEach((to) => {
     const defaultTitle = "Digital Lab | المختبر الرقمي";
     const defaultDescription =
          "Digital Lab - نظام إدارة المختبرات الطبية الرقمي. Medical Laboratory Management System.";

     // Update page title
     document.title = to.meta.title || defaultTitle;

     // Update meta description
     const descMeta = document.querySelector('meta[name="description"]');
     if (descMeta) {
          descMeta.setAttribute("content", to.meta.description || defaultDescription);
     }

     // Update canonical URL
     const canonical = document.querySelector('link[rel="canonical"]');
     if (canonical) {
          canonical.setAttribute("href", window.location.origin + to.path);
     }

     // Update OG URL
     const ogUrl = document.querySelector('meta[property="og:url"]');
     if (ogUrl) {
          ogUrl.setAttribute("content", window.location.origin + to.path);
     }
});

export default router;
