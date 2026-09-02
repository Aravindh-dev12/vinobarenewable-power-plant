(function() {
    const validPlants = new Set(['vinoba-1', 'ssv']);
    const normalize = value => String(value || '').trim().toLowerCase();
    const token = localStorage.getItem('vs_token') || sessionStorage.getItem('vs_token');
    const rawUser = localStorage.getItem('vs_user') || sessionStorage.getItem('vs_user');
    if (!token || !rawUser) { window.location.replace('index.php'); return; }

    let user;
    try { user = JSON.parse(rawUser); }
    catch (_) { window.location.replace('index.php'); return; }

    if (user.role !== 'admin') {
        const assigned = normalize(user.plant_id);
        if (!validPlants.has(assigned)) {
            window.location.replace('index.php');
            return;
        }
        user.plant_id = assigned;
        localStorage.setItem('vs_user', JSON.stringify(user));
        sessionStorage.setItem('vs_user', JSON.stringify(user));

        const current = normalize(new URLSearchParams(location.search).get('plant') || '');
        if (current && current !== assigned) {
            window.location.replace('home.php?plant=' + encodeURIComponent(assigned) + '&token=' + encodeURIComponent(token));
        }
    }
})();
