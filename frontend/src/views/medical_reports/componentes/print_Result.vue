<template>
     <div v-if="pdfUrl" class="pdf-preview">
          <iframe :src="pdfUrl" width="100%" :height="documentHeight" style="border: 1px solid #ccc"></iframe>
     </div>
     <div class="container" id="Result" ref="contentToConvert">
          <!-- normal tests -->
          <template v-if="printRecord?.tests?.length > 0 && printRecord?.tests[0]?.sub_tests === null">
               <div class="notPrintAlone">
                    <div class="container" id="Result" ref="contentToConvert">
    <result_section /> <!-- الهيدر أول مرة فقط -->

    <section v-for="(group, index) in groupedTests" :key="index">
           <!-- هنا لما يكبر العدد أو تحقق شرط خاص بك، تفرض page break -->

        <result_section  v-if="index !== 0 && index % 5 === 0" class="page-breaks"/>

      <div v-if="group.category" class="caption ">{{ group.category }}</div>
      <br>
      <div v-if="group.name" class="caption">{{ group.name }}</div>

      <table v-if="group.tests.length > 0" class="test-table">
        <thead>
          <tr>
            <th>Test</th>
            <th>Result</th>
            <th>Unit</th>
            <th>Reference Ranges</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in group.tests" :key="idx">
            <td>{{ item.report_name }}</td>
            <td>{{ item.result }}</td>
            <td>{{ item.unit }}</td>
            <td>
              <span v-for="range in getFilteredRanges(item?.test_reference_ranges)" :key="range.test_reference_range_id">
                <div v-if="range.notes">
               <p v-for="(line, index) in range.notes.split('\n')" :key="index">{{ line }}</p>
               </div>
               <p v-else>
               {{ range.from + "-" + range.to }}
               </p>            
                <p v-for="option in range.test_reference_options" :key="option">{{ option }}</p>
              </span>
            </td>
            <td>
              {{
                resultStatus?.find((s) => s.value === item.result_status_id_fk)?.label || ""
              }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <result_footer_section /> <!-- الفوتر مرة واحدة فقط -->
  </div>

               </div>
          </template>

          <!-- cultures tests -->
          <template v-if="printRecord?.cultures?.length > 0">
               <div class="notPrintAlone">
                    <section
                         class="test-details"
                         v-for="(test_group, index) in cultures"
                         :key="index"
                         :class="{ 'page-break': (index + 1) % 3 === 0 }">
                         <result_section v-if="(index + 1) % 3 === 0 || index == 0"></result_section>
                         <br />
                         <div v-if="test_group.category" class="caption">
                              {{ test_group.category }}
                         </div>
                         <br />
                         <div v-if="test_group.name" class="caption">
                              {{ test_group.name }}
                         </div>

                         <table class="test-table" v-if="cultures.cultures?.length > 0">
                              <thead>
                                   <tr>
                                        <th>test</th>
                                        <th>Result</th>
                                        <th>Unit</th>
                                        <th>reference ranges here</th>
                                        <th>Status</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="(item, index) in test_group?.tests_not_print_alone" :key="index">
                                        <td>{{ item.report_name }}</td>
                                        <td>{{ item.result }}</td>
                                        <td>{{ item.unit }}</td>
                                        <td>
                                             <span
                                                  v-for="range in getFilteredRanges(item?.test_reference_ranges)"
                                                  :key="range.id">
                                                  <p>
                                                       {{
                                                            range.notes != "" && range.notes != null
                                                                 ? range.notes
                                                                 : range.from + "-" + range.to
                                                       }}
                                                  </p>
                                                  <p v-for="option in range.test_reference_options" :key="option">
                                                       <span>{{ option }}</span>
                                                  </p>
                                             </span>
                                        </td>
                                        <td>
                                             {{
                                                  resultStatus?.find((s) => s.value === item.result_status_id_fk)
                                                       ?.label || ""
                                             }}56
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                         <result_footer_section v-if="(index + 1) % 3 === 0 || index == 0"></result_footer_section>
                    </section>
               </div>
          </template>

          <!-- seperated tests -->

          <template v-if="printRecord.tests?.length > 0">
               <result_section v-if="printRecord.tests[0].sub_tests?.length > 0"></result_section>
               <div 
               v-if="printRecord.tests[0].sub_tests?.length > 0"
               v-html="getTemplateHtml(printRecord.tests[0])" 
               class="dynamic-template">
               </div>
               <template v-else>
               <div v-for="(test_group, index) in printAlone" :key="index" >
                    <div
                         v-for="(item, index) in test_group?.tests_print_alone"
                         :key="'separate-' + index"
                         >
                         <!-- class="print-page page-break" -->

                         <hr />
                         <result_section></result_section>
                         
                         <section class="test-details">
                              <div v-if="test_group.category" class="caption">
                                   {{ test_group.category }}
                              </div>
                              <br />
                              <div v-if="test_group.name" class="caption">
                                   {{ test_group.name }}
                              </div>

                              <table class="test-table">
                                   <thead>
                                        <tr>
                                             <th>test</th>
                                             <th>Result</th>
                                             <th>Unit</th>
                                             <th>reference ranges</th>
                                             <th>Status</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr>
                                             <td>{{ item.report_name }}</td>
                                             <td>{{ item.result }}</td>
                                             <td>{{ item.unit }}</td>
                                             <td>
                                                  <span
                                                       v-for="range in getFilteredRanges(item?.test_reference_ranges)"
                                                       :key="range.id">
                                                       <p>
                                                            {{
                                                                 range.notes != "" && range.notes != null
                                                                      ? range.notes
                                                                      : range.from + "-" + range.to
                                                            }}
                                                       </p>
                                                       <p v-for="option in range?.test_reference_options" :key="option">
                                                            <span>{{ option }}</span>
                                                       </p>
                                                  </span>
                                             </td>
                                             <td>
                                                  {{
                                                       resultStatus?.find((s) => s.value === item.result_status_id_fk)
                                                            ?.label || " "
                                                  }}
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                         </section>
                         <result_footer_section></result_footer_section>
                    </div>
               </div>
               </template>
          </template>
          <!-- seperated cultures -->

          <template v-if="cultures?.length > 0">
               <div
                    class="print-page page-break"
                    v-for="(item, index) in cultures?.cultures"
                    :key="'separate-' + index">
                    <hr />
                    <result_section></result_section>

                    <section class="test-details">
                         <div v-if="item.name" class="caption">
                              {{ item.name }}
                         </div>
                         <br />
                         <div v-if="item.category" class="caption">
                              {{ item.category }}
                         </div>
                         <table class="test-table">
                              <thead>
                                   <tr>
                                        <th>test</th>
                                        <th>Result</th>
                                        <th>Unit</th>
                                        <th>reference ranges</th>
                                        <th>Status</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="(item, index) in item?.cultures" :key="index">
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.result }}</td>
                                        <td>{{ item.unit }}</td>
                                        <td>
                                             <span v-for="range in item.test_reference_ranges" :key="range">
                                                  {{ range.from + "-" + range.to }}
                                             </span>
                                        </td>
                                        <td>
                                             {{
                                                  resultStatus?.find((s) => s.value === item.result_status_id_fk)
                                                       ?.label || " "
                                             }}
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                    </section>

                    <result_footer_section></result_footer_section>
               </div>
          </template>
          <!-- seperated packages -->
          <div class="print-page page-break" v-if="printRecord?.packages?.length > 0">
               <hr />
               <result_section></result_section>
               <section class="test-details" v-for="(item, index) in printRecord?.packages" :key="index">
                    <div v-if="item.name" class="caption">
                         {{ item.name }}
                    </div>
                    <br />
                    <div v-if="item.category" class="caption">
                         {{ item.category }}
                    </div>
                    <table class="test-table">
                         <thead>
                              <tr>
                                   <th>test</th>
                                   <th>Result</th>
                                   <th>Unit</th>
                                   <th>reference ranges  </th>
                                   <th>Status</th>
                              </tr>
                         </thead>
                         <tbody>
                              <tr v-for="(i, index) in item?.tests" :key="index">
                                   <td>{{ i.name }}</td>
                                   <td>{{ i.result }}</td>
                                   <td>{{ i.unit }}</td>
                                   <td>
                                        <span
                                             v-for="range in getFilteredRanges(i?.test_reference_ranges)"
                                             :key="range.id">
                                             <p>
                                                  {{
                                                       range.notes != "" && range.notes != null
                                                            ? range.notes
                                                            : range.from + "-" + range.to
                                                  }}
                                             </p>
                                             <p v-for="option in range?.test_reference_options" :key="option">
                                                  <span>{{ option }}</span>
                                             </p>
                                        </span>
                                   </td>
                                   <td>
                                        {{ resultStatus?.find((s) => s.value === i.result_status_id_fk)?.label || " " }}
                                   </td>
                              </tr>
                              <tr v-for="(i, index) in item?.cultures" :key="index">
                                   <td>{{ i.name }}</td>
                                   <td>{{ i.result }}</td>
                                   <td>{{ i.unit }}</td>
                                   <td>
                                        <span v-for="range in i?.test_reference_ranges" :key="range">
                                             {{ range.from + "-" + range.to }}
                                        </span>
                                   </td>
                                   <td>
                                        {{ resultStatus?.find((s) => s.value === i.result_status_id_fk)?.label || " " }}
                                   </td>
                              </tr>
                         </tbody>
                    </table>
               </section>
               <result_footer_section></result_footer_section>
          </div>

          <!-- <br />
          <br />
          <br />
          <br />
          <br /> -->
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import html2pdf from "html2pdf.js";
     import { useTemplatesStore } from "@/store/modules/template";
     import JsBarcode from "jsbarcode";
     import result_section from "./result_section.vue";
     import result_footer_section from "./result_footer_section.vue";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import { useresultStatusStore } from "@/store/modules/result-status";
     import axios from "axios";
     export default {
          data() {
               return {
                    today: "",
                    pdfUrl: null,
                    documentHeight: 0,
                    patientId: null,
                    template: {}, // Store templates by ID
               };
          },
          components: {
               result_section,
               result_footer_section,
          },
          created() {
               // Set today's date in YYYY-MM-DD format when the component is created
               const today = new Date();
               const year = today.getFullYear();
               const month = String(today.getMonth() + 1).padStart(2, "0"); // Add leading zero for months
               const day = String(today.getDate()).padStart(2, "0"); // Add leading zero for days
               this.today = `${year}-${month}-${day}`;
          },
              mounted () {
               this.GetTemplates();
               this.patientId = this.$route?.params?.patientId;
               if (this.patientId) {
                    document.getElementById("Result").style.display = "block";
                    this.documentHeight = document.documentElement?.scrollHeight;
                    this.getData();
               }
          },
          computed: {
               ...mapWritableState(useTemplatesStore, ["templates", "record", "dialog", "pagination"]),
               ...mapWritableState(useresultStatusStore, ["resultStatus"]),
               ...mapWritableState(useinvoicesStore, ["printRecord"]),

               printAlone() {
                    return this.printRecord?.test_groups_all?.map((group) => ({
                         name: group?.name,
                         category: group?.category,
                         testlength: group?.tests_print_alone?.length,
                         tests_print_alone: group?.tests_print_alone,
                    }));
               },
               groupedTests() {
    if (!this.printRecord?.tests) return [];

    const groups = [];
    let currentGroup = null;

    this.printRecord.tests
      .filter(t => t.name && t.report_name) // تصفية العناصر الفارغة
      .forEach((test) => {
        if (!currentGroup || currentGroup.category !== test.category || currentGroup.name !== test.name) {
          currentGroup = {
            category: test.category,
            name: test.name,
            tests: [],
          };
          groups.push(currentGroup);
        }
        currentGroup.tests.push(test);
      });

    return groups;
  },
               // Tests that should print together

               NotprintAlone() {
                    if (!this.printRecord?.test_groups_all) return [];
                    return this.printRecord?.test_groups_all?.map((group) => ({
                         name: group?.name,
                         category: group?.category,
                         testlength: group?.tests_not_print_alone?.length,
                         tests_not_print_alone: group?.tests_not_print_alone,
                    }));
               },
               cultures() {
               const groups = this.printRecord?.test_groups_all;
               if (Array.isArray(groups)) {
               return groups.map((group) => ({
                    name: group?.name,
                    category: group?.category,
                    culturesLength: group?.cultures?.length,
                    cultures: group?.cultures,
               }));
               }
               return []; // أو null أو undefined حسب حاجتك
               }
          },

          methods: {
               ...mapActions(useinvoicesStore, ["GetinvoicesById"]),
               ...mapActions(useTemplatesStore, ["GetTemplates"]),
               async fetchTemplates() {
                         try {
                            this.templates.forEach(template => {
                                   this.template[template.id] = template.content.html; // Store template HTML
                              });
                         } catch (error) {
                              console.error("❌ Error fetching templates:", error);
                         }
                    },
                        // 2️⃣ Get the correct template for each test_group
                        getTemplateHtml(data) {
  let templateHtml = data?.content?.html || "<p>🔍 لا يوجد قالب للطباعة</p>";

  // استبدال الحقول العامة
  templateHtml = templateHtml.replace(/{{description}}/g, data?.category || "");
  templateHtml = templateHtml.replace(/{{result}}/g, data?.result || "");
  templateHtml = templateHtml.replace(/{{test}}/g, data?.name || "");
  templateHtml = templateHtml.replace(/{{name}}/g, data?.name || "");
  templateHtml = templateHtml.replace(/{{category}}/g, data?.category || "");
  templateHtml = templateHtml.replace(/{{unit}}/g, data?.unit || "");

  // ✅ استبدال جميع المتغيرات الخاصة بـ sub_tests
  if (Array.isArray(data.sub_tests)) {
    data.sub_tests.forEach((sub) => {
      const valueKey = new RegExp(`{{sub_test\\.${sub.name}\\.value}}`, "g");
      const nameKey = new RegExp(`{{sub_test\\.${sub.name}\\.name}}`, "g");
      const commentKey = new RegExp(`{{sub_test\\.${sub.name}\\.comment}}`, "g");

      templateHtml = templateHtml.replace(valueKey, sub.value || "---");
      templateHtml = templateHtml.replace(nameKey, sub.name || "---");
      templateHtml = templateHtml.replace(commentKey, sub.comment || "");
    });
  }

  // ✅ الشكل النهائي مع CSS
  return `
    <div id="custom-table-container">
      <style>
        #custom-table-container table {
          width: 100%;
          border-collapse: collapse;
          background: #fff;
        }
        #custom-table-container th, 
        #custom-table-container td {
          border: 1px solid #ccc;
          padding: 10px;
          text-align: center;
        }
        #custom-table-container th {
          background: #f8f9fa;
          font-weight: bold;
        }
      </style>
      ${templateHtml}
    </div>
  `;
}
,
               getData() {
                    this.GetinvoicesById(this.patientId).then(() => {
                         this.generatePDF();
                    });
               },
               getPatientReportLink() {
                    const url = `${window.location.origin}/result/${this.patientId}`;
                    return url;
               },
               async generatePDF() {
                    const element = this.$refs?.contentToConvert;
                    // Wait for the image to load

                    const margins = JSON.parse(localStorage.getItem('printMargins') || '{}')
                    const opt = {
                    margin: [
                    margins.top || 20,
                    margins.left || 15,
                    margins.bottom || 20,
                    margins.right || 15,
                    ],
                    filename: 'medical-report.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    }
                    // // Generate and save PDF
                    // html2pdf().set(opt).from(element).save();

                    // Generate PDF as data URL for preview

                    await html2pdf()
                         .set(opt)
                         .from(element)
                         .outputPdf("datauristring")
                         .then((pdfDataUri) => {
                              this.pdfUrl = pdfDataUri;
                         });

                    // document.getElementById("Result").style.display = "none";
               },
               getFilteredRanges(referenceRanges) {
                    const patientDetails = this.printRecord?.patient;

                    const convertToDays = (age, unit) => {
                         switch (unit) {
                              case "Days":
                                   return age; // Already in days
                              case "Months":
                                   return age * 30; // Approximation: 1 month = 30 days
                              case "Years":
                                   return age * 365; // Approximation: 1 year = 365 days
                              default:
                                   return age; // Fallback if unit is unknown
                         }
                    };

                    const patientAgeInDays = convertToDays(patientDetails?.age, patientDetails?.age_unit);

                    return referenceRanges.filter((range) => {
                         const isGenderMatch = range?.gender?.toLowerCase() === patientDetails?.gender?.toLowerCase();

                         const rangeAgeFromInDays = convertToDays(range?.age_from, range?.age_unit);
                         const rangeAgeToInDays = convertToDays(range?.age_to, range?.age_unit);

                         const isAgeMatch =
                              patientAgeInDays >= rangeAgeFromInDays && patientAgeInDays <= rangeAgeToInDays;

                         return isGenderMatch && isAgeMatch;
                    });
               },
               generateBarcodeImage(value) {
                    if (!value) return "";

                    const canvas = document.createElement("canvas");
                    JsBarcode(canvas, value, {
                         format: "CODE128",
                         displayValue: true,
                         width: 2,
                         height: 15,
                    });
                    return canvas.toDataURL("image/png");
               },
          },
     };
</script>
<style scoped>
.page-breaks {
  margin: 0 !important;
  padding: 0 !important;
  display: block;
}

.page-breaks {
  page-break-before: always;
  margin: 0 !important;
  padding: 0 !important;
}

.tests-table {
  margin-top: 0; /* يمنع المسافة فوق الجدول */
}
           @media print {
               table {
          width: 100% !important;
          border-collapse: collapse !important;
          margin-top: 10px !important;
          background: #fff !important;
          }

           th,
           td {
          border: 1px solid #ccc !important;
          padding: 10px !important;
          text-align: center !important;
          }

           th {
          background: #f8f9fa !important;
          font-weight: bold !important;
          }
           }
     #Result {
          display: none;
     }
     body {
          font-family: Arial, sans-serif;
          font-size: 14px;
          margin: 0;
          padding: 0;
          color: #000;
          font-weight: bold;
          direction: ltr;
     }

     /* Basic reset */
     * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
          font-family: Arial, sans-serif;
     }
     .sign {
          width: 100%;
          display: flex;
          justify-content: center;
          margin: 9px 0;
     }
     /* Container styling */
     .container {
          width: 100%;
          max-width: 800px;
          margin: 0 auto;
          padding: 20px;
          background-color: white;
          border: 1px solid #ddd;
          direction: ltr;
          font-weight: bold;
     }
     .caption {
          width: 100%;
          font-weight: bold;
          border: 1px solid black;
          padding: 5px;
          text-align: center;
          color: black;
          background: #dddddce0;
     }
     /* Header section */
     .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          border-bottom: 1px solid #ccc;
          padding-bottom: 10px;
     }
     .section table tr {
          margin-bottom: 1px;
     }
     .sections {
          border-top: 1px solid #000;
          border-bottom: 1px solid #000;
     }
     .test-table {
          width: 100%;
          border-collapse: collapse;
          margin: 20px 0;
     }

     .test-table th,
     .test-table td {
          border: 1px solid rgba(0, 0, 0, 0.158);
          padding: 10px;
          text-align: center;
     }

     .test-table th {
          background-color: transparent;
     }
     .summary {
          padding: 16px;
     }
     .logo img {
          max-width: 150px;
     }

     .lab-details {
          text-align: right;
     }

     .lab-details h2 {
          font-size: 18px;
          margin-bottom: 5px;
     }

     .lab-details p {
          font-size: 14px;
          margin-bottom: 2px;
     }
     .section {
          display: flex;
          flex-direction: column;
          justify-content: space-between;
     }
     .sectionItem {
          display: flex;
     }
     .border {
          border: 1px solid black;
          min-height: 25px;
          margin: 0px 7px;
          padding: 5px;
          width: 150px;
          text-align: center;
     }
     /* Patient information section */
     .patient-info {
          display: flex;
          justify-content: space-between;
          padding: 15px 0;
          border-bottom: 1px solid #ccc;
     }

     .barcode-section {
          text-align: center;
          display: flex;
     }

     .barcode-section img {
          max-width: 100px;
          margin: 10px 0;
     }

     .patient-details {
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          font-size: 14px;
     }

     /* Test details section */
     .test-details {
          padding: 15px 0;
          border-bottom: 1px solid #ccc;
          font-size: 16px;
          text-align: center;
     }

     /* Pricing information section */
     .pricing-info table {
          width: 100%;
          margin-top: 20px;
          border-collapse: collapse;
          font-size: 14px;
     }

     .pricing-info th,
     .pricing-info td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: center;
     }

     .pricing-info th {
          background-color: #f9f9f9;
          font-weight: bold;
     }

     /* For printing */
     @media print {
          body {
               margin: 0;
               padding: 0;
          }

          .container {
               width: 100%;
               max-width: 100%;
               border: none;
               box-shadow: none;
          }

          .header,
          .patient-info,
          .test-details,
          .pricing-info {
               page-break-inside: avoid;
          }
          @media print {
               .page-break {
                    page-break-before: always;
               }
          }
     }
     .page-break {
          page-break-before: always;
     }
     
     body {
          margin: 0;
          padding: 0;
     }
</style>
