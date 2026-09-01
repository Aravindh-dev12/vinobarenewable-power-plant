<?php
$token = isset($_GET['token']) ? $_GET['token'] : '';
$adminUser = null;
if ($token) {
    require 'config.php';
    $safeToken = $conn->real_escape_string($token);
    $res = $conn->query("SELECT * FROM users WHERE auth_token = '$safeToken' LIMIT 1");
    if ($res && $res->num_rows > 0) $adminUser = $res->fetch_assoc();
}
if (!$adminUser || $adminUser['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
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
        body { font-family: 'Inter', sans-serif; }
        .alert-state { animation: alert-glow 1.5s infinite; border-width: 2px !important; }
        @keyframes alert-glow {
            0%,100% { box-shadow: 0 0 5px rgba(239,68,68,.2); border-color: rgba(239,68,68,.4); }
            50% { box-shadow: 0 0 20px rgba(239,68,68,.8); border-color: rgba(239,68,68,1); }
        }
        .status-badge-pulse { animation: pulse 2s cubic-bezier(.4,0,.6,1) infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .5; } }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background:#f1f5f9; border-radius:4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:4px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">
<div class="flex flex-col min-h-screen w-full">
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex flex-wrap justify-between min-h-14 py-2 gap-2 items-center">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded-md flex items-center justify-center"><i class="fa-solid fa-bolt text-sm"></i></div>
                    <h1 class="text-lg font-bold tracking-tight text-slate-800">Vinoba Solar Dashboard</h1>
                </div>
                <div class="flex items-center gap-3 flex-wrap justify-end">
                    <span id="ws-status" class="text-xs font-bold text-red-500"><i class="fa-solid fa-circle text-[8px] mr-1"></i>Connecting...</span>
                    <div class="h-6 w-px bg-slate-200 mx-1 hidden sm:block"></div>
                    <div class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md" title="Live combined active power across all plants">
                        <i class="fa-solid fa-bolt text-amber-500"></i>
                        <span>Overall Active Power</span>
                        <span id="overall-active-power" class="font-black tabular-nums">0.00 kW</span>
                    </div>
                    <button onclick="document.getElementById('manage-users-modal').classList.remove('hidden')" class="px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors"><i class="fa-solid fa-user-plus mr-1"></i>Add User</button>
                    <button onclick="logout()" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 rounded-md transition-colors">Logout</button>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto w-full py-6 px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="plants-container"></div>
    </main>
</div>

<div id="manage-users-modal" class="fixed inset-0 bg-slate-900/50 hidden z-[100] flex items-center justify-center backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-slate-800">Add New User</h3>
            <button onclick="document.getElementById('manage-users-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="addUserForm" class="space-y-4">
            <div><label class="block text-xs font-semibold text-slate-700 mb-1">User Email</label><input type="email" id="new_email" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg"></div>
            <div><label class="block text-xs font-semibold text-slate-700 mb-1">Password</label><input type="password" id="new_password" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg"></div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Plant ID</label>
                <select id="new_plant_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                    <option value="vinoba-velliyanai">Vinoba Velliyanai</option>
                    <option value="makkalpower">Makkal Power</option>
                    <option value="anushyam">Anushyam Plant</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Role</label>
                <select id="new_role" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg"><option value="user">User</option><option value="admin">Admin</option></select>
            </div>
            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-sm transition-colors">Create User</button>
            <p id="user-msg" class="text-xs text-center font-medium mt-2 hidden"></p>
        </form>
    </div>
</div>

<script>
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const plants = [
    { id:'vinoba-velliyanai', name:'Vinoba Velliyanai', theme:'violet' },
    { id:'makkalpower', name:'Makkal Power', theme:'blue' },
    { id:'anushyam', name:'Anushyam Plant', theme:'emerald' }
];
const authToken = new URLSearchParams(window.location.search).get('token') || '';
const plantState = {};
const sockets = {};
const reconnectTimers = {};
const requestedPeakHistory = new Set();
const pendingPeakHistory = {};

plants.forEach(p => {
    plantState[p.id] = {
        vcbPower:0, hasVCB:false, dailyEnergy:0, inverters:{},
        peakInverterKw:0, peakHour:'--:--', hourlyPowerByInverter:{},
        liveCombinedKw:0, lastUpdate:'--', lastLiveAt:0
    };
    pendingPeakHistory[p.id] = [];
});

function indiaParts() {
    const parts = new Intl.DateTimeFormat('en-GB', {timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).formatToParts(new Date());
    const out = {}; parts.forEach(p => { if (p.type !== 'literal') out[p.type] = p.value; });
    return out;
}
function indiaToday() { const p=indiaParts(); return `${p.year}-${p.month}-${p.day}`; }
function indiaTime() { const p=indiaParts(); return `${p.hour}:${p.minute}:${p.second}`; }

function logout() {
    localStorage.removeItem('userRole');
    localStorage.removeItem('vs_token');
    localStorage.removeItem('vs_user');
    sessionStorage.removeItem('vs_token');
    sessionStorage.removeItem('vs_user');
    window.location.href='logout.php';
}

function renderCards() {
    const container=document.getElementById('plants-container');
    container.innerHTML=plants.map(p=>`
        <a href="home.php?plant=${encodeURIComponent(p.id)}&token=${encodeURIComponent(authToken)}" class="block">
            <div id="card-${p.id}" class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col h-[430px] transition-all hover:-translate-y-1 hover:shadow-lg duration-200">
                <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center shrink-0 bg-slate-50">
                    <div class="flex items-center gap-2"><i class="fa-solid fa-solar-panel text-${p.theme}-600"></i><h3 class="text-base font-bold text-slate-800">${p.name}</h3></div>
                    <div id="badge-${p.id}" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-500">Wait...</div>
                </div>
                <div class="p-4 grid grid-cols-2 gap-3 shrink-0">
                    <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><div class="text-slate-400 text-[10px] font-bold uppercase mb-1 tracking-wider"><i class="fa-solid fa-bolt text-yellow-500 mr-1"></i><span id="active-label-${p.id}">Active</span></div><div class="text-sm font-black text-slate-800" id="active-${p.id}">0.00 kW</div></div>
                    <div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><div class="text-slate-400 text-[10px] font-bold uppercase mb-1 tracking-wider"><i class="fa-solid fa-sun text-orange-400 mr-1"></i>Today</div><div class="text-sm font-black text-slate-800" id="today-${p.id}">0.00 kWh</div></div>
                    <div class="col-span-2 bg-amber-50/60 rounded-lg p-3 border border-amber-100 flex items-center justify-between">
                        <div><div class="text-amber-600 text-[10px] font-bold uppercase mb-1 tracking-wider"><i class="fa-solid fa-chart-line mr-1"></i>Today Peak - Combined Inverters</div><div class="text-base font-black text-slate-800" id="peak-${p.id}">0.000 MW</div></div>
                        <div class="text-right"><div class="text-slate-400 text-[10px] font-bold uppercase mb-1">Peak Hour</div><div class="text-sm font-black text-amber-700" id="peak-hour-${p.id}">--:--</div></div>
                    </div>
                </div>
                <div class="px-4 pb-4 flex flex-col overflow-hidden">
                    <div class="flex justify-between items-center mb-2 shrink-0"><div class="text-slate-500 text-[10px] font-bold uppercase tracking-wider"><i class="fa-solid fa-network-wired mr-1 text-slate-400"></i>Inverter Details</div><div class="text-[9px] text-slate-400 font-medium">Update: <span id="time-${p.id}">--</span></div></div>
                    <div id="inverters-${p.id}" class="bg-slate-50 rounded-lg p-2 border border-slate-200 flex-grow overflow-y-auto space-y-1.5 custom-scrollbar"><div class="text-xs text-slate-400 italic text-center py-4">Waiting for telemetry data...</div></div>
                </div>
            </div>
        </a>`).join('');
}

function updateOverallActivePower() {
    const totalKw=Object.values(plantState).reduce((total,st)=>{
        if(st.hasVCB) return total+(Number(st.vcbPower)||0);
        return total+Object.values(st.inverters||{}).reduce((s,i)=>s+(Number(i.power)||0),0);
    },0);
    document.getElementById('overall-active-power').textContent=totalKw.toFixed(2)+' kW';
}

function updateUI(unit) {
    const st=plantState[unit]; if(!st)return;
    const sumInv=Object.values(st.inverters).reduce((s,i)=>s+(Number(i.power)||0),0);
    const finalPower=st.hasVCB?Number(st.vcbPower||0):sumInv;
    document.getElementById(`active-${unit}`).textContent=finalPower.toFixed(2)+' kW';
    document.getElementById(`active-label-${unit}`).textContent=st.hasVCB?'VCB Active':'Active';
    document.getElementById(`today-${unit}`).textContent=Number(st.dailyEnergy||0).toFixed(2)+' kWh';
    document.getElementById(`peak-${unit}`).textContent=(Number(st.peakInverterKw||0)/1000).toFixed(3)+' MW';
    document.getElementById(`peak-hour-${unit}`).textContent=st.peakHour||'--:--';
    document.getElementById(`time-${unit}`).textContent=st.lastUpdate||'--';
    updateOverallActivePower();

    const invContainer=document.getElementById(`inverters-${unit}`);
    const names=Object.keys(st.inverters).sort((a,b)=>(parseInt(a.replace(/\D/g,''))||0)-(parseInt(b.replace(/\D/g,''))||0));
    if(names.length){
        invContainer.innerHTML=names.map(name=>{
            const inv=st.inverters[name], hasStrings=Number(inv.total||0)>0;
            const threshold=Math.min(22,Number(inv.total||0));
            const green=hasStrings&&inv.active>=threshold, red=hasStrings&&inv.active===0;
            const style=green?'bg-emerald-50 border-emerald-200':red?'bg-red-50 border-red-200':'bg-white border-slate-200';
            const badge=hasStrings?`<span class="font-bold px-1.5 py-0.5 rounded border border-slate-200 bg-slate-100 text-slate-600 min-w-[36px] text-center text-[10px]">${inv.active}/${inv.total}</span>`:'';
            return `<div class="flex justify-between items-center text-xs ${style} px-2.5 py-1.5 rounded border shadow-sm"><span class="font-semibold text-slate-700 capitalize flex items-center gap-1.5"><i class="fa-solid fa-server text-slate-400 text-[10px]"></i>${name}</span><div class="flex items-center gap-2"><span class="text-slate-500 font-medium">${Number(inv.power||0).toFixed(1)} kW</span>${badge}</div></div>`;
        }).join('');
    }

    const badge=document.getElementById(`badge-${unit}`), card=document.getElementById(`card-${unit}`);
    if(finalPower>0){
        badge.className='text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700';
        badge.innerHTML='<span class="h-2 w-2 rounded-full bg-green-500 inline-block mr-1 shadow-[0_0_5px_green]"></span> ON';
        card.classList.remove('alert-state');
    }else{
        badge.className='text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 status-badge-pulse';
        badge.innerHTML='<span class="h-2 w-2 rounded-full bg-red-500 inline-block mr-1"></span> OFF';
        card.classList.add('alert-state');
    }
}

function extractInverterPower(values){
    if(!values||typeof values!=='object')return 0;
    for(const key in values){const k=key.toLowerCase();if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(k)&&!/reactive|apparent|3\.phase/.test(k))return Number(values[key])||0;}
    return 0;
}

function loadInverterPeakHistory(unit,data,requestedDevice=''){
    const st=plantState[unit], readings=data.data||data.records||data.results||data.values||[];
    if(!st||!Array.isArray(readings)||!readings.length)return;
    const sample=readings[0]||{}, deviceName=data.device||sample.device||requestedDevice;
    if(!deviceName)return;
    const series={};
    readings.forEach(r=>{
        const values=r.values||r.data||r, power=extractInverterPower(values);
        const raw=String(r.timestamp||r.time||r.recorded_at||r.datetime||'');
        const direct=raw.match(/(?:T|\s|^)(\d{1,2}):(\d{2})/), dt=new Date(raw);
        let h=null,m=null;
        if(direct){h=Number(direct[1]);m=Number(direct[2]);}else if(!isNaN(dt.getTime())){h=dt.getHours();m=dt.getMinutes();}
        if(h!==null&&m!==null){const label=String(h).padStart(2,'0')+':'+String(Math.floor(m/5)*5).padStart(2,'0');if(power>(series[label]||0))series[label]=power;}
    });
    st.hourlyPowerByInverter[deviceName]=series;
    let peak=0,peakTime='--:--';
    const allSeries=Object.values(st.hourlyPowerByInverter);
    const times=[...new Set(allSeries.flatMap(s=>Object.keys(s)))];
    times.forEach(t=>{const total=allSeries.reduce((sum,s)=>sum+(Number(s[t])||0),0);if(total>peak){peak=total;peakTime=t;}});
    if(peak>st.peakInverterKw){st.peakInverterKw=peak;st.peakHour=peakTime;}
    if(st.liveCombinedKw>st.peakInverterKw){const p=indiaParts();st.peakInverterKw=st.liveCombinedKw;st.peakHour=`${p.hour}:${String(Math.floor(Number(p.minute)/5)*5).padStart(2,'0')}`;}
    updateUI(unit);
}

function countStrings(values){
    let active=0,total=0;
    for(const key in values){
        const kl=key.toLowerCase();
        if(/phase|phasa|ph_|r.phase|y.phase|b.phase|a.phase|c.phase|3.phase|three.phase/.test(kl))continue;
        if(/inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|reactive.*curr|mppt.*curr|dc.*curr/.test(kl))continue;
        if(/freq|temperature|temp|ambient|cosphi|pf.*_/.test(kl))continue;
        if(/\b(curr|current|amp|i)\b/.test(kl)&&!/\b(volt|voltage|temp|freq)\b/.test(kl)&&/\d/.test(key)){total++;if((Number(values[key])||0)>.5)active++;}
    }
    return {active,total};
}

function handlePlantMessage(expectedUnit,data,socket){
    const unit=data.unit_id||expectedUnit;
    if(!plantState[unit]||unit!==expectedUnit)return;
    if(data.type==='daily_data_result'){
        const queued=pendingPeakHistory[unit]?.length?pendingPeakHistory[unit].shift():'';
        loadInverterPeakHistory(unit,data,queued); return;
    }
    const st=plantState[unit];
    st.lastLiveAt=Date.now(); st.lastUpdate=data.time||indiaTime();
    const values=data.values||{}, task=String(data.task||'').toLowerCase(), device=String(data.device||'');
    const deviceLower=device.toLowerCase();
    const isVCB=task==='vcb'||deviceLower.includes('vcb');
    if(isVCB&&values['3 Phase Active Power']!==undefined){st.vcbPower=Number(values['3 Phase Active Power'])||0;st.hasVCB=true;}
    if(data.virtualTags&&data.virtualTags['vcb-today']!==undefined)st.dailyEnergy=Number(data.virtualTags['vcb-today']?.value)||0;

    const keys=Object.keys(values);
    const hasInvPower=keys.some(k=>{const x=k.toLowerCase();return /power/.test(x)&&/active|ac/.test(x)&&!/reactive|apparent|3.phase/.test(x);});
    const hasCurrents=keys.some(k=>/\d/.test(k)&&/curr|current|amp/i.test(k)&&!/phase|3.phase|reactive|apparent|freq|temp/i.test(k.toLowerCase()));
    const isInv=!isVCB&&(task==='inverter'||deviceLower.includes('inverter')||hasInvPower||hasCurrents);
    if(isInv){
        const name=device||'Unknown Inverter', existing=st.inverters[name]||{active:0,total:0,power:0};
        const strings=countStrings(values), power=extractInverterPower(values);
        st.inverters[name]={active:strings.total?strings.active:existing.active,total:strings.total||existing.total,power:hasInvPower?power:existing.power};
        st.liveCombinedKw=Object.values(st.inverters).reduce((s,i)=>s+(Number(i.power)||0),0);
        if(st.liveCombinedKw>st.peakInverterKw){const p=indiaParts();st.peakInverterKw=st.liveCombinedKw;st.peakHour=`${p.hour}:${String(Math.floor(Number(p.minute)/5)*5).padStart(2,'0')}`;}
        const historyKey=unit+'|'+name;
        if(!requestedPeakHistory.has(historyKey)&&socket&&socket.readyState===WebSocket.OPEN){
            requestedPeakHistory.add(historyKey); pendingPeakHistory[unit].push(name);
            socket.send(JSON.stringify({type:'get_daily_data',unit_id:unit,device:name,date:indiaToday()}));
        }
    }
    updateUI(unit);
}

function updateConnectionStatus(){
    const connected=plants.filter(p=>sockets[p.id]&&sockets[p.id].readyState===WebSocket.OPEN).length;
    const el=document.getElementById('ws-status');
    if(connected===plants.length){el.className='text-xs font-bold text-green-500';el.innerHTML='<i class="fa-solid fa-circle text-[8px] mr-1 shadow-[0_0_5px_green]"></i> All Plants Live';}
    else if(connected>0){el.className='text-xs font-bold text-amber-600';el.innerHTML=`<i class="fa-solid fa-circle text-[8px] mr-1"></i> ${connected}/${plants.length} Live`;} 
    else {el.className='text-xs font-bold text-red-500 status-badge-pulse';el.innerHTML='<i class="fa-solid fa-circle text-[8px] mr-1"></i> Reconnecting...';}
}

function connectPlantWS(unit){
    if(reconnectTimers[unit]){clearTimeout(reconnectTimers[unit]);reconnectTimers[unit]=null;}
    const old=sockets[unit]; if(old&&(old.readyState===WebSocket.OPEN||old.readyState===WebSocket.CONNECTING))return;
    try{
        const socket=new WebSocket(WS_URL); sockets[unit]=socket;
        socket.onopen=()=>{pendingPeakHistory[unit]=[];socket.send(JSON.stringify({type:'subscribe',unit_id:unit}));updateConnectionStatus();};
        socket.onmessage=e=>{try{handlePlantMessage(unit,JSON.parse(e.data),socket);}catch(err){console.error('WS parse',unit,err);}};
        socket.onclose=()=>{if(sockets[unit]===socket)sockets[unit]=null;updateConnectionStatus();reconnectTimers[unit]=setTimeout(()=>connectPlantWS(unit),5000);};
        socket.onerror=()=>console.warn('WebSocket error for',unit);
    }catch(err){console.error('WebSocket connect failed for',unit,err);reconnectTimers[unit]=setTimeout(()=>connectPlantWS(unit),5000);}
}
function connectWS(){plants.forEach(p=>connectPlantWS(p.id));}

async function fetchPlantFallback(unit){
    try{
        const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaToday(),plant:unit}); if(authToken)q.set('token',authToken);
        const res=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:authToken?{'Authorization':'Bearer '+authToken}:{}});
        const json=await res.json(); if(!json.success||!Array.isArray(json.data)||!json.data.length)return;
        const st=plantState[unit], rows=json.data, names=json.meta?.inv_names||[];
        const now=indiaParts(), nowMin=Number(now.hour)*60+Number(now.minute);
        const elapsed=rows.filter(r=>{const m=String(r.time_label||'').match(/^(\d{1,2}):(\d{2})/);return m&&(Number(m[1])*60+Number(m[2]))<=nowMin;});
        const latest=elapsed[elapsed.length-1]||rows[rows.length-1]; if(!latest)return;
        const liveFresh=(Date.now()-st.lastLiveAt)<20000;

        let invPower=0;
        names.forEach((name,i)=>{
            const key='inv'+(i+1)+'_kw', pwr=Number(latest[key]||0); invPower+=pwr;
            const existing=st.inverters[name]||{active:0,total:0,power:0};
            if(!liveFresh||!st.inverters[name])st.inverters[name]={...existing,power:pwr};
        });
        const vcbPower=Number(latest.vcb_kw||0);
        if(!liveFresh){st.vcbPower=vcbPower;st.hasVCB=vcbPower!==0;st.lastUpdate='DB '+indiaTime();}
        const maxEnergy=Math.max(0,...elapsed.map(r=>Number(r.inv_total_kwh||0)));
        if(!liveFresh||st.dailyEnergy<=0)st.dailyEnergy=maxEnergy;

        let peak=0,peakTime='--:--';
        elapsed.forEach(r=>{let total=0;names.forEach((_,i)=>total+=Number(r['inv'+(i+1)+'_kw']||0));if(total>peak){peak=total;peakTime=r.time_label||'--:--';}});
        if(peak>st.peakInverterKw){st.peakInverterKw=peak;st.peakHour=peakTime;}
        if(!liveFresh)st.liveCombinedKw=invPower;
        updateUI(unit);
    }catch(err){console.warn('Admin API fallback failed for',unit,err);}
}
function refreshFallbacks(){plants.forEach(p=>fetchPlantFallback(p.id));}

const addUserForm=document.getElementById('addUserForm');
addUserForm.addEventListener('submit',async e=>{
    e.preventDefault(); const msg=document.getElementById('user-msg'); msg.classList.add('hidden');
    try{
        const res=await fetch('api.php?action=add_user',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+authToken},body:JSON.stringify({email:document.getElementById('new_email').value,password:document.getElementById('new_password').value,plant_id:document.getElementById('new_plant_id').value,role:document.getElementById('new_role').value})});
        const data=await res.json(); msg.textContent=data.message||data.status; msg.className=`text-xs text-center font-medium mt-2 ${data.status==='success'?'text-green-600':'text-red-600'}`; msg.classList.remove('hidden'); if(data.status==='success')addUserForm.reset();
    }catch(err){msg.textContent='Unable to create user';msg.className='text-xs text-center font-medium mt-2 text-red-600';msg.classList.remove('hidden');}
});

document.addEventListener('DOMContentLoaded',()=>{
    renderCards();
    refreshFallbacks();
    connectWS();
    setInterval(refreshFallbacks,15000);
});
</script>
</body>
</html>
