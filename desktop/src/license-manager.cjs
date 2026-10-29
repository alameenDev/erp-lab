'use strict';
const fs=require('node:fs/promises');const path=require('node:path');const crypto=require('node:crypto');
const {run}=require('./process.cjs');
const {verifyDevice,decodeLicense,verifyLicense,digest}=require('./license.cjs');
class LicenseManager {
  constructor({userData,resources,vault,config}) {Object.assign(this,{userData,resources,vault,config});this.file=path.join(userData,'activation.dat');}
  async device(binding='tpm') {
    if(binding==='software'&&!this.config.allowSoftwareBinding)throw new Error('هذه النسخة تتطلب حماية TPM.');
    const exe=path.join(this.resources,'DigitalLab.DeviceIdentity.exe');
    if(digest(await fs.readFile(exe))!==this.config.deviceHelperSha256)throw new Error('ملف حماية الجهاز تالف أو معدّل. أعد التنصيب من النسخة الأصلية.');
    const challenge=crypto.randomBytes(32).toString('base64');
    try {return verifyDevice(JSON.parse(await run(exe,[binding,challenge],{timeout:30000})),challenge);}
    catch {throw new Error('تعذر فتح مفتاح الحاسبة. تأكد من تفعيل TPM واستخدام نفس حساب Windows. لم يتم تخفيض الحماية تلقائياً.');}
  }
  async state() {
    try{return JSON.parse(this.vault.decrypt(await fs.readFile(this.file)));}
    catch(e){if(e.code==='ENOENT')return null;throw new Error('تعذر قراءة تفعيل الجهاز. شغّل المثبّت لاستعادة التفعيل؛ بيانات المرضى لم تتغير.');}
  }
  async save(state) {
    await fs.mkdir(this.userData,{recursive:true});
    const temp=this.file+'.'+crypto.randomUUID()+'.tmp';
    try{await fs.writeFile(temp,this.vault.encrypt(JSON.stringify(state)),{mode:0o600,flag:'wx'});await fs.rename(temp,this.file);}
    finally{await fs.rm(temp,{force:true});}
  }
  async activate(token) {
    const claims=decodeLicense(token,this.config.publicKey);
    const device=await this.device(claims.binding);
    const old=await this.state();
    this.claims=verifyLicense(token,this.config,device,{lastSeen:old?.lastSeen||0});
    await this.save({token:token.trim(),lastSeen:Math.max(Date.now(),old?.lastSeen||0)});
    this.token=token;return this.claims;
  }
  async check() {
    const state=await this.state();if(!state)throw new Error('هذه النسخة غير مفعّلة. شغّل ملف التنصيب لإكمال التفعيل على هذه الحاسبة.');
    const claims=decodeLicense(state.token,this.config.publicKey);
    const device=await this.device(claims.binding);
    this.claims=verifyLicense(state.token,this.config,device,{lastSeen:state.lastSeen});
    await this.save({token:state.token,lastSeen:Math.max(Date.now(),state.lastSeen||0)});
    this.token=state.token;return this.claims;
  }
  assertCurrent() {
    if(!this.claims)throw new Error('الترخيص غير مفعّل.');
    verifyLicense(this.token,this.config,this.claims,{lastSeen:this.lastTime||0});
    this.lastTime=Math.max(this.lastTime||0,Date.now());
  }
}
module.exports={LicenseManager};
