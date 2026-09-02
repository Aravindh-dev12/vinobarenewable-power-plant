<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title id="pageTitle">Solar Plant - HT / VCB</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="sidebar-control.js?v=4" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
<div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden"></div>
<div id="sidebar-container"></div>
<main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
<header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
<div class="flex items-center gap-3"><button id="menuBtn" class="md:hidden text-emerald-600 text-2xl">&#9776;</button><div><h2 class="text-xl font-black text-slate-800 tracking-tight">HT / VCB Panel</h2><p class="text-xs text-slate-500">Optional SCADA telemetry</p></div></div>
<div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100"><div id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></div><span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline">--:--:--</span></div>
</header>
<div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
<div id="noData" class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 text-sm font-semibold">HT / VCB data is not available yet for this SCADA plant. Values will appear automatically when the device starts publishing.</div>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
<div class="bg-white rounded-xl border p-5"><p class="text-[10px] font-black text-slate-400 uppercase">VCB Status</p><p id="vcb_status" class="text-3xl font-black text-slate-500 mt-2">No data</p></div>
<div class="bg-white rounded-xl border p-5"><p class="text-[10px] font-black text-slate-400 uppercase">3-Phase Active Power</p><p id="vcb_load" class="text-3xl font-black mt-2">-- <span class="text-sm text-blue-600">kW</span></p></div>
<div class="bg-white rounded-xl border p-5"><p class="text-[10px] font-black text-slate-400 uppercase">Today Energy</p><p id="vcb_today" class="text-3xl font-black mt-2">-- <span class="text-sm text-purple-600">kWh</span></p></div>
<div class="bg-white rounded-xl border p-5"><p class="text-[10px] font-black text-slate-400 uppercase">Frequency</p><p id="vcb_freq" class="text-3xl font-black mt-2">-- <span class="text-sm text-orange-600">Hz</span></p></div>
</div>
<div class="bg-white rounded-xl border p-5"><h3 class="text-sm font-black text-slate-600 uppercase mb-4">Voltages</h3><div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" id="voltageGrid"></div></div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
<div class="bg-white rounded-xl border p-5"><h3 class="text-sm font-black text-slate-600 uppercase mb-4">Currents & Active Power</h3><div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="powerGrid"></div></div>
<div class="bg-white rounded-xl border p-5"><h3 class="text-sm font-black text-slate-600 uppercase mb-4">Power Factor & THD</h3><div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="qualityGrid"></div></div>
</div>
<div class="bg-white rounded-xl border p-5"><h3 class="text-sm font-black text-slate-600 uppercase mb-4">Energy & Reactive Power</h3><div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="energyGrid"></div></div>
</div>
</main></div>
<script>
const params=new URLSearchParams(location.search);
const currentPlant=params.get('plant')||'vinoba-1';
const authToken=params.get('token')||sessionStorage.getItem('vs_token')||'';
const plantNames={'vinoba-1':'Vinoba Renewable Energy Private Limited','ssv':'SSV Green Power Private Limited'};
document.getElementById('pageTitle').textContent=(plantNames[currentPlant]||currentPlant)+' - HT / VCB';
setInterval(()=>document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false}),1000);

