<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

$token = trim((string)($_GET['token'] ?? ''));
$adminUser = null;
if ($token !== '' && isset($conn) && $conn instanceof mysqli) {
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
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
body{font-family:'Inter',sans-serif}.dot{width:8px;height:8px;border-radius:9999px;display:inline-block}.scroll::-webkit-scrollbar{width:4px}.scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">
<nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="min-h-16 py-2 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center"><i class="fa-solid fa-solar-panel"></i></div><div><h1 class="text-lg font-bold">Renewable Solar Dashboard</h1><p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Plant Portfolio</p></div></div>
            <div class="flex items-center gap-2 sm:gap-3"><span id="wsStatus" class="text-xs font-bold text-slate-500 flex items-center gap-1.5"><span class="dot bg-slate-400"></span>Connecting</span><div class="hidden sm:flex items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg"><i class="fa-solid fa-bolt text-amber-500"></i><span>Overall</span><span id="overall" class="font-black tabular-nums">0.00 kW</span></div><button onclick="location.href='logout.php'" class="px-3 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 rounded-lg">Logout</button></div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto w-full py-6 px-4 sm:px-6">
    <div class="flex items-center justify-between gap-3 mb-5"><div><h2 class="text-xl font-black">Plant Overview</h2><p class="text-xs text-slate-500 mt-1">Live WebSocket telemetry</p></div><span id="portfolioLive" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">CONNECTING</span></div>
    <div id="cards" class="grid grid-cols-1 lg:grid-cols-2 gap-5"></div>
</main>

<div id="stringModal" class="fixed inset-0 bg-slate-900/50 hidden z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b"><div><h3 id="stringModalTitle" class="text-lg font-black">String Details</h3><p class="text-xs text-slate-500 mt-1">Live WebSocket current + voltage pairs only</p></div><button type="button" onclick="closeStringModal()" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="p-5 overflow-y-auto"><div id="stringGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3"></div></div>
    </div>
</div>

<script>
const plants = <?php echo json_encode(array_values($catalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const token = <?php echo json_encode($token); ?>;
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const state={};
let ws=null,reconnectTimer=null;
plants.forEach(p=>state[p.id]={vcbPower:0,hasVCB:false,dailyEnergy:0,inverters:{},lastLive:0,lastUpdate:'Waiting for telemetry'});

const num=v=>{const n=Number(v);return Number.isFinite(n)?n:0;};
const fmt=(v,d=2)=>num(v).toFixed(d);
function esc(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function invPower(values){for(const [k,v] of Object.entries(values||{})){const s=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(s)&&!/reactive|apparent|3.phase/.test(s))return num(v);}return 0;}
function dailyGen(values){for(const [k,v] of Object.entries(values||{}))if(/daily.*generation|daily.*gen/i.test(k))return num(v);return null;}

function stringChannelNumber(key){
    const s=String(key||'').toLowerCase().replace(/[_.-]+/g,' ');
    const explicit=s.match(/(?:string|pv(?:\s*string)?|input)\s*#?\s*(\d+)/i) || s.match(/(\d+)\s*(?:string|pv(?:\s*string)?)/i);
    if(explicit) return Number(explicit[1]);
    if(/phase|frequency|freq|temperature|temp|ambient|reactive|apparent|power|energy|generation|co2|working|hour|mppt|grid|load|total|efficiency|factor|cosphi|thd|alarm|status/.test(s)) return null;
    if(!/(?:curr|current|amp|volt|voltage)/.test(s)) return null;
    const m=s.match(/\b(\d+)\b/); if(!m) return null;
    const residual=s
        .replace(/\b(?:curr(?:ent)?|amp(?:ere)?s?|volt(?:age)?)\b/g,' ')
        .replace(new RegExp('\\b'+m[1]+'\\b','g'),' ')
        .replace(/\b(?:ch|channel|input|string|pv)\b/g,' ')
        .replace(/\s+/g,' ').trim();
    return residual==='' ? Number(m[1]) : null;
}
function parseStrings(values){
    const groups={};
    for(const key of Object.keys(values||{})){
        const no=stringChannelNumber(key); if(no===null) continue;
        const lower=key.toLowerCase();
        if(!groups[no]) groups[no]={currKey:'',voltKey:''};
        if(!groups[no].currKey && /curr|current|amp/.test(lower) && !/volt|voltage/.test(lower)) groups[no].currKey=key;
        if(!groups[no].voltKey && /volt|voltage/.test(lower) && !/curr|current|amp/.test(lower)) groups[no].voltKey=key;
    }
    const out=[];
    Object.entries(groups).forEach(([no,pair])=>{
        if(!pair.currKey || !pair.voltKey) return;
        const curr=num(values[pair.currKey]),volt=num(values[pair.voltKey]);
        out.push({n:Number(no),curr,volt,active:curr>0.5,currKey:pair.currKey,voltKey:pair.voltKey});
    });
    return out.sort((a,b)=>a.n-b.n);
}
function finalPower(st){const inv=Object.values(st.inverters).reduce((s,x)=>s+num(x.power),0);return st.hasVCB&&num(st.vcbPower)>0?num(st.vcbPower):inv;}
function updateOverall(){document.getElementById('overall').textContent=Object.values(state).reduce((s,x)=>s+finalPower(x),0).toFixed(2)+' kW';}

function cardHtml(p){return `<article class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden"><div class="p-5">
    <div class="flex items-start justify-between gap-4"><div class="min-w-0"><span id="badge-${p.id}" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span><h2 class="text-lg font-black mt-2">${esc(p.name)}</h2><div class="flex flex-wrap gap-x-4 mt-2 text-xs text-slate-500"><span class="font-bold text-emerald-700"><i class="fa-solid fa-bolt mr-1"></i>Service ${esc(p.service_number)}</span><span><i class="fa-solid fa-solar-panel mr-1"></i>${num(p.capacity)} MW</span></div></div><a href="home.php?plant=${encodeURIComponent(p.id)}&token=${encodeURIComponent(token)}" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shrink-0"><i class="fa-solid fa-arrow-right mr-1"></i>Open</a></div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5"><div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Active Power</p><p id="active-${p.id}" class="text-2xl font-black mt-2">0.00 <span class="text-xs text-blue-600">kW</span></p><p id="activeSource-${p.id}" class="text-[10px] text-slate-500 mt-1">Waiting</p></div><div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Today Energy</p><p id="today-${p.id}" class="text-2xl font-black mt-2">0.00 <span class="text-xs text-purple-600">kWh</span></p><p class="text-[10px] text-slate-500 mt-1">Combined inverter generation</p></div><div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Last Update</p><p id="time-${p.id}" class="text-sm font-black mt-2">Waiting</p><p id="invCount-${p.id}" class="text-[10px] text-slate-500 mt-1">0 inverters detected</p></div></div>
    <div class="mt-4 rounded-lg border border-slate-200 overflow-hidden"><div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between"><p class="text-xs font-black">Inverter Fleet</p><span class="text-[9px] font-bold text-slate-400 uppercase">Live WSS</span></div><div id="inv-${p.id}" class="p-3 min-h-[96px] max-h-[240px] overflow-y-auto scroll"><div class="text-xs font-semibold text-slate-400 text-center py-7">Waiting for live inverter telemetry...</div></div></div>
</div></article>`;}
function renderCards(){document.getElementById('cards').innerHTML=plants.map(cardHtml).join('');}

function updatePlant(id){
    const st=state[id];if(!st)return;const power=finalPower(st);
    document.getElementById('active-'+id).innerHTML=fmt(power)+' <span class="text-xs text-blue-600">kW</span>';
    document.getElementById('activeSource-'+id).textContent=st.hasVCB&&num(st.vcbPower)>0?'HT/VCB active power':'Combined inverter active power';
    document.getElementById('today-'+id).innerHTML=fmt(st.dailyEnergy)+' <span class="text-xs text-purple-600">kWh</span>';
    document.getElementById('time-'+id).textContent=st.lastUpdate;
    const live=Date.now()-st.lastLive<20000,badge=document.getElementById('badge-'+id);badge.className='text-[10px] font-bold px-2.5 py-1 rounded-full '+(live?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500');badge.textContent=live?'LIVE':'WAITING';
    const names=Object.keys(st.inverters).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));document.getElementById('invCount-'+id).textContent=names.length+' inverter'+(names.length===1?'':'s')+' detected';
    const box=document.getElementById('inv-'+id);
    if(names.length){box.innerHTML=names.map(name=>{const inv=st.inverters[name],strings=inv.strings||[],active=strings.filter(s=>s.active).length,hasLive=inv.stringSource==='websocket'&&strings.length>0;return `<div class="flex items-center justify-between gap-3 px-2 py-2.5 border-b border-slate-100 last:border-0"><div class="min-w-0"><span class="text-xs font-black truncate block">${esc(name)}</span><span class="text-[10px] text-slate-400">${hasLive?active+'/'+strings.length+' verified strings':'No verified live string pairs'}</span></div><div class="flex items-center gap-3 shrink-0"><span class="text-[11px] font-black">${fmt(inv.power,1)} kW</span><button type="button" class="admin-eye w-8 h-8 rounded-md border ${hasLive?'border-blue-200 text-blue-600 hover:bg-blue-50':'border-slate-200 text-slate-300 cursor-not-allowed'} flex items-center justify-center" data-plant="${id}" data-name="${encodeURIComponent(name)}" ${hasLive?'':'disabled'} title="View verified live strings"><i class="fa-solid fa-eye"></i></button></div></div>`;}).join('');}
    box.querySelectorAll('.admin-eye:not([disabled])').forEach(btn=>btn.addEventListener('click',()=>openAdminStrings(btn.dataset.plant,decodeURIComponent(btn.dataset.name||''))));
    updateOverall();updatePortfolioStatus();
}

function handleMessage(d){
    if(!d||typeof d!=='object'||d.type==='daily_data_result')return;
    const id=String(d.unit_id||d.unitId||d.plant_id||d.plantId||'').trim().toLowerCase();if(!state[id])return;
    const st=state[id],values=d.values||{},task=String(d.task||'').toLowerCase(),dev=String(d.device||''),device=dev.toLowerCase(),keys=Object.keys(values),isVCB=task==='vcb'||device.includes('vcb');
    if(isVCB&&values['3 Phase Active Power']!==undefined){st.vcbPower=num(values['3 Phase Active Power']);st.hasVCB=true;}
    let vcbToday=null;for(const [k,v] of Object.entries(d.virtualTags||{})){if(/vcb.*today|today.*energy/i.test(k)){vcbToday=num(v&&typeof v==='object'?v.value:v);break;}}if(vcbToday!==null&&vcbToday>=0)st.dailyEnergy=vcbToday;
    const parsed=parseStrings(values),isInv=!isVCB&&(task==='inverter'||device.includes('inverter')||keys.some(k=>/active.*power|ac.*power|power.*ac/i.test(k))||parsed.length>0);
    if(isInv){
        const name=dev||'Inverter',old=st.inverters[name]||{},daily=dailyGen(values);
        st.inverters[name]={...old,power:invPower(values),daily:daily===null?num(old.daily):daily};
        if(parsed.length){st.inverters[name].strings=parsed;st.inverters[name].stringSource='websocket';}
        if(!(vcbToday!==null&&vcbToday>0))st.dailyEnergy=Object.values(st.inverters).reduce((s,x)=>s+num(x.daily),0);
    }
    if(!isVCB&&!isInv)return;st.lastLive=Date.now();st.lastUpdate=d.time||d.timestamp||new Date().toLocaleTimeString('en-IN',{hour12:false});updatePlant(id);
}

function openAdminStrings(plantId,name){
    const inv=state[plantId]?.inverters?.[name];if(!inv)return;
    document.getElementById('stringModalTitle').textContent=name+' - String Details';
    const strings=inv.stringSource==='websocket'?(inv.strings||[]):[];
    document.getElementById('stringGrid').innerHTML=strings.length?strings.map(s=>`<div class="rounded-lg border ${s.active?'border-emerald-200 bg-emerald-50':'border-slate-200 bg-slate-50'} p-3 text-center"><p class="text-[10px] font-black">STRING ${s.n}</p><p class="text-lg font-black mt-1">${fmt(s.curr,2)} A</p><p class="text-[10px] text-slate-500 mt-1">${fmt(s.volt,1)} V</p></div>`).join(''):'<p class="col-span-full text-center text-sm text-slate-400 py-8">No verified live current/voltage string pairs.</p>';
    document.getElementById('stringModal').classList.remove('hidden');
}
function closeStringModal(){document.getElementById('stringModal').classList.add('hidden');}window.closeStringModal=closeStringModal;document.getElementById('stringModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeStringModal();});

function updatePortfolioStatus(){const liveCount=plants.filter(p=>Date.now()-state[p.id].lastLive<20000).length,all=liveCount===plants.length,some=liveCount>0;const el=document.getElementById('wsStatus');el.innerHTML=`<span class="dot ${all?'bg-emerald-500':some?'bg-amber-500':'bg-red-500'}"></span>${all?'All plants live':liveCount+'/'+plants.length+' live'}`;el.className='text-xs font-bold flex items-center gap-1.5 '+(all?'text-emerald-600':some?'text-amber-600':'text-red-600');const p=document.getElementById('portfolioLive');p.textContent=all?'ALL PLANTS LIVE':liveCount+'/'+plants.length+' LIVE';p.className='text-[10px] font-bold rounded-full px-3 py-1.5 '+(all?'bg-emerald-100 text-emerald-700':some?'bg-amber-100 text-amber-700':'bg-red-50 text-red-700');}
function connectWS(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;try{ws=new WebSocket(WS_URL);ws.onopen=()=>{plants.forEach(p=>ws.send(JSON.stringify({type:'subscribe',unit_id:p.id})));updatePortfolioStatus();};ws.onmessage=e=>{try{handleMessage(JSON.parse(e.data));}catch(err){console.error(err);}};ws.onclose=()=>{ws=null;clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,3000);updatePortfolioStatus();};ws.onerror=()=>{try{ws.close();}catch(_){}};}catch(_){reconnectTimer=setTimeout(connectWS,3000);}}

function indiaNow(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return{date:`${o.year}-${o.month}-${o.day}`,mins:Number(o.hour)*60+Number(o.minute)};}
async function databaseFallback(id){
    if(Date.now()-state[id].lastLive<20000)return;
    try{const t=indiaNow(),q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:t.date,plant:id,token}),r=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:{Authorization:'Bearer '+token}}),j=await r.json();if(!j.success||!Array.isArray(j.data)||!j.data.length)return;const elapsed=j.data.filter(row=>{const m=String(row.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=t.mins;});if(!elapsed.length)return;const row=elapsed.at(-1),st=state[id],names=Array.isArray(j.meta?.inv_names)?j.meta.inv_names:[];names.forEach((name,i)=>{const old=st.inverters[name]||{};st.inverters[name]={...old,power:num(row['inv'+(i+1)+'_kw']),daily:num(row['inv'+(i+1)+'_kwh'])};});st.vcbPower=num(row.vcb_kw);st.hasVCB=Boolean(j.meta?.ht_available)&&st.vcbPower>0;st.dailyEnergy=Math.max(0,...elapsed.map(x=>num(x.inv_total_kwh)));st.lastUpdate='Database '+new Date().toLocaleTimeString('en-IN',{hour12:false});updatePlant(id);}catch(_){}
}

renderCards();connectWS();plants.forEach(p=>setTimeout(()=>databaseFallback(p.id),2500));setInterval(()=>plants.forEach(p=>databaseFallback(p.id)),30000);setInterval(updatePortfolioStatus,3000);updatePortfolioStatus();
</script>
</body>
</html>