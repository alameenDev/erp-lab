<script setup>
import { computed, reactive, ref, watch, toRaw } from 'vue';
import { ageFromBirthDate, birthDateFromAge, birthDateFromParts, calendarDate, isEstimatedBirthDate, todayInBaghdad } from '@/utils/patientAge';
const props = defineProps({ modelValue: { type: Object, required: true }, units: { type: Array, default: () => [] }, locale: { type: String, default: 'ar' } });
const emit = defineEmits(['update:modelValue']);
const text = (ar, en) => props.locale === 'en' ? en : ar;
const parts = reactive({ day: '', month: '', year: '' });
const error = ref('');
const editingDate = ref(false);
const estimated = computed(() => isEstimatedBirthDate(props.modelValue.dob_is_estimated) || (!props.modelValue.dob && !!estimatedDate()));
const unit = computed(() => props.units.find(u => String(u.value) === String(props.modelValue.age_unit_id_fk)));
const yearUnit = () => props.units.find(u => u.label === 'Years');
const estimatedDate = () => birthDateFromAge(props.modelValue.age, unit.value?.label);
let emittedRecord;
const patch = values => { emittedRecord = { ...props.modelValue, ...values }; emit('update:modelValue', emittedRecord); };
const fillParts = dob => {
  const value = calendarDate(dob);
  Object.assign(parts, value ? { day: String(value.getUTCDate()), month: String(value.getUTCMonth() + 1), year: String(value.getUTCFullYear()) } : { day: '', month: '', year: '' });
};
watch(() => [props.modelValue, props.modelValue.id, props.modelValue.dob, props.modelValue.age, props.modelValue.age_unit_id_fk, props.units], () => {
  if (toRaw(props.modelValue) !== emittedRecord) { editingDate.value = false; error.value = ''; }
  if (!editingDate.value) {
    fillParts(props.modelValue.dob || estimatedDate());
    if (props.modelValue.dob && !isEstimatedBirthDate(props.modelValue.dob_is_estimated)) {
      const result = ageFromBirthDate(props.modelValue.dob);
      const selected = props.units.find(u => u.label === result?.unit);
      if (selected && (Number(props.modelValue.age) !== result.age || String(props.modelValue.age_unit_id_fk) !== String(selected.value))) {
        patch({ age: result.age, age_unit_id_fk: selected.value });
      }
    }
  }
}, { immediate: true });
watch(() => props.modelValue.id, () => { editingDate.value = false; error.value = ''; fillParts(props.modelValue.dob || estimatedDate()); });
function enterAge(value, unitId = props.modelValue.age_unit_id_fk || yearUnit()?.value) {
  editingDate.value = false;
  const selected = props.units.find(u => String(u.value) === String(unitId));
  const dob = birthDateFromAge(value, selected?.label);
  fillParts(dob);
  error.value = value !== '' && !dob ? text('أدخل عمراً صحيحاً ووحدة العمر.', 'Enter a valid age and age unit.') : '';
  patch({ age: value === '' ? '' : Number(value), age_unit_id_fk: unitId ?? '', dob: dob || '', dob_is_estimated: !!dob });
}
function enterDate() {
  editingDate.value = true;
  const dob = birthDateFromParts(parts);
  const result = ageFromBirthDate(dob);
  const selected = props.units.find(u => u.label === result?.unit);
  error.value = '';
  if (result && selected) patch({ dob, dob_is_estimated: false, age: result.age, age_unit_id_fk: selected.value });
  else {
    patch({ dob: '', dob_is_estimated: false, age: '' });
    if (parts.day && parts.month && String(parts.year).length >= 4) error.value = text('تاريخ الميلاد غير صحيح أو يقع في المستقبل.', 'Birth date is invalid or in the future.');
  }
}
function validate() {
  if (editingDate.value && (!birthDateFromParts(parts) || !ageFromBirthDate(birthDateFromParts(parts)))) {
    error.value = text('أكمل يوم وشهر وسنة الميلاد بتاريخ صحيح.', 'Complete a valid day, month and year.'); return false;
  }
  if (!birthDateFromAge(props.modelValue.age, unit.value?.label)) {
    error.value = text('أدخل العمر أو تاريخ الميلاد.', 'Enter an age or birth date.'); return false;
  }
  error.value = ''; return true;
}
defineExpose({ validate });
</script>

