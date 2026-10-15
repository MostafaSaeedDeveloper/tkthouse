/* TKT House V2 — shared UI behaviour (header, tabs, lightbox, toast, image fallbacks) */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* Mobile navigation */
    var header = $('#tktHeader');
    var burger = $('#tktBurger');
    function setMenu(open) {
        if (!header || !burger) return;
        header.classList.toggle('is-open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (burger) {
        burger.addEventListener('click', function () { setMenu(!header.classList.contains('is-open')); });
    }
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) setMenu(false); });

    /* Account dropdown */
    var account = $('#tktAccount');
    if (account) {
        var toggle = $('.tkt-account__toggle', account);
        var setAccount = function (open) {
            account.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        toggle.addEventListener('click', function (e) { e.stopPropagation(); setAccount(!account.classList.contains('is-open')); });
        document.addEventListener('click', function (e) { if (!account.contains(e.target)) setAccount(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setAccount(false); });
    }

    /* Open the auth modal on the requested tab (login / register) */
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-auth-open]');
        if (!trigger) return;
        setMenu(false);
        var tab = $('[data-auth-tab="' + trigger.getAttribute('data-auth-open') + '"]');
        if (tab) tab.click();
    });

    /* Tabs */
    $$('[data-tkt-tabs]').forEach(function (root) {
        var tabs = $$('[role="tab"]', root);
        function activate(tab, focus) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) panel.classList.toggle('is-active', on);
            });
            if (focus) tab.focus();
        }
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { activate(tab); });
            tab.addEventListener('keydown', function (e) {
                var next = null;
                if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
                if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
                if (next) { e.preventDefault(); activate(next, true); }
            });
        });
    });

    /* Toast */
    var toastTimer;
    window.tktToast = function (message) {
        var el = $('#tktToast');
        if (!el) return;
        el.textContent = message;
        el.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('is-visible'); }, 2600);
    };

    /* Lightbox for gallery images */
    var box;
    function closeBox() { if (box) box.classList.remove('is-open'); }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-tkt-lightbox]');
        if (link) {
            e.preventDefault();
            if (!box) {
                box = document.createElement('div');
                box.className = 'tkt-lightbox';
                box.innerHTML = '<button type="button" class="tkt-lightbox__close" aria-label="Close">&times;</button><img alt="">';
                box.addEventListener('click', function (ev) { if (ev.target !== box.querySelector('img')) closeBox(); });
                document.body.appendChild(box);
            }
            var img = box.querySelector('img');
            img.src = link.getAttribute('href');
            img.alt = link.getAttribute('data-alt') || '';
            box.classList.add('is-open');
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeBox(); });

    /* Loading state + double-submit guard for plain forms */
    $$('form[data-tkt-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = $('button[type="submit"]', form);
            if (!btn || btn.classList.contains('is-loading')) return;
            // Defer so the browser still submits the button's own value/state.
            setTimeout(function () { btn.classList.add('is-loading'); }, 0);
        });
    });

    /* Image fallback: show the branded placeholder when a cover fails to load */
    document.addEventListener('error', function (e) {
        var el = e.target;
        if (el && el.tagName === 'IMG' && el.classList.contains('tkt-img')) {
            var media = el.closest('.tkt-media');
            if (media) media.classList.add('is-empty');
        }
    }, true);
    $$('img.tkt-img').forEach(function (img) {
        if (img.complete && img.naturalWidth === 0) {
            var media = img.closest('.tkt-media');
            if (media) media.classList.add('is-empty');
        }
    });
})();
