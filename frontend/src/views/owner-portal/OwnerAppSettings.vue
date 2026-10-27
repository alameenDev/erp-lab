<script setup>
defineProps({ app:{ type:Object, required:true } });
</script>
<template>
  <div class="owner-app-settings">
    <section class="owner-install-card">
      <span class="owner-install-icon"><i class="pi pi-mobile" aria-hidden="true"></i></span>
      <div><span class="owner-eyebrow">مختبراتك أقرب إليك</span><h2>بوابة المالك، على جهازك</h2><p>افتح البوابة من أيقونة مستقلة، وتابع حركة الفواتير وملخص يومك.</p></div>
      <span v-if="app.device.installed" class="owner-badge paid">تعمل الآن كتطبيق</span>
      <button v-else-if="app.installAvailable" class="owner-primary" @click="app.install">تثبيت التطبيق</button>
      <p v-else-if="app.device.ios" class="owner-install-steps">على iPhone أو iPad: افتح البوابة في Safari، اضغط <b>مشاركة</b> ثم <b>إضافة إلى الشاشة الرئيسية</b>، وافتحها من الأيقونة.</p>
      <p v-else class="owner-install-steps">من قائمة المتصفح اختر <b>تثبيت التطبيق</b> أو <b>إضافة إلى الشاشة الرئيسية</b>. على الكمبيوتر قد تظهر أيقونة التثبيت بجانب شريط العنوان.</p>
    </section>
    <section class="owner-card owner-device-settings">
      <div class="owner-card-heading"><div><h2>إشعارات هذا الجهاز</h2><p>اختَر ما يصلك، وسجل الإشعارات يبقى متاحاً داخل البوابة.</p></div><span :class="['owner-badge', app.subscribed ? 'paid' : 'neutral']">{{ app.subscribed ? 'مفعّلة' : 'غير مفعّلة' }}</span></div>
      <p v-if="app.preparing" role="status">جاري تجهيز خدمة الإشعارات...</p>
      <p v-else-if="app.config && !app.config.enabled" class="owner-alert">خدمة الإرسال تحتاج إلى تفعيل من مدير النظام. إشعاراتك محفوظة داخل البوابة.</p>
      <p v-if="app.device.embedded" class="owner-alert">افتح الرابط في Safari أو Chrome لتثبيت التطبيق وتفعيل الإشعارات.</p>
      <p v-if="app.device.ios && !app.device.installed" class="owner-alert">لتفعيل الإشعارات على iPhone وiPad، أضف البوابة للشاشة الرئيسية ثم افتحها منها. يتطلب iOS / iPadOS 16.4 أو أحدث.</p>
      <p v-else-if="!app.device.push" class="owner-alert">الإشعارات غير متاحة في هذا المتصفح. استخدم متصفحاً داعماً واتصالاً آمناً HTTPS.</p>
      <p v-if="app.permission === 'denied'" class="owner-alert">الإشعارات محظورة. اسمح بها من إعدادات الموقع في المتصفح، ثم اضغط إعادة التجهيز.</p>
      <label class="owner-notice-toggle"><span><strong>حذف الفواتير وتعديل مبالغها</strong><small>رقم الفاتورة، المبلغ قبل وبعد، واسم منفّذ العملية.</small></span><input type="checkbox" v-model="app.invoice_enabled" :disabled="app.busy" aria-label="إشعارات حذف وتعديل الفواتير"></label>
      <label class="owner-notice-toggle"><span><strong>ملخص نهاية اليوم</strong><small>الساعة ١٢:٠٥ بعد منتصف الليل بتوقيت بغداد، عن اليوم السابق. ملخص مستقل لكل مختبر.</small></span><input type="checkbox" v-model="app.daily_enabled" :disabled="app.busy" aria-label="إشعارات ملخص نهاية اليوم"></label>
      <p class="owner-muted">تظهر المبالغ واسم الموظف في إشعار الجهاز؛ يمكنك إخفاء معاينتها من إعدادات إشعارات جهازك. بعد تسجيل الخروج تتوقف إشعارات هذا الجهاز.</p>
      <div class="owner-notice-actions">
        <button class="owner-primary" @click="app.enable" :disabled="!app.ready || app.busy || app.permission === 'denied' || (app.device.ios && !app.device.installed)">{{ app.busy ? 'جاري الحفظ...' : app.subscribed ? 'حفظ الاختيارات' : 'تفعيل الإشعارات' }}</button>
        <button v-if="app.subscribed" class="owner-secondary" @click="app.test" :disabled="app.busy">إرسال إشعار تجريبي</button>
        <button v-if="app.subscribed" class="owner-text-button" @click="app.disable" :disabled="app.busy">إيقاف على هذا الجهاز</button>
        <button class="owner-text-button" @click="app.prepare" :disabled="app.preparing || app.busy">إعادة التجهيز</button>
      </div>
      <p v-if="app.error" class="owner-error" role="alert">{{ app.error }}</p><p v-if="app.message" class="owner-success" role="status">{{ app.message }}</p>
    </section>
  </div>
</template>
