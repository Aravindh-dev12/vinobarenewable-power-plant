(function () {
    if (window.__dashboardUiLoaded) return;
    window.__dashboardUiLoaded = true;

    function ensureStylesheet() {
        if (document.querySelector('link[data-dashboard-ui]')) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'dashboard-ui.css?v=1';
        link.dataset.dashboardUi = '1';
        document.head.appendChild(link);
    }

    function enhance(root) {
        const scope = root && root.querySelectorAll ? root : document;

        scope.querySelectorAll('main > header, body > nav').forEach(el => el.classList.add('ui-app-header'));
        scope.querySelectorAll('main > div.p-4, main > div.p-6, main > section.p-4, main > section.p-6').forEach(el => el.classList.add('ui-page'));
        scope.querySelectorAll('main .bg-white.rounded-xl, main .bg-white.rounded-2xl, main .bg-white.border.rounded-xl, main .bg-white.border.rounded-2xl').forEach(el => el.classList.add('ui-surface'));
        scope.querySelectorAll('button, input, select, textarea').forEach(el => el.classList.add('ui-control'));
        scope.querySelectorAll('.wrap, .overflow-x-auto').forEach(el => el.classList.add('ui-table-scroll'));
        scope.querySelectorAll('.fixed.inset-0').forEach(el => el.classList.add('ui-modal'));
        scope.querySelectorAll('.flex, .grid').forEach(el => el.classList.add('ui-nowrap-safe'));

        const menu = document.getElementById('menuBtn');
        if (menu && !menu.getAttribute('aria-label')) menu.setAttribute('aria-label', 'Open navigation');
        const close = document.getElementById('closeSidebarBtn');
        if (close && !close.getAttribute('aria-label')) close.setAttribute('aria-label', 'Close navigation');
        const collapse = document.getElementById('collapseSidebarBtn');
        if (collapse && !collapse.getAttribute('aria-label')) collapse.setAttribute('aria-label', 'Collapse navigation');
    }

    ensureStylesheet();

    let scheduled = false;
    function scheduleEnhance() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            enhance(document);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleEnhance, { once: true });
    } else {
        scheduleEnhance();
    }

    const observer = new MutationObserver(scheduleEnhance);
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
