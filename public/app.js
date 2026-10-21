document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
const c=document.getElementById('pad');
if(c){const x=c.getContext('2d');let d=false,drawn=false;
 const fit=()=>{const r=c.getBoundingClientRect(),s=window.devicePixelRatio||1;c.width=r.width*s;c.height=r.height*s;x.scale(s,s);x.lineWidth=2.5;x.lineCap='round';x.strokeStyle='#0f172a';drawn=false};fit();
 const p=e=>{const r=c.getBoundingClientRect();return[e.clientX-r.left,e.clientY-r.top]};
 c.addEventListener('pointerdown',e=>{d=true;c.setPointerCapture(e.pointerId);x.beginPath();x.moveTo(...p(e))});
 c.addEventListener('pointermove',e=>{if(!d)return;x.lineTo(...p(e));x.stroke();drawn=true});
 ['pointerup','pointercancel'].forEach(t=>c.addEventListener(t,()=>d=false));
 document.getElementById('clear').onclick=()=>{x.clearRect(0,0,c.width,c.height)
 ;drawn=false};
 document.getElementById('signform').addEventListener('submit',e=>{if(!drawn){e.preventDefault();alert('Vă rugăm să desenați semnătura în chenar.');return}document.getElementById('sig').value=c.toDataURL('image/png')});
}
(function(){const box=document.getElementById('chat');if(!box)return;let last=0;
 const esc=s=>s.replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const load=()=>fetch(box.dataset.feed,{credentials:'same-origin'}).then(r=>r.json()).then(M=>{const n=M.length?M[M.length-1].id:0;if(n===last)return;last=n;
  box.innerHTML=M.map(m=>`<div class="m${m.me?' me':''}"><small>${esc(m.a)} · ${esc(m.d)}</small>${esc(m.t)}</div>`).join('')||'<p class="muted">Niciun mesaj încă.</p>';box.scrollTop=box.scrollHeight}).catch(()=>{});
 load();setInterval(load,4000);
 const f=document.getElementById('chatform');f.addEventListener('submit',e=>{e.preventDefault();const d=new FormData(f);d.append('ajax','1');fetch(f.action,{method:'POST',body:d,credentials:'same-origin'}).then(()=>{f.text.value='';load()})});})();
(function(){const t=document.querySelector('.timer');if(!t)return;let s=+t.dataset.min*60;const sp=t.querySelector('span'),f=document.getElementById('testform');
 const tick=()=>{sp.textContent=Math.floor(s/60)+':'+String(s%60).padStart(2,'0')+' rămase';if(s--<=0){f.querySelectorAll('[required]').forEach(x=>x.required=false);f.submit();}};tick();setInterval(tick,1000);})();
