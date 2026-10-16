/* TKT House — Admin V2 shell behaviour (sidebar, menus, confirmations, loading states). No dependencies. */
(function () {
    'use strict';
    var shell = document.querySelector('.a-shell');
    if (!shell) return;

    /* ---- off-canvas sidebar (below 992px) ---- */
    var sidebar = document.getElementById('aSidebar');
    var openBtn = document.querySelector('[data-a-nav-open]');
    var lastFocus = null;
    function setNav(open) {
        shell.classList.toggle('is-nav-open', open);
        if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) { lastFocus = document.activeElement; var first = sidebar.querySelector('a, button'); if (first) first.focus(); }
        else if (lastFocus) { try { lastFocus.focus(); } catch (e) {} }
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-a-nav-open]')) { setNav(true); return; }
        if (e.target.closest('[data-a-nav-close]')) { setNav(false); return; }
        // submenu toggles
        var t = e.target.closest('[data-a-submenu]');
        if (t) {
            e.preventDefault();
            var item = t.closest('.a-nav__item');
            var open = !item.classList.contains('is-open');
            item.classList.toggle('is-open', open);
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        // dropdown menus
        var mt = e.target.closest('[data-a-menu-toggle]');
        $$('.a-menu.is-open').forEach(function (m) { if (!mt || !m.contains(mt)) { m.classList.remove('is-open'); var b = m.querySelector('[data-a-menu-toggle]'); if (b) b.setAttribute('aria-expanded', 'false'); } });
        if (mt) {
            var menu = mt.closest('.a-menu');
            var on = !menu.classList.contains('is-open');
            menu.classList.toggle('is-open', on);
            mt.setAttribute('aria-expanded', on ? 'true' : 'false');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (shell.classList.contains('is-nav-open')) setNav(false);
        $$('.a-menu.is-open').forEach(function (m) { m.classList.remove('is-open'); var b = m.querySelector('[data-a-menu-toggle]'); if (b) { b.setAttribute('aria-expanded', 'false'); b.focus(); } });
    });
    // keep focus inside the open off-canvas sidebar
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !shell.classList.contains('is-nav-open')) return;
        var f = $$('a[href], button:not([disabled])', sidebar).filter(function (el) { return el.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    window.matchMedia('(min-width: 992px)').addEventListener('change', function (m) { if (m.matches) setNav(false); });

    /* ---- loading state: forms with data-a-loading disable their submit button once ---- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form')) return;
        if (e.defaultPrevented) return;
        var btn = form.querySelector('button[type=submit][data-a-loading], [data-a-loading] button[type=submit]');
        if (btn && !btn.classList.contains('is-loading')) { setTimeout(function () { btn.classList.add('is-loading'); btn.disabled = true; }, 0); }
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) $$('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.disabled = false; }); });

    /* ---- confirmation dialogs: <form data-a-confirm="Message"> or <button data-a-confirm> ---- */
    var dialog = document.getElementById('aConfirm');
    var pending = null;
    function ask(message, title, okLabel, onOk) {
        if (!dialog || !window.bootstrap) { if (window.confirm(message)) onOk(); return; }
        dialog.querySelector('[data-a-confirm-title]').textContent = title || 'Please confirm';
        dialog.querySelector('[data-a-confirm-text]').textContent = message;
        dialog.querySelector('[data-a-confirm-ok]').textContent = okLabel || 'Confirm';
        pending = onOk;
        window.bootstrap.Modal.getOrCreateInstance(dialog).show();
    }
    if (dialog) {
        dialog.querySelector('[data-a-confirm-ok]').addEventListener('click', function () {
            var fn = pending; pending = null;
            window.bootstrap.Modal.getOrCreateInstance(dialog).hide();
            if (fn) fn();
        });
    }
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute && form.getAttribute('data-a-confirm');
        if (!msg || form.dataset.aConfirmed === '1') return;
        e.preventDefault();
        ask(msg, form.getAttribute('data-a-confirm-title'), form.getAttribute('data-a-confirm-ok'), function () { form.dataset.aConfirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); });
    }, true);
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-a-confirm], a[data-a-confirm]');
        if (!btn || btn.dataset.aConfirmed === '1') return;
        e.preventDefault();
        ask(btn.getAttribute('data-a-confirm'), btn.getAttribute('data-a-confirm-title'), btn.getAttribute('data-a-confirm-ok'), function () {
            btn.dataset.aConfirmed = '1';
            if (btn.tagName === 'A') window.location.href = btn.href; else btn.click();
        });
    }, true);


    /* ---- floating panels inside scrollable tables: menus and "+N more" popovers are positioned with fixed coordinates so the table wrapper never clips them ---- */
    function place(panel, anchor, alignRight) {
        panel.style.position = 'fixed'; panel.style.left = 'auto'; panel.style.right = 'auto'; panel.style.top = '0';
        var a = anchor.getBoundingClientRect(), w = panel.offsetWidth, h = panel.offsetHeight;
        var left = alignRight ? a.right - w : a.left;
        left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
        var top = a.bottom + 6; if (top + h > window.innerHeight - 8 && a.top - h - 6 > 8) top = a.top - h - 6;
        panel.style.left = left + 'px'; panel.style.top = top + 'px';
    }
    document.addEventListener('click', function (e) {
        var mt = e.target.closest('[data-a-menu-toggle]');
        if (mt) { var m = mt.closest('.a-menu'); var panel = m && m.querySelector('.a-menu__panel'); if (panel && m.classList.contains('is-open') && m.closest('.a-table-wrap')) place(panel, mt, true); }
        $$('details.a-more[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    });
    document.addEventListener('toggle', function (e) {
        var d = e.target; if (!d.matches || !d.matches('details.a-more') || !d.open) return;
        $$('details.a-more[open]').forEach(function (o) { if (o !== d) o.removeAttribute('open'); });
        var pop = d.querySelector('.a-more__pop'); if (pop && d.closest('.a-table-wrap')) place(pop, d.querySelector('summary'), false);
    }, true);
    function closeFloating() { $$('.a-menu.is-open').forEach(function (m) { if (m.closest('.a-table-wrap')) m.classList.remove('is-open'); }); $$('details.a-more[open]').forEach(function (d) { d.removeAttribute('open'); }); }
    window.addEventListener('scroll', closeFloating, true); window.addEventListener('resize', closeFloating);

    /* ---- toasts: window.aToast(message, 'success' | 'error' | 'info') ---- */
    window.aToast = function (message, type) {
        var root = document.getElementById('aToastRoot');
        if (!root) { root = document.createElement('div'); root.id = 'aToastRoot'; root.className = 'a-toast-root'; root.setAttribute('aria-live', 'polite'); document.body.appendChild(root); }
        var t = document.createElement('div'); t.className = 'a-toast a-toast--' + (type || 'info'); t.setAttribute('role', 'status'); t.textContent = message;
        root.appendChild(t); setTimeout(function () { t.classList.add('is-out'); setTimeout(function () { t.remove(); }, 250); }, 4200);
    };

    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
})();
