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
    <link rel="stylesheet" href="dashboard-ui.css?v=8" data-dashboard-ui>
    <script src="sidebar-control.js?v=10" defer></script>
    <style>
        @keyframes homeZeroBlink{0%,100%{background:#fee2e2;border-color:#fecaca}50%{background:#fff1f2;border-color:#fca5a5}}
        .home-zero-inverter{animation:homeZeroBlink 1s ease-in-out infinite}
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>

    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl" aria-label="Open menu">&#9776;</button>
                <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Live Plant Telemetry</h2><p class="text-xs text-slate-500 hidden sm:block">Real-time SCADA monitoring</p></div>
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
                    <div class="flex items-baseline gap-2 mt-3"><p class="font-black text-emerald-600 text-2xl"><?php echo htmlspecialchars((string)$plantInfo['capacity']); ?> <span class="text-sm font-bold">MW</span></p><p class="text-xs text-slate-500 font-medium border-l border-slate-200 pl-2"><?php echo htmlspecialchars($plantInfo['location']); ?></p></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Today Generation</h3>
                    <p id="plantToday" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
                    <p id="todaySource" class="text-xs text-slate-500 mt-1">Waiting for inverter telemetry</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Live Active Power</h3>
                    <p id="plantPower" class="font-black text-slate-800 text-3xl">-- <span class="text-sm font-bold text-blue-600">kW</span></p>
                    <p class="text-xs text-slate-500 mt-1">Peak Solar Day: <span id="plantPeak" class="font-bold text-slate-700">--</span> kW</p>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <div><h3 class="text-sm font-black text-slate-600 uppercase tracking-widest"><i class="fa-solid fa-cloud-sun text-orange-500 mr-2"></i>WMS - Weather Monitoring System</h3></div>
                    <span id="wmsStatus" class="text-[10px] font-bold text-slate-400 uppercase">Waiting</span>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-orange-50/60 border border-orange-100 p-4"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Irradiance</p><p class="mt-2 text-2xl font-black"><span id="wmos_rad">--</span> <span class="text-xs font-bold text-orange-600">W/m²</span></p></div>
                    <div class="rounded-xl bg-red-50/60 border border-red-100 p-4"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Panel Temperature</p><p class="mt-2 text-2xl font-black"><span id="wmos_ptemp">--</span> <span class="text-xs font-bold text-red-600">°C</span></p></div>
                    <div class="rounded-xl bg-sky-50/60 border border-sky-100 p-4"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Humidity</p><p class="mt-2 text-2xl font-black"><span id="wmos_humidity">--</span> <span class="text-xs font-bold text-sky-600">%</span></p></div>
                    <div class="rounded-xl bg-cyan-50/60 border border-cyan-100 p-4"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Wind Speed</p><p class="mt-2 text-2xl font-black"><span id="wmos_wind">--</span> <span class="text-xs font-bold text-cyan-600">m/s</span></p></div>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 h-[430px] flex flex-col">
                <div class="flex items-center justify-between gap-3 mb-4 shrink-0">
                    <h3 id="curveTitle" class="text-sm font-black text-slate-600 uppercase tracking-widest"><i class="fa-solid fa-chart-line text-blue-500 mr-2"></i>Generation Curve (Today)</h3>
                    <span id="chartSource" class="text-[10px] font-bold text-slate-400 text-right">Loading 5-minute history</span>
                </div>
                <div class="relative w-full flex-1"><canvas id="powerChart"></canvas></div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-5">
                    <div><h3 class="text-sm font-black text-slate-600 uppercase tracking-widest">Inverter Field Array</h3><p class="text-[10px] text-slate-400 mt-1">Actual strings are string current values greater than 0.1 A</p></div>
                    <div class="flex flex-wrap gap-2 text-[10px] font-bold">
                        <span class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2">Total Active Power: <b id="invSummaryPower" class="text-blue-700">-- kW</b></span>
                        <span class="rounded-lg bg-purple-50 border border-purple-100 px-3 py-2">Today Generation: <b id="invSummaryToday" class="text-purple-700">-- kWh</b></span>
                        <span class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2"><b id="inverterCount">0</b> inverters</span>
                    </div>
                </div>
                <div id="inv_grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
            </section>
        </div>
    </main>
</div>

<div id="stringModal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
        <div class="p-5 border-b flex items-center justify-between"><div><h3 id="stringModalTitle" class="font-black">String Details</h3><p class="text-xs text-slate-500 mt-1">Actual string threshold: current &gt; 0.1 A</p></div><button onclick="closeStringModal()" class="w-9 h-9 bg-slate-100 rounded-lg" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="p-5 overflow-y-auto"><div id="stringGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-3"></div></div>
    </div>
</div>

<script>
const CURRENT_PLANT = <?php echo json_encode($currentPlantId); ?>;
const TOKEN = new URLSearchParams(location.search).get('token') || sessionStorage.getItem('vs_token') || localStorage.getItem('vs_token') || '';
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const EXPECTED = CURRENT_PLANT==='vinoba-1' ? {1:23,2:23,3:23,4:23,5:23,6:23,7:22} : {1:23,2:23,3:23,4:23};
const EXPECTED_COUNT = CURRENT_PLANT==='vinoba-1' ? 7 : 4;
const state = {inverters:{},vcbPower:0,hasVcb:false,vcbToday:null,peak:0,lastLive:0};
const requestedHistory = new Set();
const vcbHistory5 = {};
const inverterHistory5 = {};
let ws=null,reconnectTimer=null;

function num(v){const n=Number(v);return Number.isFinite(n)?n:0;}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function invNo(name){const m=String(name||'').match(/(\d+)/);return m?Number(m[1]):0;}
function canonicalInvName(name){const n=invNo(name);return n?`Inverter ${n}`:String(name||'Inverter');}
function expectedStrings(name){return EXPECTED[invNo(name)]||23;}
function indiaParts(date=new Date()){
    const o={};
    new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(date).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});
    return o;
}
function indiaDateFrom(date){const p=indiaParts(date);return `${p.year}-${p.month}-${p.day}`;}
function indiaDate(){return indiaDateFrom(new Date());}
function curveDate(){const p=indiaParts();if(Number(p.hour)>=5)return indiaDate();const midnight=Date.parse(indiaDate()+'T00:00:00+05:30');return indiaDateFrom(new Date(midnight-86400000));}
function curveIsPrevious(){return curveDate()!==indiaDate();}
function tickClock(){const p=indiaParts();document.getElementById('clockDisplay').textContent=`${p.hour}:${p.minute}:${p.second}`;}
tickClock();setInterval(tickClock,1000);

