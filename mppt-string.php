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
        .matrix-scroll::-webkit-scrollbar{height:8px;width:8px}.matrix-scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}.matrix-scroll::-webkit-scrollbar-track{background:#f8fafc}
        .matrix-table{border-collapse:separate;border-spacing:0}.matrix-table th,.matrix-table td{white-space:nowrap;border-right:1px solid #dbe4f0;border-bottom:1px solid #dbe4f0}.matrix-table th:last-child,.matrix-table td:last-child{border-right:0}
        .matrix-table thead th{position:sticky;top:0;z-index:5}.matrix-table .sticky-col{position:sticky;left:0;z-index:6}.matrix-table thead .sticky-col{z-index:8}
        .mppt-group{background:#eef2ff;color:#4338ca}.sub-head{background:#f8fafc;color:#64748b}
        .string-active{background:#ecfdf5;color:#059669}.string-zero{background:#fff1f2;color:#dc2626}.string-missing{background:#fff;color:#94a3b8}.string-na{background:#f8fafc;color:#cbd5e1}
        .status-dot{width:8px;height:8px;border-radius:999px;display:inline-block}
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>

    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white px-4 sm:px-6 py-3 flex justify-between items-center sticky top-0 z-20 border-b border-indigo-100 shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <button id="menuBtn" class="md:hidden text-indigo-600 text-2xl" aria-label="Open menu">&#9776;</button>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-sm shrink-0"><i class="fa-solid fa-bolt"></i></div>
                <div class="min-w-0"><h2 class="text-lg sm:text-xl font-black text-slate-800 tracking-tight truncate">Inverter MPPT &amp; Strings</h2><p class="text-[10px] font-black uppercase tracking-[0.18em] text-violet-500">Live Dashboard</p></div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <span id="headerLive" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black text-slate-500 shadow-sm"><span class="status-dot bg-slate-400"></span><span>CONNECTING</span></span>
                <span id="clockDisplay" class="hidden sm:inline text-xs font-bold text-slate-500 tabular-nums">--:--:--</span>
            </div>
        </header>

        <div class="p-4 sm:p-6 w-full max-w-[1900px] mx-auto space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 bg-gradient-to-r from-white via-indigo-50/40 to-white flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-sm"><i class="fa-solid fa-bolt"></i></div>
                        <div><h3 class="text-lg font-black text-slate-800">MPPT Matrix</h3><p id="mpptMeta" class="text-xs font-semibold text-violet-500">Waiting for live MPPT telemetry</p></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-wider">
                        <span id="zeroFaultBadge" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500"><span class="status-dot bg-slate-400"></span>Zero current: --</span>
                        <span id="systemBadge" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-500"><span class="status-dot bg-slate-400"></span>System waiting</span>
                    </div>
                </div>

                <div class="matrix-scroll overflow-auto max-h-[46vh]">
                    <table id="mpptTable" class="matrix-table min-w-full text-xs">
                        <thead id="mpptHead"></thead>
                        <tbody id="mpptBody"></tbody>
                    </table>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="text-xs font-black uppercase tracking-[0.12em] text-slate-600">String Health Summary</div>
                    <div class="flex flex-wrap items-center gap-5 text-xs font-bold">
                        <span class="text-slate-400 uppercase tracking-wider">Active strings <b id="activeTotal" class="ml-2 text-2xl text-emerald-600 tabular-nums">--</b></span>
                        <span class="text-slate-400 uppercase tracking-wider">Zero strings <b id="zeroTotal" class="ml-2 text-2xl text-rose-600 tabular-nums">--</b></span>
                        <span class="text-slate-400 uppercase tracking-wider">Observed <b id="observedTotal" class="ml-2 text-lg text-slate-700 tabular-nums">--</b></span>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3"><div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center"><i class="fa-solid fa-table-cells"></i></div><div><h3 class="text-sm font-black text-slate-800">Dense String Matrix</h3><p id="stringMeta" class="text-[10px] text-slate-400 mt-0.5">Live string current by inverter</p></div></div>
                    <div class="flex flex-wrap gap-3 text-[10px] font-bold"><span class="inline-flex items-center gap-1.5 text-emerald-700"><span class="status-dot bg-emerald-500"></span>&gt; 0.1 A active</span><span class="inline-flex items-center gap-1.5 text-rose-600"><span class="status-dot bg-rose-500"></span>0 A / inactive</span><span class="inline-flex items-center gap-1.5 text-slate-400"><span class="status-dot bg-slate-300"></span>-- no data</span></div>
                </div>
                <div class="matrix-scroll overflow-auto max-h-[48vh]">
                    <table class="matrix-table min-w-max w-full text-xs">
                        <thead id="stringHead"></thead>
                        <tbody id="stringBody"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
const CURRENT_PLANT=<?php echo json_encode($currentPlant); ?>;
const PLANT_NAME=<?php echo json_encode($plantInfo['name']); ?>;
const WS_URL='wss://vinobasolar.scadahub.in:5001';
const EXPECTED=CURRENT_PLANT==='vinoba-1'?{1:23,2:23,3:23,4:23,5:23,6:23,7:22}:{1:23,2:23,3:23,4:23};
const EXPECTED_COUNT=CURRENT_PLANT==='vinoba-1'?7:4;
const DEFAULT_MPPT_COLUMNS=6;
const data={};
let ws=null,reconnectTimer=null,lastLiveAt=0,socketOpen=false;

function num(v){const n=Number(v);return Number.isFinite(n)?n:null;}
function fmt(v,d=1){return v===null||v===undefined?'--':Number(v).toFixed(d);}
function invNo(name){const m=String(name||'').match(/(\d+)/);return m?Number(m[1]):0;}
function canonical(name){const n=invNo(name);return n?`Inverter ${n}`:String(name||'Inverter');}
function expectedStrings(name){return EXPECTED[invNo(name)]||Math.max(...Object.values(EXPECTED));}
function maxStringColumns(){return Math.max(...Object.values(EXPECTED));}
function mergeChannelMap(oldMap,newMap){const out={...(oldMap||{})};for(const [k,v] of Object.entries(newMap||{}))out[k]={...(out[k]||{}),...v};return out;}
function normalizeMessagePlant(d){return String(d?.unit_id||d?.unitId||d?.plant_id||d?.plantId||d?.data?.unit_id||'').trim().toLowerCase();}
function messageValues(d){if(d?.values&&typeof d.values==='object')return d.values;if(d?.data?.values&&typeof d.data.values==='object')return d.data.values;return {};}
function messageDevice(d){return String(d?.device||d?.deviceName||d?.data?.device||'');}

for(let i=1;i<=EXPECTED_COUNT;i++)data[`Inverter ${i}`]={received:false,strings:{},mppt:{},lastLive:0,deviceName:`Inverter ${i}`};

function parseStrings(values){
    const groups={};
    for(const key of Object.keys(values||{})){
        const lower=key.toLowerCase();
        if(/mppt|phase|3\.phase|three\.phase|freq|temperature|temp|ambient|reactive|apparent|inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|dc.*curr/.test(lower))continue;
        const match=key.match(/(\d+)/);if(!match)continue;
        const no=Number(match[1]);(groups[no]??=[]).push(key);
    }
    const out={};
    Object.entries(groups).forEach(([no,keys])=>{
        let currKey='',voltKey='';
        for(const key of keys){
            const s=key.toLowerCase();
            if(!currKey&&/(curr|current|amp)/.test(s)&&!/(volt|voltage)/.test(s))currKey=key;
            if(!voltKey&&/(volt|voltage)/.test(s)&&!/(curr|current|amp)/.test(s))voltKey=key;
        }
        if(!currKey)return;
        const curr=num(values[currKey]),volt=voltKey?num(values[voltKey]):null;
        if(curr===null&&volt===null)return;
        out[Number(no)]={curr,volt};
    });
    return out;
}

function parseMppt(values){
    const groups={};
    for(const [key,value] of Object.entries(values||{})){
        const lower=key.toLowerCase();if(!/mppt/.test(lower))continue;
        const match=lower.match(/mppt\D*(\d+)/i)||key.match(/(\d+)/);if(!match)continue;
        const no=Number(match[1]);if(!groups[no])groups[no]={voltage:null,current:null};
        const n=num(value);if(n===null)continue;
        if(/volt|voltage/.test(lower))groups[no].voltage=n;
        else if(/curr|current|amp/.test(lower))groups[no].current=n;
    }
    return groups;
}

function observedMpptColumns(){
    let max=0;Object.values(data).forEach(item=>Object.keys(item.mppt||{}).forEach(k=>{max=Math.max(max,Number(k)||0);}));
    return Math.max(DEFAULT_MPPT_COLUMNS,max);
}
function stringStats(){
    let active=0,zero=0,observed=0;
    Object.values(data).forEach(item=>Object.values(item.strings||{}).forEach(s=>{if(s.curr===null||s.curr===undefined)return;observed++;if(Number(s.curr)>0.1)active++;else zero++;}));
    return {active,zero,observed};
}
function liveInverterCount(){return Object.values(data).filter(x=>x.received).length;}

function buildMpptHead(){
    const count=observedMpptColumns();
    let top='<tr><th rowspan="2" class="sticky-col bg-slate-100 px-4 py-3 text-left font-black uppercase tracking-wider text-slate-700 min-w-[150px]">Inverter</th>';
    for(let i=1;i<=count;i++)top+=`<th colspan="2" class="mppt-group px-4 py-2 text-center font-black uppercase tracking-wider min-w-[150px]">MPPT ${i}</th>`;
    top+='</tr><tr>';
    for(let i=1;i<=count;i++)top+='<th class="sub-head px-3 py-2 text-right text-[10px] font-black">V</th><th class="sub-head px-3 py-2 text-right text-[10px] font-black">A</th>';
    top+='</tr>';document.getElementById('mpptHead').innerHTML=top;
}
function buildStringHead(){
    const max=maxStringColumns();let html='<tr><th class="sticky-col bg-slate-100 px-4 py-2.5 text-left font-black uppercase tracking-wider text-slate-700 min-w-[140px]">Inverter</th>';
    for(let i=1;i<=max;i++)html+=`<th class="bg-slate-50 px-2.5 py-2 text-center text-[10px] font-black text-slate-600 min-w-[58px]">${i}</th>`;
    html+='</tr>';document.getElementById('stringHead').innerHTML=html;
}
function mpptRows(){
    const count=observedMpptColumns();
    return Object.keys(data).sort((a,b)=>invNo(a)-invNo(b)).map(name=>{
        const item=data[name];let row=`<tr class="hover:bg-slate-50/70"><td class="sticky-col bg-white px-4 py-3 font-black text-slate-800">${name}<div class="text-[9px] mt-0.5 ${item.received?'text-emerald-600':'text-slate-400'}">${item.received?'LIVE':'WAITING'}</div></td>`;
        for(let i=1;i<=count;i++){const m=item.mppt[i]||{};row+=`<td class="px-3 py-3 text-right font-mono text-slate-700">${fmt(m.voltage,1)}</td><td class="px-3 py-3 text-right font-mono text-slate-700">${fmt(m.current,2)}</td>`;}
        return row+'</tr>';
    }).join('');
}
function stringCell(item,no,name){
    const limit=expectedStrings(name);if(no>limit)return '<td class="string-na px-2 py-3 text-center font-mono">N/A</td>';
    const s=item.strings[no];if(!s||s.curr===null||s.curr===undefined)return '<td class="string-missing px-2 py-3 text-center font-mono">--</td>';
    const active=Number(s.curr)>0.1,cls=active?'string-active':'string-zero',title=`String ${no}: ${fmt(s.curr,2)} A${s.volt!==null&&s.volt!==undefined?' / '+fmt(s.volt,1)+' V':''}`;
    return `<td class="${cls} px-2 py-3 text-center font-mono font-black" title="${title}">${fmt(s.curr,2)}</td>`;
}
function stringRows(){
    const max=maxStringColumns();
    return Object.keys(data).sort((a,b)=>invNo(a)-invNo(b)).map(name=>{
        const item=data[name],observed=Object.values(item.strings||{}).filter(s=>s.curr!==null&&s.curr!==undefined),active=observed.filter(s=>Number(s.curr)>0.1).length,health=observed.length?`${active}/${expectedStrings(name)}`:`--/${expectedStrings(name)}`;
        let row=`<tr><td class="sticky-col bg-white px-4 py-3"><div class="font-black text-slate-800">INV-${invNo(name)||name}</div><div class="text-[9px] mt-0.5 text-slate-400">${health} active</div></td>`;
        for(let i=1;i<=max;i++)row+=stringCell(item,i,name);return row+'</tr>';
    }).join('');
}
function render(){
    buildMpptHead();buildStringHead();document.getElementById('mpptBody').innerHTML=mpptRows();document.getElementById('stringBody').innerHTML=stringRows();
    const mpptCols=observedMpptColumns(),stats=stringStats(),liveCount=liveInverterCount();
    document.getElementById('mpptMeta').textContent=`${EXPECTED_COUNT} inverters × ${mpptCols} MPPT slots · live values only`;
    document.getElementById('stringMeta').textContent=`${EXPECTED_COUNT} inverter rows · up to ${maxStringColumns()} configured strings · ${PLANT_NAME}`;
    document.getElementById('activeTotal').textContent=stats.observed?stats.active:'--';document.getElementById('zeroTotal').textContent=stats.observed?stats.zero:'--';document.getElementById('observedTotal').textContent=stats.observed||'--';
    const zero=document.getElementById('zeroFaultBadge');zero.innerHTML=`<span class="status-dot ${stats.observed?(stats.zero?'bg-rose-500':'bg-emerald-500'):'bg-slate-400'}"></span>Zero current: ${stats.observed?stats.zero:'--'}`;zero.className=`inline-flex items-center gap-2 rounded-lg border px-3 py-2 ${stats.observed&&stats.zero?'border-rose-200 bg-rose-50 text-rose-600':stats.observed?'border-emerald-200 bg-emerald-50 text-emerald-700':'border-slate-200 bg-slate-50 text-slate-500'}`;
    const system=document.getElementById('systemBadge'),fresh=Date.now()-lastLiveAt<20000&&liveCount>0;system.innerHTML=`<span class="status-dot ${fresh?'bg-emerald-500':'bg-slate-400'}"></span>${fresh?'System live':`Waiting ${liveCount}/${EXPECTED_COUNT}`}`;system.className=`inline-flex items-center gap-2 rounded-lg border px-3 py-2 ${fresh?'border-emerald-200 bg-emerald-50 text-emerald-700':'border-slate-200 bg-white text-slate-500'}`;
}
function setSocketState(mode){
    const el=document.getElementById('headerLive');
    if(mode==='live'){el.innerHTML='<span class="status-dot bg-emerald-500"></span><span>LIVE</span>';el.className='inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[10px] font-black text-emerald-700 shadow-sm';}
    else if(mode==='error'){el.innerHTML='<span class="status-dot bg-rose-500"></span><span>RECONNECTING</span>';el.className='inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-[10px] font-black text-rose-600 shadow-sm';}
    else{el.innerHTML='<span class="status-dot bg-slate-400"></span><span>CONNECTING</span>';el.className='inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black text-slate-500 shadow-sm';}
}
function tickClock(){document.getElementById('clockDisplay').textContent=new Date().toLocaleTimeString('en-IN',{hour12:false,timeZone:'Asia/Kolkata'});}tickClock();setInterval(tickClock,1000);

function handle(d){
    if(!d||d.type==='daily_data_result')return;
    const pid=normalizeMessagePlant(d);if(pid&&pid!==CURRENT_PLANT)return;
    const values=messageValues(d),deviceRaw=messageDevice(d),device=deviceRaw.toLowerCase(),task=String(d.task||d.data?.task||'').toLowerCase();
    const strings=parseStrings(values),mppt=parseMppt(values),hasString=Object.keys(strings).length>0,hasMppt=Object.keys(mppt).length>0;
    const isVcb=task==='vcb'||device.includes('vcb'),isTransformer=task==='transformer'||device.includes('transformer');
    const isInv=!isVcb&&!isTransformer&&(task==='inverter'||device.includes('inverter')||hasString||hasMppt);if(!isInv)return;
    const name=canonical(deviceRaw||'Inverter'),old=data[name]||{received:false,strings:{},mppt:{},lastLive:0,deviceName:name};
    data[name]={received:true,strings:mergeChannelMap(old.strings,strings),mppt:mergeChannelMap(old.mppt,mppt),lastLive:Date.now(),deviceName:deviceRaw||old.deviceName||name};
    lastLiveAt=Date.now();setSocketState('live');render();
}
window.handleLive=handle;

function connect(){
    if(ws&&(ws.readyState===WebSocket.OPEN||ws.readyState===WebSocket.CONNECTING))return;setSocketState('connecting');
    try{ws=new WebSocket(WS_URL);ws.onopen=()=>{socketOpen=true;setSocketState('live');ws.send(JSON.stringify({type:'subscribe',unit_id:CURRENT_PLANT}));};ws.onmessage=e=>{try{handle(JSON.parse(e.data));}catch(err){console.error('MPPT/string message error',err);}};ws.onclose=()=>{socketOpen=false;ws=null;setSocketState('error');clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connect,3000);};ws.onerror=()=>{try{ws.close();}catch(_){}};}catch(_){socketOpen=false;setSocketState('error');reconnectTimer=setTimeout(connect,3000);}
}

render();connect();setInterval(render,5000);
</script>
</body>
</html>