<template>
  <fieldset class="patient-age-card" data-patient-age>
    <legend>{{ text('العمر وتاريخ الميلاد', 'Age and birth date') }}</legend>
    <p class="patient-age-help">{{ text('أدخل العمر أو تاريخ الميلاد، ويتحدّث الآخر تلقائياً.', 'Enter age or birth date; the other updates automatically.') }}</p>
    <div class="patient-age-layout">
      <label>{{ text('العمر', 'Age') }}
        <input data-age-value type="number" min="0" step="1" :value="modelValue.age" @input="enterAge($event.target.value)" :aria-label="text('العمر', 'Age')" :aria-invalid="!!error" />
      </label>
      <label>{{ text('وحدة العمر', 'Age unit') }}
        <select data-age-unit :value="modelValue.age_unit_id_fk" @change="enterAge(modelValue.age, $event.target.value)" :aria-label="text('وحدة العمر', 'Age unit')">
          <option value="">{{ text('اختر', 'Select') }}</option>
          <option v-for="u in units" :key="u.value" :value="u.value">{{ locale === 'en' ? u.label : ({ Years: 'سنة', Months: 'شهر', Days: 'يوم' }[u.label] || u.label) }}</option>
        </select>
      </label>
      <div class="patient-birth-fields">
        <label>{{ text('اليوم', 'Day') }}<input data-birth-day type="number" min="1" max="31" step="1" v-model="parts.day" @input="enterDate" :aria-label="text('يوم الميلاد', 'Birth day')" placeholder="DD" /></label>
        <label>{{ text('الشهر', 'Month') }}<input data-birth-month type="number" min="1" max="12" step="1" v-model="parts.month" @input="enterDate" :aria-label="text('شهر الميلاد', 'Birth month')" placeholder="MM" /></label>
        <label>{{ text('السنة', 'Year') }}<input data-birth-year type="number" min="1" :max="Number(todayInBaghdad().slice(0, 4))" step="1" v-model="parts.year" @input="enterDate" :aria-label="text('سنة الميلاد', 'Birth year')" placeholder="YYYY" /></label>
      </div>
    </div>
    <p v-if="estimated" class="patient-age-estimated" data-birth-estimated>{{ text('تاريخ الميلاد تقريبي ومحسوب من العمر؛ اكتب التاريخ الفعلي إذا كان معروفاً.', 'Birth date is estimated from age. Enter the actual date if known.') }}</p>
    <p v-if="error" class="patient-age-error" role="alert">{{ error }}</p>
  </fieldset>
</template>

<style scoped>
.patient-age-card{min-width:0;border:1px solid #cbd5e1;border-radius:14px;background:#f8fafc;padding:16px;margin:0}
legend{font-weight:700;color:#334155;padding:0 6px;font-size:14px}.patient-age-help{font-size:12px;color:#64748b;margin:0 0 12px}.patient-age-layout{display:grid;grid-template-columns:minmax(80px,1fr) minmax(90px,1fr) minmax(240px,2.4fr);gap:12px}.patient-birth-fields{display:grid;grid-template-columns:1fr 1fr 1.3fr;gap:8px}label{display:flex;flex-direction:column;gap:6px;color:#475569;font-size:13px;min-width:0}input,select{width:100%;min-width:0;min-height:44px;background:white;border:1px solid #cbd5e1;border-radius:9px;padding:9px 10px;color:#0f172a;outline:none;font-size:15px}input{text-align:center;direction:ltr}input:focus,select:focus{border-color:#0d9488;box-shadow:0 0 0 3px #0d94881a}.patient-age-estimated{font-size:12px;color:#92400e;margin:10px 0 0}.patient-age-error{font-size:13px;color:#b91c1c;margin:10px 0 0}@media(max-width:640px){.patient-age-layout{grid-template-columns:1fr 1fr}.patient-birth-fields{grid-column:1/-1}}
</style>
