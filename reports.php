<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/plant_config.php';

$catalog = plant_catalog();
$userRole = $user['role'] ?? 'user';
$userPlant = normalize_plant_id($user['plant_id'] ?? '');
$requested = trim((string)($_GET['plant'] ?? ''));
$selectedPlant = $userRole === 'admin'
    ? ($requested === 'all' ? 'all' : normalize_plant_id($requested))
    : $userPlant;
if ($selectedPlant !== 'all' && !isset($catalog[$selectedPlant])) $selectedPlant = 'vinoba-1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Reports</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="sidebar-control.js?v=8" defer></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
body{font-family:'Inter',sans-serif;background:#f8fafc;color:#1e293b}
.report-table{table-layout:fixed;min-width:1200px;width:100%;border-collapse:collapse}
.table-hscroll{overflow-x:auto;overflow-y:visible}
.report-table th,.report-table td{padding:5px 3px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.report-table th{font-size:9px;line-height:1.1;text-transform:uppercase;color:#475569;font-weight:700;border-bottom:2px solid #e2e8f0;background:#f8fafc}
.report-table td{font-size:11px;color:#334155;border-bottom:1px solid #f1f5f9;font-variant-numeric:tabular-nums;background:#fff}
.report-table tbody tr:hover td{background:#f8fafc}
.table-hscroll::-webkit-scrollbar{height:10px}.table-hscroll::-webkit-scrollbar-track{background:#f8fafc;border-radius:5px}.table-hscroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:5px}
.status-dot{width:8px;height:8px;border-radius:999px;display:inline-block}
.pdf-mode{background:#fff!important;padding:12px!important}.pdf-mode .table-hscroll{overflow:visible!important;width:100%!important}.pdf-mode .report-table{width:100%!important}.pdf-mode .no-print{display:none!important}
</style>
</head>
<body class="h-full bg-slate-50 text-slate-800">
<div class="min-h-screen flex relative">
<div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
<div id="sidebar-container"></div>

<main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
<header class="bg-white border-b border-gray-200 p-4 sm:px-6 flex justify-between items-center shadow-sm sticky top-0 z-20">
    <div class="flex items-center gap-3 sm:gap-4">
        <button id="menuBtn" class="md:hidden text-green-700 text-2xl focus:outline-none">&#9776;</button>
        <div>
            <h2 class="text-lg sm:text-xl font-bold text-gray-800">System Reports</h2>
            <p class="text-xs text-gray-500 hidden sm:block">Generate, view and export plant data</p>
        </div>
    </div>
    <div class="flex items-center gap-2 no-print">
        <button onclick="exportPDF()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-3 sm:px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm">
            <i class="fa-solid fa-file-pdf"></i><span class="hidden sm:inline">Export PDF</span>
        </button>
        <button onclick="downloadJson()" id="jsonBtn" disabled class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3 sm:px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 text-sm opacity-50 cursor-not-allowed">
            <i class="fa-solid fa-download"></i><span class="hidden sm:inline">JSON</span>
        </button>
    </div>
</header>

<div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5 flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center no-print">
        <div class="flex flex-col gap-1">
            <div class="px-4 py-2 text-sm font-bold rounded-lg bg-green-50 text-green-700 border border-green-200 w-fit">Inverter &amp; HT/VCB</div>
            <p class="text-[11px] text-slate-500">Daily report: 05:00 AM–07:00 PM IST · 15-minute intervals</p>
        </div>
        <div class="flex items-center gap-2 w-full lg:w-auto flex-wrap">
            <select id="plantSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer"></select>
            <select id="typeSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium cursor-pointer">
                <option value="daily">Daily</option>
                <option value="monthly">Monthly</option>
            </select>
            <input type="date" id="dateSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium">
            <input type="month" id="monthSelect" class="hidden border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-gray-50 font-medium">
            <button id="viewBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition flex items-center gap-2 text-sm">
                <i class="fa-solid fa-eye"></i> View
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-1 flex flex-col">
        <div id="printableReport" class="p-5 bg-white w-full">
            <div class="border-b-2 border-green-600 pb-4 mb-5">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-2">
                    <div>
                        <h1 id="plantTitle" class="text-2xl font-black text-gray-900 tracking-tight">--</h1>
                        <h2 id="reportMainTitle" class="text-lg font-bold text-green-700 mt-1">Inverter &amp; HT/VCB Report</h2>
                        <p id="serviceLine" class="text-xs font-bold text-emerald-700 mt-1"></p>
                    </div>
                    <div id="status" class="text-xs font-bold text-slate-500 flex gap-2 items-center sm:justify-end">
                        <span class="status-dot bg-slate-400"></span>Waiting
                    </div>
                </div>

                <div class="report-info-grid grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm mt-4 font-medium text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <div><span class="text-gray-500 uppercase text-[10px] font-bold block">Plant Location</span><span id="location">--</span></div>
                    <div><span class="text-gray-500 uppercase text-[10px] font-bold block">Plant Capacity</span><span id="capacity">--</span></div>
                    <div><span class="text-gray-500 uppercase text-[10px] font-bold block" id="reportDateLabel">Report Date</span><span id="displayDate" class="font-bold text-gray-900">--</span></div>
                    <div><span class="text-gray-500 uppercase text-[10px] font-bold block">Report Window</span><span id="windowText" class="font-bold text-gray-900">05:00–19:00 IST</span><br><span id="liveStatus" class="text-[10px] font-bold text-slate-500 mt-0.5 inline-flex items-center gap-1"><span class="status-dot bg-slate-400"></span>Database report</span></div>
                </div>
            </div>

            <div id="htNote" class="hidden mb-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs font-bold text-amber-800">
                HT/VCB telemetry is not available for this period. Inverter data is still shown.
            </div>

            <div class="table-hscroll w-full">
                <table class="w-full report-table border-collapse">
                    <thead id="head"></thead>
                    <tbody id="body">
                        <tr><td colspan="30" class="py-10 text-center text-gray-500">Loading report data...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-12 pt-8 flex justify-between gap-6 text-sm font-bold text-gray-800 border-t border-gray-200">
                <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Operator Signature</div>
                <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Site Engineer</div>
                <div class="text-center w-40"><div class="border-b border-gray-400 mb-2 h-8"></div>Plant Manager</div>
            </div>
        </div>
    </div>
</div>
</main>
</div>

<script>
const catalog=<?php echo json_encode($catalog,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const role=<?php echo json_encode($userRole); ?>;
const assigned=<?php echo json_encode($userPlant); ?>;
const initial=<?php echo json_encode($selectedPlant); ?>;
const token=new URLSearchParams(location.search).get('token')||sessionStorage.getItem('vs_token')||localStorage.getItem('vs_token')||'';
const plantSelect=document.getElementById('plantSelect');
const typeSelect=document.getElementById('typeSelect');
const dateSelect=document.getElementById('dateSelect');
const monthSelect=document.getElementById('monthSelect');
let report=null,ws=null,liveInv={},liveVcb=null,refreshTimer=null,reconnectTimer=null,wsGeneration=0;

function indiaParts(){const o={};new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(p=>{if(p.type!=='literal')o[p.type]=p.value});return o}
function today(){const p=indiaParts();return `${p.year}-${p.month}-${p.day}`}
function month(){return today().slice(0,7)}
function currentMinutes(){const p=indiaParts();return Number(p.hour)*60+Number(p.minute)}
function quarter(){const p=indiaParts();return `${p.hour}:${String(Math.floor(Number(p.minute)/15)*15).padStart(2,'0')}`}
function insideReportWindow(){const m=currentMinutes();return m>=300&&m<=1140}
function isTodayDaily(){return typeSelect.value==='daily'&&dateSelect.value===today()&&plantSelect.value!=='all'}
function liveEligible(){return isTodayDaily()&&insideReportWindow()}
function f(v){const n=Number(v);return Number.isFinite(n)?n.toFixed(2):'--'}
function esc(x){return String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}

function init(){
    if(role==='admin')plantSelect.add(new Option('All Plants','all'));
    Object.values(catalog).forEach(p=>{if(role==='admin'||p.id===assigned)plantSelect.add(new Option(p.name,p.id))});
    plantSelect.value=[...plantSelect.options].some(o=>o.value===initial)?initial:(role==='admin'?'vinoba-1':assigned);
    if(role!=='admin')plantSelect.classList.add('hidden');
    dateSelect.value=today();
    monthSelect.value=month();
    updatePlantHeader();
    toggleInputs();
    fetchReport();
}

function updatePlantHeader(){
    const id=plantSelect.value;
    const p=catalog[id];
    document.getElementById('plantTitle').textContent=id==='all'?'ALL PLANTS':(p?.name||id).toUpperCase();
    document.getElementById('serviceLine').textContent=id==='all'?'Service Numbers: 06914430133 / 06914430134':'Service Number - '+(p?.service_number||'--');
    document.getElementById('location').textContent=id==='all'?'Multiple Sites':(p?.location||'--');
    document.getElementById('capacity').textContent=id==='all'?Object.values(catalog).reduce((s,x)=>s+Number(x.capacity||0),0)+' MW':Number(p?.capacity||0)+' MW';
}

function toggleInputs(){
    const monthly=typeSelect.value==='monthly';
    dateSelect.classList.toggle('hidden',monthly);
    monthSelect.classList.toggle('hidden',!monthly);
    document.getElementById('reportDateLabel').textContent=monthly?'Report Month':'Report Date';
    document.getElementById('reportMainTitle').textContent=monthly?'Monthly Inverter & HT/VCB Report':'Daily Inverter & HT/VCB Report';
    document.getElementById('windowText').textContent=monthly?'Each day 05:00–19:00 IST':'05:00–19:00 IST';
    if(monthly){closeWS();clearInterval(refreshTimer);refreshTimer=null}else{connectWS();if(!refreshTimer)refreshTimer=setInterval(fetchReport,30000)}
}

function setStatus(text,kind='normal'){
    const cls=kind==='live'?'bg-emerald-500':kind==='error'?'bg-red-500':'bg-slate-400';
    document.getElementById('status').innerHTML=`<span class="status-dot ${cls}"></span>${esc(text)}`;
}

function updateLiveStatus(){
    const el=document.getElementById('liveStatus');
    if(liveEligible())el.innerHTML='<span class="status-dot bg-emerald-500"></span>Live WebSocket';
    else if(isTodayDaily())el.innerHTML='<span class="status-dot bg-slate-400"></span>Report window closed';
    else el.innerHTML='<span class="status-dot bg-slate-400"></span>Database report';
}

async function fetchReport(){
    const type=typeSelect.value;
    const period=type==='daily'?dateSelect.value:monthSelect.value;
    const plant=plantSelect.value;
    if(!period)return;
    document.getElementById('displayDate').textContent=period;
    try{
        const q=new URLSearchParams({tab:'inv_vcb',type,date:period,plant});
        if(token)q.set('token',token);
        const r=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:token?{'Authorization':'Bearer '+token}:{}});
        const j=await r.json();
        if(!j.success)throw new Error(j.error||'Report failed');
        report=j;
        mergeLive();
        render();
        setStatus((j.meta?.source==='db_live'?'Live database':'Report database')+' • '+(j.meta?.generated_at||''),j.meta?.source==='db_live'?'live':'normal');
        const btn=document.getElementById('jsonBtn');btn.disabled=false;btn.classList.remove('opacity-50','cursor-not-allowed');
        updateLiveStatus();
    }catch(e){
        setStatus(e.message,'error');
        document.getElementById('body').innerHTML=`<tr><td colspan="30" class="py-10 text-red-600 font-bold">${esc(e.message)}</td></tr>`;
    }
}

function mergeLive(){
    if(!report||!liveEligible())return;
    const rows=report.data||[];
    const names=report.meta.inv_names||[];
    Object.keys(liveInv).forEach(name=>{if(!names.includes(name)){names.push(name);const i=names.length;rows.forEach(r=>{r['inv'+i+'_kwh']=0;r['inv'+i+'_kw']=0})}});
    const label=quarter();
    if(label<'05:00'||label>'19:00')return;
    const row=rows.find(r=>r.time_label===label);
    if(!row)return;
    Object.entries(liveInv).forEach(([name,v])=>{const i=names.indexOf(name)+1;row['inv'+i+'_kwh']=Number(v.kwh||0);row['inv'+i+'_kw']=Number(v.kw||0)});
    row.inv_total_kwh=names.reduce((s,_,i)=>s+Number(row['inv'+(i+1)+'_kwh']||0),0);
    row.inv_total_kw=names.reduce((s,_,i)=>s+Number(row['inv'+(i+1)+'_kw']||0),0);
    if(liveVcb!==null){row.vcb_kwh=liveVcb;report.meta.ht_available=true;row.tx_loss=row.inv_total_kwh-row.vcb_kwh}
}

function render(){
    if(!report)return;
    const names=report.meta.inv_names||[];
    const ht=!!report.meta.ht_available;
    document.getElementById('htNote').classList.toggle('hidden',ht);
    let h='<tr><th>Time / Date</th>';
    names.forEach(n=>h+=`<th title="${esc(n)}">${esc(n)} kWh</th><th title="${esc(n)}">${esc(n)} kW</th>`);
    h+='<th>Inv Total kWh</th><th>Inv Total kW</th><th>HT/VCB kWh</th><th>HT/VCB kW</th><th>TX Loss kWh</th></tr>';
    document.getElementById('head').innerHTML=h;
    const rows=report.data||[];
    document.getElementById('body').innerHTML=rows.map(r=>{
        let x=`<tr><td class="font-bold">${esc(r.time_label)}</td>`;
        names.forEach((_,i)=>x+=`<td>${f(r['inv'+(i+1)+'_kwh'])}</td><td>${f(r['inv'+(i+1)+'_kw'])}</td>`);
        x+=`<td class="font-bold">${f(r.inv_total_kwh)}</td><td class="font-bold">${f(r.inv_total_kw)}</td><td>${ht?f(r.vcb_kwh):'--'}</td><td>${ht?f(r.vcb_kw):'--'}</td><td>${ht&&r.tx_loss!==null?f(r.tx_loss):'--'}</td></tr>`;
        return x;
    }).join('')||'<tr><td colspan="30" class="py-10 text-gray-500">No report data</td></tr>';
}

function connectWS(){
    closeWS();
    updateLiveStatus();
    if(!liveEligible())return;
    const plant=plantSelect.value;
    const generation=++wsGeneration;
    try{
        const socket=new WebSocket('wss://vinobasolar.scadahub.in:5001');
        ws=socket;
        socket.onopen=()=>{if(generation!==wsGeneration)return;socket.send(JSON.stringify({type:'subscribe',unit_id:plant}));updateLiveStatus()};
        socket.onmessage=e=>{
            try{
                if(generation!==wsGeneration)return;
                const d=JSON.parse(e.data);if(d.unit_id!==plant)return;
                const vals=d.values||{};
                const task=String(d.task||'').toLowerCase();
                const dev=String(d.device||'');
                if(task==='inverter'||dev.toLowerCase().includes('inverter')){
                    let kwh=null,kw=null;
                    for(const [k,v] of Object.entries(vals)){
                        if(kwh===null&&/daily.*generation|daily.*gen/i.test(k))kwh=Number(v)||0;
                        if(kw===null&&/active.*power|ac.*power|power.*ac/i.test(k)&&!/reactive|apparent|3.phase/i.test(k))kw=Number(v)||0;
                    }
                    liveInv[dev||'Inverter']={kwh:kwh??liveInv[dev]?.kwh??0,kw:kw??liveInv[dev]?.kw??0};
                }
                for(const [k,v] of Object.entries(d.virtualTags||{}))if(/vcb.*today|today.*energy/i.test(k)){liveVcb=Number(typeof v==='object'?v.value:v)||0;break}
                mergeLive();render();setStatus('Live WebSocket • '+new Date().toLocaleTimeString('en-IN',{hour12:false}),'live');updateLiveStatus();
            }catch(_){ }
        };
        socket.onclose=()=>{if(generation!==wsGeneration)return;ws=null;if(liveEligible()){clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,4000)}else updateLiveStatus()};
        socket.onerror=()=>{try{socket.close()}catch(_){}};
    }catch(_){if(liveEligible()){clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,4000)}}
}

function closeWS(){
    wsGeneration++;
    clearTimeout(reconnectTimer);reconnectTimer=null;
    if(ws){const old=ws;ws=null;try{old.onclose=null;old.close()}catch(_){}}
    liveInv={};liveVcb=null;
}

function reportFileLabel(){
    if(plantSelect.value==='all')return 'all-plants';
    const name=catalog[plantSelect.value]?.name||'plant';
    return name.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
}
function downloadJson(){
    if(!report)return;
    const period=typeSelect.value==='daily'?dateSelect.value:monthSelect.value;
    const blob=new Blob([JSON.stringify(report,null,2)],{type:'application/json'}),a=document.createElement('a');
    a.href=URL.createObjectURL(blob);a.download=`report-${reportFileLabel()}-${typeSelect.value}-${period}.json`;a.click();URL.revokeObjectURL(a.href);
}
function exportPDF(){
    if(!report)return;
    const period=typeSelect.value==='daily'?dateSelect.value:monthSelect.value;
    const el=document.getElementById('printableReport');el.classList.add('pdf-mode');
    html2pdf().set({margin:5,filename:`report-${reportFileLabel()}-${typeSelect.value}-${period}.pdf`,html2canvas:{scale:1.35,useCORS:true},jsPDF:{orientation:'landscape',unit:'mm',format:'a4'},pagebreak:{mode:['css','legacy']}}).from(el).save().finally(()=>el.classList.remove('pdf-mode'));
}

plantSelect.addEventListener('change',()=>{updatePlantHeader();connectWS();fetchReport()});
typeSelect.addEventListener('change',()=>{toggleInputs();fetchReport()});
dateSelect.addEventListener('change',()=>{connectWS();fetchReport()});
monthSelect.addEventListener('change',fetchReport);
document.getElementById('viewBtn').addEventListener('click',()=>{connectWS();fetchReport()});
setInterval(()=>{if(typeSelect.value==='daily'){if(liveEligible()&&!ws)connectWS();else if(!liveEligible()&&ws)closeWS();updateLiveStatus()}},60000);
init();
</script>
</body>
</html>
