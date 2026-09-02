<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';
$token = trim((string)($_GET['token'] ?? ''));
$adminUser = null;
if ($token !== '') {
    $st = $conn->prepare('SELECT * FROM users WHERE auth_token=? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $token);
        $st->execute();
        $r = $st->get_result();
        if ($r && $r->num_rows) $adminUser = $r->fetch_assoc();
        $st->close();
    }
}
if (!$adminUser || ($adminUser['role'] ?? '') !== 'admin') { header('Location: index.php'); exit; }
$catalog = plant_catalog();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Solar Portfolio Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:#0f172a}
.surface{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}
.metric{font-variant-numeric:tabular-nums;letter-spacing:-.03em}.mini{font-size:.625rem;line-height:.875rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8}.dot{width:8px;height:8px;border-radius:999px;display:inline-block}.plant-card{transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.plant-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(15,23,42,.08);border-color:#cbd5e1}.scroll::-webkit-scrollbar{width:5px}.scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px}
</style>
</head>
<body class="min-h-screen">
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200">
  <div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-lg shadow-sm shrink-0"><i class="fa-solid fa-solar-panel"></i></div>
      <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-[.14em] text-emerald-700">SCADA Control Center</p><h1 class="text-lg sm:text-xl font-black text-slate-950 truncate">Solar Portfolio</h1></div>
    </div>
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <div class="hidden md:flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"><span id="wsDot" class="dot bg-slate-400"></span><div><p id="wsStatus" class="text-[10px] font-black uppercase tracking-wider text-slate-500">Connecting</p><p class="text-[9px] font-semibold text-slate-400">WebSocket portfolio status</p></div></div>
      <button onclick="openUsers()" class="h-10 px-3 sm:px-4 rounded-xl bg-slate-900 text-white text-xs font-black hover:bg-slate-800"><i class="fa-solid fa-user-plus sm:mr-2"></i><span class="hidden sm:inline">Add User</span></button>
      <button onclick="location.href='logout.php'" class="h-10 w-10 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" aria-label="Logout"><i class="fa-solid fa-right-from-bracket"></i></button>
    </div>
  </div>
</header>

<main class="max-w-[1500px] mx-auto p-4 sm:p-6 space-y-5">
  <section class="surface p-5 sm:p-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
      <div><p class="text-[10px] font-black uppercase tracking-[.14em] text-emerald-700">Portfolio Overview</p><h2 class="text-2xl sm:text-3xl font-black text-slate-950 mt-1">Renewable Power Operations</h2><p class="text-sm text-slate-500 mt-2 max-w-2xl">Real-time status for all configured plants. Open a plant to view inverter, HT/VCB, transformer, analytics and reports.</p></div>
      <div class="grid grid-cols-2 gap-3 lg:shrink-0">
        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 min-w-[135px]"><p class="mini">Plants</p><p class="text-2xl font-black text-slate-900 mt-1"><?php echo count($catalog); ?></p></div>
        <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 min-w-[160px]"><p class="mini text-emerald-600">Overall Output</p><p id="overall" class="metric text-2xl font-black text-emerald-700 mt-1">0.00 kW</p></div>
      </div>
    </div>
  </section>

  <section>
    <div class="flex items-center justify-between gap-3 mb-3 px-1"><div><h3 class="text-sm font-black text-slate-800">Plants</h3><p class="text-xs text-slate-500 mt-0.5">Live WebSocket first · database fallback when required</p></div><span id="portfolioLive" class="text-[10px] font-black rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">CONNECTING</span></div>
    <div id="cards" class="grid grid-cols-1 xl:grid-cols-2 gap-4"></div>
  </section>
</main>

<div id="userModal" class="fixed inset-0 hidden bg-slate-950/50 backdrop-blur-sm z-50 items-center justify-center p-4">
  <div class="surface w-full max-w-md overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center gap-3"><div><p class="text-[10px] font-black uppercase tracking-[.12em] text-emerald-700">Access Management</p><h2 class="text-lg font-black text-slate-950 mt-0.5">Create User</h2></div><button onclick="closeUsers()" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
    <form id="userForm" class="p-5 space-y-4">
      <div><label class="mini block mb-1.5">Email</label><input id="email" type="email" required placeholder="user@example.com" class="w-full h-11 border border-slate-200 rounded-xl px-3 outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500"></div>
      <div><label class="mini block mb-1.5">Password</label><input id="password" type="password" required minlength="8" placeholder="Minimum 8 characters" class="w-full h-11 border border-slate-200 rounded-xl px-3 outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500"></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label class="mini block mb-1.5">Plant</label><select id="plant" class="w-full h-11 border border-slate-200 rounded-xl px-3 bg-white"></select></div><div><label class="mini block mb-1.5">Role</label><select id="role" class="w-full h-11 border border-slate-200 rounded-xl px-3 bg-white"><option value="user">User</option><option value="admin">Admin</option></select></div></div>
      <button class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-sm">Create User</button><p id="userMsg" class="text-xs font-bold text-center min-h-[1rem]"></p>
    </form>
  </div>
</div>

<script>
const plants=<?php echo json_encode(array_values($catalog), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const token=<?php echo json_encode($token); ?>;
const state={},sockets={},reconnect={};
plants.forEach(p=>state[p.id]={vcbPower:0,hasVCB:false,dailyEnergy:0,inverters:{},lastLive:0,lastUpdate:'Waiting for telemetry'});

function cardHtml(p){return `<article class="surface plant-card overflow-hidden"><div class="p-5 sm:p-6"><div class="flex items-start justify-between gap-4"><div class="min-w-0"><div class="flex items-center gap-2 mb-2"><span class="inline-flex items-center gap-1.5 text-[9px] font-black px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700"><span class="dot bg-emerald-500"></span>SCADA PLANT</span><span id="badge-${p.id}" class="text-[9px] font-black px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span></div><h2 class="text-lg sm:text-xl font-black text-slate-950 leading-snug">${p.name}</h2><div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-2 text-xs text-slate-500"><span class="font-bold text-emerald-700"><i class="fa-solid fa-bolt mr-1"></i>Service ${p.service_number}</span><span><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${p.location||'Karur'}</span><span><i class="fa-solid fa-solar-panel mr-1 text-slate-400"></i>${p.capacity||'--'} MW</span></div></div><a href="home.php?plant=${encodeURIComponent(p.id)}&token=${encodeURIComponent(token)}" class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0 hover:bg-emerald-600" title="Open plant"><i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a></div>
<div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-5"><div class="rounded-xl bg-blue-50/60 border border-blue-100 p-4"><p class="mini text-blue-500">Active Power</p><p id="active-${p.id}" class="metric text-2xl font-black text-slate-950 mt-1">0.00 <span class="text-xs text-blue-600">kW</span></p><p id="activeSource-${p.id}" class="text-[10px] text-slate-500 mt-1">Waiting</p></div><div class="rounded-xl bg-purple-50/60 border border-purple-100 p-4"><p class="mini text-purple-500">Today Energy</p><p id="today-${p.id}" class="metric text-2xl font-black text-slate-950 mt-1">0.00 <span class="text-xs text-purple-600">kWh</span></p><p class="text-[10px] text-slate-500 mt-1">Current-day generation</p></div><div class="col-span-2 lg:col-span-1 rounded-xl bg-slate-50 border border-slate-200 p-4"><p class="mini">Last Update</p><p id="time-${p.id}" class="text-sm font-black text-slate-800 mt-2">Waiting</p><p id="invCount-${p.id}" class="text-[10px] text-slate-500 mt-1">0 inverters detected</p></div></div>
<div class="mt-4 rounded-xl border border-slate-200 overflow-hidden"><div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between"><p class="text-xs font-black text-slate-700">Inverter Fleet</p><span class="text-[9px] font-bold text-slate-400">Live summary</span></div><div id="inv-${p.id}" class="p-3 min-h-[112px] max-h-[210px] overflow-y-auto scroll"><div class="text-xs font-semibold text-slate-400 text-center py-8">Waiting for inverter telemetry…</div></div></div></div></article>`;}
function renderCards(){document.getElementById('cards').innerHTML=plants.map(cardHtml).join('');const sel=document.getElementById('plant');plants.forEach(p=>sel.add(new Option(p.name,p.id)));}
function invPower(values){for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(x)&&!/reactive|apparent|3.phase/.test(x))return Number(v)||0;}return 0;}
function dailyGen(values){for(const [k,v] of Object.entries(values||{}))if(/daily.*generation|daily.*gen/i.test(k))return Number(v)||0;return null;}
function stringCount(values){let a=0,t=0;for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(x))continue;if(/\d/.test(k)&&/curr|current|amp/i.test(k)){t++;if((Number(v)||0)>.5)a++;}}return {a,t};}
function finalPower(st){const inv=Object.values(st.inverters).reduce((s,x)=>s+(Number(x.power)||0),0);return st.hasVCB?st.vcbPower:inv;}
function updateOverall(){document.getElementById('overall').textContent=Object.values(state).reduce((s,x)=>s+finalPower(x),0).toFixed(2)+' kW';}
function update(id){const st=state[id],pwr=finalPower(st);document.getElementById('active-'+id).innerHTML=pwr.toFixed(2)+' <span class="text-xs text-blue-600">kW</span>';document.getElementById('activeSource-'+id).textContent=st.hasVCB?'HT/VCB active power':'Combined inverter active power';document.getElementById('today-'+id).innerHTML=(Number(st.dailyEnergy)||0).toFixed(2)+' <span class="text-xs text-purple-600">kWh</span>';document.getElementById('time-'+id).textContent=st.lastUpdate;const live=Date.now()-st.lastLive<20000,b=document.getElementById('badge-'+id);b.className='text-[9px] font-black px-2.5 py-1 rounded-full '+(live?'bg-emerald-50 text-emerald-700':'bg-blue-50 text-blue-700');b.textContent=live?'LIVE':'DB FALLBACK';const box=document.getElementById('inv-'+id),keys=Object.keys(st.inverters).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));document.getElementById('invCount-'+id).textContent=keys.length+' inverter'+(keys.length===1?'':'s')+' detected';if(keys.length)box.innerHTML=keys.map(k=>{const v=st.inverters[k],online=(Number(v.power)||0)>0||Number(v.daily)>0;return `<div class="flex items-center justify-between gap-3 px-2 py-2.5 border-b border-slate-100 last:border-0"><div class="flex items-center gap-2 min-w-0"><span class="dot ${online?'bg-emerald-500':'bg-slate-300'}"></span><span class="text-xs font-black text-slate-700 truncate">${k}</span></div><div class="flex items-center gap-4 text-[11px] shrink-0"><span class="font-black text-slate-800">${(Number(v.power)||0).toFixed(1)} kW</span><span class="text-slate-500">${v.total?`${v.active}/${v.total} strings`:'--'}</span></div></div>`;}).join('');updateOverall();}
function handle(id,d){if(!d||d.unit_id!==id)return;const st=state[id];st.lastLive=Date.now();st.lastUpdate=d.time||new Date().toLocaleTimeString('en-IN',{hour12:false});const vals=d.values||{},task=String(d.task||'').toLowerCase(),dev=String(d.device||''),isVCB=task==='vcb'||dev.toLowerCase().includes('vcb');if(isVCB&&vals['3 Phase Active Power']!==undefined){st.vcbPower=Number(vals['3 Phase Active Power'])||0;st.hasVCB=true;}let tagEnergy=null;for(const [k,v] of Object.entries(d.virtualTags||{}))if(/vcb.*today|today.*energy/i.test(k)){tagEnergy=Number(typeof v==='object'?v.value:v);break;}if(Number.isFinite(tagEnergy)&&tagEnergy>=0)st.dailyEnergy=tagEnergy;const keys=Object.keys(vals),isInv=!isVCB&&(task==='inverter'||dev.toLowerCase().includes('inverter')||keys.some(k=>/active.*power|ac.*power/i.test(k)));if(isInv){const old=st.inverters[dev]||{},sc=stringCount(vals),dg=dailyGen(vals);st.inverters[dev||'Inverter']={power:invPower(vals)||old.power||0,active:sc.t?sc.a:old.active||0,total:sc.t||old.total||0,daily:dg!==null?dg:old.daily||0};if(!(Number.isFinite(tagEnergy)&&tagEnergy>0))st.dailyEnergy=Object.values(st.inverters).reduce((s,x)=>s+(Number(x.daily)||0),0);}update(id);}
function status(){const n=plants.filter(p=>sockets[p.id]&&sockets[p.id].readyState===WebSocket.OPEN).length,el=document.getElementById('wsStatus'),dot=document.getElementById('wsDot'),portfolio=document.getElementById('portfolioLive');const all=n===plants.length,some=n>0;el.textContent=all?'All plants live':`${n}/${plants.length} live`;el.className='text-[10px] font-black uppercase tracking-wider '+(all?'text-emerald-600':some?'text-amber-600':'text-red-600');dot.className='dot '+(all?'bg-emerald-500':some?'bg-amber-500':'bg-red-500');portfolio.textContent=all?'ALL PLANTS LIVE':`${n}/${plants.length} LIVE`;portfolio.className='text-[10px] font-black rounded-full px-3 py-1.5 '+(all?'bg-emerald-50 text-emerald-700':some?'bg-amber-50 text-amber-700':'bg-red-50 text-red-700');}
function connect(id){if(sockets[id]&&(sockets[id].readyState===WebSocket.OPEN||sockets[id].readyState===WebSocket.CONNECTING))return;try{const w=new WebSocket('wss://vinobasolar.scadahub.in:5001');sockets[id]=w;w.onopen=()=>{w.send(JSON.stringify({type:'subscribe',unit_id:id}));status();};w.onmessage=e=>{try{handle(id,JSON.parse(e.data));}catch(_){}};w.onclose=()=>{sockets[id]=null;status();clearTimeout(reconnect[id]);reconnect[id]=setTimeout(()=>connect(id),3000);};w.onerror=()=>{try{w.close();}catch(_){}};}catch(_){reconnect[id]=setTimeout(()=>connect(id),3000);}}
function indiaDate(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return {date:`${o.year}-${o.month}-${o.day}`,mins:Number(o.hour)*60+Number(o.minute)};}
async function fallback(id){try{const t=indiaDate(),q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:t.date,plant:id,token});const r=await fetch('api_reports.php?'+q,{cache:'no-store',headers:{Authorization:'Bearer '+token}}),j=await r.json();if(!j.success||!j.data?.length)return;const elapsed=j.data.filter(x=>{const m=String(x.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=t.mins;}),row=elapsed.at(-1)||j.data[0],st=state[id],fresh=Date.now()-st.lastLive<20000,names=j.meta?.inv_names||[];if(!fresh){names.forEach((n,i)=>{const old=st.inverters[n]||{};st.inverters[n]={...old,power:Number(row['inv'+(i+1)+'_kw']||0),daily:Number(row['inv'+(i+1)+'_kwh']||0)};});st.vcbPower=Number(row.vcb_kw||0);st.hasVCB=!!j.meta?.ht_available&&st.vcbPower!==0;st.dailyEnergy=Math.max(0,...elapsed.map(x=>Number(x.inv_total_kwh||0)));st.lastUpdate='Database '+new Date().toLocaleTimeString('en-IN',{hour12:false});update(id);}}catch(_){}}
function openUsers(){const m=document.getElementById('userModal');m.classList.remove('hidden');m.classList.add('flex');}function closeUsers(){const m=document.getElementById('userModal');m.classList.add('hidden');m.classList.remove('flex');}
document.getElementById('userForm').addEventListener('submit',async e=>{e.preventDefault();const body={email:document.getElementById('email').value,password:document.getElementById('password').value,plant_id:document.getElementById('plant').value,role:document.getElementById('role').value},msg=document.getElementById('userMsg');try{const r=await fetch('api.php?action=add_user',{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+token},body:JSON.stringify(body)}),j=await r.json();msg.textContent=j.message||j.status;msg.className='text-xs font-bold text-center '+(j.status==='success'?'text-emerald-600':'text-red-600');if(j.status==='success')e.target.reset();}catch(_){msg.textContent='Unable to create user';msg.className='text-xs font-bold text-center text-red-600';}});
renderCards();plants.forEach(p=>{connect(p.id);setTimeout(()=>fallback(p.id),3000);});setInterval(()=>plants.forEach(p=>fallback(p.id)),30000);status();
</script>
</body>
</html>
