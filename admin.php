<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

$token = trim((string)($_GET['token'] ?? ''));
$adminUser = null;

if ($token !== '') {
    $st = $conn->prepare('SELECT * FROM users WHERE auth_token=? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $token);
        $st->execute();
        $r = $st->get_result();
        if ($r && $r->num_rows) $adminUser = $r->fetch_assoc();
        $st->close();
    }
}

if (!$adminUser || ($adminUser['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$catalog = plant_catalog();
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
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
body{font-family:'Inter',sans-serif}.dot{width:8px;height:8px;border-radius:9999px;display:inline-block}
.scroll::-webkit-scrollbar{width:4px}.scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">
<nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="min-h-16 py-2 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center"><i class="fa-solid fa-solar-panel"></i></div>
                <div>
                    <h1 class="text-lg font-bold text-slate-800">Renewable Solar Dashboard</h1>
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Plant Portfolio</p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <span id="wsStatus" class="text-xs font-bold text-slate-500 flex items-center gap-1.5"><span class="dot bg-slate-400"></span>Loading live data</span>
                <div class="hidden sm:flex items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <i class="fa-solid fa-bolt text-amber-500"></i><span>Overall</span><span id="overall" class="font-black tabular-nums">0.00 kW</span>
                </div>
                <button onclick="location.href='logout.php'" class="px-3 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 rounded-lg">Logout</button>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto w-full py-6 px-4 sm:px-6">
    <div class="flex items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-black text-slate-800">Plant Overview</h2>
            <p class="text-xs text-slate-500 mt-1">Live inverter telemetry by plant</p>
        </div>
        <span id="portfolioLive" class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-3 py-1.5">LOADING</span>
    </div>
    <div id="cards" class="grid grid-cols-1 lg:grid-cols-2 gap-5"></div>
</main>

<script>
const plants = <?php echo json_encode(array_values($catalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const token = <?php echo json_encode($token); ?>;
const state = {};
const sockets = {};
const reconnect = {};

plants.forEach(p => {
    state[p.id] = {
        vcbPower: 0,
        hasVCB: false,
        dailyEnergy: 0,
        inverters: {},
        lastLive: 0,
        lastUpdate: 'Waiting for telemetry',
        source: 'Waiting'
    };
});

function cardHtml(p) {
    return `
    <article class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <span id="badge-${p.id}" class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">WAITING</span>
                    <h2 class="text-lg font-black text-slate-800 leading-snug mt-2">${p.name}</h2>
                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-2 text-xs text-slate-500">
                        <span class="font-bold text-emerald-700"><i class="fa-solid fa-bolt mr-1"></i>Service ${p.service_number}</span>
                        <span><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${p.location || 'Karur'}</span>
                        <span><i class="fa-solid fa-solar-panel mr-1 text-slate-400"></i>${p.capacity || '--'} MW</span>
                    </div>
                </div>
                <a href="home.php?plant=${encodeURIComponent(p.id)}&token=${encodeURIComponent(token)}" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shrink-0">
                    <i class="fa-solid fa-arrow-right mr-1"></i>Open
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5">
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Power</p>
                    <p id="active-${p.id}" class="text-2xl font-black text-slate-800 mt-2">0.00 <span class="text-xs text-blue-600">kW</span></p>
                    <p id="activeSource-${p.id}" class="text-[10px] text-slate-500 mt-1">Waiting</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today Energy</p>
                    <p id="today-${p.id}" class="text-2xl font-black text-slate-800 mt-2">0.00 <span class="text-xs text-purple-600">kWh</span></p>
                    <p class="text-[10px] text-slate-500 mt-1">Combined inverter generation</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Last Update</p>
                    <p id="time-${p.id}" class="text-sm font-black text-slate-800 mt-2">Waiting</p>
                    <p id="invCount-${p.id}" class="text-[10px] text-slate-500 mt-1">0 inverters detected</p>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <p class="text-xs font-black text-slate-700">Inverter Fleet</p>
                    <span id="source-${p.id}" class="text-[9px] font-bold text-slate-400 uppercase">Waiting</span>
                </div>
                <div id="inv-${p.id}" class="p-3 min-h-[96px] max-h-[220px] overflow-y-auto scroll">
                    <div class="text-xs font-semibold text-slate-400 text-center py-7">Waiting for inverter telemetry...</div>
                </div>
            </div>
        </div>
    </article>`;
}

function renderCards() {
    document.getElementById('cards').innerHTML = plants.map(cardHtml).join('');
}

function num(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
}

function normalizeMessage(raw, expectedPlant) {
    if (!raw || typeof raw !== 'object') return null;
    let d = raw;

    if (raw.data && typeof raw.data === 'object' && !Array.isArray(raw.data) &&
        (raw.data.values || raw.data.device || raw.data.deviceName || raw.data.task)) {
        d = Object.assign({}, raw, raw.data);
    }

    const incoming = String(
        d.unit_id || d.unitId || d.plant_id || d.plantId || d.plant || ''
    ).trim().toLowerCase();

    if (incoming && incoming !== expectedPlant) return null;

    return Object.assign({}, d, {
        unit_id: expectedPlant,
        device: d.device || d.deviceName || '',
        values: d.values || d.tags || {}
    });
}

function invPower(values) {
    for (const [k, v] of Object.entries(values || {})) {
        const x = k.toLowerCase();
        if (/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(x) && !/reactive|apparent|3.phase/.test(x)) {
            return num(v);
        }
    }
    return 0;
}

function dailyGen(values) {
    for (const [k, v] of Object.entries(values || {})) {
        if (/daily.*generation|daily.*gen/i.test(k)) return num(v);
    }
    return null;
}

function stringCount(values) {
    let active = 0;
    let total = 0;
    for (const [k, v] of Object.entries(values || {})) {
        const x = k.toLowerCase();
        if (/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(x)) continue;
        if (/\d/.test(k) && /curr|current|amp/i.test(k)) {
            total++;
            if (num(v) > 0.5) active++;
        }
    }
    return { active, total };
}

function finalPower(st) {
    const inverterTotal = Object.values(st.inverters).reduce((sum, x) => sum + num(x.power), 0);
    return st.hasVCB && num(st.vcbPower) > 0 ? num(st.vcbPower) : inverterTotal;
}

function updateOverall() {
    const total = Object.values(state).reduce((sum, st) => sum + finalPower(st), 0);
    document.getElementById('overall').textContent = total.toFixed(2) + ' kW';
}

function updatePlant(id) {
    const st = state[id];
    if (!st) return;

    const power = finalPower(st);
    document.getElementById('active-' + id).innerHTML = power.toFixed(2) + ' <span class="text-xs text-blue-600">kW</span>';
    document.getElementById('activeSource-' + id).textContent = st.hasVCB && num(st.vcbPower) > 0
        ? 'HT/VCB active power'
        : 'Combined inverter active power';
    document.getElementById('today-' + id).innerHTML = num(st.dailyEnergy).toFixed(2) + ' <span class="text-xs text-purple-600">kWh</span>';
    document.getElementById('time-' + id).textContent = st.lastUpdate;
    document.getElementById('source-' + id).textContent = st.source;

    const live = Date.now() - st.lastLive < 15000;
    const badge = document.getElementById('badge-' + id);
    badge.className = 'text-[10px] font-bold px-2.5 py-1 rounded-full ' +
        (live ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-50 text-blue-700');
    badge.textContent = live ? 'LIVE' : (st.source.includes('Database') ? 'DB FALLBACK' : 'WAITING');

    const names = Object.keys(st.inverters).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    document.getElementById('invCount-' + id).textContent = names.length + ' inverter' + (names.length === 1 ? '' : 's') + ' detected';

    const box = document.getElementById('inv-' + id);
    if (names.length) {
        box.innerHTML = names.map(name => {
            const inv = st.inverters[name];
            const online = num(inv.power) > 0 || num(inv.daily) > 0;
            const strings = inv.total ? `${inv.active}/${inv.total} strings` : '--';
            return `<div class="flex items-center justify-between gap-3 px-2 py-2.5 border-b border-slate-100 last:border-0">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="dot ${online ? 'bg-emerald-500' : 'bg-slate-300'}"></span>
                    <span class="text-xs font-black text-slate-700 truncate">${name}</span>
                </div>
                <div class="flex items-center gap-4 text-[11px] shrink-0">
                    <span class="font-black text-slate-800">${num(inv.power).toFixed(1)} kW</span>
                    <span class="text-slate-500">${strings}</span>
                </div>
            </div>`;
        }).join('');
    }

    updateOverall();
    updatePortfolioStatus();
}

function handle(id, raw, sourceLabel = 'Live WebSocket') {
    const d = normalizeMessage(raw, id);
    if (!d) return;

    const st = state[id];
    const values = d.values || {};
    const task = String(d.task || '').toLowerCase();
    const deviceName = String(d.device || '');
    const device = deviceName.toLowerCase();
    const keys = Object.keys(values);

    const isVCB = task === 'vcb' || device.includes('vcb');
    if (isVCB && values['3 Phase Active Power'] !== undefined) {
        st.vcbPower = num(values['3 Phase Active Power']);
        st.hasVCB = true;
    }

    let tagEnergy = null;
    for (const [k, v] of Object.entries(d.virtualTags || {})) {
        if (/vcb.*today|today.*energy/i.test(k)) {
            tagEnergy = num(v && typeof v === 'object' ? v.value : v);
            break;
        }
    }
    if (tagEnergy !== null && tagEnergy >= 0) st.dailyEnergy = tagEnergy;

    const isInverter = !isVCB && (
        task === 'inverter' ||
        device.includes('inverter') ||
        keys.some(k => /active.*power|ac.*power|power.*ac/i.test(k))
    );

    if (isInverter) {
        const name = deviceName || 'Inverter';
        const old = st.inverters[name] || {};
        const strings = stringCount(values);
        const daily = dailyGen(values);
        st.inverters[name] = {
            power: invPower(values),
            active: strings.total ? strings.active : num(old.active),
            total: strings.total || num(old.total),
            daily: daily === null ? num(old.daily) : daily
        };

        if (!(tagEnergy !== null && tagEnergy > 0)) {
            st.dailyEnergy = Object.values(st.inverters).reduce((sum, x) => sum + num(x.daily), 0);
        }
    }

    if (!isVCB && !isInverter) return;

    st.lastLive = Date.now();
    st.lastUpdate = d.time || d.timestamp || new Date().toLocaleTimeString('en-IN', { hour12: false });
    st.source = sourceLabel;
    updatePlant(id);
}

function updatePortfolioStatus() {
    const liveCount = plants.filter(p => Date.now() - state[p.id].lastLive < 15000).length;
    const all = liveCount === plants.length;
    const some = liveCount > 0;

    const status = document.getElementById('wsStatus');
    status.innerHTML = `<span class="dot ${all ? 'bg-emerald-500' : some ? 'bg-amber-500' : 'bg-red-500'}"></span>${all ? 'All plants live' : liveCount + '/' + plants.length + ' live'}`;
    status.className = 'text-xs font-bold flex items-center gap-1.5 ' +
        (all ? 'text-emerald-600' : some ? 'text-amber-600' : 'text-red-600');

    const portfolio = document.getElementById('portfolioLive');
    portfolio.textContent = all ? 'ALL PLANTS LIVE' : liveCount + '/' + plants.length + ' LIVE';
    portfolio.className = 'text-[10px] font-bold rounded-full px-3 py-1.5 ' +
        (all ? 'bg-emerald-100 text-emerald-700' : some ? 'bg-amber-100 text-amber-700' : 'bg-red-50 text-red-700');
}

function connect(id) {
    if (sockets[id] && (
        sockets[id].readyState === WebSocket.OPEN ||
        sockets[id].readyState === WebSocket.CONNECTING
    )) return;

    try {
        const w = new WebSocket('wss://vinobasolar.scadahub.in:5001');
        sockets[id] = w;

        w.onopen = () => {
            w.send(JSON.stringify({ type: 'subscribe', unit_id: id }));
        };
        w.onmessage = event => {
            try { handle(id, JSON.parse(event.data), 'Direct WSS'); } catch (_) {}
        };
        w.onclose = () => {
            sockets[id] = null;
            clearTimeout(reconnect[id]);
            reconnect[id] = setTimeout(() => connect(id), 3000);
        };
        w.onerror = () => {
            try { w.close(); } catch (_) {}
        };
    } catch (_) {
        reconnect[id] = setTimeout(() => connect(id), 3000);
    }
}

async function seedLive(id) {
    try {
        const q = new URLSearchParams({ token, plant: id });
        const response = await fetch('api_live.php?' + q.toString(), {
            cache: 'no-store',
            headers: { Authorization: 'Bearer ' + token }
        });
        const json = await response.json();
        if (!response.ok || !json.success || !Array.isArray(json.messages)) return;

        const source = json.source ? 'Live ' + json.source.replace(/^wss?:\/\//, '') : 'Live snapshot';
        json.messages.forEach(message => handle(id, message, source));
    } catch (_) {}
}

function indiaDate() {
    const o = {};
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Kolkata',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    }).formatToParts(new Date()).forEach(x => {
        if (x.type !== 'literal') o[x.type] = x.value;
    });
    return {
        date: `${o.year}-${o.month}-${o.day}`,
        mins: Number(o.hour) * 60 + Number(o.minute)
    };
}

async function databaseFallback(id) {
    if (Date.now() - state[id].lastLive < 15000) return;
    try {
        const t = indiaDate();
        const q = new URLSearchParams({
            tab: 'inv_vcb',
            type: 'daily',
            date: t.date,
            plant: id,
            token
        });
        const response = await fetch('api_reports.php?' + q.toString(), {
            cache: 'no-store',
            headers: { Authorization: 'Bearer ' + token }
        });
        const json = await response.json();
        if (!json.success || !Array.isArray(json.data) || !json.data.length) return;

        const elapsed = json.data.filter(row => {
            const m = String(row.time_label || '').match(/^(\d+):(\d+)/);
            return m && Number(m[1]) * 60 + Number(m[2]) <= t.mins;
        });
        const row = elapsed.at(-1) || json.data[0];
        const st = state[id];
        const names = json.meta?.inv_names || [];

        names.forEach((name, i) => {
            const old = st.inverters[name] || {};
            st.inverters[name] = {
                ...old,
                power: num(row['inv' + (i + 1) + '_kw']),
                daily: num(row['inv' + (i + 1) + '_kwh'])
            };
        });

        st.vcbPower = num(row.vcb_kw);
        st.hasVCB = Boolean(json.meta?.ht_available) && st.vcbPower > 0;
        st.dailyEnergy = Math.max(0, ...elapsed.map(x => num(x.inv_total_kwh)));
        st.lastUpdate = 'Database ' + new Date().toLocaleTimeString('en-IN', { hour12: false });
        st.source = 'Database fallback';
        updatePlant(id);
    } catch (_) {}
}

renderCards();
plants.forEach(p => {
    seedLive(p.id);
    connect(p.id);
    setTimeout(() => databaseFallback(p.id), 2500);
});

setInterval(() => plants.forEach(p => seedLive(p.id)), 5000);
setInterval(() => plants.forEach(p => databaseFallback(p.id)), 30000);
setInterval(updatePortfolioStatus, 3000);
updatePortfolioStatus();
</script>
</body>
</html>
