<template>
     <div class="card filterTable pb-0">
          <div class="flex justify-content-between mb-2">
               <h5>{{ t("patientsManagment") }}</h5>
               <Button
                    v-if="havePermission('patients create')"
                    size="small"
                    class="p-button-success"
                    :label="t('addPatient')"
                    @click="addRecord"></Button>
          </div>
          <!-- <users-filter></users-filter> -->

          <div class="card flex justify-content-center mb-5" v-if="pagination.total?.toLocaleString() == 0">
               <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
          </div>

          <div v-else>
               <br />
               <br />
               <div class="globalSearch">
                    <input
                         v-model="Filters.code"
                         :placeholder="t('search') + '...'"
                         class="search p-inputtext p-component mb-2" />
                    <div class="btns">
                         <Button
                              :label="t('Clear Filters')"
                              icon="pi pi-filter-slash"
                              @click="clearFilters"
                              class="p-button-secondary mb-2" />
                         <Button
                              :label="t('Export to Excel')"
                              icon="pi pi-file-excel"
                              @click="exportToExcel"
                              class="p-button-success mb-2" />
                    </div>
               </div>

               <br />
               <br />

               <DataTable
                    size="small"
                    :value="filteredRecords"
                    scrollable
                    scrollHeight="500px"
                    responsiveLayout="scroll"
                    :paginator="true"
                    :lazy="true"
                    :rows="pagination.per_page"
                    :totalRecords="pagination.total"
                    :first="(pagination.current_page - 1) * pagination.per_page"
                    @page="onPageChange">
                    <template #empty>
                         <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
                              {{ t("noData") }}
                         </div>
                    </template>
                    <Column class="text-center" header="#" field="index" style="min-width: 10px" />
                    <Column class="text-center" field="code" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("code") }}</p>
                              <input
                                   v-model="Filters.code"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="title" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("title") }}</p>
                              <Dropdown
                                   v-model="Filters.title"
                                   :options="titles"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>
                    <Column class="text-center" field="name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("name") }}</p>
                              <input
                                   v-model="Filters.name"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="email" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("email") }}</p>
                              <input
                                   v-model="Filters.email"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>

                    <Column class="text-center" field="phone_number" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("phone_number") }}</p>
                              <input
                                   v-model="Filters.phone_number"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="lab" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab/bruanch") }}</p>
                              <input
                                   v-model="Filters.lab"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="lab_card" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("lab_card") }}</p>
                              <input
                                   v-model="Filters.lab_card"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="address" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("address") }}</p>
                              <input
                                   v-model="Filters.address"
                                   :placeholder="t('search') + '...'"
                                   class="p-inputtext p-component" />
                         </template>
                    </Column>
                    <Column class="text-center" field="contract.name" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("contract") }}</p>

                              <Dropdown
                                   v-model="Filters.contract"
                                   :options="contracts"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>
                    <Column class="text-center" field="nationality" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("nationality") }}</p>
                              <Dropdown
                                   v-model="Filters.nationality"
                                   :options="nationalities"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>
                    <Column class="text-center filter-calendar" field="dob" style="min-width: 130px">
                         <template #header>
                              <p>{{ t("dob") }}</p>
                              <Calendar
                                   v-model="Filters.dob"
                                   :placeholder="t('search') + '...'"
                                   showIcon
                                   dateFormat="yy-mm-dd" />
                         </template>
                         <template #body="slotProps">
                              {{ dateTimeFormat(slotProps.data.dob) }}
                         </template>
                    </Column>
                    <Column class="text-center" field="gender" style="min-width: 100px">
                         <template #header>
                              <p>{{ t("gender") }}</p>
                              <Dropdown
                                   v-model="Filters.gender"
                                   :options="genders"
                                   optionLabel="label"
                                   optionValue="label"
                                   :placeholder="t('search') + '...'" />
                         </template>
                    </Column>
                    <Column class="text-center" field="actions" style="min-width: 200px">
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <Button
                                   v-if="havePermission('patients edit')"
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button>
                              <Button
                                   v-if="havePermission('patients delete')"
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                              <Button
                                   icon="pi pi-id-card"
                                   severity="success"
                                   class="p-button-rounded mx-1"
                                   @click="showLabCard(slotProps.data)"></Button>
                              <!-- <Button
                                   icon="pi pi-whatsapp"
                                   severity="success"
                                   class="p-button-rounded mx-1"
                                   @click="sendMsg(slotProps.data.phone_number)"></Button> -->
                         </template>
                    </Column>
               </DataTable>
               <!-- <Paginator dir="rtl" :rows="25" :totalRecords="totalCount" v-model:first="filter.pageNumber"></Paginator> -->
          </div>
          <patientModal></patientModal>
          <whatsappModal></whatsappModal>
          <labCardModal></labCardModal>
     </div>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import patientModal from "./components/patient-modal.vue";
     import whatsappModal from "./components/whatsApp-modal.vue";
     import labCardModal from "./components/labCard-modal.vue";
     import { usePatientsStore } from "@/store/modules/patients";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { useContractsStore } from "@/store/modules/contracts";
     import { useNationalitiesStore } from "@/store/modules/nationalities";
     import { useTitlesStore } from "@/store/modules/titles";
     import { format } from "date-fns";
     import * as XLSX from "xlsx";
       import { useAuthStore } from '@/store/modules/auth'
     export default {
          data() {
               return {
                    Filters: {
                         code: "",
                         title: "",
                         name: "",
                         email: "",
                         lab: "",
                         phone_number: "",
                         lab_card: "",
                         address: "",
                         nationality: "",
                         dob: null,
                         contract: "",
                         gender: "",
                    },
                    globalFilter: "", // Global filter input
               };
          },
          components: {
               patientModal,
               whatsappModal,
               labCardModal,
          },

          mounted() {
               this.GetRecords();
          },
          computed: {
               ...mapWritableState(usePatientsStore, [
                    "records",
                    "totalCount",
                    "AgeUnits",
                    "record",
                    "genders",
                    "dialog",
                    "whatsDialog",
                    "labDialog",
                    "MsgRecord",
                    "pagination",
               ]),
               ...mapWritableState(useNationalitiesStore, ["nationalities"]),
               ...mapWritableState(useTitlesStore, ["titles"]),
               ...mapWritableState(useContractsStore, ["contracts"]),
               ...mapWritableState(useAuthStore, ["havePermission"]),

               filteredRecords() {
                    return this.records.filter((record) => {
                         const matchesGlobalFilter = this.globalFilter
                              ? record.code?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.title?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.name?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.email?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.lab?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.phone_number?.includes(this.globalFilter) ||
                                record.lab_card?.includes(this.globalFilter) ||
                                record.address?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.nationality?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.dob?.includes(this.globalFilter) ||
                                record.gender?.toLowerCase().includes(this.globalFilter.toLowerCase()) ||
                                record.contract?.name?.toLowerCase().includes(this.globalFilter.toLowerCase())
                              : true;

                         const matchesColumnFilters =
                              (!this.Filters.code ||
                                   record.code?.toLowerCase().includes(this.Filters.code.toLowerCase())) &&
                              (!this.Filters.title ||
                                   record.title?.toLowerCase().includes(this.Filters.title.toLowerCase())) &&
                              (!this.Filters.name ||
                                   record.name?.toLowerCase().includes(this.Filters.name.toLowerCase())) &&
                              (!this.Filters.email ||
                                   record.email?.toLowerCase().includes(this.Filters.email.toLowerCase())) &&
                              (!this.Filters.lab ||
                                   record.lab?.toLowerCase().includes(this.Filters.lab.toLowerCase())) &&
                              (!this.Filters.phone_number ||
                                   record.phone_number?.includes(this.Filters.phone_number)) &&
                              (!this.Filters.lab_card || record.lab_card?.includes(this.Filters.lab_card)) &&
                              (!this.Filters.address ||
                                   record.address?.toLowerCase().includes(this.Filters.address.toLowerCase())) &&
                              (!this.Filters.nationality ||
                                   record.nationality
                                        ?.toLowerCase()
                                        .includes(this.Filters.nationality?.toLowerCase())) &&
                              (!this.Filters.dob || this.dateMatches(record.dob, this.Filters.dob)) &&
                              (!this.Filters.gender || record.gender == this.Filters.gender) &&
                              (!this.Filters.contract || record.contract?.name == this.Filters.contract);

                         return matchesGlobalFilter && matchesColumnFilters;
                    });
               },
          },
          methods: {
               ...mapActions(usePatientsStore, ["GetRecords", "AddPatient", "RemovePatient"]),
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemovePatient();
                         }
                    });
               },
               onPageChange(event) {
                    this.pagination.current_page = event.page + 1;

                    // PrimeVue pages start from 0
                    this.GetRecords();
               },
               dateMatches(recordDate, filterDate) {
                    if (!recordDate || !filterDate) return false;
                    const formattedRecordDate = format(new Date(recordDate), "yyyy-MM-dd");
                    const formattedFilterDate = format(new Date(filterDate), "yyyy-MM-dd");
                    return formattedRecordDate === formattedFilterDate;
               },

               clearFilters() {
                    this.Filters = {
                         code: "",
                         title: "",
                         name: "",
                         lab: "",
                         email: "",
                         phone_number: "",
                         lab_card: "",
                         address: "",
                         nationality: "",
                         dob: null,
                         gender: "",
                         contract: "",
                    };
                    this.globalFilter = ""; // Clear global filter
               },

               exportToExcel() {
                    // Create a worksheet from the filtered records
                    const ws = XLSX.utils.json_to_sheet(this.filteredRecords, {
                         header: [
                              "id",
                              "code",
                              "title",
                              "name",
                              "email",
                              "lab",
                              "role",
                              "phone_number",
                              "lab_card",
                              "address",
                              "contract_name",
                              "nationality",
                              "dob",
                              "gender",
                              "age",
                              "age_unit",
                              "passport_no",
                              "national_id_no",
                         ],
                    });

                    // Create a new workbook and append the worksheet
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

                    // Write the workbook to a file
                    XLSX.writeFile(wb, "export.xlsx");
               },
               editRecord(record) {
                    Object.assign(this.record, record);
                    this.record.title_id_fk = this.titles.find((v) => v.label === record?.title)?.value ?? null;
                    this.record.age_unit_id_fk = this.AgeUnits.find((v) => v.label === record?.age_unit)?.value ?? null;
                    this.record.gender_id_fk = this.genders.find((v) => v.label === record?.gender)?.value ?? null;
                    this.record.contract_id_fk =
                         this.contracts.find((v) => v.label === record.contract?.name)?.value ?? null;
                    this.record.nationality_id_fk =
                         this.nationalities.find((v) => v.label === record?.nationality)?.value ?? null;
                    this.dialog = true;
               },
               addRecord() {
                    this.clearObjectValues(this.record);
                    this.dialog = true;
               },
               sendMsg(phone) {
                    this.MsgRecord.phone = phone;
                    this.whatsDialog = true;
               },
               showLabCard(record) {
                    Object.assign(this.record, record);
                    this.labDialog = true;
               },
          },
                      watch: {
               Filters: {
                    deep: true,
                    handler() {
                         this.pagination.current_page = 1; 
                         this.GetRecords(this.Filters); 
                    }
               }
          },
     };
</script>
<style scoped></style>
