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
    <link rel="stylesheet" href="dashboard-ui.css?v=8" data-dashboard-ui>
    <script src="sidebar-control.js?v=10" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3"><button id="menuBtn" class="md:hidden text-emerald-600 text-2xl" aria-label="Open menu">&#9776;</button><h2 class="text-xl font-black text-slate-800 tracking-tight">Inverter Overview</h2></div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100"><span id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></span><span id="liveText" class="text-[10px] font-bold text-slate-500 hidden sm:inline">CONNECTING</span><span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline">--:--:--</span></div>
        </header>

        <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
            <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Inverters</p><p id="inv_total_count" class="text-3xl font-black mt-2">--</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Active Power</p><p id="inv_total" class="text-3xl font-black mt-2">-- <span class="text-sm text-blue-600">kW</span></p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Today Generation</p><p id="inv_total_gen" class="text-3xl font-black mt-2">-- <span class="text-sm text-purple-600">kWh</span></p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-5"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Average Efficiency</p><p id="inv_avg_eff" class="text-3xl font-black mt-2">-- <span class="text-sm text-sky-600">%</span></p></div>
            </section>
            <section id="inv_detail_container" class="space-y-4"></section>
        </div>
    </main>
</div>

<div id="stringModal" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-100"><div><h3 id="stringModalTitle" class="text-lg font-black text-slate-800">String Details</h3><p class="text-xs text-slate-500 mt-1">Actual string = current greater than 0.1 A</p></div><button type="button" onclick="closeStringModal()" class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="p-5 overflow-y-auto"><div id="stringGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3"></div></div>
    </div>
</div>

<script>
const CURRENT_PLANT = <?php echo json_encode($currentPlant); ?>;
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const EXPECTED = CURRENT_PLANT==='vinoba-1' ? {1:23,2:23,3:23,4:23,5:23,6:23,7:22} : {1:23,2:23,3:23,4:23};
const EXPECTED_COUNT = CURRENT_PLANT==='vinoba-1' ? 7 : 4;
const invData = {};
let ws=null,reconnectTimer=null;

const num=v=>{const n=Number(v);return Number.isFinite(n)?n:0;};
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function invNo(name){const m=String(name||'').match(/(\d+)/);return m?Number(m[1]):0;}
function canonicalName(name){const n=invNo(name);return n?`Inverter ${n}`:String(name||'Inverter');}
function expectedStrings(name){return EXPECTED[invNo(name)]||23;}
function setConnection(mode){const dot=document.getElementById('refreshPulse'),text=document.getElementById('liveText');if(mode==='live'){dot.className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';text.textContent='LIVE';text.className='text-[10px] font-bold text-emerald-600 hidden sm:inline';}else if(mode==='error'){dot.className='w-2.5 h-2.5 bg-red-500 rounded-full';text.textContent='RECONNECTING';text.className='text-[10px] font-bold text-red-600 hidden sm:inline';}else{dot.className='w-2.5 h-2.5 bg-slate-400 rounded-full';text.textContent='CONNECTING';text.className='text-[10px] font-bold text-slate-500 hidden sm:inline';}}
function tickClock(){document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false});}tickClock();setInterval(tickClock,1000);
function findValue(values,tests){for(const [key,value] of Object.entries(values||{})){const k=key.toLowerCase();if(tests.some(test=>test(k))){const n=Number(value);return Number.isFinite(n)?n:null;}}return null;}
function activePower(values){return findValue(values,[k=>/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(k)&&!/reactive|apparent|3.phase/.test(k)]);}
function dailyGeneration(values){return findValue(values,[k=>/daily.*generation|daily.*gen/.test(k)]);}
function exactOr(values,exact,fallbackTests=[]){if(values&&values[exact]!==undefined){const n=Number(values[exact]);return Number.isFinite(n)?n:null;}return fallbackTests.length?findValue(values,fallbackTests):null;}
function parseStrings(values){
    const groups={};
    for(const key of Object.keys(values||{})){const lower=key.toLowerCase();if(/phase|3\.phase|three\.phase|freq|temperature|temp|ambient|reactive|apparent|inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|mppt.*curr|dc.*curr/.test(lower))continue;const m=key.match(/(\d+)/);if(!m)continue;const no=Number(m[1]);(groups[no]??=[]).push(key);}
    const strings=[];
    Object.entries(groups).forEach(([no,keys])=>{let currKey='',voltKey='';for(const key of keys){const lower=key.toLowerCase();if(!currKey&&/curr|current|amp/.test(lower)&&!/volt|voltage/.test(lower))currKey=key;if(!voltKey&&/volt|voltage/.test(lower)&&!/curr|current|amp/.test(lower))voltKey=key;}if(!currKey)return;const curr=num(values[currKey]),volt=voltKey?num(values[voltKey]):0;strings.push({n:Number(no),curr,volt,active:curr>0.1});});
    return strings.sort((a,b)=>a.n-b.n);
}
function blankInverter(i){return {name:`Inverter ${i}`,deviceName:`Inverter ${i}`,received:false,power:null,reactive:null,pf:null,vac_ab:null,vac_bc:null,vac_ca:null,freq:null,i_a:null,i_b:null,i_c:null,eff:null,amb:null,dailyGen:null,totalGen:null,dailyCO2:null,totalCO2:null,strings:[],stringsSeen:false};}
for(let i=1;i<=EXPECTED_COUNT;i++)invData[`Inverter ${i}`]=blankInverter(i);

