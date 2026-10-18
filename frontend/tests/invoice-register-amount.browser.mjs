import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';

const browser = await chromium.launch(), page = await browser.newPage({viewport:{width:1440,height:1050}}), errors=[];
page.on('pageerror', e=>errors.push(e.message));
const ready = () => page.waitForFunction(()=>Boolean(window.invoiceFixture?.form()));
const field = () => page.getByLabel('المبلغ المدفوع في السجل · د.ع',{exact:true});
const amount = async value => {await field().fill(value);await field().press('Tab');};
const edit = async () => {await page.evaluate(()=>invoiceFixture.router.push('/invoices/301/edit'));await ready();};
const update = async () => {await page.evaluate(()=>invoiceFixture.form().update());await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');};
const payload = () => page.evaluate(()=>invoiceFixture.state.lastSaved);
try {
 await mkdir('test-results/work-register',{recursive:true});
 await page.goto('http://127.0.0.1:5173/tests/invoice-referrals-browser.html?page=1',{waitUntil:'networkidle'});await ready();
 assert.equal(await field().inputValue(),'');
 await page.evaluate(()=>{const f=invoiceFixture.form();f.selectExistingPatient({id:700,name:'مريض تجريبي'});f.addSelection('test',invoiceFixture.test,false);f.payment_details[0].amount=1000;});
 await amount('7500');
 assert.equal(await page.evaluate(()=>invoiceFixture.form().the_paid),1000);
 assert.equal(await page.evaluate(()=>invoiceFixture.form().record.total),5000);
 await page.evaluate(()=>invoiceFixture.form().markFullyPaid());
 assert.equal(await page.evaluate(()=>invoiceFixture.form().record.work_register_amount),7500,'fully paid only changes ordinary payments');
 assert.equal(await page.evaluate(()=>invoiceFixture.form().the_paid),5000);
 const positions=await page.evaluate(()=>({payment:document.querySelector('[data-invoice-payment]').getBoundingClientRect().top,register:document.querySelector('[data-register-amount-field]').getBoundingClientRect().top}));
 assert.ok(positions.register>positions.payment,'manual field is beneath payments');
 await field().scrollIntoViewIfNeeded();
 await page.screenshot({path:'test-results/work-register/invoice-manual-amount.png',fullPage:true});
 await page.evaluate(()=>invoiceFixture.form().create());
 await page.waitForFunction(()=>invoiceFixture.state.saved===1);
 assert.equal((await payload()).work_register_amount,7500);
 assert.equal((await payload()).payment_details[0].amount,5000);
 assert.equal(await page.evaluate(()=>Object.hasOwn(invoiceFixture.store.printRecord,'work_register_amount')),false,'invoice printing receives no register amount');
 await page.evaluate(()=>{invoiceFixture.store.printInvoiceDialog=false;});
 await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');
 await edit();assert.equal(await page.evaluate(()=>invoiceFixture.form().record.work_register_amount),7500);
 // Another receptionist changes the register after this invoice was opened.
 await page.evaluate(()=>{invoiceFixture.state.registerAmounts[301]=9000;invoiceFixture.form().record.notes='unrelated edit';});
 await update();assert.equal(Object.hasOwn(await payload(),'work_register_amount'),false);
 assert.equal(await page.evaluate(()=>invoiceFixture.state.registerAmounts[301]),9000);
 await edit();assert.equal(await page.evaluate(()=>invoiceFixture.form().record.work_register_amount),9000);
 await amount('0');await update();assert.equal((await payload()).work_register_amount,0);assert.equal((await payload()).work_register_amount_original,9000);
 await edit();assert.equal(await page.evaluate(()=>invoiceFixture.form().record.work_register_amount),0);
 await amount('');await update();assert.equal((await payload()).work_register_amount,null);assert.equal((await payload()).work_register_amount_original,0);
 await edit();assert.equal(await field().inputValue(),'');
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/create'));await ready();assert.equal(await field().inputValue(),'');
 await page.evaluate(()=>{const f=invoiceFixture.form();f.selectExistingPatient({id:701,name:'مريض آخر'});});
 await amount('2300');
 await page.evaluate(()=>invoiceFixture.form().selectExistingPatient({id:702,name:'مريض جديد'}));
 assert.equal(await field().inputValue(),'','switching patient clears the previous amount');
 await amount('1900');await page.evaluate(()=>{const f=invoiceFixture.form();f.addSelection('test',invoiceFixture.test,false);invoiceFixture.state.failSave=true;});
 await page.evaluate(()=>invoiceFixture.form().create());
 assert.equal(await page.evaluate(()=>invoiceFixture.form().record.work_register_amount),1900,'failed save preserves the entered amount');
 await page.evaluate(()=>invoiceFixture.form().close());
 await page.waitForFunction(()=>invoiceFixture.router.currentRoute.value.path==='/invoices');
 await page.evaluate(()=>invoiceFixture.router.push('/invoices/create'));await ready();assert.equal(await field().inputValue(),'');
 assert.deepEqual(errors,[]);
 console.log('PASS invoice manual register amount: placement, isolated payments/printing, edit roundtrip, unchanged omission, zero, clear, patient reset and failed-save preservation');
} catch(error) {await page.screenshot({path:'test-results/work-register/invoice-amount-failure.png',fullPage:true}).catch(()=>{});throw error;}
finally {await browser.close();}
