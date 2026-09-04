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
    <title><?php echo htmlspecialchars($plantInfo['name']); ?> - Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-ui.css?v=8" data-dashboard-ui>
    <script src="sidebar-control.js?v=10" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3"><button id="menuBtn" class="md:hidden text-emerald-600 text-2xl" aria-label="Open menu">&#9776;</button><h2 class="text-xl font-black text-slate-800 tracking-tight">Plant Analytics</h2></div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100"><span id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></span><span id="liveText" class="text-[10px] font-bold text-slate-500 hidden sm:inline">LOADING</span><span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline">--:--:--</span></div>
        </header>
        <div class="p-4 sm:p-6 w-full max-w-[1600px] mx-auto">
            <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 h-[520px] flex flex-col">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 shrink-0">
                    <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Overall Plant Output</p><h2 class="text-xl font-black text-slate-900">Power Trend</h2></div>
                    <span id="graphSource" class="text-[10px] font-bold text-slate-400">Fetching history</span>
                </div>
                <div class="relative flex-1 min-h-0"><canvas id="analyticsChart"></canvas></div>
            </section>
        </div>
    </main>
</div>
<script>
const CURRENT_PLANT=<?php echo json_encode($currentPlant); ?>;
const TOKEN=new URLSearchParams(location.search).get('token')||sessionStorage.getItem('vs_token')||localStorage.getItem('vs_token')||'';
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const invState={};
let ws=null,reconnectTimer=null,vcbPower=0,hasVcb=false;
function num(v){const n=Number(v);return Number.isFinite(n)?n:0;}
function indiaParts(){const o={};new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')o[x.type]=x.value});return o;}
function indiaDate(){const p=indiaParts();return `${p.year}-${p.month}-${p.day}`;}
function tickClock(){const p=indiaParts();document.getElementById('clockDisplay').textContent=`${p.hour}:${p.minute}:${p.second}`;}tickClock();setInterval(tickClock,1000);
function setStatus(mode){const dot=document.getElementById('refreshPulse'),text=document.getElementById('liveText');if(mode==='live'){dot.className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';text.textContent='LIVE';text.className='text-[10px] font-bold text-emerald-600 hidden sm:inline';}else if(mode==='error'){dot.className='w-2.5 h-2.5 bg-red-500 rounded-full';text.textContent='RECONNECTING';text.className='text-[10px] font-bold text-red-600 hidden sm:inline';}else{dot.className='w-2.5 h-2.5 bg-slate-400 rounded-full';text.textContent='LOADING';text.className='text-[10px] font-bold text-slate-500 hidden sm:inline';}}
function inverterPower(values){for(const [k,v] of Object.entries(values||{})){const s=k.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(s)&&!/reactive|apparent|3.phase/.test(s))return num(v);}return 0;}
function currentPower(){const inv=Object.values(invState).reduce((s,v)=>s+num(v),0);return hasVcb&&vcbPower>0?vcbPower:inv;}
const chart=new Chart(document.getElementById('analyticsChart').getContext('2d'),{type:'line',data:{labels:[],datasets:[{label:'Plant Output (kW)',data:[],borderColor:'#2563eb',backgroundColor:'rgba(37,99,235,.08)',fill:true,tension:.25,pointRadius:1.5,pointHoverRadius:4,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#64748b',maxTicksLimit:18,maxRotation:0}},y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{color:'#64748b'},title:{display:true,text:'kW'}}}}});
function rowInvPower(row){const direct=Number(row?.inv_total_kw);if(Number.isFinite(direct))return direct;let total=0;for(const [k,v] of Object.entries(row||{}))if(/^inv\d+_kw$/i.test(k))total+=num(v);return total;}
function loadRows(rows){const labels=[],data=[];(rows||[]).forEach(row=>{const label=String(row.time_label||'');if(!label)return;const v=num(row.vcb_kw),inv=rowInvPower(row);labels.push(label);data.push(v>0?v:inv);});chart.data.labels=labels;chart.data.datasets[0].data=data;chart.update('none');document.getElementById('graphSource').textContent='Database history · '+indiaDate();}
async function fetchGraph(){try{const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant:CURRENT_PLANT});if(TOKEN)q.set('token',TOKEN);const r=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:TOKEN?{Authorization:'Bearer '+TOKEN}:{}}),j=await r.json();if(j.success&&Array.isArray(j.data))loadRows(j.data);else document.getElementById('graphSource').textContent='No history data';}catch(_){document.getElementById('graphSource').textContent='History unavailable';}}
function pushLive(){const p=indiaParts(),label=`${p.hour}:${p.minute}`;const idx=chart.data.labels.indexOf(label);if(idx>=0)chart.data.datasets[0].data[idx]=currentPower();else{chart.data.labels.push(label);chart.data.datasets[0].data.push(currentPower());if(chart.data.labels.length>240){chart.data.labels.shift();chart.data.datasets[0].data.shift();}}chart.update('none');document.getElementById('graphSource').textContent='Live WebSocket + database history';}
function connect(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setStatus('loading');try{ws=new WebSocket(WS_URL);ws.onopen=()=>{setStatus('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));};ws.onmessage=e=>{try{const d=JSON.parse(e.data);if(d.unit_id!==CURRENT_PLANT||d.type==='daily_data_result')return;const values=d.values||{},task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),isVcb=task==='vcb'||device.includes('vcb');if(isVcb){if(values['3 Phase Active Power']!==undefined){vcbPower=num(values['3 Phase Active Power']);hasVcb=true;}}else if(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power/i.test(k))){invState[d.device||'Inverter']=inverterPower(values);}pushLive();}catch(_){}};ws.onclose=()=>{ws=null;setStatus('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}catch(_){setStatus('error');reconnectTimer=setTimeout(connect,3000);}}
fetchGraph();connect();setInterval(fetchGraph,300000);
</script>
</body>
</html>
