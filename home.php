<?php
require 'check_auth.php';
require_once __DIR__ . '/plant_config.php';
$currentPlantId = normalize_plant_id($_GET['plant'] ?? ($user['plant_id'] ?? 'vinoba-1'));
if (!is_valid_plant_id($currentPlantId)) $currentPlantId = 'vinoba-1';
$plantInfo = plant_info($currentPlantId) ?? plant_catalog()['vinoba-1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo htmlspecialchars($plantInfo['name']); ?> - Live Overview</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="sidebar-control.js?v=6" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
<div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
<div id="sidebar-container"></div>
<main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
<header class="bg-white px-4 sm:px-6 py-3.5 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
    <div class="flex items-center gap-3 min-w-0">
        <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl shrink-0" aria-label="Open navigation">&#9776;</button>
        <div class="min-w-0">
            <h2 class="text-xl font-black text-slate-800 tracking-tight leading-tight">Live Plant Telemetry</h2>
            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Real-time SCADA overview</p>
        </div>
    </div>
    <div class="flex items-center gap-2.5 bg-slate-50 px-3 py-2 rounded-xl border border-slate-100 shrink-0">
        <div id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></div>
        <span id="headerLiveText" class="text-[10px] font-black text-slate-500 uppercase tracking-wider hidden sm:inline">Connecting</span>
        <span id="clockDisplay" class="text-xs font-bold text-slate-700 tracking-widest">--:--:--</span>
    </div>
</header>

<div class="p-4 sm:p-6 w-full flex flex-col gap-5 max-w-[1600px] mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.16em]">Plant Profile</h3>
                <span class="inline-flex items-center gap-1.5 text-[9px] font-black text-emerald-700 bg-emerald-50 rounded-full px-2.5 py-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>SCADA</span>
            </div>
            <p id="profileName" class="font-black text-slate-900 text-lg leading-snug mt-3"><?php echo htmlspecialchars($plantInfo['name']); ?></p>
            <p id="profileServiceNumber" class="text-[11px] text-emerald-700 font-bold mt-2">Service No. <?php echo htmlspecialchars($plantInfo['service_number']); ?></p>
            <div class="flex items-center gap-3 mt-4 pt-3 border-t border-slate-100">
                <p id="profileCapacity" class="font-black text-emerald-600 text-2xl"><?php echo htmlspecialchars((string)$plantInfo['capacity']); ?> <span class="text-sm font-bold">MW</span></p>
                <span class="h-5 w-px bg-slate-200"></span>
                <p id="profileLocation" class="text-xs text-slate-500 font-semibold"><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i><?php echo htmlspecialchars($plantInfo['location']); ?></p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.16em]">Today Energy</h3>
            <p class="font-black text-slate-900 text-3xl mt-3" id="vcb_etoday">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
            <p id="energySource" class="text-xs text-slate-500 mt-2">Waiting for live inverter data</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.16em]">Live Active Power</h3>
            <p class="font-black text-slate-900 text-3xl mt-3" id="vcb_active">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
            <p class="text-xs text-slate-500 mt-2">Peak Today: <span id="vcb_peak" class="font-bold text-slate-700">--</span> kW</p>
            <p id="powerSource" class="text-[10px] text-slate-400 font-semibold mt-1">Waiting for live power</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.16em] mb-4">Weather</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between gap-3"><span class="text-slate-500">Radiation</span><strong class="text-slate-800"><span id="wmos_rad">--</span> W/m²</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Panel Temp</span><strong class="text-slate-800"><span id="wmos_ptemp">--</span> °C</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Wind Speed</span><strong class="text-slate-800"><span id="wmos_wind">--</span> m/s</strong></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-black text-slate-700 uppercase tracking-[0.14em]">Generation Window</h3>
                <p class="text-xs text-slate-500 mt-1">Live WebSocket history with database fallback</p>
            </div>
            <span id="htAvailability" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">HT DATA OPTIONAL</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                <div class="flex items-center justify-between"><p class="text-xs font-black text-blue-700 uppercase">Inverters</p><span id="inverterWindowState" class="text-[9px] font-black text-slate-400">WAITING</span></div>
                <div class="grid grid-cols-2 gap-3 mt-4"><div><p class="text-[10px] text-slate-400 uppercase">Start</p><p id="inverter_start_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div><div><p class="text-[10px] text-slate-400 uppercase">End / Latest</p><p id="inverter_end_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div></div>
            </div>
            <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-4">
                <div class="flex items-center justify-between"><p class="text-xs font-black text-purple-700 uppercase">HT / VCB</p><span id="vcbWindowState" class="text-[9px] font-black text-slate-400">WAITING</span></div>
                <div class="grid grid-cols-2 gap-3 mt-4"><div><p class="text-[10px] text-slate-400 uppercase">Start</p><p id="vcb_start_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div><div><p class="text-[10px] text-slate-400 uppercase">End / Latest</p><p id="vcb_end_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div></div>
            </div>
            <div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                <div class="flex items-center justify-between"><p class="text-xs font-black text-orange-700 uppercase">Transformer</p><span id="transformerWindowState" class="text-[9px] font-black text-slate-400">WAITING</span></div>
                <div class="grid grid-cols-2 gap-3 mt-4"><div><p class="text-[10px] text-slate-400 uppercase">Start</p><p id="transformer_start_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div><div><p class="text-[10px] text-slate-400 uppercase">End / Latest</p><p id="transformer_end_time" class="text-xl font-black text-slate-800 mt-1">--:--</p></div></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 h-[400px]">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-black text-slate-700 uppercase tracking-[0.14em]"><i class="fa-solid fa-chart-line text-blue-500 mr-2"></i>Generation Curve (Today)</h3>
            <span class="text-[10px] font-semibold text-slate-400">Live + historical</span>
        </div>
        <div class="relative w-full" style="height:320px"><canvas id="powerChart"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex justify-between gap-3 items-center border-b border-slate-100 pb-3 mb-5">
            <div><h3 class="text-sm font-black text-slate-700 uppercase tracking-[0.14em]">Inverter Field Array</h3><p class="text-xs text-slate-500 mt-1">Live inverter power, generation and string availability</p></div>
            <span id="liveStatus" class="text-xs font-black text-slate-500">CONNECTING</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="inv_grid"><div class="text-sm text-slate-400 italic text-center py-8 col-span-full">Waiting for live inverter telemetry...</div></div>
    </div>
