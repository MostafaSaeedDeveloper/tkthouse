/* TKT House — sequential checkout wizard (one step at a time on /checkout).
   Your details → Ticket 1 … Ticket N → Review & payment.
   Presentation only: every field stays in the same <form>, nothing is created while moving between steps, and the
   final Pay submit goes through the same endpoint and server-side rules as the one-page checkout (tkt-express.js).
   Without JavaScript the page degrades to the one-page layout. */
(function () {
    'use strict';

    var root = document.querySelector('[data-wz]');
    var form = document.getElementById('expressForm');
    if (!root || !form) return;

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var buyerEl = $('[data-wz-step="buyer"]', form);
    var attEl = $('[data-wz-step="attendees"]', form);
    var reviewEl = $('[data-wz-step="review"]', form);
    var cards = $$('[data-attendee]', attEl);
    var T = cards.length;
    if (!buyerEl || !attEl || !reviewEl || !T) return;

    var progress = $('[data-wz-progress]', form);
    var payButtons = $$('[data-wz-pay]');
    var otpBox = $('[data-wz-otp]', form);
    var signedIn = root.getAttribute('data-signed-in') === '1';
    var preflightEmail = (root.getAttribute('data-preflight-email') || '').toLowerCase();

    // step index: 0 = details, 1..T = tickets, T+1 = review & payment
    var REVIEW = T + 1;
    var cur = 0;
    var lastTicket = 1;
    var returnTo = -1;      // when editing from the review step, Continue goes straight back there
    var busy = false;
    var errored = {};

    form.noValidate = true; // our own per-step checks run first (hidden steps would block native validation silently)

    function hide(el, on) { el.hidden = on; el.classList.toggle('tkt-wz-hide', on); }
    function val(sel) { var el = $(sel, form); return el ? (el.value || '').trim() : ''; }
    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    /* ---------- per-step validation (client side convenience; the server stays authoritative) ---------- */
    function problemsIn(root0) {
        var bad = [];
        var seen = {};
        $$('input, select, textarea', root0).forEach(function (el) {
            if (el.type === 'hidden' || el.disabled || !el.required) { if (el.type === 'email' && !el.disabled && el.value.trim() && !emailRe.test(el.value.trim())) bad.push({ el: el, msg: 'Enter a valid email address.' }); return; }
            if (el.type === 'radio') {
                if (seen[el.name]) return;
                seen[el.name] = true;
                if (!$$('input[name="' + el.name.replace(/"/g, '\\"') + '"]', root0.closest('form') || document).some(function (r) { return r.checked; })) bad.push({ el: el, msg: 'Please choose one.' });
                return;
            }
            var v = (el.value || '').trim();
            if (v === '') { bad.push({ el: el, msg: 'This field is required.' }); return; }
            if (el.type === 'email' && !emailRe.test(v)) bad.push({ el: el, msg: 'Enter a valid email address.' });
        });
        return bad;
    }

    function stepRoot(step) { return step === 0 ? buyerEl : step === REVIEW ? $('#methodGroup', reviewEl) || reviewEl : cards[step - 1]; }

    function stepProblems(step) {
        if (step === REVIEW) {
            var radios = $$('input[name="payment_method"]', reviewEl);
            if (radios.length && !radios.some(function (r) { return r.checked; })) return [{ el: radios[0], msg: 'Please choose a payment method.' }];
            return [];
        }
        return problemsIn(stepRoot(step));
    }

    function buyerCleared() {
        return signedIn || (val('#buyer_email').toLowerCase() === preflightEmail && preflightEmail !== '');
    }

    function stepDone(step) {
        if (step === REVIEW) return false;
        if (step === 0) return stepProblems(0).length === 0 && buyerCleared();
        return stepProblems(step).length === 0;
    }

    function firstIncomplete() {
        for (var i = 0; i < REVIEW; i++) if (!stepDone(i)) return i;
        return REVIEW;
    }

    function clearMarks(el) {
        $$('.tkt-field__error[data-js]', el).forEach(function (e) { e.remove(); });
        $$('.is-invalid', el).forEach(function (e) { e.classList.remove('is-invalid'); });
    }

    function mark(bad) {
        bad.forEach(function (p) {
            var gender = p.el.closest('.tkt-gender');
            if (p.el.type === 'radio' && p.el.name === 'payment_method') { var g = $('#methodGroup'); if (g) g.classList.add('is-invalid'); }
            else if (gender) gender.classList.add('is-invalid'); else p.el.classList.add('is-invalid');
            var host = p.el.closest('.tkt-field') || (p.el.name === 'payment_method' ? $('#methodGroup') : null) || p.el.parentElement;
            if (host && !host.querySelector('.tkt-field__error[data-js]')) {
                var d = document.createElement('div');
                d.className = 'tkt-field__error';
                d.setAttribute('data-js', 'wz');
                d.textContent = p.msg;
                host.appendChild(d);
            }
        });
    }

    /* ---------- building the UI ---------- */
    var PHASES = ['Your details', 'Attendees', 'Payment'];
    function phaseOf(step) { return step === 0 ? 0 : step === REVIEW ? 2 : 1; }

    var phaseBtns = [];
    var sub;
    var fillBar;
    (function buildProgress() {
        progress.innerHTML = '<ol class="tkt-wz__phases"></ol><div class="tkt-wz__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"><i></i></div><p class="tkt-wz__sub" aria-live="polite"></p>';
        var ol = $('.tkt-wz__phases', progress);
        PHASES.forEach(function (name, k) {
            var li = document.createElement('li');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tkt-wz__phase';
            b.setAttribute('data-wz-phase', k);
            b.innerHTML = '<span class="tkt-wz__dot">' + (k + 1) + '</span><span class="tkt-wz__phase-name">' + name + '</span>';
            li.appendChild(b);
            ol.appendChild(li);
            phaseBtns.push(b);
        });
        sub = $('.tkt-wz__sub', progress);
        fillBar = $('.tkt-wz__bar i', progress);
    })();

    // navigation rows
    function addNav(host, html) {
        var nav = document.createElement('div');
        nav.className = 'tkt-wz__nav';
        nav.innerHTML = html;
        host.appendChild(nav);
        return nav;
    }
    var navBuyer = addNav(buyerEl, '<button type="button" class="tkt-btn tkt-btn--primary" data-wz-next>Continue</button>');
    cards.forEach(function (card, i) {
        var name = card.getAttribute('data-ticket-name') || 'Ticket';
        var couple = card.getAttribute('data-couple') === '1';
        var title = $('.tkt-attendee__title', card);
        if (title) {
            title.innerHTML = '';
            var n = document.createElement('span'); n.textContent = 'Ticket ' + (i + 1) + ' of ' + T; title.appendChild(n);
            var cat = document.createElement('span'); cat.className = 'tkt-text-muted'; cat.style.fontWeight = '500'; cat.textContent = '· ' + name; title.appendChild(cat);
            if (couple) {
                var badge = document.createElement('span');
                badge.className = 'tkt-badge tkt-badge--warning';
                badge.textContent = 'Couple pass · Person ' + card.getAttribute('data-person') + ' of ' + card.getAttribute('data-persons');
                title.appendChild(badge);
            }
        }
        addNav(card, '<button type="button" class="tkt-btn tkt-btn--outline" data-wz-back>Back</button><button type="button" class="tkt-btn tkt-btn--primary" data-wz-next></button>');
    });
    var navReview = addNav(reviewEl, '<button type="button" class="tkt-btn tkt-btn--outline" data-wz-back>Back</button>');
    navReview.classList.add('tkt-wz__nav--review');

    /* ---------- rendering ---------- */
    function render(opts) {
        opts = opts || {};
        var ph = phaseOf(cur);
        var done = [stepDone(0), true, false];
        for (var i = 1; i <= T; i++) if (!stepDone(i)) done[1] = false;
        phaseBtns.forEach(function (b, k) {
            b.classList.toggle('is-current', k === ph);
            b.classList.toggle('is-done', k !== ph && done[k]);
            b.classList.toggle('has-error', phaseHasError(k));
            var reach = k === 0 || (k === 1 && stepDone(0)) || (k === 2 && firstIncomplete() === REVIEW);
            b.disabled = !reach;
            if (k === ph) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
        });
        var pct = cur === 0 ? 8 : cur === REVIEW ? 100 : 8 + Math.round((cur / (T + 1)) * 84);
        fillBar.style.width = pct + '%';
        sub.textContent = cur === 0 ? 'Tell us who is booking' : cur === REVIEW ? 'Review and pay' : 'Ticket ' + cur + ' of ' + T;

        hide(buyerEl, cur !== 0);
        hide(attEl, !(cur >= 1 && cur <= T));
        hide(reviewEl, cur !== REVIEW);
        cards.forEach(function (c, i) { hide(c, i + 1 !== cur); c.classList.remove('is-entering'); });

        var panel = cur === 0 ? buyerEl : cur === REVIEW ? reviewEl : cards[cur - 1];
        if (!reduceMotion && !opts.silent) { panel.classList.remove('is-entering'); void panel.offsetWidth; panel.classList.add('is-entering'); }

        // labels
        $('[data-wz-next]', buyerEl).textContent = returnTo === REVIEW ? 'Save and back to review' : 'Continue';
        cards.forEach(function (c, i) {
            var back = $('[data-wz-back]', c), next = $('[data-wz-next]', c);
            back.hidden = false;
            next.textContent = returnTo === REVIEW ? 'Save and back to review' : (i === T - 1 ? 'Review & pay' : 'Next ticket');
        });

        // money-moving controls only exist on the final step
        payButtons.forEach(function (b) { b.classList.toggle('tkt-wz-hide', cur !== REVIEW); });
        document.body.classList.toggle('tkt-wz-on-review', cur === REVIEW);

        if (cur === REVIEW) renderRecap();
    }

    function phaseHasError(k) {
        return Object.keys(errored).some(function (s) { return errored[s] && phaseOf(parseInt(s, 10)) === k; });
    }

    function renderRecap() {
        var body = $('[data-wz-recap-body]', reviewEl);
        body.innerHTML = '';
        var list = document.createElement('ul');
        list.className = 'tkt-wz__list';
        function row(title, line, step) {
            var li = document.createElement('li');
            var t = document.createElement('div');
            var b = document.createElement('b'); b.textContent = title;
            var s = document.createElement('small'); s.textContent = line;
            t.appendChild(b); t.appendChild(s);
            var e = document.createElement('button');
            e.type = 'button'; e.className = 'tkt-btn tkt-btn--ghost tkt-wz__edit'; e.textContent = 'Edit'; e.setAttribute('data-wz-edit', step);
            li.appendChild(t); li.appendChild(e); list.appendChild(li);
        }
        var phone = val('#buyer_phone');
        row('Your details', [val('#buyer_name'), val('#buyer_email'), phone].filter(Boolean).join(' · '), 0);
        cards.forEach(function (c, i) {
            var name = ($('[data-att-name]', c) || {}).value || '';
            var email = ($('input[type=email]', c) || {}).value || '';
            var couple = c.getAttribute('data-couple') === '1' ? ' (couple pass)' : '';
            row('Ticket ' + (i + 1) + ' · ' + name.trim(), (c.getAttribute('data-ticket-name') || '') + couple + (email ? ' · ' + email.trim() : ''), i + 1);
        });
        body.appendChild(list);
    }

    function scrollTop() {
        var r = root.getBoundingClientRect();
        var anchor = $('[data-wz-progress]', form) || root;
        var rr = anchor.getBoundingClientRect();
        if (rr.top < 70 || rr.top > window.innerHeight * 0.5) anchor.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    function show(step, opts) {
        opts = opts || {};
        cur = Math.max(0, Math.min(REVIEW, step));
        if (cur >= 1 && cur <= T) lastTicket = cur;
        render(opts);
        if (!opts.silent) {
            scrollTop();
            if (!opts.noFocus) {
                var panel = cur === 0 ? buyerEl : cards[cur - 1];
                if (cur !== REVIEW) {
                    var f = $$('input', panel).filter(function (el) { return el.type !== 'hidden' && el.type !== 'radio' && !el.readOnly && !el.closest('[hidden]'); })[0];
                    if (f && (!f.value || cur === 0)) { try { f.focus({ preventScroll: true }); } catch (e) {} }
                }
            }
            saveDraft();
        }
    }

    /* ---------- moving ---------- */
    function validateStep(step) {
        var bad = stepProblems(step);
        var host = step === REVIEW ? reviewEl : stepRoot(step);
        clearMarks(host);
        if (!bad.length) { errored[step] = false; return true; }
        errored[step] = true;
        mark(bad);
        var el = bad[0].el;
        if (el.type !== 'radio' && el.focus) { try { el.focus({ preventScroll: false }); } catch (e) {} }
        else { var g = el.closest('.tkt-gender') || $('#methodGroup'); if (g) g.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        render({ silent: true });
        return false;
    }

    function afterStep(step) {
        // where Continue goes from a step that just validated
        if (returnTo === REVIEW && firstIncomplete() === REVIEW) { returnTo = -1; return show(REVIEW); }
        var nxt = step + 1;
        if (returnTo === REVIEW) { returnTo = -1; }
        show(Math.min(nxt, REVIEW));
    }

    function next() {
        if (busy) return;
        if (!validateStep(cur)) return;
        errored[cur] = false;
        if (cur === 0 && !buyerCleared()) {
            busy = true;
            setBusy(true);
            window.TktExpress.preflight().then(function (r) {
                busy = false; setBusy(false);
                if (r === 'ok') { preflightEmail = val('#buyer_email').toLowerCase(); hideOtp(); afterStep(0); }
                else if (r === 'verify') { showOtp(); }
                // 'error': field errors were shown and revealErrors() already opened the right step
            });
            return;
        }
        afterStep(cur);
    }

    function back() {
        if (busy) return;
        returnTo = -1;
        if (cur > 0) show(cur - 1);
    }

    function setBusy(on) {
        var btn = $('[data-wz-next]', buyerEl);
        btn.disabled = on; btn.classList.toggle('is-loading', on);
    }

    /* ---------- inline e-mail code box (step 1) ---------- */
    function showOtp() {
        if (!otpBox) return;
        otpBox.hidden = false;
        navBuyerHide(true);
        var panel = $('[data-otp-panel]', otpBox);
        var email = val('#buyer_email');
        var o = window.TktOtp.init(panel);
        o.setEmail(email, window.TktExpress.lastMasked || email);
        o.send();
        otpBox.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
        setTimeout(function () { var c = $('[data-otp-code]', panel); if (c) c.focus({ preventScroll: true }); }, 250);
    }
    function hideOtp() { if (otpBox) { otpBox.hidden = true; navBuyerHide(false); } }
    function navBuyerHide(on) { navBuyer.hidden = on; }

    /* ---------- events ---------- */
    form.addEventListener('click', function (e) {
        var t = e.target.closest('[data-wz-next],[data-wz-back],[data-wz-edit]');
        if (!t) return;
        e.preventDefault();
        if (t.hasAttribute('data-wz-next')) next();
        else if (t.hasAttribute('data-wz-back')) back();
        else { returnTo = REVIEW; show(parseInt(t.getAttribute('data-wz-edit'), 10)); }
    });

    progress.addEventListener('click', function (e) {
        var b = e.target.closest('[data-wz-phase]');
        if (!b || b.disabled) return;
        var k = parseInt(b.getAttribute('data-wz-phase'), 10);
        var fromReview = cur === REVIEW;
        returnTo = -1;
        if (k === 0) show(0);
        else if (k === 1) show(lastTicket);
        else if (firstIncomplete() === REVIEW) show(REVIEW);
        if (fromReview && k !== 2) returnTo = REVIEW;
    });

    // Typing clears that field's mark.
    form.addEventListener('input', function (e) {
        var el = e.target;
        var holder = el.closest && (el.closest('.tkt-gender') || el);
        if (holder && holder.classList) holder.classList.remove('is-invalid');
        var host = el.closest && (el.closest('.tkt-field') || el.parentElement);
        if (host) { var err = host.querySelector('.tkt-field__error[data-js]'); if (err) err.remove(); }
        scheduleDraft();
    });
    form.addEventListener('change', function (e) {
        var g = e.target.closest && e.target.closest('.tkt-gender');
        if (g) { g.classList.remove('is-invalid'); var err = g.parentElement.querySelector('.tkt-field__error[data-js]'); if (err) err.remove(); }
        var m = e.target.closest && e.target.closest('#methodGroup');
        if (m) { m.classList.remove('is-invalid'); var er = m.querySelector('.tkt-field__error[data-js]'); if (er) er.remove(); }
        scheduleDraft();
    });

    // Enter never sends the payment request: it means "continue" inside a step (promo box applies the code).
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.isComposing) return;
        var t = e.target;
        if (!t || t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.type === 'submit') return;
        e.preventDefault();
        if (t.id === 'promoInput') { var a = $('#promoApply'); if (a) a.click(); return; }
        if (t.closest('[data-wz-otp]')) { var v = $('[data-otp-verify]', otpBox); if (v) v.click(); return; }
        if (cur !== REVIEW) next();
    });

    var mt = $('[data-wz-msum-toggle]');
    if (mt) mt.addEventListener('click', function () {
        var body = $('[data-wz-msum-body]');
        var open = body.hidden;
        body.hidden = !open;
        mt.setAttribute('aria-expanded', open ? 'true' : 'false');
        mt.classList.toggle('is-open', open);
    });

    var timer = null;
    function scheduleDraft() { clearTimeout(timer); timer = setTimeout(saveDraft, 1200); }
    function saveDraft() { clearTimeout(timer); if (window.TktExpress && window.TktExpress.saveDraft) window.TktExpress.saveDraft(); }

    /* ---------- API for tkt-express.js ---------- */
    function stepOfInput(el) {
        var c = el.closest && el.closest('[data-attendee]');
        if (c) return cards.indexOf(c) + 1;
        if (el.closest && el.closest('[data-wz-step="review"]')) return REVIEW;
        return 0;
    }

    window.TktWizard = {
        active: true,

        /** Pay gate: every step must be valid; otherwise the first incomplete one opens with its errors shown. */
        ensureComplete: function () {
            for (var s = 0; s < REVIEW + 1; s++) {
                var bad = stepProblems(s);
                if (bad.length) {
                    errored[s] = true;
                    returnTo = -1;
                    show(s, { noFocus: true });
                    clearMarks(s === REVIEW ? reviewEl : stepRoot(s));
                    mark(bad);
                    var el = bad[0].el;
                    if (el.type !== 'radio' && el.focus) { try { el.focus({ preventScroll: true }); } catch (x) {} }
                    return { ok: false, step: s, label: s === 0 ? 'your details' : s === REVIEW ? 'the payment method' : 'ticket ' + s };
                }
                errored[s] = false;
            }
            return { ok: true };
        },

        /** Server-side errors: open the earliest step that has one and flag every affected step. */
        revealErrors: function (inputs) {
            if (!inputs.length) return null;
            var hits = inputs.map(function (el) { return { el: el, s: stepOfInput(el) }; });
            hits.forEach(function (h) { errored[h.s] = true; });
            var first = hits.slice().sort(function (a, b) { return a.s - b.s; })[0];
            returnTo = -1;
            if (first.s === 0) showOtpHide();
            show(first.s, { noFocus: true });
            return first.el;
        },

        clearErrors: function () { errored = {}; render({ silent: true }); },

        /** The e-mail code was accepted. */
        verified: function () {
            signedIn = true;
            preflightEmail = val('#buyer_email').toLowerCase();
            hideOtp();
        },

        /** Continue the journey after a code accepted during step 1. */
        advanceFromDetails: function () { if (cur === 0) afterStep(0); },

        /** A pay-time 409 (e-mail needs a code): go back to step 1 and show the code box there. */
        needsCode: function () { returnTo = REVIEW; preflightEmail = ''; show(0, { noFocus: true }); showOtp(); },

        editEmail: function () { hideOtp(); show(0, { noFocus: true }); var f = $('#buyer_email'); if (f) { setTimeout(function () { f.focus(); f.select(); }, 150); } },

        step: function () { return cur; }
    };
    function showOtpHide() { /* keep the code box state as is */ }

    /* ---------- initial state: restored draft / server-rendered errors ---------- */
    var serverErr = $$('.tkt-field__error:not([data-js])', form).filter(function (el) { return el.textContent.trim() !== '' && !el.closest('[data-otp-panel]'); }).map(function (el) { return stepOfInput(el); });
    if (serverErr.length) {
        serverErr.forEach(function (s) { errored[s] = true; });
        show(Math.min.apply(null, serverErr), { silent: true });
    } else {
        show(firstIncomplete(), { silent: true });
    }
})();
