<!-- PrintMarginDialog.vue -->
<template>
  <Dialog v-model:visible="visible" modal header="ضبط هوامش الطباعة" :style="{ width: '500px' }">
    <div class="grid">
      <div class="col-6">
        <label>الهامش العلوي (mm)</label>
        <InputNumber v-model="margins.top" suffix="mm" :min="0" />
      </div>
      <div class="col-6">
        <label>الهامش السفلي (mm)</label>
        <InputNumber v-model="margins.bottom" suffix="mm" :min="0" />
      </div>
      <div class="col-6 mt-3">
        <label>الهامش الأيسر (mm)</label>
        <InputNumber v-model="margins.left" suffix="mm" :min="0" />
      </div>
      <div class="col-6 mt-3">
        <label>الهامش الأيمن (mm)</label>
        <InputNumber v-model="margins.right" suffix="mm" :min="0" />
      </div>
    </div>

    <template #footer>
      <!-- <Button label="معاينة" icon="pi pi-eye" @click="preview" /> -->
      <Button label="حفظ" icon="pi pi-save" class="p-button-success" @click="saveMargins" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const visible = defineModel('visible')
const emit = defineEmits(['preview'])

const margins = ref({
  top: 20,
  bottom: 20,
  left: 15,
  right: 15,
})

onMounted(() => {
  const saved = localStorage.getItem('printMargins')
  if (saved) margins.value = JSON.parse(saved)
})

async function saveMargins() {
  await localStorage.setItem('printMargins', JSON.stringify(margins.value))
  visible.value = false
}

function preview() {
  saveMargins()
  emit('preview')
}
</script>

<style scoped>
label {
  font-weight: 600;
  display: block;
  margin-bottom: 5px;
}
</style>
