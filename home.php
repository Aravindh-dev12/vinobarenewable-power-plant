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
<title><?php echo htmlspecialchars($plantInfo['name']); ?> - Live Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="sidebar-control.js?v=7" defer></script>
<style>
:root{--surface:#fff;--line:#e2e8f0;--muted:#64748b;--ink:#0f172a;--green:#059669;--blue:#2563eb;--purple:#7c3aed;--amber:#d97706}
body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:var(--ink)}
.dashboard-shell{max-width:1560px;margin:0 auto;width:100%}
.surface{background:var(--surface);border:1px solid var(--line);border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}
.kpi-card{position:relative;overflow:hidden;min-height:150px}
.kpi-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.metric-value{font-variant-numeric:tabular-nums;letter-spacing:-.03em}
.status-dot{width:8px;height:8px;border-radius:999px;display:inline-block}
.section-title{font-size:.75rem;line-height:1rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#475569}
.mini-label{font-size:.625rem;line-height:.875rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8}
.live-ring{box-shadow:0 0 0 4px rgba(16,185,129,.10)}
@media(max-width:767px){.surface{border-radius:14px}.dashboard-shell{padding-left:0!important;padding-right:0!important}}
</style>
</head>
<body>
<div class="min-h-screen flex relative">
<div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
<div id="sidebar-container"></div>

<main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden min-w-0">
<header class="bg-white/95 backdrop-blur px-4 sm:px-6 py-3 sticky top-0 z-20 border-b border-slate-200">
    <div class="dashboard-shell flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <button id="menuBtn" class="md:hidden w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-700 shrink-0" aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[.14em] text-emerald-700">
                    <span>Solar SCADA</span><span class="text-slate-300">/</span><span class="text-slate-500">Live dashboard</span>
                </div>
                <h1 class="text-lg sm:text-xl font-black text-slate-900 truncate mt-0.5">Plant Operations</h1>
            </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <div id="connectionBadge" class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50">
                <span id="refreshPulse" class="status-dot bg-slate-400"></span>
                <div class="leading-tight"><p id="headerLiveText" class="text-[10px] font-black uppercase tracking-wider text-slate-500">Connecting</p><p id="lastUpdateText" class="text-[9px] font-semibold text-slate-400">Waiting for telemetry</p></div>
            </div>
            <div class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-black text-slate-700 tabular-nums"><i class="fa-regular fa-clock mr-1.5 text-slate-400"></i><span id="clockDisplay">--:--:--</span></div>
        </div>
    </div>
</header>

<div class="dashboard-shell p-4 sm:p-6 space-y-5">
    <section class="surface p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-wider"><span class="status-dot bg-emerald-500"></span>SCADA Plant</span>
                    <span id="sourceBadge" class="inline-flex px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-wider">Connecting</span>
                </div>
                <h2 id="profileName" class="text-xl sm:text-2xl font-black text-slate-950 leading-tight"><?php echo htmlspecialchars($plantInfo['name']); ?></h2>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 text-sm text-slate-500">
                    <span id="profileServiceNumber" class="font-bold"><i class="fa-solid fa-bolt mr-1.5 text-emerald-600"></i>Service No. <?php echo htmlspecialchars($plantInfo['service_number']); ?></span>
                    <span id="profileLocation" class="font-semibold"><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i><?php echo htmlspecialchars($plantInfo['location']); ?></span>
                    <span id="profileCapacity" class="font-black text-slate-700"><i class="fa-solid fa-solar-panel mr-1.5 text-slate-400"></i><?php echo htmlspecialchars((string)$plantInfo['capacity']); ?> MW</span>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:flex gap-3 lg:shrink-0">
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 min-w-[120px]"><p class="mini-label">Plant ID</p><p class="text-sm font-black text-slate-800 mt-1"><?php echo htmlspecialchars($currentPlantId); ?></p></div>
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 min-w-[120px]"><p class="mini-label">Data mode</p><p id="dataModeText" class="text-sm font-black text-slate-800 mt-1">Connecting</p></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="surface kpi-card p-5">
            <div class="flex items-start justify-between"><div class="kpi-icon bg-blue-50 text-blue-600"><i class="fa-solid fa-bolt-lightning"></i></div><span id="powerLivePill" class="text-[9px] font-black px-2 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span></div>
            <p class="mini-label mt-5">Active Power</p>
            <p id="vcb_active" class="metric-value text-3xl font-black text-slate-950 mt-1">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
            <div class="flex items-center justify-between gap-3 mt-3"><p id="powerSource" class="text-[11px] text-slate-500 font-semibold">Waiting for live power</p><p class="text-[11px] text-slate-400 whitespace-nowrap">Peak <span id="vcb_peak" class="font-black text-slate-600">--</span></p></div>
        </div>

        <div class="surface kpi-card p-5">
            <div class="flex items-start justify-between"><div class="kpi-icon bg-purple-50 text-purple-600"><i class="fa-solid fa-chart-area"></i></div><span class="text-[9px] font-black px-2 py-1 rounded-full bg-purple-50 text-purple-700">TODAY</span></div>
            <p class="mini-label mt-5">Energy Generated</p>
            <p id="vcb_etoday" class="metric-value text-3xl font-black text-slate-950 mt-1">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
            <p id="energySource" class="text-[11px] text-slate-500 font-semibold mt-3">Waiting for live inverter data</p>
        </div>

        <div class="surface kpi-card p-5">
            <div class="flex items-start justify-between"><div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="fa-solid fa-server"></i></div><span id="inverterHealthPill" class="text-[9px] font-black px-2 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span></div>
            <p class="mini-label mt-5">Inverter Fleet</p>
            <p class="metric-value text-3xl font-black text-slate-950 mt-1"><span id="inverterCount">0</span> <span class="text-sm font-bold text-emerald-600">units</span></p>
            <p class="text-[11px] text-slate-500 font-semibold mt-3"><span id="stringHealth">--</span> active strings</p>
        </div>

        <div class="surface kpi-card p-5">
            <div class="flex items-start justify-between"><div class="kpi-icon bg-amber-50 text-amber-600"><i class="fa-solid fa-sun"></i></div><span class="text-[9px] font-black px-2 py-1 rounded-full bg-amber-50 text-amber-700">WEATHER</span></div>
            <p class="mini-label mt-5">Solar Conditions</p>
            <p class="metric-value text-3xl font-black text-slate-950 mt-1"><span id="wmos_rad">--</span> <span class="text-sm font-bold text-amber-600">W/m²</span></p>
            <div class="flex gap-4 mt-3 text-[11px] text-slate-500 font-semibold"><span><i class="fa-solid fa-temperature-half mr-1 text-slate-400"></i><span id="wmos_ptemp">--</span> °C</span><span><i class="fa-solid fa-wind mr-1 text-slate-400"></i><span id="wmos_wind">--</span> m/s</span></div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="surface p-5 sm:p-6 xl:col-span-2 min-h-[390px]">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div><p class="section-title">Generation Curve</p><p class="text-sm text-slate-500 mt-1">Today · live WebSocket with historical backfill</p></div>
                <div class="flex items-center gap-2"><span class="status-dot bg-blue-500"></span><span class="text-[10px] font-bold text-slate-500">Plant output (kW)</span></div>
            </div>
            <div class="relative w-full" style="height:310px"><canvas id="powerChart"></canvas></div>
        </div>

        <div class="surface p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 mb-5"><div><p class="section-title">Generation Window</p><p class="text-xs text-slate-500 mt-1">First and latest active time today</p></div><span id="htAvailability" class="text-[9px] font-black rounded-full bg-slate-100 text-slate-500 px-2.5 py-1.5 whitespace-nowrap">HT OPTIONAL</span></div>
            <div class="space-y-3">
                <div class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-server text-xs"></i></span><div><p class="text-xs font-black text-slate-800">Inverters</p><p id="inverterWindowState" class="text-[9px] font-black text-slate-400 mt-0.5">WAITING</p></div></div><div class="text-right"><p class="mini-label">Start → Latest</p><p class="font-black text-slate-800 tabular-nums mt-1"><span id="inverter_start_time">--:--</span> <span class="text-slate-300 mx-1">→</span> <span id="inverter_end_time">--:--</span></p></div></div></div>
                <div class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><span class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center"><i class="fa-solid fa-bolt text-xs"></i></span><div><p class="text-xs font-black text-slate-800">HT / VCB</p><p id="vcbWindowState" class="text-[9px] font-black text-slate-400 mt-0.5">WAITING</p></div></div><div class="text-right"><p class="mini-label">Start → Latest</p><p class="font-black text-slate-800 tabular-nums mt-1"><span id="vcb_start_time">--:--</span> <span class="text-slate-300 mx-1">→</span> <span id="vcb_end_time">--:--</span></p></div></div></div>
                <div class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><span class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><i class="fa-solid fa-temperature-half text-xs"></i></span><div><p class="text-xs font-black text-slate-800">Transformer</p><p id="transformerWindowState" class="text-[9px] font-black text-slate-400 mt-0.5">WAITING</p></div></div><div class="text-right"><p class="mini-label">Start → Latest</p><p class="font-black text-slate-800 tabular-nums mt-1"><span id="transformer_start_time">--:--</span> <span class="text-slate-300 mx-1">→</span> <span id="transformer_end_time">--:--</span></p></div></div></div>
            </div>
        </div>
    </section>

    <section class="surface p-5 sm:p-6">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
            <div><p class="section-title">Inverter Fleet</p><p class="text-sm text-slate-500 mt-1">Live output, daily generation and string availability</p></div>
            <span id="liveStatus" class="inline-flex items-center gap-2 text-[10px] font-black text-slate-500 bg-slate-100 rounded-full px-3 py-1.5"><span class="status-dot bg-slate-400"></span>CONNECTING</span>
        </div>
        <div id="inv_grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3"><div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-slate-50 py-10 text-center text-sm font-semibold text-slate-400">Waiting for live inverter telemetry…</div></div>
    </section>
</div>
</main>
</div>

<script>
const CURRENT_PLANT=<?php echo json_encode($currentPlantId); ?>;
const PLANT_INFO=<?php echo json_encode($plantInfo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const TOKEN=new URLSearchParams(location.search).get('token')||sessionStorage.getItem('vs_token')||localStorage.getItem('vs_token')||'';
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const state={inverters:{},vcbPower:0,hasVcb:false,vcbToday:null,peakPower:0,lastLive:0,lastUpdate:''};
const windows={inverter:{start:'',end:''},vcb:{start:'',end:''},transformer:{start:'',end:''}};
const requestedHistory=new Set();
let ws=null,reconnectTimer=null,apiTimer=null;

function indiaParts(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return o;}
function indiaDate(){const p=indiaParts();return `${p.year}-${p.month}-${p.day}`;}
function nowMinutes(){const p=indiaParts();return Number(p.hour)*60+Number(p.minute);}
function currentTime(){const p=indiaParts();return `${p.hour}:${p.minute}`;}
function clockTick(){const p=indiaParts();document.getElementById('clockDisplay').textContent=`${p.hour}:${p.minute}:${p.second}`;}
clockTick();setInterval(clockTick,1000);

function numberValue(v){const n=Number(v);return Number.isFinite(n)?n:0;}
function inverterPower(values){for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(x)&&!/reactive|apparent|3.phase/.test(x))return numberValue(v);}return 0;}
function vcbPower(values){if(values&&values['3 Phase Active Power']!==undefined)return numberValue(values['3 Phase Active Power']);for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/3.*phase.*active.*power|active.*power/.test(x)&&!/reactive|apparent/.test(x))return numberValue(v);}return 0;}
function inverterDaily(values){for(const [k,v] of Object.entries(values||{}))if(/daily.*generation|daily.*gen/i.test(k))return numberValue(v);return null;}
function stringSummary(values){let active=0,total=0;for(const [k,v] of Object.entries(values||{})){const x=k.toLowerCase();if(/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(x))continue;if(/\d/.test(k)&&/curr|current|amp/i.test(k)){total++;if(numberValue(v)>.5)active++;}}return {active,total};}
function inverterTotalPower(){return Object.values(state.inverters).reduce((s,v)=>s+numberValue(v.power),0);}
function inverterTotalEnergy(){return Object.values(state.inverters).reduce((s,v)=>s+numberValue(v.daily),0);}
function activePower(){return state.hasVcb?state.vcbPower:inverterTotalPower();}
function todayEnergy(){return state.vcbToday!==null?state.vcbToday:inverterTotalEnergy();}

