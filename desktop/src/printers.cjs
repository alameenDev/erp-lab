const path = require('node:path');
function createPrinters(bundle, data) {
  const {State} = require(path.join(bundle,'print/state.cjs'));
  const {validateJob} = require(path.join(bundle,'print/validation.cjs'));
  const native = require(path.join(bundle,'print/printing.cjs'));
  const state = new State(path.join(data,'print-jobs.json'));
  let chain = Promise.resolve(), pending = 0;
  const publicJob = j=>({id:j.id,status:j.status,error:j.error});
  return {
    get pending(){return pending;},
    async request(route,{method='GET',body}={}) {
      if(route==='/health' && method==='GET') return {name:'Digital Lab Print',protocol:1,version:'1.0.0'};
      if(route==='/printers' && method==='GET') return {printers:(await native.listPrinters()).map(p=>({name:p.name,displayName:p.displayName||p.name,isDefault:!!p.isDefault}))};
      const match = /^\/jobs\/([a-f0-9-]{36})$/.exec(route);
      if(match && method==='GET') {const j=state.job('desktop',match[1]);if(!j) throw new Error('JOB_NOT_FOUND');return publicJob(j);}
      if(route==='/jobs' && method==='POST') {
        const input = validateJob(body);if(pending>=5) throw new Error('انتظر اكتمال الطباعة الحالية.');
        const {job,fresh} = state.createJob('desktop',input);
        if(fresh) {pending++;chain=chain.then(async()=>{
          try {state.update(job,'rendering');await native.print(input,()=>state.update(job,'submitting'));state.update(job,'submitted');}
          catch(e){state.update(job,job.status==='submitting'?'unknown':'failed',e.safeMessage||'تعذرت الطباعة. تحقق من قائمة Windows قبل الإعادة.');}
          finally{pending--;}
        }).catch(()=>{});}
        return publicJob(job);
      }
      throw new Error('UNSUPPORTED_PRINT_REQUEST');
    }
  };
}
module.exports = {createPrinters};
