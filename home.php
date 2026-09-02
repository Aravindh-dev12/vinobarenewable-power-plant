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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($plantInfo['name']); ?> - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=9" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl">&#9776;</button>
                <div>
                    <h2 class="text-xl font-black text-slate-800 tracking-tight">Live Plant Telemetry</h2>
                    <p class="text-xs text-slate-500 hidden sm:block">Real-time SCADA monitoring</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                <span id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></span>
                <span id="liveText" class="text-[10px] font-bold text-slate-500 hidden sm:inline">CONNECTING</span>
                <span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline border-l border-slate-200 pl-3">--:--:--</span>
            </div>
        </header>

        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
            <section class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Plant Profile</h3>
                    <p class="font-black text-slate-800 text-lg leading-snug"><?php echo htmlspecialchars($plantInfo['name']); ?></p>
                    <p class="text-[11px] font-bold text-emerald-700 mt-1"><i class="fa-solid fa-bolt mr-1"></i>Service No. <?php echo htmlspecialchars($plantInfo['service_number']); ?></p>
                    <div class="flex items-baseline gap-2 mt-3">
                        <p class="font-black text-emerald-600 text-2xl"><?php echo htmlspecialchars((string)$plantInfo['capacity']); ?> <span class="text-sm font-bold">MW</span></p>
                        <p class="text-xs text-slate-500 font-medium border-l border-slate-200 pl-2"><?php echo htmlspecialchars($plantInfo['location']); ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Today Energy</h3>
                    <p id="vcb_etoday" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
                    <p id="energySource" class="text-xs text-slate-500 mt-1">Waiting for telemetry</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Live Active Power</h3>
                    <p id="vcb_active" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
                    <p class="text-xs text-slate-500 mt-1">Peak Today: <span id="vcb_peak" class="font-bold text-slate-700">--</span> kW</p>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest"><i class="fa-regular fa-clock text-emerald-500 mr-2"></i>Generation Start &amp; End Time</h3>
                    <span id="windowSource" class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Waiting</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                        <div class="flex items-center justify-between mb-3"><p class="text-xs font-black text-blue-700 uppercase">Combined Inverters</p><span id="inverter_time_status" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-1">WAITING</span></div>
                        <div class="grid grid-cols-2 gap-3"><div><p class="text-[10px] font-bold text-slate-400 uppercase">Start Time</p><p id="inverter_start_time" class="text-xl font-black mt-1">--:--</p></div><div><p class="text-[10px] font-bold text-slate-400 uppercase">End / Latest</p><p id="inverter_end_time" class="text-xl font-black mt-1">--:--</p></div></div>
                    </div>
                    <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-4">
                        <div class="flex items-center justify-between mb-3"><p class="text-xs font-black text-purple-700 uppercase">HT Panel (VCB)</p><span id="vcb_time_status" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-1">WAITING</span></div>
                        <div class="grid grid-cols-2 gap-3"><div><p class="text-[10px] font-bold text-slate-400 uppercase">Start Time</p><p id="vcb_start_time" class="text-xl font-black mt-1">--:--</p></div><div><p class="text-[10px] font-bold text-slate-400 uppercase">End / Latest</p><p id="vcb_end_time" class="text-xl font-black mt-1">--:--</p></div></div>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                        <div class="flex items-center justify-between mb-3"><p class="text-xs font-black text-orange-700 uppercase">Transformer</p><span id="transformer_time_status" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-1">WAITING</span></div>
                        <div class="grid grid-cols-2 gap-3"><div><p class="text-[10px] font-bold text-slate-400 uppercase">Start Time</p><p id="transformer_start_time" class="text-xl font-black mt-1">--:--</p></div><div><p class="text-[10px] font-bold text-slate-400 uppercase">End / Latest</p><p id="transformer_end_time" class="text-xl font-black mt-1">--:--</p></div></div>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 h-[400px] flex flex-col">
                <div class="flex items-center justify-between mb-4 shrink-0">
                    <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest"><i class="fa-solid fa-chart-line text-blue-500 mr-2"></i>Generation Curve (Today)</h3>
                    <span id="chartSource" class="text-[10px] font-bold text-slate-400">Loading history</span>
                </div>
                <div class="relative w-full flex-1"><canvas id="powerChart"></canvas></div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-5">
                    <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest">Inverter Field Array <span class="text-[10px] lowercase text-slate-400 font-medium">(Active Strings &amp; Power)</span></h3>
                    <span id="inverterCount" class="text-[10px] font-bold text-slate-500">0 inverters</span>
                </div>
                <div id="inv_grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"><div class="col-span-full text-center text-sm text-slate-400 py-8">Waiting for inverter telemetry...</div></div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Radiation</p><p class="mt-2 text-2xl font-black text-slate-800"><span id="wmos_rad">--</span> <span class="text-sm font-bold text-orange-500">W/m²</span></p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Panel Temp</p><p class="mt-2 text-2xl font-black text-slate-800"><span id="wmos_ptemp">--</span> <span class="text-sm font-bold text-red-500">°C</span></p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Wind Speed</p><p class="mt-2 text-2xl font-black text-slate-800"><span id="wmos_wind">--</span> <span class="text-sm font-bold text-sky-500">m/s</span></p></div>
            </section>
        </div>
    </main>
