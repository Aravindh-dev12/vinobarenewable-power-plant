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
    <title><?php echo htmlspecialchars($plantInfo['name']); ?> - MPPT & Strings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-ui.css?v=8" data-dashboard-ui>
    <script src="sidebar-control.js?v=10" defer></script>
    <style>
        .string-table th,.string-table td{white-space:nowrap}
        .string-table th{position:sticky;top:0;z-index:2}
        .string-table th:first-child,.string-table td:first-child{position:sticky;left:0;z-index:3}
        .string-table th:first-child{z-index:4}
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3"><button id="menuBtn" class="md:hidden text-emerald-600 text-2xl" aria-label="Open menu">&#9776;</button><div><h2 class="text-xl font-black text-slate-800 tracking-tight">MPPT &amp; String Monitoring</h2><p class="text-xs text-slate-500 hidden sm:block">All inverter string values in one live table</p></div></div>
            <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100"><span id="refreshPulse" class="w-2.5 h-2.5 bg-slate-400 rounded-full"></span><span id="liveText" class="text-[10px] font-bold text-slate-500 hidden sm:inline">CONNECTING</span><span id="clockDisplay" class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline">--:--:--</span></div>
        </header>
        <div class="p-4 sm:p-6 w-full max-w-[1800px] mx-auto">
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div><h3 class="text-sm font-black text-slate-700 uppercase tracking-widest">Live String Current Table</h3><p class="text-xs text-slate-500 mt-1">Actual/active string threshold: current &gt; 0.1 A</p></div>
                    <span id="tableStatus" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">WAITING</span>
                </div>
                <div class="overflow-x-auto max-h-[72vh] overflow-y-auto">
                    <table class="string-table min-w-[2200px] w-full text-xs border-collapse">
                        <thead id="tableHead" class="bg-slate-100 text-slate-600"></thead>
                        <tbody id="tableBody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
