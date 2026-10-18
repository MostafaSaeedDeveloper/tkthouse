/* TKT House V2 — soft navigation between the public pages (Swup).
   The server still renders every page in full (a hard load, refresh or crawler gets normal HTML); this only
   swaps <main id="tkt-main"> when the visitor moves between the pages listed in PUBLIC_PATHS and keeps the
   header, footer, auth modal and loaded scripts in place.

   Deliberately NOT handled here (they keep a normal full navigation): checkout, payment and its provider
   redirects/callbacks, OTP, login/register/logout and password reset, the customer account area, tickets,
   admin and scanner, every form submission, and anything that is not a plain same-origin link. */
(function () {
    'use strict';

    if (!window.Swup || window.TktSpa) return;

    var CONTAINER = '#tkt-main';

    // Pages that may be swapped in and out. A visit is soft only if BOTH the current page and the
    // destination are listed; anything else is a full page load.
    var PUBLIC_PATHS = [
        /^\/$/,
        /^\/events\/?$/,
        /^\/events\/[^\/]+\/?$/,
        /^\/about\/?$/,
        /^\/contact\/?$/,
        /^\/(terms-and-conditions|privacy-policy|cookie-policy)\/?$/
    ];

    function isPublicPath(pathname) {
        return PUBLIC_PATHS.some(function (re) { return re.test(pathname); });
    }

    function toUrl(href) {
        try { return new URL(href, window.location.href); } catch (e) { return null; }
    }

    function isSoftUrl(href) {
        var url = toUrl(href);
        return !!url && url.origin === window.location.origin && isPublicPath(url.pathname);
    }

    function metaToken(doc) {
        var el = doc.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    /* ---- feedback: a small non-blocking bar, never a full-screen loader ---- */
    var bar;
    function showError(message, retry) {
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'tkt-navbar-error';
            bar.setAttribute('role', 'alert');
            bar.innerHTML = '<span class="tkt-navbar-error__msg"></span><button type="button" class="tkt-navbar-error__retry">Try again</button><button type="button" class="tkt-navbar-error__close" aria-label="Dismiss">&times;</button>';
            bar.querySelector('.tkt-navbar-error__close').addEventListener('click', hideError);
            document.body.appendChild(bar);
        }
        bar.querySelector('.tkt-navbar-error__msg').textContent = message;
        var btn = bar.querySelector('.tkt-navbar-error__retry');
        btn.hidden = !retry;
        btn.onclick = function () { hideError(); if (retry) retry(); };
        bar.classList.add('is-visible');
    }
    function hideError() { if (bar) bar.classList.remove('is-visible'); }

    /* ---- a visit that is already running must not be started a second time ---- */
    var pending = null;
    function sameTarget(a, b) { return !!a && !!b && a.pathname === b.pathname && a.search === b.search; }

    var a11y = new window.SwupA11yPlugin({ headingSelector: 'h1, [role="heading"]' });
    // After a soft navigation, keyboard and screen-reader focus start at the top of the new page content.
    a11y.rootSelector = CONTAINER;

    var swup = new window.Swup({
        containers: [CONTAINER],
        cache: false,            // ticket availability and prices must always come from the server
        animationSelector: false, // no page transitions: the swap itself is the whole effect
        timeout: 20000,
        linkToSelf: 'scroll',
        plugins: [
            new window.SwupHeadPlugin(),
            a11y,
            new window.SwupScrollPlugin({
                animateScroll: { betweenPages: false, samePageWithHash: true, samePage: true },
                // The plugin's default adds `scrollend`/`wheel` listeners on every call and they pile up whenever the page is
                // already at the target position (no scroll, so no scrollend). Scroll directly instead.
                scrollFunction: function (el, top, left, animate, start, end) {
                    start();
                    el.scrollTo({ top: top, left: left, behavior: animate ? 'smooth' : 'instant' });
                    end();
                }
            }),
            // Only appears if a request takes longer than 300 ms.
            new window.SwupProgressPlugin({ className: 'tkt-progress', delay: 300 })
        ],
        ignoreVisit: function (href, ctx) {
            var el = ctx && ctx.el;
            var event = ctx && ctx.event;
            var target = toUrl(href);

            if (!target || !isSoftUrl(href) || !isPublicPath(window.location.pathname)) return true;
            if (el && el.closest('[data-no-swup], [data-toggle], [data-auth-open], [data-express-checkout], [download]')) return true;

            if (navigator.onLine === false) {
                if (event) event.preventDefault();
                showError('You appear to be offline. Check your connection and try again.', function () { swup.navigate(target.pathname + target.search + target.hash); });
                return true;
            }

            // Second click on a link whose page is still loading: ignore it instead of aborting and re-requesting.
            if (pending && sameTarget(pending, target)) {
                if (event) event.preventDefault();
                return true;
            }
            return false;
        }
    });

    /* ---- header chrome that depends on the current page (comes from the new document, so the server stays the source of truth) ---- */
    function syncChrome(doc) {
        [['.tkt-nav a', '.tkt-nav a'], ['.tkt-mobile-panel nav a', '.tkt-mobile-panel nav a']].forEach(function (pair) {
            var next = doc.querySelectorAll(pair[1]);
            document.querySelectorAll(pair[0]).forEach(function (link, i) {
                var from = next[i];
                if (!from) return;
                link.className = from.className;
                if (from.hasAttribute('aria-current')) link.setAttribute('aria-current', from.getAttribute('aria-current'));
                else link.removeAttribute('aria-current');
            });
        });
        var nextSearch = doc.querySelector('.tkt-header__bar .tkt-header__search input[name="q"]');
        var search = document.querySelector('.tkt-header__bar .tkt-header__search input[name="q"]');
        if (search && nextSearch && document.activeElement !== search) search.value = nextSearch.value;
    }

    swup.hooks.on('visit:start', function (visit) {
        pending = toUrl(visit.to.url);
        hideError();
        document.dispatchEvent(new CustomEvent('tkt:navigate'));
    });
    swup.hooks.on('visit:end', function () { pending = null; });
    swup.hooks.on('visit:abort', function () { pending = null; });

    // The fetched page must really be a swappable public page; anything else (maintenance page, error page
    // with another layout, redirect to checkout/login, a changed session...) throws, and Swup then falls back to a
    // normal full page load. Hooks added with `on`/`before` swallow errors, so this one *replaces* the default
    // page:load handler and validates what it returns.
    swup.hooks.replace('page:load', function (visit, args, defaultHandler) {
        return defaultHandler(visit, args).then(function (page) {
            var doc = page && new DOMParser().parseFromString(page.html, 'text/html');
            var finalUrl = page && toUrl(page.url);
            if (!doc || !finalUrl || !isPublicPath(finalUrl.pathname) || !doc.querySelector(CONTAINER) ||
                doc.body.getAttribute('data-layout') !== document.body.getAttribute('data-layout')) {
                throw new Error('Not a soft-navigable page: ' + (page && page.url));
            }
            // The header and the auth modal (with its CSRF fields) stay in place, so a changed session means they are stale:
            // signed in/out elsewhere, or the session expired and got a new CSRF token. Reload everything once.
            if (doc.body.getAttribute('data-authenticated') !== document.body.getAttribute('data-authenticated') ||
                metaToken(doc) !== metaToken(document)) {
                throw new Error('Session changed: full page load needed');
            }
            return page;
        });
    });

    swup.hooks.before('content:replace', function () { window.TktPage && window.TktPage.unmount(); });

    swup.hooks.on('content:replace', function (visit) {
        if (visit.to.document) syncChrome(visit.to.document);
        if (window.TktPage) window.TktPage.mount(document.querySelector(CONTAINER));
    });

    swup.hooks.on('page:view', function () {
        // Single-page-style analytics: the initial hit is sent by the page itself, soft navigations send their own.
        if (typeof window.gtag === 'function') {
            window.gtag('event', 'page_view', { page_title: document.title, page_location: window.location.href, page_path: window.location.pathname });
        }
        if (typeof window.fbq === 'function') window.fbq('track', 'PageView');
    });

    /* ---- search: only GET forms marked role="search" that point at a public page. Every other form (POST, payment,
       OTP, login, contact, logout...) is never touched and submits normally. ---- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form.matches || !form.matches('form[role="search"]')) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
        var action = toUrl(form.getAttribute('action') || window.location.href);
        if (!action || !isSoftUrl(action.href) || !isPublicPath(window.location.pathname) || navigator.onLine === false) return;

        var query = new URLSearchParams(new window.FormData(form)).toString();
        var target = toUrl(action.pathname + (query ? '?' + query : ''));
        e.preventDefault();
        if (pending && sameTarget(pending, target)) return;
        swup.navigate(target.pathname + target.search);
    });

    window.TktSpa = { swup: swup, isSoftUrl: isSoftUrl };
})();
