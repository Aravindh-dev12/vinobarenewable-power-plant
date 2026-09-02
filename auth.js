(function() {
    const aliases = {
        'vinoba-velliyanai': 'vinoba-1',
        'vinoba': 'vinoba-1',
        'makkalpower': 'ssv',
        'makkal-power': 'ssv',
        'anushyam': 'ssv'
    };
    const normalize = value => {
        const id = String(value || '').trim().toLowerCase();
        return aliases[id] || id;
    };
    const token = localStorage.getItem('vs_token') || sessionStorage.getItem('vs_token');
    const rawUser = localStorage.getItem('vs_user') || sessionStorage.getItem('vs_user');
    if (!token || !rawUser) { window.location.replace('index.php'); return; }
    let user;
    try { user = JSON.parse(rawUser); } catch (_) { window.location.replace('index.php'); return; }
    if (user.plant_id) {
        const canonical = normalize(user.plant_id);
        if (canonical !== user.plant_id) {
            user.plant_id = canonical;
            localStorage.setItem('vs_user', JSON.stringify(user));
            sessionStorage.setItem('vs_user', JSON.stringify(user));
        }
    }
    const current = normalize(new URLSearchParams(location.search).get('plant') || '');
    if (user.role !== 'admin' && user.plant_id && current && current !== user.plant_id) {
        window.location.replace('home.php?plant=' + encodeURIComponent(user.plant_id) + '&token=' + encodeURIComponent(token));
    }
})();
