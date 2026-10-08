<template>
  <div class="space-y-3" data-package-details>
    <section v-for="(group, index) in contents.groups" :key="group.id ?? group.test_group_id_fk ?? index" class="rounded-xl border border-blue-200 bg-white overflow-hidden" data-package-group>
      <div class="bg-blue-50 px-3 py-2 text-sm text-blue-900">
        <span class="text-xs text-blue-600">{{ t('group') }}</span>
        <AnalysisName :name="group.group_name || group.name" :shortcut="group.shortcut" />
      </div>
      <div class="p-2 space-y-1.5">
        <AnalysisName v-for="(test, testIndex) in group.tests" :key="test.id ?? test.test_id_fk ?? testIndex" :name="test.name" :shortcut="test.shortcut" class="rounded-lg bg-slate-50 p-2 text-sm text-slate-700" />
        <AnalysisName v-for="(culture, cultureIndex) in group.cultures" :key="'culture-' + (culture.id ?? culture.culture_id_fk ?? cultureIndex)" :name="culture.name" :shortcut="culture.shortcut" class="rounded-lg bg-purple-50 p-2 text-sm text-purple-700" />
        <p v-if="!group.tests.length && !group.cultures.length" class="p-2 text-xs text-slate-500">{{ lang === 'en' ? 'No analyses in this group.' : 'لا توجد تحاليل مضافة داخل هذا الكروب.' }}</p>
      </div>
    </section>
    <div v-if="contents.tests.length || contents.cultures.length" class="space-y-1.5" data-package-direct-tests>
      <p v-if="contents.groups.length" class="text-xs font-semibold text-slate-600">{{ lang === 'en' ? 'Individual analyses' : 'التحاليل المفردة' }}</p>
      <div v-for="(test, index) in contents.tests" :key="test.id ?? test.test_id_fk ?? index" class="flex items-start justify-between gap-2 p-2 bg-white rounded-lg text-sm">
        <AnalysisName :name="test.name" :shortcut="test.shortcut" class="text-slate-700" />
        <span class="shrink-0 text-slate-400 text-xs">{{ test.for_customer_price ?? test.price }}</span>
      </div>
      <div v-for="(culture, index) in contents.cultures" :key="'culture-' + (culture.id ?? culture.culture_id_fk ?? index)" class="flex items-start justify-between gap-2 p-2 bg-purple-50 rounded-lg text-sm">
        <AnalysisName :name="culture.name" :shortcut="culture.shortcut" class="text-purple-700" />
        <span class="shrink-0 text-purple-400 text-xs">{{ culture.for_customer_price ?? culture.price }}</span>
      </div>
    </div>
    <p v-if="!contents.groups.length && !contents.tests.length && !contents.cultures.length" class="text-sm text-slate-500">{{ lang === 'en' ? 'No analyses or groups in this package.' : 'لا توجد تحاليل أو كروبات في هذه الباقة.' }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import AnalysisName from './AnalysisName.vue';
import { invoicePackageContents } from '@/utils/invoicePackageContents';
const props = defineProps({ item: { type: Object, required: true } });
const contents = computed(() => invoicePackageContents(props.item));
</script>
