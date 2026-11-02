// A separate lifetime owner shuts the database down even if the GUI crashes.
// It never discovers or stops any other MariaDB instance on the workstation.
const {spawn}=require('node:child_process');
const [server,config,admin,port,parent]=process.argv.slice(2);
if(!server||!config||!admin||!/^\d+$/.test(port||'')||!/^\d+$/.test(parent||''))process.exit(2);
let stopping=false;
const child=spawn(server,[`--defaults-file=${config}`,'--console'],{windowsHide:true,stdio:'ignore'});
child.on('error',()=>process.exit(1));
child.on('exit',code=>process.exit(code||0));
function shutdown(){
  if(stopping)return;stopping=true;
  const request=spawn(admin,['--no-defaults','--protocol=tcp','--host=127.0.0.1',`--port=${port}`,'--user=root','shutdown'],{windowsHide:true,stdio:'ignore'});
  request.on('error',()=>{});
  // If startup never completed or graceful shutdown is impossible, terminate
  // only our own child; InnoDB performs crash recovery at the next launch.
  setTimeout(()=>{child.kill();process.exit(1);},45000);
}
process.on('disconnect',shutdown);
process.on('SIGTERM',shutdown);
setInterval(()=>{try{process.kill(Number(parent),0);}catch(e){if(e.code==='ESRCH')shutdown();}},1000).unref();
