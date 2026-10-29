const fs=require('node:fs');const fsp=fs.promises;const path=require('node:path');const crypto=require('node:crypto');
async function hashFile(file){const hash=crypto.createHash('sha256');for await(const chunk of fs.createReadStream(file))hash.update(chunk);return hash.digest('hex');}
async function inventory(root,relative,files={}){
  for(const entry of await fsp.readdir(path.join(root,relative),{withFileTypes:true})){
    const name=relative+'/'+entry.name;
    if(entry.isSymbolicLink())throw new Error('Unexpected symbolic link in application resources');
    if(entry.isDirectory())await inventory(root,name,files);
    else if(entry.isFile())files[name]=await hashFile(path.join(root,name));
  }
  return files;
}
async function verifyIntegrity(root,expected){
  const actual={};await inventory(root,'bundle',actual);await inventory(root,'licensing',actual);
  const keys=Object.keys(actual);
  if(keys.length!==Object.keys(expected).length||keys.some(k=>actual[k]!==expected[k]))throw new Error('ملفات البرنامج تغيرت أو تلفت. أعد تنصيب النسخة الأصلية. بيانات المختبر محفوظة.');
}
module.exports={hashFile,inventory,verifyIntegrity};
