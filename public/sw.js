const C='benial-v3', ASSETS=['style.css','app.js','pwa.js','icons/icon-192.png','icons/icon-512.png','offline.html'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(C).then(c=>c.addAll(ASSETS)));self.skipWaiting();});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==C).map(x=>caches.delete(x)))));self.clients.claim();});
self.addEventListener('fetch',e=>{const r=e.request;if(r.method!=='GET')return;
  const u=new URL(r.url); if(u.origin!==location.origin)return;
  if(r.mode==='navigate'){e.respondWith(fetch(r).catch(()=>caches.match('offline.html')));return;}
  if(/\.(css|js|png|svg|webmanifest)$/.test(u.pathname)) e.respondWith(caches.match(r).then(m=>m||fetch(r)));
});