</div>
</main>
</div>

<script>
const CURRENT_PLANT=<?php echo json_encode($currentPlantId); ?>;
const PLANT_INFO=<?php echo json_encode($plantInfo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const TOKEN=new URLSearchParams(location.search).get('token')||sessionStorage.getItem('vs_token')||localStorage.getItem('vs_token')||'';
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const state={inverters:{},vcbPower:0,hasVcb:false,vcbToday:null,peakPower:0,lastLive:0};
const windows={inverter:{start:'',end:''},vcb:{start:'',end:''},transformer:{start:'',end:''}};
const requestedHistory=new Set();
let ws=null,reconnectTimer=null,apiTimer=null;

function indiaParts(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return o;}
function indiaDate(){const p=indiaParts();return `${p.year}-${p.month}-${p.day}`;}
function nowMinutes(){const p=indiaParts();return Number(p.hour)*60+Number(p.minute);}
function currentTime(){const p=indiaParts();return `${p.hour}:${p.minute}`;}
setInterval(()=>document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false}),1000);

function numberValue(v){const n=Number(v);return Number.isFinite(n)?n:0;}
function inverterPower(values){for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(x)&&!/reactive|apparent|3.phase/.test(x))return numberValue(v);}return 0;}
function vcbPower(values){if(values&&values['3 Phase Active Power']!==undefined)return numberValue(values['3 Phase Active Power']);for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/3.*phase.*active.*power|active.*power/.test(x)&&!/reactive|apparent/.test(x))return numberValue(v);}return 0;}
function inverterDaily(values){for(const [k,v] of Object.entries(values||{}))if(/daily.*generation|daily.*gen/i.test(k))return numberValue(v);return null;}
function stringSummary(values){let active=0,total=0;for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(x))continue;if(/\d/.test(k)&&/curr|current|amp/i.test(k)){total++;if(numberValue(v)>.5)active++;}}return {active,total};}
function inverterTotalPower(){return Object.values(state.inverters).reduce((s,v)=>s+numberValue(v.power),0);}
function inverterTotalEnergy(){return Object.values(state.inverters).reduce((s,v)=>s+numberValue(v.daily),0);}
function activePower(){return state.hasVcb?state.vcbPower:inverterTotalPower();}
function todayEnergy(){return state.vcbToday!==null?state.vcbToday:inverterTotalEnergy();}

