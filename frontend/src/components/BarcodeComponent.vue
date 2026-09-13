<template>
     <div>
          <svg ref="barcode"></svg>
          <!-- Placeholder for the barcode -->
     </div>
</template>

<script>
     import JsBarcode from "jsbarcode";

     export default {
          props: {
               width: {
                    default: 1, // Default barcode format
               },
               height: {
                    default: 20, // Default barcode format
               },
               value: {
                    type: String,
                    required: true, // The barcode value to generate
               },
               format: {
                    type: String,
                    default: "CODE128", // Default barcode format
               },
               displayValue: {
                    type: Boolean,
                    default: false, // Default barcode format
               },
          },
          mounted() {
               this.generateBarcode();
          },
          methods: {
               generateBarcode() {
                    JsBarcode(this.$refs.barcode, this.value, {
                         format: this.format,
                         displayValue: this.displayValue,
                         lineColor: "#000",
                         width: this.width,
                         height: this.height,
                    });
               },
          },
     };
</script>
<style>
     svg {
          padding: 0;
     }
</style>
