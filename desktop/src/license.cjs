'use strict';
const crypto = require('node:crypto');
const PRODUCT = 'digital-lab-desktop-v1';
const fail = message => { throw new Error(message); };
const digest = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
function verifyDevice(request, challenge) {
  if (!request || !['tpm','software'].includes(request.binding) || typeof request.spki !== 'string' || request.spki.length > 4096) fail('طلب تفعيل الجهاز غير صالح.');
  const spki = Buffer.from(request.spki,'base64');
  const key = crypto.createPublicKey({key:spki,type:'spki',format:'der'});
  if (key.asymmetricKeyType !== 'rsa' || key.asymmetricKeyDetails.modulusLength < 2048 || request.deviceId !== digest(spki) ||
      request.challenge !== challenge || Buffer.from(challenge,'base64').length !== 32 ||
      !crypto.verify('sha256',Buffer.from(challenge,'base64'),key,Buffer.from(request.proof || '', 'base64'))) fail('تعذر إثبات ارتباط الترخيص بهذه الحاسبة.');
  return request;
}
function decodeLicense(token, publicKey) {
  if (typeof token !== 'string' || token.length > 32768) fail('ملف الترخيص غير صالح.');
  const parts = token.trim().split('.');
  if(parts.length !== 2 || parts.some(p=>!p || !/^[A-Za-z0-9_-]+$/.test(p))) fail('صيغة الترخيص غير صالحة.');
  const bytes = Buffer.from(parts[0],'base64url');
  const key = crypto.createPublicKey(publicKey);
  if(key.asymmetricKeyType !== 'ed25519' || !crypto.verify(null,bytes,key,Buffer.from(parts[1],'base64url'))) fail('توقيع الترخيص غير صحيح.');
  const c = JSON.parse(bytes.toString('utf8'));
  if(c.version !== 1 || c.product !== PRODUCT || !['production','qa'].includes(c.channel) ||
     !['annual','lifetime'].includes(c.type) || !['tpm','software'].includes(c.binding) ||
     !/^[a-f0-9]{64}$/.test(c.deviceId) || typeof c.lab !== 'string' || !c.lab.trim() || c.lab.length>255 ||
     typeof c.id !== 'string' || !/^[a-zA-Z0-9-]{8,100}$/.test(c.id) ||
     !Number.isSafeInteger(c.issuedAt) || c.issuedAt<0 ||
     (c.type==='lifetime' ? c.expiresAt!==null : !Number.isSafeInteger(c.expiresAt) || c.expiresAt<=c.issuedAt)) fail('بيانات الترخيص غير صالحة.');
  return c;
}
function verifyLicense(token, config, device, {now=Date.now(),lastSeen=0}={}) {
  const c=decodeLicense(token,config.publicKey);
  if(c.channel!==config.channel || c.deviceId!==device.deviceId || c.binding!==device.binding) fail('هذا الترخيص مخصص لحاسبة أخرى أو لإصدار مختلف.');
  if(c.binding==='software' && !config.allowSoftwareBinding) fail('هذه النسخة تتطلب حماية TPM.');
  if(!Number.isFinite(now) || !Number.isFinite(lastSeen) || now+300000 < lastSeen || now+300000 < c.issuedAt) fail('وقت الحاسبة غير صحيح. صحح التاريخ والوقت ثم حاول ثانية.');
  if(c.type==='annual' && now>=c.expiresAt) fail('انتهى الترخيص السنوي. استورد ملف التجديد من المثبّت. بيانات المختبر محفوظة.');
  return c;
}
function signLicense(claims, privateKey) {
  const bytes=Buffer.from(JSON.stringify(claims));
  return bytes.toString('base64url')+'.'+crypto.sign(null,bytes,privateKey).toString('base64url');
}
module.exports={PRODUCT,digest,verifyDevice,decodeLicense,verifyLicense,signLicense};
