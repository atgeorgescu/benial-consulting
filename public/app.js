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
document.querySelectorAll('.upbox').forEach(b=>b.querySelectorAll('input[type=file]').forEach(i=>i.addEventListener('change',()=>{const n=[...b.querySelectorAll('input[type=file]')].flatMap(x=>[...x.files].map(f=>f.name+' ('+(f.size/1048576).toFixed(1)+' MB)'+(f.size>20971520?' – PREA MARE':'')));b.querySelector('.upnames').textContent=n.join(', ')})));
/* ANAF: completare automată după CUI (formular firmă) */
(function(){const f=document.querySelector('form[action*="e=firme"]');if(!f)return;const cui=f.querySelector('[name=cui]');if(!cui)return;
 const b=document.createElement('button');b.type='button';b.className='btn sm ghost';b.textContent='🔎 Completează din ANAF';const st=document.createElement('small');st.className='muted';
 cui.insertAdjacentElement('afterend',st);cui.insertAdjacentElement('afterend',b);
 const set=(n,v)=>{const el=f.querySelector('[name="'+n+'"]');if(el&&v!==undefined&&v!==null&&String(v)!==''){el.value=v;el.dispatchEvent(new Event('change'))}};
 const go=()=>{const v=cui.value.replace(/\D/g,'');if(v.length<2){st.textContent='Introduceți CUI-ul.';return}st.textContent='Se caută la ANAF…';b.disabled=true;
  fetch('?r=anaf&cui='+v,{credentials:'same-origin'}).then(r=>r.json()).then(d=>{b.disabled=false;if(!d.ok){st.textContent='⚠️ '+d.error;return}
   ['denumire','cui','reg_com','caen','adresa','telefon','localitate','judet','tva','iban'].forEach(k=>set(k,d[k]));st.textContent='✔ Date preluate din ANAF'+(d.inactiv?' – ATENȚIE: contribuabil INACTIV':'')+(d.stare?' ('+d.stare+')':'');}).catch(()=>{b.disabled=false;st.textContent='⚠️ Eroare de conexiune.'})};
 b.addEventListener('click',go);cui.addEventListener('blur',()=>{const n=f.querySelector('[name=denumire]');if(n&&!n.value&&cui.value.replace(/\D/g,'').length>=2)go()});})();
/* Asistent AI */
(function(){const fab=document.getElementById('ai-fab'),p=document.getElementById('ai-panel');if(!fab||!p)return;const log=document.getElementById('ai-log'),inp=document.getElementById('ai-in'),q=document.getElementById('ai-quota');let H=[];
 fab.onclick=()=>{p.hidden=!p.hidden;if(!p.hidden)inp.focus()};document.getElementById('ai-close').onclick=()=>p.hidden=true;
 const esc=s=>s.replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
 const md=s=>esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>');
 const add=(cls,html)=>{const d=document.createElement('div');d.className='ai-m '+cls;d.innerHTML=html;log.appendChild(d);log.scrollTop=log.scrollHeight;return d};
 const form=()=>{const f=document.querySelector('main form.form');if(!f)return[];return[...f.querySelectorAll('input[name],select[name],textarea[name]')].filter(e=>!['hidden','password','file','checkbox'].includes(e.type)&&e.name!=='_csrf').map(e=>({name:e.name,label:(e.closest('label')?.childNodes[0]?.textContent||'').trim(),value:e.value,options:e.tagName==='SELECT'?[...e.options].filter(o=>o.value).map(o=>o.value+'='+o.text):undefined}))};
 document.getElementById('ai-form').addEventListener('submit',e=>{e.preventDefault();const t=inp.value.trim();if(!t)return;inp.value='';add('me',esc(t));H.push({role:'user',content:t});const w=add('bot','…');
  fetch(p.dataset.url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:p.dataset.csrf,messages:H,form:form(),page:document.querySelector('main h1')?.textContent||''})}).then(r=>r.json()).then(d=>{
   if(!d.ok){w.innerHTML='⚠️ '+esc(d.error||'Eroare');H.pop();return}H.push({role:'assistant',content:d.reply});w.innerHTML=md(d.reply);if(d.limit)q.textContent=d.used+'/'+d.limit+' azi';
   if(d.fill&&Object.keys(d.fill).length){const b=document.createElement('button');b.className='btn sm';b.textContent='Aplică în formular ('+Object.keys(d.fill).length+' câmpuri)';b.onclick=()=>{const f=document.querySelector('main form.form');Object.entries(d.fill).forEach(([k,v])=>{const el=f.querySelector('[name="'+k+'"]');if(el){if(el.tagName==='SELECT'){const o=[...el.options].find(o=>o.value==v||o.text==v);if(o)el.value=o.value}else el.value=v;el.classList.add('ai-filled')}});b.disabled=true;b.textContent='✔ Aplicat – verificați înainte de salvare'};w.appendChild(document.createElement('br'));w.appendChild(b)}
  }).catch(()=>{w.textContent='⚠️ Eroare de conexiune.';H.pop()})});
 inp.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('ai-form').requestSubmit()}});})();
