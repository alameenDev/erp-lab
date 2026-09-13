<template>
     <div id="app" :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <!-- <loading v-if="IsLoading" /> -->
          <router-view></router-view>
     </div>
</template>

<script>
import loading from "@/components/loading.vue";
import { LoaderStore } from "@/store/modules/loader";
import { useAuthStore } from "@/store/modules/auth";
import { mapWritableState } from "pinia";
import { $http } from "@/plugins/axios";

export default {
     components: {
          loading,
     },
     computed: {
          ...mapWritableState(LoaderStore, ["IsLoading"]),
          ...mapWritableState(useAuthStore, ["setPermissions"]),
          lang() {
               return localStorage.getItem("locale") ? localStorage.getItem("locale") : "ar";
          },
     },
     async mounted() {
          if (this.isUserLoggedIn()) {
               const user = JSON.parse(localStorage.getItem("user"));
               console.log("user logged in" ,this.user);
               const permissions = localStorage.getItem("rolePermissions");

               try {
                    const { data } = await $http.get(`/permissions?role_id=${user.role_id}`);
                    console.log(data.permissions, "permissions");
                      await  this.allPermissionFullNames(data.permissions);
                     await this.setPermissions(data.permissions)
                    const newPermissions = JSON.stringify(data.permissions);
                    console.log('test all permissions',this.allPermissionFullNames(data.permissions));

               } catch (error) {
                    console.log("Error fetching permissions:", error);
               }
          }
     },
     methods: {
          isUserLoggedIn() {
               return !!localStorage.getItem("token");
          },
          allPermissionFullNames(state) {
               return state.flatMap(group =>
                    group.permissions.map(p => p.full_name)
               );
          }
     },

};
</script>
<style lang="scss">
.tiptap.ProseMirror {
     min-height: 300px !important;
     border: 1px solid #ddd !important;
     padding: 10px !important;
}

.boldText {
     color: #004e54;
     font-weight: bold;
}

.reports .p-datatable-table {
     min-height: 212px !important;
}

.p-button-success {
     background-color: #004e54 !important;
     border-color: #004e54 !important;
     color: white !important;
}

.p-datatable .p-column-header-content {
     justify-content: center !important;
}

label {
     font-weight: bold;
}

/* Customize the scrollbar width and background */
::-webkit-scrollbar {
     width: 2px;
     height: 12px;
}

/* Background of the scrollbar */
::-webkit-scrollbar-track {
     background: #f1f1f1;
     border-radius: 10px;
}

/* The scrollbar handle */
::-webkit-scrollbar-thumb {
     background: #888;
     border-radius: 10px;
}

/* Hover effect on the scrollbar handle */
::-webkit-scrollbar-thumb:hover {
     background: #555;
}

/* Optional: Customize the scrollbar corner when both vertical and horizontal scrollbars are visible */
::-webkit-scrollbar-corner {
     background: #f1f1f1;
}

.en .p-dialog {
     direction: ltr;
}

.filterTable {
     .globalSearch {
          display: flex;
          justify-content: space-between;

          .search {
               width: 46%;
               text-align: -webkit-auto;
               padding: 0 30px !important;
               border-radius: 7px;
               margin: 6px;
          }

          .btns {
               button {
                    margin: 2px;
               }
          }
     }

     .p-datatable .p-column-header-content {
          justify-content: center;
          flex-direction: column;
     }

     .p-inputtext {
          padding: 4px 0 !important;
          width: 68%;
          text-align: center;
          border-radius: 2px;
     }

     .filter-calendar .p-button-icon-only {
          background: rgb(0 0 0 / 5%);
          color: #6f6c6c;
          border-radius: 2px;
          border-color: #d1d5db85;
          padding: 4px;
     }

     .noData {
          text-align: center;
          line-height: 6;
          font-size: large;
          font-weight: 500;
          color: #f43c6b;
     }

     .p-dropdown {
          padding: 0 7px;
     }
}

.filter {
     align-items: flex-end;
}

.text-success {
     color: #2eb560;
}

body,
label {
     text-transform: capitalize;
}

.details {
     border: 2px solid rgb(59 130 246 / 39%) !important;
}

.table thead {
     background: none !important;
     color: black !important;
}

/* Add gap between icon and text in menu items */
.p-menu .p-menuitem-link {
     gap: 10px !important;
}

/* Change button icon margin to left instead of right */
.p-button .p-button-icon-left {
     margin-right: 0 !important;
     margin-left: 0.5rem !important;
}
</style>
