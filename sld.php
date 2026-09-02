<?php require 'check_auth.php'; require_once __DIR__ . '/plant_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - SLD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=4" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl">&#9776;</button>
                <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Single Line Diagram</h2></div>
            </div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
            </div>
        </header>
        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-5">
                    <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Plant SLD</p><h2 class="text-xl font-bold text-slate-900">Power Flow Overview</h2></div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Live view</span>
                </div>
                <div class="grid gap-4 lg:grid-cols-3 mb-6">
                    <div class="bg-slate-50 rounded-lg p-5 border border-slate-100 text-center">
                        <p class="text-sm font-black text-slate-800">PV / Inverter Output</p>
                        <p class="mt-3 text-3xl font-black text-slate-900" id="sld_pv">-- <span class="text-lg text-slate-500">kW</span></p>
                        <p class="mt-2 text-xs text-slate-500">Combined inverter AC output</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-5 border border-slate-100 text-center">
                        <p class="text-sm font-black text-slate-800">Inverter AC Output</p>
                        <p class="mt-3 text-3xl font-black text-slate-900" id="sld_inv">-- <span class="text-lg text-slate-500">kW</span></p>
                        <p class="mt-2 text-xs text-slate-500">Combined active power</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-5 border border-slate-100 text-center">
                        <p class="text-sm font-black text-slate-800">Grid Export (HT/VCB)</p>
                        <p class="mt-3 text-3xl font-black text-slate-900" id="sld_grid">-- <span class="text-lg text-slate-500">kW</span></p>
                        <p class="mt-2 text-xs text-slate-500" id="htStatus">HT data unavailable until received</p>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-900 text-white p-8 min-h-[360px] flex items-center justify-center">
                    <div class="text-center space-y-5">
                        <p class="text-lg font-bold text-emerald-400">SLD Power Flow</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 text-sm">
                            <div><p class="text-slate-400 mb-1">PV / Inverters</p><p class="text-2xl font-black text-yellow-400" id="sld_pv2">-- kW</p></div>
                            <div><p class="text-slate-400 mb-1">AC Output</p><p class="text-2xl font-black text-blue-400" id="sld_inv2">-- kW</p></div>
                            <div><p class="text-slate-400 mb-1">Grid / HT</p><p class="text-2xl font-black text-emerald-400" id="sld_grid2">--</p></div>
                        </div>
                        <p class="text-xs text-slate-500">Live data from WebSocket | Plant: <span id="sld_plant_name">--</span></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
const urlParams = new URLSearchParams(window.location.search);
const currentPlant = urlParams.get('plant') || 'vinoba-1';
const plantNames = {
    'vinoba-1': 'Vinoba Renewable Energy Private Limited',
    'ssv': 'SSV Green Power Private Limited'
};
const authToken = urlParams.get('token') || sessionStorage.getItem('vs_token') || '';
document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - SLD';
document.getElementById('sld_plant_name').textContent = plantNames[currentPlant] || currentPlant;
setInterval(() => { document.getElementById('clockDisplay').innerText = new Date().toLocaleTimeString('en-IN', {hour12:false}); }, 1000);

fetch('sidebar.html', {cache:'no-store'}).then(r=>r.text()).then(html=>{
    document.getElementById('sidebar-container').innerHTML=html;
    document.querySelectorAll('#sidebarNav a').forEach(link=>{
        let href=link.getAttribute('href');
        if(!href || href.includes('logout')) return;
        link.setAttribute('href', `${href}?plant=${encodeURIComponent(currentPlant)}&token=${encodeURIComponent(authToken)}`);
    });
    const pn=document.getElementById('sidebarPlantName'); if(pn) pn.textContent=plantNames[currentPlant]||currentPlant;
    if(typeof initSidebar==='function') initSidebar();
    const overlay=document.getElementById('overlay'), sidebar=document.getElementById('sidebar');
    document.getElementById('menuBtn')?.addEventListener('click',()=>{sidebar?.classList.remove('-translate-x-full');overlay?.classList.remove('hidden');});
    document.getElementById('closeSidebarBtn')?.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay?.classList.add('hidden');});
    overlay?.addEventListener('click',()=>{sidebar?.classList.add('-translate-x-full');overlay.classList.add('hidden');});
});

const sldState={vcbPower:0,hasVcb:false,inverters:{}};
function inverterPower(values){
    for(const [key,value] of Object.entries(values||{})){
        const k=key.toLowerCase();
        if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(k)&&!/reactive|apparent|3.phase/.test(k)) return Number(value)||0;
    }
    return 0;
}
function getInvTotal(){return Object.values(sldState.inverters).reduce((s,p)=>s+(Number(p)||0),0);}
function updateSld(){
    const inv=getInvTotal();
    document.getElementById('sld_pv').innerHTML=inv.toFixed(2)+' <span class="text-lg text-slate-500">kW</span>';
    document.getElementById('sld_inv').innerHTML=inv.toFixed(2)+' <span class="text-lg text-slate-500">kW</span>';
    document.getElementById('sld_pv2').textContent=inv.toFixed(2)+' kW';
    document.getElementById('sld_inv2').textContent=inv.toFixed(2)+' kW';
    if(sldState.hasVcb){
        document.getElementById('sld_grid').innerHTML=sldState.vcbPower.toFixed(2)+' <span class="text-lg text-slate-500">kW</span>';
        document.getElementById('sld_grid2').textContent=sldState.vcbPower.toFixed(2)+' kW';
        document.getElementById('htStatus').textContent='Live HT/VCB active power';
    } else {
        document.getElementById('sld_grid').innerHTML='-- <span class="text-lg text-slate-500">kW</span>';
        document.getElementById('sld_grid2').textContent='--';
        document.getElementById('htStatus').textContent='HT data unavailable until received';
    }
}
function connectWS(){
    const ws=new WebSocket('wss://vinobasolar.scadahub.in:5001');
    ws.onopen=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';ws.send(JSON.stringify({type:'subscribe',unit_id:currentPlant}));};
    ws.onmessage=e=>{
        try{
            const d=JSON.parse(e.data); if(d.unit_id!==currentPlant) return;
            const task=String(d.task||'').toLowerCase(), device=String(d.device||'').toLowerCase(), values=d.values||{};
            const isVcb=task==='vcb'||device.includes('vcb');
            if(isVcb&&values['3 Phase Active Power']!==undefined){sldState.vcbPower=Number(values['3 Phase Active Power'])||0;sldState.hasVcb=true;}
            const isInv=!isVcb&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power/i.test(k)));
            if(isInv) sldState.inverters[d.device||'Inverter']=inverterPower(values);
            updateSld();
        }catch(_){ }
    };
    ws.onclose=()=>{document.getElementById('refreshPulse').className='w-2.5 h-2.5 bg-red-500 rounded-full';setTimeout(connectWS,5000);};
}
connectWS();
</script>
</body>
</html>
