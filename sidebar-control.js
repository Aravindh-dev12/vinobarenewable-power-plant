(function () {
    'use strict';

    const PLANTS = {
        'vinoba-1': { id:'vinoba-1', name:'Vinoba Renewable Energy Private Limited', capacity:'2.0' },
        'ssv': { id:'ssv', name:'SSV Green Power Private Limited', capacity:'1.0' }
    };
    const WS_SEED_INTERVAL = 30000;
    const DESKTOP_BREAKPOINT = 768;
    const COLLAPSE_KEY = 'vs_sidebar_collapsed';
    let seedBusy = false;
    let seedTimer = null;
    let resizeBound = false;

    const normalize = value => String(value || '').trim().toLowerCase();
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
        return PLANTS[selected] ? selected : urlPlant();
    }
    function pageName() { return (location.pathname.split('/').pop() || 'home.php').toLowerCase(); }
    function ensureUiAssets() {
        if (!document.querySelector('link[data-dashboard-ui]')) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'dashboard-ui.css?v=8';
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
      <div class="sidebar-brand-text min-w-0 w-full"><h1 id="sidebarPlantName" class="text-sm font-bold text-gray-800 leading-tight break-words">Solar Plant</h1></div>
    </div>
  </div>
  <nav id="sidebarNav" class="flex-1 p-4 space-y-1 font-medium text-sm overflow-y-auto">
    <a href="home.php" data-page="home.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-house text-gray-400 w-5 text-center"></i><span>Home</span></a>
    <a href="inverter.php" data-page="inverter.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-server text-gray-400 w-5 text-center"></i><span>Inverter</span></a>
    <a href="vcb.php" data-page="vcb.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-bolt text-gray-400 w-5 text-center"></i><span>VCB / HT Panel</span></a>
    <a href="sld.php" data-page="sld.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-diagram-project text-gray-400 w-5 text-center"></i><span>SLD</span></a>
    <a href="analytics.php" data-page="analytics.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-chart-line text-gray-400 w-5 text-center"></i><span>Analytics</span></a>
    <a href="mppt-string.php" data-page="mppt-string.php" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all border-l-4 border-transparent hover:border-emerald-500"><i class="fa-solid fa-table-cells text-gray-400 w-5 text-center"></i><span>MPPT &amp; Strings</span></a>
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
        if (!sidebar) { container.innerHTML = sidebarMarkup(); sidebar = document.getElementById('sidebar'); }
        return sidebar;
    }
    function updatePlantIdentity() {
        const info = PLANTS[urlPlant()];
        const name = document.getElementById('sidebarPlantName');
        if (name) name.textContent = info.name;
        const sldName = document.getElementById('sld_plant_name');
        if (sldName) sldName.textContent = info.name;
    }
    function wireLinks(sidebar) {
        const plant = urlPlant(), token = currentToken(), currentPage = pageName();
        sidebar.querySelectorAll('#sidebarNav a').forEach(link => {
            const page = link.dataset.page || (link.getAttribute('href') || '').split('?')[0];
            const q = new URLSearchParams({ plant }); if (token) q.set('token', token);
            link.href = page + '?' + q.toString();
            const active = page === currentPage || page.replace('.php','.html') === currentPage;
            link.classList.toggle('!bg-emerald-50', active); link.classList.toggle('!text-emerald-700', active); link.classList.toggle('!border-emerald-500', active);
            const icon = link.querySelector('i'); if (icon) icon.classList.toggle('!text-emerald-600', active);
        });
        const user = storedUser();
        const dashboard = document.getElementById('sidebarDashboard');
        if (dashboard) {
            const show = String(user.role || '').toLowerCase() === 'admin' && currentPage !== 'admin.php';
            dashboard.classList.toggle('hidden', !show); dashboard.classList.toggle('flex', show);
            dashboard.href = 'admin.php' + (token ? '?token=' + encodeURIComponent(token) : '');
        }
        const logout = document.getElementById('sidebarLogout'); if (logout) logout.href = token ? 'logout.php?token=' + encodeURIComponent(token) : 'logout.php';
    }
    function applyLayout() {
        const sidebar = document.getElementById('sidebar'); if (!sidebar) return;
        const main = document.querySelector('main'), desktop = window.innerWidth >= DESKTOP_BREAKPOINT, collapsed = desktop && localStorage.getItem(COLLAPSE_KEY) === '1';
        if (desktop) { sidebar.style.transform='translateX(0)'; sidebar.style.width=collapsed?'5rem':'16rem'; if(main)main.style.marginLeft=collapsed?'5rem':'16rem'; }
        else { sidebar.style.width='min(18rem, 88vw)'; if(sidebar.dataset.mobileOpen!=='1')sidebar.style.transform='translateX(-100%)'; if(main)main.style.marginLeft='0'; }
        sidebar.querySelectorAll('nav a span').forEach(label=>label.classList.toggle('hidden',collapsed));
        const brandText=sidebar.querySelector('.sidebar-brand-text'),brandIcon=sidebar.querySelector('.sidebar-brand-icon'),footerText=sidebar.querySelector('.sidebar-footer-text'),toggle=document.getElementById('collapseSidebarBtn'),icon=toggle?toggle.querySelector('i'):null;
        if(brandText)brandText.classList.toggle('hidden',collapsed);if(footerText)footerText.classList.toggle('hidden',collapsed);
        if(brandIcon){brandIcon.classList.toggle('h-16',!collapsed);brandIcon.classList.toggle('w-16',!collapsed);brandIcon.classList.toggle('h-10',collapsed);brandIcon.classList.toggle('w-10',collapsed);}
        sidebar.querySelectorAll('nav a').forEach(link=>{link.style.justifyContent=collapsed?'center':'';link.style.paddingLeft=collapsed?'0.5rem':'';link.style.paddingRight=collapsed?'0.5rem':'';});
        if(icon)icon.className=collapsed?'fa-solid fa-angles-right':'fa-solid fa-angles-left';if(toggle)toggle.title=collapsed?'Expand sidebar':'Collapse sidebar';
    }
    function wireControls(sidebar) {
        const overlay=document.getElementById('overlay'),menuBtn=document.getElementById('menuBtn'),closeBtn=document.getElementById('closeSidebarBtn'),collapseBtn=document.getElementById('collapseSidebarBtn');
        const openMobile=()=>{sidebar.dataset.mobileOpen='1';sidebar.style.transform='translateX(0)';if(overlay)overlay.classList.remove('hidden');};
        const closeMobile=()=>{sidebar.dataset.mobileOpen='0';if(window.innerWidth<DESKTOP_BREAKPOINT)sidebar.style.transform='translateX(-100%)';if(overlay)overlay.classList.add('hidden');};
        if(menuBtn&&menuBtn.dataset.sidebarBound!=='1'){menuBtn.dataset.sidebarBound='1';menuBtn.addEventListener('click',openMobile);}if(closeBtn&&closeBtn.dataset.sidebarBound!=='1'){closeBtn.dataset.sidebarBound='1';closeBtn.addEventListener('click',closeMobile);}if(overlay&&overlay.dataset.sidebarBound!=='1'){overlay.dataset.sidebarBound='1';overlay.addEventListener('click',closeMobile);}
        if(collapseBtn&&collapseBtn.dataset.sidebarBound!=='1'){collapseBtn.dataset.sidebarBound='1';collapseBtn.addEventListener('click',()=>{localStorage.setItem(COLLAPSE_KEY,localStorage.getItem(COLLAPSE_KEY)==='1'?'0':'1');applyLayout();});}
    }
    function initSidebar() { ensureUiAssets(); const sidebar=ensureSidebarMarkup(); if(!sidebar)return; updatePlantIdentity(); wireLinks(sidebar); wireControls(sidebar); applyLayout(); }
    window.initSidebar = initSidebar;

    function deliver(message, plant) {
        const data = Object.assign({}, message, { unit_id: normalize(message.unit_id) || plant });
        try { if (typeof window.handleLive === 'function') window.handleLive(data); } catch (_) {}
        try { window.dispatchEvent(new CustomEvent('scada:live', { detail: data })); } catch (_) {}
    }
    async function seedLiveOnce() {
        if (seedBusy || document.hidden) return;
        const token=currentToken(), plant=livePlant(); if(!token||!PLANTS[plant])return;
        seedBusy=true;
        try {
            const q=new URLSearchParams({token}); if(String(storedUser().role||'').toLowerCase()==='admin')q.set('plant',plant);
            const response=await fetch('api_live.php?'+q.toString(),{cache:'no-store',headers:{Authorization:'Bearer '+token}}),json=await response.json();
            if(!response.ok||!json.success||!Array.isArray(json.messages))return;
            json.messages.forEach(message=>deliver(message,plant));
        } catch (_) {} finally { seedBusy=false; }
    }
    window.requestScadaLiveSeed = seedLiveOnce;
    function startLiveSeed() {
        seedLiveOnce(); if(!seedTimer)seedTimer=window.setInterval(seedLiveOnce,WS_SEED_INTERVAL);
        const selector=document.getElementById('plantSelect'); if(selector&&selector.dataset.liveSeedBound!=='1'){selector.dataset.liveSeedBound='1';selector.addEventListener('change',()=>window.setTimeout(seedLiveOnce,50));}
        document.addEventListener('visibilitychange',()=>{if(!document.hidden)seedLiveOnce();},{passive:true});
    }
    function boot() {
        initSidebar(); startLiveSeed();
        const container=document.getElementById('sidebar-container'); if(container&&container.dataset.sidebarObserver!=='1'){container.dataset.sidebarObserver='1';const observer=new MutationObserver(()=>window.setTimeout(initSidebar,0));observer.observe(container,{childList:true});}
        if(!resizeBound){resizeBound=true;window.addEventListener('resize',applyLayout,{passive:true});}
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
