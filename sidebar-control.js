(function () {
    const PLANTS = {
        'vinoba-1': {
            id: 'vinoba-1',
            name: 'Vinoba Renewable Energy Private Limited',
            service: '06914430133',
            capacity: '2.0',
            location: 'Karur'
        },
        'ssv': {
            id: 'ssv',
            name: 'SSV Green Power Private Limited',
            service: '06914430134',
            capacity: '2.0',
            location: 'Karur'
        }
    };

    const DESKTOP_BREAKPOINT = 768;
    const STORAGE_KEY = 'vs_sidebar_collapsed';
    let sidebarLoading = false;
    let relayTimer = null;
    let relayBusy = false;

    const normalize = value => String(value || '').trim().toLowerCase();

    window.SOLAR_PLANTS = PLANTS;
    window.normalizePlantId = normalize;

    function storedUser() {
        for (const storage of [sessionStorage, localStorage]) {
            try {
                const parsed = JSON.parse(storage.getItem('vs_user') || '{}');
                if (parsed && typeof parsed === 'object' && Object.keys(parsed).length) return parsed;
            } catch (_) {}
        }
        return {};
    }

    function currentToken() {
        const params = new URLSearchParams(window.location.search);
        return params.get('token') || sessionStorage.getItem('vs_token') || localStorage.getItem('vs_token') || '';
    }

    function currentPlant() {
        const params = new URLSearchParams(window.location.search);
        let plant = normalize(params.get('plant'));
        if (!PLANTS[plant]) {
            const assigned = normalize(storedUser().plant_id);
            if (PLANTS[assigned]) plant = assigned;
        }
        return PLANTS[plant] ? plant : 'vinoba-1';
    }

    function ensureUiAssets() {
        if (!document.querySelector('link[data-dashboard-ui]')) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'dashboard-ui.css?v=2';
            css.dataset.dashboardUi = '1';
            document.head.appendChild(css);
        }
        if (!document.querySelector('script[data-dashboard-ui]')) {
            const js = document.createElement('script');
            js.src = 'dashboard-ui.js?v=1';
            js.defer = true;
            js.dataset.dashboardUi = '1';
            document.head.appendChild(js);
        }
    }

    function fallbackSidebarMarkup() {
        return `<aside id="sidebar" class="w-64 bg-white border-r border-gray-200 shadow-xl flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-40">
<div class="p-4 border-b border-gray-100 relative shrink-0">
<button id="closeSidebarBtn" type="button" class="md:hidden absolute top-4 right-4 text-gray-400 text-3xl" aria-label="Close navigation">&times;</button>
<button id="collapseSidebarBtn" type="button" class="hidden md:flex absolute top-3 right-3 w-7 h-7 items-center justify-center rounded-md bg-slate-100 text-slate-500" aria-label="Collapse navigation"><i class="fa-solid fa-angles-left text-[10px]"></i></button>
<div class="flex flex-col items-center text-center gap-3 mt-2"><div class="sidebar-brand-icon h-16 w-16 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg"><i class="fa-solid fa-solar-panel"></i></div><div class="sidebar-brand-text w-full"><h1 id="sidebarPlantName" class="text-sm font-bold text-gray-800 leading-tight break-words">Solar Plant</h1><p id="sidebarServiceNumber" class="text-[9px] text-emerald-700 font-bold mt-1"></p><p class="text-[9px] text-gray-500 font-semibold mt-1 uppercase tracking-wide">Monitoring Dashboard</p></div></div>
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
<nav class="px-4 pb-4"><a href="logout.php" id="sidebarLogout" class="group flex items-center gap-3 px-3 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-semibold"><i class="fa-solid fa-right-from-bracket w-5 text-center"></i><span>Logout</span></a></nav>
<div class="px-2 py-2 bg-slate-50 border-t border-gray-200 shrink-0"><div class="sidebar-footer-text text-[8px] font-black uppercase tracking-wide text-gray-500 text-center">Powered by <span class="text-emerald-700">Oriks Care Pvt Ltd</span></div></div>
</aside>`;
    }

    async function ensureSidebar() {
        if (document.getElementById('sidebar')) {
            initializeSidebar();
            return;
        }
        const container = document.getElementById('sidebar-container');
        if (!container || sidebarLoading) return;
        sidebarLoading = true;
        try {
            const response = await fetch('sidebar.html?v=6', { cache: 'no-store' });
            if (!response.ok) throw new Error('sidebar unavailable');
            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const aside = parsed.getElementById('sidebar');
            if (!aside) throw new Error('sidebar markup missing');
            container.replaceChildren(document.importNode(aside, true));
        } catch (_) {
            container.innerHTML = fallbackSidebarMarkup();
        } finally {
            sidebarLoading = false;
            initializeSidebar();
        }
    }

    function updatePlantIdentity() {
        const plant = currentPlant();
        const info = PLANTS[plant];

        const nameTargets = ['sidebarPlantName', 'profileName', 'sld_plant_name'];
        nameTargets.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = info.name;
        });

        const sidebarService = document.getElementById('sidebarServiceNumber');
        if (sidebarService) sidebarService.textContent = 'Service No. ' + info.service;

        let profileService = document.getElementById('profileServiceNumber');
        const profileName = document.getElementById('profileName');
        if (profileName && !profileService) {
            profileService = document.createElement('p');
            profileService.id = 'profileServiceNumber';
            profileService.className = 'text-xs text-emerald-700 font-bold mt-1';
            profileName.insertAdjacentElement('afterend', profileService);
        }
        if (profileService) profileService.textContent = 'Service Number - ' + info.service;

        const capacity = document.getElementById('profileCapacity');
        if (capacity) capacity.innerHTML = info.capacity + ' <span class="text-sm font-bold">MW</span>';
        const location = document.getElementById('profileLocation');
        if (location) location.textContent = info.location;
    }

    function wireLinks(sidebar) {
        const plant = currentPlant();
        const token = currentToken();
        const currentPage = window.location.pathname.split('/').pop() || 'home.php';

        sidebar.querySelectorAll('#sidebarNav a').forEach(link => {
            const page = link.dataset.page || String(link.getAttribute('href') || '').split('?')[0];
            const url = new URL(page, window.location.href);
            url.searchParams.set('plant', plant);
            if (token) url.searchParams.set('token', token);
            link.href = url.pathname.split('/').pop() + url.search;

            const active = page === currentPage || page.replace('.php', '.html') === currentPage;
            link.classList.toggle('!bg-slate-100', active);
            link.classList.toggle('!text-emerald-700', active);
            link.classList.toggle('!border-emerald-500', active);
            link.classList.toggle('shadow-lg', active);
            link.classList.toggle('shadow-slate-200', active);
            const icon = link.querySelector('i');
            if (icon) icon.classList.toggle('!text-emerald-600', active);
        });

        const logout = document.getElementById('sidebarLogout') || sidebar.querySelector('a[href^="logout.php"]');
        if (logout) logout.href = token ? 'logout.php?token=' + encodeURIComponent(token) : 'logout.php';
    }

    function installAdminBackLink() {
        const user = storedUser();
        if (String(user.role || '').toLowerCase() !== 'admin') return;
        if (document.getElementById('plantAdminBack')) return;
        if (/\/admin\.php$/i.test(window.location.pathname)) return;

        const header = document.querySelector('main > header');
        if (!header) return;
        const left = header.querySelector(':scope > div') || header.firstElementChild;
        if (!left) return;

        const link = document.createElement('a');
        link.id = 'plantAdminBack';
        const token = currentToken();
        link.href = 'admin.php' + (token ? '?token=' + encodeURIComponent(token) : '');
        link.title = 'Back to Plants';
        link.className = 'flex items-center gap-2 px-3 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition shrink-0';
        link.innerHTML = '<i class="fa-solid fa-arrow-left"></i><span class="text-sm font-bold hidden sm:inline">Dashboard</span>';
        left.insertBefore(link, left.firstChild);
    }

    function wireMobile(sidebar) {
        const overlay = document.getElementById('overlay');
        const menuBtn = document.getElementById('menuBtn');
        const closeBtn = document.getElementById('closeSidebarBtn');

        const close = () => {
            sidebar.classList.add('-translate-x-full');
            overlay?.classList.add('hidden');
        };
        const open = () => {
            sidebar.classList.remove('-translate-x-full');
            overlay?.classList.remove('hidden');
        };

        if (menuBtn && menuBtn.dataset.sharedSidebarBound !== '1') {
            menuBtn.dataset.sharedSidebarBound = '1';
            menuBtn.addEventListener('click', open);
        }
        if (closeBtn && closeBtn.dataset.sharedSidebarBound !== '1') {
            closeBtn.dataset.sharedSidebarBound = '1';
            closeBtn.addEventListener('click', close);
        }
        if (overlay && overlay.dataset.sharedSidebarBound !== '1') {
            overlay.dataset.sharedSidebarBound = '1';
            overlay.addEventListener('click', close);
        }
    }

    function initializeSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        updatePlantIdentity();
        wireLinks(sidebar);
        wireMobile(sidebar);
        installAdminBackLink();

        if (sidebar.dataset.collapseReady === 'true') return;
        sidebar.dataset.collapseReady = 'true';

        const main = document.querySelector('main');
        const toggle = document.getElementById('collapseSidebarBtn');
        const icon = toggle ? toggle.querySelector('i') : null;
        const navLinks = sidebar.querySelectorAll('nav a');
        const primaryNav = document.getElementById('sidebarNav');
        const labels = sidebar.querySelectorAll('nav a span');
        const brandText = sidebar.querySelector('.sidebar-brand-text');
        const brandIcon = sidebar.querySelector('.sidebar-brand-icon');
        const footerText = sidebar.querySelector('.sidebar-footer-text');
        const sidebarHeader = sidebar.firstElementChild;

        if (toggle && sidebarHeader) {
            sidebarHeader.appendChild(toggle);
            toggle.className = 'hidden md:flex absolute top-3 w-7 h-7 items-center justify-center rounded-full bg-white border border-slate-200 shadow-md text-slate-500 hover:bg-emerald-100 hover:text-emerald-700 transition z-50';
            toggle.style.right = '-0.85rem';
        }

        function applyLayout() {
            const desktop = window.innerWidth >= DESKTOP_BREAKPOINT;
            const collapsed = desktop && localStorage.getItem(STORAGE_KEY) === '1';

            sidebar.style.width = collapsed ? '5rem' : '16rem';
            sidebar.style.overflow = 'visible';
            if (main) {
                main.style.marginLeft = desktop ? (collapsed ? '5rem' : '16rem') : '0';
                main.style.transition = 'margin-left 300ms ease';
            }

            labels.forEach(label => label.classList.toggle('hidden', collapsed));
            brandText?.classList.toggle('hidden', collapsed);
            footerText?.classList.toggle('hidden', collapsed);
            if (sidebarHeader) sidebarHeader.style.padding = collapsed ? '0.75rem 0.5rem' : '1rem';
            if (primaryNav) primaryNav.style.padding = collapsed ? '0.5rem' : '1rem';
            if (brandIcon) {
                brandIcon.classList.toggle('h-16', !collapsed);
                brandIcon.classList.toggle('w-16', !collapsed);
                brandIcon.classList.toggle('h-10', collapsed);
                brandIcon.classList.toggle('w-10', collapsed);
            }

            navLinks.forEach(link => {
                link.classList.toggle('justify-center', collapsed);
                link.style.justifyContent = collapsed ? 'center' : '';
                link.style.paddingLeft = collapsed ? '0.5rem' : '';
                link.style.paddingRight = collapsed ? '0.5rem' : '';
                link.style.minHeight = '2.75rem';
                const label = link.querySelector('span');
                const linkIcon = link.querySelector('i');
                if (label) link.title = collapsed ? label.textContent.trim() : '';
                if (linkIcon) {
                    linkIcon.style.display = 'inline-flex';
                    linkIcon.style.alignItems = 'center';
                    linkIcon.style.justifyContent = 'center';
                    linkIcon.style.flexShrink = '0';
                    linkIcon.style.margin = '0';
                }
            });

            if (icon) icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
            if (toggle) {
                toggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
                toggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
            }
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                const collapsed = localStorage.getItem(STORAGE_KEY) === '1';
                localStorage.setItem(STORAGE_KEY, collapsed ? '0' : '1');
                applyLayout();
            });
        }

        window.addEventListener('resize', applyLayout, { passive: true });
        applyLayout();
    }

    // Keep compatibility with the original Vinoba-Scada plant pages, which call
    // initSidebar() after injecting sidebar.html.
    window.initSidebar = initializeSidebar;

    async function relayLiveOnce() {
        if (relayBusy || typeof window.handleLive !== 'function') return;
        relayBusy = true;
        try {
            const token = currentToken();
            const user = storedUser();
            const params = new URLSearchParams();
            if (token) params.set('token', token);
            if (String(user.role || '').toLowerCase() === 'admin') params.set('plant', currentPlant());
            const response = await fetch('api_live.php?' + params.toString(), { cache: 'no-store' });
            const json = await response.json();
            if (!response.ok || !json.success || !Array.isArray(json.messages)) return;
            json.messages.forEach(message => {
                try { window.handleLive({ ...message, unit_id: currentPlant() }); } catch (_) {}
            });
        } catch (_) {
            // Direct WebSocket and database fallbacks on each page remain active.
        } finally {
            relayBusy = false;
        }
    }

    function startBackendRelay() {
        if (relayTimer || typeof window.handleLive !== 'function') return;
        relayLiveOnce();
        relayTimer = window.setInterval(relayLiveOnce, 5000);
    }

    function boot() {
        ensureUiAssets();
        ensureSidebar();
        initializeSidebar();
        startBackendRelay();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();

    window.addEventListener('load', function () {
        initializeSidebar();
        startBackendRelay();
    }, { once: true });

    const observer = new MutationObserver(function () {
        if (!document.getElementById('sidebar')) ensureSidebar();
        else initializeSidebar();
        startBackendRelay();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
