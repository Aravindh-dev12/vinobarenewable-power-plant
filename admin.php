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
if (!$adminUser || ($adminUser['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}
$catalog = plant_catalog();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solar Plants Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
body{font-family:'Inter',sans-serif}.dot{width:8px;height:8px;border-radius:9999px;display:inline-block}.custom-scrollbar::-webkit-scrollbar{width:4px}.custom-scrollbar::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}.plant-card{transition:border-color .18s ease,box-shadow .18s ease}.plant-card:hover{border-color:#cbd5e1;box-shadow:0 4px 12px rgba(15,23,42,.06)}
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

<nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex flex-wrap justify-between min-h-14 py-2 gap-3 items-center">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 bg-emerald-600 text-white rounded-lg flex items-center justify-center shadow-sm"><i class="fa-solid fa-solar-panel text-sm"></i></div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-slate-800">Renewable Solar Dashboard</h1>
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">SCADA Plant Monitoring</p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <span id="wsStatus" class="text-xs font-bold text-red-500 flex items-center gap-1.5"><span id="wsDot" class="dot bg-red-500"></span>Disconnected</span>
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg" title="Live combined active power across all plants">
                    <i class="fa-solid fa-bolt text-amber-500"></i>
                    <span>Overall Active Power</span>
                    <span id="overall" class="font-black tabular-nums">0.00 kW</span>
                </div>
                <button onclick="openUsers()" class="px-3 py-2 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-100 rounded-lg transition-colors"><i class="fa-solid fa-user-plus mr-1"></i><span class="hidden sm:inline">Add User</span></button>
                <button onclick="location.href='logout.php'" class="px-3 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto w-full py-6 px-4 sm:px-6">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-black text-slate-800">Plant Overview</h2>
            <p class="text-xs text-slate-500 mt-1">Live WebSocket telemetry with database fallback</p>
        </div>
        <span id="portfolioLive" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">CONNECTING</span>
    </div>
    <div id="cards" class="grid grid-cols-1 lg:grid-cols-2 gap-5"></div>
</main>

<div id="userModal" class="fixed inset-0 hidden bg-slate-900/50 z-[100] items-center justify-center px-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-5">
            <div><h3 class="text-lg font-bold text-slate-800">Add New User</h3><p class="text-xs text-slate-500 mt-1">Assign access to a configured plant</p></div>
            <button onclick="closeUsers()" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="userForm" class="space-y-4">
            <div><label class="block text-xs font-semibold text-slate-700 mb-1">User Email</label><input id="email" type="email" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-200"></div>
            <div><label class="block text-xs font-semibold text-slate-700 mb-1">Password</label><input id="password" type="password" required minlength="8" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-200"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="block text-xs font-semibold text-slate-700 mb-1">Plant</label><select id="plant" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white"></select></div>
                <div><label class="block text-xs font-semibold text-slate-700 mb-1">Role</label><select id="role" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white"><option value="user">User</option><option value="admin">Admin</option></select></div>
            </div>
            <button class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-sm">Create User</button>
            <p id="userMsg" class="text-xs font-bold text-center min-h-[1rem]"></p>
        </form>
    </div>
</div>

<script>
const plants=<?php echo json_encode(array_values($catalog), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const token=<?php echo json_encode($token); ?>;
const state={},sockets={},reconnect={};
plants.forEach(p=>state[p.id]={vcbPower:0,hasVCB:false,dailyEnergy:0,inverters:{},lastLive:0,lastUpdate:'Waiting for telemetry'});

function cardHtml(p){
    return `<article class="plant-card bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2"><span id="badge-${p.id}" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span></div>
                    <h2 class="text-lg font-black text-slate-800 leading-snug">${p.name}</h2>
                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-2 text-xs text-slate-500">
                        <span class="font-bold text-emerald-700"><i class="fa-solid fa-bolt mr-1"></i>Service ${p.service_number}</span>
                        <span><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${p.location||'Karur'}</span>
                        <span><i class="fa-solid fa-solar-panel mr-1 text-slate-400"></i>${p.capacity||'--'} MW</span>
                    </div>
                </div>
                <a href="home.php?plant=${encodeURIComponent(p.id)}&token=${encodeURIComponent(token)}" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shrink-0"><i class="fa-solid fa-arrow-right mr-1"></i>Open</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5">
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Power</p><p id="active-${p.id}" class="text-2xl font-black text-slate-800 mt-2">0.00 <span class="text-xs text-blue-600">kW</span></p><p id="activeSource-${p.id}" class="text-[10px] text-slate-500 mt-1">Waiting</p></div>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today Energy</p><p id="today-${p.id}" class="text-2xl font-black text-slate-800 mt-2">0.00 <span class="text-xs text-purple-600">kWh</span></p><p class="text-[10px] text-slate-500 mt-1">Current-day generation</p></div>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Last Update</p><p id="time-${p.id}" class="text-sm font-black text-slate-800 mt-2">Waiting</p><p id="invCount-${p.id}" class="text-[10px] text-slate-500 mt-1">0 inverters detected</p></div>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between"><p class="text-xs font-black text-slate-700">Inverter Fleet</p><span class="text-[9px] font-bold text-slate-400 uppercase">Live summary</span></div>
                <div id="inv-${p.id}" class="p-3 min-h-[96px] max-h-[200px] overflow-y-auto custom-scrollbar"><div class="text-xs font-semibold text-slate-400 text-center py-7">Waiting for inverter telemetry...</div></div>
            </div>
        </div>
    </article>`;
}

function renderCards(){
    document.getElementById('cards').innerHTML=plants.map(cardHtml).join('');
    const sel=document.getElementById('plant');
    plants.forEach(p=>sel.add(new Option(p.name,p.id)));
}
function invPower(values){for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(x)&&!/reactive|apparent|3.phase/.test(x))return Number(v)||0;}return 0;}
function dailyGen(values){for(const [k,v] of Object.entries(values||{}))if(/daily.*generation|daily.*gen/i.test(k))return Number(v)||0;return null;}
function stringCount(values){let a=0,t=0;for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(x))continue;if(/\d/.test(k)&&/curr|current|amp/i.test(k)){t++;if((Number(v)||0)>.5)a++;}}return {a,t};}
function finalPower(st){const inv=Object.values(st.inverters).reduce((s,x)=>s+(Number(x.power)||0),0);return st.hasVCB?st.vcbPower:inv;}
function updateOverall(){document.getElementById('overall').textContent=Object.values(state).reduce((s,x)=>s+finalPower(x),0).toFixed(2)+' kW';}