</div>

<div id="stringModal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center p-4"><div class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden"><div class="p-5 border-b flex items-center justify-between"><h3 id="stringModalTitle" class="font-black">String Details</h3><button onclick="closeStringModal()" class="w-9 h-9 bg-slate-100 rounded-lg"><i class="fa-solid fa-xmark"></i></button></div><div class="p-5 overflow-y-auto"><div id="stringGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-3"></div></div></div></div>

<script>
const CURRENT_PLANT=<?php echo json_encode($currentPlantId); ?>;
const TOKEN=new URLSearchParams(location.search).get('token')||sessionStorage.getItem('vs_token')||localStorage.getItem('vs_token')||'';
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const state={inverters:{},vcbPower:0,hasVcb:false,vcbToday:null,peak:0,lastLive:0};
const windows={inverter:{start:'',end:''},vcb:{start:'',end:''},transformer:{start:'',end:''}};
const requestedHistory=new Set();
let ws=null,reconnectTimer=null,chartHistoryLoaded=false;

function indiaParts(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return o}
function indiaDate(){const p=indiaParts();return `${p.year}-${p.month}-${p.day}`}
function currentTime(){const p=indiaParts();return `${p.hour}:${p.minute}`}
function currentMinutes(){const p=indiaParts();return Number(p.hour)*60+Number(p.minute)}
function n(v){const x=Number(v);return Number.isFinite(x)?x:0}
function tickClock(){const p=indiaParts();document.getElementById('clockDisplay').textContent=`${p.hour}:${p.minute}:${p.second}`}tickClock();setInterval(tickClock,1000);

function inverterPower(v){for(const [k,x] of Object.entries(v||{})){const s=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(s)&&!/reactive|apparent|3.phase/.test(s))return n(x)}return 0}
function inverterDaily(v){for(const [k,x] of Object.entries(v||{}))if(/daily.*generation|daily.*gen/i.test(k))return n(x);return null}
function vcbPower(v){if(v&&v['3 Phase Active Power']!==undefined)return n(v['3 Phase Active Power']);for(const [k,x] of Object.entries(v||{})){const s=k.toLowerCase();if(/3.*phase.*active.*power|active.*power/.test(s)&&!/reactive|apparent/.test(s))return n(x)}return 0}
function vcbToday(d){for(const [k,x] of Object.entries(d.virtualTags||{}))if(/vcb.*today|today.*energy/i.test(k))return n(x&&typeof x==='object'?x.value:x);return null}
function stringData(values){const groups={},out=[];for(const [k,v] of Object.entries(values||{})){const lower=k.toLowerCase();if(/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(lower))continue;const m=k.match(/(\d+)/);if(!m)continue;const no=Number(m[1]);(groups[no]??=[]).push([k,v])}Object.entries(groups).forEach(([no,items])=>{let current=null,voltage=null;items.forEach(([k,v])=>{const s=k.toLowerCase();if(current===null&&/curr|current|amp/.test(s)&&!/phase/.test(s))current=n(v);if(voltage===null&&/volt|voltage/.test(s))voltage=n(v)});if(current!==null)out.push({n:Number(no),curr:current,volt:voltage||0,active:current>.5})});return out.sort((a,b)=>a.n-b.n)}
function totalInvPower(){return Object.values(state.inverters).reduce((s,x)=>s+n(x.power),0)}
function totalInvEnergy(){return Object.values(state.inverters).reduce((s,x)=>s+n(x.daily),0)}
function activePower(){return state.hasVcb?state.vcbPower:totalInvPower()}
function todayEnergy(){return state.vcbToday!==null?state.vcbToday:totalInvEnergy()}

