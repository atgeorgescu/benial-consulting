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
