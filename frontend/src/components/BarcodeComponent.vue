<template>
     <div v-if="value">
          <svg ref="barcode"></svg>
          <span v-if="invalid" role="status" class="text-xs text-red-700">باركود غير صالح</span>
     </div>
</template>

<script>
     import JsBarcode from "jsbarcode";

     export default {
          data: () => ({ invalid: false }),
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
          computed: {
               barcodeOptions() {
                    return [this.value, this.format, this.width, this.height, this.displayValue];
               },
          },
          mounted() { this.generateBarcode(); },
          watch: {
               barcodeOptions() {
                    this.$nextTick(() => this.generateBarcode());
               },
          },
          methods: {
               generateBarcode() {
                    this.invalid = false;
                    if (!this.$refs.barcode || !this.value) return;
                    // Clear the previous symbol before trying a new value/format.
                    this.$refs.barcode.replaceChildren();
                    try {
                         // JsBarcode uppercases CODE39 internally; reject an
                         // incompatible value instead of changing the sample ID.
                         if (this.format === 'CODE39' && !/^[0-9A-Z .\-$/+%]+$/.test(this.value)) throw new Error('Invalid CODE39 value');
                         JsBarcode(this.$refs.barcode, this.value, {
                              format: this.format,
                              displayValue: this.displayValue,
                              lineColor: "#000",
                              width: this.width,
                              height: this.height,
                         });
                    } catch {
                         this.$refs.barcode.replaceChildren();
                         this.invalid = true;
                    }
               },
          },
     };
</script>
<!-- Styles moved to Tailwind - svg uses p-0 class if needed -->
