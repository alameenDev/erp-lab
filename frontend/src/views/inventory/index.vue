<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { $http } from "@/plugins/axios";
import { alertError, alertSuccess } from "@/utils/helper";

const data=reactive({items:[],kits:[],bindings:[]}), options=reactive({subjects:{test:[],culture:[],package:[],test_group:[]}}), report=ref([]);
const tab=ref("stock"),busy=ref(false),invoiceId=ref(""),invoice=reactive({relations:[],movements:[]});
const item=reactive({name:"",unit:"تحليل",low_stock_threshold:5});
const kit=reactive({item_id:"",serial_number:"",lot_number:"",expires_on:"",capacity:30});
const binding=reactive({subject_type:"test",subject_id:"",item_id:"",quantity:1,active:true});
const filters=reactive({from:"",to:"",kind:"",search:""});
const subjectList=computed(()=>options.subjects[binding.subject_type]||[]);
const total=computed(()=>data.items.reduce((n,x)=>n+Number(x.available||0),0));
const low=computed(()=>data.items.filter(x=>Number(x.available)<=Number(x.low_stock_threshold)).length);
const apiError=e=>e?.response?.data?.message||Object.values(e?.response?.data?.errors||{}).flat()[0]||"تعذر إكمال العملية";
async function load(){busy.value=true;try{const [a,b]=await Promise.all([$http.get("/inventory"),$http.get("/inventory/options")]);Object.assign(data,a.data);Object.assign(options,b.data)}catch(e){alertError(apiError(e))}finally{busy.value=false}}
async function saveItem(){try{await $http.post("/inventory/items",item);Object.assign(item,{name:"",unit:"تحليل",low_stock_threshold:5});alertSuccess("تمت إضافة المادة");await load()}catch(e){alertError(apiError(e))}}
async function receive(){try{await $http.post("/inventory/kits",kit);Object.assign(kit,{item_id:"",serial_number:"",lot_number:"",expires_on:"",capacity:30});alertSuccess("تم استلام الكِت وتسجيل رصيده");await load()}catch(e){alertError(apiError(e))}}
async function bind(){try{await $http.post("/inventory/bindings",binding);alertSuccess("تم ربط التحليل بالمخزون");await load()}catch(e){alertError(apiError(e))}}
async function adjust(k){const q=Number(prompt("أدخل الكمية: موجب للإضافة وسالب للتنقيص"));if(!q)return;const reason=prompt("سبب التسوية (إلزامي)");if(!reason)return;try{await $http.post(`/inventory/kits/${k.id}/adjust`,{quantity:q,reason,expected_remaining:k.remaining});await load()}catch(e){alertError(apiError(e))}}
async function loadReport(){try{const {data:r}=await $http.get("/inventory/report",{params:filters});report.value=r.rows.data}catch(e){alertError(apiError(e))}}
function exportCsv(){const q=new URLSearchParams(Object.entries(filters).filter(([,v])=>v));window.open(`${$http.defaults.baseURL}/inventory/report/export?${q}`)}
async function loadInvoice(){if(!invoiceId.value)return;try{const {data:r}=await $http.get(`/inventory/invoices/${invoiceId.value}`);Object.assign(invoice,r)}catch(e){alertError(apiError(e))}}
async function repeat(rel){const reason=prompt("اكتب سبب إعادة التحليل (إلزامي، 5 أحرف على الأقل)");if(!reason)return;try{await $http.post(`/inventory/invoices/${invoiceId.value}/repeat`,{invoice_test_rel_id:rel.id,reason,request_id:crypto.randomUUID()});alertSuccess("تم تسجيل إعادة التحليل وخصم المواد");await Promise.all([load(),loadInvoice()])}catch(e){alertError(apiError(e))}}
const subjectName=b=>options.subjects[b.subject_type]?.find(x=>x.id==b.subject_id)?.name||"#"+b.subject_id;
onMounted(load);
</script>

