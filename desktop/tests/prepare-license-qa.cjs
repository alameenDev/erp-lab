// Ephemeral QA issuer; private key stays in RUNNER_TEMP, never in an artifact.
const fs=require('node:fs');const path=require('node:path');const os=require('node:os');const crypto=require('node:crypto');const {execFileSync}=require('node:child_process');
const {PRODUCT,verifyDevice,signLicense}=require('../src/license.cjs');
const root=path.resolve(__dirname,'..'),temp=process.env.RUNNER_TEMP||os.tmpdir();
const pair=crypto.generateKeyPairSync('ed25519');const pub=path.join(temp,'dld-qa-public.pem');fs.writeFileSync(pub,pair.publicKey.export({type:'spki',format:'pem'}));
execFileSync(process.execPath,[path.join(root,'scripts/configure-license.cjs'),pub,'qa','true'],{stdio:'inherit'});
const challenge=crypto.randomBytes(32).toString('base64');
const request=JSON.parse(execFileSync(path.join(root,'licensing/DigitalLab.DeviceIdentity.exe'),['software',challenge],{encoding:'utf8'}));verifyDevice(request,challenge);
const claims={version:1,product:PRODUCT,channel:'qa',id:crypto.randomUUID(),lab:'QA — NOT FOR DISTRIBUTION',type:'lifetime',issuedAt:Date.now(),expiresAt:null,deviceId:request.deviceId,binding:request.binding};
const token=signLicense(claims,pair.privateKey);const file=path.join(temp,'dld-qa.dllicense');fs.writeFileSync(file,token,{mode:0o600});
if(process.env.GITHUB_ENV)fs.appendFileSync(process.env.GITHUB_ENV,'DLD_QA_LICENSE='+file+'\n');
console.log('Prepared ephemeral, device-bound QA license. No production authority configured.');
