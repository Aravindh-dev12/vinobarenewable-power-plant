<?php
require 'check_auth.php';
require_once __DIR__ . '/plant_config.php';
$currentPlant = normalize_plant_id($_GET['plant'] ?? ($user['plant_id'] ?? 'vinoba-1'));
if (!is_valid_plant_id($currentPlant)) $currentPlant = 'vinoba-1';
$plantInfo = plant_info($currentPlant) ?? plant_catalog()['vinoba-1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($plantInfo['name']); ?> - Inverter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-ui.css?v=7" data-dashboard-ui>
    <script src="sidebar-control.js?v=9" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl" aria-label="Open menu">&#9776;</button>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">Inverter Overview</h2>
            </div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                <span id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></span>
                <span id="liveText" class="text-[10px] font-bold text-slate-500 hidden sm:inline">CONNECTING</span>
                <span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline">--:--:--</span>
            </div>
        </header>

        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
            <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Inverters</p><p id="inv_total_count" class="text-3xl font-black mt-2">--</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Power</p><p id="inv_total" class="text-3xl font-black mt-2">-- <span class="text-sm text-blue-600">kW</span></p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Today Generation</p><p id="inv_total_gen" class="text-3xl font-black mt-2">-- <span class="text-sm text-purple-600">kWh</span></p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Average Efficiency</p><p id="inv_avg_eff" class="text-3xl font-black mt-2">-- <span class="text-sm text-sky-600">%</span></p></div>
            </section>

            <section id="inv_detail_container" class="space-y-4">
                <div class="text-sm text-slate-400 text-center py-8">Waiting for inverter telemetry...</div>
            </section>
        </div>
    </main>
</div>

<div id="stringModal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <div><h3 id="stringModalTitle" class="text-lg font-black text-slate-800">String Details</h3><p class="text-xs text-slate-500 mt-1">Live WebSocket string current and voltage</p></div>
            <button type="button" onclick="closeStringModal()" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-5 overflow-y-auto"><div id="stringGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3"></div></div>
    </div>
</div>

<script>
const CURRENT_PLANT = <?php echo json_encode($currentPlant); ?>;
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const invData = {};
let ws = null;
let reconnectTimer = null;

const num = value => { const n = Number(value); return Number.isFinite(n) ? n : 0; };
const fmt = (value, digits=2) => num(value).toFixed(digits);
function esc(value){ return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function setConnection(mode){
    const dot=document.getElementById('refreshPulse'), text=document.getElementById('liveText');
    if(mode==='live'){dot.className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';text.textContent='LIVE WEBSOCKET';text.className='text-[10px] font-bold text-emerald-600 hidden sm:inline';}
    else if(mode==='error'){dot.className='w-2.5 h-2.5 bg-red-500 rounded-full';text.textContent='RECONNECTING';text.className='text-[10px] font-bold text-red-600 hidden sm:inline';}
    else{dot.className='w-2.5 h-2.5 bg-slate-400 rounded-full';text.textContent='CONNECTING';text.className='text-[10px] font-bold text-slate-500 hidden sm:inline';}
}
function tickClock(){ document.getElementById('clockDisplay').textContent = new Date().toLocaleTimeString('en-IN',{hour12:false}); }
tickClock(); setInterval(tickClock,1000);

function findValue(values, tests){
    for(const [key,value] of Object.entries(values||{})){
        const k=key.toLowerCase();
        if(tests.some(test=>test(k))) return num(value);
    }
    return 0;
}
function activePower(values){ return findValue(values,[k=>/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(k)&&!/reactive|apparent|3.phase/.test(k)]); }
function dailyGeneration(values){ return findValue(values,[k=>/daily.*generation|daily.*gen/.test(k)]); }
function exactOr(values, exact, fallbackTests=[]){
    if(values && values[exact] !== undefined) return num(values[exact]);
    return fallbackTests.length ? findValue(values,fallbackTests) : 0;
}

function parseStrings(values){
    const groups={};
    for(const key of Object.keys(values||{})){
        const lower=key.toLowerCase();
        if(/phase|3\.phase|three\.phase|freq|temperature|temp|ambient|reactive|apparent|inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|mppt.*curr|dc.*curr/.test(lower)) continue;
        const match=key.match(/(\d+)/); if(!match) continue;
        const no=Number(match[1]); (groups[no]??=[]).push(key);
    }
    const strings=[];
    Object.entries(groups).forEach(([no,keys])=>{
        let currKey='', voltKey='';
        for(const key of keys){
            const lower=key.toLowerCase();
            if(!currKey && /curr|current|amp/.test(lower) && !/volt|voltage/.test(lower)) currKey=key;
            if(!voltKey && /volt|voltage/.test(lower) && !/curr|current|amp/.test(lower)) voltKey=key;
        }
        if(!currKey) return;
        const curr=num(values[currKey]), volt=voltKey?num(values[voltKey]):0;
        strings.push({n:Number(no),curr,volt,active:curr>0.5});
    });
    return strings.sort((a,b)=>a.n-b.n);
}

function updateInverter(message){
    const values=message.values||{};
    const name=String(message.device||'Inverter');
    const old=invData[name]||{};
    const parsedStrings=parseStrings(values);
    invData[name]={
        power: activePower(values),
        reactive: exactOr(values,'a.c. reactive power',[k=>/reactive.*power/.test(k)]),
        pf: exactOr(values,'Power Factor',[k=>/power.*factor|cosphi/.test(k)]),
        vac_ab: exactOr(values,'a.c. voltage AB',[k=>/voltage.*ab|ab.*voltage/.test(k)]),
        vac_bc: exactOr(values,'a.c. voltage BC',[k=>/voltage.*bc|bc.*voltage/.test(k)]),
        vac_ca: exactOr(values,'a.c. voltage CA',[k=>/voltage.*ca|ca.*voltage/.test(k)]),
        freq: exactOr(values,'a.c. frequency',[k=>/frequency|freq/.test(k)]),
        i_a: exactOr(values,'A phase current',[k=>/a.*phase.*current/.test(k)]),
        i_b: exactOr(values,'B phase current',[k=>/b.*phase.*current/.test(k)]),
        i_c: exactOr(values,'C phase current',[k=>/c.*phase.*current/.test(k)]),
        eff: exactOr(values,'inverter efficiency',[k=>/efficiency/.test(k)]),
        amb: exactOr(values,'internal ambient temperature',[k=>/ambient.*temp|internal.*temp/.test(k)]),
        dailyGen: dailyGeneration(values) || num(old.dailyGen),
        totalGen: exactOr(values,'total generation',[k=>/total.*generation/.test(k)]) || num(old.totalGen),
        dailyCO2: exactOr(values,'daily CO2 reduction',[k=>/daily.*co2/.test(k)]) || num(old.dailyCO2),
        totalCO2: exactOr(values,'total CO2 reduction',[k=>/total.*co2/.test(k)]) || num(old.totalCO2),
        strings: parsedStrings.length ? parsedStrings : (old.strings||[]),
        stringLive: parsedStrings.length ? true : Boolean(old.stringLive)
    };
    renderAll();
}

function renderAll(){
    const names=Object.keys(invData).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));
    if(!names.length) return;
    const totalPower=names.reduce((s,k)=>s+num(invData[k].power),0);
    const totalGen=names.reduce((s,k)=>s+num(invData[k].dailyGen),0);
    const efficiencies=names.map(k=>num(invData[k].eff)).filter(v=>v>0);
    const avgEff=efficiencies.length?efficiencies.reduce((a,b)=>a+b,0)/efficiencies.length:0;
    document.getElementById('inv_total_count').textContent=names.length;
    document.getElementById('inv_total').innerHTML=fmt(totalPower)+' <span class="text-sm font-bold text-blue-600">kW</span>';
    document.getElementById('inv_total_gen').innerHTML=fmt(totalGen)+' <span class="text-sm font-bold text-purple-600">kWh</span>';
    document.getElementById('inv_avg_eff').innerHTML=fmt(avgEff,1)+' <span class="text-sm font-bold text-sky-600">%</span>';

    const container=document.getElementById('inv_detail_container');
    container.innerHTML=names.map(name=>{
        const v=invData[name], online=num(v.power)>0.01, strings=v.strings||[], active=strings.filter(x=>x.active).length;
        return `<article class="relative bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <button type="button" class="string-eye absolute top-4 right-4 w-9 h-9 rounded-lg border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-200 text-blue-600 flex items-center justify-center" data-name="${encodeURIComponent(name)}" title="View live strings" aria-label="View strings for ${esc(name)}"><i class="fa-solid fa-eye"></i></button>
            <div class="pr-12 flex items-center gap-3 mb-4 pb-3 border-b border-slate-100">
                <i class="fa-solid fa-server text-blue-500"></i><h3 class="text-sm font-black text-slate-700 uppercase tracking-widest">${esc(name)}</h3>
                <span class="rounded-full ${online?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500'} px-2.5 py-1 text-[10px] font-bold">${online?'ONLINE':'OFFLINE'}</span>
                <span class="text-[10px] font-bold text-slate-500">${v.stringLive&&strings.length?active+'/'+strings.length+' strings':'No live string data'}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                ${metric('AC Power',v.power,'kW',2)}${metric('Reactive',v.reactive,'kVAR',2)}${metric('Power Factor',v.pf,'',3)}${metric('Efficiency',v.eff,'%',1)}${metric('AC Frequency',v.freq,'Hz',2)}${metric('Ambient',v.amb,'°C',1)}
                ${metric('Vac AB',v.vac_ab,'V',1)}${metric('Vac BC',v.vac_bc,'V',1)}${metric('Vac CA',v.vac_ca,'V',1)}${metric('I A',v.i_a,'A',2)}${metric('I B',v.i_b,'A',2)}${metric('I C',v.i_c,'A',2)}
                ${metric('Daily Gen',v.dailyGen,'kWh',1)}${metric('Total Gen',v.totalGen,'kWh',0)}${metric('Daily CO₂',v.dailyCO2,'kg',1)}${metric('Total CO₂',v.totalCO2,'kg',0)}
            </div>
        </article>`;
    }).join('');
    container.querySelectorAll('.string-eye').forEach(btn=>btn.addEventListener('click',()=>openStringModal(decodeURIComponent(btn.dataset.name||''))));
}
function metric(label,value,unit,digits){ return `<div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${label}</p><p class="mt-1 text-lg font-black text-slate-800">${fmt(value,digits)}${unit?` <span class="text-xs text-slate-500">${unit}</span>`:''}</p></div>`; }

function openStringModal(name){
    const inverter=invData[name]; if(!inverter) return;
    document.getElementById('stringModalTitle').textContent=name+' - String Details';
    const strings=inverter.strings||[];
    document.getElementById('stringGrid').innerHTML=strings.length?strings.map(s=>`<div class="rounded-lg border ${s.active?'border-emerald-200 bg-emerald-50':'border-slate-200 bg-slate-50'} p-3 text-center"><p class="text-[10px] font-black ${s.active?'text-emerald-700':'text-slate-500'}">STRING ${s.n}</p><p class="text-lg font-black mt-1">${fmt(s.curr,2)} A</p><p class="text-[10px] text-slate-500 mt-1">${s.volt?fmt(s.volt,1)+' V':'Voltage unavailable'}</p></div>`).join(''):'<p class="col-span-full text-center text-sm text-slate-400 py-8">No live string telemetry received for this inverter.</p>';
    document.getElementById('stringModal').classList.remove('hidden');
}
function closeStringModal(){ document.getElementById('stringModal').classList.add('hidden'); }
window.closeStringModal=closeStringModal;
document.getElementById('stringModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeStringModal();});

function handleLive(message){
    if(!message||message.type==='daily_data_result') return;
    const unit=String(message.unit_id||'').trim().toLowerCase();
    if(unit!==CURRENT_PLANT) return;
    const values=message.values||{}, task=String(message.task||'').toLowerCase(), device=String(message.device||'').toLowerCase(), keys=Object.keys(values);
    if(task==='vcb'||device.includes('vcb')||task==='transformer'||device.includes('transformer')) return;
    const isInv=task==='inverter'||device.includes('inverter')||keys.some(k=>/active.*power|ac.*power|power.*ac/i.test(k))||parseStrings(values).length>0;
    if(!isInv) return;
    setConnection('live'); updateInverter(message);
}
window.handleLive=handleLive;

function connectWS(){
    if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING)) return;
    setConnection('connecting');
    try{
        ws=new WebSocket(WS_URL);
        ws.onopen=()=>{setConnection('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));};
        ws.onmessage=e=>{try{handleLive(JSON.parse(e.data));}catch(err){console.error(err);}};
        ws.onclose=()=>{ws=null;setConnection('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,3000);};
        ws.onerror=()=>{try{ws.close();}catch(_){}};
    }catch(_){setConnection('error');reconnectTimer=setTimeout(connectWS,3000);}
}
setConnection('connecting'); connectWS();
</script>
</body>
</html>