function setConnection(mode){const dot=document.getElementById('refreshPulse'),text=document.getElementById('liveText');if(mode==='live'){dot.className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';text.textContent='LIVE WEBSOCKET';text.className='text-[10px] font-bold text-emerald-600 hidden sm:inline'}else if(mode==='db'){dot.className='w-2.5 h-2.5 bg-blue-500 rounded-full';text.textContent='DATABASE FALLBACK';text.className='text-[10px] font-bold text-blue-600 hidden sm:inline'}else if(mode==='error'){dot.className='w-2.5 h-2.5 bg-red-500 rounded-full';text.textContent='RECONNECTING';text.className='text-[10px] font-bold text-red-600 hidden sm:inline'}else{dot.className='w-2.5 h-2.5 bg-slate-400 rounded-full';text.textContent='CONNECTING';text.className='text-[10px] font-bold text-slate-500 hidden sm:inline'}}

function mergeWindow(type,start,end){const w=windows[type];if(!w)return;if(start&&(!w.start||start<w.start))w.start=start;if(end&&(!w.end||end>w.end))w.end=end;paintWindow(type)}
function paintWindow(type){const w=windows[type],start=document.getElementById(type+'_start_time'),end=document.getElementById(type+'_end_time'),badge=document.getElementById(type+'_time_status');if(start)start.textContent=w.start||'--:--';if(end)end.textContent=w.end||'--:--';if(badge){badge.textContent=w.start?'ACTIVE TODAY':'WAITING';badge.className='text-[10px] font-bold rounded-full px-2 py-1 '+(w.start?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500')}}
function recordWindow(type,active){if(!active)return;const t=currentTime();mergeWindow(type,t,t)}
function historyTime(raw){const m=String(raw||'').match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);return m?String(Number(m[1])).padStart(2,'0')+':'+m[2]:''}

const chart=new Chart(document.getElementById('powerChart').getContext('2d'),{type:'line',data:{labels:[],datasets:[{label:'Plant Output (kW)',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.08)',fill:true,tension:.3,pointRadius:2,pointHoverRadius:5,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#64748b',maxTicksLimit:15,maxRotation:0}},y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{color:'#64748b'},title:{display:true,text:'kW'}}}}});
function solarLabels(){const p=indiaParts(),hour=Number(p.hour),end=hour<5?5:Math.min(hour,19),labels=[];for(let h=5;h<=end;h++)labels.push(String(h).padStart(2,'0')+':00');return labels}
function paintHourly(hourly,source){const labels=solarLabels();if(!labels.length)return;chart.data.labels=labels;chart.data.datasets[0].data=labels.map(l=>n(hourly[l]));chart.update('none');chartHistoryLoaded=true;document.getElementById('chartSource').textContent=source}
function updateLiveChart(power){const p=indiaParts(),hour=Number(p.hour);if(hour<5||hour>19)return;const label=String(hour).padStart(2,'0')+':00';let idx=chart.data.labels.indexOf(label);if(idx<0){const hourly={};chart.data.labels.forEach((l,i)=>hourly[l]=chart.data.datasets[0].data[i]);hourly[label]=power;paintHourly(hourly,'Live SCADA')}else{chart.data.datasets[0].data[idx]=power;chart.update('none');document.getElementById('chartSource').textContent='Live SCADA'}}
function loadChartRows(rows,source='Database history'){const hourly={};(rows||[]).forEach(r=>{const m=String(r.time_label||'').match(/^(\d{1,2}):(\d{2})/);if(!m)return;const label=String(Number(m[1])).padStart(2,'0')+':00';const total=n(r.vcb_kw)>0?n(r.vcb_kw):n(r.inv_total_kw);hourly[label]=total});paintHourly(hourly,source)}
function loadWsCurve(rows){const hourly={};(rows||[]).forEach(r=>{const values=r.values||r.data||r,ts=r.timestamp||r.time||r.recorded_at||r.datetime||r.date||'',t=historyTime(ts);if(!t)return;const label=t.slice(0,2)+':00';hourly[label]=vcbPower(values)});if(Object.keys(hourly).length)paintHourly(hourly,'WebSocket history')}

function renderInverters(){const grid=document.getElementById('inv_grid'),names=Object.keys(state.inverters).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));document.getElementById('inverterCount').textContent=names.length+' inverter'+(names.length===1?'':'s');if(!names.length)return;grid.innerHTML=names.map(name=>{const x=state.inverters[name],online=n(x.power)>0||n(x.daily)>0,active=(x.strings||[]).filter(s=>s.active).length,total=(x.strings||[]).length;return `<button type="button" data-name="${encodeURIComponent(name)}" class="inv-card text-left bg-slate-50 rounded-xl border border-slate-200 p-4"><div class="flex justify-between gap-3"><div><p class="text-xs font-black text-slate-700">${name}</p><p class="text-2xl font-black mt-1">${n(x.power).toFixed(1)} <span class="text-xs text-blue-600">kW</span></p></div><span class="w-2.5 h-2.5 mt-1 rounded-full ${online?'bg-emerald-500':'bg-slate-300'}"></span></div><div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-200"><div><p class="text-[9px] font-bold text-slate-400 uppercase">Today</p><p class="text-xs font-black mt-1">${n(x.daily).toFixed(1)} kWh</p></div><div><p class="text-[9px] font-bold text-slate-400 uppercase">Strings</p><p class="text-xs font-black mt-1">${total?active+'/'+total:'--'}</p></div></div></button>`}).join('');grid.querySelectorAll('.inv-card').forEach(b=>b.addEventListener('click',()=>openStringModal(decodeURIComponent(b.dataset.name||''))))}
function render(){const power=activePower(),energy=todayEnergy();state.peak=Math.max(state.peak,power);document.getElementById('vcb_active').innerHTML=power.toFixed(2)+' <span class="text-sm font-bold text-blue-600">kW</span>';document.getElementById('vcb_etoday').innerHTML=energy.toFixed(2)+' <span class="text-sm font-bold text-purple-600">kWh</span>';document.getElementById('vcb_peak').textContent=state.peak.toFixed(2);document.getElementById('energySource').textContent=state.vcbToday!==null?'HT / VCB today energy':'Combined inverter daily generation';renderInverters();updateLiveChart(power)}

