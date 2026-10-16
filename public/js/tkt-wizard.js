/* TKT House — attendee wizard for the express checkout.
   Shows ONE ticket's form at a time. Every field stays in the same <form>, so nothing is lost when moving between
   tickets, and no request is made while navigating (the order is only created by the final Pay submit).
   Without JavaScript (or with the feature flag off) the page is the original stacked list. */
(function () {
    'use strict';

    var section = document.querySelector('[data-attendee-wizard]');
    var form = document.getElementById('expressForm');
    if (!section || !form) return;

    var cards = Array.prototype.slice.call(section.querySelectorAll('[data-attendee]'));
    var total = cards.length;
    if (!total) return;

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var current = 0;
    var summaryOn = false;
    var fromSummary = false;
    var hint = $('[data-wiz-hint]');

    form.noValidate = true; // our own checks run first; hidden tickets would otherwise block the native ones silently
    section.classList.add('tkt-wiz-on');

    /* ---------- build the UI once ---------- */
    var progress = document.createElement('div');
    progress.className = 'tkt-wiz__progress';
    progress.innerHTML =
        '<div class="tkt-wiz__top"><b class="tkt-wiz__label" data-wiz-label aria-live="polite"></b><span class="tkt-wiz__count" data-wiz-count></span></div>' +
        '<div class="tkt-wiz__bar" role="progressbar" aria-valuemin="0" aria-valuemax="' + total + '"><i data-wiz-fill></i></div>' +
        '<ol class="tkt-wiz__chips" data-wiz-chips></ol>';
    var firstGroup = $('.tkt-ticket-group', section);
    section.insertBefore(progress, firstGroup);

    var label = $('[data-wiz-label]', progress);
    var count = $('[data-wiz-count]', progress);
    var fill = $('[data-wiz-fill]', progress);
    var bar = $('.tkt-wiz__bar', progress);
    var chipList = $('[data-wiz-chips]', progress);
    var chips = cards.map(function (card, i) {
        var li = document.createElement('li');
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'tkt-wiz__chip';
        b.textContent = String(i + 1);
        b.setAttribute('data-wiz-chip', i);
        li.appendChild(b);
        chipList.appendChild(li);
        return b;
    });

    var summary = document.createElement('div');
    summary.className = 'tkt-wiz__summary';
    summary.hidden = true;
    section.appendChild(summary);

    cards.forEach(function (card, i) {
        var name = card.getAttribute('data-ticket-name') || 'Ticket';
        var couple = card.getAttribute('data-couple') === '1';
        var title = $('.tkt-attendee__title', card);
        if (title) {
            title.innerHTML = '';
            var n = document.createElement('span');
            n.textContent = 'Ticket ' + (i + 1) + ' of ' + total;
            title.appendChild(n);
            var cat = document.createElement('span');
            cat.className = 'tkt-text-muted';
            cat.style.fontWeight = '500';
            cat.textContent = '· ' + name;
            title.appendChild(cat);
            if (couple) {
                var badge = document.createElement('span');
                badge.className = 'tkt-badge tkt-badge--warning';
                badge.textContent = 'Couple pass · Person ' + card.getAttribute('data-person') + ' of ' + card.getAttribute('data-persons');
                title.appendChild(badge);
            }
        }
        var nav = document.createElement('div');
        nav.className = 'tkt-wiz__nav';
        nav.innerHTML =
            '<button type="button" class="tkt-btn tkt-btn--outline" data-wiz-back>Back</button>' +
            '<button type="button" class="tkt-btn tkt-btn--primary" data-wiz-next></button>';
        card.appendChild(nav);
    });

    /* ---------- validation of one ticket ---------- */
    function fieldsOf(card) {
        return $$('input, select, textarea', card).filter(function (el) { return el.type !== 'hidden' && !el.disabled; });
    }

    function problemsIn(card) {
        var bad = [];
        var seenRadio = {};
        fieldsOf(card).forEach(function (el) {
            if (el.type === 'radio') {
                if (seenRadio[el.name]) return;
                seenRadio[el.name] = true;
                if (el.required && !$$('input[name="' + el.name.replace(/"/g, '\\"') + '"]', card).some(function (r) { return r.checked; })) bad.push({ el: el, msg: 'Please choose one.' });
                return;
            }
            var value = (el.value || '').trim();
            if (el.required && value === '') { bad.push({ el: el, msg: 'This field is required.' }); return; }
            if (el.type === 'email' && value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) bad.push({ el: el, msg: 'Enter a valid email address.' });
        });
        return bad;
    }

    function isComplete(i) { return problemsIn(cards[i]).length === 0; }

    function clearMarks(card) {
        $$('.tkt-field__error[data-js]', card).forEach(function (e) { e.remove(); });
        $$('.is-invalid', card).forEach(function (e) { e.classList.remove('is-invalid'); });
    }

    function showProblems(card, bad) {
        clearMarks(card);
        bad.forEach(function (p) {
            var gender = p.el.closest('.tkt-gender');
            if (gender) gender.classList.add('is-invalid'); else p.el.classList.add('is-invalid');
            var host = p.el.closest('.tkt-field') || p.el.parentElement;
            if (host && !host.querySelector('.tkt-field__error[data-js]')) {
                var d = document.createElement('div');
                d.className = 'tkt-field__error';
                d.setAttribute('data-js', 'wiz');
                d.textContent = p.msg;
                host.appendChild(d);
            }
        });
    }

    /* ---------- state + rendering ---------- */
    var done = cards.map(function (c, i) { return isComplete(i); });
    var errored = cards.map(function () { return false; });

    function firstIncomplete() {
        for (var i = 0; i < total; i++) if (!done[i]) return i;
        return -1;
    }

    function doneCount() { return done.filter(Boolean).length; }

    function renderChips() {
        var limit = firstIncomplete();
        chips.forEach(function (b, i) {
            var reachable = limit === -1 || i <= limit;
            b.classList.toggle('is-current', !summaryOn && i === current);
            b.classList.toggle('is-done', done[i] && !errored[i]);
            b.classList.toggle('has-error', errored[i]);
            b.disabled = !reachable;
            if (!summaryOn && i === current) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
            b.setAttribute('aria-label', 'Ticket ' + (i + 1) + (done[i] ? ' (complete)' : ''));
        });
    }

    function renderProgress() {
        var d = doneCount();
        if (summaryOn) {
            label.textContent = 'All ' + total + ' ticket' + (total > 1 ? 's' : '') + ' complete';
            count.textContent = '';
            fill.style.width = '100%';
            bar.setAttribute('aria-valuenow', total);
        } else {
            label.textContent = 'Ticket ' + (current + 1) + ' of ' + total;
            count.textContent = d + ' of ' + total + ' done';
            fill.style.width = Math.round(((current + (done[current] ? 1 : 0)) / total) * 100) + '%';
            bar.setAttribute('aria-valuenow', d);
        }
        if (hint) {
            hint.hidden = d === total;
            hint.textContent = d === total ? '' : 'Complete all ticket details to pay (' + d + ' of ' + total + ' done).';
        }
        renderChips();
    }

    function renderNav() {
        cards.forEach(function (card, i) {
            var back = $('[data-wiz-back]', card);
            var next = $('[data-wiz-next]', card);
            back.hidden = fromSummary || i === 0;
            next.textContent = fromSummary ? 'Save ticket' : (i === total - 1 ? 'Finish tickets' : 'Next ticket');
        });
    }

    function renderSummary() {
        summary.innerHTML = '';
        var ok = document.createElement('div');
        ok.className = 'tkt-wiz__ok';
        ok.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> Ticket details complete — choose a payment method below.';
        summary.appendChild(ok);
        var ul = document.createElement('ul');
        ul.className = 'tkt-wiz__list';
        cards.forEach(function (card, i) {
            var name = (($('[data-att-name]', card) || {}).value || '').trim();
            var email = (($('input[type=email]', card) || {}).value || '').trim();
            var li = document.createElement('li');
            var text = document.createElement('div');
            var strong = document.createElement('b');
            strong.textContent = 'Ticket ' + (i + 1) + ' · ' + name;
            var small = document.createElement('small');
            small.textContent = (card.getAttribute('data-ticket-name') || '') + (email ? ' · ' + email : '');
            text.appendChild(strong); text.appendChild(small);
            var edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'tkt-btn tkt-btn--ghost tkt-wiz__edit';
            edit.textContent = 'Edit';
            edit.setAttribute('data-wiz-edit', i);
            li.appendChild(text); li.appendChild(edit);
            ul.appendChild(li);
        });
        summary.appendChild(ul);
    }

    function scrollToTop() {
        var r = section.getBoundingClientRect();
        if (r.top < 70 || r.top > window.innerHeight * 0.6) section.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    function show(i, opts) {
        opts = opts || {};
        current = Math.max(0, Math.min(total - 1, i));
        summaryOn = false;
        summary.hidden = true;
        cards.forEach(function (card, k) {
            var on = k === current;
            card.hidden = !on;
            card.classList.remove('is-entering');
            if (on && !reduceMotion && !opts.silent) { void card.offsetWidth; card.classList.add('is-entering'); }
        });
        renderNav();
        renderProgress();
        if (!opts.silent) {
            scrollToTop();
            if (!opts.noFocus) {
                var f = fieldsOf(cards[current]).filter(function (el) { return !el.readOnly; })[0];
                if (f && f.type !== 'radio') { try { f.focus({ preventScroll: true }); } catch (e) {} }
            }
        }
    }

    function showSummary(opts) {
        opts = opts || {};
        summaryOn = true;
        fromSummary = false;
        cards.forEach(function (c) { c.hidden = true; });
        renderSummary();
        summary.hidden = false;
        renderNav();
        renderProgress();
        if (!opts.silent) scrollToTop();
    }

    function leaveCurrent() { // moving away never wipes data, it only re-checks whether the ticket is complete
        if (!summaryOn) done[current] = isComplete(current);
    }

    function next() {
        var bad = problemsIn(cards[current]);
        if (bad.length) {
            errored[current] = true;
            done[current] = false;
            showProblems(cards[current], bad);
            bad[0].el.focus && bad[0].el.type !== 'radio' && bad[0].el.focus();
            if (bad[0].el.type === 'radio') bad[0].el.closest('.tkt-gender').scrollIntoView({ block: 'center', behavior: 'smooth' });
            renderProgress();
            return;
        }
        clearMarks(cards[current]);
        errored[current] = false;
        done[current] = true;

        if (fromSummary) { fromSummary = false; return showSummary(); }
        var nextIncomplete = firstIncomplete();
        if (nextIncomplete === -1) return showSummary();
        if (current < total - 1) return show(current + 1);
        show(nextIncomplete); // last card reached but an earlier one is still incomplete
    }

    function back() {
        leaveCurrent();
        if (current > 0) show(current - 1);
    }

    /* ---------- events ---------- */
    section.addEventListener('click', function (e) {
        var t = e.target.closest('[data-wiz-next],[data-wiz-back],[data-wiz-chip],[data-wiz-edit]');
        if (!t) return;
        if (t.hasAttribute('data-wiz-next')) { e.preventDefault(); next(); }
        else if (t.hasAttribute('data-wiz-back')) { e.preventDefault(); back(); }
        else if (t.hasAttribute('data-wiz-chip')) {
            e.preventDefault();
            leaveCurrent();
            fromSummary = false;
            show(parseInt(t.getAttribute('data-wiz-chip'), 10));
        } else if (t.hasAttribute('data-wiz-edit')) {
            e.preventDefault();
            fromSummary = true;
            show(parseInt(t.getAttribute('data-wiz-edit'), 10));
        }
    });

    // Typing clears that field's mark; the ticket re-checks itself when left.
    section.addEventListener('input', function (e) {
        var el = e.target;
        var gender = el.closest && el.closest('.tkt-gender');
        (gender || el).classList && (gender || el).classList.remove('is-invalid');
        var host = el.closest && (el.closest('.tkt-field') || el.parentElement);
        if (host) { var err = host.querySelector('.tkt-field__error[data-js]'); if (err) err.remove(); }
    });
    section.addEventListener('change', function (e) {
        var gender = e.target.closest && e.target.closest('.tkt-gender');
        if (gender) { gender.classList.remove('is-invalid'); var err = gender.parentElement.querySelector('.tkt-field__error[data-js]'); if (err) err.remove(); }
    });

    // Enter must never send the payment request while filling tickets.
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.isComposing) return;
        var t = e.target;
        if (!t || t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.type === 'submit') return;
        e.preventDefault();
        var card = t.closest('[data-attendee]');
        if (card) { next(); return; }
        if (t.id === 'promoInput') { var apply = $('#promoApply'); if (apply) apply.click(); return; }
        var inputs = $$('input:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([disabled])', form).filter(function (el) { return el.offsetParent !== null; });
        var at = inputs.indexOf(t);
        if (at > -1 && inputs[at + 1]) { var nx = inputs[at + 1]; if (nx.closest('[data-attendee]') && !nx.closest('[data-attendee]').hidden || !nx.closest('[data-attendee]')) nx.focus(); }
    });

    /* ---------- API used by the checkout script ---------- */
    window.TktWizard = {
        active: true,

        /** Pay gate: true when every ticket is complete, otherwise opens the first incomplete one and shows why. */
        ensureComplete: function () {
            leaveCurrent();
            for (var i = 0; i < total; i++) {
                var bad = problemsIn(cards[i]);
                if (bad.length) {
                    done[i] = false;
                    errored[i] = true;
                    fromSummary = false;
                    show(i, { noFocus: true });
                    showProblems(cards[i], bad);
                    var el = bad[0].el;
                    if (el.type !== 'radio' && el.focus) { try { el.focus({ preventScroll: true }); } catch (x) {} }
                    return { ok: false, ticket: i + 1 };
                }
                done[i] = true;
            }
            return { ok: true };
        },

        /** Server-side validation errors: open the first ticket that has one and mark every affected ticket. */
        revealErrors: function (inputs) {
            var hit = [];
            inputs.forEach(function (el) {
                var c = el.closest && el.closest('[data-attendee]');
                if (c) hit.push({ el: el, i: cards.indexOf(c) });
            });
            if (!hit.length) return null;
            hit.forEach(function (h) { errored[h.i] = true; done[h.i] = false; });
            var first = hit.slice().sort(function (a, b) { return a.i - b.i; })[0];
            fromSummary = false;
            show(first.i, { noFocus: true });
            return first.el;
        },

        clearErrors: function () {
            for (var i = 0; i < total; i++) errored[i] = false;
            renderChips();
        }
    };

    /* ---------- initial state (restored draft / server-rendered errors) ---------- */
    var serverError = cards.findIndex(function (c) { return c.querySelector('.tkt-field__error:not([data-js])'); });
    if (serverError > -1) {
        cards.forEach(function (c, i) { if (c.querySelector('.tkt-field__error:not([data-js])')) { errored[i] = true; done[i] = false; } });
        show(serverError, { silent: true });
    } else if (firstIncomplete() === -1) {
        showSummary({ silent: true });
    } else {
        show(firstIncomplete(), { silent: true });
    }
})();
