<template>
     <div class="container" id="thermalRecord">
          <br />
          <br />
          <br />
          <br />
          <br />
          <br />
          <div class="elements">
               <section class="patient-details">
                    <div class="parcod">
                         <div style="display: flex; align-items: center">
                              <span><strong>Barcode:</strong></span>
                              <span style="display: flex; flex-direction: column; align-items: center">
                                   <img :src="generateBarcodeImage(printRecord?.barcode)" alt="Barcode" />
                                   <span>{{ printRecord?.barcode }}</span>
                              </span>
                         </div>
                    </div>
                    <p>
                         <strong>Patient ID:</strong>
                         <span style="display: flex; flex-direction: column; align-items: center">
                              <img :src="generateBarcodeImage(printRecord?.patient?.code)" alt="Barcode" />

                              <span>{{ printRecord?.patient?.code }}</span>
                         </span>
                    </p>
                    <p>
                         <strong>patient name :</strong>
                         <strong>{{ printRecord?.patient?.name }}</strong>
                    </p>
                    <p>
                         <strong>age / Sex :</strong>
                         <strong>
                              {{ printRecord?.patient?.age + printRecord?.patient?.age_unit }} /
                              {{ printRecord?.patient?.gender }}
                         </strong>
                    </p>
                    <p>
                         <strong>Request Date :</strong>
                         <strong>{{ dateTimeFormat(printRecord?.registration_date) }}</strong>
                    </p>
                    <p>
                         <strong>Result date:</strong>
                         <strong>{{ dateTimeFormat(printRecord?.result_date) }}</strong>
                    </p>
               </section>
               <hr />
               <section class="test-details">
                    <table class="test-table">
                         <thead>
                              <tr>
                                   <th>Test</th>
                                   <th>Price</th>
                                   <th>test type</th>
                              </tr>
                         </thead>
                         <tbody>
                              <tr v-for="(item, index) in printRecord?.tests" :key="index">
                                   <td>{{ item.report_name }}</td>
                                   <td>{{ item.price }}</td>
                                   <td>tests</td>
                              </tr>
                              <tr v-for="(item, index) in printRecord?.cultures" :key="index">
                                   <td>{{ item.name }}</td>
                                   <td>{{ item.price }}</td>
                                   <td>cultures</td>
                              </tr>
                              <tr v-for="(item, index) in printRecord?.packages" :key="index">
                                   <td>{{ item.name }}</td>
                                   <td>{{ item.price }}</td>
                                   <td>packages</td>
                              </tr>
                         </tbody>
                    </table>
               </section>

               <section
                    class="summary"
                    style="margin-top: 5px; display: flex; justify-content: space-between; flex-direction: column">
                    <div class="summaryItm">
                         <strong>Total:</strong>
                         {{ printRecord?.total }}
                    </div>

                    <div class="summaryItm">
                         <strong>paid:</strong>
                         {{ printRecord?.paid }}
                    </div>

                    <div class="summaryItm">
                         <strong>Due:</strong>
                         {{ printRecord?.total - printRecord?.paid }}
                    </div>
               </section>

               <section class="footer">
                    <p><strong>امسح الباركود و احصل على النتائج مباشرة</strong></p>

                    <QrcodeVue
                         :value="getPatientReportLink(printRecord?.patient?.id)"
                         :size="90"
                         level="H"
                         render-as="svg"
                         class="qr-code" />
               </section>
               <br />
               <br />
               <div>مختبر التعاون الطبي التخصصي يتمنى لكم الصحة و العافية</div>
          </div>
          <br />
          <br />
          <br />
          <br />
     </div>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import JsBarcode from "jsbarcode";
     import QrcodeVue from "qrcode.vue";

     import { useinvoicesStore } from "@/store/modules/invoices";

     export default {
          components: {
               QrcodeVue,
          },
          computed: {
               ...mapWritableState(useinvoicesStore, ["printRecord"]),
          },

          methods: {
               getPatientReportLink(patientId) {
                    // Construct the full URL to the medical reports page
                    // This could be a relative or absolute URL depending on your routing
                    return `${window.location.origin}/medical-reports/${patientId}`;
                    // Or if using vue-router
                    // return this.$router.resolve({
                    //   name: 'medical-reports',
                    //   params: { patientId: patientId }
                    // }).href
               },
               generateBarcodeImage(value) {
                    if (!value) return "";

                    const canvas = document.createElement("canvas");
                    document.body.appendChild(canvas); // Append canvas to the DOM
                    JsBarcode(canvas, value, {
                         format: "CODE128",
                         displayValue: false,
                         width: 1,
                         height: 10,
                    });
                    const barcodeImage = canvas.toDataURL("image/png");
                    document.body.removeChild(canvas); // Remove canvas from the DOM
                    return barcodeImage;
               },
          },
     };
</script>

<style scoped>
     #thermalRecord {
          display: none;
     }
</style>
