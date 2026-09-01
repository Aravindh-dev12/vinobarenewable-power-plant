(function () {
    const DESKTOP_BREAKPOINT = 768;
    const STORAGE_KEY = 'vs_sidebar_collapsed';

    function initializeSidebar() {
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

        // Keep the same top-right control position even if older sidebar markup
        // remains in the browser cache.
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
            if (icon) icon.className = collapsed
                ? 'fa-solid fa-angles-right'
                : 'fa-solid fa-angles-left';
            if (toggle) toggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                const collapsed = localStorage.getItem(STORAGE_KEY) === '1';
                localStorage.setItem(STORAGE_KEY, collapsed ? '0' : '1');
                applyLayout();
            });
        }
        window.addEventListener('resize', applyLayout);
        applyLayout();
    }

    document.addEventListener('DOMContentLoaded', initializeSidebar);
    const observer = new MutationObserver(initializeSidebar);
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();

// Home overview: keep the Today Energy card live even when the VCB virtual tag
// is missing or delayed. Vinoba in particular reliably publishes each inverter's
// daily generation, so use that as a live fallback and the report API as backup.
(function () {
    function startHomeTodayEnergyFix() {
        const energyEl = document.getElementById('vcb_etoday');
        if (!energyEl || energyEl.dataset.liveEnergyFix === '1') return;
        energyEl.dataset.liveEnergyFix = '1';

        const params = new URLSearchParams(window.location.search);
        const plant = params.get('plant') || 'vinoba-velliyanai';
        const token = params.get('token') || sessionStorage.getItem('vs_token') || localStorage.getItem('vs_token') || '';
        const inverterDaily = {};
        let vcbToday = null;
        let apiToday = 0;
        let lastLiveAt = 0;
        let energySocket = null;
        let reconnectTimer = null;

        function indiaDate() {
            const parts = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit'
            }).formatToParts(new Date());
            const p = {};
            parts.forEach(x => { if (x.type !== 'literal') p[x.type] = x.value; });
            return `${p.year}-${p.month}-${p.day}`;
        }

        function liveInverterTotal() {
            return Object.values(inverterDaily).reduce((sum, value) => sum + (Number(value) || 0), 0);
        }

        function bestEnergy() {
            const inverterTotal = liveInverterTotal();
            if (vcbToday !== null && Number.isFinite(vcbToday) && vcbToday > 0) return vcbToday;
            if (inverterTotal > 0) return inverterTotal;
            return apiToday > 0 ? apiToday : 0;
        }

        function paintEnergy() {
            const value = bestEnergy();
            energyEl.innerHTML = value.toFixed(2) + ' <span class="text-sm font-bold text-purple-600">kWh</span>';
        }

        function readVirtualToday(data) {
            const tags = data && data.virtualTags;
            if (!tags || typeof tags !== 'object') return null;
            const candidateKeys = ['vcb-today', 'vcb_today', 'today-energy', 'today_energy', 'today'];
            for (const key of candidateKeys) {
                if (tags[key] === undefined) continue;
                const raw = tags[key] && typeof tags[key] === 'object' ? tags[key].value : tags[key];
                const n = Number(raw);
                if (Number.isFinite(n)) return n;
            }
            for (const [key, rawValue] of Object.entries(tags)) {
                if (!/vcb.*today|today.*energy|energy.*today/i.test(key)) continue;
                const raw = rawValue && typeof rawValue === 'object' ? rawValue.value : rawValue;
                const n = Number(raw);
                if (Number.isFinite(n)) return n;
            }
            return null;
        }

        function handleLive(data) {
            if (!data || data.unit_id !== plant) return;
            const virtualToday = readVirtualToday(data);
            if (virtualToday !== null) {
                vcbToday = virtualToday;
                lastLiveAt = Date.now();
            }

            const values = data.values || {};
            const task = String(data.task || '').toLowerCase();
            const device = String(data.device || '');
            const isInverter = task === 'inverter' || device.toLowerCase().includes('inverter');
            if (isInverter) {
                for (const [key, raw] of Object.entries(values)) {
                    if (!/daily.*generation|daily.*gen/i.test(key)) continue;
                    const n = Number(raw);
                    if (Number.isFinite(n)) {
                        inverterDaily[device || 'Unknown Inverter'] = n;
                        lastLiveAt = Date.now();
                        break;
                    }
                }
            }
            paintEnergy();
        }

        function connectEnergySocket() {
            if (energySocket && (energySocket.readyState === WebSocket.OPEN || energySocket.readyState === WebSocket.CONNECTING)) return;
            try {
                energySocket = new WebSocket('wss://vinobasolar.scadahub.in:5001');
                energySocket.onopen = function () {
                    energySocket.send(JSON.stringify({ type: 'subscribe', unit_id: plant }));
                };
                energySocket.onmessage = function (event) {
                    try { handleLive(JSON.parse(event.data)); } catch (_) {}
                };
                energySocket.onclose = function () {
                    energySocket = null;
                    clearTimeout(reconnectTimer);
                    reconnectTimer = setTimeout(connectEnergySocket, 5000);
                };
                energySocket.onerror = function () {};
            } catch (_) {
                clearTimeout(reconnectTimer);
                reconnectTimer = setTimeout(connectEnergySocket, 5000);
            }
        }

        async function fetchEnergyFallback() {
            try {
                const q = new URLSearchParams({ tab: 'inv_vcb', type: 'daily', date: indiaDate(), plant: plant });
                if (token) q.set('token', token);
                const res = await fetch('api_reports.php?' + q.toString(), {
                    cache: 'no-store',
                    headers: token ? { 'Authorization': 'Bearer ' + token } : {}
                });
                const json = await res.json();
                if (!json.success || !Array.isArray(json.data)) return;
                let best = 0;
                json.data.forEach(row => {
                    const total = Number(row.inv_total_kwh || 0);
                    if (total > best) best = total;
                });
                apiToday = best;
                // Do not replace fresh telemetry with a database value; only fill gaps.
                if (Date.now() - lastLiveAt > 15000) paintEnergy();
            } catch (_) {}
        }

        // home.php's original updateDash() can repaint this card with a stale zero.
        // Wrap it so the live/fallback energy is restored immediately afterward.
        if (typeof window.updateDash === 'function' && !window.updateDash.__todayEnergyWrapped) {
            const originalUpdateDash = window.updateDash;
            const wrapped = function () {
                const result = originalUpdateDash.apply(this, arguments);
                paintEnergy();
                return result;
            };
            wrapped.__todayEnergyWrapped = true;
            window.updateDash = wrapped;
        }

        connectEnergySocket();
        fetchEnergyFallback();
        setInterval(fetchEnergyFallback, 10000);
        setInterval(paintEnergy, 1000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startHomeTodayEnergyFix);
    else startHomeTodayEnergyFix();
})();
