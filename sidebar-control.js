(function () {
    const PLANTS = {
        'vinoba-1': { name: 'Vinoba Renewable Energy Private Limited', service: '06914430133' },
        'ssv': { name: 'SSV Green Power Private Limited', service: '06914430134' }
    };
    const normalize = id => String(id || '').trim().toLowerCase();
    window.SOLAR_PLANTS = PLANTS;
    window.normalizePlantId = normalize;

    function currentPlant() {
        const params = new URLSearchParams(location.search);
        let plant = normalize(params.get('plant'));
        if (!PLANTS[plant]) {
            for (const storage of [sessionStorage, localStorage]) {
                try {
                    const user = JSON.parse(storage.getItem('vs_user') || '{}');
                    const candidate = normalize(user.plant_id);
                    if (PLANTS[candidate]) { plant = candidate; break; }
                } catch (_) {}
            }
        }
        return PLANTS[plant] ? plant : 'vinoba-1';
    }

    function updatePlantIdentity() {
        const id = currentPlant();
        const info = PLANTS[id];
        const sidebarName = document.getElementById('sidebarPlantName');
        if (sidebarName) sidebarName.textContent = info.name;
        const service = document.getElementById('sidebarServiceNumber');
        if (service) service.textContent = 'Service No. ' + info.service;
        const profile = document.getElementById('profileName');
        if (profile) profile.textContent = info.name;
        const serviceEl = document.getElementById('profileServiceNumber');
        if (serviceEl) serviceEl.textContent = 'Service Number - ' + info.service;
    }

    const DESKTOP_BREAKPOINT = 768;
    const STORAGE_KEY = 'vs_sidebar_collapsed';
    function initializeSidebar() {
        updatePlantIdentity();
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || sidebar.dataset.collapseReady === 'true') return;
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
            const desktop = innerWidth >= DESKTOP_BREAKPOINT;
            const collapsed = desktop && localStorage.getItem(STORAGE_KEY) === '1';
            sidebar.style.width = collapsed ? '5rem' : '16rem';
            sidebar.style.overflow = 'visible';
            if (main) {
                main.style.marginLeft = desktop ? (collapsed ? '5rem' : '16rem') : '0';
                main.style.transition = 'margin-left 300ms ease';
            }
            labels.forEach(x => x.classList.toggle('hidden', collapsed));
            if (brandText) brandText.classList.toggle('hidden', collapsed);
            if (footerText) footerText.classList.toggle('hidden', collapsed);
            if (sidebarHeader) sidebarHeader.style.padding = collapsed ? '0.75rem 0.5rem' : '1rem';
            if (primaryNav) primaryNav.style.padding = collapsed ? '0.5rem' : '1rem';
            if (brandIcon) {
                brandIcon.classList.toggle('h-16', !collapsed);
                brandIcon.classList.toggle('w-16', !collapsed);
                brandIcon.classList.toggle('h-10', collapsed);
                brandIcon.classList.toggle('w-10', collapsed);
            }
            navLinks.forEach(link => {
                link.style.justifyContent = collapsed ? 'center' : '';
                link.style.paddingLeft = collapsed ? '0.5rem' : '';
                link.style.paddingRight = collapsed ? '0.5rem' : '';
            });
            if (icon) icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
        }

        if (toggle) toggle.addEventListener('click', () => {
            const collapsed = localStorage.getItem(STORAGE_KEY) === '1';
            localStorage.setItem(STORAGE_KEY, collapsed ? '0' : '1');
            applyLayout();
        });
        addEventListener('resize', applyLayout);
        applyLayout();
    }

    document.addEventListener('DOMContentLoaded', initializeSidebar);
    const observer = new MutationObserver(initializeSidebar);
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
