<script setup>
import { ref, computed } from 'vue';
import { usePatientPortalApp } from '@/composables/usePatientPortalApp';

const props = defineProps({ token: { type: String, required: true } });
const { device, permission, config, subscribed, results, offers, busy, preparing, workerReady, error, message, installEvent,
  inbox, unread, initialized, canSubscribe, active, statusText, enable, save, disable, testNotification, install, loadInbox, markRead, prepare } = usePatientPortalApp(props.token);
const panel = ref(''), instructions = ref(false);
const needsInstall = computed(() => device.value.ios && !device.value.installed);
const summary = computed(() => {
  if (active.value) return results.value || offers.value
    ? 'يمكن أن تصلك الإشعارات حتى بعد إغلاق البوابة، حسب إعدادات جهازك.'
    : 'أوقفت أنواع الإشعارات من تفضيلاتك. يمكنك تغييرها من الإعدادات.';
  if (device.value.embedded) return 'افتح رابط البوابة في متصفح الهاتف لإكمال التفعيل.';
  if (needsInstall.value) return 'على الآيفون، أضف البوابة للشاشة الرئيسية وافتحها من أيقونتها، ثم اسمح بالإشعارات مرة واحدة.';
  if (permission.value === 'denied') return 'السماح متوقف من الجهاز. غيّره من إعدادات الإشعارات ثم ارجع إلى البوابة.';
  if (!device.value.push) return 'هذا المتصفح لا يدعم إشعارات الجهاز. يمكنك متابعة تحديثاتك من الجرس.';
  if (initialized.value && !config.value?.public_key && !error.value) return 'خدمة إشعارات الجهاز غير متاحة حالياً. يمكنك متابعة تحديثاتك من الجرس.';
  return 'استلم تنبيهات جاهزية النتائج وأخبار المختبر بعد موافقتك. يمكنك تغيير الأنواع من الإعدادات، ولا تحتاج إبقاء البوابة مفتوحة.';
});
async function open(view) {
  panel.value = panel.value === view ? '' : view;
  if (panel.value === 'inbox') {
    try { await loadInbox(); await markRead(); } catch { error.value = 'تعذر تحميل الإشعارات. حاول مجدداً.'; }
  }
}
async function addToDevice() {
  if (installEvent.value && !device.value.ios) await install();
  else instructions.value = !instructions.value;
}
const date = value => new Intl.DateTimeFormat('ar-IQ', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
</script>

<template>
  <section class="portal-app" dir="rtl" aria-label="إشعارات بوابة المريض">
    <div class="portal-app-toolbar">
      <div class="portal-app-brand"><span></span>{{ config?.lab_name || 'بوابة المريض' }}</div>
      <div class="portal-app-tools">
        <button type="button" @click="open('inbox')" aria-label="الإشعارات" :aria-expanded="panel === 'inbox'" aria-controls="portal-app-inbox" class="portal-app-bell">
          <i class="pi pi-bell" aria-hidden="true"></i><span v-if="unread" class="portal-app-count">{{ unread > 9 ? '9+' : unread }}</span>
        </button>
        <button type="button" @click="open('settings')" :aria-expanded="panel === 'settings'" aria-controls="portal-app-settings"><i class="pi pi-sliders-h" aria-hidden="true"></i> إعدادات البوابة</button>
      </div>
    </div>

    <div class="portal-app-banner" :class="{ active }" :aria-busy="busy || preparing">
      <span class="portal-app-feature-icon"><i :class="active ? 'pi pi-check-circle' : 'pi pi-bell'" aria-hidden="true"></i></span>
      <div class="portal-app-banner-copy">
        <h2>{{ active ? statusText : needsInstall ? 'استلم إشعاراتك على الآيفون' : 'إشعارات النتائج وأخبار المختبر' }}</h2>
        <p>{{ summary }}</p>
        <span v-if="!active" class="portal-app-state">{{ statusText }}</span>
      </div>
      <button v-if="!active && needsInstall" type="button" class="portal-app-button secondary" @click="instructions = !instructions" :aria-expanded="instructions" aria-controls="portal-app-install-guide">خطوات التفعيل للآيفون</button>
      <button v-else-if="!active" type="button" class="portal-app-button primary" :disabled="busy || preparing || !canSubscribe" @click="enable">
        <i :class="busy || preparing ? 'pi pi-spin pi-spinner' : 'pi pi-bell'" aria-hidden="true"></i>{{ busy ? 'جاري التفعيل…' : preparing ? 'جاري التجهيز…' : 'السماح بالإشعارات' }}
      </button>
    </div>

    <div v-if="instructions && !device.installed" id="portal-app-install-guide" class="portal-app-guide">
      <template v-if="device.ios">
        <h3>مرة واحدة على الآيفون</h3>
        <ol>
          <li>افتح رابط بوابتك في <b>Safari</b>، ثم اضغط <b>مشاركة</b>.</li>
          <li>اختر <b>إضافة إلى الشاشة الرئيسية</b> ثم <b>إضافة</b>.</li>
          <li>افتح البوابة من <b>الأيقونة الجديدة</b> واضغط <b>السماح بالإشعارات</b>، ثم وافق على طلب الجهاز.</li>
        </ol>
        <p>يتطلب iOS 16.4 أو أحدث. السماح على الحاسبة لا يفعّل إشعارات الآيفون؛ لكل جهاز سماحه الخاص.</p>
      </template>
      <template v-else>
        <h3>أضف البوابة لشاشتك الرئيسية</h3>
        <p>من قائمة المتصفح اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية» إذا كان الخيار متاحاً.</p>
      </template>
    </div>

    <p v-if="error" class="portal-app-feedback error" role="alert">{{ error }}</p>
    <button v-if="error && initialized && !workerReady && !preparing" type="button" class="portal-app-button secondary retry" @click="prepare">إعادة تجهيز الإشعارات</button>
    <p v-if="message" class="portal-app-feedback success" role="status">{{ message }}</p>

    <section v-if="panel === 'settings'" id="portal-app-settings" class="portal-app-panel" aria-labelledby="portal-app-settings-title">
      <div class="portal-app-panel-heading"><h3 id="portal-app-settings-title">إعدادات الإشعارات</h3><button type="button" aria-label="إغلاق الإعدادات" @click="panel = ''"><i class="pi pi-times" aria-hidden="true"></i></button></div>
      <p class="portal-app-help">النتائج وأخبار المختبر محددة افتراضياً للاشتراك الجديد بعد السماح. يمكنك اختيار الأنواع أو إيقاف الإشعارات في أي وقت.</p>
      <div class="portal-app-preferences">
        <label><span><b>جاهزية النتائج</b><small>إشعار عام؛ تفاصيل النتائج تبقى داخل بوابتك.</small></span><input v-model="results" type="checkbox" role="switch" aria-label="إشعارات جاهزية النتائج" :disabled="busy" /></label>
        <label><span><b>العروض والمكافآت</b><small>أخبار مختبرك وبرنامج الولاء، حسب اختيارك.</small></span><input v-model="offers" type="checkbox" role="switch" aria-label="إشعارات العروض والمكافآت" :disabled="busy" /></label>
      </div>
      <template v-if="active">
        <button type="button" class="portal-app-button primary" :disabled="busy" @click="save">{{ busy ? 'جاري الحفظ…' : 'حفظ التفضيلات' }}</button>
        <div class="portal-app-inline-actions">
          <button type="button" :disabled="busy" @click="testNotification">إرسال إشعار تجريبي</button>
          <button type="button" :disabled="busy" @click="disable">إيقاف على هذا الجهاز</button>
        </div>
        <p class="portal-app-help">بالآيفون، تأكد من السماح لإشعارات البوابة في إعدادات الجهاز ومن إعدادات «التركيز». قد يؤخر الجهاز عرض التنبيهات.</p>
      </template>
      <p v-else class="portal-app-help">تُحفظ هذه الاختيارات عند الضغط على «السماح بالإشعارات» وإكمال موافقة الجهاز.</p>
      <button v-if="!device.installed" type="button" class="portal-app-button secondary" :disabled="busy || !initialized" @click="addToDevice">
        <i class="pi pi-mobile" aria-hidden="true"></i>{{ device.ios ? 'طريقة الإضافة للآيفون' : installEvent ? 'إضافة البوابة للجهاز' : 'طريقة إضافة البوابة' }}
      </button>
    </section>

    <section v-if="panel === 'inbox'" id="portal-app-inbox" class="portal-app-panel" aria-labelledby="portal-app-inbox-title">
      <div class="portal-app-panel-heading"><h3 id="portal-app-inbox-title">إشعاراتك</h3><button type="button" aria-label="إغلاق الإشعارات" @click="panel = ''"><i class="pi pi-times" aria-hidden="true"></i></button></div>
      <div v-if="!inbox.length" class="portal-app-empty"><i class="pi pi-check-circle" aria-hidden="true"></i><p>راح تظهر هنا تنبيهاتك الجديدة عند توفرها.</p></div>
      <article v-for="item in inbox" :key="item.id" class="portal-app-notice"><span class="portal-app-feature-icon"><i :class="item.kind === 'result' ? 'pi pi-file' : 'pi pi-bell'" aria-hidden="true"></i></span><div><h3>{{ item.title }}</h3><p>{{ item.body }}</p><time :datetime="item.created_at">{{ date(item.created_at) }}</time></div></article>
    </section>
  </section>
</template>

<style scoped>
.portal-app{color:#173c37;font-family:inherit}
.portal-app-toolbar,.portal-app-tools,.portal-app-brand{display:flex;align-items:center;gap:8px}
.portal-app-toolbar{justify-content:space-between;gap:12px;padding:0 2px 10px}
.portal-app-brand{font-size:11px;color:#5d746c;min-width:0}
.portal-app-brand>span{width:6px;height:6px;border-radius:50%;background:#0f766e;flex:none}
.portal-app-tools{flex:none}
.portal-app-tools button,.portal-app-panel-heading button{display:flex;align-items:center;gap:7px;border:1px solid #dce7e2;background:white;color:#335951;border-radius:10px;padding:9px 11px;font-size:11px;cursor:pointer}
.portal-app-bell{position:relative}
.portal-app-count{position:absolute;top:-5px;left:-5px;background:#0f766e;color:white;font-size:10px;border-radius:20px;min-width:18px;padding:1px 4px;border:2px solid white}
.portal-app-banner{display:flex;align-items:center;gap:13px;padding:18px;border:1px solid #dcebe5;border-radius:16px;background:linear-gradient(110deg,#fff,#f0f9f5)}
.portal-app-banner.active{background:#f1faf5;border-color:#d4eade}
.portal-app-feature-icon{display:grid;place-items:center;background:white;color:#0f766e;width:40px;height:40px;border:1px solid #e5efea;border-radius:12px;flex:none;font-size:19px}
.portal-app-banner-copy{flex:1;min-width:0}
.portal-app h2{font-size:14px;font-weight:700;line-height:1.7;margin:0 0 3px}
.portal-app p{font-size:12px;line-height:1.9;margin:0;color:#617b70}
.portal-app-state{display:block;margin-top:5px;font-size:10px;color:#789084}
.portal-app-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;padding:12px 16px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid transparent;flex:none}
.portal-app-button.primary{background:#0f766e;color:white}.portal-app-button.primary:hover{background:#0b625a}
.portal-app-button.secondary{background:#f7fcf9;border-color:#cce4d8;color:#196750}
.portal-app-button:disabled,.portal-app-inline-actions button:disabled{opacity:.55;cursor:not-allowed}
.portal-app-guide,.portal-app-panel{background:#fff;border:1px solid #dfeae5;border-radius:14px;padding:20px;margin-top:10px}
.portal-app-guide h3,.portal-app-panel h3{font-size:14px;font-weight:700;margin:0 0 8px}
.portal-app-guide ol{list-style:decimal;padding-right:20px;font-size:12px;line-height:2;margin:6px 0 12px}
.portal-app-guide li+li{margin-top:6px}
.portal-app-guide p,.portal-app-help{font-size:11px!important}
.portal-app-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
.portal-app-panel-heading h3{margin:0}
.portal-app-preferences{margin:6px 0 14px}
.portal-app-preferences label{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:14px 0;border-bottom:1px solid #edf2ef;cursor:pointer}
.portal-app-preferences b{display:block;font-size:12px;font-weight:600}
.portal-app-preferences small{display:block;font-size:10px;line-height:1.8;color:#718a7e;margin-top:4px}
.portal-app-preferences input{appearance:none;flex:none;width:36px;height:21px;border-radius:20px;background:#d6e0dc;position:relative;cursor:pointer}
.portal-app-preferences input::after{content:'';position:absolute;top:3px;left:3px;width:15px;height:15px;background:white;border-radius:50%;box-shadow:0 1px 3px #0002}
.portal-app-preferences input:checked{background:#0f766e}.portal-app-preferences input:checked::after{transform:translateX(15px)}
.portal-app-inline-actions{display:flex;justify-content:space-between;gap:14px;margin:10px 0}
.portal-app-inline-actions button{font-size:11px;background:transparent;border:0;color:#416e5b;padding:8px 0;cursor:pointer;text-decoration:underline;text-underline-offset:3px}
.portal-app-panel>.portal-app-button.secondary{margin-top:14px}
.portal-app-feedback{border-radius:10px;padding:12px;margin:10px 0 0!important}
.portal-app-feedback.error{background:#fff1f2;color:#9f2439}.portal-app-feedback.success{background:#ecfdf5;color:#176745}
.portal-app-button.retry{margin-top:8px}
.portal-app-empty{text-align:center;padding:20px 0}.portal-app-empty>i{font-size:28px;color:#91b8a3}
.portal-app-notice{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #edf1ef}
.portal-app-notice time{font-size:10px;color:#869c8e;display:block;margin-top:5px}
button:focus-visible,input:focus-visible{outline:3px solid #39b8a5;outline-offset:3px}
@media(max-width:600px){.portal-app-banner{flex-wrap:wrap;padding:15px;gap:11px}.portal-app-banner-copy{flex-basis:calc(100% - 55px)}.portal-app-banner>.portal-app-button{width:100%}.portal-app-brand{max-width:40%;overflow:hidden;max-height:36px;line-height:1.6}.portal-app-tools button{padding:9px;font-size:10px}.portal-app-panel,.portal-app-guide{padding:16px}}
</style>
