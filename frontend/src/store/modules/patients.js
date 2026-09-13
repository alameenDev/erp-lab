import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const usePatientsStore = defineStore("patient", {
     state: () => ({
          totalCount: 0,
          whatsDialog: false,
          PatientRolestotalCount: 0,
          labDialog: false,
          records: [],
          genders: [],
          selectedFile: "",
          AgeUnits: [],
          searchRecords: [],
          responseData: [],
          searchNameTotalCount: "",
          dialog: false,
          pagination: {
               pageNumber: 1,
               total: null,
               per_page: 25,
               current_page: 1,
               last_page: null,
               from: null,
               to: null,
          },
          record: {
               id: "",
               code: "",
               title_id_fk: "",
               name: "",
               email: "",
               phone_number: "",
               contract_id_fk: "",
               national_id_no: "",
               passport_no: "",
               address: "",
               nationality_id_fk: "",
               dob: "",
               gender_id_fk: "",
               age: "",
               age_unit_id_fk: "",
          },
     }),
     actions: {
          resetFilter() {
               this.filter = {
                    pageSize: 10,
                    pageNumber: 1,
                    Patientname: null,
                    name: null,
               };
          },
          async searchByname(name) {
               if (name) {
                    const { data } = await $http.post(`/invoices/search-name`, { name: name });

                    this.searchRecords = data;

                    this.searchNameTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async GetRecords(filters) {
               const { data } = await $http.get("/patients", {
                    params: {
                         page: this.pagination.current_page,
                         ...filters,
                    },
               });
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.records = data.data.map((item, index) => ({
                    ...item,
                    index: this.pagination.from + index,
               }));
               this.pagination.total = data.pagination.total;
               this.pagination.per_page = data.pagination.per_page;
               this.pagination.current_page = data.pagination.current_page;
               this.pagination.last_page = data.pagination.last_page;
               this.pagination.from = data.pagination.from;
               this.pagination.to = data.pagination.to;
          },
          async GetGenders() {
               const { data } = await $http.get("/genders");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.genders = data.map((record) => ({ label: record.gender_type, value: record.id }));
          },
          async GetAgeUnits() {
               const { data } = await $http.get("/age-units");
               // let pageNumber = Math.floor(this.filter.pageNumber / this.filter.pageSize) + 1;
               this.AgeUnits = data.map((record) => ({ label: record.unit_name, value: record.id }));
          },
          /**
           * Asynchronously adds a new patient record.
           *
           * This function creates a FormData object, appends the selected file and
           * patient record data to it, and sends a POST request to the server to
           * create a new patient. Upon successful creation, it updates the response
           * data and retrieves the updated list of patient records.
           *
           * and the records are updated.
           */
          async AddPatient() {
               const formData = new FormData();
               formData.append("image", this.selectedFile);
               for (let key in this.record) {
                    formData.append(key, this.record[key]);
               }
               const data = await $http.post(`/patients/create`, formData);
               this.responseData = data.data;
               this.GetRecords();
          },
          async UpdatePatient() {
               const formData = new FormData();
               formData.append("image", this.selectedFile);
               for (let key in this.record) {
                    formData.append(key, this.record[key]);
               }
               const data = await $http.post(`/patients/update`, formData);
               this.responseData = data.data.patient;
               this.GetRecords();
          },
          async RemovePatient() {
               await $http.delete("/patients/delete", {
                    data: { id: this.record.id },
               });
               this.GetRecords();
          },
          async whatsapp(phone, message) {
               let MsgRecord = {
                    phone: phone,
                    message: message,
               };
               await $http.post(`/whatsapp/message`, MsgRecord);
          },
     },
});
