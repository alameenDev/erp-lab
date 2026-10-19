const fs=require('node:fs');const path=require('node:path');
const root=path.resolve(__dirname,'../..'), bundle=path.join(root,'desktop/bundle');
fs.mkdirSync(bundle,{recursive:true});
const backend=path.join(bundle,'backend');fs.rmSync(backend,{recursive:true,force:true});fs.mkdirSync(backend);
for(const dir of ['app','bootstrap','config','database','public','resources','routes','vendor']) {
  fs.cpSync(path.join(root,'backend',dir),path.join(backend,dir),{recursive:true,filter:source=>!/(?:^|[\\/])(?:\.env(?:\..*)?|\.git|node_modules|storage|database\.sqlite)(?:[\\/]|$)/.test(source)});
}
// Composer discovery cache may contain absolute build-runner paths. Generate
// these in the writable per-user cache when Laravel boots on the workstation.
fs.rmSync(path.join(backend,'bootstrap/cache'),{recursive:true,force:true});fs.mkdirSync(path.join(backend,'bootstrap/cache'));
for(const file of ['composer.json','composer.lock','artisan'])fs.copyFileSync(path.join(root,'backend',file),path.join(backend,file));
fs.rmSync(path.join(bundle,'frontend'),{recursive:true,force:true});fs.cpSync(path.join(root,'frontend/dist'),path.join(bundle,'frontend'),{recursive:true});
fs.cpSync(path.join(root,'desktop/node_modules/leaflet/dist'),path.join(bundle,'frontend/desktop-vendor/leaflet'),{recursive:true});
let html=fs.readFileSync(path.join(bundle,'frontend/index.html'),'utf8');
html=html.replace(/<script[^>]*type="application\/ld\+json"[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<link[^>]*(?:canonical|alternate|preconnect|https:\/\/fonts)[^>]*>/gi,'').replace(/<meta[^>]*(?:og:|twitter:)[^>]*>/gi,'');
fs.writeFileSync(path.join(bundle,'frontend/index.html'),html);
for(const file of ['sitemap.xml','robots.txt'])fs.rmSync(path.join(bundle,'frontend',file),{force:true});
fs.cpSync(path.join(root,'desktop/php'),path.join(bundle,'desktop-php'),{recursive:true});
fs.mkdirSync(path.join(bundle,'print'),{recursive:true});
for(const file of ['printing.cjs','print-preload.cjs','validation.cjs','state.cjs','smoke.cjs'])fs.copyFileSync(path.join(root,'print-helper/src',file),path.join(bundle,'print',file));
fs.copyFileSync(path.join(root,'desktop/README_AR.md'),path.join(bundle,'README_AR.txt'));
fs.writeFileSync(path.join(bundle,'build.json'),JSON.stringify({version:'1.0.0',source:process.env.GITHUB_SHA||'local',php:'8.4.26',mariadb:'10.11.19',origin:'http://127.0.0.1:17892'},null,2));
console.log('Staged complete application, independent backend, frontend and native printing.');
