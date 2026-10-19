const {ORIGIN}=require('./constants.cjs');
function allowedRequest({url,method='GET',resourceType}) {
  if(url.startsWith(ORIGIN+'/') || url.startsWith('blob:'+ORIGIN+'/') || url.startsWith('data:') || url==='about:blank')return true;
  // The original map can use public tiles while online. Patient data, API
  // requests and scripts are never sent to an external application backend.
  try{
    const u=new URL(url);
    return method==='GET' && resourceType==='image' && u.protocol==='https:' && !u.username && !u.password
      && ((/^(?:[abc]\.)?tile\.openstreetmap\.org$/.test(u.hostname) && /^\/\d+\/\d+\/\d+\.png$/.test(u.pathname))
        || (u.hostname==='placehold.co' && u.pathname==='/64x64'));
  }catch{return false;}
}
module.exports={allowedRequest};
