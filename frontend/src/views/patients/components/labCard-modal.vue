<template>
     <Dialog
          v-model:visible="labDialog"
          class="card"
          modal
          :header="t('patient_card')"
          style="width: 70rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <div class="border-bluegray-100">
               <div class="grid top">
                    <div class="col-3 img">
                         <img :src="record?.image" alt="Image" class="mb-3" />
                    </div>
                    <div class="col-6 info">
                         <div class="titles">
                              <p>{{ t("name") }} :</p>
                              <p>{{ t("dob") }} :</p>
                              <p>{{ t("gender") }} :</p>
                              <p>{{ t("phone_number") }} :</p>
                              <p>{{ t("address") }} :</p>
                              <p>{{ t("national_id_no") }} :</p>
                         </div>
                         <div class="data">
                              <p>{{ record?.name }}</p>
                              <p>{{ record?.dob }}</p>
                              <p>{{ record?.gender }}</p>
                              <p>{{ record?.phone_number }}</p>
                              <p>{{ record?.address }}</p>
                              <p>{{ record?.national_id_no }}</p>
                         </div>
                    </div>
                    <div class="col-3">
                         <!-- QR Code Component -->
                         <QrcodeVue
                              :value="getPatientReportLink(record.id)"
                              :size="200"
                              level="H"
                              render-as="svg"
                              class="qr-code" />
                    </div>
               </div>

               <div class="flex justify-content-center border-bluegray-100 mt-2">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               </div>
          </div>
     </Dialog>
</template>

<script>
     import { mapActions, mapWritableState } from "pinia";
     import { usePatientsStore } from "@/store/modules/patients";
     import QRCode from "qrcode";
     import QrcodeVue from "qrcode.vue";
     import { nextTick } from "vue";
     export default {
          components: {
               QrcodeVue,
          },
          computed: {
               ...mapWritableState(usePatientsStore, ["labDialog", "record"]),
          },
          mounted() {},
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
               close() {
                    this.labDialog = false;
                    this.clearObjectValues(this.MsgRecord);
               },
          },
     };
</script>
<style scoped lang="scss">
     .top {
          background: #e3ebf859;
     }
     .img img {
          width: 100%;
          aspect-ratio: 1 / 1.3;

          border-radius: 8px;
     }
     .info {
          display: flex;
          justify-content: flex-start;

          padding: 23px;
     }
     .titles {
          font-weight: bold;
     }
     .grid {
          justify-content: center;
          align-items: center;
          padding-top: 25px;
     }
     .data {
          margin: 0 20px;
          text-align: center;
          color: #6b7280;
          font-weight: 500;
     }
</style>
