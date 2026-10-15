/* TKT House V2 — checkout behaviour (details, review & payment, confirmation).
   Prices shown here always come from the server; this file only displays them. */
(function () {
    'use strict';

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var money = function (n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EGP';
    };
    var mobile = function () { return window.matchMedia('(max-width: 991px)').matches; };

    /* Sticky bottom bar: reserve space, and tuck it away while a field is focused so the on-screen keyboard
       never covers (or is covered by) the action button. */
    var bar = $('[data-bottombar]');
    if (bar) {
        document.body.classList.add('tkt-has-bottombar');
        var timer;
        document.addEventListener('focusin', function (e) {
            if (!mobile() || !/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName) || e.target.type === 'radio' || e.target.type === 'checkbox') return;
            clearTimeout(timer);
            bar.classList.add('is-hidden');
        });
        document.addEventListener('focusout', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { bar.classList.remove('is-hidden'); }, 150);
        });
    }

    /* Loading state + double-submit guard on checkout forms */
    var submitting = false;
    function setLoading(on) {
        $$('[data-loading-target]').forEach(function (btn) {
            btn.classList.toggle('is-loading', on);
            btn.disabled = on;
        });
    }
    $$('[data-checkout-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (submitting) { e.preventDefault(); return; }
            submitting = true;
            // Defer disabling so the submit still goes out with the clicked button's state.
            setTimeout(function () { setLoading(true); }, 0);
        });
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { submitting = false; setLoading(false); }
    });

    /* Jump to the first problem after a server-side validation error */
    var firstInvalid = $('.is-invalid, .tkt-field__error');
    if (firstInvalid) {
        var target = firstInvalid.matches('.tkt-field__error') ? (firstInvalid.previousElementSibling || firstInvalid) : firstInvalid;
        setTimeout(function () {
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            if (target.focus) { try { target.focus({ preventScroll: true }); } catch (e) {} }
        }, 150);
    }

    /* "Same as buyer" for the first attendee */
    var same = $('[data-same-as-buyer]');
    if (same) {
        var buyerName = $('[data-buyer-name]');
        var buyerEmail = $('[data-buyer-email]');
        var buyerPhone = $('[data-buyer-phone]');
        var attName = $('[data-att-name]');
        var attEmail = $('[data-att-email]');
        var attPhone = $('[data-att-phone]');

        var e164 = function (input) {
            // The phone widget keeps the full international number in a hidden input next to the visible one.
            var hidden = input && input.parentElement ? input.parentElement.querySelector('input[type="hidden"]') : null;
            return (hidden && hidden.value) || (input ? input.value : '');
        };
        var setPhone = function (input, value) {
            if (!input) return;
            if (input._itiInstance && value) { input._itiInstance.setNumber(value); } else { input.value = value; }
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };
        var copy = function () {
            if (!same.checked) return;
            if (attName && buyerName) attName.value = buyerName.value;
            if (attEmail && buyerEmail) attEmail.value = buyerEmail.value;
            setPhone(attPhone, e164(buyerPhone));
        };
        same.addEventListener('change', function () {
            copy();
            [attName, attEmail].forEach(function (el) { if (el) el.readOnly = same.checked; });
        });
        [buyerName, buyerEmail, buyerPhone].forEach(function (el) { if (el) el.addEventListener('input', copy); });
        if (buyerPhone) buyerPhone.addEventListener('countrychange', copy);
        if (same.checked) { [attName, attEmail].forEach(function (el) { if (el) el.readOnly = true; }); }
    }

    /* Promo code: ask the server, then display what it returned */
    var promoInput = $('#promoInput');
    if (promoInput) {
        var promoApply = $('#promoApply');
        var promoRemove = $('#promoRemove');
        var promoMsg = $('#promoMsg');
        var promoHidden = $('#promoHidden');
        var expected = $('#expectedTotal');
        var csrf = $('#placeOrderForm input[name="_token"]').value;

        var say = function (text, kind) {
            promoMsg.textContent = text || '';
            promoMsg.className = 'tkt-promo-msg' + (kind ? ' is-' + kind : '');
            promoInput.classList.toggle('is-invalid', kind === 'error');
        };

        var render = function (q) {
            $$('[data-sum-subtotal]').forEach(function (el) { el.textContent = money(q.subtotal); });
            $$('[data-sum-total]').forEach(function (el) { el.textContent = money(q.total); });
            $$('[data-sum-total-bar]').forEach(function (el) { el.textContent = money(q.total); });
            var feeBox = $('[data-sum-fees]');
            if (feeBox) {
                feeBox.innerHTML = '';
                q.fees.forEach(function (fee) {
                    var row = document.createElement('div');
                    row.className = 'tkt-ordersum__row tkt-ordersum__row--fee';
                    var a = document.createElement('span'); a.textContent = fee.name;
                    var b = document.createElement('span'); b.textContent = money(fee.amount);
                    row.appendChild(a); row.appendChild(b); feeBox.appendChild(row);
                });
            }
            var dRow = $('[data-sum-discount-row]');
            if (dRow) {
                dRow.hidden = !(q.discount > 0);
                var d = $('[data-sum-discount]'); if (d) d.textContent = '−' + money(q.discount);
                var c = $('[data-sum-promo-code]'); if (c) c.textContent = q.promo_code || '';
            }
            if (expected) expected.value = Number(q.total).toFixed(2);
            promoHidden.value = q.promo_code || '';
            promoRemove.hidden = !q.promo_code;
            $$('[data-pay-label]').forEach(function (btn) {
                if (/^Pay /.test(btn.textContent) && btn.textContent.trim() !== 'Pay now') btn.textContent = 'Pay ' + money(q.total);
            });
        };

        var call = function (body) {
            promoApply.classList.add('is-loading'); promoApply.disabled = true;
            return fetch(promoInput.getAttribute('data-promo-url'), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                credentials: 'same-origin'
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
              .catch(function () { return { ok: false, status: 0, data: { message: 'Could not reach the server. Please try again.' } }; })
              .finally(function () { promoApply.classList.remove('is-loading'); promoApply.disabled = false; });
        };

        var apply = function () {
            var code = promoInput.value.trim();
            if (!code) { say('Enter a promo code first.', 'error'); return; }
            say('Checking…', '');
            call({ promo_code: code }).then(function (res) {
                if (res.ok && res.data.ok) { render(res.data.quote); say(res.data.message, 'ok'); }
                else if (res.status === 409) { window.location.reload(); }
                else { say(res.data.message || 'Promo code is invalid.', 'error'); }
            });
        };

        promoApply.addEventListener('click', apply);
        promoInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
        promoRemove.addEventListener('click', function () {
            call({ remove: true }).then(function (res) {
                if (res.ok && res.data.ok) { render(res.data.quote); promoInput.value = ''; say('Promo code removed.', ''); }
            });
        });
    }

    /* Confirmation: re-check a pending payment a few times without a manual refresh */
    var auto = $('[data-autorefresh]');
    if (auto) {
        var key = 'tkt-refresh-' + location.pathname;
        var n = parseInt(sessionStorage.getItem(key) || '0', 10);
        if (n < 12) {
            setTimeout(function () { sessionStorage.setItem(key, String(n + 1)); location.reload(); }, parseInt(auto.getAttribute('data-autorefresh'), 10) || 12000);
        }
    } else {
        try { sessionStorage.removeItem('tkt-refresh-' + location.pathname); } catch (e) {}
    }
})();
