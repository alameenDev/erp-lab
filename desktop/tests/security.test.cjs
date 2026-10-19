const test=require('node:test');const assert=require('node:assert/strict');
const fs=require('node:fs/promises');const path=require('node:path');const os=require('node:os');const crypto=require('node:crypto');
const {validPath,createGateway}=require('../src/gateway.cjs');
const {Runtime}=require('../src/runtime.cjs');const {paths}=require('../src/constants.cjs');
const {writeBackup,prepareRestore,safeEntry}=require('../src/backup.cjs');
test('gateway denies other origins, unauthenticated pages and traversal; bridge still requires backend token',async()=>{
  for(const input of ['/../secrets.dat','/%2e%2e/secrets.dat','/%252e%252e/secrets.dat','/a\\b','/.env','/%00'])assert.equal(validPath(input),false,input);
  assert.equal(validPath('/api/patients?search=abc'),true);
  const gateway=createGateway({},'a'.repeat(64),17993);await new Promise(r=>gateway.server.listen(17993,'127.0.0.1',r));
  try{
    for(const [url,headers] of [['/',{}],['/api/patients',{}],['/api/patients',{'X-Digital-Lab-Desktop':'a'.repeat(64),Origin:'https://foreign.test'}],['/',{Host:'evil.test'}]])assert.equal((await fetch('http://127.0.0.1:17993'+url,{headers})).status,403);
  }finally{await gateway.close();}
});
test('desktop environment cannot inherit hosted database or cloud credentials',()=>{
  process.env.DB_URL='mysql://not-local';process.env.AWS_ACCESS_KEY_ID='test-only';
  const runtime=new Runtime(paths('/bundle','/local'),{});runtime.secrets={appKey:'local',dbPassword:'local'};runtime.dbPort=32101;
  const env=runtime.environment();assert.equal(env.DB_URL,undefined);assert.equal(env.AWS_ACCESS_KEY_ID,undefined);assert.equal(env.DB_DATABASE,'digital_lab_desktop');assert.equal(env.DB_HOST,'127.0.0.1');assert.equal(env.APP_URL,'http://127.0.0.1:17892');assert.equal(env.MAIL_MAILER,'array');
  delete process.env.DB_URL;delete process.env.AWS_ACCESS_KEY_ID;
});
test('CGI gateway queues concurrent application requests and drains before backups',async()=>{
  const root=await fs.mkdtemp(path.join(os.tmpdir(),'dld-cgi-'));
  const script=path.join(root,'cgi.cjs');
  await fs.writeFile(script,"process.stdin.resume();process.stdin.on('end',()=>setTimeout(()=>{process.stdout.write('Status: 200 OK\\r\\nContent-Type: application/json\\r\\n\\r\\n'+JSON.stringify({uri:process.env.REQUEST_URI}));},80));");
  const runtime={p:{bundle:root,backend:root,cgi:process.execPath},env:process.env,phpArgs:()=>[script]};
  const gateway=createGateway(runtime,'b'.repeat(64),17994);await new Promise(r=>gateway.server.listen(17994,'127.0.0.1',r));
  try{
    const responses=await Promise.all(Array.from({length:16},(_,i)=>fetch('http://127.0.0.1:17994/api/example?id='+i,{headers:{'X-Digital-Lab-Desktop':'b'.repeat(64)}})));
    assert.ok(responses.every(r=>r.status===200));
    assert.equal((await responses[12].json()).uri,'/api/example?id=12');
    await gateway.pause();assert.equal((await fetch('http://127.0.0.1:17994/api/example',{headers:{'X-Digital-Lab-Desktop':'b'.repeat(64)}})).status,503);
  }finally{await gateway.close();await fs.rm(root,{recursive:true,force:true});}
});
test('encrypted complete backup restores database, attachments and keys; wrong password and tamper fail before restore',async()=>{
  const root=await fs.mkdtemp(path.join(os.tmpdir(),'dld-backup-test-'));const data=path.join(root,'data');
  const vault={encrypt:s=>Buffer.from(s),decrypt:b=>b.toString()};
  const secrets={appKey:'base64:'+crypto.randomBytes(32).toString('base64'),dbPassword:'b'.repeat(64),rootPassword:'c'.repeat(64)};
  try{
    await fs.mkdir(path.join(data,'database/mysql'),{recursive:true});await fs.mkdir(path.join(data,'storage/app/public'),{recursive:true});
    await fs.writeFile(path.join(data,'database/mysql/system.ibd'),'synthetic database');await fs.writeFile(path.join(data,'storage/app/report.pdf'),'%PDF-1.4 synthetic');
    const destination=path.join(root,'good.dlbackup');await writeBackup({data,secrets,destination,password:'test-password-123'});
    const bytes=await fs.readFile(destination);assert.equal(bytes.includes(Buffer.from('synthetic database')),false);
    await assert.rejects(prepareRestore({source:destination,password:'wrong-password-123',stage:path.join(root,'wrong'),vault}));await assert.rejects(fs.stat(path.join(root,'wrong')));
    const stage=path.join(root,'restored');await prepareRestore({source:destination,password:'test-password-123',stage,vault});
    assert.equal(await fs.readFile(path.join(stage,'database/mysql/system.ibd'),'utf8'),'synthetic database');assert.equal(await fs.readFile(path.join(stage,'storage/app/report.pdf'),'utf8'),'%PDF-1.4 synthetic');assert.deepEqual(JSON.parse(await fs.readFile(path.join(stage,'secrets.dat'),'utf8')),secrets);
    bytes[70]^=1;const bad=path.join(root,'bad.dlbackup');await fs.writeFile(bad,bytes);await assert.rejects(prepareRestore({source:bad,password:'test-password-123',stage:path.join(root,'bad'),vault}));
    await assert.rejects(writeBackup({data,secrets,destination,password:'test-password-123'}));
  }finally{await fs.rm(root,{recursive:true,force:true});}
});
test('archives cannot address files outside data or embed executable app resources',()=>{
  for(const name of ['../secrets','/database/x','database/../../x','database\\x','C:/x','src/main.cjs','storage/app/../x','database/x\0'])assert.equal(safeEntry(name),false);
  for(const name of ['database/mysql/a.ibd','storage/app/attachments/file.pdf','secrets.json'])assert.equal(safeEntry(name),true);
});
