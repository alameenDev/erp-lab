#!/usr/bin/env node
'use strict';
// Owner workstation only. Never included by electron-builder's files allowlist.
const fs=require('node:fs');const crypto=require('node:crypto');const path=require('node:path');
const {PRODUCT,verifyDevice,signLicense,decodeLicense}=require('../src/license.cjs');
const [command,...args]=process.argv.slice(2);
const options={};for(let i=0;i<args.length;i+=2){if(!args[i].startsWith('--')||args[i+1]===undefined)throw new Error('Use --name value');options[args[i].slice(2)]=args[i+1];}
function required(name){if(!options[name])throw new Error('Missing --'+name);return options[name];}
function write(file,data){fs.mkdirSync(path.dirname(path.resolve(file)),{recursive:true});fs.writeFileSync(file,data,{flag:'wx',mode:0o600});}
try {
  if(command==='keygen') {
    const password=process.env.DL_LICENSE_KEY_PASSWORD;
    if(!password||password.length<16)throw new Error('Set DL_LICENSE_KEY_PASSWORD to at least 16 characters. Do not pass it on the command line.');
    const keys=crypto.generateKeyPairSync('ed25519');
    write(required('private'),keys.privateKey.export({format:'pem',type:'pkcs8',cipher:'aes-256-cbc',passphrase:password}));
    write(required('public'),keys.publicKey.export({format:'pem',type:'spki'}));
    console.log('Encrypted issuer key created. Back it up privately. Only the public key belongs in a build.');
  } else if(command==='issue') {
    const request=JSON.parse(fs.readFileSync(required('request'),'utf8'));
    verifyDevice(request,request.challenge);
    if(request.binding==='software'&&options['allow-software']!=='yes')throw new Error('Software binding requires explicit --allow-software yes after owner review.');
    const type=required('type');if(!['annual','lifetime'].includes(type))throw new Error('Type must be annual or lifetime');
    const issuedAt=Date.now();let expiresAt=null;
    if(type==='annual') {const expiry=new Date(issuedAt);expiry.setUTCFullYear(expiry.getUTCFullYear()+1);expiresAt=expiry.getTime();}
    const key=crypto.createPrivateKey({key:fs.readFileSync(required('private')),passphrase:process.env.DL_LICENSE_KEY_PASSWORD});
    if(key.asymmetricKeyType!=='ed25519')throw new Error('Expected Ed25519 issuer key');
    const claims={version:1,product:PRODUCT,channel:options.channel||'production',id:crypto.randomUUID(),lab:required('lab'),type,issuedAt,expiresAt,deviceId:request.deviceId,binding:request.binding};
    const token=signLicense(claims,key);decodeLicense(token,crypto.createPublicKey(key).export({format:'pem',type:'spki'}));
    write(required('output'),token+'\n');
    console.log(JSON.stringify({id:claims.id,lab:claims.lab,type,expiresAt,deviceId:claims.deviceId,binding:claims.binding}));
  } else throw new Error('Commands: keygen --private FILE --public FILE; issue --private FILE --request FILE --lab NAME --type annual|lifetime --output FILE');
} catch(e){console.error(e.message);process.exitCode=1;}