function renderWindow(type){const item=windows[type];const start=document.getElementById(type+'_start_time'),end=document.getElementById(type+'_end_time'),badge=document.getElementById(type+'WindowState');if(start)start.textContent=item.start||'--:--';if(end)end.textContent=item.end||'--:--';if(badge){badge.textContent=item.start?'ACTIVE TODAY':'WAITING';badge.className='text-[9px] font-black '+(item.start?'text-emerald-600':'text-slate-400');}}
function mergeWindow(type,start,end){if(!windows[type])return;if(start&&(!windows[type].start||start<windows[type].start))windows[type].start=start;if(end&&(!windows[type].end||end>windows[type].end))windows[type].end=end;renderWindow(type);}
function recordLiveWindow(type,active){if(!active)return;const t=currentTime();mergeWindow(type,t,t);}
function parseHistoryTime(raw){const m=String(raw||'').match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);if(!m)return '';return String(Number(m[1])).padStart(2,'0')+':'+m[2];}

function render(){const power=activePower(),energy=todayEnergy();state.peakPower=Math.max(state.peakPower,power);document.getElementById('vcb_active').innerHTML=power.toFixed(2)+' <span class="text-sm font-bold text-blue-600">kW</span>';document.getElementById('vcb_etoday').innerHTML=energy.toFixed(2)+' <span class="text-sm font-bold text-purple-600">kWh</span>';document.getElementById('vcb_peak').textContent=state.peakPower.toFixed(2);document.getElementById('powerSource').textContent=state.hasVcb?'HT / VCB active power':'Combined inverter active power';document.getElementById('energySource').textContent=state.vcbToday!==null?'HT / VCB today energy':'Combined inverter daily generation';document.getElementById('htAvailability').textContent=state.hasVcb||state.vcbToday!==null?'HT DATA LIVE':'HT DATA UNAVAILABLE';document.getElementById('htAvailability').className='text-[10px] font-bold rounded-full px-3 py-1.5 '+(state.hasVcb||state.vcbToday!==null?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500');renderInverters();updateCurrentChart(power);}
function renderInverters(){const grid=document.getElementById('inv_grid'),keys=Object.keys(state.inverters).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));if(!keys.length)return;grid.innerHTML=keys.map(name=>{const v=state.inverters[name],good=v.total>0&&v.active>=Math.min(22,v.total),bad=v.total>0&&v.active===0;const cls=good?'border-emerald-200 bg-emerald-50/50':bad?'border-red-200 bg-red-50':'border-slate-200 bg-white';const badge=good?'bg-emerald-100 text-emerald-700':bad?'bg-red-100 text-red-700':'bg-slate-100 text-slate-600';return `<div class="border rounded-xl p-5 ${cls}"><div class="flex justify-between items-start gap-2"><div><p class="text-xs font-bold text-slate-500">${name}</p><p class="font-black text-2xl mt-1 text-slate-900">${numberValue(v.power).toFixed(1)} <span class="text-sm text-blue-600">kW</span></p><p class="text-xs text-purple-600 font-bold mt-1">${numberValue(v.daily).toFixed(1)} kWh today</p></div><span class="text-[10px] font-bold rounded-full px-2 py-1 ${badge}">${v.total?`${v.active}/${v.total} strings`:'LIVE'}</span></div></div>`}).join('');}

