<template>
  <div>
    <h2>عرض القالب</h2>

    <div ref="reportContent" v-html="renderedTemplate"></div>

    <button @click="printTemplate">طباعة</button>
    <button @click="generatePDF">تصدير PDF</button>
  </div>
</template>

<script>
import axios from "axios";
import html2canvas from "html2canvas";
import jsPDF from "jspdf";

export default {
  data() {
    return {
      renderedTemplate: "",
      patient: {
        name: "أحمد علي",
        age: 30,
        gender: "ذكر",
        test: {
          type: "تحليل السكر",
          result: "120 mg/dL",
        },
      },
    };
  },
  async mounted() {
    try {
      const response = await axios.get("/api/templates/1"); // Fetch the template from the database
      let template = response.data.content;

      // Replace placeholders with patient data
      template = template.replace("{{name}}", this.patient.name);
      template = template.replace("{{age}}", this.patient.age);
      template = template.replace("{{gender}}", this.patient.gender);
      template = template.replace("{{test_type}}", this.patient.test.type);
      template = template.replace("{{test_result}}", this.patient.test.result);

      this.renderedTemplate = template;
    } catch (error) {
      console.error("خطأ في تحميل القالب:", error);
    }
  },
  methods: {
    printTemplate() {
      window.print();
    },
    async generatePDF() {
      const content = this.$refs.reportContent;
      const canvas = await html2canvas(content);
      const imgData = canvas.toDataURL("image/png");

      const pdf = new jsPDF();
      pdf.addImage(imgData, "PNG", 10, 10, 190, 0);
      pdf.save("تقرير-التحليل.pdf");
    },
  },
};
</script>
