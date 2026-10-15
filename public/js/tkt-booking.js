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
    if (!checkoutBtn || !summary) return; // finished event: nothing to select

    var bookingClosed = root.dataset.bookingClosed === '1';
    var fees = [];
    try { fees = JSON.parse(root.dataset.fees || '[]'); } catch (e) { fees = []; }

    var cart = {}; // id -> { name, price, qty }

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
        summary.classList.toggle('has-items', ids.length > 0);
        document.body.classList.toggle('tkt-has-bar', ids.length > 0 && window.matchMedia('(max-width: 991px)').matches);
        if (!ids.length) {
            summary.classList.remove('is-expanded');
            if (toggleEl) toggleEl.setAttribute('aria-expanded', 'false');
        }
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

    if (toggleEl) {
        toggleEl.addEventListener('click', function () {
            var open = summary.classList.toggle('is-expanded');
            toggleEl.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    window.addEventListener('resize', function () {
        document.body.classList.toggle('tkt-has-bar', Object.keys(cart).length > 0 && window.matchMedia('(max-width: 991px)').matches);
    });

    render();
})();
