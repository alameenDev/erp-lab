<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { safeBannerImage, safeBannerLink } from '@/utils/labBanners';

const props = defineProps({ banner: { type: Object, required: true }, preview: Boolean });
const titleId = `lab-banner-title-${getCurrentInstance().uid}`;
const failed = ref(false);
const image = computed(() => safeBannerImage(props.banner.image_url, props.preview));
const mobileImage = computed(() => safeBannerImage(props.banner.mobile_image_url, props.preview));
const link = computed(() => safeBannerLink(props.banner.button_url));
const text = (ar, en) => localStorage.getItem('locale') === 'en' ? en : ar;
watch(() => [image.value, mobileImage.value], () => { failed.value = false; });
</script>

<template>
  <article class="lab-banner-card" :aria-labelledby="titleId" data-testid="lab-banner-card">
    <picture v-if="image && !failed" class="lab-banner-picture">
      <source v-if="mobileImage" media="(max-width: 640px)" :srcset="mobileImage" />
      <img :src="image" :alt="banner.title" decoding="async" @error="failed = true" data-testid="banner-image" />
    </picture>
    <div class="lab-banner-content">
      <div class="lab-banner-copy">
        <span class="lab-banner-label">{{ text('إعلان', 'Announcement') }}</span>
        <h2 :id="titleId">{{ banner.title }}</h2>
        <p v-if="banner.description">{{ banner.description }}</p>
      </div>
      <a v-if="link && banner.button_label" :href="link" target="_blank" rel="noopener noreferrer" class="lab-banner-cta" data-testid="banner-link">
        {{ banner.button_label }} <i class="pi pi-external-link" aria-hidden="true"></i>
        <span class="banner-sr-only">{{ text('يفتح في تبويب جديد', 'Opens in a new tab') }}</span>
      </a>
    </div>
  </article>
</template>

<style scoped>
.lab-banner-card{width:100%;min-width:0;overflow:hidden;border:1px solid #dce6ef;border-radius:1.1rem;background:#fff;box-shadow:0 4px 16px #0f172a08;text-align:start;color:#17253b}
.lab-banner-picture{display:block;width:100%;background:#f1f6fa}.lab-banner-picture img{display:block;width:100%;height:auto;max-height:420px;object-fit:contain}
.lab-banner-content{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;padding:clamp(1rem,2vw,1.6rem)}
.lab-banner-copy{flex:1 1 20rem;min-width:0}.lab-banner-label{display:inline-block;font-size:.72rem;font-weight:700;color:#036983;background:#e5f7fc;border-radius:99px;padding:.2rem .7rem;margin-bottom:.45rem}
.lab-banner-copy h2{margin:0;font-size:clamp(1.1rem,1.7vw,1.5rem);font-weight:750;line-height:1.6;overflow-wrap:anywhere}
.lab-banner-copy p{margin:.4rem 0 0;color:#53667e;font-size:.95rem;line-height:1.8;white-space:pre-wrap;overflow-wrap:anywhere}
.lab-banner-cta{display:inline-flex;align-items:center;justify-content:center;gap:.6rem;flex:0 1 auto;max-width:100%;padding:.8rem 1.35rem;border-radius:.7rem;background:#087f9c;color:#fff;font-weight:700;text-decoration:none;line-height:1.6;overflow-wrap:anywhere;white-space:normal}
.lab-banner-cta:hover{background:#06647c}.lab-banner-cta:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}
.banner-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:640px){.lab-banner-picture img{max-height:320px}.lab-banner-content{display:grid;grid-template-columns:minmax(0,1fr);gap:.85rem}.lab-banner-cta{width:100%;box-sizing:border-box}.lab-banner-copy h2{font-size:1.1rem}}
@media print{.lab-banner-card{display:none!important}}
</style>
