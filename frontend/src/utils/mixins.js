import { UserRole } from "@/enums";
import en from "./en.json";
import ar from "./ar.json";
const Mixins = {
     computed: {
          User() {
               return localStorage.user ? JSON.parse(localStorage.user) : null;
          },
          Role() {
               return localStorage.user ? UserRole.getLabelByValue(JSON.parse(localStorage.user).role_id) : null;
          },
          ImageURL() {
               return import.meta.env.VITE_ImageURL;
          },

          lang() {
               return localStorage.getItem("locale") ? localStorage.getItem("locale") : "ar";
          },
     },
     methods: {
          setLocale: (locale) => {
               localStorage.setItem("locale", locale);
          },

          t: (key) => {
               let local = localStorage.getItem("locale") == "en" ? en : ar;
               return local[key];
          },

          dateFormat: (date) => {
               return date ? date.slice(0, 10) : "-";
          },
          dateTimeFormat: (dateTimeStr) => {
               if (!dateTimeStr) {
                    return "";
               }
               const dateTime = new Date(dateTimeStr);
               const year = dateTime.getFullYear();
               const month = ("0" + (dateTime.getMonth() + 1)).slice(-2);
               const date = ("0" + dateTime.getDate()).slice(-2);
               const hours = ("0" + dateTime.getHours()).slice(-2);
               const minutes = ("0" + dateTime.getMinutes()).slice(-2);
               return `${hours}:${minutes} ${year}-${month}-${date}`;
          },
          timeFormat: (dateTimeStr) => {
               let dateTime = new Date(dateTimeStr);

               if (isNaN(dateTime)) {
                    return "";
               }

               let hours = dateTime.getHours();
               let minutes = dateTime.getMinutes();
               let ampm = hours >= 12 ? "م" : "ص";
               hours = hours % 12;
               hours = hours ? hours : 12;
               minutes = minutes < 10 ? "0" + minutes : minutes;

               return `${hours}:${minutes} ${ampm}`;
          },
          commaFormat(value) {
               // Remove any non-digit characters
               const numStr = value?.toString().replace(/[^\d]/g, "");

               // Split the string into groups of three digits from right to left
               let result = "";
               for (let i = numStr?.length - 1, j = 1; i >= 0; i--, j++) {
                    result = numStr[i] + result;
                    if (j % 3 === 0 && i !== 0) {
                         result = "," + result;
                    }
               }

               return result;
          },
          clearObjectValues(record) {
               Object.keys(record).forEach((key) => {
                    record[key] = "";
               });
               return record;
          },
          objectToFormData(data) {
               const formData = new FormData();
               for (const key in data) {
                    if (typeof data[key] === "object") {
                         // Handle nested objects
                         for (const subKey in data[key]) {
                              if (typeof data[key][subKey] === "object") {
                                   // Handle nested objects in attachments
                                   for (const subSubKey in data[key][subKey]) {
                                        const fieldName = `${key}[${subKey}].${subSubKey}`;
                                        formData.append(fieldName, data[key][subKey][subSubKey]);
                                   }
                              } else {
                                   const fieldName = `${key}[${subKey}]`;
                                   formData.append(fieldName, data[key][subKey]);
                              }
                         }
                    } else {
                         formData.append(key, data[key]);
                    }
               }
               return formData;
          },
          alertError(message) {
               var toast = document.createElement("div");
               toast.className = "error-toast";
               toast.innerHTML = "خطأ : " + message;
               document.body.appendChild(toast);
               setTimeout(function () {
                    document.body.removeChild(toast);
               }, 3000);
          },
          alertSuccess(message) {
               var toast = document.createElement("div");
               toast.className = "success-toast";
               toast.innerHTML = message;
               document.body.appendChild(toast);
               setTimeout(function () {
                    document.body.removeChild(toast);
               }, 3000);
          },
          canAccess(roles) {
               if (this.Role) {
                    return roles.some((role) => this.Role === role);
               }
               return false;
          },
          cannotAccess(roles) {
               if (this.Role) {
                    return roles?.every((role) => this.Role !== role);
               }
               return true;
          },
     },
};

export default Mixins;
