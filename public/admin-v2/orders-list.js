/* Admin V2 - Orders list behaviour: filter panel, row action menus, status modal, payment countdown, loading state. */
(function () {
    'use strict';
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* ---- "View tickets" popup: the ticket types of one order (data comes from the row, nothing is fetched) ---- */
    var tixModal = document.getElementById('ordTixModal');
    if (tixModal) {
        var tixList = document.getElementById('ordTixList');
        var tixLast = null;
        var money = function (n) { return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var closeTix = function () {
            tixModal.classList.remove('show'); tixModal.setAttribute('aria-hidden', 'true');
            if (tixLast && tixLast.isConnected) tixLast.focus();
        };
        document.addEventListener('click', function (e) {
            var open = e.target.closest('[data-ord-tix-open]');
            if (open) {
                var types = [];
                try { types = JSON.parse(open.getAttribute('data-types') || '[]'); } catch (err) { types = []; }
                tixLast = open;
                document.getElementById('ordTixNumber').textContent = open.getAttribute('data-number') || '';
                tixList.textContent = '';
                var qty = 0, sum = 0;
                types.forEach(function (t) {
                    qty += t.qty; sum += t.total;
                    var li = document.createElement('li');
                    var dot = document.createElement('i'); dot.className = 'a-ord-dot';
                    if (/^#[0-9a-fA-F]{6}$/.test(t.color || '')) dot.style.background = t.color;
                    var name = document.createElement('span'); name.className = 'a-ord-tixlist__name'; name.textContent = t.name;
                    var meta = document.createElement('span'); meta.className = 'a-ord-tixlist__meta'; meta.textContent = t.qty + ' × ' + money(t.price);
                    var total = document.createElement('b'); total.textContent = money(t.total) + ' EGP';
                    li.appendChild(dot); li.appendChild(name); li.appendChild(meta); li.appendChild(total);
                    tixList.appendChild(li);
                });
                document.getElementById('ordTixCount').textContent = qty;
                document.getElementById('ordTixSum').textContent = money(sum) + ' EGP';
                tixModal.classList.add('show'); tixModal.setAttribute('aria-hidden', 'false');
                var closeBtn = tixModal.querySelector('[data-ord-tix-close]'); if (closeBtn) closeBtn.focus();
                return;
            }
            if (e.target.closest('[data-ord-tix-close]') || e.target === tixModal) closeTix();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && tixModal.classList.contains('show')) closeTix(); });
    }

    /* ---- advanced filters panel ---- */
    $$('[data-ord-filter-toggle]').forEach(function (btn) {
        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;
        btn.addEventListener('click', function () {
            var open = panel.hasAttribute('hidden');
            if (open) panel.removeAttribute('hidden'); else panel.setAttribute('hidden', '');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    /* ---- row action menus: the table wrapper clips overflow, so the panel is positioned in viewport space ---- */
    function placeMenu(menu) {
        var btn = $('[data-a-menu-toggle]', menu), panel = $('.a-menu__panel', menu);
        if (!btn || !panel || !menu.classList.contains('is-open')) return;
        var r = btn.getBoundingClientRect();
        var h = panel.offsetHeight, w = panel.offsetWidth;
        var below = window.innerHeight - r.bottom;
        var top = below >= h + 12 || r.top < h + 12 ? r.bottom + 6 : r.top - h - 6;
        var left = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w));
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('.a-menu--fixed [data-a-menu-toggle]');
        if (!t) return;
        var menu = t.closest('.a-menu');
        // admin-v2.js toggles the class in its own click handler; read the result after it ran
        setTimeout(function () { placeMenu(menu); var first = $('.a-menu__item', menu); if (menu.classList.contains('is-open') && first && e.detail === 0) first.focus(); }, 0);
    });
    function closeMenus() { $$('.a-menu--fixed.is-open').forEach(function (m) { m.classList.remove('is-open'); var b = $('[data-a-menu-toggle]', m); if (b) b.setAttribute('aria-expanded', 'false'); }); }
    window.addEventListener('resize', closeMenus);
    window.addEventListener('scroll', closeMenus, true);

    /* ---- change-status modal ---- */
    var modal = document.getElementById('ordStatusModal');
    if (modal) {
        var form = document.getElementById('ordStatusForm');
        var select = document.getElementById('ordStatusSelect');
        var label = document.getElementById('ordStatusNumber');
        var lastTrigger = null;
        var close = function () {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            if (lastTrigger && lastTrigger.isConnected) lastTrigger.focus();
        };
        document.addEventListener('click', function (e) {
            var open = e.target.closest('[data-ord-status-open]');
            if (open) {
                lastTrigger = open.closest('.a-menu') ? $('[data-a-menu-toggle]', open.closest('.a-menu')) : open;
                closeMenus();
                form.setAttribute('action', open.dataset.action);
                label.textContent = '#' + open.dataset.number;
                select.value = open.dataset.current;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                var focusable = $('.a-sel__btn', modal) || select;
                focusable.focus();
                return;
            }
            if (e.target.closest('[data-ord-status-close]') || e.target === modal) close();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('show')) close(); });
    }

    /* ---- payment deadline countdown ---- */
    var timers = $$('.js-admin-order-timer');
    if (timers.length) {
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var render = function (el, s) {
            if (s <= 0) { el.textContent = 'Expired'; el.classList.add('is-expired'); return; }
            var d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
            el.textContent = 'Expires in ' + (d > 0 ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s % 60);
        };
        timers.forEach(function (el) {
            var left = parseInt(el.dataset.secondsLeft || '0', 10);
            render(el, left);
            if (left > 0) setInterval(function () { left -= 1; render(el, left); }, 1000);
        });
    }

    /* ---- loading state while a filter/sort/page request is in flight ---- */
    var card = $('.a-ord-listcard');
    function busy() { if (card) { card.classList.add('is-loading'); card.setAttribute('aria-busy', 'true'); } }
    document.addEventListener('submit', function (e) {
        if (!e.target.matches('[data-a-loading-table]')) return;
        // keep the URL clean: empty filters are not sent
        $$('input[name], select[name]', e.target).forEach(function (f) { if (f.type !== 'hidden' && f.value === '') f.disabled = true; });
        busy();
    });
    document.addEventListener('click', function (e) {
        var a = e.target.closest('.a-ord-quick a, .a-ordt__sort, .a-pager a');
        if (a && !e.defaultPrevented && !e.metaKey && !e.ctrlKey) busy();
    });
    window.addEventListener('pageshow', function () { if (card) { card.classList.remove('is-loading'); card.removeAttribute('aria-busy'); } });
})();
