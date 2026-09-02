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
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SCADA Reports</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="sidebar-control.js?v=8" defer></script>
<style>
body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:#0f172a}
.surface{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}
.wrap{overflow:auto;max-height:68vh;border:1px solid #e2e8f0;border-radius:14px}
.tbl{border-collapse:separate;border-spacing:0;min-width:1120px;width:100%}
.tbl th,.tbl td{padding:8px 9px;text-align:center;white-space:nowrap;border-bottom:1px solid #e2e8f0;font-variant-numeric:tabular-nums}
.tbl th{font-size:10px;text-transform:uppercase;letter-spacing:.05em;background:#f8fafc;color:#475569;position:sticky;top:0;z-index:2}
.tbl td{font-size:11px;background:#fff}.tbl tbody tr:hover td{background:#f8fafc}.tbl tbody tr:last-child td{border-bottom:0}
.status-dot{width:8px;height:8px;border-radius:999px;display:inline-block}.metric{font-variant-numeric:tabular-nums;letter-spacing:-.02em}.mini{font-size:10px;line-height:14px;font-weight:900;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8}
.pdf-mode .wrap{overflow:visible;max-height:none;border:0}.pdf-mode .tbl th{position:static}.pdf-mode .no-print{display:none!important}
@media(max-width:767px){.surface{border-radius:14px}.wrap{max-height:none}}
</style>
</head>
<body>
<div class="min-h-screen flex relative">
<div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
<div id="sidebar-container"></div>
<main class="flex-1 w-full md:ml-64 overflow-x-hidden min-w-0">

<header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-200 px-4 sm:px-6 py-3">
  <div class="max-w-[1700px] mx-auto flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <button id="menuBtn" class="md:hidden w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-700" aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
      <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-[.14em] text-emerald-700">SCADA Reporting</p><h1 class="text-lg sm:text-xl font-black text-slate-950 truncate">Energy & Power Reports</h1></div>
    </div>
    <div class="flex items-center gap-2 no-print">
      <button onclick="exportPDF()" class="h-10 px-3 sm:px-4 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black"><i class="fa-solid fa-file-pdf sm:mr-2"></i><span class="hidden sm:inline">Export PDF</span></button>
      <button id="jsonBtn" onclick="downloadJson()" disabled class="h-10 px-3 sm:px-4 bg-emerald-600 text-white rounded-xl text-xs font-black opacity-50"><i class="fa-solid fa-code sm:mr-2"></i><span class="hidden sm:inline">JSON</span></button>
    </div>
  </div>
</header>

<section class="max-w-[1700px] mx-auto p-4 sm:p-6 space-y-5">
  <div class="surface p-4 sm:p-5 no-print">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
      <div><p class="text-sm font-black text-slate-900"><i class="fa-solid fa-chart-column mr-2 text-emerald-600"></i>Inverter & HT/VCB Report</p><p class="text-xs text-slate-500 mt-1">Daily telemetry is reported from <strong>05:00 AM to 07:00 PM IST</strong>. Monthly mode gives one summary row for every calendar day.</p></div>
      <div class="flex flex-wrap gap-2">
        <select id="plantSelect" class="h-10 border border-slate-200 rounded-xl px-3 bg-white text-sm font-semibold"></select>
        <select id="typeSelect" class="h-10 border border-slate-200 rounded-xl px-3 bg-white text-sm font-semibold"><option value="daily">Daily Report</option><option value="monthly">Monthly Report</option></select>
        <input id="dateSelect" type="date" class="h-10 border border-slate-200 rounded-xl px-3 bg-white text-sm font-semibold">
        <input id="monthSelect" type="month" class="hidden h-10 border border-slate-200 rounded-xl px-3 bg-white text-sm font-semibold">
        <button id="viewBtn" class="h-10 bg-slate-900 hover:bg-slate-800 text-white rounded-xl px-5 text-sm font-black"><i class="fa-solid fa-rotate mr-2"></i>View</button>
      </div>
    </div>
  </div>

  <div id="printable" class="space-y-5">
    <div class="surface p-5 sm:p-6">
      <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-2"><span id="reportTypeBadge" class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-[10px] font-black uppercase tracking-wider">Daily Report</span><span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-wider">05:00–19:00 IST</span></div>
          <h2 id="plantTitle" class="text-xl sm:text-2xl font-black text-slate-950">--</h2>
          <p id="serviceLine" class="text-sm font-bold text-emerald-700 mt-1"></p>
        </div>
        <div id="status" class="inline-flex gap-2 items-center px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 shrink-0"><span class="status-dot bg-slate-400"></span>Waiting</div>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4 text-sm">
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><span class="mini block">Location</span><span id="location" class="font-bold text-slate-800 mt-1 block">--</span></div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><span class="mini block">Capacity</span><span id="capacity" class="font-bold text-slate-800 mt-1 block">--</span></div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><span class="mini block">Period</span><span id="period" class="font-bold text-slate-800 mt-1 block">--</span></div>
        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><span class="mini block">Rows</span><span id="rowCount" class="font-bold text-slate-800 mt-1 block">--</span></div>
      </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
      <div class="surface p-4 sm:p-5"><p class="mini">Generation</p><p id="summaryGeneration" class="metric text-2xl sm:text-3xl font-black text-slate-950 mt-2">--</p><p id="summaryGenerationHint" class="text-[11px] text-slate-500 mt-1">--</p></div>
      <div class="surface p-4 sm:p-5"><p class="mini">HT / VCB Energy</p><p id="summaryVcb" class="metric text-2xl sm:text-3xl font-black text-slate-950 mt-2">--</p><p class="text-[11px] text-slate-500 mt-1">Export energy in selected period</p></div>
      <div class="surface p-4 sm:p-5"><p class="mini">Peak Plant Power</p><p id="summaryPeak" class="metric text-2xl sm:text-3xl font-black text-slate-950 mt-2">--</p><p class="text-[11px] text-slate-500 mt-1">Highest reported output</p></div>
      <div class="surface p-4 sm:p-5"><p class="mini">Transformer Loss</p><p id="summaryLoss" class="metric text-2xl sm:text-3xl font-black text-slate-950 mt-2">--</p><p class="text-[11px] text-slate-500 mt-1">Inverter energy minus HT export</p></div>
    </div>

    <div class="surface p-4 sm:p-5">
      <div id="htNote" class="hidden mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs font-bold text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i>HT/VCB telemetry is not available for this period. Inverter data remains valid; HT columns are shown as --.</div>
      <div class="flex flex-wrap items-center justify-between gap-3 mb-3"><div><h3 class="text-sm font-black text-slate-900">Detailed Data</h3><p id="tableDescription" class="text-xs text-slate-500 mt-0.5">15-minute readings from 05:00 to 19:00 IST</p></div><span id="liveWindowBadge" class="text-[10px] font-black rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">HISTORICAL / DB</span></div>
      <div class="wrap"><table class="tbl"><thead id="head"></thead><tbody id="body"><tr><td class="py-10 text-slate-400">Loading...</td></tr></tbody></table></div>
    </div>
  </div>
</section>
</main>
</div>

<script>
const catalog=<?php echo json_encode($catalog,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const role=<?php echo json_encode($userRole); ?>;
const assigned=<?php echo json_encode($userPlant); ?>;
const initial=<?php echo json_encode($selectedPlant); ?>;
const token=new URLSearchParams(location.search).get('token')||sessionStorage.getItem('vs_token')||localStorage.getItem('vs_token')||'';
const plantSelect=document.getElementById('plantSelect'),typeSelect=document.getElementById('typeSelect'),dateSelect=document.getElementById('dateSelect'),monthSelect=document.getElementById('monthSelect');
let report=null,ws=null,liveInv={},liveVcb=null,refreshTimer=null,reconnectTimer=null,wsGeneration=0;

function parts(){const o={};new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).formatToParts(new Date()).forEach(p=>{if(p.type!=='literal')o[p.type]=p.value});return o}
function today(){const p=parts();return `${p.year}-${p.month}-${p.day}`}
function month(){return today().slice(0,7)}
function currentMinutes(){const p=parts();return Number(p.hour)*60+Number(p.minute)}
function quarter(){const p=parts();return `${p.hour}:${String(Math.floor(Number(p.minute)/15)*15).padStart(2,'0')}`}
function insideReportWindow(){const m=currentMinutes();return m>=300&&m<=1140}
function f(v){const n=Number(v);return Number.isFinite(n)?n.toFixed(2):'--'}
function esc(x){return String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
function isTodayDaily(){return typeSelect.value==='daily'&&dateSelect.value===today()&&plantSelect.value!=='all'}
function liveEligible(){return isTodayDaily()&&insideReportWindow()}

function init(){
  if(role==='admin')plantSelect.add(new Option('All Plants','all'));
  Object.values(catalog).forEach(p=>{if(role==='admin'||p.id===assigned)plantSelect.add(new Option(p.name,p.id))});
  plantSelect.value=[...plantSelect.options].some(o=>o.value===initial)?initial:(role==='admin'?'vinoba-1':assigned);
  if(role!=='admin')plantSelect.classList.add('hidden');
  dateSelect.value=today();monthSelect.value=month();header();toggle();fetchReport();
}

function header(){
  const id=plantSelect.value,p=catalog[id];
  document.getElementById('plantTitle').textContent=id==='all'?'ALL PLANTS':p.name.toUpperCase();
  document.getElementById('serviceLine').textContent=id==='all'?'Service Numbers: 06914430133 / 06914430134':'Service Number - '+p.service_number;
  document.getElementById('location').textContent=id==='all'?'Multiple Sites':p.location;
  document.getElementById('capacity').textContent=id==='all'?Object.values(catalog).reduce((s,x)=>s+Number(x.capacity||0),0)+' MW':p.capacity+' MW';
}

function toggle(){
  const monthly=typeSelect.value==='monthly';
  dateSelect.classList.toggle('hidden',monthly);monthSelect.classList.toggle('hidden',!monthly);
  document.getElementById('reportTypeBadge').textContent=monthly?'Monthly Report':'Daily Report';
  document.getElementById('tableDescription').textContent=monthly?'One daily summary row for every calendar day · each day calculated from 05:00–19:00 IST':'15-minute readings from 05:00–19:00 IST';
  if(monthly){closeWS();clearInterval(refreshTimer);refreshTimer=null}else{connectWS();if(!refreshTimer)refreshTimer=setInterval(fetchReport,30000)}
}

function setStatus(t,k='normal'){
  const cls=k==='live'?'bg-emerald-500':k==='error'?'bg-red-500':'bg-slate-400';
  document.getElementById('status').innerHTML=`<span class="status-dot ${cls}"></span>${esc(t)}`;
}

function summaryValue(rows,key,type){
  const vals=rows.map(r=>Number(r[key])).filter(Number.isFinite);
  if(!vals.length)return null;
  return type==='monthly'?vals.reduce((a,b)=>a+b,0):Math.max(...vals);
}
function renderSummary(){
  const rows=report?.data||[],monthly=typeSelect.value==='monthly',ht=!!report?.meta?.ht_available;
  const generation=summaryValue(rows,'inv_total_kwh',monthly?'monthly':'daily');
  const vcb=ht?summaryValue(rows,'vcb_kwh',monthly?'monthly':'daily'):null;
  const peak=Math.max(0,...rows.map(r=>Math.max(Number(r.inv_total_kw)||0,ht?(Number(r.vcb_kw)||0):0)));
  let loss=null;
  if(ht){const vals=rows.map(r=>Number(r.tx_loss)).filter(Number.isFinite);loss=monthly?vals.reduce((a,b)=>a+b,0):(vals.length?vals[vals.length-1]:null)}
  document.getElementById('summaryGeneration').textContent=generation===null?'--':generation.toFixed(2)+' kWh';
  document.getElementById('summaryGenerationHint').textContent=monthly?'Sum of daily generation for the month':'Final / maximum daily generation value';
  document.getElementById('summaryVcb').textContent=vcb===null?'--':vcb.toFixed(2)+' kWh';
  document.getElementById('summaryPeak').textContent=peak.toFixed(2)+' kW';
  document.getElementById('summaryLoss').textContent=loss===null?'--':loss.toFixed(2)+' kWh';
}

async function fetchReport(){
  const type=typeSelect.value,period=type==='daily'?dateSelect.value:monthSelect.value,plant=plantSelect.value;if(!period)return;
  document.getElementById('period').textContent=type==='daily'?`${period} · 05:00–19:00 IST`:`${period} · daily 05:00–19:00 IST`;
  try{
    const q=new URLSearchParams({tab:'inv_vcb',type,date:period,plant});if(token)q.set('token',token);
    const r=await fetch('api_reports.php?'+q,{cache:'no-store',headers:token?{'Authorization':'Bearer '+token}:{}}),j=await r.json();
    if(!j.success)throw new Error(j.error||'Report failed');
    report=j;mergeLive();render();renderSummary();
    document.getElementById('rowCount').textContent=(j.data||[]).length+(type==='daily'?' intervals':' days');
    const source=j.meta?.source==='db_live'?'Live database':'Report database';
    setStatus(source+' • '+(j.meta?.generated_at||''),j.meta?.source==='db_live'?'live':'normal');
    const b=document.getElementById('jsonBtn');b.disabled=false;b.classList.remove('opacity-50');
    updateLiveBadge();
  }catch(e){setStatus(e.message,'error');document.getElementById('body').innerHTML=`<tr><td class="py-10 text-red-600 font-bold">${esc(e.message)}</td></tr>`}
}

function mergeLive(){
  if(!report||!liveEligible())return;
  const rows=report.data||[],names=report.meta.inv_names||[];
  Object.keys(liveInv).forEach(n=>{if(!names.includes(n)){names.push(n);const i=names.length;rows.forEach(r=>{r['inv'+i+'_kwh']=0;r['inv'+i+'_kw']=0})}});
  const label=quarter();
  if(label<'05:00'||label>'19:00')return;
  const row=rows.find(r=>r.time_label===label);if(!row)return;
  Object.entries(liveInv).forEach(([n,v])=>{const i=names.indexOf(n)+1;row['inv'+i+'_kwh']=Number(v.kwh||0);row['inv'+i+'_kw']=Number(v.kw||0)});
  row.inv_total_kwh=names.reduce((s,_,i)=>s+Number(row['inv'+(i+1)+'_kwh']||0),0);
  row.inv_total_kw=names.reduce((s,_,i)=>s+Number(row['inv'+(i+1)+'_kw']||0),0);
  if(liveVcb!==null){row.vcb_kwh=liveVcb;report.meta.ht_available=true;row.tx_loss=row.inv_total_kwh-row.vcb_kwh}
}

function render(){
  if(!report)return;
  const names=report.meta.inv_names||[],ht=!!report.meta.ht_available;
  document.getElementById('htNote').classList.toggle('hidden',ht);
  let h='<tr><th>Time / Date</th>';names.forEach(n=>h+=`<th>${esc(n)} kWh</th><th>${esc(n)} kW</th>`);h+='<th>Inv Total kWh</th><th>Inv Total kW</th><th>HT/VCB kWh</th><th>HT/VCB kW</th><th>TX Loss kWh</th></tr>';
  document.getElementById('head').innerHTML=h;
  document.getElementById('body').innerHTML=(report.data||[]).map(r=>{let x=`<tr><td class="font-bold text-slate-700">${esc(r.time_label)}</td>`;names.forEach((_,i)=>x+=`<td>${f(r['inv'+(i+1)+'_kwh'])}</td><td>${f(r['inv'+(i+1)+'_kw'])}</td>`);x+=`<td class="font-bold">${f(r.inv_total_kwh)}</td><td class="font-bold">${f(r.inv_total_kw)}</td><td>${ht?f(r.vcb_kwh):'--'}</td><td>${ht?f(r.vcb_kw):'--'}</td><td>${ht&&r.tx_loss!==null?f(r.tx_loss):'--'}</td></tr>`;return x}).join('')||'<tr><td class="py-10 text-slate-400">No data</td></tr>';
}

function updateLiveBadge(){
  const el=document.getElementById('liveWindowBadge');
  if(liveEligible()){el.textContent='LIVE WEBSOCKET · TODAY';el.className='text-[10px] font-black rounded-full bg-emerald-100 text-emerald-700 px-3 py-1.5'}
  else if(isTodayDaily()){el.textContent=insideReportWindow()?'TODAY · DB + LIVE':'TODAY · REPORT WINDOW CLOSED';el.className='text-[10px] font-black rounded-full bg-slate-100 text-slate-600 px-3 py-1.5'}
  else{el.textContent='HISTORICAL · DATABASE';el.className='text-[10px] font-black rounded-full bg-slate-100 text-slate-500 px-3 py-1.5'}
}

function connectWS(){
  closeWS();updateLiveBadge();if(!liveEligible())return;
  const plant=plantSelect.value,generation=++wsGeneration;
  try{
    const socket=new WebSocket('wss://vinobasolar.scadahub.in:5001');ws=socket;
    socket.onopen=()=>{if(generation!==wsGeneration)return;socket.send(JSON.stringify({type:'subscribe',unit_id:plant}));updateLiveBadge()};
    socket.onmessage=e=>{try{if(generation!==wsGeneration)return;const d=JSON.parse(e.data);if(d.unit_id!==plant)return;const vals=d.values||{},task=String(d.task||'').toLowerCase(),dev=String(d.device||'');if(task==='inverter'||dev.toLowerCase().includes('inverter')){let kwh=null,kw=null;for(const [k,v] of Object.entries(vals)){if(kwh===null&&/daily.*generation|daily.*gen/i.test(k))kwh=Number(v)||0;if(kw===null&&/active.*power|ac.*power|power.*ac/i.test(k)&&!/reactive|apparent|3.phase/i.test(k))kw=Number(v)||0}liveInv[dev||'Inverter']={kwh:kwh??liveInv[dev]?.kwh??0,kw:kw??liveInv[dev]?.kw??0}}for(const [k,v] of Object.entries(d.virtualTags||{}))if(/vcb.*today|today.*energy/i.test(k)){liveVcb=Number(typeof v==='object'?v.value:v)||0;break}mergeLive();render();renderSummary();setStatus('Live WebSocket • '+new Date().toLocaleTimeString('en-IN',{hour12:false}),'live')}catch(_){}};
    socket.onclose=()=>{if(generation!==wsGeneration)return;ws=null;if(liveEligible()){clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,4000)}else updateLiveBadge()};
    socket.onerror=()=>{try{socket.close()}catch(_){}};
  }catch(_){if(liveEligible()){clearTimeout(reconnectTimer);reconnectTimer=setTimeout(connectWS,4000)}}
}

function closeWS(){wsGeneration++;clearTimeout(reconnectTimer);reconnectTimer=null;if(ws){const old=ws;ws=null;try{old.onclose=null;old.close()}catch(_){}}liveInv={};liveVcb=null}
function reportFileLabel(){if(plantSelect.value==='all')return 'all-plants';const name=catalog[plantSelect.value]?.name||'plant';return name.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')}
function downloadJson(){if(!report)return;const blob=new Blob([JSON.stringify(report,null,2)],{type:'application/json'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`report-${reportFileLabel()}-${typeSelect.value}-${typeSelect.value==='daily'?dateSelect.value:monthSelect.value}.json`;a.click();URL.revokeObjectURL(a.href)}
function exportPDF(){if(!report)return;const el=document.getElementById('printable');el.classList.add('pdf-mode');html2pdf().set({margin:5,filename:`report-${reportFileLabel()}-${typeSelect.value}-${typeSelect.value==='daily'?dateSelect.value:monthSelect.value}.pdf`,html2canvas:{scale:1.35,useCORS:true},jsPDF:{orientation:'landscape',unit:'mm',format:'a4'},pagebreak:{mode:['avoid-all','css','legacy']}}).from(el).save().finally(()=>el.classList.remove('pdf-mode'))}

plantSelect.addEventListener('change',()=>{header();connectWS();fetchReport()});
typeSelect.addEventListener('change',()=>{toggle();fetchReport()});
dateSelect.addEventListener('change',()=>{connectWS();fetchReport()});
monthSelect.addEventListener('change',fetchReport);
document.getElementById('viewBtn').addEventListener('click',()=>{connectWS();fetchReport()});
setInterval(()=>{if(typeSelect.value==='daily'){if(liveEligible()&&!ws)connectWS();else if(!liveEligible()&&ws)closeWS();updateLiveBadge()}},60000);
init();
</script>
</body>
</html>
