const test = require('node:test');
const assert = require('node:assert/strict');
const {mkdtempSync, rmSync, readFileSync} = require('node:fs');
const {tmpdir} = require('node:os');
const path = require('node:path');
const {randomUUID} = require('node:crypto');
const {State} = require('../src/state.cjs');
const {createServer, validOrigin} = require('../src/server.cjs');
const {validateJob, KINDS} = require('../src/validation.cjs');
const ORIGIN = 'https://lab.example';
const job = (patch = {}) => ({id: randomUUID(), kind: 'barcode', copies: 1, printer: 'Label Printer', html: '<html><body>synthetic-patient-secret</body></html>', paper: {width: 50, height: 30}, ...patch});
async function fixture(t, options = {}) {
  const dir = mkdtempSync(path.join(tmpdir(), 'dlprint-')), file = path.join(dir, 'state.json');
  const state = new State(file), printed = []; let approvals = 0;
  const server = createServer({state, approvePair: async () => {approvals++;return options.approve !== false;},
    listPrinters: async () => [{name: 'Label Printer', displayName: 'Labels', isDefault: false}],
    print: options.print || (async (input, submit) => {submit();printed.push(input);}), port: 0});
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  t.after(async () => {await new Promise(resolve => server.close(resolve)); rmSync(dir, {recursive: true, force: true});});
  const call = async (url, opts = {}) => {
    const {origin = ORIGIN, token, body, ...rest} = opts;
    const res = await fetch(`http://127.0.0.1:${server.address().port}/v1${url}`, {...rest,
      headers: {Origin: origin, ...(token ? {Authorization: 'Bearer '+token} : {}), ...(body ? {'Content-Type':'application/json'} : {}), ...(rest.headers || {})},
      ...(body ? {body: JSON.stringify(body)} : {})});
    return {status: res.status, body: res.status === 204 ? {} : await res.json(), headers: res.headers};
  };
  return {state, file, call, printed, port: server.address().port, approvals: () => approvals, token: state.pair(ORIGIN)};
}
test('only exact HTTPS origins are accepted; localhost is development-only', () => {
  for (const v of ['null','file:///tmp','http://lab.example','https://user@lab.example','https://lab.example/path','https://lab.example/']) assert.equal(validOrigin(v), false);
  assert.equal(validOrigin(ORIGIN), true); assert.equal(validOrigin('http://localhost:5173'), false); assert.equal(validOrigin('http://localhost:5173', true), true);
});
test('pairing requires expiring one-use code AND explicit desktop approval', async t => {
  const f = await fixture(t);
  assert.equal((await f.call('/pair', {method:'POST',body:{code:'00000000'}})).status,403);
  const code=f.state.newCode().value;
  const paired=await f.call('/pair',{method:'POST',body:{code}});
  assert.equal(paired.status,200);assert.equal(f.approvals(),1);assert.match(paired.body.token,/^[a-f0-9]{64}$/);
  assert.equal((await f.call('/pair',{method:'POST',body:{code}})).status,403);
  assert.equal((await f.call('/printers',{token:paired.body.token})).status,200);
  assert.equal((await f.call('/printers',{token:f.token})).status,401,'new pairing revokes old browser token');
  const blocked=await fixture(t,{approve:false});const denied=await blocked.call('/pair',{method:'POST',body:{code:blocked.state.newCode().value}});assert.equal(denied.status,403);
});
test('codes expire and lock after five invalid attempts', async t => {
  const f=await fixture(t),code=f.state.newCode().value;
  for(let i=0;i<5;i++) assert.equal(f.state.consumeCode('invalid'),false);
  assert.equal(f.state.consumeCode(code),false);
  f.state.newCode();f.state.code.expires=Date.now()-1;assert.equal(f.state.consumeCode(f.state.code.value),false);
});
test('host/origin/token, CORS and revoke isolate printer and job access', async t => {
  const f=await fixture(t);
  assert.equal((await f.call('/printers')).status,401);
  assert.equal((await f.call('/printers',{token:f.token,origin:'https://other.example'})).status,401);
  const hostStatus = await new Promise(resolve => require('node:http').get({hostname:'127.0.0.1',port:f.port,path:'/v1/health',headers:{Host:'attacker.example',Origin:ORIGIN}},r=>{r.resume();resolve(r.statusCode);}));
  assert.equal(hostStatus,403);
  assert.equal((await f.call('/health',{origin:'null'})).status,403);
  const pre=await f.call('/jobs',{method:'OPTIONS'});assert.equal(pre.headers.get('access-control-allow-origin'),ORIGIN);assert.equal(pre.headers.get('access-control-allow-private-network'),'true');
  assert.equal((await f.call('/printers',{token:f.token})).body.printers[0].name,'Label Printer');
  assert.equal((await f.call('/pair',{method:'DELETE',token:f.token})).status,200);
  assert.equal((await f.call('/printers',{token:f.token})).status,401);
});
test('all document types preserve identity, explicit printer, copies and paper', () => {
  for(const kind of KINDS) assert.equal(validateJob(job({kind,copies:3})).copies,3);
  assert.deepEqual(validateJob(job({kind:'thermal',paper:{width:58,height:null}})).paper,{width:58,height:null});
  for(const patch of [{printer:''},{copies:0},{copies:21},{copies:1.5},{id:'x'},{kind:'shell'},{html:''},{paper:{width:0,height:30}},{paper:{width:50,height:null}},{paper:{width:50,height:Infinity}}]) assert.throws(()=>validateJob(job(patch)));
});
test('replaying a job is idempotent, conflicting reuse is rejected, ledger contains no patient content', async t => {
  const f=await fixture(t),input=job();
  assert.equal((await f.call('/jobs',{method:'POST',token:f.token,body:input})).status,202);
  assert.equal((await f.call('/jobs',{method:'POST',token:f.token,body:input})).status,202);
  assert.equal((await f.call('/jobs',{method:'POST',token:f.token,body:{...input,copies:2}})).status,409);
  const response=await f.call('/jobs/'+input.id,{token:f.token});assert.equal(response.body.status,'submitted');assert.equal(f.printed.length,1);
  assert.equal(readFileSync(f.file,'utf8').includes('synthetic-patient-secret'),false);
  assert.equal(readFileSync(f.file,'utf8').includes(f.token),false);
  const other=f.state.pair('https://other.example');assert.equal((await f.call('/jobs/'+input.id,{origin:'https://other.example',token:other})).status,404);
});
test('jobs are serialized and errors before/after spool submission have distinct outcomes', async t => {
  let running=0,max=0;
  const f=await fixture(t,{print:async(input,submit)=>{running++;max=Math.max(max,running);await new Promise(r=>setTimeout(r,30));running--;if(input.copies===2)throw new Error('early');submit();if(input.copies===3)throw new Error('late');}});
  const inputs=[job(),job({copies:2}),job({copies:3})];
  await Promise.all(inputs.map(body=>f.call('/jobs',{method:'POST',token:f.token,body})));
  await new Promise(r=>setTimeout(r,150));
  assert.equal(max,1);
  assert.deepEqual(await Promise.all(inputs.map(async i=>(await f.call('/jobs/'+i.id,{token:f.token})).body.status)),['submitted','failed','unknown']);
});
test('restart never replays queued or potentially spooled documents', async t => {
  const f=await fixture(t);const a=f.state.createJob(ORIGIN,job()).job,b=f.state.createJob(ORIGIN,job()).job;
  f.state.update(b,'submitting');const restarted=new State(f.file);
  assert.equal(restarted.job(ORIGIN,a.id).status,'failed');assert.equal(restarted.job(ORIGIN,b.id).status,'unknown');
});
