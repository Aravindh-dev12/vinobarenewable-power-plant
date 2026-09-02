(function () {
    'use strict';

    const PLANTS = {
        'vinoba-1': { id: 'vinoba-1', name: 'Vinoba Renewable Energy Private Limited', service: '06914430133', capacity: '2.0', location: 'Karur' },
        'ssv': { id: 'ssv', name: 'SSV Green Power Private Limited', service: '06914430134', capacity: '2.0', location: 'Karur' }
    };
    const WS_SEED_INTERVAL = 30000;
    const DESKTOP_BREAKPOINT = 768;
    const COLLAPSE_KEY = 'vs_sidebar_collapsed';
    const liveState = {};
    let seedBusy = false;
    let seedTimer = null;
    let resizeBound = false;

    const normalize = value => String(value || '').trim().toLowerCase();
    const num = value => { const n = Number(value); return Number.isFinite(n) ? n : 0; };
    window.SOLAR_PLANTS = PLANTS;
    window.normalizePlantId = normalize;

    function storedUser() {
        for (const storage of [sessionStorage, localStorage]) {
            try {
                const user = JSON.parse(storage.getItem('vs_user') || '{}');
                if (user && typeof user === 'object' && Object.keys(user).length) return user;
            } catch (_) {}
        }
        return {};
    }

    function currentToken() {
        const params = new URLSearchParams(location.search);
        return params.get('token') || sessionStorage.getItem('vs_token') || localStorage.getItem('vs_token') || '';
    }

    function urlPlant() {
        const params = new URLSearchParams(location.search);
        let plant = normalize(params.get('plant'));
        if (!PLANTS[plant]) {
            const assigned = normalize(storedUser().plant_id);
            if (PLANTS[assigned]) plant = assigned;
        }
        return PLANTS[plant] ? plant : 'vinoba-1';
    }

    function livePlant() {
        const selector = document.getElementById('plantSelect');
        const selected = normalize(selector && selector.value);
        if (PLANTS[selected]) return selected;
        return urlPlant();
    }

    function pageName() {
        return (location.pathname.split('/').pop() || 'home.php').toLowerCase();
    }

    function ensureLiveState(plant) {
        if (!liveState[plant]) liveState[plant] = { inverters: {}, vcbPower: 0, hasVcb: false, vcbToday: null, transformer: {} };
        return liveState[plant];
    }

    function ensureUiAssets() {
        if (!document.querySelector('link[data-dashboard-ui]')) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'dashboard-ui.css?v=4';
            css.dataset.dashboardUi = '1';
            document.head.appendChild(css);
        }
    }

    function sidebarMarkup() {
        return `
<aside id="sidebar" class="w-64 bg-white border-r border-gray-200 shadow-xl flex flex-col fixed inset-y-0 left-0 transition-transform duration-300 ease-in-out z-40" style="width:16rem;transform:translateX(0);">
  <div class="p-4 border-b border-gray-100 relative shrink-0">
    <button id="closeSidebarBtn" type="button" class="md:hidden absolute top-4 right-4 text-gray-400 text-3xl hover:text-gray-800 leading-none" aria-label="Close navigation">&times;</button>
    <button id="collapseSidebarBtn" type="button" class="hidden md:flex absolute top-3 right-3 w-7 h-7 items-center justify-center rounded-full bg-white border border-slate-200 shadow-md text-slate-500 hover:bg-emerald-100 hover:text-emerald-700 transition z-50" style="right:-0.85rem" title="Collapse sidebar" aria-label="Collapse navigation"><i class="fa-solid fa-angles-left text-[10px]"></i></button>
    <div class="flex flex-col items-center text-center gap-3 mt-2 min-w-0">
      <div class="sidebar-brand-icon h-16 w-16 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg shrink-0"><i class="fa-solid fa-solar-panel"></i></div>
      <div class="sidebar-brand-text min-w-0 w-full">
        <h1 id="sidebarPlantName" class="text-sm font-bold text-gray-800 leading-tight break-words">Solar Plant</h1>
        <p id="sidebarServiceNumber" class="text-[9px] text-emerald-700 font-bold mt-1"></p>
        <p class="text-[9px] text-gray-500 font-semibold mt-1 uppercase tracking-wide">Monitoring Dashboard</p>
      </div>
    </div>
  </div>
  <nav id="sidebarNav" class="flex-1 p-4 space-y-1 font-medium text-sm overflow-y-auto">
    <a href="home.php" data-page="home.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-house text-gray-400 w-5 text-center"></i><span>Home</span></a>
    <a href="inverter.php" data-page="inverter.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-server text-gray-400 w-5 text-center"></i><span>Inverter</span></a>
    <a href="vcb.php" data-page="vcb.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-bolt text-gray-400 w-5 text-center"></i><span>VCB / HT Panel</span></a>
    <a href="transformer.php" data-page="transformer.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-temperature-half text-gray-400 w-5 text-center"></i><span>Transformer</span></a>
    <a href="sld.php" data-page="sld.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-diagram-project text-gray-400 w-5 text-center"></i><span>SLD</span></a>
    <a href="analytics.php" data-page="analytics.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-chart-line text-gray-400 w-5 text-center"></i><span>Analytics</span></a>
    <a href="reports.php" data-page="reports.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-file-lines text-gray-400 w-5 text-center"></i><span>Reports</span></a>
  </nav>
  <div class="border-t border-gray-100 my-3 mx-4"></div>
  <nav id="sidebarActions" class="px-4 pb-4 space-y-2 font-medium text-sm">
    <a href="admin.php" id="sidebarDashboard" class="hidden group items-center gap-3 px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold transition-all border-l-4 border-transparent hover:border-slate-400"><i class="fa-solid fa-arrow-left text-slate-500 w-5 text-center"></i><span>Dashboard</span></a>
    <a href="logout.php" id="sidebarLogout" class="group flex items-center gap-3 px-3 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-semibold transition-all border-l-4 border-transparent hover:border-red-500"><i class="fa-solid fa-right-from-bracket text-red-500 w-5 text-center"></i><span>Logout</span></a>
  </nav>
  <div class="px-2 py-2 bg-slate-50 border-t border-gray-200 shrink-0"><div class="sidebar-footer-text text-[8px] font-black uppercase tracking-wide text-gray-500 text-center">Powered by <span class="text-emerald-700">Oriks Care Pvt Ltd</span></div></div>
</aside>`;
    }

    function ensureSidebarMarkup() {
        const container = document.getElementById('sidebar-container');
        if (!container) return null;
        let sidebar = document.getElementById('sidebar');
        if (!sidebar) {
            container.innerHTML = sidebarMarkup();
            sidebar = document.getElementById('sidebar');
        }
        return sidebar;
    }

    function updatePlantIdentity() {
        const info = PLANTS[urlPlant()];
        const sidebarName = document.getElementById('sidebarPlantName');
        if (sidebarName) sidebarName.textContent = info.name;
        const sidebarService = document.getElementById('sidebarServiceNumber');
        if (sidebarService) sidebarService.textContent = 'Service No. ' + info.service;
        const sldName = document.getElementById('sld_plant_name');
        if (sldName) sldName.textContent = info.name;
    }

    function wireLinks(sidebar) {
        const plant = urlPlant();
        const token = currentToken();
        const currentPage = pageName();
        sidebar.querySelectorAll('#sidebarNav a').forEach(link => {
            const page = link.dataset.page || (link.getAttribute('href') || '').split('?')[0];
            const q = new URLSearchParams();
            q.set('plant', plant);
            if (token) q.set('token', token);
            link.href = page + '?' + q.toString();
            const active = page === currentPage || page.replace('.php', '.html') === currentPage;
            link.classList.toggle('!bg-slate-100', active);
            link.classList.toggle('!text-emerald-700', active);
            link.classList.toggle('!border-emerald-500', active);
            link.classList.toggle('shadow-lg', active);
            link.classList.toggle('shadow-slate-200', active);
            const icon = link.querySelector('i');
            if (icon) icon.classList.toggle('!text-emerald-600', active);
        });
        const user = storedUser();
        const dashboard = document.getElementById('sidebarDashboard');
        if (dashboard) {
            const show = String(user.role || '').toLowerCase() === 'admin' && currentPage !== 'admin.php';
            dashboard.classList.toggle('hidden', !show);
            dashboard.classList.toggle('flex', show);
            dashboard.href = 'admin.php' + (token ? '?token=' + encodeURIComponent(token) : '');
        }
        const logout = document.getElementById('sidebarLogout');
        if (logout) logout.href = token ? 'logout.php?token=' + encodeURIComponent(token) : 'logout.php';
    }

    function applyLayout() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const main = document.querySelector('main');
        const desktop = window.innerWidth >= DESKTOP_BREAKPOINT;
        const collapsed = desktop && localStorage.getItem(COLLAPSE_KEY) === '1';
        if (desktop) {
            sidebar.style.transform = 'translateX(0)';
            sidebar.style.width = collapsed ? '5rem' : '16rem';
            if (main) main.style.marginLeft = collapsed ? '5rem' : '16rem';
        } else {
            sidebar.style.width = 'min(18rem, 88vw)';
            if (sidebar.dataset.mobileOpen !== '1') sidebar.style.transform = 'translateX(-100%)';
            if (main) main.style.marginLeft = '0';
        }
        const labels = sidebar.querySelectorAll('nav a span');
        const brandText = sidebar.querySelector('.sidebar-brand-text');
        const brandIcon = sidebar.querySelector('.sidebar-brand-icon');
        const footerText = sidebar.querySelector('.sidebar-footer-text');
        const toggle = document.getElementById('collapseSidebarBtn');
        const icon = toggle ? toggle.querySelector('i') : null;
        labels.forEach(label => label.classList.toggle('hidden', collapsed));
        if (brandText) brandText.classList.toggle('hidden', collapsed);
        if (footerText) footerText.classList.toggle('hidden', collapsed);
        if (brandIcon) {
            brandIcon.classList.toggle('h-16', !collapsed); brandIcon.classList.toggle('w-16', !collapsed);
            brandIcon.classList.toggle('h-10', collapsed); brandIcon.classList.toggle('w-10', collapsed);
        }
        sidebar.querySelectorAll('nav a').forEach(link => {
            link.style.justifyContent = collapsed ? 'center' : '';
            link.style.paddingLeft = collapsed ? '0.5rem' : '';
            link.style.paddingRight = collapsed ? '0.5rem' : '';
        });
        if (icon) icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
        if (toggle) toggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
    }

    function wireControls(sidebar) {
        const overlay = document.getElementById('overlay');
        const menuBtn = document.getElementById('menuBtn');
        const closeBtn = document.getElementById('closeSidebarBtn');
        const collapseBtn = document.getElementById('collapseSidebarBtn');
        const openMobile = () => { sidebar.dataset.mobileOpen = '1'; sidebar.style.transform = 'translateX(0)'; if (overlay) overlay.classList.remove('hidden'); };
        const closeMobile = () => { sidebar.dataset.mobileOpen = '0'; if (window.innerWidth < DESKTOP_BREAKPOINT) sidebar.style.transform = 'translateX(-100%)'; if (overlay) overlay.classList.add('hidden'); };
        if (menuBtn && menuBtn.dataset.sidebarBound !== '1') { menuBtn.dataset.sidebarBound = '1'; menuBtn.addEventListener('click', openMobile); }
        if (closeBtn && closeBtn.dataset.sidebarBound !== '1') { closeBtn.dataset.sidebarBound = '1'; closeBtn.addEventListener('click', closeMobile); }
        if (overlay && overlay.dataset.sidebarBound !== '1') { overlay.dataset.sidebarBound = '1'; overlay.addEventListener('click', closeMobile); }
        if (collapseBtn && collapseBtn.dataset.sidebarBound !== '1') {
            collapseBtn.dataset.sidebarBound = '1';
            collapseBtn.addEventListener('click', () => {
                localStorage.setItem(COLLAPSE_KEY, localStorage.getItem(COLLAPSE_KEY) === '1' ? '0' : '1');
                applyLayout();
            });
        }
    }

    function initSidebar() {
        ensureUiAssets();
        const sidebar = ensureSidebarMarkup();
        if (!sidebar) return;
        updatePlantIdentity();
        wireLinks(sidebar);
        wireControls(sidebar);
        applyLayout();
    }
    window.initSidebar = initSidebar;

    function inverterPower(values) {
        for (const [key, value] of Object.entries(values || {})) {
            const k = key.toLowerCase();
            if (/active.*power|ac.*power|power.*ac|a\.c\..*power/.test(k) && !/reactive|apparent|3.phase/.test(k)) return num(value);
        }
        return 0;
    }
    function inverterDaily(values) {
        for (const [key, value] of Object.entries(values || {})) if (/daily.*generation|daily.*gen/i.test(key)) return num(value);
        return null;
    }
    function vcbToday(message) {
        for (const [key, raw] of Object.entries(message.virtualTags || {})) {
            if (/vcb.*today|today.*energy/i.test(key)) return num(raw && typeof raw === 'object' ? raw.value : raw);
        }
        return null;
    }
    function stringSummary(values) {
        let active = 0, total = 0;
        for (const [key, value] of Object.entries(values || {})) {
            const k = key.toLowerCase();
            if (/phase|3.phase|freq|temp|reactive|apparent|inverter.*curr|total.*curr|grid.*curr|dc.*curr/.test(k)) continue;
            if (/\d/.test(key) && /curr|current|amp/i.test(key)) { total++; if (num(value) > 0.5) active++; }
        }
        return { active, total };
    }
    function classify(message) {
        const values = message.values || {};
        const task = normalize(message.task);
        const device = normalize(message.device);
        const isVcb = task === 'vcb' || device.includes('vcb') || (values['3 Phase Active Power'] !== undefined && (values['R Phase-N Voltage'] !== undefined || values['Active Total Export'] !== undefined));
        const isTransformer = task === 'transformer' || device.includes('transformer') || values['oil-temp'] !== undefined || values['winding-temp'] !== undefined;
        const isInverter = !isVcb && !isTransformer && (task === 'inverter' || device.includes('inverter') || Object.keys(values).some(k => /active.*power|ac.*power/i.test(k)));
        return { values, task, device, isVcb, isTransformer, isInverter };
    }

    function updateState(message) {
        const plant = normalize(message.unit_id) || livePlant();
        const state = ensureLiveState(plant);
        const c = classify(message);
        if (c.isVcb) {
            state.vcbPower = num(c.values['3 Phase Active Power']);
            state.hasVcb = true;
            const today = vcbToday(message); if (today !== null) state.vcbToday = today;
        }
        if (c.isInverter) {
            const name = String(message.device || 'Inverter');
            const old = state.inverters[name] || {};
            const daily = inverterDaily(c.values);
            const strings = stringSummary(c.values);
            state.inverters[name] = {
                power: inverterPower(c.values),
                daily: daily === null ? num(old.daily) : daily,
                active: strings.total ? strings.active : num(old.active),
                total: strings.total || num(old.total),
                values: c.values
            };
        }
        if (c.isTransformer) {
            if (c.values['oil-temp'] !== undefined) state.transformer.oil = num(c.values['oil-temp']);
            if (c.values['winding-temp'] !== undefined) state.transformer.winding = num(c.values['winding-temp']);
            state.transformer.time = message.time || new Date().toLocaleTimeString('en-IN', { hour12: false });
        }
        return { plant, state, c };
    }

    function setMetric(id, value, unit, digits = 2) {
        const el = document.getElementById(id); if (!el) return;
        const n = Number(value); const text = Number.isFinite(n) ? n.toFixed(digits) : '--';
        el.innerHTML = text + (unit ? ` <span class="text-xs text-slate-500">${unit}</span>` : '');
    }

    function hydrateVcb(message, c) {
        if (!c.isVcb) return;
        const v = c.values;
        document.getElementById('noData')?.classList.add('hidden');
        const pulse = document.getElementById('refreshPulse'); if (pulse) pulse.className = 'w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse';
        const p = num(v['3 Phase Active Power']);
        const status = document.getElementById('vcb_status');
        if (status) { status.textContent = p > 0 ? 'Online' : 'Standby'; status.className = 'text-3xl font-black mt-2 ' + (p > 0 ? 'text-emerald-700' : 'text-slate-500'); }
        setMetric('vcb_load', p, 'kW', 2); setMetric('vcb_freq', v['Frequency (Hz)'], 'Hz', 2);
        const today = vcbToday(message); setMetric('vcb_today', today, 'kWh', 2);
        const map = {
            v_r:['R Phase-N Voltage','V',1], v_y:['Y Phase-N Voltage','V',1], v_b:['B Phase-N Voltage','V',1],
            v_ry:['V12 (RY)','V',1], v_yb:['V23 (YB)','V',1], v_br:['V31 (BR)','V',1],
            i_r:['L1 (R)','A',2], i_y:['L2 (Y)','A',2], i_b:['L3 (B)','A',2],
            p_r:['Active Power R','kW',2], p_y:['Active Power Y','kW',2], p_b:['Active Power B','kW',2],
            pf_q1:['Q1 PF','',3], pf_q2:['Q2 PF','',3], pf_q3:['Q3 PF','',3],
            vthd_r:['Voltage THD R','%',2], vthd_y:['Voltage THD Y','%',2], vthd_b:['Voltage THD B','%',2],
            act_exp:['Active Total Export','kWh',2], act_imp:['Active Total Import','kWh',2],
            react_imp:['Reactive Import (Q1+Q2)','kVAR',2], react_exp:['Reactive Export (Q3+Q4)','kVAR',2]
        };
        Object.entries(map).forEach(([id, spec]) => setMetric(id, v[spec[0]], spec[1], spec[2]));
    }

    function hydrateTransformer(message, c) {
        if (!c.isTransformer) return;
        const v = c.values;
        const oil = v['oil-temp'] !== undefined ? num(v['oil-temp']) : null;
        const winding = v['winding-temp'] !== undefined ? num(v['winding-temp']) : null;
        if (oil !== null) { setMetric('oil_temp', oil, 'degC', 1); setMetric('oil_detail', oil, 'degC', 1); }
        if (winding !== null) { setMetric('wind_temp', winding, 'degC', 1); setMetric('wind_detail', winding, 'degC', 1); }
        if (oil === null && winding === null) return;
        document.getElementById('noDataNote')?.classList.add('hidden');
        const warn = (oil !== null && oil > 80) || (winding !== null && winding > 100);
        const status = document.getElementById('trafo_status'); if (status) { status.textContent = warn ? 'Warning' : 'Normal'; status.className = 'font-black text-3xl ' + (warn ? 'text-amber-600' : 'text-emerald-700'); }
        const badge = document.getElementById('trafo_badge'); if (badge) { badge.textContent = warn ? 'Check' : 'Live'; badge.className = 'rounded-full px-3 py-1 text-xs font-bold ' + (warn ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'); }
        const last = document.getElementById('last_update'); if (last) last.textContent = message.time || new Date().toLocaleTimeString('en-IN', { hour12: false });
    }

    function hydrateSld(state) {
        const inv = Object.values(state.inverters).reduce((sum, item) => sum + num(item.power), 0);
        setMetric('sld_pv', inv, 'kW', 2); setMetric('sld_inv', inv, 'kW', 2);
        const pv2 = document.getElementById('sld_pv2'); if (pv2) pv2.textContent = inv.toFixed(2) + ' kW';
        const inv2 = document.getElementById('sld_inv2'); if (inv2) inv2.textContent = inv.toFixed(2) + ' kW';
        if (state.hasVcb) {
            setMetric('sld_grid', state.vcbPower, 'kW', 2);
            const grid2 = document.getElementById('sld_grid2'); if (grid2) grid2.textContent = state.vcbPower.toFixed(2) + ' kW';
            const ht = document.getElementById('htStatus'); if (ht) ht.textContent = 'Live HT/VCB active power';
        }
    }

    function hydrateAnalytics(state) {
        const invValues = Object.values(state.inverters);
        const invPower = invValues.reduce((sum, item) => sum + num(item.power), 0);
        const power = state.hasVcb ? state.vcbPower : invPower;
        const energy = state.vcbToday !== null ? state.vcbToday : invValues.reduce((sum, item) => sum + num(item.daily), 0);
        const active = invValues.filter(item => num(item.power) > 0.01).length;
        const availability = invValues.length ? active / invValues.length * 100 : 0;
        setMetric('perf_val', power / 2000 * 100, '%', 1);
        setMetric('yield_val', energy, 'kWh', 2);
        setMetric('avail_val', availability, '%', 1);
    }

    function hydrateInverter(state) {
        const container = document.getElementById('inv_detail_container');
        if (!container) return;
        const names = Object.keys(state.inverters).sort((a,b) => a.localeCompare(b, undefined, { numeric: true }));
        if (!names.length) return;
        const totalPower = names.reduce((sum, name) => sum + num(state.inverters[name].power), 0);
        const totalGen = names.reduce((sum, name) => sum + num(state.inverters[name].daily), 0);
        const efficiencies = names.map(name => num(state.inverters[name].values?.['inverter efficiency']));
        const avgEff = efficiencies.length ? efficiencies.reduce((a,b)=>a+b,0) / efficiencies.length : 0;
        const totalCount = document.getElementById('inv_total_count'); if (totalCount) totalCount.textContent = names.length;
        setMetric('inv_total', totalPower, 'kW', 2); setMetric('inv_total_gen', totalGen, 'kWh', 2); setMetric('inv_avg_eff', avgEff, '%', 1);
        container.innerHTML = names.map(name => {
            const item = state.inverters[name], v = item.values || {}, power = num(item.power), online = power > 0.01;
            return `<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
              <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100"><h3 class="text-sm font-black text-slate-600 uppercase tracking-widest flex items-center gap-2"><i class="fa-solid fa-server text-blue-500"></i>${name}</h3><span class="rounded-full ${online?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500'} px-3 py-1 text-xs font-bold">${online?'Online':'Offline'}</span></div>
              <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                ${instantTile('AC Power', power, 'kW', 2)}${instantTile('Daily Gen', item.daily, 'kWh', 1)}${instantTile('Efficiency', v['inverter efficiency'], '%', 1)}${instantTile('Power Factor', v['Power Factor'], '', 3)}${instantTile('Frequency', v['a.c. frequency'], 'Hz', 2)}${instantTile('Strings', item.active, '/ '+item.total, 0)}
              </div>
              <p class="text-[10px] text-slate-400 mt-3">Initial WebSocket snapshot · full details continue updating live</p>
            </div>`;
        }).join('');
    }

    function instantTile(label, value, unit, digits) {
        const n = Number(value); const valueText = Number.isFinite(n) ? n.toFixed(digits) : '--';
        return `<div class="bg-slate-50 rounded-lg p-3 border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${label}</p><p class="mt-1 text-lg font-black text-slate-800">${valueText}${unit?` <span class="text-xs text-slate-500">${unit}</span>`:''}</p></div>`;
    }

    function hydrateReports(state, plant) {
        const select = document.getElementById('plantSelect');
        if (!select || normalize(select.value) !== plant || !document.getElementById('body')) return;
        const status = document.getElementById('status');
        if (status) status.innerHTML = '<span class="status-dot bg-emerald-500"></span>Live WebSocket connected';
    }

    function hydrateKnownPage(message) {
        const result = updateState(message);
        if (result.plant !== livePlant()) return;
        const page = pageName();
        if (page === 'vcb.php') hydrateVcb(message, result.c);
        else if (page === 'transformer.php') hydrateTransformer(message, result.c);
        else if (page === 'sld.php') hydrateSld(result.state);
        else if (page === 'analytics.php') hydrateAnalytics(result.state);
        else if (page === 'inverter.php') hydrateInverter(result.state);
        else if (page === 'reports.php') hydrateReports(result.state, result.plant);
    }

    function deliver(message, plant) {
        const data = Object.assign({}, message, { unit_id: normalize(message.unit_id) || plant });
        try { if (typeof window.handleLive === 'function') window.handleLive(data); } catch (_) {}
        try { window.dispatchEvent(new CustomEvent('scada:live', { detail: data })); } catch (_) {}
        try { hydrateKnownPage(data); } catch (_) {}
    }

    async function seedLiveOnce() {
        if (seedBusy || document.hidden) return;
        const token = currentToken();
        const plant = livePlant();
        if (!token || !PLANTS[plant]) return;
        seedBusy = true;
        try {
            const q = new URLSearchParams({ token });
            if (String(storedUser().role || '').toLowerCase() === 'admin') q.set('plant', plant);
            const response = await fetch('api_live.php?' + q.toString(), { cache: 'no-store', headers: { Authorization: 'Bearer ' + token } });
            const json = await response.json();
            if (!response.ok || !json.success || !Array.isArray(json.messages)) return;
            json.messages.forEach(message => deliver(message, plant));
        } catch (_) {
            // Direct browser WebSocket on the page remains the continuous live source.
        } finally {
            seedBusy = false;
        }
    }
    window.requestScadaLiveSeed = seedLiveOnce;

    function startLiveSeed() {
        seedLiveOnce();
        if (!seedTimer) seedTimer = window.setInterval(seedLiveOnce, WS_SEED_INTERVAL);
        const selector = document.getElementById('plantSelect');
        if (selector && selector.dataset.liveSeedBound !== '1') {
            selector.dataset.liveSeedBound = '1';
            selector.addEventListener('change', () => window.setTimeout(seedLiveOnce, 50));
        }
        document.addEventListener('visibilitychange', () => { if (!document.hidden) seedLiveOnce(); }, { passive: true });
    }

    function boot() {
        initSidebar();
        startLiveSeed();
        const container = document.getElementById('sidebar-container');
        if (container && container.dataset.sidebarObserver !== '1') {
            container.dataset.sidebarObserver = '1';
            const observer = new MutationObserver(() => window.setTimeout(initSidebar, 0));
            observer.observe(container, { childList: true });
        }
        if (!resizeBound) { resizeBound = true; window.addEventListener('resize', applyLayout, { passive: true }); }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
})();