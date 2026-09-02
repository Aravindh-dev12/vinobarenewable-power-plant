<?php require 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - Transformer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=4" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3"><button id="menuBtn" class="md:hidden text-emerald-600 text-2xl">&#9776;</button><h2 class="text-xl font-black text-slate-800 tracking-tight">Transformer Monitoring</h2></div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100"><div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div><span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span></div>
        </header>
        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5"><h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Oil Temperature</h3><p class="font-black text-slate-800 text-3xl" id="oil_temp">-- <span class="text-sm font-bold text-orange-600">degC</span></p><p class="text-xs text-slate-500 font-medium mt-1">Transformer oil sensor</p></div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5"><h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Winding Temp</h3><p class="font-black text-slate-800 text-3xl" id="wind_temp">-- <span class="text-sm font-bold text-amber-600">degC</span></p><p class="text-xs text-slate-500 font-medium mt-1">Transformer winding sensor</p></div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5"><h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Status</h3><p class="font-black text-slate-500 text-3xl" id="trafo_status">Waiting</p><p class="text-xs text-slate-500 font-medium mt-1">Optional telemetry</p></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-4"><div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</p><h2 class="text-xl font-bold text-slate-900">Transformer Health</h2></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500" id="trafo_badge">No data</span></div>
                <p id="noDataNote" class="mb-4 text-xs text-slate-500">Transformer data will remain unavailable until the SCADA unit publishes it.</p>
                <div class="grid gap-4 md:grid-cols-3 mb-4">
                    <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Oil Temp</p><p class="mt-3 text-2xl font-black text-slate-800" id="oil_detail">-- <span class="text-sm text-orange-600">degC</span></p></div>
                    <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Winding Temp</p><p class="mt-3 text-2xl font-black text-slate-800" id="wind_detail">-- <span class="text-sm text-amber-600">degC</span></p></div>
                    <div class="bg-slate-50 rounded-lg p-4 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase">Last Update</p><p class="mt-3 text-2xl font-black text-slate-800" id="last_update">--</p></div>
                </div>
                <div style="height:280px;"><canvas id="trafoChart"></canvas></div>
            </div>
        </div>
    </main>
</div>
<script>
const urlParams=new URLSearchParams(window.location.search);
const currentPlant=urlParams.get('plant')||'vinoba-1';
const authToken=urlParams.get('token')||sessionStorage.getItem('vs_token')||'';
const plantNames={'vinoba-1':'Vinoba Renewable Energy Private Limited','ssv':'SSV Green Power Private Limited'};
document.getElementById('pageTitle').textContent=(plantNames[currentPlant]||currentPlant)+' - Transformer';
setInterval(()=>document.getElementById('clockDisplay').innerText=new Date().toLocaleTimeString('en-IN',{hour12:false}),1000);
fetch('sidebar.html',{cache:'no-store'}).then(r=>r.text()).then(html=>{
    document.getElementById('sidebar-container').innerHTML=html;
    document.querySelectorAll('#sidebarNav a').forEach(link=>{let href=link.getAttribute('href');if(!href||href.includes('logout'))return;link.setAttribute('href',`${href}?plant=${encodeURIComponent(currentPlant)}&token=${encodeURIComponent(authToken)}`);});
    const pn=document.getElementById('sidebarPlantName');if(pn)pn.textContent=plantNames[currentPlant]||currentPlant;
    if(typeof initSidebar==='function')initSidebar();
    const overlay=document.getElementById('overlay'),sidebar=document.getElementById('sidebar');
    document.getElementById('menuBtn')?.addEventListener('click',()=>{sidebar?.classList.remove('-translate-x-full');overlay?.classList.remove('hidden');});
    document.getElementById('closeSidebarBtn')?.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden');});
    overlay?.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay.classList.add('hidden');});
});
const state={oil:null,winding:null,hasData:false};
const ctx=document.getElementById('trafoChart').getContext('2d');
const chart=new Chart(ctx,{type:'line',data:{labels:[],datasets:[{label:'Oil Temp (degC)',borderColor:'#f97316',borderWidth:2,tension:0,pointRadius:0,data:[]},{label:'Winding Temp (degC)',borderColor:'#f59e0b',borderWidth:2,tension:0,pointRadius:0,data:[]}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:false}}}});
let lastPush=0;
function updateStatus(){
    if(!state.hasData){document.getElementById('trafo_status').textContent='Waiting';return;}
    const warn=(state.oil!==null&&state.oil>80)||(state.winding!==null&&state.winding>100);
    const status=document.getElementById('trafo_status'),badge=document.getElementById('trafo_badge');
    status.textContent=warn?'Warning':'Normal';status.className='font-black text-3xl '+(warn?'text-amber-600':'text-emerald-700');
    badge.textContent=warn?'Check':'Live';badge.className='rounded-full px-3 py-1 text-xs font-bold '+(warn?'bg-amber-100 text-amber-700':'bg-emerald-100 text-emerald-700');
    document.getElementById('noDataNote').classList.add('hidden');
}
function pushPoint(){if(!state.hasData||Date.now()-lastPush<10000)return;lastPush=Date.now();chart.data.labels.push(new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',hour12:false}));chart.data.datasets[0].data.push(state.oil);chart.data.datasets[1].data.push(state.winding);if(chart.data.labels.length>50){chart.data.labels.shift();chart.data.datasets.forEach(ds=>ds.data.shift());}chart.update('none');}
function connect(){
    const ws=new WebSocket('wss://vinobasolar.scadahub.in:5001');
    ws.onopen=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';ws.send(JSON.stringify({type:'subscribe',unit_id:currentPlant}));};
    ws.onmessage=e=>{try{const d=JSON.parse(e.data);if(d.unit_id!==currentPlant)return;const task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),v=d.values||{};if(task!=='transformer'&&!device.includes('transformer'))return;let changed=false;if(v['oil-temp']!==undefined){state.oil=Number(v['oil-temp']);changed=true;document.getElementById('oil_temp').innerHTML=state.oil.toFixed(1)+' <span class="text-sm font-bold text-orange-600">degC</span>';document.getElementById('oil_detail').innerHTML=state.oil.toFixed(1)+' <span class="text-sm font-bold text-orange-600">degC</span>';}if(v['winding-temp']!==undefined){state.winding=Number(v['winding-temp']);changed=true;document.getElementById('wind_temp').innerHTML=state.winding.toFixed(1)+' <span class="text-sm font-bold text-amber-600">degC</span>';document.getElementById('wind_detail').innerHTML=state.winding.toFixed(1)+' <span class="text-sm font-bold text-amber-600">degC</span>';}if(changed){state.hasData=true;document.getElementById('last_update').textContent=d.time||new Date().toLocaleTimeString('en-IN');updateStatus();pushPoint();}}catch(_){}};
    ws.onclose=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-red-500 rounded-full';setTimeout(connect,5000);};
}
connect();
</script>
</body>
</html>
