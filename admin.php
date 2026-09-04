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
<title>Vinoba Renewable Energy Private Limited</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
body{font-family:'Inter',sans-serif}.dot{width:8px;height:8px;border-radius:9999px;display:inline-block}.scroll::-webkit-scrollbar{width:4px}.scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
@keyframes zeroBlink{0%,100%{background:#fee2e2;box-shadow:inset 4px 0 0 #ef4444}50%{background:#fff1f2;box-shadow:inset 4px 0 0 #f87171}}
.zero-inverter{animation:zeroBlink 1s ease-in-out infinite}
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">
<nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="min-h-16 py-2 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-solar-panel"></i></div>
                <h1 class="text-base sm:text-lg font-black leading-tight truncate">Vinoba Renewable Energy Private Limited</h1>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <span id="wsStatus" class="text-xs font-bold text-slate-500 flex items-center gap-1.5"><span class="dot bg-slate-400"></span>Connecting</span>
                <div class="hidden sm:flex items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg"><i class="fa-solid fa-bolt text-amber-500"></i><span>Total</span><span id="overall" class="font-black tabular-nums">0.00 kW</span></div>
                <button onclick="location.href='logout.php'" class="px-3 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 rounded-lg">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto w-full py-6 px-4 sm:px-6">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div><h2 class="text-xl font-black">Plant Overview</h2><p class="text-xs text-slate-500 mt-1">Real-time plant telemetry</p></div>
        <span id="portfolioLive" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">CONNECTING</span>
    </div>
    <div id="cards" class="grid grid-cols-1 lg:grid-cols-2 gap-5"></div>
</main>

<script>
const plants = <?php echo json_encode(array_values($catalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const token = <?php echo json_encode($token); ?>;
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const state = {};
let ws = null, reconnectTimer = null;

const EXPECTED_STRINGS = {
    'vinoba-1': {1:23,2:23,3:23,4:23,5:23,6:23,7:22},
    'ssv': {1:23,2:23,3:23,4:23}
};

plants.forEach(p => state[p.id] = {vcbPower:0,hasVCB:false,vcbDaily:0,inverters:{},lastLive:0});

const num = v => { const n = Number(v); return Number.isFinite(n) ? n : 0; };
const fmt = (v,d=2) => num(v).toFixed(d);
function esc(value){ return String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function inverterNumber(name){ const m=String(name||'').match(/(\d+)/); return m?Number(m[1]):0; }
function expectedStrings(plantId,name){ const n=inverterNumber(name); return EXPECTED_STRINGS[plantId]?.[n] || 23; }
function invPower(values){
    for(const [k,v] of Object.entries(values||{})){
        const s=k.toLowerCase();
        if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(s) && !/reactive|apparent|3.phase/.test(s)) return num(v);
    }
    return 0;
}
function dailyGen(values){
    for(const [k,v] of Object.entries(values||{})) if(/daily.*generation|daily.*gen/i.test(k)) return num(v);
    return null;
}
function liveStringStats(values){
    const groups={};
    for(const key of Object.keys(values||{})){
        const lower=key.toLowerCase();
        if(/phase|3\.phase|three\.phase|freq|temperature|temp|ambient|reactive|apparent|inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|mppt.*curr|dc.*curr/.test(lower)) continue;
        const match=key.match(/(\d+)/); if(!match) continue;
        const no=Number(match[1]); (groups[no]??=[]).push(key);
    }
    let active=0, seen=0;
    Object.values(groups).forEach(keys=>{
        let currKey='';
        for(const key of keys){
            const lower=key.toLowerCase();
            if(!currKey && /curr|current|amp/.test(lower) && !/volt|voltage/.test(lower)) currKey=key;
        }
        if(!currKey) return;
        seen++;
        if(num(values[currKey])>0.1) active++;
    });
    return seen ? {active,seen} : null;
}
function inverterTotal(st){ return Object.values(st.inverters).reduce((s,x)=>s+num(x.power),0); }
function finalPower(st){ const inv=inverterTotal(st); return st.hasVCB && num(st.vcbPower)>0 ? num(st.vcbPower) : inv; }
function finalDaily(st){
    const values=Object.values(st.inverters);
    const hasInvDaily=values.some(x=>x.daily!==null && x.daily!==undefined);
    return hasInvDaily ? values.reduce((s,x)=>s+num(x.daily),0) : num(st.vcbDaily);
}
function updateOverall(){ document.getElementById('overall').textContent = Object.values(state).reduce((s,x)=>s+finalPower(x),0).toFixed(2)+' kW'; }
function cardHtml(p){
    return `<article class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        <div class="p-5 flex-1">
            <div class="flex items-start justify-between gap-4"><div class="min-w-0"><span id="badge-${p.id}" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span><h2 class="text-lg font-black mt-2">${esc(p.name)}</h2><div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-500"><span class="font-bold text-emerald-700"><i class="fa-solid fa-bolt mr-1"></i>Service ${esc(p.service_number)}</span><span><i class="fa-solid fa-solar-panel mr-1"></i>${num(p.capacity)} MW</span></div></div></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Total Active Power</p><p id="active-${p.id}" class="text-2xl font-black mt-2">0.00 <span class="text-xs text-blue-600">kW</span></p></div>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Today Generation</p><p id="today-${p.id}" class="text-2xl font-black mt-2">0.00 <span class="text-xs text-purple-600">kWh</span></p></div>
            </div>
            <div class="mt-4 rounded-lg border border-slate-200 overflow-hidden"><div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between"><p class="text-xs font-black">INVERTER</p><span class="text-[9px] font-bold text-emerald-600 uppercase">LIVE</span></div><div id="inv-${p.id}" class="p-3 min-h-[128px] max-h-[300px] overflow-y-auto scroll"><div class="text-xs font-semibold text-slate-400 text-center py-10">Waiting for live inverter telemetry...</div></div></div>
        </div>
        <div class="p-5 pt-0"><a href="home.php?plant=${encodeURIComponent(p.id)}&token=${encodeURIComponent(token)}" class="w-full min-h-12 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-black flex items-center justify-center gap-2 shadow-sm"><span>OPEN PLANT</span><i class="fa-solid fa-arrow-right"></i></a></div>
    </article>`;
}
function renderCards(){ document.getElementById('cards').innerHTML = plants.map(cardHtml).join(''); }
function updatePlant(id){
    const st=state[id]; if(!st)return;
    document.getElementById('active-'+id).innerHTML=fmt(finalPower(st))+' <span class="text-xs text-blue-600">kW</span>';
    document.getElementById('today-'+id).innerHTML=fmt(finalDaily(st))+' <span class="text-xs text-purple-600">kWh</span>';
    const live=Date.now()-st.lastLive<20000;
    const badge=document.getElementById('badge-'+id);
    badge.className='text-[10px] font-bold px-2.5 py-1 rounded-full '+(live?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500');
    badge.textContent=live?'LIVE':'WAITING';
    const names=Object.keys(st.inverters).sort((a,b)=>inverterNumber(a)-inverterNumber(b)||a.localeCompare(b));
    const box=document.getElementById('inv-'+id);
    if(names.length){
        box.innerHTML=names.map(name=>{
            const inv=st.inverters[name], expected=expectedStrings(id,name), active=inv.stringStats?.active ?? 0, zero=inv.received && num(inv.power)<=0.01;
            const stringText=inv.stringStats?`${active}/${expected} strings`:`--/${expected} strings`;
            return `<div class="${zero?'zero-inverter':''} flex items-center justify-between gap-3 px-3 py-3 border-b border-slate-100 last:border-0 rounded-md"><div class="min-w-0"><span class="text-xs font-black truncate block">${esc(name)}</span><span class="text-[10px] ${zero?'text-red-700 font-bold':'text-slate-500'}">${stringText}</span></div><span class="text-[11px] font-black shrink-0 ${zero?'text-red-700':'text-slate-800'}">${fmt(inv.power,1)} kW</span></div>`;
        }).join('');
    }
    updateOverall(); updatePortfolioStatus();
}
function handleMessage(d){
    if(!d || typeof d!=='object' || d.type==='daily_data_result') return;
    const id=String(d.unit_id||d.unitId||d.plant_id||d.plantId||'').trim().toLowerCase();
    if(!state[id]) return;
    const st=state[id], values=d.values||{}, task=String(d.task||'').toLowerCase(), dev=String(d.device||''), device=dev.toLowerCase(), keys=Object.keys(values);
    const isVCB=task==='vcb'||device.includes('vcb');
    if(isVCB){
        if(values['3 Phase Active Power']!==undefined){st.vcbPower=num(values['3 Phase Active Power']);st.hasVCB=true;}
        for(const [k,v] of Object.entries(d.virtualTags||{})) if(/vcb.*today|today.*energy/i.test(k)){st.vcbDaily=num(v&&typeof v==='object'?v.value:v);break;}
    }
    const stringStats=liveStringStats(values);
    const isInv=!isVCB&&(task==='inverter'||device.includes('inverter')||keys.some(k=>/active.*power|ac.*power|power.*ac/i.test(k))||stringStats!==null);
    if(isInv){
        const name=dev||'Inverter', old=st.inverters[name]||{daily:null,stringStats:null}, daily=dailyGen(values);
        st.inverters[name]={power:invPower(values),daily:daily===null?old.daily:daily,stringStats:stringStats||old.stringStats,received:true};
    }
    if(!isVCB&&!isInv)return;
    st.lastLive=Date.now(); updatePlant(id);
}
function updatePortfolioStatus(){
    const liveCount=plants.filter(p=>Date.now()-state[p.id].lastLive<20000).length, all=liveCount===plants.length, some=liveCount>0;
    const el=document.getElementById('wsStatus');
    el.innerHTML=`<span class="dot ${all?'bg-emerald-500':some?'bg-amber-500':'bg-red-500'}"></span>${all?'All plants live':liveCount+'/'+plants.length+' live'}`;
    el.className='text-xs font-bold flex items-center gap-1.5 '+(all?'text-emerald-600':some?'text-amber-600':'text-red-600');
    const badge=document.getElementById('portfolioLive');
    badge.textContent=all?'ALL PLANTS LIVE':liveCount+'/'+plants.length+' LIVE';
    badge.className='text-[10px] font-bold rounded-full px-3 py-1.5 '+(all?'bg-emerald-100 text-emerald-700':some?'bg-amber-100 text-amber-700':'bg-red-50 text-red-700');
}
function connectWS(){
    if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;
    try{
        ws=new WebSocket(WS_URL);
        ws.onopen=()=>{plants.forEach(p=>ws.send(JSON.stringify({type:'subscribe',unit_id:p.id})));updatePortfolioStatus();};
        ws.onmessage=e=>{try{handleMessage(JSON.parse(e.data));}catch(err){console.error(err);}};
        ws.onclose=()=>{ws=null;clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,3000);updatePortfolioStatus();};
        ws.onerror=()=>{try{ws.close();}catch(_){}};
    }catch(_){reconnectTimer=setTimeout(connectWS,3000);}
}
function indiaNow(){
    const parts={};
    new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')parts[x.type]=x.value});
    return {date:`${parts.year}-${parts.month}-${parts.day}`,mins:Number(parts.hour)*60+Number(parts.minute)};
}
async function databaseFallback(id){
    if(Date.now()-state[id].lastLive<20000)return;
    try{
        const t=indiaNow(), q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:t.date,plant:id,token});
        const r=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:{Authorization:'Bearer '+token}}), j=await r.json();
        if(!j.success||!Array.isArray(j.data)||!j.data.length)return;
        const elapsed=j.data.filter(row=>{const m=String(row.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=t.mins;});
        if(!elapsed.length)return;
        const row=elapsed.at(-1), st=state[id], names=Array.isArray(j.meta?.inv_names)?j.meta.inv_names:[];
        names.forEach((name,i)=>{const old=st.inverters[name]||{stringStats:null};st.inverters[name]={power:num(row['inv'+(i+1)+'_kw']),daily:num(row['inv'+(i+1)+'_kwh']),stringStats:old.stringStats||null,received:true};});
        st.vcbPower=num(row.vcb_kw); st.hasVCB=Boolean(j.meta?.ht_available)&&st.vcbPower>0; st.vcbDaily=num(row.vcb_kwh); updatePlant(id);
    }catch(_){}
}
renderCards(); connectWS();
plants.forEach(p=>setTimeout(()=>databaseFallback(p.id),1200));
setInterval(()=>{plants.forEach(p=>databaseFallback(p.id));updatePortfolioStatus();},30000);
setInterval(updatePortfolioStatus,5000);
</script>
</body>
</html>
