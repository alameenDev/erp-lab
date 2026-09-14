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
          record: {
               id: "",
               code: "",
               title_id_fk: "",
               name: "",
               email: "",
               phone: "",
               phone_number: "",
               contract_id_fk: "",
               national_id_no: "",
               passport_no: "",
               address: "",
               nationality_id_fk: "",
               dob: "",
               gender_id_fk: "",
               gender_type_id_fk: "",
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
                    const { data } = await $http.post(`/patients/search-name`, { name: name });

                    this.searchRecords = data;

                    this.searchNameTotalCount = this.searchRecords.length;
               } else {
                    this.searchRecords = [];
               }
          },
          async GetRecords() {
               try {
                    const { data } = await $http.get("/patients");
                    const items = Array.isArray(data) ? data : [];
                    this.records = items.map((item, index) => ({
                         ...item,
                         index: index + 1,
                    }));
                    this.totalCount = this.records.length;
               } catch (error) {
                    this.records = [];
               }
          },
          async GetGenders() {
               try {
                    const { data } = await $http.get("/genders");
                    this.genders = (data || []).map((record) => ({ label: record.gender_type, value: record.id }));
               } catch (error) {
                    this.genders = [];
               }
          },
          async GetAgeUnits() {
               try {
                    const { data } = await $http.get("/age-units");
                    this.AgeUnits = (data || []).map((record) => ({ label: record.unit_name, value: record.id }));
               } catch (error) {
                    this.AgeUnits = [];
               }
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
               if (this.selectedFile && this.selectedFile instanceof File) {
                    formData.append("image", this.selectedFile);
               }
               for (let key in this.record) {
                    if (this.record[key] !== null && this.record[key] !== undefined) {
                         formData.append(key, this.record[key]);
                    }
               }
               const data = await $http.post(`/patients/create`, formData);
               this.responseData = data.data;
               this.GetRecords();
          },
          async UpdatePatient() {
               const formData = new FormData();
               if (this.selectedFile && this.selectedFile instanceof File) {
                    formData.append("image", this.selectedFile);
               }
               for (let key in this.record) {
                    if (this.record[key] !== null && this.record[key] !== undefined) {
                         formData.append(key, this.record[key]);
                    }
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