const chart=new Chart(document.getElementById('powerChart').getContext('2d'),{type:'line',data:{labels:[],datasets:[{label:'Plant Output (kW)',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.08)',fill:true,tension:.25,pointRadius:2}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:true,position:'top',align:'end'}},scales:{y:{beginAtZero:true}}}});
function updateCurrentChart(power){const p=indiaParts(),label=`${p.hour}:00`,idx=chart.data.labels.indexOf(label);if(idx>=0){chart.data.datasets[0].data[idx]=power;chart.update('none');}else if(numberValue(power)>0){chart.data.labels.push(label);chart.data.datasets[0].data.push(power);chart.update('none');}}
function loadChart(rows){if(!Array.isArray(rows))return;const labels=[],data=[];rows.forEach(r=>{const val=numberValue(r.vcb_kw)||numberValue(r.inv_total_kw);if(val!==0){labels.push(r.time_label);data.push(val);}});if(labels.length){chart.data.labels=labels;chart.data.datasets[0].data=data;chart.update('none');}}

function requestHistory(device){if(!ws||ws.readyState!==WebSocket.OPEN||!device||requestedHistory.has(device))return;requestedHistory.add(device);ws.send(JSON.stringify({type:'get_daily_data',unit_id:CURRENT_PLANT,device,date:indiaDate()}));}
function handleDailyData(d){const rows=d.data||d.records||d.results||d.values||[];if(!Array.isArray(rows)||!rows.length)return;const sample=rows[0]||{},sampleValues=sample.values||sample.data||sample;const device=String(d.device||d.task||sample.device||'').toLowerCase();const keys=sampleValues&&typeof sampleValues==='object'?Object.keys(sampleValues):[];let type='';if(device.includes('transformer')||keys.some(k=>/oil.*temp|winding.*temp/i.test(k)))type='transformer';else if(device.includes('vcb')||keys.some(k=>/3.*phase.*active.*power|phase-n voltage|active total export/i.test(k)))type='vcb';else if(device.includes('inv')||keys.some(k=>/active.*power|ac.*power/i.test(k)))type='inverter';if(!type)return;const times=[],hourly={};rows.forEach(row=>{const values=row.values||row.data||row,ts=row.timestamp||row.time||row.recorded_at||row.datetime||row.date||'',time=parseHistoryTime(ts);let active=false,power=0;if(type==='vcb'){power=vcbPower(values);active=power>0;}else if(type==='inverter'){power=inverterPower(values);active=power>0;}else{active=Object.entries(values||{}).some(([k,v])=>/oil.*temp|winding.*temp/i.test(k)&&numberValue(v)>0);}if(active&&time)times.push(time);if(type==='vcb'&&time){const h=time.slice(0,2)+':00';hourly[h]=power;}});if(times.length){times.sort();mergeWindow(type,times[0],times[times.length-1]);}if(type==='vcb'&&Object.keys(hourly).length){const labels=Object.keys(hourly).sort();chart.data.labels=labels;chart.data.datasets[0].data=labels.map(k=>hourly[k]);chart.update('none');}}

function handleLive(d){if(!d)return;if(d.type==='daily_data_result'){handleDailyData(d);return;}if(d.unit_id!==CURRENT_PLANT)return;state.lastLive=Date.now();document.getElementById('liveStatus').textContent='LIVE';document.getElementById('liveStatus').className='text-xs font-black text-emerald-600';document.getElementById('headerLiveText').textContent='Live';document.getElementById('headerLiveText').className='text-[10px] font-black text-emerald-600 uppercase tracking-wider hidden sm:inline';const values=d.values||{},task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer');if(isVcb){const p=vcbPower(values);if(values['3 Phase Active Power']!==undefined||p!==0){state.vcbPower=p;state.hasVcb=true;}recordLiveWindow('vcb',p>0);}for(const [k,x] of Object.entries(d.virtualTags||{})){if(/vcb.*today|today.*energy/i.test(k)){const n=Number(typeof x==='object'?x.value:x);if(Number.isFinite(n))state.vcbToday=n;break;}}const isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power/i.test(k)));if(isInv){const name=d.device||'Inverter',old=state.inverters[name]||{},summary=stringSummary(values),daily=inverterDaily(values),power=inverterPower(values);state.inverters[name]={power:power||old.power||0,daily:daily===null?(old.daily||0):daily,active:summary.total?summary.active:(old.active||0),total:summary.total||(old.total||0)};recordLiveWindow('inverter',power>0);requestHistory(name);}if(isTransformer){const active=Object.entries(values).some(([k,v])=>/oil.*temp|winding.*temp/i.test(k)&&numberValue(v)>0);recordLiveWindow('transformer',active);}if(values['raw data']!==undefined)document.getElementById('wmos_rad').textContent=values['raw data'];if(values['pannel temperature']!==undefined)document.getElementById('wmos_ptemp').textContent=values['pannel temperature'];if(values['windspeed']!==undefined)document.getElementById('wmos_wind').textContent=values['windspeed'];render();}
window.handleLive=handleLive;

function connect(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;document.getElementById('headerLiveText').textContent='Connecting';ws=new WebSocket(WS_URL);ws.onopen=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';document.getElementById('headerLiveText').textContent='Live';document.getElementById('headerLiveText').className='text-[10px] font-black text-emerald-600 uppercase tracking-wider hidden sm:inline';ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));requestHistory('VCB');requestHistory('Transformer');};ws.onmessage=e=>{try{handleLive(JSON.parse(e.data));}catch(err){console.warn('WS message error',err);}};ws.onclose=()=>{ws=null;requestedHistory.clear();document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-red-500 rounded-full';document.getElementById('headerLiveText').textContent='Reconnecting';document.getElementById('headerLiveText').className='text-[10px] font-black text-red-600 uppercase tracking-wider hidden sm:inline';document.getElementById('liveStatus').textContent='RECONNECTING';clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}

function hydrateTimes(meta){const t=meta?.operating_times||{};['inverter','vcb','transformer'].forEach(type=>{const x=t[type]||{};mergeWindow(type,x.start||'',x.end||'');});}
async function apiFallback(){try{const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant:CURRENT_PLANT});if(TOKEN)q.set('token',TOKEN);const r=await fetch('api_reports.php?'+q,{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),j=await r.json();if(!j.success||!Array.isArray(j.data))return;hydrateTimes(j.meta);if(!chart.data.labels.length)loadChart(j.data);if(Date.now()-state.lastLive<20000)return;const elapsed=j.data.filter(row=>{const m=String(row.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=nowMinutes();}),row=elapsed.at(-1)||j.data[0],names=j.meta?.inv_names||[];names.forEach((name,i)=>{const old=state.inverters[name]||{};state.inverters[name]={...old,power:numberValue(row['inv'+(i+1)+'_kw']),daily:numberValue(row['inv'+(i+1)+'_kwh'])};});state.hasVcb=!!j.meta?.ht_available;state.vcbPower=numberValue(row.vcb_kw);state.vcbToday=j.meta?.ht_available?numberValue(row.vcb_kwh):null;document.getElementById('liveStatus').textContent='DB FALLBACK';document.getElementById('liveStatus').className='text-xs font-black text-blue-600';render();}catch(_){}}

connect();
setTimeout(apiFallback,3000);
apiTimer=setInterval(apiFallback,30000);
</script>
</body>
</html>