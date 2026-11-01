import test from 'node:test';
import assert from 'node:assert/strict';
import {mutationReasonPolicy,reasonValue,attachReason,validReason} from '../src/utils/mutationReason.js';
test('action-time reasons cover all manual catalogue edits and deletes, including invoice and test prices',()=>{
 for(const [method,url,action] of [
   ['put','/invoices/update','update'],['put','/tests/update','update'],
   ['put','/patients/update','update'],['put','/owner-portal/team/27','update'],
   ['patch','/work-register/3','update'],['post','/doctors/27','update'],
   ['post','/invoices/update-result','update'],['post','/invoices/add-payment','update'],
   ['delete','/invoices/delete','delete'],['delete','/tests/delete','delete'],
   ['delete','/patients/delete','delete'],['delete','/work-register/4','delete'],
   ['delete','/promo-codes/4','delete'],
 ])assert.equal(mutationReasonPolicy({method,url})?.action,action,method+' '+url);
});
test('initial discounts prompt, normal invoice creation does not',()=>{
 assert.equal(mutationReasonPolicy({method:'post',url:'/invoices/create',data:{discount:5000}})?.action,'discount');
 assert.equal(mutationReasonPolicy({method:'post',url:'/invoices/create',data:{discount:0}}),null);
 assert.equal(mutationReasonPolicy({method:'post',url:'/invoices/create',data:{}}),null);
});
test('background autosaves, lab device messages and report transport do not display prompts',()=>{
 for(const [method,url] of [
   ['post','/invoices/update-result?silent=1'],['post','/device/bridge/results'],
   ['post','/device/results'],['post','/owner-portal/notifications/read'],
   ['post','/invoices/report-action'],['post','/invoices/send'],
   ['post','/user/login']
 ])assert.equal(mutationReasonPolicy({method,url}),null,method+' '+url);
 assert.equal(mutationReasonPolicy({method:'put',url:'/invoices/update',skipMutationReason:true}),null);
});
test('cancellation safe policy supports JSON, form submissions and multipart uploads',()=>{
 const reason='تم تصحيح السعر بناء على موافقة إدارة المختبر';
 assert.equal(validReason(reason),true);
 assert.equal(validReason(' '),false);
 assert.equal(validReason('short'),true);\n assert.equal(validReason('سبب'),true);\n assert.equal(validReason('  '),false);
 const json=attachReason({id:7},reason);
 assert.equal(json.change_reason,reason);
 assert.equal(reasonValue(json),reason);
 const data=new FormData();data.append('id','7');
 const output=attachReason(data,reason);
 assert.equal(output.get('id'),'7');
 assert.equal(reasonValue(output),reason);
 assert.equal(reasonValue(attachReason(JSON.stringify({id:7}),reason)),reason);
});
