(function () {
    const PLANTS = {
        'vinoba-1': { name: 'Vinoba Renewable Energy Private Limited', service: '06914430133' },
        'ssv': { name: 'SSV Green Power Private Limited', service: '06914430134' }
    };
    const LEGACY = {
        'vinoba-velliyanai': 'vinoba-1', 'vinoba': 'vinoba-1',
        'makkalpower': 'ssv', 'makkal-power': 'ssv', 'anushyam': 'ssv'
    };
    const normalize = id => {
        const v = String(id || '').trim().toLowerCase();
        return LEGACY[v] || v;
    };
    window.SOLAR_PLANTS = PLANTS;
    window.normalizePlantId = normalize;

    function normalizeStoredUser(storage) {
        try {
            const raw = storage.getItem('vs_user');
            if (!raw) return;
            const user = JSON.parse(raw);
            const next = normalize(user.plant_id);
            if (PLANTS[next] && next !== user.plant_id) {
                user.plant_id = next;
                storage.setItem('vs_user', JSON.stringify(user));
            }
        } catch (_) {}
    }
    normalizeStoredUser(localStorage);
    normalizeStoredUser(sessionStorage);

    function currentPlant() {
        const params = new URLSearchParams(location.search);
        let plant = normalize(params.get('plant'));
        if (!PLANTS[plant]) {
            for (const storage of [sessionStorage, localStorage]) {
                try {
                    const user = JSON.parse(storage.getItem('vs_user') || '{}');
                    const p = normalize(user.plant_id);
                    if (PLANTS[p]) { plant = p; break; }
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
        if (profile && !document.getElementById('profileServiceNumber')) {
            const target = document.getElementById('profileLocation') || profile;
            const el = document.createElement('p');
            el.id = 'profileServiceNumber';
            el.className = 'text-xs text-slate-500 font-semibold mt-1';
            el.textContent = 'Service Number - ' + info.service;
            target.parentElement ? target.parentElement.appendChild(el) : profile.insertAdjacentElement('afterend', el);
        }
        const serviceEl = document.getElementById('profileServiceNumber');
        if (serviceEl) serviceEl.textContent = 'Service Number - ' + info.service;
        if (document.title) {
            const suffix = document.title.includes(' - ') ? document.title.substring(document.title.indexOf(' - ')) : '';
            if (/vinoba|makkal|anushyam|ssv|solar plant/i.test(document.title)) document.title = info.name + suffix;
        }
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
            if (main) { main.style.marginLeft = desktop ? (collapsed ? '5rem' : '16rem') : '0'; main.style.transition = 'margin-left 300ms ease'; }
            labels.forEach(x => x.classList.toggle('hidden', collapsed));
            if (brandText) brandText.classList.toggle('hidden', collapsed);
            if (footerText) footerText.classList.toggle('hidden', collapsed);
            if (sidebarHeader) sidebarHeader.style.padding = collapsed ? '0.75rem 0.5rem' : '1rem';
            if (primaryNav) primaryNav.style.padding = collapsed ? '0.5rem' : '1rem';
            if (brandIcon) { brandIcon.classList.toggle('h-16', !collapsed); brandIcon.classList.toggle('w-16', !collapsed); brandIcon.classList.toggle('h-10', collapsed); brandIcon.classList.toggle('w-10', collapsed); }
            navLinks.forEach(link => { link.style.justifyContent = collapsed ? 'center' : ''; link.style.paddingLeft = collapsed ? '0.5rem' : ''; link.style.paddingRight = collapsed ? '0.5rem' : ''; });
            if (icon) icon.className = collapsed ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
        }
        if (toggle) toggle.addEventListener('click', () => { const c=localStorage.getItem(STORAGE_KEY)==='1'; localStorage.setItem(STORAGE_KEY,c?'0':'1'); applyLayout(); });
        addEventListener('resize', applyLayout); applyLayout();
    }

    function startTodayEnergyFix() {
        const energyEl = document.getElementById('vcb_etoday');
        if (!energyEl || energyEl.dataset.liveEnergyFix === '1') return;
        energyEl.dataset.liveEnergyFix = '1';
        const plant = currentPlant();
        const token = new URLSearchParams(location.search).get('token') || sessionStorage.getItem('vs_token') || localStorage.getItem('vs_token') || '';
        const invDaily = {};
        let vcbToday = null, apiToday = 0, lastLiveAt = 0, socket = null, timer = null;
        function indiaDate() {
            const p={}; new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).forEach(x=>{if(x.type!=='literal')p[x.type]=x.value;});
            return `${p.year}-${p.month}-${p.day}`;
        }
        const invTotal = () => Object.values(invDaily).reduce((s,v)=>s+(Number(v)||0),0);
        function paint() {
            const liveInv=invTotal();
            const value=(vcbToday!==null&&vcbToday>0)?vcbToday:(liveInv>0?liveInv:apiToday);
            energyEl.innerHTML=(Number(value)||0).toFixed(2)+' <span class="text-sm font-bold text-purple-600">kWh</span>';
        }
        function handle(data) {
            if (!data || normalize(data.unit_id)!==plant) return;
            const tags=data.virtualTags||{};
            for (const key of Object.keys(tags)) {
                if (/vcb.*today|today.*energy|energy.*today/i.test(key)) {
                    const raw=typeof tags[key]==='object'?tags[key].value:tags[key]; const n=Number(raw);
                    if(Number.isFinite(n)){vcbToday=n;lastLiveAt=Date.now();break;}
                }
            }
            const values=data.values||{}; const task=String(data.task||'').toLowerCase(); const dev=String(data.device||'');
            if(task==='inverter'||dev.toLowerCase().includes('inverter')){
                for(const [key,raw] of Object.entries(values)) if(/daily.*generation|daily.*gen/i.test(key)){const n=Number(raw);if(Number.isFinite(n)){invDaily[dev||'Inverter']=n;lastLiveAt=Date.now();break;}}
            }
            paint();
        }
        function connect() {
            if(socket&&(socket.readyState===WebSocket.OPEN||socket.readyState===WebSocket.CONNECTING))return;
            try{
                socket=new WebSocket('wss://vinobasolar.scadahub.in:5001');
                socket.onopen=()=>socket.send(JSON.stringify({type:'subscribe',unit_id:plant}));
                socket.onmessage=e=>{try{handle(JSON.parse(e.data));}catch(_){} };
                socket.onclose=()=>{socket=null;clearTimeout(timer);timer=setTimeout(connect,5000);}; socket.onerror=()=>{};
            }catch(_){clearTimeout(timer);timer=setTimeout(connect,5000);}
        }
        async function fallback(){
            try{
                const q=new URLSearchParams({tab:'inv_vcb',type:'daily',date:indiaDate(),plant});if(token)q.set('token',token);
                const r=await fetch('api_reports.php?'+q,{cache:'no-store',headers:token?{'Authorization':'Bearer '+token}:{}});const j=await r.json();if(!j.success||!Array.isArray(j.data))return;
                apiToday=Math.max(0,...j.data.map(x=>Number(x.inv_total_kwh||0)));if(Date.now()-lastLiveAt>15000)paint();
            }catch(_){}
        }
        connect(); fallback(); setInterval(fallback,10000); setInterval(paint,1000);
    }

    document.addEventListener('DOMContentLoaded', () => { initializeSidebar(); updatePlantIdentity(); startTodayEnergyFix(); });
    const observer = new MutationObserver(() => { initializeSidebar(); updatePlantIdentity(); });
    observer.observe(document.documentElement,{childList:true,subtree:true});
})();
