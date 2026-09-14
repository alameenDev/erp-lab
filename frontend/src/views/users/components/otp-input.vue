<script setup>
import { ref, watch, onMounted } from "vue";

const props = defineProps({
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
});

const emit = defineEmits(["update:modelValue"]);

const digits = ref(Array(props.length).fill(""));
const otpInputs = ref([]);

const updateDigits = (value) => {
  const valueArray = value.split("").slice(0, props.length);
  digits.value = valueArray;
  while (digits.value.length < props.length) {
    digits.value.push("");
  }
};

const onInput = (index, event) => {
  let value = event.target.value;
  if (props.integerOnly) {
    value = value.replace(/\D/g, "");
  }

  if (value.length > 1) {
    value = value.charAt(value.length - 1);
  }

  digits.value[index] = value;
  emit("update:modelValue", digits.value.join(""));

  if (value && index < props.length - 1) {
    otpInputs.value[index + 1]?.focus();
  }
};

const onKeydown = (index, event) => {
  if (event.key === "Backspace" && !digits.value[index]) {
    if (index > 0) {
      otpInputs.value[index - 1]?.focus();
    }
  }
};

const onPaste = (event) => {
  event.preventDefault();
  const pastedData = event.clipboardData.getData("text");
  let cleanData = props.integerOnly ? pastedData.replace(/\D/g, "") : pastedData;
  cleanData = cleanData.slice(0, props.length);

  for (let i = 0; i < props.length; i++) {
    digits.value[i] = cleanData[i] || "";
  }
  emit("update:modelValue", digits.value.join(""));

  // Focus on the next empty input or the last one
  const nextEmptyIndex = digits.value.findIndex(d => !d);
  if (nextEmptyIndex !== -1) {
    otpInputs.value[nextEmptyIndex]?.focus();
  } else {
    otpInputs.value[props.length - 1]?.focus();
  }
};

watch(() => props.modelValue, (newVal) => {
  updateDigits(newVal);
});

onMounted(() => {
  updateDigits(props.modelValue);
});
</script>

<template>
  <div class="flex gap-2 justify-center" dir="ltr">
    <input
      v-for="(digit, index) in digits"
      :key="index"
      :ref="el => otpInputs[index] = el"
      type="text"
      maxlength="1"
      :value="digit"
      @input="onInput(index, $event)"
      @keydown="onKeydown(index, $event)"
      @paste="onPaste"
      class="w-12 h-14 text-center text-xl font-semibold border-2 border-gray-300 rounded-lg focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none transition-all duration-200"
    />
  </div>
</template>