function openStringModal(name){const x=state.inverters[name];if(!x)return;document.getElementById('stringModalTitle').textContent=name+' - String Details';document.getElementById('stringGrid').innerHTML=(x.strings||[]).map(s=>`<div class="rounded-lg border ${s.active?'border-emerald-200 bg-emerald-50':'border-slate-200 bg-slate-50'} p-3 text-center"><p class="text-[10px] font-black text-slate-500">STRING ${s.n}</p><p class="text-lg font-black mt-1">${s.curr.toFixed(2)} A</p><p class="text-[10px] text-slate-500 mt-1">${s.volt?s.volt.toFixed(1)+' V':'--'}</p></div>`).join('')||'<p class="col-span-full text-center text-sm text-slate-400 py-6">No string telemetry available.</p>';document.getElementById('stringModal').classList.remove('hidden')}
function closeStringModal(){document.getElementById('stringModal').classList.add('hidden')}window.closeStringModal=closeStringModal;

function requestHistory(device){if(!ws||ws.readyState!==WebSocket.OPEN||!device||requestedHistory.has(device))return;requestedHistory.add(device);ws.send(JSON.stringify({type:'get_daily_data',unit_id:CURRENT_PLANT,device,date:indiaDate()}))}
function handleHistory(d){const rows=d.data||d.records||d.results||d.values||[];if(!Array.isArray(rows)||!rows.length)return;const sample=rows[0]||{},sv=sample.values||sample.data||sample,device=String(d.device||d.task||sample.device||'').toLowerCase(),keys=sv&&typeof sv==='object'?Object.keys(sv):[];let type='';if(device.includes('transformer')||keys.some(k=>/oil.*temp|winding.*temp/i.test(k)))type='transformer';else if(device.includes('vcb')||keys.some(k=>/3.*phase.*active.*power|phase-n voltage|active total export/i.test(k)))type='vcb';else if(device.includes('inv')||keys.some(k=>/active.*power|ac.*power/i.test(k)))type='inverter';if(!type)return;const times=[];rows.forEach(r=>{const values=r.values||r.data||r,t=historyTime(r.timestamp||r.time||r.recorded_at||r.datetime||r.date||'');let active=false;if(type==='vcb')active=vcbPower(values)>0;else if(type==='inverter')active=inverterPower(values)>0;else active=Object.entries(values||{}).some(([k,v])=>/oil.*temp|winding.*temp/i.test(k)&&n(v)>0);if(active&&t)times.push(t)});if(times.length){times.sort();mergeWindow(type,times[0],times[times.length-1])}if(type==='vcb')loadWsCurve(rows)}

