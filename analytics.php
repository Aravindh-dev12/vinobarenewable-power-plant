<?php
require 'check_auth.php';
require 'config.php';

$plant = isset($_GET['plant']) ? $conn->real_escape_string($_GET['plant']) : 'vinoba-1';
$hist = [];
try {
    $check = $conn->query("SHOW TABLES LIKE 'telemetry_history'");
    if ($check && $check->num_rows > 0) {
        $res = $conn->query("SELECT metric_type, metric_value, recorded_at FROM telemetry_history WHERE plant_id='$plant' AND DATE(recorded_at)=CURDATE() AND metric_type='vcb_power' ORDER BY recorded_at ASC LIMIT 50");
        if ($res) while ($row = $res->fetch_assoc()) $hist[] = $row;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Solar Plant - Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="sidebar-control.js?v=4" defer></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
    <div class="min-h-screen flex relative">
        <div id="overlay" class="fixed inset-0 bg-slate-900 bg-opacity-40 hidden z-30 md:hidden transition-opacity"></div>
        <div id="sidebar-container"></div>
        <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
            <header class="bg-white p-4 sm:px-6 flex justify-between items-center sticky top-0 z-20 border-b border-slate-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl focus:outline-none">&#9776;</button>
                    <div><h2 class="text-xl font-black text-slate-800 tracking-tight">Plant Analytics</h2></div>
                </div>
                <div class="flex items-center gap-3 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                    <div id="refreshPulse" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
                    <span class="text-xs font-bold text-slate-600 tracking-widest hidden sm:inline" id="clockDisplay">--:--:--</span>
                </div>
            </header>
            <div class="p-4 sm:p-6 w-full flex flex-col gap-6 max-w-[1600px] mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full blur-xl -z-10 group-hover:bg-blue-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Performance</h3>
                        <p class="font-black text-slate-800 text-3xl" id="perf_val">-- <span class="text-sm font-bold text-blue-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Capacity factor</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 rounded-full blur-xl -z-10 group-hover:bg-purple-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Yield</h3>
                        <p class="font-black text-slate-800 text-3xl" id="yield_val">-- <span class="text-sm font-bold text-purple-600">kWh</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Daily energy</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition duration-300">
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full blur-xl -z-10 group-hover:bg-emerald-100 transition"></div>
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Availability</h3>
                        <p class="font-black text-slate-800 text-3xl" id="avail_val">-- <span class="text-sm font-bold text-emerald-600">%</span></p>
                        <p class="text-xs text-slate-500 font-medium mt-1">Inverter uptime</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Overall Plant Output</p>
                            <h2 class="text-xl font-bold text-slate-900">Power Trend (kW)</h2>
                            <p class="text-xs text-slate-500 mt-1">Uses HT/VCB power when available, otherwise inverter output</p>
                        </div>
                    </div>
                    <div style="height:300px; min-height:300px;"><canvas id="analyticsChart"></canvas></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-slate-600 uppercase tracking-widest">Alerts & Recommendations</h3>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700" id="recBadge">0 items</span>
                    </div>
                    <div id="recContainer" class="space-y-3">
                        <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                            <p class="font-black text-slate-800 text-sm">No Alerts</p>
                            <p class="mt-2 text-sm text-slate-600">All systems operating within normal parameters. No action required.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const currentPlant = urlParams.get('plant') || 'vinoba-1';
        const authToken = urlParams.get('token') || sessionStorage.getItem('vs_token') || '';
        const plantNames = {
            'vinoba-1': 'Vinoba Renewable Energy Private Limited',
            'ssv': 'SSV Green Power Private Limited'
        };
        document.getElementById('pageTitle').textContent = (plantNames[currentPlant] || currentPlant) + ' - Analytics';
        setInterval(() => { document.getElementById('clockDisplay').innerText = new Date().toLocaleTimeString('en-IN', {hour12: false}); }, 1000);
        fetch('sidebar.html', { cache: 'no-store' }).then(r => r.text()).then(html => {
            document.getElementById('sidebar-container').innerHTML = html;
            const _token = new URLSearchParams(window.location.search).get('token') || sessionStorage.getItem('vs_token') || '';
            const _plant = new URLSearchParams(window.location.search).get('plant') || currentPlant || 'vinoba-1';
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                let href = link.getAttribute('href');
                if (!href || href.indexOf('logout') !== -1) return;
                if (href.indexOf('?plant=') === -1) link.setAttribute('href', href + '?plant=' + encodeURIComponent(_plant) + '&token=' + encodeURIComponent(_token));
                else if (href.indexOf('token=') === -1) link.setAttribute('href', href + '&token=' + encodeURIComponent(_token));
            });
            const _pn = document.getElementById('sidebarPlantName');
            if (_pn) _pn.textContent = plantNames[_plant] || _plant;
            if (typeof initSidebar === 'function') initSidebar();
            const curPage = window.location.pathname.split('/').pop() || 'home.php';
            document.querySelectorAll('#sidebarNav a').forEach(link => {
                const dp = link.getAttribute('data-page');
                if (dp && (dp === curPage || dp.replace('.php','.html') === curPage)) {
                    link.classList.add('!bg-emerald-50', '!text-emerald-700', '!border-emerald-500', 'shadow-emerald-100');
                    const ic = link.querySelector('i');
                    if (ic) ic.classList.add('!text-emerald-600');
                }
            });
            const overlay = document.getElementById('overlay'), sidebar = document.getElementById('sidebar');
            document.getElementById('menuBtn')?.addEventListener('click', () => { sidebar?.classList.remove('-translate-x-full'); overlay?.classList.remove('hidden'); });
            document.getElementById('closeSidebarBtn')?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay?.classList.add('hidden'); });
            overlay?.addEventListener('click', () => { sidebar?.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
        });
        const historyData = <?php echo json_encode($hist); ?>;
        const hourlyMap = {};
        historyData.forEach(r => {
            const h = new Date(r.recorded_at).getHours();
            const label = String(h).padStart(2, '0') + ':00';
            hourlyMap[label] = parseFloat(r.metric_value);
        });
        const allLabels = [];
        const allValues = [];
        for (let h = 0; h < 24; h++) {
            const label = String(h).padStart(2, '0') + ':00';
            allLabels.push(label);
            allValues.push(hourlyMap[label] || 0);
        }

        let analyticsChart;
        function initAnalyticsChart() {
            const ctx = document.getElementById('analyticsChart').getContext('2d');
            analyticsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: allLabels,
                    datasets: [{
                        label: 'Plant Output (kW)',
                        data: allValues,
                        backgroundColor: '#2563eb',
                        borderColor: '#1d4ed8',
                        borderWidth: 1,
                        borderRadius: 6,
                        barThickness: 20
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { color: '#475569' } }, x: { grid: { display: false }, ticks: { color: '#475569', maxTicksLimit: 24, font: { size: 10 } } } }, plugins: { legend: { display: false } } }
            });
        }
        initAnalyticsChart();

        const plantConfig = { 'vinoba-1': { capacity: 2.0 }, 'ssv': { capacity: 2.0 } };
        const cfg = plantConfig[currentPlant] || { capacity: 2.0 };
        const aState = { power: 0, energy: 0, inverters: {}, hasVcb: false };
        let lastAnalyticsPush = 0;
        let analyticsHasData = false;
        function pushAnalyticsPoint() {
            const now = Date.now();
            if (analyticsHasData && now - lastAnalyticsPush < 60000) return;
            lastAnalyticsPush = now;
            analyticsHasData = true;
            const nowDate = new Date();
            const timeStr = String(nowDate.getHours()).padStart(2, '0') + ':00';
            const ds = analyticsChart.data.datasets[0];
            const idx = analyticsChart.data.labels.indexOf(timeStr);
            if (idx >= 0) ds.data[idx] = aState.power;
            analyticsChart.update('none');
        }
        function updateAnalyticsCards() {
            const perf = cfg.capacity > 0 ? ((aState.power / (cfg.capacity * 1000)) * 100) : 0;
            document.getElementById('perf_val').innerHTML = perf.toFixed(1) + ' <span class="text-sm font-bold text-blue-600">%</span>';
            document.getElementById('yield_val').innerHTML = aState.energy.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
            const invKeys = Object.keys(aState.inverters);
            const totalInv = invKeys.length;
            const activeInv = invKeys.filter(k => aState.inverters[k].active).length;
            const avail = totalInv > 0 ? ((activeInv / totalInv) * 100) : 0;
            document.getElementById('avail_val').innerHTML = avail.toFixed(1) + ' <span class="text-sm font-bold text-emerald-600">%</span>';
        }
        function inverterPower(values) {
            for (const [key, value] of Object.entries(values || {})) {
                const lower = key.toLowerCase();
                if (/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(lower) && !/reactive|apparent|3.phase/.test(lower)) return Number(value) || 0;
            }
            return 0;
        }
        function inverterDaily(values) {
            for (const [key, value] of Object.entries(values || {})) if (/daily.*generation|daily.*gen/i.test(key)) return Number(value) || 0;
            return null;
        }
        function connectWSAnalytics() {
            const ws = new WebSocket("wss://vinobasolar.scadahub.in:5001");
            ws.onopen = function() { ws.send(JSON.stringify({ type: "subscribe", unit_id: currentPlant })); };
            ws.onmessage = function(e) {
                try {
                    const d = JSON.parse(e.data); if (d.unit_id !== currentPlant) return;
                    const task = String(d.task || '').toLowerCase();
                    const device = String(d.device || '').toLowerCase();
                    const values = d.values || {};
                    const isVcb = task === 'vcb' || device.includes('vcb');
                    if (isVcb && values["3 Phase Active Power"] !== undefined) {
                        aState.power = parseFloat(values["3 Phase Active Power"]) || 0;
                        aState.hasVcb = true;
                    }
                    if (d.virtualTags && d.virtualTags["vcb-today"] !== undefined) aState.energy = parseFloat(d.virtualTags["vcb-today"].value) || 0;
                    const isInv = !isVcb && (task === 'inverter' || device.includes('inverter') || Object.keys(values).some(k => /active.*power|ac.*power/i.test(k)));
                    if (isInv) {
                        const pwr = inverterPower(values);
                        const daily = inverterDaily(values);
                        aState.inverters[d.device || 'Unknown'] = { active: pwr > 0.01, power: pwr, daily: daily === null ? (aState.inverters[d.device || 'Unknown']?.daily || 0) : daily };
                        if (!aState.hasVcb) aState.power = Object.values(aState.inverters).reduce((s, v) => s + (v.power || 0), 0);
                        if (!(d.virtualTags && d.virtualTags["vcb-today"] !== undefined)) aState.energy = Object.values(aState.inverters).reduce((s, v) => s + (v.daily || 0), 0);
                    }
                    updateAnalyticsCards();
                    pushAnalyticsPoint();
                } catch(err) {}
            };
            ws.onclose = function() { setTimeout(connectWSAnalytics, 5000); };
        }
        connectWSAnalytics();
    </script>
</body>
</html>
