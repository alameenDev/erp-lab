<script setup>
import { ref } from "vue";

import AppMenuItem from "./AppMenuItem.vue";

const model = ref([
     {
          // label: "Home",
          // "pi pi-fw  pi-user"
          items: [
               {
                    label: "dashboard",
                    to: "/dashboard",
                    icon: "/src/assets/dashboard.png",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "reports view"
               },
               // {
               //      label: "print_format",
               //      to: "/print_format",
               //      icon: "/src/assets/dashboard.png",
               //      roles: ["lab", "branch_lab", "user"],
               // },
               {
                    label: "invoices",
                    to: "/invoices",
                    icon: "/src/assets/invoice.png",
                    permission: "invoices view",
                    // roles: ["lab", "branch_lab", "user", "sample_collector"],
               },
               {
                    label: "medical_reports",
                    to: "/medical_reports",
                    icon: "/src/assets/medical_reports.png",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "invoices view"
               },
               {
                    label: "reports",
                    to: "/reports",
                    icon: "/src/assets/report.png",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "reports view"
               },
               {
                    label: "patients",
                    icon: "/src/assets/patient.png",
                    to: "/patients",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "patients view"
               },
               {
                    label: "priceList",
                    to: "/priceList",
                    icon: "/src/assets/price-list.png",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "price list view"
               },
               {
                    label: "the_tests",
                    icon: "/src/assets/the_tests.png",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "tests view",
                    items: [
                         {
                              label: "tests",
                              icon: "/src/assets/test.png",
                              to: "/tests",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "tests view"
                         },
                         {
                              label: "test-groups",
                              to: "/test-groups",
                              icon: "/src/assets/test_groups.png",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "test groups view"
                         },
                         {
                              label: "categories",
                              icon: "/src/assets/categories.png",
                              to: "/categories",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "categories view"
                         },

                         {
                              label: "samples",
                              to: "/samples",
                              icon: "/src/assets/sample.png",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "samples view"
                         },
                         {
                              label: "packages",
                              to: "/packages",
                              icon: "/src/assets/conveyor-belt.png",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "packages view"
                         },
                         {
                              label: "cultures",
                              to: "/cultures",
                              icon: "/src/assets/cultuares.png",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "cultures view"
                         },
                         {
                              label: "test-questions",
                              to: "/test-questions",
                              icon: "/src/assets/questions.png",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "test questions view"
                         },
                         {
                              label: "Antibiotics",
                              to: "/Antibiotics",
                              icon: "/src/assets/Antibiotics.png",
                              // roles: ["lab", "branch_lab", "user"],
                              permission: "antibiotics view"
                         },
                    ],
               },
               {
                    label: "users",
                    icon: "/src/assets/users.png",
                    to: "/users",
                    // roles: ["Admin", "lab"],
                    permission: "users view"
               },
               {
                    label: "role_permissions",
                    icon: "/src/assets/roles.png",
                    to: "/roles",
                    // roles: ["Admin", "lab"],
                    permission: "roles view"
               },
               {
                    label: "referrals",
                    icon: "/src/assets/referal.png",
                    to: "/referrals",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "referrals view"
               },
               {
                    label: "contracts",
                    icon: "/src/assets/contract.jpg",
                    to: "/contracts",
                    // roles: ["Admin", "lab", "user"],
                    permission: "contracts view"
               },
               {
                    label: "paymentMethods",
                    to: "/paymentMethods",
                    icon: "/src/assets/money.png",
                    // roles: ["lab", "branch_lab", "user"],
                    permission: "payments view"
               },

               {
                    label: "activityLogger",
                    icon: "/src/assets/activity_log.png",
                    to: "/activityLogger",
                    // roles: ["Admin", "lab", "user"],
                    permission: "activities view"
               },

               {
                    label: "logOut",
                    icon: "/src/assets/logOut.png",
                    to: "/logout",
                    // roles: ["Admin", "lab", "branch_lab", "user", "sample_collector"],
                    permission: "activities view"
               },
          ],
     },
]);
</script>

<template>
     <ul class="layout-menu">
          <template v-for="(item, i) in model" :key="item">
               <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
               <li v-if="item.separator" class="menu-separator"></li>
          </template>
     </ul>
</template>
