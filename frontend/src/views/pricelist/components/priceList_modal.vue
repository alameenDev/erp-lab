<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="t('add')"
          style="width: 80rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="create()" class="border-top-1 border-bluegray-100">
               <!-- Name and Discount Input -->
               <div class="grid mt-1">
                    <div class="col-6 pb-0">
                         <label class="block text-md mb-2">{{ t("name") }}</label>
                         <InputText required class="w-full" type="text" v-model="record.name" maxlength="255" />
                    </div>
                    <div class="col-6 pb-0" v-if="!is_constant_price">
                         <label class="block text-md mb-2">{{ t("discount") }}</label>
                         <InputText class="w-full" type="number" v-model="record.discount" />
                    </div>
                    <div class="col-6 pb-0" style="align-self: center">
                         <div class="mt-3">
                              <Checkbox v-model="is_constant_price" inputId="ingredient2" binary />
                              <label for="ingredient2" class="ml-2 mr-2">{{ t("is_constatnt_price") }}</label>
                         </div>
                    </div>
               </div>
               <br />
               <TabView v-if="is_constant_price">
                    <TabPanel :header="t('tests')">
                         <!-- Search Input -->
                         <div class="mt-4">
                              <InputText class="w-full" v-model="testsSearchQuery" :placeholder="t('search')" />
                         </div>
                         <!-- Table for Tests -->
                         <div v-if="filteredTests.length > 0" class="mt-4">
                              <table class="table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Original_Price") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(test, index) in filteredTests" :key="index">
                                             <td>{{ test.name }}</td>
                                             <td>
                                                  <InputNumber
                                                       class="w-full"
                                                       type="number"
                                                       v-model="test.price_for_customer"
                                                       :placeholder="t('Original_Price')" />
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <Paginator
                                   dir="rtl"
                                   :layz="true"
                                   :rows="pagination.per_page"
                                   :totalRecords="pagination.total"
                                   v-model:first="pagination.current_page"></Paginator>
                         </div>
                         <div v-else class="noData text-center p-5 text-danger">
                              <div>{{ t("noData") }}</div>
                         </div>
                    </TabPanel>
                    <TabPanel :header="t('cultures')">
                         <!-- Search Input -->
                         <div class="mt-4">
                              <InputText class="w-full" v-model="cultureSearchQuery" :placeholder="t('search')" />
                         </div>
                         <!-- Table for Cultures -->
                         <div v-if="filteredCultures.length > 0" class="mt-4">
                              <table class="table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Original_Price") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(culture, index) in filteredCultures" :key="index">
                                             <td>{{ culture.name }}</td>
                                             <td>
                                                  <InputNumber
                                                       class="w-full"
                                                       type="number"
                                                       v-model="culture.price_for_customer"
                                                       :placeholder="t('Original_Price')" />
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <!-- Pagination Controls -->

                              <div class="pagination-controls">
                                   <Button
                                        icon="pi pi-angle-double-right  "
                                        class="p-button mx-1"
                                        @click="cultures_prevPage"
                                        :disabled="cultures_currentPage === 1"></Button>
                                   <!--{{ cultures_totalPages }}  -->
                                   <span>{{ cultures_currentPage }}</span>
                                   <Button
                                        icon="pi pi-angle-double-left "
                                        class="p-button mx-1"
                                        @click="cultures_nextPage"
                                        :disabled="cultures_currentPage === cultures_totalPages"></Button>
                              </div>
                         </div>
                         <div v-else class="noData text-center p-5 text-danger">
                              <div>{{ t("noData") }}</div>
                         </div>
                    </TabPanel>
                    <TabPanel :header="t('packages')">
                         <!-- Search Input -->
                         <div class="mt-4">
                              <InputText class="w-full" v-model="packageSearchQuery" :placeholder="t('search')" />
                         </div>
                         <!-- Table for Packages  -->
                         <div v-if="filteredPackages.length > 0" class="mt-4">
                              <table class="table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Original_Price") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(packageItem, index) in filteredPackages" :key="index">
                                             <td>{{ packageItem.name }}</td>
                                             <td>
                                                  <InputNumber
                                                       class="w-full"
                                                       type="number"
                                                       v-model="packageItem.price_for_customer"
                                                       :placeholder="t('Original_Price')" />
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <!-- Pagination Controls -->
                              <div class="pagination-controls">
                                   <Button
                                        icon="pi pi-angle-double-right  "
                                        class="p-button mx-1"
                                        @click="packages_prevPage"
                                        :disabled="packages_currentPage === 1"></Button>

                                   <span>{{ packages_currentPage }}</span>
                                   <Button
                                        icon="pi pi-angle-double-left "
                                        class="p-button mx-1"
                                        @click="packages_nextPage"
                                        :disabled="packages_currentPage === packages_totalPages"></Button>
                              </div>
                         </div>
                         <div v-else class="noData text-center p-5 text-danger">
                              <div>{{ t("noData") }}</div>
                         </div>
                    </TabPanel>
               </TabView>

               <!-- Submit Button -->
               <div class="flex justify-content-end gap-2 mt-3 pt-3">
                    <Button size="small" :label="t('add')" severity="success" type="submit" />
                    <Button size="small" :label="t('close')" severity="danger" @click="close" />
               </div>
          </form>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { priceListStore } from "@/store/modules/priceList";
     import { usePackagesStore } from "@/store/modules/packages";
     import { useculturesStore } from "@/store/modules/cultures";
     import { usetestsStore } from "@/store/modules/tests";

     export default {
          data() {
               return {
                    testsSearchQuery: "",
                    cultureSearchQuery: "",
                    packageSearchQuery: "",
                    is_constant_price: false,
                    test_currentPage: 1,
                    packages_currentPage: 1,
                    cultures_currentPage: 1,
                    rowsPerPage: 10,
               };
          },
          computed: {
               ...mapWritableState(priceListStore, ["record", "dialog"]),
               ...mapWritableState(usePackagesStore, ["packagesList"]),
               ...mapWritableState(useculturesStore, ["cultures"]),
               ...mapWritableState(usetestsStore, ["tests", "pagination"]),
               tests_totalPages() {
                    return Math.ceil(this.tests.length / this.rowsPerPage);
               },
               packages_totalPages() {
                    return Math.ceil(this.packagesList.length / this.rowsPerPage);
               },
               cultures_totalPages() {
                    return Math.ceil(this.cultures.length / this.rowsPerPage);
               },
               // Paginated list of tests based on current page and rows per page
               paginatedTests() {
                    const start = (this.test_currentPage - 1) * this.rowsPerPage;
                    const end = start + this.rowsPerPage;
                    return this.tests.slice(start, end);
               },
               paginatedPackages() {
                    const start = (this.packages_currentPage - 1) * this.rowsPerPage;
                    const end = start + this.rowsPerPage;
                    return this.packagesList.slice(start, end);
               },
               paginatedCultures() {
                    const start = (this.cultures_currentPage - 1) * this.rowsPerPage;
                    const end = start + this.rowsPerPage;
                    return this.cultures.slice(start, end);
               },
               filteredCultures() {
                    return this.paginatedCultures.filter((culture) =>
                         culture.name.toLowerCase().includes(this.cultureSearchQuery.toLowerCase())
                    );
               },
               filteredTests() {
                    return this.tests.filter((culture) =>
                         culture.name.toLowerCase().includes(this.testsSearchQuery.toLowerCase())
                    );
               },
               filteredPackages() {
                    return this.paginatedPackages.filter((culture) =>
                         culture.name.toLowerCase().includes(this.packageSearchQuery.toLowerCase())
                    );
               },
          },
          mounted() {
               this.Getpackages();
               this.Getcultures();
               this.GetTests();
          },
          methods: {
               ...mapActions(usePackagesStore, ["Getpackages"]),
               ...mapActions(useculturesStore, ["Getcultures"]),
               ...mapActions(usetestsStore, ["GetTests", "pagination"]),
               ...mapActions(priceListStore, ["AddpriceList", "UpdatepriceList"]),

               test_prevPage() {
                    if (this.pagination.current_page > 1) {
                         this.pagination.current_page--;
                         this.GetTests();
                    }
               },
               packages_prevPage() {
                    if (this.packages_currentPage > 1) {
                         this.packages_currentPage--;
                    }
               },

               cultures_prevPage() {
                    if (this.cultures_currentPage > 1) {
                         this.cultures_currentPage--;
                    }
               }, // Navigate to the next page
               test_nextPage() {
                    if (this.pagination.current_page < this.pagination.totalPages) {
                         this.pagination.current_page++;
                         this.GetTests();
                    }
               },
               // Navigate to the next page
               cultures_nextPage() {
                    if (this.cultures_currentPage < this.cultures_totalPages) {
                         this.cultures_currentPage++;
                    }
               },

               packages_nextPage() {
                    if (this.packages_currentPage < this.packages_totalPages) {
                         this.packages_currentPage++;
                    }
               },
               create() {
                    this.record.tests = this.tests.map((test) => ({
                         id: test.id,
                         price: test.price_for_customer ?? null,
                    }));
                    this.record.cultures = this.cultures.map((culture) => ({
                         id: culture.id,
                         price: culture.price_for_customer ?? null,
                    }));
                    this.record.packages = this.packagesList.map((packageItem) => ({
                         id: packageItem.id,
                         price: packageItem.price_for_customer ?? null,
                    }));

                    this.AddpriceList().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                         this.dialog = false;
                    });
               },

               close() {
                    this.clearObjectValues(this.record);
                    this.dialog = false;
               },
          },
          watch: {
               pagination: {
                    handler() {
                         this.GetTests();
                    },
                    deep: true,
               },
          },
     };
</script>
<style scoped>
     /* Table Styling */
     tr {
          height: 55px;
     }
     td {
          border: 1px solid #0000002b;
     }
     thead {
          background: #374151;
          color: white;
          height: 35px;
     }
     .table {
          width: 100%;
          /* border: 1px solid; */
          text-align: center;
          background: #f9fafb;
     }
     /* Pagination Controls */
     .pagination-controls {
          display: flex;
          justify-content: center;
          align-items: center;
          margin-top: 15px;
     }

     .pagination-controls span {
          margin: 0 10px;
          font-weight: bold;
     }

     .btn-pagination {
          padding: 8px 12px;
          background-color: #009879;
          color: white;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          font-size: 14px;
     }

     .btn-pagination[disabled] {
          background-color: #cccccc;
          cursor: not-allowed;
     }
     .pagination-controls .p-button {
          background: none;
          color: #363785;
          border: none;
     }
     .text-danger {
          color: rgba(255, 0, 0, 0.688);
     }
</style>