function inverterPower(values){
    for(const [k,v] of Object.entries(values||{})){const s=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(s)&&!/reactive|apparent|3.phase/.test(s))return num(v);}return 0;
}
function inverterDaily(values){for(const [k,v] of Object.entries(values||{}))if(/daily.*generation|daily.*gen/i.test(k))return num(v);return null;}
function vcbPower(values){if(values&&values['3 Phase Active Power']!==undefined)return num(values['3 Phase Active Power']);for(const [k,v] of Object.entries(values||{})){const s=k.toLowerCase();if(/3.*phase.*active.*power|active.*power/.test(s)&&!/reactive|apparent/.test(s))return num(v);}return 0;}
function vcbToday(message){for(const [k,v] of Object.entries(message.virtualTags||{}))if(/vcb.*today|today.*energy/i.test(k))return num(v&&typeof v==='object'?v.value:v);return null;}
function parseStrings(values){
    const groups={};
    for(const key of Object.keys(values||{})){
        const lower=key.toLowerCase();
        if(/phase|3\.phase|three\.phase|freq|temperature|temp|ambient|reactive|apparent|inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|mppt.*curr|dc.*curr/.test(lower))continue;
        const m=key.match(/(\d+)/);if(!m)continue;const no=Number(m[1]);(groups[no]??=[]).push(key);
    }
    const out=[];
    Object.entries(groups).forEach(([no,keys])=>{
        let currKey='',voltKey='';
        for(const key of keys){const s=key.toLowerCase();if(!currKey&&/curr|current|amp/.test(s)&&!/volt|voltage/.test(s))currKey=key;if(!voltKey&&/volt|voltage/.test(s)&&!/curr|current|amp/.test(s))voltKey=key;}
        if(!currKey)return;const curr=num(values[currKey]),volt=voltKey?num(values[voltKey]):0;out.push({n:Number(no),curr,volt,active:curr>0.1});
    });
    return out.sort((a,b)=>a.n-b.n);
}
function totalInvPower(){return Object.values(state.inverters).filter(x=>x.received).reduce((s,x)=>s+num(x.power),0);}
function totalInvToday(){return Object.values(state.inverters).filter(x=>x.received).reduce((s,x)=>s+num(x.daily),0);}
function anyInvReceived(){return Object.values(state.inverters).some(x=>x.received);}
function plantPower(){const inv=totalInvPower();return state.hasVcb&&num(state.vcbPower)>0?num(state.vcbPower):inv;}
function plantToday(){return anyInvReceived()?totalInvToday():(state.vcbToday!==null?num(state.vcbToday):0);}
function setConnection(mode){
    const dot=document.getElementById('refreshPulse'),text=document.getElementById('liveText');
    if(mode==='live'){dot.className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';text.textContent='LIVE';text.className='text-[10px] font-bold text-emerald-600 hidden sm:inline';}
    else if(mode==='db'){dot.className='w-2.5 h-2.5 bg-blue-500 rounded-full';text.textContent='DATABASE';text.className='text-[10px] font-bold text-blue-600 hidden sm:inline';}
    else if(mode==='error'){dot.className='w-2.5 h-2.5 bg-red-500 rounded-full';text.textContent='RECONNECTING';text.className='text-[10px] font-bold text-red-600 hidden sm:inline';}
    else{dot.className='w-2.5 h-2.5 bg-slate-400 rounded-full';text.textContent='CONNECTING';text.className='text-[10px] font-bold text-slate-500 hidden sm:inline';}
}

for(let i=1;i<=EXPECTED_COUNT;i++)state.inverters[`Inverter ${i}`]={deviceName:`Inverter ${i}`,power:null,daily:null,strings:[],stringsSeen:false,received:false};

const chart=new Chart(document.getElementById('powerChart').getContext('2d'),{
    type:'line',
    data:{labels:[],datasets:[{label:'Plant Output (kW)',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.08)',fill:true,tension:.22,pointRadius:0,pointHoverRadius:4,borderWidth:2,spanGaps:true}]},
    options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#64748b',maxTicksLimit:16,maxRotation:0}},y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{color:'#64748b'},title:{display:true,text:'kW'}}}}
});
function bucket5(hour,minute){const m=Math.floor(Number(minute)/5)*5;return String(Number(hour)).padStart(2,'0')+':'+String(m).padStart(2,'0');}
function curveLabels(){
    const p=indiaParts(),h=Number(p.hour),m=Number(p.minute),labels=[];
    let end=19*60;
    if(!curveIsPrevious()&&h<19)end=Math.max(5*60,h*60+Math.floor(m/5)*5);
    for(let mins=5*60;mins<=end;mins+=5)labels.push(String(Math.floor(mins/60)).padStart(2,'0')+':'+String(mins%60).padStart(2,'0'));
    return labels;
}
function historyClock(raw){const m=String(raw||'').match(/(?:T|\s|^)(\d{1,2}):(\d{2})/);return m?{h:Number(m[1]),m:Number(m[2])}:null;}
function combinedHistory(){
    const out={},labels=curveLabels();
    labels.forEach(label=>{
        const v=num(vcbHistory5[label]);
        let inv=0,hasInv=false;
        Object.values(inverterHistory5).forEach(map=>{if(map[label]!==undefined){inv+=num(map[label]);hasInv=true;}});
        if(v>0)out[label]=v;else if(hasInv)out[label]=inv;
    });
    return out;
}
function paintCurve(source){
    const labels=curveLabels(),map=combinedHistory();
    chart.data.labels=labels;chart.data.datasets[0].data=labels.map(l=>map[l]===undefined?null:num(map[l]));chart.update('none');
    const valid=chart.data.datasets[0].data.filter(v=>v!==null).map(num);if(valid.length)state.peak=Math.max(state.peak,...valid);
    document.getElementById('plantPeak').textContent=state.peak?state.peak.toFixed(2):'--';
    document.getElementById('chartSource').textContent=source+' · '+curveDate();
}
function loadReportRows(rows){
    (rows||[]).forEach(row=>{
        const m=String(row.time_label||'').match(/^(\d{1,2}):(\d{2})/);if(!m)return;const label=bucket5(m[1],m[2]);
        const v=num(row.vcb_kw);let inv=Number(row.inv_total_kw);if(!Number.isFinite(inv)){inv=0;for(const [k,val] of Object.entries(row||{}))if(/^inv\d+_kw$/i.test(k))inv+=num(val);}
        if(v>0)vcbHistory5[label]=v;else if(inv>0){inverterHistory5.__db??={};inverterHistory5.__db[label]=inv;}
    });
    paintCurve('5-minute curve');
}
function loadWsHistory(rows,device,type){
    const map={};
    (rows||[]).forEach(row=>{const t=historyClock(row.timestamp||row.time||row.recorded_at||row.datetime||row.date||'');if(!t||t.h<5||t.h>19)return;const values=row.values||row.data||row,label=bucket5(t.h,t.m);map[label]=type==='vcb'?vcbPower(values):inverterPower(values);});
    if(type==='vcb')Object.assign(vcbHistory5,map);else inverterHistory5[device]=map;
    paintCurve('5-minute WebSocket history');
}
function updateLiveCurve(){
    if(curveIsPrevious())return;const p=indiaParts(),h=Number(p.hour),m=Number(p.minute);if(h<5||h>19)return;const label=bucket5(h,m),power=plantPower();
    if(state.hasVcb&&num(state.vcbPower)>0)vcbHistory5[label]=power;else{inverterHistory5.__live??={};inverterHistory5.__live[label]=power;}
    paintCurve('5-minute live curve');
}
function updateCurveHeading(){document.getElementById('curveTitle').innerHTML='<i class="fa-solid fa-chart-line text-blue-500 mr-2"></i>'+(curveIsPrevious()?'Generation Curve (Previous Solar Day)':'Generation Curve (Today)');}
updateCurveHeading();

function renderInverters(){
    const names=Object.keys(state.inverters).sort((a,b)=>invNo(a)-invNo(b)||a.localeCompare(b));
    document.getElementById('inverterCount').textContent=names.filter(n=>state.inverters[n].received).length;
    const any=anyInvReceived();document.getElementById('invSummaryPower').textContent=any?totalInvPower().toFixed(2)+' kW':'-- kW';document.getElementById('invSummaryToday').textContent=any?totalInvToday().toFixed(2)+' kWh':'-- kWh';
    document.getElementById('inv_grid').innerHTML=names.map(name=>{
        const x=state.inverters[name],expected=expectedStrings(name),active=x.strings.filter(s=>s.active).length,zero=x.received&&num(x.power)<=0.01;
        const power=x.received?num(x.power).toFixed(1):'--',daily=x.received&&x.daily!==null?num(x.daily).toFixed(1):'--',stringText=x.stringsSeen?`${active}/${expected}`:`--/${expected}`;
        return `<button type="button" data-name="${encodeURIComponent(name)}" class="inv-overview-card relative text-left rounded-xl border p-4 ${zero?'home-zero-inverter border-red-200':'bg-slate-50 border-slate-200'}">
            <div class="flex justify-between gap-3"><div><p class="text-xs font-black text-slate-700">${esc(name)}</p><p class="text-2xl font-black mt-1">${power} <span class="text-xs text-blue-600">kW</span></p></div><span class="w-8 h-8 rounded-lg border border-blue-200 bg-blue-50 text-blue-600 flex items-center justify-center shrink-0" aria-hidden="true"><i class="fa-solid fa-eye text-xs"></i></span></div>
            <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-200"><div><p class="text-[9px] font-bold text-slate-400 uppercase">Today Generation</p><p class="text-xs font-black mt-1">${daily} kWh</p></div><div><p class="text-[9px] font-bold text-slate-400 uppercase">Actual Strings</p><p class="text-xs font-black mt-1 ${zero?'text-red-700':''}">${stringText}</p></div></div>
        </button>`;
    }).join('');
    document.querySelectorAll('.inv-overview-card').forEach(btn=>btn.addEventListener('click',()=>openStringModal(decodeURIComponent(btn.dataset.name||''))));
}
function render(){
    const power=plantPower(),today=plantToday(),has=anyInvReceived()||state.hasVcb;
    if(has){state.peak=Math.max(state.peak,power);document.getElementById('plantPower').innerHTML=power.toFixed(2)+' <span class="text-sm font-bold text-blue-600">kW</span>';document.getElementById('plantToday').innerHTML=today.toFixed(2)+' <span class="text-sm font-bold text-purple-600">kWh</span>';document.getElementById('plantPeak').textContent=state.peak.toFixed(2);}
    document.getElementById('todaySource').textContent=anyInvReceived()?'Sum of inverter today generation':'Waiting for inverter telemetry';
    renderInverters();updateLiveCurve();
}
function openStringModal(name){
    const x=state.inverters[name];if(!x)return;document.getElementById('stringModalTitle').textContent=name+' - String Details';
    document.getElementById('stringGrid').innerHTML=x.strings.length?x.strings.map(s=>`<div class="rounded-lg border ${s.active?'border-emerald-200 bg-emerald-50':'border-red-100 bg-red-50'} p-3 text-center"><p class="text-[10px] font-black ${s.active?'text-emerald-700':'text-red-600'}">STRING ${s.n}</p><p class="text-lg font-black mt-1">${s.curr.toFixed(2)} A</p><p class="text-[10px] text-slate-500 mt-1">${s.volt?s.volt.toFixed(1)+' V':'--'}</p></div>`).join(''):'<p class="col-span-full text-center text-sm text-slate-400 py-6">No live string telemetry received yet.</p>';
    document.getElementById('stringModal').classList.remove('hidden');
}
function closeStringModal(){document.getElementById('stringModal').classList.add('hidden');}
window.closeStringModal=closeStringModal;

function requestHistory(device){if(!ws||ws.readyState!==WebSocket.OPEN||!device)return;const key=device+'|'+curveDate();if(requestedHistory.has(key))return;requestedHistory.add(key);ws.send(JSON.stringify({type:'get_daily_data',unit_id:CURRENT_PLANT,device,date:curveDate()}));}
function handleHistory(message){
    const rows=message.data||message.records||message.results||message.values||[];if(!Array.isArray(rows)||!rows.length)return;
    const sample=rows[0]||{},values=sample.values||sample.data||sample,deviceName=String(message.device||sample.device||message.task||''),device=deviceName.toLowerCase(),keys=values&&typeof values==='object'?Object.keys(values):[];
    const isVcb=device.includes('vcb')||keys.some(k=>/3.*phase.*active.*power|phase-n voltage|active total export/i.test(k));
    const isInv=!isVcb&&(device.includes('inv')||keys.some(k=>/active.*power|ac.*power|power.*ac/i.test(k)));
    if(isVcb)loadWsHistory(rows,deviceName||'VCB','vcb');else if(isInv)loadWsHistory(rows,deviceName||'Inverter','inverter');
}
function handleLive(message){
    if(!message)return;if(message.type==='daily_data_result'){handleHistory(message);return;}if(message.unit_id!==CURRENT_PLANT)return;
    state.lastLive=Date.now();setConnection('live');const values=message.values||{},task=String(message.task||'').toLowerCase(),deviceRaw=String(message.device||''),device=deviceRaw.toLowerCase();
    const isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer');
    if(isVcb){state.vcbPower=vcbPower(values);state.hasVcb=true;const today=vcbToday(message);if(today!==null)state.vcbToday=today;requestHistory(deviceRaw||'VCB');}
    const isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power|power.*ac/i.test(k)));
    if(isInv){
        const key=canonicalInvName(deviceRaw||'Inverter'),old=state.inverters[key]||{power:null,daily:null,strings:[],stringsSeen:false,received:false},daily=inverterDaily(values),strings=parseStrings(values);
        state.inverters[key]={deviceName:deviceRaw||key,power:inverterPower(values),daily:daily===null?old.daily:daily,strings:strings.length?strings:old.strings,stringsSeen:strings.length?true:old.stringsSeen,received:true};requestHistory(deviceRaw||key);
    }
    let wmsTouched=false;
    for(const [k,v] of Object.entries(values)){
        const key=k.toLowerCase();
        if(key==='raw data'||/irradiance|radiation/.test(key)){document.getElementById('wmos_rad').textContent=v;wmsTouched=true;}
        if(/pannel temperature|panel temperature|module temperature/.test(key)){document.getElementById('wmos_ptemp').textContent=v;wmsTouched=true;}
        if(/humidity|relative humidity/.test(key)){document.getElementById('wmos_humidity').textContent=v;wmsTouched=true;}
        if(/windspeed|wind speed/.test(key)){document.getElementById('wmos_wind').textContent=v;wmsTouched=true;}
    }
    if(wmsTouched)document.getElementById('wmsStatus').textContent='LIVE';
    render();
}
window.handleLive=handleLive;

function connect(){
    if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setConnection('connecting');
    try{ws=new WebSocket(WS_URL);ws.onopen=()=>{setConnection('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));requestHistory('VCB');};ws.onmessage=e=>{try{handleLive(JSON.parse(e.data));}catch(err){console.error(err);}};ws.onclose=()=>{ws=null;requestedHistory.clear();setConnection('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}catch(_){setConnection('error');reconnectTimer=setTimeout(connect,3000);}
}
async function fetchCurveHistory(){
    try{const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:curveDate(),plant:CURRENT_PLANT});if(TOKEN)q.set('token',TOKEN);const response=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),json=await response.json();if(json.success&&Array.isArray(json.data)){loadReportRows(json.data);const names=json.meta?.inv_names||[];names.forEach(name=>{const key=canonicalInvName(name);if(state.inverters[key])state.inverters[key].deviceName=name;requestHistory(name);});}}catch(_){document.getElementById('chartSource').textContent='History unavailable';}
}
async function dbFallback(){
    if(Date.now()-state.lastLive<20000)return;
    try{const p=indiaParts(),mins=Number(p.hour)*60+Number(p.minute),q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant:CURRENT_PLANT});if(TOKEN)q.set('token',TOKEN);const response=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),json=await response.json();if(!json.success||!Array.isArray(json.data))return;const elapsed=json.data.filter(row=>{const m=String(row.time_label||'').match(/^(\d+):(\d+)/);return m&&Number(m[1])*60+Number(m[2])<=mins;});if(!elapsed.length)return;const row=elapsed.at(-1),names=json.meta?.inv_names||[];names.forEach((name,i)=>{const key=canonicalInvName(name),old=state.inverters[key]||{strings:[],stringsSeen:false};state.inverters[key]={deviceName:name,power:num(row['inv'+(i+1)+'_kw']),daily:num(row['inv'+(i+1)+'_kwh']),strings:old.strings||[],stringsSeen:old.stringsSeen||false,received:true};});state.vcbPower=num(row.vcb_kw);state.hasVcb=!!json.meta?.ht_available;state.vcbToday=json.meta?.ht_available?num(row.vcb_kwh):null;setConnection('db');render();}catch(_){}
}

renderInverters();setConnection('connecting');connect();fetchCurveHistory();setTimeout(dbFallback,1500);setInterval(dbFallback,30000);
</script>
</body>
</html>