const fields={
 voltageGrid:[['v_r','R Phase-N','R Phase-N Voltage','V',1],['v_y','Y Phase-N','Y Phase-N Voltage','V',1],['v_b','B Phase-N','B Phase-N Voltage','V',1],['v_ry','V12 (RY)','V12 (RY)','V',1],['v_yb','V23 (YB)','V23 (YB)','V',1],['v_br','V31 (BR)','V31 (BR)','V',1]],
 powerGrid:[['i_r','L1 (R)','L1 (R)','A',2],['i_y','L2 (Y)','L2 (Y)','A',2],['i_b','L3 (B)','L3 (B)','A',2],['p_r','Active Power R','Active Power R','kW',2],['p_y','Active Power Y','Active Power Y','kW',2],['p_b','Active Power B','Active Power B','kW',2]],
 qualityGrid:[['pf_q1','Q1 PF','Q1 PF','',3],['pf_q2','Q2 PF','Q2 PF','',3],['pf_q3','Q3 PF','Q3 PF','',3],['vthd_r','Voltage THD R','Voltage THD R','%',2],['vthd_y','Voltage THD Y','Voltage THD Y','%',2],['vthd_b','Voltage THD B','Voltage THD B','%',2]],
 energyGrid:[['act_exp','Active Total Export','Active Total Export','kWh',2],['act_imp','Active Total Import','Active Total Import','kWh',2],['react_imp','Reactive Import','Reactive Import (Q1+Q2)','kVAR',2],['react_exp','Reactive Export','Reactive Export (Q3+Q4)','kVAR',2]]
};
Object.entries(fields).forEach(([container,list])=>{document.getElementById(container).innerHTML=list.map(([id,label,,unit])=>`<div class="bg-slate-50 border rounded-lg p-3 text-center"><p class="text-[10px] font-bold text-slate-400 uppercase">${label}</p><p id="${id}" class="mt-2 text-xl font-black">--${unit?' <span class="text-xs text-slate-500">'+unit+'</span>':''}</p></div>`).join('');});
function paint(id,value,unit='',digits=2){const el=document.getElementById(id);if(!el)return;const n=Number(value);el.innerHTML=(Number.isFinite(n)?n.toFixed(digits):'--')+(unit?` <span class="text-xs text-slate-500">${unit}</span>`:'');}
function paintDetail(values){Object.values(fields).flat().forEach(([id,,key,unit,digits])=>paint(id,values[key],unit,digits));}
function isVcbMessage(d){const task=String(d.task||'').toLowerCase(),dev=String(d.device||'').toLowerCase(),v=d.values||{};return task==='vcb'||dev.includes('vcb')||(v['3 Phase Active Power']!==undefined&&(v['R Phase-N Voltage']!==undefined||v['Active Total Export']!==undefined));}
function connect(){const ws=new WebSocket('wss://vinobasolar.scadahub.in:5001');ws.onopen=()=>{ws.send(JSON.stringify({type:'subscribe',unit_id:currentPlant}));};ws.onmessage=e=>{try{const d=JSON.parse(e.data);if(d.unit_id!==currentPlant||!isVcbMessage(d)||!d.values)return;document.getElementById('noData').classList.add('hidden');document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';const v=d.values,p=Number(v['3 Phase Active Power'])||0;document.getElementById('vcb_status').textContent=p>0?'Online':'Standby';document.getElementById('vcb_status').className='text-3xl font-black mt-2 '+(p>0?'text-emerald-700':'text-slate-500');paint('vcb_load',p,'kW',2);paint('vcb_freq',v['Frequency (Hz)'],'Hz',2);let today=null;for(const [k,x] of Object.entries(d.virtualTags||{})){if(/vcb.*today|today.*energy/i.test(k)){today=typeof x==='object'?x.value:x;break;}}paint('vcb_today',today,'kWh',2);paintDetail(v);}catch(_){}};ws.onclose=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-red-500 rounded-full';setTimeout(connect,5000);};}
fetch('sidebar.html',{cache:'no-store'}).then(r=>r.text()).then(html=>{document.getElementById('sidebar-container').innerHTML=html;document.querySelectorAll('#sidebarNav a').forEach(a=>{let h=a.getAttribute('href');if(!h||h.includes('logout'))return;a.setAttribute('href',`${h}?plant=${encodeURIComponent(currentPlant)}&token=${encodeURIComponent(authToken)}`);});const pn=document.getElementById('sidebarPlantName');if(pn)pn.textContent=plantNames[currentPlant]||currentPlant;if(typeof initSidebar==='function')initSidebar();const o=document.getElementById('overlay'),s=document.getElementById('sidebar');document.getElementById('menuBtn')?.addEventListener('click',()=>{s?.classList.remove('-translate-x-full');o?.classList.remove('hidden');});document.getElementById('closeSidebarBtn')?.addEventListener('click',()=>{s?.classList.add('-translate-x-full');o?.classList.add('hidden');});o?.addEventListener('click',()=>{s?.classList.add('-translate-x-full');o.classList.add('hidden');});});
connect();
</script>
</body></html>