function updateInverter(message){
    const values=message.values||{},key=canonicalName(message.device||'Inverter'),old=invData[key]||blankInverter(invNo(key)||1),parsedStrings=parseStrings(values);
    invData[key]={
        ...old,name:key,deviceName:String(message.device||key),received:true,
        power:activePower(values),reactive:exactOr(values,'a.c. reactive power',[k=>/reactive.*power/.test(k)]),pf:exactOr(values,'Power Factor',[k=>/power.*factor|cosphi/.test(k)]),
        vac_ab:exactOr(values,'a.c. voltage AB',[k=>/voltage.*ab|ab.*voltage/.test(k)]),vac_bc:exactOr(values,'a.c. voltage BC',[k=>/voltage.*bc|bc.*voltage/.test(k)]),vac_ca:exactOr(values,'a.c. voltage CA',[k=>/voltage.*ca|ca.*voltage/.test(k)]),
        freq:exactOr(values,'a.c. frequency',[k=>/frequency|freq/.test(k)]),i_a:exactOr(values,'A phase current',[k=>/a.*phase.*current/.test(k)]),i_b:exactOr(values,'B phase current',[k=>/b.*phase.*current/.test(k)]),i_c:exactOr(values,'C phase current',[k=>/c.*phase.*current/.test(k)]),
        eff:exactOr(values,'inverter efficiency',[k=>/efficiency/.test(k)]),amb:exactOr(values,'internal ambient temperature',[k=>/ambient.*temp|internal.*temp/.test(k)]),
        dailyGen:dailyGeneration(values)??old.dailyGen,totalGen:exactOr(values,'total generation',[k=>/total.*generation/.test(k)])??old.totalGen,dailyCO2:exactOr(values,'daily CO2 reduction',[k=>/daily.*co2/.test(k)])??old.dailyCO2,totalCO2:exactOr(values,'total CO2 reduction',[k=>/total.*co2/.test(k)])??old.totalCO2,
        strings:parsedStrings.length?parsedStrings:old.strings,stringsSeen:parsedStrings.length?true:old.stringsSeen
    };
    renderAll();
}
function display(value,digits=2){return value===null||value===undefined?'--':Number(value).toFixed(digits);}
function metric(label,value,unit,digits){return `<div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${label}</p><p class="mt-1 text-lg font-black text-slate-800">${display(value,digits)}${unit?` <span class="text-xs text-slate-500">${unit}</span>`:''}</p></div>`;}
function renderAll(){
    const names=Object.keys(invData).sort((a,b)=>invNo(a)-invNo(b)||a.localeCompare(b)),received=names.filter(k=>invData[k].received);
    document.getElementById('inv_total_count').textContent=EXPECTED_COUNT;
    if(received.length){const totalPower=received.reduce((s,k)=>s+num(invData[k].power),0),totalGen=received.reduce((s,k)=>s+num(invData[k].dailyGen),0),effs=received.map(k=>invData[k].eff).filter(v=>v!==null&&v!==undefined);document.getElementById('inv_total').innerHTML=totalPower.toFixed(2)+' <span class="text-sm text-blue-600">kW</span>';document.getElementById('inv_total_gen').innerHTML=totalGen.toFixed(2)+' <span class="text-sm text-purple-600">kWh</span>';document.getElementById('inv_avg_eff').innerHTML=(effs.length?(effs.reduce((a,b)=>a+Number(b),0)/effs.length).toFixed(1):'--')+' <span class="text-sm text-sky-600">%</span>';}
    const container=document.getElementById('inv_detail_container');
    container.innerHTML=names.map(name=>{
        const v=invData[name],strings=v.strings||[],active=strings.filter(x=>x.active).length,expected=expectedStrings(name),status=!v.received?'WAITING':num(v.power)>0.01?'ONLINE':'OFFLINE',statusClass=!v.received?'bg-slate-100 text-slate-500':num(v.power)>0.01?'bg-emerald-100 text-emerald-700':'bg-red-100 text-red-700',stringText=v.stringsSeen?`${active}/${expected} strings`:`--/${expected} strings`;
        return `<article class="relative bg-white rounded-xl border border-slate-200 p-5 shadow-sm"><button type="button" class="string-eye absolute top-4 right-4 w-9 h-9 rounded-lg border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-200 text-blue-600 flex items-center justify-center" data-name="${encodeURIComponent(name)}" title="View live strings" aria-label="View strings for ${esc(name)}"><i class="fa-solid fa-eye"></i></button><div class="pr-12 flex flex-wrap items-center gap-3 mb-4 pb-3 border-b border-slate-100"><i class="fa-solid fa-server text-blue-500"></i><h3 class="text-sm font-black text-slate-700 uppercase tracking-widest">${esc(name)}</h3><span class="rounded-full ${statusClass} px-2.5 py-1 text-[10px] font-bold">${status}</span><span class="text-[10px] font-bold text-slate-500">${stringText}</span></div><div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">${metric('AC Power',v.power,'kW',2)}${metric('Reactive Power',v.reactive,'kVAR',2)}${metric('Power Factor',v.pf,'',3)}${metric('Efficiency',v.eff,'%',1)}${metric('AC Frequency',v.freq,'Hz',2)}${metric('Ambient Temperature',v.amb,'°C',1)}${metric('Vac AB',v.vac_ab,'V',1)}${metric('Vac BC',v.vac_bc,'V',1)}${metric('Vac CA',v.vac_ca,'V',1)}${metric('I A',v.i_a,'A',2)}${metric('I B',v.i_b,'A',2)}${metric('I C',v.i_c,'A',2)}${metric('Daily Generation',v.dailyGen,'kWh',1)}${metric('Total Generation',v.totalGen,'kWh',0)}${metric('Daily CO₂',v.dailyCO2,'kg',1)}${metric('Total CO₂',v.totalCO2,'kg',0)}</div></article>`;
    }).join('');
    container.querySelectorAll('.string-eye').forEach(btn=>btn.addEventListener('click',()=>openStringModal(decodeURIComponent(btn.dataset.name||''))));
}
function openStringModal(name){const inverter=invData[name];if(!inverter)return;document.getElementById('stringModalTitle').textContent=name+' - String Details';const strings=inverter.strings||[];document.getElementById('stringGrid').innerHTML=strings.length?strings.map(s=>`<div class="rounded-lg border ${s.active?'border-emerald-200 bg-emerald-50':'border-red-100 bg-red-50'} p-3 text-center"><p class="text-[10px] font-black ${s.active?'text-emerald-700':'text-red-600'}">STRING ${s.n}</p><p class="text-lg font-black mt-1">${s.curr.toFixed(2)} A</p><p class="text-[10px] text-slate-500 mt-1">${s.volt?s.volt.toFixed(1)+' V':'--'}</p></div>`).join(''):'<p class="col-span-full text-center text-sm text-slate-400 py-6">No live string telemetry received yet.</p>';document.getElementById('stringModal').classList.remove('hidden');}
function closeStringModal(){document.getElementById('stringModal').classList.add('hidden');}window.closeStringModal=closeStringModal;
function connect(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setConnection('connecting');try{ws=new WebSocket(WS_URL);ws.onopen=()=>{setConnection('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));};ws.onmessage=e=>{try{const d=JSON.parse(e.data);if(d.unit_id!==CURRENT_PLANT||d.type==='daily_data_result')return;const task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),values=d.values||{},isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer'),isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power|power.*ac/i.test(k)));if(isInv)updateInverter(d);}catch(err){console.error(err);}};ws.onclose=()=>{ws=null;setConnection('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}catch(_){setConnection('error');reconnectTimer=setTimeout(connect,3000);}}
renderAll();connect();
</script>
</body>
</html>