<template>
<div class="space-y-6" dir="rtl">
 <header class="rounded-3xl bg-gradient-to-l from-slate-950 via-slate-900 to-teal-950 p-7 text-white shadow-xl">
  <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs tracking-[.25em] text-teal-300">LAB INVENTORY</p><h1 class="mt-2 text-3xl font-black">إدارة المخزون والكِتّات</h1><p class="mt-2 text-slate-300">تتبّع كل صرف من السيريال إلى المريض والمستخدم، مع منع تكرار الخصم.</p></div>
  <div class="flex gap-3"><div class="rounded-2xl bg-white/10 px-5 py-3"><small>الرصيد المتاح</small><b class="block text-2xl">{{total}}</b></div><div class="rounded-2xl bg-amber-400/15 px-5 py-3"><small>مواد منخفضة</small><b class="block text-2xl text-amber-300">{{low}}</b></div></div></div>
 </header>
 <nav class="flex gap-2 rounded-2xl bg-white p-2 shadow-sm">
  <button v-for="x in [['stock','المخزون'],['links','ربط التحاليل'],['reports','سجل الصرف'],['repeat','إعادة تحليل']]" :key="x[0]" @click="tab=x[0];x[0]==='reports'&&loadReport()" class="rounded-xl px-5 py-2.5 font-bold" :class="tab===x[0]?'bg-teal-600 text-white':'text-slate-600 hover:bg-slate-100'">{{x[1]}}</button>
 </nav>
 <div v-if="busy" class="rounded-2xl bg-white p-10 text-center">جاري التحميل...</div>
 <template v-else-if="tab==='stock'">
  <section class="grid gap-5 xl:grid-cols-2">
   <form @submit.prevent="saveItem" class="card"><h2>تعريف مادة مخزنية</h2><div class="grid gap-3 sm:grid-cols-3"><input v-model="item.name" required placeholder="مثال: Kit Vitamin D3"><input v-model="item.unit" required placeholder="الوحدة"><input v-model.number="item.low_stock_threshold" type="number" min="0" placeholder="حد التنبيه"></div><button>إضافة المادة</button></form>
   <form @submit.prevent="receive" class="card"><h2>استلام كِت جديد</h2><div class="grid gap-3 sm:grid-cols-2"><select v-model="kit.item_id" required><option value="">المادة</option><option v-for="x in data.items" :value="x.id">{{x.name}}</option></select><input v-model="kit.serial_number" required placeholder="Serial Number"><input v-model="kit.lot_number" placeholder="Lot Number"><input v-model="kit.expires_on" type="date"><input v-model.number="kit.capacity" required type="number" min="1" placeholder="عدد التحاليل"></div><button>استلام وتسجيل الرصيد</button></form>
  </section>
  <section class="card overflow-x-auto"><h2>الكِتّات والأرصدة</h2><table><thead><tr><th>المادة</th><th>السيريال</th><th>اللوط</th><th>الصلاحية</th><th>الرصيد</th><th></th></tr></thead><tbody><tr v-for="k in data.kits"><td>{{k.item_name}}</td><td class="font-mono">{{k.serial_number}}</td><td>{{k.lot_number||"-"}}</td><td>{{k.expires_on||"-"}}</td><td><b :class="k.remaining<5?'text-red-600':'text-teal-700'">{{k.remaining}} / {{k.capacity}}</b></td><td><button class="mini" @click="adjust(k)">تسوية</button></td></tr></tbody></table></section>
 </template>
 <template v-else-if="tab==='links'">
  <form @submit.prevent="bind" class="card max-w-4xl"><h2>ربط التحليل بالمادة</h2><p>عند اعتماد أول نتيجة يُخصم المقدار تلقائياً من أقرب كِت انتهاءً.</p><div class="grid gap-3 sm:grid-cols-4"><select v-model="binding.subject_type" @change="binding.subject_id=''"><option value="test">تحليل</option><option value="culture">زرع</option><option value="package">باقة</option><option value="test_group">مجموعة</option></select><select v-model="binding.subject_id" required><option value="">اختر التحليل</option><option v-for="x in subjectList" :value="x.id">{{x.name}}</option></select><select v-model="binding.item_id" required><option value="">اختر المادة</option><option v-for="x in data.items" :value="x.id">{{x.name}}</option></select><input v-model.number="binding.quantity" type="number" min="1" required></div><button>حفظ الربط</button></form>
  <section class="card"><h2>الروابط الحالية</h2><div class="grid gap-3 md:grid-cols-2"><div v-for="b in data.bindings" class="rounded-xl border p-4"><b>{{subjectName(b)}}</b><p class="text-sm text-slate-500">{{b.item_name}} × {{b.quantity}}</p></div></div></section>
 </template>
 <template v-else-if="tab==='reports'">
  <section class="card"><div class="flex flex-wrap items-end gap-3"><label>من<input v-model="filters.from" type="date"></label><label>إلى<input v-model="filters.to" type="date"></label><select v-model="filters.kind"><option value="">كل الحركات</option><option value="initial">صرف أول نتيجة</option><option value="repeat">إعادة تحليل</option><option value="receipt">استلام</option><option value="adjustment">تسوية</option></select><input v-model="filters.search" placeholder="المريض، الكود، التحليل، السيريال"><button @click="loadReport">بحث</button><button class="secondary" @click="exportCsv">Excel CSV</button></div></section>
  <section class="card overflow-x-auto"><table><thead><tr><th>التاريخ والوقت</th><th>المادة / السيريال</th><th>المريض</th><th>التحليل</th><th>الحركة</th><th>الكمية</th><th>المستخدم</th><th>السبب</th></tr></thead><tbody><tr v-for="r in report"><td>{{r.created_at}}</td><td>{{r.item_name}}<small class="block font-mono">{{r.serial_number}}</small></td><td>{{r.patient_name||"-"}}<small class="block">{{r.patient_code}}</small></td><td>{{r.test_name||"-"}}</td><td>{{r.kind}}</td><td :class="r.quantity<0?'text-red-600':'text-emerald-600'">{{r.quantity}}</td><td>{{r.actor_name||"-"}}</td><td>{{r.reason||"-"}}</td></tr></tbody></table></section>
 </template>
 <template v-else>
  <section class="card"><h2>تسجيل إعادة تحليل</h2><p>ابحث برقم الفاتورة، ثم اختر تحليلاً مكتمل النتيجة. سيطلب النظام سبب الإعادة ويصرف كمية جديدة.</p><div class="flex gap-3"><input v-model="invoiceId" type="number" placeholder="رقم الفاتورة"><button @click="loadInvoice">عرض التحاليل</button></div><div class="mt-5 grid gap-3 md:grid-cols-2"><div v-for="r in invoice.relations" class="rounded-xl border p-4"><div class="flex justify-between"><div><b>{{r.analysis_name||'تحليل'}}</b><p class="text-sm text-slate-500">مرات التشغيل: {{r.runs?.length||0}}</p></div><button v-if="r.is_done" class="mini" @click="repeat(r)">تسجيل إعادة</button><span v-else class="text-sm text-amber-600">النتيجة غير معتمدة</span></div></div></div></section>
 </template>
</div>
</template>
<style scoped>
.card{@apply rounded-2xl border border-slate-200 bg-white p-5 shadow-sm}.card h2{@apply mb-4 text-lg font-black text-slate-800}.card p{@apply mb-4 text-sm text-slate-500}input,select{@apply min-h-11 rounded-xl border border-slate-200 bg-white px-3 outline-none focus:border-teal-500}button{@apply rounded-xl bg-teal-600 px-5 py-2.5 font-bold text-white hover:bg-teal-700}.secondary,.mini{@apply bg-slate-100 text-slate-700 hover:bg-slate-200}.mini{@apply px-3 py-1.5 text-xs}table{@apply w-full min-w-[800px] text-sm}th{@apply bg-slate-50 p-3 text-right font-bold text-slate-500}td{@apply border-t border-slate-100 p-3}label{@apply grid gap-1 text-xs font-bold text-slate-500}
</style>