function setConnection(mode){const pulse=document.getElementById('refreshPulse'),header=document.getElementById('headerLiveText'),source=document.getElementById('sourceBadge'),dataMode=document.getElementById('dataModeText'),live=document.getElementById('liveStatus'),powerPill=document.getElementById('powerLivePill');let cls='bg-slate-400',text='Connecting',badge='bg-slate-100 text-slate-500';if(mode==='live'){cls='bg-emerald-500 live-ring';text='Live WebSocket';badge='bg-emerald-50 text-emerald-700';}else if(mode==='db'){cls='bg-blue-500';text='DB fallback';badge='bg-blue-50 text-blue-700';}else if(mode==='reconnecting'){cls='bg-red-500';text='Reconnecting';badge='bg-red-50 text-red-700';}pulse.className='status-dot '+cls;header.textContent=text;header.className='text-[10px] font-black uppercase tracking-wider '+(mode==='live'?'text-emerald-600':mode==='db'?'text-blue-600':mode==='reconnecting'?'text-red-600':'text-slate-500');source.textContent=text;source.className='inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider '+badge;dataMode.textContent=text;live.innerHTML=`<span class="status-dot ${cls.split(' ')[0]}"></span>${text.toUpperCase()}`;live.className='inline-flex items-center gap-2 text-[10px] font-black rounded-full px-3 py-1.5 '+badge;powerPill.textContent=mode==='live'?'LIVE':mode==='db'?'DB':'WAITING';powerPill.className='text-[9px] font-black px-2 py-1 rounded-full '+badge;}
function markUpdate(label){state.lastUpdate=label||new Date().toLocaleTimeString('en-IN',{hour12:false});document.getElementById('lastUpdateText').textContent='Updated '+state.lastUpdate;}

