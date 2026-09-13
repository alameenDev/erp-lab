<template>
     <div>
          <input
               v-for="(digit, index) in digits"
               :key="index"
               type="text"
               maxlength="1"
               :value="digit"
               @input="onInput(index, $event)"
               @keydown="onKeydown(index, $event)"
               class="otp-input"
               ref="otpInput" />
     </div>
</template>

<script>
     export default {
          props: {
               modelValue: {
                    type: String,
                    default: "",
               },
               length: {
                    type: Number,
                    default: 6,
               },
               integerOnly: {
                    type: Boolean,
                    default: false,
               },
          },
          data() {
               return {
                    digits: Array(this.length).fill(""),
               };
          },
          watch: {
               modelValue(newVal) {
                    this.updateDigits(newVal);
               },
          },
          methods: {
               onInput(index, event) {
                    let value = event.target.value;
                    if (this.integerOnly) {
                         value = value.replace(/\D/g, "");
                    }

                    if (value.length > 1) {
                         value = value.charAt(value.length - 1);
                    }

                    this.digits.splice(index, 1, value);
                    this.$emit("update:modelValue", this.digits.join(""));

                    if (value && index < this.length - 1) {
                         this.$nextTick(() => {
                              this.$refs.otpInput[index + 1].focus();
                         });
                    }
               },
               onKeydown(index, event) {
                    if (event.key === "Backspace" && !this.digits[index]) {
                         if (index > 0) {
                              this.$nextTick(() => {
                                   this.$refs.otpInput[index - 1].focus();
                              });
                         }
                    }
               },
               updateDigits(value) {
                    this.digits = value.split("").slice(0, this.length);
                    while (this.digits.length < this.length) {
                         this.digits.push("");
                    }
               },
          },
          mounted() {
               this.updateDigits(this.modelValue);
          },
     };
</script>

<style>
     .otp-input {
          width: 4em;
          margin: 5px 0.2em;
          text-align: center;
          padding: 18px;
          border: 1px solid rgba(0, 0, 0, 0.2);
          border-radius: 5px;
     }
</style>
