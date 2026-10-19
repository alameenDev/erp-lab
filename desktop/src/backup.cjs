const fs = require('node:fs');
const fsp = fs.promises;
const path = require('node:path');
const crypto = require('node:crypto');
const {promisify} = require('node:util');
const {pipeline} = require('node:stream/promises');
const yazl = require('yazl');
const yauzl = require('yauzl');
const scrypt = promisify(crypto.scrypt);
const MAGIC = Buffer.from('DLBACK01');
const DB_VERSION = '10.11.19';
function passwordCheck(password) {if (typeof password !== 'string' || password.length < 12 || password.length > 256) throw new Error('كلمة سر النسخة الاحتياطية يجب أن تكون 12 حرفاً على الأقل.');}
async function addTree(zip, root, prefix) {
  for (const entry of await fsp.readdir(root, {withFileTypes:true})) {
    if (entry.isSymbolicLink()) throw new Error('لا يمكن نسخ ارتباط رمزي.');
    const file = path.join(root,entry.name), name = `${prefix}/${entry.name}`;
    if (entry.isDirectory()) await addTree(zip,file,name);
    else if (entry.isFile()) zip.addFile(file,name);
  }
}
async function writeBackup({data, secrets, destination, password}) {
  passwordCheck(password);
  const salt = crypto.randomBytes(32), iv = crypto.randomBytes(12);
  const key = await scrypt(password,salt,32), cipher = crypto.createCipheriv('aes-256-gcm',key,iv);
  const temporary = destination + '.' + crypto.randomUUID() + '.partial';
  const zip = new yazl.ZipFile();
  try {
    await fsp.writeFile(temporary,Buffer.concat([MAGIC,salt,iv]),{flag:'wx',mode:0o600});
    const writing = pipeline(zip.outputStream,cipher,fs.createWriteStream(temporary,{flags:'a'}));
    await addTree(zip,path.join(data,'database'),'database');
    await addTree(zip,path.join(data,'storage/app'),'storage/app');
    zip.addBuffer(Buffer.from(JSON.stringify({format:1,app:'Digital Lab Desktop',version:'1.0.0',database:DB_VERSION,created:new Date().toISOString()})),'manifest.json');
    zip.addBuffer(Buffer.from(JSON.stringify(secrets)),'secrets.json');
    zip.end();await writing;await fsp.appendFile(temporary,cipher.getAuthTag());
    // Never overwrite a backup silently, even when called outside the UI.
    await fsp.copyFile(temporary,destination,fs.constants.COPYFILE_EXCL);await fsp.rm(temporary);
  } catch (e) {zip.end();await fsp.rm(temporary,{force:true});throw e;} finally {key.fill(0);}
}
function safeEntry(name) {
  return typeof name === 'string' && !name.includes('\\') && !name.includes(':') && !/[\x00-\x1f]/.test(name)
    && !name.startsWith('/') && !name.split('/').some(p=>p==='..'||p==='.')
    && (['manifest.json','secrets.json'].includes(name) || name.startsWith('database/') || name.startsWith('storage/app/'));
}
async function extract(zipPath, stage) {
  const zip = await new Promise((resolve,reject)=>yauzl.open(zipPath,{lazyEntries:true},(e,z)=>e?reject(e):resolve(z)));
  return new Promise((resolve,reject)=>{
    let total = 0,count = 0;const seen = new Set();
    const stop = e=>{zip.close();reject(e);};zip.on('error',stop);zip.on('end',resolve);
    zip.on('entry',async entry=>{
      try {
        const mode = (entry.externalFileAttributes>>>16)&0o170000;
        if (!safeEntry(entry.fileName)||mode===0o120000||seen.has(entry.fileName.toLowerCase())||++count>100000||(total+=entry.uncompressedSize)>20*1024**3) throw new Error('ملف النسخة الاحتياطية غير صالح.');
        seen.add(entry.fileName.toLowerCase());const target = path.join(stage,entry.fileName);
        if (entry.fileName.endsWith('/')) await fsp.mkdir(target,{recursive:true});
        else {await fsp.mkdir(path.dirname(target),{recursive:true});const stream = await new Promise((r,j)=>zip.openReadStream(entry,(e,s)=>e?j(e):r(s)));await pipeline(stream,fs.createWriteStream(target,{flags:'wx',mode:0o600}));}
        zip.readEntry();
      } catch(e){stop(e);}
    });zip.readEntry();
  });
}
async function prepareRestore({source,password,stage,vault}) {
  passwordCheck(password);
  const temporary = stage+'.zip';
  await fsp.mkdir(stage,{recursive:false});
  let key;
  try {
    const file = await fsp.open(source,'r'), size = (await file.stat()).size;
    const header = Buffer.alloc(52), tag = Buffer.alloc(16);
    try {await file.read(header,0,52,0);await file.read(tag,0,16,size-16);} finally {await file.close();}
    if (size<70 || !header.subarray(0,8).equals(MAGIC)) throw new Error('صيغة النسخة غير صحيحة.');
    key = await scrypt(password,header.subarray(8,40),32);
    const decipher = crypto.createDecipheriv('aes-256-gcm',key,header.subarray(40,52));decipher.setAuthTag(tag);
    await pipeline(fs.createReadStream(source,{start:52,end:size-17}),decipher,fs.createWriteStream(temporary,{flags:'wx',mode:0o600}));
    // Authentication completes before archive contents are used or live data touched.
    await extract(temporary,stage);
    const manifest = JSON.parse(await fsp.readFile(path.join(stage,'manifest.json'),'utf8'));
    if (manifest.format!==1 || manifest.app!=='Digital Lab Desktop' || manifest.database!==DB_VERSION || !fs.existsSync(path.join(stage,'database/mysql'))) throw new Error('نسخة غير متوافقة مع هذا الإصدار.');
    const secrets = JSON.parse(await fsp.readFile(path.join(stage,'secrets.json'),'utf8'));
    if (!/^base64:[A-Za-z0-9+/]{43}=$/.test(secrets.appKey||'') || ![secrets.dbPassword,secrets.rootPassword].every(v=>/^[a-f0-9]{64}$/.test(v||''))) throw new Error('مفاتيح النسخة غير صالحة.');
    await fsp.writeFile(path.join(stage,'secrets.dat'),vault.encrypt(JSON.stringify(secrets)),{mode:0o600});
    await fsp.rm(path.join(stage,'secrets.json'));await fsp.rm(path.join(stage,'manifest.json'));
    await fsp.mkdir(path.join(stage,'storage/app/public'),{recursive:true});return manifest;
  } catch(e) {await fsp.rm(stage,{recursive:true,force:true});throw new Error('تعذر فتح النسخة: تحقق من كلمة السر وسلامة الملف وتوافق الإصدار.');}
  finally {key?.fill(0);await fsp.rm(temporary,{force:true});}
}
module.exports = {writeBackup, prepareRestore, safeEntry, DB_VERSION};