function update(id){
    const st=state[id],pwr=finalPower(st);
    document.getElementById('active-'+id).innerHTML=pwr.toFixed(2)+' <span class="text-xs text-blue-600">kW</span>';
    document.getElementById('activeSource-'+id).textContent=st.hasVCB?'HT/VCB active power':'Combined inverter active power';
    document.getElementById('today-'+id).innerHTML=(Number(st.dailyEnergy)||0).toFixed(2)+' <span class="text-xs text-purple-600">kWh</span>';
    document.getElementById('time-'+id).textContent=st.lastUpdate;
    const live=Date.now()-st.lastLive<20000,b=document.getElementById('badge-'+id);
    b.className='text-[10px] font-bold px-2.5 py-1 rounded-full '+(live?'bg-emerald-100 text-emerald-700':'bg-blue-50 text-blue-700');
    b.textContent=live?'LIVE':'DB FALLBACK';
    const box=document.getElementById('inv-'+id),keys=Object.keys(st.inverters).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));
    document.getElementById('invCount-'+id).textContent=keys.length+' inverter'+(keys.length===1?'':'s')+' detected';
    if(keys.length)box.innerHTML=keys.map(k=>{const v=st.inverters[k],online=(Number(v.power)||0)>0||Number(v.daily)>0;return `<div class="flex items-center justify-between gap-3 px-2 py-2.5 border-b border-slate-100 last:border-0"><div class="flex items-center gap-2 min-w-0"><span class="dot ${online?'bg-emerald-500':'bg-slate-300'}"></span><span class="text-xs font-black text-slate-700 truncate">${k}</span></div><div class="flex items-center gap-4 text-[11px] shrink-0"><span class="font-black text-slate-800">${(Number(v.power)||0).toFixed(1)} kW</span><span class="text-slate-500">${v.total?`${v.active}/${v.total} strings`:'--'}</span></div></div>`;}).join('');
    updateOverall();
}

function handle(id,d){
    if(!d||d.unit_id!==id)return;
    const st=state[id];st.lastLive=Date.now();st.lastUpdate=d.time||new Date().toLocaleTimeString('en-IN',{hour12:false});
    const vals=d.values||{},task=String(d.task||'').toLowerCase(),dev=String(d.device||''),isVCB=task==='vcb'||dev.toLowerCase().includes('vcb');
    if(isVCB&&vals['3 Phase Active Power']!==undefined){st.vcbPower=Number(vals['3 Phase Active Power'])||0;st.hasVCB=true;}
    let tagEnergy=null;for(const [k,v] of Object.entries(d.virtualTags||{}))if(/vcb.*today|today.*energy/i.test(k)){tagEnergy=Number(typeof v==='object'?v.value:v);break;}
    if(Number.isFinite(tagEnergy)&&tagEnergy>=0)st.dailyEnergy=tagEnergy;
    const keys=Object.keys(vals),isInv=!isVCB&&(task==='inverter'||dev.toLowerCase().includes('inverter')||keys.some(k=>/active.*power|ac.*power/i.test(k)));
    if(isInv){const name=dev||'Inverter',old=st.inverters[name]||{},sc=stringCount(vals),dg=dailyGen(vals);st.inverters[name]={power:invPower(vals)||old.power||0,active:sc.t?sc.a:old.active||0,total:sc.t||old.total||0,daily:dg!==null?dg:old.daily||0};if(!(Number.isFinite(tagEnergy)&&tagEnergy>0))st.dailyEnergy=Object.values(st.inverters).reduce((s,x)=>s+(Number(x.daily)||0),0);}
    update(id);
}

