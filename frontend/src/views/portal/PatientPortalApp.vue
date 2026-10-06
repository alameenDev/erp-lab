<script setup>
import { ref, watch, nextTick } from 'vue';
import { usePatientPortalApp } from '@/composables/usePatientPortalApp';

const props = defineProps({ token: { type: String, required: true } });
const { device, permission, config, subscribed, results, offers, busy, error, message, welcome, installEvent,
  inbox, unread, initialized, canSubscribe, statusText, dismiss, enable, save, disable, testNotification, install, loadInbox, markRead } = usePatientPortalApp(props.token);
const dialog = ref(null), screen = ref('welcome'), instructions = ref(false);

async function open(view = 'settings') {
  screen.value = view; instructions.value = false; error.value = ''; message.value = '';
  await nextTick();
  if (!dialog.value.open) dialog.value.showModal();
  if (view === 'inbox') {
    try { await loadInbox(); await markRead(); } catch { error.value = 'تعذر تحميل الإشعارات. حاول مجدداً.'; }
  }
}
watch(welcome, value => { if (value) void open('welcome'); });
function close() { dismiss(); dialog.value?.close(); }
function backdrop(event) { if (event.target === dialog.value) close(); }
async function addToDevice() {
  if (installEvent.value && !device.value.ios) await install();
  else instructions.value = true;
}
const date = value => new Intl.DateTimeFormat('ar-IQ', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
</script>

<template>
  <section class="portal-app-toolbar" aria-label="إعدادات بوابة المريض">
    <div class="portal-app-brand"><span class="portal-app-dot"></span><span>{{ config?.lab_name || 'بوابة المريض' }}</span></div>
    <div class="portal-app-tools">
      <button type="button" @click="open('inbox')" aria-label="الإشعارات" class="portal-app-bell">
        <i class="pi pi-bell" aria-hidden="true"></i><span v-if="unread" class="portal-app-count">{{ unread > 9 ? '9+' : unread }}</span>
      </button>
      <button type="button" @click="open('settings')" class="portal-app-settings"><i class="pi pi-sliders-h" aria-hidden="true"></i><span>إعدادات البوابة</span></button>
    </div>
  </section>

  <Teleport to="body">
    <dialog ref="dialog" class="portal-app-dialog" dir="rtl" aria-labelledby="portal-app-title" @click="backdrop" @cancel="dismiss" @close="dismiss">
      <div class="portal-app-shell">
        <header class="portal-app-heading">
          <div class="portal-app-heading-icon"><i :class="screen === 'inbox' ? 'pi pi-bell' : 'pi pi-mobile'" aria-hidden="true"></i></div>
          <button type="button" class="portal-app-close" aria-label="إغلاق" @click="close"><i class="pi pi-times" aria-hidden="true"></i></button>
          <p class="portal-app-eyebrow">{{ config?.lab_name || 'بوابة المريض' }}</p>
          <h2 id="portal-app-title">{{ screen === 'welcome' ? 'بوابتك، أقرب إليك' : screen === 'inbox' ? 'إشعاراتك' : 'بوابتك على طريقتك' }}</h2>
          <p>{{ screen === 'inbox' ? 'تحديثاتك في مكان واحد، حتى عند إيقاف إشعارات الجهاز.' : 'وصول أسرع لنتائجك وتنبيه عند جاهزيتها.' }}</p>
        </header>

        <div class="portal-app-content">
          <template v-if="screen !== 'inbox'">
            <section class="portal-app-card">
              <div class="portal-app-card-top">
                <span class="portal-app-feature-icon"><i class="pi pi-mobile" aria-hidden="true"></i></span>
                <div><h3>أضف البوابة لموبايلك</h3><p>افتحها مباشرة من الشاشة الرئيسية.</p></div>
                <span v-if="device.installed" class="portal-app-state good"><i class="pi pi-check" aria-hidden="true"></i> مضافة</span>
              </div>
              <button v-if="!device.installed" type="button" class="portal-app-button secondary" :disabled="busy || !initialized" @click="addToDevice">
                <i class="pi pi-plus" aria-hidden="true"></i>{{ device.ios ? 'طريقة الإضافة للآيفون' : installEvent ? 'إضافة البوابة للجهاز' : 'طريقة إضافة البوابة' }}
              </button>
              <div v-if="instructions" class="portal-app-guide" role="status">
                <template v-if="device.ios">
                  <strong>ثلاث خطوات بسيطة</strong>
                  <ol><li>افتح رابط بوابتك في <b>Safari</b>.</li><li>اضغط <b>مشاركة</b> ثم <b>إضافة إلى الشاشة الرئيسية</b>.</li><li>افتح البوابة من الأيقونة الجديدة، ثم فعّل الإشعارات.</li></ol>
                </template>
                <template v-else>
                  <strong>إضافة البوابة من المتصفح</strong>
                  <ol><li>افتح الرابط في <b>Chrome</b> أو متصفح يدعم التثبيت.</li><li>من قائمة المتصفح اختر <b>تثبيت التطبيق</b> أو <b>إضافة إلى الشاشة الرئيسية</b> إذا كان الخيار متاحاً.</li><li>أكّد الإضافة وافتح أيقونة البوابة على جهازك.</li></ol>
                </template>
              </div>
            </section>

            <section class="portal-app-card">
              <div class="portal-app-card-top">
                <span class="portal-app-feature-icon amber"><i class="pi pi-bell" aria-hidden="true"></i></span>
                <div><h3>خليك على اطلاع</h3><p>تنبيه عند توفر تحديثاتك.</p></div>
              </div>
              <div class="portal-app-notification-status"><span class="portal-app-state" :class="{ good: subscribed && permission === 'granted' }">{{ statusText }}</span></div>
              <div class="portal-app-preferences">
                <label><span><b>جاهزية النتائج</b><small>إشعار عام؛ التفاصيل داخل بوابتك.</small></span><input v-model="results" type="checkbox" role="switch" aria-label="إشعارات جاهزية النتائج" :disabled="busy" /></label>
                <label><span><b>العروض والمكافآت</b><small>اختياري — أخبار مختبرك وبرنامج الولاء.</small></span><input v-model="offers" type="checkbox" role="switch" aria-label="إشعارات العروض والمكافآت" :disabled="busy" /></label>
              </div>
              <p v-if="device.embedded" class="portal-app-hint">افتح الرابط في متصفح الهاتف لتفعيل الإشعارات وإضافة البوابة.</p>
              <p v-else-if="device.ios && !device.installed" class="portal-app-hint">بالآيفون، أضف البوابة للشاشة الرئيسية وافتحها من الأيقونة أولاً. الإشعارات تتطلب iOS 16.4 أو أحدث.</p>
              <p v-else-if="permission === 'denied'" class="portal-app-hint">الإشعارات محظورة لهذا الموقع. اسمح بها من إعدادات المتصفح أو إعدادات إشعارات الجهاز، ثم ارجع للبوابة.</p>
              <p v-else-if="!device.push" class="portal-app-hint">إشعارات الجهاز غير متاحة في هذا المتصفح. تبقى تحديثاتك متاحة من جرس البوابة.</p>
              <p v-else-if="initialized && !config?.public_key" class="portal-app-hint">إشعارات الجهاز غير متاحة حالياً. تبقى تحديثاتك داخل البوابة.</p>
              <button v-if="!subscribed || permission !== 'granted'" type="button" class="portal-app-button primary" :disabled="busy || !canSubscribe || !initialized" @click="enable">
                <i class="pi pi-bell" aria-hidden="true"></i>{{ busy ? 'جاري التفعيل…' : 'تفعيل الإشعارات' }}
              </button>
              <template v-else>
                <button type="button" class="portal-app-button primary" :disabled="busy" @click="save">{{ busy ? 'جاري الحفظ…' : 'حفظ التفضيلات' }}</button>
                <div class="portal-app-inline-actions"><button type="button" :disabled="busy" @click="testNotification">إرسال إشعار تجريبي</button><button type="button" :disabled="busy" @click="disable">إيقاف على هذا الجهاز</button></div>
              </template>
            </section>
            <p class="portal-app-privacy"><i class="pi pi-lock" aria-hidden="true"></i>اختياراتك تخص هذا المريض وهذا الجهاز. يمكنك تغييرها في أي وقت.</p>
          </template>

          <template v-else>
            <div v-if="!inbox.length" class="portal-app-empty"><i class="pi pi-check-circle" aria-hidden="true"></i><h3>كل شيء محدّث</h3><p>راح تظهر هنا تنبيهاتك الجديدة عند توفرها.</p></div>
            <article v-for="item in inbox" :key="item.id" class="portal-app-notice"><span class="portal-app-feature-icon"><i :class="item.kind === 'result' ? 'pi pi-file' : 'pi pi-bell'" aria-hidden="true"></i></span><div><h3>{{ item.title }}</h3><p>{{ item.body }}</p><time :datetime="item.created_at">{{ date(item.created_at) }}</time></div></article>
            <button type="button" class="portal-app-button secondary" @click="open('settings')">إعدادات الإشعارات والتثبيت</button>
          </template>

          <p v-if="error" class="portal-app-feedback error" role="alert">{{ error }}</p>
          <p v-if="message" class="portal-app-feedback success" role="status">{{ message }}</p>
        </div>
        <footer class="portal-app-footer"><span>نتائجك متاحة دائماً بدون التثبيت</span><button type="button" @click="close">{{ screen === 'welcome' ? 'لاحقاً' : 'تم' }}</button></footer>
      </div>
    </dialog>
  </Teleport>
</template>

<style scoped>
.portal-app-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;color:#52666a;font-size:12px;padding:2px 2px 6px}
.portal-app-brand,.portal-app-tools,.portal-app-settings{display:flex;align-items:center;gap:8px}.portal-app-brand{min-width:0}.portal-app-brand>span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.portal-app-dot{width:7px;height:7px;border-radius:50%;background:#0f766e;flex:none}.portal-app-tools{flex:none}.portal-app-settings,.portal-app-bell{background:white;border:1px solid #dce7e5;border-radius:10px;padding:10px;color:#335951;cursor:pointer}.portal-app-bell{position:relative;min-width:38px}.portal-app-count{position:absolute;top:-5px;right:-4px;background:#0f766e;color:white;font-size:10px;border-radius:20px;min-width:18px;padding:1px 4px;border:2px solid #f9fafb}
.portal-app-dialog{padding:0;border:0;border-radius:24px;width:min(520px,calc(100% - 24px));max-height:calc(100dvh - 24px);margin:auto;box-shadow:0 24px 80px #0b2e3433;color:#173c37;background:#fff;font-family:inherit;overflow:auto}.portal-app-dialog::backdrop{background:#102d3b80;backdrop-filter:blur(4px)}.portal-app-shell{overflow:hidden;border-radius:24px;display:flex;flex-direction:column;max-height:calc(100dvh - 24px)}.portal-app-heading{position:relative;flex:none;padding:26px 28px 22px;background:linear-gradient(130deg,#effaf6,#f5fafc);border-bottom:1px solid #e2eeea}.portal-app-heading-icon{display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:#fff;box-shadow:0 3px 9px #0b655512;color:#0f766e;font-size:23px;margin-bottom:15px}.portal-app-close{position:absolute;top:18px;left:18px;width:34px;height:34px;background:#ffffffb3;border:1px solid #e1ebe7;border-radius:50%;color:#648078;cursor:pointer}.portal-app-eyebrow{font-size:11px;font-weight:600;color:#547a70;margin:0 0 6px!important}.portal-app-heading h2{font-size:24px;font-weight:800;line-height:1.4;margin:0 0 8px}.portal-app-heading p{font-size:12px;line-height:1.8;color:#58756e;margin:0}.portal-app-content{padding:22px 24px;display:grid;gap:14px;overflow-y:auto;min-height:0}.portal-app-card{padding:18px;border:1px solid #e3eae8;border-radius:16px;background:#fff}.portal-app-card-top{display:flex;align-items:center;gap:11px}.portal-app-card-top>div{flex:1;min-width:0}.portal-app-feature-icon{display:grid;place-items:center;background:#eef8f5;color:#0f766e;width:39px;height:39px;border-radius:12px;flex:none;font-size:18px}.portal-app-feature-icon.amber{background:#fff7e7;color:#b58125}.portal-app-card h3,.portal-app-notice h3{font-size:14px;font-weight:700;margin:0 0 4px}.portal-app-card p,.portal-app-notice p{font-size:11px;line-height:1.8;color:#667a75;margin:0}.portal-app-button{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;border-radius:11px;padding:12px 10px;font-size:12px;font-weight:700;cursor:pointer;margin-top:15px;border:1px solid transparent;transition:background .15s}.portal-app-button.primary{background:#0f766e;color:white}.portal-app-button.primary:hover{background:#0b625a}.portal-app-button.secondary{background:#f5faf8;border-color:#dcece5;color:#126154}.portal-app-button.secondary:hover{background:#eaf5ef}.portal-app-button:disabled,.portal-app-inline-actions button:disabled{opacity:.5;cursor:not-allowed}.portal-app-notification-status{margin-top:12px}.portal-app-state{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;color:#71807c;background:#f1f4f3;border-radius:20px;padding:4px 8px;white-space:nowrap}.portal-app-state.good{color:#176745;background:#eaf7ee}.portal-app-preferences{margin-top:8px}.portal-app-preferences label{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:13px 0;border-bottom:1px solid #eff3f1;cursor:pointer}.portal-app-preferences label:last-child{border:0}.portal-app-preferences b{display:block;font-size:12px;font-weight:600}.portal-app-preferences small{display:block;font-size:10px;line-height:1.8;color:#789087;margin-top:4px}.portal-app-preferences input{appearance:none;flex:none;width:36px;height:21px;border-radius:20px;background:#d6e0dc;position:relative;cursor:pointer;transition:background .15s}.portal-app-preferences input::after{content:'';position:absolute;top:3px;left:3px;width:15px;height:15px;background:white;border-radius:50%;box-shadow:0 1px 3px #0002;transition:transform .15s}.portal-app-preferences input:checked{background:#0f766e}.portal-app-preferences input:checked::after{transform:translateX(15px)}.portal-app-hint{background:#f8faf9;border-radius:9px;padding:10px!important;margin-top:8px!important}.portal-app-guide{padding:14px;background:#f5faf8;border-radius:10px;margin-top:12px;font-size:12px;line-height:1.9}.portal-app-guide strong{display:block;margin-bottom:6px}.portal-app-guide ol{list-style:decimal;padding-right:18px;margin:0}.portal-app-guide li+li{margin-top:6px}.portal-app-privacy{display:flex;align-items:flex-start;gap:7px;font-size:10px;color:#778e85;line-height:1.8;padding:0 5px;margin:0}.portal-app-privacy i{margin-top:3px}.portal-app-inline-actions{display:flex;justify-content:space-between;gap:8px;margin-top:12px}.portal-app-inline-actions button{font-size:10px;background:transparent;border:0;color:#597d72;padding:5px 0;cursor:pointer;text-decoration:underline;text-underline-offset:3px}.portal-app-feedback{font-size:12px;line-height:1.8;border-radius:10px;padding:12px;margin:0}.portal-app-feedback.error{background:#fff1f2;color:#9f2439}.portal-app-feedback.success{background:#ecfdf5;color:#176745}.portal-app-footer{flex:none;border-top:1px solid #edf1ef;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fbfdfc}.portal-app-footer span{font-size:10px;color:#82978e}.portal-app-footer button{background:white;border:1px solid #dce7e1;border-radius:9px;padding:9px 23px;font-size:12px;color:#365b4e;font-weight:600;cursor:pointer}.portal-app-empty{text-align:center;padding:20px 0}.portal-app-empty>i{font-size:36px;color:#a4c6b5}.portal-app-empty h3{font-size:16px;margin:12px 0 7px}.portal-app-empty p{font-size:12px;color:#7b9287}.portal-app-notice{display:flex;gap:12px;padding:10px 0 16px;border-bottom:1px solid #edf1ef}.portal-app-notice time{font-size:10px;color:#8a9d94;display:block;margin-top:8px}
button:focus-visible,input:focus-visible{outline:3px solid #39b8a5;outline-offset:3px}
@media(max-width:420px){.portal-app-heading{padding:22px}.portal-app-heading h2{font-size:22px}.portal-app-content{padding:18px 16px}.portal-app-card{padding:15px}.portal-app-footer{padding:14px 18px}.portal-app-brand{max-width:42%}.portal-app-settings{font-size:11px}}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