<script>
const CURRENT_PLANT=<?php echo json_encode($currentPlant); ?>;
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const EXPECTED=CURRENT_PLANT==='vinoba-1'?{1:23,2:23,3:23,4:23,5:23,6:23,7:22}:{1:23,2:23,3:23,4:23};
const EXPECTED_COUNT=CURRENT_PLANT==='vinoba-1'?7:4;
const data={};let ws=null,reconnectTimer=null;
function num(v){const n=Number(v);return Number.isFinite(n)?n:0;}
function invNo(name){const m=String(name||'').match(/(\d+)/);return m?Number(m[1]):0;}
function canonical(name){const n=invNo(name);return n?`Inverter ${n}`:String(name||'Inverter');}
function expected(name){return EXPECTED[invNo(name)]||23;}
function setStatus(mode){const dot=document.getElementById('refreshPulse'),text=document.getElementById('liveText'),badge=document.getElementById('tableStatus');if(mode==='live'){dot.className='w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';text.textContent='LIVE';text.className='text-[10px] font-bold text-emerald-600 hidden sm:inline';badge.textContent='LIVE';badge.className='text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700 px-3 py-1.5';}else if(mode==='error'){dot.className='w-2.5 h-2.5 bg-red-500 rounded-full';text.textContent='RECONNECTING';text.className='text-[10px] font-bold text-red-600 hidden sm:inline';badge.textContent='RECONNECTING';badge.className='text-[10px] font-bold rounded-full bg-red-100 text-red-700 px-3 py-1.5';}else{dot.className='w-2.5 h-2.5 bg-slate-400 rounded-full';text.textContent='CONNECTING';text.className='text-[10px] font-bold text-slate-500 hidden sm:inline';badge.textContent='WAITING';badge.className='text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5';}}
function tickClock(){document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false});}tickClock();setInterval(tickClock,1000);
function parseStrings(values){
    const groups={};
    for(const key of Object.keys(values||{})){const lower=key.toLowerCase();if(/phase|3\.phase|three\.phase|freq|temperature|temp|ambient|reactive|apparent|inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|mppt.*curr|dc.*curr/.test(lower))continue;const m=key.match(/(\d+)/);if(!m)continue;const n=Number(m[1]);(groups[n]??=[]).push(key);}
    const out={};
    Object.entries(groups).forEach(([no,keys])=>{let currKey='',voltKey='';for(const key of keys){const s=key.toLowerCase();if(!currKey&&/curr|current|amp/.test(s)&&!/volt|voltage/.test(s))currKey=key;if(!voltKey&&/volt|voltage/.test(s)&&!/curr|current|amp/.test(s))voltKey=key;}if(!currKey)return;out[Number(no)]={curr:num(values[currKey]),volt:voltKey?num(values[voltKey]):0};});
    return out;
}
function parseMppt(values){
    const out={};
    for(const [key,value] of Object.entries(values||{})){const s=key.toLowerCase();if(!/mppt/.test(s)||!/curr|current|amp/.test(s))continue;const m=key.match(/(\d+)/);const n=m?Number(m[1]):Object.keys(out).length+1;out[n]=num(value);}
    return out;
}
for(let i=1;i<=EXPECTED_COUNT;i++)data[`Inverter ${i}`]={received:false,strings:{},mppt:{}};
function buildHead(){let html='<tr><th class="bg-slate-100 px-3 py-3 text-left font-black border-r border-slate-200">Inverter</th><th class="bg-slate-100 px-3 py-3 text-center font-black">Actual</th><th class="bg-slate-100 px-3 py-3 text-left font-black min-w-[220px]">MPPT Current</th>';for(let i=1;i<=23;i++)html+=`<th class="bg-slate-100 px-2 py-3 text-center font-black min-w-[74px]">S${i}</th>`;html+='</tr>';document.getElementById('tableHead').innerHTML=html;}
function stringCell(item,no,max){if(no>max)return '<td class="px-2 py-3 text-center text-slate-300 bg-slate-50">N/A</td>';const s=item.strings[no];if(!item.received||!s)return '<td class="px-2 py-3 text-center text-slate-400">--</td>';const active=s.curr>0.1;return `<td class="px-2 py-2 text-center ${active?'bg-emerald-50':'bg-red-50'}"><div class="font-black ${active?'text-emerald-700':'text-red-700'}">${s.curr.toFixed(2)} A</div><div class="text-[9px] text-slate-400">${s.volt?s.volt.toFixed(1)+' V':'--'}</div></td>`;}
function render(){const names=Object.keys(data).sort((a,b)=>invNo(a)-invNo(b));document.getElementById('tableBody').innerHTML=names.map(name=>{const item=data[name],max=expected(name),active=Object.values(item.strings).filter(s=>s.curr>0.1).length,actual=item.received?`${active}/${max}`:`--/${max}`,mppt=Object.keys(item.mppt).length?Object.entries(item.mppt).sort((a,b)=>Number(a[0])-Number(b[0])).map(([n,v])=>`M${n}: ${Number(v).toFixed(2)} A`).join(' · '):'--';let row=`<tr class="hover:bg-slate-50"><td class="sticky left-0 bg-white px-3 py-3 font-black border-r border-slate-200">${name}</td><td class="px-3 py-3 text-center font-black">${actual}</td><td class="px-3 py-3 text-[10px] text-slate-600">${mppt}</td>`;for(let i=1;i<=23;i++)row+=stringCell(item,i,max);return row+'</tr>';}).join('');}
function handle(d){if(!d||d.unit_id!==CURRENT_PLANT||d.type==='daily_data_result')return;const task=String(d.task||'').toLowerCase(),device=String(d.device||'').toLowerCase(),values=d.values||{},isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer'),isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||Object.keys(values).some(k=>/active.*power|ac.*power|mppt|string/i.test(k)));if(!isInv)return;const name=canonical(d.device||'Inverter'),old=data[name]||{received:false,strings:{},mppt:{}},strings=parseStrings(values),mppt=parseMppt(values);data[name]={received:true,strings:Object.keys(strings).length?strings:old.strings,mppt:Object.keys(mppt).length?mppt:old.mppt};render();}
function connect(){if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setStatus('waiting');try{ws=new WebSocket(WS_URL);ws.onopen=()=>{setStatus('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));};ws.onmessage=e=>{try{handle(JSON.parse(e.data));}catch(_){}};ws.onclose=()=>{ws=null;setStatus('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}catch(_){setStatus('error');reconnectTimer=setTimeout(connect,3000);}}
buildHead();render();connect();
</script>
</body>
</html>
