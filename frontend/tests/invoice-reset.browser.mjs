import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:1360,height:1000}});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
await mkdir('test-results/invoice-referrals',{recursive:true});
const ready=()=>page.waitForFunction(()=>Boolean(window.invoiceFixture?.form()));
const clean=async()=>{
 await ready();
 const state=await page.evaluate(()=>{const f=invoiceFixture.form();return {payments:f.payment_details,paid:f.the_paid,patient:f.responseData,name:f.patientStore.record.name,file:f.patientStore.selectedFile,notes:f.record.notes,referral:f.record.referral_id_fk,contract:f.record.contract_id_fk,fromLab:f.record.from_lab_id_fk,discount:f.discountValue,items:f.totalItemsCount,questions:f.testQuesions,order:f.record.selection_order,promo:f.promoCodeApplied,flags:[f.perceInput,f.valueInput]};});
 assert.deepEqual(state,{payments:[{amount:null,contract_id_fk:null,payment_method_id_fk:null}],paid:0,patient:null,name:'',file:'',notes:null,referral:null,contract:null,fromLab:null,discount:0,items:0,questions:[],order:[],promo:null,flags:[false,false]});
 const inputs=page.locator('[data-invoice-payment] input');
 assert.equal(await inputs.count(),1);assert.equal(await inputs.first().inputValue(),'');
};
try {
 await page.goto('http://127.0.0.1:5173/tests/invoice-referrals-browser.html?page=1');await clean();
 await page.evaluate(async()=>{const f=invoiceFixture.form();
  Object.assign(f.patientStore.record,{name:'First new patient',gender_type_id_fk:1,age:30,phone:'7700000000'});
  f.addSelection('test',invoiceFixture.test,false);f.record.notes='First invoice only';
  f.payment_details=[{amount:3000,contract_id_fk:null,payment_method_id_fk:1},{amount:1000,contract_id_fk:null,payment_method_id_fk:2}];
  await f.create();
 });
 await page.waitForFunction(()=>invoiceFixture.state.saved===1);await clean();
 assert.deepEqual(await page.evaluate(()=>invoiceFixture.store.printRecord.paidDetails.map(x=>x.amount)),[3000,1000],'saved/printed invoice keeps its original payments');
 await page.evaluate(()=>{invoiceFixture.store.printInvoiceDialog=false;});
 await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/create'));await clean();
 await page.evaluate(async()=>{const f=invoiceFixture.form();Object.assign(f.patientStore.record,{name:'Second new patient',gender_type_id_fk:2,age:25});f.addSelection('test',invoiceFixture.test,false);await f.create();});
 await page.waitForFunction(()=>invoiceFixture.state.saved===2);
 assert.equal(await page.evaluate(()=>invoiceFixture.state.lastSaved.payment_details[0].amount),null,'second invoice never submits first payment');
 assert.equal(await page.evaluate(()=>invoiceFixture.state.lastSaved.notes),undefined);
 await page.evaluate(()=>{invoiceFixture.store.printInvoiceDialog=false;});
 await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/301/edit'));await ready();
 await page.evaluate(async()=>{invoiceFixture.form().payment_details[0].amount=7200;await invoiceFixture.form().update();});
 await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');
 assert.equal(await page.evaluate(()=>invoiceFixture.state.lastSaved.payment_details[0].amount),7200);
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/301/edit'));await ready();
 assert.equal(await page.evaluate(()=>invoiceFixture.form().payment_details[0].amount),7200,'editing loads the saved payment');
 // Same page component, different route: this used to retain the standalone payment array.
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/create'));await clean();
 await page.evaluate(()=>{const f=invoiceFixture.form();f.selectExistingPatient({id:700,name:'Picked patient'});f.addSelection('test',invoiceFixture.test,false);f.payment_details[0].amount=2500;f.record.notes='discarded';f.patientStore.selectedFile='old-image.png';f.clearPatientSelection();});await clean();
 await page.evaluate(()=>{const f=invoiceFixture.form();f.selectExistingPatient({id:700,name:'Picked patient'});f.addSelection('test',invoiceFixture.test,false);f.payment_details[0].amount=2500;invoiceFixture.state.failSave=true;});
 await page.evaluate(()=>invoiceFixture.form().create());
 assert.equal(await page.evaluate(()=>invoiceFixture.form().payment_details[0].amount),2500,'failed save preserves the draft for correction');
 await page.evaluate(()=>{invoiceFixture.form().close();});
 await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/create'));await clean();
 // Explicitly linked patient is kept, with no previous invoice finances or image.
 await page.evaluate(async()=>{await invoiceFixture.router.push('/invoices');const f=await import('/src/store/modules/patients.js');f.usePatientsStore().responseData={id:701,name:'Linked patient'};await invoiceFixture.router.push('/invoices/create?patient_id=701');});await ready();
 assert.equal(await page.evaluate(()=>invoiceFixture.form().responseData.id),701);
 assert.equal(await page.evaluate(()=>invoiceFixture.form().the_paid),0);
 await page.screenshot({path:'test-results/invoice-referrals/clean-new-invoice.png',fullPage:true,animations:'disabled'});
 assert.deepEqual(errors,[]);console.log('PASS fresh payments across create/edit/cancel/route reuse, new patients, failed saves and explicit patient linking');
}finally{await browser.close();}