function renderWindow(type){const item=windows[type];const start=document.getElementById(type+'_start_time'),end=document.getElementById(type+'_end_time'),badge=document.getElementById(type+'WindowState');if(start)start.textContent=item.start||'--:--';if(end)end.textContent=item.end||'--:--';if(badge){badge.textContent=item.start?'ACTIVE TODAY':'WAITING';badge.className='text-[9px] font-black mt-0.5 '+(item.start?'text-emerald-600':'text-slate-400');}}
function mergeWindow(type,start,end){if(!windows[type])return;if(start&&(!windows[type].start||start<windows[type].start))windows[type].start=start;if(end&&(!windows[type].end||end>windows[type].end))windows[type].end=end;renderWindow(type);}
function recordLiveWindow(type,active){if(!active)return;const t=currentTime();mergeWindow(type,t,t);}
function parseHistoryTime(raw){const m=String(raw||'').match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);if(!m)return '';return String(Number(m[1])).padStart(2,'0')+':'+m[2];}

function render(){const power=activePower(),energy=todayEnergy(),inv=Object.values(state.inverters),activeStrings=inv.reduce((s,x)=>s+numberValue(x.active),0),totalStrings=inv.reduce((s,x)=>s+numberValue(x.total),0);state.peakPower=Math.max(state.peakPower,power);document.getElementById('vcb_active').innerHTML=power.toFixed(2)+' <span class="text-sm font-bold text-blue-600">kW</span>';document.getElementById('vcb_etoday').innerHTML=energy.toFixed(2)+' <span class="text-sm font-bold text-purple-600">kWh</span>';document.getElementById('vcb_peak').textContent=state.peakPower.toFixed(2)+' kW';document.getElementById('powerSource').textContent=state.hasVcb?'HT / VCB active power':'Combined inverter active power';document.getElementById('energySource').textContent=state.vcbToday!==null?'HT / VCB today energy':'Combined inverter daily generation';document.getElementById('htAvailability').textContent=state.hasVcb||state.vcbToday!==null?'HT DATA LIVE':'HT OPTIONAL';document.getElementById('htAvailability').className='text-[9px] font-black rounded-full px-2.5 py-1.5 whitespace-nowrap '+(state.hasVcb||state.vcbToday!==null?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-500');document.getElementById('inverterCount').textContent=inv.length;document.getElementById('stringHealth').textContent=totalStrings?`${activeStrings}/${totalStrings}`:'No string telemetry';const health=document.getElementById('inverterHealthPill');if(inv.length){const healthy=totalStrings===0||activeStrings>0;health.textContent=healthy?'ONLINE':'CHECK';health.className='text-[9px] font-black px-2 py-1 rounded-full '+(healthy?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-700');}renderInverters();updateCurrentChart(power);}
function renderInverters(){const grid=document.getElementById('inv_grid'),keys=Object.keys(state.inverters).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));if(!keys.length)return;grid.innerHTML=keys.map(name=>{const v=state.inverters[name],good=v.total>0&&v.active>=Math.min(22,v.total),bad=v.total>0&&v.active===0,online=numberValue(v.power)>0||numberValue(v.daily)>0;const border=bad?'border-red-200':good?'border-emerald-200':'border-slate-200',dot=bad?'bg-red-500':online?'bg-emerald-500':'bg-slate-400',badge=bad?'bg-red-50 text-red-700':online?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-500';return `<div class="rounded-xl border ${border} bg-white p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-black text-slate-700 truncate">${name}</p><p class="metric-value text-2xl font-black text-slate-950 mt-1">${numberValue(v.power).toFixed(1)} <span class="text-xs font-bold text-blue-600">kW</span></p></div><span class="inline-flex items-center gap-1.5 text-[9px] font-black px-2 py-1 rounded-full ${badge}"><span class="status-dot ${dot}"></span>${bad?'CHECK':online?'LIVE':'IDLE'}</span></div><div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-slate-100"><div><p class="mini-label">Today</p><p class="text-sm font-black text-slate-700 mt-1">${numberValue(v.daily).toFixed(1)} kWh</p></div><div><p class="mini-label">Strings</p><p class="text-sm font-black text-slate-700 mt-1">${v.total?`${v.active}/${v.total}`:'--'}</p></div></div></div>`}).join('');}

const chart=new Chart(document.getElementById('powerChart').getContext('2d'),{type:'line',data:{labels:[],datasets:[{label:'Plant Output',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.07)',fill:true,tension:.28,pointRadius:0,pointHoverRadius:4,borderWidth:2.5}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{displayColors:false}},scales:{x:{grid:{display:false},ticks:{color:'#94a3b8',maxTicksLimit:12}},y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{color:'#94a3b8'},title:{display:true,text:'kW',color:'#94a3b8'}}}}});
function updateCurrentChart(power){const p=indiaParts(),label=`${p.hour}:00`,idx=chart.data.labels.indexOf(label);if(idx>=0){chart.data.datasets[0].data[idx]=power;chart.update('none');}else if(numberValue(power)>0){chart.data.labels.push(label);chart.data.datasets[0].data.push(power);chart.update('none');}}
function loadChart(rows){if(!Array.isArray(rows))return;const labels=[],data=[];rows.forEach(r=>{const val=numberValue(r.vcb_kw)||numberValue(r.inv_total_kw);if(val!==0){labels.push(r.time_label);data.push(val);}});if(labels.length){chart.data.labels=labels;chart.data.datasets[0].data=data;chart.update('none');}}

function requestHistory(device){if(!ws||ws.readyState!==WebSocket.OPEN||!device||requestedHistory.has(device))return;requestedHistory.add(device);ws.send(JSON.stringify({type:'get_daily_data',unit_id:CURRENT_PLANT,device,date:indiaDate()}));}
function handleDailyData(d){const rows=d.data||d.records||d.results||d.values||[];if(!Array.isArray(rows)||!rows.length)return;const sample=rows[0]||{},sampleValues=sample.values||sample.data||sample;const device=String(d.device||d.task||sample.device||'').toLowerCase();const keys=sampleValues&&typeof sampleValues==='object'?Object.keys(sampleValues):[];let type='';if(device.includes('transformer')||keys.some(k=>/oil.*temp|winding.*temp/i.test(k)))type='transformer';else if(device.includes('vcb')||keys.some(k=>/3.*phase.*active.*power|phase-n voltage|active total export/i.test(k)))type='vcb';else if(device.includes('inv')||keys.some(k=>/active.*power|ac.*power/i.test(k)))type='inverter';if(!type)return;const times=[],hourly={};rows.forEach(row=>{const values=row.values||row.data||row,ts=row.timestamp||row.time||row.recorded_at||row.datetime||row.date||'',time=parseHistoryTime(ts);let active=false,power=0;if(type==='vcb'){power=vcbPower(values);active=power>0;}else if(type==='inverter'){power=inverterPower(values);active=power>0;}else{active=Object.entries(values||{}).some(([k,v])=>/oil.*temp|winding.*temp/i.test(k)&&numberValue(v)>0);}if(active&&time)times.push(time);if(type==='vcb'&&time){const h=time.slice(0,2)+':00';hourly[h]=power;}});if(times.length){times.sort();mergeWindow(type,times[0],times[times.length-1]);}if(type==='vcb'&&Object.keys(hourly).length){const labels=Object.keys(hourly).sort();chart.data.labels=labels;chart.data.datasets[0].data=labels.map(k=>hourly[k]);chart.update('none');}}

function handleLive(d){if(!d)return;if(d.type==='daily_data_result'){handleDailyData(d);return;}if(d.unit_id!==CURRENT_PLANT)return;state.lastLive=Date.now();setConnection('live');markUpdate(d.time||new Date().toLocaleTimeString('en-IN',{hour12:false}));const values=d.values||{},task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer');if(isVcb){const p=vcbPower(values);if(values['3 Phase Active Power']!==undefined||p!==0){state.vcbPower=p;state.hasVcb=true;}recordLiveWindow('vcb',p>0);}for(const [k,x] of Object.entries(d.virtualTags||{})){if(/vcb.*today|today.*energy/i.test(k)){const n=Number(typeof x==='object'?x.value:x);if(Number.isFinite(n))state.vcbToday=n;break;}}const isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power/i.test(k)));if(isInv){const name=d.device||'Inverter',old=state.inverters[name]||{},summary=stringSummary(values),daily=inverterDaily(values),power=inverterPower(values);state.inverters[name]={power:power||old.power||0,daily:daily===null?(old.daily||0):daily,active:summary.total?summary.active:(old.active||0),total:summary.total||(old.total||0)};recordLiveWindow('inverter',power>0);requestHistory(name);}if(isTransformer){const active=Object.entries(values).some(([k,v])=>/oil.*temp|winding.*temp/i.test(k)&&numberValue(v)>0);recordLiveWindow('transformer',active);}if(values['raw data']!==undefined)document.getElementById('wmos_rad').textContent=values['raw data'];if(values['pannel temperature']!==undefined)document.getElementById('wmos_ptemp').textContent=values['pannel temperature'];if(values['windspeed']!==undefined)document.getElementById('wmos_wind').textContent=values['windspeed'];render();}
window.handleLive=handleLive;

function connect(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setConnection('connecting');ws=new WebSocket(WS_URL);ws.onopen=()=>{setConnection('live');markUpdate('connected');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));requestHistory('VCB');requestHistory('Transformer');};ws.onmessage=e=>{try{handleLive(JSON.parse(e.data));}catch(err){console.warn('WS message error',err);}};ws.onclose=()=>{ws=null;requestedHistory.clear();setConnection('reconnecting');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}

function hydrateTimes(meta){const t=meta?.operating_times||{};['inverter','vcb','transformer'].forEach(type=>{const x=t[type]||{};mergeWindow(type,x.start||'',x.end||'');});}
async function apiFallback(){try{const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant:CURRENT_PLANT});if(TOKEN)q.set('token',TOKEN);const r=await fetch('api_reports.php?'+q,{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),j=await r.json();if(!j.success||!Array.isArray(j.data))return;hydrateTimes(j.meta);if(!chart.data.labels.length)loadChart(j.data);if(Date.now()-state.lastLive<20000)return;const elapsed=j.data.filter(row=>{const m=String(row.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=nowMinutes();}),row=elapsed.at(-1)||j.data[0],names=j.meta?.inv_names||[];names.forEach((name,i)=>{const old=state.inverters[name]||{};state.inverters[name]={...old,power:numberValue(row['inv'+(i+1)+'_kw']),daily:numberValue(row['inv'+(i+1)+'_kwh'])};});state.hasVcb=!!j.meta?.ht_available;state.vcbPower=numberValue(row.vcb_kw);state.vcbToday=j.meta?.ht_available?numberValue(row.vcb_kwh):null;setConnection('db');markUpdate('database');render();}catch(_){}}

setConnection('connecting');
connect();
setTimeout(apiFallback,4000);
apiTimer=setInterval(apiFallback,30000);
</script>
</body>
</html>
