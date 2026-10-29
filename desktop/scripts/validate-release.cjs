'use strict';
const fs=require('node:fs');const path=require('node:path');const crypto=require('node:crypto');
module.exports=async context=>{
  const root=context.appDir;const config=JSON.parse(fs.readFileSync(path.join(root,'src/license-config.json'),'utf8'));
  if(!['production','qa'].includes(config.channel)||crypto.createPublicKey(config.publicKey).asymmetricKeyType!=='ed25519')throw new Error('Configure the issuer public key before building');
  if(crypto.createHash('sha256').update(fs.readFileSync(path.join(root,'licensing/DigitalLab.DeviceIdentity.exe'))).digest('hex')!==config.deviceHelperSha256)throw new Error('Device helper changed; configure the release again');
  const {inventory}=require('../src/integrity.cjs');
  const manifest={};await inventory(root,'bundle',manifest);await inventory(root,'licensing',manifest);
  fs.writeFileSync(path.join(root,'src/integrity-manifest.json'),JSON.stringify(manifest));
  // Fail closed if keys, requests or private signing material entered any distributed path.
  for(const dir of ['src','assets','licensing','bundle']) {
    function scan(folder){for(const entry of fs.readdirSync(folder,{withFileTypes:true})){const file=path.join(folder,entry.name);if(entry.isDirectory())scan(file);else {
      if(/\.(?:dllicense|dlrequest|pfx|key|p12)$/i.test(file))throw new Error('Private activation material in distribution: '+file);
      if(/\.(?:pem|json|cjs|js|txt|env)$/i.test(file)&&fs.statSync(file).size<1024*1024&&/-----BEGIN (?:ENCRYPTED |RSA |EC )?PRIVATE KEY-----/.test(fs.readFileSync(file,'utf8')))throw new Error('Private key must never be distributed: '+file);
    }}}
    scan(path.join(root,dir));
  }
};
