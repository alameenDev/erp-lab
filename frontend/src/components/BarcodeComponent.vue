<template>
     <div v-if="value">
          <svg ref="barcode"></svg>
     </div>
</template>

<script>
     import JsBarcode from "jsbarcode";

     export default {
          props: {
               width: {
                    default: 1,
               },
               height: {
                    default: 20,
               },
               value: {
                    type: String,
                    default: "",
               },
               format: {
                    type: String,
                    default: "CODE128",
               },
               displayValue: {
                    type: Boolean,
                    default: false,
               },
          },
          mounted() {
               if (this.value) {
                    this.generateBarcode();
               }
          },
          watch: {
               value(newVal) {
                    if (newVal) {
                         this.$nextTick(() => {
                              this.generateBarcode();
                         });
                    }
               },
          },
          methods: {
               generateBarcode() {
                    if (this.$refs.barcode && this.value) {
                         JsBarcode(this.$refs.barcode, this.value, {
                              format: this.format,
                              displayValue: this.displayValue,
                              lineColor: "#000",
                              width: this.width,
                              height: this.height,
                         });
                    }
               },
          },
     };
</script>
<!-- Styles moved to Tailwind - svg uses p-0 class if needed -->
