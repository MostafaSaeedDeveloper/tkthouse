/* TKT House V2 — ticket selection on the event page.
   Produces the same checkout hand-off as before:
   /checkout?event={id}&cart={base64url([{ticket_id, qty}])} */
(function () {
    'use strict';

    var root = document.getElementById('tktBooking');
    if (!root) return;

    var checkoutBtn = document.getElementById('checkoutBtn');
    var summary = document.getElementById('orderSummary');
    var linesEl = document.getElementById('summaryLines');
    var totalEl = document.getElementById('summaryTotal');
    var toggleEl = document.getElementById('summaryToggle');
    var countEl = document.getElementById('summaryCount');
    var backdrop = document.getElementById('sheetBackdrop');
    var mobile = window.matchMedia('(max-width: 991px)');
    if (!checkoutBtn || !summary) return; // finished event: nothing to select

    var bookingClosed = root.dataset.bookingClosed === '1';
    var fees = [];
    try { fees = JSON.parse(root.dataset.fees || '[]'); } catch (e) { fees = []; }

    var cart = {}; // id -> { name, price, qty }
    var setters = {}; // id -> function(qty), used by Clear selection

    function money(n) {
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EGP';
    }

    function toast(msg) { if (window.tktToast) window.tktToast(msg); }

    // Same fee rule the checkout applies: percentage of subtotal, or a fixed amount per order.
    function feeAmount(fee, subtotal) {
        var amount = fee.type === 'percentage' ? Math.round(subtotal * fee.value) / 100 : fee.value;
        return Math.max(0, Math.round(amount * 100) / 100);
    }

    function line(label, value, extraClass) {
        var row = document.createElement('div');
        row.className = 'tkt-summary__line' + (extraClass ? ' ' + extraClass : '');
        var a = document.createElement('span');
        var b = document.createElement('span');
        a.textContent = label;
        b.textContent = value;
        row.appendChild(a);
        row.appendChild(b);
        return row;
    }

    function updateCheckoutLink(count) {
        var base = checkoutBtn.dataset.base || checkoutBtn.getAttribute('href');
        checkoutBtn.dataset.base = base;

        var enabled = !bookingClosed && count > 0;
        checkoutBtn.classList.toggle('is-disabled', !enabled);
        checkoutBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        checkoutBtn.tabIndex = enabled ? 0 : -1;

        if (!enabled) {
            checkoutBtn.setAttribute('href', root.dataset.checkoutBase || base);
            return;
        }

        var payload = Object.keys(cart).map(function (id) {
            return { ticket_id: parseInt(id.replace('ticket-', ''), 10), qty: cart[id].qty };
        }).filter(function (item) { return !isNaN(item.ticket_id); });

        var encoded = btoa(JSON.stringify(payload)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        checkoutBtn.setAttribute('href', (root.dataset.checkoutBase || base) + '?event=' + encodeURIComponent(root.dataset.eventId) + '&cart=' + encodeURIComponent(encoded));
    }

    function render() {
        var ids = Object.keys(cart);
        var subtotal = 0;
        var count = 0;
        linesEl.innerHTML = '';

        ids.forEach(function (id) {
            var item = cart[id];
            subtotal += item.price * item.qty;
            count += item.qty;
            linesEl.appendChild(line(item.name + ' × ' + item.qty, money(item.price * item.qty)));
        });

        var feeTotal = 0;
        if (ids.length) {
            if (fees.length) {
                linesEl.appendChild(line('Subtotal', money(subtotal)));
            }
            fees.forEach(function (fee) {
                var amount = feeAmount(fee, subtotal);
                if (amount > 0) {
                    feeTotal += amount;
                    linesEl.appendChild(line(fee.name || 'Fee', money(amount), 'tkt-summary__line--fee'));
                }
            });
        }

        totalEl.textContent = money(subtotal + feeTotal);
        if (countEl) countEl.textContent = count > 0 ? count + (count === 1 ? ' ticket' : ' tickets') : 'No tickets selected';
        summary.classList.toggle('has-items', ids.length > 0);
        document.body.classList.toggle('tkt-has-bar', ids.length > 0 && mobile.matches);
        if (!ids.length) { closeSheet(); if (typeof showClear === 'function') showClear(false); }
        updateCheckoutLink(count);
    }

    Array.prototype.forEach.call(root.querySelectorAll('.tkt-ticket'), function (card) {
        if (card.dataset.disabled === '1') return;

        var id = card.dataset.id;
        var isCouple = card.dataset.isCouple === '1';
        var maxQty = parseInt(card.dataset.maxQty || '10', 10);
        if (isNaN(maxQty) || maxQty < 1) maxQty = 10;
        var step = isCouple ? 2 : 1;

        var minus = card.querySelector('[data-step="-1"]');
        var plus = card.querySelector('[data-step="1"]');
        var out = card.querySelector('output');
        if (!minus || !plus || !out) return;

        function set(qty) {
            setters[id] = set;
            qty = Math.max(0, Math.min(maxQty, qty));
            if (qty === 0) {
                delete cart[id];
            } else {
                cart[id] = { name: card.dataset.ticket, price: parseFloat(card.dataset.price), qty: qty };
            }
            out.textContent = String(qty);
            minus.disabled = qty === 0;
            plus.disabled = qty >= maxQty;
            card.classList.toggle('is-selected', qty > 0);
            render();
            applyFold();
        }

        minus.addEventListener('click', function () {
            set((cart[id] ? cart[id].qty : 0) - step);
        });
        plus.addEventListener('click', function () {
            var current = cart[id] ? cart[id].qty : 0;
            if (current >= maxQty) {
                toast('Maximum for this ticket is ' + maxQty + ' per order.');
                return;
            }
            if (isCouple) toast(card.dataset.ticket + ' is for couples only (2 tickets).');
            set(current + step);
        });
    });

    /* ---- order details sheet (mobile bottom bar) ---- */
    function openSheet() {
        if (!mobile.matches || !summary.classList.contains('has-items')) return;
        summary.classList.add('is-expanded');
        if (toggleEl) toggleEl.setAttribute('aria-expanded', 'true');
        if (backdrop) backdrop.hidden = false;
        sheetOpener = document.activeElement;
        var closeBtn = summary.querySelector('[data-sheet-close]');
        if (closeBtn) closeBtn.focus();
    }
    var sheetOpener = null;
    function closeSheet() {
        var wasOpen = summary.classList.contains('is-expanded');
        summary.classList.remove('is-expanded');
        if (toggleEl) toggleEl.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.hidden = true;
        if (wasOpen && sheetOpener && document.contains(sheetOpener)) { try { sheetOpener.focus(); } catch (e) {} }
        if (wasOpen) sheetOpener = null;
    }
    if (toggleEl) toggleEl.addEventListener('click', function () { summary.classList.contains('is-expanded') ? closeSheet() : openSheet(); });
    if (backdrop) backdrop.addEventListener('click', closeSheet);
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !summary.classList.contains('is-expanded')) return;
        var f = Array.prototype.slice.call(summary.querySelectorAll('button, a[href]:not(.is-disabled)')).filter(function (el) { return el.offsetParent !== null && !el.disabled; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    Array.prototype.forEach.call(summary.querySelectorAll('[data-sheet-close]'), function (b) { b.addEventListener('click', closeSheet); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && summary.classList.contains('is-expanded')) { closeSheet(); } });

    /* ---- mobile: show the first few tickets, reveal more step by step (selected tickets are never folded away) ---- */
    var cards = Array.prototype.slice.call(root.querySelectorAll('.tkt-ticket'));
    var moreBtn = root.querySelector('[data-more]');
    var lessBtn = root.querySelector('[data-less]');
    var initial = parseInt(root.dataset.foldInitial || '5', 10);
    var stepBy = parseInt(root.dataset.foldStep || '5', 10);
    var shown = initial;
    function applyFold() {
        var folded = 0;
        cards.forEach(function (card, i) {
            var hide = i >= shown && !card.classList.contains('is-selected');
            card.classList.toggle('is-folded', hide);
            if (hide) folded++;
        });
        if (moreBtn) {
            moreBtn.hidden = folded === 0;
            if (folded > 0) { var n = Math.min(stepBy, folded); moreBtn.textContent = 'Show ' + n + ' more ticket' + (n === 1 ? '' : 's') + ' (' + folded + ' left)'; }
        }
        if (lessBtn) lessBtn.hidden = !(shown > initial && cards.length > initial);
    }
    if (moreBtn) moreBtn.addEventListener('click', function () { shown += stepBy; applyFold(); });
    if (lessBtn) lessBtn.addEventListener('click', function () {
        shown = initial; applyFold();
        var sc = root.querySelector('[data-ticket-scroll]'); if (sc) sc.scrollTop = 0;
        if (moreBtn && !moreBtn.hidden) moreBtn.focus();
    });

    /* ---- clear selection (asks first) ---- */
    var clearOpen = summary.querySelector('[data-clear-open]');
    var clearBox = summary.querySelector('[data-clear-confirm]');
    function showClear(on) {
        if (!clearBox) return;
        clearBox.hidden = !on;
        if (clearOpen) clearOpen.hidden = on;
        if (on) { var y = clearBox.querySelector('[data-clear-yes]'); if (y) y.focus(); } else if (clearOpen && !clearOpen.hidden) clearOpen.focus();
    }
    if (clearOpen) clearOpen.addEventListener('click', function () { showClear(true); });
    var clearNo = summary.querySelector('[data-clear-no]');
    if (clearNo) clearNo.addEventListener('click', function () { showClear(false); });
    var clearYes = summary.querySelector('[data-clear-yes]');
    if (clearYes) clearYes.addEventListener('click', function () {
        Object.keys(cart).slice().forEach(function (id) { if (setters[id]) setters[id](0); });
        showClear(false);
        closeSheet();
        toast('Selection cleared.');
    });

    /* ---- sold-out accordion ---- */
    var soldToggle = document.getElementById('soldoutToggle');
    var soldList = document.getElementById('soldoutList');
    if (soldToggle && soldList) soldToggle.addEventListener('click', function () {
        var open = soldList.hidden;
        soldList.hidden = !open;
        soldToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    var onBreakpoint = function () {
        document.body.classList.toggle('tkt-has-bar', Object.keys(cart).length > 0 && mobile.matches);
        if (!mobile.matches) closeSheet();
        applyFold();
    };
    if (mobile.addEventListener) mobile.addEventListener('change', onBreakpoint); else window.addEventListener('resize', onBreakpoint);

    render();
    applyFold();
})();