function status(){
    const n=plants.filter(p=>sockets[p.id]&&sockets[p.id].readyState===WebSocket.OPEN).length;
    const el=document.getElementById('wsStatus'),dot=document.getElementById('wsDot'),portfolio=document.getElementById('portfolioLive');
    const all=n===plants.length,some=n>0;
    el.innerHTML=`<span id="wsDot" class="dot ${all?'bg-emerald-500':some?'bg-amber-500':'bg-red-500'}"></span>${all?'All plants live':`${n}/${plants.length} live`}`;
    el.className='text-xs font-bold flex items-center gap-1.5 '+(all?'text-emerald-600':some?'text-amber-600':'text-red-600');
    portfolio.textContent=all?'ALL PLANTS LIVE':`${n}/${plants.length} LIVE`;
    portfolio.className='text-[10px] font-bold rounded-full px-3 py-1.5 '+(all?'bg-emerald-100 text-emerald-700':some?'bg-amber-100 text-amber-700':'bg-red-50 text-red-700');
}

function connect(id){
    if(sockets[id]&&(sockets[id].readyState===WebSocket.OPEN||sockets[id].readyState===WebSocket.CONNECTING))return;
    try{
        const w=new WebSocket('wss://vinobasolar.scadahub.in:5001');sockets[id]=w;
        w.onopen=()=>{w.send(JSON.stringify({type:'subscribe',unit_id:id}));status();};
        w.onmessage=e=>{try{handle(id,JSON.parse(e.data));}catch(_){}};
        w.onclose=()=>{sockets[id]=null;status();clearTimeout(reconnect[id]);reconnect[id]=setTimeout(()=>connect(id),3000);};
        w.onerror=()=>{try{w.close();}catch(_){}};
    }catch(_){reconnect[id]=setTimeout(()=>connect(id),3000);}
}

function indiaDate(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return {date:`${o.year}-${o.month}-${o.day}`,mins:Number(o.hour)*60+Number(o.minute)};}
async function fallback(id){
    try{
        const t=indiaDate(),q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:t.date,plant:id,token});
        const r=await fetch('api_reports.php?'+q,{cache:'no-store',headers:{Authorization:'Bearer '+token}}),j=await r.json();
        if(!j.success||!j.data?.length)return;
        const elapsed=j.data.filter(x=>{const m=String(x.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=t.mins;}),row=elapsed.at(-1)||j.data[0],st=state[id],fresh=Date.now()-st.lastLive<20000,names=j.meta?.inv_names||[];
        if(!fresh){names.forEach((n,i)=>{const old=st.inverters[n]||{};st.inverters[n]={...old,power:Number(row['inv'+(i+1)+'_kw']||0),daily:Number(row['inv'+(i+1)+'_kwh']||0)};});st.vcbPower=Number(row.vcb_kw||0);st.hasVCB=!!j.meta?.ht_available&&st.vcbPower!==0;st.dailyEnergy=Math.max(0,...elapsed.map(x=>Number(x.inv_total_kwh||0)));st.lastUpdate='Database '+new Date().toLocaleTimeString('en-IN',{hour12:false});update(id);}
    }catch(_){ }
}

function openUsers(){const m=document.getElementById('userModal');m.classList.remove('hidden');m.classList.add('flex');}
function closeUsers(){const m=document.getElementById('userModal');m.classList.add('hidden');m.classList.remove('flex');}

document.getElementById('userForm').addEventListener('submit',async e=>{
    e.preventDefault();
    const body={email:document.getElementById('email').value,password:document.getElementById('password').value,plant_id:document.getElementById('plant').value,role:document.getElementById('role').value};
    const msg=document.getElementById('userMsg');
    try{
        const r=await fetch('api.php?action=add_user',{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+token},body:JSON.stringify(body)}),j=await r.json();
        msg.textContent=j.message||j.status;
        msg.className='text-xs font-bold text-center '+(j.status==='success'?'text-emerald-600':'text-red-600');
        if(j.status==='success')e.target.reset();
    }catch(_){msg.textContent='Unable to create user';msg.className='text-xs font-bold text-center text-red-600';}
});

renderCards();
plants.forEach(p=>{connect(p.id);setTimeout(()=>fallback(p.id),3000);});
setInterval(()=>plants.forEach(p=>fallback(p.id)),30000);
status();
</script>
</body>
</html>