function handleLive(d){if(!d)return;if(d.type==='daily_data_result'){handleHistory(d);return}if(d.unit_id!==CURRENT_PLANT)return;state.lastLive=Date.now();setConnection('live');document.getElementById('windowSource').textContent='Live WebSocket';const values=d.values||{},task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer');if(isVcb){const p=vcbPower(values);if(values['3 Phase Active Power']!==undefined||p!==0){state.vcbPower=p;state.hasVcb=true}const today=vcbToday(d);if(today!==null)state.vcbToday=today;recordWindow('vcb',p>0)}const isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power/i.test(k)));if(isInv){const name=d.device||'Inverter',old=state.inverters[name]||{},daily=inverterDaily(values),power=inverterPower(values),strings=stringData(values);state.inverters[name]={power:power||old.power||0,daily:daily===null?n(old.daily):daily,strings:strings.length?strings:(old.strings||[])};recordWindow('inverter',power>0);requestHistory(name)}if(isTransformer){recordWindow('transformer',Object.entries(values).some(([k,v])=>/oil.*temp|winding.*temp/i.test(k)&&n(v)>0))}if(values['raw data']!==undefined)document.getElementById('wmos_rad').textContent=values['raw data'];if(values['pannel temperature']!==undefined)document.getElementById('wmos_ptemp').textContent=values['pannel temperature'];if(values['panel temperature']!==undefined)document.getElementById('wmos_ptemp').textContent=values['panel temperature'];if(values['windspeed']!==undefined)document.getElementById('wmos_wind').textContent=values['windspeed'];render()}
window.handleLive=handleLive;

function connect(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setConnection('connecting');try{ws=new WebSocket(WS_URL);ws.onopen=()=>{setConnection('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));requestHistory('VCB');requestHistory('Transformer')};ws.onmessage=e=>{try{handleLive(JSON.parse(e.data))}catch(_){}};ws.onclose=()=>{ws=null;requestedHistory.clear();setConnection('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000)};ws.onerror=()=>{try{ws.close()}catch(_){}}}catch(_){setConnection('error');reconnectTimer=setTimeout(connect,3000)}}

async function fetchChartHistory(){try{const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant:CURRENT_PLANT,chart:'1'});if(TOKEN)q.set('token',TOKEN);const r=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),j=await r.json();if(j.success&&Array.isArray(j.data))loadChartRows(j.data,'Database history')}catch(_){}}
async function dbFallback(){try{const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant:CURRENT_PLANT});if(TOKEN)q.set('token',TOKEN);const r=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),j=await r.json();if(!j.success||!Array.isArray(j.data)||!j.data.length)return;const oper=j.meta?.operating_times||{};['inverter','vcb','transformer'].forEach(type=>{const x=oper[type]||{};mergeWindow(type,x.start||'',x.end||'')});if(!chartHistoryLoaded)loadChartRows(j.data,'Database history');if(Date.now()-state.lastLive<20000)return;const elapsed=j.data.filter(row=>{const m=String(row.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=currentMinutes()}),row=elapsed.at(-1)||j.data[0],names=j.meta?.inv_names||[];names.forEach((name,i)=>{const old=state.inverters[name]||{};state.inverters[name]={power:n(row['inv'+(i+1)+'_kw']),daily:n(row['inv'+(i+1)+'_kwh']),strings:old.strings||[]}});state.hasVcb=!!j.meta?.ht_available;state.vcbPower=n(row.vcb_kw);state.vcbToday=j.meta?.ht_available?n(row.vcb_kwh):null;setConnection('db');document.getElementById('windowSource').textContent='Database fallback';render()}catch(_){}}

setConnection('connecting');connect();fetchChartHistory();setTimeout(dbFallback,1500);setInterval(dbFallback,30000);
</script>
</body>
</html>
