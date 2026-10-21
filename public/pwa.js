(function(){
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(function(){});
  var card=document.getElementById('pwa-install'); if(!card) return;
  var standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone;
  if(standalone) return;
  var btn=document.getElementById('pwa-btn'), ios=document.getElementById('pwa-ios'), help=document.getElementById('pwa-help'), ev=null;
  var isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
  card.hidden=false;
  window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();ev=e;});
  window.addEventListener('appinstalled',function(){card.hidden=true;});
  btn.addEventListener('click',function(){
    if(ev){ev.prompt();ev.userChoice.finally(function(){ev=null;});}
    else if(isIOS){ios.hidden=false;}
    else {help.hidden=false;}
  });
})();
