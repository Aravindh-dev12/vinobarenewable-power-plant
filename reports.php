<?php
require 'check_auth.php';
require 'config.php';

$plants = [];
try {
    $pRes = $conn->query("SELECT id, name, capacity, location FROM plants ORDER BY name ASC");
    if ($pRes) while ($row = $pRes->fetch_assoc()) $plants[] = $row;
} catch (Throwable $e) {}
if (empty($plants)) {
    $plants = [
        ['id'=>'vinoba-velliyanai','name'=>'Vinoba Velliyanai','capacity'=>2.0,'location'=>'Karur'],
        ['id'=>'makkalpower','name'=>'Makkal Power','capacity'=>2.0,'location'=>'Karur'],
        ['id'=>'anushyam','name'=>'Anushyam Plant','capacity'=>2.0,'location'=>'Karur']
    ];
}

$userRole = $user['role'] ?? 'user';
$userPlant = $user['plant_id'] ?? '';
$requestedPlant = isset($_GET['plant']) ? trim($_GET['plant']) : '';
if ($userRole !== 'admin' && $userPlant) $selectedPlant = $userPlant;
elseif ($requestedPlant) $selectedPlant = $requestedPlant;
else $selectedPlant = $plants[0]['id'] ?? 'vinoba-velliyanai';

