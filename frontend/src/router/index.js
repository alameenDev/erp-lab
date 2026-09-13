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
                         path: "/patients",
                         name: "patients",
                         component: () => import("@/views/patients/index.vue"),
                    },

                    {
                         path: "/users",
                         name: "users",
                         component: () => import("@/views/users/index.vue"),
                    },
                    {
                         path: "/roles",
                         name: "roles",
                         component: () => import("@/views/roles/index.vue"),
                    },
                    {
                         path: "/activityLogger",
                         name: "activityLogger",
                         component: () => import("@/views/activityLogger/index.vue"),
                    },
                    {
                         path: "/test-groups",
                         name: "test-groups",
                         component: () => import("@/views/test-groups/index.vue"),
                    },
                    {
                         path: "/tests",
                         name: "tests",
                         component: () => import("@/views/tests/index.vue"),
                    },
                    {
                         path: "/samples",
                         name: "samples",
                         component: () => import("@/views/samples/index.vue"),
                    },
                    {
                         path: "/test-questions",
                         name: "test-questions",
                         component: () => import("@/views/test-questions/index.vue"),
                    },
                    {
                         path: "/categories",
                         name: "categories",
                         component: () => import("@/views/categories/index.vue"),
                    },
                    {
                         path: "/referrals",
                         name: "referrals",
                         component: () => import("@/views/referrals/index.vue"),
                    },
                    {
                         path: "/packages",
                         name: "packages",
                         component: () => import("@/views/packages/index.vue"),
                    },
                    {
                         path: "/cultures",
                         name: "cultures",
                         component: () => import("@/views/cultures/index.vue"),
                    },
                    {
                         path: "/Antibiotics",
                         name: "Antibiotics",
                         component: () => import("@/views/Antibiotics/index.vue"),
                    },
                    {
                         path: "/priceList",
                         name: "priceList",
                         component: () => import("@/views/priceList/index.vue"),
                    },
                    {
                         path: "/paymentMethods",
                         name: "paymentMethods",
                         component: () => import("@/views/paymentMethods/index.vue"),
                    },
                    {
                         path: "/invoices",
                         name: "invoices",
                         component: () => import("@/views/invoices/index.vue"),
                    },
                    {
                         path: "/medical_reports",
                         name: "medical_reports",
                         component: () => import("@/views/medical_reports/index.vue"),
                    },
                    {
                         path: "/contracts",
                         name: "contracts",
                         component: () => import("@/views/contracts/index.vue"),
                    },
                    // {
                    //      path: "/print_format",
                    //      name: "print_format",
                    //      component: () => import("@/views/print-format/index.vue"),
                    // },
                    {
                         path: "/reports",
                         name: "reports",
                         component: () => import("@/views/reports/index.vue"),
                    },
                    {
                         path: "/dashboard",
                         name: "dashboard",
                         component: () => import("@/views/dashboard/index.vue"),
                    },
               ],
          },
          {
               path: "/result/:patientId",
               name: "result",

               component: () => import("@/views/medical_reports/componentes/print_Result.vue"),
          },
          {
               path: "/invoice/:invoiceId",
               name: "invoice",
               component: () => import("@/views/invoices/componentes/print_invoice.vue"),
          },

          {
               path: "/medical-reports/:patientId",
               name: "medical-reports",
               component: () => import("@/views/medical_reports/index.vue"),
               meta: { requiresAuth: false },
          },
          {
               path: "/",
               name: "login",
               component: () => import("@/views/auth/login.vue"),
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
          {
               path: "/:catchAll(.*)",
               name: "notfound",
               component: () => import("@/views/auth/404.vue"),
          },
     ],
});

// Navigation guard to check if user is authenticated
router.beforeEach((to, from, next) => {
     const isAuthenticated = localStorage.getItem("token"); // Check if token exists in localStorage
     const requiresAuth = to.matched.some((record) => record.meta.requiresAuth); // Check if the route requires authentication
     if (requiresAuth && !isAuthenticated) {
          // Redirect to login if route requires auth and user is not authenticated
          next("/");
     } else {
          // Allow access to routes that don't require authentication
          next();
     }
});

export default router;
