const test=require('node:test');const assert=require('node:assert/strict');const crypto=require('node:crypto');
const {PRODUCT,digest,verifyDevice,verifyLicense,signLicense}=require('../src/license.cjs');
const issuer=crypto.generateKeyPairSync('ed25519'), other=crypto.generateKeyPairSync('ed25519');
const publicKey=issuer.publicKey.export({type:'spki',format:'pem'});
const config={publicKey,channel:'production',allowSoftwareBinding:false};
const now=Date.UTC(2026,9,29), device={deviceId:'a'.repeat(64),binding:'tpm'};
const claims={version:1,product:PRODUCT,channel:'production',id:crypto.randomUUID(),lab:'مختبر الاختبار',type:'annual',issuedAt:now-1000,expiresAt:now+86400000,...device};
const token=(changes={},key=issuer.privateKey)=>signLicense({...claims,...changes},key);
test('valid annual license; exact expiry is denied',()=>{assert.equal(verifyLicense(token(),config,device,{now}).type,'annual');assert.throws(()=>verifyLicense(token({expiresAt:now}),config,device,{now}),/انتهى/);});
test('lifetime has no expiry, remains valid decades later and cannot carry an expiry',()=>{assert.equal(verifyLicense(token({type:'lifetime',expiresAt:null}),config,device,{now:now+100*365*86400000}).type,'lifetime');assert.throws(()=>verifyLicense(token({type:'lifetime'}),config,device,{now}));});
test('copied license, modified claims, wrong signer and malformed token fail',()=>{
  assert.throws(()=>verifyLicense(token(),config,{...device,deviceId:'b'.repeat(64)},{now}));
  const t=token().split('.');const c=JSON.parse(Buffer.from(t[0],'base64url'));c.type='lifetime';c.expiresAt=null;t[0]=Buffer.from(JSON.stringify(c)).toString('base64url');
  for(const value of [t.join('.'),token({},other.privateKey),'','a.b','a.b.c'])assert.throws(()=>verifyLicense(value,config,device,{now}));
});
test('QA licenses cannot activate production; unsupported schema and future issue dates fail',()=>{
  for(const c of [{channel:'qa'},{version:2},{product:'other'},{issuedAt:now+600000},{type:'unlimited'},{expiresAt:null},{lab:''}])assert.throws(()=>verifyLicense(token(c),config,device,{now}));
});
test('local clock rollback blocked, small correction tolerated',()=>{assert.throws(()=>verifyLicense(token(),config,device,{now,lastSeen:now+600000}));assert.ok(verifyLicense(token(),config,device,{now,lastSeen:now+60000}));});
test('software protection requires explicit build policy and signed matching binding',()=>{
  const d={...device,binding:'software'},t=token({binding:'software'});
  assert.throws(()=>verifyLicense(t,config,d,{now}));assert.ok(verifyLicense(t,{...config,allowSoftwareBinding:true},d,{now}));assert.throws(()=>verifyLicense(token(),{...config,allowSoftwareBinding:true},d,{now}));
});
test('device must prove possession of private key using a fresh unpredictable challenge',()=>{
  const pair=crypto.generateKeyPairSync('rsa',{modulusLength:2048}), spki=pair.publicKey.export({type:'spki',format:'der'}),challenge=crypto.randomBytes(32).toString('base64');
  const r={binding:'tpm',deviceId:digest(spki),spki:spki.toString('base64'),challenge,proof:crypto.sign('sha256',Buffer.from(challenge,'base64'),pair.privateKey).toString('base64')};
  assert.equal(verifyDevice(r,challenge),r);assert.throws(()=>verifyDevice(r,crypto.randomBytes(32).toString('base64')));assert.throws(()=>verifyDevice({...r,deviceId:'b'.repeat(64)},challenge));assert.throws(()=>verifyDevice({...r,proof:'AAAA'},challenge));
});
test('gateway blocks all work including analyzer ingestion when license expires',async()=>{
  const {createGateway}=require('../src/gateway.cjs');const gateway=createGateway({},'x'.repeat(64),17995,()=>{throw new Error('License expired');});
  await new Promise(r=>gateway.server.listen(17995,'127.0.0.1',r));
  try {for(const [route,method] of [['/api/patients','GET'],['/api/device/bridge/results','POST'],['/','GET']])assert.equal((await fetch('http://127.0.0.1:17995'+route,{method,headers:{'X-Digital-Lab-Desktop':'x'.repeat(64)}})).status,402);}
  finally{await gateway.close();}
});