$plantMap = [];
foreach ($plants as $p) $plantMap[$p['id']] = $p;
$selectedInfo = $plantMap[$selectedPlant] ?? ['name'=>'All Plants','capacity'=>0,'location'=>'Multiple Sites'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>System Reports | Vinoba Solar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=3" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body{font-family:Inter,sans-serif}.table-wrap{overflow-x:auto}.report-table{border-collapse:collapse;min-width:900px;width:100%}
        .report-table th,.report-table td{padding:6px 7px;text-align:center;white-space:nowrap;border-bottom:1px solid #eef2f7;font-variant-numeric:tabular-nums}
        .report-table th{font-size:10px;text-transform:uppercase;color:#475569;background:#f8fafc;font-weight:800}.report-table td{font-size:11px;color:#334155}
        .pdf-mode .table-wrap{overflow:visible!important}.pdf-mode .report-table{width:100%!important}.status-dot{width:8px;height:8px;border-radius:9999px;display:inline-block}
    </style>
</head>
<body class="bg-slate-50 text-slate-800">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 md:ml-64 w-full overflow-x-hidden">
        <header class="sticky top-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="menuBtn" class="md:hidden text-emerald-700 text-2xl">&#9776;</button>
                <div><h1 class="text-xl font-extrabold">System Reports</h1><p class="text-xs text-slate-500">Daily live and monthly generation reports</p></div>
            </div>
            <div class="flex gap-2">
                <button onclick="exportToPDF()" class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-bold"><i class="fa-solid fa-file-pdf mr-1"></i><span class="hidden sm:inline">PDF</span></button>
                <button id="jsonBtn" onclick="downloadJson()" disabled class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold opacity-50 cursor-not-allowed"><i class="fa-solid fa-download mr-1"></i><span class="hidden sm:inline">JSON</span></button>
            </div>
        </header>

        <section class="p-4 sm:p-6 max-w-[1700px] mx-auto space-y-5">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-wrap gap-3 items-center justify-between">
                <div class="flex items-center gap-2 text-sm font-bold text-emerald-700"><i class="fa-solid fa-chart-column"></i>Inverter &amp; VCB MFM Report</div>
                <div class="flex flex-wrap gap-2 items-center">
                    <select id="plantSelect" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 font-semibold"></select>
                    <select id="reportType" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 font-semibold">
                        <option value="daily">Daily</option><option value="monthly">Monthly</option>
                    </select>
                    <input id="dateSelect" type="date" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 font-semibold">
                    <input id="monthSelect" type="month" class="hidden border border-slate-300 rounded-lg px-3 py-2 text-sm bg-slate-50 font-semibold">
                    <button id="viewBtn" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-bold"><i class="fa-solid fa-eye mr-1"></i>View</button>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div id="printableReport" class="p-5 bg-white">
                    <div class="border-b-2 border-emerald-600 pb-4 mb-5">
                        <div class="flex flex-wrap justify-between gap-3 items-start">
                            <div><h2 id="plantTitle" class="text-2xl font-black tracking-tight"><?php echo htmlspecialchars(strtoupper($selectedInfo['name'])); ?></h2><p class="text-lg font-bold text-emerald-700 mt-1">Inverter &amp; VCB MFM Report</p></div>
                            <div id="statusBox" class="text-xs font-bold text-slate-500 flex items-center gap-2"><span class="status-dot bg-slate-400"></span>Waiting for report</div>
                        </div>
                        <div class="grid sm:grid-cols-3 gap-3 mt-4 bg-slate-50 border border-slate-100 rounded-lg p-3 text-sm">
                            <div><span class="block text-[10px] uppercase font-extrabold text-slate-400">Plant Location</span><span id="plantLocation" class="font-bold"><?php echo htmlspecialchars($selectedInfo['location'] ?? 'Unknown'); ?></span></div>
                            <div><span class="block text-[10px] uppercase font-extrabold text-slate-400">Plant Capacity</span><span id="plantCapacity" class="font-bold"><?php echo htmlspecialchars((string)($selectedInfo['capacity'] ?? 0)); ?> MW</span></div>
                            <div><span id="periodLabel" class="block text-[10px] uppercase font-extrabold text-slate-400">Report Date</span><span id="displayDate" class="font-bold">--</span></div>
                        </div>
                    </div>
                    <div class="table-wrap"><table class="report-table"><thead id="reportHead"></thead><tbody id="reportBody"><tr><td class="py-12 text-slate-400">Loading report...</td></tr></tbody></table></div>
                    <div class="mt-10 pt-8 border-t border-slate-200 flex justify-between text-xs sm:text-sm font-bold">
                        <div class="w-36 sm:w-44 text-center"><div class="border-b border-slate-400 h-7 mb-2"></div>Operator Signature</div>
                        <div class="w-36 sm:w-44 text-center"><div class="border-b border-slate-400 h-7 mb-2"></div>Site Engineer</div>
                        <div class="w-36 sm:w-44 text-center"><div class="border-b border-slate-400 h-7 mb-2"></div>Plant Manager</div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
const userRole = <?php echo json_encode($userRole); ?>;
const userPlant = <?php echo json_encode($userPlant); ?>;
const initialPlant = <?php echo json_encode($selectedPlant); ?>;
const plantOptions = <?php echo json_encode($plants); ?>;
const plantMeta = Object.fromEntries(plantOptions.map(p => [p.id, p]));
const token = new URLSearchParams(location.search).get('token') || sessionStorage.getItem('vs_token') || '';
const WS_URL = 'wss://vinobasolar.scadahub.in:5001';
const plantSelect = document.getElementById('plantSelect');
const reportType = document.getElementById('reportType');
const dateSelect = document.getElementById('dateSelect');
const monthSelect = document.getElementById('monthSelect');
let lastReportData = null;
let refreshTimer = null;
let ws = null;
let wsPlant = '';
let liveInv = {};
let liveVcbToday = null;
let renderQueued = false;

function indiaParts() {
    const parts = new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date());
    const o={}; parts.forEach(p=>{if(p.type!=='literal')o[p.type]=p.value;}); return o;
}
function indiaToday(){const p=indiaParts();return `${p.year}-${p.month}-${p.day}`;}
function indiaMonth(){return indiaToday().slice(0,7);}
function currentQuarterHour(){const p=indiaParts();const m=Math.floor(Number(p.minute)/15)*15;return `${p.hour}:${String(m).padStart(2,'0')}`;}
function fmt(v){const n=Number(v);return Number.isFinite(n)?n.toFixed(2):'0.00';}
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}

function initPlants(){
    if(userRole==='admin') plantSelect.add(new Option('All Plants','all'));
    plantOptions.forEach(p=>{if(userRole==='admin'||p.id===userPlant) plantSelect.add(new Option(p.name,p.id));});
    if(userRole!=='admin' && userPlant && !Array.from(plantSelect.options).some(o=>o.value===userPlant)) plantSelect.add(new Option(userPlant,userPlant));
    plantSelect.value = Array.from(plantSelect.options).some(o=>o.value===initialPlant) ? initialPlant : (userRole!=='admin'?userPlant:(plantOptions[0]?.id||'all'));
    if(userRole!=='admin') plantSelect.classList.add('hidden');
    updatePlantHeader();
}
function updatePlantHeader(){
    const id=plantSelect.value, p=plantMeta[id];
    document.getElementById('plantTitle').textContent = id==='all' ? 'ALL PLANTS' : ((p?.name||id).toUpperCase());
    document.getElementById('plantLocation').textContent = id==='all' ? 'Multiple Sites' : (p?.location||'Unknown');
    document.getElementById('plantCapacity').textContent = id==='all' ? `${plantOptions.reduce((s,x)=>s+Number(x.capacity||0),0)} MW` : `${p?.capacity||0} MW`;
}
function togglePeriod(){
    const monthly=reportType.value==='monthly';
    dateSelect.classList.toggle('hidden',monthly); monthSelect.classList.toggle('hidden',!monthly);
    document.getElementById('periodLabel').textContent = monthly?'Report Month':'Report Date';
    if(monthly){closeWS();stopAutoRefresh();}else{startAutoRefresh();connectWS();}
}
function setStatus(text,kind='neutral'){
    const box=document.getElementById('statusBox');
    const cls=kind==='live'?'bg-emerald-500':kind==='error'?'bg-red-500':'bg-slate-400';
    box.innerHTML=`<span class="status-dot ${cls}"></span>${esc(text)}`;
}
function displayPeriod(){
    if(reportType.value==='daily'){
        const d=new Date(dateSelect.value+'T00:00:00'); document.getElementById('displayDate').textContent=d.toLocaleDateString('en-IN',{year:'numeric',month:'long',day:'numeric'});
    } else {
        const d=new Date(monthSelect.value+'-01T00:00:00'); document.getElementById('displayDate').textContent=d.toLocaleDateString('en-IN',{year:'numeric',month:'long'});
    }
}
async function fetchReport(){
    const type=reportType.value, period=type==='daily'?dateSelect.value:monthSelect.value, plant=plantSelect.value;
    if(!period)return;
    displayPeriod();
    document.getElementById('reportBody').innerHTML='<tr><td class="py-12 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Fetching report...</td></tr>';
    try{
        const q=new URLSearchParams({tab:'inv_vcb',type,date:period,plant}); if(token)q.set('token',token);
        const res=await fetch('api_reports.php?'+q.toString(),{cache:'no-store',headers:token?{'Authorization':'Bearer '+token}:{}});
        const text=await res.text(); let json; try{json=JSON.parse(text);}catch(e){throw new Error('Invalid server response');}
        if(!json.success)throw new Error(json.error||'Report request failed');
        lastReportData=json;
        mergeLiveIntoReport();
        renderReport();
        const src=json.meta?.source==='db_live'?'Live database':'Report database';
        setStatus(`${src} • ${json.meta?.generated_at||'updated'}`,json.meta?.source==='db_live'?'live':'neutral');
        enableJson();
    }catch(err){
        document.getElementById('reportHead').innerHTML='';
        document.getElementById('reportBody').innerHTML=`<tr><td class="py-12 text-red-600 font-bold">${esc(err.message)}</td></tr>`;
        setStatus(err.message,'error');
    }
}
function enableJson(){const b=document.getElementById('jsonBtn');b.disabled=false;b.classList.remove('opacity-50','cursor-not-allowed');}
function mergeLiveIntoReport(){
    if(!lastReportData||reportType.value!=='daily'||dateSelect.value!==indiaToday()||plantSelect.value==='all')return;
    const rows=lastReportData.data||[]; if(!rows.length)return;
    const names=lastReportData.meta?.inv_names||[];
    Object.keys(liveInv).sort().forEach(name=>{
        let idx=names.indexOf(name);
        if(idx<0){names.push(name);idx=names.length-1;rows.forEach(r=>{r['inv'+(idx+1)+'_kwh']=0;r['inv'+(idx+1)+'_kw']=0;});}
    });
    lastReportData.meta.inv_names=names;
    const label=currentQuarterHour();
    const row=rows.find(r=>r.time_label===label); if(!row)return;
    Object.entries(liveInv).forEach(([name,v])=>{const i=names.indexOf(name)+1;if(i>0){row['inv'+i+'_kwh']=Number(v.kwh||0);row['inv'+i+'_kw']=Number(v.kw||0);}});
    let total=0;names.forEach((_,i)=>total+=Number(row['inv'+(i+1)+'_kwh']||0));row.inv_total_kwh=total;
    if(liveVcbToday!==null)row.vcb_kwh=Number(liveVcbToday||0);
    row.tx_loss=Number(row.inv_total_kwh||0)-Number(row.vcb_kwh||0);
}
function renderReport(){
    if(!lastReportData)return;
    const rows=lastReportData.data||[], names=lastReportData.meta?.inv_names||[];
    let top='<tr><th rowspan="2">'+(reportType.value==='daily'?'Time':'Date')+'</th>';
    names.forEach(n=>top+=`<th class="bg-blue-50">${esc(n)}</th>`);top+='<th class="bg-indigo-50">INV Total</th><th class="bg-purple-50">HT Panel (VCB)</th><th class="bg-red-50">TX Loss</th></tr>';
    let sub='<tr>'+names.map(()=>'<th class="bg-blue-50">kWh</th>').join('')+'<th class="bg-indigo-50">kWh</th><th class="bg-purple-50">kWh</th><th class="bg-red-50">kWh</th></tr>';
    document.getElementById('reportHead').innerHTML=top+sub;
    if(!rows.length){document.getElementById('reportBody').innerHTML='<tr><td class="py-12 text-slate-400" colspan="20">No data found.</td></tr>';return;}
    const now=indiaParts();const nowMin=Number(now.hour)*60+Number(now.minute);const today=indiaToday();
    let html='';
    rows.forEach((r,ri)=>{
        let future=false;if(reportType.value==='daily'&&dateSelect.value===today){const m=String(r.time_label||'').match(/^(\d{2}):(\d{2})$/);if(m)future=(Number(m[1])*60+Number(m[2]))>nowMin;}
        html+=`<tr class="${ri%2?'bg-slate-50/60':'bg-white'}"><td class="font-bold bg-slate-50">${esc(r.time_label||'-')}</td>`;
        names.forEach((_,i)=>html+=`<td class="text-blue-700 font-semibold">${fmt(future?0:r['inv'+(i+1)+'_kwh'])}</td>`);
        html+=`<td class="font-extrabold text-indigo-700">${fmt(future?0:r.inv_total_kwh)}</td><td class="font-extrabold text-purple-700">${fmt(future?0:r.vcb_kwh)}</td><td class="font-bold text-red-600">${fmt(future?0:r.tx_loss)}</td></tr>`;
    });
    document.getElementById('reportBody').innerHTML=html;
}
function parseLiveMessage(d){
    if(!d||d.unit_id!==plantSelect.value||reportType.value!=='daily'||dateSelect.value!==indiaToday())return;
    const values=d.values||{}, task=String(d.task||'').toLowerCase(), device=String(d.device||'');
    const keys=Object.keys(values), isInv=task==='inverter'||device.toLowerCase().includes('inverter')||keys.some(k=>/active.*power|ac.*power|power.*ac/i.test(k)&&!/reactive|apparent/i.test(k));
    if(isInv){
        let kwh=0,kw=0;for(const [k,val] of Object.entries(values)){const kl=k.toLowerCase();if(/daily.*generation|daily.*gen/.test(kl))kwh=Number(val)||0;if(/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(kl)&&!/reactive|apparent|3\.phase/.test(kl))kw=Number(val)||0;}
        if(device)liveInv[device]={kwh,kw};
    }
    const isVcb=task==='vcb'||device.toLowerCase().includes('vcb');
    if(isVcb&&d.virtualTags&&d.virtualTags['vcb-today']!==undefined){liveVcbToday=Number(d.virtualTags['vcb-today']?.value)||0;}
    if(!renderQueued){renderQueued=true;setTimeout(()=>{renderQueued=false;mergeLiveIntoReport();renderReport();if(ws&&ws.readyState===WebSocket.OPEN)setStatus('Live telemetry connected','live');},250);}
}
function connectWS(){
    closeWS();liveInv={};liveVcbToday=null;
    if(reportType.value!=='daily'||plantSelect.value==='all'||dateSelect.value!==indiaToday())return;
    wsPlant=plantSelect.value;
    try{
        ws=new WebSocket(WS_URL);
        ws.onopen=()=>{if(ws&&ws.readyState===WebSocket.OPEN)ws.send(JSON.stringify({type:'subscribe',unit_id:wsPlant}));setStatus('Live telemetry connected','live');};
        ws.onmessage=e=>{try{parseLiveMessage(JSON.parse(e.data));}catch(_){} };
        ws.onclose=()=>{if(reportType.value==='daily'&&plantSelect.value===wsPlant&&dateSelect.value===indiaToday())setTimeout(connectWS,5000);};
        ws.onerror=()=>setStatus('Live socket unavailable; using database','neutral');
    }catch(_){setStatus('Using database report','neutral');}
}
function closeWS(){if(ws){ws.onclose=null;try{ws.close();}catch(_){}ws=null;}wsPlant='';}
function startAutoRefresh(){stopAutoRefresh();if(reportType.value==='daily')refreshTimer=setInterval(fetchReport,30000);}
function stopAutoRefresh(){if(refreshTimer){clearInterval(refreshTimer);refreshTimer=null;}}
function downloadJson(){
    if(!lastReportData)return;const blob=new Blob([JSON.stringify(lastReportData,null,2)],{type:'application/json'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`solar_${plantSelect.value}_${reportType.value}_${reportType.value==='daily'?dateSelect.value:monthSelect.value}.json`;a.click();URL.revokeObjectURL(a.href);
}
function exportToPDF(){
    const el=document.getElementById('printableReport'),clone=el.cloneNode(true);clone.classList.add('pdf-mode');clone.style.position='absolute';clone.style.left='0';clone.style.top='0';clone.style.zIndex='-9999';clone.style.width=Math.max(document.querySelector('.report-table').scrollWidth+40,1100)+'px';document.body.appendChild(clone);
    html2pdf().set({margin:8,filename:`solar_${plantSelect.value}_${reportType.value}_report.pdf`,image:{type:'jpeg',quality:.98},html2canvas:{scale:1.5,useCORS:true,windowWidth:clone.scrollWidth+40},jsPDF:{unit:'mm',format:'a4',orientation:'landscape'}}).from(clone).save().finally(()=>clone.remove());
}
function loadSidebar(){
    fetch('sidebar.html',{cache:'no-store'}).then(r=>r.text()).then(html=>{document.getElementById('sidebar-container').innerHTML=html;const p=plantSelect.value||userPlant||'vinoba-velliyanai';document.querySelectorAll('#sidebarNav a').forEach(a=>{let h=a.getAttribute('href');if(!h||h.includes('logout'))return;const u=new URL(h,location.href);u.searchParams.set('plant',p);if(token)u.searchParams.set('token',token);a.setAttribute('href',u.pathname.split('/').pop()+u.search);});if(typeof initSidebar==='function')initSidebar();document.getElementById('menuBtn')?.addEventListener('click',()=>{document.getElementById('sidebar')?.classList.remove('-translate-x-full');document.getElementById('overlay')?.classList.remove('hidden');});document.getElementById('closeSidebarBtn')?.addEventListener('click',()=>{document.getElementById('sidebar')?.classList.add('-translate-x-full');document.getElementById('overlay')?.classList.add('hidden');});document.getElementById('overlay')?.addEventListener('click',()=>{document.getElementById('sidebar')?.classList.add('-translate-x-full');document.getElementById('overlay')?.classList.add('hidden');});});
}

initPlants();dateSelect.value=indiaToday();monthSelect.value=indiaMonth();togglePeriod();loadSidebar();
document.getElementById('viewBtn').addEventListener('click',()=>{fetchReport();connectWS();startAutoRefresh();});
reportType.addEventListener('change',()=>{togglePeriod();fetchReport();});
dateSelect.addEventListener('change',()=>{fetchReport();connectWS();});
monthSelect.addEventListener('change',fetchReport);
plantSelect.addEventListener('change',()=>{updatePlantHeader();liveInv={};liveVcbToday=null;fetchReport();connectWS();loadSidebar();});
fetchReport();connectWS();startAutoRefresh();
window.addEventListener('beforeunload',()=>{closeWS();stopAutoRefresh();});
</script>
</body></html>
