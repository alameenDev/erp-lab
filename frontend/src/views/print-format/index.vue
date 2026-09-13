<template>
  <div class="card filterTable pb-0">
    <div class="flex justify-content-between mb-2">
               <h5>{{ t("samples") }}</h5>
               <Button
              
                    size="small"
                    :label="t('add')"
                    @click="addRecord"></Button>
          </div>
    <DataTable size="small" :value="templates" scrollable scrollHeight="700px" responsiveLayout="scroll"
        :paginator="true"
        :loading="loading"
        :lazy="true"
        :rows="pagination.per_page"
        :totalRecords="pagination.total"
        :first="(pagination.current_page - 1) * pagination.per_page"
        @page="onPageChange"
      >
      <template #loading>
    <div class="p-datatable-loading-overlay p-d-flex p-ai-center p-jc-center">
      <i class="pi pi-spin pi-spinner text-white" style="font-size: 2rem"></i>
      <span class="mx-2 my-6 text-white">جاري تحميل البيانات...</span>
    </div>
  </template>
      <template #empty v-if="!loading">
        <div class="noData p-d-flex p-ai-center p-jc-center" style="height: 100px">
          {{ t("noData") }}
        </div>
      </template>
      <Column class="text-center" header="name" field="name" style="min-width: 100px" />
      <Column class="text-center" header="type" field="type" style="min-width: 100px" />
      <Column class="text-center" header="is_test" field="test_or_culture_name" style="min-width: 100px" />
      <Column header="content" field="content">
        <template #body="slotProps">
          <Button @click="showTemplate(slotProps.data)" icon="pi pi-eye" class="p-button-rounded p-button-info" />
        </template>
      </Column>
      <Column class="text-center" field="actions">
                         <template #header>
                              <p>{{ t("actions") }}</p>
                         </template>
                         <template #body="slotProps">
                              <!-- <Button
                                   icon="pi pi-pencil"
                                   class="p-button-rounded mx-1"
                                   @click="editRecord(slotProps.data)"></Button> -->
                              <Button
                                   icon="pi pi-trash"
                                   class="p-button-rounded mx-1 p-button-danger"
                                   @click="deleteRecord(slotProps.data)"></Button>
                         </template>
                    </Column>
    </DataTable>
    <!-- 🔹 Dialog لعرض تفاصيل القالب -->
    <Dialog v-model:visible="dialogVisible" modal header="عرض القالب" :style="{ width: '50vw' }">
      <div v-html="selectedTemplate.content.html" class="preview"></div>
    </Dialog>
    
    <FormatModal ></FormatModal>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapWritableState } from "pinia";
import { useTemplatesStore } from "@/store/modules/template";
import { showAlertWithConfirm } from "@/utils/helper";
import FormatModal from "@/views/print-format/components/format_modal.vue"



export default {

  data() {
    return {
      dialogVisible: false, // التحكم في إظهار أو إخفاء الـ Dialog

    };
  },
  components:{
    FormatModal
  },
  computed: {
    ...mapWritableState(useTemplatesStore, ["templates", "record", "dialog", "pagination" , "loading"]),

  },
  methods: {
    ...mapActions(useTemplatesStore, ["addTemplate" ,"RemoveTemplates"]),
    ...mapGetters(useTemplatesStore, ["filteredtests"]),
    ...mapActions(useTemplatesStore, ["GetTemplates"]),
    onPageChange(event) {
                    this.pagination.current_page = event.page + 1;

                    // PrimeVue pages start from 0
                    this.GetTemplates();
               },
    showTemplate(template) {
      this.selectedTemplate = template; // تخزين القالب المحدد
      this.dialogVisible = true; // إظهار Dialog
    },
    addRecord() {
                    this.clearObjectValues(this.record);
                    this.dialog = true;
               },
               
               deleteRecord(record) {
                    showAlertWithConfirm(this.t("AlertWithConfirm")).then((res) => {
                         if (res.value) {
                              this.record.id = record.id;
                              this.RemoveTemplates();
                         }
                    });
               },
  },
  mounted() {
    this.GetTemplates();
  },
};
</script>

<style>
.preview {
  border: 1px solid #ddd;
  padding: 15px;
  background: #f9f9f9;
  min-height: 200px;
}


</style>
